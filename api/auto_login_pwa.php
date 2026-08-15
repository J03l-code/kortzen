<?php
/**
 * KORTZEN - Auto Login API for PWA Standalone Mode
 * Restaura la sesión del cliente instantáneamente desde el token de la app
 */
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json');

$clientId = intval($_POST['client_id'] ?? $_GET['client_id'] ?? 0);

if ($clientId > 0) {
    try {
        $pdo = getConnection();
        $stmt = $pdo->prepare("SELECT id, nombre, email, foto_perfil, google_id FROM clientes WHERE id = ?");
        $stmt->execute([$clientId]);
        $c = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($c) {
            $_SESSION['cliente_logged_in'] = true;
            $_SESSION['cliente_id'] = $c['id'];
            $_SESSION['cliente_nombre'] = $c['nombre'];
            $_SESSION['cliente_email'] = $c['email'];
            $_SESSION['cliente_foto'] = $c['foto_perfil'] ?? null;
            $_SESSION['cliente_google_id'] = $c['google_id'] ?? null;

            setcookie('kortzen_pwa_client_id', (string)$c['id'], [
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

echo json_encode(['success' => false]);
