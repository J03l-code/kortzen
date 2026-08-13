<?php
/**
 * KORTZEN - API Acción de Inventario y Ventas para Barberos
 */

session_start();
require_once '../config.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Sesión no válida.']);
    exit;
}

$user = getCurrentUser();
$usuarioId = $user['id'];
$sucursalId = $user['sucursal_id'] ?? 1;
$action = $_POST['action'] ?? '';

try {
    $pdo = getConnection();

    if ($action === 'descontar_insumo') {
        // Descontar material consumido en turno (cuchilla, toalla, etc.)
        $productoId = intval($_POST['producto_id'] ?? 0);
        $cantidad = intval($_POST['cantidad'] ?? 1);

        if ($productoId <= 0 || $cantidad <= 0) {
            throw new Exception('Selecciona un insumo y cantidad válida.');
        }

        // Verificar stock actual
        $stmtCheck = $pdo->prepare("SELECT producto, cantidad FROM inventario WHERE id = ?");
        $stmtCheck->execute([$productoId]);
        $prod = $stmtCheck->fetch(PDO::FETCH_ASSOC);

        if (!$prod) {
            throw new Exception('Producto o insumo no encontrado.');
        }

        if ($prod['cantidad'] < $cantidad) {
            throw new Exception("Stock insuficiente de '{$prod['producto']}'. Quedan solo {$prod['cantidad']} unidades.");
        }

        // Debitar del inventario
        $stmtUpdate = $pdo->prepare("UPDATE inventario SET cantidad = cantidad - ? WHERE id = ?");
        $stmtUpdate->execute([$cantidad, $productoId]);

        echo json_encode([
            'success' => true, 
            'message' => "Se debitaron {$cantidad} unidades de '{$prod['producto']}' del inventario."
        ]);
        exit;

    } elseif ($action === 'registrar_venta') {
        // Registrar venta de producto con comisión para el barbero
        $productoId = intval($_POST['producto_id'] ?? 0);
        $cantidad = intval($_POST['cantidad'] ?? 1);

        if ($productoId <= 0 || $cantidad <= 0) {
            throw new Exception('Selecciona un producto y cantidad válida.');
        }

        // Verificar stock y precio
        $stmtCheck = $pdo->prepare("SELECT producto, cantidad, precio FROM inventario WHERE id = ?");
        $stmtCheck->execute([$productoId]);
        $prod = $stmtCheck->fetch(PDO::FETCH_ASSOC);

        if (!$prod) {
            throw new Exception('Producto no encontrado.');
        }

        if ($prod['cantidad'] < $cantidad) {
            throw new Exception("Stock insuficiente de '{$prod['producto']}'. Quedan solo {$prod['cantidad']} unidades.");
        }

        $precioUnitario = floatval($prod['precio']);

        // 1. Debitar stock
        $stmtUpdate = $pdo->prepare("UPDATE inventario SET cantidad = cantidad - ? WHERE id = ?");
        $stmtUpdate->execute([$cantidad, $productoId]);

        // 2. Registrar venta vinculada al barbero
        $stmtVenta = $pdo->prepare("
            INSERT INTO ventas_productos (producto_id, cantidad, precio_unitario, sucursal_id, usuario_id, fecha)
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        $stmtVenta->execute([$productoId, $cantidad, $precioUnitario, $sucursalId, $usuarioId]);

        echo json_encode([
            'success' => true,
            'message' => "Venta de '{$prod['producto']}' registrada con éxito. ¡Tu comisión se ha sumado a tus ganancias!"
        ]);
        exit;

    } else {
        throw new Exception('Acción desconocida.');
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
