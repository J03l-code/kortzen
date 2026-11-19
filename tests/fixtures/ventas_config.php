<?php
// Dependencias aisladas para ejecutar los endpoints reales sin acceder a producción.
$peticionTest = json_decode(file_get_contents(__DIR__ . '/request.json'), true, 512, JSON_THROW_ON_ERROR);
require_once $peticionTest['helper'];
require_once dirname($peticionTest['helper']) . '/reasignacion.php';
if (session_status() === PHP_SESSION_NONE) session_start();
$_POST = $peticionTest['post'];
$_GET = $peticionTest['get'] ?? [];
$_SERVER['HTTP_ACCEPT'] = 'application/json';
$_SERVER['REQUEST_METHOD'] = 'POST';
$_SESSION = ['user_id' => $peticionTest['operador'] ?? 1, 'user_rol' => $peticionTest['rol'] ?? 'admin', 'csrf_token' => 'token-pruebas'];

class VentasEndpointTestPDO extends PDO
{
    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        if (str_contains($query,'ON DUPLICATE KEY UPDATE')) {
            $query=preg_replace('/ON DUPLICATE KEY UPDATE[\s\S]*$/',
                'ON CONFLICT(barbero_id,dia_semana) DO UPDATE SET hora_inicio=excluded.hora_inicio,hora_fin=excluded.hora_fin,activo=excluded.activo',$query);
        }
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

function getUsuarioSucursalesIds($id): array
{
    $usuario = query('SELECT rol,sucursal_id FROM usuarios WHERE id = ?', [$id])[0] ?? [];
    if (($usuario['rol'] ?? '') === 'admin') return array_map('intval', array_column(query('SELECT id FROM sucursales'), 'id'));
    return array_map('intval', array_column(query('SELECT sucursal_id FROM usuarios_sucursales WHERE usuario_id = ?', [$id]), 'sucursal_id')) ?: [(int) ($usuario['sucursal_id'] ?? 0)];
}
function canManageSchedules(): bool { return isAdminTecnico() || isAdminLocal(); }
