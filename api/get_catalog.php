<?php
require_once '../config.php';

header('Content-Type: application/json');

$type = $_GET['type'] ?? 'services'; // services | barbers

try {
    $pdo = getConnection();

    if ($type === 'barbers') {
        $sql = "SELECT u.id, u.nombre, s.nombre as sucursal_nombre
                FROM usuarios u 
                LEFT JOIN sucursales s ON u.sucursal_id = s.id 
                WHERE u.rol = 'barbero' AND u.activo = 1";
        $data = query($sql);
        echo json_encode(['barberos' => $data]);
    } else {
        $sql = "SELECT id, nombre, precio, duracion_minutos FROM servicios WHERE activo = 1";
        $data = query($sql);
        echo json_encode(['servicios' => $data]);
    }

} catch (PDOException $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
