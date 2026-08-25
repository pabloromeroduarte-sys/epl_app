<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/watch.php';

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

function epl_watch_json(array $data, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function epl_watch_body(): array
{
    $raw = file_get_contents('php://input');
    if ($raw === false || trim($raw) === '') {
        return [];
    }
    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        epl_watch_json(['ok' => false, 'error' => 'JSON no válido.'], 400);
    }
    return $decoded;
}

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$action = trim((string)($_GET['action'] ?? ''));

try {
    if ($action === 'pair_start' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $body = epl_watch_body();
        epl_watch_json(['ok' => true] + epl_watch_pair_start((string)($body['device_name'] ?? 'Galaxy Watch FE')));
    }

    if ($action === 'pair_status' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $body = epl_watch_body();
        epl_watch_json(['ok' => true] + epl_watch_pair_status((string)($body['device_code'] ?? '')));
    }

    $player = epl_watch_authenticate(epl_watch_bearer_token());
    if (!$player) {
        epl_watch_json(['ok' => false, 'error' => 'Reloj no vinculado o sesión vencida.'], 401);
    }
    $playerId = (int)$player['jugador_id'];

    if ($action === 'me' && $_SERVER['REQUEST_METHOD'] === 'GET') {
        epl_watch_json([
            'ok' => true,
            'player' => [
                'id' => $playerId,
                'name' => trim((string)$player['nombre'] . ' ' . (string)$player['apellido']),
            ],
        ]);
    }

    if ($action === 'matches' && $_SERVER['REQUEST_METHOD'] === 'GET') {
        epl_watch_json(['ok' => true, 'matches' => epl_watch_match_rows($playerId)]);
    }

    if ($action === 'result' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $body = epl_watch_body();
        $idempotency = (string)($_SERVER['HTTP_IDEMPOTENCY_KEY'] ?? ($body['idempotency_key'] ?? ''));
        $result = epl_watch_submit_result(
            $playerId,
            (int)($body['partido_id'] ?? 0),
            is_array($body['sets'] ?? null) ? $body['sets'] : [],
            $idempotency
        );
        epl_watch_json($result);
    }

    epl_watch_json(['ok' => false, 'error' => 'Ruta no encontrada.'], 404);
} catch (InvalidArgumentException $e) {
    epl_watch_json(['ok' => false, 'error' => $e->getMessage()], 422);
} catch (RuntimeException $e) {
    epl_watch_json(['ok' => false, 'error' => $e->getMessage()], 409);
} catch (Throwable $e) {
    error_log('EPL Watch API: ' . $e->getMessage());
    epl_watch_json(['ok' => false, 'error' => 'No pudimos completar la operación. Inténtalo nuevamente.'], 500);
}

