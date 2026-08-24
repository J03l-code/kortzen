<?php
/**
 * KORTZEN - Migración de Tabla Sucursales y Estado 'proximamente'
 */
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json');

try {
    $pdo = getConnection();

    // 1. Asegurar tabla sucursales existe
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS sucursales (
            id INT AUTO_INCREMENT PRIMARY KEY,
            nombre VARCHAR(255) NOT NULL,
            direccion VARCHAR(255) NULL,
            telefono VARCHAR(50) NULL,
            horario_apertura TIME DEFAULT '09:00:00',
            horario_cierre TIME DEFAULT '20:00:00',
            estado VARCHAR(50) DEFAULT 'activo',
            imagen_url VARCHAR(255) NULL,
            mapa_url TEXT NULL,
            fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    // 2. Agregar columna 'estado' si no existe
    try {
        $pdo->exec("ALTER TABLE sucursales ADD COLUMN estado VARCHAR(50) DEFAULT 'activo' AFTER telefono");
    } catch (Exception $ex1) {}

    // 3. Agregar columna 'horario_apertura' si no existe
    try {
        $pdo->exec("ALTER TABLE sucursales ADD COLUMN horario_apertura TIME DEFAULT '09:00:00'");
    } catch (Exception $ex2) {}

    // 4. Agregar columna 'horario_cierre' si no existe
    try {
        $pdo->exec("ALTER TABLE sucursales ADD COLUMN horario_cierre TIME DEFAULT '20:00:00'");
    } catch (Exception $ex3) {}

    // 5. Agregar columna 'imagen_url' si no existe
    try {
        $pdo->exec("ALTER TABLE sucursales ADD COLUMN imagen_url VARCHAR(255) NULL");
    } catch (Exception $ex4) {}

    // 6. Agregar columna 'mapa_url' si no existe
    try {
        $pdo->exec("ALTER TABLE sucursales ADD COLUMN mapa_url TEXT NULL");
    } catch (Exception $ex5) {}

    // 7. Verificar sucursal por defecto (KORTZEN Llano Chico)
    $stmtCheck = $pdo->query("SELECT COUNT(*) FROM sucursales");
    if ($stmtCheck->fetchColumn() == 0) {
        $stmtIns = $pdo->prepare("INSERT INTO sucursales (id, nombre, direccion, telefono, horario_apertura, horario_cierre, estado, mapa_url) VALUES (1, ?, ?, ?, ?, ?, 'activo', ?)");
        $stmtIns->execute([
            'KORTZEN Llano Chico',
            'Calle 17 de septiembre, frente a la casa de colchon, Llano Chico, Quito',
            '+593 098 842 2770',
            '09:00:00',
            '20:00:00',
            'https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3989.8071991201023!2d-78.44604192503535!3d-0.13528119986338483!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x91d58fc52de96153%3A0x35f5708deeee0cf7!2sKORTZEN!5e0!3m2!1sen!2sec!4v1786588668585!5m2!1sen!2sec'
        ]);
    }

    echo json_encode([
        'success' => true,
        'message' => 'Migración de sucursales completada con éxito.'
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
