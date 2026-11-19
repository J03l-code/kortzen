<?php
require_once __DIR__ . '/fixtures/ventas_database.php';
require_once __DIR__ . '/../includes/ventas_productos.php';
require_once __DIR__ . '/../includes/reasignacion.php';
require_once __DIR__ . '/../includes/exportar_tablas.php';

class OperativaTestPDO extends PDO
{
    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        return parent::prepare(str_replace(' FOR UPDATE', '', $query), $options);
    }
}
function verificarOp(bool $ok, string $mensaje): void { if (!$ok) throw new RuntimeException($mensaje); }
function rechazarOp(callable $accion): void
{
    try { $accion(); } catch (InvalidArgumentException $e) { return; }
    throw new RuntimeException('La operación inválida fue aceptada.');
}
function getUsuarioSucursalesIds($id): array
{
    $pdo = $GLOBALS['operativaPDO'];
    $stmt = $pdo->prepare('SELECT rol,sucursal_id FROM usuarios WHERE id=?'); $stmt->execute([$id]); $u=$stmt->fetch(PDO::FETCH_ASSOC);
    if ($u['rol']==='admin') return [1,2];
    return [(int) $u['sucursal_id']];
}
function fixtureOp(): PDO
{
    // Reutilizar exactamente el esquema desechable de las pruebas de endpoints.
    $base = fixtureEndpoint(':memory:');
    $pdo = new OperativaTestPDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
    foreach ($base->query("SELECT sql FROM sqlite_master WHERE type='table'")->fetchAll(PDO::FETCH_COLUMN) as $sql) $pdo->exec($sql);
    foreach ($base->query("SELECT name FROM sqlite_master WHERE type='table'")->fetchAll(PDO::FETCH_COLUMN) as $tabla) {
        foreach ($base->query('SELECT * FROM ' . $tabla)->fetchAll(PDO::FETCH_ASSOC) as $fila) {
            $stmt=$pdo->prepare('INSERT INTO '.$tabla.' ('.implode(',',array_keys($fila)).') VALUES ('.implode(',',array_fill(0,count($fila),'?')).')');
            $stmt->execute(array_values($fila));
        }
    }
    $pdo->exec("INSERT INTO usuarios (id,nombre,rol,activo,sucursal_id,comision_productos) VALUES (4,'Segundo barbero','barbero',1,1,10)");
    $pdo->exec('ALTER TABLE usuarios ADD COLUMN almuerzo_activo INTEGER DEFAULT 0');
    $pdo->exec("ALTER TABLE usuarios ADD COLUMN almuerzo_inicio TEXT DEFAULT '13:00:00'");
    $pdo->exec("ALTER TABLE usuarios ADD COLUMN almuerzo_fin TEXT DEFAULT '14:00:00'");
    for ($dia=0;$dia<7;$dia++) foreach ([3,4] as $barbero) $pdo->exec("INSERT INTO horarios_barberos VALUES ($barbero,$dia,'10:00:00','20:00:00',1)");
    $futura = (new DateTimeImmutable('tomorrow'))->format('Y-m-d').' 10:00:00';
    $stmt=$pdo->prepare('UPDATE citas SET fecha_hora=? WHERE id=42');$stmt->execute([$futura]);
    $GLOBALS['operativaPDO']=$pdo;
    return $pdo;
}
function operadorOp(PDO $pdo): array { return getOperadorVenta($pdo,1); }
function diaOp(PDO $pdo): int { return (int) date('w',strtotime($pdo->query('SELECT fecha_hora FROM citas WHERE id=42')->fetchColumn())); }
function dejarPendienteOp(PDO $pdo): void
{
    $dia=diaOp($pdo); $pdo->beginTransaction();
    $pdo->exec("UPDATE horarios_barberos SET activo=0 WHERE barbero_id=3 AND dia_semana=$dia");
    verificarOp(marcarCitasSinBarbero($pdo,3,operadorOp($pdo))===1,'Debe marcar la reserva afectada.');
    $pdo->commit();
}

$casosOp = [
    'Método de pago obligatorio sin afectar stock' => function () {
        $p=fixtureOp();
        foreach ([null,'','tarjeta','invalido',[]] as $metodo) rechazarOp(fn()=>registrarVentaProductos($p,[['producto_id'=>1,'cantidad'=>1]],2,1,null,null,$metodo));
        verificarOp((int)$p->query('SELECT cantidad FROM inventario WHERE id=1')->fetchColumn()===10,'No debe consumir inventario.');
    },
    'Cada método conserva importes y cortes en caja' => function () {
        $p=fixtureOp();$id=100;
        foreach (metodosPagoKortzen() as $metodo=>$nombre) {
            $p->beginTransaction(); registrarCobroCaja($p,['cita_id'=>$id++,'sucursal_id'=>1,'registrado_por'=>1,'metodo_pago'=>$metodo,'servicios'=>10,'productos'=>5,'propina'=>2]);$p->commit();
        }
        $r=resumenCaja($p,operadorOp($p),[]);
        verificarOp($r['totales']['cortes']===5.0 && $r['totales']['total']===85.0,'Debe sumar cinco cobros de $17.');
        verificarOp(count($r['metodos'])===5,'Los métodos deben permanecer separados.');
    },
    'Productos se cuentan una vez y registros antiguos quedan sin especificar' => function () {
        $p=fixtureOp();
        registrarVentaProductos($p,[['producto_id'=>1,'cantidad'=>2,'precio_unitario'=>11]],2,1,null,null,'efectivo');
        $stmt=$p->prepare("UPDATE citas SET estado='completada',fecha_completada=?,precio_final=15,propina=2 WHERE id=42");$stmt->execute([date('Y-m-d H:i:s')]);
        $p->exec('INSERT INTO ventas_productos (producto_id,cantidad,precio_unitario,sucursal_id,usuario_id,cita_id) VALUES (1,2,3,1,3,42)');
        $r=resumenCaja($p,operadorOp($p),[]);
        verificarOp($r['totales']['total']===45.0 && $r['totales']['productos']===28.0,'Debe sumar $22 nuevos y $23 históricos, sin duplicar productos.');
        verificarOp($r['totales']['cortes']===1.0,'Los productos no cuentan como cortes.');
        verificarOp(in_array('sin_especificar',array_column($r['metodos'],'metodo_pago'),true),'No debe inventar métodos históricos.');
    },
    'Fallo al guardar caja revierte ventas e inventario' => function () {
        $p=fixtureOp();$p->exec("CREATE TRIGGER fallo_caja BEFORE INSERT ON cobros_caja BEGIN SELECT RAISE(ABORT,'Fallo controlado'); END");
        try {registrarVentaProductos($p,[['producto_id'=>1,'cantidad'=>2]],2,1,null,null,'deuna');throw new RuntimeException('Debía fallar.');}
        catch(PDOException $e) {verificarOp((int)$p->query('SELECT cantidad FROM inventario WHERE id=1')->fetchColumn()===10,'Debe restituir stock.');}
        verificarOp((int)$p->query('SELECT COUNT(*) FROM ventas_productos')->fetchColumn()===0,'No deben quedar ventas sueltas.');
    },
    'Historial filtra vendedor, fecha y conserva el precio real' => function () {
        $p=fixtureOp();registrarVentaProductos($p,[['producto_id'=>1,'cantidad'=>2,'precio_unitario'=>11]],2,1,null,null,'transferencia');
        registrarVentaProductos($p,[['producto_id'=>1,'cantidad'=>1]],3,1,null,null,'tarjeta_credito');
        $r=historialVentas($p,operadorOp($p),['vendedor_id'=>2]);
        verificarOp($r['registros']===1 && $r['total']===22.0,'Debe filtrar por vendedor y precio cobrado.');
        verificarOp($r['filas'][0]['metodo_pago']==='transferencia','Debe conservar el método.');
        verificarOp(historialVentas($p,operadorOp($p),['desde'=>'2000-01-01','hasta'=>'2000-01-01'])['registros']===0,'Debe respetar las fechas.');
    },
    'Caja e historial respetan sucursales y permisos' => function () {
        $p=fixtureOp();registrarVentaProductos($p,[['producto_id'=>1,'cantidad'=>1]],2,1,null,null,'efectivo');
        $p->exec("UPDATE usuarios SET rol='admin_local',sucursal_id=2 WHERE id=2");
        $local=getOperadorVenta($p,2);
        verificarOp(resumenCaja($p,$local,[])['totales']['total']===0,'Un admin local no ve caja de otra sucursal.');
        verificarOp(historialVentas($p,$local,['sucursal_id'=>1])['registros']===0,'Un filtro no puede ampliar los permisos.');
        rechazarOp(fn()=>resumenCaja($p,getOperadorVenta($p,3),[]));
    },
    'Historial pagina y la exportación contiene todo el filtro' => function () {
        $p=fixtureOp();$p->exec('UPDATE inventario SET cantidad=100 WHERE id=1');
        for($i=0;$i<51;$i++) registrarVentaProductos($p,[['producto_id'=>1,'cantidad'=>1,'precio_unitario'=>1]],2,1,null,null,'efectivo');
        $r=historialVentas($p,operadorOp($p),[]);$segunda=historialVentas($p,operadorOp($p),['pagina'=>2]);
        verificarOp(count($r['filas'])===50 && count($segunda['filas'])===1,'Debe paginar en 50 registros.');
        verificarOp(count(historialVentas($p,operadorOp($p),[],true)['filas'])===51,'La descarga no debe limitarse a la página visible.');
    },
    'Fechas inválidas y rangos invertidos son rechazados' => function () {
        foreach ([['desde'=>'2026-02-30'],['desde'=>'2026-11-20','hasta'=>'2026-11-19'],['desde'=>[]],['desde'=>'2020-01-01','hasta'=>'2026-01-01']] as $rango) rechazarOp(fn()=>rangoReporte($rango));
    },
    'Apagar un día mantiene la cita y su barbero original en el historial' => function () {
        $p=fixtureOp();dejarPendienteOp($p);$c=$p->query('SELECT * FROM citas WHERE id=42')->fetch();
        verificarOp((int)$c['reasignacion_pendiente']===1 && (int)$c['barbero_id']===3 && (int)$c['barbero_anterior_id']===3,'Debe mantener la referencia histórica y marcar sin asignación.');
        verificarOp($c['estado']==='confirmada' && (float)$c['precio_final']===15.0,'No debe cancelar ni cambiar el valor de la reserva.');
        verificarOp((int)$p->query('SELECT COUNT(*) FROM citas_reasignaciones')->fetchColumn()===1,'Debe auditar el cambio.');
    },
    'Cambiar otro día no afecta la reserva y restaurar no reasigna automáticamente' => function () {
        $p=fixtureOp();$dia=(diaOp($p)+1)%7;$p->beginTransaction();$p->exec("UPDATE horarios_barberos SET activo=0 WHERE barbero_id=3 AND dia_semana=$dia");
        verificarOp(marcarCitasSinBarbero($p,3,operadorOp($p))===0,'No debe afectar otros días.');$p->commit();
        dejarPendienteOp($p);$p->beginTransaction();$p->exec('UPDATE horarios_barberos SET activo=1 WHERE barbero_id=3');
        verificarOp(marcarCitasSinBarbero($p,3,operadorOp($p))===0,'No debe duplicar alertas ni auditar dos veces.');$p->commit();
        verificarOp((int)$p->query('SELECT reasignacion_pendiente FROM citas')->fetchColumn()===1,'La reasignación requiere una decisión administrativa.');
    },
    'Reducir la jornada considera la duración completa de la cita' => function () {
        $p=fixtureOp();$dia=diaOp($p);$p->beginTransaction();$p->exec("UPDATE horarios_barberos SET hora_fin='10:20:00' WHERE barbero_id=3 AND dia_semana=$dia");
        verificarOp(marcarCitasSinBarbero($p,3,operadorOp($p))===1,'Una cita de 45 minutos no cabe antes de las 10:20.');$p->commit();
    },
    'Citas completadas y pasadas no se desasignan' => function () {
        $p=fixtureOp();$p->exec("UPDATE citas SET estado='completada' WHERE id=42");$p->beginTransaction();$p->exec('UPDATE horarios_barberos SET activo=0 WHERE barbero_id=3');
        verificarOp(marcarCitasSinBarbero($p,3,operadorOp($p))===0,'No debe cambiar citas completadas.');$p->commit();
        $p->exec("UPDATE citas SET estado='confirmada',fecha_hora='2000-01-01 10:00:00' WHERE id=42");$p->beginTransaction();
        verificarOp(marcarCitasSinBarbero($p,3,operadorOp($p))===0,'No debe modificar citas pasadas.');$p->commit();
    },
    'Bloqueos parciales sólo afectan citas que se superponen' => function () {
        $p=fixtureOp();$fecha=substr($p->query('SELECT fecha_hora FROM citas')->fetchColumn(),0,10);
        $p->exec("INSERT INTO bloqueos_horas VALUES (1,3,'$fecha','11:00:00','12:00:00','Descanso',1)");$p->beginTransaction();
        verificarOp(marcarCitasSinBarbero($p,3,operadorOp($p))===0,'Un bloqueo posterior no debe afectar la cita.');$p->commit();
        $p->exec("UPDATE bloqueos_horas SET hora_inicio='10:30:00' WHERE id=1");$p->beginTransaction();
        verificarOp(marcarCitasSinBarbero($p,3,operadorOp($p))===1,'Debe detectar un bloqueo dentro de la cita.');$p->commit();
    },
    'Reasignación válida conserva fecha y servicio y registra auditoría' => function () {
        $p=fixtureOp();$antes=$p->query('SELECT * FROM citas')->fetch();dejarPendienteOp($p);reasignarCita($p,42,4,operadorOp($p));$despues=$p->query('SELECT * FROM citas')->fetch();
        verificarOp((int)$despues['barbero_id']===4 && !$despues['reasignacion_pendiente'],'Debe asignar al nuevo barbero.');
        verificarOp($antes['fecha_hora']===$despues['fecha_hora'] && $antes['servicio_id']===$despues['servicio_id'],'Debe conservar la reserva.');
        verificarOp((int)$p->query('SELECT COUNT(*) FROM citas_reasignaciones')->fetchColumn()===2,'Debe auditar ambas transiciones.');
    },
    'Reasignación rechaza barberos ocupados, fuera de horario o inactivos' => function () {
        foreach (['ocupado','horario','inactivo'] as $caso) {
            $p=fixtureOp();dejarPendienteOp($p);$f=$p->query('SELECT fecha_hora FROM citas')->fetchColumn();
            if($caso==='ocupado') $p->exec("INSERT INTO citas (id,cliente_id,servicio_id,barbero_id,sucursal_id,estado,fecha_hora) VALUES (43,1,1,4,1,'confirmada','$f')");
            if($caso==='horario') $p->exec('UPDATE horarios_barberos SET activo=0 WHERE barbero_id=4');
            if($caso==='inactivo') $p->exec('UPDATE usuarios SET activo=0 WHERE id=4');
            rechazarOp(fn()=>reasignarCita($p,42,4,operadorOp($p)));
            verificarOp((int)$p->query('SELECT reasignacion_pendiente FROM citas WHERE id=42')->fetchColumn()===1,'La cita debe seguir pendiente.');
        }
    },
    'Reasignación valida sucursal, servicio y permisos administrativos' => function () {
        $p=fixtureOp();dejarPendienteOp($p);$p->exec('UPDATE usuarios SET sucursal_id=2 WHERE id=4');
        rechazarOp(fn()=>reasignarCita($p,42,4,operadorOp($p)));
        $p->exec('UPDATE usuarios SET sucursal_id=1 WHERE id=4');$p->exec('INSERT INTO servicios_barberos VALUES (1,3)');
        rechazarOp(fn()=>reasignarCita($p,42,4,operadorOp($p)));
        rechazarOp(fn()=>reasignarCita($p,42,4,getOperadorVenta($p,3)));
    },
    'Excel contiene XML válido, importes numéricos y texto sin fórmulas' => function () {
        $bytes=tablaXlsx(['Producto','Total'],[['=SUM(A1:A2)',12.5],['Champú',0]]);$ruta=tempnam(sys_get_temp_dir(),'kortzen-test-xlsx-');
        try {
            file_put_contents($ruta,$bytes);$zip=new ZipArchive();verificarOp($zip->open($ruta)===true,'Debe ser un ZIP XLSX válido.');
            $sheet=$zip->getFromName('xl/worksheets/sheet1.xml');$dom=new DOMDocument();verificarOp($dom->loadXML($sheet),'Debe ser XML válido.');
            verificarOp(str_contains($sheet,'inlineStr') && !str_contains($sheet,'<f>'),'Los nombres no deben ejecutar fórmulas.');
            verificarOp(str_contains($sheet,'<v>12.5</v>'),'El importe debe ser numérico.');$zip->close();
        } finally {unlink($ruta);}
    },
    'PDF pagina y mantiene referencias xref válidas' => function () {
        $filas=array_fill(0,100,['19/11/2026 10:00','Champú profesional',2,22,'Mishell Pérez','Local Quito','Transferencia']);
        $pdf=tablaPdf('Historial de ventas',['Fecha','Producto','Cantidad','Total','Vendedor','Sucursal','Pago'],$filas);
        verificarOp(str_starts_with($pdf,'%PDF-1.4'),'Debe producir un PDF real.');
        verificarOp(preg_match('/\/Type \/Pages \/Count (\d+)/',$pdf,$m) && (int)$m[1]>1,'Debe paginar los registros.');
        preg_match('/startxref\n(\d+)/',$pdf,$m);verificarOp(substr($pdf,(int)$m[1],4)==='xref','La tabla de referencias debe ser correcta.');
    }
];
foreach($casosOp as $nombre=>$prueba) {$prueba();echo "OK: $nombre\n";}
echo count($casosOp)." pruebas de gestión operativa completadas.\n";
