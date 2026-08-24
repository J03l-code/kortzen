<?php
/**
 * KORTZEN - API Pública de Sucursales (Optimizada sin DDL)
 * Retorna las sucursales activas y próximas aperturas
 */
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json');

try {
    $pdo = getConnection();

    // Intentar consulta con columnas extendidas
    try {
        $stmt = $pdo->prepare("
            SELECT id, nombre, direccion, telefono, 
                   DATE_FORMAT(COALESCE(horario_apertura, '09:00:00'), '%H:%i') as horario_apertura, 
                   DATE_FORMAT(COALESCE(horario_cierre, '20:00:00'), '%H:%i') as horario_cierre, 
                   COALESCE(estado, 'activo') as estado,
                   imagen_url, mapa_url
            FROM sucursales 
            WHERE COALESCE(estado, 'activo') IN ('activo', 'proximamente')
            ORDER BY CASE WHEN COALESCE(estado, 'activo') = 'activo' THEN 1 ELSE 2 END, id ASC
        ");
        $stmt->execute();
        $sucursales = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $exSql) {
        // Fallback básico si la tabla aún no tiene las nuevas columnas
        $stmt = $pdo->prepare("SELECT * FROM sucursales");
        $stmt->execute();
        $sucursales = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Formatear datos para cliente
    $formatted = [];
    foreach ($sucursales as $s) {
        $estado = $s['estado'] ?? 'activo';
        if ($estado === 'inactivo') continue;

        $formatted[] = [
            'id' => intval($s['id']),
            'name' => $s['nombre'],
            'address' => $s['direccion'] ?: 'Quito',
            'phone' => $s['telefono'] ?: '',
            'openTime' => !empty($s['horario_apertura']) ? date('H:i', strtotime($s['horario_apertura'])) : '09:00',
            'closeTime' => !empty($s['horario_cierre']) ? date('H:i', strtotime($s['horario_cierre'])) : '20:00',
            'estado' => $estado,
            'isProximamente' => ($estado === 'proximamente'),
            'mapa_url' => $s['mapa_url'] ?? null
        ];
    }

    echo json_encode([
        'success' => true,
        'data' => $formatted
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
