<?php
/**
 * KORTZEN - Migración de Puntos KORTZEN para Clientes
 * Agrega la columna `puntos` a la tabla `clientes` si no existe
 */
require_once __DIR__ . '/../config.php';

try {
    $pdo = getConnection();
    
    // Verificar si la columna 'puntos' existe en 'clientes'
    $stmt = $pdo->query("SHOW COLUMNS FROM clientes LIKE 'puntos'");
    $col = $stmt->fetch();
    
    if (!$col) {
        $pdo->exec("ALTER TABLE clientes ADD COLUMN puntos INT DEFAULT 0 AFTER telefono");
        echo "✅ Columna 'puntos' agregada a la tabla clientes.<br>";
    } else {
        echo "ℹ️ La columna 'puntos' ya existía en la tabla clientes.<br>";
    }

    // Recalcular puntos iniciales basados en citas completadas (100 pts por cita completada)
    $sqlRecalc = "
        UPDATE clientes c 
        SET puntos = (
            SELECT COALESCE(COUNT(*) * 100, 0) 
            FROM citas 
            WHERE cliente_id = c.id AND estado = 'completada'
        )
    ";
    $pdo->exec($sqlRecalc);
    echo "✅ Puntos de clientes recalculados según sus citas completadas reales.<br>";

} catch (Exception $e) {
    echo "❌ Error en la migración: " . $e->getMessage();
}
