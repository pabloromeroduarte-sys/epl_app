<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

header('Content-Type: application/json');

$jugador = epl_jugador_actual();
if (!$jugador) {
    http_response_code(401);
    echo json_encode(['ok' => false]);
    exit;
}

$body = json_decode(file_get_contents('php://input') ?: '{}', true);
$endpoint = $body['endpoint'] ?? '';

try {
    $db = epl_db();
    if ($endpoint) {
        // Borrar solo este endpoint
        $st = $db->prepare("DELETE FROM push_subscriptions WHERE jugador_id=? AND endpoint=?");
        $st->execute([$jugador['id'], $endpoint]);
    } else {
        // Borrar TODAS las subs del jugador (reset total)
        $st = $db->prepare("DELETE FROM push_subscriptions WHERE jugador_id=?");
        $st->execute([$jugador['id']]);
    }
    echo json_encode(['ok' => true, 'deleted' => $st->rowCount()]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
