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

// 1) Notificación in-app (campana)
epl_notif_crear(
    $target_id,
    '🔔 Notificación de prueba',
    'Esta es una notificación de prueba enviada desde el panel de administración.',
    epl_url('dashboard.php')
);

// 2) Push web (si hay suscripciones)
$pushed = epl_push_jugador(
    $target_id,
    '🔔 Elite Padel League',
    'Notificación de prueba — el sistema funciona correctamente.',
    '/dashboard.php'
);

echo json_encode([
    'ok'        => true,
    'inapp'     => true,
    'push_sent' => $pushed,
    'msg'       => $pushed > 0
        ? "✅ Push enviado a $pushed dispositivo(s)"
        : "⚠️ In-app creada, pero no hay dispositivos suscritos a push todavía.",
]);
