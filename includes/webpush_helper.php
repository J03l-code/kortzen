<?php
/**
 * KORTZEN - WebPush VAPID Dispatcher Helper
 */

define('VAPID_PUBLIC_KEY', 'BN3FX2wXwG5gj_QlNIm0OZuDaQj37jelLWAZHsjGpu86iIlFkIvcylgw9rimD6APwtzJOzYiIbC_V3qiaTZ6Z8U');
define('VAPID_PRIVATE_KEY', 'O9mKeqQcXT9n-9wt_Jc3ypub6GrlV9av9rPQb2lVxDc');
define('VAPID_SUBJECT', 'mailto:info@kortzen.com');

/**
 * Despachar Notificación Push a Endpoint de Google FCM / Apple APNs / Mozilla
 */
function enviarWebPushVapid($subscription, $payload) {
    $endpoint = $subscription['endpoint'] ?? '';
    if (empty($endpoint) || strpos($endpoint, 'http') !== 0) {
        return false;
    }

    $jsonPayload = is_string($payload) ? $payload : json_encode($payload);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $endpoint);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonPayload);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'TTL: 86400',
        'Urgency: high',
        'Crypto-Key: p256ecdsa=' . VAPID_PUBLIC_KEY
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return ($httpCode >= 200 && $httpCode < 300);
}
