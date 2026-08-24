<?php
require_once '../config.php';

if (!isLoggedIn()) {
    header('Location: ../login.php');
    exit;
}

$action = $_POST['action'] ?? '';

try {
    $pdo = getConnection();

    switch ($action) {
        case 'create':
            $nombre = trim($_POST['nombre'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $telefono = trim($_POST['telefono'] ?? '');
            $fecha_nacimiento = $_POST['fecha_nacimiento'] ?? null;
            $notas = trim($_POST['notas'] ?? '');

            if (empty($nombre)) {
                throw new Exception('El nombre es obligatorio.');
            }

            // Validar email si se proporcionó
            if ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new Exception('El email no es válido.');
            }

            // Verificar si el email ya existe
            if ($email) {
                $check = query("SELECT COUNT(*) as count FROM clientes WHERE email = ?", [$email]);
                if ($check[0]['count'] > 0) {
                    throw new Exception('El email ya está registrado.');
                }
            }

            $sql = "INSERT INTO clientes (nombre, email, telefono, fecha_nacimiento, notas) 
                    VALUES (?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $nombre,
                $email ?: null,
                $telefono ?: null,
                $fecha_nacimiento ?: null,
                $notas
            ]);

            $newId = $pdo->lastInsertId();
            registrarLog('CREAR', 'clientes', $newId, "Cliente '$nombre' registrado en el sistema");

            header('Location: ../clientes.php?success=Cliente creado exitosamente');
            exit;

        case 'update':
            $id = intval($_POST['id'] ?? 0);
            $nombre = trim($_POST['nombre'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $telefono = trim($_POST['telefono'] ?? '');
            $fecha_nacimiento = $_POST['fecha_nacimiento'] ?? null;
            $notas = trim($_POST['notas'] ?? '');

            if ($id <= 0) {
                throw new Exception('ID de cliente inválido.');
            }

            if (empty($nombre)) {
                throw new Exception('El nombre es obligatorio.');
            }

            // Validar email si se proporcionó
            if ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new Exception('El email no es válido.');
            }

            // Verificar si el email ya existe en otro cliente
            if ($email) {
                $check = query("SELECT COUNT(*) as count FROM clientes WHERE email = ? AND id != ?", [$email, $id]);
                if ($check[0]['count'] > 0) {
                    throw new Exception('El email ya está registrado por otro cliente.');
                }
            }

            $sql = "UPDATE clientes 
                    SET nombre = ?, email = ?, telefono = ?, fecha_nacimiento = ?, notas = ? 
                    WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $nombre,
                $email ?: null,
                $telefono ?: null,
                $fecha_nacimiento ?: null,
                $notas,
                $id
            ]);

            registrarLog('EDITAR', 'clientes', $id, "Datos del cliente '$nombre' actualizados");
            header('Location: ../clientes.php?success=Cliente actualizado exitosamente');
            exit;

        case 'update_puntos':
            $id = intval($_POST['id'] ?? 0);
            $puntos = intval($_POST['puntos'] ?? 0);

            if ($id <= 0) {
                throw new Exception('ID de cliente inválido.');
            }

            try {
                $pdo->exec("ALTER TABLE clientes ADD COLUMN puntos INT DEFAULT 0 AFTER telefono");
            } catch (Exception $ex) {}

            $stmt = $pdo->prepare("UPDATE clientes SET puntos = ? WHERE id = ?");
            $stmt->execute([$puntos, $id]);

            registrarLog('PUNTOS', 'clientes', $id, "Puntos KORTZEN del cliente #$id actualizados a $puntos pts");

            $redirect = !empty($_POST['redirect_to']) ? $_POST['redirect_to'] : '../clientes.php';
            if (preg_match('/^(https?:|\/\/)/i', $redirect)) {
                $redirect = '../clientes.php';
            }
            if (strpos($redirect, '/') !== 0 && strpos($redirect, '../') !== 0) {
                $redirect = '../' . ltrim($redirect, '/');
            }

            header('Location: ' . $redirect . (strpos($redirect, '?') !== false ? '&' : '?') . 'success=' . urlencode('Puntos KORTZEN actualizados a ' . number_format($puntos) . ' pts'));
            exit;

        case 'guardar_notas_barbero':
            $clienteId = intval($_POST['cliente_id'] ?? 0);
            $notasBarbero = trim($_POST['notas_barbero'] ?? '');

            if ($clienteId <= 0) {
                throw new Exception('ID de cliente inválido.');
            }

            try {
                $pdo->exec("ALTER TABLE clientes ADD COLUMN notas_barbero TEXT NULL AFTER notas");
            } catch (Exception $exN) {}

            $stmtN = $pdo->prepare("UPDATE clientes SET notas_barbero = ? WHERE id = ?");
            $stmtN->execute([$notasBarbero, $clienteId]);

            registrarLog('NOTAS', 'clientes', $clienteId, "Notas del barbero actualizadas para el cliente #$clienteId");

            header('Location: ../barber-dashboard.php?success=' . urlencode('Notas del cliente guardadas con éxito.'));
            exit;

        case 'delete':
            $id = intval($_POST['id'] ?? 0);

            if ($id <= 0) {
                throw new Exception('ID de cliente inválido.');
            }

            // Las citas se eliminarán automáticamente por CASCADE
            $sql = "DELETE FROM clientes WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$id]);

            registrarLog('ELIMINAR', 'clientes', $id, "Cliente #$id eliminado del sistema");

            header('Location: ../clientes.php?success=Cliente eliminado exitosamente');
            exit;

        default:
            throw new Exception('Acción no válida.');
    }

} catch (PDOException $e) {
    error_log("Error en clientes_action.php: " . $e->getMessage());
    header('Location: ../clientes.php?error=' . urlencode('Error de base de datos'));
    exit;

} catch (Exception $e) {
    header('Location: ../clientes.php?error=' . urlencode($e->getMessage()));
    exit;
}
