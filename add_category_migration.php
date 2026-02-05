<?php
require_once 'config.php';

try {
    $pdo = getConnection();

    // Check if column exists
    $stmt = $pdo->prepare("SHOW COLUMNS FROM servicios LIKE 'categoria'");
    $stmt->execute();
    $exists = $stmt->fetch();

    if (!$exists) {
        $sql = "ALTER TABLE servicios ADD COLUMN categoria VARCHAR(50) NOT NULL DEFAULT 'General' AFTER duracion_minutos";
        $pdo->exec($sql);
        echo "Column 'categoria' added successfully.";
    } else {
        echo "Column 'categoria' already exists.";
    }

    // Define common categories for enum-like behavior later if needed, 
    // but for now VARCHAR is flexible as requested.

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
