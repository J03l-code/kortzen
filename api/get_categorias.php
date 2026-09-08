<?php
require_once '../config.php';

header('Content-Type: application/json');

try {
    $pdo = getConnection();
    $categorias = getCategoriasServicios($pdo, true);
    echo json_encode([
        'success' => true,
        'data' => $categorias
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
