<?php
/**
 * Parche: rellena nombre_fecha y jornada en partidos existentes
 * desde el campo nombre_jornada del WP original.
 *
 * NO borra datos — solo actualiza campos vacíos.
 * Acceso: http://localhost/elitepadelleague/migration/patch_nombre_fecha.php
 */
error_reporting(E_ALL); ini_set('display_errors', 1);
set_time_limit(60);

$wp  = new PDO('mysql:host=localhost;dbname=wp_epl_import;charset=utf8mb4', 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$epl = new PDO('mysql:host=localhost;dbname=epleague;charset=utf8mb4',     'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

$ok = 0; $skip = 0; $log = [];

// ── 1. Mapa wp_team_id → epl_equipo_id ──────────────────────────
$equipoMap = []; // wp_team_id => epl_equipo_id
foreach ($epl->query("SELECT id, wp_team_id FROM equipos WHERE wp_team_id IS NOT NULL") as $e) {
    $equipoMap[(int)$e['wp_team_id']] = (int)$e['id'];
}
$log[] = "Equipos con wp_team_id: " . count($equipoMap);

// ── 2. Cargar todos los eventos WP con nombre_jornada y teams ───
$events = $wp->query("
    SELECT p.ID as wp_id,
           MAX(CASE WHEN pm.meta_key='nombre_jornada' THEN pm.meta_value END) as nombre_fecha,
           MAX(CASE WHEN pm.meta_key='sp_day'         THEN pm.meta_value END) as dia_semana,
           GROUP_CONCAT(DISTINCT CASE WHEN pm.meta_key='sp_team' THEN pm.meta_value END
                        ORDER BY pm.meta_id ASC) as team_ids
    FROM wpqu_posts p
    LEFT JOIN wpqu_postmeta pm ON pm.post_id=p.ID
    WHERE p.post_type='sp_event' AND p.post_status IN ('publish','future')
    GROUP BY p.ID
    HAVING nombre_fecha IS NOT NULL
")->fetchAll(PDO::FETCH_ASSOC);

$log[] = "Eventos WP con nombre_jornada: " . count($events);

// ── 3. Extraer número de jornada del texto "Fecha N" ───────────
function parse_jornada(string $s): ?int {
    preg_match('/(\d+)/', $s, $m);
    return isset($m[1]) ? (int)$m[1] : null;
}

// ── 4. Para cada evento WP, buscar el partido EPL por equipos ───
$stUpd = $epl->prepare("
    UPDATE partidos
    SET nombre_fecha = ?,
        jornada = ?
    WHERE (
        (equipo_local_id = ? AND equipo_visitante_id = ?) OR
        (equipo_local_id = ? AND equipo_visitante_id = ?)
    )
    AND nombre_fecha IS NULL
");

foreach ($events as $ev) {
    $teams = array_filter(array_map('intval', explode(',', $ev['team_ids'] ?? '')));
    $teams = array_values($teams);
    if (count($teams) < 2) { $skip++; continue; }

    $wpT1 = $teams[0];
    $wpT2 = $teams[1];

    $eplT1 = $equipoMap[$wpT1] ?? null;
    $eplT2 = $equipoMap[$wpT2] ?? null;
    if (!$eplT1 || !$eplT2) {
        $log[] = "⚠️  Sin mapeo: wp_team {$wpT1},{$wpT2}";
        $skip++;
        continue;
    }

    $nombre_fecha = html_entity_decode($ev['nombre_fecha'], ENT_QUOTES, 'UTF-8');
    $jornada_num  = parse_jornada($nombre_fecha);

    $stUpd->execute([$nombre_fecha, $jornada_num, $eplT1, $eplT2, $eplT2, $eplT1]);
    $rows = $stUpd->rowCount();
    if ($rows > 0) {
        $ok += $rows;
        $log[] = "✅ {$nombre_fecha} — equipos EPL {$eplT1}/{$eplT2} → {$rows} partido(s)";
    } else {
        $skip++;
    }
}

// ── 5. También rellenar los que ya tienen jornada numérica pero no nombre_fecha ─
$epl->exec("UPDATE partidos SET nombre_fecha = CONCAT('Fecha ', jornada) WHERE nombre_fecha IS NULL AND jornada IS NOT NULL AND jornada > 0");
$extra = $epl->query("SELECT ROW_COUNT()")->fetchColumn();

// ── Resumen ─────────────────────────────────────────────────────
$stats = [
    'Con nombre_fecha' => $epl->query("SELECT COUNT(*) FROM partidos WHERE nombre_fecha IS NOT NULL")->fetchColumn(),
    'Sin nombre_fecha' => $epl->query("SELECT COUNT(*) FROM partidos WHERE nombre_fecha IS NULL")->fetchColumn(),
    'Fechas distintas' => $epl->query("SELECT COUNT(DISTINCT nombre_fecha) FROM partidos WHERE nombre_fecha IS NOT NULL")->fetchColumn(),
];
?><!DOCTYPE html>
<html lang="es">
<head><meta charset="UTF-8"><title>Patch nombre_fecha</title>
<style>
body{font-family:monospace;padding:2rem;background:#0f172a;color:#e2e8f0;line-height:1.6}
h1{color:#C9A762}h2{color:#94a3b8;font-size:1rem}
.ok{color:#4ade80}.err{color:#f87171}.info{color:#94a3b8}
.stats{display:flex;gap:1.5rem;margin:1.5rem 0;flex-wrap:wrap}
.stat{background:#1e293b;padding:.75rem 1.25rem;border-radius:8px;text-align:center}
.stat b{display:block;font-size:1.8rem;color:#C9A762}
</style></head>
<body>
<h1>Patch — nombre_fecha en partidos</h1>
<div class="stats">
  <?php foreach ($stats as $k=>$v): ?>
  <div class="stat"><b><?= $v ?></b><?= $k ?></div>
  <?php endforeach; ?>
  <div class="stat"><b class="ok"><?= $ok ?></b>Actualizados</div>
  <div class="stat"><b class="info"><?= $skip ?></b>Saltados</div>
</div>
<h2>Log:</h2>
<pre><?php foreach ($log as $l) echo htmlspecialchars($l)."\n"; ?></pre>
</body></html>
