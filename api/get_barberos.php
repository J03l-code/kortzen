<?php
require_once '../config.php';

header('Content-Type: application/json');

try {
    $pdo = getConnection();

    $sucursal_id = isset($_GET['sucursal_id']) ? intval($_GET['sucursal_id']) : 0;

    // Base query: Active users with role 'barbero' or 'admin_local'
    // If sucursal_id is provided, filter by it.
    // If not, we might return empty or all. Let's return empty if no branch ID to be safe, or allow 0 for all.

    $params = [];
    $whereAuto = "activo = 1 AND rol = 'barbero'";

    if ($sucursal_id > 0) {
        $sql = "SELECT * FROM usuarios WHERE $whereAuto AND sucursal_id = ?";
        $params[] = $sucursal_id;
    } else {
        $sql = "SELECT * FROM usuarios WHERE $whereAuto";
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $barberos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Add placeholder image and bio fallback
    foreach ($barberos as &$barbero) {
        $barbero['cargo'] = ($barbero['rol'] === 'admin_local') ? 'Gerente / Master Barber' : 'Barbero Profesional';
        $barbero['foto'] = !empty($barbero['foto_url']) ? $barbero['foto_url'] : '/assets/images/barber-placeholder.jpg';
        $barbero['biografia'] = !empty($barbero['biografia']) ? $barbero['biografia'] : (!empty($barbero['bio']) ? $barbero['bio'] : '');
    }


    echo json_encode(['success' => true, 'data' => $barberos]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
