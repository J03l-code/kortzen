<?php
session_start();
require_once '../config.php';

// Verificar login
if (!isClienteLoggedIn()) {
    header('Location: ../cliente-login.php');
    exit;
}

// Verificar método POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../mis-citas.php');
    exit;
}

$citaId = intval($_POST['cita_id'] ?? 0);
$cliente = getCurrentCliente();

if (!$citaId) {
    header('Location: ../mis-citas.php?error=' . urlencode('ID de cita inválido'));
    exit;
}

try {
    $pdo = getConnection();

    // 1. Verificar que la cita pertenece al cliente
    $stmt = $pdo->prepare("SELECT id, fecha_hora FROM citas WHERE id = ? AND cliente_id = ?");
    $stmt->execute([$citaId, $cliente['id']]);
    $cita = $stmt->fetch();

    if (!$cita) {
        throw new Exception('Cita no encontrada o no tienes permiso.');
    }

    // 2. Verificar si ya pasó la fecha (opcional, pero recomendado)
    if (strtotime($cita['fecha_hora']) < time()) {
        throw new Exception('No puedes cancelar una cita pasada.');
    }

    // 3. Cancelar
    $stmtUpdate = $pdo->prepare("UPDATE citas SET estado = 'cancelada' WHERE id = ?");
    $stmtUpdate->execute([$citaId]);

    // 4. Log para admins (opcional, si existe función registrarLog)
    if (function_exists('registrarLog')) {
        // Asumimos que registrarLog maneja usuario_id=0 para clientes o un sistema independiente
        // Por ahora lo omitimos para no complicar dependencias de auth de admin
    }

    header('Location: ../mis-citas.php?success=' . urlencode('Cita cancelada correctamente.'));

} catch (Exception $e) {
    header('Location: ../mis-citas.php?error=' . urlencode($e->getMessage()));
}
