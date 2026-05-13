<?php
error_reporting(E_ALL); ini_set('display_errors', 1);
$epl = new PDO('mysql:host=localhost;dbname=epleague;charset=utf8mb4', 'root', '');
$wp  = new PDO('mysql:host=localhost;dbname=wp_epl_import;charset=utf8mb4', 'root', '');

// 1. Cargar recintos
$recintos = [];
foreach ($epl->query("SELECT id, nombre FROM recintos") as $r) {
    $recintos[mb_strtolower($r['nombre'])] = (int)$r['id'];
}

// 2. Mapa equipo por nombre
$eplEquipos = [];
foreach ($epl->query("SELECT id, nombre FROM equipos") as $e) {
    $eplEquipos[mb_strtolower(trim($e['nombre']))] = (int)$e['id'];
}

$wpTeams = [];
foreach ($wp->query("SELECT ID, post_title FROM wpqu_posts WHERE post_type='sp_team'") as $t) {
    $wpTeams[(int)$t['ID']] = mb_strtolower(trim(html_entity_decode($t['post_title'], ENT_QUOTES, 'UTF-8')));
}

$equipoMap = []; // wp_team_id => epl_equipo_id
foreach ($wpTeams as $wpId => $wpName) {
    if (isset($eplEquipos[$wpName])) {
        $equipoMap[$wpId] = $eplEquipos[$wpName];
    }
}

// 3. Eventos WP
$events = $wp->query("
    SELECT p.ID as wp_id, p.post_date as fecha_post,
           MAX(CASE WHEN pm.meta_key='nombre_jornada' THEN pm.meta_value END) as nombre_fecha,
           MAX(CASE WHEN pm.meta_key='sp_time' THEN pm.meta_value END) as hora,
           GROUP_CONCAT(DISTINCT CASE WHEN pm.meta_key='sp_team' THEN pm.meta_value END ORDER BY pm.meta_id ASC) as team_ids,
           (
               SELECT GROUP_CONCAT(t.name)
               FROM wpqu_term_relationships tr
               JOIN wpqu_term_taxonomy tt ON tt.term_taxonomy_id = tr.term_taxonomy_id AND tt.taxonomy='sp_venue'
               JOIN wpqu_terms t ON t.term_id = tt.term_id
               WHERE tr.object_id = p.ID
           ) as venues
    FROM wpqu_posts p
    LEFT JOIN wpqu_postmeta pm ON pm.post_id=p.ID
    WHERE p.post_type='sp_event' AND p.post_status IN ('publish','future')
    GROUP BY p.ID
")->fetchAll(PDO::FETCH_ASSOC);

$stUpd = $epl->prepare("
    UPDATE partidos
    SET nombre_fecha = ?,
        jornada = ?,
        recinto_id = ?,
        fecha_programada = ?
    WHERE (
        (equipo_local_id = ? AND equipo_visitante_id = ?) OR
        (equipo_local_id = ? AND equipo_visitante_id = ?)
    )
");

$ok = 0; $skip = 0;
foreach ($events as $ev) {
    $teams = array_filter(array_map('intval', explode(',', $ev['team_ids'] ?? '')));
    $teams = array_values($teams);
    if (count($teams) < 2) continue;

    $eplT1 = $equipoMap[$teams[0]] ?? null;
    $eplT2 = $equipoMap[$teams[1]] ?? null;
    if (!$eplT1 || !$eplT2) { $skip++; continue; }

    // Jornada
    $nombre_fecha = $ev['nombre_fecha'] ? html_entity_decode($ev['nombre_fecha'], ENT_QUOTES, 'UTF-8') : null;
    $jornada_num  = null;
    if ($nombre_fecha) {
        preg_match('/(\d+)/', $nombre_fecha, $m);
        $jornada_num = isset($m[1]) ? (int)$m[1] : null;
    }

    // Fecha
    $fecha = $ev['fecha_post'];
    if ($ev['hora'] && $ev['hora'] !== '00:00') {
        $fecha = date('Y-m-d', strtotime($ev['fecha_post'])) . ' ' . $ev['hora'] . ':00';
    }

    // Recinto
    $recinto_id = null;
    if ($ev['venues']) {
        $vnames = explode(',', $ev['venues']);
        foreach ($vnames as $vn) {
            $vn = mb_strtolower(trim(html_entity_decode($vn, ENT_QUOTES, 'UTF-8')));
            if (isset($recintos[$vn])) {
                $recinto_id = $recintos[$vn];
            }
        }
    }

    $stUpd->execute([$nombre_fecha, $jornada_num, $recinto_id, $fecha, $eplT1, $eplT2, $eplT2, $eplT1]);
    if ($stUpd->rowCount() > 0) $ok++;
}

echo "Partidos actualizados con exito: $ok\n";
echo "Partidos saltados por falta de equipo: $skip\n";
