<?php
/**
 * Dashboard del Cliente - Native App UI
 * Interfaz nativa para PWA y clientes autenticados
 */

session_start();
require_once 'config.php';

// Verificar si el cliente está logueado
if (!isset($_SESSION['cliente_logged_in']) || !$_SESSION['cliente_logged_in']) {
    header('Location: cliente-login.php');
    exit;
}

$nombre = $_SESSION['cliente_nombre'] ?? 'Cliente';
$email = $_SESSION['cliente_email'] ?? '';
$foto = $_SESSION['cliente_foto'] ?? null;
$cliente_id = $_SESSION['cliente_id'] ?? null;

// Primer nombre para saludo personalizado
$primer_nombre = htmlspecialchars(explode(' ', trim($nombre))[0]);

// Iniciales para el avatar
$nombres_arr = explode(' ', trim($nombre));
$iniciales = strtoupper(substr($nombres_arr[0], 0, 1) . (isset($nombres_arr[1]) ? substr($nombres_arr[1], 0, 1) : ''));

// Obtener próxima cita del cliente
$proxima_cita = null;
if ($cliente_id) {
    try {
        $pdo = getConnection();
        $stmt = $pdo->prepare("
            SELECT c.*, s.nombre as servicio_nombre, b.nombre as barbero_nombre
            FROM citas c
            LEFT JOIN servicios s ON c.servicio_id = s.id
            LEFT JOIN usuarios b ON c.barbero_id = b.id
            WHERE c.cliente_id = ? AND c.fecha >= CURDATE() AND c.estado IN ('pendiente', 'confirmada')
            ORDER BY c.fecha ASC, c.hora ASC
            LIMIT 1
        ");
        $stmt->execute([$cliente_id]);
        $proxima_cita = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        // Fallback silencioso
    }
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title>KORTZEN - App</title>

    <link rel="stylesheet" href="/css/variables.css?v=23">
    <link rel="stylesheet" href="/css/reset.css?v=23">
    <link rel="stylesheet" href="/css/pwa-native.css?v=2">

    <!-- PWA Manifest & Meta Tags -->
    <link rel="manifest" href="/manifest.json">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="KORTZEN">
    <link rel="apple-touch-icon" href="/assets/icons/favicon.png">
    <script src="/js/pwa.js" defer></script>
</head>

<body class="pwa-app-mode">

    <div class="pwa-container">
        <!-- Native Top Bar (Screen 1) -->
        <header class="pwa-header">
            <div style="width: 32px;"></div>
            <div class="pwa-header__logo">KORTZEN</div>
            <button class="pwa-header__btn" title="Notificaciones" onclick="alert('No tienes notificaciones pendientes')">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path>
                    <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
                </svg>
            </button>
        </header>

        <!-- Hero Banner Card -->
        <div class="pwa-hero-card">
            <h1 class="pwa-hero-card__title">Bienvenido,<br><?php echo $primer_nombre; ?></h1>
            <p class="pwa-hero-card__subtitle">Precisión. Estilo. Confianza.</p>
        </div>

        <!-- Accesos Rápidos -->
        <div class="pwa-section-title">Accesos rápidos</div>
        <div class="pwa-quick-actions">
            <a href="reservar.php" class="pwa-quick-action-btn">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                    <line x1="16" y1="2" x2="16" y2="6"></line>
                    <line x1="8" y1="2" x2="8" y2="6"></line>
                    <line x1="3" y1="10" x2="21" y2="10"></line>
                </svg>
                <span>Reservar cita</span>
            </a>
            <a href="mis-citas.php" class="pwa-quick-action-btn">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                    <circle cx="12" cy="7" r="4"></circle>
                </svg>
                <span>Mis citas</span>
            </a>
            <a href="mi-perfil.php#beneficios" class="pwa-quick-action-btn">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"></path>
                    <line x1="7" y1="7" x2="7.01" y2="7"></line>
                </svg>
                <span>Promociones</span>
            </a>
            <a href="javascript:void(0)" onclick="alert('Sucursal principal: KORTZEN Llano Chico\nHorarios: 10:00 - 20:00\nTeléfono: +593 098 842 2770')" class="pwa-quick-action-btn">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path>
                    <circle cx="12" cy="10" r="3"></circle>
                </svg>
                <span>Sucursales</span>
            </a>
        </div>

        <!-- Banner Card El Método KORTZEN -->
        <div class="pwa-banner-card">
            <div class="pwa-banner-card__left">
                <div class="pwa-banner-card__icon-box">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="6" cy="6" r="3"></circle>
                        <circle cx="6" cy="18" r="3"></circle>
                        <line x1="20" y1="4" x2="8.12" y2="15.88"></line>
                        <line x1="14.47" y1="14.48" x2="20" y2="20"></line>
                        <line x1="8.12" y1="8.12" x2="12" y2="12"></line>
                    </svg>
                </div>
                <div>
                    <div class="pwa-banner-card__title">El Método KORTZEN</div>
                    <div class="pwa-banner-card__desc">Calidad en cada detalle.</div>
                </div>
            </div>
            <a href="servicios.html" class="pwa-banner-card__link">
                Conocer más <span>→</span>
            </a>
        </div>

        <?php if ($proxima_cita): ?>
        <!-- Proxima cita widget -->
        <div class="pwa-section-title">Tu próxima cita</div>
        <div class="pwa-upcoming-card">
            <div class="pwa-upcoming-body">
                <div class="pwa-date-box">
                    <div class="pwa-date-box__day"><?php echo date('D', strtotime($proxima_cita['fecha'])); ?></div>
                    <div class="pwa-date-box__num"><?php echo date('d', strtotime($proxima_cita['fecha'])); ?></div>
                    <div class="pwa-date-box__month"><?php echo date('M', strtotime($proxima_cita['fecha'])); ?></div>
                </div>
                <div class="pwa-upcoming-info">
                    <div class="pwa-upcoming-service"><?php echo htmlspecialchars($proxima_cita['servicio_nombre'] ?? 'Corte de Autor'); ?></div>
                    <div class="pwa-upcoming-detail">con <?php echo htmlspecialchars($proxima_cita['barbero_nombre'] ?? 'Master Barber'); ?></div>
                    <div class="pwa-upcoming-detail">🕒 <?php echo date('H:i', strtotime($proxima_cita['hora'])); ?> • KORTZEN Llano Chico</div>
                </div>
            </div>
            <a href="mis-citas.php" class="pwa-btn-secondary">VER TODAS MIS CITAS</a>
        </div>
        <?php endif; ?>

        <!-- Botón negro de Reserva flotante -->
        <a href="reservar.php" class="pwa-btn-black">
            <span>RESERVAR CITA</span>
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                <line x1="16" y1="2" x2="16" y2="6"></line>
                <line x1="8" y1="2" x2="8" y2="6"></line>
                <line x1="3" y1="10" x2="21" y2="10"></line>
            </svg>
        </a>
    </div>

    <!-- Native Bottom Navigation Bar -->
    <nav class="pwa-bottom-nav-bar">
        <a href="cliente-dashboard.php" class="pwa-nav-tab pwa-nav-tab--active">
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
        <a href="mis-citas.php" class="pwa-nav-tab">
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