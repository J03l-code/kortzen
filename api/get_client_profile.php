<?php
require_once '../config.php';

header('Content-Type: application/json');

if (!isClienteLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

try {
    $cliente = getCurrentCliente();
    $pdo = getConnection();

    $stmt = $pdo->prepare("SELECT id, nombre, email, telefono FROM clientes WHERE id = ?");
    $stmt->execute([$cliente['id']]);
    $data = $stmt->fetch();

    if ($data) {
        echo json_encode(['success' => true, 'cliente' => $data]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Cliente no encontrado']);
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error del servidor']);
}
