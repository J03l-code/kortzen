<?php
/**
 * KORTZEN - Procesador de Bloqueo Rápido de Horarios / Descansos para Barberos
 */
require_once '../config.php';
requireLogin();

if (!in_array($_SESSION['user_rol'] ?? '', ['barbero', 'admin', 'admin_local'])) {
    header('Location: ../dashboard.php');
    exit;
}

$action = $_POST['action'] ?? '';

if ($action === 'bloquear_descanso_barbero') {
    try {
        $pdo = getConnection();
        $barberoId = $_SESSION['user_id'];
        $fecha = $_POST['fecha'] ?? date('Y-m-d');
        $horaInicio = $_POST['hora_inicio'] ?? '';
        $duracion = intval($_POST['duracion_minutos'] ?? 60);
        $motivo = trim($_POST['motivo'] ?? 'Almuerzo / Descanso');

        if (empty($horaInicio)) {
            header('Location: ../barber-dashboard.php?error=' . urlencode('Debes seleccionar una hora de inicio.'));
            exit;
        }

        $tsInicio = strtotime("$fecha $horaInicio:00");
        $tsFin = $tsInicio + ($duracion * 60);
        $horaFinStr = date('H:i:s', $tsFin);
        $horaInicioStr = date('H:i:s', $tsInicio);

        // Asegurar tabla bloqueos_horas
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS bloqueos_horas (
                id INT AUTO_INCREMENT PRIMARY KEY,
                barbero_id INT NOT NULL,
                fecha DATE NOT NULL,
                hora_inicio TIME NOT NULL,
                hora_fin TIME NOT NULL,
                motivo VARCHAR(255) NULL,
                fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");

        $stmt = $pdo->prepare("INSERT INTO bloqueos_horas (barbero_id, fecha, hora_inicio, hora_fin, motivo) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$barberoId, $fecha, $horaInicioStr, $horaFinStr, $motivo]);

        header('Location: ../barber-dashboard.php?success=' . urlencode("Horario bloqueado ($horaInicioStr - $horaFinStr) por motivo: $motivo."));
        exit;
    } catch (Exception $e) {
        header('Location: ../barber-dashboard.php?error=' . urlencode($e->getMessage()));
        exit;
    }
}

header('Location: ../barber-dashboard.php');
exit;
