<?php
// Dependencias aisladas para ejecutar los endpoints reales sin acceder a producción.
$peticionTest = json_decode(file_get_contents(__DIR__ . '/request.json'), true, 512, JSON_THROW_ON_ERROR);
require_once $peticionTest['helper'];
if (session_status() === PHP_SESSION_NONE) session_start();
$_POST = $peticionTest['post'];
$_GET = [];
$_SERVER['HTTP_ACCEPT'] = 'application/json';
$_SESSION = ['user_id' => $peticionTest['operador'] ?? 1, 'user_rol' => $peticionTest['rol'] ?? 'admin', 'csrf_token' => 'token-pruebas'];

class VentasEndpointTestPDO extends PDO
{
    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        return parent::prepare(str_replace(' FOR UPDATE', '', $query), $options);
    }

    public function query(string $query, ?int $fetchMode = null, mixed ...$fetchModeArgs): PDOStatement|false
    {
        if (preg_match('/^SHOW COLUMNS FROM ([a-z_]+)$/', $query, $matches)) {
            $query = "SELECT name FROM pragma_table_info('" . $matches[1] . "')";
        }
        return $fetchMode === null ? parent::query($query) : parent::query($query, $fetchMode, ...$fetchModeArgs);
    }
}

function getConnection(): PDO
{
    static $pdo;
    global $peticionTest;
    if (!$pdo) {
        $pdo = new VentasEndpointTestPDO('sqlite:' . $peticionTest['db'], null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]);
        $pdo->sqliteCreateFunction('NOW', fn () => date('Y-m-d H:i:s'));
    }
    return $pdo;
}
function isLoggedIn(): bool { return !empty($_SESSION['user_id']); }
function getCurrentUser() { return query('SELECT * FROM usuarios WHERE id = ?', [$_SESSION['user_id']])[0] ?? null; }
function isAdminTecnico(): bool { return $_SESSION['user_rol'] === 'admin'; }
function isAdminLocal(): bool { return $_SESSION['user_rol'] === 'admin_local'; }
function canManageInventory(): bool { return isAdminTecnico() || isAdminLocal(); }
function verifyCSRFToken($token): bool { return hash_equals($_SESSION['csrf_token'], $token); }
function registrarLog(...$args): void {}
function query($sql, $params = []): array
{
    $stmt = getConnection()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}
