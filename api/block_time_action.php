<?php
require_once '../config.php';
requireLogin();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$currentUser = getCurrentUser();
$barbero_id = $currentUser['id'];
$sucursal_id = $currentUser['sucursal_id'];
$action = $_POST['action'] ?? '';

try {
    // 1. Verificar/Crear Cliente "SISTEMA BLOQUEO"
    $stmt = $pdo->prepare("SELECT id FROM clientes WHERE email = 'sistema_bloqueo@kortzen.local' LIMIT 1");
    $stmt->execute();
    $cliente = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$cliente) {
        $stmt = $pdo->prepare("INSERT INTO clientes (nombre, email, telefono, notas, foto_perfil) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute(['TIEMPO BLOQUEADO', 'sistema_bloqueo@kortzen.local', '0000000000', 'Cliente interno para bloqueos de agenda', 'assets/system_lock.png']);
        $cliente_id = $pdo->lastInsertId();
    } else {
        $cliente_id = $cliente['id'];
    }

    // 2. Verificar/Crear Servicio "BLOQUEO DE TIEMPO" (60 min)
    $stmt = $pdo->prepare("SELECT id FROM servicios WHERE nombre = 'Bloqueo Administrativo' LIMIT 1");
    $stmt->execute();
    $servicio = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$servicio) {
        $stmt = $pdo->prepare("INSERT INTO servicios (nombre, descripcion, precio, duracion_minutos) VALUES (?, ?, ?, ?)");
        $stmt->execute(['Bloqueo Administrativo', 'Espacio reservado por el barbero/admin', 0.00, 60]);
        $servicio_id = $pdo->lastInsertId();
    } else {
        $servicio_id = $servicio['id'];
    }

    // 3. Procesar Acción
    if ($action === 'quick_block_next_hour') {
        // Bloquear la próxima hora disponible (o desde YA)
        // Redondear al siguiente intervalo de 15 min
        $now = time();
        $minutes = date('i', $now);
        $remainder = $minutes % 15;
        $diff = 15 - $remainder;
        $startTime = $now + ($diff * 60); // Próximos 15 min

        $fecha_hora = date('Y-m-d H:i:s', $startTime);
        $notas = "Bloqueo Rápido (1 Hora)";

        // Insertar Cita de Bloqueo
        $stmt = $pdo->prepare("INSERT INTO citas (cliente_id, servicio_id, barbero_id, sucursal_id, fecha_hora, estado, notas, precio_final) 
                               VALUES (?, ?, ?, ?, ?, 'confirmada', ?, 0.00)");
        $stmt->execute([$cliente_id, $servicio_id, $barbero_id, $sucursal_id, $fecha_hora, $notas]);

        echo json_encode(['success' => true, 'message' => 'Hora bloqueada exitosamente desde ' . date('H:i', $startTime)]);

    } elseif ($action === 'block_custom_range') {
        // Bloqueo manual (fecha y hora específica)
        $fecha = $_POST['fecha'];
        $hora = $_POST['hora']; // HH:mm
        $duracion = $_POST['duracion'] ?? 60; // minutos

        // Buscar servicio con duración aproximada o usar el de 60 si es default?
        // Como no podemos cambiar duración de servicio dinámicamente POR CITA en este schema (la duración está en servicios),
        // usaremos el de 60 min por defecto. Si el usuario quiere más, insertamos 2 citas? 
        // Para simplificar: Solo bloqueamos 1 hora por defecto.
        // O mejor: Actualizamos el servicio_id si encontramos uno de esa duración.

        $fecha_hora = "$fecha $hora:00";
        $notas = "Bloqueo Custom ($duracion min)";

        // Insertar
        $stmt = $pdo->prepare("INSERT INTO citas (cliente_id, servicio_id, barbero_id, sucursal_id, fecha_hora, estado, notas, precio_final) 
                               VALUES (?, ?, ?, ?, ?, 'confirmada', ?, 0.00)");
        $stmt->execute([$cliente_id, $servicio_id, $barbero_id, $sucursal_id, $fecha_hora, $notas]);

        echo json_encode(['success' => true, 'message' => 'Bloqueo registrado']);
    } else {
        throw new Exception("Acción no reconocida");
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
