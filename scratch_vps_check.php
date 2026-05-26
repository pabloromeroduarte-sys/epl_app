<?php
require_once __DIR__ . '/includes/functions.php';

$db = epl_db();
$liga = epl_liga_activa();

if (!$liga) {
    echo "No hay liga activa.\n";
    exit;
}

echo "Liga Activa: " . $liga['nombre'] . " (ID: " . $liga['id'] . ")\n";
echo "Fecha actual (PHP): " . date('Y-m-d H:i:s') . "\n";

// Obtener partidos ordenados por fecha_programada
$st = $db->prepare("
    SELECT p.id, p.jornada, p.fecha_programada, p.estado,
           el.nombre AS local, ev.nombre AS visita
    FROM partidos p
    JOIN equipos el ON el.id = p.equipo_local_id
    JOIN equipos ev ON ev.id = p.equipo_visitante_id
    WHERE p.liga_id = ? AND p.fecha_programada IS NOT NULL AND p.fecha_programada > '1900-01-01'
    ORDER BY p.fecha_programada ASC
");
$st->execute([$liga['id']]);
$partidos = $st->fetchAll();

echo "\n--- Todos los partidos con fecha programada ---\n";
foreach ($partidos as $p) {
    echo "ID: {$p['id']} | Jornada: {$p['jornada']} | Fecha: {$p['fecha_programada']} | Estado: {$p['estado']} | {$p['local']} vs {$p['visita']}\n";
}

echo "\n--- Cálculo de jornada reciente (cercana a NOW()) ---\n";
$stReciente = $db->prepare("
    SELECT p.jornada, p.fecha_programada, ABS(TIMESTAMPDIFF(SECOND, p.fecha_programada, NOW())) as diff
    FROM partidos p
    WHERE p.liga_id = ?
      AND p.fecha_programada IS NOT NULL
      AND p.fecha_programada > '1900-01-01'
      AND p.jornada IS NOT NULL AND p.jornada > 0
    ORDER BY diff ASC
    LIMIT 5
");
$stReciente->execute([$liga['id']]);
$recientes = $stReciente->fetchAll();
foreach ($recientes as $r) {
    echo "Jornada: {$r['jornada']} | Fecha: {$r['fecha_programada']} | Diff segundos: {$r['diff']}\n";
}
?>
