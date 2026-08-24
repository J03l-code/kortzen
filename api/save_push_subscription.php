<?php
/**
 * KORTZEN - API Registro de Suscripción Notificaciones Push Web / PWA
 */
require_once '../config.php';

header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$input = json_decode(file_get_contents('php://input'), true);

$endpoint = $input['endpoint'] ?? $_POST['endpoint'] ?? $_GET['endpoint'] ?? '';
$keys = $input['keys'] ?? $_POST['keys'] ?? [];
$p256dh = is_array($keys) ? ($keys['p256dh'] ?? '') : ($keys ?? '');
$auth = is_array($keys) ? ($keys['auth'] ?? '') : '';
$clienteId = isset($_SESSION['cliente_id']) ? intval($_SESSION['cliente_id']) : intval($_REQUEST['cliente_id'] ?? 0);

if (empty($endpoint)) {
    $endpoint = 'pwa_device_client_' . ($clienteId ?: 'guest') . '_' . Date('YmdHis');
}

try {
    $pdo = getConnection();
    
    // Auto-migración tabla push_subscriptions
    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS push_subscriptions (
                id INT AUTO_INCREMENT PRIMARY KEY,
                cliente_id INT NULL,
                endpoint VARCHAR(500) NOT NULL,
                p256dh TEXT NULL,
                auth TEXT NULL,
                fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_cliente (cliente_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");
    } catch (Exception $ex) {}

    // Verificar si ya existe este cliente o endpoint
    if ($clienteId > 0) {
        $stmtCheckCli = $pdo->prepare("SELECT id FROM push_subscriptions WHERE cliente_id = ?");
        $stmtCheckCli->execute([$clienteId]);
        $existingCliId = $stmtCheckCli->fetchColumn();

        if ($existingCliId) {
            $stmtUpd = $pdo->prepare("UPDATE push_subscriptions SET endpoint = ?, p256dh = ?, auth = ? WHERE id = ?");
            $stmtUpd->execute([$endpoint, $p256dh, $auth, $existingCliId]);
            echo json_encode(['success' => true, 'message' => 'Dispositivo actualizado para el cliente.']);
            exit;
        }
    }

    $stmtCheck = $pdo->prepare("SELECT id FROM push_subscriptions WHERE endpoint = ?");
    $stmtCheck->execute([$endpoint]);
    $existingId = $stmtCheck->fetchColumn();

    if ($existingId) {
        $stmtUpd = $pdo->prepare("UPDATE push_subscriptions SET cliente_id = ?, p256dh = ?, auth = ? WHERE id = ?");
        $stmtUpd->execute([$clienteId, $p256dh, $auth, $existingId]);
    } else {
        $stmtIns = $pdo->prepare("INSERT INTO push_subscriptions (cliente_id, endpoint, p256dh, auth) VALUES (?, ?, ?, ?)");
        $stmtIns->execute([$clienteId ?: null, $endpoint, $p256dh, $auth]);
    }

    echo json_encode([
        'success' => true, 
        'cliente_id' => $clienteId,
        'message' => 'Suscripción de notificaciones push guardada correctamente.'
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
