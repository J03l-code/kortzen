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
            $nombre = mb_substr(trim($_POST['nombre'] ?? ''), 0, 100);
            $email = mb_substr(trim($_POST['email'] ?? ''), 0, 100);
            $telefono = mb_substr(trim($_POST['telefono'] ?? ''), 0, 20);
            $fecha_nacimiento = $_POST['fecha_nacimiento'] ?? null;
            $notas = mb_substr(trim($_POST['notas'] ?? ''), 0, 1000);

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
            $nombre = mb_substr(trim($_POST['nombre'] ?? ''), 0, 100);
            $email = mb_substr(trim($_POST['email'] ?? ''), 0, 100);
            $telefono = mb_substr(trim($_POST['telefono'] ?? ''), 0, 20);
            $fecha_nacimiento = $_POST['fecha_nacimiento'] ?? null;
            $notas = mb_substr(trim($_POST['notas'] ?? ''), 0, 1000);

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

            // Obtener datos anteriores para registrar el cambio exacto
            $oldC = query("SELECT * FROM clientes WHERE id = ?", [$id]);
            $old = !empty($oldC) ? $oldC[0] : [];

            $cambios = [];
            if (!empty($old)) {
                if (($old['nombre'] ?? '') !== $nombre) {
                    $cambios[] = "Nombre: '" . ($old['nombre'] ?? '') . "' ➔ '$nombre'";
                }
                if (($old['email'] ?? '') !== $email) {
                    $oldEm = $old['email'] ?: 'Sin email';
                    $newEm = $email ?: 'Sin email';
                    $cambios[] = "Email: '$oldEm' ➔ '$newEm'";
                }
                if (($old['telefono'] ?? '') !== $telefono) {
                    $oldTel = $old['telefono'] ?: 'Sin teléfono';
                    $newTel = $telefono ?: 'Sin teléfono';
                    $cambios[] = "Teléfono: '$oldTel' ➔ '$newTel'";
                }
                if (($old['fecha_nacimiento'] ?? '') !== $fecha_nacimiento) {
                    $oldF = $old['fecha_nacimiento'] ?: 'Sin fecha';
                    $newF = $fecha_nacimiento ?: 'Sin fecha';
                    $cambios[] = "F. Nacimiento: '$oldF' ➔ '$newF'";
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

            $descCambios = !empty($cambios) ? implode('; ', $cambios) : 'Guardado sin modificaciones de texto';
            registrarLog('EDITAR', 'clientes', $id, "Cliente '$nombre' (#$id) actualizado. [$descCambios]");
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

            $stmtCInfo = $pdo->prepare("SELECT nombre, puntos FROM clientes WHERE id = ?");
            $stmtCInfo->execute([$id]);
            $cInfo = $stmtCInfo->fetch(PDO::FETCH_ASSOC);
            $clienteNombre = $cInfo ? $cInfo['nombre'] : "ID #$id";
            $puntosAnteriores = intval($cInfo['puntos'] ?? 0);
            $diferencia = $puntos - $puntosAnteriores;
            $diffStr = ($diferencia >= 0 ? "+$diferencia" : "$diferencia") . " pts";

            $stmt = $pdo->prepare("UPDATE clientes SET puntos = ? WHERE id = ?");
            $stmt->execute([$puntos, $id]);

            registrarLog('PUNTOS', 'clientes', $id, "Puntos KORTZEN del cliente '$clienteNombre' (#$id) modificados de $puntosAnteriores pts a $puntos pts ($diffStr)");

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

            $stmtCName = $pdo->prepare("SELECT nombre FROM clientes WHERE id = ?");
            $stmtCName->execute([$clienteId]);
            $clienteNombre = $stmtCName->fetchColumn() ?: "ID #$clienteId";

            registrarLog('NOTAS', 'clientes', $clienteId, "Notas del barbero guardadas para el cliente '$clienteNombre'");

            header('Location: ../barber-dashboard.php?success=' . urlencode('Notas del cliente guardadas con éxito.'));
            exit;

        case 'delete':
            $id = intval($_POST['id'] ?? 0);

            if ($id <= 0) {
                throw new Exception('ID de cliente inválido.');
            }

            $stmtCName = $pdo->prepare("SELECT nombre FROM clientes WHERE id = ?");
            $stmtCName->execute([$id]);
            $clienteNombre = $stmtCName->fetchColumn() ?: "ID #$id";

            // Las citas se eliminarán automáticamente por CASCADE
            $sql = "DELETE FROM clientes WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$id]);

            registrarLog('ELIMINAR', 'clientes', $id, "Cliente '$clienteNombre' fue eliminado del sistema");

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
