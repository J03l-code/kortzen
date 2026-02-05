<?php
require_once '../config.php';

if (!isLoggedIn()) {
    header('Location: ../login.php');
    exit;
}

$action = $_POST['action'] ?? '';

try {
    $pdo = getConnection();

    $redirect_url = '../citas.php'; // Default
    if (isset($_POST['redirect_source']) && $_POST['redirect_source'] === 'dashboard') {
        $redirect_url = '../dashboard.php';
    }

    switch ($action) {
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

            // 2. Procesar inventario
            // Logs de consumo se guardan en una tabla nueva 'consumos_citas' o simplemente actualizamos stock
            // Para simplicidad por ahora: Update directo a stock + Log general
            if (!empty($materiales)) {
                $stmtStock = $pdo->prepare("UPDATE inventario SET cantidad = cantidad - ? WHERE id = ?");

                for ($i = 0; $i < count($materiales); $i++) {
                    $prodId = intval($materiales[$i]);
                    $cant = floatval($cantidades[$i]);

                    if ($prodId > 0 && $cant > 0) {
                        $stmtStock->execute([$cant, $prodId]);
                        // Opcional: Registrar consumo (si existiera tabla)
                        // registrarConsumo($id, $prodId, $cant);
                    }
                }
                registrarLog('UPDATE', 'inventario', $id, 'Materiales consumidos al completar cita');
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
