<?php
/**
 * KORTZEN - Migración de Comisión por Venta de Productos para Barberos
 * Agrega la columna `comision_productos` a la tabla `usuarios`
 */
require_once __DIR__ . '/../config.php';

try {
    $pdo = getConnection();
    
    // Verificar si existe la columna 'comision_productos'
    $stmt = $pdo->query("SHOW COLUMNS FROM usuarios LIKE 'comision_productos'");
    $col = $stmt->fetch();
    
    if (!$col) {
        $pdo->exec("ALTER TABLE usuarios ADD COLUMN comision_productos DECIMAL(5,2) DEFAULT 10.00 AFTER comision_fin_semana");
        echo "✅ Columna 'comision_productos' agregada con éxito a la tabla usuarios.<br>";
    } else {
        echo "ℹ️ La columna 'comision_productos' ya existía en la tabla usuarios.<br>";
    }

} catch (Exception $e) {
    echo "❌ Error en la migración: " . $e->getMessage();
}
