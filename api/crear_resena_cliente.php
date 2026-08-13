<?php
/**
 * KORTZEN - API Crear Reseña del Cliente (PWA)
 */

session_start();
require_once '../config.php';

header('Content-Type: application/json');

if (!isClienteLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Debes iniciar sesión.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método no permitido.']);
    exit;
}

$cliente = getCurrentCliente();
$calificacion = intval($_POST['calificacion'] ?? 5);
$comentario = trim($_POST['comentario'] ?? '');

if ($calificacion < 1) $calificacion = 1;
if ($calificacion > 5) $calificacion = 5;

if (empty($comentario)) {
    echo json_encode(['success' => false, 'message' => 'Escribe un comentario sobre tu servicio.']);
    exit;
}

try {
    $pdo = getConnection();
    $stmt = $pdo->prepare("INSERT INTO resenas (cliente_nombre, comentario, calificacion, fecha, visible) VALUES (?, ?, ?, CURDATE(), 1)");
    $stmt->execute([$cliente['nombre'], $comentario, $calificacion]);

    echo json_encode(['success' => true, 'message' => '¡Gracias por tu opinión! Tu calificación ha sido registrada.']);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error al guardar la reseña: ' . $e->getMessage()]);
}
