<?php
require_once '../config.php';

header('Content-Type: application/json');

try {
    $pdo = getConnection();

    $sucursal_id = isset($_GET['sucursal_id']) ? intval($_GET['sucursal_id']) : 0;

    $sql = "SELECT * FROM servicios WHERE activo = 1";
    $params = [];

    if ($sucursal_id > 0) {
        // Si existe tabla servicios_sucursales, buscar ahí o si sucursal_id coincide o es global
        try {
            $checkSS = $pdo->query("SELECT COUNT(*) FROM servicios_sucursales WHERE sucursal_id = $sucursal_id")->fetchColumn();
            if ($checkSS > 0) {
                $sql = "SELECT s.* FROM servicios s 
                        INNER JOIN servicios_sucursales ss ON s.id = ss.servicio_id 
                        WHERE s.activo = 1 AND ss.sucursal_id = ? 
                        ORDER BY s.id ASC";
                $params = [$sucursal_id];
            } else {
                $sql = "SELECT * FROM servicios WHERE activo = 1 AND (sucursal_id = ? OR sucursal_id IS NULL OR sucursal_id = 0) ORDER BY id ASC";
                $params = [$sucursal_id];
            }
        } catch (Throwable $e) {
            $sql = "SELECT * FROM servicios WHERE activo = 1 AND (sucursal_id = ? OR sucursal_id IS NULL OR sucursal_id = 0) ORDER BY id ASC";
            $params = [$sucursal_id];
        }
    } else {
        $sql .= " ORDER BY id ASC";
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $servicios = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($servicios as &$s) {
        if (empty($s['foto_url']) && !empty($s['imagen_url'])) {
            $s['foto_url'] = $s['imagen_url'];
        }
        if (empty($s['imagen_url']) && !empty($s['foto_url'])) {
            $s['imagen_url'] = $s['foto_url'];
        }
        if (empty($s['categoria'])) {
            $s['categoria'] = 'General';
        }
    }

    echo json_encode(['success' => true, 'data' => $servicios]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

