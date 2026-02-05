<?php
// Test de diagnóstico
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<!DOCTYPE html><html><head><title>Test</title></head><body>";
echo "<h1>Diagnóstico del Sistema</h1>";

// 1. Test de config
echo "<h2>1. Test config.php</h2>";
require_once 'config.php';
echo "✓ config.php cargado<br>";
echo "✓ SITE_NAME: " . SITE_NAME . "<br>";

// 2. Test de conexión
echo "<h2>2. Test de conexión DB</h2>";
try {
    $conn = getConnection();
    echo "✓ Conexión establecida<br>";
} catch (Exception $e) {
    echo "✗ ERROR: " . $e->getMessage() . "<br>";
}

// 3. Test de login
echo "<h2>3. Test de sesión</h2>";
echo "✓ Sesión activa: " . (isLoggedIn() ? 'SÍ' : 'NO') . "<br>";

if (isLoggedIn()) {
    echo "✓ User ID: " . $_SESSION['user_id'] . "<br>";
    echo "✓ Usuario: " . $_SESSION['user_nombre'] . "<br>";
    $user = getCurrentUser();
    if ($user) {
        echo "✓ Datos de usuario obtenidos correctamente<br>";
    }
}

// 4. Test de CSS
echo "<h2>4. Test de archivos</h2>";
echo "✓ css/style.css existe: " . (file_exists('css/style.css') ? 'SÍ' : 'NO') . "<br>";
echo "✓ Tamaño CSS: " . filesize('css/style.css') . " bytes<br>";

echo "</body></html>";
