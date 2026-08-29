<?php
/**
 * KORTZEN - Google reCAPTCHA Verification Helper
 */
require_once __DIR__ . '/../config.php';

/**
 * Verifica el token de reCAPTCHA v2 / v3 contra el servicio de Google
 * 
 * @param string $recaptchaResponse Token entregado por el frontend
 * @param string $remoteIp IP del usuario
 * @return bool true si es válido o si reCAPTCHA no está configurado
 */
function verificarRecaptcha($recaptchaResponse, $remoteIp = '') {
    if (!defined('RECAPTCHA_SECRET_KEY') || empty(RECAPTCHA_SECRET_KEY)) {
        return true;
    }

    if (empty($recaptchaResponse)) {
        return false;
    }

    if (empty($remoteIp)) {
        $remoteIp = $_SERVER['REMOTE_ADDR'] ?? '';
    }

    try {
        $url = 'https://www.google.com/recaptcha/api/siteverify';
        $data = [
            'secret' => RECAPTCHA_SECRET_KEY,
            'response' => $recaptchaResponse,
            'remoteip' => $remoteIp
        ];

        $options = [
            'http' => [
                'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
                'method'  => 'POST',
                'content' => http_build_query($data),
                'timeout' => 5
            ]
        ];

        $context = stream_context_create($options);
        $result = @file_get_contents($url, false, $context);

        if ($result === false) {
            // Fallback con cURL si file_get_contents falla
            if (function_exists('curl_init')) {
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $url);
                curl_setopt($ch, CURLOPT_POST, 1);
                curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_TIMEOUT, 5);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
                $result = curl_exec($ch);
                curl_close($ch);
            }
        }

        if ($result) {
            $json = json_decode($result, true);
            if (isset($json['success']) && $json['success'] === true) {
                // Para v3, verificar score mínimo (0.3)
                if (isset($json['score']) && floatval($json['score']) < 0.3) {
                    return false;
                }
                return true;
            }
        }
    } catch (Exception $e) {
        error_log("Error validando reCAPTCHA: " . $e->getMessage());
    }

    return false;
}
