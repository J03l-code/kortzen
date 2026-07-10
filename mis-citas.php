<?php
session_start();
require_once 'config.php';

if (!isClienteLoggedIn()) {
    header('Location: cliente-login.php');
    exit;
}

$cliente = getCurrentCliente();
$pdo = getConnection();

// Obtener citas futuras
$sqlFuturas = "SELECT c.*, s.nombre as servicio, u.nombre as barbero, suc.nombre as sucursal 
               FROM citas c
               JOIN servicios s ON c.servicio_id = s.id
               JOIN usuarios u ON c.barbero_id = u.id
               JOIN sucursales suc ON c.sucursal_id = suc.id
               WHERE c.cliente_id = ? AND c.fecha_hora >= NOW() AND c.estado != 'cancelada'
               ORDER BY c.fecha_hora ASC";
$citasFuturas = query($sqlFuturas, [$cliente['id']]);

// Obtener historial
$sqlHistorial = "SELECT c.*, s.nombre as servicio, u.nombre as barbero 
                 FROM citas c
                 JOIN servicios s ON c.servicio_id = s.id
                 JOIN usuarios u ON c.barbero_id = u.id
                 WHERE c.cliente_id = ? AND (c.fecha_hora < NOW() OR c.estado = 'cancelada')
                 ORDER BY c.fecha_hora DESC LIMIT 10";
$citasHistorial = query($sqlHistorial, [$cliente['id']]);
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mis Citas - KORTZEN</title>
    <link rel="stylesheet" href="/css/variables.css">
    <link rel="stylesheet" href="/css/reset.css">
    <link rel="stylesheet" href="/css/base.css">

    <!-- PWA Manifest & Meta Tags -->
    <link rel="manifest" href="/manifest.json">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="KORTZEN">
    <link rel="apple-touch-icon" href="/assets/icons/favicon.png">
    <script src="/js/pwa.js" defer></script>
    <style>
        body {
            background-color: #0A0A0A;
            color: #fff;
            font-family: 'Outfit', sans-serif;
            padding: 20px;
        }

        .container {
            max-width: 800px;
            margin: 0 auto;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }

        .title {
            font-size: 2rem;
            color: #FFFFFF;
        }

        .back-btn {
            color: #888;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .section-title {
            font-size: 1.2rem;
            margin: 30px 0 15px;
            border-bottom: 1px solid #333;
            padding-bottom: 10px;
        }

        .cita-card {
            background: #1A1A1A;
            border: 1px solid #333;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 15px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .cita-info h3 {
            color: #FFFFFF;
            margin-bottom: 5px;
            font-size: 1.1rem;
        }

        .cita-meta {
            color: #aaa;
            font-size: 0.9rem;
            line-height: 1.5;
        }

        .badge {
            padding: 5px 10px;
            border-radius: 4px;
            font-size: 0.8rem;
            text-transform: uppercase;
        }

        .badge-pendiente {
            background: rgba(255, 193, 7, 0.1);
            color: #ffc107;
            border: 1px solid rgba(255, 193, 7, 0.2);
        }

        .badge-confirmada {
            background: rgba(40, 167, 69, 0.1);
            color: #28a745;
            border: 1px solid rgba(40, 167, 69, 0.2);
        }

        .badge-completada {
            background: rgba(23, 162, 184, 0.1);
            color: #17a2b8;
            border: 1px solid rgba(23, 162, 184, 0.2);
        }

        .badge-cancelada {
            background: rgba(220, 53, 69, 0.1);
            color: #dc3545;
            border: 1px solid rgba(220, 53, 69, 0.2);
        }

        .btn-cancelar {
            background: transparent;
            border: 1px solid #dc3545;
            color: #dc3545;
            padding: 8px 15px;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.2s;
        }

        .btn-cancelar:hover {
            background: #dc3545;
            color: white;
        }

        .btn-reagendar {
            background: #ffffff;
            border: 1px solid #ffffff;
            color: #111111;
            padding: 8px 15px;
            border-radius: 6px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            font-size: 13.3333px;
            font-weight: 500;
            transition: all 0.2s;
        }

        .btn-reagendar:hover {
            background: transparent;
            color: #ffffff;
            border-color: #ffffff;
        }

        @media (max-width: 600px) {
            .cita-card {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }

            .btn-cancelar {
                width: 100%;
            }
        }
    </style>
</head>

<body>

    <div class="container">
        <div class="header">
            <a href="cliente-dashboard.php" class="back-btn">← Volver al Dashboard</a>
            <h1 class="title">Mis Citas</h1>
        </div>

        <h2 class="section-title">Próximas Citas</h2>
        <?php if (empty($citasFuturas)): ?>
            <p style="color: #666; font-style: italic;">No tienes citas programadas.</p>
            <a href="reservar.php" style="display:inline-block; margin-top:10px; color:#333333;">Reservar una cita ahora
                →</a>
        <?php else: ?>
            <?php foreach ($citasFuturas as $cita): ?>
                <div class="cita-card">
                    <div class="cita-info">
                        <h3>
                            <?php echo htmlspecialchars($cita['servicio']); ?>
                        </h3>
                        <div class="cita-meta">
                            📅
                            <?php echo date('d/m/Y', strtotime($cita['fecha_hora'])); ?>
                            🕒
                            <?php echo date('H:i', strtotime($cita['fecha_hora'])); ?><br>
                            💈
                            <?php echo htmlspecialchars($cita['barbero']); ?><br>
                            📍
                            <?php echo htmlspecialchars($cita['sucursal']); ?>
                        </div>
                    </div>
                    <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                        <a href="reservar.php?reagendar_id=<?php echo $cita['id']; ?>" class="btn-reagendar">Reagendar</a>
                        <form onsubmit="return confirm('¿Seguro que deseas cancelar esta cita?');"
                            action="api/cancelar_cita.php" method="POST" style="margin: 0;">
                            <input type="hidden" name="cita_id" value="<?php echo $cita['id']; ?>">
                            <button type="submit" class="btn-cancelar">Cancelar Cita</button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>

        <h2 class="section-title">Historial Reciente</h2>
        <?php if (empty($citasHistorial)): ?>
            <p style="color: #666; font-style: italic;">No hay historial disponible.</p>
        <?php else: ?>
            <?php foreach ($citasHistorial as $cita): ?>
                <div class="cita-card" style="opacity: 0.7;">
                    <div class="cita-info">
                        <h3>
                            <?php echo htmlspecialchars($cita['servicio']); ?>
                        </h3>
                        <div class="cita-meta">
                            📅
                            <?php echo date('d/m/Y', strtotime($cita['fecha_hora'])); ?>
                            🕒
                            <?php echo date('H:i', strtotime($cita['fecha_hora'])); ?><br>
                            💈
                            <?php echo htmlspecialchars($cita['barbero']); ?>
                        </div>
                    </div>
                    <div>
                        <span class="badge badge-<?php echo $cita['estado']; ?>">
                            <?php echo ucfirst($cita['estado']); ?>
                        </span>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>

    </div>

    <!-- Bottom Nav Bar for Client PWA -->
    <nav class="pwa-bottom-nav">
        <a href="cliente-dashboard.php" class="pwa-bottom-nav__item <?php echo basename($_SERVER['PHP_SELF']) == 'cliente-dashboard.php' ? 'active' : ''; ?>">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect></svg>
            <span>Inicio</span>
        </a>
        <a href="reservar.php" class="pwa-bottom-nav__item <?php echo basename($_SERVER['PHP_SELF']) == 'reservar.php' ? 'active' : ''; ?>">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
            <span>Reservar</span>
        </a>
        <a href="mis-citas.php" class="pwa-bottom-nav__item <?php echo basename($_SERVER['PHP_SELF']) == 'mis-citas.php' ? 'active' : ''; ?>">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
            <span>Mis Citas</span>
        </a>
        <a href="mi-perfil.php" class="pwa-bottom-nav__item <?php echo basename($_SERVER['PHP_SELF']) == 'mi-perfil.php' ? 'active' : ''; ?>">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
            <span>Mi Perfil</span>
        </a>
    </nav>
</body>
</html>