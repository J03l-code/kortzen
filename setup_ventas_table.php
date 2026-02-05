<?php
require_once 'config.php';

try {
    $pdo = getConnection();

    // Clean up test table
    $pdo->exec("DROP TABLE IF EXISTS ventas_simple");

    // Create actual table
    $sql = "CREATE TABLE IF NOT EXISTS ventas_productos (
        id INT(10) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        cita_id INT(10) UNSIGNED NULL,
        producto_id INT(10) UNSIGNED NOT NULL,
        cantidad INT NOT NULL,
        precio_unitario DECIMAL(10,2) NOT NULL,
        fecha DATETIME DEFAULT CURRENT_TIMESTAMP,
        sucursal_id INT(10) UNSIGNED NOT NULL,
        usuario_id INT(10) UNSIGNED NOT NULL,
        FOREIGN KEY (producto_id) REFERENCES inventario(id) ON DELETE CASCADE,
        FOREIGN KEY (sucursal_id) REFERENCES sucursales(id),
        FOREIGN KEY (cita_id) REFERENCES citas(id) ON DELETE SET NULL,
        FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

    $pdo->exec($sql);
    echo "Tabla 'ventas_productos' creada correctamente.";

} catch (PDOException $e) {
    die("Error en migración: " . $e->getMessage());
}
?>