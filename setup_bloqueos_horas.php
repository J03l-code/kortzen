<?php
require_once 'config.php';

try {
    $pdo = getConnection();

    // Drop if exists to ensure clean state
    $pdo->exec("DROP TABLE IF EXISTS bloqueos_horas");

    $sql = "CREATE TABLE bloqueos_horas (
        id INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        barbero_id INT(11) UNSIGNED NOT NULL,
        fecha DATE NOT NULL,
        hora_inicio TIME NOT NULL,
        hora_fin TIME NOT NULL,
        motivo VARCHAR(255),
        creado_por INT(11) UNSIGNED,
        fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (barbero_id) REFERENCES usuarios(id) ON DELETE CASCADE,
        FOREIGN KEY (creado_por) REFERENCES usuarios(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

    $pdo->exec($sql);
    echo "Tabla 'bloqueos_horas' creada correctamente (Fixed FK).";

} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}
?>