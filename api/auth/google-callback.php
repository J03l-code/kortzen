<?php
/**
 * Callback de Google OAuth
 * Recibe la respuesta de Google y crea la sesión del usuario
 */

session_start();
require_once __DIR__ . '/google-config.php';
require_once __DIR__ . '/../../config.php';

// Función para mostrar errores de forma amigable
function showError($title, $message)
{
    die('
        <div style="font-family: Arial; max-width: 500px; margin: 100px auto; padding: 30px; border: 2px solid #dc3545; border-radius: 12px; background: #f8d7da; color: #721c24; text-align: center;">
            <h2>❌ ' . htmlspecialchars($title) . '</h2>
            <p>' . htmlspecialchars($message) . '</p>
            <a href="/" style="display: inline-block; margin-top: 15px; padding: 10px 20px; background: #721c24; color: white; text-decoration: none; border-radius: 6px;">
                ← Volver al inicio
            </a>
        </div>
    ');
}

// Verificar si hay error de Google
if (isset($_GET['error'])) {
    showError('Acceso Denegado', 'El usuario canceló el inicio de sesión o hubo un error: ' . ($_GET['error_description'] ?? $_GET['error']));
}

// Verificar que recibimos el código de autorización
if (!isset($_GET['code'])) {
    showError('Error de Autenticación', 'No se recibió el código de autorización de Google.');
}

// Verificar el state para prevenir CSRF
if (
    !isset($_GET['state']) ||
    !isset($_SESSION['google_oauth_state']) ||
    $_GET['state'] !== $_SESSION['google_oauth_state']
) {
    showError('Error de Seguridad', 'Token de seguridad inválido. Por favor, intenta de nuevo.');
}

// Limpiar el state de la sesión
unset($_SESSION['google_oauth_state']);

// Intercambiar código por token
$tokenData = exchangeCodeForToken($_GET['code']);
if (!$tokenData || !isset($tokenData['access_token'])) {
    error_log("Google OAuth Error: " . json_encode($tokenData));
    showError('Error de Token', 'No se pudo obtener el token de acceso de Google.');
}

// Obtener información del usuario
$googleUser = getGoogleUserInfo($tokenData['access_token']);
if (!$googleUser || !isset($googleUser['email'])) {
    showError('Error de Usuario', 'No se pudo obtener la información del usuario de Google.');
}

// Datos del usuario de Google
$googleId = $googleUser['id'];
$email = $googleUser['email'];
$nombre = $googleUser['name'] ?? ($googleUser['given_name'] ?? 'Usuario');
$fotoPerfil = $googleUser['picture'] ?? null;

try {
    $pdo = getConnection();

    // DEBUG: Mostrar qué base de datos estamos usando
    $dbInfo = $pdo->query("SELECT DATABASE() as db")->fetch();

    // DEBUG: Ver estructura de la tabla clientes
    $tableCheck = $pdo->query("SHOW TABLES LIKE 'clientes'")->fetch();
    if (!$tableCheck) {
        // La tabla no existe, crearla
        $pdo->exec("
            CREATE TABLE clientes (
                id INT AUTO_INCREMENT PRIMARY KEY,
                google_id VARCHAR(255) UNIQUE,
                email VARCHAR(255) NOT NULL UNIQUE,
                nombre VARCHAR(255),
                foto_perfil VARCHAR(500),
                telefono VARCHAR(20),
                fecha_registro DATETIME DEFAULT CURRENT_TIMESTAMP,
                ultima_sesion DATETIME,
                estado ENUM('activo', 'inactivo') DEFAULT 'activo',
                INDEX idx_google_id (google_id),
                INDEX idx_email (email)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    // DEBUG: Ver columnas de la tabla
    $columns = $pdo->query("DESCRIBE clientes")->fetchAll(PDO::FETCH_COLUMN);

    // Agregar columnas que falten
    if (!in_array('google_id', $columns)) {
        $pdo->exec("ALTER TABLE clientes ADD COLUMN google_id VARCHAR(255) UNIQUE");
    }
    if (!in_array('foto_perfil', $columns)) {
        $pdo->exec("ALTER TABLE clientes ADD COLUMN foto_perfil VARCHAR(500)");
    }
    if (!in_array('ultima_sesion', $columns)) {
        $pdo->exec("ALTER TABLE clientes ADD COLUMN ultima_sesion DATETIME");
    }

    // Buscar si el usuario ya existe (por google_id o email)
    $stmt = $pdo->prepare("SELECT * FROM clientes WHERE google_id = ? OR email = ?");
    $stmt->execute([$googleId, $email]);
    $cliente = $stmt->fetch();

    if ($cliente) {
        // Usuario existe - actualizar datos
        $stmt = $pdo->prepare("
            UPDATE clientes 
            SET google_id = ?, 
                nombre = ?, 
                foto_perfil = ?,
                ultima_sesion = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$googleId, $nombre, $fotoPerfil, $cliente['id']]);
        $clienteId = $cliente['id'];
    } else {
        // Nuevo usuario - insertar
        $stmt = $pdo->prepare("
            INSERT INTO clientes (google_id, email, nombre, foto_perfil, ultima_sesion) 
            VALUES (?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$googleId, $email, $nombre, $fotoPerfil]);
        $clienteId = $pdo->lastInsertId();
    }

    // Crear sesión del cliente
    $_SESSION['cliente_id'] = $clienteId;
    $_SESSION['cliente_nombre'] = $nombre;
    $_SESSION['cliente_email'] = $email;
    $_SESSION['cliente_foto'] = $fotoPerfil;
    $_SESSION['cliente_google_id'] = $googleId;
    $_SESSION['cliente_logged_in'] = true;

    // Regenerar ID de sesión por seguridad
    session_regenerate_id(true);

    // Redirigir a la página de éxito o inicio
    // Redirigir a la página de reservas por defecto (o dashboard si se prefiere)
    // El usuario quería reservar, así que priorizamos el flujo de reserva.
    header('Location: ' . SITE_BASE_URL . '/reservar.php');
    exit;

} catch (PDOException $e) {
    error_log("Database Error in Google Callback: " . $e->getMessage());
    // Mostrar error detallado temporalmente para diagnosticar
    showError('Error de Base de Datos', 'Error: ' . $e->getMessage());
}
