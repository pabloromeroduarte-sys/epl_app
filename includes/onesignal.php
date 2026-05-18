<?php
declare(strict_types=1);

/**
 * Envía una notificación push via OneSignal a uno o varios jugadores.
 *
 * @param array|int  $jugador_ids  ID(s) de jugador del sistema (external_id)
 * @param string     $titulo
 * @param string     $mensaje
 * @param string     $url          URL a abrir al tocar la notificación
 */
function epl_push_notificar($jugador_ids, string $titulo, string $mensaje, string $url = ''): void {
    $app_id  = $_ENV['ONESIGNAL_APP_ID']  ?? '';
    $api_key = $_ENV['ONESIGNAL_REST_KEY'] ?? '';

    if (!$app_id || !$api_key) return; // No configurado aún

    $ids = array_map('strval', is_array($jugador_ids) ? $jugador_ids : [$jugador_ids]);
    if (empty($ids)) return;

    $payload = [
        'app_id'            => $app_id,
        'include_aliases'   => ['external_id' => $ids],
        'target_channel'    => 'push',
        'headings'          => ['en' => $titulo, 'es' => $titulo],
        'contents'          => ['en' => $mensaje, 'es' => $mensaje],
        'url'               => $url ?: 'https://epleague.cl/dashboard.php',
        'chrome_web_icon'   => 'https://epleague.cl/assets/img/logo-epl-square.png',
        'firefox_icon'      => 'https://epleague.cl/assets/img/logo-epl-square.png',
    ];

    $ch = curl_init('https://onesignal.com/api/v1/notifications');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($payload),
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Authorization: Key ' . $api_key,
        ],
        CURLOPT_TIMEOUT        => 5,
    ]);
    curl_exec($ch);
    curl_close($ch);
}
