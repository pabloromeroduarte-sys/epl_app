<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

header('Content-Type: application/json');

$j = epl_jugador_actual();
if (!$j) { http_response_code(401); echo json_encode(['error' => 'no auth']); exit; }

$data = json_decode(file_get_contents('php://input'), true);
$endpoint = trim($data['endpoint'] ?? '');
$p256dh   = trim($data['keys']['p256dh'] ?? '');
$auth     = trim($data['keys']['auth']   ?? '');

if (!$endpoint || !$p256dh || !$auth) {
    http_response_code(400);
    echo json_encode(['error' => 'missing fields']);
    exit;
}

$db = epl_db();
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

$db->prepare("INSERT INTO push_subscriptions (jugador_id, endpoint, p256dh, auth)
    VALUES (?, ?, ?, ?)
    ON DUPLICATE KEY UPDATE jugador_id=VALUES(jugador_id), p256dh=VALUES(p256dh), auth=VALUES(auth)")
   ->execute([(int)$j['id'], $endpoint, $p256dh, $auth]);

http_response_code(201);
echo json_encode(['ok' => true]);
