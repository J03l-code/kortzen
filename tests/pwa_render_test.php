<?php
/** php tests/pwa_render_test.php [--preview] */
$source=dirname(__DIR__);
$tmp=sys_get_temp_dir().DIRECTORY_SEPARATOR.'kortzen-pwa-'.bin2hex(random_bytes(8));
mkdir($tmp);mkdir($tmp.'/includes');
$files=[];
$copy=function($from,$to) use (&$files) {copy($from,$to);$files[]=$to;};
try {
    $copy($source.'/pwa-admin.php',$tmp.'/pwa-admin.php');
    foreach(['ventas_resumen.php','historial_ventas.php','vendedor_select.php','metodo_pago.php'] as $file) $copy($source.'/includes/'.$file,$tmp.'/includes/'.$file);
    $boot='<?php define("PWA_RENDER_SOURCE",'.var_export($source,true).'); require '.var_export(__DIR__.'/fixtures/pwa_render_config.php',true).';';
    file_put_contents($tmp.'/config.php',$boot);$files[]=$tmp.'/config.php';
    $render=function() use ($tmp): array {
        $proc=proc_open([PHP_BINARY,'-d','display_errors=stderr',$tmp.'/pwa-admin.php'],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,$tmp);
        fclose($pipes[0]);$html=stream_get_contents($pipes[1]);$errors=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);
        return [proc_close($proc),$html,$errors];
    };
    $current=file_get_contents($tmp.'/includes/historial_ventas.php');
    file_put_contents($tmp.'/includes/historial_ventas.php','<?php query("SELECT id, nombre, activo FROM usuarios UNION ALL SELECT v.usuario_id");');
    [$status,$broken,$errors]=$render();
    if ($status===0 || str_contains($broken,'<nav class="pwa-bottom-bar">') || !str_contains($errors,'Illegal mix of collations')) throw new RuntimeException('No se reprodujo el corte de la versión anterior. '.$errors);
    echo "OK: el fallo anterior corta la página antes de la barra inferior y sus controles.\n";
    file_put_contents($tmp.'/includes/historial_ventas.php',$current);
    [$status,$html,$errors]=$render();
    if ($status!==0 || str_contains($errors,'Fatal error')) throw new RuntimeException('La PWA no se renderizó completa: '.$errors);
    foreach(['<nav class="pwa-bottom-bar">','id="pwaDrawerContent"','function cambiarVistaPwa(','function abrirDrawer(','</html>'] as $marker) {
        if (!str_contains($html,$marker)) throw new RuntimeException('Falta un control de navegación: '.$marker);
    }
    echo "OK: la PWA completa genera menú, barra inferior, modales y JavaScript de navegación.\n";
    // Validar el JavaScript que realmente emitió PHP, no una sustitución de sus tags.
    preg_match_all('/<script\b([^>]*)>(.*?)<\/script>/si',$html,$scripts,PREG_SET_ORDER);
    foreach($scripts as $script) {
        if (str_contains($script[1],'src=') || trim($script[2])==='') continue;
        $proc=proc_open(['node','--check','-'],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
        fwrite($pipes[0],$script[2]);fclose($pipes[0]);$out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);
        if(proc_close($proc)!==0) throw new RuntimeException('JavaScript renderizado inválido: '.$err.$out);
    }
    echo "OK: JavaScript renderizado por PHP sin errores de sintaxis.\n";
    if(in_array('--preview',$argv,true)) {
        $preview=dirname($source).'/pwa-preview';
        foreach(['','/js','/css','/api'] as $dir) if(!is_dir($preview.$dir)) mkdir($preview.$dir,0777,true);
        file_put_contents($preview.'/index.html',$html);
        foreach(['ventas-vendedor.js','historial-ventas.js','pwa.js'] as $file) copy($source.'/js/'.$file,$preview.'/js/'.$file);
        copy($source.'/css/gestion-operativa.css',$preview.'/css/gestion-operativa.css');
        file_put_contents($preview.'/api/reportes_ventas.php','<?php http_response_code(503); header("Content-Type: application/json"); echo json_encode(["success"=>false,"message"=>"Fallo del reporte simulado para probar el aislamiento"]);');
        file_put_contents($preview.'/api/check_pending_pwa_notifications.php','<?php header("Content-Type: application/json"); echo json_encode(["pending"=>false]);');
        file_put_contents($preview.'/api/get_agenda_admin.php','<?php header("Content-Type: application/json"); echo json_encode(["success"=>true,"rango"=>["mes_nombre"=>"noviembre 2026"],"metricas"=>["ingresos_semana"=>"0.00","citas_semana"=>0],"citas"=>[],"barberos"=>[],"citas_por_fecha"=>(object)[],"citas_sin_barbero"=>[],"disponibilidad"=>(object)[]]);');
        echo 'Vista local de prueba: '.$preview."\n";
    }
} finally {
    foreach(array_reverse($files) as $file) if(is_file($file)) unlink($file);
    rmdir($tmp.'/includes');rmdir($tmp);
}
