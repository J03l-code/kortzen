<?php
require_once '../config.php';

$action = $_POST['action'] ?? '';

if ($action !== 'cancelar_cita_cliente' && !isLoggedIn()) {
    header('Location: ../login.php');
    exit;
}

try {
    $pdo = getConnection();

    $redirect_url = '../citas.php'; // Default
    if (isset($_POST['redirect_source']) && $_POST['redirect_source'] === 'dashboard') {
        $redirect_url = '../dashboard.php';
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
            $stmtCita = $pdo->prepare("SELECT id, fecha_hora, estado FROM citas WHERE id = ? AND cliente_id = ?");
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

            header('Location: ../cliente-dashboard.php?success=' . urlencode('Tu cita ha sido cancelada exitosamente.'));
            exit;

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

            $fecha_hora = $fecha . ' ' . $hora . ':00';

            $sql = "INSERT INTO citas (cliente_id, servicio_id, barbero_id, sucursal_id, fecha_hora, estado, notas) 
                    VALUES (?, ?, ?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$cliente_id, $servicio_id, $barbero_id, $sucursal_id, $fecha_hora, $estado, $notas]);

            header('Location: ../citas.php?success=Cita creada exitosamente');
            exit;

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

            $sql = "UPDATE citas SET estado = 'cancelada' WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$id]);

            registrarLog('UPDATE', 'citas', $id, 'Cita cancelada por barbero');
            header('Location: ' . $redirect_url . '?success=' . urlencode('Cita cancelada correctamente.'));
            exit;

        case 'completar':
            if (!in_array($_SESSION['user_rol'], ['barbero', 'admin', 'admin_local'])) {
                throw new Exception('No autorizado. Rol actual: ' . ($_SESSION['user_rol'] ?? 'ninguno'));
            }

            $id = intval($_POST['id'] ?? 0);
            // Array de materiales y cantidades
            $materiales = $_POST['materiales'] ?? [];
            $cantidades = $_POST['cantidades'] ?? [];

            if ($id <= 0) {
                throw new Exception('ID inválido');
            }

            // 1. Marcar completada
            $stmtComp = $pdo->prepare("UPDATE citas SET estado = 'completada' WHERE id = ?");
            $stmtComp->execute([$id]);

            // Cargar puntos configurados
            $stmtCfg = $pdo->query("SELECT clave, valor FROM configuracion");
            $cfgs = $stmtCfg->fetchAll(PDO::FETCH_KEY_PAIR);
            $puntosPorCorte = intval($cfgs['puntos_por_corte'] ?? 100);

            // Sumar Puntos KORTZEN al cliente por completar cita
            $stmtCitaClient = $pdo->prepare("SELECT cliente_id FROM citas WHERE id = ?");
            $stmtCitaClient->execute([$id]);
            $clientRow = $stmtCitaClient->fetch();
            if ($clientRow && !empty($clientRow['cliente_id'])) {
                try {
                    $pdo->exec("ALTER TABLE clientes ADD COLUMN puntos INT DEFAULT 0 AFTER telefono");
                } catch (Exception $ex) {}
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

            // Obtener sucursal de la cita
            $stmtCitaInfo = $pdo->prepare("SELECT sucursal_id FROM citas WHERE id = ?");
            $stmtCitaInfo->execute([$id]);
            $citaInfo = $stmtCitaInfo->fetch();
            $sucursal_id = $citaInfo['sucursal_id'] ?? 0;
            $usuario_id = $_SESSION['user_id']; // Quién completó la cita (vendedor)

            // 2. Procesar inventario y Registrar Venta
            if (!empty($materiales)) {
                $stmtStock = $pdo->prepare("UPDATE inventario SET cantidad = cantidad - ? WHERE id = ?");
                $stmtPrice = $pdo->prepare("SELECT precio FROM inventario WHERE id = ?");
                $stmtVenta = $pdo->prepare("INSERT INTO ventas_productos (cita_id, producto_id, cantidad, precio_unitario, sucursal_id, usuario_id) VALUES (?, ?, ?, ?, ?, ?)");

                for ($i = 0; $i < count($materiales); $i++) {
                    $prodId = intval($materiales[$i]);
                    $cant = floatval($cantidades[$i]);

                    if ($prodId > 0 && $cant > 0) {
                        // Actualizar Stock
                        $stmtStock->execute([$cant, $prodId]);

                        // Obtener precio actual
                        $stmtPrice->execute([$prodId]);
                        $prodInfo = $stmtPrice->fetch();
                        $precioUnitario = $prodInfo['precio'] ?? 0;

                        // Registrar Venta/Consumo
                        if ($sucursal_id > 0) {
                            $stmtVenta->execute([$id, $prodId, $cant, $precioUnitario, $sucursal_id, $usuario_id]);
                        }
                    }
                }
                registrarLog('UPDATE', 'inventario', $id, 'Materiales consumidos/vendidos en cita');
            }

            header('Location: ' . $redirect_url . '?success=' . urlencode('Cita completada y stock actualizado.'));
            exit;

        case 'delete':
            $id = intval($_POST['id'] ?? 0);

            if ($id <= 0) {
                throw new Exception('ID de cita inválido.');
            }

            $sql = "DELETE FROM citas WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$id]);

            header('Location: ../citas.php?success=Cita eliminada exitosamente');
            exit;

        default:
            throw new Exception('Acción no válida.');
    }

} catch (PDOException $e) {
    error_log("Error en citas_action.php: " . $e->getMessage());
    header('Location: ' . $redirect_url . '?error=' . urlencode('Error de base de datos'));
    exit;

} catch (Exception $e) {
    header('Location: ' . $redirect_url . '?error=' . urlencode($e->getMessage()));
    exit;
}
