<?php
require_once '../config.php';

if (!isLoggedIn()) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

$action = $_POST['action'] ?? '';

try {
    $pdo = getConnection();

    switch ($action) {
        case 'create':
            $nombre = trim($_POST['nombre'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';
            $rol = $_POST['rol'] ?? 'barbero';
            $sucursal_id = !empty($_POST['sucursal_id']) ? intval($_POST['sucursal_id']) : null;

            $biografia = trim($_POST['biografia'] ?? '');
            $especialidades = trim($_POST['especialidades'] ?? '');
            $foto_url = trim($_POST['foto_url'] ?? '');
            $telefono = trim($_POST['telefono'] ?? '');
            $comision_porcentaje = floatval($_POST['comision_porcentaje'] ?? 50.00);
            $comision_fin_semana = floatval($_POST['comision_fin_semana'] ?? 50.00);

            // Validaciones
            if (empty($nombre) || empty($email) || empty($password)) {
                throw new Exception('Todos los campos son obligatorios.');
            }

            if (!isValidEmail($email)) {
                throw new Exception('El email no es válido.');
            }

            if (strlen($password) < 6) {
                throw new Exception('La contraseña debe tener al menos 6 caracteres.');
            }

            // Verificar que el email no exista
            $check = query("SELECT COUNT(*) as count FROM usuarios WHERE email = ?", [$email]);
            if ($check[0]['count'] > 0) {
                throw new Exception('El email ya está registrado.');
            }

            // Hash de contraseña con BCRYPT
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);

            $sql = "INSERT INTO usuarios (nombre, email, password, rol, sucursal_id, biografia, especialidades, foto_url, telefono, comision_porcentaje, comision_fin_semana) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$nombre, $email, $passwordHash, $rol, $sucursal_id, $biografia, $especialidades, $foto_url, $telefono, $comision_porcentaje, $comision_fin_semana]);

            header('Location: ../usuarios.php?success=Usuario creado exitosamente');
            exit;

        case 'update':
            $id = intval($_POST['id'] ?? 0);
            $nombre = trim($_POST['nombre'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';
            $rol = $_POST['rol'] ?? 'barbero';
            $sucursal_id = !empty($_POST['sucursal_id']) ? intval($_POST['sucursal_id']) : null;
            $biografia = trim($_POST['biografia'] ?? '');
            $especialidades = trim($_POST['especialidades'] ?? '');
            $foto_url = trim($_POST['foto_url'] ?? '');
            $telefono = trim($_POST['telefono'] ?? '');
            $comision_porcentaje = floatval($_POST['comision_porcentaje'] ?? 50.00);
            $comision_fin_semana = floatval($_POST['comision_fin_semana'] ?? 50.00);

            if ($id <= 0) {
                throw new Exception('ID de usuario inválido.');
            }

            if (empty($nombre) || empty($email)) {
                throw new Exception('El nombre y email son obligatorios.');
            }

            if (!isValidEmail($email)) {
                throw new Exception('El email no es válido.');
            }

            // Verificar que el email no esté en uso por otro usuario
            $check = query("SELECT COUNT(*) as count FROM usuarios WHERE email = ? AND id != ?", [$email, $id]);
            if ($check[0]['count'] > 0) {
                throw new Exception('El email ya está registrado por otro usuario.');
            }

            // Si se proporcionó contraseña, actualizarla
            if (!empty($password)) {
                if (strlen($password) < 6) {
                    throw new Exception('La contraseña debe tener al menos 6 caracteres.');
                }
                $passwordHash = password_hash($password, PASSWORD_DEFAULT);
                $sql = "UPDATE usuarios SET nombre = ?, email = ?, password = ?, rol = ?, sucursal_id = ?, biografia = ?, especialidades = ?, foto_url = ?, telefono = ?, comision_porcentaje = ?, comision_fin_semana = ? WHERE id = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$nombre, $email, $passwordHash, $rol, $sucursal_id, $biografia, $especialidades, $foto_url, $telefono, $comision_porcentaje, $comision_fin_semana, $id]);
            } else {
                // No actualizar contraseña
                $sql = "UPDATE usuarios SET nombre = ?, email = ?, rol = ?, sucursal_id = ?, biografia = ?, especialidades = ?, foto_url = ?, telefono = ?, comision_porcentaje = ?, comision_fin_semana = ? WHERE id = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$nombre, $email, $rol, $sucursal_id, $biografia, $especialidades, $foto_url, $telefono, $comision_porcentaje, $comision_fin_semana, $id]);
            }

            header('Location: ../usuarios.php?success=Usuario actualizado exitosamente');
            exit;

        case 'delete':
            $id = intval($_POST['id'] ?? 0);

            if ($id <= 0) {
                throw new Exception('ID de usuario inválido.');
            }

            // No permitir eliminar al usuario actual
            if ($id == $_SESSION['user_id']) {
                throw new Exception('No puedes eliminar tu propia cuenta.');
            }

            // Primero desvincular las citas del barbero (poner barbero_id en NULL)
            $sqlCitas = "UPDATE citas SET barbero_id = NULL WHERE barbero_id = ?";
            $stmtCitas = $pdo->prepare($sqlCitas);
            $stmtCitas->execute([$id]);

            // Eliminar horarios del barbero
            $sqlHorarios = "DELETE FROM horarios_barberos WHERE barbero_id = ?";
            $stmtHorarios = $pdo->prepare($sqlHorarios);
            $stmtHorarios->execute([$id]);

            // Eliminar días bloqueados del barbero
            $sqlBloqueos = "DELETE FROM dias_bloqueados WHERE barbero_id = ?";
            $stmtBloqueos = $pdo->prepare($sqlBloqueos);
            $stmtBloqueos->execute([$id]);

            // Ahora eliminar el usuario
            $sql = "DELETE FROM usuarios WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$id]);

            registrarLog('DELETE', 'usuarios', $id, 'Usuario eliminado');
            header('Location: ../usuarios.php?success=Usuario eliminado exitosamente');
            exit;

        default:
            throw new Exception('Acción no válida.');
    }

} catch (PDOException $e) {
    error_log("Error en usuarios_action.php: " . $e->getMessage());
    header('Location: ../usuarios.php?error=' . urlencode('Error de base de datos: ' . $e->getMessage()));
    exit;

} catch (Exception $e) {
    header('Location: ../usuarios.php?error=' . urlencode($e->getMessage()));
    exit;
}
