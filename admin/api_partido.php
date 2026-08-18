<?php
declare(strict_types=1);

require_once '../includes/auth.php';
require_once '../includes/functions.php';

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');

$admin = epl_jugador_actual();
if (!$admin || ($admin['rol'] ?? '') !== 'admin') {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Acceso no autorizado.']);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Método no permitido.']);
    exit;
}

$partido_id = (int)($_GET['id'] ?? 0);
if (!$partido_id) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Partido no válido.']);
    exit;
}

epl_ensure_partidos_columnas_originales();
$db = epl_db();
$st = $db->prepare("
    SELECT p.*,
           el.nombre AS local_nombre,
           ev.nombre AS visitante_nombre,
           jl1.nombre AS jl1_n, jl1.apellido AS jl1_a, jl1.telefono AS jl1_t,
           jl2.nombre AS jl2_n, jl2.apellido AS jl2_a, jl2.telefono AS jl2_t,
           jv1.nombre AS jv1_n, jv1.apellido AS jv1_a, jv1.telefono AS jv1_t,
           jv2.nombre AS jv2_n, jv2.apellido AS jv2_a, jv2.telefono AS jv2_t
    FROM partidos p
    JOIN equipos el ON el.id = p.equipo_local_id
    JOIN equipos ev ON ev.id = p.equipo_visitante_id
    LEFT JOIN jugadores jl1 ON jl1.id = el.jugador1_id
    LEFT JOIN jugadores jl2 ON jl2.id = el.jugador2_id
    LEFT JOIN jugadores jv1 ON jv1.id = ev.jugador1_id
    LEFT JOIN jugadores jv2 ON jv2.id = ev.jugador2_id
    WHERE p.id = ?
    LIMIT 1
");
$st->execute([$partido_id]);
$partido = $st->fetch(PDO::FETCH_ASSOC);

if (!$partido) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'Partido no encontrado.']);
    exit;
}

echo json_encode(
    ['ok' => true, 'partido' => $partido],
    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
);
