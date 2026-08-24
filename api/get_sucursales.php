<?php
/**
 * KORTZEN - API Pública de Sucursales
 * Retorna las sucursales activas y próximas aperturas
 */
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json');

try {
    $pdo = getConnection();

    // Auto-migración en caso de que aún no exista la columna estado
    try {
        $pdo->exec("ALTER TABLE sucursales ADD COLUMN estado VARCHAR(50) DEFAULT 'activo' AFTER telefono");
    } catch (Exception $ex) {}

    // Obtener sucursales activas y próximamente
    $stmt = $pdo->prepare("
        SELECT id, nombre, direccion, telefono, 
               DATE_FORMAT(COALESCE(horario_apertura, '09:00:00'), '%H:%i') as horario_apertura, 
               DATE_FORMAT(COALESCE(horario_cierre, '20:00:00'), '%H:%i') as horario_cierre, 
               COALESCE(estado, 'activo') as estado,
               imagen_url, mapa_url
        FROM sucursales 
        WHERE estado IN ('activo', 'proximamente') OR estado IS NULL
        ORDER BY CASE WHEN estado = 'activo' THEN 1 ELSE 2 END, id ASC
    ");
    $stmt->execute();
    $sucursales = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Formatear datos para cliente
    foreach ($sucursales as &$s) {
        $s['openTime'] = $s['horario_apertura'] ?: '09:00';
        $s['closeTime'] = $s['horario_cierre'] ?: '20:00';
        $s['name'] = $s['nombre'];
        $s['address'] = $s['direccion'] ?: 'Quito';
        $s['phone'] = $s['telefono'] ?: '';
        $s['isProximamente'] = ($s['estado'] === 'proximamente');
    }

    echo json_encode([
        'success' => true,
        'data' => $sucursales
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
