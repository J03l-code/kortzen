<?php
/**
 * KORTZEN - API para Validación y Aplicación de Códigos de Referido
 */
header('Content-Type: application/json');
require_once '../config.php';

$action = $_POST['action'] ?? $_GET['action'] ?? '';

try {
    $pdo = getConnection();

    // 1. Validar Código de Referido
    if ($action === 'validar') {
        $codigo = strtoupper(trim($_POST['codigo'] ?? ''));
        $codigoLimpio = str_replace('KORTZEN-', '', $codigo);

        if (empty($codigoLimpio)) {
            echo json_encode(['success' => false, 'message' => 'Por favor ingresa un código.']);
            exit;
        }

        // Cargar configuraciones de la base de datos
        $stmtCfg = $pdo->query("SELECT clave, valor FROM configuracion");
        $configs = $stmtCfg->fetchAll(PDO::FETCH_KEY_PAIR);
        $montoDescuento = floatval($configs['descuento_referido_amigo'] ?? 2.00);

        // Buscar cliente dueño del código
        $stmtRef = $pdo->prepare("SELECT id, nombre FROM clientes WHERE codigo_referido = ? OR codigo_referido = ?");
        $stmtRef->execute([$codigoLimpio, $codigo]);
        $referente = $stmtRef->fetch(PDO::FETCH_ASSOC);

        if (!$referente) {
            echo json_encode(['success' => false, 'message' => 'El código de referido no existe o es inválido.']);
            exit;
        }

        // Validaciones del cliente que intenta aplicar el código
        if (isset($_SESSION['cliente_id']) && $_SESSION['cliente_id'] > 0) {
            $clienteIdActual = intval($_SESSION['cliente_id']);

            // 1. No puede usar su propio código de referido
            if ($clienteIdActual == $referente['id']) {
                echo json_encode(['success' => false, 'message' => 'No puedes usar tu propio código de referido.']);
                exit;
            }

            // 2. Verificar si ya ha usado un código de referido anteriormente
            $stmtCheckUsed = $pdo->prepare("SELECT COUNT(*) FROM referidos WHERE referido_id = ?");
            $stmtCheckUsed->execute([$clienteIdActual]);
            if ($stmtCheckUsed->fetchColumn() > 0) {
                echo json_encode(['success' => false, 'message' => 'El código de referido solo es válido para tu primera reserva. Ya has utilizado uno anteriormente.']);
                exit;
            }

            // 3. Verificar si el cliente ya tiene citas previas registradas (solo válido para la primera visita)
            $stmtCheckCitas = $pdo->prepare("SELECT COUNT(*) FROM citas WHERE cliente_id = ? AND estado != 'cancelada'");
            $stmtCheckCitas->execute([$clienteIdActual]);
            if ($stmtCheckCitas->fetchColumn() > 0) {
                echo json_encode(['success' => false, 'message' => 'El descuento por referido es exclusivo para nuevos clientes en su primera visita.']);
                exit;
            }
        }

        echo json_encode([
            'success' => true,
            'message' => '¡Código de ' . explode(' ', $referente['nombre'])[0] . ' aplicado! Descuento de $' . number_format($montoDescuento, 2) . ' activado.',
            'referente_id' => $referente['id'],
            'referente_nombre' => $referente['nombre'],
            'codigo' => $codigo,
            'descuento' => $montoDescuento
        ]);
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'Acción no válida.']);
    exit;

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    exit;
}
