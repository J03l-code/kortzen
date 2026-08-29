<?php
/**
 * KORTZEN - Configuración Directa de SMTP para info@kortzen.com
 */
require_once __DIR__ . '/../config.php';
requireLogin();
if (($_SESSION['user_rol'] ?? '') !== 'admin') {
    http_response_code(403);
    die('Acceso restringido a administradores.');
}

try {
    $pdo = getConnection();
    
    $smtpConfigs = [
        ['smtp_host', 'smtp.hostinger.com', 'Servidor SMTP Hostinger'],
        ['smtp_port', '465', 'Puerto SMTP SSL'],
        ['smtp_user', 'info@kortzen.com', 'Correo Remitente SMTP'],
        ['smtp_pass', 'Kortzen2026!', 'Contraseña SMTP']
    ];

    $stmt = $pdo->prepare("INSERT INTO configuracion (clave, valor, descripcion) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE valor = VALUES(valor)");

    foreach ($smtpConfigs as $cfg) {
        $stmt->execute($cfg);
    }

    echo "<h2 style='color: green;'>✅ Credenciales SMTP para info@kortzen.com guardadas y activadas en la base de datos.</h2>";
    echo "<p>Las confirmaciones de reserva y recordatorios ahora se enviarán autenticados vía <strong>smtp.hostinger.com:465 (SSL)</strong> directamente desde info@kortzen.com.</p>";

} catch (Exception $e) {
    echo "<h2 style='color: red;'>❌ Error al guardar SMTP: " . htmlspecialchars($e->getMessage()) . "</h2>";
}
