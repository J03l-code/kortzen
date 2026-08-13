<?php
/**
 * Servicios - Native App UI (Screen 2)
 */

session_start();
require_once 'config.php';

// Obtener servicios de la BD
$servicios = [];
try {
    $pdo = getConnection();
    $stmt = $query = $pdo->query("SELECT * FROM servicios WHERE activo = 1 ORDER BY orden ASC, id ASC");
    $servicios = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // Mock fallbacks if database error
    $servicios = [
        ['nombre' => 'Estilo Pro', 'descripcion' => 'Corte personalizado + lavado + peinado premium.', 'precio' => 45.00],
        ['nombre' => 'Ondulación o Semi Ondulación', 'descripcion' => 'Ondas naturales con acabado profesional.', 'precio' => 60.00],
        ['nombre' => 'Corte con Mateo', 'descripcion' => 'Experiencia exclusiva con nuestro barbero experto.', 'precio' => 70.00],
        ['nombre' => 'Barba Premium', 'descripcion' => 'Perfilado y diseño de barba + toallas calientes.', 'precio' => 40.00],
        ['nombre' => 'Cejas', 'descripcion' => 'Diseño y definición personalizada.', 'precio' => 20.00],
        ['nombre' => 'Limpieza Facial Completa', 'descripcion' => 'Limpieza profunda, exfoliación e hidratación.', 'precio' => 60.00]
    ];
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title>Servicios - KORTZEN</title>

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
        <!-- Native Top Bar (Screen 2) -->
        <header class="pwa-header">
            <button class="pwa-header__btn" onclick="history.back()" title="Atrás">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <polyline points="15 18 9 12 15 6"></polyline>
                </svg>
            </button>
            <div class="pwa-header__title">Servicios</div>
            <button class="pwa-header__btn" onclick="location.href='reservar.php'" title="Bolsa">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"></path>
                    <line x1="3" y1="6" x2="21" y2="6"></line>
                    <path d="M16 10a4 4 0 0 1-8 0"></path>
                </svg>
            </button>
        </header>

        <p class="pwa-subtitle">Servicios premium para un estilo impecable y duradero.</p>

        <!-- Services List -->
        <div class="pwa-services-list">
            <?php foreach ($servicios as $s): ?>
            <a href="reservar.php?servicio_id=<?php echo $s['id'] ?? ''; ?>" class="pwa-service-card">
                <div class="pwa-service-card__left">
                    <div class="pwa-service-card__icon">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="6" cy="6" r="3"></circle>
                            <circle cx="6" cy="18" r="3"></circle>
                            <line x1="20" y1="4" x2="8.12" y2="15.88"></line>
                            <line x1="14.47" y1="14.48" x2="20" y2="20"></line>
                            <line x1="8.12" y1="8.12" x2="12" y2="12"></line>
                        </svg>
                    </div>
                    <div class="pwa-service-card__info">
                        <div class="pwa-service-card__name"><?php echo htmlspecialchars($s['nombre']); ?></div>
                        <div class="pwa-service-card__desc"><?php echo htmlspecialchars($s['descripcion'] ?? 'Cuidado y estilismo personalizado.'); ?></div>
                    </div>
                </div>
                <div class="pwa-service-card__right">
                    <div class="pwa-service-card__price">$<?php echo number_format($s['precio'], 0); ?></div>
                    <svg class="pwa-chevron" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="9 18 15 12 9 6"></polyline>
                    </svg>
                </div>
            </a>
            <?php endforeach; ?>
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
        <a href="pwa-servicios.php" class="pwa-nav-tab pwa-nav-tab--active">
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
