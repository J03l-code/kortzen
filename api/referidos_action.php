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

        if (empty($codigo)) {
            echo json_encode(['success' => false, 'message' => 'Por favor ingresa un código.']);
            exit;
        }

        // Cargar configuraciones de la base de datos
        $stmtCfg = $pdo->query("SELECT clave, valor FROM configuracion");
        $configs = $stmtCfg->fetchAll(PDO::FETCH_KEY_PAIR);
        $montoDescuento = floatval($configs['descuento_referido_amigo'] ?? 2.00);

        // Buscar cliente dueño del código
        $stmtRef = $pdo->prepare("SELECT id, nombre FROM clientes WHERE codigo_referido = ?");
        $stmtRef->execute([$codigo]);
        $referente = $stmtRef->fetch(PDO::FETCH_ASSOC);

        if (!$referente) {
            echo json_encode(['success' => false, 'message' => 'El código de referido no existe o es inválido.']);
            exit;
        }

        // Si el usuario actual inició sesión y es el mismo dueño del código, no puede referirse a sí mismo
        if (isset($_SESSION['cliente_id']) && $_SESSION['cliente_id'] == $referente['id']) {
            echo json_encode(['success' => false, 'message' => 'No puedes usar tu propio código de referido.']);
            exit;
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
