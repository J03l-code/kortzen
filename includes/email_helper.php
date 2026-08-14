<?php
/**
 * KORTZEN - Helper para Envío de Correos Electrónicos (Confirmaciones & Recordatorios)
 */

/**
 * Enviar correo de confirmación de reserva
 */
function enviarCorreoReserva($toEmail, $clienteNombre, $datosCita)
{
    if (empty($toEmail)) return false;

    $subject = "Confirmación de tu Cita - KORTZEN Barbería";

    $servicio = htmlspecialchars($datosCita['servicio'] ?? 'Servicio de Barbería');
    $barbero = htmlspecialchars($datosCita['barbero'] ?? 'Barbero Profesional');
    $fecha = htmlspecialchars($datosCita['fecha'] ?? '');
    $hora = htmlspecialchars($datosCita['hora'] ?? '');
    $precio = htmlspecialchars($datosCita['precio'] ?? '0.00');

    $message = "
    <!DOCTYPE html>
    <html lang='es'>
    <head>
        <meta charset='UTF-8'>
        <title>Confirmación de Cita - KORTZEN</title>
        <style>
            body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #0A0A0A; color: #FFFFFF; margin: 0; padding: 20px; }
            .container { max-width: 580px; margin: 0 auto; background-color: #161616; border: 1px solid #C0A062; border-radius: 12px; overflow: hidden; box-shadow: 0 10px 30px rgba(0,0,0,0.5); }
            .header { background-color: #111111; padding: 25px; text-align: center; border-bottom: 2px solid #C0A062; }
            .logo { color: #C0A062; font-size: 26px; font-weight: 800; letter-spacing: 2px; text-decoration: none; }
            .content { padding: 30px 25px; line-height: 1.6; }
            .h2-title { color: #C0A062; margin-top: 0; font-size: 20px; font-weight: 700; text-align: center; }
            .box { background: #222222; border-left: 4px solid #C0A062; padding: 18px; border-radius: 8px; margin: 20px 0; }
            .row { border-bottom: 1px solid #333333; padding: 10px 0; font-size: 14px; display: flex; justify-content: space-between; }
            .row:last-child { border-bottom: none; }
            .label { color: #AAAAAA; font-weight: 600; }
            .value { color: #FFFFFF; font-weight: 800; float: right; }
            .btn { display: block; width: 220px; margin: 25px auto 10px auto; background-color: #C0A062; color: #111111; padding: 14px 20px; text-decoration: none; border-radius: 8px; font-weight: 800; text-align: center; font-size: 15px; text-transform: uppercase; letter-spacing: 1px; }
            .footer { text-align: center; padding: 20px; font-size: 12px; color: #777777; background: #0E0E0E; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <span class='logo'>KORTZEN</span>
            </div>
            <div class='content'>
                <h2 class='h2-title'>¡Tu Cita está Confirmada! 💈</h2>
                <p style='color: #E0E0E0; font-size: 15px; text-align: center;'>Hola <strong>$clienteNombre</strong>, te esperamos para brindarte la mejor atención de barbería de autor.</p>
                
                <div class='box'>
                    <div class='row'><span class='label'>💈 Servicio:</span><span class='value'>$servicio</span></div>
                    <div class='row'><span class='label'>✂️ Barbero:</span><span class='value'>$barbero</span></div>
                    <div class='row'><span class='label'>📅 Fecha:</span><span class='value'>$fecha</span></div>
                    <div class='row'><span class='label'>🕒 Hora:</span><span class='value'>$hora</span></div>
                    <div class='row'><span class='label'>💰 Total:</span><span class='value'>$$precio</span></div>
                </div>
                
                <p style='font-size: 13px; color: #999999; text-align: center;'>
                    📍 <strong>Ubicación:</strong> KORTZEN Llano Chico • Por favor llega 5 minutos antes de tu cita.
                </p>
                
                <a href='https://kortzen.com/mis-citas.php' class='btn'>Ver mis Citas</a>
            </div>
            <div class='footer'>
                &copy; " . date('Y') . " KORTZEN Barbería • Todos los derechos reservados.
            </div>
        </div>
    </body>
    </html>
    ";

    $fromEmail = "no-reply@kortzen.com";
    $headers  = "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= "From: KORTZEN Barbería <$fromEmail>\r\n";
    $headers .= "Reply-To: KORTZEN <contacto@kortzen.com>\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion() . "\r\n";

    return @mail($toEmail, $subject, $message, $headers, "-f $fromEmail");
}

/**
 * Enviar correo de recordatorio el día del corte
 */
function enviarCorreoRecordatorio($toEmail, $clienteNombre, $datosCita)
{
    if (empty($toEmail)) return false;

    $subject = "⏰ Recordatorio: ¡Hoy es tu Cita en KORTZEN Barbería!";

    $servicio = htmlspecialchars($datosCita['servicio'] ?? 'Corte / Servicio');
    $barbero = htmlspecialchars($datosCita['barbero'] ?? 'Barbero Profesional');
    $hora = htmlspecialchars($datosCita['hora'] ?? '');

    $message = "
    <!DOCTYPE html>
    <html lang='es'>
    <head>
        <meta charset='UTF-8'>
        <title>Recordatorio de Cita - KORTZEN</title>
        <style>
            body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #0A0A0A; color: #FFFFFF; margin: 0; padding: 20px; }
            .container { max-width: 580px; margin: 0 auto; background-color: #161616; border: 1px solid #C0A062; border-radius: 12px; overflow: hidden; box-shadow: 0 10px 30px rgba(0,0,0,0.5); }
            .header { background-color: #111111; padding: 25px; text-align: center; border-bottom: 2px solid #C0A062; }
            .logo { color: #C0A062; font-size: 26px; font-weight: 800; letter-spacing: 2px; text-decoration: none; }
            .content { padding: 30px 25px; line-height: 1.6; }
            .h2-title { color: #C0A062; margin-top: 0; font-size: 20px; font-weight: 700; text-align: center; }
            .box { background: #222222; border-left: 4px solid #C0A062; padding: 18px; border-radius: 8px; margin: 20px 0; }
            .row { border-bottom: 1px solid #333333; padding: 10px 0; font-size: 14px; display: flex; justify-content: space-between; }
            .row:last-child { border-bottom: none; }
            .label { color: #AAAAAA; font-weight: 600; }
            .value { color: #FFFFFF; font-weight: 800; float: right; }
            .btn { display: block; width: 220px; margin: 25px auto 10px auto; background-color: #C0A062; color: #111111; padding: 14px 20px; text-decoration: none; border-radius: 8px; font-weight: 800; text-align: center; font-size: 15px; text-transform: uppercase; letter-spacing: 1px; }
            .footer { text-align: center; padding: 20px; font-size: 12px; color: #777777; background: #0E0E0E; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <span class='logo'>KORTZEN</span>
            </div>
            <div class='content'>
                <h2 class='h2-title'>⏰ ¡Hoy es tu Cita!</h2>
                <p style='color: #E0E0E0; font-size: 15px; text-align: center;'>Hola <strong>$clienteNombre</strong>, te recordamos que tienes tu cita agendada para el día de <strong>HOY</strong>.</p>
                
                <div class='box'>
                    <div class='row'><span class='label'>🕒 Hora de atención:</span><span class='value'>$hora</span></div>
                    <div class='row'><span class='label'>💈 Servicio:</span><span class='value'>$servicio</span></div>
                    <div class='row'><span class='label'>✂️ Barbero:</span><span class='value'>$barbero</span></div>
                    <div class='row'><span class='label'>📍 Sucursal:</span><span class='value'>KORTZEN Llano Chico</span></div>
                </div>
                
                <p style='font-size: 13px; color: #999999; text-align: center;'>
                    Recuerda llegar 5 minutos antes para brindarte la mejor experiencia.
                </p>
                
                <a href='https://kortzen.com/mis-citas.php' class='btn'>Ver Detalles</a>
            </div>
            <div class='footer'>
                &copy; " . date('Y') . " KORTZEN Barbería • Todos los derechos reservados.
            </div>
        </div>
    </body>
    </html>
    ";

    $fromEmail = "no-reply@kortzen.com";
    $headers  = "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= "From: KORTZEN Barbería <$fromEmail>\r\n";
    $headers .= "Reply-To: KORTZEN <contacto@kortzen.com>\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion() . "\r\n";

    return @mail($toEmail, $subject, $message, $headers, "-f $fromEmail");
}
