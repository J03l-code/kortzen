<?php
/**
 * Configuración de Conexión a Base de Datos
 * Sistema de Gestión de Barberías
 * 
 * IMPORTANTE: Modifica estos valores con las credenciales de tu base de datos en Hostinger
 */

// Cargar variables de entorno desde .env si existe en el servidor
if (file_exists(__DIR__ . '/.env')) {
    $env_lines = @file(__DIR__ . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($env_lines) {
        foreach ($env_lines as $line) {
            if (strpos(trim($line), '#') === 0) continue;
            list($key, $val) = explode('=', $line, 2) + [null, null];
            if ($key && $val !== null) {
                $k = trim($key);
                $v = trim($val);
                putenv("$k=$v");
                $_ENV[$k] = $v;
            }
        }
    }
}

// Configuración de la Base de Datos
$db_host = (strpos($_SERVER['HTTP_HOST'] ?? '', 'localhost') !== false) ? '195.35.61.92' : 'localhost';
define('DB_HOST', getenv('DB_HOST') ?: $db_host);
define('DB_NAME', getenv('DB_NAME') ?: 'u434851126_kortzen');
define('DB_USER', getenv('DB_USER') ?: 'u434851126_kortzenusr');
define('DB_PASS', getenv('DB_PASS') ?: 'Kortzen2026!');
define('DB_CHARSET', 'utf8mb4');

// Configuración de Zona Horaria (Ecuador)
date_default_timezone_set('America/Guayaquil');

// Configuración de la aplicación
define('SITE_URL', getenv('SITE_URL') ?: 'https://kortzen.com');
define('SITE_NAME', getenv('SITE_NAME') ?: 'KORTZEN Barbería');

// Configuración de Google reCAPTCHA
define('RECAPTCHA_SITE_KEY', getenv('RECAPTCHA_SITE_KEY') ?: '6Ldm9oUtAAAAALygbin3zWA6sx15vHe7DeJ0-Rop');
define('RECAPTCHA_SECRET_KEY', getenv('RECAPTCHA_SECRET_KEY') ?: '6Ldm9oUtAAAAAItTxMMq49FFc2Gl76ppbDsJfBIU');

// Configuración de sesión persistente (PWA 365 Días)
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_lifetime', 31536000); // 1 año en segundos
ini_set('session.gc_maxlifetime', 31536000);  // 1 año en segundos

// Iniciar sesión con parámetros persistentes de 1 año
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 31536000,
        'path' => '/',
        'domain' => '',
        'secure' => (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on'),
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
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

            // Asegurar zona horaria Ecuador (-05:00) en MySQL
            try {
                $pdo->exec("SET time_zone = '-05:00'");
            } catch (Exception $e_tz) {}

            // Auto-migración columna propina en citas
            try {
                $pdo->exec("ALTER TABLE citas ADD COLUMN propina DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER precio_final");
            } catch (Exception $e_prop) {}

            // Auto-migración tabla inventario_barbero (Stock por Barbero)
            try {
                $pdo->exec("
                    CREATE TABLE IF NOT EXISTS inventario_barbero (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        barbero_id INT NOT NULL,
                        sucursal_id INT DEFAULT NULL,
                        producto VARCHAR(255) NOT NULL,
                        cantidad DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                        unidad VARCHAR(50) DEFAULT 'unidades',
                        precio DECIMAL(10,2) DEFAULT 0.00,
                        descripcion TEXT NULL,
                        fecha_actualizacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                        INDEX idx_barbero (barbero_id)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
                ");
            } catch (Exception $e_invb) {}

            // Auto-migración columnas de horario de almuerzo fijo en usuarios (Por defecto 13:00 a 14:00 para todos)
            try {
                $pdo->exec("ALTER TABLE usuarios ADD COLUMN almuerzo_inicio TIME DEFAULT '13:00:00', ADD COLUMN almuerzo_fin TIME DEFAULT '14:00:00', ADD COLUMN almuerzo_activo TINYINT DEFAULT 1");
            } catch (Exception $e_almuerzo) {}

            try {
                $pdo->exec("UPDATE usuarios SET almuerzo_inicio = '13:00:00', almuerzo_fin = '14:00:00' WHERE almuerzo_inicio = '14:00:00' OR almuerzo_inicio IS NULL");
            } catch (Exception $e_updalm) {}

            // Auto-migración tabla push_subscriptions y columnas recordatorio_2h_enviado, asistencia_confirmada
            try {
                $pdo->exec("ALTER TABLE citas ADD COLUMN recordatorio_2h_enviado TINYINT(1) DEFAULT 0");
            } catch (Exception $e_r2h) {}

            try {
                $pdo->exec("ALTER TABLE citas ADD COLUMN asistencia_confirmada TINYINT(1) DEFAULT 0");
            } catch (Exception $e_asist) {}

            try {
                $pdo->exec("
                    CREATE TABLE IF NOT EXISTS push_subscriptions (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        cliente_id INT NULL,
                        endpoint TEXT NOT NULL,
                        p256dh TEXT NULL,
                        auth TEXT NULL,
                        fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                        INDEX idx_cliente (cliente_id)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
                ");
            } catch (Exception $e_psub) {}

        } catch (PDOException $e) {
            // Log del error (en producción, usa error_log)
            error_log("Error de conexión a la base de datos: " . $e->getMessage());

            // Si es una petición API o AJAX, devolver JSON
            $reqUri = $_SERVER['REQUEST_URI'] ?? '';
            $isApiResult = (strpos($reqUri, '/api/') !== false) ||
                (!empty($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false);

            if ($isApiResult) {
                header('Content-Type: application/json');
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Error de conexión a base de datos', 'error' => $e->getMessage()]);
                exit;
            }

            // Mostrar mensaje amigable al usuario (HTML)
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
    if (isset($_SESSION['user_id']) && !empty($_SESSION['user_id'])) {
        return true;
    }

    // Auto-restaurar sesión de Barbero/Staff desde cookie persistente (365 días)
    if (!empty($_COOKIE['kortzen_pwa_user_id'])) {
        $userId = intval($_COOKIE['kortzen_pwa_user_id']);
        if ($userId > 0) {
            try {
                $pdo = getConnection();
                $stmt = $pdo->prepare("SELECT id, nombre, email, rol, sucursal_id FROM usuarios WHERE id = ?");
                $stmt->execute([$userId]);
                $u = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($u) {
                    $_SESSION['user_id'] = $u['id'];
                    $_SESSION['user_nombre'] = $u['nombre'];
                    $_SESSION['user_email'] = $u['email'];
                    $_SESSION['user_rol'] = $u['rol'];
                    $_SESSION['sucursal_id'] = $u['sucursal_id'];
                    return true;
                }
            } catch (Exception $e) {}
        }
    }

    return false;
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
 * Asegurar que la tabla logs_actividad exista y tenga todas sus columnas
 */
function asegurarTablaLogs()
{
    static $asegurado = false;
    if ($asegurado) return;
    $asegurado = true;

    try {
        $pdo = getConnection();
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS logs_actividad (
                id INT AUTO_INCREMENT PRIMARY KEY,
                usuario_id INT NULL,
                cliente_id INT NULL,
                accion VARCHAR(50) NOT NULL DEFAULT 'INFO',
                tabla_afectada VARCHAR(50) NOT NULL DEFAULT 'sistema',
                registro_id INT NULL,
                descripcion TEXT NOT NULL,
                ip_address VARCHAR(45) NULL,
                fecha_hora DATETIME DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");

        $colsStmt = $pdo->query("SHOW COLUMNS FROM logs_actividad");
        $cols = $colsStmt ? $colsStmt->fetchAll(PDO::FETCH_COLUMN) : [];

        if (!in_array('usuario_id', $cols)) {
            @$pdo->exec("ALTER TABLE logs_actividad ADD COLUMN usuario_id INT NULL");
        }
        if (!in_array('cliente_id', $cols)) {
            @$pdo->exec("ALTER TABLE logs_actividad ADD COLUMN cliente_id INT NULL");
        }
        if (!in_array('accion', $cols)) {
            @$pdo->exec("ALTER TABLE logs_actividad ADD COLUMN accion VARCHAR(50) NOT NULL DEFAULT 'INFO'");
        }
        if (!in_array('tabla_afectada', $cols)) {
            @$pdo->exec("ALTER TABLE logs_actividad ADD COLUMN tabla_afectada VARCHAR(50) NOT NULL DEFAULT 'sistema'");
        }
        if (!in_array('registro_id', $cols)) {
            @$pdo->exec("ALTER TABLE logs_actividad ADD COLUMN registro_id INT NULL");
        }
        if (!in_array('descripcion', $cols)) {
            @$pdo->exec("ALTER TABLE logs_actividad ADD COLUMN descripcion TEXT NOT NULL");
        }
        if (!in_array('ip_address', $cols)) {
            @$pdo->exec("ALTER TABLE logs_actividad ADD COLUMN ip_address VARCHAR(45) NULL");
        }
        if (!in_array('fecha_hora', $cols)) {
            @$pdo->exec("ALTER TABLE logs_actividad ADD COLUMN fecha_hora DATETIME DEFAULT CURRENT_TIMESTAMP");
        }
    } catch (Exception $e) {
        error_log("Error al asegurar tabla de logs: " . $e->getMessage());
    }
}

/**
 * Registrar actividad en logs del sistema
 */
function registrarLog($accion, $tabla, $registro_id = 0, $descripcion = '')
{
    try {
        asegurarTablaLogs();
        $pdo = getConnection();

        $userId = $_SESSION['user_id'] ?? null;
        $clienteId = $_SESSION['cliente_id'] ?? null;
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

        $sql = "INSERT INTO logs_actividad (usuario_id, cliente_id, accion, tabla_afectada, registro_id, descripcion, ip_address, fecha_hora) 
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $userId ? intval($userId) : null,
            $clienteId ? intval($clienteId) : null,
            strtoupper(trim($accion)),
            trim($tabla),
            $registro_id ? intval($registro_id) : null,
            trim($descripcion),
            $ip
        ]);
    } catch (Exception $e) {
        error_log("Error al registrar log: " . $e->getMessage());
    }
}

/**
 * Verificar si un cliente está autenticado (Google OAuth)
 * @return bool
 */
function isClienteLoggedIn()
{
    if (isset($_SESSION['cliente_logged_in']) && $_SESSION['cliente_logged_in'] === true) {
        return true;
    }

    // Auto-restaurar sesión persistente de Cliente PWA (365 días)
    if (!empty($_COOKIE['kortzen_pwa_client_id'])) {
        $clientId = intval($_COOKIE['kortzen_pwa_client_id']);
        if ($clientId > 0) {
            try {
                $pdo = getConnection();
                $stmt = $pdo->prepare("SELECT id, nombre, email, foto_perfil, google_id FROM clientes WHERE id = ?");
                $stmt->execute([$clientId]);
                $c = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($c) {
                    $_SESSION['cliente_logged_in'] = true;
                    $_SESSION['cliente_id'] = $c['id'];
                    $_SESSION['cliente_nombre'] = $c['nombre'];
                    $_SESSION['cliente_email'] = $c['email'];
                    $_SESSION['cliente_foto'] = $c['foto_perfil'] ?? null;
                    $_SESSION['cliente_google_id'] = $c['google_id'] ?? null;
                    return true;
                }
            } catch (Exception $e) {}
        }
    }

    return false;
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

/**
 * Formatear número de teléfono para WhatsApp (Ecuador)
 * @param string $phone
 * @return string
 */
function formatPhoneForWhatsapp($phone)
{
    // 1. Eliminar todo lo que no sea número
    $clean = preg_replace('/[^0-9]/', '', $phone);

    // 2. Si está vacío, retornar vacío
    if (empty($clean))
        return '';

    // 3. Lógica para Ecuador (empezar con 09 -> 5939)
    // Si tiene 10 dígitos y empieza con 0, reemplazar 0 por 593
    if (strlen($clean) === 10 && substr($clean, 0, 1) === '0') {
        return '593' . substr($clean, 1);
    }

    // 4. Si ya empieza con 593 (ej: 593988...) se deja igual
    // 5. Si es de 9 dígitos (sin el 0 inicial), agregar 593
    if (strlen($clean) === 9) {
        return '593' . $clean;
    }

    return $clean;
}

/**
 * Verificación y disparo automático asíncrono de recordatorios de citas 2h antes
 * Se ejecuta de fondo máximo 1 vez cada 60 segundos por sesión.
 */
function checkCronRecordatorios2hAuto() {
    if (defined('IS_CRON_EXECUTION') || (isset($_GET['action']) && $_GET['action'] === 'cron')) return;
    
    $lastCheck = $_SESSION['last_cron_check_2h'] ?? 0;
    if (time() - $lastCheck < 60) return;
    $_SESSION['last_cron_check_2h'] = time();

    try {
        $host = $_SERVER['HTTP_HOST'] ?? 'kortzen.com';
        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['SERVER_PORT'] ?? 80) == 443;
        $port = $isHttps ? 443 : 80;
        $scheme = $isHttps ? 'ssl://' : '';

        $fp = @fsockopen($scheme . $host, $port, $errno, $errstr, 1);
        if ($fp) {
            stream_set_timeout($fp, 1);
            $out = "GET /api/cron_recordatorios_2h.php HTTP/1.1\r\n";
            $out .= "Host: {$host}\r\n";
            $out .= "User-Agent: KortzenAutoCron/1.0\r\n";
            $out .= "Connection: Close\r\n\r\n";
            @fwrite($fp, $out);
            @fclose($fp);
        }
    } catch (Exception $e) {}
}

// Auto-ejecución pasiva de fondo al cargar cualquier página
if (!empty($_SERVER['HTTP_HOST'])) {
    checkCronRecordatorios2hAuto();
}
