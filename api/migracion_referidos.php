<?php
/**
 * KORTZEN - Migración del Sistema de Referidos y Configuración de Puntos
 */
require_once __DIR__ . '/../config.php';

try {
    $pdo = getConnection();
    
    // 1. Crear o asegurar la tabla `configuracion`
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS configuracion (
            id INT AUTO_INCREMENT PRIMARY KEY,
            clave VARCHAR(50) NOT NULL UNIQUE,
            valor TEXT NOT NULL,
            descripcion VARCHAR(255) NULL,
            fecha_actualizacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    // Configuración por defecto
    $defaultConfigs = [
        ['puntos_por_corte', '100', 'Puntos otorgados al cliente por cada cita/corte completado'],
        ['puntos_por_referido', '200', 'Puntos otorgados al cliente referente por cada referido exitoso'],
        ['descuento_referido_amigo', '2.00', 'Descuento ($) en la primera reserva del amigo que aplica el código'],
        ['descuento_referente', '2.00', 'Descuento ($) otorgado al cliente referente para su próxima cita']
    ];

    $stmtCfg = $pdo->prepare("INSERT INTO configuracion (clave, valor, descripcion) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE descripcion = VALUES(descripcion)");
    foreach ($defaultConfigs as $cfg) {
        $stmtCfg->execute($cfg);
    }
    echo "✅ Tabla configuracion creada y poblada con éxito.<br>";

    // 2. Agregar columna `codigo_referido` a la tabla `clientes`
    $stmtCol = $pdo->query("SHOW COLUMNS FROM clientes LIKE 'codigo_referido'");
    if (!$stmtCol->fetch()) {
        $pdo->exec("ALTER TABLE clientes ADD COLUMN codigo_referido VARCHAR(30) UNIQUE NULL AFTER puntos");
        echo "✅ Columna 'codigo_referido' agregada a la tabla clientes.<br>";
    }

    // 3. Limpiar prefijo 'KORTZEN-' en códigos existentes
    try {
        $pdo->exec("UPDATE clientes SET codigo_referido = REPLACE(codigo_referido, 'KORTZEN-', '') WHERE codigo_referido LIKE 'KORTZEN-%'");
    } catch (Exception $exClean) {}

    // 4. Generar código de referido único para todos los clientes existentes que no tengan uno
    $stmtClients = $pdo->query("SELECT id, nombre, email FROM clientes WHERE codigo_referido IS NULL OR codigo_referido = ''");
    $clientesSinCodigo = $stmtClients->fetchAll(PDO::FETCH_ASSOC);

    $stmtUpdCode = $pdo->prepare("UPDATE clientes SET codigo_referido = ? WHERE id = ?");

    foreach ($clientesSinCodigo as $c) {
        $nombreLimpio = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', explode(' ', trim($c['nombre']))[0]));
        if (empty($nombreLimpio)) $nombreLimpio = 'CLIENTE';
        
        $codigoBase = $nombreLimpio . rand(100, 999);
        
        // Garantizar unicidad
        $counter = 1;
        $codigoFinal = $codigoBase;
        while (true) {
            $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM clientes WHERE codigo_referido = ?");
            $stmtCheck->execute([$codigoFinal]);
            if ($stmtCheck->fetchColumn() == 0) break;
            $codigoFinal = $nombreLimpio . rand(100, 999);
        }

        $stmtUpdCode->execute([$codigoFinal, $c['id']]);
    }
    echo "✅ Códigos de referidos cortos generados.<br>";

    // 4. Crear tabla `referidos`
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS referidos (
            id INT AUTO_INCREMENT PRIMARY KEY,
            referente_id INT NOT NULL,
            referido_id INT NULL,
            codigo_usado VARCHAR(30) NOT NULL,
            cita_id INT NULL,
            descuento_aplicado DECIMAL(10,2) DEFAULT 0.00,
            puntos_otorgados INT DEFAULT 0,
            estado ENUM('pendiente', 'completado', 'cancelado') DEFAULT 'pendiente',
            fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP,
            KEY idx_referente (referente_id),
            KEY idx_referido (referido_id),
            KEY idx_codigo (codigo_usado)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
    echo "✅ Tabla 'referidos' creada con éxito.<br>";

    echo "<br><strong>🎉 Migración de Referidos Completada Exitosamente.</strong>";

} catch (Exception $e) {
    echo "❌ Error en migración: " . $e->getMessage();
}
