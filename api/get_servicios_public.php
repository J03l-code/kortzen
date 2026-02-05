<?php
require_once '../config.php';

header('Content-Type: application/json');

try {
    $pdo = getConnection();

    // Fetch all active services
    $sql = "SELECT * FROM servicios WHERE activo = 1 ORDER BY categoria ASC, orden ASC, nombre ASC";
    // If 'orden' doesn't exist yet, we just order by name. Let's check schema.
    // Schema doesn't have 'orden'. We'll order by categoria then nombre.
    $sql = "SELECT id, nombre, descripcion, precio, duracion_minutos, categoria, foto_url, destacado FROM servicios WHERE activo = 1 ORDER BY categoria DESC, nombre ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $servicios = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'data' => $servicios]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
