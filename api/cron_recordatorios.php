<?php
/**
 * KORTZEN - Script de Recordatorios de Citas para el Mismo Día
 * Se ejecuta automáticamente para enviar correos a clientes que tienen cita HOY
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/email_helper.php';

header('Content-Type: application/json');

if (php_sapi_name() !== 'cli' && ($_GET['token'] ?? $_GET['cron_key'] ?? '') !== CRON_SECRET) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Acceso denegado. Token de cron inválido.']);
    exit;
}

try {
    $pdo = getConnection();

    // 1. Asegurar columna `recordatorio_enviado` en la tabla `citas`
    try {
        $pdo->exec("ALTER TABLE citas ADD COLUMN recordatorio_enviado TINYINT(1) DEFAULT 0 AFTER estado");
    } catch (Exception $eCol) {
        // Columna ya existe
    }

    // 2. Buscar todas las citas del día de HOY que no hayan recibido recordatorio
    $sql = "
        SELECT 
            c.id AS cita_id,
            c.fecha_hora,
            cl.nombre AS cliente_nombre,
            cl.email AS cliente_email,
            s.nombre AS servicio_nombre,
            b.nombre AS barbero_nombre
        FROM citas c
        INNER JOIN clientes cl ON c.cliente_id = cl.id
        LEFT JOIN servicios s ON c.servicio_id = s.id
        LEFT JOIN usuarios b ON c.barbero_id = b.id
        WHERE DATE(c.fecha_hora) = CURDATE()
          AND c.estado IN ('pendiente', 'confirmada')
          AND (c.recordatorio_enviado IS NULL OR c.recordatorio_enviado = 0)
          AND cl.email IS NOT NULL AND cl.email != ''
    ";

    $stmt = $pdo->query($sql);
    $citasHoy = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $enviados = 0;
    $errores = 0;

    $stmtUpdate = $pdo->prepare("UPDATE citas SET recordatorio_enviado = 1 WHERE id = ?");

    foreach ($citasHoy as $cita) {
        $ts = strtotime($cita['fecha_hora']);
        $horaFormateada = date('H:i', $ts);

        $datos = [
            'servicio' => $cita['servicio_nombre'] ?? 'Corte / Servicio',
            'barbero' => $cita['barbero_nombre'] ?? 'Barbero Profesional',
            'hora' => $horaFormateada
        ];

        $exito = enviarCorreoRecordatorio($cita['cliente_email'], $cita['cliente_nombre'], $datos);

        if ($exito) {
            $stmtUpdate->execute([$cita['cita_id']]);
            $enviados++;
        } else {
            $errores++;
        }
    }

    echo json_encode([
        'success' => true,
        'mensajes_enviados' => $enviados,
        'errores' => $errores,
        'citas_procesadas' => count($citasHoy),
        'fecha' => date('Y-m-d H:i:s')
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
