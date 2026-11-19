<?php
/**
 * Test Suite: Google OAuth Redirect URI & Configuration
 * Ejecutar: php tests/google_oauth_test.php
 */

$testCases = [
    'Host www.kortzen.com normaliza a https://kortzen.com' => function () {
        $out = runTestProcess(['HTTP_HOST' => 'www.kortzen.com', 'HTTPS' => 'on']);
        verificar($out['redirect_uri'] === 'https://kortzen.com/api/auth/google-callback.php', 
            "Debe ser exactamente https://kortzen.com/api/auth/google-callback.php, obtenido: {$out['redirect_uri']}");
        verificar(str_contains($out['auth_url'], 'redirect_uri=https%3A%2F%2Fkortzen.com%2Fapi%2Fauth%2Fgoogle-callback.php'),
            "La URL de auth de Google debe contener el redirect_uri canónico");
    },
    'Host kortzen.com (apex) mantiene https://kortzen.com' => function () {
        $out = runTestProcess(['HTTP_HOST' => 'kortzen.com', 'HTTPS' => 'on']);
        verificar($out['redirect_uri'] === 'https://kortzen.com/api/auth/google-callback.php', 
            "Debe ser https://kortzen.com/api/auth/google-callback.php");
    },
    'Host kortzen.com:443 con puerto explícito se limpia' => function () {
        $out = runTestProcess(['HTTP_HOST' => 'kortzen.com:443', 'SERVER_PORT' => '443', 'HTTPS' => 'on']);
        verificar($out['redirect_uri'] === 'https://kortzen.com/api/auth/google-callback.php',
            "No debe incluir el puerto :443 en la URI de redirección");
    },
    'Host www.kortzen.com en HTTP plano fuerza https://kortzen.com' => function () {
        $out = runTestProcess(['HTTP_HOST' => 'www.kortzen.com', 'HTTPS' => 'off']);
        verificar($out['redirect_uri'] === 'https://kortzen.com/api/auth/google-callback.php',
            "En producción siempre debe forzar https sin importar el protocolo entrante");
    },
    'Subdominios no autorizados de kortzen.com van al canónico' => function () {
        $out = runTestProcess(['HTTP_HOST' => 'app.kortzen.com', 'HTTPS' => 'on']);
        verificar($out['redirect_uri'] === 'https://kortzen.com/api/auth/google-callback.php',
            "Cualquier subdominio bajo kortzen.com debe unificarse al dominio canónico autorizado");
    },
    'Entorno local localhost:2020 conserva su puerto y protocolo' => function () {
        $out = runTestProcess(['HTTP_HOST' => 'localhost:2020', 'SERVER_PORT' => '2020']);
        verificar($out['redirect_uri'] === 'http://localhost:2020/api/auth/google-callback.php',
            "Localhost debe conservar su puerto de desarrollo");
    },
    'Entorno local 127.0.0.1:8000 conserva desarrollo' => function () {
        $out = runTestProcess(['HTTP_HOST' => '127.0.0.1:8000', 'SERVER_PORT' => '8000']);
        verificar($out['redirect_uri'] === 'http://127.0.0.1:8000/api/auth/google-callback.php',
            "127.0.0.1 debe funcionar en desarrollo");
    },
    'Client ID coincide con la credencial de Google Cloud' => function () {
        $out = runTestProcess(['HTTP_HOST' => 'kortzen.com']);
        verificar($out['client_id'] === '287462075073-lmhplvlmgarkmgosqbo34oqco25esfn5.apps.googleusercontent.com',
            "El Client ID debe coincidir con la credencial autorizada");
    },
    'Auth URL y Token exchange usan el mismo redirect_uri' => function () {
        $out = runTestProcess(['HTTP_HOST' => 'www.kortzen.com']);
        verificar($out['redirect_uri'] === $out['token_redirect_uri'],
            "Ambas fases deben usar estrictamente la misma constante GOOGLE_REDIRECT_URI");
    }
];

function verificar(bool $cond, string $msg): void {
    if (!$cond) throw new RuntimeException("FALLO: $msg");
}

function runTestProcess(array $serverVars): array {
    $script = dirname(__DIR__) . '/api/auth/google-config.php';
    $code = '';
    foreach ($serverVars as $k => $v) {
        $code .= '$_SERVER[' . var_export($k, true) . '] = ' . var_export($v, true) . '; ';
    }
    $code .= 'require ' . var_export($script, true) . '; ';
    $code .= 'echo json_encode([
        "redirect_uri" => GOOGLE_REDIRECT_URI,
        "client_id" => GOOGLE_CLIENT_ID,
        "token_redirect_uri" => GOOGLE_REDIRECT_URI,
        "auth_url" => getGoogleAuthUrl("test_state")
    ]);';

    $proc = proc_open([PHP_BINARY, '-r', $code], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $status = proc_close($proc);

    if ($status !== 0 || empty($stdout)) {
        throw new RuntimeException("Error en proceso aislado ($status): $stderr $stdout");
    }

    $res = json_decode($stdout, true);
    if (!is_array($res)) {
        throw new RuntimeException("Salida no es JSON válido: $stdout $stderr");
    }
    return $res;
}

echo "=== Ejecutando pruebas unitarias de Google OAuth ===\n";
foreach ($testCases as $nombre => $test) {
    $test();
    echo "OK: $nombre\n";
}
echo count($testCases) . " pruebas de Google OAuth completadas con éxito.\n";
