<?php
/**
 * KORTZEN - RFC 8291 WebPush Payload Encryption Engine for PHP
 * Supports Apple APNs (iOS Safari), Google FCM (Android Chrome), and Mozilla Push.
 */

if (!function_exists('kortzen_hkdf')) {
    function kortzen_hkdf($salt, $ikm, $info, $length) {
        $prk = hash_hmac('sha256', $ikm, $salt, true);
        $t = '';
        $last = '';
        for ($i = 1; strlen($t) < $length; $i++) {
            $last = hash_hmac('sha256', $last . $info . chr($i), $prk, true);
            $t .= $last;
        }
        return substr($t, 0, $length);
    }
}

/**
 * Encrypt WebPush Payload using RFC 8291 (aes128gcm)
 */
function encryptWebPushPayload($payload, $userP256dh, $userAuth) {
    if (empty($userP256dh) || empty($userAuth)) {
        return false;
    }

    $clientPublicKeyRaw = kortzen_b64url_decode($userP256dh);
    $clientAuthRaw = kortzen_b64url_decode($userAuth);

    if (strlen($clientPublicKeyRaw) !== 65 || strlen($clientAuthRaw) < 12) {
        return false;
    }

    // 1. Generate local ephemeral EC key pair (NIST P-256)
    $config = [
        "curve_name" => "prime256v1",
        "private_key_type" => OPENSSL_KEYTYPE_EC
    ];
    $localKey = openssl_pkey_new($config);
    if (!$localKey) return false;

    $localDetails = openssl_pkey_get_details($localKey);
    $localX = str_pad($localDetails['ec']['x'], 32, "\0", STR_PAD_LEFT);
    $localY = str_pad($localDetails['ec']['y'], 32, "\0", STR_PAD_LEFT);
    $localPublicKeyRaw = "\x04" . $localX . $localY;

    // Convert client public key to PEM format for OpenSSL
    $clientDer = hex2bin("3059301306072a8648ce3d020106082a8648ce3d030107034200") . $clientPublicKeyRaw;
    $clientPem = "-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode($clientDer), 64, "\n") . "-----END PUBLIC KEY-----\n";
    $clientKey = openssl_pkey_get_public($clientPem);
    if (!$clientKey) return false;

    // 2. Compute Shared Secret via ECDH
    $sharedSecret = openssl_pkey_derive($clientKey, $localKey);
    if (!$sharedSecret) return false;

    // 3. Generate Random 16-byte Salt
    $salt = random_bytes(16);

    // 4. Derive Master PRK using HKDF
    $infoAuth = "WebPush: info\x00" . $clientPublicKeyRaw . $localPublicKeyRaw;
    $ikm = kortzen_hkdf($clientAuthRaw, $sharedSecret, $infoAuth, 32);

    // 5. Derive Content Encryption Key (CEK) and Nonce
    $cekInfo = "Content-Encoding: aes128gcm\x00";
    $nonceInfo = "Content-Encoding: nonce\x00";

    $cek = kortzen_hkdf($salt, $ikm, $cekInfo, 16);
    $nonce = kortzen_hkdf($salt, $ikm, $nonceInfo, 12);

    // 6. Format Plaintext Record with Padding (RFC 8291)
    $plaintext = is_string($payload) ? $payload : json_encode($payload);
    $paddedPayload = $plaintext . "\x02"; // Delimiter byte

    // 7. Encrypt with AES-128-GCM
    $tag = '';
    $ciphertext = openssl_encrypt($paddedPayload, 'aes-128-gcm', $cek, OPENSSL_RAW_DATA, $nonce, $tag);
    if (!$ciphertext) return false;

    // 8. Construct RFC 8291 Binary Header + Ciphertext + Auth Tag
    $recordSize = pack('N', 4096);
    $keyLen = chr(65);

    $encryptedBody = $salt . $recordSize . $keyLen . $localPublicKeyRaw . $ciphertext . $tag;

    return $encryptedBody;
}
