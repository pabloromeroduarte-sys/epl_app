<?php
$page_title = 'Admin — Detalle Liga';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
epl_require_admin();

$db  = epl_db();
$id  = (int)($_GET['id'] ?? 0);
$tab = in_array($_GET['tab'] ?? '', ['equipos','partidos']) ? $_GET['tab'] : 'equipos';
$ok  = '';
$err = '';

if (!$id) { header('Location: ligas.php'); exit; }

$stL = $db->prepare("
    SELECT l.*, r.nombre AS recinto_nombre, rs.nombre AS recinto_superior_nombre
    FROM ligas l
    LEFT JOIN recintos r ON r.id = l.recinto_id
    LEFT JOIN recintos rs ON rs.id = r.superior_id
    WHERE l.id=?
");
$stL->execute([$id]);
$liga = $stL->fetch();
if (!$liga) { header('Location: ligas.php'); exit; }

// ── POST actions ──────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // ── Editar info liga ────────────────────────────────────
    if ($action === 'editar_liga') {
        $nombre    = trim($_POST['nombre']    ?? '');
        $tipo      = in_array($_POST['tipo']??'',['liga','torneo']) ? $_POST['tipo'] : $liga['tipo'];
        $formato   = in_array($_POST['formato']??'',['liga_regular','mata_mata','grupos_mata_mata']) ? $_POST['formato'] : $liga['formato'];
        $temporada = trim($_POST['temporada'] ?? '');
        $cat       = (int)($_POST['categoria'] ?? 0);
        $estado    = in_array($_POST['estado']??'',['proximamente','inscripcion','activa','finalizada']) ? $_POST['estado'] : $liga['estado'];
        $precio    = (float)($_POST['precio'] ?? 0) ?: null;
        $recinto_id = (int)($_POST['recinto_id'] ?? 0) ?: null;
        $url_maps  = trim($_POST['url_maps']  ?? '');
        $f_inicio  = trim($_POST['fecha_inicio'] ?? '') ?: null;
        $f_fin     = trim($_POST['fecha_fin']    ?? '') ?: null;
        $i_inicio  = trim($_POST['inscripcion_inicio'] ?? '') ?: null;
        $i_fin     = trim($_POST['inscripcion_fin']    ?? '') ?: null;
        $p1        = max(0,(int)($_POST['puntos_1']      ?? $liga['puntos_1']));
        $p2        = max(0,(int)($_POST['puntos_2']      ?? $liga['puntos_2']));
        $p3        = max(0,(int)($_POST['puntos_3']      ?? $liga['puntos_3']));
        $p4        = max(0,(int)($_POST['puntos_4']      ?? $liga['puntos_4']));
        $pg        = max(0,(int)($_POST['puntos_grupos'] ?? $liga['puntos_grupos']));
        if (!$nombre) { $err = 'El nombre es obligatorio.'; }
        else {
            $db->prepare("UPDATE ligas SET
                nombre=?,tipo=?,formato=?,temporada=?,categoria=?,estado=?,precio=?,
                sede=?,url_maps=?,fecha_inicio=?,fecha_fin=?,
                inscripcion_inicio=?,inscripcion_fin=?,
                puntos_1=?,puntos_2=?,puntos_3=?,puntos_4=?,puntos_grupos=?,
                recinto_id=?
                WHERE id=?")
               ->execute([$nombre,$tipo,$formato,$temporada,$cat?:null,$estado,$precio,
                          $recinto?:null,$url_maps?:null,$f_inicio,$f_fin,$i_inicio,$i_fin,
                          $p1,$p2,$p3,$p4,$pg,$recinto_id,$id]);
            $ok = 'Liga actualizada.';
            $stL->execute([$id]); $liga = $stL->fetch();
        }

    // ── Agregar equipo ──────────────────────────────────────
    } elseif ($action === 'agregar_equipo') {
        $j1     = (int)($_POST['jugador1_id'] ?? 0);
        $j2     = (int)($_POST['jugador2_id'] ?? 0);
        $nombre = trim($_POST['nombre_equipo'] ?? '');
        if (!$j1 || !$j2 || $j1 === $j2) { $err = 'Selecciona dos jugadores distintos.'; }
        else {
            if (!$nombre) {
                $r1 = $db->prepare("SELECT apellido FROM jugadores WHERE id=?"); $r1->execute([$j1]);
                $r2 = $db->prepare("SELECT apellido FROM jugadores WHERE id=?"); $r2->execute([$j2]);
                $nombre = ($r1->fetchColumn() ?: 'J1') . ' - ' . ($r2->fetchColumn() ?: 'J2');
            }
            try {
                $existe = $db->prepare("SELECT e.id FROM equipos e JOIN liga_equipos le ON le.equipo_id=e.id WHERE le.liga_id=? AND ((e.jugador1_id=? AND e.jugador2_id=?) OR (e.jugador1_id=? AND e.jugador2_id=?))");
                $existe->execute([$id,$j1,$j2,$j2,$j1]);
                if ($existe->fetch()) { $err = 'Esa pareja ya está inscrita.'; }
                else {
                    $db->prepare("INSERT INTO equipos (nombre,jugador1_id,jugador2_id) VALUES (?,?,?)")->execute([$nombre,$j1,$j2]);
                    $eid = $db->lastInsertId();
                    $db->prepare("INSERT IGNORE INTO liga_equipos (liga_id,equipo_id) VALUES (?,?)")->execute([$id,$eid]);
                    $db->prepare("INSERT IGNORE INTO clasificacion (liga_id,equipo_id) VALUES (?,?)")->execute([$id,$eid]);
                    $ok = 'Equipo <strong>'.htmlspecialchars($nombre).'</strong> inscrito.';
                }
            } catch (Exception $e) {
                $err = 'Error al crear equipo.';
            }
        }

    // ── Bulk Actions Partidos ─────────────────────────────────
    } elseif ($action === 'bulk_partidos') {
        $partido_ids = $_POST['partido_ids'] ?? [];
        $bulk_action = $_POST['bulk_action'] ?? '';
        
        if (empty($partido_ids)) {
            $err = 'No se seleccionaron partidos.';
        } else {
            $ids_str = implode(',', array_map('intval', $partido_ids));
            
            if ($bulk_action === 'eliminar') {
                $db->query("DELETE FROM partidos WHERE id IN ($ids_str)");
                $ok = count($partido_ids) . ' partidos eliminados.';
            } elseif ($bulk_action === 'estado_pendiente') {
                $db->query("UPDATE partidos SET estado='pendiente', ganador_id=NULL, sets_local=NULL, sets_visitante=NULL WHERE id IN ($ids_str)");
                $ok = 'Estados actualizados a Pendiente.';
            } elseif ($bulk_action === 'cambiar_recinto') {
                $rid = (int)($_POST['bulk_recinto_id'] ?? 0) ?: null;
                $stmt = $db->prepare("UPDATE partidos SET recinto_id=? WHERE id IN ($ids_str)");
                $stmt->execute([$rid]);
                $ok = 'Recintos actualizados.';
            } elseif ($bulk_action === 'cambiar_fecha') {
                $n_fecha = trim($_POST['bulk_nombre_fecha'] ?? '');
                $n_jornada = (int)($_POST['bulk_jornada'] ?? 0) ?: null;
                if ($n_fecha) {
                    $stmt = $db->prepare("UPDATE partidos SET nombre_fecha=?, jornada=? WHERE id IN ($ids_str)");
                    $stmt->execute([$n_fecha, $n_jornada]);
                    $ok = 'Información de jornada actualizada.';
                }
            } elseif ($bulk_action === 'cambiar_fecha_programada') {
                $n_fecha_prog = trim($_POST['bulk_fecha_programada'] ?? '');
                if ($n_fecha_prog) {
                    $stmt = $db->prepare("UPDATE partidos SET fecha_programada=? WHERE id IN ($ids_str)");
                    $stmt->execute([str_replace('T', ' ', $n_fecha_prog) . ':00']);
                    $ok = 'Fecha y hora de los partidos actualizada.';
                }
            } elseif ($bulk_action === 'poner_alerta') {
                $alerta = trim($_POST['bulk_alerta'] ?? '');
                $stmt = $db->prepare("UPDATE partidos SET alerta_admin=? WHERE id IN ($ids_str)");
                $stmt->execute([$alerta ?: null]);
                $ok = 'Alertas actualizadas.';
            }
        }

    // ── Quitar equipo ───────────────────────────────────────
    } elseif ($action === 'quitar_equipo') {
        $eid = (int)($_POST['equipo_id'] ?? 0);
        if ($eid) {
            $db->prepare("DELETE FROM liga_equipos WHERE liga_id=? AND equipo_id=?")->execute([$id,$eid]);
            $db->prepare("DELETE FROM clasificacion WHERE liga_id=? AND equipo_id=?")->execute([$id,$eid]);
            $ok = 'Equipo quitado.';
        }

    // ── Recalcular clasificación ────────────────────────────
    } elseif ($action === 'recalcular') {
        epl_recalcular_clasificacion($id);
        $ok = 'Clasificación recalculada.';

    // ── Asignar puntos ranking ──────────────────────────────
    } elseif ($action === 'asignar_puntos') {
        if ($liga['estado'] !== 'finalizada') { $err = 'La competición debe estar finalizada.'; }
        else {
            $rows = $db->prepare("SELECT c.posicion,c.equipo_id,e.jugador1_id,e.jugador2_id FROM clasificacion c JOIN equipos e ON e.id=c.equipo_id WHERE c.liga_id=? ORDER BY c.posicion ASC, c.puntos DESC");
            $rows->execute([$id]);
            $clasificados = $rows->fetchAll();
            $map = [1=>$liga['puntos_1'],2=>$liga['puntos_2'],3=>$liga['puntos_3'],4=>$liga['puntos_4']];
            $fecha_comp = $liga['fecha_fin'] ?: date('Y-m-d');
            $asignados  = 0;
            foreach ($clasificados as $pos => $row) {
                $posicion = $row['posicion'] ?: ($pos + 1);
                $pts = $map[$posicion] ?? $liga['puntos_grupos'];
                foreach ([$row['jugador1_id'], $row['jugador2_id']] as $jid) {
                    $db->prepare("INSERT INTO ranking_puntos (jugador_id,liga_id,posicion_final,puntos,fecha_competicion) VALUES (?,?,?,?,?) ON DUPLICATE KEY UPDATE posicion_final=VALUES(posicion_final),puntos=VALUES(puntos),fecha_competicion=VALUES(fecha_competicion)")
                       ->execute([$jid,$id,$posicion,$pts,$fecha_comp]);
                    $asignados++;
                }
            }
            $ok = "Puntos asignados a {$asignados} jugadores.";
        }

    // ── Crear partido ───────────────────────────────────────
    } elseif ($action === 'crear_partido') {
        $local_id    = (int)($_POST['equipo_local_id']     ?? 0);
        $vis_id      = (int)($_POST['equipo_visitante_id'] ?? 0);
        $fecha       = trim($_POST['fecha_programada']     ?? '') ?: null;
        $jornada     = (int)($_POST['jornada']             ?? 0) ?: null;
        $nombre_f    = trim($_POST['nombre_fecha']         ?? '') ?: null;
        $recinto_id  = (int)($_POST['recinto_id']            ?? 0) ?: null;
        if (!$local_id || !$vis_id || $local_id === $vis_id) { $err = 'Selecciona equipos distintos.'; }
        else {
            $db->prepare("INSERT INTO partidos (liga_id,equipo_local_id,equipo_visitante_id,jornada,nombre_fecha,recinto_id,fecha_programada) VALUES (?,?,?,?,?,?,?)")
               ->execute([$id,$local_id,$vis_id,$jornada,$nombre_f,$recinto_id,$fecha]);
            foreach ([$local_id,$vis_id] as $eid) {
                $db->prepare("INSERT IGNORE INTO liga_equipos (liga_id,equipo_id) VALUES (?,?)")->execute([$id,$eid]);
                $db->prepare("INSERT IGNORE INTO clasificacion (liga_id,equipo_id) VALUES (?,?)")->execute([$id,$eid]);
            }
            $ok = 'Partido creado.';
            $tab = 'partidos';
        }

    // ── Editar resultado ────────────────────────────────────
    } elseif ($action === 'editar_resultado') {
        $pid      = (int)($_POST['partido_id'] ?? 0);
        $est      = $_POST['estado'] ?? 'jugado';
        $fecha_j  = $_POST['fecha_jugado'] ?? null;
        $jornada  = (int)($_POST['jornada'] ?? 0) ?: null;
        $nombre_f = trim($_POST['nombre_fecha'] ?? '') ?: null;
        $recinto_id = (int)($_POST['recinto_id'] ?? 0) ?: null;
        $fecha_p  = trim($_POST['fecha_programada'] ?? '') ?: null;
        $sets_l   = 0; $sets_v = 0; $sets = [];
        for ($s=1;$s<=3;$s++) {
            $gl = isset($_POST["s{$s}_l"]) && $_POST["s{$s}_l"]!=='' ? (int)$_POST["s{$s}_l"] : null;
            $gv = isset($_POST["s{$s}_v"]) && $_POST["s{$s}_v"]!=='' ? (int)$_POST["s{$s}_v"] : null;
            $sets[$s] = ['l'=>$gl,'v'=>$gv];
            if ($gl !== null && $gv !== null) { if ($gl > $gv) $sets_l++; else $sets_v++; }
        }
        $ganador_id = null;
        if ($est === 'jugado') {
            $stP = $db->prepare("SELECT * FROM partidos WHERE id=?"); $stP->execute([$pid]); $p2 = $stP->fetch();
            $ganador_id = $sets_l > $sets_v ? $p2['equipo_local_id'] : $p2['equipo_visitante_id'];
        }
        $alerta_a = trim($_POST['alerta_admin'] ?? '') ?: null;
        $db->prepare("UPDATE partidos SET estado=?,fecha_programada=?,fecha_jugado=?,jornada=?,nombre_fecha=?,recinto_id=?,sets_local=?,sets_visitante=?,games_s1_local=?,games_s1_visitante=?,games_s2_local=?,games_s2_visitante=?,games_s3_local=?,games_s3_visitante=?,ganador_id=?,alerta_admin=? WHERE id=?")
           ->execute([$est,$fecha_p,$fecha_j?:null,$jornada,$nombre_f,$recinto_id,$sets_l,$sets_v,$sets[1]['l'],$sets[1]['v'],$sets[2]['l'],$sets[2]['v'],$sets[3]['l'],$sets[3]['v'],$ganador_id,$alerta_a,$pid]);
        epl_recalcular_clasificacion($id);
        $ok  = 'Partido actualizado.';
        $tab = 'partidos';
    }
}

// ── Datos ─────────────────────────────────────────────────
$equipos_liga = $db->prepare("
    SELECT e.*, j1.nombre AS j1n, j1.apellido AS j1a, j1.foto AS j1foto,
           j2.nombre AS j2n, j2.apellido AS j2a, j2.foto AS j2foto,
           c.posicion, c.pj, c.pg, c.pp, c.puntos AS pts_tabla, c.games_favor, c.games_contra
    FROM liga_equipos le
    JOIN equipos e ON e.id = le.equipo_id
    JOIN jugadores j1 ON j1.id = e.jugador1_id
    JOIN jugadores j2 ON j2.id = e.jugador2_id
    LEFT JOIN clasificacion c ON c.liga_id=? AND c.equipo_id=e.id
    WHERE le.liga_id=?
    ORDER BY c.posicion ASC, c.puntos DESC, e.nombre ASC
");
$equipos_liga->execute([$id,$id]);
$equipos_liga = $equipos_liga->fetchAll();

// Filtro fecha en tab partidos
$f_fecha  = trim($_GET['fecha'] ?? '');
$f_est    = trim($_GET['estado_p'] ?? '');
$f_search = trim($_GET['search'] ?? '');
$f_desde  = trim($_GET['desde'] ?? '');
$f_hasta  = trim($_GET['hasta'] ?? '');

$where_p = "WHERE p.liga_id=?";
$params_p = [$id];

if ($f_fecha) { $where_p .= " AND p.nombre_fecha=?"; $params_p[] = $f_fecha; }
if ($f_est)   { $where_p .= " AND p.estado=?";        $params_p[] = $f_est;   }
if ($f_desde) { $where_p .= " AND p.fecha_programada >= ?"; $params_p[] = $f_desde . ' 00:00:00'; }
if ($f_hasta) { $where_p .= " AND p.fecha_programada <= ?"; $params_p[] = $f_hasta . ' 23:59:59'; }

if ($f_search) {
    $search_parts = explode(' ', $f_search);
    foreach ($search_parts as $part) {
        $part = trim($part);
        if ($part === '') continue;
        $where_p .= " AND (
            el.nombre LIKE ? OR ev.nombre LIKE ? OR
            jl1.nombre LIKE ? OR jl1.apellido LIKE ? OR
            jl2.nombre LIKE ? OR jl2.apellido LIKE ? OR
            jv1.nombre LIKE ? OR jv1.apellido LIKE ? OR
            jv2.nombre LIKE ? OR jv2.apellido LIKE ?
        )";
        $p_val = "%$part%";
        array_push($params_p, $p_val, $p_val, $p_val, $p_val, $p_val, $p_val, $p_val, $p_val, $p_val, $p_val);
    }
}

$partidos = $db->prepare("
    SELECT p.*, el.nombre AS local_nombre, ev.nombre AS visitante_nombre,
           r.nombre AS recinto_nombre
    FROM partidos p
    JOIN equipos el ON el.id = p.equipo_local_id
    JOIN equipos ev ON ev.id = p.equipo_visitante_id
    LEFT JOIN jugadores jl1 ON jl1.id = el.jugador1_id
    LEFT JOIN jugadores jl2 ON jl2.id = el.jugador2_id
    LEFT JOIN jugadores jv1 ON jv1.id = ev.jugador1_id
    LEFT JOIN jugadores jv2 ON jv2.id = ev.jugador2_id
    LEFT JOIN recintos r ON r.id = p.recinto_id
    $where_p
    ORDER BY p.jornada ASC, p.nombre_fecha ASC, p.fecha_programada ASC
");
$partidos->execute($params_p);
$partidos = $partidos->fetchAll();

// Lista de fechas distintas para el filtro
$fechas_disponibles = $db->prepare("SELECT nombre_fecha FROM partidos WHERE liga_id=? AND nombre_fecha IS NOT NULL GROUP BY nombre_fecha ORDER BY MAX(jornada) ASC, nombre_fecha ASC");
$fechas_disponibles->execute([$id]);
$fechas_disponibles = array_column($fechas_disponibles->fetchAll(), 'nombre_fecha');

$todos_jugadores = $db->query("SELECT id,nombre,apellido FROM jugadores WHERE estado='activo' ORDER BY apellido,nombre")->fetchAll();

$ranking_asignado = $db->prepare("SELECT rp.*,j.nombre,j.apellido,j.foto FROM ranking_puntos rp JOIN jugadores j ON j.id=rp.jugador_id WHERE rp.liga_id=? ORDER BY rp.posicion_final ASC,rp.puntos DESC");
$ranking_asignado->execute([$id]);
$ranking_asignado = $ranking_asignado->fetchAll();

// Construir árbol jerárquico de recintos para selects
$_recintos_raw = $db->query("SELECT id, nombre, superior_id FROM recintos ORDER BY nombre")->fetchAll();
$_rec_roots = []; $_rec_children = [];
foreach ($_recintos_raw as $_r) {
    if (!$_r['superior_id']) $_rec_roots[] = $_r;
    else $_rec_children[$_r['superior_id']][] = $_r;
}
$todos_recintos = [];
$map_recintos_full = [];
function _flattenRecintos(array $nodes, array $children, int $depth, array &$out, array &$map, string $path = ''): void {
    foreach ($nodes as $n) {
        $prefix = $depth === 0 ? '🏛 ' : ($depth === 1 ? '   📍 ' : '      🎾 ');
        $out[] = ['id' => $n['id'], 'label' => $prefix . $n['nombre'], 'depth' => $depth];
        $currentPath = $path ? $path . ' · ' . $n['nombre'] : $n['nombre'];
        $map[$n['id']] = $currentPath;
        if (isset($children[$n['id']])) _flattenRecintos($children[$n['id']], $children, $depth + 1, $out, $map, $currentPath);
    }
}
_flattenRecintos($_rec_roots, $_rec_children, 0, $todos_recintos, $map_recintos_full);

$tipo_label = ['liga'=>'Liga','torneo'=>'Torneo'];
$fmt_label  = ['liga_regular'=>'Liga regular','mata_mata'=>'Mata-mata','grupos_mata_mata'=>'Grupos + Mata-mata'];

$total_partidos = count($partidos);
$total_equipos  = count($equipos_liga);
?>
<?php require_once '../includes/header.php'; ?>

<div class="dash-layout">
  <?php include __DIR__ . '/partials/sidebar.php'; ?>

  <main class="dash-main">

    <!-- Breadcrumb -->
    <div style="margin-bottom:1rem;font-size:.82rem;color:var(--gray-400)">
      <a href="ligas.php" style="color:var(--gold)">Ligas y Torneos</a>
      <span style="margin:0 .4rem">›</span>
      <span style="color:var(--navy);font-weight:600"><?= epl_h($liga['nombre']) ?></span>
    </div>

    <?php if ($ok): ?><div class="alert alert-success"><?= $ok ?></div><?php endif; ?>
    <?php if ($err): ?><div class="alert alert-error"><?= epl_h($err) ?></div><?php endif; ?>

    <!-- ── Header liga ─────────────────────────────────── -->
    <div class="card mb-3">
      <div class="card-body" style="display:flex;align-items:flex-start;justify-content:space-between;flex-wrap:wrap;gap:1rem">
        <div>
          <div style="display:flex;align-items:center;gap:.6rem;flex-wrap:wrap;margin-bottom:.5rem">
            <h2 style="font-family:var(--font-head);font-size:1.4rem;color:var(--navy);margin:0"><?= epl_h($liga['nombre']) ?></h2>
            <span class="badge <?= $liga['tipo']==='liga'?'badge-pendiente':'badge-reprog' ?>"><?= $tipo_label[$liga['tipo']] ?></span>
            <span class="badge <?= $liga['estado']==='activa'?'badge-jugado':($liga['estado']==='finalizada'?'badge-walkover':'badge-pendiente') ?>">
              <?= ucfirst($liga['estado']) ?>
            </span>
          </div>
          <div style="font-size:.83rem;color:var(--gray-600);display:flex;flex-wrap:wrap;gap:1.25rem">
            <?php if ($liga['temporada']): ?><span>📅 <?= epl_h($liga['temporada']) ?></span><?php endif; ?>
            <?php if ($liga['sede']): ?><span>📍 <?= epl_h($liga['sede']) ?><?php if ($liga['url_maps']): ?> <a href="<?= epl_h($liga['url_maps']) ?>" target="_blank" style="color:var(--gold)">↗</a><?php endif; ?></span><?php endif; ?>
            <?php if ($liga['fecha_inicio']): ?><span>🗓 <?= date('d/m/Y',strtotime($liga['fecha_inicio'])) ?><?= $liga['fecha_fin'] ? ' → '.date('d/m/Y',strtotime($liga['fecha_fin'])) : '' ?></span><?php endif; ?>
            <span>🏆 <?= $fmt_label[$liga['formato']] ?></span>
            <?php if ($liga['categoria']): ?><span>📊 <?= $liga['categoria'] ?>ª cat.</span><?php endif; ?>
          </div>
          <div style="margin-top:.5rem;font-size:.75rem;color:var(--gray-400)">
            Puntos ranking: 🥇<?= $liga['puntos_1'] ?> · 🥈<?= $liga['puntos_2'] ?> · 🥉<?= $liga['puntos_3'] ?> · 4°<?= $liga['puntos_4'] ?> · Part.<?= $liga['puntos_grupos'] ?>
          </div>
        </div>
        <div style="display:flex;gap:.5rem;flex-wrap:wrap;align-items:flex-start">
          <button onclick="document.getElementById('modalEditar').style.display='flex'"
                  class="btn btn-sm" style="border:1px solid var(--navy);color:var(--navy)">✏ Editar</button>
          <form method="post" style="display:inline">
            <input type="hidden" name="action" value="recalcular">
            <button type="submit" class="btn btn-sm" style="border:1px solid var(--gray-200);color:var(--gray-600)">↺ Recalcular</button>
          </form>
          <?php if ($liga['estado']==='finalizada'): ?>
          <form method="post" style="display:inline">
            <input type="hidden" name="action" value="asignar_puntos">
            <button type="submit" class="btn btn-sm btn-primary" onclick="return confirm('¿Asignar puntos de ranking a todos los jugadores?')">🏅 Asignar puntos</button>
          </form>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- ── Sub-tabs ─────────────────────────────────────── -->
    <div style="display:flex;gap:0;border-bottom:2px solid var(--gray-200);margin-bottom:1.5rem">
      <a href="liga_detalle.php?id=<?= $id ?>&tab=equipos"
         style="padding:.65rem 1.5rem;font-size:.82rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;text-decoration:none;border-bottom:3px solid <?= $tab==='equipos'?'var(--gold)':'transparent' ?>;color:<?= $tab==='equipos'?'var(--navy)':'var(--gray-400)' ?>;margin-bottom:-2px">
        Equipos <span style="font-size:.75rem;font-weight:400">(<?= $total_equipos ?>)</span>
      </a>
      <a href="liga_detalle.php?id=<?= $id ?>&tab=partidos"
         style="padding:.65rem 1.5rem;font-size:.82rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;text-decoration:none;border-bottom:3px solid <?= $tab==='partidos'?'var(--gold)':'transparent' ?>;color:<?= $tab==='partidos'?'var(--navy)':'var(--gray-400)' ?>;margin-bottom:-2px">
        Partidos <span style="font-size:.75rem;font-weight:400">(<?= count($db->query("SELECT id FROM partidos WHERE liga_id=$id")->fetchAll()) ?>)</span>
      </a>
    </div>

    <?php if ($tab === 'equipos'): ?>
    <!-- ══════════════ TAB EQUIPOS ═══════════════════════ -->
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:.75rem">
      <h3 style="font-family:var(--font-head);font-size:1rem;text-transform:uppercase;color:var(--navy);margin:0">
        Equipos (<?= $total_equipos ?>)
      </h3>
      <button onclick="document.getElementById('modalAgregarEquipo').style.display='flex'" class="btn btn-primary btn-sm">+ Agregar equipo</button>
    </div>

    <div class="card">
      <div style="overflow-x:auto">
        <table style="width:100%;border-collapse:collapse;font-size:.83rem">
          <thead>
            <tr style="background:var(--navy);color:#fff">
              <th style="padding:.65rem 1rem;text-align:left;font-size:.7rem;text-transform:uppercase">Equipo</th>
              <th style="padding:.65rem 1rem;text-align:left;font-size:.7rem;text-transform:uppercase">Pareja</th>
              <th style="padding:.65rem .75rem;font-size:.7rem;text-transform:uppercase;text-align:center">Pos</th>
              <th style="padding:.65rem .75rem;font-size:.7rem;text-transform:uppercase;text-align:center">PJ</th>
              <th style="padding:.65rem .75rem;font-size:.7rem;text-transform:uppercase;text-align:center">PG</th>
              <th style="padding:.65rem .75rem;font-size:.7rem;text-transform:uppercase;text-align:center">PP</th>
              <th style="padding:.65rem .75rem;font-size:.7rem;text-transform:uppercase;text-align:center">GF/GC</th>
              <th style="padding:.65rem .75rem;font-size:.7rem;text-transform:uppercase;text-align:center">Pts</th>
              <th style="padding:.65rem .75rem;font-size:.7rem;text-transform:uppercase"></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($equipos_liga as $e): ?>
            <tr style="border-bottom:1px solid var(--gray-100)">
              <td style="padding:.65rem 1rem;font-weight:700;color:var(--navy)"><?= epl_h($e['nombre']) ?></td>
              <td style="padding:.65rem 1rem">
                <div style="display:flex;align-items:center;gap:.4rem">
                  <img src="<?= epl_h(epl_foto_jugador($e['j1foto'],$e['j1n'].' '.$e['j1a'])) ?>" style="width:26px;height:26px;border-radius:50%;object-fit:cover">
                  <span style="font-size:.8rem"><?= epl_h($e['j1n'].' '.$e['j1a']) ?></span>
                </div>
                <div style="display:flex;align-items:center;gap:.4rem;margin-top:.2rem">
                  <img src="<?= epl_h(epl_foto_jugador($e['j2foto'],$e['j2n'].' '.$e['j2a'])) ?>" style="width:26px;height:26px;border-radius:50%;object-fit:cover">
                  <span style="font-size:.8rem"><?= epl_h($e['j2n'].' '.$e['j2a']) ?></span>
                </div>
              </td>
              <td style="padding:.65rem .75rem;text-align:center;font-weight:700;color:var(--gold)"><?= $e['posicion'] ?: '—' ?></td>
              <td style="padding:.65rem .75rem;text-align:center"><?= $e['pj']??0 ?></td>
              <td style="padding:.65rem .75rem;text-align:center;color:#16a34a;font-weight:600"><?= $e['pg']??0 ?></td>
              <td style="padding:.65rem .75rem;text-align:center;color:#dc2626"><?= $e['pp']??0 ?></td>
              <td style="padding:.65rem .75rem;text-align:center;font-size:.78rem"><?= ($e['games_favor']??0) ?>/<?= ($e['games_contra']??0) ?></td>
              <td style="padding:.65rem .75rem;text-align:center;font-weight:700"><?= $e['pts_tabla']??0 ?></td>
              <td style="padding:.65rem .75rem;text-align:center">
                <form method="post" style="display:inline">
                  <input type="hidden" name="action" value="quitar_equipo">
                  <input type="hidden" name="equipo_id" value="<?= $e['id'] ?>">
                  <button type="submit" class="btn btn-sm" style="border:1px solid #dc2626;color:#dc2626;font-size:.68rem"
                          onclick="return confirm('¿Quitar este equipo?')">Quitar</button>
                </form>
              </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($equipos_liga)): ?><tr><td colspan="9" style="padding:2rem;text-align:center;color:var(--gray-400)">Sin equipos inscritos.</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <?php if ($ranking_asignado): ?>
    <h3 style="font-family:var(--font-head);font-size:1rem;text-transform:uppercase;color:var(--navy);margin:2rem 0 .75rem">Puntos de Ranking Asignados</h3>
    <div class="card">
      <div style="overflow-x:auto">
        <table style="width:100%;border-collapse:collapse;font-size:.83rem">
          <thead><tr style="background:var(--navy);color:#fff">
            <th style="padding:.65rem 1rem;text-align:left;font-size:.7rem;text-transform:uppercase">Jugador</th>
            <th style="padding:.65rem 1rem;text-align:center;font-size:.7rem;text-transform:uppercase">Posición</th>
            <th style="padding:.65rem 1rem;text-align:center;font-size:.7rem;text-transform:uppercase">Puntos</th>
            <th style="padding:.65rem 1rem;text-align:center;font-size:.7rem;text-transform:uppercase">Fecha</th>
          </tr></thead>
          <tbody>
            <?php foreach ($ranking_asignado as $r): ?>
            <tr style="border-bottom:1px solid var(--gray-100)">
              <td style="padding:.65rem 1rem">
                <div style="display:flex;align-items:center;gap:.6rem">
                  <img src="<?= epl_h(epl_foto_jugador($r['foto'],$r['nombre'].' '.$r['apellido'])) ?>" style="width:30px;height:30px;border-radius:50%;object-fit:cover">
                  <span style="font-weight:600;color:var(--navy)"><?= epl_h($r['nombre'].' '.$r['apellido']) ?></span>
                </div>
              </td>
              <td style="padding:.65rem 1rem;text-align:center;font-weight:700;color:var(--gold)"><?= $r['posicion_final'] ?>°</td>
              <td style="padding:.65rem 1rem;text-align:center;font-weight:700;font-size:1rem"><?= $r['puntos'] ?></td>
              <td style="padding:.65rem 1rem;text-align:center;font-size:.78rem;color:var(--gray-400)"><?= date('d/m/Y',strtotime($r['fecha_competicion'])) ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
    <?php endif; ?>

    <?php else: ?>
    <!-- ══════════════ TAB PARTIDOS ═══════════════════════ -->
    <div style="display:flex;justify-content:space-between;align-items:flex-end;flex-wrap:wrap;gap:.75rem;margin-bottom:1rem">
      <h3 style="font-family:var(--font-head);font-size:1rem;text-transform:uppercase;color:var(--navy);margin:0">Partidos</h3>
      <button onclick="document.getElementById('modalCrearPartido').style.display='flex'" class="btn btn-primary btn-sm">+ Nuevo partido</button>
    </div>

    <!-- Filtros -->
    <div class="card mb-3" style="padding:.75rem 1.25rem">
      <form method="get" style="display:flex;gap:.75rem;flex-wrap:wrap;align-items:flex-end">
        <input type="hidden" name="id" value="<?= $id ?>">
        <input type="hidden" name="tab" value="partidos">
        <div style="flex:1;min-width:200px">
          <label style="font-size:.7rem;font-weight:700;text-transform:uppercase;color:var(--navy);display:block;margin-bottom:.25rem">Buscar Jugador / Equipo</label>
          <input type="text" name="search" class="form-control" placeholder="Ej: Perez Gomez" value="<?= epl_h($f_search) ?>">
        </div>
        <div>
          <label style="font-size:.7rem;font-weight:700;text-transform:uppercase;color:var(--navy);display:block;margin-bottom:.25rem">Fecha / Jornada</label>
          <select name="fecha" class="form-control" style="width:auto">
            <option value="">Todas</option>
            <?php foreach ($fechas_disponibles as $fn): ?>
              <option value="<?= epl_h($fn) ?>" <?= $f_fecha===$fn?'selected':'' ?>><?= epl_h($fn) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label style="font-size:.7rem;font-weight:700;text-transform:uppercase;color:var(--navy);display:block;margin-bottom:.25rem">Desde</label>
          <input type="date" name="desde" class="form-control" value="<?= epl_h($f_desde) ?>">
        </div>
        <div>
          <label style="font-size:.7rem;font-weight:700;text-transform:uppercase;color:var(--navy);display:block;margin-bottom:.25rem">Hasta</label>
          <input type="date" name="hasta" class="form-control" value="<?= epl_h($f_hasta) ?>">
        </div>
        <div>
          <label style="font-size:.7rem;font-weight:700;text-transform:uppercase;color:var(--navy);display:block;margin-bottom:.25rem">Estado</label>
          <select name="estado_p" class="form-control" style="width:auto">
            <option value="">Todos</option>
            <option value="pendiente"    <?= $f_est==='pendiente'    ?'selected':'' ?>>Pendiente</option>
            <option value="jugado"       <?= $f_est==='jugado'       ?'selected':'' ?>>Jugado</option>
            <option value="reprogramado" <?= $f_est==='reprogramado' ?'selected':'' ?>>Reprogramado</option>
            <option value="walkover"     <?= $f_est==='walkover'     ?'selected':'' ?>>Walkover</option>
          </select>
        </div>
        <button type="submit" class="btn btn-navy btn-sm">Filtrar</button>
        <?php if ($f_fecha || $f_est || $f_search || $f_desde || $f_hasta): ?>
          <a href="liga_detalle.php?id=<?= $id ?>&tab=partidos" class="btn btn-sm" style="border:1px solid var(--gray-200);color:var(--gray-600)">Limpiar</a>
        <?php endif; ?>
      </form>
    </div>

    <div class="card mb-3" id="bulkBar" style="display:none; position:sticky; top:1rem; z-index:100; background:var(--navy); color:#fff; padding:.75rem 1.25rem; border-radius:.5rem; box-shadow:0 10px 25px rgba(0,0,0,.2)">
      <form method="post" id="bulkForm" style="display:flex; align-items:center; gap:1rem; flex-wrap:wrap">
        <input type="hidden" name="action" value="bulk_partidos">
        <div style="font-weight:700; font-size:.8rem; text-transform:uppercase; letter-spacing:.05em">
          <span id="bulkCount">0</span> seleccionados
        </div>
        
        <select name="bulk_action" id="bulkAction" class="form-control" style="width:auto; background:rgba(255,255,255,1); border-color:rgba(255,255,255,.2); color:var(--navy); font-size:.75rem" onchange="toggleBulkExtra()">
          <option value="" style="color:var(--navy)">— Acción masiva —</option>
          <option value="estado_pendiente" style="color:var(--navy)">Mover a Pendiente (borrar resultado)</option>
          <option value="cambiar_fecha_programada" style="color:var(--navy)">Cambiar Fecha y Hora (Programación)</option>
          <option value="poner_alerta" style="color:var(--navy)">⚠️ Poner/Quitar Alerta Admin</option>
          <option value="cambiar_recinto" style="color:var(--navy)">Cambiar Recinto/Cancha</option>
          <option value="cambiar_fecha" style="color:var(--navy)">Cambiar Nombre de Fecha/Jornada</option>
          <option value="eliminar" style="color:#dc2626; font-weight:700">Eliminar permanentemente</option>
        </select>

        <div id="bulkExtraAlerta" style="display:none">
          <input type="text" name="bulk_alerta" class="form-control" placeholder="Texto de la alerta..." style="width:250px; font-size:.75rem">
        </div>

        <div id="bulkExtraFechaProg" style="display:none">
          <input type="datetime-local" name="bulk_fecha_programada" class="form-control" style="width:auto; font-size:.75rem">
        </div>

        <div id="bulkExtraRecinto" style="display:none">
          <select name="bulk_recinto_id" class="form-control" style="width:auto; font-size:.75rem">
            <option value="">— Sin recinto —</option>
            <?php foreach ($todos_recintos as $rc): ?>
              <option value="<?= $rc['id'] ?>"><?= epl_h($rc['label']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div id="bulkExtraFecha" style="display:none; gap:.5rem; align-items:center">
          <input type="text" name="bulk_nombre_fecha" class="form-control" placeholder="Nombre Fecha" style="width:120px; font-size:.75rem">
          <input type="number" name="bulk_jornada" class="form-control" placeholder="Jornada N°" style="width:80px; font-size:.75rem">
        </div>

        <button type="submit" class="btn btn-gold btn-sm" onclick="return confirm('¿Aplicar acción masiva a los partidos seleccionados?')">Aplicar</button>
        <button type="button" class="btn btn-sm" style="background:none; color:rgba(255,255,255,.6)" onclick="deselectAllPartidos()">Cancelar</button>
      </form>
    </div>

    <div class="card">
      <div style="overflow-x:auto">
        <table style="width:100%;border-collapse:collapse;font-size:.83rem">
          <thead>
            <tr style="background:var(--navy);color:#fff">
              <th style="padding:.65rem .75rem;width:40px;text-align:center"><input type="checkbox" id="checkAllPartidos"></th>
              <th style="padding:.65rem .75rem;text-align:left;font-size:.7rem;text-transform:uppercase">Fecha</th>
              <th style="padding:.65rem 1rem;text-align:left;font-size:.7rem;text-transform:uppercase">Local</th>
              <th style="padding:.65rem .75rem;text-align:center;font-size:.7rem;text-transform:uppercase">Resultado</th>
              <th style="padding:.65rem 1rem;text-align:right;font-size:.7rem;text-transform:uppercase">Visitante</th>
              <th style="padding:.65rem .75rem;text-align:center;font-size:.7rem;text-transform:uppercase">Estado</th>
              <th style="padding:.65rem .75rem;text-align:center;font-size:.7rem;text-transform:uppercase">Cancha</th>
              <th style="padding:.65rem .75rem;text-align:center;font-size:.7rem;text-transform:uppercase">Jornada</th>
              <th style="padding:.65rem .75rem;text-align:center;font-size:.7rem;text-transform:uppercase"></th>
            </tr>
          </thead>
          <tbody>
            <?php
            $current_fecha = null;
            foreach ($partidos as $p):
              // Separador visual por nombre_fecha
              if ($p['nombre_fecha'] !== $current_fecha):
                $current_fecha = $p['nombre_fecha'];
            ?>
            <tr style="background:var(--gray-100)">
              <td style="padding:.4rem .75rem;text-align:center">
                <input type="checkbox" class="check-group" data-group="<?= epl_h($p['nombre_fecha']) ?>" title="Seleccionar toda esta fecha">
              </td>
              <td colspan="8" style="padding:.4rem 1rem;font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:var(--navy)">
                📅 <?= epl_h($p['nombre_fecha'] ?? 'Sin fecha asignada') ?>
                <?php if ($p['jornada']): ?><span style="font-weight:400;color:var(--gray-400);margin-left:.5rem">— Jornada <?= $p['jornada'] ?></span><?php endif; ?>
              </td>
            </tr>
            <?php endif; ?>
            <tr style="border-bottom:1px solid var(--gray-100)">
              <td style="padding:.6rem .75rem;text-align:center">
                <input type="checkbox" name="partido_ids[]" value="<?= $p['id'] ?>" class="partido-check" data-group="<?= epl_h($p['nombre_fecha']) ?>" form="bulkForm">
              </td>
              <td style="padding:.6rem .75rem;font-size:.75rem;color:var(--gray-500)">
                <?= $p['fecha_programada'] ? date('d/m H:i', strtotime($p['fecha_programada'])) : '—' ?>
                <?php if ($p['alerta_admin']): ?>
                  <div style="margin-top:.2rem"><span class="badge badge-walkover" style="font-size:.6rem;padding:.1rem .3rem"><?= epl_h($p['alerta_admin']) ?></span></div>
                <?php endif; ?>
              </td>
              <td style="padding:.6rem 1rem;font-weight:600;color:var(--navy)"><?= epl_h($p['local_nombre']) ?></td>
              <td style="padding:.6rem .75rem;text-align:center">
                <?php if ($p['estado']==='jugado'): ?>
                  <span style="font-family:var(--font-head);font-size:1rem;font-weight:700"><?= $p['sets_local'] ?>–<?= $p['sets_visitante'] ?></span>
                  <?php $ss=[];for($s=1;$s<=3;$s++){$gl=$p["games_s{$s}_local"];$gv=$p["games_s{$s}_visitante"];if($gl!==null)$ss[]="$gl-$gv";}
                  if($ss): ?><div style="font-size:.68rem;color:var(--gray-400)"><?= implode(' · ',$ss) ?></div><?php endif;
                elseif (in_array($p['estado'],['walkover','no_presentado'])): ?>
                  <span style="font-size:.75rem;color:#dc2626;font-weight:700">W/O</span>
                <?php else: ?><span style="color:var(--gray-300);font-size:.9rem">vs</span><?php endif; ?>
              </td>
              <td style="padding:.6rem 1rem;font-weight:600;color:var(--navy);text-align:right"><?= epl_h($p['visitante_nombre']) ?></td>
              <td style="padding:.6rem .75rem;text-align:center">
                <div class="info-val">
                  <?php if ($liga['recinto_nombre']): ?>
                    <?= epl_h($liga['recinto_nombre']) ?>
                    <?php if ($liga['recinto_superior_nombre']): ?>
                      <span style="display:block;font-size:.7rem;color:var(--gray-400);font-weight:400"><?= epl_h($liga['recinto_superior_nombre']) ?></span>
                    <?php endif; ?>
                  <?php else: ?>
                    <?= epl_h($liga['sede'] ?: 'No definido') ?>
                  <?php endif; ?>
                </div>
              </td>
              <td style="padding:.6rem .75rem;text-align:center;font-size:.75rem;color:var(--gray-500)">
                <?= $p['jornada'] ?: '—' ?>
              </td>
              <td style="padding:.6rem .75rem;text-align:center">
                <button type="button" onclick="window.editarPartido(this)" data-partido="<?= epl_h(base64_encode((string)(json_encode($p, JSON_UNESCAPED_UNICODE) ?: '{}'))) ?>" class="btn btn-sm" style="border:1px solid var(--gray-200);font-size:.68rem">Editar</button>
              </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($partidos)): ?>
              <tr><td colspan="7" style="padding:2rem;text-align:center;color:var(--gray-400)">No hay partidos<?= $f_fecha?' en esta fecha':'' ?>.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
    <?php endif; ?>

  </main>
</div>

<!-- Modal editar liga -->
<div id="modalEditar" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:99999;align-items:center;justify-content:center;padding:1rem">
  <div class="card" style="width:100%;max-width:560px;max-height:92vh;overflow-y:auto">
    <div class="card-head" style="position:sticky;top:0;background:#fff;z-index:1">
      <h3 style="font-family:var(--font-head);font-size:1rem;text-transform:uppercase;color:var(--navy)">Editar Competición</h3>
      <button onclick="document.getElementById('modalEditar').style.display='none'" style="background:none;font-size:1.5rem;color:var(--gray-400)">×</button>
    </div>
    <div class="card-body">
      <form method="post">
        <input type="hidden" name="action" value="editar_liga">
        <div class="form-group"><label class="form-label">Nombre *</label><input type="text" name="nombre" class="form-control" required value="<?= epl_h($liga['nombre']) ?>"></div>
        <div class="grid-2">
          <div class="form-group"><label class="form-label">Tipo</label><select name="tipo" class="form-control"><option value="liga" <?= $liga['tipo']==='liga'?'selected':'' ?>>Liga</option><option value="torneo" <?= $liga['tipo']==='torneo'?'selected':'' ?>>Torneo</option></select></div>
          <div class="form-group"><label class="form-label">Formato</label><select name="formato" class="form-control"><option value="liga_regular" <?= $liga['formato']==='liga_regular'?'selected':'' ?>>Liga regular</option><option value="mata_mata" <?= $liga['formato']==='mata_mata'?'selected':'' ?>>Mata-mata</option><option value="grupos_mata_mata" <?= $liga['formato']==='grupos_mata_mata'?'selected':'' ?>>Grupos + Mata-mata</option></select></div>
        </div>
              <div class="info-item">
                <div class="info-label">Estado</div>
                <div class="info-val">
                  <?php 
                  $est_auto = epl_get_liga_status($liga);
                  $labels = ['proximamente'=>'Próximamente','inscripcion'=>'Inscripción','activa'=>'Activa','finalizada'=>'Finalizada'];
                  $badges = ['proximamente'=>'badge-reprog','inscripcion'=>'badge-pendiente','activa'=>'badge-jugado','finalizada'=>'badge-walkover'];
                  ?>
                  <span class="badge <?= $badges[$est_auto] ?? '' ?>">
                    <?= $labels[$est_auto] ?? $est_auto ?>
                  </span>
                </div>
              </div>
        <div class="grid-2">
          <div class="form-group">
            <label class="form-label">Temporada</label>
            <input type="text" name="temporada" class="form-control" value="<?= epl_h($liga['temporada']??'') ?>">
          </div>
          <div class="form-group">
            <label class="form-label">Categoría</label>
            <select name="categoria" class="form-control">
              <?php for ($n=1;$n<=8;$n++): ?>
                <option value="<?= $n ?>" <?= ($liga['categoria']??5)==$n?'selected':'' ?>><?= $n ?>ª categoría</option>
              <?php endfor; ?>
            </select>
          </div>
        </div>
        <div class="grid-2">
          <div class="form-group">
            <label class="form-label">Fecha inicio liga</label>
            <input type="date" name="fecha_inicio" class="form-control" value="<?= $liga['fecha_inicio']??'' ?>">
          </div>
          <div class="form-group">
            <label class="form-label">Fecha fin liga</label>
            <input type="date" name="fecha_fin" class="form-control" value="<?= $liga['fecha_fin']??'' ?>">
          </div>
        </div>

        <p style="font-size:.7rem;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:var(--navy);margin:.5rem 0 .75rem">Inscripciones (Automático)</p>
        <div class="grid-2">
          <div class="form-group">
            <label class="form-label">Apertura inscrip.</label>
            <input type="date" name="inscripcion_inicio" class="form-control" value="<?= $liga['inscripcion_inicio']??'' ?>">
          </div>
          <div class="form-group">
            <label class="form-label">Cierre inscrip.</label>
            <input type="date" name="inscripcion_fin" class="form-control" value="<?= $liga['inscripcion_fin']??'' ?>">
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Recinto / Club</label>
          <select name="recinto_id" class="form-control">
            <option value="">— Selecciona un recinto —</option>
            <?php 
            // Fetch root clubs and their direct children (sedes)
            $stR = $db->query("SELECT id, nombre, superior_id FROM recintos WHERE superior_id IS NULL OR superior_id IN (SELECT id FROM recintos WHERE superior_id IS NULL) ORDER BY COALESCE(superior_id, id), superior_id IS NOT NULL, nombre");
            $all_r = $stR->fetchAll();
            foreach ($all_r as $r): 
              $prefix = $r['superior_id'] ? '   📍 ' : '🏛 ';
            ?>
              <option value="<?= $r['id'] ?>" <?= ($liga['recinto_id']??0)==$r['id']?'selected':'' ?>><?= $prefix . epl_h($r['nombre']) ?></option>
            <?php endforeach; ?>
          </select>
          <input type="hidden" name="recinto" value="<?= epl_h($liga['sede']??'') ?>">
        </div>
        <div class="form-group"><label class="form-label">URL Google Maps</label><input type="url" name="url_maps" class="form-control" value="<?= epl_h($liga['url_maps']??'') ?>"></div>
        <div class="grid-2">
          <div class="form-group"><label class="form-label">Estado</label><select name="estado" class="form-control"><option value="proximamente" <?= $liga['estado']==='proximamente'?'selected':'' ?>>Próximamente</option><option value="inscripcion" <?= $liga['estado']==='inscripcion'?'selected':'' ?>>Inscripción</option><option value="activa" <?= $liga['estado']==='activa'?'selected':'' ?>>Activa</option><option value="finalizada" <?= $liga['estado']==='finalizada'?'selected':'' ?>>Finalizada</option></select></div>
          <div class="form-group"><label class="form-label">Precio (CLP)</label><input type="number" name="precio" class="form-control" min="0" value="<?= $liga['precio']??'' ?>"></div>
        </div>
        <p style="font-size:.7rem;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:var(--navy);margin:1rem 0 .75rem;border-top:1px solid var(--gray-200);padding-top:1rem">Puntos de ranking</p>
        <div class="grid-2">
          <div class="form-group"><label class="form-label">🥇 1°</label><input type="number" name="puntos_1" class="form-control" min="0" value="<?= $liga['puntos_1'] ?>"></div>
          <div class="form-group"><label class="form-label">🥈 2°</label><input type="number" name="puntos_2" class="form-control" min="0" value="<?= $liga['puntos_2'] ?>"></div>
        </div>
        <div class="grid-2">
          <div class="form-group"><label class="form-label">🥉 3°</label><input type="number" name="puntos_3" class="form-control" min="0" value="<?= $liga['puntos_3'] ?>"></div>
          <div class="form-group"><label class="form-label">4°</label><input type="number" name="puntos_4" class="form-control" min="0" value="<?= $liga['puntos_4'] ?>"></div>
        </div>
        <div class="form-group"><label class="form-label">Participación</label><input type="number" name="puntos_grupos" class="form-control" min="0" value="<?= $liga['puntos_grupos'] ?>"></div>
        <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;margin-top:.5rem">Guardar cambios</button>
      </form>
    </div>
  </div>
</div>

<!-- Modal agregar equipo -->
<div id="modalAgregarEquipo" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:99999;align-items:center;justify-content:center;padding:1rem">
  <div class="card" style="width:100%;max-width:480px">
    <div class="card-head">
      <h3 style="font-family:var(--font-head);font-size:1rem;text-transform:uppercase;color:var(--navy)">Agregar Equipo</h3>
      <button onclick="document.getElementById('modalAgregarEquipo').style.display='none'" style="background:none;font-size:1.5rem;color:var(--gray-400)">×</button>
    </div>
    <div class="card-body">
      <form method="post">
        <input type="hidden" name="action" value="agregar_equipo">
        <div class="form-group"><label class="form-label">Nombre del equipo</label><input type="text" name="nombre_equipo" class="form-control" placeholder="Se genera automático si está vacío"></div>
        <div class="grid-2">
          <div class="form-group">
            <label class="form-label">Jugador 1 *</label>
            <select name="jugador1_id" class="form-control" required>
              <option value="">— Selecciona —</option>
              <?php foreach ($todos_jugadores as $j): ?><option value="<?= $j['id'] ?>"><?= epl_h($j['apellido'].', '.$j['nombre']) ?></option><?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Jugador 2 *</label>
            <select name="jugador2_id" class="form-control" required>
              <option value="">— Selecciona —</option>
              <?php foreach ($todos_jugadores as $j): ?><option value="<?= $j['id'] ?>"><?= epl_h($j['apellido'].', '.$j['nombre']) ?></option><?php endforeach; ?>
            </select>
          </div>
        </div>
        <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center">Agregar equipo</button>
      </form>
    </div>
  </div>
</div>

<!-- Modal crear partido -->
<div id="modalCrearPartido" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:99999;align-items:center;justify-content:center;padding:1rem">
  <div class="card" style="width:100%;max-width:500px;max-height:92vh;overflow-y:auto">
    <div class="card-head">
      <h3 style="font-family:var(--font-head);font-size:1rem;text-transform:uppercase;color:var(--navy)">Nuevo Partido</h3>
      <button onclick="document.getElementById('modalCrearPartido').style.display='none'" style="background:none;font-size:1.5rem;color:var(--gray-400)">×</button>
    </div>
    <div class="card-body">
      <form method="post">
        <input type="hidden" name="action" value="crear_partido">
        <div class="grid-2">
          <div class="form-group">
            <label class="form-label">Nombre de fecha *</label>
            <input type="text" name="nombre_fecha" class="form-control" placeholder="Fecha 1, Fecha 2, Cuartos..." list="fechas-list">
            <datalist id="fechas-list">
              <?php foreach ($fechas_disponibles as $fn): ?><option value="<?= epl_h($fn) ?>"><?php endforeach; ?>
            </datalist>
          </div>
          <div class="form-group">
            <label class="form-label">Jornada N°</label>
            <input type="number" name="jornada" class="form-control" min="1" placeholder="1">
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Equipo local *</label>
          <select name="equipo_local_id" class="form-control" required>
            <option value="">— Selecciona —</option>
            <?php foreach ($equipos_liga as $e): ?><option value="<?= $e['id'] ?>"><?= epl_h($e['nombre']) ?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Equipo visitante *</label>
          <select name="equipo_visitante_id" class="form-control" required>
            <option value="">— Selecciona —</option>
            <?php foreach ($equipos_liga as $e): ?><option value="<?= $e['id'] ?>"><?= epl_h($e['nombre']) ?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="grid-2">
          <div class="form-group">
            <label class="form-label">Cancha / Recinto</label>
            <select name="recinto_id" class="form-control">
              <option value="">— Sin recinto —</option>
              <?php foreach ($todos_recintos as $rc): ?>
                <option value="<?= $rc['id'] ?>"><?= epl_h($rc['label']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Fecha y hora</label>
            <input type="datetime-local" name="fecha_programada" class="form-control">
          </div>
        </div>
        <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center">Crear partido</button>
      </form>
    </div>
  </div>
</div>

<!-- Modal editar partido -->
<div id="modalEditarPartido" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:99999;align-items:center;justify-content:center;padding:1rem">
  <div class="card" style="width:100%;max-width:500px;max-height:92vh;overflow-y:auto">
    <div class="card-head">
      <h3 id="editPartidoTitle" style="font-family:var(--font-head);font-size:1rem;text-transform:uppercase;color:var(--navy)">Editar Partido</h3>
      <button onclick="document.getElementById('modalEditarPartido').style.display='none'" style="background:none;font-size:1.5rem;color:var(--gray-400)">×</button>
    </div>
    <div class="card-body">
      <form method="post">
        <input type="hidden" name="action" value="editar_resultado">
        <input type="hidden" name="partido_id" id="editPartidoId">
        <div class="grid-2">
          <div class="form-group">
            <label class="form-label">Nombre de fecha</label>
            <input type="text" name="nombre_fecha" id="editNombreFecha" class="form-control" placeholder="Fecha 1, Cuartos..." list="fechas-list">
          </div>
          <div class="form-group">
            <label class="form-label">Jornada N°</label>
            <input type="number" name="jornada" id="editJornada" class="form-control" min="1">
          </div>
        </div>
        <div class="grid-2">
          <div class="form-group">
            <label class="form-label">Cancha / Recinto</label>
            <select name="recinto_id" id="editRecintoId" class="form-control">
              <option value="">— Sin recinto —</option>
              <?php foreach ($todos_recintos as $rc): ?>
                <option value="<?= $rc['id'] ?>" data-id="<?= $rc['id'] ?>"><?= epl_h($rc['label']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Fecha programada</label>
            <input type="datetime-local" name="fecha_programada" id="editFechaProg" class="form-control">
          </div>
        </div>
        <div class="grid-2">
          <div class="form-group">
            <label class="form-label">Estado</label>
            <select name="estado" id="editEstado" class="form-control">
              <option value="pendiente">Pendiente</option>
              <option value="jugado">Jugado</option>
              <option value="reprogramado">Reprogramado</option>
              <option value="walkover">Walkover</option>
              <option value="no_presentado">No presentado</option>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Fecha jugado</label>
            <input type="datetime-local" name="fecha_jugado" id="editFechaJugado" class="form-control">
          </div>
        </div>
        <p style="font-size:.78rem;font-weight:700;text-transform:uppercase;color:var(--navy);margin:.75rem 0 .5rem">Resultado</p>
        <?php for ($s=1;$s<=3;$s++): ?>
        <div class="score-input-row">
          <span class="score-label">Set <?= $s ?></span>
          <div class="score-fields">
            <input type="number" name="s<?= $s ?>_l" id="s<?= $s ?>l" class="score-num form-control" min="0" max="7" placeholder="0">
            <span class="score-sep">–</span>
            <input type="number" name="s<?= $s ?>_v" id="s<?= $s ?>v" class="score-num form-control" min="0" max="7" placeholder="0">
          </div>
        </div>
        <?php endfor; ?>
        <div class="form-group">
          <label class="form-label">Alerta / Nota Admin (Etiqueta roja)</label>
          <input type="text" name="alerta_admin" id="editAlertaAdmin" class="form-control" placeholder="Ej: Falta confirmar cancha, Pendiente WO...">
        </div>
        <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;margin-top:.75rem">Guardar cambios</button>
      </form>
    </div>
  </div>
</div>

<script>
window.editarPartido = function (btn) {
  var modal = document.getElementById('modalEditarPartido');
  if (!modal) return;
  var raw = btn.getAttribute('data-partido');
  if (!raw) return;
  var bin = atob(raw);
  var bytes = new Uint8Array(bin.length);
  for (var i = 0; i < bin.length; i++) bytes[i] = bin.charCodeAt(i);
  var p = JSON.parse(new TextDecoder('utf-8').decode(bytes));
  function q(sel) {
    return modal.querySelector(sel);
  }
  function setVal(sel, v) {
    var el = q(sel);
    if (el) el.value = v;
  }
  var hid = q('input[name="partido_id"]');
  if (!hid) return;
  hid.value = p.id;
  var titleEl = q('#editPartidoTitle');
  if (titleEl) titleEl.textContent = p.local_nombre + ' vs ' + p.visitante_nombre;
  setVal('input[name="nombre_fecha"]', p.nombre_fecha || '');
  setVal('input[name="jornada"]', p.jornada || '');
  setVal('select[name="recinto_id"]', p.recinto_id || '');
  setVal('select[name="estado"]', p.estado || 'pendiente');
  var fp = p.fecha_programada ? String(p.fecha_programada).replace(' ', 'T').substring(0, 16) : '';
  var fj = p.fecha_jugado ? String(p.fecha_jugado).replace(' ', 'T').substring(0, 16) : '';
  setVal('input[name="fecha_programada"]', fp);
  setVal('input[name="fecha_jugado"]', fj);
  [1, 2, 3].forEach(function (s) {
    setVal('input[name="s' + s + '_l"]', p['games_s' + s + '_local'] != null ? p['games_s' + s + '_local'] : '');
    setVal('input[name="s' + s + '_v"]', p['games_s' + s + '_visitante'] != null ? p['games_s' + s + '_visitante'] : '');
  });
  setVal('input[name="alerta_admin"]', p.alerta_admin || '');
  modal.style.display = 'flex';
};

// Bulk Actions Logic
const bulkBar = document.getElementById('bulkBar');
const bulkCount = document.getElementById('bulkCount');
const checkAll = document.getElementById('checkAllPartidos');
const checks = document.querySelectorAll('.partido-check');

function updateBulkBar() {
  const selected = document.querySelectorAll('.partido-check:checked');
  if (selected.length > 0) {
    bulkBar.style.display = 'flex';
    bulkCount.textContent = selected.length;
  } else {
    bulkBar.style.display = 'none';
  }
}

if (checkAll) {
  checkAll.addEventListener('change', function() {
    checks.forEach(c => c.checked = this.checked);
    updateBulkBar();
  });
}

checks.forEach(c => {
  c.addEventListener('change', updateBulkBar);
});

function deselectAllPartidos() {
  checks.forEach(c => c.checked = false);
  if (checkAll) checkAll.checked = false;
  updateBulkBar();
}

function toggleBulkExtra() {
  const action = document.getElementById('bulkAction').value;
  document.getElementById('bulkExtraRecinto').style.display = (action === 'cambiar_recinto') ? 'block' : 'none';
  document.getElementById('bulkExtraFecha').style.display = (action === 'cambiar_fecha') ? 'flex' : 'none';
  document.getElementById('bulkExtraFechaProg').style.display = (action === 'cambiar_fecha_programada') ? 'block' : 'none';
  document.getElementById('bulkExtraAlerta').style.display = (action === 'poner_alerta') ? 'block' : 'none';
}

document.querySelectorAll('.check-group').forEach(groupCheck => {
  groupCheck.addEventListener('change', function() {
    const groupName = this.getAttribute('data-group');
    document.querySelectorAll('.partido-check[data-group="' + groupName + '"]').forEach(c => {
      c.checked = this.checked;
    });
    updateBulkBar();
  });
});
</script>

<?php require_once '../includes/footer.php'; ?>
