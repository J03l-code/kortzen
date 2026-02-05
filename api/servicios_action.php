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
            $descripcion = trim($_POST['descripcion'] ?? '');
            $precio = floatval($_POST['precio'] ?? 0);
            $duracion_minutos = intval($_POST['duracion_minutos'] ?? 30);
            $categoria = trim($_POST['categoria'] ?? 'General');
            $foto_url = trim($_POST['foto_url'] ?? '');
            $activo = intval($_POST['activo'] ?? 1);
            $destacado = isset($_POST['destacado']) ? 1 : 0;
            $sucursales = $_POST['sucursales'] ?? [];

            if (empty($nombre)) {
                throw new Exception('El nombre del servicio es obligatorio.');
            }

            if ($precio < 0) {
                throw new Exception('El precio debe ser mayor o igual a 0.');
            }

            $pdo->beginTransaction();

            try {
                $sql = "INSERT INTO servicios (nombre, descripcion, precio, duracion_minutos, categoria, foto_url, activo, destacado) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$nombre, $descripcion, $precio, $duracion_minutos, $categoria, $foto_url, $activo, $destacado]);
                $servicioId = $pdo->lastInsertId();

                // Save branch associations
                if (!empty($sucursales)) {
                    $insertSql = "INSERT INTO servicios_sucursales (servicio_id, sucursal_id) VALUES (?, ?)";
                    $insertStmt = $pdo->prepare($insertSql);
                    foreach ($sucursales as $sucursalId) {
                        $insertStmt->execute([$servicioId, $sucursalId]);
                    }
                }

                $pdo->commit();
                header('Location: ../servicios.php?success=Servicio creado exitosamente');
            } catch (Exception $e) {
                $pdo->rollBack();
                throw $e;
            }
            exit;

        case 'update':
            $id = intval($_POST['id'] ?? 0);
            $nombre = trim($_POST['nombre'] ?? '');
            $descripcion = trim($_POST['descripcion'] ?? '');
            $precio = floatval($_POST['precio'] ?? 0);
            $duracion_minutos = intval($_POST['duracion_minutos'] ?? 30);
            $categoria = trim($_POST['categoria'] ?? 'General');
            $foto_url = trim($_POST['foto_url'] ?? '');
            $activo = intval($_POST['activo'] ?? 1);
            $destacado = isset($_POST['destacado']) ? 1 : 0;
            $sucursales = $_POST['sucursales'] ?? [];

            if ($id <= 0) {
                throw new Exception('ID de servicio inválido.');
            }

            if (empty($nombre)) {
                throw new Exception('El nombre del servicio es obligatorio.');
            }

            $pdo->beginTransaction();

            try {
                $sql = "UPDATE servicios 
                        SET nombre = ?, descripcion = ?, precio = ?, duracion_minutos = ?, categoria = ?, foto_url = ?, activo = ?, destacado = ? 
                        WHERE id = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$nombre, $descripcion, $precio, $duracion_minutos, $categoria, $foto_url, $activo, $destacado, $id]);

                // Update branch associations
                // First delete existing
                $deleteStmt = $pdo->prepare("DELETE FROM servicios_sucursales WHERE servicio_id = ?");
                $deleteStmt->execute([$id]);

                // Then insert new ones
                if (!empty($sucursales)) {
                    $insertSql = "INSERT INTO servicios_sucursales (servicio_id, sucursal_id) VALUES (?, ?)";
                    $insertStmt = $pdo->prepare($insertSql);
                    foreach ($sucursales as $sucursalId) {
                        $insertStmt->execute([$id, $sucursalId]);
                    }
                }

                $pdo->commit();
                header('Location: ../servicios.php?success=Servicio actualizado exitosamente');
            } catch (Exception $e) {
                $pdo->rollBack();
                throw $e;
            }
            exit;

        case 'delete':
            $id = intval($_POST['id'] ?? 0);

            if ($id <= 0) {
                throw new Exception('ID de servicio inválido.');
            }

            // Verificar si hay citas asociadas
            $check = query("SELECT COUNT(*) as count FROM citas WHERE servicio_id = ?", [$id]);
            if ($check[0]['count'] > 0) {
                throw new Exception('No se puede eliminar el servicio porque tiene citas asociadas.');
            }

            $sql = "DELETE FROM servicios WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$id]);

            header('Location: ../servicios.php?success=Servicio eliminado exitosamente');
            exit;

        default:
            throw new Exception('Acción no válida.');
    }

} catch (PDOException $e) {
    error_log("Error en servicios_action.php: " . $e->getMessage());
    header('Location: ../servicios.php?error=' . urlencode('Error de base de datos'));
    exit;

} catch (Exception $e) {
    header('Location: ../servicios.php?error=' . urlencode($e->getMessage()));
    exit;
}
