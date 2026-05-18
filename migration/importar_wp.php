<?php
/**
 * IMPORTADOR WORDPRESS → EPL
 * Lee la BD de WordPress (wp_import) y migra datos a EPL (epldb).
 *
 * ANTES DE CORRER:
 *  1. Crear base de datos "wp_import" en phpMyAdmin local.
 *  2. Importar epleague_wp108.sql en "wp_import".
 *  3. Correr limpiar_bd.php para vaciar EPL.
 *  4. Abrir este script en el navegador.
 */
require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/auth.php';
epl_require_admin();

// ── Conexión a la BD de WordPress (temporal) ─────────────────────────────────
$WP_DB   = 'wp_import';
$WP_PRE  = 'wpqu_';   // prefijo de tablas WP
$DB_HOST = 'localhost';
$DB_USER = 'root';
$DB_PASS = '';

try {
    $wp = new PDO("mysql:host=$DB_HOST;dbname=$WP_DB;charset=utf8mb4", $DB_USER, $DB_PASS, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (Throwable $e) {
    die('<pre style="color:red">No se pudo conectar a la BD de WordPress ("' . $WP_DB . '").<br>' .
        'Asegurate de haber creado la base de datos e importado el .sql.<br>' . $e->getMessage() . '</pre>');
}

$epl = epl_db();

// ── Helpers ──────────────────────────────────────────────────────────────────
function wp_meta(PDO $wp, string $pre, string $type, int $id): array {
    // type: 'post' | 'user'
    $tbl = $pre . ($type === 'post' ? 'postmeta' : 'usermeta');
    $col = $type === 'post' ? 'post_id' : 'user_id';
    $rows = $wp->prepare("SELECT meta_key, meta_value FROM `$tbl` WHERE `$col` = ?");
    $rows->execute([$id]);
    $meta = [];
    foreach ($rows->fetchAll() as $r) {
        $meta[$r['meta_key']] = $r['meta_value'];
    }
    return $meta;
}

function wp_unser(string $val): mixed {
    if (str_starts_with(trim($val), 'a:') || str_starts_with(trim($val), 'O:')) {
        $r = @unserialize($val);
        return $r !== false ? $r : $val;
    }
    return $val;
}

function log_line(string $txt, string $color = '#94a3b8'): void {
    echo "<div style='color:$color;font-size:.82rem;margin:.1rem 0'>$txt</div>";
    ob_flush(); flush();
}

$TEMP_PASS = password_hash('Epl2026!', PASSWORD_DEFAULT);

// ── Mapas de IDs ─────────────────────────────────────────────────────────────
$liga_map   = []; // wp_term_id → epl liga_id
$recinto_map= []; // wp_term_id → epl recinto_id
$jugador_map= []; // wp_user_id → epl jugador_id
$equipo_map = []; // wp_team_post_id → epl equipo_id

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { ?>
<!DOCTYPE html>
<html lang="es">
<head><meta charset="UTF-8"><title>Importar WordPress → EPL</title>
<style>
  body { font-family: monospace; background: #0a1421; color: #fff; padding: 2rem; }
  h1 { color: #C9A762; } h2 { color: #7dd3fc; margin-top: 1.5rem; }
  .card { background: #1C2F48; border-radius: 12px; padding: 2rem; max-width: 700px; margin: 0 auto; }
  .info { background: #0c4a6e; color: #7dd3fc; padding: 1rem; border-radius: 8px; margin-bottom: 1rem; font-size: .85rem; }
  ol li { margin-bottom: .5rem; }
  button { background: #C9A762; color: #0a1421; border: none; padding: .75rem 2rem; border-radius: 8px; font-size: 1rem; font-weight: bold; cursor: pointer; margin-top: 1rem; }
  code { background: #0a1421; padding: .2rem .4rem; border-radius: 4px; color: #fbbf24; }
</style>
</head>
<body>
<div class="card">
  <h1>🚀 Importar WordPress → EPL</h1>

  <div class="info">
    Esto migrará: <strong>jugadores, equipos, ligas, recintos, partidos, resultados, galletas/suplentes y reprogramaciones</strong>
    desde la base de datos de WordPress hacia EPL.
  </div>

  <h2>Pasos previos obligatorios</h2>
  <ol>
    <li>Abrí <strong>phpMyAdmin</strong> local → crear base de datos: <code>wp_import</code> (utf8mb4)</li>
    <li>En <code>wp_import</code> → <strong>Importar</strong> → subir <code>epleague_wp108.sql</code></li>
    <li>Correr <a href="limpiar_bd.php" style="color:#C9A762">limpiar_bd.php</a> para vaciar EPL</li>
    <li>Volver acá y hacer clic en <strong>Iniciar importación</strong></li>
  </ol>

  <div class="info" style="background:#14532d;color:#86efac">
    ⚠️ Contraseña temporal para todos los usuarios importados: <code>Epl2026!</code><br>
    El admin puede enviar reset de contraseñas después.
  </div>

  <form method="POST">
    <button type="submit">▶ Iniciar importación</button>
  </form>
</div>
</body>
</html>
<?php
    exit;
}

// ════════════════════════════════════════════════════════════════════════════
// IMPORTACIÓN
// ════════════════════════════════════════════════════════════════════════════
?>
<!DOCTYPE html>
<html lang="es">
<head><meta charset="UTF-8"><title>Importando...</title>
<style>
  body { font-family: monospace; background: #0a1421; color: #fff; padding: 2rem; font-size:.85rem; }
  h2 { color: #C9A762; margin: 1.5rem 0 .5rem; border-bottom: 1px solid #1C2F48; padding-bottom: .3rem; }
  .ok   { color: #86efac; }
  .warn { color: #fbbf24; }
  .err  { color: #fca5a5; }
  .dim  { color: #475569; }
  #log  { max-width: 800px; }
</style>
</head>
<body>
<div id="log">
<h2>⏳ Importando datos de WordPress...</h2>
<?php ob_start(); ob_implicit_flush(true); ob_end_flush(); flush();

// ── PASO 1: LIGAS (taxonomía sp_league) ──────────────────────────────────────
echo '<h2>1. Ligas</h2>';
$ligas_wp = $wp->query("
    SELECT t.term_id, t.name, t.slug, tt.description
    FROM {$WP_PRE}terms t
    JOIN {$WP_PRE}term_taxonomy tt ON tt.term_id = t.term_id
    WHERE tt.taxonomy = 'sp_league'
      AND tt.count > 0
    ORDER BY t.term_id ASC
")->fetchAll();

$ins_liga = $epl->prepare("
    INSERT INTO ligas (nombre, tipo, estado, temporada, descripcion)
    VALUES (?, 'liga', 'finalizada', '2026', ?)
");
foreach ($ligas_wp as $l) {
    $ins_liga->execute([$l['name'], $l['description'] ?: null]);
    $liga_map[$l['term_id']] = (int)$epl->lastInsertId();
    log_line("  Liga: <span class='ok'>{$l['name']}</span> → id={$liga_map[$l['term_id']]}");
}
log_line(count($liga_map) . ' ligas importadas.', '#86efac');

// ── PASO 2: RECINTOS (taxonomía sp_venue) ────────────────────────────────────
echo '<h2>2. Recintos</h2>';
$recintos_wp = $wp->query("
    SELECT t.term_id, t.name
    FROM {$WP_PRE}terms t
    JOIN {$WP_PRE}term_taxonomy tt ON tt.term_id = t.term_id
    WHERE tt.taxonomy = 'sp_venue'
    ORDER BY t.term_id ASC
")->fetchAll();

$ins_rec = $epl->prepare("INSERT INTO recintos (nombre) VALUES (?)");
foreach ($recintos_wp as $r) {
    $ins_rec->execute([$r['name']]);
    $recinto_map[$r['term_id']] = (int)$epl->lastInsertId();
    log_line("  Recinto: <span class='ok'>{$r['name']}</span>");
}
if (empty($recintos_wp)) {
    // Recinto por defecto si no había en WP
    $ins_rec->execute(['Conecta Santa Blanca']);
    $recinto_map[0] = (int)$epl->lastInsertId();
    log_line("  Recinto por defecto creado.", '#fbbf24');
}
log_line(count($recinto_map) . ' recintos importados.', '#86efac');

// ── PASO 3: JUGADORES (usuarios WP) ──────────────────────────────────────────
echo '<h2>3. Jugadores</h2>';
$users_wp = $wp->query("
    SELECT u.ID, u.user_email, u.user_registered, u.display_name
    FROM {$WP_PRE}users u
    ORDER BY u.ID ASC
")->fetchAll();

$ins_jug = $epl->prepare("
    INSERT INTO jugadores
      (email, password, rol, estado, nombre, apellido, rut, fecha_nacimiento,
       sexo, telefono, whatsapp, comuna, profesion, nivel, lado, pala, talla,
       frecuencia_juego, wp_user_id, created_at)
    VALUES
      (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
");

// Meta roles WP
$roles_wp = $wp->query("
    SELECT user_id, meta_value FROM {$WP_PRE}usermeta WHERE meta_key = '{$WP_PRE}capabilities'
")->fetchAll(PDO::FETCH_KEY_PAIR);

foreach ($users_wp as $u) {
    $meta = wp_meta($wp, $WP_PRE, 'user', $u['ID']);

    $nombre   = $meta['first_name']  ?? '';
    $apellido = $meta['last_name']   ?? '';
    if (!$nombre && !$apellido) {
        // Intentar split de display_name
        $parts    = explode(' ', $u['display_name'], 2);
        $nombre   = $parts[0] ?? '';
        $apellido = $parts[1] ?? '';
    }

    // Nivel: convertir texto a número
    $nivel_raw = $meta['epl_nivel'] ?? $meta['nivel_estimado'] ?? '5';
    $nivel = (int)preg_replace('/[^0-9]/', '', $nivel_raw) ?: 5;
    $nivel = max(1, min(7, $nivel));

    // Lado
    $lado_raw = strtolower($meta['epl_lado'] ?? $meta['ladojuego'] ?? '');
    $lado = match(true) {
        str_contains($lado_raw, 'rev') => 'reves',
        str_contains($lado_raw, 'der') => 'derecha',
        str_contains($lado_raw, 'amb') => 'ambos',
        default                         => null,
    };

    // Sexo
    $sexo_raw = strtolower($meta['epl_sexo'] ?? $meta['sexo'] ?? '');
    $sexo = match(true) {
        str_contains($sexo_raw, 'f') => 'F',
        str_contains($sexo_raw, 'm') => 'M',
        default                       => null,
    };

    // Frecuencia
    $freq_raw = strtolower($meta['epl_frecuencia'] ?? $meta['frecuencia_juego'] ?? '');
    $freq = match(true) {
        str_contains($freq_raw, '3')  => '3_o_mas',
        str_contains($freq_raw, '2')  => '2_semana',
        str_contains($freq_raw, '1')  => '1_semana',
        str_contains($freq_raw, 'oc') => 'ocasional',
        default                        => null,
    };

    // Fecha nacimiento
    $fnac_raw = $meta['epl_fecha_nac'] ?? $meta['fecha_nacimienti'] ?? null;
    $fnac = null;
    if ($fnac_raw) {
        $fnac = date('Y-m-d', strtotime($fnac_raw)) ?: null;
        if ($fnac === '1970-01-01') $fnac = null;
    }

    // Rol
    $caps_raw = $roles_wp[$u['ID']] ?? '';
    $caps     = wp_unser($caps_raw);
    $rol      = (is_array($caps) && !empty($caps['administrator'])) ? 'admin' : 'jugador';

    // Pala
    $pala = $meta['epl_marca_pala'] ?? $meta['marca_pala'] ?? null;

    try {
        $ins_jug->execute([
            $u['user_email'],
            $TEMP_PASS,
            $rol,
            'activo',
            $nombre   ?: 'Sin nombre',
            $apellido ?: '',
            $meta['epl_rut'] ?? null,
            $fnac,
            $sexo,
            $meta['epl_telefono'] ?? $meta['telefono'] ?? null,
            $meta['epl_telefono'] ?? $meta['telefono'] ?? null, // whatsapp = mismo
            $meta['epl_comuna'] ?? $meta['comuna'] ?? $meta['sector'] ?? null,
            $meta['epl_profesion'] ?? null,
            $nivel,
            $lado,
            $pala,
            $meta['epl_talla'] ?? $meta['talla_camiseta'] ?? null,
            $freq,
            $u['ID'],
            $u['user_registered'],
        ]);
        $jugador_map[$u['ID']] = (int)$epl->lastInsertId();
        $tag_rol = $rol === 'admin' ? "<span style='color:#fbbf24'>[ADMIN]</span>" : '';
        log_line("  {$nombre} {$apellido} &lt;{$u['user_email']}&gt; {$tag_rol}", '#86efac');
    } catch (Throwable $e) {
        log_line("  ⚠ {$u['user_email']}: " . $e->getMessage(), '#fbbf24');
    }
}
log_line(count($jugador_map) . ' jugadores importados.', '#86efac');

// ── PASO 4: EQUIPOS (sp_team) ─────────────────────────────────────────────────
echo '<h2>4. Equipos</h2>';

// ── Construir mapa email → wp_user_id ────────────────────────────────────────
$email_to_user = [];
foreach ($users_wp as $u) {
    $email_to_user[strtolower(trim($u['user_email']))] = (int)$u['ID'];
}

// ── Construir mapa sp_player_post_id → epl_jugador_id ────────────────────────
// Fuente 1: meta 'sp_user' en postmeta del sp_player
// Fuente 2: meta 'correo_jugador' matcheado contra wp_users por email
// Fuente 3: post_author del sp_player
$player_post_to_jugador = []; // sp_player_post_id → epl jugador_id

$sp_players_rows = $wp->query("
    SELECT p.ID, p.post_author FROM {$WP_PRE}posts p
    WHERE p.post_type = 'sp_player' AND p.post_status != 'trash'
")->fetchAll();

foreach ($sp_players_rows as $spp) {
    $sp_id  = (int)$spp['ID'];
    $pmeta  = wp_meta($wp, $WP_PRE, 'post', $sp_id);

    $epl_jug_id = null;

    // 1. sp_user → wp_user_id
    $sp_user_id = (int)($pmeta['sp_user'] ?? 0);
    if ($sp_user_id && isset($jugador_map[$sp_user_id])) {
        $epl_jug_id = $jugador_map[$sp_user_id];
    }

    // 2. correo_jugador → email match
    if (!$epl_jug_id) {
        $correo = strtolower(trim($pmeta['correo_jugador'] ?? ''));
        if ($correo && isset($email_to_user[$correo]) && isset($jugador_map[$email_to_user[$correo]])) {
            $epl_jug_id = $jugador_map[$email_to_user[$correo]];
        }
    }

    // 3. post_author fallback
    if (!$epl_jug_id) {
        $author = (int)$spp['post_author'];
        if ($author && isset($jugador_map[$author])) {
            $epl_jug_id = $jugador_map[$author];
        }
    }

    if ($epl_jug_id) {
        $player_post_to_jugador[$sp_id] = $epl_jug_id;
    }
}
log_line("  Mapa sp_player→jugador: " . count($player_post_to_jugador) . " entradas", '#475569');

// ── Construir equipos agrupando sp_players por su meta sp_team ───────────────
// (más fiable que leer sp_player desde el equipo, ya que algunos equipos no tienen ese meta)
$team_players = []; // wp_team_post_id → [epl_jugador_id, ...]

foreach ($sp_players_rows as $spp) {
    $sp_id = (int)$spp['ID'];
    if (!isset($player_post_to_jugador[$sp_id])) continue;
    $pmeta = wp_meta($wp, $WP_PRE, 'post', $sp_id);
    // sp_team puede ser int o array serializado
    $sp_team_raw = $pmeta['sp_team'] ?? $pmeta['sp_current_team'] ?? '';
    $sp_team_val = wp_unser((string)$sp_team_raw);
    $team_ids = is_array($sp_team_val) ? array_values($sp_team_val) : [(int)$sp_team_val];
    foreach ($team_ids as $tid) {
        $tid = (int)$tid;
        if ($tid > 0) {
            $team_players[$tid][] = $player_post_to_jugador[$sp_id];
        }
    }
}

$teams_wp = $wp->query("
    SELECT ID, post_title FROM {$WP_PRE}posts
    WHERE post_type = 'sp_team' AND post_status != 'trash'
    ORDER BY ID ASC
")->fetchAll();

$ins_eq = $epl->prepare("
    INSERT INTO equipos (nombre, jugador1_id, jugador2_id, wp_team_id)
    VALUES (?,?,?,?)
");

foreach ($teams_wp as $t) {
    $tid  = (int)$t['ID'];
    $pids = array_unique($team_players[$tid] ?? []);

    // Fallback: leer sp_player meta del equipo directamente
    if (count($pids) < 2) {
        $meta    = wp_meta($wp, $WP_PRE, 'post', $tid);
        $players = wp_unser($meta['sp_player'] ?? '');
        if (is_array($players)) {
            foreach (array_values($players) as $sp_pid) {
                $sp_pid = (int)$sp_pid;
                if (isset($player_post_to_jugador[$sp_pid])) {
                    $pids[] = $player_post_to_jugador[$sp_pid];
                }
            }
            $pids = array_unique($pids);
        }
    }

    if (count($pids) < 2) {
        log_line("  ⚠ Equipo '{$t['post_title']}' sin 2 jugadores — omitido.", '#fbbf24');
        continue;
    }

    try {
        $ins_eq->execute([$t['post_title'], $pids[0], $pids[1], $tid]);
        $equipo_map[$tid] = (int)$epl->lastInsertId();
        log_line("  Equipo: <span class='ok'>{$t['post_title']}</span>");
    } catch (Throwable $e) {
        log_line("  ⚠ Equipo '{$t['post_title']}': " . $e->getMessage(), '#fbbf24');
    }
}
log_line(count($equipo_map) . ' equipos importados.', '#86efac');

// ── PASO 5: LIGA_EQUIPOS — vincular equipos a ligas ──────────────────────────
echo '<h2>5. Equipos → Ligas</h2>';
$ins_le  = $epl->prepare("INSERT IGNORE INTO liga_equipos (liga_id, equipo_id) VALUES (?,?)");
$ins_cls = $epl->prepare("INSERT IGNORE INTO clasificacion (liga_id, equipo_id) VALUES (?,?)");
$le_count = 0;

foreach (array_keys($equipo_map) as $wp_team_id) {
    // Ligas del equipo vía term_relationships
    $term_rows = $wp->prepare("
        SELECT tt.term_id FROM {$WP_PRE}term_relationships tr
        JOIN {$WP_PRE}term_taxonomy tt ON tt.term_taxonomy_id = tr.term_taxonomy_id
        WHERE tr.object_id = ? AND tt.taxonomy = 'sp_league'
    ");
    $term_rows->execute([$wp_team_id]);
    foreach ($term_rows->fetchAll() as $tr) {
        if (isset($liga_map[$tr['term_id']], $equipo_map[$wp_team_id])) {
            $ins_le->execute([$liga_map[$tr['term_id']], $equipo_map[$wp_team_id]]);
            $ins_cls->execute([$liga_map[$tr['term_id']], $equipo_map[$wp_team_id]]);
            $le_count++;
        }
    }
}
log_line("$le_count vínculos equipo↔liga creados.", '#86efac');

// ── PASO 6: PARTIDOS desde WP All Export CSV ─────────────────────────────────
echo '<h2>6. Partidos (desde CSV export)</h2>';

// Helper: normalizar nombre de equipo para matching
function norm_eq(string $s): string {
    $s = html_entity_decode($s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $s = str_replace(["\u{2013}", "\u{2014}", "–", "—", "&#8211;", "&#8212;"], "-", $s);
    $s = preg_replace('/\s*-\s*/', ' - ', $s); // normalizar espacios alrededor de guión
    $s = preg_replace('/\s+/', ' ', $s);
    $s = mb_strtolower(trim($s), 'UTF-8');
    // Quitar acentos → ASCII puro para que levenshtein funcione bien
    $from = ['á','é','í','ó','ú','ü','ñ','à','è','ì','ò','ù','â','ê','î','ô','û','ä','ë','ï','ö','ü'];
    $to   = ['a','e','i','o','u','u','n','a','e','i','o','u','a','e','i','o','u','a','e','i','o','u'];
    return str_replace($from, $to, $s);
}

// Helper: matching difuso por Levenshtein
function best_eq_match(string $norm, array $map): ?int {
    $best_id = null; $best_d = 999;
    foreach ($map as $k => $eid) {
        $d = levenshtein($norm, $k);
        if ($d < $best_d && $d <= 4) { $best_d = $d; $best_id = $eid; }
    }
    return $best_id;
}

// Mapa nombre_equipo (normalizado) → equipo_id
$equipo_nombre_map = [];
foreach ($epl->query("SELECT id, nombre FROM equipos")->fetchAll() as $eq) {
    $equipo_nombre_map[norm_eq($eq['nombre'])] = (int)$eq['id'];
}

// Agregar variantes de nombres para cubrir typos del CSV
// (norm_eq ya quita acentos y normaliza espacios, acá cubrimos solo el typo z→s)
$aliases = [];
foreach ($equipo_nombre_map as $k => $eid) {
    // Typo común: "alvarez" escrito como "alvares" (ya sin acento por norm_eq)
    $aliases[str_replace('alvarez', 'alvares', $k)] = $eid;
}
$equipo_nombre_map = array_merge($equipo_nombre_map, $aliases);

// Mapa recinto nombre → recinto_id
$recinto_nombre_map = [];
foreach ($epl->query("SELECT id, nombre FROM recintos")->fetchAll() as $rec) {
    $recinto_nombre_map[mb_strtolower(trim($rec['nombre']))] = (int)$rec['id'];
}

// Liga con más equipos = liga principal
$liga_principal_row = $epl->query("SELECT liga_id, COUNT(*) c FROM liga_equipos GROUP BY liga_id ORDER BY c DESC LIMIT 1")->fetch();
$liga_principal_id  = $liga_principal_row ? (int)$liga_principal_row['liga_id'] : (int)(reset($liga_map) ?: 1);
log_line("  Liga principal: id=$liga_principal_id", '#475569');

// Mapa equipo_id → liga_id
$equipo_a_liga = [];
foreach ($epl->query("SELECT liga_id, equipo_id FROM liga_equipos")->fetchAll() as $le) {
    $equipo_a_liga[(int)$le['equipo_id']] = (int)$le['liga_id'];
}

// Leer CSV desde ZIP
$zip_path = dirname(__DIR__) . '/temp_archive/wp-content/uploads/wpallexport/exports/c29cc781e0cce2f83e0d69bd0766d22d/Eventos-Export-2026-April-01-1603.zip';
$csv_rows = [];
if (file_exists($zip_path)) {
    $za = new ZipArchive();
    if ($za->open($zip_path) === true) {
        for ($i = 0; $i < $za->numFiles; $i++) {
            if (str_ends_with($za->getNameIndex($i), '.csv')) {
                $content = $za->getFromIndex($i);
                $content = ltrim($content, "\xEF\xBB\xBF"); // strip UTF-8 BOM
                $lines   = preg_split('/\r?\n/', $content);
                $header  = null;
                foreach ($lines as $line) {
                    $line = trim($line);
                    if (!$line) continue;
                    $row = str_getcsv($line, ',', '"');
                    if (!$header) { $header = $row; continue; }
                    if (count($row) >= count($header)) {
                        $csv_rows[] = array_combine($header, array_slice($row, 0, count($header)));
                    }
                }
                break;
            }
        }
        $za->close();
    }
}
log_line("  " . count($csv_rows) . " eventos leídos del CSV", '#475569');

$ins_par = $epl->prepare("
    INSERT INTO partidos
      (liga_id, equipo_local_id, equipo_visitante_id, jornada, nombre_fecha,
       fecha_programada, estado, sets_local, sets_visitante, recinto_id)
    VALUES (?,?,?,?,?,?,?,0,0,?)
");

$p_ok = 0; $p_skip = 0;

foreach ($csv_rows as $row) {
    $title        = $row['Title'] ?? '';
    $nombre_fecha = trim($row['nombre_jornada'] ?? '');
    $venue_str    = trim($row['Recintos'] ?? '');
    $date_str     = trim($row['Date'] ?? '');
    $status       = trim($row['Status'] ?? 'future');

    // Número de jornada
    $jornada = null;
    if (preg_match('/\d+/', $nombre_fecha, $m)) $jornada = (int)$m[0];

    // Separar "Local vs Visitante"
    $decoded_title = html_entity_decode($title, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $parts = preg_split('/ vs /iu', $decoded_title, 2);
    if (count($parts) < 2) {
        // Fallback: título mal formateado sin "vs", intentar split por " - "
        // Ej: "Romero Merino - Concha Vial" → ["Romero Merino", "Concha Vial"]
        // El fuzzy matching manejará la diferencia con "Romero - Merino"
        $dash_parts = preg_split('/ - /', $decoded_title, 2);
        if (count($dash_parts) === 2) {
            $parts = $dash_parts;
            log_line("  ↩ Sin 'vs', intentando split por ' - ': $title", '#fbbf24');
        } else {
            log_line("  ⚠ Sin 'vs': $title", '#475569');
            $p_skip++; continue;
        }
    }
    $local_norm = norm_eq($parts[0]);
    $vis_norm   = norm_eq($parts[1]);

    // Match exacto
    $local_id = $equipo_nombre_map[$local_norm] ?? null;
    $vis_id   = $equipo_nombre_map[$vis_norm]   ?? null;

    // Match parcial si falla
    if (!$local_id || !$vis_id) {
        foreach ($equipo_nombre_map as $k => $eid) {
            if (!$local_id && (str_contains($local_norm, $k) || str_contains($k, $local_norm))) $local_id = $eid;
            if (!$vis_id   && (str_contains($vis_norm,   $k) || str_contains($k, $vis_norm)))   $vis_id   = $eid;
        }
    }

    // Match difuso Levenshtein si aún falla
    if (!$local_id) $local_id = best_eq_match($local_norm, $equipo_nombre_map);
    if (!$vis_id)   $vis_id   = best_eq_match($vis_norm,   $equipo_nombre_map);

    if (!$local_id || !$vis_id) {
        log_line("  ⚠ Equipos no encontrados: '$local_norm' vs '$vis_norm'", '#fbbf24');
        $p_skip++; continue;
    }

    // Liga
    $liga_id = $equipo_a_liga[$local_id] ?? $equipo_a_liga[$vis_id] ?? $liga_principal_id;

    // Recinto: "Conecta Santa Blanca>Cancha 5"
    $recinto_id = null;
    if ($venue_str) {
        $vparts  = explode('>', $venue_str);
        $cancha  = mb_strtolower(trim(end($vparts)));
        // Normalizar número: "cancha 5" → "cancha 05"
        $cancha_norm = preg_replace_callback('/(\bcancha\s+)(\d+)\b/i', fn($m) => $m[1] . str_pad($m[2], 2, '0', STR_PAD_LEFT), $cancha);
        $recinto_id = $recinto_nombre_map[$cancha_norm] ?? $recinto_nombre_map[$cancha] ?? null;
        // Fallback: recinto padre
        if (!$recinto_id && count($vparts) > 1) {
            $padre = mb_strtolower(trim($vparts[0]));
            $recinto_id = $recinto_nombre_map[$padre] ?? null;
        }
    }

    // Fecha: formato "dd-mm-yy" → "20yy-mm-dd"
    $fecha_prog = null;
    if ($date_str && preg_match('/^(\d{2})-(\d{2})-(\d{2})$/', $date_str, $dm)) {
        $fecha_prog = "20{$dm[3]}-{$dm[2]}-{$dm[1]}";
    } elseif ($date_str) {
        $ts = strtotime($date_str);
        if ($ts) $fecha_prog = date('Y-m-d', $ts);
    }

    $estado = ($status === 'publish') ? 'jugado' : 'pendiente';

    try {
        $ins_par->execute([$liga_id, $local_id, $vis_id, $jornada, $nombre_fecha ?: null, $fecha_prog, $estado, $recinto_id]);
        $p_ok++;
        log_line("  ✓ " . html_entity_decode($parts[0], ENT_QUOTES, 'UTF-8') . " vs " . html_entity_decode($parts[1], ENT_QUOTES, 'UTF-8') . " — {$nombre_fecha}");
    } catch (Throwable $e) {
        log_line("  ⚠ $title: " . $e->getMessage(), '#fbbf24');
        $p_skip++;
    }
}
log_line("$p_ok partidos importados, $p_skip omitidos.", '#86efac');

// ── PASO 7: RECALCULAR CLASIFICACIÓN ─────────────────────────────────────────
echo '<h2>7. Recalculando clasificación</h2>';
require_once dirname(__DIR__) . '/includes/functions.php';
foreach ($liga_map as $liga_id) {
    try {
        epl_recalcular_clasificacion($liga_id);
        log_line("  Liga $liga_id recalculada.", '#86efac');
    } catch (Throwable $e) {
        log_line("  ⚠ Liga $liga_id: " . $e->getMessage(), '#fbbf24');
    }
}

// ── PASO 8: GALLETAS / SUPLENTES ─────────────────────────────────────────────
echo '<h2>8. Galletas (suplentes)</h2>';
$players_wp = $wp->query("
    SELECT ID FROM {$WP_PRE}posts WHERE post_type = 'sp_player' AND post_status != 'trash'
")->fetchAll(PDO::FETCH_COLUMN);

$ins_sup = $epl->prepare("
    INSERT IGNORE INTO suplentes (liga_id, equipo_id, jugador_id, estado, registrado_por)
    VALUES (?,?,?,'activo',1)
");
$s_count = 0;

// Encontrar primer admin para registrado_por
$admin_id = $epl->query("SELECT id FROM jugadores WHERE rol='admin' LIMIT 1")->fetchColumn() ?: 1;

foreach ($players_wp as $sp_post_id) {
    $meta = wp_meta($wp, $WP_PRE, 'post', $sp_post_id);

    // Ligas del jugador
    $liga_terms = $wp->prepare("
        SELECT tt.term_id FROM {$WP_PRE}term_relationships tr
        JOIN {$WP_PRE}term_taxonomy tt ON tt.term_taxonomy_id = tr.term_taxonomy_id
        WHERE tr.object_id = ? AND tt.taxonomy = 'sp_league'
    ");
    $liga_terms->execute([$sp_post_id]);
    $player_ligas = $liga_terms->fetchAll(PDO::FETCH_COLUMN);

    // sp_team → equipo
    $team_raw = wp_unser($meta['sp_team'] ?? '');
    $wp_team_ids = is_array($team_raw) ? array_values($team_raw) : [(int)$team_raw];

    $galletas_raw = $meta['epl_galletas_data'] ?? '';
    if (!$galletas_raw) continue;

    $galletas = wp_unser($galletas_raw);
    if (!is_array($galletas)) continue;

    foreach ($galletas as $gk => $gdata) {
        if (!is_array($gdata)) continue;
        $gal_wp_user_id = (int)($gdata['user_id'] ?? 0);
        if (!$gal_wp_user_id || !isset($jugador_map[$gal_wp_user_id])) continue;
        $gal_jugador_id = $jugador_map[$gal_wp_user_id];

        // Vincular con liga y equipo del jugador principal
        foreach ($wp_team_ids as $wt) {
            $equipo_id_g = $equipo_map[(int)$wt] ?? null;
            foreach ($player_ligas as $lt) {
                $liga_id_g = $liga_map[$lt] ?? null;
                if (!$equipo_id_g || !$liga_id_g) continue;
                try {
                    $ins_sup->execute([$liga_id_g, $equipo_id_g, $gal_jugador_id]);
                    $s_count++;
                } catch (Throwable $e) { /* ignorar duplicados */ }
            }
        }
    }
}
log_line("$s_count galletas/suplentes importados.", '#86efac');

// ── PASO 9: REPROGRAMACIONES ──────────────────────────────────────────────────
echo '<h2>9. Reprogramaciones</h2>';
$repros_wp = $wp->query("
    SELECT ID FROM {$WP_PRE}posts WHERE post_type = 'epl_repro' AND post_status != 'trash'
")->fetchAll(PDO::FETCH_COLUMN);

// Buscar tabla solicitudes_reprogramacion
$has_repro = $epl->query("SHOW TABLES LIKE 'solicitudes_reprogramacion'")->fetchColumn();
$r_count = 0;

if ($has_repro) {
    $ins_rep = $epl->prepare("
        INSERT INTO solicitudes_reprogramacion
          (partido_id, solicitante_id, motivo, fecha_propuesta, estado, rival_no_responde, created_at)
        VALUES (?,?,?,?,?,?,?)
    ");

    foreach ($repros_wp as $rp_id) {
        $meta = wp_meta($wp, $WP_PRE, 'post', $rp_id);
        $wp_evento_id = (int)($meta['req_evento_id'] ?? 0);
        $wp_jug_id    = (int)($meta['req_jugador_id'] ?? 0);

        // Buscar partido EPL por wp_event_id
        $st = $epl->prepare("SELECT id FROM partidos WHERE wp_event_id = ? LIMIT 1");
        $st->execute([$wp_evento_id]);
        $partido_id   = $st->fetchColumn();
        $solicitante_id = $jugador_map[$wp_jug_id] ?? null;

        if (!$partido_id || !$solicitante_id) continue;

        $estado_raw = strtolower($meta['req_estado'] ?? 'pendiente');
        $estado_epl = match($estado_raw) {
            'aprobada','aprobado'   => 'aprobada',
            'rechazada','rechazado' => 'rechazada',
            default                 => 'pendiente',
        };
        $rnr = !empty($meta['rival_no_responde']) ? 1 : 0;

        // Combinar fecha + hora en fecha_propuesta
        $fecha_prop = null;
        if (!empty($meta['req_nueva_fecha'])) {
            $hora = $meta['req_nueva_hora'] ?? '00:00';
            $fecha_prop = date('Y-m-d H:i:s', strtotime($meta['req_nueva_fecha'] . ' ' . $hora)) ?: null;
        }

        try {
            $ins_rep->execute([
                $partido_id,
                $solicitante_id,
                $meta['req_motivo'] ?: 'Sin motivo',
                $fecha_prop,
                $estado_epl,
                $rnr,
                date('Y-m-d H:i:s'),
            ]);
            $r_count++;
        } catch (Throwable $e) {
            log_line("  ⚠ Repro $rp_id: " . $e->getMessage(), '#fbbf24');
        }
    }
}
log_line("$r_count reprogramaciones importadas.", '#86efac');

// ── FIN ───────────────────────────────────────────────────────────────────────
echo '<h2 style="color:#86efac">✅ Importación completada</h2>';
log_line("Jugadores: " . count($jugador_map), '#7dd3fc');
log_line("Equipos:   " . count($equipo_map), '#7dd3fc');
log_line("Ligas:     " . count($liga_map),   '#7dd3fc');
log_line("Partidos:  $p_ok", '#7dd3fc');
log_line("Galletas:  $s_count", '#7dd3fc');
log_line("Reprogramaciones: $r_count", '#7dd3fc');
?>

<div style="margin-top:2rem;padding:1.5rem;background:#14532d;border-radius:10px;color:#86efac">
  <strong>Próximos pasos:</strong><br>
  1. Revisá los datos en el panel admin<br>
  2. Ajustá las ligas (estado, precios, fechas) en Admin → Ligas<br>
  3. La contraseña temporal de todos es: <code style="background:#0a1421;padding:.2rem .4rem;border-radius:4px">Epl2026!</code><br>
  4. Podés enviar reset de contraseñas desde Admin → Jugadores
</div>

<div style="margin-top:1rem">
  <a href="/elitepadelleague/admin/" style="color:#C9A762">→ Ir al panel admin</a>
</div>
</div>
</body>
</html>
