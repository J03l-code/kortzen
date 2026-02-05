<?php
require_once 'config.php';

try {
    $pdo = getConnection();

    // Create resenas table
    $sql = "CREATE TABLE IF NOT EXISTS `resenas` (
        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `cliente_nombre` VARCHAR(100) NOT NULL,
        `comentario` TEXT NOT NULL,
        `calificacion` TINYINT UNSIGNED NOT NULL DEFAULT 5, /* 1 to 5 */
        `fecha` DATE DEFAULT CURRENT_DATE,
        `visible` TINYINT(1) NOT NULL DEFAULT 1,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

    $pdo->exec($sql);
    echo "Table resenas created successfully.<br>";

    // Add dummy data if empty
    $count = $pdo->query("SELECT COUNT(*) FROM resenas")->fetchColumn();
    if ($count == 0) {
        $dummy = [
            ['Juan Pérez', 'Excelente servicio, el corte quedó perfecto.', 5, '2023-11-15'],
            ['Maria Garcia', 'Muy buena atención y el ambiente es genial.', 5, '2023-12-01'],
            ['Carlos Ruiz', 'Recomendado 100%, Mateo es un crack.', 5, '2024-01-10']
        ];

        $stmt = $pdo->prepare("INSERT INTO resenas (cliente_nombre, comentario, calificacion, fecha) VALUES (?, ?, ?, ?)");
        foreach ($dummy as $d) {
            $stmt->execute($d);
        }
        echo "Inserted dummy reviews.<br>";
    }

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
