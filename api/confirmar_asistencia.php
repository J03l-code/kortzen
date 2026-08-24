<?php
/**
 * KORTZEN - API Confirmar Asistencia de Cita por el Cliente
 */
require_once '../config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$cita_id = intval($_POST['cita_id'] ?? $_GET['cita_id'] ?? $_GET['id'] ?? 0);
$cliente_id = isset($_SESSION['cliente_id']) ? intval($_SESSION['cliente_id']) : 0;

if ($cita_id <= 0) {
    header('Location: ../cliente-dashboard.php?error=' . urlencode('Cita no válida.'));
    exit;
}

try {
    $pdo = getConnection();

    // Auto-migración columna asistencia_confirmada
    try {
        $pdo->exec("ALTER TABLE citas ADD COLUMN asistencia_confirmada TINYINT(1) DEFAULT 0");
    } catch (Exception $e) {}

    // Verificar propiedad si el cliente está en sesión
    if ($cliente_id > 0) {
        $stmtCheck = $pdo->prepare("SELECT id FROM citas WHERE id = ? AND cliente_id = ?");
        $stmtCheck->execute([$cita_id, $cliente_id]);
        $valida = $stmtCheck->fetchColumn();

        if (!$valida) {
            header('Location: ../cliente-dashboard.php?error=' . urlencode('Cita no encontrada o no pertenece a tu cuenta.'));
            exit;
        }
    }

    // Actualizar estado de asistencia_confirmada = 1
    $stmtUpd = $pdo->prepare("UPDATE citas SET asistencia_confirmada = 1 WHERE id = ?");
    $stmtUpd->execute([$cita_id]);

    registrarLog('CONFIRMAR', 'citas', $cita_id, "Cliente confirmó su asistencia a la cita #$cita_id");

    header('Location: ../cliente-dashboard.php?success=' . urlencode('¡Excelente! Has confirmado tu asistencia a la cita. El barbero ha sido notificado.'));
    exit;

} catch (Exception $e) {
    header('Location: ../cliente-dashboard.php?error=' . urlencode('Error al confirmar asistencia: ' . $e->getMessage()));
    exit;
}
