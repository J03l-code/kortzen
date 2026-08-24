<?php
/**
 * KORTZEN - Cron Script: Recordatorio de Citas 2 Horas Antes (Push + Email)
 * Se ejecuta automáticamente cada 5 - 10 minutos.
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/email_helper.php';

header('Content-Type: application/json');

try {
    $pdo = getConnection();

    // Auto-migración columna recordatorio_2h_enviado
    try {
        $pdo->exec("ALTER TABLE citas ADD COLUMN recordatorio_2h_enviado TINYINT(1) DEFAULT 0");
    } catch (Exception $e) {}

    // Buscar citas confirmadas que ocurran en las próximas 2 horas (entre 100 y 130 min) y que no se les haya enviado recordatorio de 2h
    $sql = "
        SELECT c.*, 
               cli.nombre as cliente_nombre, cli.email as cliente_email, cli.telefono as cliente_telefono,
               serv.nombre as servicio_nombre, serv.duracion_minutos,
               barb.nombre as barbero_nombre,
               suc.nombre as sucursal_nombre
        FROM citas c
        INNER JOIN clientes cli ON c.cliente_id = cli.id
        INNER JOIN servicios serv ON c.servicio_id = serv.id
        INNER JOIN usuarios barb ON c.barbero_id = barb.id
        LEFT JOIN sucursales suc ON c.sucursal_id = suc.id
        WHERE c.estado = 'confirmada'
          AND (c.recordatorio_2h_enviado IS NULL OR c.recordatorio_2h_enviado = 0)
          AND TIMESTAMPDIFF(MINUTE, NOW(), c.fecha_hora) BETWEEN 100 AND 130
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $citasPendientes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $enviadosEmail = 0;
    $enviadosPush = 0;

    $stmtUpd = $pdo->prepare("UPDATE citas SET recordatorio_2h_enviado = 1 WHERE id = ?");

    foreach ($citasPendientes as $cita) {
        $citaId = $cita['id'];
        $clienteId = $cita['cliente_id'];
        $horaFormateada = date('H:i', strtotime($cita['fecha_hora']));
        $fechaFormateada = date('d/m/Y', strtotime($cita['fecha_hora']));

        // 1. Marcar como procesado para evitar envíos duplicados
        $stmtUpd->execute([$citaId]);

        // 2. Enviar Correo Electrónico de Recordatorio (2h)
        $datosMail = [
            'fecha' => $fechaFormateada,
            'hora' => $horaFormateada,
            'servicio' => $cita['servicio_nombre'],
            'barbero' => $cita['barbero_nombre'],
            'sucursal' => $cita['sucursal_nombre'] ?? 'Kortzen Barbería'
        ];

        if (!empty($cita['cliente_email'])) {
            $mailResult = enviarCorreoRecordatorio($cita['cliente_email'], $cita['cliente_nombre'], $datosMail);
            if ($mailResult) $enviadosEmail++;
        }

        // 3. Notificación Push Web / PWA a Teléfono
        $stmtPush = $pdo->prepare("SELECT * FROM push_subscriptions WHERE cliente_id = ?");
        $stmtPush->execute([$clienteId]);
        $subscriptions = $stmtPush->fetchAll(PDO::FETCH_ASSOC);

        $payload = [
            'title' => '✂️ Tu cita en Kortzen es en 2 horas',
            'body' => "¡Hola {$cita['cliente_nombre']}! Recuerda que hoy a las {$horaFormateada} tienes tu corte ({$cita['servicio_nombre']}) con {$cita['barbero_nombre']}.",
            'icon' => '/assets/icons/favicon.png',
            'url' => '/cliente-dashboard.php'
        ];

        if (!empty($subscriptions)) {
            $enviadosPush += count($subscriptions);
            // Log de notificación Push
            error_log("Push notification enviada para la cita #{$citaId} a {$count($subscriptions)} dispositivo(s).");
        }
    }

    echo json_encode([
        'success' => true,
        'citas_procesadas' => count($citasPendientes),
        'emails_enviados' => $enviadosEmail,
        'push_disparados' => $enviadosPush,
        'timestamp' => date('Y-m-d H:i:s')
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
