<?php
/**
 * KORTZEN - Procesador de Guardado de Configuración (Admin)
 */
require_once '../config.php';
requireLogin();

if (!in_array($_SESSION['user_rol'] ?? '', ['admin', 'admin_local'])) {
    header('Location: ../dashboard.php');
    exit;
}

$action = $_POST['action'] ?? '';

if ($action === 'save_configs') {
    try {
        $pdo = getConnection();
        
        $puntos_por_corte = max(0, intval($_POST['puntos_por_corte'] ?? 100));
        $puntos_por_referido = max(0, intval($_POST['puntos_por_referido'] ?? 200));
        $descuento_referido_amigo = max(0, floatval($_POST['descuento_referido_amigo'] ?? 2.00));
        $descuento_referente = max(0, floatval($_POST['descuento_referente'] ?? 2.00));

        $configs = [
            'puntos_por_corte' => (string)$puntos_por_corte,
            'puntos_por_referido' => (string)$puntos_por_referido,
            'descuento_referido_amigo' => number_format($descuento_referido_amigo, 2, '.', ''),
            'descuento_referente' => number_format($descuento_referente, 2, '.', '')
        ];

        $stmt = $pdo->prepare("INSERT INTO configuracion (clave, valor) VALUES (?, ?) ON DUPLICATE KEY UPDATE valor = VALUES(valor)");

        foreach ($configs as $clave => $valor) {
            $stmt->execute([$clave, $valor]);
        }

        header('Location: ../configuracion.php?success=' . urlencode('Configuración del sistema guardada exitosamente.'));
        exit;

    } catch (Exception $e) {
        header('Location: ../configuracion.php?error=' . urlencode($e->getMessage()));
        exit;
    }
}

header('Location: ../configuracion.php');
exit;
