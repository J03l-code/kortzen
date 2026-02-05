<?php
require_once 'config.php';

try {
    $pdo = getConnection();

    // Add column if not exists
    $sql = "ALTER TABLE usuarios ADD COLUMN comision_fin_semana DECIMAL(5,2) DEFAULT 50.00 AFTER comision_porcentaje;";

    try {
        $pdo->exec($sql);
        echo "Columna 'comision_fin_semana' agregada correctamente.";
    } catch (PDOException $e) {
        // Ignore if already exists (error 1060)
        if (strpos($e->getMessage(), 'Duplicate column') !== false) {
            echo "La columna ya existe.";
        } else {
            throw $e;
        }
    }

} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}
?>