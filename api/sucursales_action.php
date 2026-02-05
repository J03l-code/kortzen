<?php
require_once '../config.php';

// Asegurar que el usuario esté logueado
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
            $direccion = trim($_POST['direccion'] ?? '');
            $telefono = trim($_POST['telefono'] ?? '');

            if (empty($nombre)) {
                throw new Exception('El nombre de la sucursal es obligatorio.');
            }

            $sql = "INSERT INTO sucursales (nombre, direccion, telefono) VALUES (?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$nombre, $direccion, $telefono]);

            header('Location: ../sucursales.php?success=Sucursal creada exitosamente');
            exit;

        case 'update':
            $id = intval($_POST['id'] ?? 0);
            $nombre = trim($_POST['nombre'] ?? '');
            $direccion = trim($_POST['direccion'] ?? '');
            $telefono = trim($_POST['telefono'] ?? '');

            if ($id <= 0) {
                throw new Exception('ID de sucursal inválido.');
            }

            if (empty($nombre)) {
                throw new Exception('El nombre de la sucursal es obligatorio.');
            }

            $sql = "UPDATE sucursales SET nombre = ?, direccion = ?, telefono = ? WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$nombre, $direccion, $telefono, $id]);

            header('Location: ../sucursales.php?success=Sucursal actualizada exitosamente');
            exit;

        case 'delete':
            $id = intval($_POST['id'] ?? 0);

            if ($id <= 0) {
                throw new Exception('ID de sucursal inválido.');
            }

            // Verificar que la sucursal existe
            $check = query("SELECT COUNT(*) as count FROM sucursales WHERE id = ?", [$id]);
            if ($check[0]['count'] == 0) {
                throw new Exception('La sucursal no existe.');
            }

            // El ON DELETE CASCADE se encargará de eliminar el inventario automáticamente
            $sql = "DELETE FROM sucursales WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$id]);

            header('Location: ../sucursales.php?success=Sucursal eliminada exitosamente (incluido su inventario)');
            exit;

        default:
            throw new Exception('Acción no válida.');
    }

} catch (PDOException $e) {
    error_log("Error en sucursales_action.php: " . $e->getMessage());
    header('Location: ../sucursales.php?error=' . urlencode('Error de base de datos: ' . $e->getMessage()));
    exit;

} catch (Exception $e) {
    header('Location: ../sucursales.php?error=' . urlencode($e->getMessage()));
    exit;
}
