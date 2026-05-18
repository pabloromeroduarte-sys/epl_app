<?php
declare(strict_types=1);

/**
 * WebPush — Implementación nativa RFC 8291 + RFC 8292 (VAPID)
 * Sin dependencias externas. Requiere extensión openssl y PHP 8.0+
 */
class WebPush {

    private mixed  $privKey;
    private string $pubB64u;
    private string $subject;

    public function __construct(string $privKeyPem, string $pubB64u, string $subject) {
        $this->privKey  = openssl_pkey_get_private($privKeyPem);
        $this->pubB64u  = $pubB64u;
        $this->subject  = $subject;
    }

    // ── API pública ───────────────────────────────────────────────────

    public function send(string $endpoint, string $p256dh, string $auth, string $jsonPayload, int $ttl = 86400): bool {
        $parsed   = parse_url($endpoint);
        $audience = $parsed['scheme'] . '://' . $parsed['host'];
        $jwt      = $this->vapidJwt($audience);
        $authHdr  = 'vapid t=' . $jwt . ',k=' . $this->pubB64u;

        $encrypted = $this->encrypt($jsonPayload, $p256dh, $auth);

        $ch = curl_init($endpoint);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $encrypted,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/octet-stream',
                'Content-Encoding: aes128gcm',
                'TTL: ' . $ttl,
                'Authorization: ' . $authHdr,
            ],
            CURLOPT_TIMEOUT => 10,
        ]);
        $httpCode = (int)curl_getinfo(curl_exec($ch) !== false ? $ch : $ch, CURLINFO_HTTP_CODE);
        curl_exec($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return $httpCode >= 200 && $httpCode < 300;
    }

    // ── VAPID JWT (RFC 8292) ──────────────────────────────────────────

    private function vapidJwt(string $audience): string {
        $header  = self::b64u(json_encode(['typ' => 'JWT', 'alg' => 'ES256']));
        $claims  = self::b64u(json_encode([
            'aud' => $audience,
            'exp' => time() + 43200,
            'sub' => $this->subject,
        ]));
        $input = $header . '.' . $claims;
        openssl_sign($input, $sig, $this->privKey, OPENSSL_ALGO_SHA256);
        return $input . '.' . self::b64u(self::derToRaw($sig));
    }

    // ── Cifrado aes128gcm (RFC 8291) ──────────────────────────────────

    private function encrypt(string $payload, string $p256dhB64u, string $authB64u): string {
        $salt = random_bytes(16);

        // Generar clave efímera EC P-256
        $eph = openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);
        $ephDetails    = openssl_pkey_get_details($eph);
        $ephPublicRaw  = self::pointFromDetails($ephDetails['ec']);

        // Clave pública del suscriptor
        $uaPublicRaw   = self::b64u_decode($p256dhB64u);
        $uaPublicKey   = self::importEcPublic($uaPublicRaw);
        $authRaw       = self::b64u_decode($authB64u);

        // ECDH
        $ecdhSecret = openssl_pkey_derive($uaPublicKey, $eph);

        // Derivación de claves (RFC 8291 §3.3)
        $authInfo = "WebPush: info\x00" . $uaPublicRaw . $ephPublicRaw;
        $prkKey   = hash_hmac('sha256', $ecdhSecret, $authRaw, true);
        $ikm      = self::hkdfExpand($prkKey, $authInfo . "\x01", 32);

        $prk   = hash_hmac('sha256', $ikm, $salt, true);
        $cek   = self::hkdfExpand($prk, "Content-Encoding: aes128gcm\x00\x01", 16);
        $nonce = self::hkdfExpand($prk, "Content-Encoding: nonce\x00\x01", 12);

        // Cifrar con AES-128-GCM
        $record = $payload . "\x02";   // padding delimiter RFC 8188
        $tag    = '';
        $ct     = openssl_encrypt($record, 'aes-128-gcm', $cek, OPENSSL_RAW_DATA, $nonce, $tag, '', 16);

        // Cabecera: salt(16) + rs(4) + idlen(1) + ephPublic(65)
        return $salt . pack('N', 4096) . chr(65) . $ephPublicRaw . $ct . $tag;
    }

    // ── Helpers ───────────────────────────────────────────────────────

    private static function importEcPublic(string $raw): mixed {
        $der = hex2bin('3059301306072a8648ce3d020106082a8648ce3d030107034200') . $raw;
        $pem = "-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode($der), 64, "\n") . "-----END PUBLIC KEY-----\n";
        return openssl_pkey_get_public($pem);
    }

    private static function pointFromDetails(array $ec): string {
        return "\x04"
            . str_pad($ec['x'], 32, "\x00", STR_PAD_LEFT)
            . str_pad($ec['y'], 32, "\x00", STR_PAD_LEFT);
    }

    private static function derToRaw(string $der): string {
        $pos = 2;
        if (ord($der[1]) & 0x80) $pos += ord($der[1]) & 0x7f;
        $pos++;
        $rLen = ord($der[$pos++]);
        $r    = substr($der, $pos, $rLen); $pos += $rLen;
        $pos++;
        $sLen = ord($der[$pos++]);
        $s    = substr($der, $pos, $sLen);
        $r    = str_pad(ltrim($r, "\x00"), 32, "\x00", STR_PAD_LEFT);
        $s    = str_pad(ltrim($s, "\x00"), 32, "\x00", STR_PAD_LEFT);
        return $r . $s;
    }

    private static function hkdfExpand(string $prk, string $info, int $len): string {
        $okm = ''; $t = '';
        for ($i = 1; strlen($okm) < $len; $i++) {
            $t   = hash_hmac('sha256', $t . $info . chr($i), $prk, true);
            $okm .= $t;
        }
        return substr($okm, 0, $len);
    }

    public static function b64u(string $data): string {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    public static function b64u_decode(string $data): string {
        return base64_decode(strtr($data, '-_', '+/') . str_repeat('=', (4 - strlen($data) % 4) % 4));
    }
}
