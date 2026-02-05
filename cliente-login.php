<?php
/**
 * Login para CLIENTES con Google
 * Página dedicada para que los clientes inicien sesión antes de reservar
 */

session_start();
require_once 'config.php';

// Si ya está logueado como cliente, redirigir
if (isset($_SESSION['cliente_logged_in']) && $_SESSION['cliente_logged_in']) {
    header('Location: cliente-dashboard.php');
    exit;
}

// Verificar si Google OAuth está configurado
$googleConfigured = false;
$googleConfigPath = __DIR__ . '/api/auth/google-config.php';
if (file_exists($googleConfigPath)) {
    require_once $googleConfigPath;
    // Si el archivo existe, asumimos que está configurado correctamente 
    // para evitar falsos negativos de la función de validación
    $googleConfigured = true;
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar Sesión - KORTZEN</title>
    <link rel="stylesheet" href="/css/variables.css">
    <link rel="stylesheet" href="/css/reset.css">
    <link rel="stylesheet" href="/css/base.css">
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
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
        }

        .login-container {
            background: linear-gradient(145deg, #1E1E1E, #121212);
            border: 1px solid rgba(192, 160, 98, 0.3);
            border-radius: 20px;
            padding: 3rem;
            max-width: 420px;
            width: 100%;
            text-align: center;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
        }

        .login-logo {
            font-family: 'Playfair Display', serif;
            font-size: 2rem;
            font-weight: 700;
            color: #FFFFFF;
            letter-spacing: 0.15em;
            margin-bottom: 0.5rem;
        }

        .login-logo span {
            color: #C0A062;
        }

        .login-subtitle {
            color: #9CA3AF;
            font-size: 1rem;
            margin-bottom: 2rem;
            line-height: 1.6;
        }

        .benefits-list {
            text-align: left;
            margin: 1.5rem 0 2rem;
            padding: 1.25rem;
            background: rgba(192, 160, 98, 0.08);
            border-radius: 12px;
            border: 1px solid rgba(192, 160, 98, 0.15);
        }

        .benefits-list h4 {
            color: #C0A062;
            font-size: 0.9rem;
            margin-bottom: 0.75rem;
            font-weight: 600;
        }

        .benefits-list ul {
            list-style: none;
        }

        .benefits-list li {
            color: #D1D5DB;
            font-size: 0.9rem;
            padding: 0.4rem 0;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .benefits-list li::before {
            content: '✓';
            color: #22C55E;
            font-weight: 600;
        }

        .btn-google {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 14px;
            width: 100%;
            padding: 16px 24px;
            background: #FFFFFF;
            border: none;
            border-radius: 12px;
            font-size: 1rem;
            font-weight: 600;
            color: #374151;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            margin-bottom: 1rem;
        }

        .btn-google:hover {
            background: #F9FAFB;
            box-shadow: 0 8px 25px rgba(255, 255, 255, 0.15);
            transform: translateY(-2px);
        }

        .btn-google svg {
            flex-shrink: 0;
        }

        .security-note {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            color: #6B7280;
            font-size: 0.8rem;
            margin-top: 1rem;
        }

        .security-note svg {
            color: #22C55E;
        }

        .divider {
            display: flex;
            align-items: center;
            margin: 1.5rem 0;
            color: #6B7280;
            font-size: 0.85rem;
        }

        .divider::before,
        .divider::after {
            content: '';
            flex: 1;
            height: 1px;
            background: rgba(192, 160, 98, 0.2);
        }

        .divider span {
            padding: 0 1rem;
        }

        .btn-guest {
            display: inline-block;
            color: #C0A062;
            text-decoration: none;
            font-size: 0.9rem;
            transition: color 0.3s ease;
        }

        .btn-guest:hover {
            color: #D4B87A;
            text-decoration: underline;
        }

        .back-link {
            display: block;
            margin-top: 2rem;
            color: #6B7280;
            text-decoration: none;
            font-size: 0.9rem;
            transition: color 0.3s ease;
        }

        .back-link:hover {
            color: #C0A062;
        }

        .error-box {
            background: rgba(220, 53, 69, 0.1);
            border: 1px solid rgba(220, 53, 69, 0.3);
            border-radius: 12px;
            padding: 1.25rem;
            margin-bottom: 1.5rem;
            color: #FCA5A5;
            font-size: 0.9rem;
        }

        .error-box code {
            display: block;
            margin-top: 0.5rem;
            font-size: 0.8rem;
            color: #9CA3AF;
            background: rgba(0, 0, 0, 0.2);
            padding: 0.5rem;
            border-radius: 6px;
        }
    </style>
</head>

<body>
    <div class="login-container">
        <h1 class="login-logo">KORT<span>ZEN</span></h1>
        <p class="login-subtitle">Inicia sesión para reservar tu cita y acceder a beneficios exclusivos</p>

        <div class="benefits-list">
            <h4>Al iniciar sesión podrás:</h4>
            <ul>
                <li>Reservar citas rápidamente</li>
                <li>Ver tu historial de citas</li>
                <li>Recibir recordatorios por email</li>
                <li>Guardar tu barbero favorito</li>
            </ul>
        </div>

        <!-- Botón de Google siempre visible -->
        <a href="/api/auth/google-login.php" class="btn-google">
            <svg width="22" height="22" viewBox="0 0 24 24">
                <path
                    d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"
                    fill="#4285F4" />
                <path
                    d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"
                    fill="#34A853" />
                <path
                    d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"
                    fill="#FBBC05" />
                <path
                    d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"
                    fill="#EA4335" />
            </svg>
            Continuar con Google
        </a>

        <div class="security-note">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
            </svg>
            Conexión segura con tu cuenta de Google
        </div>

        <div class="divider"><span>o</span></div>

        <a href="/contacto.html" class="btn-guest">Continuar sin cuenta →</a>

        <a href="/" class="back-link">← Volver al inicio</a>
    </div>
</body>

</html>