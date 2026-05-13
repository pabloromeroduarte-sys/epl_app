<?php
/**
 * Migración WordPress/SportPress → EPL custom
 * Lee de wp_epl_import y escribe en epleague
 *
 * Acceso: http://localhost/elitepadelleague/migration/migrate_from_wp.php
 * Ejecutar UNA sola vez. Borrar después.
 */
error_reporting(E_ALL); ini_set('display_errors', 1);
set_time_limit(120);

// ── Conexiones ────────────────────────────────────────────────
$wp  = new PDO('mysql:host=localhost;dbname=wp_epl_import;charset=utf8mb4', 'root', '', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4",
]);
$epl = new PDO('mysql:host=localhost;dbname=epleague;charset=utf8mb4', 'root', '', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4",
]);

$log = [];
function log_ok($msg)  { global $log; $log[] = "✅ $msg"; }
function log_err($msg) { global $log; $log[] = "❌ $msg"; }
function log_info($msg){ global $log; $log[] = "ℹ️ $msg"; }

// ── Helpers ───────────────────────────────────────────────────
function map_nivel(string $v): int {
    preg_match('/(\d)/', $v, $m);
    return $m[1] ?? 5;
}
function map_lado(string $v): ?string {
    $v = mb_strtolower($v);
    if (str_contains($v,'drive'))   return 'derecha';
    if (str_contains($v,'rev'))     return 'reves';
    if (str_contains($v,'ambos'))   return 'ambos';
    return null;
}
function map_frecuencia(string $v): ?string {
    if (str_contains($v,'3+') || str_contains($v,'3 o'))  return '3_o_mas';
    if (str_contains($v,'2'))  return '2_semana';
    if (str_contains($v,'1'))  return '1_semana';
    if (str_contains($v,'cas') || str_contains($v,'oca')) return 'ocasional';
    return null;
}
function split_name(string $full): array {
    $parts = explode(' ', trim($full), 3);
    $nombre   = $parts[0] ?? '';
    $apellido = isset($parts[1]) ? implode(' ', array_slice($parts, 1)) : '';
    return [$nombre, $apellido];
}

// ── 1. Limpiar tablas EPL (orden de FK) ──────────────────────
$epl->exec("SET FOREIGN_KEY_CHECKS=0");
foreach (['suplente_partidos','suplentes','clasificacion','partidos','liga_equipos','inscripciones','equipos','ligas','jugadores'] as $t) {
    $epl->exec("TRUNCATE TABLE `$t`");
}
$epl->exec("SET FOREIGN_KEY_CHECKS=1");
log_ok("Tablas EPL vaciadas");

// ── 2. Cargar todos los WP users (email lookup) ───────────────
function ascii_slug(string $s): string {
    $map = ['á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ü'=>'u','ñ'=>'n',
            'Á'=>'a','É'=>'e','Í'=>'i','Ó'=>'o','Ú'=>'u','Ü'=>'u','Ñ'=>'n'];
    $s = strtr($s, $map);
    return preg_replace('/[^a-z0-9]/', '', mb_strtolower($s));
}
$wpUsers = [];
// Index by WP user_id, by login slug, and by display_name slug
foreach ($wp->query("SELECT ID, user_login, user_email, display_name FROM wpqu_users") as $u) {
    $wpUsers[(int)$u['ID']]              = $u;
    $wpUsers['login_'.$u['user_login']]  = $u;
    $wpUsers['slug_'.ascii_slug($u['display_name'])] = $u;
}
// Also index by email to detect duplicates
$emailUsed = [];

// ── 3. Cargar meta de jugadores (sp_player posts) ─────────────
$playersRaw = $wp->query("
    SELECT p.ID as wp_id, p.post_title as nombre_completo, p.post_name as slug,
      MAX(CASE WHEN pm.meta_key='epl_rut'         THEN pm.meta_value END) as rut,
      MAX(CASE WHEN pm.meta_key='epl_fecha_nac'   THEN pm.meta_value END) as fecha_nac,
      MAX(CASE WHEN pm.meta_key='epl_sexo'        THEN pm.meta_value END) as sexo,
      MAX(CASE WHEN pm.meta_key='epl_telefono'    THEN pm.meta_value END) as telefono,
      MAX(CASE WHEN pm.meta_key='epl_comuna'      THEN pm.meta_value END) as comuna,
      MAX(CASE WHEN pm.meta_key='epl_profesion'   THEN pm.meta_value END) as profesion,
      MAX(CASE WHEN pm.meta_key='epl_talla'       THEN pm.meta_value END) as talla,
      MAX(CASE WHEN pm.meta_key='epl_nivel'       THEN pm.meta_value END) as nivel,
      MAX(CASE WHEN pm.meta_key='epl_lado'        THEN pm.meta_value END) as lado,
      MAX(CASE WHEN pm.meta_key='epl_marca_pala'  THEN pm.meta_value END) as pala,
      MAX(CASE WHEN pm.meta_key='epl_frecuencia'  THEN pm.meta_value END) as frecuencia,
      MAX(CASE WHEN pm.meta_key='sp_user'         THEN pm.meta_value END) as wp_user_id,
      MAX(CASE WHEN pm.meta_key='sp_current_team' THEN pm.meta_value END) as wp_team_id
    FROM wpqu_posts p
    LEFT JOIN wpqu_postmeta pm ON pm.post_id=p.ID
    WHERE p.post_type='sp_player' AND p.post_status='publish'
    GROUP BY p.ID, p.post_title, p.post_name
    ORDER BY p.post_title
")->fetchAll(PDO::FETCH_ASSOC);

// Map wp_player_id → epl_jugador_id
$playerMap = []; // wp_id => epl_id
$stIns = $epl->prepare("
    INSERT INTO jugadores (nombre,apellido,email,password,rut,telefono,fecha_nacimiento,sexo,
        comuna,profesion,talla,nivel,lado,pala,frecuencia_juego,estado,rol,created_at)
    VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,'activo','jugador',NOW())
");

$adminEmail = 'pabloromeroduarte@gmail.com';
$defaultPass = password_hash('EplTemporal2026!', PASSWORD_DEFAULT);

foreach ($playersRaw as $p) {
    // Excluir test players
    if (str_contains(strtolower($p['nombre_completo']), 'prueba') ||
        str_contains(strtolower($p['nombre_completo']), 'mercado') ||
        str_contains(strtolower($p['nombre_completo']), 'amazon')) {
        log_info("Saltando jugador test: {$p['nombre_completo']}");
        continue;
    }

    // Resolver email: prioridad sp_user → post_name slug → display_name slug
    $email = null;
    if ($p['wp_user_id'] && isset($wpUsers[(int)$p['wp_user_id']])) {
        $email = $wpUsers[(int)$p['wp_user_id']]['user_email'];
    } else {
        $loginKey = 'login_'.str_replace('-', '', $p['slug']);
        $slugKey  = 'slug_'.ascii_slug($p['nombre_completo']);
        $email = $wpUsers[$loginKey]['user_email']
              ?? $wpUsers[$slugKey]['user_email']
              ?? null;
    }
    // Evitar duplicados de email (dos jugadores mismo email)
    if ($email && isset($emailUsed[$email])) {
        log_err("Email duplicado '$email' para {$p['nombre_completo']} — usando placeholder");
        $email = null;
    }
    if ($email) $emailUsed[$email] = true;

    $rol = ($email === $adminEmail) ? 'admin' : 'jugador';

    [$nombre, $apellido] = split_name($p['nombre_completo']);

    $sexo = match(mb_strtolower($p['sexo'] ?? '')) {
        'masculino','hombre','m' => 'M',
        'femenino','mujer','f'   => 'F',
        default => null
    };

    $stIns->execute([
        $nombre,
        $apellido,
        $email ?? ('sin-email-'.$p['wp_id'].'@epl.local'),
        $defaultPass,
        $p['rut']     ?: null,
        $p['telefono']?: null,
        $p['fecha_nac']?: null,
        $sexo,
        $p['comuna']   ?: null,
        $p['profesion']?: null,
        $p['talla']    ?: null,
        map_nivel($p['nivel'] ?? '5'),
        map_lado($p['lado'] ?? ''),
        $p['pala']     ?: null,
        map_frecuencia($p['frecuencia'] ?? ''),
    ]);

    $eplId = (int)$epl->lastInsertId();
    $playerMap[$p['wp_id']] = $eplId;
    log_ok("Jugador: {$p['nombre_completo']} → id $eplId" . ($email ? " ($email)" : " ⚠️ sin email"));
}
log_info("Total jugadores importados: " . count($playerMap));

// ── 4. Liga ───────────────────────────────────────────────────
$epl->prepare("
    INSERT INTO ligas (nombre,temporada,categoria,estado,sede,fecha_inicio,fecha_fin)
    VALUES ('Liga EPL','Apertura 2026',5,'activa','Club de Pádel Las Condes','2026-03-26',NULL)
")->execute();
$ligaId = (int)$epl->lastInsertId();
log_ok("Liga creada: id $ligaId");

// ── 5. Equipos ────────────────────────────────────────────────
$teamsRaw = $wp->query("
    SELECT p.ID as wp_id, p.post_title as nombre
    FROM wpqu_posts p
    WHERE p.post_type='sp_team' AND p.post_status='publish'
    AND p.post_title NOT LIKE '%Prueba%' AND p.post_title NOT LIKE '%Mercado%'
    ORDER BY p.post_title
")->fetchAll(PDO::FETCH_ASSOC);

// Map wp_team_id → epl_equipo_id
$teamMap = []; // wp_team_id => epl_equipo_id

// Build wp_player_id → wp_team_id
$playerTeamMap = []; // wp_player_id => wp_team_id
foreach ($playersRaw as $p) {
    if ($p['wp_team_id']) $playerTeamMap[$p['wp_id']] = (int)$p['wp_team_id'];
}
// Invert: wp_team_id => [wp_player_ids]
$teamPlayersMap = [];
foreach ($playerTeamMap as $pId => $tId) {
    $teamPlayersMap[$tId][] = $pId;
}

$stTeam = $epl->prepare("INSERT INTO equipos (nombre, jugador1_id, jugador2_id) VALUES (?,?,?)");
$stLeague = $epl->prepare("INSERT INTO liga_equipos (liga_id, equipo_id) VALUES (?,?)");
$stClasif = $epl->prepare("INSERT IGNORE INTO clasificacion (liga_id,equipo_id,pj,pg,pp,games_favor,games_contra,puntos,posicion) VALUES (?,?,0,0,0,0,0,0,0)");

foreach ($teamsRaw as $t) {
    $wpPlayers = $teamPlayersMap[$t['wp_id']] ?? [];
    $j1 = null; $j2 = null;
    foreach ($wpPlayers as $wpPid) {
        if (isset($playerMap[$wpPid])) {
            if (!$j1) $j1 = $playerMap[$wpPid];
            elseif (!$j2) $j2 = $playerMap[$wpPid];
        }
    }

    $stTeam->execute([$t['nombre'], $j1, $j2]);
    $eplTeamId = (int)$epl->lastInsertId();
    $teamMap[$t['wp_id']] = $eplTeamId;

    $stLeague->execute([$ligaId, $eplTeamId]);
    $stClasif->execute([$ligaId, $eplTeamId]);

    $j1Str = $j1 ?? '?';
    $j2Str = $j2 ?? '?';
    log_ok("Equipo: {$t['nombre']} → id $eplTeamId (j1=$j1Str, j2=$j2Str)");
}
log_info("Total equipos: " . count($teamMap));

// ── 6. Partidos ───────────────────────────────────────────────
// Get all team pairs per event
$eventTeams = [];
foreach ($wp->query("
    SELECT pm.post_id as event_id, pm.meta_value as team_id
    FROM wpqu_postmeta pm
    JOIN wpqu_posts p ON p.ID=pm.post_id
    WHERE p.post_type='sp_event' AND pm.meta_key='sp_team'
    ORDER BY pm.post_id, pm.meta_id
") as $row) {
    $eventTeams[$row['event_id']][] = (int)$row['team_id'];
}

// Get all match data
$eventsRaw = $wp->query("
    SELECT p.ID as wp_id, p.post_title as titulo, p.post_date as fecha, p.post_status as status,
      MAX(CASE WHEN pm.meta_key='sp_results'    THEN pm.meta_value END) as results_serial,
      MAX(CASE WHEN pm.meta_key='sp_day'         THEN pm.meta_value END) as dia_semana,
      MAX(CASE WHEN pm.meta_key='nombre_jornada' THEN pm.meta_value END) as nombre_jornada,
      MAX(CASE WHEN pm.meta_key='sp_time'       THEN pm.meta_value END) as hora
    FROM wpqu_posts p
    LEFT JOIN wpqu_postmeta pm ON pm.post_id=p.ID
    WHERE p.post_type='sp_event' AND p.post_status IN ('publish','future')
    GROUP BY p.ID, p.post_title, p.post_date, p.post_status
    ORDER BY p.post_date
")->fetchAll(PDO::FETCH_ASSOC);

$stPartido = $epl->prepare("
    INSERT INTO partidos (liga_id,equipo_local_id,equipo_visitante_id,estado,
        fecha_programada,fecha_jugado,
        jornada,nombre_fecha,
        sets_local,sets_visitante,
        games_s1_local,games_s1_visitante,
        games_s2_local,games_s2_visitante,
        games_s3_local,games_s3_visitante,
        ganador_id,wp_event_id)
    VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
");

$partidosInserted = 0;
$partidosSkipped  = 0;

foreach ($eventsRaw as $ev) {
    $teams = $eventTeams[$ev['wp_id']] ?? [];
    if (count($teams) < 2) { $partidosSkipped++; continue; }

    $wpLocal     = $teams[0];
    $wpVisitante = $teams[1];
    $eplLocal     = $teamMap[$wpLocal]     ?? null;
    $eplVisitante = $teamMap[$wpVisitante] ?? null;

    if (!$eplLocal || !$eplVisitante) { $partidosSkipped++; continue; }

    $estado = ($ev['status'] === 'publish') ? 'jugado' : 'pendiente';

    // Fecha + hora
    $fecha = $ev['fecha'];
    if ($ev['hora'] && $ev['hora'] !== '00:00') {
        $fecha = date('Y-m-d', strtotime($ev['fecha'])) . ' ' . $ev['hora'] . ':00';
    }

    // Parsear resultados (PHP serialize)
    $sl=0; $sv=0;
    $g1l=$g1v=$g2l=$g2v=$g3l=$g3v=null;
    $ganador = null;

    if ($ev['results_serial'] && $estado === 'jugado') {
        $res = @unserialize($ev['results_serial']);
        if (is_array($res)) {
            $teamData = array_values($res);
            $localData     = $res[$wpLocal]     ?? $teamData[0] ?? [];
            $visitanteData = $res[$wpVisitante] ?? $teamData[1] ?? [];

            $sl = (int)($localData['sets']     ?? 0);
            $sv = (int)($visitanteData['sets'] ?? 0);

            $g1l = isset($localData['sone'])   ? (int)$localData['sone']   : null;
            $g1v = isset($visitanteData['sone'])? (int)$visitanteData['sone']:null;
            $g2l = isset($localData['stwo'])   ? (int)$localData['stwo']   : null;
            $g2v = isset($visitanteData['stwo'])? (int)$visitanteData['stwo']:null;
            $g3l = (isset($localData['sthree']) && $localData['sthree'] !== '') ? (int)$localData['sthree'] : null;
            $g3v = (isset($visitanteData['sthree']) && $visitanteData['sthree'] !== '') ? (int)$visitanteData['sthree'] : null;

            // Determinar ganador
            if ($sl > $sv) $ganador = $eplLocal;
            elseif ($sv > $sl) $ganador = $eplVisitante;
        }
    }

    // nombre_fecha = "Fecha N" desde nombre_jornada; jornada = número extraído
    $nombre_fecha = $ev['nombre_jornada'] ? html_entity_decode($ev['nombre_jornada'], ENT_QUOTES, 'UTF-8') : null;
    $jornada_num  = null;
    if ($nombre_fecha) { preg_match('/(\d+)/', $nombre_fecha, $m); $jornada_num = isset($m[1]) ? (int)$m[1] : null; }

    $stPartido->execute([
        $ligaId,
        $eplLocal,
        $eplVisitante,
        $estado,
        $fecha,
        $estado === 'jugado' ? $fecha : null,
        $jornada_num,
        $nombre_fecha,
        $sl, $sv,
        $g1l, $g1v,
        $g2l, $g2v,
        $g3l, $g3v,
        $ganador,
        $ev['wp_id'],
    ]);
    $partidosInserted++;
}
log_ok("Partidos importados: $partidosInserted");
if ($partidosSkipped) log_info("Partidos saltados (sin equipos mapeados): $partidosSkipped");

// ── 7. Recalcular clasificación ───────────────────────────────
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
epl_recalcular_clasificacion($ligaId);
log_ok("Clasificación recalculada para liga $ligaId");

// ── Asignar admin ─────────────────────────────────────────────
$epl->prepare("UPDATE jugadores SET rol='admin' WHERE email=?")->execute([$adminEmail]);
log_ok("Admin asignado a: $adminEmail");

// ── Resultado ─────────────────────────────────────────────────
$stats = [
    'jugadores' => $epl->query("SELECT COUNT(*) FROM jugadores")->fetchColumn(),
    'equipos'   => $epl->query("SELECT COUNT(*) FROM equipos")->fetchColumn(),
    'partidos'  => $epl->query("SELECT COUNT(*) FROM partidos")->fetchColumn(),
    'jugados'   => $epl->query("SELECT COUNT(*) FROM partidos WHERE estado='jugado'")->fetchColumn(),
    'pendientes'=> $epl->query("SELECT COUNT(*) FROM partidos WHERE estado='pendiente'")->fetchColumn(),
];

?><!DOCTYPE html>
<html lang="es">
<head><meta charset="UTF-8"><title>Migración EPL</title>
<style>
body{font-family:monospace;padding:2rem;background:#0f172a;color:#e2e8f0;line-height:1.6}
h1{color:#C9A762;font-size:1.5rem}
.ok {color:#4ade80}.err{color:#f87171}.info{color:#94a3b8}
.stats{display:flex;gap:2rem;margin:2rem 0;flex-wrap:wrap}
.stat{background:#1e293b;padding:1rem 1.5rem;border-radius:8px;text-align:center}
.stat b{display:block;font-size:2rem;color:#C9A762}
</style></head>
<body>
<h1>Migración WordPress → EPL</h1>
<div class="stats">
  <?php foreach ($stats as $k => $v): ?>
  <div class="stat"><b><?= $v ?></b><?= $k ?></div>
  <?php endforeach; ?>
</div>
<pre><?php foreach ($log as $l) echo htmlspecialchars($l)."\n"; ?></pre>
</body></html>
