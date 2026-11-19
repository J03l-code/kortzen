<?php
require_once __DIR__ . '/../config.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: private, no-store');

try {
    if (!isLoggedIn()) {
        throw new InvalidArgumentException('Inicia sesión para consultar el cierre de caja.');
    }
    $pdo = getConnection();
    $usuario = getOperadorVenta($pdo, (int) $_SESSION['user_id']);
    if (!esAdministradorCaja($usuario)) {
        throw new InvalidArgumentException('Acceso reservado a administración.');
    }

    $reporte = resumenCaja($pdo, $usuario, $_GET);
    echo json_encode(['success' => true, 'reporte' => $reporte]);
} catch (InvalidArgumentException $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} catch (Throwable $e) {
    error_log('Caja API error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'No se pudo generar el cierre de caja.']);
}
