<?php
require_once 'config.php';

try {
    $pdo = getConnection();
    // Add categoria column
    $sql = "ALTER TABLE galeria_imagenes ADD COLUMN categoria VARCHAR(50) DEFAULT 'corte' AFTER descripcion";
    $pdo->exec($sql);
    echo "Columna 'categoria' añadida correctamente.";
} catch (PDOException $e) {
    echo "Nota/Error: " . $e->getMessage();
}
?>