<?php
// Protección: solo admin logueado puede ejecutar este script de diagnóstico.
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
epl_require_admin();
// ─────────────────────────────────────────────────────────────────────────────$db = new PDO('mysql:host=localhost;dbname=epleague;charset=utf8mb4', 'root', '');
$res = $db->query('SELECT id, nombre FROM equipos')->fetchAll(PDO::FETCH_ASSOC);
print_r($res);

