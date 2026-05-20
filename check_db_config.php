<?php
// Protección: solo admin logueado puede ejecutar este script de diagnóstico.
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
epl_require_admin();
// ─────────────────────────────────────────────────────────────────────────────$db = new PDO('mysql:host=localhost;dbname=epleague;charset=utf8mb4', 'root', '');
$res = $db->query("SHOW CREATE TABLE jugadores")->fetch(PDO::FETCH_ASSOC);
print_r($res);
$res2 = $db->query("SHOW CREATE TABLE partidos")->fetch(PDO::FETCH_ASSOC);
print_r($res2);

