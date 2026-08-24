<?php
/**
 * KORTZEN - Actualizar Horario General a Lunes-Domingo 10:00 a 20:00
 */
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json');

try {
    $pdo = getConnection();

    // 1. Actualizar sucursales
    $pdo->exec("UPDATE sucursales SET horario_apertura = '10:00:00', horario_cierre = '20:00:00'");

    // 2. Actualizar horarios_barberos
    try {
        $pdo->exec("UPDATE horarios_barberos SET hora_inicio = '10:00:00', hora_fin = '20:00:00', activo = 1");
    } catch (Exception $e) {}

    echo json_encode([
        'success' => true,
        'message' => 'Horario actualizado en todas las sucursales y barberos a 10:00 - 20:00 (Lunes a Domingo).'
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
