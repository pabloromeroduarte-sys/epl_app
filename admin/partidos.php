<?php
$page_title = 'Admin — Partidos';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../includes/mail.php';
epl_require_admin();

$db = epl_db();

// ────────────────────────────────────────────────────────────────────────
// Helper redirect que preserva filtros
// ────────────────────────────────────────────────────────────────────────
function partidos_redirect(string $msg = '', string $err = '', array $extra = []): void {
    if ($msg) epl_flash_set('ok', $msg);
    if ($err) epl_flash_set('error', $err);

    $params = [];
    // Filtros desde POST hidden inputs
    foreach (['_f_liga'=>'liga','_f_fecha'=>'fecha','_f_estado_p'=>'estado_p','_f_search'=>'search','_f_desde'=>'desde','_f_hasta'=>'hasta'] as $post_k => $get_k) {
        if (!empty($_POST[$post_k])) $params[$get_k] = $_POST[$post_k];
    }
    // Si vino desde GET con filtros
    foreach (['liga','fecha','estado_p','search','desde','hasta'] as $k) {
        if (!isset($params[$k]) && !empty($_GET[$k])) $params[$k] = $_GET[$k];
    }
    foreach ($extra as $k => $v) $params[$k] = $v;

    $url = 'partidos.php' . (!empty($params) ? '?' . http_build_query($params) : '');
    header('Location: ' . $url);
    exit;
}

// ────────────────────────────────────────────────────────────────────────
// POST handlers (bulk_partidos, crear_partido, editar_resultado)
// ────────────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // ── Bulk acciones ───────────────────────────────────────
    if ($action === 'bulk_partidos') {
        $partido_ids = $_POST['partido_ids'] ?? [];
        $bulk_action = $_POST['bulk_action'] ?? '';

        if (empty($partido_ids)) {
            partidos_redirect('', 'No se seleccionaron partidos.');
        }
        $ids_str = implode(',', array_map('intval', $partido_ids));
        $msg = '';

        // Exportar a CSV/Excel
        if ($bulk_action === 'exportar_excel') {
            $rows = $db->query("
                SELECT p.jornada, p.nombre_fecha, p.fecha_programada, p.estado,
                       el.nombre AS local_nombre, ev.nombre AS visitante_nombre,
                       p.sets_local, p.sets_visitante,
                       p.games_s1_local, p.games_s1_visitante,
                       p.games_s2_local, p.games_s2_visitante,
                       p.games_s3_local, p.games_s3_visitante,
                       r.nombre AS recinto_nombre,
                       l.nombre AS liga_nombre,
                       sr.motivo
                FROM partidos p
                JOIN ligas l ON l.id = p.liga_id
                JOIN equipos el ON el.id = p.equipo_local_id
                JOIN equipos ev ON ev.id = p.equipo_visitante_id
                LEFT JOIN recintos r ON r.id = p.recinto_id
                LEFT JOIN solicitudes_reprogramacion sr ON sr.id = (
                    SELECT MAX(sr2.id) FROM solicitudes_reprogramacion sr2
                    WHERE sr2.partido_id = p.id AND sr2.estado != 'rechazada'
                )
                WHERE p.id IN ($ids_str)
                ORDER BY p.fecha_programada IS NULL ASC, p.fecha_programada ASC
            ")->fetchAll();

            $nombre_archivo = 'partidos_EPL_' . date('Ymd_His') . '.csv';
            header('Content-Type: text/csv; charset=UTF-8');
            header('Content-Disposition: attachment; filename="' . $nombre_archivo . '"');
            header('Cache-Control: no-cache');
            $out = fopen('php://output', 'w');
            fputs($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Liga','Jornada','Nombre Fecha','Fecha Programada','Hora','Local','Visitante','Sets Local','Sets Visitante','S1 (L-V)','S2 (L-V)','S3 (L-V)','Estado','Cancha','Motivo Reprog.'], ';');
            foreach ($rows as $r) {
                $fp = $r['fecha_programada'];
                $es_ph = $fp && date('Y-m-d', strtotime($fp)) === '2026-12-31';
                $fecha_fmt = (!$fp || $es_ph) ? 'Sin fecha' : date('d/m/Y', strtotime($fp));
                $hora_fmt  = (!$fp || $es_ph) ? '' : date('H:i', strtotime($fp));
                $estado_label = match($r['estado']) {
                    'jugado' => 'Jugado','pendiente' => 'Pendiente','reprogramado' => 'Reprogramado',
                    'walkover' => 'Walkover','no_presentado' => 'No presentado', default => $r['estado'],
                };
                $s1 = ($r['games_s1_local'] !== null) ? $r['games_s1_local'].'-'.$r['games_s1_visitante'] : '';
                $s2 = ($r['games_s2_local'] !== null) ? $r['games_s2_local'].'-'.$r['games_s2_visitante'] : '';
                $s3 = ($r['games_s3_local'] !== null) ? $r['games_s3_local'].'-'.$r['games_s3_visitante'] : '';
                fputcsv($out, [
                    $r['liga_nombre'], $r['jornada'] ? 'J'.$r['jornada'] : '',
                    $r['nombre_fecha'] ?? '', $fecha_fmt, $hora_fmt,
                    $r['local_nombre'], $r['visitante_nombre'],
                    $r['sets_local'] ?? '', $r['sets_visitante'] ?? '',
                    $s1, $s2, $s3, $estado_label, $r['recinto_nombre'] ?? '', $r['motivo'] ?? '',
                ], ';');
            }
            fclose($out);
            exit;
        }

        if ($bulk_action === 'eliminar') {
            $db->query("DELETE FROM partidos WHERE id IN ($ids_str)");
            $msg = count($partido_ids) . ' partidos eliminados.';
        } elseif ($bulk_action === 'estado_pendiente') {
            $db->query("UPDATE partidos SET estado='pendiente', ganador_id=NULL, sets_local=NULL, sets_visitante=NULL WHERE id IN ($ids_str)");
            $msg = 'Movidos a Pendiente (resultado borrado).';
        } elseif ($bulk_action === 'cambiar_recinto') {
            $rid = (int)($_POST['bulk_recinto_id'] ?? 0) ?: null;
            $db->prepare("UPDATE partidos SET recinto_id=? WHERE id IN ($ids_str)")->execute([$rid]);
            foreach ($partido_ids as $pid_b) {
                epl_notif_partido((int)$pid_b, 'reprogramacion', '📅 Nueva cancha asignada',
                    'La cancha de tu partido fue modificada por la organización.',
                    epl_url('mis_torneos.php'), false, [], true);
            }
            $msg = 'Recintos actualizados y jugadores notificados.';
        } elseif ($bulk_action === 'cambiar_fecha') {
            $n_fecha = trim($_POST['bulk_nombre_fecha'] ?? '');
            $n_jornada = (int)($_POST['bulk_jornada'] ?? 0) ?: null;
            if ($n_fecha) {
                $db->prepare("UPDATE partidos SET nombre_fecha=?, jornada=? WHERE id IN ($ids_str)")->execute([$n_fecha, $n_jornada]);
                $msg = 'Información de jornada actualizada.';
            }
        } elseif ($bulk_action === 'cambiar_fecha_programada') {
            $sin_fecha    = !empty($_POST['bulk_sin_fecha']);
            $n_fecha_prog = trim($_POST['bulk_fecha_programada'] ?? '');
            if ($sin_fecha) {
                $db->query("UPDATE partidos SET fecha_programada=NULL WHERE id IN ($ids_str)");
                $db->query("UPDATE partidos SET recinto_id=NULL WHERE id IN ($ids_str) AND estado='reprogramado'");
                $msg = 'Fechas eliminadas — los partidos quedan Sin fecha.';
            } elseif ($n_fecha_prog) {
                $fecha_db = str_replace('T', ' ', $n_fecha_prog) . ':00';
                $db->prepare("UPDATE partidos SET fecha_programada=? WHERE id IN ($ids_str)")->execute([$fecha_db]);
                $fecha_fmt = date('d/m/Y H:i', strtotime($fecha_db));
                foreach ($partido_ids as $pid_b) {
                    epl_notif_partido((int)$pid_b, 'reprogramacion', '📅 Nueva fecha de partido',
                        "Tu partido fue reprogramado para el {$fecha_fmt}.",
                        epl_url('mis_torneos.php'), false, [], true);
                }
                $msg = 'Fechas actualizadas y jugadores notificados.';
            }
        } elseif ($bulk_action === 'cambiar_estado') {
            $nuevo_estado = $_POST['bulk_estado'] ?? '';
            if (in_array($nuevo_estado, ['pendiente','jugado','reprogramado','walkover','no_presentado'], true)) {
                $db->prepare("UPDATE partidos SET estado=? WHERE id IN ($ids_str)")->execute([$nuevo_estado]);
                if ($nuevo_estado === 'reprogramado') {
                    $db->query("UPDATE partidos SET recinto_id=NULL WHERE id IN ($ids_str) AND (fecha_programada IS NULL OR DATE(fecha_programada)='2026-12-31')");
                }
                $msg = 'Estado actualizado a "' . $nuevo_estado . '".';
            }
        } elseif ($bulk_action === 'poner_alerta') {
            $alerta = trim($_POST['bulk_alerta'] ?? '');
            $db->prepare("UPDATE partidos SET alerta_admin=? WHERE id IN ($ids_str)")->execute([$alerta ?: null]);
            $msg = 'Alertas actualizadas.';
        }
        partidos_redirect($msg ?: 'Acción aplicada.');
    }

    // ── Crear partido ───────────────────────────────────────
    if ($action === 'crear_partido') {
        $liga_id_post = (int)($_POST['liga_id'] ?? 0);
        $local_id    = (int)($_POST['equipo_local_id'] ?? 0);
        $vis_id      = (int)($_POST['equipo_visitante_id'] ?? 0);
        $fecha       = trim($_POST['fecha_programada'] ?? '') ?: null;
        $jornada     = (int)($_POST['jornada'] ?? 0) ?: null;
        $nombre_f    = trim($_POST['nombre_fecha'] ?? '') ?: null;
        $recinto_id  = (int)($_POST['recinto_id'] ?? 0) ?: null;

        if (!$liga_id_post)                            partidos_redirect('', 'Seleccioná una liga.');
        if (!$local_id || !$vis_id || $local_id === $vis_id) partidos_redirect('', 'Seleccioná equipos distintos.');

        $db->prepare("INSERT INTO partidos (liga_id,equipo_local_id,equipo_visitante_id,jornada,nombre_fecha,recinto_id,fecha_programada) VALUES (?,?,?,?,?,?,?)")
           ->execute([$liga_id_post, $local_id, $vis_id, $jornada, $nombre_f, $recinto_id, $fecha]);
        foreach ([$local_id, $vis_id] as $eid) {
            $db->prepare("INSERT IGNORE INTO liga_equipos (liga_id,equipo_id) VALUES (?,?)")->execute([$liga_id_post, $eid]);
            $db->prepare("INSERT IGNORE INTO clasificacion (liga_id,equipo_id) VALUES (?,?)")->execute([$liga_id_post, $eid]);
        }
        partidos_redirect('Partido creado.');
    }

    // ── Editar resultado ────────────────────────────────────
    if ($action === 'editar_resultado') {
        $pid        = (int)($_POST['partido_id'] ?? 0);
        $est        = $_POST['estado'] ?? 'jugado';
        $fecha_j    = $_POST['fecha_jugado'] ?? null;
        $jornada    = (int)($_POST['jornada'] ?? 0) ?: null;
        $nombre_f   = trim($_POST['nombre_fecha'] ?? '') ?: null;
        $recinto_id = (int)($_POST['recinto_id'] ?? 0) ?: null;
        $fecha_p    = trim($_POST['fecha_programada'] ?? '') ?: null;
        $alerta_a   = trim($_POST['alerta_admin'] ?? '') ?: null;

        $sets_l = 0; $sets_v = 0; $sets = [];
        for ($s = 1; $s <= 3; $s++) {
            $gl = isset($_POST["s{$s}_l"]) && $_POST["s{$s}_l"] !== '' ? (int)$_POST["s{$s}_l"] : null;
            $gv = isset($_POST["s{$s}_v"]) && $_POST["s{$s}_v"] !== '' ? (int)$_POST["s{$s}_v"] : null;
            $sets[$s] = ['l' => $gl, 'v' => $gv];
            if ($gl !== null && $gv !== null) { if ($gl > $gv) $sets_l++; else $sets_v++; }
        }
        if (($sets_l + $sets_v) > 0 && in_array($est, ['pendiente','reprogramado'])) $est = 'jugado';

        $stP = $db->prepare("SELECT * FROM partidos WHERE id=?"); $stP->execute([$pid]); $p2 = $stP->fetch();
        if (!$p2) partidos_redirect('', 'Partido no encontrado.');

        $ganador_id = null;
        if ($est === 'jugado') {
            $ganador_id = $sets_l > $sets_v ? $p2['equipo_local_id'] : $p2['equipo_visitante_id'];
        }
        if ($est === 'reprogramado' && !$fecha_p) $recinto_id = null;

        $db->prepare("UPDATE partidos SET estado=?,fecha_programada=?,fecha_jugado=?,jornada=?,nombre_fecha=?,recinto_id=?,sets_local=?,sets_visitante=?,games_s1_local=?,games_s1_visitante=?,games_s2_local=?,games_s2_visitante=?,games_s3_local=?,games_s3_visitante=?,ganador_id=?,alerta_admin=? WHERE id=?")
           ->execute([$est, $fecha_p, $fecha_j ?: null, $jornada, $nombre_f, $recinto_id, $sets_l, $sets_v, $sets[1]['l'], $sets[1]['v'], $sets[2]['l'], $sets[2]['v'], $sets[3]['l'], $sets[3]['v'], $ganador_id, $alerta_a, $pid]);
        epl_recalcular_clasificacion((int)$p2['liga_id']);

        // Notificar cambios
        $fecha_cambio   = $fecha_p && $fecha_p !== ($p2['fecha_programada'] ?? '');
        $recinto_cambio = $recinto_id !== ((int)($p2['recinto_id'] ?? 0) ?: null);
        if ($fecha_cambio || $recinto_cambio) {
            $partes = [];
            if ($fecha_cambio)   $partes[] = 'nueva fecha: ' . date('d/m/Y H:i', strtotime($fecha_p));
            if ($recinto_cambio) $partes[] = 'nueva cancha asignada';
            $cambios_str = ucfirst(implode(' · ', $partes));
            epl_notif_partido($pid, 'reprogramacion', '📅 Cambio en tu partido',
                $cambios_str . '. Revisá los detalles en Mis Partidos.',
                epl_url('mis_torneos.php'), false, [], true);
        }
        partidos_redirect('Partido actualizado.');
    }
}

// ────────────────────────────────────────────────────────────────────────
// Flash y datos
// ────────────────────────────────────────────────────────────────────────
$_flash = epl_flash_get();
$ok  = ($_flash && $_flash['tipo'] === 'ok')    ? $_flash['msg'] : '';
$err = ($_flash && $_flash['tipo'] === 'error') ? $_flash['msg'] : '';

$ligas   = $db->query("SELECT * FROM ligas ORDER BY id DESC")->fetchAll();
$liga_id = isset($_GET['liga']) ? (int)$_GET['liga'] : 0;
$liga_sel = null;
foreach ($ligas as $l) { if ($l['id'] == $liga_id) { $liga_sel = $l; break; } }

$f_est    = trim($_GET['estado_p'] ?? '');
$f_search = trim($_GET['search']   ?? '');
$f_desde  = trim($_GET['desde']    ?? '');
$f_hasta  = trim($_GET['hasta']    ?? '');
$f_fecha  = trim($_GET['fecha']    ?? '');

$where_p = "WHERE 1=1";
$params_p = [];
if ($liga_id) { $where_p .= " AND p.liga_id=?"; $params_p[] = $liga_id; }
if ($f_fecha) { $where_p .= " AND p.nombre_fecha=?"; $params_p[] = $f_fecha; }
if ($f_est)   { $where_p .= " AND p.estado=?"; $params_p[] = $f_est; }
if ($f_desde) { $where_p .= " AND p.fecha_programada >= ?"; $params_p[] = $f_desde . ' 00:00:00'; }
if ($f_hasta) { $where_p .= " AND p.fecha_programada <= ?"; $params_p[] = $f_hasta . ' 23:59:59'; }

if ($f_search) {
    foreach (explode(' ', $f_search) as $part) {
        $part = trim($part);
        if ($part === '') continue;
        $where_p .= " AND (el.nombre LIKE ? OR ev.nombre LIKE ? OR
                           jl1.nombre LIKE ? OR jl1.apellido LIKE ? OR jl2.nombre LIKE ? OR jl2.apellido LIKE ? OR
                           jv1.nombre LIKE ? OR jv1.apellido LIKE ? OR jv2.nombre LIKE ? OR jv2.apellido LIKE ? OR
                           l.nombre LIKE ?)";
        $p_val = "%$part%";
        for ($i = 0; $i < 11; $i++) $params_p[] = $p_val;
    }
}

$stP = $db->prepare("
    SELECT p.*, el.nombre AS local_nombre, ev.nombre AS visitante_nombre,
           l.nombre AS liga_nombre, r.nombre AS recinto_nombre, rs.nombre AS recinto_superior_nombre,
           jl1.nombre AS jl1_n, jl1.apellido AS jl1_a, jl1.telefono AS jl1_t,
           jl2.nombre AS jl2_n, jl2.apellido AS jl2_a, jl2.telefono AS jl2_t,
           jv1.nombre AS jv1_n, jv1.apellido AS jv1_a, jv1.telefono AS jv1_t,
           jv2.nombre AS jv2_n, jv2.apellido AS jv2_a, jv2.telefono AS jv2_t
    FROM partidos p
    JOIN ligas l ON l.id = p.liga_id
    JOIN equipos el ON el.id = p.equipo_local_id
    JOIN equipos ev ON ev.id = p.equipo_visitante_id
    LEFT JOIN jugadores jl1 ON jl1.id = el.jugador1_id
    LEFT JOIN jugadores jl2 ON jl2.id = el.jugador2_id
    LEFT JOIN jugadores jv1 ON jv1.id = ev.jugador1_id
    LEFT JOIN jugadores jv2 ON jv2.id = ev.jugador2_id
    LEFT JOIN recintos r ON r.id = p.recinto_id
    LEFT JOIN recintos rs ON rs.id = r.superior_id
    $where_p
    ORDER BY l.id DESC, p.jornada ASC, p.fecha_programada ASC, p.id ASC
");
$stP->execute($params_p);
$partidos = $stP->fetchAll();

// Fechas disponibles (solo si hay liga)
$fechas_disponibles = [];
if ($liga_id) {
    $stF = $db->prepare("SELECT nombre_fecha FROM partidos WHERE liga_id=? AND nombre_fecha IS NOT NULL GROUP BY nombre_fecha ORDER BY MAX(jornada) ASC");
    $stF->execute([$liga_id]);
    $fechas_disponibles = array_column($stF->fetchAll(), 'nombre_fecha');
}

// Equipos para modal crear: si hay liga elegida, solo los de la liga
if ($liga_id) {
    $stE = $db->prepare("SELECT e.* FROM equipos e JOIN liga_equipos le ON le.equipo_id=e.id WHERE le.liga_id=? AND e.estado='activo' ORDER BY e.nombre");
    $stE->execute([$liga_id]);
    $equipos_modal = $stE->fetchAll();
} else {
    $equipos_modal = $db->query("SELECT * FROM equipos WHERE estado='activo' ORDER BY nombre")->fetchAll();
}

// Recintos (árbol)
$_recintos_raw = $db->query("SELECT id, nombre, superior_id FROM recintos ORDER BY nombre")->fetchAll();
$_rec_roots = []; $_rec_children = [];
foreach ($_recintos_raw as $_r) {
    if (!$_r['superior_id']) $_rec_roots[] = $_r;
    else $_rec_children[$_r['superior_id']][] = $_r;
}
$todos_recintos = []; $map_recintos_full = [];
function _flattenRecintosP(array $nodes, array $children, int $depth, array &$out, array &$map, string $path = ''): void {
    foreach ($nodes as $n) {
        $prefix = $depth === 0 ? '🏛 ' : ($depth === 1 ? '   📍 ' : '      🎾 ');
        $out[] = ['id' => $n['id'], 'label' => $prefix . $n['nombre'], 'depth' => $depth];
        $currentPath = $path ? $path . ' · ' . $n['nombre'] : $n['nombre'];
        $map[$n['id']] = $currentPath;
        if (isset($children[$n['id']])) _flattenRecintosP($children[$n['id']], $children, $depth + 1, $out, $map, $currentPath);
    }
}
_flattenRecintosP($_rec_roots, $_rec_children, 0, $todos_recintos, $map_recintos_full);

require_once '../includes/header.php';
?>

<div class="dash-layout">
  <?php include __DIR__ . '/partials/sidebar.php'; ?>

  <main class="dash-main">

    <div class="dash-header" style="background:linear-gradient(135deg,#1c2f48 0%, #0f1e30 100%);border-radius:18px;padding:1.5rem 1.75rem;color:#fff;margin-bottom:1.5rem;position:relative;overflow:hidden;box-shadow:0 8px 28px rgba(28,47,72,.18)">
      <div style="position:absolute;top:-40px;right:-40px;width:180px;height:180px;background:radial-gradient(circle,rgba(201,167,98,.18) 0%,transparent 70%);pointer-events:none"></div>
      <div style="position:relative;z-index:1;display:flex;justify-content:space-between;align-items:center;gap:1rem;flex-wrap:wrap">
        <div>
          <span style="font-size:.65rem;font-weight:900;letter-spacing:.25em;color:#C9A762;text-transform:uppercase">Panel admin</span>
          <h1 class="dash-title" style="color:#fff;margin:.2rem 0 .15rem;font-size:clamp(1.5rem,3.5vw,2rem);font-family:'Anton',sans-serif;text-transform:uppercase;line-height:1">Gestión de <span style="color:#C9A762">Partidos</span></h1>
          <p style="color:rgba(255,255,255,.7);margin-top:.2rem;font-size:.82rem"><?= count($partidos) ?> partido<?= count($partidos)!==1?'s':'' ?> · <?= $liga_sel ? 'Liga: ' . epl_h($liga_sel['nombre']) : 'Todas las ligas' ?></p>
        </div>
        <button onclick="document.getElementById('modalCrearPartido').style.display='flex'" class="btn btn-gold" style="background:var(--gold);color:var(--navy);font-weight:800;text-transform:uppercase;letter-spacing:.05em;padding:.7rem 1.4rem;border-radius:10px;border:none;font-size:.78rem;cursor:pointer">+ Nuevo partido</button>
      </div>
    </div>

    <?php if ($ok): ?><div class="alert alert-success" style="margin-bottom:1rem"><?= epl_h($ok) ?></div><?php endif; ?>
    <?php if ($err): ?><div class="alert alert-error" style="margin-bottom:1rem"><?= epl_h($err) ?></div><?php endif; ?>

    <!-- ── FILTROS ────────────────────────────────── -->
    <div class="card mb-3" style="padding:.85rem 1.25rem">
      <form method="get" style="display:flex;gap:.6rem;flex-wrap:wrap;align-items:flex-end">
        <div style="flex:1;min-width:180px">
          <label style="font-size:.7rem;font-weight:700;text-transform:uppercase;color:var(--navy);display:block;margin-bottom:.25rem">Liga / Torneo</label>
          <select name="liga" class="form-control" onchange="this.form.submit()">
            <option value="">Todas las ligas</option>
            <?php foreach ($ligas as $l): ?>
              <option value="<?= $l['id'] ?>" <?= $l['id']==$liga_id?'selected':'' ?>><?= epl_h($l['nombre']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div style="flex:1.5;min-width:200px">
          <label style="font-size:.7rem;font-weight:700;text-transform:uppercase;color:var(--navy);display:block;margin-bottom:.25rem">Buscar Jugador / Equipo</label>
          <input type="text" name="search" class="form-control" placeholder="Ej: Pérez Gómez" value="<?= epl_h($f_search) ?>">
        </div>
        <?php if ($liga_id && !empty($fechas_disponibles)): ?>
        <div style="min-width:130px">
          <label style="font-size:.7rem;font-weight:700;text-transform:uppercase;color:var(--navy);display:block;margin-bottom:.25rem">Jornada</label>
          <select name="fecha" class="form-control">
            <option value="">Todas</option>
            <?php foreach ($fechas_disponibles as $fn): ?>
              <option value="<?= epl_h($fn) ?>" <?= $f_fecha===$fn?'selected':'' ?>><?= epl_h($fn) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <?php endif; ?>
        <div style="min-width:130px">
          <label style="font-size:.7rem;font-weight:700;text-transform:uppercase;color:var(--navy);display:block;margin-bottom:.25rem">Desde</label>
          <input type="date" name="desde" class="form-control" value="<?= epl_h($f_desde) ?>">
        </div>
        <div style="min-width:130px">
          <label style="font-size:.7rem;font-weight:700;text-transform:uppercase;color:var(--navy);display:block;margin-bottom:.25rem">Hasta</label>
          <input type="date" name="hasta" class="form-control" value="<?= epl_h($f_hasta) ?>">
        </div>
        <div style="min-width:130px">
          <label style="font-size:.7rem;font-weight:700;text-transform:uppercase;color:var(--navy);display:block;margin-bottom:.25rem">Estado</label>
          <select name="estado_p" class="form-control">
            <option value="">Todos</option>
            <option value="pendiente"     <?= $f_est==='pendiente'?'selected':'' ?>>Pendiente</option>
            <option value="jugado"        <?= $f_est==='jugado'?'selected':'' ?>>Jugado</option>
            <option value="reprogramado"  <?= $f_est==='reprogramado'?'selected':'' ?>>Reprogramado</option>
            <option value="walkover"      <?= $f_est==='walkover'?'selected':'' ?>>Walkover</option>
            <option value="no_presentado" <?= $f_est==='no_presentado'?'selected':'' ?>>No presentado</option>
          </select>
        </div>
        <div style="display:flex;gap:.4rem;align-items:flex-end">
          <button type="submit" class="btn btn-navy btn-sm">Filtrar</button>
          <?php if ($f_fecha || $f_est || $f_search || $f_desde || $f_hasta || $liga_id): ?>
            <a href="partidos.php" class="btn btn-sm" style="border:1px solid var(--gray-200);color:var(--gray-600)">✕ Limpiar</a>
          <?php endif; ?>
        </div>
      </form>
    </div>

    <!-- ── BARRA BULK STICKY ─────────────────── -->
    <div class="card mb-3" id="bulkBar" style="display:none;position:sticky;top:1rem;z-index:100;background:var(--navy);color:#fff;padding:.75rem 1.25rem;border-radius:.5rem;box-shadow:0 10px 25px rgba(0,0,0,.2)">
      <form method="post" id="bulkForm" style="display:flex;align-items:center;gap:1rem;flex-wrap:wrap">
        <input type="hidden" name="action" value="bulk_partidos">
        <input type="hidden" name="_f_liga"     value="<?= epl_h((string)$liga_id) ?>">
        <input type="hidden" name="_f_fecha"    value="<?= epl_h($f_fecha) ?>">
        <input type="hidden" name="_f_estado_p" value="<?= epl_h($f_est) ?>">
        <input type="hidden" name="_f_search"   value="<?= epl_h($f_search) ?>">
        <input type="hidden" name="_f_desde"    value="<?= epl_h($f_desde) ?>">
        <input type="hidden" name="_f_hasta"    value="<?= epl_h($f_hasta) ?>">
        <div style="font-weight:700;font-size:.8rem;text-transform:uppercase;letter-spacing:.05em">
          <span id="bulkCount">0</span> seleccionados
        </div>
        <select name="bulk_action" id="bulkAction" class="form-control" style="width:auto;background:#fff;color:var(--navy);font-size:.75rem" onchange="toggleBulkExtra()">
          <option value="" style="color:var(--navy)">— Acción masiva —</option>
          <option value="cambiar_estado">Cambiar Estado</option>
          <option value="estado_pendiente">Mover a Pendiente (borrar resultado)</option>
          <option value="cambiar_fecha_programada">Cambiar Fecha y Hora</option>
          <option value="poner_alerta">⚠️ Poner/Quitar Alerta Admin</option>
          <option value="cambiar_recinto">Cambiar Recinto/Cancha</option>
          <option value="cambiar_fecha">Cambiar Nombre Fecha/Jornada</option>
          <option value="exportar_excel" style="color:#059669;font-weight:700">⬇ Exportar a Excel (.csv)</option>
          <option value="eliminar" style="color:#dc2626;font-weight:700">Eliminar permanentemente</option>
        </select>

        <div id="bulkExtraEstado" style="display:none">
          <select name="bulk_estado" class="form-control" style="width:auto;font-size:.75rem;color:var(--navy)">
            <option value="pendiente">Pendiente</option>
            <option value="jugado">Jugado</option>
            <option value="reprogramado">Reprogramado</option>
            <option value="walkover">Walkover</option>
            <option value="no_presentado">No presentado</option>
          </select>
        </div>
        <div id="bulkExtraAlerta" style="display:none">
          <input type="text" name="bulk_alerta" class="form-control" placeholder="Texto de la alerta..." style="width:250px;font-size:.75rem;color:var(--navy)">
        </div>
        <div id="bulkExtraFechaProg" style="display:none;align-items:center;gap:.75rem;flex-wrap:wrap">
          <input type="datetime-local" name="bulk_fecha_programada" id="bulkFechaProgInput" class="form-control" style="width:auto;font-size:.75rem;color:var(--navy)">
          <label style="display:flex;align-items:center;gap:.35rem;font-size:.75rem;color:#fff;font-weight:600;cursor:pointer;white-space:nowrap">
            <input type="checkbox" name="bulk_sin_fecha" id="bulkSinFecha" value="1" style="width:16px;height:16px;accent-color:#ef4444"
                   onchange="document.getElementById('bulkFechaProgInput').disabled = this.checked">
            Sin fecha (limpiar)
          </label>
        </div>
        <div id="bulkExtraRecinto" style="display:none">
          <select name="bulk_recinto_id" class="form-control" style="width:auto;font-size:.75rem;color:var(--navy)">
            <option value="">— Sin recinto —</option>
            <?php foreach ($todos_recintos as $rc): ?>
              <option value="<?= $rc['id'] ?>"><?= epl_h($rc['label']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div id="bulkExtraFecha" style="display:none;gap:.5rem;align-items:center">
          <input type="text" name="bulk_nombre_fecha" class="form-control" placeholder="Nombre Fecha" style="width:130px;font-size:.75rem;color:var(--navy)">
          <input type="number" name="bulk_jornada" class="form-control" placeholder="Jornada N°" style="width:90px;font-size:.75rem;color:var(--navy)">
        </div>

        <button type="submit" class="btn btn-gold btn-sm" data-confirm="¿Aplicar acción a los partidos seleccionados?" data-confirm-danger="false" data-confirm-ok="Aplicar">Aplicar</button>
        <button type="button" class="btn btn-sm" style="background:none;color:rgba(255,255,255,.6)" onclick="deselectAllPartidos()">Cancelar</button>
      </form>
    </div>

    <!-- ── TABLA PARTIDOS ──────────────────────── -->
    <div class="card">
      <div style="overflow-x:auto">
        <table style="width:100%;border-collapse:collapse;font-size:.83rem">
          <thead>
            <tr style="background:var(--navy);color:#fff">
              <th style="padding:.65rem .75rem;width:40px;text-align:center"><input type="checkbox" id="checkAllPartidos"></th>
              <th style="padding:.65rem .75rem;text-align:left;font-size:.7rem;text-transform:uppercase">Fecha / Estado</th>
              <th style="padding:.65rem 1rem;text-align:left;font-size:.7rem;text-transform:uppercase" class="hide-mobile">Liga</th>
              <th style="padding:.65rem 1rem;text-align:left;font-size:.7rem;text-transform:uppercase">Local</th>
              <th style="padding:.65rem .75rem;text-align:center;font-size:.7rem;text-transform:uppercase">Resultado</th>
              <th style="padding:.65rem 1rem;text-align:right;font-size:.7rem;text-transform:uppercase">Visitante</th>
              <th style="padding:.65rem .75rem;text-align:center;font-size:.7rem;text-transform:uppercase" class="hide-mobile">Cancha</th>
              <th style="padding:.65rem .75rem;text-align:center;font-size:.7rem;text-transform:uppercase">Editar</th>
            </tr>
          </thead>
          <tbody>
            <?php
            $current_grouping = null;
            foreach ($partidos as $p):
              $grouping = ($p['liga_nombre'] ?? '') . '|' . ($p['nombre_fecha'] ?? '');
              if ($grouping !== $current_grouping):
                $current_grouping = $grouping;
            ?>
            <tr style="background:#f1f5f9">
              <td style="padding:.4rem .75rem;text-align:center">
                <input type="checkbox" class="check-group" data-group="<?= epl_h($grouping) ?>" title="Seleccionar todo este grupo">
              </td>
              <td colspan="7" style="padding:.4rem 1rem;font-size:.72rem;font-weight:800;text-transform:uppercase;letter-spacing:.06em;color:var(--navy)">
                <?php if (!$liga_id): ?>
                  <span style="color:#64748b">🏆 <?= epl_h($p['liga_nombre']) ?> · </span>
                <?php endif; ?>
                📅 <?= epl_h($p['nombre_fecha'] ?? 'Sin fecha asignada') ?>
                <?php if ($p['jornada']): ?><span style="font-weight:400;color:var(--gray-400);margin-left:.5rem">— Jornada <?= $p['jornada'] ?></span><?php endif; ?>
              </td>
            </tr>
            <?php endif; ?>
            <tr id="p<?= $p['id'] ?>" style="border-bottom:1px solid var(--gray-100)">
              <td style="padding:.6rem .75rem;text-align:center">
                <input type="checkbox" name="partido_ids[]" value="<?= $p['id'] ?>" class="partido-check" data-group="<?= epl_h($grouping) ?>" form="bulkForm">
              </td>
              <td style="padding:.6rem .75rem;font-size:.75rem">
                <?php
                  $_fp = $p['fecha_programada'];
                  $_es_placeholder = $_fp && date('Y-m-d', strtotime($_fp)) === '2026-12-31';
                  if (!$_fp || $_es_placeholder):
                ?>
                  <span style="background:#fee2e2;color:#dc2626;padding:.1rem .4rem;border-radius:4px;font-size:.68rem;font-weight:700">Sin fecha</span>
                <?php else: ?>
                  <span style="color:var(--gray-700);font-weight:600"><?= date('d/m H:i', strtotime($_fp)) ?></span>
                <?php endif; ?>
                <?php
                  $_bc = match($p['estado']) {
                      'jugado' => 'badge-jugado',
                      'walkover','no_presentado' => 'badge-walkover',
                      'reprogramado' => 'badge-reprog',
                      default => 'badge-pendiente',
                  };
                  $_lbl = match($p['estado']) {
                      'jugado' => 'Jugado', 'walkover' => 'W/O', 'no_presentado' => 'No pres.',
                      'reprogramado' => 'Reprog.', default => 'Pendiente',
                  };
                ?>
                <div style="margin-top:.25rem"><span class="badge <?= $_bc ?>" style="font-size:.6rem;padding:.15rem .4rem"><?= $_lbl ?></span></div>
                <?php if ($p['alerta_admin']): ?>
                  <div style="margin-top:.2rem"><span class="badge badge-walkover" style="font-size:.6rem;padding:.1rem .3rem"><?= epl_h($p['alerta_admin']) ?></span></div>
                <?php endif; ?>
              </td>
              <td class="hide-mobile" style="padding:.6rem 1rem;font-size:.75rem;color:var(--gray-600)"><?= epl_h($p['liga_nombre']) ?></td>
              <td style="padding:.6rem 1rem;font-weight:600;color:var(--navy)"><?= epl_h($p['local_nombre']) ?></td>
              <td style="padding:.6rem .75rem;text-align:center">
                <?php if ($p['estado'] === 'jugado'): ?>
                  <span style="font-family:var(--font-head);font-size:1rem;font-weight:700"><?= $p['sets_local'] ?>–<?= $p['sets_visitante'] ?></span>
                  <?php $ss = []; for ($s=1;$s<=3;$s++) { $gl=$p["games_s{$s}_local"]; $gv=$p["games_s{$s}_visitante"]; if($gl!==null) $ss[]="$gl-$gv"; } if($ss): ?>
                    <div style="font-size:.68rem;color:var(--gray-400)"><?= implode(' · ',$ss) ?></div>
                  <?php endif;
                elseif (in_array($p['estado'], ['walkover','no_presentado'])): ?>
                  <span style="font-size:.75rem;color:#dc2626;font-weight:700">W/O</span>
                <?php else: ?>
                  <span style="color:var(--gray-300);font-size:.9rem">vs</span>
                <?php endif; ?>
              </td>
              <td style="padding:.6rem 1rem;font-weight:600;color:var(--navy);text-align:right"><?= epl_h($p['visitante_nombre']) ?></td>
              <td class="hide-mobile" style="padding:.6rem .75rem;text-align:center;font-size:.78rem">
                <?php if ($p['recinto_nombre']): ?>
                  <?= epl_h($p['recinto_nombre']) ?>
                  <?php if ($p['recinto_superior_nombre']): ?>
                    <span style="display:block;font-size:.65rem;color:var(--gray-400)"><?= epl_h($p['recinto_superior_nombre']) ?></span>
                  <?php endif; ?>
                <?php else: ?>
                  <span style="color:var(--gray-300)">—</span>
                <?php endif; ?>
              </td>
              <td style="padding:.6rem .75rem;text-align:center">
                <button type="button" onclick="window.editarPartido(this)" data-partido="<?= epl_h(base64_encode((string)(json_encode($p, JSON_UNESCAPED_UNICODE) ?: '{}'))) ?>" class="btn btn-sm" style="border:1px solid var(--gray-200);font-size:.7rem;padding:.3rem .6rem">Editar</button>
              </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($partidos)): ?>
              <tr><td colspan="8" style="padding:3rem;text-align:center;color:var(--gray-400)">
                <div style="font-size:2rem">🎾</div>
                <p style="font-weight:600;margin-top:.5rem">No hay partidos con los filtros aplicados</p>
              </td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

  </main>
</div>

<!-- ════════ MODAL CREAR PARTIDO ════════ -->
<div id="modalCrearPartido" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:99999;align-items:center;justify-content:center;padding:1rem">
  <div class="card" style="width:100%;max-width:520px;max-height:92vh;overflow-y:auto">
    <div class="card-head" style="position:sticky;top:0;background:#fff;z-index:1">
      <h3 style="font-family:var(--font-head);font-size:1rem;text-transform:uppercase;color:var(--navy)">Nuevo partido</h3>
      <button onclick="document.getElementById('modalCrearPartido').style.display='none'" style="background:none;font-size:1.5rem;color:var(--gray-400)">×</button>
    </div>
    <div class="card-body">
      <form method="post">
        <input type="hidden" name="action" value="crear_partido">

        <div class="form-group">
          <label class="form-label">Liga / Torneo *</label>
          <select name="liga_id" class="form-control" required onchange="this.form.action='partidos.php?liga='+this.value;">
            <option value="">— Seleccioná una liga —</option>
            <?php foreach ($ligas as $l): ?>
              <option value="<?= $l['id'] ?>" <?= $l['id']==$liga_id?'selected':'' ?>><?= epl_h($l['nombre']) ?></option>
            <?php endforeach; ?>
          </select>
          <small style="color:#94a3b8;font-size:.7rem">Los equipos del select se filtran por la liga elegida (recargá si la cambiás)</small>
        </div>

        <div class="grid-2">
          <div class="form-group">
            <label class="form-label">Nombre de fecha *</label>
            <input type="text" name="nombre_fecha" class="form-control" placeholder="Fecha 1, Cuartos..." list="fechas-list-create">
            <datalist id="fechas-list-create">
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
            <option value="">— Seleccioná —</option>
            <?php foreach ($equipos_modal as $e): ?><option value="<?= $e['id'] ?>"><?= epl_h($e['nombre']) ?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Equipo visitante *</label>
          <select name="equipo_visitante_id" class="form-control" required>
            <option value="">— Seleccioná —</option>
            <?php foreach ($equipos_modal as $e): ?><option value="<?= $e['id'] ?>"><?= epl_h($e['nombre']) ?></option><?php endforeach; ?>
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

<!-- ════════ MODAL EDITAR PARTIDO ════════ -->
<div id="modalEditarPartido" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:99999;align-items:center;justify-content:center;padding:1rem">
  <div class="card" style="width:100%;max-width:520px;max-height:92vh;overflow-y:auto">
    <div class="card-head" style="position:sticky;top:0;background:#fff;z-index:1">
      <h3 id="editPartidoTitle" style="font-family:var(--font-head);font-size:1rem;text-transform:uppercase;color:var(--navy)">Editar partido</h3>
      <button onclick="document.getElementById('modalEditarPartido').style.display='none'" style="background:none;font-size:1.5rem;color:var(--gray-400)">×</button>
    </div>
    <div class="card-body">
      <form method="post">
        <input type="hidden" name="action" value="editar_resultado">
        <input type="hidden" name="partido_id" id="editPartidoId">
        <input type="hidden" name="_f_liga"     value="<?= epl_h((string)$liga_id) ?>">
        <input type="hidden" name="_f_fecha"    value="<?= epl_h($f_fecha) ?>">
        <input type="hidden" name="_f_estado_p" value="<?= epl_h($f_est) ?>">
        <input type="hidden" name="_f_search"   value="<?= epl_h($f_search) ?>">
        <input type="hidden" name="_f_desde"    value="<?= epl_h($f_desde) ?>">
        <input type="hidden" name="_f_hasta"    value="<?= epl_h($f_hasta) ?>">

        <div class="grid-2">
          <div class="form-group">
            <label class="form-label">Nombre de fecha</label>
            <input type="text" name="nombre_fecha" id="editNombreFecha" class="form-control" placeholder="Fecha 1...">
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
                <option value="<?= $rc['id'] ?>"><?= epl_h($rc['label']) ?></option>
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
        <?php for ($s = 1; $s <= 3; $s++): ?>
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
          <label class="form-label">Alerta / Nota admin (etiqueta roja)</label>
          <input type="text" name="alerta_admin" id="editAlertaAdmin" class="form-control" placeholder="Ej: Falta confirmar cancha...">
        </div>

        <div id="editContactosWrapper" style="margin-top:1.25rem;border-top:1px solid var(--gray-100);padding-top:1rem">
          <p style="font-size:.78rem;font-weight:700;text-transform:uppercase;color:var(--navy);margin-bottom:.5rem">Contacto jugadores</p>
          <div id="editContactosList" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:.6rem"></div>
        </div>

        <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;margin-top:1.25rem">Guardar cambios</button>
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
  function setVal(sel, v) { var el = modal.querySelector(sel); if (el) el.value = v; }
  modal.querySelector('input[name="partido_id"]').value = p.id;
  document.getElementById('editPartidoTitle').textContent = p.local_nombre + ' vs ' + p.visitante_nombre;
  setVal('input[name="nombre_fecha"]', p.nombre_fecha || '');
  setVal('input[name="jornada"]', p.jornada || '');
  setVal('select[name="recinto_id"]', p.recinto_id || '');
  setVal('select[name="estado"]', p.estado || 'pendiente');
  setVal('input[name="fecha_programada"]', p.fecha_programada ? String(p.fecha_programada).replace(' ', 'T').substring(0,16) : '');
  setVal('input[name="fecha_jugado"]', p.fecha_jugado ? String(p.fecha_jugado).replace(' ', 'T').substring(0,16) : '');
  [1,2,3].forEach(function(s){
    setVal('input[name="s'+s+'_l"]', p['games_s'+s+'_local'] != null ? p['games_s'+s+'_local'] : '');
    setVal('input[name="s'+s+'_v"]', p['games_s'+s+'_visitante'] != null ? p['games_s'+s+'_visitante'] : '');
  });
  setVal('input[name="alerta_admin"]', p.alerta_admin || '');

  // Contactos WhatsApp
  var contactList = document.getElementById('editContactosList');
  contactList.innerHTML = '';
  [
    { n: p.jl1_n, a: p.jl1_a, t: p.jl1_t, role: 'Local' },
    { n: p.jl2_n, a: p.jl2_a, t: p.jl2_t, role: 'Local' },
    { n: p.jv1_n, a: p.jv1_a, t: p.jv1_t, role: 'Visitante' },
    { n: p.jv2_n, a: p.jv2_a, t: p.jv2_t, role: 'Visitante' },
  ].forEach(function(pl){
    if (!pl.n) return;
    var div = document.createElement('div');
    div.style.cssText = 'background:#f8fafc;padding:.5rem;border-radius:8px;border:1px solid #f1f5f9';
    var tel = pl.t ? pl.t.replace(/\D/g, '') : '';
    var wspLink = '';
    if (tel) {
      var clean = tel.startsWith('56') ? tel : '56' + tel;
      var msg = encodeURIComponent('Hola ' + pl.n + ', te contacto de Elite Padel League por tu partido: ' + p.local_nombre + ' vs ' + p.visitante_nombre);
      wspLink = 'https://wa.me/' + clean + '?text=' + msg;
    }
    var html = '<div style="font-size:.65rem;font-weight:700;color:#94a3b8;text-transform:uppercase">' + pl.role + '</div>';
    html += '<div style="font-size:.78rem;font-weight:600;color:var(--navy);margin-bottom:.3rem">' + pl.n + ' ' + (pl.a || '') + '</div>';
    html += wspLink
      ? '<a href="' + wspLink + '" target="_blank" style="background:#25D366;color:#fff;padding:.3rem .5rem;border-radius:5px;font-size:.65rem;font-weight:700;text-decoration:none;display:block;text-align:center">WhatsApp</a>'
      : '<span style="font-size:.65rem;color:#94a3b8;font-style:italic">Sin teléfono</span>';
    div.innerHTML = html;
    contactList.appendChild(div);
  });

  modal.style.display = 'flex';
};

// ─── Bulk selection ───
function updateBulkBar() {
  var selected = document.querySelectorAll('.partido-check:checked');
  var bar = document.getElementById('bulkBar');
  document.getElementById('bulkCount').textContent = selected.length;
  bar.style.display = selected.length > 0 ? 'flex' : 'none';
}
document.querySelectorAll('.partido-check').forEach(function(c){ c.addEventListener('change', updateBulkBar); });
var checkAll = document.getElementById('checkAllPartidos');
if (checkAll) {
  checkAll.addEventListener('change', function(){
    document.querySelectorAll('.partido-check').forEach(function(c){ c.checked = checkAll.checked; });
    updateBulkBar();
  });
}
document.querySelectorAll('.check-group').forEach(function(g){
  g.addEventListener('change', function(){
    var name = this.getAttribute('data-group');
    document.querySelectorAll('.partido-check[data-group="' + name + '"]').forEach(function(c){ c.checked = g.checked; });
    updateBulkBar();
  });
});
function deselectAllPartidos() {
  document.querySelectorAll('.partido-check, .check-group').forEach(function(c){ c.checked = false; });
  if (checkAll) checkAll.checked = false;
  updateBulkBar();
}
function toggleBulkExtra() {
  var action = document.getElementById('bulkAction').value;
  document.getElementById('bulkExtraEstado').style.display     = action === 'cambiar_estado' ? 'block' : 'none';
  document.getElementById('bulkExtraRecinto').style.display    = action === 'cambiar_recinto' ? 'block' : 'none';
  document.getElementById('bulkExtraFecha').style.display      = action === 'cambiar_fecha' ? 'flex' : 'none';
  document.getElementById('bulkExtraFechaProg').style.display  = action === 'cambiar_fecha_programada' ? 'flex' : 'none';
  document.getElementById('bulkExtraAlerta').style.display     = action === 'poner_alerta' ? 'block' : 'none';
  if (action !== 'cambiar_fecha_programada') {
    document.getElementById('bulkSinFecha').checked = false;
    document.getElementById('bulkFechaProgInput').disabled = false;
  }
}
</script>

<?php require_once '../includes/footer.php'; ?>
