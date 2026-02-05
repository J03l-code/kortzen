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
            $nombre = trim($_POST['cliente_nombre'] ?? '');
            $comentario = trim($_POST['comentario'] ?? '');
            $calificacion = intval($_POST['calificacion'] ?? 5);
            $fecha = $_POST['fecha'] ?? date('Y-m-d');
            $visible = intval($_POST['visible'] ?? 1);

            if (empty($nombre) || empty($comentario)) {
                throw new Exception('Nombre y comentario son obligatorios.');
            }

            $sql = "INSERT INTO resenas (cliente_nombre, comentario, calificacion, fecha, visible) VALUES (?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$nombre, $comentario, $calificacion, $fecha, $visible]);

            header('Location: ../resenas.php?success=Reseña agregada exitosamente');
            exit;

        case 'update':
            $id = intval($_POST['id'] ?? 0);
            $nombre = trim($_POST['cliente_nombre'] ?? '');
            $comentario = trim($_POST['comentario'] ?? '');
            $calificacion = intval($_POST['calificacion'] ?? 5);
            $fecha = $_POST['fecha'] ?? date('Y-m-d');
            $visible = intval($_POST['visible'] ?? 1);

            if ($id <= 0)
                throw new Exception('ID inválido');
            if (empty($nombre) || empty($comentario))
                throw new Exception('Campos obligatorios');

            $sql = "UPDATE resenas SET cliente_nombre = ?, comentario = ?, calificacion = ?, fecha = ?, visible = ? WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$nombre, $comentario, $calificacion, $fecha, $visible, $id]);

            header('Location: ../resenas.php?success=Reseña actualizada exitosamente');
            exit;

        case 'delete':
            $id = intval($_POST['id'] ?? 0);
            if ($id <= 0)
                throw new Exception('ID inválido');

            $sql = "DELETE FROM resenas WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$id]);

            header('Location: ../resenas.php?success=Reseña eliminada exitosamente');
            exit;

        case 'toggle_visibility':
            // Can be implemented via AJAX if needed, but for now full post is fine
            break;

        default:
            throw new Exception('Acción no válida');
    }

} catch (Exception $e) {
    header('Location: ../resenas.php?error=' . urlencode($e->getMessage()));
    exit;
}
