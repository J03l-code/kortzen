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

        // Alerta Stock Bajo proactiva a barberos y administración
        try {
            $stmtStk = $pdo->prepare("SELECT cantidad, stock_minimo FROM inventario WHERE id = ?");
            $stmtStk->execute([$productoId]);
            $rStk = $stmtStk->fetch(PDO::FETCH_ASSOC);
            if ($rStk && floatval($rStk['cantidad']) <= floatval($rStk['stock_minimo'])) {
                require_once __DIR__ . '/../includes/webpush_helper.php';
                if (function_exists('notificarStockBajo')) {
                    notificarStockBajo($pdo, $productoId, $rStk['cantidad'], $rStk['stock_minimo'], $usuarioId);
                }
            }
        } catch (Exception $eStk) {}

        echo json_encode([
            'success' => true, 
            'message' => "Se debitaron {$cantidad} unidades de '{$prod['producto']}' del inventario."
        ]);
        exit;

    } elseif ($action === 'registrar_venta') {
        if (!is_string($_POST['csrf_token'] ?? null) || !verifyCSRFToken($_POST['csrf_token'])) {
            throw new Exception('La sesión del formulario expiró. Actualiza la página.');
        }
        $venta = registrarVentaProductos($pdo, [[
            'producto_id' => $_POST['producto_id'] ?? null,
            'cantidad' => $_POST['cantidad'] ?? null,
            'precio_unitario' => $_POST['precio_venta'] ?? $_POST['precio_cobrado'] ?? null
        ]], $_POST['vendedor_id'] ?? null, (int) $usuarioId, null, null, $_POST['metodo_pago'] ?? null);
        notificarStockVentas($pdo, $venta);
        registrarLog('VENTA', 'inventario', (int) $_POST['producto_id'], 'Venta acreditada a ' . $venta['vendedor_nombre']);
        echo json_encode(['success' => true,
            'message' => 'Venta registrada a nombre de ' . $venta['vendedor_nombre'] . ' por $' . number_format($venta['total'], 2) . '.']);
        exit;

    } else {
        throw new Exception('Acción desconocida.');
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
