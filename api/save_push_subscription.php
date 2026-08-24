<?php
/**
 * KORTZEN - API Registro de Suscripción Notificaciones Push Web / PWA
 */
require_once '../config.php';

header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);

if (!$input || empty($input['endpoint'])) {
    echo json_encode(['success' => false, 'message' => 'Suscripción invàlida o sin endpoint.']);
    exit;
}

try {
    $pdo = getConnection();
    
    // Auto-migración tabla push_subscriptions
    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS push_subscriptions (
                id INT AUTO_INCREMENT PRIMARY KEY,
                cliente_id INT NULL,
                endpoint TEXT NOT NULL,
                p256dh TEXT NULL,
                auth TEXT NULL,
                fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_cliente (cliente_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");
    } catch (Exception $ex) {}

    $endpoint = $input['endpoint'];
    $keys = $input['keys'] ?? [];
    $p256dh = $keys['p256dh'] ?? '';
    $auth = $keys['auth'] ?? '';
    $clienteId = isset($_SESSION['cliente_id']) ? intval($_SESSION['cliente_id']) : null;

    // Verificar si ya existe este endpoint
    $stmtCheck = $pdo->prepare("SELECT id FROM push_subscriptions WHERE endpoint = ?");
    $stmtCheck->execute([$endpoint]);
    $existingId = $stmtCheck->fetchColumn();

    if ($existingId) {
        $stmtUpd = $pdo->prepare("UPDATE push_subscriptions SET cliente_id = ?, p256dh = ?, auth = ? WHERE id = ?");
        $stmtUpd->execute([$clienteId, $p256dh, $auth, $existingId]);
    } else {
        $stmtIns = $pdo->prepare("INSERT INTO push_subscriptions (cliente_id, endpoint, p256dh, auth) VALUES (?, ?, ?, ?)");
        $stmtIns->execute([$clienteId, $p256dh, $auth]);
    }

    echo json_encode([
        'success' => true, 
        'message' => 'Suscripción de notificaciones push guardada correctamente.'
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
