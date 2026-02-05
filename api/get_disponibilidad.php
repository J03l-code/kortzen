<?php
require_once '../config.php';

header('Content-Type: application/json');

// Validar parámetros
$fecha = $_GET['fecha'] ?? null;
$barberoId = isset($_GET['barbero_id']) ? intval($_GET['barbero_id']) : 0;
$servicioId = isset($_GET['servicio_id']) ? intval($_GET['servicio_id']) : 0;

if (!$fecha || !$barberoId || !$servicioId) {
    echo json_encode(['error' => 'Faltan parámetros (fecha, barbero, servicio)']);
    exit;
}

try {
    $pdo = getConnection();

    // 1. Obtener duración del servicio
    $stmtServicio = $pdo->prepare("SELECT duracion_minutos FROM servicios WHERE id = ?");
    $stmtServicio->execute([$servicioId]);
    $servicio = $stmtServicio->fetch();
    $duracion = $servicio ? intval($servicio['duracion_minutos']) : 30;

    // 2. Verificar día de la semana y si trabaja
    $timestamp = strtotime($fecha);
    $diaSemana = date('w', $timestamp); // 0 (Domingo) a 6 (Sábado)

    // Obtener horario base del barbero para ese día
    $stmtHorario = $pdo->prepare("SELECT * FROM horarios_barberos WHERE barbero_id = ? AND dia_semana = ? AND activo = 1");
    $stmtHorario->execute([$barberoId, $diaSemana]);
    $horarioBase = $stmtHorario->fetch();

    if (!$horarioBase) {
        echo json_encode([]); // No trabaja ese día
        exit;
    }

    // 3. Verificar días bloqueados (vacaciones, libres)
    $stmtBloqueo = $pdo->prepare("SELECT * FROM dias_bloqueados WHERE barbero_id = ? AND fecha = ?");
    $stmtBloqueo->execute([$barberoId, $fecha]);
    $bloqueo = $stmtBloqueo->fetch();

    if ($bloqueo && $bloqueo['todo_el_dia']) {
        echo json_encode([]); // Día bloqueado completo
        exit;
    }

    // Definir límites del día
    $horaInicioStr = $horarioBase['hora_inicio']; // Ej: "10:00:00"
    $horaFinStr = $horarioBase['hora_fin'];       // Ej: "20:00:00"

    // Convertir a timestamps para el día específico
    // Asegurar que empiece en hora exacta (xx:00:00)
    $startOfDay = ceil(strtotime("$fecha $horaInicioStr") / 3600) * 3600;
    $endOfDay = strtotime("$fecha $horaFinStr");

    // Si es hoy, filtrar horas pasadas
    if ($fecha === date('Y-m-d')) {
        $now = time();
        $startOfDay = max($startOfDay, ceil($now / 3600) * 3600); // Próxima hora en punto
    }

    // 4. Obtener citas existentes para ese barbero y fecha
    // Buscamos citas que se solapen con el horario laboral
    $stmtCitas = $pdo->prepare("
        SELECT fecha_hora, servicio_id, 
               s.duracion_minutos 
        FROM citas c
        JOIN servicios s ON c.servicio_id = s.id
        WHERE c.barbero_id = ? 
          AND c.estado != 'cancelada'
          AND DATE(c.fecha_hora) = ?
    ");
    $stmtCitas->execute([$barberoId, $fecha]);
    $citas = $stmtCitas->fetchAll();

    // Mapear intervalos ocupados
    $ocupados = [];
    foreach ($citas as $cita) {
        $inicio = strtotime($cita['fecha_hora']);
        $duracionCita = intval($cita['duracion_minutos']);
        $fin = $inicio + ($duracionCita * 60);
        $ocupados[] = ['inicio' => $inicio, 'fin' => $fin];
    }

    // 5. Generar slots disponibles (SOLO HORAS EN PUNTO)
    $slots = [];
    $intervalo = 60 * 60; // Slots cada 60 minutos (Horas en punto)

    $current = $startOfDay;

    while (($current + ($duracion * 60)) <= $endOfDay) {
        $slotInicio = $current;
        $slotFin = $current + ($duracion * 60);

        $disponible = true;

        // Verificar colisión con citas
        foreach ($ocupados as $ocupado) {
            // Si el slot empieza antes de que termine la cita Y termina después de que empiece la cita
            if ($slotInicio < $ocupado['fin'] && $slotFin > $ocupado['inicio']) {
                $disponible = false;
                break;
            }
        }

        if ($disponible) {
            $slots[] = date('H:i', $slotInicio);
        }

        $current += $intervalo;
    }

    echo json_encode($slots);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error de base de datos']);
}
