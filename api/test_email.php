<?php
/**
 * KORTZEN - Diagnóstico de Envío de Correos Electrónicos
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/email_helper.php';
requireLogin();
if (($_SESSION['user_rol'] ?? '') !== 'admin') {
    http_response_code(403);
    die('Acceso restringido a administradores.');
}

$email = $_GET['email'] ?? 'natyg2045@gmail.com';

echo "<h2>🧪 Diagnóstico de Correo KORTZEN</h2>";
echo "<p>Probando envío a: <strong>" . htmlspecialchars($email) . "</strong></p>";

$datosPrueba = [
    'servicio' => 'Corte KORTZEN Premium (Prueba)',
    'barbero' => 'Barbero de Prueba',
    'fecha' => date('d/m/Y'),
    'hora' => date('H:i'),
    'precio' => '15.00'
];

$resultado = enviarCorreoReserva($email, 'Cliente KORTZEN', $datosPrueba);

if ($resultado) {
    echo "<div style='color: green; font-weight: bold; font-size: 1.1rem;'>✅ PHP mail() ejecutó la orden de envío hacia {$email}.</div>";
} else {
    echo "<div style='color: red; font-weight: bold; font-size: 1.1rem;'>❌ PHP mail() no pudo enviar el correo desde este servidor.</div>";
}

echo "<div style='background: #fff3cd; color: #856404; padding: 15px; border-radius: 8px; margin-top: 15px;'>";
echo "<strong>⚠️ IMPORTANTE PARA CORREOS GMAIL (@gmail.com):</strong><br>";
echo "Google/Gmail bloquea o descarta en silencio los correos enviados por servidores de hosting (como Hostinger) si no están firmados con <strong>SMTP Autenticado</strong> (SPF/DKIM).<br>";
echo "Para solucionar esto en 1 minuto: Ingresa a <a href='../configuracion.php'>Configuración Admin</a> y coloca la contraseña de tu correo corporativo de Hostinger (ej. <code>contacto@kortzen.com</code>). ¡Con eso Gmail los entregará al 100%!</div>";

echo "<hr>";
echo "<h3>📋 Registros de Envío (logs/email_log.txt):</h3>";
$logPath = __DIR__ . '/../logs/email_log.txt';
if (file_exists($logPath)) {
    echo "<pre style='background: #222; color: #0f0; padding: 15px; border-radius: 8px;'>" . htmlspecialchars(file_get_contents($logPath)) . "</pre>";
} else {
    echo "<p>No existe el archivo de log aún.</p>";
}
