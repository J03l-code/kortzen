<?php
require_once '../config.php';

if (!isLoggedIn()) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

// Verificar permisos
if (!canManageSchedules()) {
    header('Location: ../dashboard.php?error=' . urlencode('No tienes permiso para gestionar horarios.'));
    exit;
}

$action = $_POST['action'] ?? '';
$barberoId = intval($_POST['barbero_id'] ?? 0);

if ($barberoId <= 0) {
    header('Location: ../horarios.php?error=' . urlencode('Barbero no válido.'));
    exit;
}

try {
    $pdo = getConnection();

    switch ($action) {
        case 'guardar_horarios':
            // Guardar horarios semanales
            $horaInicio = $_POST['hora_inicio'] ?? [];
            $horaFin = $_POST['hora_fin'] ?? [];
            $activo = $_POST['activo'] ?? [];

            // Días de la semana (0-6)
            for ($dia = 0; $dia <= 6; $dia++) {
                $esActivo = isset($activo[$dia]) ? 1 : 0;
                $inicio = $horaInicio[$dia] ?? '10:00';
                $fin = $horaFin[$dia] ?? '20:00';

                // Insertar o actualizar (UPSERT)
                $sql = "INSERT INTO horarios_barberos (barbero_id, dia_semana, hora_inicio, hora_fin, activo) 
                        VALUES (?, ?, ?, ?, ?)
                        ON DUPLICATE KEY UPDATE 
                            hora_inicio = VALUES(hora_inicio),
                            hora_fin = VALUES(hora_fin),
                            activo = VALUES(activo)";

                $stmt = $pdo->prepare($sql);
                $stmt->execute([$barberoId, $dia, $inicio, $fin, $esActivo]);
            }

            registrarLog('UPDATE', 'horarios_barberos', $barberoId, 'Horarios actualizados');
            header('Location: ../horarios.php?barbero=' . $barberoId . '&success=' . urlencode('Horarios guardados correctamente'));
            exit;

        case 'agregar_bloqueo':
            $fecha = $_POST['fecha'] ?? '';
            $motivo = trim($_POST['motivo'] ?? 'Día de descanso');

            if (empty($fecha)) {
                throw new Exception('La fecha es obligatoria.');
            }

            // Verificar que la fecha no sea pasada
            if (strtotime($fecha) < strtotime(date('Y-m-d'))) {
                throw new Exception('No se pueden bloquear fechas pasadas.');
            }

            // Insertar bloqueo
            $sql = "INSERT INTO dias_bloqueados (barbero_id, fecha, motivo, creado_por) VALUES (?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$barberoId, $fecha, $motivo, $_SESSION['user_id']]);

            registrarLog('INSERT', 'dias_bloqueados', $pdo->lastInsertId(), "Día bloqueado: $fecha - $motivo");
            header('Location: ../horarios.php?barbero=' . $barberoId . '&success=' . urlencode('Día bloqueado agregado'));
            exit;

        case 'eliminar_bloqueo':
            $bloqueoId = intval($_POST['bloqueo_id'] ?? 0);

            if ($bloqueoId <= 0) {
                throw new Exception('Bloqueo no válido.');
            }

            $sql = "DELETE FROM dias_bloqueados WHERE id = ? AND barbero_id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$bloqueoId, $barberoId]);

            registrarLog('DELETE', 'dias_bloqueados', $bloqueoId, 'Día bloqueado eliminado');
            header('Location: ../horarios.php?barbero=' . $barberoId . '&success=' . urlencode('Día bloqueado eliminado'));
            exit;

        case 'agregar_bloqueo_hora':
            $fecha = $_POST['fecha'] ?? '';
            $horaInicio = $_POST['hora_inicio'] ?? '';
            $horaFin = $_POST['hora_fin'] ?? '';
            $motivo = trim($_POST['motivo'] ?? 'Bloqueo parcial');

            if (empty($fecha) || empty($horaInicio) || empty($horaFin)) {
                throw new Exception('Fecha y horas son obligatorias.');
            }

            if (strtotime($fecha) < strtotime(date('Y-m-d'))) {
                throw new Exception('No se pueden bloquear fechas pasadas.');
            }

            if (strtotime($horaFin) <= strtotime($horaInicio)) {
                throw new Exception('La hora de fin debe ser posterior a la de inicio.');
            }

            $sql = "INSERT INTO bloqueos_horas (barbero_id, fecha, hora_inicio, hora_fin, motivo, creado_por) VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$barberoId, $fecha, $horaInicio, $horaFin, $motivo, $_SESSION['user_id']]);

            registrarLog('INSERT', 'bloqueos_horas', $pdo->lastInsertId(), "Bloqueo hora: $fecha ($horaInicio-$horaFin) - $motivo");
            header('Location: ../horarios.php?barbero=' . $barberoId . '&success=' . urlencode('Bloqueo por horas agregado'));
            exit;

        case 'eliminar_bloqueo_hora':
            $bloqueoId = intval($_POST['bloqueo_id'] ?? 0);

            if ($bloqueoId <= 0) {
                throw new Exception('Bloqueo no válido.');
            }

            $sql = "DELETE FROM bloqueos_horas WHERE id = ? AND barbero_id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$bloqueoId, $barberoId]);

            registrarLog('DELETE', 'bloqueos_horas', $bloqueoId, 'Bloqueo por horas eliminado');
            header('Location: ../horarios.php?barbero=' . $barberoId . '&success=' . urlencode('Bloqueo por horas eliminado'));
            exit;

        default:
            throw new Exception('Acción no válida.');
    }

} catch (PDOException $e) {
    error_log("Error en horarios_action.php: " . $e->getMessage());

    // Verificar si es error de duplicado
    if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
        header('Location: ../horarios.php?barbero=' . $barberoId . '&error=' . urlencode('Esta fecha ya está bloqueada.'));
    } else {
        header('Location: ../horarios.php?barbero=' . $barberoId . '&error=' . urlencode('Error de base de datos.'));
    }
    exit;

} catch (Exception $e) {
    header('Location: ../horarios.php?barbero=' . $barberoId . '&error=' . urlencode($e->getMessage()));
    exit;
}
