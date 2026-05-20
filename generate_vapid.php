<?php
// Protección: solo admin logueado puede ejecutar este script de diagnóstico.
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
epl_require_admin();
// ─────────────────────────────────────────────────────────────────────────────/**
 * Genera claves VAPID para Web Push.
 * Ejecutar UNA SOLA VEZ en el servidor:  php generate_vapid.php
 * Luego agregar las líneas al .env y ELIMINAR este archivo.
 */
if (php_sapi_name() !== 'cli' && ($_SERVER['REMOTE_ADDR'] ?? '') !== '127.0.0.1') {
    http_response_code(403); exit('Solo ejecutar desde CLI o localhost.');
}

$key = openssl_pkey_new([
    'curve_name'        => 'prime256v1',
    'private_key_type'  => OPENSSL_KEYTYPE_EC,
]);

openssl_pkey_export($key, $privPem);
$details = openssl_pkey_get_details($key);

$pubRaw  = "\x04"
    . str_pad($details['ec']['x'], 32, "\x00", STR_PAD_LEFT)
    . str_pad($details['ec']['y'], 32, "\x00", STR_PAD_LEFT);

$pubB64u  = rtrim(strtr(base64_encode($pubRaw), '+/', '-_'), '=');
$privB64  = base64_encode($privPem);

echo "\n✅ Claves VAPID generadas. Agrega estas líneas a tu .env:\n\n";
echo "VAPID_PRIVATE_KEY={$privB64}\n";
echo "VAPID_PUBLIC_KEY={$pubB64u}\n";
echo "VAPID_SUBJECT=mailto:admin@epleague.cl\n\n";
echo "⚠️  Elimina este archivo después de copiar las claves.\n\n";

