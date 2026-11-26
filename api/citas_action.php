<?php
require_once '../config.php';
require_once __DIR__ . '/../includes/webpush_helper.php';

$action = $_POST['action'] ?? '';

if ($action !== 'cancelar_cita_cliente' && !isLoggedIn()) {
    header('Location: ../login.php');
    exit;
}

try {
    $pdo = getConnection();

    $isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
        || (!empty($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false)
        || (isset($_POST['ajax']) && $_POST['ajax'] == '1')
        || (isset($_GET['ajax']) && $_GET['ajax'] == '1');

    $redirect_url = '../citas.php'; // Default
    if (!empty($_POST['return_url'])) {
        $redirect_url = $_POST['return_url'];
    } elseif (!empty($_GET['return_url'])) {
        $redirect_url = $_GET['return_url'];
    } elseif (isset($_POST['redirect_source']) && $_POST['redirect_source'] === 'dashboard') {
        $redirect_url = '../dashboard.php';
    } elseif (!empty($_SERVER['HTTP_REFERER']) && (strpos($_SERVER['HTTP_REFERER'], 'citas.php') !== false || strpos($_SERVER['HTTP_REFERER'], 'dashboard.php') !== false)) {
        $redirect_url = $_SERVER['HTTP_REFERER'];
    }

    if (!function_exists('responderAccionCita')) {
        function responderAccionCita($isAjax, $redirectUrl, $msgSuccess, $extraData = []) {
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(array_merge([
                    'success' => true,
                    'message' => $msgSuccess
                ], $extraData));
                exit;
            }

            $parts = explode('#', $redirectUrl, 2);
            $urlWithoutHash = $parts[0];
            $hash = isset($parts[1]) ? '#' . $parts[1] : '';

            $qParts = explode('?', $urlWithoutHash, 2);
            $path = $qParts[0];
            $query = [];
            if (isset($qParts[1])) {
                parse_str($qParts[1], $query);
            }
            $query['success'] = $msgSuccess;
            header('Location: ' . $path . '?' . http_build_query($query) . $hash);
            exit;
        }
    }

    switch ($action) {
        case 'cancelar_cita_cliente':
            if (!isClienteLoggedIn()) {
                throw new Exception('Debes iniciar sesión.');
            }
            $clienteId = $_SESSION['cliente_id'];
            $citaId = intval($_POST['id'] ?? 0);

            if ($citaId <= 0) {
                throw new Exception('Cita inválida.');
            }

            // Verificar propiedad de la cita y tiempo límite (> 2 horas)
            $stmtCita = $pdo->prepare("SELECT id, barbero_id, fecha_hora, estado FROM citas WHERE id = ? AND cliente_id = ?");
            $stmtCita->execute([$citaId, $clienteId]);
            $cita = $stmtCita->fetch(PDO::FETCH_ASSOC);

            if (!$cita) {
                throw new Exception('La cita no fue encontrada.');
            }

            if ($cita['estado'] === 'cancelada') {
                throw new Exception('Esta cita ya se encuentra cancelada.');
            }

            $timestampCita = strtotime($cita['fecha_hora']);
            $diferenciaHoras = ($timestampCita - time()) / 3600;

            if ($diferenciaHoras < 2) {
                throw new Exception('Las citas solo se pueden cancelar con al menos 2 horas de anticipación. Por favor contacta directamente a la barbería.');
            }

            $stmtCancel = $pdo->prepare("UPDATE citas SET estado = 'cancelada' WHERE id = ?");
            $stmtCancel->execute([$citaId]);

            // Notificar al barbero en tiempo real
            try {
                notificarBarbero($pdo, $cita['barbero_id'] ?? 0, $citaId, 'cita_cancelada');
            } catch (Exception $eNotif) {}

            header('Location: ../cliente-dashboard.php?success=' . urlencode('Tu cita ha sido cancelada exitosamente.'));
            exit;

        case 'crear_manual':
            if (!in_array($_SESSION['user_rol'] ?? '', ['admin', 'admin_local', 'administrador', 'superadmin'])) {
                throw new Exception('Permisos insuficientes.');
            }

            $cliNombre = trim($_POST['cliente_nombre'] ?? '');
            $cliTel = trim($_POST['cliente_telefono'] ?? '');
            $barbero_id = intval($_POST['barbero_id'] ?? 0);
            $servicio_id = intval($_POST['servicio_id'] ?? 0);
            $fecha = trim($_POST['fecha'] ?? '');
            $hora = trim($_POST['hora'] ?? '');

            if (empty($cliNombre) || !$barbero_id || !$servicio_id || empty($fecha) || empty($hora)) {
                throw new Exception('Faltan campos obligatorios para agendar la cita.');
            }

            // Obtener sucursal del barbero
            $stmtBInfo = $pdo->prepare("SELECT sucursal_id, nombre FROM usuarios WHERE id = ?");
            $stmtBInfo->execute([$barbero_id]);
            $bRow = $stmtBInfo->fetch(PDO::FETCH_ASSOC);
            $sucursal_id = $bRow['sucursal_id'] ?? 1;

            // Obtener o registrar cliente
            $cliente_id = 0;
            if (!empty($cliTel)) {
                $stmtClFind = $pdo->prepare("SELECT id FROM clientes WHERE telefono = ? LIMIT 1");
                $stmtClFind->execute([$cliTel]);
                $cliente_id = intval($stmtClFind->fetchColumn() ?? 0);
            }

            if ($cliente_id <= 0) {
                // Crear nuevo cliente rápido
                $fakeEmail = 'cli_' . time() . '_' . rand(100, 999) . '@kortzen.com';
                $stmtNewCl = $pdo->prepare("INSERT INTO clientes (nombre, telefono, email, activo) VALUES (?, ?, ?, 1)");
                $stmtNewCl->execute([$cliNombre, $cliTel, $fakeEmail]);
                $cliente_id = intval($pdo->lastInsertId());
            }

            // Obtener duración del servicio
            $stmtServ = $pdo->prepare("SELECT nombre, duracion_minutos, precio FROM servicios WHERE id = ?");
            $stmtServ->execute([$servicio_id]);
            $servRow = $stmtServ->fetch(PDO::FETCH_ASSOC);
            $duracionMin = intval($servRow['duracion_minutos'] ?? 45);
            $precioServicio = floatval($servRow['precio'] ?? 0.00);

            $fecha_hora = $fecha . ' ' . $hora . ':00';
            $horaFinStr = date('H:i:s', strtotime($fecha_hora) + ($duracionMin * 60));

            $stmtInsert = $pdo->prepare("
                INSERT INTO citas (cliente_id, cliente_nombre, cliente_telefono, servicio_id, barbero_id, sucursal_id, fecha_hora, hora_fin, estado, precio_final, notas) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'confirmada', ?, 'Cita manual agendada por administración')
            ");
            $stmtInsert->execute([$cliente_id, $cliNombre, $cliTel, $servicio_id, $barbero_id, $sucursal_id, $fecha_hora, $horaFinStr, $precioServicio]);
            $newCitaId = intval($pdo->lastInsertId());

            registrarLog('CREAR', 'citas', $newCitaId, "Cita manual rápida #$newCitaId creada para '$cliNombre' con barbero #$barbero_id");

            try {
                notificarBarbero($pdo, $barbero_id, $newCitaId, 'nueva_reserva');
            } catch (Exception $eN) {}

            responderAccionCita($isAjax, $redirect_url, 'Cita agendada exitosamente.', ['cita_id' => $newCitaId]);

        case 'create':
            $cliente_id = intval($_POST['cliente_id'] ?? 0);
            $servicio_id = intval($_POST['servicio_id'] ?? 0);
            $barbero_id = intval($_POST['barbero_id'] ?? 0);
            $sucursal_id = intval($_POST['sucursal_id'] ?? 0);
            $fecha = $_POST['fecha'] ?? '';
            $hora = $_POST['hora'] ?? '';
            $estado = $_POST['estado'] ?? 'pendiente';
            $notas = trim($_POST['notas'] ?? '');

            if (!$cliente_id || !$servicio_id || !$barbero_id || !$sucursal_id || !$fecha || !$hora) {
                throw new Exception('Todos los campos son obligatorios.');
            }

            // Validar si el servicio es exclusivo de un barbero
            $sData = $pdo->query("SELECT nombre, barbero_id FROM servicios WHERE id = $servicio_id")->fetch(PDO::FETCH_ASSOC);
            if ($sData && !empty($sData['barbero_id']) && intval($sData['barbero_id']) !== $barbero_id) {
                $bName = $pdo->query("SELECT nombre FROM usuarios WHERE id = " . intval($sData['barbero_id']))->fetchColumn();
                throw new Exception("El servicio '{$sData['nombre']}' es exclusivo de " . ($bName ?: 'otro barbero') . ".");
            }

            $fecha_hora = $fecha . ' ' . $hora . ':00';

            // Validar si el barbero ya tiene citas activas en esta fecha
            $diaSemCita = date('w', strtotime($fecha));
            $stmtCitasCount = $pdo->prepare("SELECT COUNT(*) FROM citas WHERE barbero_id = ? AND reasignacion_pendiente = 0 AND DATE(fecha_hora) = ? AND estado NOT IN ('cancelada', 'no_asistio')");
            $stmtCitasCount->execute([$barbero_id, $fecha]);
            $hasCitasOnDate = intval($stmtCitasCount->fetchColumn() ?? 0) > 0;

            // Validar que el barbero no tenga el día desactivado en sus horarios (a menos que tenga citas o sea walk-in)
            $isWalkin = !empty($_POST['is_walkin']) || !empty($_POST['walkin']) || !empty($_POST['allow_past']);
            $stmtHorDia = $pdo->prepare("SELECT activo FROM horarios_barberos WHERE barbero_id = ? AND dia_semana = ?");
            $stmtHorDia->execute([$barbero_id, $diaSemCita]);
            $horDia = $stmtHorDia->fetch(PDO::FETCH_ASSOC);
            if ($horDia && intval($horDia['activo']) === 0 && !$hasCitasOnDate && !$isWalkin) {
                throw new Exception('El barbero no trabaja este día. Selecciona otro día u otro barbero.');
            }

            // Validar que el día no esté bloqueado completo
            $stmtBloqDia = $pdo->prepare("SELECT id FROM dias_bloqueados WHERE barbero_id = ? AND fecha = ? AND todo_el_dia = 1");
            $stmtBloqDia->execute([$barbero_id, $fecha]);
            if ($stmtBloqDia->fetch() && !$hasCitasOnDate && !$isWalkin) {
                throw new Exception('El barbero tiene este día bloqueado. Selecciona otro día u otro barbero.');
            }

            // Obtener precio y duración del servicio
            $stmtSrv = $pdo->prepare("SELECT nombre, precio, COALESCE(duracion_minutos, 45) as duracion_minutos FROM servicios WHERE id = ?");
            $stmtSrv->execute([$servicio_id]);
            $srvData = $stmtSrv->fetch(PDO::FETCH_ASSOC);
            $precioServicio = floatval($srvData['precio'] ?? 0.00);

            try {
                $sql = "INSERT INTO citas (cliente_id, servicio_id, barbero_id, sucursal_id, fecha_hora, estado, precio_final, notas) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$cliente_id, $servicio_id, $barbero_id, $sucursal_id, $fecha_hora, $estado, $precioServicio, $notas]);
            } catch (PDOException $eIns) {
                $sql = "INSERT INTO citas (cliente_id, servicio_id, barbero_id, sucursal_id, fecha_hora, estado, notas) 
                        VALUES (?, ?, ?, ?, ?, ?, ?)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$cliente_id, $servicio_id, $barbero_id, $sucursal_id, $fecha_hora, $estado, $notas]);
            }

            $newCitaId = $pdo->lastInsertId();

            $stmtCName = $pdo->prepare("SELECT nombre FROM clientes WHERE id = ?");
            $stmtCName->execute([$cliente_id]);
            $clienteNombre = $stmtCName->fetchColumn() ?: "Cliente #$cliente_id";

            registrarLog('CREAR', 'citas', $newCitaId, "Cita agendada para el cliente '$clienteNombre' ($fecha_hora)");

            // Notificar al Barbero Asignado (PWA + WebPush)
            try {
                notificarBarbero($pdo, $barbero_id, $newCitaId, 'nueva_reserva');
            } catch (Exception $eNotif) {}

            // Enviar correo de confirmación al cliente si tiene correo registrado
            try {
                $stmtClient = $pdo->prepare("SELECT nombre, email FROM clientes WHERE id = ?");
                $stmtClient->execute([$cliente_id]);
                $cData = $stmtClient->fetch(PDO::FETCH_ASSOC);

                if ($cData && !empty($cData['email'])) {
                    require_once __DIR__ . '/../includes/email_helper.php';
                    $stmtSrv = $pdo->prepare("SELECT nombre, precio FROM servicios WHERE id = ?");
                    $stmtSrv->execute([$servicio_id]);
                    $srvData = $stmtSrv->fetch(PDO::FETCH_ASSOC);

                    $stmtBarb = $pdo->prepare("SELECT nombre FROM usuarios WHERE id = ?");
                    $stmtBarb->execute([$barbero_id]);
                    $barbName = $stmtBarb->fetchColumn() ?: 'Barbero Profesional';

                    enviarCorreoReserva($cData['email'], $cData['nombre'], [
                        'servicio' => $srvData['nombre'] ?? 'Servicio de Barbería',
                        'barbero' => $barbName,
                        'fecha' => date('d/m/Y', strtotime($fecha)),
                        'hora' => $hora,
                        'precio' => number_format(floatval($srvData['precio'] ?? 0), 2)
                    ]);
                }
            } catch (Throwable $eMail) {}

            $irACobro = !empty($_POST['ir_a_cobro']) && $_POST['ir_a_cobro'] == '1';
            if ($irACobro) {
                $redirect_url = '../citas.php?abrir_cobro_id=' . $newCitaId;
            }

            responderAccionCita($isAjax, $redirect_url, 'Cita creada exitosamente' . ($irACobro ? '. Procede con el cobro y productos.' : ''), [
                'cita_id' => $newCitaId,
                'ir_a_cobro' => $irACobro
            ]);

        case 'get_cita':
            $id = intval($_GET['id'] ?? ($_POST['id'] ?? 0));
            if ($id <= 0) throw new Exception('ID inválido');
            $stmtC = $pdo->prepare("
                SELECT c.*, cl.nombre as cliente_nombre, cl.telefono as cliente_telefono,
                       s.nombre as servicio_nombre, COALESCE(s.precio, 0.00) as servicio_precio,
                       u.nombre as barbero_nombre, COALESCE(u.comision_porcentaje, 50.0) as barbero_comision,
                       COALESCE(u.comision_fin_semana, 50.0) as barbero_comision_finde,
                       su.nombre as sucursal_nombre
                FROM citas c
                LEFT JOIN clientes cl ON c.cliente_id = cl.id
                LEFT JOIN servicios s ON c.servicio_id = s.id
                LEFT JOIN usuarios u ON c.barbero_id = u.id
                LEFT JOIN sucursales su ON c.sucursal_id = su.id
                WHERE c.id = ?
            ");
            $stmtC->execute([$id]);
            $citaRow = $stmtC->fetch(PDO::FETCH_ASSOC);
            if (!$citaRow) throw new Exception('Cita no encontrada');
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'cita' => $citaRow]);
            exit;

        case 'update':
            $id = intval($_POST['id'] ?? 0);
            $cliente_id = intval($_POST['cliente_id'] ?? 0);
            $servicio_id = intval($_POST['servicio_id'] ?? 0);
            $barbero_id = intval($_POST['barbero_id'] ?? 0);
            $sucursal_id = intval($_POST['sucursal_id'] ?? 0);
            $fecha = $_POST['fecha'] ?? '';
            $hora = $_POST['hora'] ?? '';
            $estado = $_POST['estado'] ?? 'pendiente';
            $notas = trim($_POST['notas'] ?? '');
            $propina = floatval($_POST['propina'] ?? 0.00);

            if ($id <= 0 || !$cliente_id || !$servicio_id || !$barbero_id || !$sucursal_id || !$fecha || !$hora) {
                throw new Exception('Todos los campos son obligatorios para actualizar la cita.');
            }

            $estadoPrevio = $pdo->prepare('SELECT estado, reasignacion_pendiente FROM citas WHERE id = ?');
            $estadoPrevio->execute([$id]);
            $citaPrevia = $estadoPrevio->fetch(PDO::FETCH_ASSOC);
            if ($estado === 'completada' && ($citaPrevia['estado'] ?? '') !== 'completada') {
                throw new Exception('Finaliza la cita desde el modal de cobro para registrar su método de pago.');
            }
            if (!empty($citaPrevia['reasignacion_pendiente'])) {
                throw new Exception('Esta cita está sin barbero asignado. Usa Reasignar en Citas antes de editarla.');
            }

            // Duración del servicio
            $stmtServ = $pdo->prepare("SELECT nombre, duracion_minutos, precio FROM servicios WHERE id = ?");
            $stmtServ->execute([$servicio_id]);
            $servRow = $stmtServ->fetch(PDO::FETCH_ASSOC);
            $duracionMin = intval($servRow['duracion_minutos'] ?? 45);
            $precioServicio = floatval($servRow['precio'] ?? 0.00);

            $fecha_hora = $fecha . ' ' . $hora . ':00';
            $horaFinStr = date('H:i:s', strtotime($fecha_hora) + ($duracionMin * 60));

            $stmtUpdate = $pdo->prepare("
                UPDATE citas 
                SET cliente_id = ?, servicio_id = ?, barbero_id = ?, sucursal_id = ?, fecha_hora = ?, hora_fin = ?, estado = ?, precio_final = ?, propina = ?, notas = ? 
                WHERE id = ?
            ");
            $stmtUpdate->execute([$cliente_id, $servicio_id, $barbero_id, $sucursal_id, $fecha_hora, $horaFinStr, $estado, $precioServicio, $propina, $notas, $id]);

            registrarLog('EDITAR', 'citas', $id, "Cita #$id actualizada para cliente #$cliente_id ($fecha_hora)");

            responderAccionCita($isAjax, $redirect_url, 'Cita actualizada exitosamente.', ['cita_id' => $id]);

        case 'cancelar_barbero':
            // Permitir a barbero, admin y admin_local
            if (!in_array($_SESSION['user_rol'], ['barbero', 'admin', 'admin_local'])) {
                throw new Exception('No permitido. Rol actual: ' . ($_SESSION['user_rol'] ?? 'ninguno'));
            }

            $id = intval($_POST['id'] ?? 0);
            $barberoId = $_SESSION['user_id'];

            // Si es un barbero normal, verificar que es SU cita
            if ($_SESSION['user_rol'] === 'barbero') {
                $stmtCheck = $pdo->prepare("SELECT id FROM citas WHERE id = ? AND barbero_id = ?");
                $stmtCheck->execute([$id, $barberoId]);
                if (!$stmtCheck->fetch()) {
                    throw new Exception('Esta cita no te pertenece.');
                }
            }
            // Si es admin, no verificamos ownership, confiamos en su poder.

            $stmtCInfo = $pdo->prepare("SELECT cita.cliente_id, c.nombre FROM citas cita JOIN clientes c ON cita.cliente_id = c.id WHERE cita.id = ?");
            $stmtCInfo->execute([$id]);
            $citaCli = $stmtCInfo->fetch(PDO::FETCH_ASSOC);
            $clienteNombre = $citaCli ? $citaCli['nombre'] : "Cita #$id";
            $clienteIdNotif = $citaCli ? $citaCli['cliente_id'] : 0;

            $sql = "UPDATE citas SET estado = 'cancelada' WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$id]);

            // Notificar al cliente si existe
            if ($clienteIdNotif > 0) {
                try {
                    notificarCliente($pdo, $clienteIdNotif, $id, 'Cita Cancelada', 'Tu cita de barbería ha sido cancelada por la administración o barbero.', '/cliente-dashboard.php');
                } catch (Exception $eNotif) {}
            }

            registrarLog('CANCELAR', 'citas', $id, "Cita #$id cancelada para el cliente '$clienteNombre'");
            responderAccionCita($isAjax, $redirect_url, 'Cita cancelada correctamente.', ['cita_id' => $id]);

        case 'completar':
            if (!in_array($_SESSION['user_rol'], ['admin', 'admin_local'])) {
                throw new Exception('Acceso denegado. Solo la administración puede finalizar citas y registrar propinas.');
            }

            if (!is_string($_POST['csrf_token'] ?? null) || !verifyCSRFToken($_POST['csrf_token'])) {
                throw new Exception('La sesión del formulario expiró. Actualiza la página.');
            }

            $metodoPago = validarMetodoPago($_POST['metodo_pago'] ?? null);
            $id = intval($_POST['id'] ?? 0);
            $propina = floatval($_POST['propina'] ?? 0.00);
            if ($propina < 0) $propina = 0.00;

            // Array de materiales y cantidades
            $materiales = $_POST['materiales'] ?? [];
            $cantidades = $_POST['cantidades'] ?? [];

            if ($id <= 0) {
                throw new Exception('ID inválido');
            }

            // Asegurar que las columnas y tablas necesarias existan
            try {
                $pdo->exec("ALTER TABLE citas ADD COLUMN propina DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER precio_final");
            } catch (Exception $exP) {}

            try {
                $pdo->exec("ALTER TABLE citas ADD COLUMN servicios_adicionales_txt VARCHAR(255) NULL AFTER precio_final");
            } catch (Exception $exSat) {}

            try {
                $pdo->exec("
                    CREATE TABLE IF NOT EXISTS citas_servicios_adicionales (
                        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                        cita_id INT UNSIGNED NOT NULL,
                        servicio_id INT UNSIGNED NOT NULL,
                        nombre VARCHAR(150) NOT NULL,
                        precio DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                        fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        INDEX idx_csa_cita (cita_id),
                        INDEX idx_csa_servicio (servicio_id)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
                ");
            } catch (Exception $exCsa) {}

            $preciosMateriales = $_POST['precios_materiales'] ?? $_POST['precios_venta'] ?? [];
            if (!is_array($materiales) || !is_array($cantidades) || !is_array($preciosMateriales)) {
                throw new Exception('La lista de productos no es válida.');
            }
            $productosVenta = [];
            foreach ($materiales as $indice => $productoId) {
                if ($productoId !== '') {
                    $productosVenta[] = ['producto_id' => $productoId, 'cantidad' => $cantidades[$indice] ?? null,
                        'precio_unitario' => $preciosMateriales[$indice] ?? null];
                }
            }
            $pdo->beginTransaction();
            $stmtCitaVenta = $pdo->prepare('SELECT sucursal_id, estado, reasignacion_pendiente FROM citas WHERE id = ? FOR UPDATE');
            $stmtCitaVenta->execute([$id]);
            $citaVenta = $stmtCitaVenta->fetch(PDO::FETCH_ASSOC);
            if (!$citaVenta || in_array($citaVenta['estado'], ['completada', 'cancelada'], true)) {
                throw new Exception('La cita no existe, ya fue finalizada o está cancelada.');
            }
            if (!empty($citaVenta['reasignacion_pendiente'])) throw new Exception('Asigna un barbero disponible antes de finalizar esta cita.');
            validarSucursalVenta($pdo, getOperadorVenta($pdo, (int) $_SESSION['user_id']), (int) $citaVenta['sucursal_id']);

            // Cargar datos previos de la cita para cálculo de precio y comisiones
            $stmtCitaBase = $pdo->prepare("
                SELECT c.*, s.nombre as servicio_nombre, COALESCE(s.precio, 0.00) as servicio_precio,
                       u.nombre as barbero_nombre, COALESCE(u.comision_porcentaje, 50.0) as barbero_comision,
                       COALESCE(u.comision_fin_semana, 50.0) as barbero_comision_finde,
                       cl.nombre as cliente_nombre, cl.telefono as cliente_telefono
                FROM citas c
                LEFT JOIN servicios s ON c.servicio_id = s.id
                LEFT JOIN usuarios u ON c.barbero_id = u.id
                LEFT JOIN clientes cl ON c.cliente_id = cl.id
                WHERE c.id = ?
            ");
            $stmtCitaBase->execute([$id]);
            $citaBase = $stmtCitaBase->fetch(PDO::FETCH_ASSOC);
            if (!$citaBase) {
                throw new Exception('La cita no existe.');
            }

            // Calcular precio base del servicio agendado
            $precioBase = isset($_POST['precio_base']) && floatval($_POST['precio_base']) > 0
                ? floatval($_POST['precio_base'])
                : floatval($citaBase['servicio_precio'] > 0 ? $citaBase['servicio_precio'] : ($citaBase['precio_final'] ?? 0));

            // Procesar servicios adicionales
            $serviciosAdicionalesIds = $_POST['servicios_adicionales'] ?? [];
            if (!is_array($serviciosAdicionalesIds)) {
                $serviciosAdicionalesIds = [];
            }

            // Limpiar registros previos de servicios adicionales para esta cita (evitar duplicidad)
            $stmtDelAdic = $pdo->prepare("DELETE FROM citas_servicios_adicionales WHERE cita_id = ?");
            $stmtDelAdic->execute([$id]);

            $sumaAdicionales = 0.00;
            $nombresAdicionales = [];
            $itemsAdicionales = [];

            if (!empty($serviciosAdicionalesIds)) {
                $stmtInsAdic = $pdo->prepare("INSERT INTO citas_servicios_adicionales (cita_id, servicio_id, nombre, precio) VALUES (?, ?, ?, ?)");
                $stmtGetSrv = $pdo->prepare("SELECT id, nombre, precio FROM servicios WHERE id = ?");

                foreach ($serviciosAdicionalesIds as $srvId) {
                    $srvId = intval($srvId);
                    if ($srvId > 0) {
                        $stmtGetSrv->execute([$srvId]);
                        $srvData = $stmtGetSrv->fetch(PDO::FETCH_ASSOC);
                        if ($srvData) {
                            $srvPrecio = floatval($srvData['precio']);
                            $srvNombre = $srvData['nombre'];
                            $stmtInsAdic->execute([$id, $srvId, $srvNombre, $srvPrecio]);
                            $sumaAdicionales += $srvPrecio;
                            $nombresAdicionales[] = $srvNombre . " ($" . number_format($srvPrecio, 2) . ")";
                            $itemsAdicionales[] = [
                                'id' => $srvId,
                                'nombre' => $srvNombre,
                                'precio' => $srvPrecio
                            ];
                        }
                    }
                }
            }

            $nuevoPrecioFinal = $precioBase + $sumaAdicionales;
            $txtAdicionales = !empty($nombresAdicionales) ? implode(', ', $nombresAdicionales) : null;

            // 1. Marcar completada con propina, precio_final actualizado, servicios adicionales y timestamp para notificación a los 30 min
            try {
                $stmtComp = $pdo->prepare("UPDATE citas SET estado = 'completada', fecha_completada = NOW(), notif_calificacion_enviada = 0, propina = ?, precio_final = ?, servicios_adicionales_txt = ? WHERE id = ?");
                $stmtComp->execute([$propina, $nuevoPrecioFinal, $txtAdicionales, $id]);
            } catch (Exception $eCompUpd) {
                // Fallback por si las columnas están migrándose
                $stmtComp = $pdo->prepare("UPDATE citas SET estado = 'completada', propina = ?, precio_final = ?, servicios_adicionales_txt = ? WHERE id = ?");
                $stmtComp->execute([$propina, $nuevoPrecioFinal, $txtAdicionales, $id]);
            }

            // Cargar puntos configurados
            $stmtCfg = $pdo->query("SELECT clave, valor FROM configuracion");
            $cfgs = $stmtCfg->fetchAll(PDO::FETCH_KEY_PAIR);
            $puntosPorCorte = intval($cfgs['puntos_por_corte'] ?? 100);

            // Sumar Puntos KORTZEN al cliente por completar cita
            $stmtCitaClient = $pdo->prepare("SELECT cliente_id FROM citas WHERE id = ?");
            $stmtCitaClient->execute([$id]);
            $clientRow = $stmtCitaClient->fetch();
            if ($clientRow && !empty($clientRow['cliente_id'])) {
                $stmtAddPts = $pdo->prepare("UPDATE clientes SET puntos = COALESCE(puntos, 0) + ? WHERE id = ?");
                $stmtAddPts->execute([$puntosPorCorte, $clientRow['cliente_id']]);
            }

            // Procesar recompensa de referido pendiente
            try {
                $stmtPendingRef = $pdo->prepare("SELECT * FROM referidos WHERE cita_id = ? AND estado = 'pendiente'");
                $stmtPendingRef->execute([$id]);
                $refPending = $stmtPendingRef->fetch(PDO::FETCH_ASSOC);

                if ($refPending) {
                    $referenteId = $refPending['referente_id'];
                    $puntosBonus = intval($refPending['puntos_otorgados'] ?? 200);

                    // 1. Marcar referido como completado
                    $stmtMarkRef = $pdo->prepare("UPDATE referidos SET estado = 'completado' WHERE id = ?");
                    $stmtMarkRef->execute([$refPending['id']]);

                    // 2. Sumar puntos bonus al referente
                    if ($referenteId > 0 && $puntosBonus > 0) {
                        $stmtAddRefPts = $pdo->prepare("UPDATE clientes SET puntos = COALESCE(puntos, 0) + ? WHERE id = ?");
                        $stmtAddRefPts->execute([$puntosBonus, $referenteId]);
                    }
                }
            } catch (Exception $exRef) {}

            // Registrar productos con vendedor y precio real, conservando los datos del comprobante.
            $ventaProductos = null;
            $itemsProductos = [];
            $totalProductos = 0.00;
            if ($productosVenta) {
                $ventaProductos = registrarVentaProductos($pdo, $productosVenta, $_POST['vendedor_id'] ?? null,
                    (int) $_SESSION['user_id'], $id, (int) $citaVenta['sucursal_id'], $metodoPago);
                $itemsProductos = $ventaProductos['items'];
                $totalProductos = $ventaProductos['total'];
            }

            $stmtCDetails = $pdo->prepare("
                SELECT c.nombre as cliente_nombre, s.nombre as servicio_nombre, b.nombre as barbero_nombre, cita.precio_final
                FROM citas cita
                LEFT JOIN clientes c ON cita.cliente_id = c.id
                LEFT JOIN servicios s ON cita.servicio_id = s.id
                LEFT JOIN usuarios b ON cita.barbero_id = b.id
                WHERE cita.id = ?
            ");
            $stmtCDetails->execute([$id]);
            $cD = $stmtCDetails->fetch(PDO::FETCH_ASSOC);
            $cobroId = registrarCobroCaja($pdo, ['cita_id' => $id, 'sucursal_id' => $citaVenta['sucursal_id'],
                'registrado_por' => (int) $_SESSION['user_id'], 'metodo_pago' => $metodoPago,
                'servicios' => $nuevoPrecioFinal, 'productos' => $totalProductos, 'propina' => $propina]);
            $stmtMetodo = $pdo->prepare('UPDATE citas SET metodo_pago = ? WHERE id = ?');
            $stmtMetodo->execute([$metodoPago, $id]);
            if ($ventaProductos !== null) {
                $idsVenta = $ventaProductos['ventas_ids'];
                $stmtCobro = $pdo->prepare('UPDATE ventas_productos SET cobro_id = ? WHERE id IN (' . implode(',', array_fill(0, count($idsVenta), '?')) . ')');
                $stmtCobro->execute(array_merge([$cobroId], $idsVenta));
            }
            $pdo->commit();
            if ($ventaProductos !== null) {
                notificarStockVentas($pdo, $ventaProductos, (int) $citaBase['barbero_id']);
            }

            $cNombre = $cD ? $cD['cliente_nombre'] : "Cita #$id";
            $sNombre = $cD ? $cD['servicio_nombre'] : "Servicio";
            $bNombre = $cD ? $cD['barbero_nombre'] : "Barbero";
            $precioVal = number_format(floatval($cD['precio_final'] ?? 0), 2);

            $esFinde = in_array(date('w', strtotime($citaBase['fecha_hora'])), [0, 6]);
            $comPct = $esFinde 
                ? floatval($citaBase['barbero_comision_finde'] ?? ($citaBase['barbero_comision'] ?? 50.0))
                : floatval($citaBase['barbero_comision'] ?? 50.0);
            $comisionTotalBarbero = ($nuevoPrecioFinal * $comPct / 100);

            $totalCobrarFinal = $nuevoPrecioFinal + $totalProductos + $propina;
            $detalleAdicLog = !empty($txtAdicionales) ? " + Adicionales: [$txtAdicionales]" : "";
            $detalleProdLog = ($totalProductos > 0) ? " + Productos: $" . number_format($totalProductos, 2) : "";
            registrarLog('COMPLETAR', 'citas', $id, "Cita #$id finalizada para el cliente '$cNombre' (Servicio: '$sNombre'$detalleAdicLog$detalleProdLog, Barbero: '$bNombre', Servicios: $$precioVal, Productos: $$totalProductos, Comisión Barbero ({$comPct}%): $$comisionTotalBarbero, Propina: $$propina, Total Pagado: $$totalCobrarFinal, +$puntosPorCorte pts ganados)");

            $msgOk = 'Cita completada con éxito.';
            if ($propina > 0) {
                $msgOk .= ' Propina registrada para el barbero: $' . number_format($propina, 2);
            }

            $protocolo = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443) ? 'https' : 'http';
            $hostActual = $_SERVER['HTTP_HOST'] ?? 'localhost';
            $linkCalificar = "{$protocolo}://{$hostActual}/calificar.php?c={$id}";

            $clienteNombreCorto = !empty($cNombre) ? explode(' ', trim($cNombre))[0] : 'Cliente';
            $waMsgCalificar = "¡Hola {$clienteNombreCorto}! Gracias por visitarnos hoy en Kortzen. ¿Cómo calificarías tu corte con {$bNombre}? Déjanos tu calificación de 1 a 5 estrellas y apóyanos con tu reseña en Google aquí: {$linkCalificar}";
            $rawPhone = $cD['cliente_telefono'] ?? ($citaBase['cliente_telefono'] ?? '');
            $telLimpio = preg_replace('/[^0-9]/', '', $rawPhone);
            if (!empty($telLimpio) && strlen($telLimpio) === 10 && substr($telLimpio, 0, 1) === '0') {
                $telLimpio = '593' . substr($telLimpio, 1);
            }
            $waUrlCalificar = !empty($telLimpio)
                ? "https://wa.me/{$telLimpio}?text=" . rawurlencode($waMsgCalificar)
                : "https://wa.me/?text=" . rawurlencode($waMsgCalificar);

            // Nota: La notificación Push al cliente queda programada para enviarse automáticamente
            // a los 30 minutos de finalizada la cita mediante el cron / procesador en segundo plano.

            $reciboData = [
                'metodo_pago' => $metodoPago,
                'metodo_pago_label' => metodosPagoKortzen()[$metodoPago],
                'cita_id' => $id,
                'fecha_hora' => $citaBase['fecha_hora'],
                'fecha_hora_formateada' => date('d/m/Y H:i', strtotime($citaBase['fecha_hora'])),
                'cliente_nombre' => $cNombre,
                'cliente_telefono' => $rawPhone,
                'barbero_nombre' => $bNombre,
                'servicio_principal' => $sNombre,
                'precio_principal' => $precioBase,
                'servicios_adicionales' => $itemsAdicionales,
                'suma_adicionales' => $sumaAdicionales,
                'total_servicios' => $nuevoPrecioFinal,
                'productos' => $itemsProductos,
                'total_productos' => $totalProductos,
                'propina' => $propina,
                'total_a_cobrar' => $totalCobrarFinal,
                'total_pagado' => $totalCobrarFinal,
                'comision_porcentaje' => $comPct,
                'comision_barbero' => $comisionTotalBarbero,
                'ganancia_neta_negocio' => $nuevoPrecioFinal - $comisionTotalBarbero,
                'link_calificar' => $linkCalificar,
                'wa_msg_calificar' => $waMsgCalificar,
                'wa_url_calificar' => $waUrlCalificar
            ];

            responderAccionCita($isAjax, $redirect_url, $msgOk, [
                'cita_id' => $id,
                'propina' => $propina,
                'nuevo_precio_final' => $nuevoPrecioFinal,
                'total_productos' => $totalProductos,
                'total_pagado' => $totalCobrarFinal,
                'link_calificar' => $linkCalificar,
                'wa_url_calificar' => $waUrlCalificar,
                'recibo' => $reciboData
            ]);

        case 'get_recibo':
            $id = intval($_GET['id'] ?? $_POST['id'] ?? 0);
            if ($id <= 0) {
                throw new Exception('ID de cita inválido.');
            }

            $stmtR = $pdo->prepare("
                SELECT c.*, 
                       cl.nombre as cliente_nombre, cl.telefono as cliente_telefono,
                       s.nombre as servicio_nombre, COALESCE(s.precio, 0.00) as servicio_precio,
                       u.nombre as barbero_nombre, COALESCE(u.comision_porcentaje, 50.0) as barbero_comision,
                       COALESCE(u.comision_fin_semana, 50.0) as barbero_comision_finde,
                       su.nombre as sucursal_nombre, su.direccion as sucursal_direccion
                FROM citas c
                LEFT JOIN clientes cl ON c.cliente_id = cl.id
                LEFT JOIN servicios s ON c.servicio_id = s.id
                LEFT JOIN usuarios u ON c.barbero_id = u.id
                LEFT JOIN sucursales su ON c.sucursal_id = su.id
                WHERE c.id = ?
            ");
            $stmtR->execute([$id]);
            $citaR = $stmtR->fetch(PDO::FETCH_ASSOC);
            if (!$citaR) {
                throw new Exception('La cita no existe.');
            }

            $stmtCobroHistorico = $pdo->prepare('SELECT * FROM cobros_caja WHERE cita_id = ?');
            $stmtCobroHistorico->execute([$id]);
            $cobroHistorico = $stmtCobroHistorico->fetch(PDO::FETCH_ASSOC);

            // Obtener adicionales de la tabla de servicios adicionales
            $stmtAd = $pdo->prepare("SELECT id, servicio_id, nombre, precio FROM citas_servicios_adicionales WHERE cita_id = ? ORDER BY id ASC");
            $stmtAd->execute([$id]);
            $adicionales = $stmtAd->fetchAll(PDO::FETCH_ASSOC);

            $sumaAdic = 0.00;
            foreach ($adicionales as $ad) {
                $sumaAdic += floatval($ad['precio']);
            }

            $precioFinal = floatval($citaR['precio_final'] > 0 ? $citaR['precio_final'] : ($citaR['servicio_precio'] + $sumaAdic));
            $precioBase = floatval($citaR['servicio_precio']);
            if ($cobroHistorico) {
                $precioFinal = (float) $cobroHistorico['servicios'];
                $precioBase = max(0, $precioFinal - $sumaAdic);
            }
            if ($precioFinal > $sumaAdic && $precioBase == 0) {
                $precioBase = $precioFinal - $sumaAdic;
            }

            // Obtener productos asociados a la cita de ventas_productos
            $stmtProd = $pdo->prepare("
                SELECT vp.producto_id, vp.cantidad, vp.precio_unitario, i.producto as producto_nombre
                FROM ventas_productos vp
                LEFT JOIN inventario i ON vp.producto_id = i.id
                WHERE vp.cita_id = ?
                ORDER BY vp.id ASC
            ");
            $stmtProd->execute([$id]);
            $prods = $stmtProd->fetchAll(PDO::FETCH_ASSOC);

            $itemsProductos = [];
            $totalProductos = 0.00;
            foreach ($prods as $p) {
                $cantP = floatval($p['cantidad']);
                $precP = floatval($p['precio_unitario']);
                $subtotalP = $cantP * $precP;
                $totalProductos += $subtotalP;
                $itemsProductos[] = [
                    'id' => $p['producto_id'],
                    'nombre' => $p['producto_nombre'] ?? 'Producto',
                    'cantidad' => $cantP,
                    'precio_unitario' => $precP,
                    'total' => $subtotalP
                ];
            }

            if ($cobroHistorico) $totalProductos = (float) $cobroHistorico['productos'];
            $propina = (float) ($cobroHistorico['propina'] ?? $citaR['propina'] ?? 0.00);
            $totalCobrar = $precioFinal + $totalProductos + $propina;
            $esFinde = in_array(date('w', strtotime($citaR['fecha_hora'])), [0, 6]);
            $comPct = $esFinde ? floatval($citaR['barbero_comision_finde']) : floatval($citaR['barbero_comision']);
            $comisionBarbero = ($precioFinal * $comPct / 100);

            $protocolo = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443) ? 'https' : 'http';
            $hostActual = $_SERVER['HTTP_HOST'] ?? 'kortzen.com';
            $linkCalificar = "{$protocolo}://{$hostActual}/calificar.php?c={$id}";

            $cliNomCorto = !empty($citaR['cliente_nombre']) ? explode(' ', trim($citaR['cliente_nombre']))[0] : 'Cliente';
            $barbNom = $citaR['barbero_nombre'] ?? 'Barbero';
            $waMsgCalificar = "¡Hola {$cliNomCorto}! Gracias por visitarnos hoy en Kortzen. ¿Cómo calificarías tu corte con {$barbNom}? Déjanos tu calificación de 1 a 5 estrellas y apóyanos con tu reseña en Google aquí: {$linkCalificar}";
            $rawPhoneR = $citaR['cliente_telefono'] ?? '';
            $telLimpioR = preg_replace('/[^0-9]/', '', $rawPhoneR);
            if (!empty($telLimpioR) && strlen($telLimpioR) === 10 && substr($telLimpioR, 0, 1) === '0') {
                $telLimpioR = '593' . substr($telLimpioR, 1);
            }
            $waUrlCalificar = !empty($telLimpioR)
                ? "https://wa.me/{$telLimpioR}?text=" . rawurlencode($waMsgCalificar)
                : "https://wa.me/?text=" . rawurlencode($waMsgCalificar);

            responderAccionCita(true, '', 'Recibo obtenido con éxito.', [
                'recibo' => [
                    'metodo_pago' => $cobroHistorico['metodo_pago'] ?? $citaR['metodo_pago'] ?? null,
                    'metodo_pago_label' => metodosPagoKortzen()[$cobroHistorico['metodo_pago'] ?? $citaR['metodo_pago'] ?? ''] ?? 'Sin especificar',
                    'cita_id' => $id,
                    'fecha_hora' => $citaR['fecha_hora'],
                    'fecha_hora_formateada' => date('d/m/Y H:i', strtotime($citaR['fecha_hora'])),
                    'sucursal_nombre' => $citaR['sucursal_nombre'] ?? 'Kortzen Barbería',
                    'sucursal_direccion' => $citaR['sucursal_direccion'] ?? '',
                    'cliente_nombre' => $citaR['cliente_nombre'] ?? 'Cliente',
                    'cliente_telefono' => $citaR['cliente_telefono'] ?? '',
                    'barbero_nombre' => $citaR['barbero_nombre'] ?? 'Barbero',
                    'servicio_principal' => $citaR['servicio_nombre'] ?? 'Corte',
                    'precio_principal' => $precioBase,
                    'servicios_adicionales' => $adicionales,
                    'suma_adicionales' => $sumaAdic,
                    'total_servicios' => $precioFinal,
                    'productos' => $itemsProductos,
                    'total_productos' => $totalProductos,
                    'propina' => $propina,
                    'total_a_cobrar' => $totalCobrar,
                    'total_pagado' => $totalCobrar,
                    'comision_porcentaje' => $comPct,
                    'comision_barbero' => $comisionBarbero,
                    'ganancia_neta_negocio' => $precioFinal - $comisionBarbero,
                    'link_calificar' => $linkCalificar,
                    'wa_msg_calificar' => $waMsgCalificar,
                    'wa_url_calificar' => $waUrlCalificar
                ]
            ]);
            break;

        case 'marcar_no_asistio':
            $id = intval($_POST['id'] ?? $_GET['id'] ?? 0);
            if ($id <= 0) {
                throw new Exception('ID de cita inválido.');
            }

            // Obtener cita
            $stmtC = $pdo->prepare("
                SELECT c.*, cli.nombre as cliente_nombre, u.nombre as barbero_nombre, s.nombre as servicio_nombre
                FROM citas c
                LEFT JOIN clientes cli ON c.cliente_id = cli.id
                LEFT JOIN usuarios u ON c.barbero_id = u.id
                LEFT JOIN servicios s ON c.servicio_id = s.id
                WHERE c.id = ?
            ");
            $stmtC->execute([$id]);
            $citaRow = $stmtC->fetch(PDO::FETCH_ASSOC);
            if (!$citaRow) {
                throw new Exception('La cita no existe.');
            }

            // Validar RBAC: el barbero asignado o cualquier administrador
            $userRol = $_SESSION['user_rol'] ?? '';
            $userId = intval($_SESSION['user_id'] ?? 0);
            $userSucursal = intval($_SESSION['user_sucursal_id'] ?? 0);

            if ($userRol === 'barbero' && intval($citaRow['barbero_id']) !== $userId) {
                throw new Exception('Acceso denegado: Solo puedes reportar inasistencia en tus propias citas.');
            }
            if ($userRol === 'admin_local' && $userSucursal > 0 && intval($citaRow['sucursal_id']) !== $userSucursal) {
                throw new Exception('Acceso denegado: Esta cita pertenece a otra sucursal.');
            }

            // Auto-migración segura
            try {
                $pdo->exec("ALTER TABLE citas MODIFY COLUMN estado ENUM('pendiente', 'confirmada', 'en_atencion', 'completada', 'cancelada', 'no_asistio') NOT NULL DEFAULT 'pendiente'");
            } catch (Throwable $eEnum) {
                try {
                    $pdo->exec("ALTER TABLE citas MODIFY COLUMN estado VARCHAR(50) NOT NULL DEFAULT 'pendiente'");
                } catch (Throwable $eVar) {}
            }

            // Actualizar estado a no_asistio y asistencia_confirmada a 0
            $stmtUpd = $pdo->prepare("UPDATE citas SET estado = 'no_asistio', asistencia_confirmada = 0 WHERE id = ?");
            $stmtUpd->execute([$id]);

            $cliNombre = $citaRow['cliente_nombre'] ?? 'Cliente';
            $horaLegible = date('d/m/Y H:i', strtotime($citaRow['fecha_hora']));
            registrarLog('NO_ASISTIO', 'citas', $id, "Cita #$id ($horaLegible) marcada como 'NO ASISTIÓ' para el cliente '$cliNombre'");

            responderAccionCita($isAjax, $redirect_url, 'Cita registrada como No Asistió correctamente.', [
                'cita_id' => $id,
                'estado' => 'no_asistio',
                'estado_label' => 'NO ASISTIÓ'
            ]);
            break;

        case 'revertir_no_asistio':
            $id = intval($_POST['id'] ?? $_GET['id'] ?? 0);
            if ($id <= 0) {
                throw new Exception('ID de cita inválido.');
            }

            $stmtC = $pdo->prepare("SELECT * FROM citas WHERE id = ?");
            $stmtC->execute([$id]);
            $citaRow = $stmtC->fetch(PDO::FETCH_ASSOC);
            if (!$citaRow) throw new Exception('La cita no existe.');

            $userRol = $_SESSION['user_rol'] ?? '';
            $userId = intval($_SESSION['user_id'] ?? 0);
            if ($userRol === 'barbero' && intval($citaRow['barbero_id']) !== $userId) {
                throw new Exception('Acceso denegado.');
            }

            $stmtUpd = $pdo->prepare("UPDATE citas SET estado = 'confirmada', asistencia_confirmada = 1 WHERE id = ?");
            $stmtUpd->execute([$id]);

            registrarLog('REVERTIR_NO_ASISTIO', 'citas', $id, "Inasistencia de cita #$id revertida a 'confirmada'");

            responderAccionCita($isAjax, $redirect_url, 'Inasistencia revertida a confirmada.', [
                'cita_id' => $id,
                'estado' => 'confirmada',
                'estado_label' => 'ASISTENCIA CONFIRMADA'
            ]);
            break;

        case 'cambiar_estado':
            $id = intval($_POST['id'] ?? $_GET['id'] ?? 0);
            $nuevoEstado = trim($_POST['estado'] ?? $_GET['estado'] ?? '');
            $propina = floatval($_POST['propina'] ?? 0.00);
            if ($propina < 0) $propina = 0.00;

            if ($nuevoEstado === 'completada') throw new Exception('Usa Finalizar Cita para registrar el método de pago y el cobro.');

            $validEstados = ['pendiente', 'confirmada', 'en_atencion', 'completada', 'cancelada', 'no_asistio'];

            if ($id <= 0 || !in_array($nuevoEstado, $validEstados)) {
                throw new Exception('Parámetros de cita o estado inválidos.');
            }

            // Obtener estado anterior y detalles de la cita
            $stmtCitaInfo = $pdo->prepare("
                SELECT c.*, cli.id as cliente_id, cli.nombre as cliente_nombre, s.nombre as servicio_nombre, u.nombre as barbero_nombre
                FROM citas c
                LEFT JOIN clientes cli ON c.cliente_id = cli.id
                LEFT JOIN servicios s ON c.servicio_id = s.id
                LEFT JOIN usuarios u ON c.barbero_id = u.id
                WHERE c.id = ?
            ");
            $stmtCitaInfo->execute([$id]);
            $citaRow = $stmtCitaInfo->fetch(PDO::FETCH_ASSOC);

            if (!$citaRow) {
                throw new Exception('La cita no existe.');
            }

            // Validar Permisos de Acceso y Propiedad de Cita (RBAC & IDOR Protection)
            $userRol = $_SESSION['user_rol'] ?? '';
            $userId = intval($_SESSION['user_id'] ?? 0);
            $userSucursal = intval($_SESSION['user_sucursal_id'] ?? 0);

            if ($userRol === 'barbero' && intval($citaRow['barbero_id']) !== $userId) {
                throw new Exception('Acceso denegado: Solo puedes modificar tus propias citas.');
            }

            if ($userRol === 'admin_local' && $userSucursal > 0 && intval($citaRow['sucursal_id']) !== $userSucursal) {
                throw new Exception('Acceso denegado: Esta cita pertenece a otra sucursal.');
            }

            $estadoAnterior = $citaRow['estado'];

            // Actualizar estado (y propina si se envió)
            if ($nuevoEstado === 'completada' && $propina >= 0) {
                try {
                    $pdo->exec("ALTER TABLE citas ADD COLUMN propina DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER precio_final");
                } catch (Exception $exP) {}

                $stmtUpdate = $pdo->prepare("UPDATE citas SET estado = ?, propina = ? WHERE id = ?");
                $stmtUpdate->execute([$nuevoEstado, $propina, $id]);
            } elseif ($nuevoEstado === 'pendiente') {
                // Si el admin resetea a Pendiente, resetear asistencia_confirmada = 0 en toda la plataforma
                $stmtUpdate = $pdo->prepare("UPDATE citas SET estado = 'pendiente', asistencia_confirmada = 0 WHERE id = ?");
                $stmtUpdate->execute([$id]);
            } elseif ($nuevoEstado === 'confirmada') {
                // Si el admin marca como Confirmada, marcar asistencia_confirmada = 1
                $stmtUpdate = $pdo->prepare("UPDATE citas SET estado = 'confirmada', asistencia_confirmada = 1 WHERE id = ?");
                $stmtUpdate->execute([$id]);
            } elseif ($nuevoEstado === 'no_asistio') {
                // Inasistencia: asistencia_confirmada = 0
                $stmtUpdate = $pdo->prepare("UPDATE citas SET estado = 'no_asistio', asistencia_confirmada = 0 WHERE id = ?");
                $stmtUpdate->execute([$id]);
            } else {
                $stmtUpdate = $pdo->prepare("UPDATE citas SET estado = ? WHERE id = ?");
                $stmtUpdate->execute([$nuevoEstado, $id]);
            }

            // Si cambió a COMPLETADA y antes NO estaba completada: Otorgar puntos de fidelidad y bonus referido
            if ($nuevoEstado === 'completada' && $estadoAnterior !== 'completada') {
                if (!empty($citaRow['cliente_id'])) {
                    // Cargar puntos configurados
                    $stmtCfg = $pdo->query("SELECT clave, valor FROM configuracion");
                    $cfgs = $stmtCfg->fetchAll(PDO::FETCH_KEY_PAIR);
                    $puntosPorCorte = intval($cfgs['puntos_por_corte'] ?? 100);

                    try {
                        $pdo->exec("ALTER TABLE clientes ADD COLUMN puntos INT DEFAULT 0 AFTER telefono");
                    } catch (Exception $ex) {}
                    $stmtAddPts = $pdo->prepare("UPDATE clientes SET puntos = COALESCE(puntos, 0) + ? WHERE id = ?");
                    $stmtAddPts->execute([$puntosPorCorte, $citaRow['cliente_id']]);
                }

                try {
                    $stmtPendingRef = $pdo->prepare("SELECT * FROM referidos WHERE cita_id = ? AND estado = 'pendiente'");
                    $stmtPendingRef->execute([$id]);
                    $refPending = $stmtPendingRef->fetch(PDO::FETCH_ASSOC);

                    if ($refPending) {
                        $referenteId = $refPending['referente_id'];
                        $puntosBonus = intval($refPending['puntos_otorgados'] ?? 200);

                        $stmtMarkRef = $pdo->prepare("UPDATE referidos SET estado = 'completado' WHERE id = ?");
                        $stmtMarkRef->execute([$refPending['id']]);

                        if ($referenteId > 0 && $puntosBonus > 0) {
                            $stmtAddRefPts = $pdo->prepare("UPDATE clientes SET puntos = COALESCE(puntos, 0) + ? WHERE id = ?");
                            $stmtAddRefPts->execute([$puntosBonus, $referenteId]);
                        }
                    }
                } catch (Exception $exRef) {}
            }

            // Notificaciones en tiempo real al Barbero Asignado (PWA + WebPush)
            $barberoIdNotif = intval($citaRow['barbero_id'] ?? 0);
            if ($barberoIdNotif > 0) {
                try {
                    $tipoNotif = 'cambio_estado';
                    if ($nuevoEstado === 'confirmada') $tipoNotif = 'cita_confirmada';
                    elseif ($nuevoEstado === 'cancelada') $tipoNotif = 'cita_cancelada';
                    
                    notificarBarbero($pdo, $barberoIdNotif, $id, $tipoNotif, [
                        'cliente' => $citaRow['cliente_nombre'] ?? 'Cliente',
                        'servicio' => $citaRow['servicio_nombre'] ?? 'Servicio',
                        'fecha' => date('d/m/Y', strtotime($citaRow['fecha_hora'])),
                        'hora' => date('H:i', strtotime($citaRow['fecha_hora']))
                    ]);
                } catch (Exception $eNotif) {}
            }

            // Notificar al Cliente si se cancela o confirma
            $clienteIdNotif = intval($citaRow['cliente_id'] ?? 0);
            if ($clienteIdNotif > 0) {
                try {
                    if ($nuevoEstado === 'confirmada') {
                        notificarCliente($pdo, $clienteIdNotif, $id, 'Cita Confirmada', 'Tu cita en Kortzen ha sido confirmada por el equipo.', '/cliente-dashboard.php');
                    } elseif ($nuevoEstado === 'cancelada') {
                        notificarCliente($pdo, $clienteIdNotif, $id, 'Cita Cancelada', 'Tu cita de barbería ha sido cancelada.', '/cliente-dashboard.php');
                    }
                } catch (Exception $eNotif) {}
            }

            $cNombre = $citaRow['cliente_nombre'] ?? "Cita #$id";
            $sNombre = $citaRow['servicio_nombre'] ?? "Servicio";
            registrarLog('EDITAR', 'citas', $id, "Estado de cita #$id ($sNombre) cambiado a '$nuevoEstado' para '$cNombre'");

            responderAccionCita($isAjax, $redirect_url, "Estado de cita actualizado exitosamente a " . ucfirst($nuevoEstado), ['cita_id' => $id, 'estado' => $nuevoEstado]);

        case 'delete':
            $id = intval($_POST['id'] ?? 0);

            if ($id <= 0) {
                throw new Exception('ID de cita inválido.');
            }

            $userRol = $_SESSION['user_rol'] ?? '';
            $userSucursal = intval($_SESSION['user_sucursal_id'] ?? 0);

            if ($userRol === 'barbero') {
                throw new Exception('Acceso denegado: Los barberos no tienen permisos para eliminar citas.');
            }

            $stmtCDetails = $pdo->prepare("
                SELECT cita.sucursal_id, c.nombre as cliente_nombre, s.nombre as servicio_nombre
                FROM citas cita
                LEFT JOIN clientes c ON cita.cliente_id = c.id
                LEFT JOIN servicios s ON cita.servicio_id = s.id
                WHERE cita.id = ?
            ");
            $stmtCDetails->execute([$id]);
            $cD = $stmtCDetails->fetch(PDO::FETCH_ASSOC);

            if (!$cD) {
                throw new Exception('La cita a eliminar no existe.');
            }

            if ($userRol === 'admin_local' && $userSucursal > 0 && intval($cD['sucursal_id']) !== $userSucursal) {
                throw new Exception('Acceso denegado: No puedes eliminar citas de otra sucursal.');
            }

            $cNombre = $cD['cliente_nombre'] ?? "Cliente";
            $sNombre = $cD['servicio_nombre'] ?? "Servicio";

            $sql = "DELETE FROM citas WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$id]);

            registrarLog('ELIMINAR', 'citas', $id, "Cita #$id ('$sNombre') del cliente '$cNombre' fue eliminada del sistema");

            responderAccionCita($isAjax, $redirect_url, 'Cita eliminada exitosamente.', ['cita_id' => $id]);

        default:
            throw new Exception('Acción no válida.');
    }

} catch (PDOException $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Error en citas_action.php: " . $e->getMessage());
    if ($isAjax) {
        header('Content-Type: application/json');
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Error de base de datos: ' . $e->getMessage()]);
        exit;
    }
    $parts = explode('?', $redirect_url, 2);
    $path = $parts[0];
    $query = [];
    if (isset($parts[1])) parse_str($parts[1], $query);
    $query['error'] = 'Error de base de datos';
    header('Location: ' . $path . '?' . http_build_query($query));
    exit;

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Error en citas_action.php: " . $e->getMessage());
    if ($isAjax) {
        header('Content-Type: application/json');
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        exit;
    }
    $parts = explode('?', $redirect_url, 2);
    $path = $parts[0];
    $query = [];
    if (isset($parts[1])) parse_str($parts[1], $query);
    $query['error'] = $e->getMessage();
    header('Location: ' . $path . '?' . http_build_query($query));
    exit;
}
