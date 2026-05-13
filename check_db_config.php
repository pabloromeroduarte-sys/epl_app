<?php
$db = new PDO('mysql:host=localhost;dbname=epleague;charset=utf8mb4', 'root', '');
$res = $db->query("SHOW CREATE TABLE jugadores")->fetch(PDO::FETCH_ASSOC);
print_r($res);
$res2 = $db->query("SHOW CREATE TABLE partidos")->fetch(PDO::FETCH_ASSOC);
print_r($res2);
