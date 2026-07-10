<?php
session_start();
require_once 'config.php';

if (!isClienteLoggedIn()) {
    header('Location: cliente-login.php');
    exit;
}

$cliente = getCurrentCliente();
$mensaje = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $telefono = trim($_POST['telefono'] ?? '');

    // Actualizar tel
    $pdo = getConnection();
    $stmt = $pdo->prepare("UPDATE clientes SET telefono = ? WHERE id = ?");
    if ($stmt->execute([$telefono, $cliente['id']])) {
        $mensaje = 'Perfil actualizado correctamente.';
        // Refrescar datos
        $cliente['telefono'] = $telefono;
    } else {
        $mensaje = 'Error al actualizar.';
    }
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mi Perfil - KORTZEN</title>
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
            max-width: 600px;
            margin: 0 auto;
        }

        .header {
            margin-bottom: 30px;
        }

        .back-btn {
            color: #888;
            text-decoration: none;
            display: block;
            margin-bottom: 10px;
        }

        .title {
            font-size: 2rem;
            color: #FFFFFF;
        }

        .profile-card {
            background: #1A1A1A;
            border: 1px solid #333;
            border-radius: 12px;
            padding: 30px;
        }

        .avatar-section {
            text-align: center;
            margin-bottom: 30px;
        }

        .avatar {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            border: 3px solid #333333;
            object-fit: cover;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .label {
            display: block;
            color: #888;
            margin-bottom: 5px;
            font-size: 0.9rem;
        }

        .input {
            width: 100%;
            padding: 12px;
            background: #0A0A0A;
            border: 1px solid #333;
            color: white;
            border-radius: 6px;
            font-family: inherit;
        }

        .input:focus {
            border-color: #333333;
            outline: none;
        }

        .input[readonly] {
            opacity: 0.6;
            cursor: not-allowed;
        }

        .btn {
            background: #333333;
            color: #000;
            border: none;
            padding: 12px;
            width: 100%;
            border-radius: 6px;
            font-weight: bold;
            cursor: pointer;
            transition: opacity 0.2s;
        }

        .btn:hover {
            opacity: 0.9;
        }

        .alert {
            background: rgba(40, 167, 69, 0.2);
            color: #28a745;
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 20px;
            text-align: center;
        }
    </style>
</head>

<body>

    <div class="container">
        <div class="header">
            <a href="cliente-dashboard.php" class="back-btn">← Volver al Dashboard</a>
            <h1 class="title">Mi Perfil</h1>
        </div>

        <?php if ($mensaje): ?>
            <div class="alert">
                <?php echo htmlspecialchars($mensaje); ?>
            </div>
        <?php endif; ?>

        <div class="profile-card">
            <div class="avatar-section">
                <?php if (!empty($cliente['foto_perfil'])): ?>
                    <img src="<?php echo htmlspecialchars($cliente['foto_perfil']); ?>" class="avatar" alt="Avatar">
                <?php else: ?>
                    <div class="avatar"
                        style="background: #333; display: flex; align-items: center; justify-content: center; margin: 0 auto; font-size: 2rem;">
                        <?php echo strtoupper(substr($cliente['nombre'], 0, 1)); ?>
                    </div>
                <?php endif; ?>
                <h2 style="margin-top: 15px;">
                    <?php echo htmlspecialchars($cliente['nombre']); ?>
                </h2>
                <p style="color: #666;">Cliente KORTZEN</p>
            </div>

            <form method="POST">
                <div class="form-group">
                    <label class="label">Email (Vinculado a Google)</label>
                    <input type="text" class="input" value="<?php echo htmlspecialchars($cliente['email']); ?>"
                        readonly>
                </div>

                <div class="form-group">
                    <label class="label">Teléfono de Contacto</label>
                    <input type="tel" name="telefono" class="input" placeholder="Ej: +34 600 000 000"
                        value="<?php echo htmlspecialchars($cliente['telefono'] ?? ''); ?>">
                </div>

                <button type="submit" class="btn">Guardar Cambios</button>
            </form>
        </div>
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