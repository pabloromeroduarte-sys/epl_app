<?php
$db = new PDO('mysql:host=localhost;dbname=epleague;charset=utf8mb4', 'root', '');
$res = $db->query('SELECT id, nombre FROM equipos')->fetchAll(PDO::FETCH_ASSOC);
print_r($res);
