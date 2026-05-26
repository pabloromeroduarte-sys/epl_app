<?php
require_once __DIR__ . '/includes/functions.php';

$db = epl_db();
$liga = epl_liga_activa();

if (!$liga) {
    echo "No hay liga activa.\n";
    exit;
}

echo "Liga Activa: " . $liga['nombre'] . " (ID: " . $liga['id'] . ")\n";

$st = $db->prepare("
    SELECT p.id, p.jornada, p.fecha_programada, p.estado,
           el.nombre AS local, ev.nombre AS visita,
           el.id AS local_id, ev.id AS visitante_id
    FROM partidos p
    JOIN equipos el ON el.id = p.equipo_local_id
    JOIN equipos ev ON ev.id = p.equipo_visitante_id
    WHERE p.liga_id = ? AND p.jornada = 9
    ORDER BY p.fecha_programada ASC
");
$st->execute([$liga['id']]);
$partidos = $st->fetchAll(PDO::FETCH_ASSOC);

print_r($partidos);
?>
