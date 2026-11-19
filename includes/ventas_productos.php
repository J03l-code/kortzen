<?php
/** Ventas acreditadas al vendedor, independientemente de quien realiza el cobro. */
function getVendedoresVenta(PDO $pdo): array
{
    return $pdo->query('SELECT id, nombre, rol FROM usuarios WHERE activo = 1 ORDER BY nombre, id')->fetchAll(PDO::FETCH_ASSOC);
}

function validarSucursalVenta(PDO $pdo, array $operador, int $sucursalId): void
{
    if (in_array($operador['rol'], ['admin', 'superadmin', 'administrador'], true)) {
        return;
    }
    $stmt = $pdo->prepare('SELECT sucursal_id FROM usuarios_sucursales WHERE usuario_id = ?');
    $stmt->execute([$operador['id']]);
    $ids = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    if (!$ids && !empty($operador['sucursal_id'])) {
        $ids = [(int) $operador['sucursal_id']];
    }
    if ($sucursalId <= 0 || !in_array($sucursalId, $ids, true)) {
        throw new InvalidArgumentException('No tienes acceso al inventario de esta sucursal.');
    }
}

function getOperadorVenta(PDO $pdo, int $usuarioId): array
{
    $stmt = $pdo->prepare('SELECT id, nombre, rol, sucursal_id FROM usuarios WHERE id = ? AND activo = 1');
    $stmt->execute([$usuarioId]);
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$usuario) {
        throw new InvalidArgumentException('El usuario que registra la venta no está activo.');
    }
    return $usuario;
}

/**
 * Admite una transacción externa para que el cobro, los puntos y el stock sean atómicos.
 * usuario_id conserva el significado de beneficiario de la comisión en los reportes existentes.
 */
function registrarVentaProductos(PDO $pdo, array $items, $vendedorId, int $operadorId, ?int $citaId = null, ?int $sucursalCitaId = null): array
{
    $vendedorId = filter_var($vendedorId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if (!$vendedorId) {
        throw new InvalidArgumentException('Selecciona un vendedor para registrar la venta.');
    }
    if (!$items) {
        throw new InvalidArgumentException('Selecciona al menos un producto para vender.');
    }

    $propia = !$pdo->inTransaction();
    if ($propia) {
        $pdo->beginTransaction();
    }
    try {
        $operador = getOperadorVenta($pdo, $operadorId);
        $stmt = $pdo->prepare('SELECT id, nombre, rol, comision_productos FROM usuarios WHERE id = ? AND activo = 1 FOR UPDATE');
        $stmt->execute([$vendedorId]);
        $vendedor = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$vendedor) {
            throw new InvalidArgumentException('El vendedor seleccionado no existe o está inactivo.');
        }
        $porcentaje = max(0, min(100, (float) ($vendedor['comision_productos'] ?? 0)));

        // Bloquear cada producto una sola vez; conservar líneas con precios diferentes.
        $cantidades = [];
        $lineas = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                throw new InvalidArgumentException('La lista de productos no es válida.');
            }
            $productoId = filter_var($item['producto_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            $cantidad = filter_var($item['cantidad'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if (!$productoId || !$cantidad) {
                throw new InvalidArgumentException('Selecciona productos y cantidades enteras mayores a cero.');
            }
            $precio = $item['precio_unitario'] ?? null;
            $clavePrecio = 'catalogo';
            if ($precio !== null && $precio !== '') {
                if (!is_numeric($precio) || !is_finite((float) $precio) || (float) $precio < 0 || (float) $precio > 99999999.99) {
                    throw new InvalidArgumentException('El precio cobrado debe ser un importe válido mayor o igual a cero.');
                }
                $clavePrecio = number_format((float) $precio, 2, '.', '');
            }
            $cantidades[$productoId] = ($cantidades[$productoId] ?? 0) + $cantidad;
            $lineas[$productoId][$clavePrecio] = ($lineas[$productoId][$clavePrecio] ?? 0) + $cantidad;
        }
        ksort($cantidades);
        $stock = $pdo->prepare('SELECT * FROM inventario WHERE id = ? FOR UPDATE');
        $descontar = $pdo->prepare('UPDATE inventario SET cantidad = cantidad - ? WHERE id = ? AND cantidad >= ?');
        $insertar = $pdo->prepare('INSERT INTO ventas_productos
            (cita_id, producto_id, cantidad, precio_unitario, sucursal_id, usuario_id, registrado_por, vendedor_nombre, comision_porcentaje)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $totalCentavos = 0;
        $itemsRecibo = [];
        $alertasStock = [];
        foreach ($cantidades as $productoId => $cantidad) {
            $stock->execute([$productoId]);
            $producto = $stock->fetch(PDO::FETCH_ASSOC);
            if (!$producto) {
                throw new InvalidArgumentException('El producto seleccionado no existe.');
            }
            $sucursalId = (int) ($producto['sucursal_id'] ?? $sucursalCitaId ?? $operador['sucursal_id'] ?? 0);
            if ($sucursalId <= 0) {
                throw new InvalidArgumentException('El producto debe tener una sucursal asignada.');
            }
            validarSucursalVenta($pdo, $operador, $sucursalId);
            if ($sucursalCitaId !== null && $sucursalCitaId !== $sucursalId) {
                throw new InvalidArgumentException('Los productos deben pertenecer a la sucursal de la cita.');
            }
            if ($cantidad > (int) $producto['cantidad']) {
                throw new InvalidArgumentException('Stock insuficiente de ' . $producto['producto'] . '.');
            }
            $descontar->execute([$cantidad, $productoId, $cantidad]);
            if ($descontar->rowCount() !== 1) {
                throw new RuntimeException('El stock cambió. Actualiza el inventario e intenta nuevamente.');
            }
            foreach ($lineas[$productoId] as $clavePrecio => $cantidadLinea) {
                $precio = $clavePrecio === 'catalogo' ? $producto['precio'] : $clavePrecio;
                $centavos = (int) round((float) $precio * 100);
                $precio = $centavos / 100.0;
                $subtotalCentavos = $cantidadLinea * $centavos;
                $insertar->execute([$citaId, $productoId, $cantidadLinea, $precio, $sucursalId,
                    $vendedorId, $operadorId, $vendedor['nombre'], $porcentaje]);
                $totalCentavos += $subtotalCentavos;
                $itemsRecibo[] = ['id' => $productoId, 'nombre' => $producto['producto'], 'cantidad' => $cantidadLinea,
                    'precio_unitario' => $precio, 'total' => $subtotalCentavos / 100.0];
            }
            $restante = (int) $producto['cantidad'] - $cantidad;
            $minimo = (float) ($producto['stock_minimo'] ?? 5);
            if ($restante <= $minimo) {
                $alertasStock[] = ['producto_id' => $productoId, 'cantidad' => $restante, 'stock_minimo' => $minimo];
            }
        }
        if ($propia) {
            $pdo->commit();
        }
        return ['vendedor_id' => $vendedorId, 'vendedor_nombre' => $vendedor['nombre'],
            'barbero_id' => ($vendedor['rol'] === 'barbero' ? $vendedorId : null),
            'total' => $totalCentavos / 100.0, 'items' => $itemsRecibo, 'alertas_stock' => $alertasStock];
    } catch (Throwable $e) {
        if ($propia && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

/** Enviar avisos después del commit para no anunciar operaciones que se revierten. */
function notificarStockVentas(PDO $pdo, array $venta, ?int $barberoId = null): void
{
    if ($pdo->inTransaction()) return;
    foreach ($venta['alertas_stock'] ?? [] as $alerta) {
        try {
            require_once __DIR__ . '/webpush_helper.php';
            notificarStockBajo($pdo, $alerta['producto_id'], $alerta['cantidad'], $alerta['stock_minimo'],
                $barberoId ?? ($venta['barbero_id'] ?? null));
        } catch (Throwable $e) {
            error_log('No se pudo notificar el stock bajo: ' . $e->getMessage());
        }
    }
}
