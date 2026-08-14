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

    // 3.5. Procesar Código de Referido y Descuentos
    $codigoReferido = strtoupper(trim($_POST['codigo_referido'] ?? ''));
    $montoDescuento = 0.00;
    $referenteId = null;
    $puntosPorReferido = 200;

    if (!empty($codigoReferido)) {
        // Cargar configuraciones
        $stmtCfg = $pdo->query("SELECT clave, valor FROM configuracion");
        $cfgs = $stmtCfg->fetchAll(PDO::FETCH_KEY_PAIR);
        $montoDescuento = floatval($cfgs['descuento_referido_amigo'] ?? 2.00);
        $puntosPorReferido = intval($cfgs['puntos_por_referido'] ?? 200);

        // Buscar referente
        $stmtRefCheck = $pdo->prepare("SELECT id FROM clientes WHERE codigo_referido = ?");
        $stmtRefCheck->execute([$codigoReferido]);
        $refRow = $stmtRefCheck->fetch(PDO::FETCH_ASSOC);

        if ($refRow && $refRow['id'] != $clienteId) {
            $referenteId = $refRow['id'];
        } else {
            $montoDescuento = 0.00;
        }
    }

    $precioFinal = max(0.00, floatval($precio) - $montoDescuento);

    $reagendarId = isset($_POST['reagendar_id']) ? intval($_POST['reagendar_id']) : 0;

    if ($reagendarId > 0) {
        // Validar que la cita pertenece al cliente
        $stmtCheckOwner = $pdo->prepare("SELECT id FROM citas WHERE id = ? AND cliente_id = ?");
        $stmtCheckOwner->execute([$reagendarId, $clienteId]);
        if ($stmtCheckOwner->fetchColumn() === false) {
            throw new Exception('Cita original no encontrada.');
        }

        // Actualizar la cita existente
        $sql = "UPDATE citas SET servicio_id = ?, barbero_id = ?, sucursal_id = ?, fecha_hora = ?, estado = 'pendiente', precio_final = ? WHERE id = ? AND cliente_id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$servicioId, $barberoId, $sucursalId, $fechaHora, $precioFinal, $reagendarId, $clienteId]);
        $citaId = $reagendarId;
    } else {
        // 4. Insertar la cita
        $sql = "INSERT INTO citas (cliente_id, servicio_id, barbero_id, sucursal_id, fecha_hora, estado, precio_final) 
                VALUES (?, ?, ?, ?, ?, 'pendiente', ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$clienteId, $servicioId, $barberoId, $sucursalId, $fechaHora, $precioFinal]);
        $citaId = $pdo->lastInsertId();
    }

    // Registrar seguimiento de referido si aplica
    if ($referenteId && $citaId > 0) {
        try {
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS referidos (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    referente_id INT NOT NULL,
                    referido_id INT NULL,
                    codigo_usado VARCHAR(30) NOT NULL,
                    cita_id INT NULL,
                    descuento_aplicado DECIMAL(10,2) DEFAULT 0.00,
                    puntos_otorgados INT DEFAULT 0,
                    estado ENUM('pendiente', 'completado', 'cancelado') DEFAULT 'pendiente',
                    fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP,
                    KEY idx_referente (referente_id),
                    KEY idx_referido (referido_id),
                    KEY idx_codigo (codigo_usado)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
            ");
            $stmtInsertRef = $pdo->prepare("INSERT INTO referidos (referente_id, referido_id, codigo_usado, cita_id, descuento_aplicado, puntos_otorgados, estado) VALUES (?, ?, ?, ?, ?, ?, 'pendiente')");
            $stmtInsertRef->execute([$referenteId, $clienteId, $codigoReferido, $citaId, $montoDescuento, $puntosPorReferido]);
        } catch (Exception $exRef) {}
    }

    // 5. Sincronización Automática con Google Calendar (Opción A)
    if (!empty($_SESSION['google_access_token'])) {
        require_once '../includes/google_calendar_helper.php';
        try {
            agendarEnGoogleCalendar($_SESSION['google_access_token'], [
                'servicio' => $nombreServicio,
                'barbero' => $nombreBarbero,
                'fecha_hora' => $fechaHora,
                'duracion_minutos' => 35
            ]);
        } catch (Exception $eg) {
            // Silencioso para no romper el flujo principal
        }
    }

    // 6. Enviar Correo
    require_once '../includes/email_helper.php';
    $fechaLegible = date('d/m/Y', strtotime($fecha));

    // Obtener datos del cliente actualizados de la BD
    $stmtCInfo = $pdo->prepare("SELECT nombre, email FROM clientes WHERE id = ?");
    $stmtCInfo->execute([$clienteId]);
    $cInfo = $stmtCInfo->fetch(PDO::FETCH_ASSOC);

    $finalEmail = !empty($cInfo['email']) ? $cInfo['email'] : ($_SESSION['cliente_email'] ?? '');
    $finalNombre = !empty($cInfo['nombre']) ? $cInfo['nombre'] : ($_SESSION['cliente_nombre'] ?? 'Cliente');

    if (!empty($finalEmail)) {
        enviarCorreoReserva($finalEmail, $finalNombre, [
            'servicio' => $nombreServicio,
            'barbero' => $nombreBarbero,
            'fecha' => $fechaLegible,
            'hora' => $hora,
            'precio' => number_format($precioFinal, 2)
        ]);
    }

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
