<?php
/**
 * php tests/ventas_endpoints_test.php
 * Ejecuta copias sin modificaciones de las APIs en procesos PHP y una BD SQLite desechable.
 * Las migraciones y los bloqueos concurrentes de MySQL requieren validación adicional en MySQL.
 */
$origen = dirname(__DIR__);
$temporal = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'kortzen-ventas-' . bin2hex(random_bytes(8));
$archivos = [];
mkdir($temporal);
mkdir($temporal . '/api');
mkdir($temporal . '/includes');

function comprobarEndpoint(bool $condicion, string $mensaje): void
{
    if (!$condicion) throw new RuntimeException($mensaje);
}

function fixtureEndpoint(string $ruta): PDO
{
    $pdo = new PDO('sqlite:' . $ruta, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $pdo->exec('CREATE TABLE usuarios (id INTEGER PRIMARY KEY, nombre TEXT, rol TEXT, activo INTEGER, sucursal_id INTEGER, comision_productos DECIMAL)');
    $pdo->exec("INSERT INTO usuarios VALUES (1, 'Caja', 'admin', 1, 1, 0), (2, 'Recepción', 'recepcion', 1, 1, 15), (3, 'Barbero', 'barbero', 1, 1, 10)");
    $pdo->exec('CREATE TABLE usuarios_sucursales (usuario_id INTEGER, sucursal_id INTEGER)');
    $pdo->exec('CREATE TABLE inventario (id INTEGER PRIMARY KEY, producto TEXT, cantidad INTEGER, precio DECIMAL, stock_minimo INTEGER, sucursal_id INTEGER, unidad TEXT, categoria TEXT, descripcion TEXT)');
    $pdo->exec("INSERT INTO inventario VALUES (1, 'Cera', 10, 12.5, 2, 1, 'unidades', 'General', ''), (2, 'Champú', 1, 8, 2, 1, 'unidades', 'General', ''), (3, 'Otra sucursal', 5, 10, 2, 2, 'unidades', 'General', '')");
    $pdo->exec('CREATE TABLE ventas_productos (id INTEGER PRIMARY KEY, cita_id INTEGER, producto_id INTEGER, cantidad INTEGER, precio_unitario DECIMAL,
        sucursal_id INTEGER, usuario_id INTEGER, registrado_por INTEGER, vendedor_nombre TEXT, comision_porcentaje DECIMAL, fecha DATETIME DEFAULT CURRENT_TIMESTAMP)');
    $pdo->exec('CREATE TABLE citas (id INTEGER PRIMARY KEY, cliente_id INTEGER, servicio_id INTEGER, barbero_id INTEGER, sucursal_id INTEGER, estado TEXT, precio_final DECIMAL, propina DECIMAL DEFAULT 0)');
    $pdo->exec("INSERT INTO citas VALUES (42, 1, 1, 3, 1, 'confirmada', 15, 0)");
    $pdo->exec('CREATE TABLE clientes (id INTEGER PRIMARY KEY, nombre TEXT, puntos INTEGER)');
    $pdo->exec("INSERT INTO clientes VALUES (1, 'Cliente', 0)");
    $pdo->exec('CREATE TABLE servicios (id INTEGER PRIMARY KEY, nombre TEXT)');
    $pdo->exec("INSERT INTO servicios VALUES (1, 'Corte')");
    $pdo->exec('CREATE TABLE configuracion (clave TEXT, valor TEXT)');
    $pdo->exec("INSERT INTO configuracion VALUES ('puntos_por_corte', '100')");
    $pdo->exec('CREATE TABLE referidos (id INTEGER PRIMARY KEY, cita_id INTEGER, estado TEXT, referente_id INTEGER, puntos_otorgados INTEGER)');
    $pdo->exec('ALTER TABLE usuarios ADD COLUMN comision_porcentaje DECIMAL DEFAULT 50');
    $pdo->exec('ALTER TABLE usuarios ADD COLUMN comision_fin_semana DECIMAL DEFAULT 50');
    $pdo->exec("ALTER TABLE clientes ADD COLUMN telefono TEXT DEFAULT '0999999999'");
    $pdo->exec('ALTER TABLE servicios ADD COLUMN precio DECIMAL DEFAULT 15');
    $pdo->exec("ALTER TABLE citas ADD COLUMN fecha_hora TEXT DEFAULT '2026-11-19 10:00:00'");
    $pdo->exec('ALTER TABLE citas ADD COLUMN fecha_completada DATETIME');
    $pdo->exec('ALTER TABLE citas ADD COLUMN notif_calificacion_enviada INTEGER DEFAULT 0');
    $pdo->exec('ALTER TABLE citas ADD COLUMN servicios_adicionales_txt TEXT');
    $pdo->exec('CREATE TABLE citas_servicios_adicionales (id INTEGER PRIMARY KEY, cita_id INTEGER, servicio_id INTEGER, nombre TEXT, precio DECIMAL)');
    $pdo->exec('CREATE TABLE sucursales (id INTEGER PRIMARY KEY, nombre TEXT, direccion TEXT)');
    $pdo->exec("INSERT INTO sucursales VALUES (1, 'Local 1', 'Dirección 1'), (2, 'Local 2', 'Dirección 2')");
    $pdo->exec('CREATE TABLE notificaciones_pwa (id INTEGER PRIMARY KEY, cliente_id INTEGER, usuario_id INTEGER, cita_id INTEGER, titulo TEXT, mensaje TEXT, url TEXT, leido INTEGER, fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP)');
    $pdo->exec('CREATE TABLE push_subscriptions (id INTEGER PRIMARY KEY, usuario_id INTEGER, cliente_id INTEGER, endpoint TEXT)');
    return $pdo;
}

function ejecutarEndpoint(string $endpoint, array $post, string $db, int $operador = 1, string $rol = 'admin'): array
{
    global $origen, $temporal;
    file_put_contents($temporal . '/request.json', json_encode([
        'post' => $post, 'db' => $db, 'operador' => $operador, 'rol' => $rol,
        'helper' => $origen . '/includes/ventas_productos.php'
    ], JSON_THROW_ON_ERROR));
    $proceso = proc_open([PHP_BINARY, '-d', 'display_errors=stderr', $temporal . '/api/' . $endpoint],
        [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $temporal . '/api');
    comprobarEndpoint(is_resource($proceso), 'No se pudo iniciar el endpoint.');
    fclose($pipes[0]);
    $salida = stream_get_contents($pipes[1]);
    $errores = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $codigo = proc_close($proceso);
    comprobarEndpoint($codigo === 0, "El endpoint terminó con error: $errores");
    $resultado = json_decode($salida, true, 512, JSON_THROW_ON_ERROR);
    comprobarEndpoint(is_array($resultado) && array_key_exists('success', $resultado), 'Respuesta JSON inválida.');
    return $resultado;
}

function comprobarCobroIntacto(PDO $pdo): void
{
    comprobarEndpoint($pdo->query('SELECT estado FROM citas WHERE id = 42')->fetchColumn() === 'confirmada', 'Una cita fallida quedó completada.');
    comprobarEndpoint((int) $pdo->query('SELECT puntos FROM clientes WHERE id = 1')->fetchColumn() === 0, 'Un cobro fallido otorgó puntos.');
    comprobarEndpoint((int) $pdo->query('SELECT SUM(cantidad) FROM inventario')->fetchColumn() === 16, 'Un cobro fallido consumió stock.');
    comprobarEndpoint((int) $pdo->query('SELECT COUNT(*) FROM ventas_productos')->fetchColumn() === 0, 'Un cobro fallido guardó ventas.');
    comprobarEndpoint((int) $pdo->query('SELECT COUNT(*) FROM notificaciones_pwa')->fetchColumn() === 0, 'Un cobro fallido no debe enviar alertas.');
}

try {
    foreach (['citas_action.php', 'inventario_action.php', 'barbero_inventario_action.php'] as $nombre) {
        $destino = $temporal . '/api/' . $nombre;
        copy($origen . '/api/' . $nombre, $destino);
        $archivos[] = $destino;
    }
    foreach (['webpush_helper.php', 'webpush_encrypt.php'] as $nombre) {
        $destino = $temporal . '/includes/' . $nombre;
        copy($origen . '/includes/' . $nombre, $destino);
        $archivos[] = $destino;
    }
    copy(__DIR__ . '/fixtures/ventas_config.php', $temporal . '/config.php');
    $archivos[] = $temporal . '/config.php';
    $archivos[] = $temporal . '/request.json';
    $base = ['action' => 'completar', 'ajax' => '1', 'id' => 42, 'propina' => '3.50',
        'csrf_token' => 'token-pruebas', 'vendedor_id' => 2, 'materiales' => [1], 'cantidades' => [2]];

    $casos = [
        'Cobro completo y segundo envío sin duplicar puntos ni ventas' => function ($pdo, $db) use ($base) {
            $r = ejecutarEndpoint('citas_action.php', $base, $db);
            comprobarEndpoint($r['success'], 'El cobro válido debe completarse: ' . json_encode($r));
            comprobarEndpoint($pdo->query('SELECT estado FROM citas')->fetchColumn() === 'completada', 'Debe completar la cita.');
            comprobarEndpoint((int) $pdo->query('SELECT puntos FROM clientes')->fetchColumn() === 100, 'Debe sumar los puntos una vez.');
            comprobarEndpoint((float) $pdo->query('SELECT propina FROM citas')->fetchColumn() === 3.5, 'Debe conservar la propina.');
            comprobarEndpoint((int) $pdo->query('SELECT usuario_id FROM ventas_productos')->fetchColumn() === 2, 'La venta debe ser del vendedor elegido.');
            comprobarEndpoint((int) $pdo->query('SELECT registrado_por FROM ventas_productos')->fetchColumn() === 1, 'Debe conservar al cajero.');
            $r = ejecutarEndpoint('citas_action.php', $base, $db);
            comprobarEndpoint(!$r['success'], 'Debe rechazar una cita ya cobrada.');
            comprobarEndpoint((int) $pdo->query('SELECT COUNT(*) FROM ventas_productos')->fetchColumn() === 1, 'No debe duplicar la venta.');
            comprobarEndpoint((int) $pdo->query('SELECT puntos FROM clientes')->fetchColumn() === 100, 'No debe duplicar los puntos.');
        },
        'Cobro sin productos permite omitir vendedor' => function ($pdo, $db) use ($base) {
            $r = ejecutarEndpoint('citas_action.php', array_replace($base, ['materiales' => [''], 'vendedor_id' => '']), $db);
            comprobarEndpoint($r['success'], 'El servicio sin productos no exige vendedor.');
            comprobarEndpoint((int) $pdo->query('SELECT COUNT(*) FROM ventas_productos')->fetchColumn() === 0, 'No debe inventar ventas.');
        },
        'Falta de vendedor no modifica cita, puntos ni stock' => function ($pdo, $db) use ($base) {
            $r = ejecutarEndpoint('citas_action.php', array_replace($base, ['vendedor_id' => '']), $db);
            comprobarEndpoint(!$r['success'], 'Debe exigir vendedor.');
            comprobarCobroIntacto($pdo);
        },
        'Stock insuficiente revierte un cobro con varios productos' => function ($pdo, $db) use ($base) {
            $r = ejecutarEndpoint('citas_action.php', array_replace($base, ['materiales' => [1, 2], 'cantidades' => [2, 3]]), $db);
            comprobarEndpoint(!$r['success'], 'Debe rechazar el exceso de stock.');
            comprobarCobroIntacto($pdo);
        },
        'Error al guardar puntos revierte cita y venta' => function ($pdo, $db) use ($base) {
            $pdo->exec("CREATE TRIGGER fallo_puntos BEFORE UPDATE ON clientes BEGIN SELECT RAISE(ABORT, 'Fallo de prueba'); END");
            $r = ejecutarEndpoint('citas_action.php', $base, $db);
            comprobarEndpoint(!$r['success'], 'El fallo de persistencia debe devolver error.');
            comprobarCobroIntacto($pdo);
        },
        'Error al leer detalles finales también revierte el cobro' => function ($pdo, $db) use ($base) {
            $pdo->exec('ALTER TABLE servicios RENAME TO servicios_no_disponibles');
            $r = ejecutarEndpoint('citas_action.php', $base, $db);
            comprobarEndpoint(!$r['success'], 'El detalle inválido debe impedir la confirmación.');
            comprobarCobroIntacto($pdo);
        },
        'Productos de otra sucursal no se cobran en esta cita' => function ($pdo, $db) use ($base) {
            $r = ejecutarEndpoint('citas_action.php', array_replace($base, ['materiales' => [3]]), $db);
            comprobarEndpoint(!$r['success'], 'Debe respetar la sucursal de la cita.');
            comprobarCobroIntacto($pdo);
        },
        'Token CSRF malformado devuelve JSON sin modificar datos' => function ($pdo, $db) use ($base) {
            $r = ejecutarEndpoint('citas_action.php', array_replace($base, ['csrf_token' => ['invalido']]), $db);
            comprobarEndpoint(!$r['success'], 'Debe rechazar tokens que no sean cadenas.');
            comprobarCobroIntacto($pdo);
        },
        'Cantidades ausentes o listas malformadas devuelven error controlado' => function ($pdo, $db) use ($base) {
            foreach ([['cantidades' => []], ['materiales' => 'invalido'], ['materiales' => [[]]]] as $cambio) {
                $r = ejecutarEndpoint('citas_action.php', array_replace($base, $cambio), $db);
                comprobarEndpoint(!$r['success'], 'Debe rechazar las listas malformadas.');
                comprobarCobroIntacto($pdo);
            }
        },
        'Cobro conserva adicionales, descuento, recibo y programación de calificación' => function ($pdo, $db) use ($base) {
            $pdo->exec("INSERT INTO servicios VALUES (2, 'Barba', 5.50)");
            $r = ejecutarEndpoint('citas_action.php', array_replace($base, [
                'servicios_adicionales' => [2], 'precios_materiales' => ['11.00']
            ]), $db);
            comprobarEndpoint($r['success'], 'Debe completar el cobro con adicionales y descuento.');
            $recibo = $r['recibo'];
            comprobarEndpoint((float) $recibo['total_servicios'] === 20.5, 'Debe sumar el servicio adicional.');
            comprobarEndpoint((float) $recibo['total_productos'] === 22.0, 'Debe usar el precio rebajado de $11 por unidad.');
            comprobarEndpoint((float) $recibo['total_a_cobrar'] === 46.0, 'Debe sumar servicios, productos y propina.');
            comprobarEndpoint((float) $recibo['comision_barbero'] === 10.25, 'La comisión de servicios debe incluir el adicional.');
            comprobarEndpoint((int) $pdo->query('SELECT usuario_id FROM ventas_productos')->fetchColumn() === 2, 'El producto debe acreditarse a recepción.');
            comprobarEndpoint((bool) $pdo->query('SELECT fecha_completada FROM citas')->fetchColumn(), 'Debe conservar la fecha de finalización.');
            comprobarEndpoint((int) $pdo->query('SELECT notif_calificacion_enviada FROM citas')->fetchColumn() === 0, 'La notificación a los 30 minutos debe quedar pendiente.');
            $consulta = ejecutarEndpoint('citas_action.php', ['action' => 'get_recibo', 'id' => 42, 'ajax' => '1'], $db);
            comprobarEndpoint($consulta['success'] && (float) $consulta['recibo']['total_a_cobrar'] === 46.0, 'La consulta posterior del recibo debe conservar el total.');
        },
        'Stock bajo alerta sólo a administración y barberos después de confirmar' => function ($pdo, $db) {
            $r = ejecutarEndpoint('inventario_action.php', ['action' => 'sell', 'ajax' => '1', 'csrf_token' => 'token-pruebas',
                'id' => 2, 'cantidad' => 1, 'precio_venta' => '6.50', 'vendedor_id' => 2], $db);
            comprobarEndpoint($r['success'], 'Debe aceptar el formulario actual con action sell.');
            $ids = array_map('intval', $pdo->query('SELECT usuario_id FROM notificaciones_pwa ORDER BY usuario_id')->fetchAll(PDO::FETCH_COLUMN));
            comprobarEndpoint($ids === [1, 3], 'Recepción y clientes no deben recibir alertas de stock bajo.');
            comprobarEndpoint((float) $pdo->query('SELECT precio_unitario FROM ventas_productos')->fetchColumn() === 6.5, 'Debe conservar el descuento del inventario.');
        },
        'Inventario y dashboard registran ventas con el mismo criterio' => function ($pdo, $db) {
            foreach (['inventario_action.php', 'barbero_inventario_action.php'] as $api) {
                $r = ejecutarEndpoint($api, ['action' => 'registrar_venta', 'ajax' => '1', 'csrf_token' => 'token-pruebas',
                    'producto_id' => 1, 'cantidad' => 1, 'vendedor_id' => 2], $db);
                comprobarEndpoint($r['success'], 'Ambas APIs deben registrar la venta.');
            }
            comprobarEndpoint((int) $pdo->query('SELECT COUNT(*) FROM ventas_productos WHERE usuario_id = 2 AND registrado_por = 1')->fetchColumn() === 2, 'Ambas ventas deben atribuirse correctamente.');
            comprobarEndpoint((int) $pdo->query('SELECT cantidad FROM inventario WHERE id = 1')->fetchColumn() === 8, 'Debe descontar una unidad por venta.');
        }
    ];
    $indice = 0;
    foreach ($casos as $nombre => $prueba) {
        $db = $temporal . '/caso-' . (++$indice) . '.sqlite';
        $archivos[] = $db;
        $pdo = fixtureEndpoint($db);
        try {
            $prueba($pdo, $db);
        } finally {
            $pdo = null;
        }
        echo "OK: $nombre\n";
    }
    echo count($casos) . " pruebas de endpoints completadas.\n";
} finally {
    $pdo = null;
    foreach (array_reverse($archivos) as $archivo) {
        if (is_file($archivo)) unlink($archivo);
    }
    rmdir($temporal . '/includes');
    rmdir($temporal . '/api');
    rmdir($temporal);
}
