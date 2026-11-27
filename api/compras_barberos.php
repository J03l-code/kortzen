<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/insumos_barberos.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: private, no-store');

try {
    if (!isLoggedIn()) {
        throw new InvalidArgumentException('Inicia sesión para continuar.');
    }
    $pdo = getConnection();
    $usuario = getOperadorVenta($pdo, (int) $_SESSION['user_id']);
    asegurarTablasInsumosYComprasBarberos($pdo);

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!is_string($_POST['csrf_token'] ?? null) || !verifyCSRFToken($_POST['csrf_token'])) {
            throw new InvalidArgumentException('La sesión del formulario expiró. Actualiza la página.');
        }
        $action = $_POST['action'] ?? 'registrar';
        if ($action === 'marcar_descontado') {
            if (!esAdministradorCaja($usuario)) {
                throw new InvalidArgumentException('Solo administración puede marcar descuentos en rol.');
            }
            $compraId = filter_var($_POST['compra_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if (!$compraId) {
                throw new InvalidArgumentException('ID de compra inválido.');
            }
            $stmt = $pdo->prepare('SELECT id, sucursal_id, barbero_nombre, total FROM compras_barberos WHERE id = ?');
            $stmt->execute([$compraId]);
            $compra = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$compra) {
                throw new InvalidArgumentException('Compra no encontrada.');
            }
            validarSucursalVenta($pdo, $usuario, (int) $compra['sucursal_id']);
            $upd = $pdo->prepare("UPDATE compras_barberos SET estado_rol = 'descontado' WHERE id = ?");
            $upd->execute([$compraId]);
            registrarLog('ROL_DESCONTADO', 'compras_barberos', $compraId,
                'Compra #' . $compraId . ' de ' . $compra['barbero_nombre'] . ' ($' . number_format((float) $compra['total'], 2) . ') marcada como descontada en rol.');
            echo json_encode(['success' => true, 'message' => 'Marcado como descontado en rol.']);
            exit;
        }

        // Registrar nueva compra de barbero / personal
        $compra = registrarCompraBarbero(
            $pdo,
            $_POST['producto_id'] ?? $_POST['id'] ?? null,
            $_POST['barbero_id'] ?? null,
            $_POST['cantidad'] ?? null,
            $_POST['precio_barbero'] ?? null,
            (int) $_SESSION['user_id'],
            null,
            null,
            (string) ($_POST['notas'] ?? '')
        );
        notificarStockVentas($pdo, $compra, (int) $compra['barbero_id']);
        registrarLog('COMPRA_BARBERO', 'inventario', (int) $compra['producto_id'],
            'Compra de personal: ' . $compra['barbero_nombre'] . ' x' . $compra['cantidad'] . ' ' . $compra['producto_nombre'] . ' ($' . number_format($compra['total'], 2) . ') - Pendiente de rol.');
        echo json_encode([
            'success' => true,
            'message' => 'Compra registrada para ' . $compra['barbero_nombre'] . ' ($' . number_format($compra['total'], 2) . ' a descontar en su rol).',
            'compra' => $compra
        ]);
        exit;
    }

    $reporte = obtenerComprasBarberos($pdo, $usuario, $_GET);
    echo json_encode(['success' => true, 'reporte' => $reporte]);
} catch (InvalidArgumentException $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} catch (Throwable $e) {
    error_log('Compras barberos API error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'No se pudo procesar la solicitud de compras de barberos.']);
}
