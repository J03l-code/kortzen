<?php
/**
 * KORTZEN - Helper para Envío de Correos Electrónicos (Diseño Blanco y Negro Minimalista)
 */

require_once __DIR__ . '/../config.php';

/**
 * Enviar correo a través de SMTP Sockets (Sin dependencias externas)
 */
function enviarCorreoSMTPDirecto($toEmail, $subject, $htmlMessage, $smtpConfig) {
    $host = $smtpConfig['smtp_host'] ?? 'smtp.hostinger.com';
    $port = intval($smtpConfig['smtp_port'] ?? 465);
    $username = $smtpConfig['smtp_user'] ?? 'info@kortzen.com';
    $password = $smtpConfig['smtp_pass'] ?? 'Kortzen2026!';
    $fromName = "KORTZEN Barbería";

    $socketHost = ($port == 465) ? "ssl://{$host}" : $host;
    $socket = @fsockopen($socketHost, $port, $errno, $errstr, 3);

    if (!$socket) {
        return false;
    }
    stream_set_timeout($socket, 3);

    $read = function($socket) {
        $response = '';
        while ($str = fgets($socket, 515)) {
            $response .= $str;
            if (substr($str, 3, 1) == ' ') break;
        }
        return $response;
    };

    $send = function($socket, $cmd) use ($read) {
        fputs($socket, $cmd . "\r\n");
        return $read($socket);
    };

    $read($socket); // banner
    $send($socket, "EHLO " . gethostname());

    if ($port == 587) {
        $send($socket, "STARTTLS");
        stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
        $send($socket, "EHLO " . gethostname());
    }

    $authRes = $send($socket, "AUTH LOGIN");
    if (substr($authRes, 0, 3) != '334') { fclose($socket); return false; }

    $send($socket, base64_encode($username));
    $passRes = $send($socket, base64_encode($password));
    if (substr($passRes, 0, 3) != '235') { fclose($socket); return false; }

    $send($socket, "MAIL FROM: <{$username}>");
    $send($socket, "RCPT TO: <{$toEmail}>");
    $send($socket, "DATA");

    $headers  = "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= "From: {$fromName} <{$username}>\r\n";
    $headers .= "To: <{$toEmail}>\r\n";
    $headers .= "Subject: {$subject}\r\n";
    $headers .= "Date: " . date('r') . "\r\n";

    $messageData = $headers . "\r\n" . $htmlMessage . "\r\n.";
    $dataRes = $send($socket, $messageData);

    $send($socket, "QUIT");
    fclose($socket);

    return (substr($dataRes, 0, 3) == '250');
}

/**
 * Enviar correo de confirmación de reserva
 */
function enviarCorreoReserva($toEmail, $clienteNombre, $datosCita)
{
    if (empty($toEmail)) return false;

    $subject = "Confirmación de Cita - KORTZEN Barbería";

    $servicio = htmlspecialchars($datosCita['servicio'] ?? 'Servicio de Barbería');
    $barbero = htmlspecialchars($datosCita['barbero'] ?? 'Barbero Profesional');
    $fecha = htmlspecialchars($datosCita['fecha'] ?? '');
    $hora = htmlspecialchars($datosCita['hora'] ?? '');
    $precio = htmlspecialchars($datosCita['precio'] ?? '0.00');
    $nombreCliente = htmlspecialchars($clienteNombre ?? 'Cliente');

    $message = "
    <!DOCTYPE html>
    <html lang='es'>
    <head>
        <meta charset='UTF-8'>
        <title>Confirmación de Cita - KORTZEN</title>
        <style>
            body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; background-color: #F8F9FA; color: #111111; margin: 0; padding: 30px 15px; }
            .container { max-width: 540px; margin: 0 auto; background-color: #FFFFFF; border: 1px solid #EAEAEA; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.05); }
            .header { background-color: #000000; padding: 28px 20px; text-align: center; }
            .logo { color: #FFFFFF; font-size: 22px; font-weight: 900; letter-spacing: 4px; text-transform: uppercase; text-decoration: none; }
            .content { padding: 36px 30px; line-height: 1.6; }
            .title { color: #111111; margin: 0 0 10px 0; font-size: 22px; font-weight: 800; text-align: center; letter-spacing: -0.02em; }
            .subtitle { color: #666666; font-size: 14px; text-align: center; margin-bottom: 26px; font-weight: 400; }
            .details-box { background: #FAFAFA; border: 1px solid #EEEEEE; border-radius: 12px; padding: 18px 22px; margin: 24px 0; }
            .detail-row { border-bottom: 1px solid #EEEEEE; padding: 12px 0; font-size: 14px; display: flex; justify-content: space-between; align-items: center; }
            .detail-row:last-child { border-bottom: none; }
            .detail-label { color: #888888; font-weight: 600; text-transform: uppercase; font-size: 11px; letter-spacing: 1px; }
            .detail-value { color: #111111; font-weight: 700; font-size: 14px; text-align: right; }
            .btn { display: block; width: 210px; margin: 30px auto 10px auto; background-color: #000000; color: #FFFFFF; padding: 14px 24px; text-decoration: none; border-radius: 50px; font-weight: 700; text-align: center; font-size: 12px; text-transform: uppercase; letter-spacing: 1.5px; }
            .location-note { font-size: 12px; color: #777777; text-align: center; margin-top: 24px; border-top: 1px solid #F0F0F0; padding-top: 18px; }
            .footer { text-align: center; padding: 22px; font-size: 11px; color: #999999; background: #FAFAFA; border-top: 1px solid #EEEEEE; text-transform: uppercase; letter-spacing: 1px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <span class='logo'>KORTZEN</span>
            </div>
            <div class='content'>
                <h2 class='title'>Cita Confirmada</h2>
                <p class='subtitle'>Hola <strong>$nombreCliente</strong>, tu reserva ha sido registrada correctamente.</p>
                
                <div class='details-box'>
                    <div class='detail-row'><span class='detail-label'>Servicio</span><span class='detail-value'>$servicio</span></div>
                    <div class='detail-row'><span class='detail-label'>Barbero</span><span class='detail-value'>$barbero</span></div>
                    <div class='detail-row'><span class='detail-label'>Fecha</span><span class='detail-value'>$fecha</span></div>
                    <div class='detail-row'><span class='detail-label'>Hora</span><span class='detail-value'>$hora</span></div>
                    <div class='detail-row'><span class='detail-label'>Total</span><span class='detail-value'>$$precio</span></div>
                </div>
                
                <div class='location-note'>
                    Ubicación: <strong>KORTZEN Llano Chico</strong><br>Por favor llega 5 minutos antes de la hora agendada.
                </div>
                
                <a href='https://kortzen.com/mis-citas.php' class='btn'>Ver mis Citas</a>
            </div>
            <div class='footer'>
                &copy; " . date('Y') . " KORTZEN Barbería • Todos los derechos reservados.
            </div>
        </div>
    </body>
    </html>
    ";

    try {
        $cfgs = [];
        try {
            $pdo = getConnection();
            $stmtCfg = $pdo->query("SELECT clave, valor FROM configuracion WHERE clave LIKE 'smtp_%'");
            $cfgs = $stmtCfg->fetchAll(PDO::FETCH_KEY_PAIR);
        } catch (Exception $eDb) {}

        if (empty($cfgs['smtp_user']) || empty($cfgs['smtp_pass'])) {
            $cfgs['smtp_host'] = 'smtp.hostinger.com';
            $cfgs['smtp_port'] = 465;
            $cfgs['smtp_user'] = 'info@kortzen.com';
            $cfgs['smtp_pass'] = 'Kortzen2026!';
        }

        $smtpOk = enviarCorreoSMTPDirecto($toEmail, $subject, $message, $cfgs);
        @file_put_contents(__DIR__ . '/../logs/email_log.txt', date('[Y-m-d H:i:s] ') . "SMTP RESERVA: Para: $toEmail | Resultado: " . ($smtpOk ? 'EXITO' : 'FALLO') . "\n", FILE_APPEND);
        if ($smtpOk) return true;
    } catch (Exception $exSmtp) {}

    $fromEmail = "info@kortzen.com";
    $headers  = "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= "From: KORTZEN Barbería <$fromEmail>\r\n";
    $headers .= "Reply-To: KORTZEN Barbería <$fromEmail>\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion() . "\r\n";

    $res = @mail($toEmail, $subject, $message, $headers, "-f $fromEmail");
    @file_put_contents(__DIR__ . '/../logs/email_log.txt', date('[Y-m-d H:i:s] ') . "MAIL NATIVO RESERVA: Para: $toEmail | Resultado: " . ($res ? 'EXITO' : 'FALLO') . "\n", FILE_APPEND);

    return $res;
}

/**
 * Enviar correo de recordatorio el día del corte
 */
function enviarCorreoRecordatorio($toEmail, $clienteNombre, $datosCita)
{
    if (empty($toEmail)) return false;

    $subject = "Recordatorio de Cita - KORTZEN Barbería";

    $servicio = htmlspecialchars($datosCita['servicio'] ?? 'Corte / Servicio');
    $barbero = htmlspecialchars($datosCita['barbero'] ?? 'Barbero Profesional');
    $hora = htmlspecialchars($datosCita['hora'] ?? '');
    $nombreCliente = htmlspecialchars($clienteNombre ?? 'Cliente');

    $message = "
    <!DOCTYPE html>
    <html lang='es'>
    <head>
        <meta charset='UTF-8'>
        <title>Recordatorio de Cita - KORTZEN</title>
        <style>
            body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; background-color: #F8F9FA; color: #111111; margin: 0; padding: 30px 15px; }
            .container { max-width: 540px; margin: 0 auto; background-color: #FFFFFF; border: 1px solid #EAEAEA; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.05); }
            .header { background-color: #000000; padding: 28px 20px; text-align: center; }
            .logo { color: #FFFFFF; font-size: 22px; font-weight: 900; letter-spacing: 4px; text-transform: uppercase; text-decoration: none; }
            .content { padding: 36px 30px; line-height: 1.6; }
            .title { color: #111111; margin: 0 0 10px 0; font-size: 22px; font-weight: 800; text-align: center; letter-spacing: -0.02em; }
            .subtitle { color: #666666; font-size: 14px; text-align: center; margin-bottom: 26px; font-weight: 400; }
            .details-box { background: #FAFAFA; border: 1px solid #EEEEEE; border-radius: 12px; padding: 18px 22px; margin: 24px 0; }
            .detail-row { border-bottom: 1px solid #EEEEEE; padding: 12px 0; font-size: 14px; display: flex; justify-content: space-between; align-items: center; }
            .detail-row:last-child { border-bottom: none; }
            .detail-label { color: #888888; font-weight: 600; text-transform: uppercase; font-size: 11px; letter-spacing: 1px; }
            .detail-value { color: #111111; font-weight: 700; font-size: 14px; text-align: right; }
            .btn { display: block; width: 210px; margin: 30px auto 10px auto; background-color: #000000; color: #FFFFFF; padding: 14px 24px; text-decoration: none; border-radius: 50px; font-weight: 700; text-align: center; font-size: 12px; text-transform: uppercase; letter-spacing: 1.5px; }
            .location-note { font-size: 12px; color: #777777; text-align: center; margin-top: 24px; border-top: 1px solid #F0F0F0; padding-top: 18px; }
            .footer { text-align: center; padding: 22px; font-size: 11px; color: #999999; background: #FAFAFA; border-top: 1px solid #EEEEEE; text-transform: uppercase; letter-spacing: 1px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <span class='logo'>KORTZEN</span>
            </div>
            <div class='content'>
                <h2 class='title'>Recordatorio de Cita</h2>
                <p class='subtitle'>Hola <strong>$nombreCliente</strong>, te recordamos que tu cita está agendada para el día de <strong>HOY</strong>.</p>
                
                <div class='details-box'>
                    <div class='detail-row'><span class='detail-label'>Hora de atención</span><span class='detail-value'>$hora</span></div>
                    <div class='detail-row'><span class='detail-label'>Servicio</span><span class='detail-value'>$servicio</span></div>
                    <div class='detail-row'><span class='detail-label'>Barbero</span><span class='detail-value'>$barbero</span></div>
                    <div class='detail-row'><span class='detail-label'>Sucursal</span><span class='detail-value'>KORTZEN Llano Chico</span></div>
                </div>
                
                <div class='location-note'>
                    Recuerda llegar 5 minutos antes para brindarte la mejor atención.
                </div>
                
                <a href='https://kortzen.com/mis-citas.php' class='btn'>Ver Detalles</a>
            </div>
            <div class='footer'>
                &copy; " . date('Y') . " KORTZEN Barbería • Todos los derechos reservados.
            </div>
        </div>
    </body>
    </html>
    ";

    try {
        $cfgs = [];
        try {
            $pdo = getConnection();
            $stmtCfg = $pdo->query("SELECT clave, valor FROM configuracion WHERE clave LIKE 'smtp_%'");
            $cfgs = $stmtCfg->fetchAll(PDO::FETCH_KEY_PAIR);
        } catch (Exception $eDb) {}

        if (empty($cfgs['smtp_user']) || empty($cfgs['smtp_pass'])) {
            $cfgs['smtp_host'] = 'smtp.hostinger.com';
            $cfgs['smtp_port'] = 465;
            $cfgs['smtp_user'] = 'info@kortzen.com';
            $cfgs['smtp_pass'] = 'Kortzen2026!';
        }

        $smtpOk = enviarCorreoSMTPDirecto($toEmail, $subject, $message, $cfgs);
        @file_put_contents(__DIR__ . '/../logs/email_log.txt', date('[Y-m-d H:i:s] ') . "SMTP RECORDATORIO: Para: $toEmail | Resultado: " . ($smtpOk ? 'EXITO' : 'FALLO') . "\n", FILE_APPEND);
        if ($smtpOk) return true;
    } catch (Exception $exSmtp) {}

    $fromEmail = "info@kortzen.com";
    $headers  = "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= "From: KORTZEN Barbería <$fromEmail>\r\n";
    $headers .= "Reply-To: KORTZEN Barbería <$fromEmail>\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion() . "\r\n";

    $res = @mail($toEmail, $subject, $message, $headers, "-f $fromEmail");
    @file_put_contents(__DIR__ . '/../logs/email_log.txt', date('[Y-m-d H:i:s] ') . "MAIL NATIVO RECORDATORIO: Para: $toEmail | Resultado: " . ($res ? 'EXITO' : 'FALLO') . "\n", FILE_APPEND);

    return $res;
}
