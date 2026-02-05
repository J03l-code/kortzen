<?php
require_once '../config.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

$currentUser = getCurrentUser();
$fecha = $_GET['fecha'] ?? date('Y-m-d');
// Prioritize passed sucursal_id, else fallback to user's sucursal (if admin_local/barber), else global if admin
$sucursal_id = isset($_GET['sucursal_id']) ? intval($_GET['sucursal_id']) : ($currentUser['sucursal_id'] ?? null);

try {
    $pdo = getConnection();

    $sql = "SELECT c.id, c.fecha_hora, c.estado, c.precio_final, 
                   u.nombre as barbero, 
                   s.nombre as servicio, 
                   cli.nombre as cliente
            FROM citas c
            JOIN usuarios u ON c.barbero_id = u.id
            JOIN servicios s ON c.servicio_id = s.id
            JOIN clientes cli ON c.cliente_id = cli.id
            WHERE DATE(c.fecha_hora) = ?";

    $params = [$fecha];

    if ($sucursal_id) {
        $sql .= " AND c.sucursal_id = ?";
        $params[] = $sucursal_id;
    }

    $sql .= " ORDER BY c.fecha_hora ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $citas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Calculate totals
    $totalRecaudado = 0;
    $totalCitas = count($citas);

    foreach ($citas as &$cita) {
        if ($cita['estado'] === 'completada') {
            $totalRecaudado += floatval($cita['precio_final']);
        }
    }

    echo json_encode([
        'success' => true,
        'fecha' => $fecha,
        'citas' => $citas,
        'total_recaudado' => $totalRecaudado,
        'total_citas' => $totalCitas
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>