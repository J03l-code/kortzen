<?php
require_once '../config.php';

header('Content-Type: application/json');

try {
    $sql = "SELECT id, cliente_nombre, comentario, calificacion, fecha 
            FROM resenas 
            WHERE visible = 1 
            ORDER BY created_at DESC, id DESC 
            LIMIT 10";

    $reviews = query($sql);
    echo json_encode(['success' => true, 'reviews' => $reviews]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
