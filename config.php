<?php
/**
 * Configuración de Conexión a Base de Datos
 * Sistema de Gestión de Barberías
 * 
 * IMPORTANTE: Modifica estos valores con las credenciales de tu base de datos en Hostinger
 */

// Configuración de la Base de Datos
// Configuración de la Base de Datos
// Si estamos en localhost (entorno local), usamos la IP remota. Si estamos en producción, usamos localhost.
$db_host = (strpos($_SERVER['HTTP_HOST'] ?? '', 'localhost') !== false) ? '195.35.61.92' : 'localhost';
define('DB_HOST', $db_host);
define('DB_NAME', 'u434851126_barb');  // Nombre de tu base de datos (actualiza este valor)
define('DB_USER', 'u434851126_barb_usr');     // Usuario de base de datos (actualiza este valor)
define('DB_PASS', 'Kortzen2026');                    // Contraseña de base de datos (actualiza este valor)
define('DB_CHARSET', 'utf8mb4');

// Configuración de Zona Horaria (Ecuador)
date_default_timezone_set('America/Guayaquil');

// Configuración de la aplicación
define('SITE_URL', 'https://kortzenbrb.jiyanedesign.com');  // URL de tu sitio
define('SITE_NAME', 'Sistema de Gestión de Barberías');  // Nombre del sitio

// Configuración de sesión
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_secure', 0);      // Cambia a 1 si usas HTTPS en Hostinger

// Iniciar sesión si no está iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Función para obtener la conexión PDO
 * @return PDO|null
 */
function getConnection()
{
    static $pdo = null;

    if ($pdo === null) {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . DB_CHARSET
            ];

            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);

        } catch (PDOException $e) {
            // Log del error (en producción, usa error_log)
            error_log("Error de conexión a la base de datos: " . $e->getMessage());

            // Mostrar mensaje amigable al usuario
            die("
                <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 100px auto; padding: 20px; border: 2px solid #dc3545; border-radius: 8px; background: #f8d7da; color: #721c24;'>
                    <h2>⚠️ Error de Conexión a la Base de Datos</h2>
                    <p><strong>No se pudo conectar a la base de datos.</strong></p>
                    <p>Por favor verifica:</p>
                    <ul>
                        <li>Que el servidor MySQL esté funcionando</li>
                        <li>Que las credenciales en <code>config.php</code> sean correctas</li>
                        <li>Que la base de datos '<strong>" . DB_NAME . "</strong>' exista</li>
                        <li>Que el usuario '<strong>" . DB_USER . "</strong>' tenga permisos</li>
                    </ul>
                    <hr>
                    <small><strong>Error técnico:</strong> " . htmlspecialchars($e->getMessage()) . "</small>
                </div>
            ");
        }
    }

    return $pdo;
}

/**
 * Función helper para ejecutar consultas SELECT
 * @param string $sql
 * @param array $params
 * @return array
 */
function query($sql, $params = [])
{
    $pdo = getConnection();
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

/**
 * Función helper para ejecutar consultas INSERT/UPDATE/DELETE
 * @param string $sql
 * @param array $params
 * @return bool
 */
function execute($sql, $params = [])
{
    $pdo = getConnection();
    $stmt = $pdo->prepare($sql);
    return $stmt->execute($params);
}

/**
 * Verificar si el usuario está autenticado
 * @return bool
 */
function isLoggedIn()
{
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Redirigir si no está autenticado
 */
function requireLogin()
{
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit;
    }
}

/**
 * Obtener el usuario actual de la sesión
 * @return array|null
 */
function getCurrentUser()
{
    if (!isLoggedIn()) {
        return null;
    }

    $sql = "SELECT u.*, s.nombre as sucursal_nombre 
            FROM usuarios u 
            LEFT JOIN sucursales s ON u.sucursal_id = s.id 
            WHERE u.id = ?";

    $result = query($sql, [$_SESSION['user_id']]);
    return $result[0] ?? null;
}

/**
 * Sanitizar entrada de usuario
 * @param string $input
 * @return string
 */
function sanitize($input)
{
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

/**
 * Validar email
 * @param string $email
 * @return bool
 */
function isValidEmail($email)
{
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Generar token CSRF
 * @return string
 */
function generateCSRFToken()
{
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verificar token CSRF
 * @param string $token
 * @return bool
 */
function verifyCSRFToken($token)
{
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Registrar actividad en logs
 * @param string $accion
 * @param string $tabla
 * @param int $registro_id
 * @param string $descripcion
 */
function registrarLog($accion, $tabla, $registro_id, $descripcion)
{
    if (!isLoggedIn()) {
        return;
    }

    try {
        $pdo = getConnection();
        $sql = "INSERT INTO logs_actividad (usuario_id, accion, tabla_afectada, registro_id, descripcion) 
                VALUES (?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$_SESSION['user_id'], $accion, $tabla, $registro_id, $descripcion]);
    } catch (PDOException $e) {
        error_log("Error al registrar log: " . $e->getMessage());
    }
}

/**
 * Verificar si un cliente está autenticado (Google OAuth)
 * @return bool
 */
function isClienteLoggedIn()
{
    return isset($_SESSION['cliente_logged_in']) && $_SESSION['cliente_logged_in'] === true;
}

/**
 * Obtener datos del cliente actual
 * @return array|null
 */
function getCurrentCliente()
{
    if (!isClienteLoggedIn()) {
        return null;
    }

    return [
        'id' => $_SESSION['cliente_id'] ?? null,
        'nombre' => $_SESSION['cliente_nombre'] ?? 'Cliente',
        'email' => $_SESSION['cliente_email'] ?? '',
        'foto' => $_SESSION['cliente_foto'] ?? null,
        'google_id' => $_SESSION['cliente_google_id'] ?? null
    ];
}

/**
 * Obtener el estado de autenticación para JavaScript (JSON)
 * @return string JSON con el estado de autenticación
 */
function getAuthStateJSON()
{
    $state = [
        'isLoggedIn' => isClienteLoggedIn(),
        'user' => null
    ];

    if (isClienteLoggedIn()) {
        $state['user'] = [
            'nombre' => $_SESSION['cliente_nombre'] ?? 'Cliente',
            'email' => $_SESSION['cliente_email'] ?? '',
            'foto' => $_SESSION['cliente_foto'] ?? null
        ];
    }

    return json_encode($state);
}

// ============================================================
// SISTEMA DE PERMISOS POR ROL
// ============================================================

/**
 * Obtener el rol del usuario actual
 * @return string|null
 */
function getCurrentUserRole()
{
    return $_SESSION['user_rol'] ?? null;
}

/**
 * Verificar si el usuario es Admin Técnico
 * @return bool
 */
function isAdminTecnico()
{
    return getCurrentUserRole() === 'admin';
}

/**
 * Verificar si el usuario es Admin de Locales
 * @return bool
 */
function isAdminLocal()
{
    return getCurrentUserRole() === 'admin_local';
}

/**
 * Verificar si el usuario es Barbero
 * @return bool
 */
function isBarbero()
{
    return getCurrentUserRole() === 'barbero';
}

/**
 * Verificar si puede gestionar usuarios (crear/editar/eliminar)
 * Solo Admin Técnico
 * @return bool
 */
function canManageUsers()
{
    return isAdminTecnico();
}

/**
 * Verificar si puede ver usuarios
 * Admin Técnico y Admin Local
 * @return bool
 */
function canViewUsers()
{
    return isAdminTecnico() || isAdminLocal();
}

/**
 * Verificar si puede gestionar horarios de barberos
 * Admin Técnico y Admin Local
 * @return bool
 */
function canManageSchedules()
{
    return isAdminTecnico() || isAdminLocal();
}

/**
 * Verificar si puede gestionar inventario
 * Admin Técnico y Admin Local
 * @return bool
 */
function canManageInventory()
{
    return isAdminTecnico() || isAdminLocal();
}

/**
 * Verificar si puede gestionar sucursales (crear/eliminar)
 * Solo Admin Técnico
 * @return bool
 */
function canManageBranches()
{
    return isAdminTecnico();
}

/**
 * Verificar si puede ver logs del sistema
 * Solo Admin Técnico
 * @return bool
 */
function canViewLogs()
{
    return isAdminTecnico();
}

/**
 * Verificar si puede ver reportes y estadísticas
 * Admin Técnico y Admin Local
 * @return bool
 */
function canViewReports()
{
    return isAdminTecnico() || isAdminLocal();
}

/**
 * Obtener nombre del rol para mostrar
 * @param string $rol
 * @return string
 */
function getRolDisplayName($rol)
{
    $nombres = [
        'admin' => 'Admin Técnico',
        'admin_local' => 'Admin Locales',
        'barbero' => 'Barbero'
    ];
    return $nombres[$rol] ?? ucfirst($rol);
}

/**
 * Requerir permiso específico, redirige si no tiene acceso
 * @param bool $hasPermission
 * @param string $redirectTo
 */
function requirePermission($hasPermission, $redirectTo = 'dashboard.php')
{
    if (!$hasPermission) {
        header('Location: ' . $redirectTo . '?error=' . urlencode('No tienes permiso para acceder a esta sección.'));
        exit;
    }
}
