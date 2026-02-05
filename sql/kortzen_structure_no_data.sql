-- ============================================================
-- KORTZEN - Estructura de Base de Datos VACÍA
-- Version: Clean Structure Only
-- ============================================================
-- Este script crea SOLO las tablas vacías.
-- NO inserta usuarios, ni sucursales, ni servicios.
-- ============================================================
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';
SET time_zone = '-05:00';
-- ------------------------------------------------------------
-- 1. Eliminar tablas previas para limpiar
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `logs_actividad`;
DROP TABLE IF EXISTS `dias_bloqueados`;
DROP TABLE IF EXISTS `horarios_barberos`;
DROP TABLE IF EXISTS `citas`;
DROP TABLE IF EXISTS `inventario`;
DROP TABLE IF EXISTS `clientes`;
DROP TABLE IF EXISTS `servicios`;
DROP TABLE IF EXISTS `usuarios`;
DROP TABLE IF EXISTS `sucursales`;
-- ------------------------------------------------------------
-- 2. Crear Tablas
-- ------------------------------------------------------------
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
CREATE TABLE `usuarios` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `nombre` VARCHAR(100) NOT NULL,
    `email` VARCHAR(100) NOT NULL,
    `password` VARCHAR(255) NOT NULL,
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
CREATE TABLE `clientes` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `nombre` VARCHAR(100) NOT NULL,
    `email` VARCHAR(100) DEFAULT NULL,
    `telefono` VARCHAR(20) DEFAULT NULL,
    `fecha_nacimiento` DATE DEFAULT NULL,
    `notas` TEXT DEFAULT NULL,
    `google_id` VARCHAR(100) DEFAULT NULL,
    `foto_perfil` VARCHAR(500) DEFAULT NULL,
    `activo` TINYINT(1) NOT NULL DEFAULT 1,
    `fecha_creacion` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `fecha_actualizacion` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_clientes_email` (`email`),
    UNIQUE KEY `uk_clientes_google_id` (`google_id`),
    INDEX `idx_clientes_telefono` (`telefono`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
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
    `precio_final` DECIMAL(10, 2) DEFAULT NULL,
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
CREATE TABLE `horarios_barberos` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `barbero_id` INT UNSIGNED NOT NULL,
    `dia_semana` TINYINT UNSIGNED NOT NULL,
    `hora_inicio` TIME NOT NULL DEFAULT '10:00:00',
    `hora_fin` TIME NOT NULL DEFAULT '20:00:00',
    `activo` TINYINT(1) NOT NULL DEFAULT 1,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_barbero_dia` (`barbero_id`, `dia_semana`),
    INDEX `idx_horarios_barbero` (`barbero_id`),
    CONSTRAINT `fk_horarios_barbero` FOREIGN KEY (`barbero_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
CREATE TABLE `dias_bloqueados` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `barbero_id` INT UNSIGNED NOT NULL,
    `fecha` DATE NOT NULL,
    `motivo` VARCHAR(255) NOT NULL DEFAULT 'Día de descanso',
    `todo_el_dia` TINYINT(1) NOT NULL DEFAULT 1,
    `hora_inicio` TIME DEFAULT NULL,
    `hora_fin` TIME DEFAULT NULL,
    `creado_por` INT UNSIGNED DEFAULT NULL,
    `fecha_creacion` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_barbero_fecha` (`barbero_id`, `fecha`),
    INDEX `idx_bloqueos_fecha` (`fecha`),
    CONSTRAINT `fk_bloqueos_barbero` FOREIGN KEY (`barbero_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_bloqueos_creador` FOREIGN KEY (`creado_por`) REFERENCES `usuarios` (`id`) ON DELETE
    SET NULL ON UPDATE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
CREATE TABLE `logs_actividad` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `usuario_id` INT UNSIGNED DEFAULT NULL,
    `accion` VARCHAR(50) NOT NULL,
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
SET FOREIGN_KEY_CHECKS = 1;
-- ============================================================
-- FIN DEL SCRIPT DE ESTRUCTURA
-- ============================================================