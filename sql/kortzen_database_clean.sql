-- ============================================================
-- KORTZEN - Sistema de Gestión de Barberías
-- Base de Datos Completa para Hostinger
-- Versión: 1.0.0
-- Fecha: 2026-01-24
-- ============================================================
-- 
-- INSTRUCCIONES PARA HOSTINGER:
-- 1. Acceder a phpMyAdmin desde hPanel
-- 2. Seleccionar tu base de datos (ej: u434851126_barberia)
-- 3. Ir a la pestaña "Importar"
-- 4. Seleccionar este archivo SQL
-- 5. Ejecutar la importación
--
-- IMPORTANTE: Este script ELIMINA las tablas existentes y las recrea
-- ============================================================
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';
SET time_zone = '-05:00';
-- ============================================================
-- ELIMINAR TABLAS EXISTENTES (en orden correcto por dependencias)
-- ============================================================
DROP TABLE IF EXISTS `logs_actividad`;
DROP TABLE IF EXISTS `dias_bloqueados`;
DROP TABLE IF EXISTS `horarios_barberos`;
DROP TABLE IF EXISTS `citas`;
DROP TABLE IF EXISTS `inventario`;
DROP TABLE IF EXISTS `clientes`;
DROP TABLE IF EXISTS `servicios`;
DROP TABLE IF EXISTS `usuarios`;
DROP TABLE IF EXISTS `sucursales`;
-- ============================================================
-- TABLA: sucursales
-- Almacena las sucursales/locales de la barbería
-- ============================================================
CREATE TABLE `sucursales` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `nombre` VARCHAR(100) NOT NULL,
    `direccion` VARCHAR(255) DEFAULT NULL,
    `telefono` VARCHAR(20) DEFAULT NULL,
    `horario_apertura` TIME DEFAULT '10:00:00',
    `horario_cierre` TIME DEFAULT '21:00:00',
    `activo` TINYINT(1) NOT NULL DEFAULT 1,
    `fecha_creacion` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `fecha_actualizacion` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_sucursales_activo` (`activo`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
-- ============================================================
-- TABLA: usuarios
-- Almacena el personal del sistema (admin, admin_local, barberos)
-- ============================================================
CREATE TABLE `usuarios` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `nombre` VARCHAR(100) NOT NULL,
    `email` VARCHAR(100) NOT NULL,
    `password` VARCHAR(255) NOT NULL COMMENT 'Hash BCRYPT',
    `rol` ENUM('admin', 'admin_local', 'barbero') NOT NULL DEFAULT 'barbero',
    `sucursal_id` INT UNSIGNED DEFAULT NULL,
    `activo` TINYINT(1) NOT NULL DEFAULT 1,
    `fecha_creacion` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `fecha_actualizacion` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_usuarios_email` (`email`),
    INDEX `idx_usuarios_rol` (`rol`),
    INDEX `idx_usuarios_sucursal` (`sucursal_id`),
    CONSTRAINT `fk_usuarios_sucursal` FOREIGN KEY (`sucursal_id`) REFERENCES `sucursales` (`id`) ON DELETE
    SET NULL ON UPDATE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
-- ============================================================
-- TABLA: clientes
-- Almacena los clientes de la barbería
-- ============================================================
CREATE TABLE `clientes` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `nombre` VARCHAR(100) NOT NULL,
    `email` VARCHAR(100) DEFAULT NULL,
    `telefono` VARCHAR(20) DEFAULT NULL,
    `fecha_nacimiento` DATE DEFAULT NULL,
    `notas` TEXT DEFAULT NULL,
    `google_id` VARCHAR(100) DEFAULT NULL COMMENT 'ID para login con Google',
    `foto_perfil` VARCHAR(500) DEFAULT NULL COMMENT 'URL de foto de Google',
    `activo` TINYINT(1) NOT NULL DEFAULT 1,
    `fecha_creacion` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `fecha_actualizacion` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_clientes_email` (`email`),
    UNIQUE KEY `uk_clientes_google_id` (`google_id`),
    INDEX `idx_clientes_telefono` (`telefono`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
-- ============================================================
-- TABLA: servicios
-- Catálogo de servicios ofrecidos
-- ============================================================
CREATE TABLE `servicios` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `nombre` VARCHAR(100) NOT NULL,
    `descripcion` TEXT DEFAULT NULL,
    `precio` DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    `duracion_minutos` INT UNSIGNED NOT NULL DEFAULT 30,
    `activo` TINYINT(1) NOT NULL DEFAULT 1,
    `fecha_creacion` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `fecha_actualizacion` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_servicios_activo` (`activo`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
-- ============================================================
-- TABLA: inventario
-- Productos en inventario por sucursal
-- ============================================================
CREATE TABLE `inventario` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `producto` VARCHAR(150) NOT NULL,
    `cantidad` INT NOT NULL DEFAULT 0,
    `precio` DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    `stock_minimo` INT NOT NULL DEFAULT 5,
    `sucursal_id` INT UNSIGNED NOT NULL,
    `fecha_creacion` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `fecha_actualizacion` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_inventario_sucursal` (`sucursal_id`),
    INDEX `idx_inventario_stock` (`cantidad`, `stock_minimo`),
    CONSTRAINT `fk_inventario_sucursal` FOREIGN KEY (`sucursal_id`) REFERENCES `sucursales` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
-- ============================================================
-- TABLA: citas
-- Citas programadas con clientes
-- ============================================================
CREATE TABLE `citas` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `cliente_id` INT UNSIGNED NOT NULL,
    `servicio_id` INT UNSIGNED NOT NULL,
    `barbero_id` INT UNSIGNED DEFAULT NULL,
    `sucursal_id` INT UNSIGNED NOT NULL,
    `fecha_hora` DATETIME NOT NULL,
    `estado` ENUM(
        'pendiente',
        'confirmada',
        'completada',
        'cancelada'
    ) NOT NULL DEFAULT 'pendiente',
    `notas` TEXT DEFAULT NULL,
    `precio_final` DECIMAL(10, 2) DEFAULT NULL COMMENT 'Precio cobrado (puede diferir del precio del servicio)',
    `fecha_creacion` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `fecha_actualizacion` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_citas_fecha` (`fecha_hora`),
    INDEX `idx_citas_estado` (`estado`),
    INDEX `idx_citas_barbero_fecha` (`barbero_id`, `fecha_hora`),
    INDEX `idx_citas_cliente` (`cliente_id`),
    INDEX `idx_citas_sucursal` (`sucursal_id`),
    CONSTRAINT `fk_citas_cliente` FOREIGN KEY (`cliente_id`) REFERENCES `clientes` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_citas_servicio` FOREIGN KEY (`servicio_id`) REFERENCES `servicios` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_citas_barbero` FOREIGN KEY (`barbero_id`) REFERENCES `usuarios` (`id`) ON DELETE
    SET NULL ON UPDATE CASCADE,
        CONSTRAINT `fk_citas_sucursal` FOREIGN KEY (`sucursal_id`) REFERENCES `sucursales` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
-- ============================================================
-- TABLA: horarios_barberos
-- Horarios semanales de cada barbero
-- ============================================================
CREATE TABLE `horarios_barberos` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `barbero_id` INT UNSIGNED NOT NULL,
    `dia_semana` TINYINT UNSIGNED NOT NULL COMMENT '0=Domingo, 1=Lunes, 2=Martes, 3=Miércoles, 4=Jueves, 5=Viernes, 6=Sábado',
    `hora_inicio` TIME NOT NULL DEFAULT '10:00:00',
    `hora_fin` TIME NOT NULL DEFAULT '20:00:00',
    `activo` TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Si trabaja este día',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_barbero_dia` (`barbero_id`, `dia_semana`),
    INDEX `idx_horarios_barbero` (`barbero_id`),
    CONSTRAINT `fk_horarios_barbero` FOREIGN KEY (`barbero_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
-- ============================================================
-- TABLA: dias_bloqueados
-- Días de descanso, vacaciones, etc. de barberos
-- ============================================================
CREATE TABLE `dias_bloqueados` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `barbero_id` INT UNSIGNED NOT NULL,
    `fecha` DATE NOT NULL,
    `motivo` VARCHAR(255) NOT NULL DEFAULT 'Día de descanso',
    `todo_el_dia` TINYINT(1) NOT NULL DEFAULT 1,
    `hora_inicio` TIME DEFAULT NULL COMMENT 'Si no es todo el día',
    `hora_fin` TIME DEFAULT NULL COMMENT 'Si no es todo el día',
    `creado_por` INT UNSIGNED DEFAULT NULL,
    `fecha_creacion` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_barbero_fecha` (`barbero_id`, `fecha`),
    INDEX `idx_bloqueos_fecha` (`fecha`),
    CONSTRAINT `fk_bloqueos_barbero` FOREIGN KEY (`barbero_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_bloqueos_creador` FOREIGN KEY (`creado_por`) REFERENCES `usuarios` (`id`) ON DELETE
    SET NULL ON UPDATE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
-- ============================================================
-- TABLA: logs_actividad
-- Registro de actividad del sistema para auditoría
-- ============================================================
CREATE TABLE `logs_actividad` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `usuario_id` INT UNSIGNED DEFAULT NULL,
    `accion` VARCHAR(50) NOT NULL COMMENT 'INSERT, UPDATE, DELETE, LOGIN, etc.',
    `tabla_afectada` VARCHAR(50) DEFAULT NULL,
    `registro_id` INT UNSIGNED DEFAULT NULL,
    `descripcion` TEXT DEFAULT NULL,
    `ip_address` VARCHAR(45) DEFAULT NULL,
    `user_agent` VARCHAR(500) DEFAULT NULL,
    `fecha` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_logs_usuario` (`usuario_id`),
    INDEX `idx_logs_fecha` (`fecha`),
    INDEX `idx_logs_accion` (`accion`),
    CONSTRAINT `fk_logs_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE
    SET NULL ON UPDATE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
-- ============================================================
-- DATOS INICIALES
-- ============================================================
-- Insertar sucursal principal (KORTZEN Llano Chico)
INSERT INTO `sucursales` (
        `nombre`,
        `direccion`,
        `telefono`,
        `horario_apertura`,
        `horario_cierre`
    )
VALUES (
        'KORTZEN Llano Chico',
        'Av. Principal, Llano Chico, Quito, Ecuador',
        '+593 098 842 2770',
        '10:00:00',
        '21:00:00'
    );
-- Insertar usuario administrador técnico
-- Contraseña: Admin2026! (hasheada con password_hash BCRYPT)
INSERT INTO `usuarios` (
        `nombre`,
        `email`,
        `password`,
        `rol`,
        `sucursal_id`
    )
VALUES (
        'Administrador',
        'admin@kortzen.com',
        '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
        'admin',
        NULL
    );
-- NOTA IMPORTANTE: El hash de arriba corresponde a la contraseña: 'password'
-- DEBES CAMBIAR LA CONTRASEÑA INMEDIATAMENTE después de importar.
-- 
-- Opción 1: Desde phpMyAdmin, ejecuta:
-- UPDATE usuarios SET password = '$2y$10$NUEVO_HASH_AQUI' WHERE email = 'admin@kortzen.com';
-- 
-- Opción 2: Genera un nuevo hash con PHP:
-- php -r "echo password_hash('TuContraseñaSegura', PASSWORD_DEFAULT);"
-- Insertar servicios de muestra
INSERT INTO `servicios` (
        `nombre`,
        `descripcion`,
        `precio`,
        `duracion_minutos`,
        `activo`
    )
VALUES (
        'Corte Clásico',
        'Corte de cabello tradicional con acabado profesional',
        12.00,
        30,
        1
    ),
    (
        'Corte + Barba',
        'Corte de cabello y arreglo de barba completo',
        18.00,
        45,
        1
    ),
    (
        'Barba Completa',
        'Perfilado, afeitado y tratamiento de barba',
        10.00,
        30,
        1
    ),
    (
        'Corte Degradado',
        'Fade o degradado con diseño personalizado',
        15.00,
        40,
        1
    ),
    (
        'Afeitado Clásico',
        'Afeitado tradicional con navaja y toallas calientes',
        12.00,
        25,
        1
    ),
    (
        'Tratamiento Capilar',
        'Mascarilla hidratante y masaje capilar',
        15.00,
        30,
        1
    ),
    (
        'Corte Premium',
        'Corte VIP con consulta de estilo, lavado y styling',
        25.00,
        60,
        1
    ),
    (
        'Tintura de Barba',
        'Coloración de barba para cubrir canas',
        18.00,
        45,
        1
    );
-- ============================================================
-- HABILITAR FOREIGN KEYS
-- ============================================================
SET FOREIGN_KEY_CHECKS = 1;
-- ============================================================
-- VERIFICACIÓN FINAL
-- ============================================================
-- Ejecutar estas consultas para verificar que todo está correcto:
-- 
-- SELECT TABLE_NAME, ENGINE, TABLE_COLLATION 
-- FROM information_schema.TABLES 
-- WHERE TABLE_SCHEMA = DATABASE();
--
-- SHOW TABLE STATUS;
-- ============================================================