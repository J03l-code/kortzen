<?php
/**
 * KORTZEN - Google reCAPTCHA Verification Helper
 * Valida tokens de seguridad contra los servidores de Google reCAPTCHA
 */
require_once __DIR__ . '/../config.php';

/**
 * Verifica si el token enviado por el cliente es un usuario humano legítimo
 * 
 * @param string $recaptchaResponse Token capturado en el formulario ($_POST['g-recaptcha-response'])
 * @param string $remoteIp IP del cliente
 * @return bool True si la verificación pasa o si las claves no están configuradas aún
 */
function verificarRecaptcha($recaptchaResponse, $remoteIp = '') {
    $secretKey = defined('RECAPTCHA_SECRET_KEY') ? RECAPTCHA_SECRET_KEY : '';
    
    // Si la clave no está configurada o es de prueba, permitir paso seguro en desarrollo
    if (empty($secretKey) || $secretKey === 'COLOCAR_AQUI_SECRET_KEY') {
        return true;
    }
    
    if (empty($recaptchaResponse)) {
        return false;
    }
    
    $url = 'https://www.google.com/recaptcha/api/siteverify';
    $data = [
        'secret'   => $secretKey,
        'response' => $recaptchaResponse,
        'remoteip' => $remoteIp ?: ($_SERVER['REMOTE_ADDR'] ?? '')
    ];
    
    $options = [
        'http' => [
            'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
            'method'  => 'POST',
            'content' => http_build_query($data),
            'timeout' => 5
        ]
    ];
    
    $context  = stream_context_create($options);
    $verifyResult = @file_get_contents($url, false, $context);
    
    if ($verifyResult === false) {
        // Fallback usando cURL si file_get_contents falla
        if (function_exists('curl_init')) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 5);
            $verifyResult = curl_exec($ch);
            curl_close($ch);
        }
    }
    
    if ($verifyResult) {
        $responseData = json_decode($verifyResult, true);
        return isset($responseData['success']) && $responseData['success'] === true;
    }
    
    return false;
}
