<?php
require_once '../config.php';

header('Content-Type: application/json');

if (!isClienteLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Debes iniciar sesión.']);
    exit;
}

// Obtener datos
$cliente = getCurrentCliente();
$clienteId = $cliente['id'];

$servicioId = intval($_POST['servicio_id'] ?? 0);
$barberoId = intval($_POST['barbero_id'] ?? 0);
$fecha = $_POST['fecha'] ?? '';
$hora = $_POST['hora'] ?? '';
$telefono = isset($_POST['telefono']) ? trim($_POST['telefono']) : '';

if (!$servicioId || !$barberoId || empty($fecha) || empty($hora)) {
    echo json_encode(['success' => false, 'message' => 'Faltan datos de la reserva.']);
    exit;
}

if (empty($telefono)) {
    echo json_encode(['success' => false, 'message' => 'El teléfono es obligatorio.']);
    exit;
}

try {
    $pdo = getConnection();

    // 0. Actualizar teléfono del cliente
    $stmtUpdate = $pdo->prepare("UPDATE clientes SET telefono = ? WHERE id = ?");
    $stmtUpdate->execute([$telefono, $clienteId]);

    // 1. Validar disponibilidad (Doble check para concurrencia)
    $fechaHora = "$fecha $hora:00";

    // Check rápido si ya tiene cita a esa hora EXACTA
    $stmtCcheck = $pdo->prepare("SELECT COUNT(*) FROM citas WHERE barbero_id = ? AND fecha_hora = ? AND estado != 'cancelada'");
    $stmtCcheck->execute([$barberoId, $fechaHora]);
    if ($stmtCcheck->fetchColumn() > 0) {
        throw new Exception('Lo sentimos, este horario acaba de ser ocupado.');
    }

    // 2. Obtener sucursal del barbero
    $stmtBarbero = $pdo->prepare("SELECT sucursal_id, nombre FROM usuarios WHERE id = ?");
    $stmtBarbero->execute([$barberoId]);
    $barberoData = $stmtBarbero->fetch();
    $sucursalId = $barberoData['sucursal_id'] ?? 1;
    $nombreBarbero = $barberoData['nombre'];

    // 3. Obtener precio y nombre servicio
    $stmtServicio = $pdo->prepare("SELECT nombre, precio FROM servicios WHERE id = ?");
    $stmtServicio->execute([$servicioId]);
    $servicioData = $stmtServicio->fetch();
    $nombreServicio = $servicioData['nombre'];
    $precio = $servicioData['precio'];

    // 4. Insertar la cita
    $sql = "INSERT INTO citas (cliente_id, servicio_id, barbero_id, sucursal_id, fecha_hora, estado, precio_final) 
            VALUES (?, ?, ?, ?, ?, 'pendiente', ?)";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$clienteId, $servicioId, $barberoId, $sucursalId, $fechaHora, $precio]);

    // 5. Enviar Correo
    require_once '../includes/email_helper.php';
    $fechaLegible = date('d/m/Y', strtotime($fecha));

    // Intentar enviar correo pero no fallar si el mail server no responde (try catch silencioso o simplemente if)
    // mail() devuelve bool, no lanza excep.
    enviarCorreoReserva($cliente['email'], $cliente['nombre'], [
        'servicio' => $nombreServicio,
        'barbero' => $nombreBarbero,
        'fecha' => $fechaLegible,
        'hora' => $hora,
        'precio' => $precio
    ]);

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
