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
            $producto = trim($_POST['producto'] ?? '');
            $cantidad = intval($_POST['cantidad'] ?? 0);
            $precio = floatval($_POST['precio'] ?? 0);
            $stock_minimo = intval($_POST['stock_minimo'] ?? 5); // Default 5
            $sucursal_id = intval($_POST['sucursal_id'] ?? 0);

            // Validaciones
            if (empty($producto)) {
                throw new Exception('El nombre del producto es obligatorio.');
            }

            if ($sucursal_id <= 0) {
                throw new Exception('Debes seleccionar una sucursal.');
            }

            if ($cantidad < 0) {
                throw new Exception('La cantidad no puede ser negativa.');
            }

            if ($precio < 0) {
                throw new Exception('El precio no puede ser negativo.');
            }

            if ($stock_minimo < 0) {
                throw new Exception('El stock mínimo no puede ser negativo.');
            }

            // Verificar que la sucursal existe
            $check = query("SELECT COUNT(*) as count FROM sucursales WHERE id = ?", [$sucursal_id]);
            if ($check[0]['count'] == 0) {
                throw new Exception('La sucursal seleccionada no existe.');
            }

            $sql = "INSERT INTO inventario (producto, cantidad, precio, stock_minimo, sucursal_id) VALUES (?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$producto, $cantidad, $precio, $stock_minimo, $sucursal_id]);

            header('Location: ../inventario.php?success=Producto agregado exitosamente');
            exit;

        case 'update':
            $id = intval($_POST['id'] ?? 0);
            $producto = trim($_POST['producto'] ?? '');
            $cantidad = intval($_POST['cantidad'] ?? 0);
            $precio = floatval($_POST['precio'] ?? 0);
            $stock_minimo = intval($_POST['stock_minimo'] ?? 5);
            $sucursal_id = intval($_POST['sucursal_id'] ?? 0);

            if ($id <= 0) {
                throw new Exception('ID de producto inválido.');
            }

            if (empty($producto)) {
                throw new Exception('El nombre del producto es obligatorio.');
            }

            if ($sucursal_id <= 0) {
                throw new Exception('Debes seleccionar una sucursal.');
            }

            if ($cantidad < 0) {
                throw new Exception('La cantidad no puede ser negativa.');
            }

            if ($precio < 0) {
                throw new Exception('El precio no puede ser negativo.');
            }

            if ($stock_minimo < 0) {
                throw new Exception('El stock mínimo no puede ser negativo.');
            }

            // Verificar que la sucursal existe
            $check = query("SELECT COUNT(*) as count FROM sucursales WHERE id = ?", [$sucursal_id]);
            if ($check[0]['count'] == 0) {
                throw new Exception('La sucursal seleccionada no existe.');
            }

            $sql = "UPDATE inventario SET producto = ?, cantidad = ?, precio = ?, stock_minimo = ?, sucursal_id = ? WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$producto, $cantidad, $precio, $stock_minimo, $sucursal_id, $id]);

            header('Location: ../inventario.php?success=Producto actualizado exitosamente');
            exit;

        case 'delete':
            $id = intval($_POST['id'] ?? 0);

            if ($id <= 0) {
                throw new Exception('ID de producto inválido.');
            }

            // Verificar que el producto existe
            $check = query("SELECT COUNT(*) as count FROM inventario WHERE id = ?", [$id]);
            if ($check[0]['count'] == 0) {
                throw new Exception('El producto no existe.');
            }

            $sql = "DELETE FROM inventario WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$id]);

            header('Location: ../inventario.php?success=Producto eliminado exitosamente');
            exit;

        default:
            throw new Exception('Acción no válida.');
    }

} catch (PDOException $e) {
    error_log("Error en inventario_action.php: " . $e->getMessage());
    header('Location: ../inventario.php?error=' . urlencode('Error de base de datos: ' . $e->getMessage()));
    exit;

} catch (Exception $e) {
    header('Location: ../inventario.php?error=' . urlencode($e->getMessage()));
    exit;
}
