<?php
/**
 * KORTZEN - Guardar Preferencias del Cliente (Estilo, Ambiente y Bebida)
 */
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json');

try {
    $pdo = getConnection();

    // Asegurar que las columnas existan en la tabla clientes
    try {
        $pdo->exec("ALTER TABLE clientes ADD COLUMN estilo_buscado VARCHAR(255) NULL AFTER telefono");
    } catch (Exception $e) {}
    try {
        $pdo->exec("ALTER TABLE clientes ADD COLUMN ambiente_preferido VARCHAR(255) NULL AFTER estilo_buscado");
    } catch (Exception $e) {}
    try {
        $pdo->exec("ALTER TABLE clientes ADD COLUMN bebida_preferida VARCHAR(255) NULL AFTER ambiente_preferido");
    } catch (Exception $e) {}

    $estilo = isset($_POST['estilo_buscado']) ? trim($_POST['estilo_buscado']) : '';
    $ambiente = isset($_POST['ambiente_preferido']) ? trim($_POST['ambiente_preferido']) : '';
    $bebida = isset($_POST['bebida_preferida']) ? trim($_POST['bebida_preferida']) : '';

    if (isClienteLoggedIn()) {
        $cliente = getCurrentCliente();
        $clienteId = $cliente['id'];

        $stmt = $pdo->prepare("
            UPDATE clientes 
            SET estilo_buscado = ?, ambiente_preferido = ?, bebida_preferida = ? 
            WHERE id = ?
        ");
        $stmt->execute([$estilo, $ambiente, $bebida, $clienteId]);

        echo json_encode([
            'success' => true,
            'message' => 'Preferencias guardadas exitosamente en tu perfil.'
        ]);
    } else {
        // Retornar éxito para que el cliente lo mantenga en localStorage hasta iniciar sesión/reservar
        echo json_encode([
            'success' => true,
            'guest' => true,
            'message' => 'Preferencias guardadas temporalmente. Se vincularán cuando inicies sesión o reserves.'
        ]);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error al guardar preferencias: ' . $e->getMessage()
    ]);
}
