<?php
function metodosPagoKortzen(): array
{
    return ['efectivo' => 'Efectivo', 'transferencia' => 'Transferencia', 'deuna' => 'DeUna',
        'tarjeta_debito' => 'Tarjeta de débito', 'tarjeta_credito' => 'Tarjeta de crédito'];
}

function validarMetodoPago($metodo): string
{
    if (!is_string($metodo) || !isset(metodosPagoKortzen()[$metodo])) {
        throw new InvalidArgumentException('Selecciona el método de pago recibido.');
    }
    return $metodo;
}

function esAdministradorCaja(array $usuario): bool
{
    return in_array($usuario['rol'] ?? '', ['admin', 'admin_local', 'administrador', 'superadmin'], true);
}

/** Importe en centavos: no se aceptan importes negativos, infinitos ni desbordamientos. */
function centavosCaja($importe): int
{
    if (!is_numeric($importe) || !is_finite((float) $importe) || $importe < 0 || $importe > 99999999.99) {
        throw new InvalidArgumentException('El importe del cobro no es válido.');
    }
    return (int) round((float) $importe * 100);
}

/** Debe formar parte de la misma transacción que el servicio y el inventario. */
function registrarCobroCaja(PDO $pdo, array $datos): int
{
    if (!$pdo->inTransaction()) throw new LogicException('El cobro requiere una transacción.');
    $metodo = validarMetodoPago($datos['metodo_pago'] ?? null);
    $servicios = centavosCaja($datos['servicios'] ?? 0);
    $productos = centavosCaja($datos['productos'] ?? 0);
    $propina = centavosCaja($datos['propina'] ?? 0);
    $total = $servicios + $productos + $propina;
    if ($total > 9999999999) throw new InvalidArgumentException('El total supera el límite permitido.');
    $stmt = $pdo->prepare('INSERT INTO cobros_caja (cita_id, sucursal_id, registrado_por, metodo_pago,
        servicios, productos, propina, total) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
    $stmt->execute([$datos['cita_id'] ?? null, $datos['sucursal_id'], $datos['registrado_por'], $metodo,
        $servicios / 100.0, $productos / 100.0, $propina / 100.0, $total / 100.0]);
    return (int) $pdo->lastInsertId();
}

function rangoReporte(array $entrada): array
{
    $periodo = $entrada['periodo'] ?? 'diario';
    $hoy = new DateTimeImmutable('today');
    $inicio = $hoy;
    $fin = $hoy;
    if ($periodo === 'semanal') { $inicio = $hoy->modify('monday this week'); $fin = $inicio->modify('+6 days'); }
    if ($periodo === 'mensual') { $inicio = $hoy->modify('first day of this month'); $fin = $hoy->modify('last day of this month'); }
    foreach (['desde' => &$inicio, 'hasta' => &$fin] as $clave => &$fecha) {
        if (array_key_exists($clave, $entrada) && $entrada[$clave] !== '' && $entrada[$clave] !== null) {
            if (!is_string($entrada[$clave])) throw new InvalidArgumentException('Fecha no válida.');
            $valor = DateTimeImmutable::createFromFormat('!Y-m-d', $entrada[$clave]);
            if (!$valor || $valor->format('Y-m-d') !== $entrada[$clave]) throw new InvalidArgumentException('Fecha no válida.');
            $fecha = $valor;
        }
    }
    unset($fecha);
    if ($inicio > $fin || $inicio->diff($fin)->days > 366) {
        throw new InvalidArgumentException('Selecciona un rango válido de hasta 366 días.');
    }
    return [$inicio->format('Y-m-d H:i:s'), $fin->modify('+1 day')->format('Y-m-d H:i:s')];
}

/** El alcance se calcula en el servidor, nunca a partir de IDs autorizados por el navegador. */
function alcanceReporte(array $usuario, string $alias, array &$params, $sucursal = null): string
{
    $filtro = '';
    if (!in_array($usuario['rol'] ?? '', ['admin', 'administrador', 'superadmin'], true)) {
        $ids = getUsuarioSucursalesIds($usuario['id']);
        $ids = array_values(array_filter(array_map('intval', $ids)));
        $filtro = " AND $alias.sucursal_id IN (" . implode(',', array_fill(0, max(1, count($ids)), '?')) . ')';
        $params = array_merge($params, $ids ?: [0]);
    }
    if ($sucursal !== null && $sucursal !== '' && (int) $sucursal > 0) {
        $filtro .= " AND $alias.sucursal_id = ?";
        $params[] = (int) $sucursal;
    }
    return $filtro;
}

function vendedoresHistorial(PDO $pdo, array $usuario): array
{
    if (!esAdministradorCaja($usuario)) {
        return [['id' => (int) $usuario['id'], 'nombre' => $usuario['nombre'], 'activo' => 1]];
    }
    $equipo = $pdo->query('SELECT id, nombre, activo FROM usuarios ORDER BY nombre')->fetchAll(PDO::FETCH_ASSOC);
    // Consultas separadas evitan mezclar collations de tablas heredadas en un UNION.
    try {
        $anteriores = $pdo->query("SELECT v.usuario_id AS id,
            COALESCE(MAX(v.vendedor_nombre), 'Usuario eliminado') AS nombre, 0 AS activo
            FROM ventas_productos v LEFT JOIN usuarios u ON u.id = v.usuario_id
            WHERE u.id IS NULL GROUP BY v.usuario_id")->fetchAll(PDO::FETCH_ASSOC);
        $equipo = array_merge($equipo, $anteriores);
    } catch (Throwable $e) {
        error_log('Vendedores históricos no disponibles: ' . $e->getMessage());
    }
    usort($equipo, fn ($a, $b) => strcasecmp($a['nombre'], $b['nombre']));
    return $equipo;
}

function historialVentas(PDO $pdo, array $usuario, array $filtros, bool $exportar = false): array
{
    [$desde, $hasta] = rangoReporte($filtros);
    $params = [$desde, $hasta];
    $where = ' WHERE v.fecha >= ? AND v.fecha < ?' . alcanceReporte($usuario, 'v', $params, $filtros['sucursal_id'] ?? null);
    if (!esAdministradorCaja($usuario)) {
        $where .= ' AND v.usuario_id = ?';
        $params[] = (int) $usuario['id'];
    } elseif (!empty($filtros['vendedor_id'])) {
        $where .= ' AND v.usuario_id = ?';
        $params[] = (int) $filtros['vendedor_id'];
    }
    $stmt = $pdo->prepare('SELECT COUNT(*) AS filas, COALESCE(SUM(v.cantidad * v.precio_unitario), 0) AS total FROM ventas_productos v' . $where);
    $stmt->execute($params);
    $resumen = $stmt->fetch(PDO::FETCH_ASSOC);
    $pagina = max(1, (int) ($filtros['pagina'] ?? 1));
    $paginas = max(1, (int) ceil($resumen['filas'] / 50));
    $pagina = min($pagina, $paginas);
    $sql = "SELECT v.id, v.fecha, COALESCE(i.producto, 'Producto eliminado') AS producto, v.cantidad,
        v.precio_unitario, v.cantidad * v.precio_unitario AS total,
        COALESCE(v.vendedor_nombre, u.nombre, 'Usuario eliminado') AS vendedor,
        COALESCE(s.nombre, 'Sucursal eliminada') AS sucursal,
        COALESCE(v.metodo_pago, c.metodo_pago, 'sin_especificar') AS metodo_pago
        FROM ventas_productos v LEFT JOIN inventario i ON i.id = v.producto_id
        LEFT JOIN usuarios u ON u.id = v.usuario_id LEFT JOIN sucursales s ON s.id = v.sucursal_id
        LEFT JOIN citas c ON c.id = v.cita_id" . $where . ' ORDER BY v.fecha DESC, v.id DESC';
    if (!$exportar) $sql .= ' LIMIT 50 OFFSET ' . (($pagina - 1) * 50);
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if ($exportar) {
        // El total descargado corresponde exactamente a las filas del SELECT exportado.
        $centavosExportados = 0;
        foreach ($filas as $lineaExportada) $centavosExportados += (int) round((float) $lineaExportada['total'] * 100);
        $resumen['total'] = $centavosExportados / 100.0;
        $resumen['filas'] = count($filas);
    }
    foreach ($filas as &$fila) $fila['metodo_pago_label'] = metodosPagoKortzen()[$fila['metodo_pago']] ?? 'Sin especificar';
    unset($fila);
    return ['filas' => $filas, 'total' => (float) $resumen['total'], 'registros' => (int) $resumen['filas'],
        'pagina' => $pagina, 'paginas' => $paginas, 'desde' => substr($desde, 0, 10), 'hasta' => date('Y-m-d', strtotime($hasta . ' -1 day'))];
}

function resumenCaja(PDO $pdo, array $usuario, array $filtros): array
{
    if (!esAdministradorCaja($usuario)) throw new InvalidArgumentException('Acceso reservado a administración.');
    [$desde, $hasta] = rangoReporte($filtros);
    // Los cobros nuevos son instantáneas. Los datos anteriores se incluyen sin inventar su método.
    $sql = "SELECT fecha_pago AS fecha, sucursal_id, metodo_pago, servicios, productos, propina, total,
        CASE WHEN cita_id IS NULL THEN 0 ELSE 1 END AS cortes FROM cobros_caja
        UNION ALL SELECT COALESCE(c.fecha_completada, c.fecha_hora), c.sucursal_id,
        COALESCE(c.metodo_pago, 'sin_especificar'), COALESCE(c.precio_final,0), 0, COALESCE(c.propina,0),
        COALESCE(c.precio_final,0) + COALESCE(c.propina,0), 1 FROM citas c
        WHERE c.estado = 'completada' AND NOT EXISTS (SELECT 1 FROM cobros_caja cc WHERE cc.cita_id = c.id)
        UNION ALL SELECT v.fecha, v.sucursal_id, COALESCE(v.metodo_pago, c.metodo_pago, 'sin_especificar'),
        0, v.cantidad * v.precio_unitario, 0, v.cantidad * v.precio_unitario, 0
        FROM ventas_productos v LEFT JOIN citas c ON c.id = v.cita_id WHERE v.cobro_id IS NULL";
    $params = [$desde, $hasta];
    $where = ' WHERE r.fecha >= ? AND r.fecha < ?' . alcanceReporte($usuario, 'r', $params, $filtros['sucursal_id'] ?? null);
    $stmt = $pdo->prepare('SELECT r.metodo_pago, COUNT(*) AS operaciones, SUM(r.cortes) AS cortes,
        SUM(r.servicios) AS servicios, SUM(r.productos) AS productos, SUM(r.propina) AS propinas, SUM(r.total) AS total
        FROM (' . $sql . ') r' . $where . ' GROUP BY r.metodo_pago ORDER BY r.metodo_pago');
    $stmt->execute($params);
    $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $totales = ['cortes' => 0, 'servicios' => 0, 'productos' => 0, 'propinas' => 0, 'total' => 0];
    foreach ($filas as &$fila) {
        $fila['nombre'] = metodosPagoKortzen()[$fila['metodo_pago']] ?? 'Sin especificar';
        foreach ($totales as $clave => &$valor) $valor = round($valor + (float) $fila[$clave], 2);
        unset($valor);
    }
    unset($fila);
    return ['metodos' => $filas, 'totales' => $totales, 'desde' => substr($desde,0,10), 'hasta' => date('Y-m-d', strtotime($hasta . ' -1 day'))];
}
