<?php
/**
 * Helper para envío de correos electrónicos
 */

function enviarCorreoReserva($toEmail, $clienteNombre, $datosCita)
{
    if (empty($toEmail))
        return false;

    $subject = "Confirmación de Cita - KORTZEN";

    $servicio = $datosCita['servicio'];
    $barbero = $datosCita['barbero'];
    $fecha = $datosCita['fecha']; // Formato legible
    $hora = $datosCita['hora'];
    $precio = $datosCita['precio'];

    // Plantilla HTML del correo
    $message = "
    <html>
    <head>
        <title>Confirmación de Cita</title>
        <style>
            body { font-family: Arial, sans-serif; background-color: #121212; color: #ffffff; padding: 20px; }
            .container { max-width: 600px; margin: 0 auto; background-color: #1E1E1E; border: 1px solid #C9A96E; border-radius: 10px; overflow: hidden; }
            .header { background-color: #000; padding: 20px; text-align: center; border-bottom: 2px solid #C9A96E; }
            .logo { color: #C9A96E; font-size: 24px; font-weight: bold; text-decoration: none; }
            .content { padding: 30px 20px; }
            .detail-row { border-bottom: 1px solid #333; padding: 10px 0; display: flex; justify-content: space-between; }
            .label { color: #888; }
            .value { color: #fff; font-weight: bold; }
            .footer { text-align: center; padding: 20px; font-size: 12px; color: #666; }
            .btn { display: inline-block; background-color: #C9A96E; color: #000; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin-top: 20px; font-weight: bold; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <span class='logo'>KORTZEN</span>
            </div>
            <div class='content'>
                <h2 style='color: #C9A96E; margin-top: 0;'>¡Tu cita está confirmada!</h2>
                <p>Hola $clienteNombre, te esperamos para tu próxima experiencia premium.</p>
                
                <div style='margin-top: 20px; background: rgba(255,255,255,0.05); padding: 15px; border-radius: 8px;'>
                    <div class='detail-row'>
                        <span class='label'>Servicio:</span>
                        <span class='value'>$servicio</span>
                    </div>
                    <div class='detail-row'>
                        <span class='label'>Barbero:</span>
                        <span class='value'>$barbero</span>
                    </div>
                    <div class='detail-row'>
                        <span class='label'>Fecha:</span>
                        <span class='value'>$fecha</span>
                    </div>
                    <div class='detail-row'>
                        <span class='label'>Hora:</span>
                        <span class='value'>$hora</span>
                    </div>
                    <div class='detail-row' style='border-bottom: none;'>
                        <span class='label'>Precio estimado:</span>
                        <span class='value'>$$precio</span>
                    </div>
                </div>
                
                <p style='margin-top: 20px; font-size: 0.9em; color: #aaa;'>
                    Por favor, llega 5 minutos antes de tu cita. Si necesitas cancelar, puedes hacerlo desde tu perfil.
                </p>
                
                <div style='text-align: center;'>
                    <a href='https://kortzenbrb.jiyanedesign.com/mis-citas.php' class='btn'>Ver mis Citas</a>
                </div>
            </div>
            <div class='footer'>
                &copy; " . date('Y') . " KORTZEN Barber Studio. Todos los derechos reservados.
            </div>
        </div>
    </body>
    </html>
    ";

    // Cabeceras
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= 'From: KORTZEN <no-reply@kortzen.com>' . "\r\n"; // Ajustar dominio real si es posible

    // Enviar
    return mail($toEmail, $subject, $message, $headers);
}
