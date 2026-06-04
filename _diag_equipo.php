<?php
/**
 * Diagnóstico temporal — equipos con separador "/" o sospechosos.
 * Uso por CLI:  php _diag_equipo.php
 * BORRAR luego de usarlo.
 */
require __DIR__ . '/includes/functions.php';
$db = epl_db();

function pj($db, $jid) {
    $jid = (int)$jid;
    if (!$jid) return "(vacío)";
    $j = $db->query("SELECT id,nombre,apellido,email,estado,created_at FROM jugadores WHERE id={$jid}")->fetch(PDO::FETCH_ASSOC);
    if (!$j) return "#{$jid} NO EXISTE";
    return "#{$j['id']} {$j['nombre']} {$j['apellido']} <{$j['email']}> [{$j['estado']}] creado:{$j['created_at']}";
}

echo "=========================================================\n";
echo " EQUIPOS CON SEPARADOR '/'  (nombre tipeado a mano)\n";
echo "=========================================================\n";
$rows = $db->query("SELECT id,nombre,jugador1_id,jugador2_id,estado FROM equipos WHERE nombre LIKE '% / %' ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
echo "Encontrados: " . count($rows) . "\n\n";
foreach ($rows as $e) {
    echo "EQUIPO #{$e['id']}  [{$e['nombre']}]  estado={$e['estado']}\n";
    echo "   J1: " . pj($db, $e['jugador1_id']) . "\n";
    echo "   J2: " . pj($db, $e['jugador2_id']) . "\n";
    // Ligas
    try {
        $lg = $db->query("SELECT le.liga_id, l.nombre FROM liga_equipos le LEFT JOIN ligas l ON l.id=le.liga_id WHERE le.equipo_id={$e['id']}")->fetchAll(PDO::FETCH_ASSOC);
        echo "   Ligas: " . (count($lg) ? implode(", ", array_map(fn($g)=>"#{$g['liga_id']} {$g['nombre']}", $lg)) : "(ninguna)") . "\n";
    } catch (Throwable $ex) {}
    // Partidos jugados
    try {
        $np = (int)$db->query("SELECT COUNT(*) FROM partidos WHERE equipo_local_id={$e['id']} OR equipo_visitante_id={$e['id']}")->fetchColumn();
        echo "   Partidos asociados: {$np}\n";
    } catch (Throwable $ex) {}
    echo "\n";
}

echo "=========================================================\n";
echo " BUSCAR jugadores Campos / Rivera\n";
echo "=========================================================\n";
$js = $db->query("SELECT id,nombre,apellido,email,estado FROM jugadores WHERE apellido LIKE '%Campos%' OR apellido LIKE '%Rivera%' ORDER BY apellido")->fetchAll(PDO::FETCH_ASSOC);
foreach ($js as $j) echo "   #{$j['id']} {$j['nombre']} {$j['apellido']} <{$j['email']}> [{$j['estado']}]\n";
echo "\nListo. (Acordate de borrar este archivo)\n";
