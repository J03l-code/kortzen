<?php
/**
 * Dashboard del Cliente
 * Página de bienvenida después de iniciar sesión con Google
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
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mi Cuenta - KORTZEN</title>

    <!-- Google Fonts: Outfit (Premium look) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@200;300;400;500;600;700&display=swap"
        rel="stylesheet">

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
        :root {
            --gold-primary: #FFFFFF;
            --gold-hover: #D4D4D4;
            --gold-glow: rgba(255, 255, 255, 0.2);
            --bg-dark: #050505;
            --glass-bg: rgba(255, 255, 255, 0.03);
            --glass-border: rgba(255, 255, 255, 0.08);
            --text-primary: #FFFFFF;
            --text-secondary: #AAAAAA;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: var(--font-body);
            background-color: var(--bg-dark);
            min-height: 100vh;
            color: var(--text-primary);
            overflow-x: hidden;
        }

        /* --- Header --- */
        .dashboard-header {
            background: rgba(5, 5, 5, 0.8);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border-bottom: 1px solid var(--glass-border);
            padding: 1.2rem 3rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .dashboard-logo {
            font-family: 'Outfit', sans-serif;
            font-size: 1.6rem;
            font-weight: 700;
            color: #FFFFFF;
            text-decoration: none;
            letter-spacing: 0.15em;
            text-transform: uppercase;
        }

        .dashboard-logo span {
            color: var(--gold-primary);
        }

        .user-menu {
            display: flex;
            align-items: center;
            gap: 1.5rem;
        }

        .user-avatar {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid var(--gold-primary);
            box-shadow: 0 0 15px var(--gold-glow);
        }

        .user-avatar-placeholder {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--gold-primary), #8B7355);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 1.2rem;
            color: #000;
            border: 1px solid var(--gold-hover);
        }

        .logout-btn {
            padding: 0.6rem 1.4rem;
            background: transparent;
            border: 1px solid var(--glass-border);
            color: #ccc;
            border-radius: 30px;
            font-size: 0.85rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.4s ease;
            text-decoration: none;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .logout-btn:hover {
            border-color: var(--gold-primary);
            color: var(--gold-primary);
            transform: translateY(-2px);
        }

        /* --- Main Content --- */
        .dashboard-main {
            max-width: 1100px;
            margin: 0 auto;
            padding: 4rem 2rem;
            animation: fadeIn 0.8s ease-out;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .welcome-section {
            text-align: center;
            margin-bottom: 5rem;
            position: relative;
        }

        .welcome-section::after {
            content: '';
            display: block;
            width: 60px;
            height: 2px;
            background: var(--gold-primary);
            margin: 2rem auto 0;
            opacity: 0.7;
        }

        .welcome-title {
            font-size: 3.5rem;
            font-weight: 200;
            letter-spacing: -0.02em;
            margin-bottom: 0.5rem;
        }

        .welcome-title span {
            font-weight: 600;
            background: linear-gradient(90deg, #fff, var(--gold-primary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .welcome-subtitle {
            color: var(--text-secondary);
            font-size: 1.1rem;
            letter-spacing: 0.05em;
            text-transform: uppercase;
        }

        /* --- Cards Grid --- */
        .cards-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 2rem;
            margin-bottom: 5rem;
        }

        @media (max-width: 900px) {
            .cards-grid {
                grid-template-columns: 1fr;
            }
        }

        .card {
            background: var(--glass-bg);
            border: 1px solid var(--glass-border);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 2.5rem 2rem;
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.05) 0%, transparent 100%);
            opacity: 0;
            transition: opacity 0.4s;
        }

        .card:hover {
            transform: translateY(-10px);
            border-color: rgba(212, 175, 55, 0.3);
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.5);
        }

        .card:hover::before {
            opacity: 1;
        }

        .card-icon {
            width: 70px;
            height: 70px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid var(--glass-border);
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1.5rem;
            color: var(--gold-primary);
            transition: all 0.3s;
        }

        .card:hover .card-icon {
            background: var(--gold-primary);
            color: #000;
            box-shadow: 0 0 20px var(--gold-glow);
            border-color: var(--gold-primary);
        }

        .card-title {
            font-size: 1.4rem;
            font-weight: 500;
            margin-bottom: 1rem;
            letter-spacing: 0.02em;
        }

        .card-description {
            color: var(--text-secondary);
            font-size: 0.95rem;
            line-height: 1.6;
            margin-bottom: 2rem;
            flex-grow: 1;
        }

        .card-btn {
            padding: 1rem 2rem;
            border-radius: 50px;
            font-size: 0.9rem;
            font-weight: 600;
            text-decoration: none;
            letter-spacing: 1px;
            text-transform: uppercase;
            transition: all 0.3s;
            position: relative;
            z-index: 10;
        }

        .btn-primary {
            background: var(--gold-primary);
            color: #000;
            box-shadow: 0 4px 15px rgba(212, 175, 55, 0.2);
        }

        .btn-primary:hover {
            background: #fff;
            box-shadow: 0 6px 20px rgba(255, 255, 255, 0.3);
            transform: scale(1.05);
        }

        .btn-outline {
            background: transparent;
            border: 1px solid var(--glass-border);
            color: #fff;
        }

        .btn-outline:hover {
            border-color: var(--gold-primary);
            color: var(--gold-primary);
        }

        /* --- Profile Section --- */
        .profile-section {
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.03), rgba(255, 255, 255, 0.01));
            border: 1px solid var(--glass-border);
            border-radius: 24px;
            padding: 3rem;
            display: flex;
            align-items: center;
            gap: 3rem;
            backdrop-filter: blur(20px);
        }

        @media (max-width: 768px) {
            .profile-section {
                flex-direction: column;
                text-align: center;
            }
        }

        .profile-avatar-lg {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid var(--gold-primary);
            box-shadow: 0 0 30px rgba(212, 175, 55, 0.15);
        }

        .profile-placeholder-lg {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background: #111;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 2px solid var(--gold-primary);
            color: var(--gold-primary);
            font-size: 2.5rem;
        }

        .profile-details h3 {
            font-size: 2rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        .profile-details p {
            color: var(--text-secondary);
            font-size: 1.1rem;
            margin-bottom: 1.5rem;
        }

        .verified-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.8rem;
            padding: 0.6rem 1.2rem;
            background: rgba(212, 175, 55, 0.1);
            border: 1px solid rgba(212, 175, 55, 0.3);
            border-radius: 50px;
            color: var(--gold-primary);
            font-size: 0.85rem;
            font-weight: 500;
            letter-spacing: 0.5px;
        }

        .verified-badge svg {
            width: 16px;
            height: 16px;
        }
    </style>
</head>

<body>
    <header class="dashboard-header">
        <a href="/" class="dashboard-logo">KORT<span>ZEN</span></a>
        <div class="user-menu">
            <?php if ($foto): ?>
                <img src="<?php echo htmlspecialchars($foto); ?>" alt="Avatar" class="user-avatar">
            <?php else: ?>
                <div class="user-avatar-placeholder">
                    <?php echo strtoupper(substr($nombre, 0, 1)); ?>
                </div>
            <?php endif; ?>
            <a href="/api/auth/google-logout.php" class="logout-btn">CERRAR SESIÓN</a>
        </div>
    </header>

    <main class="dashboard-main">
        <section class="welcome-section">
            <h1 class="welcome-title">Bienvenido, <span><?php echo htmlspecialchars(explode(' ', $nombre)[0]); ?></span>
            </h1>
            <p class="welcome-subtitle">Gestiona tu experiencia premium en Kortzen</p>
        </section>

        <div class="cards-grid">
            <!-- Nueva Cita -->
            <div class="card">
                <div class="card-icon">
                    <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                        <line x1="16" y1="2" x2="16" y2="6"></line>
                        <line x1="8" y1="2" x2="8" y2="6"></line>
                        <line x1="3" y1="10" x2="21" y2="10"></line>
                    </svg>
                </div>
                <h3 class="card-title">Reservar Cita</h3>
                <p class="card-description">Agenda tu próxima sesión con nuestros maestros barberos.</p>
                <a href="/reservar.php" class="card-btn btn-primary">Reservar Ahora</a>
            </div>

            <!-- Mis Citas -->
            <div class="card">
                <div class="card-icon">
                    <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
                    </svg>
                </div>
                <h3 class="card-title">Mis Citas</h3>
                <p class="card-description">Consulta tu historial y próximos agendamientos.</p>
                <a href="/mis-citas.php" class="card-btn btn-outline">Ver Historial</a>
            </div>

            <!-- Perfil -->
            <div class="card">
                <div class="card-icon">
                    <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                        <circle cx="12" cy="7" r="4"></circle>
                    </svg>
                </div>
                <h3 class="card-title">Mi Perfil</h3>
                <p class="card-description">Actualiza tus datos y preferencias personales.</p>
                <a href="/mi-perfil.php" class="card-btn btn-outline">Editar Perfil</a>
            </div>
        </div>

        <!-- Seccion Perfil Grande -->
        <section class="profile-section">
            <?php if ($foto): ?>
                <img src="<?php echo htmlspecialchars($foto); ?>" alt="Avatar" class="profile-avatar-lg">
            <?php else: ?>
                <div class="profile-placeholder-lg">
                    <?php echo strtoupper(substr($nombre, 0, 1)); ?>
                </div>
            <?php endif; ?>

            <div class="profile-details">
                <h3><?php echo htmlspecialchars($nombre); ?></h3>
                <p><?php echo htmlspecialchars($email); ?></p>

                <div class="verified-badge">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                        stroke-linejoin="round">
                        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                        <polyline points="22 4 12 14.01 9 11.01"></polyline>
                    </svg>
                    Cuenta Verificada con Google
                </div>
            </div>
        </section>
    </main>

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