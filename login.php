<?php
/**
 * Login para STAFF/BARBEROS
 * Solo acceso con email y contraseña
 */

require_once 'config.php';

// Si ya está logueado como staff, redirigir al dashboard
if (isLoggedIn()) {
    header('Location: dashboard.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error = 'Por favor complete todos los campos';
    } else {
        try {
            $sql = "SELECT * FROM usuarios WHERE email = ?";
            $result = query($sql, [$email]);

            if (count($result) > 0) {
                $user = $result[0];

                if (password_verify($password, $user['password'])) {
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['user_nombre'] = $user['nombre'];
                    $_SESSION['user_email'] = $user['email'];
                    $_SESSION['user_rol'] = $user['rol'];

                    session_regenerate_id(true);

                    header('Location: dashboard.php');
                    exit;
                } else {
                    $error = 'Credenciales incorrectas';
                }
            } else {
                $error = 'Credenciales incorrectas';
            }
        } catch (PDOException $e) {
            error_log("Error en login: " . $e->getMessage());
            $error = 'Error al procesar la solicitud';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>KORTZEN - Acceso Barberos</title>
    <link rel="stylesheet" href="css/style.css">
</head>

<body>
    <div class="login-wrapper">
        <div class="login-box">
            <h1 class="login-logo">KORTZEN</h1>
            <p class="login-subtitle">Acceso para Barberos</p>

            <?php if ($error): ?>
                <div class="alert alert-danger">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="" class="login-form">
                <div class="form-group">
                    <label for="email" class="form-label">Email</label>
                    <input type="email" id="email" name="email" class="form-input"
                        value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required autocomplete="email">
                </div>

                <div class="form-group">
                    <label for="password" class="form-label">Contraseña</label>
                    <input type="password" id="password" name="password" class="form-input" required
                        autocomplete="current-password">
                </div>

                <button type="submit" class="btn-login">Iniciar Sesión</button>
            </form>

            <div style="margin-top: 15px; margin-bottom: 15px;">
                <a href="cliente-login.php" style="color: #aaaaaa; font-size: 0.85rem; text-decoration: none; display: inline-block; transition: color 0.2s;" onmouseover="this.style.color='#ffffff'" onmouseout="this.style.color='#aaaaaa'">¿Eres Cliente? Inicia Sesión Aquí →</a>
            </div>

            <a href="index.html" class="back-link">← Volver al sitio</a>
        </div>
    </div>
</body>

</html>