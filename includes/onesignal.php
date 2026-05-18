<?php
declare(strict_types=1);
/**
 * Envía Web Push nativo a uno o varios jugadores.
 * Lee suscripciones desde push_subscriptions y usa VAPID del .env
 */
function epl_push_notificar($jugador_ids, string $titulo, string $mensaje, string $url = ''): void {
    $privPem   = base64_decode($_ENV['VAPID_PRIVATE_KEY'] ?? '');
    $pubB64u   = $_ENV['VAPID_PUBLIC_KEY']  ?? '';
    $subject   = $_ENV['VAPID_SUBJECT']     ?? 'mailto:admin@epleague.cl';

    if (!$privPem || !$pubB64u) return;

    $ids = array_map('intval', is_array($jugador_ids) ? $jugador_ids : [$jugador_ids]);
    if (empty($ids)) return;

    $db = epl_db();

    // Crear tabla si no existe
    $db->query("CREATE TABLE IF NOT EXISTS push_subscriptions (
        id         INT AUTO_INCREMENT PRIMARY KEY,
        jugador_id INT NOT NULL,
        endpoint   TEXT NOT NULL,
        p256dh     VARCHAR(500) NOT NULL,
        auth       VARCHAR(255) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_endpoint (endpoint(255)),
        INDEX      idx_jugador (jugador_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $ph   = implode(',', array_fill(0, count($ids), '?'));
    $subs = $db->prepare("SELECT * FROM push_subscriptions WHERE jugador_id IN ($ph)");
    $subs->execute($ids);
    $rows = $subs->fetchAll();
    if (empty($rows)) return;

    require_once __DIR__ . '/WebPush.php';
    $wp = new WebPush($privPem, $pubB64u, $subject);

    $payload = json_encode([
        'title' => $titulo,
        'body'  => $mensaje,
        'url'   => $url ?: 'https://epleague.cl/dashboard.php',
        'icon'  => 'https://epleague.cl/assets/img/logo-epl-square.png',
    ]);

    foreach ($rows as $sub) {
        try {
            $wp->send($sub['endpoint'], $sub['p256dh'], $sub['auth'], $payload);
        } catch (Throwable $e) {
            error_log('WebPush: ' . $e->getMessage());
            // Si el endpoint expiró, eliminarlo
            if (str_contains($e->getMessage(), '410') || str_contains($e->getMessage(), '404')) {
                $db->prepare("DELETE FROM push_subscriptions WHERE id=?")->execute([$sub['id']]);
            }
        }
    }
}
