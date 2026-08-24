<?php
/**
 * KORTZEN - API Gestión de Stock e Inventario por Barbero
 */
require_once '../config.php';

if (!isLoggedIn()) {
    header('Location: ../login.php');
    exit;
}

$currentUser = getCurrentUser();
if ($currentUser['rol'] !== 'admin' && $currentUser['rol'] !== 'admin_local') {
    header('Location: ../dashboard.php?error=' . urlencode('Solo los administradores pueden gestionar el stock del barbero.'));
    exit;
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$barbero_id = intval($_POST['barbero_id'] ?? $_GET['barbero_id'] ?? 0);

if ($barbero_id <= 0) {
    header('Location: ../usuarios.php?error=' . urlencode('ID de barbero no válido.'));
    exit;
}

try {
    $pdo = getConnection();

    switch ($action) {
        case 'guardar_stock':
        case 'agregar_stock':
            $producto = trim($_POST['producto'] ?? '');
            $cantidad = floatval($_POST['cantidad'] ?? 0);
            $unidad = trim($_POST['unidad'] ?? 'unidades');
            $precio = floatval($_POST['precio'] ?? 0.00);
            $descripcion = trim($_POST['descripcion'] ?? '');
            $stock_id = intval($_POST['stock_id'] ?? 0);

            if (empty($producto)) {
                throw new Exception('El nombre del producto es obligatorio.');
            }

            if ($stock_id > 0) {
                // Actualizar item existente
                $stmt = $pdo->prepare("
                    UPDATE inventario_barbero 
                    SET producto = ?, cantidad = ?, unidad = ?, precio = ?, descripcion = ? 
                    WHERE id = ? AND barbero_id = ?
                ");
                $stmt->execute([$producto, $cantidad, $unidad, $precio, $descripcion, $stock_id, $barbero_id]);
                $msg = 'Stock del barbero actualizado exitosamente.';
            } else {
                // Verificar si ya existe para este barbero
                $stmtCheck = $pdo->prepare("SELECT id, cantidad FROM inventario_barbero WHERE barbero_id = ? AND LOWER(producto) = LOWER(?)");
                $stmtCheck->execute([$barbero_id, $producto]);
                $existing = $stmtCheck->fetch(PDO::FETCH_ASSOC);

                if ($existing) {
                    // Sumar cantidad al producto existente
                    $nuevaCant = floatval($existing['cantidad']) + $cantidad;
                    $stmtUpd = $pdo->prepare("UPDATE inventario_barbero SET cantidad = ?, unidad = ?, precio = ?, descripcion = ? WHERE id = ?");
                    $stmtUpd->execute([$nuevaCant, $unidad, $precio, $descripcion, $existing['id']]);
                    $msg = 'Stock existente incrementado. Nueva cantidad: ' . $nuevaCant . ' ' . $unidad;
                } else {
                    // Obtener sucursal_id del barbero
                    $stmtU = $pdo->prepare("SELECT sucursal_id FROM usuarios WHERE id = ?");
                    $stmtU->execute([$barbero_id]);
                    $userRow = $stmtU->fetch();
                    $sucursal_id = $userRow['sucursal_id'] ?? null;

                    $stmtIns = $pdo->prepare("
                        INSERT INTO inventario_barbero (barbero_id, sucursal_id, producto, cantidad, unidad, precio, descripcion) 
                        VALUES (?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmtIns->execute([$barbero_id, $sucursal_id, $producto, $cantidad, $unidad, $precio, $descripcion]);
                    $msg = 'Nuevo producto asignado al stock del barbero exitosamente.';
                }
            }

            header('Location: ../barbero_detalle.php?id=' . $barbero_id . '&success=' . urlencode($msg));
            exit;

        case 'eliminar_stock':
            $stock_id = intval($_POST['stock_id'] ?? $_GET['stock_id'] ?? 0);
            if ($stock_id <= 0) {
                throw new Exception('Item de stock no válido.');
            }

            $stmtDel = $pdo->prepare("DELETE FROM inventario_barbero WHERE id = ? AND barbero_id = ?");
            $stmtDel->execute([$stock_id, $barbero_id]);

            header('Location: ../barbero_detalle.php?id=' . $barbero_id . '&success=' . urlencode('Producto retirado del stock del barbero.'));
            exit;

        default:
            throw new Exception('Acción no válida.');
    }

} catch (Exception $e) {
    header('Location: ../barbero_detalle.php?id=' . $barbero_id . '&error=' . urlencode($e->getMessage()));
    exit;
}
