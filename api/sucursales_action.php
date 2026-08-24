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
            $estado = in_array($_POST['estado'] ?? '', ['activo', 'proximamente', 'inactivo']) ? $_POST['estado'] : 'activo';
            $horario_apertura = !empty($_POST['horario_apertura']) ? $_POST['horario_apertura'] : '09:00:00';
            $horario_cierre = !empty($_POST['horario_cierre']) ? $_POST['horario_cierre'] : '20:00:00';
            $mapa_url = trim($_POST['mapa_url'] ?? '');

            if (empty($nombre)) {
                throw new Exception('El nombre de la sucursal es obligatorio.');
            }

            try {
                $pdo->exec("ALTER TABLE sucursales ADD COLUMN estado VARCHAR(50) DEFAULT 'activo' AFTER telefono");
            } catch (Exception $ex) {}
            try {
                $pdo->exec("ALTER TABLE sucursales ADD COLUMN horario_apertura TIME DEFAULT '09:00:00'");
                $pdo->exec("ALTER TABLE sucursales ADD COLUMN horario_cierre TIME DEFAULT '20:00:00'");
                $pdo->exec("ALTER TABLE sucursales ADD COLUMN mapa_url TEXT NULL");
            } catch (Exception $ex2) {}

            $sql = "INSERT INTO sucursales (nombre, direccion, telefono, estado, horario_apertura, horario_cierre, mapa_url) VALUES (?, ?, ?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$nombre, $direccion, $telefono, $estado, $horario_apertura, $horario_cierre, $mapa_url]);

            $newSucId = $pdo->lastInsertId();
            registrarLog('CREAR', 'sucursales', $newSucId, "Nueva sucursal '$nombre' creada (Dirección: '$direccion', Teléfono: '$telefono', Estado: '$estado')");

            header('Location: ../sucursales.php?success=Sucursal creada exitosamente');
            exit;

        case 'update':
            $id = intval($_POST['id'] ?? 0);
            $nombre = trim($_POST['nombre'] ?? '');
            $direccion = trim($_POST['direccion'] ?? '');
            $telefono = trim($_POST['telefono'] ?? '');
            $estado = in_array($_POST['estado'] ?? '', ['activo', 'proximamente', 'inactivo']) ? $_POST['estado'] : 'activo';
            $horario_apertura = !empty($_POST['horario_apertura']) ? $_POST['horario_apertura'] : '09:00:00';
            $horario_cierre = !empty($_POST['horario_cierre']) ? $_POST['horario_cierre'] : '20:00:00';
            $mapa_url = trim($_POST['mapa_url'] ?? '');

            if ($id <= 0) {
                throw new Exception('ID de sucursal inválido.');
            }

            if (empty($nombre)) {
                throw new Exception('El nombre de la sucursal es obligatorio.');
            }

            try {
                $pdo->exec("ALTER TABLE sucursales ADD COLUMN estado VARCHAR(50) DEFAULT 'activo' AFTER telefono");
            } catch (Exception $ex) {}

            $oldS = query("SELECT * FROM sucursales WHERE id = ?", [$id]);
            $old = !empty($oldS) ? $oldS[0] : [];

            $cambios = [];
            if (!empty($old)) {
                if (($old['nombre'] ?? '') !== $nombre) {
                    $cambios[] = "Nombre: '" . ($old['nombre'] ?? '') . "' ➔ '$nombre'";
                }
                if (($old['direccion'] ?? '') !== $direccion) {
                    $cambios[] = "Dirección: '" . ($old['direccion'] ?? '') . "' ➔ '$direccion'";
                }
                if (($old['telefono'] ?? '') !== $telefono) {
                    $cambios[] = "Teléfono: '" . ($old['telefono'] ?? '') . "' ➔ '$telefono'";
                }
                if (($old['estado'] ?? '') !== $estado) {
                    $cambios[] = "Estado: '" . ($old['estado'] ?? '') . "' ➔ '$estado'";
                }
            }

            $sql = "UPDATE sucursales SET nombre = ?, direccion = ?, telefono = ?, estado = ?, horario_apertura = ?, horario_cierre = ?, mapa_url = ? WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$nombre, $direccion, $telefono, $estado, $horario_apertura, $horario_cierre, $mapa_url, $id]);

            $descCambios = !empty($cambios) ? implode('; ', $cambios) : 'Guardado sin modificaciones de texto';
            registrarLog('EDITAR', 'sucursales', $id, "Sucursal '$nombre' (#$id) actualizada. [$descCambios]");

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

            $oldS = query("SELECT nombre FROM sucursales WHERE id = ?", [$id]);
            $sucNom = !empty($oldS) ? $oldS[0]['nombre'] : "ID #$id";

            $sql = "DELETE FROM sucursales WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$id]);

            registrarLog('ELIMINAR', 'sucursales', $id, "Sucursal '$sucNom' (#$id) fue eliminada del sistema");

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
