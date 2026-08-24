<?php
/**
 * KORTZEN - WebPush VAPID Dispatcher Helper with RFC 8291 Encryption
 */

require_once __DIR__ . '/webpush_encrypt.php';

define('VAPID_PUBLIC_KEY', 'BN3FX2wXwG5gj_QlNIm0OZuDaQj37jelLWAZHsjGpu86iIlFkIvcylgw9rimD6APwtzJOzYiIbC_V3qiaTZ6Z8U');
define('VAPID_PRIVATE_KEY', 'O9mKeqQcXT9n-9wt_Jc3ypub6GrlV9av9rPQb2lVxDc');
define('VAPID_SUBJECT', 'mailto:info@kortzen.com');

function kortzen_b64url_encode($data) {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function kortzen_b64url_decode($data) {
    return base64_decode(strtr($data, '-_', '+/') . str_repeat('=', (4 - strlen($data) % 4) % 4));
}

function generarVapidAuthHeader($endpoint, $vapidPublic = VAPID_PUBLIC_KEY, $vapidPrivate = VAPID_PRIVATE_KEY, $subject = VAPID_SUBJECT) {
    $parsedUrl = parse_url($endpoint);
    $aud = ($parsedUrl['scheme'] ?? 'https') . '://' . ($parsedUrl['host'] ?? '');

    $header = json_encode(['typ' => 'JWT', 'alg' => 'ES256']);
    $payload = json_encode([
        'aud' => $aud,
        'exp' => time() + 43200,
        'sub' => $subject
    ]);

    $jwtUnsigned = kortzen_b64url_encode($header) . '.' . kortzen_b64url_encode($payload);

    $privKeyRaw = kortzen_b64url_decode($vapidPrivate);
    $pubKeyRaw = kortzen_b64url_decode($vapidPublic);

    $derPriv = hex2bin("30770201010420") . $privKeyRaw . hex2bin("a00a06082a8648ce3d030107a144034200") . $pubKeyRaw;
    $pemPriv = "-----BEGIN EC PRIVATE KEY-----\n" . chunk_split(base64_encode($derPriv), 64, "\n") . "-----END EC PRIVATE KEY-----\n";

    $signature = '';
    $success = openssl_sign($jwtUnsigned, $signature, $pemPriv, OPENSSL_ALGO_SHA256);

    if (!$success) {
        return false;
    }

    $asn1 = $signature;
    $offset = 2;
    $rLength = ord($asn1[$offset + 1]);
    $r = substr($asn1, $offset + 2, $rLength);
    $sLength = ord($asn1[$offset + 2 + $rLength + 1]);
    $s = substr($asn1, $offset + 2 + $rLength + 2, $sLength);

    $r = str_pad(ltrim($r, "\x00"), 32, "\x00", STR_PAD_LEFT);
    $s = str_pad(ltrim($s, "\x00"), 32, "\x00", STR_PAD_LEFT);

    $rawSignature = $r . $s;
    $jwt = $jwtUnsigned . '.' . kortzen_b64url_encode($rawSignature);

    return 'vapid t=' . $jwt . ', k=' . $vapidPublic;
}

/**
 * Despachar Notificación Push con Cifrado RFC 8291 (aes128gcm) a Apple APNs (iPhone 16 Pro Max) / Google FCM
 */
function enviarWebPushVapid($subscription, $payload) {
    $endpoint = is_array($subscription) ? ($subscription['endpoint'] ?? '') : $subscription;
    if (empty($endpoint) || strpos($endpoint, 'http') !== 0) {
        return false;
    }

    $p256dh = is_array($subscription) ? ($subscription['p256dh'] ?? '') : '';
    $authSecret = is_array($subscription) ? ($subscription['auth'] ?? '') : '';

    $authHeader = generarVapidAuthHeader($endpoint);

    // Intentar cifrado RFC 8291 aes128gcm para Apple APNs (iOS Safari)
    $encryptedBody = null;
    if (!empty($p256dh) && !empty($authSecret) && $p256dh !== 'granted') {
        $encryptedBody = encryptWebPushPayload($payload, $p256dh, $authSecret);
    }

    $headers = [];
    if ($authHeader) {
        $headers[] = 'Authorization: ' . $authHeader;
    }
    $headers[] = 'TTL: 86400';
    $headers[] = 'Urgency: high';

    if ($encryptedBody !== false && $encryptedBody !== null) {
        $headers[] = 'Content-Type: application/octet-stream';
        $headers[] = 'Content-Encoding: aes128gcm';
        $postData = $encryptedBody;
    } else {
        $headers[] = 'Content-Type: application/json';
        $postData = is_string($payload) ? $payload : json_encode($payload);
    }

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $endpoint);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 6);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return ($httpCode >= 200 && $httpCode < 300);
}
