<?php
/**
 * Dashboard del Cliente
 * Página de bienvenida después de iniciar sesión con Google
 */

session_start();
require_once 'config.php';

// Verificar si el cliente está logueado
if (!isset($_SESSION['cliente_logged_in']) || !$_SESSION['cliente_logged_in']) {
    header('Location: /');
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
    <link rel="stylesheet" href="/css/variables.css">
    <link rel="stylesheet" href="/css/reset.css">
    <link rel="stylesheet" href="/css/base.css">
    <link rel="stylesheet" href="/css/components.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: linear-gradient(135deg, #0A0A0A 0%, #1E1E1E 50%, #0A0A0A 100%);
            min-height: 100vh;
            color: #F5F5F5;
        }

        .dashboard-header {
            background: rgba(18, 18, 18, 0.95);
            border-bottom: 1px solid rgba(192, 160, 98, 0.3);
            padding: 1rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .dashboard-logo {
            font-family: 'Playfair Display', serif;
            font-size: 1.5rem;
            font-weight: 700;
            color: #FFFFFF;
            text-decoration: none;
            letter-spacing: 0.1em;
        }

        .dashboard-logo span {
            color: #C0A062;
        }

        .user-menu {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid #C0A062;
        }

        .user-avatar-placeholder {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, #C0A062, #8B7355);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 1rem;
            color: #0A0A0A;
        }

        .logout-btn {
            padding: 0.5rem 1.25rem;
            background: transparent;
            border: 1px solid #C0A062;
            color: #C0A062;
            border-radius: 6px;
            font-size: 0.875rem;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
        }

        .logout-btn:hover {
            background: #C0A062;
            color: #0A0A0A;
        }

        .dashboard-main {
            max-width: 1200px;
            margin: 0 auto;
            padding: 3rem 2rem;
        }

        .welcome-section {
            text-align: center;
            margin-bottom: 3rem;
        }

        .welcome-title {
            font-family: 'Playfair Display', serif;
            font-size: 2.5rem;
            margin-bottom: 0.5rem;
        }

        .welcome-title span {
            color: #C0A062;
        }

        .welcome-subtitle {
            color: #9CA3AF;
            font-size: 1.1rem;
        }

        .cards-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1.5rem;
        }

        .card {
            background: rgba(30, 30, 30, 0.8);
            border: 1px solid rgba(192, 160, 98, 0.2);
            border-radius: 16px;
            padding: 2rem;
            transition: all 0.3s ease;
        }

        .card:hover {
            border-color: rgba(192, 160, 98, 0.5);
            transform: translateY(-4px);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
        }

        .card-icon {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, rgba(192, 160, 98, 0.2), rgba(192, 160, 98, 0.1));
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1.5rem;
            color: #C0A062;
        }

        .card-title {
            font-size: 1.25rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        .card-description {
            color: #9CA3AF;
            font-size: 0.95rem;
            line-height: 1.6;
            margin-bottom: 1.5rem;
        }

        .card-btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem 1.5rem;
            background: linear-gradient(135deg, #C0A062, #8B7355);
            color: #0A0A0A;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
        }

        .card-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(192, 160, 98, 0.3);
        }

        .profile-section {
            background: rgba(30, 30, 30, 0.8);
            border: 1px solid rgba(192, 160, 98, 0.2);
            border-radius: 16px;
            padding: 2rem;
            margin-top: 2rem;
        }

        .profile-header {
            display: flex;
            align-items: center;
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .profile-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid #C0A062;
        }

        .profile-avatar-placeholder {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: linear-gradient(135deg, #C0A062, #8B7355);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 2rem;
            color: #0A0A0A;
        }

        .profile-info h3 {
            font-size: 1.5rem;
            margin-bottom: 0.25rem;
        }

        .profile-info p {
            color: #9CA3AF;
        }

        .verified-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            background: rgba(34, 197, 94, 0.1);
            color: #22C55E;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
            margin-top: 0.5rem;
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
            <a href="/api/auth/google-logout.php" class="logout-btn">Cerrar Sesión</a>
        </div>
    </header>

    <main class="dashboard-main">
        <section class="welcome-section">
            <h1 class="welcome-title">¡Bienvenido, <span>
                    <?php echo htmlspecialchars(explode(' ', $nombre)[0]); ?>
                </span>!</h1>
            <p class="welcome-subtitle">Gestiona tus citas y experiencias en KORTZEN</p>
        </section>

        <div class="cards-grid">
            <div class="card">
                <div class="card-icon">
                    <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                        <line x1="16" y1="2" x2="16" y2="6"></line>
                        <line x1="8" y1="2" x2="8" y2="6"></line>
                        <line x1="3" y1="10" x2="21" y2="10"></line>
                    </svg>
                </div>
                <h3 class="card-title">Reservar Cita</h3>
                <p class="card-description">Agenda tu próxima experiencia de barbería premium con nuestros maestros
                    barberos.</p>
                <a href="/reservar.php" class="card-btn">
                    Reservar Ahora
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="9 18 15 12 9 6"></polyline>
                    </svg>
                </a>
            </div>

            <div class="card">
                <div class="card-icon">
                    <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
                    </svg>
                </div>
                <h3 class="card-title">Mis Citas</h3>
                <p class="card-description">Revisa el historial de tus citas anteriores y las próximas reservaciones.
                </p>
                <a href="/mis-citas.php" class="card-btn"
                    style="background: transparent; border: 1px solid #C0A062; color: #C0A062;">
                    Ver Historial
                </a>
            </div>

            <div class="card">
                <div class="card-icon">
                    <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                        <circle cx="12" cy="7" r="4"></circle>
                    </svg>
                </div>
                <h3 class="card-title">Mi Perfil</h3>
                <p class="card-description">Actualiza tu información personal y preferencias de comunicación.</p>
                <a href="/mi-perfil.php" class="card-btn" style="background: transparent; border: 1px solid #C0A062; color: #C0A062;">
                    Editar Perfil
                </a>
            </div>
        </div>

        <section class="profile-section">
            <div class="profile-header">
                <?php if ($foto): ?>
                    <img src="<?php echo htmlspecialchars($foto); ?>" alt="Avatar" class="profile-avatar">
                <?php else: ?>
                    <div class="profile-avatar-placeholder">
                        <?php echo strtoupper(substr($nombre, 0, 1)); ?>
                    </div>
                <?php endif; ?>
                <div class="profile-info">
                    <h3>
                        <?php echo htmlspecialchars($nombre); ?>
                    </h3>
                    <p>
                        <?php echo htmlspecialchars($email); ?>
                    </p>
                    <div class="verified-badge">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                            stroke-width="2">
                            <polyline points="20 6 9 17 4 12"></polyline>
                        </svg>
                        Cuenta verificada con Google
                    </div>
                </div>
            </div>
        </section>
    </main>
</body>

</html>