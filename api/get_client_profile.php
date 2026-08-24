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

    $stmt = $pdo->prepare("SELECT id, nombre, email, telefono, foto_perfil FROM clientes WHERE id = ?");
    $stmt->execute([$cliente['id']]);
    $data = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($data) {
        $foto = !empty($data['foto_perfil']) ? $data['foto_perfil'] : ($_SESSION['cliente_foto'] ?? null);
        $data['foto'] = $foto;
        $data['foto_perfil'] = $foto;
        echo json_encode(['success' => true, 'cliente' => $data]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Cliente no encontrado']);
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error del servidor']);
}
