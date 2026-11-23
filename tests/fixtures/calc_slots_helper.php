<?php
/**
 * Helper de prueba para verificar getBarberSlotsForDate
 */
function testCalcBarberSlots($pdo, $bId, $fDate, $srvDuration, $excludeId = 0) {
    $ts = strtotime($fDate);
    $diaSem = date('w', $ts);

    // 1. Horario base semanal
    $stmtHCheck = $pdo->prepare("SELECT * FROM horarios_barberos WHERE barbero_id = ? AND dia_semana = ?");
    $stmtHCheck->execute([$bId, $diaSem]);
    $hCheck = $stmtHCheck->fetch(PDO::FETCH_ASSOC);

    // Si el día está desactivado en la agenda semanal -> día libre, sin disponibilidad
    if ($hCheck && intval($hCheck['activo']) === 0) {
        return [];
    }

    // 2. Día bloqueado completo -> sin disponibilidad
    $stmtB = $pdo->prepare("SELECT * FROM dias_bloqueados WHERE barbero_id = ? AND fecha = ?");
    $stmtB->execute([$bId, $fDate]);
    $bloq = $stmtB->fetch(PDO::FETCH_ASSOC);
    if ($bloq && !empty($bloq['todo_el_dia'])) {
        return [];
    }

    // 3. Definir horario base (respetando estrictamente la jornada configurada por el administrador)
    if ($hCheck && intval($hCheck['activo']) === 1) {
        $hBase = $hCheck;
        if (empty($hBase['hora_inicio']) || $hBase['hora_inicio'] === '00:00:00') {
            $hBase['hora_inicio'] = '10:00:00';
        }
        if (empty($hBase['hora_fin']) || $hBase['hora_fin'] === '00:00:00') {
            $hBase['hora_fin'] = ($diaSem == 0) ? '17:00:00' : '20:00:00';
        }
    } else {
        $hBase = [
            'hora_inicio' => '10:00:00',
            'hora_fin' => ($diaSem == 0) ? '17:00:00' : '20:00:00',
            'activo' => 1
        ];
    }

    // Bloqueos parciales
    $stmtBH = $pdo->prepare("SELECT * FROM bloqueos_horas WHERE barbero_id = ? AND fecha = ?");
    $stmtBH->execute([$bId, $fDate]);
    $bParciales = $stmtBH->fetchAll(PDO::FETCH_ASSOC);

    $startOfDay = strtotime("$fDate " . $hBase['hora_inicio']);
    $endOfDay = strtotime("$fDate " . $hBase['hora_fin']);

    // Citas del día
    $sqlC = "SELECT c.fecha_hora, COALESCE(s.duracion_minutos, 60) as duracion_minutos 
             FROM citas c 
             LEFT JOIN servicios s ON c.servicio_id = s.id 
             WHERE c.barbero_id = ? AND c.reasignacion_pendiente = 0 AND c.estado NOT IN ('cancelada', 'no_asistio') AND DATE(c.fecha_hora) = ?";
    $paramsC = [$bId, $fDate];
    if ($excludeId > 0) {
        $sqlC .= " AND c.id != ?";
        $paramsC[] = $excludeId;
    }
    $stmtC = $pdo->prepare($sqlC);
    $stmtC->execute($paramsC);
    $citas = $stmtC->fetchAll(PDO::FETCH_ASSOC);

    $ocupados = [];
    foreach ($citas as $c) {
        $ini = strtotime($c['fecha_hora']);
        $rawDur = intval($c['duracion_minutos']) > 0 ? intval($c['duracion_minutos']) : 60;
        $fin = $ini + ($rawDur * 60);
        $ocupados[] = ['inicio' => $ini, 'fin' => $fin];
    }

    $candidatos = [];
    $startHour = intval(date('H', $startOfDay));
    $endHour = intval(date('H', $endOfDay));

    for ($h = $startHour; $h <= $endHour; $h++) {
        $slotHourTs = strtotime(sprintf('%s %02d:00:00', $fDate, $h));
        if ($slotHourTs >= $startOfDay && $slotHourTs < $endOfDay) {
            $candidatos[] = $slotHourTs;
        }
    }

    $candidatos = array_unique($candidatos);
    sort($candidatos);

    $slots = [];
    foreach ($candidatos as $cur) {
        $slotEnd = $cur + ($srvDuration * 60);
        if ($slotEnd > $endOfDay) continue;

        $colision = false;
        foreach ($ocupados as $occ) {
            if ($cur < $occ['fin'] && $slotEnd > $occ['inicio']) {
                $colision = true;
                break;
            }
        }
        if (!$colision) {
            $slots[] = date('H:i', $cur);
        }
    }

    return $slots;
}
