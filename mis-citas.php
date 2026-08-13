<?php
/**
 * Mis Citas - Native App UI (Screen Citas)
 */

session_start();
require_once 'config.php';

if (!isClienteLoggedIn()) {
    header('Location: cliente-login.php');
    exit;
}

$cliente = getCurrentCliente();
$cliente_id = $_SESSION['cliente_id'] ?? $cliente['id'] ?? null;
$citasFuturas = [];
$citasHistorial = [];

if ($cliente_id) {
    try {
        $pdo = getConnection();
        // Citas futuras
        $sqlFuturas = "SELECT c.*, s.nombre as servicio, u.nombre as barbero, suc.nombre as sucursal 
                       FROM citas c
                       LEFT JOIN servicios s ON c.servicio_id = s.id
                       LEFT JOIN usuarios u ON c.barbero_id = u.id
                       LEFT JOIN sucursales suc ON c.sucursal_id = suc.id
                       WHERE c.cliente_id = ? AND c.fecha >= CURDATE() AND c.estado IN ('pendiente', 'confirmada')
                       ORDER BY c.fecha ASC, c.hora ASC";
        $stmtF = $pdo->prepare($sqlFuturas);
        $stmtF->execute([$cliente_id]);
        $citasFuturas = $stmtF->fetchAll(PDO::FETCH_ASSOC);

        // Historial
        $sqlHistorial = "SELECT c.*, s.nombre as servicio, u.nombre as barbero 
                         FROM citas c
                         LEFT JOIN servicios s ON c.servicio_id = s.id
                         LEFT JOIN usuarios u ON c.barbero_id = u.id
                         WHERE c.cliente_id = ? AND (c.fecha < CURDATE() OR c.estado IN ('completada', 'cancelada'))
                         ORDER BY c.fecha DESC LIMIT 10";
        $stmtH = $pdo->prepare($sqlHistorial);
        $stmtH->execute([$cliente_id]);
        $citasHistorial = $stmtH->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
    }
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title>Mis Citas - KORTZEN</title>

    <link rel="stylesheet" href="/css/variables.css?v=23">
    <link rel="stylesheet" href="/css/reset.css?v=23">
    <link rel="stylesheet" href="/css/pwa-native.css?v=1">

    <link rel="manifest" href="/manifest.json">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="KORTZEN">
    <link rel="apple-touch-icon" href="/assets/icons/favicon.png">
    <script src="/js/pwa.js" defer></script>
</head>

<body class="pwa-app-mode">

    <div class="pwa-container">
        <!-- Native Top Bar -->
        <header class="pwa-header">
            <button class="pwa-header__btn" onclick="history.back()" title="Atrás">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="15 18 9 12 15 6"></polyline>
                </svg>
            </button>
            <div class="pwa-header__title">Mis citas</div>
            <div style="width: 32px;"></div>
        </header>

        <!-- Dynamic List of Appointments -->
        <div class="pwa-section-title">Próximas citas</div>
        
        <?php if (!empty($citasFuturas)): ?>
            <?php foreach ($citasFuturas as $c): ?>
            <div class="pwa-upcoming-card">
                <div class="pwa-upcoming-body">
                    <div class="pwa-date-box">
                        <div class="pwa-date-box__day"><?php echo date('D', strtotime($c['fecha'])); ?></div>
                        <div class="pwa-date-box__num"><?php echo date('d', strtotime($c['fecha'])); ?></div>
                        <div class="pwa-date-box__month"><?php echo date('M', strtotime($c['fecha'])); ?></div>
                    </div>
                    <div class="pwa-upcoming-info">
                        <div class="pwa-upcoming-service"><?php echo htmlspecialchars($c['servicio'] ?? 'Corte de Autor'); ?></div>
                        <div class="pwa-upcoming-detail">con <?php echo htmlspecialchars($c['barbero'] ?? 'Master Barber'); ?></div>
                        <div class="pwa-upcoming-detail">🕒 <?php echo date('H:i', strtotime($c['hora'])); ?> • <?php echo htmlspecialchars($c['sucursal'] ?? 'KORTZEN Llano Chico'); ?></div>
                    </div>
                </div>
                <a href="reservar.php?reagendar_id=<?php echo $c['id']; ?>" class="pwa-btn-secondary">REAGENDAR CITA</a>
            </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="pwa-banner-card" style="text-align: center; justify-content: center; padding: 2rem;">
                <div>
                    <div class="pwa-banner-card__title">No tienes citas agendadas</div>
                    <div class="pwa-banner-card__desc" style="margin-bottom: 1rem;">Reserva una cita con nuestros maestros barberos.</div>
                    <a href="reservar.php" class="pwa-btn-black" style="display: inline-flex; width: auto; padding: 0.75rem 1.5rem;">Reservar ahora</a>
                </div>
            </div>
        <?php endif; ?>

        <?php if (!empty($citasHistorial)): ?>
        <div class="pwa-section-title" style="margin-top: 1.5rem;">Historial de citas</div>
        <div class="pwa-benefits-list">
            <?php foreach ($citasHistorial as $h): ?>
            <div class="pwa-benefit-item">
                <div class="pwa-benefit-item__left">
                    <div class="pwa-benefit-item__icon">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="10"></circle>
                            <polyline points="12 6 12 12 16 14"></polyline>
                        </svg>
                    </div>
                    <div>
                        <div class="pwa-benefit-item__title"><?php echo htmlspecialchars($h['servicio'] ?? 'Corte de Autor'); ?></div>
                        <div class="pwa-benefit-item__desc"><?php echo date('d/m/Y', strtotime($h['fecha'])); ?> con <?php echo htmlspecialchars($h['barbero'] ?? 'Barbero'); ?></div>
                    </div>
                </div>
                <a href="reservar.php?reagendar_id=<?php echo $h['id']; ?>" style="font-size: 0.78rem; font-weight: 600; color: #111; text-decoration: none;">Volver a pedir</a>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

    </div>

    <!-- Native Bottom Navigation Bar -->
    <nav class="pwa-bottom-nav-bar">
        <a href="cliente-dashboard.php" class="pwa-nav-tab">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
                <polyline points="9 22 9 12 15 12 15 22"></polyline>
            </svg>
            <span>Inicio</span>
        </a>
        <a href="pwa-servicios.php" class="pwa-nav-tab">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="6" cy="6" r="3"></circle>
                <circle cx="6" cy="18" r="3"></circle>
                <line x1="20" y1="4" x2="8.12" y2="15.88"></line>
                <line x1="14.47" y1="14.48" x2="20" y2="20"></line>
                <line x1="8.12" y1="8.12" x2="12" y2="12"></line>
            </svg>
            <span>Servicios</span>
        </a>
        <a href="reservar.php" class="pwa-nav-tab">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                <line x1="16" y1="2" x2="16" y2="6"></line>
                <line x1="8" y1="2" x2="8" y2="6"></line>
                <line x1="3" y1="10" x2="21" y2="10"></line>
            </svg>
            <span>Reservar</span>
        </a>
        <a href="mis-citas.php" class="pwa-nav-tab pwa-nav-tab--active">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="10"></circle>
                <polyline points="12 6 12 12 16 14"></polyline>
            </svg>
            <span>Citas</span>
        </a>
        <a href="mi-perfil.php" class="pwa-nav-tab">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                <circle cx="12" cy="7" r="4"></circle>
            </svg>
            <span>Perfil</span>
        </a>
    </nav>

</body>
</html>