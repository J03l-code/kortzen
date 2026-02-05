<?php
require_once '../config.php';
requireLogin();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$client_id = $_GET['client_id'] ?? null;

if (!$client_id) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing client_id']);
    exit;
}

try {
    // Buscar las últimas 5 citas completadas o canceladas del cliente
    // No mostramos las pendientes futuras porque eso es "Próximas Citas", no historial
    $history = query("SELECT c.fecha_hora, c.estado, c.precio_final, c.notas, s.nombre as servicio_nombre, u.nombre as barbero_nombre
                      FROM citas c
                      JOIN servicios s ON c.servicio_id = s.id
                      LEFT JOIN usuarios u ON c.barbero_id = u.id
                      WHERE c.cliente_id = ? 
                      AND c.estado IN ('completada', 'cancelada')
                      ORDER BY c.fecha_hora DESC
                      LIMIT 5", 
                      [$client_id]);

    echo json_encode(['success' => true, 'history' => $history]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error: ' . $e->getMessage()]);
}
