-- Migration to add detailed profile information for barbers
-- Run this in your database SQL interface (e.g., PHPMyAdmin)
ALTER TABLE `usuarios`
ADD COLUMN `biografia` TEXT NULL
AFTER `rol`,
    ADD COLUMN `especialidades` VARCHAR(255) NULL
AFTER `biografia`,
    ADD COLUMN `foto_url` VARCHAR(255) NULL
AFTER `especialidades`;