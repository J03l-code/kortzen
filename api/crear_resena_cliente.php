<?php
/**
 * KORTZEN - API Crear Reseña del Cliente (PWA / Web)
 * Las nuevas reseñas se registran como PENDIENTES (visible = 0) para moderación del admin.
 */

session_start();
require_once '../config.php';

header('Content-Type: application/json');

if (!isClienteLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Debes iniciar sesión para dejar una reseña.']);
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
    
    // Insertar con visible = 0 para moderación previa por el administrador
    $stmt = $pdo->prepare("INSERT INTO resenas (cliente_nombre, comentario, calificacion, fecha, visible) VALUES (?, ?, ?, CURDATE(), 0)");
    $stmt->execute([$cliente['nombre'], $comentario, $calificacion]);

    echo json_encode([
        'success' => true, 
        'message' => '¡Gracias por tu opinión! Tu reseña ha sido enviada a moderación y será publicada una vez aprobada por el administrador.'
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error al guardar la reseña: ' . $e->getMessage()]);
}
