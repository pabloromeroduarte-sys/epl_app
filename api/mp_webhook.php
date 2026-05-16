<?php
/**
 * Webhook MercadoPago — recibe notificaciones IPN/webhook de pagos
 * Configurar en MP Developers → Tu App → Webhooks: apuntar a esta URL
 */
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/functions.php';

// Log de requests para debugging
$log_file = dirname(__DIR__) . '/logs/mp_webhook.log';
if (!is_dir(dirname($log_file))) {
    @mkdir(dirname($log_file), 0755, true);
}

$raw_body = file_get_contents('php://input');
@file_put_contents($log_file, date('[Y-m-d H:i:s] ') . $_SERVER['REQUEST_METHOD'] . ' ' . json_encode($_GET) . ' BODY=' . substr($raw_body, 0, 500) . "\n", FILE_APPEND);

// MercadoPago envía GET con type=payment&data.id=XXX (IPN) o POST con JSON (webhooks)
$mp_token = epl_config_get('mp_access_token');
if (!$mp_token) {
    http_response_code(200);
    exit('no token configured');
}

$payment_id = null;
$topic      = null;

// IPN: GET ?id=123&topic=payment
if (isset($_GET['id']) && isset($_GET['topic'])) {
    $payment_id = preg_replace('/[^0-9]/', '', $_GET['id']);
    $topic      = $_GET['topic'];
}
// Webhook: POST JSON body
elseif ($raw_body) {
    $data = json_decode($raw_body, true);
    if (isset($data['data']['id'])) {
        $payment_id = preg_replace('/[^0-9]/', '', $data['data']['id']);
        $topic      = $data['type'] ?? 'payment';
    }
}

if (!$payment_id || $topic !== 'payment') {
    http_response_code(200);
    exit('ok - not a payment');
}

// Consultar el pago en la API de MercadoPago
$ch = curl_init("https://api.mercadopago.com/v1/payments/{$payment_id}");
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $mp_token],
    CURLOPT_TIMEOUT        => 15,
]);
$resp = curl_exec($ch);
curl_close($ch);

$pago = json_decode($resp, true);
@file_put_contents($log_file, date('[Y-m-d H:i:s] ') . 'MP response: ' . json_encode($pago) . "\n", FILE_APPEND);

if (!isset($pago['status'])) {
    http_response_code(200);
    exit('no status in response');
}

$external_ref = $pago['external_reference'] ?? '';
$status       = $pago['status'];           // approved, pending, rejected, etc.

if ($status === 'approved' && $external_ref) {
    $db = epl_db();

    // Marcar el pago como completado
    epl_pago_completar($external_ref, (string)$payment_id);

    // Marcar inscripción como pagada y aprobada
    $db->prepare("UPDATE inscripciones SET pago_estado='pagado', pago_ref=?,
        estado = IF(equipo_id IS NOT NULL, 'aprobada', 'pendiente')
        WHERE token=?")->execute([(string)$payment_id, $external_ref]);

    // Si fue el partner, aprobar también al capitán
    $insc = $db->prepare("
        SELECT i.*, l.nombre AS liga_nombre
        FROM inscripciones i JOIN ligas l ON l.id=i.liga_id
        WHERE i.token=?
    ");
    $insc->execute([$external_ref]);
    $insc = $insc->fetch();

    if ($insc && $insc['rol_equipo'] === 'partner' && $insc['equipo_id']) {
        $db->prepare("UPDATE inscripciones SET estado='aprobada' WHERE equipo_id=? AND rol_equipo='capitan' AND pago_estado='pagado'")
           ->execute([$insc['equipo_id']]);
    }

    // Activar equipo en la liga
    if ($insc && $insc['equipo_id'] && $insc['estado'] === 'aprobada') {
        epl_activar_equipo_en_liga((int)$insc['equipo_id'], (int)$insc['liga_id']);
    }

    if ($insc) {
        epl_notif_crear(
            (int)$insc['jugador_id'],
            'inscripcion',
            '✅ Pago confirmado',
            'Tu pago de inscripción en ' . $insc['liga_nombre'] . ' fue confirmado por MercadoPago.',
            epl_url('dashboard.php')
        );
    }
}

http_response_code(200);
echo 'ok';
