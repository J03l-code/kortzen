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

require_once __DIR__ . '/fixtures/ventas_database.php';

function ejecutarEndpoint(string $endpoint, array $post, string $db, int $operador = 1, string $rol = 'admin', array $get = []): array
{
    global $origen, $temporal;
    if (!array_key_exists('metodo_pago', $post)) $post['metodo_pago'] = 'efectivo';
    file_put_contents($temporal . '/request.json', json_encode([
        'post' => $post, 'get' => $get, 'db' => $db, 'operador' => $operador, 'rol' => $rol,
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
    foreach (['citas_action.php', 'inventario_action.php', 'barbero_inventario_action.php', 'horarios_action.php', 'reasignar_cita.php', 'calendario_citas.php', 'reportes_ventas.php'] as $nombre) {
        $destino = $temporal . '/api/' . $nombre;
        copy($origen . '/api/' . $nombre, $destino);
        $archivos[] = $destino;
    }
    foreach (['webpush_helper.php', 'webpush_encrypt.php', 'exportar_tablas.php'] as $nombre) {
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
            $pdo->exec("INSERT INTO servicios (id,nombre,precio) VALUES (2, 'Barba', 5.50)");
            $r = ejecutarEndpoint('citas_action.php', array_replace($base, [
                'servicios_adicionales' => [2], 'precios_materiales' => ['11.00']
            ]), $db);
            comprobarEndpoint($r['success'], 'Debe completar el cobro con adicionales y descuento.');
            $recibo = $r['recibo'];
            comprobarEndpoint((float) $recibo['total_servicios'] === 20.5, 'Debe sumar el servicio adicional.');
            comprobarEndpoint((float) $recibo['total_productos'] === 22.0, 'Debe usar el precio rebajado de $11 por unidad.');
            comprobarEndpoint((float) $recibo['total_a_cobrar'] === 46.0, 'Debe sumar servicios, productos y propina.');
            comprobarEndpoint((int) $pdo->query('SELECT COUNT(*) FROM cobros_caja')->fetchColumn() === 1, 'Debe registrar un solo cobro por cita.');
            comprobarEndpoint((float) $pdo->query('SELECT total FROM cobros_caja')->fetchColumn() === 46.0, 'El total de caja debe coincidir con el recibo.');
            comprobarEndpoint($recibo['metodo_pago'] === 'efectivo', 'El recibo debe conservar el método elegido.');
            comprobarEndpoint((float) $recibo['comision_barbero'] === 10.25, 'La comisión de servicios debe incluir el adicional.');
            comprobarEndpoint((int) $pdo->query('SELECT usuario_id FROM ventas_productos')->fetchColumn() === 2, 'El producto debe acreditarse a recepción.');
            comprobarEndpoint((bool) $pdo->query('SELECT fecha_completada FROM citas')->fetchColumn(), 'Debe conservar la fecha de finalización.');
            comprobarEndpoint((int) $pdo->query('SELECT notif_calificacion_enviada FROM citas')->fetchColumn() === 0, 'La notificación a los 30 minutos debe quedar pendiente.');
            $consulta = ejecutarEndpoint('citas_action.php', ['action' => 'get_recibo', 'id' => 42, 'ajax' => '1'], $db);
            comprobarEndpoint($consulta['success'] && (float) $consulta['recibo']['total_a_cobrar'] === 46.0, 'La consulta posterior del recibo debe conservar el total.');
            $pdo->exec('UPDATE citas SET precio_final=99,propina=0 WHERE id=42');
            $consulta = ejecutarEndpoint('citas_action.php', ['action'=>'get_recibo','id'=>42,'ajax'=>'1'], $db);
            comprobarEndpoint((float)$consulta['recibo']['total_a_cobrar']===46.0,'El recibo debe conservar el cobro original aunque se edite la cita después.');
        },
        'Stock bajo alerta sólo a administración y barberos después de confirmar' => function ($pdo, $db) {
            $r = ejecutarEndpoint('inventario_action.php', ['action' => 'sell', 'ajax' => '1', 'csrf_token' => 'token-pruebas',
                'id' => 2, 'cantidad' => 1, 'precio_venta' => '6.50', 'vendedor_id' => 2], $db);
            comprobarEndpoint($r['success'], 'Debe aceptar el formulario actual con action sell.');
            $ids = array_map('intval', $pdo->query('SELECT usuario_id FROM notificaciones_pwa ORDER BY usuario_id')->fetchAll(PDO::FETCH_COLUMN));
            comprobarEndpoint($ids === [1, 3], 'Recepción y clientes no deben recibir alertas de stock bajo.');
            comprobarEndpoint((float) $pdo->query('SELECT precio_unitario FROM ventas_productos')->fetchColumn() === 6.5, 'Debe conservar el descuento del inventario.');
        },
        'Cobro exige método válido y registra el pago exacto sin duplicados' => function ($pdo, $db) use ($base) {
            foreach ([null, '', 'incorrecto', []] as $metodo) {
                $r=ejecutarEndpoint('citas_action.php',array_replace($base,['metodo_pago'=>$metodo]),$db);
                comprobarEndpoint(!$r['success'],'Debe exigir un método válido.');comprobarCobroIntacto($pdo);
            }
            $r=ejecutarEndpoint('citas_action.php',array_replace($base,['metodo_pago'=>'deuna']),$db);
            comprobarEndpoint($r['success'] && $r['recibo']['metodo_pago']==='deuna','Debe guardar el pago DeUna.');
            comprobarEndpoint($pdo->query('SELECT metodo_pago FROM cobros_caja')->fetchColumn()==='deuna','Caja debe conservar el método exacto.');
            comprobarEndpoint((int)$pdo->query('SELECT COUNT(*) FROM cobros_caja')->fetchColumn()===1,'La cita debe generar un único cobro.');
            comprobarEndpoint((int)$pdo->query('SELECT COUNT(*) FROM ventas_productos WHERE cobro_id IS NOT NULL')->fetchColumn()===1,'Debe vincular el producto al mismo cobro.');
        },
        'Guardar horarios conserva reservas y permite reasignarlas desde la API' => function ($pdo,$db) {
            $fecha=(new DateTimeImmutable('tomorrow'))->format('Y-m-d').' 10:00:00';
            $stmt=$pdo->prepare('UPDATE citas SET fecha_hora=? WHERE id=42');$stmt->execute([$fecha]);
            $dia=(int)date('w',strtotime($fecha));$activos=array_fill(0,7,'1');unset($activos[$dia]);
            $r=ejecutarEndpoint('horarios_action.php',['action'=>'guardar_horarios','barbero_id'=>3,'ajax'=>'1','csrf_token'=>'token-pruebas',
                'hora_inicio'=>array_fill(0,7,'10:00'),'hora_fin'=>array_fill(0,7,'20:00'),'activo'=>$activos],$db);
            comprobarEndpoint($r['success'],'Debe guardar el horario: '.json_encode($r));
            comprobarEndpoint((int)$pdo->query('SELECT reasignacion_pendiente FROM citas')->fetchColumn()===1,'La reserva debe quedar sin asignación efectiva.');
            comprobarEndpoint((int)$pdo->query('SELECT COUNT(*) FROM citas')->fetchColumn()===1,'No debe eliminar reservas.');
            $pdo->exec("INSERT INTO usuarios (id,nombre,rol,activo,sucursal_id) VALUES (4,'Otro barbero','barbero',1,1)");
            $r=ejecutarEndpoint('reasignar_cita.php',['cita_id'=>42,'barbero_id'=>4,'csrf_token'=>'token-pruebas'],$db);
            comprobarEndpoint($r['success'],'Debe reasignar al barbero disponible: '.json_encode($r));
            comprobarEndpoint((int)$pdo->query('SELECT barbero_id FROM citas')->fetchColumn()===4,'Debe registrar el nuevo barbero.');
        },
        'Horario inválido revierte toda la modificación semanal' => function ($pdo,$db) {
            $r=ejecutarEndpoint('horarios_action.php',['action'=>'guardar_horarios','barbero_id'=>3,'ajax'=>'1','csrf_token'=>'token-pruebas',
                'hora_inicio'=>array_fill(0,7,'20:00'),'hora_fin'=>array_fill(0,7,'10:00'),'activo'=>array_fill(0,7,'1')],$db);
            comprobarEndpoint(!$r['success'],'Debe rechazar las horas invertidas.');
            comprobarEndpoint((int)$pdo->query('SELECT COUNT(*) FROM horarios_barberos')->fetchColumn()===0,'No debe guardar una semana parcial.');
        },
        'Calendario conserva citas sin asignación y las oculta al barbero anterior' => function ($pdo,$db) {
            $fecha=(new DateTimeImmutable('tomorrow'))->format('Y-m-d');
            $stmt=$pdo->prepare('UPDATE citas SET fecha_hora=?,reasignacion_pendiente=1,barbero_anterior_id=3 WHERE id=42');$stmt->execute([$fecha.' 10:00:00']);
            $r=ejecutarEndpoint('calendario_citas.php',[],$db,1,'admin',['fecha'=>$fecha]);
            comprobarEndpoint($r['success'] && count($r['eventos'])===1 && count($r['pendientes'])===1,'La reserva debe aparecer para administración.');
            $r=ejecutarEndpoint('calendario_citas.php',[],$db,3,'barbero',['fecha'=>$fecha]);
            comprobarEndpoint($r['success'] && count($r['eventos'])===0 && count($r['pendientes'])===0,'Ya no debe aparecer como una asignación del barbero anterior.');
        },
        'API del historial aplica filtros y límites de vendedor' => function ($pdo,$db) {
            ejecutarEndpoint('inventario_action.php',['action'=>'sell','csrf_token'=>'token-pruebas','producto_id'=>1,'cantidad'=>2,'precio_venta'=>11,'vendedor_id'=>2],$db);
            $r=ejecutarEndpoint('reportes_ventas.php',[],$db,1,'admin',['vendedor_id'=>2]);
            comprobarEndpoint($r['success'] && $r['reporte']['registros']===1 && (float)$r['reporte']['total']===22.0,'Debe devolver el detalle del vendedor y el total real.');
            $r=ejecutarEndpoint('reportes_ventas.php',[],$db,3,'barbero',['vendedor_id'=>2]);
            comprobarEndpoint($r['success'] && $r['reporte']['registros']===0,'Un barbero no puede consultar las ventas de otro vendedor mediante un filtro.');
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
