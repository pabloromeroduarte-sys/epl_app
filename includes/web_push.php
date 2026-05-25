<?php
declare(strict_types=1);
/**
 * Web Push / VAPID — implementación sin Composer.
 * Requiere PHP 7.4+ con extensiones openssl y mbstring.
 */

function epl_b64url_encode(string $data): string {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}
function epl_b64url_decode(string $data): string {
    return base64_decode(strtr($data . str_repeat('=', (4 - strlen($data) % 4) % 4), '-_', '+/'));
}

/** Construye clave pública EC (P-256) desde 65 bytes RAW → PEM SPKI */
function epl_ec_pub_pem_from_raw(string $raw): string {
    // SubjectPublicKeyInfo DER para P-256
    $der = "\x30\x59"
         . "\x30\x13"
         . "\x06\x07\x2a\x86\x48\xce\x3d\x02\x01"
         . "\x06\x08\x2a\x86\x48\xce\x3d\x03\x01\x07"
         . "\x03\x42\x00"
         . $raw;
    return "-----BEGIN PUBLIC KEY-----\n"
         . chunk_split(base64_encode($der), 64, "\n")
         . "-----END PUBLIC KEY-----";
}

/** Firma DER ECDSA → r||s (64 bytes) */
function epl_ecdsa_der_to_raw(string $der): string {
    $offset = 2; // skip 0x30 + seq_len
    // r
    $offset++;   // skip 0x02
    $r_len = ord($der[$offset++]);
    $r = substr($der, $offset, $r_len); $offset += $r_len;
    // s
    $offset++;   // skip 0x02
    $s_len = ord($der[$offset++]);
    $s = substr($der, $offset, $s_len);
    // pad to 32 bytes
    $r = str_pad(ltrim($r, "\x00"), 32, "\x00", STR_PAD_LEFT);
    $s = str_pad(ltrim($s, "\x00"), 32, "\x00", STR_PAD_LEFT);
    return $r . $s;
}

/** Genera JWT VAPID firmado con ES256 */
function epl_vapid_jwt(string $endpoint, string $private_key_pem, string $subject): string {
    $p    = parse_url($endpoint);
    $aud  = $p['scheme'] . '://' . $p['host'];
    $hdr  = epl_b64url_encode(json_encode(['typ' => 'JWT', 'alg' => 'ES256']));
    $pay  = epl_b64url_encode(json_encode(['aud' => $aud, 'exp' => time() + 43200, 'sub' => $subject]));
    $sig_input = "$hdr.$pay";
    openssl_sign($sig_input, $der_sig, $private_key_pem, 'SHA256');
    return $sig_input . '.' . epl_b64url_encode(epl_ecdsa_der_to_raw($der_sig));
}

/** Cifra el payload según RFC 8291 (ECDH + AES-128-GCM) */
function epl_web_push_encrypt(string $payload, string $p256dh, string $auth_b64): array {
    $client_pub = epl_b64url_decode($p256dh);      // 65 bytes
    $auth       = epl_b64url_decode($auth_b64);    // 16 bytes

    // Generar clave efímera del servidor
    $srv_key = openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);
    $det     = openssl_pkey_get_details($srv_key);
    $srv_x   = str_pad((string)$det['ec']['x'], 32, "\x00", STR_PAD_LEFT);
    $srv_y   = str_pad((string)$det['ec']['y'], 32, "\x00", STR_PAD_LEFT);
    $srv_pub = "\x04" . $srv_x . $srv_y; // 65 bytes

    // ECDH
    $client_key    = openssl_pkey_get_public(epl_ec_pub_pem_from_raw($client_pub));
    $ecdh_secret   = openssl_pkey_derive($client_key, $srv_key); // 32 bytes

    // HKDF (RFC 8291)
    $key_info = "WebPush: info\x00" . $client_pub . $srv_pub;
    $prk_key  = hash_hmac('sha256', $ecdh_secret, $auth, true);
    $ikm      = substr(hash_hmac('sha256', $key_info . "\x01", $prk_key, true), 0, 32);

    $salt     = random_bytes(16);
    $prk      = hash_hmac('sha256', $ikm, $salt, true);
    $cek      = substr(hash_hmac('sha256', "Content-Encoding: aes128gcm\x00\x01", $prk, true), 0, 16);
    $nonce    = substr(hash_hmac('sha256', "Content-Encoding: nonce\x00\x01",    $prk, true), 0, 12);

    // Cifrar
    $tag        = '';
    $ciphertext = openssl_encrypt($payload . "\x02", 'aes-128-gcm', $cek, OPENSSL_RAW_DATA, $nonce, $tag, '', 16);

    // Cabecera RFC 8188: salt(16) + rs(4 BE) + idlen(1) + server_pub(65) + ciphertext + tag(16)
    $body = $salt . pack('N', 4096) . "\x41" . $srv_pub . $ciphertext . $tag;

    return ['body' => $body, 'salt_b64' => epl_b64url_encode($salt)];
}

/**
 * Envía un Web Push a una suscripción.
 * $sub = ['endpoint','p256dh','auth']
 * Devuelve ['ok'=>bool, 'status'=>int, 'error'=>string]
 */
function epl_web_push_send(array $sub, string $title, string $body_text, string $url = '/dashboard.php'): array {
    $vapid_pub  = epl_env('VAPID_PUBLIC_KEY');
    $vapid_priv = epl_env('VAPID_PRIVATE_KEY');
    if (!$vapid_pub || !$vapid_priv) {
        return ['ok' => false, 'status' => 0, 'error' => 'VAPID keys not configured'];
    }

    $endpoint = $sub['endpoint'];
    $payload  = json_encode([
        'title' => $title,
        'body'  => $body_text,
        'icon'  => '/assets/img/logo-epl-square.png',
        'url'   => $url,
    ]);

    // Importar clave privada desde raw bytes (PKCS8 DER para EC)
    $priv_raw = epl_b64url_decode($vapid_priv); // 32 bytes
    // Construir PKCS#8 DER para P-256 private key
    $priv_der = "\x30\x41"                   // SEQUENCE
              . "\x02\x01\x00"               // version = 0
              . "\x30\x13"                   // SEQUENCE (AlgorithmIdentifier)
              . "\x06\x07\x2a\x86\x48\xce\x3d\x02\x01"  // OID ecPublicKey
              . "\x06\x08\x2a\x86\x48\xce\x3d\x03\x01\x07"  // OID P-256
              . "\x04\x27"                   // OCTET STRING (39 bytes)
              . "\x30\x25"                   // SEQUENCE (ECPrivateKey)
              . "\x02\x01\x01"               // version = 1
              . "\x04\x20"                   // OCTET STRING (32 bytes)
              . $priv_raw;
    $priv_pem = "-----BEGIN PRIVATE KEY-----\n"
              . chunk_split(base64_encode($priv_der), 64, "\n")
              . "-----END PRIVATE KEY-----";

    $jwt     = epl_vapid_jwt($endpoint, $priv_pem, 'mailto:info@epleague.cl');
    $enc     = epl_web_push_encrypt($payload, $sub['p256dh'], $sub['auth']);

    $headers = [
        'Content-Type: application/octet-stream',
        'Content-Encoding: aes128gcm',
        'TTL: 86400',
        'Urgency: normal',
        'Authorization: vapid t=' . $jwt . ',k=' . $vapid_pub,
    ];

    $ch = curl_init($endpoint);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $enc['body'],
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 8,
        CURLOPT_CONNECTTIMEOUT => 4,
    ]);
    $resp   = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err    = curl_error($ch);
    curl_close($ch);

    $ok = $status >= 200 && $status < 300;
    return ['ok' => $ok, 'status' => $status, 'error' => $ok ? '' : ($err ?: $resp)];
}

/** Envía push a todos los dispositivos suscritos de un jugador */
function epl_push_jugador(int $jugador_id, string $title, string $body, string $url = '/dashboard.php'): int {
    $db   = epl_db();
    try {
        $subs = $db->prepare("SELECT endpoint, p256dh, auth FROM push_subscriptions WHERE jugador_id=?");
        $subs->execute([$jugador_id]);
        $rows = $subs->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) { return 0; }

    $sent = 0;
    foreach ($rows as $sub) {
        $res = epl_web_push_send($sub, $title, $body, $url);
        if ($res['ok']) $sent++;
        // Si el endpoint ya no existe, borrarlo
        if (in_array($res['status'], [404, 410])) {
            try {
                $db->prepare("DELETE FROM push_subscriptions WHERE endpoint=?")->execute([$sub['endpoint']]);
            } catch (Throwable $e) {}
        }
    }
    return $sent;
}
