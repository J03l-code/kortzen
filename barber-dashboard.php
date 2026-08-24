<?php
/**
 * KORTZEN - Dashboard Exclusivo para Barberos
 * Interfaz nativa blanca y elegante para barberos (sistema de diseño idéntico al cliente, sin emojis)
 */

require_once 'config.php';
requireLogin();

$currentUser = getCurrentUser();

if ($currentUser['rol'] !== 'barbero' && $currentUser['rol'] !== 'admin_local' && $currentUser['rol'] !== 'admin') {
    header('Location: dashboard.php');
    exit;
}

$barbero_id = $currentUser['id'];
$sucursal_id = $currentUser['sucursal_id'] ?? 1;

// Asegurar columnas y tablas requeridas en la BD live
try {
    $pdo = getConnection();
    $pdo->exec("ALTER TABLE clientes ADD COLUMN notas_barbero TEXT NULL AFTER notas");
} catch (Exception $exSchema) {}

try {
    $pdo = getConnection();
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS bloqueos_horas (
            id INT AUTO_INCREMENT PRIMARY KEY,
            barbero_id INT NOT NULL,
            fecha DATE NOT NULL,
            hora_inicio TIME NOT NULL,
            hora_fin TIME NOT NULL,
            motivo VARCHAR(255) NULL,
            fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
} catch (Exception $exB) {}

// Mensajes de estado
$mensaje = '';
$tipoMensaje = '';

// Marcar cita como completada y acreditar puntos automáticamente
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'completar_cita') {
        $citaId = intval($_POST['cita_id'] ?? 0);
        if ($citaId > 0) {
            try {
                $pdo = getConnection();
                
                // 1. Actualizar estado de la cita
                $stmt = $pdo->prepare("UPDATE citas SET estado = 'completada' WHERE id = ? AND barbero_id = ? AND estado != 'completada'");
                $stmt->execute([$citaId, $barbero_id]);

                if ($stmt->rowCount() > 0) {
                    // 2. Obtener cliente_id de la cita
                    $stmtCitaInfo = $pdo->prepare("SELECT cliente_id FROM citas WHERE id = ?");
                    $stmtCitaInfo->execute([$citaId]);
                    $citaRow = $stmtCitaInfo->fetch(PDO::FETCH_ASSOC);
                    $clienteId = intval($citaRow['cliente_id'] ?? 0);

                    if ($clienteId > 0) {
                        // Cargar valores de configuración de puntos
                        $puntosPorCorte = 100;
                        $puntosPorReferido = 200;
                        try {
                            $stmtCfg = $pdo->query("SELECT clave, valor FROM configuracion");
                            $cfgs = $stmtCfg->fetchAll(PDO::FETCH_KEY_PAIR);
                            $puntosPorCorte = intval($cfgs['puntos_por_corte'] ?? 100);
                            $puntosPorReferido = intval($cfgs['puntos_por_referido'] ?? 200);
                        } catch (Exception $exCfg) {}

                        // Acreditar puntos al cliente por su corte
                        $stmtPtsCliente = $pdo->prepare("UPDATE clientes SET puntos = IFNULL(puntos, 0) + ? WHERE id = ?");
                        $stmtPtsCliente->execute([$puntosPorCorte, $clienteId]);

                        // Acreditar puntos por referido (tanto al referente como al cliente referido)
                        $stmtRefCheck = $pdo->prepare("
                            SELECT id, referente_id, referido_id, estado 
                            FROM referidos 
                            WHERE (cita_id = ? OR referido_id = ?) AND estado = 'pendiente'
                            LIMIT 1
                        ");
                        $stmtRefCheck->execute([$citaId, $clienteId]);
                        $refData = $stmtRefCheck->fetch(PDO::FETCH_ASSOC);

                        if ($refData) {
                            $referidosTableId = $refData['id'];
                            $referenteId = intval($refData['referente_id']);

                            // Actualizar registro de referido a completado
                            $stmtUpdRef = $pdo->prepare("UPDATE referidos SET estado = 'completado', cita_id = ? WHERE id = ?");
                            $stmtUpdRef->execute([$citaId, $referidosTableId]);

                            // Acreditar al dueño del código (referente)
                            if ($referenteId > 0) {
                                $stmtPtsRef = $pdo->prepare("UPDATE clientes SET puntos = IFNULL(puntos, 0) + ? WHERE id = ?");
                                $stmtPtsRef->execute([$puntosPorReferido, $referenteId]);
                            }

                            // Acreditar también al cliente referido
                            $stmtPtsReferido = $pdo->prepare("UPDATE clientes SET puntos = IFNULL(puntos, 0) + ? WHERE id = ?");
                            $stmtPtsReferido->execute([$puntosPorReferido, $clienteId]);

                            $mensaje = "¡Corte completado! +$puntosPorCorte pts al cliente y +$puntosPorReferido pts acreditados por referido al dueño del código y al cliente.";
                        } else {
                            $mensaje = "¡Corte completado! +$puntosPorCorte pts acreditados a la cuenta del cliente.";
                        }
                    } else {
                        $mensaje = 'Cita marcada como COMPLETADA. Ganancias actualizadas.';
                    }
                } else {
                    $mensaje = 'La cita ya fue marcada como completada anteriormente.';
                }
                $tipoMensaje = 'success';
            } catch (Exception $e) {
                $mensaje = 'Error al actualizar cita: ' . $e->getMessage();
                $tipoMensaje = 'error';
            }
        }
    }
}

// 1. Ganancias por Servicios + Comisión por Ventas de Productos
$com_diaria = floatval($currentUser['comision_porcentaje'] ?? 50);
$com_finde = floatval($currentUser['comision_fin_semana'] ?? 50);
$com_productos = floatval($currentUser['comision_productos'] ?? 10.00);

// Ganancia Citas Hoy (Servicios)
$gananciaHoyServicios = query("
    SELECT SUM((IFNULL(precio_final, 0) * (CASE WHEN DAYOFWEEK(fecha_hora) IN (1, 7) THEN $com_finde ELSE $com_diaria END) / 100)) as total
    FROM citas 
    WHERE barbero_id = ? AND estado = 'completada' AND DATE(fecha_hora) = CURDATE()
", [$barbero_id])[0]['total'] ?? 0;

// Ganancia Ventas Productos Hoy
$gananciaHoyVentas = query("
    SELECT SUM((IFNULL(cantidad * precio_unitario, 0) * $com_productos / 100)) as total
    FROM ventas_productos 
    WHERE usuario_id = ? AND DATE(fecha) = CURDATE()
", [$barbero_id])[0]['total'] ?? 0;

// Propinas Recibidas Hoy (Rubro Independiente)
$propinasHoy = floatval(query("
    SELECT SUM(IFNULL(propina, 0)) as total
    FROM citas 
    WHERE barbero_id = ? AND estado = 'completada' AND DATE(fecha_hora) = CURDATE()
", [$barbero_id])[0]['total'] ?? 0);

$gananciaServiciosVentasHoy = floatval($gananciaHoyServicios) + floatval($gananciaHoyVentas);
$miGananciaDia = $gananciaServiciosVentasHoy + $propinasHoy;

// Ganancia Mes (Servicios + Ventas + Propinas)
$monthStart = date('Y-m-01');
$monthEnd = date('Y-m-t');

$gananciaMesServicios = query("
    SELECT SUM((IFNULL(precio_final, 0) * (CASE WHEN DAYOFWEEK(fecha_hora) IN (1, 7) THEN $com_finde ELSE $com_diaria END) / 100)) as total
    FROM citas 
    WHERE barbero_id = ? AND estado = 'completada' AND DATE(fecha_hora) BETWEEN ? AND ?
", [$barbero_id, $monthStart, $monthEnd])[0]['total'] ?? 0;

$gananciaMesVentas = query("
    SELECT SUM((IFNULL(cantidad * precio_unitario, 0) * $com_productos / 100)) as total
    FROM ventas_productos 
    WHERE usuario_id = ? AND DATE(fecha) BETWEEN ? AND ?
", [$barbero_id, $monthStart, $monthEnd])[0]['total'] ?? 0;

// Propinas Recibidas Mes (Rubro Independiente)
$propinasMes = floatval(query("
    SELECT SUM(IFNULL(propina, 0)) as total
    FROM citas 
    WHERE barbero_id = ? AND estado = 'completada' AND DATE(fecha_hora) BETWEEN ? AND ?
", [$barbero_id, $monthStart, $monthEnd])[0]['total'] ?? 0);

$gananciaServiciosVentasMes = floatval($gananciaMesServicios) + floatval($gananciaMesVentas);
$miGananciaMes = $gananciaServiciosVentasMes + $propinasMes;

// Total de citas completadas hoy
$countHoy = query("
    SELECT COUNT(*) as total
    FROM citas 
    WHERE barbero_id = ? AND estado = 'completada' AND DATE(fecha_hora) = CURDATE()
", [$barbero_id]);
$totalCitasHoy = intval($countHoy[0]['total'] ?? 0);

// 2. Próximo Cliente (Garantiza incluir cualquier cita pendiente de HOY)
$nextClient = [];
try {
    $nextClient = query("
        SELECT c.*, s.nombre as servicio, s.duracion_minutos, cli.id as cliente_id_bd, cli.nombre as cliente, cli.telefono as cliente_telefono, cli.foto_perfil, cli.notas_barbero, cli.estilo_buscado, cli.ambiente_preferido, cli.bebida_preferida
        FROM citas c
        LEFT JOIN servicios s ON c.servicio_id = s.id
        LEFT JOIN clientes cli ON c.cliente_id = cli.id
        WHERE c.barbero_id = ? 
        AND c.estado IN ('pendiente', 'confirmada')
        AND (DATE(c.fecha_hora) = CURDATE() OR c.fecha_hora >= NOW())
        ORDER BY c.fecha_hora ASC 
        LIMIT 1
    ", [$barbero_id]);
} catch (Exception $exNext) {
    try {
        $nextClient = query("
            SELECT c.*, s.nombre as servicio, s.duracion_minutos, cli.id as cliente_id_bd, cli.nombre as cliente, cli.telefono as cliente_telefono, cli.foto_perfil
            FROM citas c
            LEFT JOIN servicios s ON c.servicio_id = s.id
            LEFT JOIN clientes cli ON c.cliente_id = cli.id
            WHERE c.barbero_id = ? 
            AND c.estado IN ('pendiente', 'confirmada')
            AND (DATE(c.fecha_hora) = CURDATE() OR c.fecha_hora >= NOW())
            ORDER BY c.fecha_hora ASC 
            LIMIT 1
        ", [$barbero_id]);
    } catch (Exception $exNext2) {
        $nextClient = [];
    }
}

$proximo = $nextClient ? $nextClient[0] : null;

// 3. Turnos de Hoy
$turnosHoy = query("
    SELECT c.*, s.nombre as servicio, s.duracion_minutos, cli.nombre as cliente, cli.telefono as cliente_telefono, cli.estilo_buscado, cli.ambiente_preferido, cli.bebida_preferida
    FROM citas c
    LEFT JOIN servicios s ON c.servicio_id = s.id
    LEFT JOIN clientes cli ON c.cliente_id = cli.id
    WHERE c.barbero_id = ? AND DATE(c.fecha_hora) = CURDATE()
    ORDER BY c.fecha_hora ASC
", [$barbero_id]);

// 4. Inventario de la Sucursal
$inventarioItems = query("SELECT id, producto, cantidad, precio FROM inventario WHERE sucursal_id = ? ORDER BY producto ASC", [$sucursal_id]);

$nombreBarbero = explode(' ', trim($currentUser['nombre']))[0];
$inicial_barbero = strtoupper(substr($nombreBarbero, 0, 1));
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title>Panel de Barbero - KORTZEN</title>

    <link rel="stylesheet" href="/css/variables.css?v=24">
    <link rel="stylesheet" href="/css/reset.css?v=24">
    <link rel="stylesheet" href="/css/pwa-native.css?v=52">

    <!-- Favicon & Touch Icons -->
    <link rel="icon" type="image/png" sizes="32x32" href="/assets/icons/favicon.png?v=10">
    <link rel="icon" type="image/png" sizes="16x16" href="/assets/icons/favicon.png?v=10">
    <link rel="shortcut icon" href="/assets/icons/favicon.png?v=10">
    <link rel="apple-touch-icon" sizes="180x180" href="/assets/icons/favicon.png?v=10">
    <script src="/js/pwa.js" defer></script>

    <style>
        .barber-stat-card {
            background: #FFFFFF;
            border: 1px solid #EAEAEA;
            border-radius: 16px;
            padding: 20px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.03);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }
        .barber-stat-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 12px;
        }
        .barber-stat-icon-box {
            width: 38px;
            height: 38px;
            border-radius: 10px;
            background: #F4F4F4;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #111111;
        }
        .barber-stat-label {
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #777777;
        }
        .barber-stat-val {
            font-size: 1.6rem;
            font-weight: 900;
            color: #111111;
            letter-spacing: -0.02em;
        }
        .barber-stat-sub {
            font-size: 0.78rem;
            color: #666666;
            margin-top: 4px;
        }
        .barber-section-card {
            background: #FFFFFF;
            border: 1px solid #EAEAEA;
            border-radius: 16px;
            padding: 24px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.03);
            margin-bottom: 24px;
        }
        .barber-section-title {
            font-size: 1.1rem;
            font-weight: 800;
            color: #111111;
            margin: 0 0 16px 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .barber-icon-stroke {
            width: 20px;
            height: 20px;
            stroke: #111111;
            stroke-width: 2;
            fill: none;
            stroke-linecap: round;
            stroke-linejoin: round;
        }
        .barber-input {
            width: 100%;
            padding: 12px 14px;
            border: 1px solid #D1D1D1;
            border-radius: 10px;
            background: #FAFAFA;
            color: #111111;
            font-size: 0.9rem;
            font-weight: 600;
            box-sizing: border-box;
            margin-bottom: 12px;
            transition: border-color 0.2s;
        }
        .barber-input:focus {
            outline: none;
            border-color: #000000;
            background: #FFFFFF;
        }
        .btn-action-black {
            width: 100%;
            background: #000000;
            color: #FFFFFF;
            border: none;
            padding: 13px 20px;
            border-radius: 10px;
            font-weight: 800;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: background 0.15s;
        }
        .btn-action-black:hover {
            background: #222222;
        }
        .badge-turno-clean {
            font-size: 0.72rem;
            font-weight: 800;
            text-transform: uppercase;
            padding: 4px 10px;
            border-radius: 20px;
            letter-spacing: 0.5px;
        }
        .badge-pendiente-clean { background: #F4F4F4; color: #111111; border: 1px solid #D1D1D1; }
        .badge-completada-clean { background: #111111; color: #FFFFFF; }
        .badge-cancelada-clean { background: #FAFAFA; color: #888888; border: 1px solid #EAEAEA; }

        .pwa-container {
            padding-top: calc(env(safe-area-inset-top, 24px) + 16px) !important;
        }

        /* Responsive Barber Header & Grids */
        .barber-header-bar {
            background: #FFFFFF;
            border: 1px solid #EAEAEA;
            border-radius: 16px;
            padding: 14px 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-top: 10px;
            margin-bottom: 24px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.03);
            gap: 12px;
        }
        .barber-header-logo {
            font-size: 1.25rem;
            font-weight: 900;
            letter-spacing: 2px;
            color: #111111;
            white-space: nowrap;
        }
        .barber-logout-btn {
            background: #111111;
            color: #FFFFFF;
            border: none;
            padding: 8px 14px;
            border-radius: 10px;
            font-weight: 700;
            font-size: 0.82rem;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 6px;
            white-space: nowrap;
            flex-shrink: 0;
            transition: background 0.15s;
        }
        .barber-stats-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 16px;
            margin-bottom: 24px;
        }
        .barber-forms-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 24px;
            margin-bottom: 24px;
        }

        @media (max-width: 768px) {
            .pwa-container {
                padding-top: calc(env(safe-area-inset-top, 28px) + 24px) !important;
            }
            .barber-header-bar {
                margin-top: 14px;
                padding: 12px 14px;
                border-radius: 14px;
                margin-bottom: 16px;
            }
            .barber-header-logo {
                font-size: 1rem;
                letter-spacing: 1px;
            }
            .barber-logout-btn {
                padding: 7px 11px;
                font-size: 0.76rem;
            }
            .barber-stats-grid {
                grid-template-columns: repeat(3, 1fr);
                gap: 8px;
                margin-bottom: 16px;
            }
            .barber-stat-card {
                padding: 10px 8px;
                border-radius: 12px;
            }
            .barber-stat-label {
                font-size: 0.62rem;
                letter-spacing: 0;
            }
            .barber-stat-icon-box {
                width: 28px;
                height: 28px;
            }
            .barber-stat-val {
                font-size: 1.15rem;
            }
            .barber-stat-sub {
                font-size: 0.65rem;
            }
            .barber-forms-grid {
                grid-template-columns: 1fr;
                gap: 16px;
                margin-bottom: 16px;
            }
            .barber-forms-grid .barber-section-card {
                margin-bottom: 0 !important;
            }
        }
        @media (max-width: 480px) {
            .barber-stats-grid {
                grid-template-columns: 1fr;
                gap: 10px;
            }
            .barber-stat-card {
                padding: 14px 16px;
            }
            .barber-stat-val {
                font-size: 1.4rem;
            }
        }
    </style>
</head>

<body class="pwa-app-mode">

    <div class="pwa-container">
        <!-- Header Exclusivo de Barbero -->
        <header class="barber-header-bar">
            <div class="barber-header-logo">KORTZEN</div>
            <div>
                <a href="logout.php" class="barber-logout-btn">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg>
                    <span>Cerrar Sesión</span>
                </a>
            </div>
        </header>

        <!-- Saludo & Avatar Barbero -->
        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 24px; margin-top: 10px;">
            <div>
                <h1 class="pwa-greeting" style="font-size: 1.6rem; font-weight: 900; color: #111111; margin: 0;">Hola, <?php echo htmlspecialchars($nombreBarbero); ?></h1>
                <p style="color: #666666; margin-top: 4px; font-size: 0.9rem;">Barbero Profesional • KORTZEN</p>
            </div>
            <div style="width: 48px; height: 48px; border-radius: 50%; background: #111111; color: #FFFFFF; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 1.2rem; flex-shrink: 0; box-shadow: 0 4px 12px rgba(0,0,0,0.1);">
                <?php echo $inicial_barbero; ?>
            </div>
        </div>

        <?php if ($mensaje): ?>
            <div style="background: #111111; color: #FFFFFF; padding: 14px 18px; border-radius: 12px; font-size: 0.88rem; margin-bottom: 24px; font-weight: 700; display: flex; align-items: center; gap: 10px;">
                <svg class="barber-icon-stroke" style="stroke: #FFFFFF;" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"></polyline></svg>
                <span><?php echo htmlspecialchars($mensaje); ?></span>
            </div>
        <?php endif; ?>

        <!-- Tarjetas de Métricas de Ganancias (Grid Minimalista de 4 columnas) -->
        <div class="barber-stats-grid">
            
            <div class="barber-stat-card">
                <div class="barber-stat-header">
                    <span class="barber-stat-label">Ganancias Totales Hoy</span>
                    <div class="barber-stat-icon-box">
                        <svg class="barber-icon-stroke" viewBox="0 0 24 24"><line x1="12" y1="1" x2="12" y2="23"></line><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path></svg>
                    </div>
                </div>
                <div class="barber-stat-val">$<?php echo number_format($miGananciaDia, 2); ?></div>
                <div class="barber-stat-sub">Servicios + Ventas + Propinas</div>
            </div>

            <!-- RUBRO APARTE DE PROPINAS -->
            <div class="barber-stat-card" style="border: 1.5px solid #10B981; background: #F0FDF4;">
                <div class="barber-stat-header">
                    <span class="barber-stat-label" style="color: #047857; font-weight: 800;">Propinas (Rubro Aparte)</span>
                    <div class="barber-stat-icon-box" style="color: #047857;">
                        <svg class="barber-icon-stroke" viewBox="0 0 24 24"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path></svg>
                    </div>
                </div>
                <div class="barber-stat-val" style="color: #065F46;">+$<?php echo number_format($propinasHoy, 2); ?></div>
                <div class="barber-stat-sub" style="color: #047857; font-weight: 700;">Acumulado Mes: +$<?php echo number_format($propinasMes, 2); ?></div>
            </div>

            <div class="barber-stat-card">
                <div class="barber-stat-header">
                    <span class="barber-stat-label">Ganancias Mes</span>
                    <div class="barber-stat-icon-box">
                        <svg class="barber-icon-stroke" viewBox="0 0 24 24"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"></polyline><polyline points="17 6 23 6 23 12"></polyline></svg>
                    </div>
                </div>
                <div class="barber-stat-val">$<?php echo number_format($miGananciaMes, 2); ?></div>
                <div class="barber-stat-sub">Total acumulado del mes</div>
            </div>

            <div class="barber-stat-card">
                <div class="barber-stat-header">
                    <span class="barber-stat-label">Cortes de Hoy</span>
                    <div class="barber-stat-icon-box">
                        <svg class="barber-icon-stroke" viewBox="0 0 24 24"><circle cx="6" cy="6" r="3"></circle><circle cx="6" cy="18" r="3"></circle><line x1="20" y1="4" x2="8.12" y2="15.88"></line><line x1="14.47" y1="14.47" x2="20" y2="20"></line><line x1="8.12" y1="8.12" x2="12" y2="12"></line></svg>
                    </div>
                </div>
                <div class="barber-stat-val"><?php echo $totalCitasHoy; ?></div>
                <div class="barber-stat-sub">Servicios completados</div>
            </div>

        </div>

        <!-- Próximo Cliente Widget -->
        <div class="barber-section-card">
            <h3 class="barber-section-title">
                <svg class="barber-icon-stroke" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
                <span>Próximo Cliente</span>
            </h3>

            <?php if ($proximo): 
                $tsProximo = strtotime($proximo['fecha_hora']);
                $esHoy = (date('Y-m-d', $tsProximo) === date('Y-m-d'));
                $fechaLegible = $esHoy ? 'Hoy (' . date('d/m/Y', $tsProximo) . ')' : date('d/m/Y', $tsProximo);
                $horaProxima = date('H:i', $tsProximo);
            ?>
            <div style="background: #FAFAFA; border: 1px solid #EEEEEE; border-radius: 14px; padding: 18px; position: relative;">
                <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 12px; flex-wrap: wrap; gap: 10px;">
                    <div>
                        <span style="font-size: 0.75rem; font-weight: 700; color: #777777; text-transform: uppercase; letter-spacing: 1px;">FECHA Y HORA</span>
                        <div style="font-size: 1.3rem; font-weight: 900; color: #111111; margin-top: 2px;"><?php echo $fechaLegible; ?> • <?php echo $horaProxima; ?></div>
                    </div>
                    <div style="text-align: right;">
                        <span style="font-size: 0.75rem; font-weight: 700; color: #777777; text-transform: uppercase; letter-spacing: 1px;">SERVICIO</span>
                        <div style="font-size: 1.1rem; font-weight: 800; color: #111111; margin-top: 2px;"><?php echo htmlspecialchars($proximo['servicio'] ?? 'Corte de Autor'); ?></div>
                    </div>
                </div>

                <div style="border-top: 1px solid #EAEAEA; padding-top: 14px; margin-bottom: 14px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 8px;">
                    <div>
                        <span style="font-size: 0.8rem; color: #666666;">Cliente:</span>
                        <strong style="color: #111111; font-size: 0.95rem; margin-left: 4px;"><?php echo htmlspecialchars($proximo['cliente'] ?? 'Cliente'); ?></strong>
                    </div>
                    <?php if (!empty($proximo['asistencia_confirmada'])): ?>
                        <div style="background: #ECFDF5; border: 1.5px solid #10B981; color: #047857; padding: 4px 10px; border-radius: 6px; font-weight: 800; font-size: 0.75rem; display: flex; align-items: center; gap: 4px;">
                            <i class="fas fa-check-circle" style="color: #10B981;"></i> CLIENTE CONFIRMÓ ASISTENCIA
                        </div>
                    <?php else: ?>
                        <div style="background: #FFFBEB; border: 1px solid #F59E0B; color: #B45309; padding: 4px 10px; border-radius: 6px; font-weight: 700; font-size: 0.72rem;">
                            Pendiente confirmación de cliente
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Tarjeta de Preferencias del Cliente (3 preguntas respondidas) -->
                <?php if (!empty($proximo['estilo_buscado']) || !empty($proximo['ambiente_preferido']) || !empty($proximo['bebida_preferida'])): ?>
                    <div style="background: #FAFAFA; border: 1px solid #EAEAEA; border-radius: 12px; padding: 14px; margin-bottom: 16px;">
                        <div style="font-size: 0.72rem; font-weight: 900; color: #111111; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 8px;">
                            PREFERENCIAS DEL CLIENTE:
                        </div>
                        <?php if (!empty($proximo['estilo_buscado'])): ?>
                            <div style="font-size: 0.82rem; color: #444444; margin-bottom: 4px;">
                                <strong>• Estilo buscado:</strong> <?php echo htmlspecialchars($proximo['estilo_buscado']); ?>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($proximo['ambiente_preferido'])): ?>
                            <div style="font-size: 0.82rem; color: #444444; margin-bottom: 4px;">
                                <strong>• Experiencia / Ambiente:</strong> <?php echo htmlspecialchars($proximo['ambiente_preferido']); ?>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($proximo['bebida_preferida'])): ?>
                            <div style="font-size: 0.82rem; color: #444444;">
                                <strong>• Bebida deseada:</strong> <?php echo htmlspecialchars($proximo['bebida_preferida']); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <!-- Formulario de Notas Privadas del Cliente -->
                <form action="api/clientes_action.php" method="POST" style="margin-bottom: 16px; background: #FFFFFF; border: 1px solid #EAEAEA; border-radius: 10px; padding: 12px;">
                    <input type="hidden" name="action" value="guardar_notas_barbero">
                    <input type="hidden" name="cliente_id" value="<?php echo $proximo['cliente_id_bd'] ?? $proximo['cliente_id']; ?>">
                    <label style="font-size: 0.72rem; font-weight: 800; color: #555555; text-transform: uppercase; display: block; margin-bottom: 6px;">
                        Notas de Preferencias del Cliente (Privado)
                    </label>
                    <textarea name="notas_barbero" placeholder="Ej: Degradado bajo #1.5, tijera arriba, raya al lado izquierdo..." style="width: 100%; height: 50px; border: 1px solid #DDD; border-radius: 8px; padding: 8px; font-size: 0.82rem; font-family: inherit; resize: none; box-sizing: border-box; background: #FAFAFA;"><?php echo htmlspecialchars($proximo['notas_barbero'] ?? ''); ?></textarea>
                    <button type="submit" style="margin-top: 6px; background: #111111; color: #FFFFFF; border: none; padding: 6px 14px; border-radius: 6px; font-size: 0.75rem; font-weight: 800; cursor: pointer; text-transform: uppercase;">
                        Guardar Notas
                    </button>
                </form>

                <div style="background: #F9FAFB; border: 1px dashed #D1D5DB; border-radius: 10px; padding: 12px; text-align: center;">
                    <span style="font-size: 0.8rem; font-weight: 800; color: #4B5563;">🔒 Finalización de servicio y cobro a cargo del administrador del local.</span>
                </div>
            </div>
            <?php else: ?>
            <div style="text-align: center; padding: 24px; background: #FAFAFA; border: 1px solid #EEEEEE; border-radius: 14px;">
                <svg class="barber-icon-stroke" style="width: 32px; height: 32px; margin-bottom: 8px;" viewBox="0 0 24 24"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
                <div style="font-size: 1rem; font-weight: 800; color: #111111;">No tienes turnos pendientes por hoy</div>
                <div style="font-size: 0.85rem; color: #666666; margin-top: 4px;">Tus ganancias se encuentran actualizadas en los paneles superiores.</div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Grid de 4 Cajas en 2x2 -->
        <div class="barber-forms-grid">
            
            <!-- Caja 1: Registrar Venta de Producto (Fila 1, Izquierda) -->
            <div class="barber-section-card" style="margin-bottom: 0;">
                <h3 class="barber-section-title">
                    <svg class="barber-icon-stroke" viewBox="0 0 24 24"><path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"></path><line x1="3" y1="6" x2="21" y2="6"></line><path d="M16 10a4 4 0 0 1-8 0"></path></svg>
                    <span>Registrar Venta de Producto</span>
                </h3>
                <p style="font-size: 0.85rem; color: #666666; margin-bottom: 16px; line-height: 1.4;">
                    Suma comisiones instantáneas al vender productos de barbería a tus clientes.
                </p>

                <form id="formVentaProducto" onsubmit="registrarVentaProductoBarbero(event)">
                    <label class="config-label" style="font-size: 0.78rem;">Producto Vendido</label>
                    <select name="producto_id" class="barber-input" required>
                        <option value="">-- Seleccionar producto del inventario --</option>
                        <?php foreach ($inventarioItems as $item): ?>
                            <option value="<?php echo $item['id']; ?>">
                                <?php echo htmlspecialchars($item['producto']); ?> (Stock: <?php echo $item['cantidad']; ?>) - $<?php echo number_format($item['precio'], 2); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <div style="display: flex; gap: 12px;">
                        <div style="flex: 1;">
                            <label class="config-label" style="font-size: 0.78rem;">Cantidad</label>
                            <input type="number" name="cantidad" value="1" min="1" class="barber-input" required>
                        </div>
                        <div style="flex: 2; display: flex; align-items: flex-end; margin-bottom: 12px;">
                            <button type="submit" class="btn-action-black">
                                <span>Registrar Venta</span>
                            </button>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Caja 2: Consumo de Insumos del Turno (Fila 1, Derecha) -->
            <div class="barber-section-card" style="margin-bottom: 0;">
                <h3 class="barber-section-title">
                    <svg class="barber-icon-stroke" viewBox="0 0 24 24"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path><polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline><line x1="12" y1="22.08" x2="12" y2="12"></line></svg>
                    <span>Consumo de Insumos del Turno</span>
                </h3>
                <p style="font-size: 0.85rem; color: #666666; margin-bottom: 16px; line-height: 1.4;">
                    Registra los materiales gastados al final del turno para mantener actualizado el inventario.
                </p>

                <form id="formConsumoInsumo" onsubmit="descontarInsumoBarbero(event)">
                    <label class="config-label" style="font-size: 0.78rem;">Insumo / Material Gastado</label>
                    <select name="producto_id" class="barber-input" required>
                        <option value="">-- Seleccionar insumo a debitar --</option>
                        <?php foreach ($inventarioItems as $item): ?>
                            <option value="<?php echo $item['id']; ?>">
                                <?php echo htmlspecialchars($item['producto']); ?> (Quedan: <?php echo $item['cantidad']; ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <div style="display: flex; gap: 12px;">
                        <div style="flex: 1;">
                            <label class="config-label" style="font-size: 0.78rem;">Cantidad Gastada</label>
                            <input type="number" name="cantidad" value="1" min="1" class="barber-input" required>
                        </div>
                        <div style="flex: 2; display: flex; align-items: flex-end; margin-bottom: 12px;">
                            <button type="submit" class="btn-action-black" style="background: #FFFFFF; color: #111111; border: 1px solid #111111;">
                                <span>Debitar Insumo</span>
                            </button>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Caja 3: Bloqueo Rápido de Descanso (Fila 2, Izquierda) -->
            <div class="barber-section-card" style="margin-bottom: 0;">
                <h3 class="barber-section-title">
                    <svg class="barber-icon-stroke" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
                    <span>Bloqueo Rápido de Descanso</span>
                </h3>
                <p style="font-size: 0.85rem; color: #666666; margin-bottom: 16px; line-height: 1.4;">
                    Bloquea tu horario de almuerzo o pausa personal para evitar reservas en esa hora.
                </p>

                <form method="POST" action="api/block_time_action.php">
                    <input type="hidden" name="action" value="bloquear_descanso_barbero">
                    <label class="config-label" style="font-size: 0.78rem;">Hora de Inicio del Descanso</label>
                    <select name="hora_inicio" class="barber-input" required>
                        <option value="">-- Seleccionar hora --</option>
                        <option value="12:00">12:00 PM</option>
                        <option value="13:00">01:00 PM</option>
                        <option value="14:00">02:00 PM</option>
                        <option value="15:00">03:00 PM</option>
                        <option value="16:00">04:00 PM</option>
                    </select>

                    <div style="display: flex; gap: 12px;">
                        <div style="flex: 1;">
                            <label class="config-label" style="font-size: 0.78rem;">Duración</label>
                            <select name="duracion_minutos" class="barber-input">
                                <option value="30">30 Minutos</option>
                                <option value="60" selected>1 Hora</option>
                            </select>
                        </div>
                        <div style="flex: 1; display: flex; align-items: flex-end; margin-bottom: 12px;">
                            <button type="submit" class="btn-action-black">
                                <span>Bloquear Horario</span>
                            </button>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Caja 4: Mis Turnos de Hoy (Fila 2, Derecha) -->
            <div class="barber-section-card" style="margin-bottom: 0;">
                <h3 class="barber-section-title">
                    <svg class="barber-icon-stroke" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
                    <span>Mis Turnos de Hoy (<?php echo date('d/m/Y'); ?>)</span>
                </h3>

                <div style="display: flex; flex-direction: column; gap: 12px;">
                    <?php if (!empty($turnosHoy)): ?>
                        <?php foreach ($turnosHoy as $t): 
                            $horaT = date('H:i', strtotime($t['fecha_hora']));
                            $est = strtolower($t['estado']);
                            $badgeClass = 'badge-pendiente-clean';
                            if ($est === 'completada') $badgeClass = 'badge-completada-clean';
                            if ($est === 'cancelada') $badgeClass = 'badge-cancelada-clean';
                        ?>
                        <div style="background: #FAFAFA; border: 1px solid #EEEEEE; border-radius: 12px; padding: 12px 14px; display: flex; align-items: center; justify-content: space-between;">
                            <div style="font-weight: 900; font-size: 1.1rem; color: #111111; width: 55px;">
                                <?php echo $horaT; ?>
                            </div>
                            <div style="flex: 1; padding: 0 10px;">
                                <div style="font-weight: 800; font-size: 0.9rem; color: #111111; margin-bottom: 2px;">
                                    <?php echo htmlspecialchars($t['servicio'] ?? 'Corte'); ?>
                                </div>
                                <div style="font-size: 0.8rem; color: #666666; display: flex; align-items: center; gap: 8px; flex-wrap: wrap; margin-top: 2px;">
                                    <span>Cliente: <strong style="color: #111111;"><?php echo htmlspecialchars($t['cliente'] ?? 'Cliente'); ?></strong></span>
                                    <?php if (!empty($t['asistencia_confirmada'])): ?>
                                        <span style="background: #ECFDF5; color: #047857; border: 1px solid #10B981; font-weight: 800; font-size: 0.68rem; padding: 2px 7px; border-radius: 4px;">
                                            ✓ CONFIRMADO
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div>
                                <?php if ($est === 'pendiente' || $est === 'confirmada'): ?>
                                    <form method="POST" style="margin: 0;">
                                        <input type="hidden" name="action" value="completar_cita">
                                        <input type="hidden" name="cita_id" value="<?php echo $t['id']; ?>">
                                        <button type="submit" style="background: #111111; color: #FFFFFF; border: none; padding: 7px 12px; border-radius: 8px; font-weight: 800; font-size: 0.72rem; cursor: pointer; text-transform: uppercase; letter-spacing: 0.5px;">
                                            Finalizar Corte
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <span class="badge-turno-clean <?php echo $badgeClass; ?>"><?php echo strtoupper($est); ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div style="text-align: center; padding: 24px; color: #777777; font-size: 0.88rem; background: #FAFAFA; border-radius: 12px;">
                            No tienes agendamientos registrados para el día de hoy.
                        </div>
                    <?php endif; ?>
                </div>
            </div>

        </div>

    </div>

    <script>
        async function registrarVentaProductoBarbero(e) {
            e.preventDefault();
            const form = e.target;
            const formData = new FormData(form);
            formData.append('action', 'registrar_venta');

            try {
                const res = await fetch('/api/barbero_inventario_action.php', {
                    method: 'POST',
                    body: formData
                });
                const data = await res.json();
                alert(data.message);
                if (data.success) {
                    window.location.reload();
                }
            } catch (err) {
                alert('Error al registrar la venta');
            }
        }

        async function descontarInsumoBarbero(e) {
            e.preventDefault();
            const form = e.target;
            const formData = new FormData(form);
            formData.append('action', 'descontar_insumo');

            try {
                const res = await fetch('/api/barbero_inventario_action.php', {
                    method: 'POST',
                    body: formData
                });
                const data = await res.json();
                alert(data.message);
                if (data.success) {
                    window.location.reload();
                }
            } catch (err) {
                alert('Error al debitar insumo');
            }
        }
    </script>

</body>
</html>
