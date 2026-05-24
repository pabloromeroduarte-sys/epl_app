<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/web_push.php';

header('Content-Type: application/json');

$jugador = epl_jugador_actual();
if (!$jugador || $jugador['rol'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'No autorizado']);
    exit;
}

$target_id = (int)($_POST['jugador_id'] ?? $jugador['id']);

// 1) Notificación in-app (campana) — FIX: parámetros en orden correcto
epl_notif_crear(
    $target_id,
    'admin',                                                          // tipo
    '🔔 Notificación de prueba',                                       // titulo
    'Push de prueba enviado desde el panel de administración.',       // mensaje
    epl_url('dashboard.php')                                          // url
);

// 2) Push web — detalle por device
$db = epl_db();
$st = $db->prepare("SELECT id, endpoint, p256dh, auth, created_at FROM push_subscriptions WHERE jugador_id = ?");
$st->execute([$target_id]);
$subs = $st->fetchAll(PDO::FETCH_ASSOC);

$total    = count($subs);
$enviados = 0;
$fallidos = 0;
$detalles = [];

foreach ($subs as $sub) {
    $res = epl_web_push_send($sub, '🔔 Elite Padel League', 'Notificación de prueba — ¿la recibís?', '/dashboard.php');

    // Detectar plataforma
    $plataforma = 'Otro';
    if (strpos($sub['endpoint'], 'fcm.googleapis.com') !== false)   $plataforma = '🤖 Android / Chrome';
    elseif (strpos($sub['endpoint'], 'push.apple.com') !== false)   $plataforma = '🍎 iOS Safari';
    elseif (strpos($sub['endpoint'], 'mozilla') !== false)          $plataforma = '🦊 Firefox';
    elseif (strpos($sub['endpoint'], 'windows.com') !== false)      $plataforma = '🪟 Edge / Windows';

    if ($res['ok']) {
        $enviados++;
        $estado = 'ok';
    } else {
        $fallidos++;
        $estado = 'error';
    }

    // Borrar endpoints muertos
    if (in_array($res['status'], [404, 410])) {
        try {
            $db->prepare("DELETE FROM push_subscriptions WHERE id = ?")->execute([$sub['id']]);
            $estado = 'borrado';
        } catch (Throwable $e) {}
    }

    $detalles[] = [
        'plataforma' => $plataforma,
        'estado'     => $estado,
        'status'     => $res['status'],
        'error'      => $res['error'] ? substr($res['error'], 0, 200) : '',
        'creado'     => $sub['created_at'],
    ];
}

echo json_encode([
    'ok'          => true,
    'inapp'       => true,
    'total_subs'  => $total,
    'enviados'    => $enviados,
    'fallidos'    => $fallidos,
    'detalles'    => $detalles,
    'msg'         => $total === 0
        ? "⚠️ Este jugador no tiene dispositivos suscritos a push."
        : "📤 {$enviados} enviado(s), {$fallidos} fallido(s) de {$total} dispositivo(s).",
]);
