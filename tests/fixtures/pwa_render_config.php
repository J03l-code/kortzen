<?php
// Sólo para renderizar la PWA con datos ficticios y fallos controlados; no carga .env.
require_once PWA_RENDER_SOURCE . '/includes/ventas_productos.php';
if (session_status() === PHP_SESSION_NONE) session_start();
date_default_timezone_set('America/Guayaquil');
$_SESSION = ['user_id'=>1,'user_rol'=>'admin','csrf_token'=>'pwa-render-test'];
function requireLogin(): void {}
function getCurrentUser(): array { return ['id'=>1,'nombre'=>'Admin de prueba','rol'=>'admin','activo'=>1,'sucursal_id'=>1,'email'=>'admin@example.test']; }
function getUsuarioSucursalesIds($id=null): array { return [1]; }
function getUsuarioSucursales($id=null): array { return [['id'=>1,'nombre'=>'Sucursal de prueba','direccion'=>'Entorno local']]; }
function generateCSRFToken(): string { return 'pwa-render-test'; }
function isAdminTecnico(): bool { return true; }
function formatPhoneDisplay($phone): string { return (string)$phone; }
function formatPhoneForWhatsapp($phone): string { return preg_replace('/\D/','',(string)$phone); }
function getConnection(): PDO
{
    static $pdo;
    if (!$pdo) {
        $pdo=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
        $pdo->exec('CREATE TABLE usuarios (id INTEGER,nombre TEXT,rol TEXT,activo INTEGER)');
        $pdo->exec("INSERT INTO usuarios VALUES (1,'Admin de prueba','admin',1)");
    }
    return $pdo;
}
function query($sql,$params=[]): array
{
    // Reproduce el corte que causaba el UNION entre collations heredadas de MySQL.
    if (str_contains($sql,'UNION ALL SELECT v.usuario_id')) {
        throw new PDOException('SQLSTATE[HY000]: 1271 Illegal mix of collations for operation UNION');
    }
    return [];
}
