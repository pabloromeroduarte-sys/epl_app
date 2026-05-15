<?php
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/functions.php';

header('Content-Type: application/json');
$jugador = epl_jugador_actual();
if (!$jugador) { echo json_encode(['count' => 0]); exit; }

echo json_encode(['count' => epl_notif_no_leidas((int)$jugador['id'])]);
