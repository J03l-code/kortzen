<?php
if (!function_exists('registrarVentaProductos')) {
    require_once __DIR__ . '/ventas_productos.php';
}

if (!function_exists('asegurarTablasInsumosYComprasBarberos')) {
function asegurarTablasInsumosYComprasBarberos(PDO $pdo): void
{
    $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    if ($driver === 'sqlite') {
        $pdo->exec("CREATE TABLE IF NOT EXISTS insumos_internos (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            cita_id INTEGER NULL,
            producto_id INTEGER NOT NULL,
            cantidad INTEGER NOT NULL DEFAULT 1,
            costo_unitario DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            sucursal_id INTEGER NOT NULL,
            barbero_id INTEGER NULL,
            registrado_por INTEGER NOT NULL,
            notas TEXT NULL,
            fecha DATETIME DEFAULT CURRENT_TIMESTAMP
        )");
        $pdo->exec("CREATE TABLE IF NOT EXISTS compras_barberos (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            producto_id INTEGER NOT NULL,
            barbero_id INTEGER NOT NULL,
            barbero_nombre TEXT NOT NULL,
            cantidad INTEGER NOT NULL DEFAULT 1,
            precio_unitario DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            total DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            sucursal_id INTEGER NOT NULL,
            cita_id INTEGER NULL,
            registrado_por INTEGER NOT NULL,
            estado_rol TEXT NOT NULL DEFAULT 'pendiente_descuento',
            notas TEXT NULL,
            fecha DATETIME DEFAULT CURRENT_TIMESTAMP
        )");
    } else {
        $pdo->exec("CREATE TABLE IF NOT EXISTS insumos_internos (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            cita_id INT UNSIGNED NULL,
            producto_id INT UNSIGNED NOT NULL,
            cantidad INT NOT NULL DEFAULT 1,
            costo_unitario DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            sucursal_id INT UNSIGNED NOT NULL,
            barbero_id INT UNSIGNED NULL,
            registrado_por INT UNSIGNED NOT NULL,
            notas VARCHAR(255) NULL,
            fecha DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_ii_cita (cita_id),
            INDEX idx_ii_fecha (fecha),
            INDEX idx_ii_sucursal (sucursal_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $pdo->exec("CREATE TABLE IF NOT EXISTS compras_barberos (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            producto_id INT UNSIGNED NOT NULL,
            barbero_id INT UNSIGNED NOT NULL,
            barbero_nombre VARCHAR(120) NOT NULL,
            cantidad INT NOT NULL DEFAULT 1,
            precio_unitario DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            total DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            sucursal_id INT UNSIGNED NOT NULL,
            cita_id INT UNSIGNED NULL,
            registrado_por INT UNSIGNED NOT NULL,
            estado_rol ENUM('pendiente_descuento', 'descontado') NOT NULL DEFAULT 'pendiente_descuento',
            notas VARCHAR(255) NULL,
            fecha DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_cb_barbero (barbero_id),
            INDEX idx_cb_fecha (fecha),
            INDEX idx_cb_estado (estado_rol),
            INDEX idx_cb_sucursal (sucursal_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }
}
}

if (!function_exists('registrarInsumosInternos')) {
/**
 * Registra insumos/gastos internos usados durante un servicio.
 * Descuenta inventario sin cobrar al cliente ni sumar ingresos en caja.
 */
function registrarInsumosInternos(PDO $pdo, array $items, int $sucursalId, ?int $barberoId, int $operadorId, ?int $citaId = null): array
{
    if (!$items) {
        return ['items' => [], 'alertas_stock' => []];
    }
    $propia = !$pdo->inTransaction();
    if ($propia) {
        asegurarTablasInsumosYComprasBarberos($pdo);
        $pdo->beginTransaction();
    }
    try {
        $operador = getOperadorVenta($pdo, $operadorId);
        $cantidades = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                throw new InvalidArgumentException('La lista de insumos no es válida.');
            }
            $productoId = filter_var($item['producto_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            $cantidad = filter_var($item['cantidad'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if (!$productoId || !$cantidad) {
                throw new InvalidArgumentException('Selecciona insumos y cantidades enteras mayores a cero.');
            }
            $cantidades[$productoId] = ($cantidades[$productoId] ?? 0) + $cantidad;
        }
        ksort($cantidades);

        $stmtStock = $pdo->prepare('SELECT * FROM inventario WHERE id = ? FOR UPDATE');
        $stmtDescontar = $pdo->prepare('UPDATE inventario SET cantidad = cantidad - ? WHERE id = ? AND cantidad >= ?');
        $stmtIns = $pdo->prepare('INSERT INTO insumos_internos (cita_id, producto_id, cantidad, costo_unitario, sucursal_id, barbero_id, registrado_por, notas) VALUES (?, ?, ?, 0.00, ?, ?, ?, ?)');

        $itemsRegistrados = [];
        $alertasStock = [];
        foreach ($cantidades as $productoId => $cantidad) {
            $stmtStock->execute([$productoId]);
            $prod = $stmtStock->fetch(PDO::FETCH_ASSOC);
            if (!$prod) {
                throw new InvalidArgumentException('El insumo seleccionado no existe en inventario.');
            }
            $sucProd = (int) ($prod['sucursal_id'] ?? $sucursalId);
            validarSucursalVenta($pdo, $operador, $sucProd);
            if ($sucursalId > 0 && $sucProd !== $sucursalId) {
                throw new InvalidArgumentException('Los insumos deben pertenecer a la sucursal de la cita.');
            }
            if ($cantidad > (int) $prod['cantidad']) {
                throw new InvalidArgumentException('Stock insuficiente de insumo: ' . $prod['producto'] . '.');
            }
            $stmtDescontar->execute([$cantidad, $productoId, $cantidad]);
            if ($stmtDescontar->rowCount() !== 1) {
                throw new RuntimeException('El stock cambió. Actualiza e intenta nuevamente.');
            }
            $stmtIns->execute([$citaId, $productoId, $cantidad, $sucProd, $barberoId, $operadorId, 'Gasto interno en servicio']);
            $itemsRegistrados[] = [
                'id' => $productoId,
                'nombre' => $prod['producto'],
                'cantidad' => $cantidad,
            ];
            $restante = (int) $prod['cantidad'] - $cantidad;
            $minimo = (float) ($prod['stock_minimo'] ?? 5);
            if ($restante <= $minimo) {
                $alertasStock[] = ['producto_id' => $productoId, 'cantidad' => $restante, 'stock_minimo' => $minimo];
            }
        }
        if ($propia) {
            $pdo->commit();
        }
        return ['items' => $itemsRegistrados, 'alertas_stock' => $alertasStock];
    } catch (Throwable $e) {
        if ($propia && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}
}

if (!function_exists('registrarCompraBarbero')) {
/**
 * Registra la compra/retiro de un producto por un barbero o miembro del personal
 * con precio especial para descontar de su rol/liquidación.
 */
function registrarCompraBarbero(
    PDO $pdo,
    $productoId,
    $barberoId,
    $cantidad,
    $precioBarbero,
    int $operadorId,
    ?int $citaId = null,
    ?int $sucursalCitaId = null,
    string $notas = ''
): array {
    $productoId = filter_var($productoId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $barberoId = filter_var($barberoId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $cantidad = filter_var($cantidad, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if (!$productoId || !$barberoId || !$cantidad) {
        throw new InvalidArgumentException('Selecciona un producto, un barbero/personal válido y una cantidad mayor a cero.');
    }
    if ($precioBarbero === null || $precioBarbero === '' || !is_numeric($precioBarbero) || !is_finite((float) $precioBarbero) || (float) $precioBarbero < 0 || (float) $precioBarbero > 99999999.99) {
        throw new InvalidArgumentException('Ingresa un precio de barbero válido mayor o igual a cero.');
    }

    $propia = !$pdo->inTransaction();
    if ($propia) {
        asegurarTablasInsumosYComprasBarberos($pdo);
        $pdo->beginTransaction();
    }
    try {
        $operador = getOperadorVenta($pdo, $operadorId);
        $stmtBarb = $pdo->prepare('SELECT id, nombre, rol FROM usuarios WHERE id = ? AND activo = 1 FOR UPDATE');
        $stmtBarb->execute([$barberoId]);
        $barbero = $stmtBarb->fetch(PDO::FETCH_ASSOC);
        if (!$barbero) {
            throw new InvalidArgumentException('El barbero o miembro del personal seleccionado no existe o está inactivo.');
        }

        $stmtStock = $pdo->prepare('SELECT * FROM inventario WHERE id = ? FOR UPDATE');
        $stmtStock->execute([$productoId]);
        $prod = $stmtStock->fetch(PDO::FETCH_ASSOC);
        if (!$prod) {
            throw new InvalidArgumentException('El producto seleccionado no existe en el inventario.');
        }
        $sucProd = (int) ($prod['sucursal_id'] ?? $sucursalCitaId ?? $operador['sucursal_id'] ?? 0);
        if ($sucProd <= 0) {
            throw new InvalidArgumentException('El producto debe tener una sucursal asignada.');
        }
        validarSucursalVenta($pdo, $operador, $sucProd);
        if ($sucursalCitaId !== null && $sucProd !== $sucursalCitaId) {
            throw new InvalidArgumentException('El producto debe pertenecer a la sucursal de la cita.');
        }
        if ($cantidad > (int) $prod['cantidad']) {
            throw new InvalidArgumentException('Stock insuficiente de ' . $prod['producto'] . '.');
        }

        $stmtDescontar = $pdo->prepare('UPDATE inventario SET cantidad = cantidad - ? WHERE id = ? AND cantidad >= ?');
        $stmtDescontar->execute([$cantidad, $productoId, $cantidad]);
        if ($stmtDescontar->rowCount() !== 1) {
            throw new RuntimeException('El stock cambió. Actualiza el inventario e intenta nuevamente.');
        }

        $centavos = (int) round((float) $precioBarbero * 100);
        $precioUnitario = $centavos / 100.0;
        $total = ($cantidad * $centavos) / 100.0;

        $stmtIns = $pdo->prepare("INSERT INTO compras_barberos
            (producto_id, barbero_id, barbero_nombre, cantidad, precio_unitario, total, sucursal_id, cita_id, registrado_por, estado_rol, notas)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pendiente_descuento', ?)");
        $stmtIns->execute([
            $productoId,
            $barberoId,
            $barbero['nombre'],
            $cantidad,
            $precioUnitario,
            $total,
            $sucProd,
            $citaId,
            $operadorId,
            trim($notas) !== '' ? trim($notas) : 'Compra de personal para descontar en rol'
        ]);
        $compraId = (int) $pdo->lastInsertId();

        $alertasStock = [];
        $restante = (int) $prod['cantidad'] - $cantidad;
        $minimo = (float) ($prod['stock_minimo'] ?? 5);
        if ($restante <= $minimo) {
            $alertasStock[] = ['producto_id' => $productoId, 'cantidad' => $restante, 'stock_minimo' => $minimo];
        }

        if ($propia) {
            $pdo->commit();
        }
        return [
            'compra_id' => $compraId,
            'barbero_id' => $barberoId,
            'barbero_nombre' => $barbero['nombre'],
            'producto_id' => $productoId,
            'producto_nombre' => $prod['producto'],
            'cantidad' => $cantidad,
            'precio_unitario' => $precioUnitario,
            'total' => $total,
            'sucursal_id' => $sucProd,
            'alertas_stock' => $alertasStock
        ];
    } catch (Throwable $e) {
        if ($propia && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}
}

if (!function_exists('obtenerComprasBarberos')) {
function obtenerComprasBarberos(PDO $pdo, array $usuario, array $filtros = []): array
{
    asegurarTablasInsumosYComprasBarberos($pdo);
    [$desde, $hasta] = rangoReporte($filtros);
    $params = [$desde, $hasta];
    $where = ' WHERE cb.fecha >= ? AND cb.fecha < ?' . alcanceReporte($usuario, 'cb', $params, $filtros['sucursal_id'] ?? null);
    if (!esAdministradorCaja($usuario)) {
        $where .= ' AND cb.barbero_id = ?';
        $params[] = (int) $usuario['id'];
    } elseif (!empty($filtros['barbero_id'])) {
        $where .= ' AND cb.barbero_id = ?';
        $params[] = (int) $filtros['barbero_id'];
    }
    if (!empty($filtros['estado_rol']) && in_array($filtros['estado_rol'], ['pendiente_descuento', 'descontado'], true)) {
        $where .= ' AND cb.estado_rol = ?';
        $params[] = $filtros['estado_rol'];
    }

    $sql = "SELECT cb.id, cb.fecha, cb.barbero_id, COALESCE(cb.barbero_nombre, u.nombre, 'Personal') AS barbero,
        cb.producto_id, COALESCE(i.producto, 'Producto eliminado') AS producto,
        cb.cantidad, cb.precio_unitario, cb.total, cb.estado_rol, cb.notas, cb.cita_id,
        COALESCE(s.nombre, 'Sucursal') AS sucursal
        FROM compras_barberos cb
        LEFT JOIN inventario i ON i.id = cb.producto_id
        LEFT JOIN usuarios u ON u.id = cb.barbero_id
        LEFT JOIN sucursales s ON s.id = cb.sucursal_id" . $where . ' ORDER BY cb.fecha DESC, cb.id DESC';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $resumenPorBarbero = [];
    $totalPendiente = 0.0;
    $totalDescontado = 0.0;
    foreach ($filas as $f) {
        $bId = (int) $f['barbero_id'];
        if (!isset($resumenPorBarbero[$bId])) {
            $resumenPorBarbero[$bId] = [
                'barbero_id' => $bId,
                'barbero' => $f['barbero'],
                'pendiente' => 0.0,
                'descontado' => 0.0,
                'total' => 0.0,
                'compras' => 0
            ];
        }
        $monto = (float) $f['total'];
        $resumenPorBarbero[$bId]['compras']++;
        $resumenPorBarbero[$bId]['total'] = round($resumenPorBarbero[$bId]['total'] + $monto, 2);
        if ($f['estado_rol'] === 'pendiente_descuento') {
            $resumenPorBarbero[$bId]['pendiente'] = round($resumenPorBarbero[$bId]['pendiente'] + $monto, 2);
            $totalPendiente = round($totalPendiente + $monto, 2);
        } else {
            $resumenPorBarbero[$bId]['descontado'] = round($resumenPorBarbero[$bId]['descontado'] + $monto, 2);
            $totalDescontado = round($totalDescontado + $monto, 2);
        }
    }

    return [
        'filas' => $filas,
        'resumen_barberos' => array_values($resumenPorBarbero),
        'total_pendiente' => $totalPendiente,
        'total_descontado' => $totalDescontado,
        'total_general' => round($totalPendiente + $totalDescontado, 2),
        'desde' => substr($desde, 0, 10),
        'hasta' => date('Y-m-d', strtotime($hasta . ' -1 day'))
    ];
}
}
