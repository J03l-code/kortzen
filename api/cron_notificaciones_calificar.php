<?php
/**
 * KORTZEN - Cron Script: Notificaciones Push de Calificación y Reseña Google (30 min después del corte)
 * 
 * Uso:
 * 1. Cron del Servidor:
 *    curl -s "https://tu-dominio.com/api/cron_notificaciones_calificar.php?token=KortzenCron2026_9b8a7c6e5f4d" >/dev/null 2>&1
 * 2. Prueba manual desde navegador con sesión de admin:
 *    https://tu-dominio.com/api/cron_notificaciones_calificar.php?test=1
 * 3. Prueba manual forzando cita específica:
 *    https://tu-dominio.com/api/cron_notificaciones_calificar.php?test=1&id=123
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/webpush_helper.php';

header('Content-Type: application/json');

// Permitir si es CLI, si tiene el token secreto CRON_SECRET, o si es un administrador logueado
$isAuthorized = (php_sapi_name() === 'cli') || 
    (($_GET['token'] ?? $_GET['cron_key'] ?? '') === CRON_SECRET) ||
    (isLoggedIn() && in_array($_SESSION['user_rol'] ?? '', ['admin', 'administrador', 'superadmin', 'admin_local']));

if (!$isAuthorized) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Acceso denegado. Token de cron inválido o sesión de administrador requerida.']);
    exit;
}

$isTest = isset($_GET['test']) || isset($_GET['forzar']) || isset($_GET['id']);
$testCitaId = intval($_GET['id'] ?? 0);
$limite = intval($_GET['limite'] ?? 30);

try {
    $pdo = getConnection();
    try {
        $pdo->exec("SET time_zone = '-05:00'");
    } catch (Exception $eTz) {}

    $resultado = procesarNotificacionesCalificacion30min($pdo, $limite, $testCitaId ?: null);

    echo json_encode([
        'success' => true,
        'modo' => $isTest ? 'PRUEBA MANUAL (?test=1)' : 'AUTOMÁTICO (CRON)',
        'resultado' => $resultado,
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
