<?php
/**
 * KORTZEN - Inicializador de Usuario Administrador (Protegido por Clave)
 */
require_once 'config.php';

if (php_sapi_name() !== 'cli' && ($_GET['key'] ?? '') !== 'KortzenAdminInit2026!') {
    http_response_code(403);
    die('Acceso no autorizado.');
}

try {
    $pdo = getConnection();
    $email = 'admin@kortzen.com';
    $password = 'Kortzen2026!'; // Contraseña de acceso
    $nombre = 'Administrador Kortzen';
    $rol = 'admin'; // Rol de administrador técnico

    // Verificar si ya existe
    $check = query("SELECT COUNT(*) as count FROM usuarios WHERE email = ?", [$email]);
    if ($check[0]['count'] > 0) {
        // Actualizar contraseña del existente
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE usuarios SET password = ?, activo = 1 WHERE email = ?");
        $stmt->execute([$hash, $email]);
        echo "✓ Contraseña de administrador actualizada con éxito a: <strong>$password</strong>";
    } else {
        // Crear administrador nuevo
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO usuarios (nombre, email, password, rol, activo) VALUES (?, ?, ?, ?, 1)");
        $stmt->execute([$nombre, $email, $hash, $rol]);
        echo "✓ Usuario administrador creado con éxito:<br>Email: <strong>$email</strong><br>Contraseña: <strong>$password</strong>";
    }
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage();
}
