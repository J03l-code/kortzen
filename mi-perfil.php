<?php
/**
 * Mi Perfil - Native App UI (Screen 4)
 */

session_start();
require_once 'config.php';

if (!isClienteLoggedIn()) {
    header('Location: cliente-login.php');
    exit;
}

$cliente = getCurrentCliente();
$nombre = $_SESSION['cliente_nombre'] ?? $cliente['nombre'] ?? 'Cliente';
$email = $_SESSION['cliente_email'] ?? $cliente['email'] ?? '';
$cliente_id = $_SESSION['cliente_id'] ?? $cliente['id'] ?? null;

// Iniciales
$nombres_arr = explode(' ', trim($nombre));
$iniciales = strtoupper(substr($nombres_arr[0], 0, 1) . (isset($nombres_arr[1]) ? substr($nombres_arr[1], 0, 1) : ''));

// Cita próxima
$proxima_cita = null;
if ($cliente_id) {
    try {
        $pdo = getConnection();
        $stmt = $pdo->prepare("
            SELECT c.*, s.nombre as servicio_nombre, b.nombre as barbero_nombre
            FROM citas c
            LEFT JOIN servicios s ON c.servicio_id = s.id
            LEFT JOIN usuarios b ON c.barbero_id = b.id
            WHERE c.cliente_id = ? AND c.fecha_hora >= NOW() AND c.estado IN ('pendiente', 'confirmada')
            ORDER BY c.fecha_hora ASC
            LIMIT 1
        ");
        $stmt->execute([$cliente_id]);
        $proxima_cita = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
    }
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title>Mi Perfil - KORTZEN</title>

    <link rel="stylesheet" href="/css/variables.css?v=23">
    <link rel="stylesheet" href="/css/reset.css?v=23">
    <link rel="stylesheet" href="/css/pwa-native.css?v=2">

    <link rel="manifest" href="/manifest.json">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="KORTZEN">
    <link rel="apple-touch-icon" href="/assets/icons/favicon.png">
    <script src="/js/pwa.js" defer></script>
</head>

<body class="pwa-app-mode">

    <div class="pwa-container">
        <?php include_once 'includes/pwa_desktop_header.php'; ?>
        
        <!-- Native Top Bar (Screen 4) -->
        <header class="pwa-header">
            <div style="width: 32px;"></div>
            <div class="pwa-header__title">Mi perfil</div>
            <button class="pwa-header__btn" title="Configuración" onclick="alert('Configuración de cuenta activa')">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="3"></circle>
                    <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path>
                </svg>
            </button>
        </header>

        <!-- User Header Card -->
        <div class="pwa-profile-header">
            <div class="pwa-profile-user">
                <div class="pwa-profile-avatar-circle"><?php echo $iniciales; ?></div>
                <div>
                    <div class="pwa-profile-name"><?php echo htmlspecialchars($nombre); ?></div>
                    <div class="pwa-profile-tier">Miembro KORTZEN • Nivel Oro 👑</div>
                </div>
            </div>
            <svg class="pwa-chevron" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <polyline points="9 18 15 12 9 6"></polyline>
            </svg>
        </div>

        <!-- Puntos KORTZEN Black Card -->
        <div class="pwa-points-card">
            <div class="pwa-points-header">
                <span>Puntos KORTZEN</span>
                <span>Siguiente nivel 1,750 pts</span>
            </div>
            <div class="pwa-points-value">1,250 <span style="font-size: 1rem; font-weight: 400;">pts</span></div>
            <div class="pwa-progress-bar">
                <div class="pwa-progress-fill" style="width: 71%;"></div>
            </div>
        </div>

        <?php if ($proxima_cita): 
            $ts_prof = strtotime($proxima_cita['fecha_hora']);
        ?>
        <!-- Próxima Cita -->
        <div class="pwa-section-title">Próxima cita</div>
        <div class="pwa-upcoming-card">
            <div class="pwa-upcoming-body">
                <div class="pwa-date-box">
                    <div class="pwa-date-box__day"><?php echo date('D', $ts_prof); ?></div>
                    <div class="pwa-date-box__num"><?php echo date('d', $ts_prof); ?></div>
                    <div class="pwa-date-box__month"><?php echo date('M', $ts_prof); ?></div>
                </div>
                <div class="pwa-upcoming-info">
                    <div class="pwa-upcoming-service"><?php echo htmlspecialchars($proxima_cita['servicio_nombre'] ?? 'Estilo Pro'); ?></div>
                    <div class="pwa-upcoming-detail">con <?php echo htmlspecialchars($proxima_cita['barbero_nombre'] ?? 'Mateo'); ?></div>
                    <div class="pwa-upcoming-detail">🕒 <?php echo date('H:i', $ts_prof); ?> • KORTZEN Llano Chico</div>
                </div>
            </div>
            <a href="mis-citas.php" class="pwa-btn-secondary">VER TODAS MIS CITAS</a>
        </div>
        <?php endif; ?>

        <!-- Mis Beneficios Section -->
        <div class="pwa-section-title" id="beneficios">Mis beneficios</div>
        <div class="pwa-benefits-list">
            <a href="javascript:void(0)" onclick="alert('Tienes un 15% de descuento en tu próximo servicio con el código KORTZEN15')" class="pwa-benefit-item">
                <div class="pwa-benefit-item__left">
                    <div class="pwa-benefit-item__icon">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"></path>
                            <line x1="7" y1="7" x2="7.01" y2="7"></line>
                        </svg>
                    </div>
                    <div>
                        <div class="pwa-benefit-item__title">Promociones exclusivas</div>
                        <div class="pwa-benefit-item__desc">Ver ofertas disponibles</div>
                    </div>
                </div>
                <svg class="pwa-chevron" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="9 18 15 12 9 6"></polyline>
                </svg>
            </a>

            <a href="javascript:void(0)" onclick="alert('Comparte tu enlace de referido con amigos y gana 200 puntos KORTZEN por cada reserva')" class="pwa-benefit-item">
                <div class="pwa-benefit-item__left">
                    <div class="pwa-benefit-item__icon">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="20 12 20 22 4 22 4 12"></polyline>
                            <rect x="2" y="7" width="20" height="5"></rect>
                            <line x1="12" y1="22" x2="12" y2="7"></line>
                            <path d="M12 7H7.5a2.5 2.5 0 0 1 0-5C11 2 12 7 12 7z"></path>
                            <path d="M12 7h4.5a2.5 2.5 0 0 0 0-5C13 2 12 7 12 7z"></path>
                        </svg>
                    </div>
                    <div>
                        <div class="pwa-benefit-item__title">Invita y gana</div>
                        <div class="pwa-benefit-item__desc">Comparte y obtén beneficios</div>
                    </div>
                </div>
                <svg class="pwa-chevron" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="9 18 15 12 9 6"></polyline>
                </svg>
            </a>

            <a href="https://wa.me/593988422770?text=Hola%20KORTZEN,%20tengo%20una%20consulta" target="_blank" class="pwa-benefit-item">
                <div class="pwa-benefit-item__left">
                    <div class="pwa-benefit-item__icon">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"></path>
                        </svg>
                    </div>
                    <div>
                        <div class="pwa-benefit-item__title">Atención por WhatsApp</div>
                        <div class="pwa-benefit-item__desc">Contacto directo con recepción</div>
                    </div>
                </div>
                <svg class="pwa-chevron" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="9 18 15 12 9 6"></polyline>
                </svg>
            </a>

            <a href="mis-citas.php" class="pwa-benefit-item">
                <div class="pwa-benefit-item__left">
                    <div class="pwa-benefit-item__icon">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="10"></circle>
                            <polyline points="12 6 12 12 16 14"></polyline>
                        </svg>
                    </div>
                    <div>
                        <div class="pwa-benefit-item__title">Historial de servicios</div>
                        <div class="pwa-benefit-item__desc">Revisa tus servicios anteriores</div>
                    </div>
                </div>
                <svg class="pwa-chevron" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="9 18 15 12 9 6"></polyline>
                </svg>
            </a>
        </div>

        <div style="margin-top: 2rem; text-align: center;">
            <a href="logout.php" style="color: #dc3545; font-size: 0.85rem; text-decoration: none; font-weight: 600;">Cerrar sesión</a>
        </div>
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
        <a href="mis-citas.php" class="pwa-nav-tab">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="10"></circle>
                <polyline points="12 6 12 12 16 14"></polyline>
            </svg>
            <span>Citas</span>
        </a>
        <a href="mi-perfil.php" class="pwa-nav-tab pwa-nav-tab--active">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                <circle cx="12" cy="7" r="4"></circle>
            </svg>
            <span>Perfil</span>
        </a>
    </nav>

</body>
</html>