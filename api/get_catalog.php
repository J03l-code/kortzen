<?php
require_once '../config.php';

header('Content-Type: application/json');

$type = $_GET['type'] ?? 'services'; // services | barbers

try {
    $pdo = getConnection();

    if ($type === 'barbers') {
        $sucursalId = isset($_GET['sucursal_id']) ? intval($_GET['sucursal_id']) : 0;

        // Return user photo if available
        $sql = "SELECT u.id, u.nombre, s.nombre as sucursal_nombre, u.foto_url as foto_perfil
                FROM usuarios u 
                LEFT JOIN sucursales s ON u.sucursal_id = s.id 
                WHERE (u.rol = 'barbero' OR u.rol = 'admin_local') AND u.activo = 1";

        if ($sucursalId > 0) {
            $sql .= " AND (u.sucursal_id = $sucursalId OR u.sucursal_id IS NULL OR u.sucursal_id = 0)";
        }
        $sql .= " ORDER BY u.id ASC";

        $data = query($sql);
        echo json_encode(['barberos' => $data]);
    } else {
        $sucursalId = isset($_GET['sucursal_id']) ? intval($_GET['sucursal_id']) : 0;

        $sql = "SELECT s.id, s.nombre, s.descripcion, s.precio, s.duracion_minutos, s.foto_url, s.categoria 
                FROM servicios s";

        if ($sucursalId > 0) {
            try {
                $checkSS = $pdo->query("SELECT COUNT(*) FROM servicios_sucursales WHERE sucursal_id = $sucursalId")->fetchColumn();
                if ($checkSS > 0) {
                    $sql .= " INNER JOIN servicios_sucursales ss ON s.id = ss.servicio_id WHERE s.activo = 1 AND ss.sucursal_id = $sucursalId";
                } else {
                    $sql .= " WHERE s.activo = 1 AND (s.sucursal_id = $sucursalId OR s.sucursal_id IS NULL OR s.sucursal_id = 0)";
                }
            } catch (Throwable $e) {
                $sql .= " WHERE s.activo = 1 AND (s.sucursal_id = $sucursalId OR s.sucursal_id IS NULL OR s.sucursal_id = 0)";
            }
        } else {
            $sql .= " WHERE s.activo = 1";
        }

        $sql .= " ORDER BY s.id ASC";

        $data = query($sql);
        echo json_encode(['servicios' => $data]);
    }

} catch (PDOException $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
