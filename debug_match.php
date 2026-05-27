<?php
require 'includes/functions.php';
$db = epl_db();
$st = $db->query("SELECT p.id, p.fecha_programada, p.fecha_original, p.estado, s.fecha_propuesta, s.estado as sol_estado FROM partidos p LEFT JOIN solicitudes_reprogramacion s ON p.id=s.partido_id WHERE p.equipo_local_id IN (SELECT id FROM equipos WHERE nombre LIKE '%Eyzaguirre%') ORDER BY s.id DESC LIMIT 1");
print_r($st->fetch(PDO::FETCH_ASSOC));
