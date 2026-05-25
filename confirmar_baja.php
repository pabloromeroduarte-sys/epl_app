<?php
require_once __DIR__ . '/includes/functions.php';

$token = trim($_GET['t'] ?? $_POST['t'] ?? '');
if (!preg_match('/^[a-f0-9]{32}$/i', $token)) {
    http_response_code(404);
    $error_msg = 'Enlace inválido.';
    $partido = null;
} else {
    $db = epl_db();
    $st = $db->prepare("
        SELECT p.id, p.fecha_programada, p.fecha_original, p.estado, p.recinto_id,
               p.baja_confirmada_at, p.baja_confirmada_por,
               p.cancha_confirmada_at, p.cancha_confirmada_por,
               p.recinto_original_id,
               l.nombre  AS liga_nombre,
               el.nombre AS local_nombre,  ev.nombre AS visitante_nombre,
               r.nombre  AS recinto_nuevo,  ro.nombre AS recinto_original,
               el.jugador1_id AS jl1_id, el.jugador2_id AS jl2_id,
               ev.jugador1_id AS jv1_id, ev.jugador2_id AS jv2_id,
               sr.fecha_propuesta
        FROM partidos p
        JOIN ligas l    ON l.id = p.liga_id
        JOIN equipos el ON el.id = p.equipo_local_id
        JOIN equipos ev ON ev.id = p.equipo_visitante_id
        LEFT JOIN recintos r  ON r.id  = p.recinto_id
        LEFT JOIN recintos ro ON ro.id = p.recinto_original_id
        LEFT JOIN solicitudes_reprogramacion sr ON sr.id = (
            SELECT MAX(sr2.id) FROM solicitudes_reprogramacion sr2
            WHERE sr2.partido_id = p.id AND sr2.estado != 'rechazada'
        )
        WHERE p.baja_token = ?
        LIMIT 1
    ");
    $st->execute([$token]);
    $partido = $st->fetch(PDO::FETCH_ASSOC);
    $error_msg = $partido ? '' : 'No encontramos esta solicitud. Pedile al admin un link nuevo.';
}

// ── ¿Tiene nueva fecha y necesita cancha? ──
$nueva_fecha_lbl = null;
$necesita_cancha = false;
$canchas         = [];
$canchas_grupos  = []; // [['sede'=>nombre, 'sede_id'=>id, 'canchas'=>[...]]]
$raiz_id         = null;
$raiz_nombre     = null;

if ($partido) {
    // ── Lógica de pre-aprobado vs post-aprobado ──
    $es_post_aprobado = !empty($partido['fecha_original']);
    $fecha_baja_real = $es_post_aprobado ? $partido['fecha_original'] : $partido['fecha_programada'];
    $fecha_nueva_real = $es_post_aprobado ? $partido['fecha_programada'] : ($partido['fecha_propuesta'] ?? null);

    $_sf = !$fecha_nueva_real || date('Y-m-d', strtotime($fecha_nueva_real)) === '2026-12-31';
    $nueva_fecha_lbl = !$_sf ? date('d/m/Y H:i', strtotime($fecha_nueva_real)) : null;

    // Necesita cancha: hay nueva fecha y el club todavía no confirmó qué cancha asignan
    $necesita_cancha = $nueva_fecha_lbl && empty($partido['cancha_confirmada_at']);

    if ($necesita_cancha) {
        $ref = $partido['recinto_original_id'] ?: null;
        if ($ref) {
            $raiz_id = epl_recinto_raiz((int)$ref);
            $st2 = $db->prepare("SELECT nombre FROM recintos WHERE id=?");
            $st2->execute([$raiz_id]);
            $raiz_nombre = $st2->fetchColumn() ?: null;
            $canchas = epl_recintos_canchas_de_club($raiz_id);
        }
        // Fallback: todos los recintos hoja del sistema
        if (empty($canchas)) {
            $all = $db->query("SELECT r.id, r.nombre, r.superior_id FROM recintos r ORDER BY r.nombre")->fetchAll(PDO::FETCH_ASSOC);
            $ids_con_hijos = array_unique(array_filter(array_column($all, 'superior_id'), fn($v) => $v !== null));
            $canchas = array_values(array_filter($all, fn($r) => !in_array((int)$r['id'], array_map('intval', $ids_con_hijos))));
            if (empty($canchas)) $canchas = $all;
        }

        // ── Agrupar canchas por su recinto superior (sede) ──
        if (!empty($canchas)) {
            $sup_ids = array_unique(array_filter(array_map(fn($c) => (int)($c['superior_id'] ?? 0), $canchas)));
            $sup_names = [];
            if ($sup_ids) {
                $ph = implode(',', array_fill(0, count($sup_ids), '?'));
                $qs = $db->prepare("SELECT id, nombre FROM recintos WHERE id IN ($ph)");
                $qs->execute(array_values($sup_ids));
                foreach ($qs->fetchAll(PDO::FETCH_ASSOC) as $r) $sup_names[(int)$r['id']] = $r['nombre'];
            }
            $grupos = [];
            foreach ($canchas as $c) {
                $sid = (int)($c['superior_id'] ?? 0);
                $nom = $sup_names[$sid] ?? ($raiz_nombre ?: 'Sin sede');
                if (!isset($grupos[$sid])) $grupos[$sid] = ['sede'=>$nom, 'sede_id'=>$sid, 'canchas'=>[], 'uso'=>0];
                $grupos[$sid]['canchas'][] = $c;
            }
            // Calcular uso histórico: cantidad de partidos jugados/programados por sede
            $cancha_ids = array_map(fn($c) => (int)$c['id'], $canchas);
            if ($cancha_ids) {
                $ph2 = implode(',', array_fill(0, count($cancha_ids), '?'));
                $qu = $db->prepare("
                    SELECT r.superior_id AS sede_id, COUNT(p.id) AS uso
                    FROM recintos r
                    LEFT JOIN partidos p ON p.recinto_id = r.id
                    WHERE r.id IN ($ph2)
                    GROUP BY r.superior_id
                ");
                $qu->execute($cancha_ids);
                foreach ($qu->fetchAll(PDO::FETCH_ASSOC) as $u) {
                    $sid = (int)$u['sede_id'];
                    if (isset($grupos[$sid])) $grupos[$sid]['uso'] = (int)$u['uso'];
                }
            }
            // Ordenar canchas dentro de cada sede por nombre natural
            foreach ($grupos as &$g) {
                usort($g['canchas'], fn($a,$b) => strnatcasecmp($a['nombre'], $b['nombre']));
            }
            unset($g);
            // Ordenar grupos por USO descendente (más usados primero), desempate alfabético
            usort($grupos, function($a, $b) {
                if ($a['uso'] !== $b['uso']) return $b['uso'] <=> $a['uso'];
                return strnatcasecmp($a['sede'], $b['sede']);
            });
            $canchas_grupos = $grupos;
        }
    }
}

// ── POST: club confirma baja (+ cancha opcional) ──
$confirmado   = false;
$cancha_nombre = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $partido && !$partido['baja_confirmada_at']) {
    $quien       = trim($_POST['quien'] ?? '');
    $recinto_nuevo = (int)($_POST['recinto_id'] ?? 0);

    $db->prepare("UPDATE partidos SET baja_confirmada_at=NOW(), baja_confirmada_por=? WHERE id=?")
       ->execute([$quien ?: 'Club', $partido['id']]);

    // Si eligió cancha y el partido aún no la tiene asignada
    if ($recinto_nuevo && $necesita_cancha) {
        $ids_validos = array_column($canchas, 'id');
        if (in_array($recinto_nuevo, array_map('intval', $ids_validos))) {
            $st3 = $db->prepare("SELECT nombre FROM recintos WHERE id=?");
            $st3->execute([$recinto_nuevo]);
            $cancha_nombre = $st3->fetchColumn();
            $db->prepare("UPDATE partidos SET recinto_id=?, cancha_confirmada_at=NOW(), cancha_confirmada_por=? WHERE id=?")
               ->execute([$recinto_nuevo, $quien ?: 'Club', $partido['id']]);
        }
    }

    $pid = (int)$partido['id'];
    $local  = $partido['local_nombre'];
    $visita = $partido['visitante_nombre'];
    $url_admin = epl_url('admin/partido_detalle.php?id=' . $pid);

    // Notificar admins: baja confirmada
    try {
        $msg_adm = "El club confirmó la baja de la reserva del partido $local vs $visita.";
        if ($quien) $msg_adm .= " Confirmó: $quien.";
        if ($cancha_nombre) $msg_adm .= " Cancha asignada para la nueva fecha: $cancha_nombre.";
        foreach (epl_admins_ids() as $aid) {
            epl_notif_crear((int)$aid, 'admin', '✅ Baja confirmada' . ($cancha_nombre ? ' + cancha asignada' : ''), $msg_adm, $url_admin, true);
        }
    } catch (Throwable $e) {}

    // Notificar jugadores: cancha nueva asignada
    if ($cancha_nombre) {
        try {
            $fecha_txt = $nueva_fecha_lbl ?? '';
            $tit  = '🎾 Cancha confirmada';
            $msg_j = "El club asignó la cancha «$cancha_nombre» para tu partido $local vs $visita" . ($fecha_txt ? " ($fecha_txt)" : '') . ".";
            foreach (array_unique(array_filter([
                (int)$partido['jl1_id'], (int)$partido['jl2_id'],
                (int)$partido['jv1_id'], (int)$partido['jv2_id'],
            ])) as $jid) {
                if ($jid) epl_notif_crear($jid, 'partido', $tit, $msg_j, epl_url('dashboard.php'), true);
            }
        } catch (Throwable $e) {}
    }

    $confirmado = true;
}

// ── Fecha original (reserva a dar de baja) ──
$baja_fecha_lbl   = $partido && $fecha_baja_real && date('Y-m-d', strtotime($fecha_baja_real)) !== '2026-12-31'
    ? date('d/m/Y H:i', strtotime($fecha_baja_real)) : null;
$baja_recinto_nom = $es_post_aprobado ? ($partido['recinto_original'] ?? null) : ($partido['recinto_nuevo'] ?? null);

// Calcular si "ya todo confirmado" (baja + cancha si aplica)
$todo_confirmado = $partido
    && !empty($partido['baja_confirmada_at'])
    && (!$necesita_cancha || !empty($partido['cancha_confirmada_at']));
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Gestionar partido — Elite Padel League</title>
  <meta name="robots" content="noindex,nofollow">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Anton&family=Montserrat:wght@400;600;700;800;900&display=swap" rel="stylesheet">
  <style>
    *,*::before,*::after { box-sizing:border-box; margin:0; padding:0; }
    body { font-family:'Montserrat',sans-serif; background:linear-gradient(135deg,#1c2f48 0%,#0f1e30 100%); min-height:100vh; padding:1rem; display:flex; align-items:flex-start; justify-content:center; }
    .card { background:#fff; max-width:520px; width:100%; border-radius:18px; overflow:hidden; box-shadow:0 20px 60px rgba(0,0,0,.3); margin:1rem 0; }
    .hdr  { background:linear-gradient(135deg,#1c2f48,#0f1e30); padding:1.5rem; color:#fff; text-align:center; }
    .hdr h1 { font-family:'Anton',sans-serif; font-size:1.5rem; text-transform:uppercase; }
    .hdr .gold { color:#C9A762; }
    .body { padding:1.5rem; }

    /* Info boxes */
    .info { background:#f8fafc; border-radius:10px; padding:.85rem 1rem; margin-bottom:1rem; }
    .info-row { display:flex; justify-content:space-between; align-items:flex-start; padding:.35rem 0; border-bottom:1px solid #f1f5f9; gap:.5rem; }
    .info-row:last-child { border-bottom:none; }
    .info-row .lbl { color:#64748b; font-weight:600; font-size:.75rem; white-space:nowrap; padding-top:.1rem; }
    .info-row .val { color:#1c2f48; font-weight:700; font-size:.85rem; text-align:right; line-height:1.3; }

    /* Sección visual */
    .section-box { border-radius:12px; padding:1rem; margin-bottom:1rem; }
    .section-box.baja    { background:#fff5f5; border:1px solid #fecaca; }
    .section-box.cancha  { background:#f0f9ff; border:1px solid #bfdbfe; }
    .section-head { font-size:.68rem; font-weight:900; text-transform:uppercase; letter-spacing:.1em; margin-bottom:.6rem; }
    .section-head.baja   { color:#991b1b; }
    .section-head.cancha { color:#1e40af; }

    /* Grupos por sede */
    .sede-group { margin:.85rem 0 .4rem; padding:.6rem .7rem .55rem; background:rgba(255,255,255,.55); border:1px solid #dbeafe; border-radius:12px; }
    .sede-titulo { display:flex; align-items:center; gap:.45rem; margin-bottom:.5rem; padding-bottom:.4rem; border-bottom:1px dashed #cfe1ff; }
    .sede-ico { font-size:1rem; }
    .sede-nom { font-size:.85rem; font-weight:800; color:#1e3a8a; flex:1; line-height:1.1; }
    .sede-count { font-size:.65rem; color:#1e40af; background:#dbeafe; padding:.15rem .5rem; border-radius:999px; font-weight:700; text-transform:uppercase; letter-spacing:.05em; }

    /* Canchas grid */
    .canchas-grid { display:grid; grid-template-columns:repeat(auto-fill, minmax(130px,1fr)); gap:.55rem; margin:.4rem 0 .1rem; }
    .cancha-btn { position:relative; }
    .cancha-btn input[type=radio] { position:absolute; opacity:0; width:0; height:0; }
    .cancha-btn label {
      display:flex; flex-direction:column; align-items:center; justify-content:center;
      gap:.25rem; padding:.8rem .5rem; border-radius:10px; cursor:pointer;
      border:2px solid #e2e8f0; background:#fff; text-align:center;
      transition:border-color .12s, background .12s; min-height:68px;
    }
    .cancha-btn label .ico { font-size:1.3rem; }
    .cancha-btn label .nom { font-size:.75rem; font-weight:800; color:#1c2f48; line-height:1.2; }
    .cancha-btn input:checked + label { border-color:#2563eb; background:#eff6ff; box-shadow:0 0 0 3px rgba(37,99,235,.18); }
    .cancha-btn label:hover { border-color:#93c5fd; background:#f0f9ff; }

    /* Alerts */
    .alert { padding:.9rem 1rem; border-radius:10px; margin-bottom:1rem; font-size:.88rem; }
    .alert-ok   { background:#dcfce7; color:#15803d; border:1px solid #86efac; }
    .alert-warn { background:#fee2e2; color:#991b1b; border:1px solid #fca5a5; }

    /* Form */
    label.form-lbl { display:block; font-size:.72rem; color:#1c2f48; font-weight:800; text-transform:uppercase; letter-spacing:.05em; margin-bottom:.3rem; margin-top:.8rem; }
    input[type=text] { width:100%; padding:.75rem 1rem; border:1.5px solid #e2e8f0; border-radius:10px; font-size:.9rem; font-family:inherit; }
    input[type=text]:focus { outline:none; border-color:#C9A762; }
    .btn-submit { display:block; width:100%; padding:1rem; border-radius:12px; border:none; font-family:inherit; font-size:.88rem; font-weight:900; text-transform:uppercase; letter-spacing:.06em; cursor:pointer; background:#C9A762; color:#1c2f48; margin-top:1rem; transition:background .15s; }
    .btn-submit:hover { background:#1c2f48; color:#C9A762; }
    .check-big { font-size:3.5rem; text-align:center; margin:.75rem 0; }

    /* Chips de estado */
    .chip { display:inline-block; padding:.25rem .7rem; border-radius:999px; font-size:.7rem; font-weight:800; text-transform:uppercase; }
    .chip-ok     { background:#dcfce7; color:#15803d; }
    .chip-warn   { background:#fef9c3; color:#854d0e; }
    .chip-info   { background:#dbeafe; color:#1e40af; }
  </style>
</head>
<body>
<div class="card">
  <div class="hdr">
    <h1>Elite <span class="gold">Padel</span> League</h1>
    <p style="color:#C9A762;font-size:.68rem;font-weight:700;letter-spacing:.15em;text-transform:uppercase;margin-top:.4rem">
      <?= $necesita_cancha ? 'Confirmar baja + asignar cancha' : 'Confirmar baja de cancha' ?>
    </p>
  </div>
  <div class="body">

  <?php if ($error_msg): ?>
    <div class="alert alert-warn"><?= epl_h($error_msg) ?></div>

  <?php elseif ($confirmado): ?>
    <!-- ── Pantalla de éxito ── -->
    <div class="check-big">🎾</div>
    <?php if ($cancha_nombre): ?>
      <div class="alert alert-ok">
        <strong>¡Todo listo!</strong><br>
        Baja confirmada y cancha asignada: <strong><?= epl_h($cancha_nombre) ?></strong>.<br>
        Los jugadores y el admin de EPL ya fueron notificados.
      </div>
    <?php else: ?>
      <div class="alert alert-ok">
        <strong>¡Gracias!</strong> Confirmaste la baja correctamente. El admin de Elite Padel League fue notificado.
      </div>
    <?php endif; ?>

  <?php elseif ($todo_confirmado && !$confirmado): ?>
    <!-- ── Ya estaba todo confirmado previamente ── -->
    <div class="check-big">✅</div>
    <div class="alert alert-ok">
      <strong>Ya está todo confirmado.</strong><br>
      Baja: <?= date('d/m/Y H:i', strtotime($partido['baja_confirmada_at'])) ?>
      <?php if ($partido['baja_confirmada_por']): ?> · por <strong><?= epl_h($partido['baja_confirmada_por']) ?></strong><?php endif; ?>
      <?php if (!empty($partido['cancha_confirmada_at'])): ?>
        <br>Cancha: <strong><?= epl_h($partido['recinto_nuevo'] ?? '—') ?></strong>
        · <?= date('d/m/Y H:i', strtotime($partido['cancha_confirmada_at'])) ?>
      <?php endif; ?>
    </div>

  <?php else: ?>
    <!-- ── Formulario principal ── -->

    <!-- Info del partido -->
    <div class="info">
      <div class="info-row">
        <span class="lbl">Partido</span>
        <span class="val"><?= epl_h($partido['local_nombre']) ?> vs <?= epl_h($partido['visitante_nombre']) ?></span>
      </div>
      <div class="info-row">
        <span class="lbl">Liga</span>
        <span class="val"><?= epl_h($partido['liga_nombre']) ?></span>
      </div>
    </div>

    <form method="post" id="frmGestionar">
      <input type="hidden" name="t" value="<?= epl_h($token) ?>">

      <!-- Sección 1: FECHA ORIGINAL (dar de baja) -->
      <?php if ($baja_fecha_lbl || $baja_recinto_nom): ?>
      <div class="section-box baja">
        <div class="section-head baja">🚫 Fecha original — DAR DE BAJA</div>
        <?php if ($baja_fecha_lbl): ?>
          <div style="font-size:1.05rem;font-weight:900;color:#991b1b"><?= $baja_fecha_lbl ?></div>
        <?php endif; ?>
        <?php if ($baja_recinto_nom): ?>
          <div style="font-size:.88rem;color:#b91c1c;font-weight:700;margin-top:.2rem">🎾 <?= epl_h($baja_recinto_nom) ?></div>
        <?php endif; ?>
        <div style="font-size:.78rem;color:#7f1d1d;margin-top:.45rem">
          Esta es la reserva que necesitamos cancelar.
        </div>
        <?php if (!empty($partido['baja_confirmada_at'])): ?>
          <span class="chip chip-ok" style="margin-top:.5rem;display:inline-block">✅ Baja ya confirmada</span>
        <?php endif; ?>
      </div>
      <?php endif; ?>

      <!-- Sección 2: FECHA PROPUESTA + ASIGNAR CANCHA -->
      <?php if ($nueva_fecha_lbl): ?>
      <div class="section-box cancha">
        <div class="section-head cancha">✅ Fecha propuesta — NUEVA FECHA DEL PARTIDO</div>
        <div style="font-size:1.05rem;font-weight:900;color:#1e3a8a"><?= $nueva_fecha_lbl ?></div>

        <?php if ($necesita_cancha && empty($partido['cancha_confirmada_at'])): ?>
          <div style="font-size:.78rem;color:#1e40af;margin-top:.4rem;margin-bottom:.5rem">
            ¿Qué cancha asignan para esta fecha?
            <?php if ($raiz_nombre): ?>
              <span style="color:#93c5fd;font-size:.7rem">(<?= epl_h($raiz_nombre) ?>)</span>
            <?php endif; ?>
          </div>
          <?php if (empty($canchas)): ?>
            <div style="background:#fee2e2;border-radius:8px;padding:.6rem .8rem;font-size:.78rem;color:#991b1b">
              ⚠️ No hay canchas registradas. Avisale al administrador.
            </div>
          <?php else: ?>
            <?php foreach ($canchas_grupos as $grupo): ?>
              <div class="sede-group">
                <div class="sede-titulo">
                  <span class="sede-ico">📍</span>
                  <span class="sede-nom"><?= epl_h($grupo['sede']) ?></span>
                  <span class="sede-count"><?= count($grupo['canchas']) ?> cancha<?= count($grupo['canchas'])>1?'s':'' ?></span>
                </div>
                <div class="canchas-grid">
                  <?php foreach ($grupo['canchas'] as $c): ?>
                    <div class="cancha-btn">
                      <input type="radio" name="recinto_id" id="rc<?= $c['id'] ?>" value="<?= $c['id'] ?>">
                      <label for="rc<?= $c['id'] ?>">
                        <span class="ico">🎾</span>
                        <span class="nom"><?= epl_h($c['nombre']) ?></span>
                      </label>
                    </div>
                  <?php endforeach; ?>
                </div>
              </div>
            <?php endforeach; ?>
            <p style="font-size:.68rem;color:#64748b;margin-top:.4rem">
              Si aún no lo saben, podés confirmar solo la baja y responder después con la cancha.
            </p>
          <?php endif; ?>
        <?php elseif (!empty($partido['cancha_confirmada_at'])): ?>
          <div style="margin-top:.5rem">
            <span class="chip chip-ok">✅ <?= epl_h($partido['recinto_nuevo'] ?? 'Cancha confirmada') ?></span>
          </div>
        <?php else: ?>
          <?php if ($partido['recinto_nuevo']): ?>
            <div style="font-size:.85rem;font-weight:700;color:#15803d;margin-top:.4rem">🎾 <?= epl_h($partido['recinto_nuevo']) ?></div>
          <?php endif; ?>
        <?php endif; ?>
      </div>
      <?php endif; ?>

      <!-- Campo nombre + botón (solo si la baja no está confirmada) -->
      <?php if (empty($partido['baja_confirmada_at'])): ?>
        <label class="form-lbl">Tu nombre (opcional)</label>
        <input type="text" name="quien" placeholder="Ej: Hugo, encargado" maxlength="100">

        <button type="submit" class="btn-submit">
          <?php if ($necesita_cancha): ?>
            ✅ Confirmar baja<?php if (!empty($canchas)): ?> y asignar cancha<?php endif; ?>
          <?php else: ?>
            ✅ Confirmar baja
          <?php endif; ?>
        </button>
      <?php endif; ?>

    </form>

    <p style="text-align:center;font-size:.7rem;color:#94a3b8;margin-top:1rem;line-height:1.4">
      Al confirmar, el admin de EPL queda notificado automáticamente.
    </p>
  <?php endif; ?>

  </div>
</div>
</body>
</html>
