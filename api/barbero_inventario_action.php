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
        if (!is_string($_POST['csrf_token'] ?? null) || !verifyCSRFToken($_POST['csrf_token'])) {
            throw new Exception('La sesión del formulario expiró. Actualiza la página.');
        }
        $venta = registrarVentaProductos($pdo, [[
            'producto_id' => $_POST['producto_id'] ?? null,
            'cantidad' => $_POST['cantidad'] ?? null
        ]], $_POST['vendedor_id'] ?? null, (int) $usuarioId);
        registrarLog('VENDER', 'inventario', (int) $_POST['producto_id'],
            'Venta acreditada a ' . $venta['vendedor_nombre'] . ' (#' . $venta['vendedor_id'] . ')');
        echo json_encode(['success' => true,
            'message' => 'Venta registrada a nombre de ' . $venta['vendedor_nombre'] . '.']);
        exit;

    } else {
        throw new Exception('Acción desconocida.');
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
