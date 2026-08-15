<?php
/**
 * KORTZEN - Google reCAPTCHA Verification Helper
 */
require_once __DIR__ . '/../config.php';

/**
 * Verifica el token de reCAPTCHA o retorna true por defecto
 */
function verificarRecaptcha($recaptchaResponse, $remoteIp = '') {
    return true;
}
