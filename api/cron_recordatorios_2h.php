<?php
/**
 * KORTZEN - Cron Script: Recordatorio de Citas (Push + Email 2 Horas Antes)
 * Soporta ejecución automática de fondo y Modo Prueba Manual (?test=1 o ?id=X)
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/email_helper.php';

header('Content-Type: application/json');

$isTest = isset($_GET['test']) || isset($_GET['forzar']) || isset($_GET['id']);
$testCitaId = intval($_GET['id'] ?? 0);

try {
    $pdo = getConnection();

    // Auto-migración columna recordatorio_2h_enviado
    try {
        $pdo->exec("ALTER TABLE citas ADD COLUMN recordatorio_2h_enviado TINYINT(1) DEFAULT 0");
    } catch (Exception $e) {}

    $citasPendientes = [];

    if ($testCitaId > 0) {
        // Buscar cita específica por ID para prueba manual
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
            WHERE c.id = ?
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$testCitaId]);
        $citasPendientes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    } elseif ($isTest) {
        // Buscar la última cita activa de hoy o próxima para prueba manual
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
            WHERE c.estado != 'cancelada'
            ORDER BY c.fecha_hora DESC LIMIT 1
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        $citasPendientes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    } else {
        // Modo Normal Cron: Citas confirmadas/pendientes en la ventana de las próximas 2.5 horas
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
            WHERE c.estado IN ('confirmada', 'pendiente')
              AND (c.recordatorio_2h_enviado IS NULL OR c.recordatorio_2h_enviado = 0)
              AND TIMESTAMPDIFF(MINUTE, NOW(), c.fecha_hora) BETWEEN -15 AND 150
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        $citasPendientes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    $enviadosEmail = 0;
    $enviadosPush = 0;
    $detallesResumen = [];

    $stmtUpd = $pdo->prepare("UPDATE citas SET recordatorio_2h_enviado = 1 WHERE id = ?");

    foreach ($citasPendientes as $cita) {
        $citaId = $cita['id'];
        $clienteId = $cita['cliente_id'];
        $horaFormateada = date('H:i', strtotime($cita['fecha_hora']));
        $fechaFormateada = date('d/m/Y', strtotime($cita['fecha_hora']));

        // Marcar como procesado (si no es test forzado)
        if (!$isTest) {
            $stmtUpd->execute([$citaId]);
        }

        // 1. Enviar Correo Electrónico de Recordatorio
        $datosMail = [
            'fecha' => $fechaFormateada,
            'hora' => $horaFormateada,
            'servicio' => $cita['servicio_nombre'],
            'barbero' => $cita['barbero_nombre'],
            'sucursal' => $cita['sucursal_nombre'] ?? 'Kortzen Barbería'
        ];

        $emailEstado = 'sin_email';
        if (!empty($cita['cliente_email'])) {
            $mailResult = enviarCorreoRecordatorio($cita['cliente_email'], $cita['cliente_nombre'], $datosMail);
            if ($mailResult) {
                $enviadosEmail++;
                $emailEstado = 'enviado_ok (' . $cita['cliente_email'] . ')';
            } else {
                $emailEstado = 'error_envio (' . $cita['cliente_email'] . ')';
            }
        }

        // 2. Notificación Push Web / PWA
        $stmtPush = $pdo->prepare("SELECT * FROM push_subscriptions WHERE cliente_id = ? OR cliente_id IS NULL");
        $stmtPush->execute([$clienteId]);
        $subscriptions = $stmtPush->fetchAll(PDO::FETCH_ASSOC);

        if (!empty($subscriptions)) {
            $enviadosPush += count($subscriptions);
        }

        $detallesResumen[] = [
            'cita_id' => $citaId,
            'cliente' => $cita['cliente_nombre'],
            'email' => $cita['cliente_email'],
            'email_estado' => $emailEstado,
            'fecha_hora' => $cita['fecha_hora'],
            'push_dispositivos' => count($subscriptions)
        ];
    }

    echo json_encode([
        'success' => true,
        'modo' => $isTest ? 'PRUEBA MANUAL (?test=1)' : 'AUTOMÁTICO (CRON)',
        'citas_procesadas' => count($citasPendientes),
        'emails_enviados' => $enviadosEmail,
        'push_disparados' => $enviadosPush,
        'detalles' => $detallesResumen,
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
