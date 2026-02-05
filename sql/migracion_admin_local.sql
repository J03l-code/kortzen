-- ============================================================
-- SCRIPT DE MIGRACIÓN: Rol Admin de Locales
-- KORTZEN - Sistema de Gestión de Barberías
-- Ejecutar este script en phpMyAdmin
-- ============================================================
-- 1. Modificar la columna rol para incluir admin_local
ALTER TABLE usuarios
MODIFY COLUMN rol ENUM('admin', 'admin_local', 'barbero') DEFAULT 'barbero';
-- 2. Crear tabla de horarios de barberos
CREATE TABLE IF NOT EXISTS horarios_barberos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    barbero_id INT NOT NULL,
    dia_semana TINYINT NOT NULL COMMENT '0=Domingo, 1=Lunes, 2=Martes, 3=Miércoles, 4=Jueves, 5=Viernes, 6=Sábado',
    hora_inicio TIME DEFAULT '10:00:00',
    hora_fin TIME DEFAULT '20:00:00',
    activo BOOLEAN DEFAULT TRUE,
    FOREIGN KEY (barbero_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    UNIQUE KEY unique_barbero_dia (barbero_id, dia_semana)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
-- 3. Crear tabla de días bloqueados (descanso, vacaciones, etc.)
CREATE TABLE IF NOT EXISTS dias_bloqueados (
    id INT AUTO_INCREMENT PRIMARY KEY,
    barbero_id INT NOT NULL,
    fecha DATE NOT NULL,
    motivo VARCHAR(255) DEFAULT 'Día de descanso',
    todo_el_dia BOOLEAN DEFAULT TRUE,
    hora_inicio TIME DEFAULT NULL,
    hora_fin TIME DEFAULT NULL,
    creado_por INT,
    fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (barbero_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    FOREIGN KEY (creado_por) REFERENCES usuarios(id) ON DELETE
    SET NULL,
        UNIQUE KEY unique_barbero_fecha (barbero_id, fecha)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
-- 4. Insertar horarios por defecto para barberos existentes
-- (Lunes a Sábado, 10:00 - 20:00, Domingo cerrado)
INSERT IGNORE INTO horarios_barberos (
        barbero_id,
        dia_semana,
        hora_inicio,
        hora_fin,
        activo
    )
SELECT id,
    1,
    '10:00:00',
    '20:00:00',
    TRUE
FROM usuarios
WHERE rol = 'barbero'
UNION ALL
SELECT id,
    2,
    '10:00:00',
    '20:00:00',
    TRUE
FROM usuarios
WHERE rol = 'barbero'
UNION ALL
SELECT id,
    3,
    '10:00:00',
    '20:00:00',
    TRUE
FROM usuarios
WHERE rol = 'barbero'
UNION ALL
SELECT id,
    4,
    '10:00:00',
    '20:00:00',
    TRUE
FROM usuarios
WHERE rol = 'barbero'
UNION ALL
SELECT id,
    5,
    '10:00:00',
    '20:00:00',
    TRUE
FROM usuarios
WHERE rol = 'barbero'
UNION ALL
SELECT id,
    6,
    '10:00:00',
    '20:00:00',
    TRUE
FROM usuarios
WHERE rol = 'barbero'
UNION ALL
SELECT id,
    0,
    '10:00:00',
    '20:00:00',
    FALSE
FROM usuarios
WHERE rol = 'barbero';
-- ============================================================
-- FIN DEL SCRIPT
-- ============================================================