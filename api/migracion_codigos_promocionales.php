<?php
/**
 * KORTZEN - Migración para la tabla de Códigos Promocionales y Cupones
 */
require_once __DIR__ . '/../config.php';

try {
    $pdo = getConnection();

    // 1. Crear tabla de códigos promocionales
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS codigos_promocionales (
            id INT AUTO_INCREMENT PRIMARY KEY,
            codigo VARCHAR(50) NOT NULL UNIQUE,
            descuento_porcentaje DECIMAL(5,2) NOT NULL DEFAULT 10.00,
            uso_maximo_por_usuario INT DEFAULT 1,
            activo TINYINT(1) DEFAULT 1,
            descripcion VARCHAR(255) NULL,
            fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    // 2. Crear tabla para registrar el historial de usos por cliente
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS usos_codigos_promocionales (
            id INT AUTO_INCREMENT PRIMARY KEY,
            codigo_id INT NOT NULL,
            cliente_id INT NOT NULL,
            cita_id INT NULL,
            descuento_monto DECIMAL(10,2) NOT NULL,
            fecha_uso DATETIME DEFAULT CURRENT_TIMESTAMP,
            KEY idx_codigo (codigo_id),
            KEY idx_cliente (cliente_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

} catch (Exception $e) {
    // Silencioso si ya existen
}
