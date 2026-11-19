<?php
$resumenVentas = [];
$errorResumenVentas = false;
try {
    $inicioVentas = date('Y-m-01');
    $finVentas = date('Y-m-01', strtotime('first day of next month'));
    $paramsVentas = [$inicioVentas, $finVentas];
    $filtroVentas = '';
    if (!isAdminTecnico()) {
        $idsVentas = getUsuarioSucursalesIds($currentUser['id']);
        $filtroVentas = ' AND v.sucursal_id IN (' . implode(',', array_fill(0, max(1, count($idsVentas)), '?')) . ')';
        $paramsVentas = array_merge($paramsVentas, $idsVentas ?: [0]);
    }
    if (($currentUser['rol'] ?? '') === 'barbero') {
        $filtroVentas .= ' AND v.usuario_id = ?';
        $paramsVentas[] = (int) $currentUser['id'];
    }
    $resumenVentas = query("SELECT v.usuario_id, COALESCE(u.nombre, MAX(v.vendedor_nombre), 'Usuario eliminado') AS vendedor,
        SUM(v.cantidad) AS unidades, SUM(v.cantidad * v.precio_unitario) AS total,
        SUM(v.cantidad * v.precio_unitario * COALESCE(v.comision_porcentaje, CASE WHEN u.rol = 'barbero' OR u.rol IS NULL THEN COALESCE(u.comision_productos, 10) ELSE 0 END) / 100.0) AS comision
        FROM ventas_productos v LEFT JOIN usuarios u ON u.id = v.usuario_id
        WHERE v.fecha >= ? AND v.fecha < ? $filtroVentas
        GROUP BY v.usuario_id, u.nombre ORDER BY total DESC", $paramsVentas);
} catch (Throwable $e) {
    $errorResumenVentas = true;
    error_log('Resumen de ventas por vendedor: ' . $e->getMessage());
}
?>
<div style="margin-top: 24px; padding: 16px; border: 1px solid #C0A062; border-radius: 10px; background: #FFF; color: #111;">
    <h2 style="font-size: 1.05rem; margin-bottom: 12px;">Ventas por vendedor · <?php echo date('m/Y'); ?></h2>
    <?php if ($errorResumenVentas): ?>
        <p>No se pudo cargar el resumen de ventas.</p>
    <?php elseif (!$resumenVentas): ?>
        <p>Aún no hay ventas registradas este mes.</p>
    <?php else: ?>
        <div style="overflow-x: auto;">
            <table style="width: 100%; text-align: left; border-collapse: collapse;">
                <thead><tr><th>Vendedor</th><th>Unidades</th><th>Ventas</th><th>Comisión</th></tr></thead>
                <tbody>
                <?php foreach ($resumenVentas as $filaVentas): ?>
                    <tr>
                        <td style="padding: 10px 6px;"><?php echo htmlspecialchars($filaVentas['vendedor'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo (int) $filaVentas['unidades']; ?></td>
                        <td>$<?php echo number_format((float) $filaVentas['total'], 2); ?></td>
                        <td>$<?php echo number_format((float) $filaVentas['comision'], 2); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
