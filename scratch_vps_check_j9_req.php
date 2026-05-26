<?php
require_once __DIR__ . '/includes/functions.php';
$db = epl_db();

$st = $db->query("
    SELECT sr.*, p.jornada, el.nombre AS local, ev.nombre AS visita
    FROM solicitudes_reprogramacion sr
    JOIN partidos p ON p.id = sr.partido_id
    JOIN equipos el ON el.id = p.equipo_local_id
    JOIN equipos ev ON ev.id = p.equipo_visitante_id
    WHERE sr.partido_id = 73 OR p.jornada = 9
    ORDER BY sr.created_at DESC
");
print_r($st->fetchAll(PDO::FETCH_ASSOC));
?>
