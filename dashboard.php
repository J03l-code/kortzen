<?php
require_once 'config.php';
requireLogin();
$currentUser = getCurrentUser();

// Obtener estadísticas generales (solo si es Admin o Admin Local, mantenemos la lógica original)
// ... (código original de admins omitido/mantenido igual, nos enfocamos en mejorar la vista de BARBERO)

$pageTitle = 'Overview Completo';
include 'includes/header.php';
?>

<div class="page-header">
    <h1 class="page-title">Bienvenido, <?php echo htmlspecialchars($currentUser['nombre']); ?></h1>
    <p class="page-subtitle">Tu panel de control profesional</p>
</div>

<!-- Estilos Específicos para Dashboard Mejorado -->
<style>
    .dashboard-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 24px;
        margin-bottom: 32px;
    }

    .earnings-card {
        background: linear-gradient(135deg, #1A1A1A 0%, #252525 100%);
        border: 1px solid rgba(255, 215, 0, 0.1);
        padding: 24px;
        border-radius: 12px;
        position: relative;
        overflow: hidden;
    }

    .earnings-card::after {
        content: '$';
        position: absolute;
        right: -10px;
        bottom: -20px;
        font-size: 120px;
        color: rgba(255, 215, 0, 0.03);
        font-family: var(--font-heading);
        font-weight: 700;
        pointer-events: none;
    }

    .earnings-title {
        font-size: 12px;
        text-transform: uppercase;
        letter-spacing: 1px;
        color: var(--text-secondary);
        margin-bottom: 8px;
    }

    .earnings-amount {
        font-size: 32px;
        font-weight: 700;
        color: var(--primary-gold);
        font-family: var(--font-heading);
    }

    .trend-indicator {
        font-size: 12px;
        margin-top: 8px;
        display: flex;
        align-items: center;
        gap: 4px;
    }

    .trend-up {
        color: #2ECC71;
    }

    /* Next Up Widget */
    .next-client-card {
        background: var(--bg-sidebar);
        border-left: 4px solid var(--primary-gold);
        padding: 24px;
        border-radius: 8px;
        display: flex;
        align-items: center;
        gap: 20px;
        margin-bottom: 24px;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.2);
    }

    .next-time {
        text-align: center;
        min-width: 80px;
    }

    .next-hour {
        font-size: 28px;
        font-weight: 700;
        color: var(--text-primary);
        line-height: 1;
    }

    .next-label {
        font-size: 11px;
        text-transform: uppercase;
        color: var(--primary-gold);
        margin-top: 4px;
    }

    .client-details h3 {
        font-size: 18px;
        margin-bottom: 4px;
        color: var(--text-primary);
    }

    .service-badge {
        display: inline-block;
        padding: 4px 10px;
        background: rgba(201, 169, 110, 0.15);
        color: var(--primary-gold);
        border-radius: 100px;
        font-size: 11px;
        font-weight: 600;
        margin-top: 6px;
    }

    .action-buttons-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 12px;
        margin-bottom: 24px;
    }

    .quick-action-btn {
        background: rgba(255, 255, 255, 0.03);
        border: 1px dashed rgba(255, 255, 255, 0.1);
        padding: 16px;
        border-radius: 8px;
        text-align: center;
        cursor: pointer;
        transition: all 0.2s;
        color: var(--text-secondary);
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 8px;
    }

    .quick-action-btn:hover {
        background: rgba(255, 255, 255, 0.06);
        border-color: var(--primary-gold);
        color: var(--primary-gold);
    }

    /* Modal Styles override/addition */
    .history-item {
        padding: 12px;
        border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .history-date {
        font-size: 12px;
        color: var(--text-muted);
    }

    .history-service {
        font-weight: 500;
        color: var(--text-primary);
        font-size: 14px;
    }

    .history-price {
        color: var(--primary-gold);
        font-weight: 600;
    }
</style>

<?php if ($currentUser['rol'] === 'admin'): ?>
    <!-- VISTA ADMIN GLOBAL (Técnico) - DASHBOARD COMPLETO -->
    <?php
    $hoy = date('Y-m-d');
    $mesInicio = date('Y-m-01');
    $mesFin = date('Y-m-t');

    // 1. KPI GLOBAL: Citas y Ventas
    $citasStats = query("SELECT 
        COUNT(*) as total, 
        SUM(CASE WHEN estado = 'pendiente' THEN 1 ELSE 0 END) as pendientes,
        SUM(CASE WHEN estado = 'completada' THEN 1 ELSE 0 END) as completadas,
        SUM(CASE WHEN estado = 'completada' AND DATE(fecha_hora) = ? THEN precio_final ELSE 0 END) as venta_dia
    FROM citas WHERE DATE(fecha_hora) = ?", [$hoy, $hoy])[0];

    // Venta Mes Global
    $ventaMes = query("SELECT SUM(precio_final) as total FROM citas WHERE estado = 'completada' AND DATE(fecha_hora) BETWEEN ? AND ?", [$mesInicio, $mesFin])[0]['total'] ?? 0;

    // 2. Ranking Barberos Global (Mes)
    $topBarberos = query("SELECT u.nombre, s.nombre as sucursal, COUNT(c.id) as citas, SUM(c.precio_final) as total
                          FROM usuarios u
                          JOIN citas c ON u.id = c.barbero_id
                          LEFT JOIN sucursales s ON u.sucursal_id = s.id
                          WHERE c.estado = 'completada'
                          AND DATE(c.fecha_hora) BETWEEN ? AND ?
                          GROUP BY u.id
                          ORDER BY total DESC LIMIT 5", [$mesInicio, $mesFin]);

    // 3. Inventario Bajo Global
    $lowStock = query("SELECT i.producto, i.cantidad, i.stock_minimo, s.nombre as sucursal 
                       FROM inventario i
                       JOIN sucursales s ON i.sucursal_id = s.id
                       WHERE i.cantidad <= i.stock_minimo 
                       ORDER BY i.cantidad ASC LIMIT 5");

    // 4. Agenda Global (Hoy) - Todas las sucursales
    $agendaGlobal = query("SELECT c.*, u.nombre as barbero, s.nombre as servicio, suc.nombre as sucursal_nombre, cli.nombre as cliente, cli.telefono, cli.id as cliente_id 
                           FROM citas c
                           JOIN usuarios u ON c.barbero_id = u.id
                           JOIN servicios s ON c.servicio_id = s.id
                           JOIN sucursales suc ON c.sucursal_id = suc.id
                           JOIN clientes cli ON c.cliente_id = cli.id
                           WHERE DATE(c.fecha_hora) = ?
                           ORDER BY c.fecha_hora ASC", [$hoy]);

    // 5. Valor Inventario Global
    $inventarioStats = query("SELECT SUM(cantidad * precio) as total_valor, SUM(cantidad) as total_items FROM inventario");
    $valorInventario = $inventarioStats[0]['total_valor'] ?? 0;
    $totalItems = $inventarioStats[0]['total_items'] ?? 0;

    // 6. Gráfica 7 Días Global
    $last7Days = [];
    $diasEsp = ['Sun' => 'Dom', 'Mon' => 'Lun', 'Tue' => 'Mar', 'Wed' => 'Mie', 'Thu' => 'Jue', 'Fri' => 'Vie', 'Sat' => 'Sab'];

    for ($i = 6; $i >= 0; $i--) {
        $d = date('Y-m-d', strtotime("-$i days"));
        $total = query("SELECT SUM(precio_final) as t FROM citas WHERE estado = 'completada' AND DATE(fecha_hora) = ?", [$d])[0]['t'] ?? 0;
        $dayName = date('D', strtotime($d));
        $last7Days[] = ['date' => $diasEsp[$dayName], 'val' => $total];
    }
    $maxVal = max(array_column($last7Days, 'val'));
    $maxVal = $maxVal > 0 ? $maxVal : 1;

    // 7. Retención Global
    $totalHoyCitas = count($agendaGlobal);
    $recurrentes = 0;
    foreach ($agendaGlobal as $c) {
        $historial = query("SELECT COUNT(*) as n FROM citas WHERE cliente_id = ? AND estado = 'completada'", [$c['cliente_id']])[0]['n'];
        if ($historial > 1) $recurrentes++;
    }
    $retentionRate = $totalHoyCitas > 0 ? round(($recurrentes / $totalHoyCitas) * 100) : 0;

    $meses = ['January' => 'Enero', 'February' => 'Febrero', 'March' => 'Marzo', 'April' => 'Abril', 'May' => 'Mayo', 'June' => 'Junio', 'July' => 'Julio', 'August' => 'Agosto', 'September' => 'Septiembre', 'October' => 'Octubre', 'November' => 'Noviembre', 'December' => 'Diciembre'];
    $mesActual = $meses[date('F')] ?? date('F');
    ?>

    <style>
        .earnings-card {
            background: #FFFFFF !important;
            border: 1px solid #E5E5E5 !important;
            color: #111 !important;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
        }
        .earnings-card .earnings-title {
            color: #666;
            font-size: 0.85em;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .earnings-card .earnings-amount {
            color: #111;
            font-weight: 800;   
        }
        .earnings-card .trend-indicator {
            color: var(--primary-gold);
            font-weight: 600;
        }
        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
    </style>

    <div class="dashboard-grid">
        <!-- Venta Día -->
        <div class="earnings-card">
            <div class="earnings-title">Venta Global (Hoy)</div>
            <div class="earnings-amount">$<?php echo number_format($citasStats['venta_dia'] ?? 0, 2); ?></div>
            <div class="trend-indicator">
                <span><?php echo $citasStats['completadas']; ?> citas finalizadas</span>
            </div>
        </div>

        <!-- Recaudación Mensual -->
        <div class="earnings-card">
            <div class="earnings-title">Recaudación Global (Mes)</div>
            <div class="earnings-amount">$<?php echo number_format($ventaMes, 2); ?></div>
            <div class="trend-indicator trend-up">
                <span><?php echo $mesActual; ?></span>
            </div>
        </div>

        <!-- Valor Inventario -->
        <div class="earnings-card">
            <div class="earnings-title">Valor Stock Global</div>
            <div class="earnings-amount">$<?php echo number_format($valorInventario, 2); ?></div>
            <div class="trend-indicator">
                <span><?php echo $totalItems; ?> items totales</span>
            </div>
        </div>
        
        <!-- Ventas Productos Hoy -->
        <?php
            $ventasProdHoy = query("SELECT SUM(precio_unitario * cantidad) as total, SUM(cantidad) as items 
                                    FROM ventas_productos 
                                    WHERE DATE(fecha) = ?", [$hoy]);
            $totalVentasProd = $ventasProdHoy[0]['total'] ?? 0;
            $itemsVendidos = $ventasProdHoy[0]['items'] ?? 0;
        ?>
        <div class="earnings-card">
            <div class="earnings-title">Ventas Productos (Hoy)</div>
            <div class="earnings-amount">$<?php echo number_format($totalVentasProd, 2); ?></div>
            <div class="trend-indicator">
                <span><?php echo $itemsVendidos; ?> items vendidos</span>
            </div>
        </div>

        <!-- Fidelización -->
        <div class="earnings-card" style="background: linear-gradient(135deg, #2C3E50 0%, #000000 100%) !important; color: #FFF !important;">
            <div class="earnings-title" style="color: #AAA !important;">Fidelización Hoy</div>
            <div class="earnings-amount" style="color: #FFF !important;"><?php echo $retentionRate; ?>%</div>
            <div class="trend-indicator">
                <span><?php echo $recurrentes; ?> recurrentes</span>
            </div>
        </div>
    </div>

    <!-- GRÁFICO DE BARRAS SEMANAL -->
    <div class="card" style="margin-bottom: 24px;">
        <div class="card-title">Tendencia de Ventas (7 Días) - Global</div>
        <div style="display: flex; align-items: flex-end; justify-content: space-between; height: 150px; padding-top: 20px;">
            <?php foreach ($last7Days as $day):
                $height = ($day['val'] / $maxVal) * 100;
                $color = $day['val'] > 0 ? 'var(--primary-gold)' : '#333';
                ?>
                <div style="text-align: center; width: 100%;">
                    <div style="font-size: 10px; color: #BBB; margin-bottom: 5px;">$<?php echo (int) $day['val']; ?></div>
                    <div style="height: <?php echo $height; ?>%; background: <?php echo $color; ?>; width: 60%; margin: 0 auto; border-radius: 4px 4px 0 0; min-height: 4px;"></div>
                    <div style="margin-top: 8px; font-size: 11px; color: #888;"><?php echo $day['date']; ?></div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="row" style="display: flex; gap: 24px; flex-wrap: wrap;">
        <!-- Ranking Barberos -->
        <div style="flex: 1; min-width: 300px;">
            <div class="card">
                <div class="card-title">🏆 Top Barberos Global (Mes)</div>
                <div class="table-container">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Barbero / Sede</th>
                                <th style="text-align: right;">Ventas</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($topBarberos): ?>
                                <?php foreach ($topBarberos as $b): ?>
                                    <tr>
                                        <td>
                                            <div style="font-weight: bold;"><?php echo htmlspecialchars($b['nombre']); ?></div>
                                            <div style="font-size: 10px; color: #888;"><?php echo htmlspecialchars($b['sucursal']); ?></div>
                                        </td>
                                        <td style="text-align: right; color: var(--primary-gold); font-weight: bold;">
                                            $<?php echo number_format($b['total'], 0); ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="2" style="text-align: center; color: #666;">Sin datos aún</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Inventario Bajo -->
        <div style="flex: 1; min-width: 300px;">
            <div class="card">
                <div class="card-title">⚠️ Alerta Stock Global</div>
                 <div class="table-container">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Producto / Sede</th>
                                <th>Cant.</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($lowStock): ?>
                                <?php foreach ($lowStock as $p): ?>
                                    <tr>
                                        <td>
                                            <div style="font-weight: bold;"><?php echo htmlspecialchars($p['producto']); ?></div>
                                            <div style="font-size: 10px; color: #888;"><?php echo htmlspecialchars($p['sucursal']); ?></div>
                                        </td>
                                        <td>
                                            <span class="badge badge-cancelada" style="background: rgba(231, 76, 60, 0.2); color: #e74c3c;">
                                                <?php echo $p['cantidad']; ?> / <?php echo $p['stock_minimo']; ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="2" style="text-align: center;">Todo en orden</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

<?php elseif ($currentUser['rol'] === 'admin_local'): ?>
    <!-- VISTA ADMIN LOCAL (Mantenida Original) -->
    <?php
    $sucursal_id = $currentUser['sucursal_id'];
    $hoy = date('Y-m-d');
    $mesInicio = date('Y-m-01');
    $mesFin = date('Y-m-t');

    // 1. KPI: Citas y Ventas
    $citasStats = query("SELECT 
        COUNT(*) as total, 
        SUM(CASE WHEN estado = 'pendiente' THEN 1 ELSE 0 END) as pendientes,
        SUM(CASE WHEN estado = 'completada' THEN 1 ELSE 0 END) as completadas,
        SUM(CASE WHEN estado = 'completada' AND DATE(fecha_hora) = ? THEN precio_final ELSE 0 END) as venta_dia
    FROM citas WHERE sucursal_id = ? AND DATE(fecha_hora) = ?", [$hoy, $sucursal_id, $hoy])[0];

    // Venta Mes
    $ventaMes = query("SELECT SUM(precio_final) as total FROM citas WHERE sucursal_id = ? AND estado = 'completada' AND DATE(fecha_hora) BETWEEN ? AND ?", [$sucursal_id, $mesInicio, $mesFin])[0]['total'] ?? 0;

    // 2. Ranking Barberos (Mes)
    $topBarberos = query("SELECT u.nombre, COUNT(c.id) as citas, SUM(c.precio_final) as total
                          FROM usuarios u
                          JOIN citas c ON u.id = c.barbero_id
                          WHERE u.sucursal_id = ? AND c.estado = 'completada'
                          AND DATE(c.fecha_hora) BETWEEN ? AND ?
                          GROUP BY u.id
                          ORDER BY total DESC LIMIT 5", [$sucursal_id, $mesInicio, $mesFin]);

    // 3. Inventario Bajo (Alerta)
    $lowStock = query("SELECT producto, cantidad, stock_minimo FROM inventario 
                       WHERE sucursal_id = ? AND cantidad <= stock_minimo 
                       ORDER BY cantidad ASC LIMIT 5", [$sucursal_id]);

    // 4. Agenda General Sucursal (Hoy)
    $agendaGlobal = query("SELECT c.*, u.nombre as barbero, s.nombre as servicio, cli.nombre as cliente, cli.telefono, cli.id as cliente_id 
                           FROM citas c
                           JOIN usuarios u ON c.barbero_id = u.id
                           JOIN servicios s ON c.servicio_id = s.id
                           JOIN clientes cli ON c.cliente_id = cli.id
                           WHERE c.sucursal_id = ? AND DATE(c.fecha_hora) = ?
                           ORDER BY c.fecha_hora ASC", [$sucursal_id, $hoy]);

    // 5. Total Valor Inventario (Activos)
    $inventarioStats = query("SELECT SUM(cantidad * precio) as total_valor, SUM(cantidad) as total_items FROM inventario WHERE sucursal_id = ?", [$sucursal_id]);
    $valorInventario = $inventarioStats[0]['total_valor'] ?? 0;
    $totalItems = $inventarioStats[0]['total_items'] ?? 0;
    ?>

    <?php
    // 6. Gráfica 7 Días (CSS Puro)
    $last7Days = [];
    $diasEsp = ['Sun' => 'Dom', 'Mon' => 'Lun', 'Tue' => 'Mar', 'Wed' => 'Mie', 'Thu' => 'Jue', 'Fri' => 'Vie', 'Sat' => 'Sab'];

    for ($i = 6; $i >= 0; $i--) {
        $d = date('Y-m-d', strtotime("-$i days"));
        // Filtro flexible: si sucursal_id es NULL, sumar todo.
        if ($sucursal_id) {
            $total = query("SELECT SUM(precio_final) as t FROM citas WHERE sucursal_id = ? AND estado = 'completada' AND DATE(fecha_hora) = ?", [$sucursal_id, $d])[0]['t'] ?? 0;
        } else {
            $total = query("SELECT SUM(precio_final) as t FROM citas WHERE estado = 'completada' AND DATE(fecha_hora) = ?", [$d])[0]['t'] ?? 0;
        }
        $dayName = date('D', strtotime($d));
        $last7Days[] = ['date' => $diasEsp[$dayName], 'val' => $total];
    }
    // Normalizar para altura de gráfico
    $maxVal = max(array_column($last7Days, 'val'));
    $maxVal = $maxVal > 0 ? $maxVal : 1;

    // 7. Tasa de Retención (Hoy)
    $totalHoyCitas = count($agendaGlobal);
    $recurrentes = 0;
    foreach ($agendaGlobal as $c) {
        $historial = query("SELECT COUNT(*) as n FROM citas WHERE cliente_id = ? AND estado = 'completada'", [$c['cliente_id']])[0]['n'];
        if ($historial > 1)
            $recurrentes++;
    }
    $retentionRate = $totalHoyCitas > 0 ? round(($recurrentes / $totalHoyCitas) * 100) : 0;

    // Helper mes español
    $meses = ['January' => 'Enero', 'February' => 'Febrero', 'March' => 'Marzo', 'April' => 'Abril', 'May' => 'Mayo', 'June' => 'Junio', 'July' => 'Julio', 'August' => 'Agosto', 'September' => 'Septiembre', 'October' => 'Octubre', 'November' => 'Noviembre', 'December' => 'Diciembre'];
    $mesActual = $meses[date('F')] ?? date('F');
    ?>

    <style>
        .earnings-card {
            background: #FFFFFF !important;
            border: 1px solid #E5E5E5 !important;
            color: #111 !important;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
        }
        .earnings-card .earnings-title {
            color: #666;
            font-size: 0.85em;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .earnings-card .earnings-amount {
            color: #111;
            font-weight: 800;   
        }
        .earnings-card .trend-indicator {
            color: var(--primary-gold);
            font-weight: 600;
        }
        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
    </style>

    <div class="dashboard-grid">
        <!-- Venta Día -->
        <div class="earnings-card">
            <div class="earnings-title">Venta del Día</div>
            <div class="earnings-amount">$<?php echo number_format($citasStats['venta_dia'] ?? 0, 2); ?></div>
            <div class="trend-indicator">
                <span><?php echo $citasStats['completadas']; ?> citas finalizadas</span>
            </div>
        </div>

        <!-- Recaudación Mensual -->
        <div class="earnings-card">
            <div class="earnings-title">Recaudación Mensual</div>
            <div class="earnings-amount">$<?php echo number_format($ventaMes, 2); ?></div>
            <div class="trend-indicator">
                <span><?php echo $mesActual; ?></span>
            </div>
        </div>

        <!-- Valor Inventario (NUEVO) -->
        <div class="earnings-card">
            <div class="earnings-title">Valor en Productos</div>
            <div class="earnings-amount">$<?php echo number_format($valorInventario, 2); ?></div>
            <div class="trend-indicator">
                <span><?php echo $totalItems; ?> unidades en stock</span>
            </div>
        </div>

        <!-- Retención -->
        <div class="earnings-card" style="background: linear-gradient(135deg, #2C3E50 0%, #000000 100%);">
            <div class="earnings-title">Fidelización Hoy</div>
            <div class="earnings-amount"><?php echo $retentionRate; ?>%</div>
            <div class="trend-indicator">
                <span><?php echo $recurrentes; ?> clientes recurrentes</span>
            </div>
        </div>
        
        <!-- PRODUCTOS VENDIDOS (HOY) -->
        <?php
            // Calcular productos vendidos HOY
            $ventasProdHoy = query("SELECT SUM(precio_unitario * cantidad) as total, SUM(cantidad) as items 
                                    FROM ventas_productos 
                                    WHERE sucursal_id = ? AND DATE(fecha) = ?", [$sucursal_id, $hoy]);
            $totalVentasProd = $ventasProdHoy[0]['total'] ?? 0;
            $itemsVendidos = $ventasProdHoy[0]['items'] ?? 0;
        ?>
        <div class="earnings-card">
            <div class="earnings-title">Ventas Productos (Hoy)</div>
            <div class="earnings-amount">$<?php echo number_format($totalVentasProd, 2); ?></div>
            <div class="trend-indicator">
                <span><?php echo $itemsVendidos; ?> items vendidos</span>
            </div>
        </div>
    </div>

    <!-- GRÁFICO DE BARRAS SEMANAL -->
    <div class="card" style="margin-bottom: 24px;">
        <div class="card-title">Tendencia de Ventas (7 Días)</div>
        <div
            style="display: flex; align-items: flex-end; justify-content: space-between; height: 150px; padding-top: 20px;">
            <?php foreach ($last7Days as $day):
                $height = ($day['val'] / $maxVal) * 100;
                $color = $day['val'] > 0 ? 'var(--primary-gold)' : '#333';
                ?>
                <div style="text-align: center; width: 100%;">
                    <div style="font-size: 10px; color: #BBB; margin-bottom: 5px;">$<?php echo (int) $day['val']; ?></div>
                    <div style="
                    height: <?php echo $height; ?>%; 
                    background: <?php echo $color; ?>; 
                    width: 60%; 
                    margin: 0 auto; 
                    border-radius: 4px 4px 0 0;
                    min-height: 4px;
                    transition: height 1s ease;">
                    </div>
                    <div style="margin-top: 8px; font-size: 11px; color: #888;"><?php echo $day['date']; ?></div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="row" style="display: flex; gap: 24px; flex-wrap: wrap;">

        <!-- Columna Izquierda: Ranking + Inventario -->
        <div style="flex: 1; min-width: 300px;">

            <!-- Ranking Barberos -->
            <div class="card">
                <div class="card-title">🏆 Top Barberos (Mes)</div>
                <div class="table-container">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Barbero</th>
                                <th style="text-align: right;">Ventas</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($topBarberos): ?>
                                <?php foreach ($topBarberos as $b): ?>
                                    <tr>
                                        <td>
                                            <div style="font-weight: bold;"><?php echo htmlspecialchars($b['nombre']); ?></div>
                                            <div style="font-size: 10px; color: #888;"><?php echo $b['citas']; ?> citas</div>
                                        </td>
                                        <td style="text-align: right; color: var(--primary-gold); font-weight: bold;">
                                            $<?php echo number_format($b['total'], 0); ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="2" style="text-align: center; color: #666;">Sin datos aún</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Alerta Inventario -->
            <?php if ($lowStock): ?>
                <div class="card" style="margin-top: 24px; border-left: 4px solid #E74C3C;">
                    <div class="card-title" style="color: #E74C3C;">⚠️ Stock Bajo</div>
                    <ul style="list-style: none; padding: 0; margin: 0;">
                        <?php foreach ($lowStock as $prod): ?>
                            <li
                                style="display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid rgba(255,255,255,0.05);">
                                <span><?php echo htmlspecialchars($prod['producto']); ?></span>
                                <span style="color: #E74C3C; font-weight: bold;"><?php echo $prod['cantidad']; ?> und.</span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                    <a href="inventario.php"
                        style="display: block; margin-top: 10px; font-size: 11px; text-align: right; color: #AAA;">Gestionar
                        Inventario →</a>
                </div>
            <?php endif; ?>

        </div>

        <!-- Columna Derecha: Agenda Global -->
        <div style="flex: 2; min-width: 400px;">
            <div class="card">
                <div class="card-title">Agenda General de Sucursal (Hoy)</div>
                <div class="table-container">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Hora</th>
                                <th>Barbero</th>
                                <th>Cliente</th>
                                <th>Servicio</th>
                                <th>Estado</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($agendaGlobal): ?>
                                <?php foreach ($agendaGlobal as $cita): ?>
                                    <tr>
                                        <td style="font-weight: bold; color: var(--primary-gold);">
                                            <?php echo date('H:i', strtotime($cita['fecha_hora'])); ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($cita['barbero']); ?></td>
                                        <td>
                                            <div style="font-weight:bold;"><?php echo htmlspecialchars($cita['cliente']); ?></div>
                                            <?php if (!empty($cita['telefono'])): 
                                                $wa_phone = formatPhoneForWhatsapp($cita['telefono']);
                                                $wa_msg = urlencode("Hola " . explode(' ', $cita['cliente'])[0] . ", te escribo de Kortzen sobre tu cita.");
                                            ?>
                                                <div style="font-size: 11px; margin-top: 4px; display: flex; gap: 8px; align-items: center;">
                                                    <span style="color:#888;"><?php echo htmlspecialchars($cita['telefono']); ?></span>
                                                    
                                                    <!-- WA Button -->
                                                    <a href="https://wa.me/<?php echo $wa_phone; ?>?text=<?php echo $wa_msg; ?>" target="_blank" title="WhatsApp" style="color: #25D366; text-decoration: none;">
                                                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981zm11.387-5.464c-.074-.124-.272-.198-.57-.347-.297-.149-1.758-.868-2.031-.967-.272-.099-.47-.149-.669.149-.198.297-.768.967-.941 1.165-.173.198-.347.223-.644.074-.297-.149-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.297-.347.446-.521.151-.172.2-.296.3-.495.099-.198.05-.372-.025-.521-.075-.148-.669-1.611-.916-2.206-.242-.579-.487-.501-.669-.51l-.57-.01c-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.095 3.2 5.076 4.487.709.306 1.263.489 1.694.626.712.226 1.36.194 1.872.118.571-.085 1.758-.719 2.006-1.413.248-.695.248-1.29.173-1.414z" /></svg>
                                                    </a>

                                                    <!-- Call Button -->
                                                    <a href="tel:<?php echo $raw_phone; ?>" title="Llamar" style="color: var(--primary-gold); text-decoration: none;">
                                                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path></svg>
                                                    </a>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($cita['servicio']); ?></td>
                                        <td>
                                            <span class="badge badge-<?php echo $cita['estado']; ?>">
                                                <?php echo ucfirst($cita['estado']); ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" style="text-align: center; padding: 20px;">No hay citas para hoy</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- CALENDARIO DE OCUPACIÓN -->
    <?php
    // Lógica del Calendario
    $calMonth = date('m');
    $calYear = date('Y');
    $daysInMonth = cal_days_in_month(CAL_GREGORIAN, $calMonth, $calYear);
    $firstDayOfMonth = date('N', strtotime("$calYear-$calMonth-01")); // 1 (Mon) to 7 (Sun)

    // Obtener días con citas
    if ($sucursal_id) {
        $bookings = query("SELECT DATE(fecha_hora) as d, COUNT(*) as c FROM citas WHERE sucursal_id = ? AND MONTH(fecha_hora) = ? AND YEAR(fecha_hora) = ? GROUP BY d", [$sucursal_id, $calMonth, $calYear]);
    } else {
        $bookings = query("SELECT DATE(fecha_hora) as d, COUNT(*) as c FROM citas WHERE MONTH(fecha_hora) = ? AND YEAR(fecha_hora) = ? GROUP BY d", [$calMonth, $calYear]);
    }

    // Mapear citas por día
    $bookingsMap = [];
    foreach ($bookings as $b) {
        $dayNum = (int) date('d', strtotime($b['d']));
        $bookingsMap[$dayNum] = $b['c'];
    }
    ?>
    <style>
        .calendar-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 8px;
            margin-top: 16px;
        }

        .cal-day-header {
            text-align: center;
            font-size: 11px;
            color: #888;
            padding-bottom: 8px;
            text-transform: uppercase;
        }

        .cal-day {
            background: rgba(255, 255, 255, 0.03);
            border-radius: 6px;
            height: 80px;
            padding: 8px;
            position: relative;
            border: 1px solid transparent;
            transition: all 0.2s;
        }

        .cal-day:hover {
            border-color: rgba(255, 255, 255, 0.1);
            background: rgba(255, 255, 255, 0.05);
        }

        .cal-number {
            font-size: 14px;
            font-weight: 600;
            color: #DDD;
        }

        .cal-dots {
            display: flex;
            gap: 3px;
            margin-top: 6px;
            flex-wrap: wrap;
        }

        .cal-dot {
            width: 6px;
            height: 6px;
            background: var(--primary-gold);
            border-radius: 50%;
        }

        .cal-badge {
            font-size: 10px;
            background: #333;
            padding: 2px 6px;
            border-radius: 4px;
            color: #AAA;
            margin-top: 4px;
            display: inline-block;
        }

        .has-bookings {
            background: rgba(201, 169, 110, 0.05);
            border-color: rgba(201, 169, 110, 0.2);
        }

        .is-today {
            border: 1px solid var(--primary-gold);
        }
    </style>

    <div class="card" style="margin-top: 24px;">
        <div class="card-title">Calendario de Ocupación - <?php echo $mesActual . ' ' . $calYear; ?></div>

        <div class="calendar-grid">
            <div class="cal-day-header">Lun</div>
            <div class="cal-day-header">Mar</div>
            <div class="cal-day-header">Mie</div>
            <div class="cal-day-header">Jue</div>
            <div class="cal-day-header">Vie</div>
            <div class="cal-day-header">Sab</div>
            <div class="cal-day-header">Dom</div>

            <?php
            // Espacios vacíos antes del día 1
            for ($i = 1; $i < $firstDayOfMonth; $i++) {
                echo "<div></div>";
            }

            // Días del mes
            for ($day = 1; $day <= $daysInMonth; $day++) {
                $count = $bookingsMap[$day] ?? 0;
                $class = $count > 0 ? 'cal-day has-bookings' : 'cal-day';
                if ($day == date('d'))
                    $class .= ' is-today';

                $onclick = "onclick=\"loadDayDetails($day)\"";
                $style = "cursor: pointer;";

                echo "<div class='$class' $onclick style='$style'>";
                echo "<div class='cal-number'>$day</div>";

                if ($count > 0) {
                    echo "<div class='cal-badge'>$count citas</div>";
                    echo "<div class='cal-dots'>";
                    // Mostrar hasta 5 puntitos visuales
                    for ($k = 0; $k < min($count, 5); $k++)
                        echo "<div class='cal-dot'></div>";
                    if ($count > 5)
                        echo "<span style='font-size:10px;color:#666;'>+</span>";
                    echo "</div>";
                }

                echo "</div>";
            }
            ?>
        </div>
    </div>

    <!-- DETALLES DEL DÍA SELECCIONADO (Oculto por defecto) -->
    <div id="day-details-container" class="card" style="margin-top: 24px; display: none; transition: opacity 0.3s ease;">
        <div class="card-title" style="display: flex; justify-content: space-between; align-items: center;">
            <span id="day-details-title">Detalles del Día</span>
            <button onclick="document.getElementById('day-details-container').style.display='none'"
                style="background:none; border:none; color: #888; cursor: pointer; font-size: 1.2rem;">&times;</button>
        </div>
        <div id="day-details-content">
            <!-- Table dynamically populated -->
        </div>
        <div id="day-details-summary"
            style="margin-top: 15px; text-align: right; font-size: 1.2rem; color: var(--primary-gold); font-weight: bold; border-top: 1px solid rgba(255,255,255,0.1); padding-top: 10px;">
            <!-- Total revenue -->
        </div>
    </div>

    <script>
        function loadDayDetails(day) {
            // Construct date string YYYY-MM-DD
            const year = <?php echo $calYear; ?>;
            // Ensure month is 2 digits for consistency
            const month = "<?php echo str_pad($calMonth, 2, '0', STR_PAD_LEFT); ?>";
            const dayStr = String(day).padStart(2, '0');
            const fullDate = `${year}-${month}-${dayStr}`;
            // Ensure we handle the PHP null/empty value correctly for JS
            const branchId = "<?php echo $sucursal_id ?: ''; ?>";

            const container = document.getElementById('day-details-container');
            const content = document.getElementById('day-details-content');
            const summary = document.getElementById('day-details-summary');
            const title = document.getElementById('day-details-title');

            // Show container with loading state
            container.style.display = 'block';
            title.innerHTML = `Detalles del <span style="color:var(--primary-gold)">${dayStr}/${month}/${year}</span>`;
            content.innerHTML = '<p style="text-align:center; color:#888; padding: 20px;">Cargando citas...</p>';
            summary.innerHTML = '';

            // Scroll to details
            container.scrollIntoView({ behavior: 'smooth', block: 'center' });

            // Fetch
            let url = `api/get_citas_dia.php?fecha=${fullDate}`;
            if (branchId) url += `&sucursal_id=${branchId}`;

            fetch(url)
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        if (data.citas && data.citas.length > 0) {
                            let html = `
                            <div class="table-container">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Hora</th>
                                            <th>Cliente</th>
                                            <th>Servicio</th>
                                            <th>Barbero</th>
                                            <th>Precio</th>
                                            <th>Estado</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                        `;

                            data.citas.forEach(cita => {
                                // Extract time HH:MM
                                const timePart = cita.fecha_hora.split(' ')[1].substring(0, 5);
                                html += `
                                <tr>
                                    <td style="font-weight:bold; color:white;">${timePart}</td>
                                    <td>${cita.cliente}</td>
                                    <td>${cita.servicio}</td>
                                    <td>${cita.barbero}</td>
                                    <td style="color:var(--primary-gold);">$${parseFloat(cita.precio_final).toFixed(2)}</td>
                                    <td><span class="badge badge-${cita.estado}">${cita.estado.charAt(0).toUpperCase() + cita.estado.slice(1)}</span></td>
                                </tr>
                            `;
                            });

                            html += `</tbody></table></div>`;
                            content.innerHTML = html;
                            summary.innerHTML = `Recaudación Total: $${parseFloat(data.total_recaudado).toFixed(2)}`;
                        } else {
                            content.innerHTML = '<p style="text-align:center; padding: 20px; color: #888;">No hubo citas registradas este día.</p>';
                            summary.innerHTML = 'Recaudación: $0.00';
                        }
                    } else {
                        content.innerHTML = `<p style="color:red; text-align:center;">Error: ${data.message}</p>`;
                    }
                })
                .catch(err => {
                    console.error(err);
                    content.innerHTML = '<p style="color:red; text-align:center;">Error de conexión.</p>';
                });
        }
    </script>

<?php else: ?>
    <!-- ========================================== -->
    <!-- VISTA BARBERO MEJORADA (SUPER DASHBOARD) -->
    <!-- ========================================== -->

    <?php
    $barbero_id = $currentUser['id'];
    $hoy = date('Y-m-d');

    // 1. CALCULAR GANANCIAS CON DOBLE TASA (Diaria vs Fin de Semana)
    $com_diaria = floatval($currentUser['comision_porcentaje'] ?? 50);
    $com_finde = floatval($currentUser['comision_fin_semana'] ?? 50);

    // Lógica SQL para calcular ganancia basada en el día de la cita
    // DAYOFWEEK: 1=Domingo, 7=Sábado => IN (1, 7) = Fin de Semana
    $sqlGanancia = "
        SUM(
            (IFNULL(precio_final, 0) * (CASE WHEN DAYOFWEEK(fecha_hora) IN (1, 7) THEN $com_finde ELSE $com_diaria END) / 100) 
            + 
            (IFNULL((SELECT SUM(vp.cantidad * vp.precio_unitario) FROM ventas_productos vp WHERE vp.cita_id = citas.id), 0) * (CASE WHEN DAYOFWEEK(fecha_hora) IN (1, 7) THEN $com_finde ELSE $com_diaria END) / 100)
        ) as total_ganancia
    ";

    // 1.1 Ganancia HOY
    $gananciaHoy = query("
        SELECT $sqlGanancia
        FROM citas 
        WHERE barbero_id = ? AND estado = 'completada' AND DATE(fecha_hora) = CURDATE()
    ", [$barbero_id]);
    $miGananciaDia = $gananciaHoy[0]['total_ganancia'] ?? 0;

    // 1.2 Ganancia SEMANA
    $monday = date('Y-m-d', strtotime('monday this week'));
    $sunday = date('Y-m-d', strtotime('sunday this week'));
    $gananciaSemana = query("
        SELECT $sqlGanancia
        FROM citas 
        WHERE barbero_id = ? AND estado = 'completada' AND DATE(fecha_hora) BETWEEN ? AND ?
    ", [$barbero_id, $monday, $sunday]);
    $miGananciaSemana = $gananciaSemana[0]['total_ganancia'] ?? 0;

    // 1.3 Ganancia MES
    $monthStart = date('Y-m-01');
    $monthEnd = date('Y-m-t');
    $gananciaMes = query("
        SELECT $sqlGanancia
        FROM citas 
        WHERE barbero_id = ? AND estado = 'completada' AND DATE(fecha_hora) BETWEEN ? AND ?
    ", [$barbero_id, $monthStart, $monthEnd]);
    $miGananciaMes = $gananciaMes[0]['total_ganancia'] ?? 0;

    // 2. PRÓXIMO CLIENTE...
    // ... (restcode kept)
    $nextClient = query("SELECT c.*, s.nombre as servicio, cli.nombre as cliente, cli.foto_perfil
                         FROM citas c
                         JOIN servicios s ON c.servicio_id = s.id
                         JOIN clientes cli ON c.cliente_id = cli.id
                         WHERE c.barbero_id = ? 
                         AND c.fecha_hora >= NOW()
                         AND c.estado IN ('pendiente', 'confirmada')
                         ORDER BY c.fecha_hora ASC 
                         LIMIT 1",
        [$barbero_id]
    );

    $proximo = $nextClient ? $nextClient[0] : null;

    // 3. CITAS DE HOY (Lista Completa)
    $misCitasHoy = query("SELECT c.*, s.nombre as servicio, cli.nombre as cliente, cli.telefono, cli.id as cliente_id
                          FROM citas c
                          JOIN servicios s ON c.servicio_id = s.id
                          JOIN clientes cli ON c.cliente_id = cli.id
                          WHERE c.barbero_id = ? 
                          AND DATE(c.fecha_hora) = ?
                          AND c.estado != 'cancelada'
                          ORDER BY c.fecha_hora ASC",
        [$barbero_id, $hoy]
    );
    ?>

    <!-- SECCIÓN DE GANANCIAS -->
    <div class="dashboard-grid">
        <div class="earnings-card">
            <!-- Si quieres mostrar el %, podrías poner "Var" o ambos ej: 50% / 60% -->
            <div class="earnings-title">Tu Ganancia Hoy (Dinámica)</div>
            <div class="earnings-amount">$<?php echo number_format($miGananciaDia, 2); ?></div>
            <div style="font-size: 11px; color: var(--text-muted); margin-top: 4px;">Venta Total:
                $<?php echo number_format($earningsTodayTotal, 2); ?></div>
            <div class="trend-indicator trend-up">
                <span>📅 <?php echo date('d M'); ?></span>
            </div>
        </div>
        <div class="earnings-card" style="border-color: rgba(255, 255, 255, 0.1);">
            <div class="earnings-title">Tu Ganancia Semana</div>
            <div class="earnings-amount">$<?php echo number_format($miGananciaSemana, 2); ?></div>
            <div style="font-size: 11px; color: var(--text-muted); margin-top: 4px;">Venta Total:
                $<?php echo number_format($earningsWeekTotal, 2); ?></div>
            <div class="trend-indicator">
                <span>Semana <?php echo date('W'); ?></span>
            </div>
        </div>
        <div class="earnings-card" style="border-color: rgba(255, 255, 255, 0.1);">
            <div class="earnings-title">Tu Ganancia Mes</div>
            <div class="earnings-amount">$<?php echo number_format($miGananciaMes, 2); ?></div>
            <div style="font-size: 11px; color: var(--text-muted); margin-top: 4px;">Venta Total:
                $<?php echo number_format($earningsMonthTotal, 2); ?></div>
            <div class="trend-indicator">
                <span><?php echo date('F Y'); ?></span>
            </div>
        </div>
    </div>

    <div class="row" style="display: flex; gap: 24px; flex-wrap: wrap;">
        <!-- COLUMNA IZQUIERDA: Next Up + Acciones -->
        <div style="flex: 1; min-width: 300px;">

            <?php if ($proximo): ?>
                <div class="next-client-card">
                    <div class="next-time">
                        <div class="next-hour"><?php echo date('H:i', strtotime($proximo['fecha_hora'])); ?></div>
                        <div class="next-label">Próximo</div>
                    </div>
                    <div class="client-details">
                        <h3 style="margin: 0;"><?php echo htmlspecialchars($proximo['cliente']); ?></h3>
                        <span class="service-badge"><?php echo htmlspecialchars($proximo['servicio']); ?></span>
                        <div style="margin-top: 8px; font-size: 12px; color: #888;">
                            <?php
                            $minutos = round((strtotime($proximo['fecha_hora']) - time()) / 60);
                            if ($minutos < 0)
                                echo "En curso (hace " . abs($minutos) . " min)";
                            else
                                echo "En $minutos minutos";
                            ?>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <div class="alert alert-success" style="margin-bottom: 24px;">
                    🎉 ¡Todo listo! No tienes más clientes pendientes por hoy.
                </div>
            <?php endif; ?>

            <!-- ACCIONES RÁPIDAS -->
            <h3 class="card-title">Acciones Rápidas</h3>
            <div class="action-buttons-grid">
                <div class="quick-action-btn" onclick="crearCitaRapida()">
                    <span style="font-size: 24px;">📅</span>
                    <span>Agendar Cita</span>
                </div>
                <div class="quick-action-btn" onclick="bloquearHora()">
                    <span style="font-size: 24px;">☕</span>
                    <span>Bloqueo 1h</span>
                </div>
            </div>

        </div>

        <!-- COLUMNA DERECHA: Lista de Hoy -->
        <div style="flex: 2; min-width: 400px;">
            <div class="card">
                <div class="card-title">
                    Agenda de Hoy
                    <span style="margin-left: auto; font-size: 0.8em; opacity: 0.7;"><?php echo count($misCitasHoy); ?>
                        citas</span>
                </div>
                <div class="table-container">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Hora</th>
                                <th>Cliente</th>
                                <th>Servicio</th>
                                <th>Estado</th>
                                <th>Acción</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($misCitasHoy as $cita): ?>
                                <tr>
                                    <td style="font-weight: bold; color: var(--primary-gold);">
                                        <?php echo date('H:i', strtotime($cita['fecha_hora'])); ?>
                                    </td>
                                    <td>
                                        <div><?php echo htmlspecialchars($cita['cliente']); ?></div>
                                        <div
                                            style="font-size: 0.85em; color: var(--text-muted); display: flex; align-items: center; gap: 6px;">
                                            <?php echo htmlspecialchars($cita['telefono']); ?>
                                        </div>
                                        <a href="#"
                                            onclick="verHistorial(<?php echo $cita['cliente_id']; ?>, '<?php echo htmlspecialchars($cita['cliente']); ?>')"
                                            style="font-size: 11px; color: var(--text-muted); text-decoration: underline;">Ver
                                            Historial</a>
                                    </td>
                                    <td><?php echo htmlspecialchars($cita['servicio']); ?></td>
                                    <td>
                                        <span
                                            class="badge badge-<?php echo $cita['estado']; ?>"><?php echo ucfirst($cita['estado']); ?></span>
                                    </td>
                                    <td class="actions-cell">
                                        <?php if ($cita['estado'] === 'pendiente' || $cita['estado'] === 'confirmada'): ?>
                                            <button onclick="abrirModalTerminar(<?php echo $cita['id']; ?>)"
                                                class="btn btn-sm btn-primary" style="margin-right: 5px;">Terminar</button>
                                            <button onclick="confirmarCancelar(<?php echo $cita['id']; ?>)"
                                                class="btn btn-sm btn-delete"
                                                style="background:transparent; border: 1px solid #E74C3C; color: #E74C3C;">Cancelar</button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- MODAL HISTORIAL CLIENTE -->
    <div id="modalHistorial" class="modal-overlay"
        style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.8); z-index: 2000; align-items: center; justify-content: center;">
        <div class="modal-content"
            style="background: #1A1A1A; width: 500px; padding: 30px; border-radius: 12px; border: 1px solid #333;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h3 id="modalClientName" style="color: var(--primary-gold); margin: 0;">Historial</h3>
                <button onclick="document.getElementById('modalHistorial').style.display='none'"
                    style="background: none; border: none; color: #FFF; font-size: 24px; cursor: pointer;">&times;</button>
            </div>
            <div id="historialContent" style="max-height: 400px; overflow-y: auto;">
                <p style="text-align: center; color: #666;">Cargando...</p>
            </div>
        </div>
    </div>

    <!-- MODAL TERMINAR CITA (Añadido para funcionalidad de botones) -->
    <div id="modalTerminar" class="modal-overlay"
        style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.8); z-index: 2000; align-items: center; justify-content: center;">
        <div class="modal-content"
            style="background: #1A1A1A; width: 500px; max-width: 90%; padding: 30px; border-radius: 12px; border: 1px solid #333;">
            <h2 style="margin-bottom: 20px; color: var(--primary-gold);">Terminar Cita</h2>
            <p style="margin-bottom: 20px; color: #AAA;">Confirma que has completado el servicio.</p>

            <form id="formTerminar" method="POST" action="api/citas_action.php">
                <input type="hidden" name="action" value="completar">
                <input type="hidden" name="id" id="citaIdTerminar">
                <input type="hidden" name="redirect_source" value="dashboard">

                <!-- Para futura expansión de inventario -->
                <!-- <div id="materialesList"></div> -->

                <div style="display: flex; gap: 10px; margin-top: 30px;">
                    <button type="button" onclick="document.getElementById('modalTerminar').style.display='none'"
                        class="btn-secondary"
                        style="flex: 1; padding: 12px; border-radius: 6px; cursor: pointer;">Cancelar</button>
                    <button type="submit" class="btn-primary"
                        style="flex: 1; padding: 12px; border-radius: 6px; border: none; cursor: pointer;">Confirmar
                        Completado</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Funciones de Cita (Terminar/Cancelar)
        function confirmarCancelar(id) {
            if (confirm('¿Realmente deseas cancelar esta cita?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = 'api/citas_action.php';

                const actionInput = document.createElement('input');
                actionInput.type = 'hidden';
                actionInput.name = 'action';
                actionInput.value = 'cancelar_barbero';

                const redirectInput = document.createElement('input');
                redirectInput.type = 'hidden';
                redirectInput.name = 'redirect_source';
                redirectInput.value = 'dashboard';

                const idInput = document.createElement('input');
                idInput.type = 'hidden';
                idInput.name = 'id';
                idInput.value = id;

                form.appendChild(actionInput);
                form.appendChild(redirectInput);
                form.appendChild(idInput);
                document.body.appendChild(form);
                form.submit();
            }
        }

        function abrirModalTerminar(id) {
            document.getElementById('citaIdTerminar').value = id;
            document.getElementById('modalTerminar').style.display = 'flex';
        }

        // Funciones del Dashboard original
        function verHistorial(clientId, clientName) {
            document.getElementById('modalHistorial').style.display = 'flex';
            document.getElementById('modalClientName').textContent = 'Historial: ' + clientName;
            document.getElementById('historialContent').innerHTML = '<p style="text-align: center; color: #666;">Cargando datos...</p>';

            fetch(`api/get_client_history.php?client_id=${clientId}`)
                .then(r => r.json())
                .then(data => {
                    const container = document.getElementById('historialContent');
                    if (data.success && data.history.length > 0) {
                        let html = '';
                        data.history.forEach(item => {
                            html += `
                                <div class="history-item">
                                    <div>
                                        <div class="history-service">${item.servicio_nombre}</div>
                                        <div class="history-date">${new Date(item.fecha_hora).toLocaleDateString()} - ${item.notas || 'Sin notas'}</div>
                                    </div>
                                    <div class="history-price">$${parseFloat(item.precio_final).toFixed(2)}</div>
                                </div>
                            `;
                        });
                        container.innerHTML = html;
                    } else {
                        container.innerHTML = '<p style="text-align: center; color: #888;">No hay historial previo disponible.</p>';
                    }
                })
                .catch(err => {
                    document.getElementById('historialContent').innerHTML = '<p style="color: red;">Error al cargar.</p>';
                });
        }

        function bloquearHora() {
            if (!confirm('¿Bloquear la próxima hora disponible en tu agenda para descanso/gestión?')) return;

            fetch('api/block_time_action.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=quick_block_next_hour'
            })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        alert('✅ ' + data.message);
                        location.reload();
                    } else {
                        alert('❌ Error: ' + data.error);
                    }
                });
        }

        function crearCitaRapida() {
            window.location.href = 'citas_crear.php';
        }
    </script>

<?php endif; ?>

<script>
    // Check for URL parameters for alerts
    window.onload = function () {
        const urlParams = new URLSearchParams(window.location.search);
        const success = urlParams.get('success');
        const error = urlParams.get('error');

        if (success) {
            alert('✅ ' + success);
            // Clean URL
            window.history.replaceState({}, document.title, window.location.pathname);
        } else if (error) {
            alert('❌ Error: ' + error);
            window.history.replaceState({}, document.title, window.location.pathname);
        }
    }
</script>

<?php include 'includes/footer.php'; ?>