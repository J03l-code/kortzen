<?php
require_once 'config.php';

if (isset($_SESSION['user_id'])) {
    registrarLog('LOGOUT', 'usuarios', $_SESSION['user_id'], 'Cierre de sesión de usuario');
}

// Destruir todas las variables de sesión
$_SESSION = array();

// Destruir la cookie de sesión y cookies PWA persistentes
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time() - 3600, '/');
}
setcookie('kortzen_pwa_client_id', '', time() - 3600, '/');
setcookie('kortzen_pwa_user_id', '', time() - 3600, '/');

// Destruir la sesión
session_destroy();

// Redirigir al login
header('Location: login.php');
exit;
