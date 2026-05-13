<?php
/**
 * migrate.php — Migración de WordPress → Elite Padel League
 *
 * REQUISITOS:
 *   1. Importar el SQL dump de WP a MySQL:
 *      mysql -u root -p ligapadel < dump.sql
 *   2. Configurar WP_DB_NAME y EPL_DB_NAME en .env (o en $config abajo)
 *   3. Ejecutar: php migrate.php
 *      (o abrirlo en el navegador: localhost/elitepadelleague/migration/migrate.php)
 *
 * El script es IDEMPOTENTE — se puede correr varias veces sin duplicar datos.
 */

// ── Configuración ───────────────────────────────────────
$config = [
    'wp_host'    => 'localhost',
    'wp_db'      => 'ligapadel',        // BD de WordPress
    'wp_prefix'  => 'wpqu_',
    'wp_user'    => 'root',
    'wp_pass'    => '',

    'epl_host'   => 'localhost',
    'epl_db'     => 'epleague',         // BD nueva
    'epl_user'   => 'root',
    'epl_pass'   => '',
];

// Cargar .env si existe
$envFile = dirname(__DIR__) . '/.env';
if (file_exists($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if ($line[0] === '#' || !str_contains($line, '=')) continue;
        [$k, $v] = explode('=', $line, 2);
        $env[trim($k)] = trim($v);
    }
    if (isset($env['WP_DB_HOST']))   $config['wp_host']  = $env['WP_DB_HOST'];
    if (isset($env['WP_DB_NAME']))   $config['wp_db']    = $env['WP_DB_NAME'];
    if (isset($env['WP_DB_USER']))   $config['wp_user']  = $env['WP_DB_USER'];
    if (isset($env['WP_DB_PASS']))   $config['wp_pass']  = $env['WP_DB_PASS'];
    if (isset($env['WP_DB_PREFIX'])) $config['wp_prefix']= $env['WP_DB_PREFIX'];
    if (isset($env['DB_HOST']))      $config['epl_host'] = $env['DB_HOST'];
    if (isset($env['DB_NAME']))      $config['epl_db']   = $env['DB_NAME'];
    if (isset($env['DB_USER']))      $config['epl_user'] = $env['DB_USER'];
    if (isset($env['DB_PASS']))      $config['epl_pass'] = $env['DB_PASS'];
}

set_time_limit(120);
header('Content-Type: text/html; charset=utf-8');

function log_step(string $msg, string $type = 'info'): void {
    $color = match($type) { 'ok'=>'#22c55e','error'=>'#ef4444','warn'=>'#f59e0b', default=>'#94a3b8' };
    echo "<div style='padding:.3rem 0;color:$color;font-size:.9rem'>$msg</div>";
    flush();
}

echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Migración EPL</title>
<style>body{font-family:monospace;background:#0f172a;color:#e2e8f0;padding:2rem;max-width:800px;margin:0 auto}
h1{color:#C9A762;font-size:1.5rem}h2{color:#94a3b8;font-size:1rem;margin-top:1.5rem;border-top:1px solid #1e293b;padding-top:.75rem}
.step{padding:.25rem 0;font-size:.88rem}</style></head><body>';
echo '<h1>🏓 Migración Elite Padel League</h1>';
echo '<p style="color:#64748b;font-size:.85rem">'.date('Y-m-d H:i:s').'</p>';

// ── Conectar a ambas BDs ─────────────────────────────────
try {
    $wp = new PDO(
        "mysql:host={$config['wp_host']};dbname={$config['wp_db']};charset=utf8mb4",
        $config['wp_user'], $config['wp_pass'],
        [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]
    );
    log_step("✓ Conectado a WP DB: {$config['wp_db']}", 'ok');
} catch (PDOException $e) {
    log_step("✗ No se pudo conectar a WP DB: " . $e->getMessage(), 'error');
    die('</body></html>');
}

try {
    $epl = new PDO(
        "mysql:host={$config['epl_host']};dbname={$config['epl_db']};charset=utf8mb4",
        $config['epl_user'], $config['epl_pass'],
        [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]
    );
    log_step("✓ Conectado a EPL DB: {$config['epl_db']}", 'ok');
} catch (PDOException $e) {
    log_step("✗ No se pudo conectar a EPL DB: " . $e->getMessage(), 'error');
    die('</body></html>');
}

$p = $config['wp_prefix'];

// ────────────────────────────────────────────────────────
// PASO 1: JUGADORES (wp_users + usermeta)
// ────────────────────────────────────────────────────────
echo '<h2>1. Jugadores</h2>';

$wpUsers = $wp->query("
    SELECT u.ID, u.user_email, u.user_pass, u.display_name, u.user_registered
    FROM {$p}users u
    WHERE u.user_email != ''
")->fetchAll();

// Cargar usermeta de una vez
$metaRows = $wp->query("
    SELECT user_id, meta_key, meta_value
    FROM {$p}usermeta
    WHERE meta_key IN ('first_name','last_name','phone_number','billing_phone',
                       'nickname','description','user_level','wp_capabilities')
")->fetchAll();
$meta = [];
foreach ($metaRows as $m) {
    $meta[$m['user_id']][$m['meta_key']] = $m['meta_value'];
}

$insertJugador = $epl->prepare("
    INSERT INTO jugadores (email, password, nombre, apellido, rol, wp_user_id, created_at)
    VALUES (?,?,?,?,?,?,?)
    ON DUPLICATE KEY UPDATE
      password=VALUES(password), nombre=VALUES(nombre), apellido=VALUES(apellido),
      wp_user_id=VALUES(wp_user_id)
");

$creados = 0; $skipped = 0;
foreach ($wpUsers as $u) {
    $email   = strtolower(trim($u['user_email']));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) { $skipped++; continue; }

    $um = $meta[$u['ID']] ?? [];
    $nombre   = $um['first_name'] ?? '';
    $apellido = $um['last_name']  ?? '';

    // Si no hay first/last name, parsear display_name
    if (!$nombre && $u['display_name']) {
        $parts    = explode(' ', trim($u['display_name']), 2);
        $nombre   = $parts[0] ?? '';
        $apellido = $parts[1] ?? '';
    }
    if (!$nombre) $nombre = $email; // fallback

    // Detectar admin (wp_capabilities contiene 'administrator')
    $caps = $um['wp_capabilities'] ?? '';
    $rol  = str_contains($caps, 'administrator') ? 'admin' : 'jugador';

    // La contraseña de WP usa phpass — NO es compatible con password_verify()
    // Guardamos la contraseña hasheada de WP; el jugador deberá usar "Recuperar contraseña" la primera vez
    // O bien generamos contraseña temporal: nombre+año
    $pass_hash = password_hash(strtolower(preg_replace('/\s+/', '', $nombre)) . '2026', PASSWORD_DEFAULT);

    $insertJugador->execute([$email, $pass_hash, $nombre, $apellido, $rol, $u['ID'], $u['user_registered']]);
    $creados++;
}
log_step("✓ $creados jugadores migrados, $skipped omitidos", 'ok');
log_step("⚠ Contraseñas temporales generadas: [nombre_sin_espacios][2026] — Los jugadores deben cambiarla", 'warn');

// Mapa wp_user_id → epl_jugador_id
$jugadorMap = [];
$stJMap = $epl->query("SELECT id, wp_user_id FROM jugadores WHERE wp_user_id IS NOT NULL");
foreach ($stJMap->fetchAll() as $row) {
    $jugadorMap[$row['wp_user_id']] = $row['id'];
}

// ────────────────────────────────────────────────────────
// PASO 2: LIGA
// ────────────────────────────────────────────────────────
echo '<h2>2. Liga principal</h2>';

$epl->exec("INSERT IGNORE INTO ligas (id,nombre,temporada,categoria,estado) VALUES (1,'Liga EPL 5ta Apertura 2026','Apertura 2026',5,'activa')");
log_step("✓ Liga EPL 5ta Apertura 2026 (id=1)", 'ok');

// ────────────────────────────────────────────────────────
// PASO 3: EQUIPOS (sp_team)
// ────────────────────────────────────────────────────────
echo '<h2>3. Equipos</h2>';

$teams = $wp->query("
    SELECT ID, post_title
    FROM {$p}posts
    WHERE post_type = 'sp_team' AND post_status = 'publish'
    ORDER BY post_title
")->fetchAll();

// Cargar postmeta de sp_team (_players)
$teamsMeta = $wp->query("
    SELECT post_id, meta_key, meta_value
    FROM {$p}postmeta
    WHERE meta_key IN ('_players','_sp_leagues','_leagues')
      AND post_id IN (SELECT ID FROM {$p}posts WHERE post_type='sp_team')
")->fetchAll();
$tMeta = [];
foreach ($teamsMeta as $m) { $tMeta[$m['post_id']][$m['meta_key']] = $m['meta_value']; }

// También cargar sp_player → wp_user_id
$players = $wp->query("
    SELECT p.ID, p.post_title, pm.meta_value AS wp_user_id
    FROM {$p}posts p
    LEFT JOIN {$p}postmeta pm ON pm.post_id=p.ID AND pm.meta_key='_user_id'
    WHERE p.post_type='sp_player' AND p.post_status='publish'
")->fetchAll();
$playerMap  = []; // sp_player_post_id → wp_user_id
$playerByName = []; // slug → sp_player_id
foreach ($players as $pl) {
    $playerMap[$pl['ID']] = $pl['wp_user_id'];
    $playerByName[strtolower($pl['post_title'])] = $pl['ID'];
}

$insertEquipo = $epl->prepare("
    INSERT INTO equipos (nombre, jugador1_id, jugador2_id, wp_team_id)
    VALUES (?,?,?,?)
    ON DUPLICATE KEY UPDATE nombre=VALUES(nombre)
");
$insertLigaEquipo = $epl->prepare("INSERT IGNORE INTO liga_equipos (liga_id,equipo_id) VALUES (1,?)");
$insertClasif     = $epl->prepare("INSERT IGNORE INTO clasificacion (liga_id,equipo_id) VALUES (1,?)");

$equipoMap = []; // wp_team_id → epl_equipo_id
$equiposCreados = 0;

foreach ($teams as $t) {
    $nombre = html_entity_decode($t['post_title'], ENT_QUOTES, 'UTF-8');

    // Obtener jugadores del equipo desde _players (PHP serialized: a:2:{i:0;s:3:"123";i:1;s:3:"456";})
    $playersRaw = $tMeta[$t['ID']]['_players'] ?? '';
    $playerIds  = [];
    if ($playersRaw) {
        preg_match_all('/s:\d+:"(\d+)"/', $playersRaw, $matches);
        $playerIds = $matches[1] ?? [];
    }

    $j1_epl = null; $j2_epl = null;

    // Mapear sp_player ids → wp_user ids → epl jugador ids
    foreach ($playerIds as $spPid) {
        $wpUid = $playerMap[$spPid] ?? null;
        if ($wpUid && isset($jugadorMap[$wpUid])) {
            if (!$j1_epl)      $j1_epl = $jugadorMap[$wpUid];
            elseif (!$j2_epl)  $j2_epl = $jugadorMap[$wpUid];
        }
    }

    // Si no pudimos mapear por _players, intentar por nombre del equipo
    if (!$j1_epl || !$j2_epl) {
        // Nombre del equipo: "Apellido1 - Apellido2" o "Apellido1 – Apellido2"
        $partes = preg_split('/\s*[–-]\s*/', $nombre, 2);
        if (count($partes) === 2) {
            // Buscar jugadores por apellido aproximado
            foreach ([$partes[0], $partes[1]] as $idx => $parte) {
                $parte = trim($parte);
                $stJ = $epl->prepare("
                    SELECT id FROM jugadores
                    WHERE apellido LIKE ? OR nombre LIKE ? OR CONCAT(nombre,' ',apellido) LIKE ?
                    LIMIT 1
                ");
                $stJ->execute(["%$parte%", "%$parte%", "%$parte%"]);
                $jid = $stJ->fetchColumn();
                if ($jid) {
                    if ($idx === 0) $j1_epl = $j1_epl ?: $jid;
                    else            $j2_epl = $j2_epl ?: $jid;
                }
            }
        }
    }

    if (!$j1_epl || !$j2_epl) {
        log_step("⚠ Equipo '$nombre' — no se encontraron los 2 jugadores (WP IDs: ".implode(',',$playerIds).")", 'warn');
        // Crear equipo con jugadores placeholder (id=1) para no perder datos
        $j1_epl = $j1_epl ?: 1;
        $j2_epl = $j2_epl ?: 1;
    }

    $insertEquipo->execute([$nombre, $j1_epl, $j2_epl, $t['ID']]);
    $eid = (int)$epl->lastInsertId();

    // Si ON DUPLICATE KEY no inserta, buscar el existente
    if (!$eid) {
        $stFind = $epl->prepare("SELECT id FROM equipos WHERE wp_team_id=?");
        $stFind->execute([$t['ID']]);
        $eid = (int)$stFind->fetchColumn();
    }

    if ($eid) {
        $equipoMap[$t['ID']] = $eid;
        $insertLigaEquipo->execute([$eid]);
        $insertClasif->execute([$eid]);
        $equiposCreados++;
    }
}
log_step("✓ $equiposCreados equipos migrados", 'ok');

// ────────────────────────────────────────────────────────
// PASO 4: PARTIDOS (sp_event)
// ────────────────────────────────────────────────────────
echo '<h2>4. Partidos</h2>';

$events = $wp->query("
    SELECT ID, post_title, post_date, post_status
    FROM {$p}posts
    WHERE post_type='sp_event' AND post_status='publish'
    ORDER BY post_date ASC
")->fetchAll();

// Cargar meta de eventos
$eventIds = array_column($events, 'ID');
$eMeta = [];
if ($eventIds) {
    $placeholders = implode(',', array_fill(0, count($eventIds), '?'));
    $stEM = $wp->prepare("
        SELECT post_id, meta_key, meta_value
        FROM {$p}postmeta
        WHERE post_id IN ($placeholders)
          AND meta_key IN ('_teams','_results','_outcome','_sp_results','_leagues')
    ");
    $stEM->execute($eventIds);
    foreach ($stEM->fetchAll() as $m) {
        $eMeta[$m['post_id']][$m['meta_key']] = $m['meta_value'];
    }
}

$insertPartido = $epl->prepare("
    INSERT INTO partidos
      (liga_id, equipo_local_id, equipo_visitante_id, fecha_programada, estado,
       sets_local, sets_visitante,
       games_s1_local, games_s1_visitante,
       games_s2_local, games_s2_visitante,
       games_s3_local, games_s3_visitante,
       ganador_id, wp_event_id)
    VALUES (1,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
    ON DUPLICATE KEY UPDATE
      equipo_local_id=VALUES(equipo_local_id),
      equipo_visitante_id=VALUES(equipo_visitante_id),
      estado=VALUES(estado)
");

$partidosCreados = 0; $sinEquipos = 0;
$jornada = 1;
$jornadaFecha = null;

foreach ($events as $ev) {
    $em    = $eMeta[$ev['ID']] ?? [];
    $teams_raw = $em['_teams'] ?? '';

    // Extraer team IDs del serialized PHP
    $teamIds = [];
    if ($teams_raw) {
        preg_match_all('/s:\d+:"(\d+)"/', $teams_raw, $m);
        $teamIds = $m[1] ?? [];
    }

    // Si no hay _teams, intentar parsear del título
    // Título: "EquipoA vs EquipoB" o "EquipoA – EquipoB vs EquipoC - EquipoD"
    $local_id = null; $vis_id = null;

    foreach ($teamIds as $tid) {
        $eid = $equipoMap[$tid] ?? null;
        if (!$local_id)     $local_id = $eid;
        elseif (!$vis_id)   $vis_id   = $eid;
    }

    if (!$local_id || !$vis_id) {
        // Fallback: buscar por nombre del título
        $titulo = html_entity_decode($ev['post_title'], ENT_QUOTES, 'UTF-8');
        if (preg_match('/^(.+?)\s+vs\s+(.+)$/i', $titulo, $mm)) {
            $nomL = trim($mm[1]); $nomV = trim($mm[2]);
            foreach ([$nomL, $nomV] as $idx => $nom) {
                $stE2 = $epl->prepare("SELECT id FROM equipos WHERE nombre LIKE ? LIMIT 1");
                $stE2->execute(["%$nom%"]);
                $eid2 = $stE2->fetchColumn();
                if ($eid2) { if($idx===0) $local_id=$eid2; else $vis_id=$eid2; }
            }
        }
    }

    if (!$local_id || !$vis_id) { $sinEquipos++; continue; }

    // Resultado
    $estado       = 'pendiente';
    $sets_l = $sets_v = 0;
    $g = array_fill(1, 3, ['l'=>null,'v'=>null]);
    $ganador_id   = null;

    $results_raw = $em['_results'] ?? $em['_sp_results'] ?? '';
    if ($results_raw) {
        // Formato: a:2:{i:TEAM_ID;a:3:{s:2:"s1";s:1:"6";s:2:"s2";s:1:"4";...}...}
        preg_match_all('/i:(\d+);a:\d+:\{([^}]+)\}/', $results_raw, $rMatches, PREG_SET_ORDER);
        $teamResults = [];
        foreach ($rMatches as $rm) {
            $tid2 = $rm[1];
            preg_match_all('/s:\d+:"([^"]+)";s:\d+:"([^"]*)"/', $rm[2], $kvs, PREG_SET_ORDER);
            foreach ($kvs as $kv) { $teamResults[$tid2][$kv[1]] = $kv[2]; }
        }

        if (count($teamResults) >= 2) {
            $estado = 'jugado';
            $tids   = array_keys($teamResults);
            $localWp  = array_search($local_id,  $equipoMap) ?: $tids[0];
            $visWp    = array_search($vis_id,     $equipoMap) ?: $tids[1];

            for ($s = 1; $s <= 3; $s++) {
                $key = 's'.$s;
                $gl = isset($teamResults[$localWp][$key]) && $teamResults[$localWp][$key] !== ''
                      ? (int)$teamResults[$localWp][$key] : null;
                $gv = isset($teamResults[$visWp][$key])   && $teamResults[$visWp][$key]   !== ''
                      ? (int)$teamResults[$visWp][$key]   : null;
                $g[$s] = ['l'=>$gl,'v'=>$gv];
                if ($gl !== null && $gv !== null) {
                    if ($gl > $gv) $sets_l++; else $sets_v++;
                }
            }
            $ganador_id = $sets_l > $sets_v ? $local_id : $vis_id;
        }
    }

    // Agrupar jornadas por fecha
    $fecha = $ev['post_date'];

    $insertPartido->execute([
        $local_id, $vis_id, $fecha, $estado,
        $sets_l, $sets_v,
        $g[1]['l'], $g[1]['v'],
        $g[2]['l'], $g[2]['v'],
        $g[3]['l'], $g[3]['v'],
        $ganador_id, $ev['ID']
    ]);
    $partidosCreados++;
}
log_step("✓ $partidosCreados partidos migrados", 'ok');
if ($sinEquipos) log_step("⚠ $sinEquipos partidos sin equipos identificados (revisa manualmente)", 'warn');

// ────────────────────────────────────────────────────────
// PASO 5: RECALCULAR CLASIFICACIÓN
// ────────────────────────────────────────────────────────
echo '<h2>5. Clasificación</h2>';

// Incluir funciones EPL
require_once dirname(__DIR__) . '/includes/functions.php';

// Redirigir la función a usar la BD correcta
// Como epl_db() usa la config de .env, que ya apunta a epleague, funciona directo
epl_recalcular_clasificacion(1);
log_step("✓ Clasificación recalculada para liga id=1", 'ok');

// ────────────────────────────────────────────────────────
// RESUMEN
// ────────────────────────────────────────────────────────
echo '<h2>Resumen</h2>';
$jugadoresEpl = $epl->query("SELECT COUNT(*) FROM jugadores")->fetchColumn();
$equiposEpl   = $epl->query("SELECT COUNT(*) FROM equipos")->fetchColumn();
$partidosEpl  = $epl->query("SELECT COUNT(*) FROM partidos")->fetchColumn();
$jugadosEpl   = $epl->query("SELECT COUNT(*) FROM partidos WHERE estado='jugado'")->fetchColumn();

echo "<table style='border-collapse:collapse;font-size:.9rem;width:100%'>
<tr><td style='padding:.35rem .75rem;color:#94a3b8'>Jugadores</td><td style='color:#22c55e;font-weight:bold'>$jugadoresEpl</td></tr>
<tr><td style='padding:.35rem .75rem;color:#94a3b8'>Equipos</td><td style='color:#22c55e;font-weight:bold'>$equiposEpl</td></tr>
<tr><td style='padding:.35rem .75rem;color:#94a3b8'>Partidos totales</td><td style='color:#22c55e;font-weight:bold'>$partidosEpl</td></tr>
<tr><td style='padding:.35rem .75rem;color:#94a3b8'>Partidos jugados</td><td style='color:#22c55e;font-weight:bold'>$jugadosEpl</td></tr>
</table>";

echo '<p style="margin-top:1.5rem;padding:.85rem;background:#1e293b;border-radius:6px;font-size:.85rem;color:#fbbf24">';
echo '⚠ <strong>Contraseñas temporales:</strong> La contraseña de cada jugador es<br>';
echo '<code>[nombre_en_minúsculas_sin_espacios]2026</code><br>';
echo 'Ejemplo: "Francisco Amigo" → <code>francisco2026</code><br>';
echo 'Los jugadores deben cambiarla al ingresar por primera vez.';
echo '</p>';

echo '<p style="margin-top:1rem;color:#22c55e;font-weight:bold;font-size:1rem">✓ Migración completada. <a href="../index.php" style="color:#C9A762">Ir al sitio →</a></p>';
echo '</body></html>';
