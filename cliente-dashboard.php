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

// Obtener próxima cita del cliente y código de referido
$proxima_cita = null;
$codigo_referido = '';
$puntos_por_referido_cfg = 200;
$descuento_referido_amigo_cfg = '2.00';

if ($cliente_id) {
    try {
        $pdo = getConnection();

        // Cargar configuraciones
        $stmtCfgs = $pdo->query("SELECT clave, valor FROM configuracion");
        $cfgsList = $stmtCfgs->fetchAll(PDO::FETCH_KEY_PAIR);
        $puntos_por_referido_cfg = intval($cfgsList['puntos_por_referido'] ?? 200);
        $descuento_referido_amigo_cfg = number_format(floatval($cfgsList['descuento_referido_amigo'] ?? 2.00), 2);

        // Código de referido
        $stmtCod = $pdo->prepare("SELECT codigo_referido FROM clientes WHERE id = ?");
        $stmtCod->execute([$cliente_id]);
        $codigo_referido = $stmtCod->fetchColumn();

        // Auto-limpiar prefijo KORTZEN de la BD
        if (!empty($codigo_referido) && (strpos($codigo_referido, 'KORTZEN-') !== false || strpos($codigo_referido, 'KORTZEN') !== false)) {
            $codigo_referido = str_replace(['KORTZEN-', 'KORTZEN'], '', $codigo_referido);
            $stmtUpdCod = $pdo->prepare("UPDATE clientes SET codigo_referido = ? WHERE id = ?");
            $stmtUpdCod->execute([$codigo_referido, $cliente_id]);
        }

        if (empty($codigo_referido)) {
            $nombreLimpio = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $primer_nombre));
            if (empty($nombreLimpio)) $nombreLimpio = 'CLIENTE';
            $codigo_referido = $nombreLimpio . rand(100, 999);
            
            try {
                $pdo->exec("ALTER TABLE clientes ADD COLUMN codigo_referido VARCHAR(30) UNIQUE NULL AFTER puntos");
            } catch (Exception $ex) {}
            
            $stmtUpdCod = $pdo->prepare("UPDATE clientes SET codigo_referido = ? WHERE id = ?");
            $stmtUpdCod->execute([$codigo_referido, $cliente_id]);
        }

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
    <link rel="stylesheet" href="/css/pwa-native.css?v=50">

    <!-- PWA Manifest & Meta Tags -->
    <link rel="manifest" href="/manifest.json">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="KORTZEN">
    <link rel="apple-touch-icon" href="/assets/icons/favicon.png">
    <script src="/js/pwa.js" defer></script>
    <script src="/js/calendar-helper.js?v=1"></script>
</head>

<body class="pwa-app-mode">

    <div class="pwa-container">
        <?php include_once 'includes/pwa_desktop_header.php'; ?>

        <!-- Native Top Bar (Screen 1) -->
        <header class="pwa-header">
            <a href="https://wa.me/593988422770?text=Hola%20KORTZEN,%20tengo%20una%20consulta" target="_blank" class="pwa-header__btn" title="Soporte WhatsApp">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"></path>
                </svg>
            </a>
            <div class="pwa-header__logo">KORTZEN</div>
            <button class="pwa-header__btn" id="pwaBellBtn" title="Notificaciones Push" onclick="activarNotificacionesPWA()">
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
                <span>Reservar</span>
            </a>
            <a href="pwa-barberos.php" class="pwa-quick-action-btn">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                    <circle cx="9" cy="7" r="4"></circle>
                    <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                    <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                </svg>
                <span>Barberos</span>
            </a>
            <a href="mis-citas.php" class="pwa-quick-action-btn">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="10"></circle>
                    <polyline points="12 6 12 12 16 14"></polyline>
                </svg>
                <span>Mis citas</span>
            </a>
            <a href="mi-perfil.php#beneficios" class="pwa-quick-action-btn">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"></path>
                    <line x1="7" y1="7" x2="7.01" y2="7"></line>
                </svg>
                <span>Beneficios</span>
            </a>
        </div>

        <!-- Banner Card Código de Referido -->
        <?php
        $wa_share_dash = urlencode("¡Hola! Te regalo $" . $descuento_referido_amigo_cfg . " de descuento en tu corte de pelo en KORTZEN Barbería. Usa mi código " . $codigo_referido . " al reservar aquí: https://kortzen.com/reservar.php");
        ?>
        <div class="pwa-banner-card" style="background: #FFFFFF; border: 1px solid #EAEAEA; box-shadow: 0 4px 15px rgba(0,0,0,0.03);">
            <div class="pwa-banner-card__left">
                <div class="pwa-banner-card__icon-box" style="background: #F4F4F4; color: #111111;">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#111111" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"></path><line x1="7" y1="7" x2="7.01" y2="7"></line></svg>
                </div>
                <div>
                    <div class="pwa-banner-card__title" style="color: #111111; font-weight: 800;">Invita y Gana: <strong><?php echo htmlspecialchars($codigo_referido); ?></strong></div>
                    <div class="pwa-banner-card__desc" style="color: #666666;">Gana +<?php echo $puntos_por_referido_cfg; ?> pts y regala $<?php echo $descuento_referido_amigo_cfg; ?> dcto.</div>
                </div>
            </div>
            <a href="https://wa.me/?text=<?php echo $wa_share_dash; ?>" target="_blank" class="pwa-banner-card__link" style="background: #111111; color: #FFFFFF; padding: 8px 14px; border-radius: 8px; font-weight: 800; text-decoration: none; display: flex; align-items: center; gap: 6px;">
                <span>Compartir</span>
            </a>
        </div>

        <!-- Banner Card Nuestros Barberos -->
        <div class="pwa-banner-card">
            <div class="pwa-banner-card__left">
                <div class="pwa-banner-card__icon-box">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                        <circle cx="9" cy="7" r="4"></circle>
                        <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                        <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                    </svg>
                </div>
                <div>
                    <div class="pwa-banner-card__title">Nuestros Barberos</div>
                    <div class="pwa-banner-card__desc">Conoce a nuestro equipo experto.</div>
                </div>
            </div>
            <a href="pwa-barberos.php" class="pwa-banner-card__link">
                Conocer <span>→</span>
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

        <?php if ($proxima_cita): 
            $ts_cita = strtotime($proxima_cita['fecha_hora']);
        ?>
        <!-- Proxima cita widget -->
        <div class="pwa-section-title">Tu próxima cita</div>
        <div class="pwa-upcoming-card">
            <div class="pwa-upcoming-body">
                <div class="pwa-date-box">
                    <div class="pwa-date-box__day"><?php echo date('D', $ts_cita); ?></div>
                    <div class="pwa-date-box__num"><?php echo date('d', $ts_cita); ?></div>
                    <div class="pwa-date-box__month"><?php echo date('M', $ts_cita); ?></div>
                </div>
                <div class="pwa-upcoming-info">
                    <div class="pwa-upcoming-service"><?php echo htmlspecialchars($proxima_cita['servicio_nombre'] ?? 'Corte de Autor'); ?></div>
                    <div class="pwa-upcoming-detail">con <?php echo htmlspecialchars($proxima_cita['barbero_nombre'] ?? 'Master Barber'); ?></div>
                    <div class="pwa-upcoming-detail">🕒 <?php echo date('H:i', $ts_cita); ?> • KORTZEN Llano Chico</div>
                </div>
            </div>
            
            <!-- Calendar buttons -->
            <div style="display: flex; gap: 0.5rem; margin-top: 0.75rem;">
                <button type="button" class="pwa-btn-secondary" style="flex: 1; text-align: center; font-size: 0.75rem;" onclick="KortzenCalendar.addToGoogleCalendar('Cita en KORTZEN: <?php echo htmlspecialchars($proxima_cita['servicio_nombre'] ?? 'Corte'); ?>', 'Cita agendada con <?php echo htmlspecialchars($proxima_cita['barbero_nombre'] ?? 'Barbero'); ?>', 'KORTZEN Llano Chico, Quito', '<?php echo $proxima_cita['fecha_hora']; ?>')">📅 GOOGLE CALENDAR</button>
                <button type="button" class="pwa-btn-secondary" style="flex: 1; text-align: center; font-size: 0.75rem;" onclick="KortzenCalendar.downloadIcs('Cita en KORTZEN: <?php echo htmlspecialchars($proxima_cita['servicio_nombre'] ?? 'Corte'); ?>', 'Cita agendada con <?php echo htmlspecialchars($proxima_cita['barbero_nombre'] ?? 'Barbero'); ?>', 'KORTZEN Llano Chico, Quito', '<?php echo $proxima_cita['fecha_hora']; ?>')">🍏 APPLE / ICS</button>
            </div>

            <div style="display: flex; gap: 0.6rem; margin-top: 0.5rem;">
                <a href="mis-citas.php" class="pwa-btn-secondary" style="flex: 1; text-align: center;">VER CITAS</a>
                <form action="/api/cancelar_cita.php" method="POST" style="flex: 1;" onsubmit="return confirm('¿Estás seguro de que deseas cancelar esta cita?');">
                    <input type="hidden" name="cita_id" value="<?php echo $proxima_cita['id']; ?>">
                    <button type="submit" class="pwa-btn-secondary" style="width: 100%; color: #dc3545; border-color: #dc3545; background: #fff; cursor: pointer;">CANCELAR</button>
                </form>
            </div>
        </div>
        <?php endif; ?>

        <!-- Calificar servicio (Reseña) Card -->
        <div class="pwa-section-title" style="margin-top: 1.5rem;">Califica tu experiencia</div>
        <div class="pwa-banner-card" style="flex-direction: column; align-items: flex-start; gap: 0.75rem;">
            <div class="pwa-banner-card__title" style="font-size: 0.95rem;">¿Qué te pareció tu último servicio? ⭐</div>
            <div class="pwa-banner-card__desc">Tu opinión nos ayuda a mantener el estándar de excelencia KORTZEN.</div>
            
            <form id="resenaPwaForm" onsubmit="enviarResenaPwa(event)" style="width: 100%; margin-top: 0.3rem;">
                <div style="display: flex; gap: 0.5rem; margin-bottom: 0.6rem; font-size: 1.4rem;">
                    <span class="star-rating" data-val="1" onclick="setRating(1)" style="cursor:pointer; opacity: 0.3;">★</span>
                    <span class="star-rating" data-val="2" onclick="setRating(2)" style="cursor:pointer; opacity: 0.3;">★</span>
                    <span class="star-rating" data-val="3" onclick="setRating(3)" style="cursor:pointer; opacity: 0.3;">★</span>
                    <span class="star-rating" data-val="4" onclick="setRating(4)" style="cursor:pointer; opacity: 0.3;">★</span>
                    <span class="star-rating" data-val="5" onclick="setRating(5)" style="cursor:pointer; color: #FFD700;">★</span>
                </div>
                <input type="hidden" name="calificacion" id="inputCalificacion" value="5">
                <textarea name="comentario" placeholder="Escribe tu comentario o reseña..." style="width: 100%; padding: 0.75rem; border: 1px solid var(--pwa-border); border-radius: 10px; font-family: inherit; font-size: 0.82rem; margin-bottom: 0.6rem; resize: none; min-height: 60px;" required></textarea>
                <button type="submit" class="pwa-btn-black" style="padding: 0.75rem; font-size: 0.8rem; margin-top: 0;">ENVIAR CALIFICACIÓN</button>
            </form>
        </div>

        <script>
            function setRating(val) {
                document.getElementById('inputCalificacion').value = val;
                const stars = document.querySelectorAll('.star-rating');
                stars.forEach((s, idx) => {
                    if (idx < val) {
                        s.style.opacity = '1';
                        s.style.color = '#FFD700';
                    } else {
                        s.style.opacity = '0.3';
                        s.style.color = '#111';
                    }
                });
            }

            async function enviarResenaPwa(e) {
                e.preventDefault();
                const form = e.target;
                const formData = new FormData(form);

                try {
                    const res = await fetch('/api/crear_resena_cliente.php', {
                        method: 'POST',
                        body: formData
                    });
                    const data = await res.json();
                    alert(data.message || 'Gracias por tu calificación');
                    if (data.success) {
                        form.reset();
                        setRating(5);
                    }
                } catch (err) {
                    alert('Gracias por tu opinión.');
                }
            }

            function activarNotificacionesPWA() {
                if ('Notification' in window) {
                    Notification.requestPermission().then(permission => {
                        if (permission === 'granted') {
                            alert('✓ Notificaciones Push activadas. Recibirás recordatorios directos de tus citas.');
                            if (navigator.serviceWorker && navigator.serviceWorker.ready) {
                                navigator.serviceWorker.ready.then(reg => {
                                    reg.showNotification('KORTZEN PWA ✂️', {
                                        body: '¡Notificaciones de citas activadas correctamente!',
                                        icon: '/assets/icons/favicon.png'
                                    });
                                });
                            }
                        } else {
                            alert('Permiso de notificaciones no otorgado.');
                        }
                    });
                } else {
                    alert('Tu navegador no soporta notificaciones nativas.');
                }
            }
        </script>

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