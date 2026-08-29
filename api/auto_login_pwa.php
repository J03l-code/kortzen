<?php
/**
 * KORTZEN - Auto Login API for PWA Standalone Mode
 * Restaura la sesión del cliente de forma segura validando el token criptográfico firmado HMAC
 */
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json');

// Si ya está logueado en la sesión activa, retornar éxito inmediatamente
if (isClienteLoggedIn()) {
    echo json_encode(['success' => true, 'redirect' => 'cliente-dashboard.php']);
    exit;
}

// Obtener token desde POST, GET o Cookie segura
$rawToken = $_POST['token'] ?? $_GET['token'] ?? $_COOKIE['kortzen_pwa_token'] ?? '';
$clientId = intval($_POST['client_id'] ?? $_GET['client_id'] ?? 0);

$tokenHash = '';
if (!empty($rawToken)) {
    if (strpos($rawToken, ':') !== false) {
        list($cId, $tokenHash) = explode(':', $rawToken, 2);
        if ($clientId <= 0) {
            $clientId = intval($cId);
        }
    } else {
        $tokenHash = $rawToken;
    }
}

if ($clientId > 0 && !empty($tokenHash)) {
    try {
        $pdo = getConnection();
        $stmt = $pdo->prepare("SELECT id, nombre, email, foto_perfil, google_id FROM clientes WHERE id = ?");
        $stmt->execute([$clientId]);
        $c = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($c && validarPwaToken($c['id'], $c['email'], $tokenHash)) {
            $_SESSION['cliente_logged_in'] = true;
            $_SESSION['cliente_id'] = $c['id'];
            $_SESSION['cliente_nombre'] = $c['nombre'];
            $_SESSION['cliente_email'] = $c['email'];
            $_SESSION['cliente_foto'] = $c['foto_perfil'] ?? null;
            $_SESSION['cliente_google_id'] = $c['google_id'] ?? null;

            session_regenerate_id(true);

            // Renovar cookie PWA segura
            $pwaToken = generarPwaToken($c['id'], $c['email']);
            setcookie('kortzen_pwa_token', $c['id'] . ':' . $pwaToken, [
                'expires' => time() + 31536000,
                'path' => '/',
                'secure' => (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on'),
                'httponly' => true,
                'samesite' => 'Lax'
            ]);

            echo json_encode(['success' => true, 'redirect' => 'cliente-dashboard.php']);
            exit;
        }
    } catch (Exception $e) {}
}

echo json_encode(['success' => false, 'message' => 'Token de sesión PWA inválido o expirado.']);
