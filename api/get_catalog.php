<?php
require_once '../config.php';

header('Content-Type: application/json');

$type = $_GET['type'] ?? 'services'; // services | barbers

try {
    $pdo = getConnection();

    if ($type === 'barbers') {
        $sucursalId = isset($_GET['sucursal_id']) ? intval($_GET['sucursal_id']) : 0;

        $sql = "SELECT u.id, u.nombre, s.nombre as sucursal_nombre
                FROM usuarios u 
                LEFT JOIN sucursales s ON u.sucursal_id = s.id 
                WHERE u.rol = 'barbero' AND u.activo = 1";

        if ($sucursalId > 0) {
            $sql .= " AND (u.sucursal_id = $sucursalId OR u.sucursal_id IS NULL)";
        }

        $data = query($sql);
        echo json_encode(['barberos' => $data]);
    } else {
        $sucursalId = isset($_GET['sucursal_id']) ? intval($_GET['sucursal_id']) : 0;

        $sql = "SELECT s.id, s.nombre, s.precio, s.duracion_minutos 
                FROM servicios s";

        if ($sucursalId > 0) {
            $sql .= " INNER JOIN servicios_sucursales ss ON s.id = ss.servicio_id 
                      WHERE s.activo = 1 AND ss.sucursal_id = $sucursalId";
        } else {
            $sql .= " WHERE s.activo = 1";
        }

        $data = query($sql);
        echo json_encode(['servicios' => $data]);
    }

} catch (PDOException $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
