<?php
/** Motivo de indisponibilidad para un intervalo, sin modificar la reserva. */
function motivoHorarioCita(PDO $pdo, int $barberoId, string $fechaHora, int $duracion): ?string
{
    $inicio = new DateTimeImmutable($fechaHora);
    $fin = $inicio->modify('+' . max(1, $duracion) . ' minutes');
    $stmt = $pdo->prepare('SELECT * FROM horarios_barberos WHERE barbero_id = ? AND dia_semana = ?');
    $stmt->execute([$barberoId, (int) $inicio->format('w')]);
    $horario = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($horario && !(int) $horario['activo']) return 'Día libre del barbero';
    $horaInicio = substr($horario['hora_inicio'] ?? '10:00', 0, 5);
    $horaFin = substr($horario['hora_fin'] ?? '20:00', 0, 5);
    if ($inicio->format('Y-m-d') !== $fin->format('Y-m-d') || $inicio->format('H:i') < $horaInicio || $fin->format('H:i') > $horaFin) {
        return 'La cita quedó fuera del horario del barbero';
    }
    $stmt = $pdo->prepare('SELECT id FROM dias_bloqueados WHERE barbero_id = ? AND fecha = ?');
    $stmt->execute([$barberoId, $inicio->format('Y-m-d')]);
    if ($stmt->fetchColumn()) return 'Día bloqueado para el barbero';
    $stmt = $pdo->prepare('SELECT id FROM bloqueos_horas WHERE barbero_id = ? AND fecha = ? AND hora_inicio < ? AND hora_fin > ?');
    $stmt->execute([$barberoId, $inicio->format('Y-m-d'), $fin->format('H:i:s'), $inicio->format('H:i:s')]);
    if ($stmt->fetchColumn()) return 'La cita coincide con un bloqueo de horario';
    return null;
}

function marcarCitasSinBarbero(PDO $pdo, int $barberoId, array $operador, ?string $ahora = null): int
{
    if (!$pdo->inTransaction()) throw new LogicException('La modificación de horarios requiere una transacción.');
    $ahora = $ahora ?? date('Y-m-d H:i:s');
    $stmt = $pdo->prepare("SELECT c.*, COALESCE(s.duracion_minutos,45) AS duracion
        FROM citas c LEFT JOIN servicios s ON s.id = c.servicio_id
        WHERE c.barbero_id = ? AND c.fecha_hora >= ? AND c.reasignacion_pendiente = 0
        AND c.estado IN ('pendiente','confirmada') ORDER BY c.fecha_hora, c.id FOR UPDATE");
    $stmt->execute([$barberoId, $ahora]);
    $citas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $actualizar = $pdo->prepare('UPDATE citas SET reasignacion_pendiente = 1,
        barbero_anterior_id = COALESCE(barbero_anterior_id, barbero_id), motivo_reasignacion = ? WHERE id = ?');
    $auditar = $pdo->prepare('INSERT INTO citas_reasignaciones (cita_id, barbero_anterior_id, barbero_nuevo_id, motivo, registrado_por) VALUES (?, ?, NULL, ?, ?)');
    $cantidad = 0;
    foreach ($citas as $cita) {
        $motivo = motivoHorarioCita($pdo, $barberoId, $cita['fecha_hora'], (int) $cita['duracion']);
        if ($motivo === null) continue;
        validarSucursalVenta($pdo, $operador, (int) $cita['sucursal_id']);
        $actualizar->execute([$motivo, $cita['id']]);
        $auditar->execute([$cita['id'], $barberoId, $motivo, $operador['id']]);
        $cantidad++;
    }
    return $cantidad;
}

function reasignarCita(PDO $pdo, int $citaId, int $barberoId, array $operador): void
{
    if (!esAdministradorCaja($operador)) throw new InvalidArgumentException('Solo administración puede reasignar citas.');
    $pdo->beginTransaction();
    try {
        // Serializar reasignaciones al mismo barbero y los cambios de su horario.
        $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE id = ? AND activo = 1 AND rol IN ('barbero','admin_local','admin') FOR UPDATE");
        $stmt->execute([$barberoId]);
        $barbero = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$barbero) throw new InvalidArgumentException('Selecciona un barbero activo.');
        $stmt = $pdo->prepare('SELECT c.*, COALESCE(s.duracion_minutos,45) AS duracion FROM citas c LEFT JOIN servicios s ON s.id=c.servicio_id WHERE c.id = ? FOR UPDATE');
        $stmt->execute([$citaId]);
        $cita = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$cita || !$cita['reasignacion_pendiente'] || !in_array($cita['estado'], ['pendiente','confirmada'], true)) {
            throw new InvalidArgumentException('Esta cita no está pendiente de reasignación.');
        }
        if ($cita['fecha_hora'] < date('Y-m-d H:i:s')) throw new InvalidArgumentException('No se puede reasignar una cita pasada.');
        validarSucursalVenta($pdo, $operador, (int) $cita['sucursal_id']);
        $stmt = $pdo->prepare('SELECT sucursal_id FROM usuarios_sucursales WHERE usuario_id = ?');
        $stmt->execute([$barberoId]);
        $sucursales = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
        if (!in_array((int) $cita['sucursal_id'], $sucursales, true) && (int) ($barbero['sucursal_id'] ?? 0) !== (int) $cita['sucursal_id']) {
            throw new InvalidArgumentException('El barbero no pertenece a la sucursal de la cita.');
        }
        $motivo = motivoHorarioCita($pdo, $barberoId, $cita['fecha_hora'], (int) $cita['duracion']);
        if ($motivo !== null) throw new InvalidArgumentException($motivo . '. Selecciona otro barbero.');
        $inicio = new DateTimeImmutable($cita['fecha_hora']);
        $fin = $inicio->modify('+' . (int) $cita['duracion'] . ' minutes');
        if (!empty($barbero['almuerzo_activo'])) {
            $almuerzoInicio = $inicio->format('Y-m-d') . ' ' . ($barbero['almuerzo_inicio'] ?? '13:00:00');
            $almuerzoFin = $inicio->format('Y-m-d') . ' ' . ($barbero['almuerzo_fin'] ?? '14:00:00');
            if ($cita['fecha_hora'] < $almuerzoFin && $fin->format('Y-m-d H:i:s') > $almuerzoInicio) {
                throw new InvalidArgumentException('El barbero está en su descanso de almuerzo.');
            }
        }
        $stmt = $pdo->prepare('SELECT servicio_id, barbero_id FROM servicios_barberos WHERE servicio_id = ?');
        $stmt->execute([$cita['servicio_id']]);
        $proveedores = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if ($proveedores && !in_array($barberoId, array_map('intval', array_column($proveedores, 'barbero_id')), true)) {
            throw new InvalidArgumentException('El barbero no está habilitado para este servicio.');
        }
        $stmt = $pdo->prepare("SELECT c.id, c.fecha_hora, COALESCE(s.duracion_minutos,45) AS duracion
            FROM citas c LEFT JOIN servicios s ON s.id=c.servicio_id WHERE c.barbero_id = ?
            AND c.id <> ? AND c.reasignacion_pendiente = 0 AND c.estado NOT IN ('cancelada','no_asistio')
            AND c.fecha_hora >= ? AND c.fecha_hora < ? FOR UPDATE");
        $stmt->execute([$barberoId, $citaId, $inicio->format('Y-m-d') . ' 00:00:00', $inicio->modify('+1 day')->format('Y-m-d') . ' 00:00:00']);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $otra) {
            $finOtra = (new DateTimeImmutable($otra['fecha_hora']))->modify('+' . (int) $otra['duracion'] . ' minutes');
            if ($otra['fecha_hora'] < $fin->format('Y-m-d H:i:s') && $finOtra > $inicio) {
                throw new InvalidArgumentException('El barbero ya tiene otra cita en este horario.');
            }
        }
        $stmt = $pdo->prepare('UPDATE citas SET barbero_id = ?, reasignacion_pendiente = 0, motivo_reasignacion = NULL WHERE id = ?');
        $stmt->execute([$barberoId, $citaId]);
        $stmt = $pdo->prepare('INSERT INTO citas_reasignaciones (cita_id, barbero_anterior_id, barbero_nuevo_id, motivo, registrado_por) VALUES (?, ?, ?, ?, ?)');
        $stmt->execute([$citaId, $cita['barbero_id'], $barberoId, 'Reasignada por administración', $operador['id']]);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}
