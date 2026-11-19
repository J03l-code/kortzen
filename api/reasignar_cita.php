<?php
require_once __DIR__ . '/../config.php';
header('Content-Type: application/json; charset=utf-8');
try {
    if (!isLoggedIn()) { http_response_code(401); throw new InvalidArgumentException('Inicia sesión.'); }
    if (!is_string($_POST['csrf_token'] ?? null) || !verifyCSRFToken($_POST['csrf_token'])) {
        throw new InvalidArgumentException('Actualiza la página y vuelve a intentar.');
    }
    $operador = getOperadorVenta(getConnection(), (int) $_SESSION['user_id']);
    reasignarCita(getConnection(), (int) ($_POST['cita_id'] ?? 0), (int) ($_POST['barbero_id'] ?? 0), $operador);
    registrarLog('REASIGNAR', 'citas', (int) $_POST['cita_id'], 'Barbero asignado: #' . (int) $_POST['barbero_id']);
    echo json_encode(['success' => true, 'message' => 'Cita reasignada. Se conservan su fecha, servicio e historial.']);
} catch (InvalidArgumentException $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} catch (Throwable $e) {
    error_log('Reasignar cita: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'No se pudo reasignar. La cita se conserva sin cambios.']);
}
