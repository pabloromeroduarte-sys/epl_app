<?php
$db = new PDO('mysql:host=localhost;dbname=epleague;charset=utf8mb4', 'root', '');
$res = $db->query('SELECT nombre, apellido FROM jugadores WHERE apellido LIKE "%Ã%" OR nombre LIKE "%Ã%" LIMIT 10')->fetchAll(PDO::FETCH_ASSOC);
echo "JUGADORES CON PROBLEMAS:\n";
print_r($res);
