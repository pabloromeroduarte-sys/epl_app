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
        SELECT p.id, p.fecha_programada, p.estado,
               p.cancha_confirmada_at, p.cancha_confirmada_por, p.recinto_id,
               l.nombre AS liga_nombre,
               el.nombre AS local_nombre, ev.nombre AS visitante_nombre,
               r.nombre  AS recinto_actual,
               ro.nombre AS recinto_original_nombre,
               p.recinto_original_id,
               el.jugador1_id AS jl1_id, el.jugador2_id AS jl2_id,
               ev.jugador1_id AS jv1_id, ev.jugador2_id AS jv2_id
        FROM partidos p
        JOIN ligas l    ON l.id = p.liga_id
        JOIN equipos el ON el.id = p.equipo_local_id
        JOIN equipos ev ON ev.id = p.equipo_visitante_id
        LEFT JOIN recintos r  ON r.id  = p.recinto_id
        LEFT JOIN recintos ro ON ro.id = p.recinto_original_id
        WHERE p.cancha_token = ?
        LIMIT 1
    ");
    $st->execute([$token]);
    $partido = $st->fetch(PDO::FETCH_ASSOC);
    $error_msg = $partido ? '' : 'No encontramos esta solicitud. Pedile al admin un link nuevo.';
}

// Cargar recintos disponibles (canchas del mismo club)
$canchas = [];
$canchas_grupos = [];
$raiz_id = null;
$raiz_nombre = null;
if ($partido) {
    $ref_recinto = $partido['recinto_original_id'] ?: $partido['recinto_id'];
    if ($ref_recinto) {
        $raiz_id = epl_recinto_raiz((int)$ref_recinto);
        $st2 = $db->prepare("SELECT nombre FROM recintos WHERE id=?");
        $st2->execute([$raiz_id]);
        $raiz_nombre = $st2->fetchColumn() ?: null;
        $canchas = epl_recintos_canchas_de_club($raiz_id);
    }
    // Si no hay recinto de referencia, mostrar todos los recintos hoja del sistema
    if (empty($canchas)) {
        $all = $db->query("SELECT r.id, r.nombre, r.superior_id FROM recintos r ORDER BY r.nombre")->fetchAll(PDO::FETCH_ASSOC);
        $ids_con_hijos = array_unique(array_column(array_filter($all, fn($r) => $r['superior_id'] !== null), 'superior_id'));
        $canchas = array_values(array_filter($all, fn($r) => !in_array((int)$r['id'], array_map('intval', $ids_con_hijos))));
        if (empty($canchas)) $canchas = $all; // fallback total
    }
    // Agrupar canchas por sede (recinto superior)
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
            if (!isset($grupos[$sid])) $grupos[$sid] = ['sede'=>$nom, 'canchas'=>[], 'uso'=>0];
            $grupos[$sid]['canchas'][] = $c;
        }
        // Uso histórico por sede
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
        foreach ($grupos as &$g) {
            usort($g['canchas'], fn($a,$b) => strnatcasecmp($a['nombre'], $b['nombre']));
        }
        unset($g);
        // Más usadas primero, desempate alfabético
        usort($grupos, function($a, $b) {
            if ($a['uso'] !== $b['uso']) return $b['uso'] <=> $a['uso'];
            return strnatcasecmp($a['sede'], $b['sede']);
        });
        $canchas_grupos = $grupos;
    }
}

// ── POST: club confirma una cancha ──
$confirmado = false;
$cancha_elegida = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $partido && !$partido['cancha_confirmada_at']) {
    $recinto_id_nuevo = (int)($_POST['recinto_id'] ?? 0);
    $quien = trim($_POST['quien'] ?? '');

    // Validar que el recinto existe y es de los disponibles
    $ids_validos = array_column($canchas, 'id');
    if ($recinto_id_nuevo && in_array($recinto_id_nuevo, array_map('intval', $ids_validos))) {
        $st3 = $db->prepare("SELECT nombre FROM recintos WHERE id=?");
        $st3->execute([$recinto_id_nuevo]);
        $cancha_elegida = $st3->fetchColumn();

        // Actualizar partido
        $db->prepare("UPDATE partidos SET recinto_id=?, cancha_confirmada_at=NOW(), cancha_confirmada_por=? WHERE id=?")
           ->execute([$recinto_id_nuevo, $quien ?: 'Club', $partido['id']]);

        $partido_id = (int)$partido['id'];
        $fecha_lbl  = date('d/m/Y H:i', strtotime($partido['fecha_programada']));
        $titulo_notif = '🎾 Cancha confirmada por el club';
        $msg_notif    = "El club asignó la cancha «{$cancha_elegida}» para el partido {$partido['local_nombre']} vs {$partido['visitante_nombre']} ({$fecha_lbl}).";
        $url_notif    = epl_url('admin/partido_detalle.php?id=' . $partido_id);

        // Notificar a los 4 jugadores del partido
        try {
            $jugadores = array_filter([
                (int)$partido['jl1_id'], (int)$partido['jl2_id'],
                (int)$partido['jv1_id'], (int)$partido['jv2_id'],
            ]);
            foreach (array_unique($jugadores) as $jid) {
                if ($jid) epl_notif_crear($jid, 'partido', $titulo_notif, $msg_notif, epl_url('dashboard.php'), true);
            }
        } catch (Throwable $e) {}

        // Notificar a los admins
        try {
            $msg_admin = "El club confirmó la cancha «{$cancha_elegida}» para {$partido['local_nombre']} vs {$partido['visitante_nombre']} ({$fecha_lbl}).";
            if ($quien) $msg_admin .= " Confirmó: $quien.";
            foreach (epl_admins_ids() as $admin_id) {
                epl_notif_crear((int)$admin_id, 'admin', $titulo_notif, $msg_admin, $url_notif, true);
            }
        } catch (Throwable $e) {}

        $confirmado = true;
    } else {
        $error_msg = 'Cancha no válida. Seleccioná una opción de la lista.';
    }
}

$fecha_lbl = ($partido && $partido['fecha_programada'])
    ? date('d/m/Y H:i', strtotime($partido['fecha_programada']))
    : null;
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Confirmar cancha — Elite Padel League</title>
  <meta name="robots" content="noindex,nofollow">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Anton&family=Montserrat:wght@400;600;700;800;900&display=swap" rel="stylesheet">
  <style>
    *,*::before,*::after { box-sizing:border-box; margin:0; padding:0; }
    body { font-family:'Montserrat',sans-serif; background:linear-gradient(135deg,#1c2f48 0%,#0f1e30 100%); min-height:100vh; padding:1rem; display:flex; align-items:center; justify-content:center; }
    .card { background:#fff; max-width:520px; width:100%; border-radius:18px; overflow:hidden; box-shadow:0 20px 60px rgba(0,0,0,.3); }
    .hdr { background:linear-gradient(135deg,#1c2f48,#0f1e30); padding:1.5rem; color:#fff; text-align:center; }
    .hdr h1 { font-family:'Anton',sans-serif; font-size:1.5rem; text-transform:uppercase; }
    .hdr .gold { color:#C9A762; }
    .body { padding:1.5rem; }
    .info { background:#f8fafc; border-radius:10px; padding:1rem; margin-bottom:1.2rem; font-size:.88rem; }
    .info-row { display:flex; justify-content:space-between; align-items:center; padding:.4rem 0; border-bottom:1px solid #f1f5f9; gap:.5rem; }
    .info-row:last-child { border-bottom:none; }
    .info-row span:first-child { color:#64748b; font-weight:600; font-size:.78rem; white-space:nowrap; }
    .info-row span:last-child { color:#1c2f48; font-weight:700; font-size:.85rem; text-align:right; }
    .alert { padding:1rem; border-radius:10px; margin-bottom:1rem; font-size:.88rem; }
    .alert-ok   { background:#dcfce7; color:#15803d; border:1px solid #86efac; }
    .alert-warn { background:#fee2e2; color:#991b1b; border:1px solid #fca5a5; }
    label { display:block; font-size:.78rem; color:#1c2f48; font-weight:800; text-transform:uppercase; letter-spacing:.05em; margin-bottom:.5rem; }
    input[type=text] { width:100%; padding:.8rem 1rem; border:1.5px solid #e2e8f0; border-radius:10px; font-size:.95rem; font-family:inherit; margin-bottom:1rem; }
    input[type=text]:focus { outline:none; border-color:#C9A762; }

    /* Grupos por sede */
    .sede-group { margin:.85rem 0 .4rem; padding:.65rem .75rem .6rem; background:#fafafa; border:1px solid #fef9ec; border-radius:12px; }
    .sede-titulo { display:flex; align-items:center; gap:.45rem; margin-bottom:.55rem; padding-bottom:.45rem; border-bottom:1px dashed #e6dcc4; }
    .sede-ico { font-size:1rem; }
    .sede-nom { font-size:.85rem; font-weight:800; color:#1c2f48; flex:1; line-height:1.1; }
    .sede-count { font-size:.65rem; color:#92400e; background:#fef9ec; padding:.15rem .5rem; border-radius:999px; font-weight:700; text-transform:uppercase; letter-spacing:.05em; border:1px solid #fde68a; }

    /* Grid de canchas */
    .canchas-grid { display:grid; grid-template-columns:repeat(auto-fill, minmax(140px, 1fr)); gap:.65rem; margin-bottom:.5rem; }
    .cancha-btn { position:relative; }
    .cancha-btn input[type=radio] { position:absolute; opacity:0; width:0; height:0; }
    .cancha-btn label {
      display:flex; flex-direction:column; align-items:center; justify-content:center;
      gap:.3rem; padding:.85rem .6rem; border-radius:12px; cursor:pointer;
      border:2px solid #e2e8f0; background:#f8fafc; text-align:center;
      transition:all .15s; min-height:72px;
    }
    .cancha-btn label .ico { font-size:1.5rem; }
    .cancha-btn label .nom { font-size:.78rem; font-weight:800; color:#1c2f48; line-height:1.2; }
    .cancha-btn input[type=radio]:checked + label {
      border-color:#C9A762; background:#fef9ec; box-shadow:0 0 0 3px rgba(201,167,98,.25);
    }
    .cancha-btn label:hover { border-color:#C9A762; background:#fef9ec; }

    .btn-submit { display:block; width:100%; padding:1rem; border-radius:12px; border:none; font-family:inherit; font-size:.9rem; font-weight:900; text-transform:uppercase; letter-spacing:.06em; cursor:pointer; background:#C9A762; color:#1c2f48; margin-top:.5rem; transition:background .15s; }
    .btn-submit:hover { background:#1c2f48; color:#C9A762; }
    .btn-submit:disabled { opacity:.5; cursor:not-allowed; }
    .check-big { font-size:4rem; text-align:center; margin:1rem 0; }
    .section-lbl { font-size:.7rem; font-weight:800; text-transform:uppercase; letter-spacing:.1em; color:#64748b; margin-bottom:.6rem; }
  </style>
</head>
<body>
<div class="card">
  <div class="hdr">
    <h1>Elite <span class="gold">Padel</span> League</h1>
    <p style="color:#C9A762;font-size:.7rem;font-weight:700;letter-spacing:.15em;text-transform:uppercase;margin-top:.4rem">Confirmar cancha asignada</p>
  </div>
  <div class="body">

  <?php if ($error_msg): ?>
    <div class="alert alert-warn"><?= epl_h($error_msg) ?></div>

  <?php elseif ($partido['cancha_confirmada_at'] && !$confirmado): ?>
    <div class="check-big">✅</div>
    <div class="alert alert-ok">
      <strong>¡La cancha ya fue confirmada!</strong><br>
      <?php
        // Mostrar nombre del recinto confirmado
        $stR = $db->prepare("SELECT nombre FROM recintos WHERE id=?");
        $stR->execute([$partido['recinto_id']]);
        $nom_conf = $stR->fetchColumn();
        if ($nom_conf) echo '<strong>' . epl_h($nom_conf) . '</strong><br>';
      ?>
      <?= date('d/m/Y H:i', strtotime($partido['cancha_confirmada_at'])) ?>
      <?php if ($partido['cancha_confirmada_por']): ?>
        · por <?= epl_h($partido['cancha_confirmada_por']) ?>
      <?php endif; ?>
    </div>

  <?php elseif ($confirmado): ?>
    <div class="check-big">🎾</div>
    <div class="alert alert-ok">
      <strong>¡Gracias!</strong> Quedó asignada la cancha <strong><?= epl_h($cancha_elegida) ?></strong>.<br>
      Los jugadores y el admin de Elite Padel League ya fueron notificados.
    </div>

  <?php else: ?>
    <p style="text-align:center;color:#64748b;font-size:.85rem;margin-bottom:1rem;line-height:1.5">
      Seleccioná qué cancha asignan para el siguiente partido:
    </p>

    <!-- Info del partido -->
    <div class="info">
      <div class="info-row">
        <span>Partido</span>
        <span><?= epl_h($partido['local_nombre']) ?> vs <?= epl_h($partido['visitante_nombre']) ?></span>
      </div>
      <div class="info-row">
        <span>Liga</span>
        <span><?= epl_h($partido['liga_nombre']) ?></span>
      </div>
      <?php if ($fecha_lbl): ?>
      <div class="info-row">
        <span>📅 Fecha</span>
        <span style="color:#1d4ed8;font-weight:800"><?= $fecha_lbl ?></span>
      </div>
      <?php endif; ?>
      <?php if ($raiz_nombre): ?>
      <div class="info-row">
        <span>Club</span>
        <span><?= epl_h($raiz_nombre) ?></span>
      </div>
      <?php endif; ?>
    </div>

    <form method="post" id="frmCancha">
      <input type="hidden" name="t" value="<?= epl_h($token) ?>">

      <div class="section-lbl">
        🎾 ¿Qué cancha asignan?
        <?php if ($raiz_nombre): ?>
          <span style="font-weight:500;text-transform:none;letter-spacing:0;color:#94a3b8;font-size:.68rem">(canchas de <?= epl_h($raiz_nombre) ?>)</span>
        <?php endif; ?>
      </div>

      <?php if (empty($canchas)): ?>
        <div class="alert alert-warn">⚠️ No encontramos canchas registradas para este club. Avisale al admin.</div>
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
                  <input type="radio" name="recinto_id" id="r<?= $c['id'] ?>" value="<?= $c['id'] ?>" required>
                  <label for="r<?= $c['id'] ?>">
                    <span class="ico">🎾</span>
                    <span class="nom"><?= epl_h($c['nombre']) ?></span>
                  </label>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>

      <label>Tu nombre (opcional)</label>
      <input type="text" name="quien" placeholder="Ej: Hugo, encargado" maxlength="100">

      <button type="submit" class="btn-submit" <?= empty($canchas) ? 'disabled' : '' ?>>
        ✅ Confirmar cancha
      </button>
    </form>

    <p style="text-align:center;font-size:.72rem;color:#94a3b8;margin-top:1rem;line-height:1.4">
      Al confirmar, los jugadores y el admin de EPL quedan notificados automáticamente.
    </p>
  <?php endif; ?>

  </div>
</div>

<script>
// Highlight visual al seleccionar radio
document.querySelectorAll('.cancha-btn input[type=radio]').forEach(function(radio) {
  radio.addEventListener('change', function() {
    document.querySelectorAll('.cancha-btn label').forEach(function(lbl) {
      lbl.style.borderColor = '';
      lbl.style.background = '';
    });
  });
});
</script>
</body>
</html>
