<?php
/**
 * KORTZEN - Limpieza de Prefijos de Códigos de Referido
 */
require_once __DIR__ . '/../config.php';

header('Content-Type: text/html; charset=UTF-8');

try {
    $pdo = getConnection();
    $pdo->exec("UPDATE clientes SET codigo_referido = REPLACE(codigo_referido, 'KORTZEN-', '') WHERE codigo_referido LIKE 'KORTZEN-%'");
    echo "<h2 style='color: green;'>✅ Códigos de referidos limpiados. Todos los prefijos KORTZEN- han sido removidos de la base de datos.</h2>";
} catch (Exception $e) {
    echo "<h2 style='color: red;'>❌ Error: " . htmlspecialchars($e->getMessage()) . "</h2>";
}
