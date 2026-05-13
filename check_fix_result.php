<?php
$db = new PDO('mysql:host=localhost;dbname=epleague;charset=utf8mb4', 'root', '');
$res = $db->query('SELECT id, nombre, apellido FROM jugadores WHERE id < 50 LIMIT 20')->fetchAll(PDO::FETCH_ASSOC);
print_r($res);
