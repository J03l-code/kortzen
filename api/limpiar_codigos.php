<?php
/**
 * KORTZEN - Limpieza de Prefijos de Códigos de Referido
 */
require_once __DIR__ . '/../config.php';
requireLogin();
if (($_SESSION['user_rol'] ?? '') !== 'admin') {
    http_response_code(403);
    die('Acceso no autorizado.');
}

try {
    $pdo = getConnection();
    $pdo->exec("UPDATE clientes SET codigo_referido = REPLACE(codigo_referido, 'KORTZEN-', '') WHERE codigo_referido LIKE 'KORTZEN-%'");
    echo "<h2 style='color: green;'>✅ Códigos de referidos limpiados. Todos los prefijos KORTZEN- han sido removidos de la base de datos.</h2>";
} catch (Exception $e) {
    echo "<h2 style='color: red;'>❌ Error: " . htmlspecialchars($e->getMessage()) . "</h2>";
}
