<?php
/**
 * KORTZEN - Dashboard Exclusivo para Barberos
 * Interfaz única para barberos: ganancias, turnos de hoy, agenda y gestión de estado
 */

require_once 'config.php';
requireLogin();

$currentUser = getCurrentUser();

// Si el usuario es admin general, permitir navegar o redirigir a dashboard.php si prefiere admin
if ($currentUser['rol'] !== 'barbero' && $currentUser['rol'] !== 'admin_local' && $currentUser['rol'] !== 'admin') {
    header('Location: dashboard.php');
    exit;
}

$barbero_id = $currentUser['id'];

// Mensajes de estado
$mensaje = '';
$tipoMensaje = '';

// Marcar cita como completada si se presiona el botón
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'completar_cita') {
        $citaId = intval($_POST['cita_id'] ?? 0);
        if ($citaId > 0) {
            try {
                $pdo = getConnection();
                $stmt = $pdo->prepare("UPDATE citas SET estado = 'completada' WHERE id = ? AND barbero_id = ?");
                $stmt->execute([$citaId, $barbero_id]);
                $mensaje = '¡Cita marcada como COMPLETADA! Se han acreditado tus ganancias.';
                $tipoMensaje = 'success';
            } catch (Exception $e) {
                $mensaje = 'Error al actualizar cita.';
                $tipoMensaje = 'error';
            }
        }
    }
}

// 1. Ganancias con doble tasa de comisión (Semana vs Fin de Semana)
$com_diaria = floatval($currentUser['comision_porcentaje'] ?? 50);
$com_finde = floatval($currentUser['comision_fin_semana'] ?? 50);

$sqlGanancia = "
    SUM(
        (IFNULL(precio_final, 0) * (CASE WHEN DAYOFWEEK(fecha_hora) IN (1, 7) THEN $com_finde ELSE $com_diaria END) / 100)
    ) as total_ganancia
";

// 1.1 Ganancia Hoy
$gananciaHoy = query("
    SELECT $sqlGanancia
    FROM citas 
    WHERE barbero_id = ? AND estado = 'completada' AND DATE(fecha_hora) = CURDATE()
", [$barbero_id]);
$miGananciaDia = floatval($gananciaHoy[0]['total_ganancia'] ?? 0);

// 1.2 Ganancia Mes
$monthStart = date('Y-m-01');
$monthEnd = date('Y-m-t');
$gananciaMes = query("
    SELECT $sqlGanancia
    FROM citas 
    WHERE barbero_id = ? AND estado = 'completada' AND DATE(fecha_hora) BETWEEN ? AND ?
", [$barbero_id, $monthStart, $monthEnd]);
$miGananciaMes = floatval($gananciaMes[0]['total_ganancia'] ?? 0);

// 1.3 Total de citas completadas hoy
$countHoy = query("
    SELECT COUNT(*) as total
    FROM citas 
    WHERE barbero_id = ? AND estado = 'completada' AND DATE(fecha_hora) = CURDATE()
", [$barbero_id]);
$totalCitasHoy = intval($countHoy[0]['total'] ?? 0);

// 2. Próximo Cliente
$nextClient = query("
    SELECT c.*, s.nombre as servicio, s.duracion_minutos, cli.nombre as cliente, cli.telefono as cliente_telefono, cli.foto_perfil
    FROM citas c
    LEFT JOIN servicios s ON c.servicio_id = s.id
    LEFT JOIN clientes cli ON c.cliente_id = cli.id
    WHERE c.barbero_id = ? 
    AND c.fecha_hora >= NOW()
    AND c.estado IN ('pendiente', 'confirmada')
    ORDER BY c.fecha_hora ASC 
    LIMIT 1
", [$barbero_id]);

$proximo = $nextClient ? $nextClient[0] : null;

// 3. Turnos de Hoy (Lista de citas programadas para hoy)
$turnosHoy = query("
    SELECT c.*, s.nombre as servicio, s.duracion_minutos, cli.nombre as cliente, cli.telefono as cliente_telefono
    FROM citas c
    LEFT JOIN servicios s ON c.servicio_id = s.id
    LEFT JOIN clientes cli ON c.cliente_id = cli.id
    WHERE c.barbero_id = ? AND DATE(c.fecha_hora) = CURDATE()
    ORDER BY c.fecha_hora ASC
", [$barbero_id]);

$inicial_barbero = strtoupper(substr($currentUser['nombre'], 0, 1));
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Panel de Barbero - KORTZEN</title>
    <link rel="stylesheet" href="/css/variables.css?v=23">
    <link rel="stylesheet" href="/css/reset.css?v=23">

    <style>
        :root {
            --barber-bg: #0C0C0C;
            --barber-card: #161616;
            --barber-border: rgba(255, 255, 255, 0.08);
            --barber-accent: #FFFFFF;
            --barber-gold: #D4AF37;
            --barber-text: #F0F0F0;
            --barber-muted: #888888;
        }

        body {
            background-color: var(--barber-bg);
            color: var(--barber-text);
            font-family: 'Plus Jakarta Sans', system-ui, -apple-system, sans-serif;
            margin: 0;
            padding-bottom: 40px;
        }

        .barber-container {
            max-width: 600px;
            margin: 0 auto;
            padding: 1.25rem 1rem;
        }

        /* Top Header */
        .barber-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid var(--barber-border);
        }

        .barber-profile {
            display: flex;
            align-items: center;
            gap: 0.85rem;
        }

        .barber-avatar {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            background: #222222;
            border: 2px solid var(--barber-gold);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 1.2rem;
            color: #FFFFFF;
        }

        .barber-name {
            font-weight: 700;
            font-size: 1.1rem;
            color: #FFFFFF;
        }

        .barber-role {
            font-size: 0.78rem;
            color: var(--barber-gold);
            font-weight: 500;
        }

        .logout-link {
            color: #ff4d4d;
            text-decoration: none;
            font-size: 0.8rem;
            font-weight: 600;
            border: 1px solid rgba(255, 77, 77, 0.3);
            padding: 0.4rem 0.8rem;
            border-radius: 8px;
        }

        /* Earnings Cards Grid */
        .metrics-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 0.85rem;
            margin-bottom: 1.5rem;
        }

        .metric-card {
            background: var(--barber-card);
            border: 1px solid var(--barber-border);
            border-radius: 16px;
            padding: 1.25rem 1rem;
        }

        .metric-title {
            font-size: 0.72rem;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: var(--barber-muted);
            margin-bottom: 0.4rem;
        }

        .metric-value {
            font-size: 1.6rem;
            font-weight: 800;
            color: var(--barber-gold);
        }

        .metric-sub {
            font-size: 0.75rem;
            color: var(--barber-muted);
            margin-top: 0.2rem;
        }

        /* Next Client Banner */
        .section-title {
            font-size: 0.9rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: #FFFFFF;
            margin-bottom: 0.85rem;
        }

        .next-client-box {
            background: linear-gradient(135deg, #1A1A1A 0%, #242424 100%);
            border: 1px solid var(--barber-gold);
            border-radius: 18px;
            padding: 1.35rem;
            margin-bottom: 1.75rem;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.4);
        }

        .next-client-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.75rem;
        }

        .next-time-pill {
            background: var(--barber-gold);
            color: #000000;
            font-weight: 800;
            padding: 0.4rem 0.85rem;
            border-radius: 20px;
            font-size: 0.85rem;
        }

        .next-service-title {
            font-size: 1.2rem;
            font-weight: 700;
            color: #FFFFFF;
            margin-bottom: 0.25rem;
        }

        .next-client-name {
            font-size: 0.9rem;
            color: var(--barber-muted);
            margin-bottom: 1rem;
        }

        .btn-complete {
            width: 100%;
            padding: 0.9rem;
            background: #2ECC71;
            color: #FFFFFF;
            border: none;
            border-radius: 10px;
            font-weight: 700;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            cursor: pointer;
        }

        /* Shift Items List */
        .turnos-list {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
        }

        .turno-card {
            background: var(--barber-card);
            border: 1px solid var(--barber-border);
            border-radius: 14px;
            padding: 1rem 1.15rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .turno-time {
            font-weight: 800;
            font-size: 1.1rem;
            color: var(--barber-gold);
            width: 65px;
            flex-shrink: 0;
        }

        .turno-info {
            flex: 1;
            padding: 0 0.75rem;
        }

        .turno-service {
            font-weight: 700;
            font-size: 0.95rem;
            color: #FFFFFF;
            margin-bottom: 0.15rem;
        }

        .turno-client {
            font-size: 0.8rem;
            color: var(--barber-muted);
        }

        .badge-status {
            font-size: 0.7rem;
            font-weight: 700;
            text-transform: uppercase;
            padding: 0.35rem 0.65rem;
            border-radius: 6px;
        }

        .badge-pendiente { background: rgba(212, 175, 55, 0.2); color: var(--barber-gold); }
        .badge-completada { background: rgba(46, 204, 113, 0.2); color: #2ECC71; }
        .badge-cancelada { background: rgba(231, 76, 60, 0.2); color: #E74C3C; }
    </style>
</head>

<body>

    <div class="barber-container">
        <!-- Top Profile Bar -->
        <div class="barber-header">
            <div class="barber-profile">
                <div class="barber-avatar">
                    <?php if (!empty($currentUser['foto_url'])): ?>
                        <img src="<?php echo htmlspecialchars($currentUser['foto_url']); ?>" style="width: 100%; height: 100%; border-radius: 50%; object-fit: cover;">
                    <?php else: ?>
                        <?php echo $inicial_barbero; ?>
                    <?php endif; ?>
                </div>
                <div>
                    <div class="barber-name"><?php echo htmlspecialchars($currentUser['nombre']); ?></div>
                    <div class="barber-role">✦ Barbero Profesional KORTZEN</div>
                </div>
            </div>
            <a href="logout.php" class="logout-link">Salir</a>
        </div>

        <?php if ($mensaje): ?>
            <div style="background: rgba(46, 204, 113, 0.15); border: 1px solid #2ECC71; color: #2ECC71; padding: 0.85rem 1rem; border-radius: 12px; font-size: 0.85rem; margin-bottom: 1.25rem; font-weight: 600;">
                ✓ <?php echo htmlspecialchars($mensaje); ?>
            </div>
        <?php endif; ?>

        <!-- Metrics Grid (Mis Ganancias) -->
        <div class="metrics-grid">
            <div class="metric-card">
                <div class="metric-title">Mis Ganancias (Hoy)</div>
                <div class="metric-value">$<?php echo number_format($miGananciaDia, 2); ?></div>
                <div class="metric-sub"><?php echo $totalCitasHoy; ?> cortes realizados hoy</div>
            </div>
            <div class="metric-card">
                <div class="metric-title">Mis Ganancias (Mes)</div>
                <div class="metric-value">$<?php echo number_format($miGananciaMes, 2); ?></div>
                <div class="metric-sub">Comisión estimada acumulada</div>
            </div>
        </div>

        <!-- Next Client Box -->
        <div class="section-title">Próximo Cliente</div>
        <?php if ($proximo): 
            $horaProxima = date('H:i', strtotime($proximo['fecha_hora']));
        ?>
        <div class="next-client-box">
            <div class="next-client-header">
                <span class="next-time-pill">⏰ HOY <?php echo $horaProxima; ?></span>
                <span style="font-size: 0.75rem; color: #888;"><?php echo $proximo['duracion_minutos'] ?? 30; ?> min</span>
            </div>
            <div class="next-service-title"><?php echo htmlspecialchars($proximo['servicio'] ?? 'Corte de Autor'); ?></div>
            <div class="next-client-name">Cliente: <strong><?php echo htmlspecialchars($proximo['cliente'] ?? 'Cliente'); ?></strong> <?php echo !empty($proximo['cliente_telefono']) ? ' • 📞 ' . htmlspecialchars($proximo['cliente_telefono']) : ''; ?></div>
            
            <form method="POST">
                <input type="hidden" name="action" value="completar_cita">
                <input type="hidden" name="cita_id" value="<?php echo $proximo['id']; ?>">
                <button type="submit" class="btn-complete">✓ MARCAR SERVICIO COMO COMPLETADO</button>
            </form>
        </div>
        <?php else: ?>
        <div class="next-client-box" style="text-align: center; border-color: var(--barber-border);">
            <div style="font-size: 1rem; color: #FFFFFF; font-weight: 700;">No tienes más turnos pendientes por hoy</div>
            <div style="font-size: 0.8rem; color: var(--barber-muted); margin-top: 0.3rem;">¡Excelente trabajo! Tus ganancias de hoy se encuentran actualizadas arriba.</div>
        </div>
        <?php endif; ?>

        <!-- Turnos de Hoy List -->
        <div class="section-title">Mis Turnos de Hoy (<?php echo date('d/m/Y'); ?>)</div>
        <div class="turnos-list">
            <?php if (!empty($turnosHoy)): ?>
                <?php foreach ($turnosHoy as $t): 
                    $horaT = date('H:i', strtotime($t['fecha_hora']));
                    $est = strtolower($t['estado']);
                    $badgeClass = 'badge-pendiente';
                    if ($est === 'completada') $badgeClass = 'badge-completada';
                    if ($est === 'cancelada') $badgeClass = 'badge-cancelada';
                ?>
                <div class="turno-card">
                    <div class="turno-time"><?php echo $horaT; ?></div>
                    <div class="turno-info">
                        <div class="turno-service"><?php echo htmlspecialchars($t['servicio'] ?? 'Corte'); ?></div>
                        <div class="turno-client"><?php echo htmlspecialchars($t['cliente'] ?? 'Cliente'); ?></div>
                    </div>
                    <div>
                        <span class="badge-status <?php echo $badgeClass; ?>"><?php echo strtoupper($est); ?></span>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="turno-card" style="text-align: center; justify-content: center; color: var(--barber-muted); font-size: 0.85rem; padding: 2rem;">
                    No tienes agendamientos para el día de hoy.
                </div>
            <?php endif; ?>
        </div>
    </div>

</body>
</html>
