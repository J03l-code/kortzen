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

            header('Location: ../clientes.php?success=Cliente actualizado exitosamente');
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
