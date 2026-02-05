<?php
require_once 'config.php';

try {
    $pdo = getConnection();

    // Create servicios_sucursales table
    $sql = "CREATE TABLE IF NOT EXISTS `servicios_sucursales` (
        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `servicio_id` INT UNSIGNED NOT NULL,
        `sucursal_id` INT UNSIGNED NOT NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uk_servicio_sucursal` (`servicio_id`, `sucursal_id`),
        CONSTRAINT `fk_ss_servicio` FOREIGN KEY (`servicio_id`) REFERENCES `servicios` (`id`) ON DELETE CASCADE,
        CONSTRAINT `fk_ss_sucursal` FOREIGN KEY (`sucursal_id`) REFERENCES `sucursales` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

    $pdo->exec($sql);
    echo "Table servicios_sucursales created successfully.<br>";

    // Initialize all existing services to be available in ALL branches (for backward compatibility)
    echo "Initializing existing services...<br>";
    $services = $pdo->query("SELECT id FROM servicios")->fetchAll(PDO::FETCH_COLUMN);
    $branches = $pdo->query("SELECT id FROM sucursales")->fetchAll(PDO::FETCH_COLUMN);

    if (!empty($services) && !empty($branches)) {
        $stmt = $pdo->prepare("INSERT IGNORE INTO servicios_sucursales (servicio_id, sucursal_id) VALUES (?, ?)");
        foreach ($services as $srvId) {
            foreach ($branches as $brId) {
                $stmt->execute([$srvId, $brId]);
            }
        }
        echo "Linked all existing services to all branches.<br>";
    }

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
