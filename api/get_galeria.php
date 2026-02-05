<?php
require_once '../config.php';
header('Content-Type: application/json');

try {
    $pdo = getConnection();
    $sql = "SELECT * FROM galeria_imagenes ORDER BY id DESC"; // LIFO (Last In First Out)
    $stmt = $pdo->query($sql);
    $imagenes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'data' => $imagenes]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>