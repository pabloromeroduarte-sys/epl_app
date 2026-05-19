<?php
$page_title = 'Admin — Disputas de Resultados';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../includes/mail.php';
epl_require_admin();

epl_ensure_disputas_schema();

$db     = epl_db();
$_flash = epl_flash_get();
$ok     = ($_flash && $_flash['tipo']==='ok') ? $_flash['msg'] : '';
$err    = '';

// ── POST: resolver disputa ─────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action     = $_POST['action'] ?? '';
    $disputa_id = (int)($_POST['disputa_id'] ?? 0);
    $notas      = trim($_POST['notas_admin'] ?? '');

    if ($action === 'resolver' && $disputa_id > 0) {
        $stD = $db->prepare("SELECT d.*, p.equipo_local_id, p.equipo_visitante_id, p.liga_id,
                               el.nombre AS local_nombre, ev.nombre AS visitante_nombre,
                               j.nombre AS jugador_nombre, j.apellido AS jugador_apellido
                             FROM partido_disputas d
                             JOIN partidos p ON p.id = d.partido_id
                             JOIN equipos el ON el.id = p.equipo_local_id
                             JOIN equipos ev ON ev.id = p.equipo_visitante_id
                             JOIN jugadores j ON j.id = d.jugador_id
                             WHERE d.id = ? AND d.estado = 'pendiente'");
        $stD->execute([$disputa_id]);
        $disp = $stD->fetch();

        if ($disp) {
            $db->prepare("UPDATE partido_disputas SET estado='resuelta', notas_admin=? WHERE id=?")
               ->execute([$notas ?: null, $disputa_id]);

            // Notificar al jugador que reclamó
            $asunto_jugador = 'Tu reclamo fue revisado';
            $msg_jugador    = 'El administrador revisó tu reclamo sobre el partido '
                            . $disp['local_nombre'] . ' vs ' . $disp['visitante_nombre'] . '. '
                            . ($notas ? "Notas: {$notas}" : 'El resultado fue confirmado.');
            epl_notif_crear(
                (int)$disp['jugador_id'],
                'disputa',
                $asunto_jugador,
                $msg_jugador,
                epl_url('dashboard.php')
            );

            epl_redirect_ok("Disputa #{$disputa_id} marcada como resuelta.");
        } else {
            $err = 'Disputa no encontrada o ya resuelta.';
        }
    }
}

// ── Cargar disputas ────────────────────────────────────────────
$filtro = $_GET['filtro'] ?? 'pendiente';
$where  = $filtro === 'todas' ? '' : "WHERE d.estado = 'pendiente'";

$disputas = $db->query("
    SELECT d.*,
           p.sets_local, p.sets_visitante,
           p.games_s1_local, p.games_s1_visitante,
           p.games_s2_local, p.games_s2_visitante,
           p.games_s3_local, p.games_s3_visitante,
           p.resultado_ingresado_at,
           el.nombre AS local_nombre,
           ev.nombre AS visitante_nombre,
           g.nombre  AS ganador_nombre,
           l.nombre  AS liga_nombre,
           j.nombre  AS jugador_nombre,
           j.apellido AS jugador_apellido,
           j.foto     AS jugador_foto
    FROM partido_disputas d
    JOIN partidos p  ON p.id  = d.partido_id
    JOIN equipos el  ON el.id = p.equipo_local_id
    JOIN equipos ev  ON ev.id = p.equipo_visitante_id
    LEFT JOIN equipos g  ON g.id  = p.ganador_id
    JOIN ligas l     ON l.id  = p.liga_id
    JOIN jugadores j ON j.id  = d.jugador_id
    $where
    ORDER BY d.estado ASC, d.created_at DESC
")->fetchAll();

// Conteo pendientes para el badge del sidebar
$pendientes_count = (int)$db->query("SELECT COUNT(*) FROM partido_disputas WHERE estado='pendiente'")->fetchColumn();

require_once '../includes/header.php';
?>

<div class="dash-layout">
<?php include __DIR__ . '/../admin/partials/sidebar.php'; ?>
<main class="dash-main">

  <div class="dash-header" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:1rem">
    <div>
      <h1 class="dash-title">Disputas de Resultados</h1>
      <p style="color:var(--gray-400);font-size:.88rem">Reclamos enviados por jugadores sobre marcadores incorrectos.</p>
    </div>
    <div style="display:flex;gap:.5rem">
      <a href="?filtro=pendiente" class="btn <?= $filtro==='pendiente'?'btn-gold':'btn-outline' ?>" style="font-size:.78rem">
        ⚠️ Pendientes
        <?php if ($pendientes_count > 0): ?>
        <span style="background:#dc2626;color:#fff;border-radius:50px;padding:.1rem .45rem;font-size:.65rem;font-weight:800;margin-left:.3rem"><?= $pendientes_count ?></span>
        <?php endif; ?>
      </a>
      <a href="?filtro=todas" class="btn <?= $filtro==='todas'?'btn-gold':'btn-outline' ?>" style="font-size:.78rem">Ver todas</a>
    </div>
  </div>

  <?php if ($ok): ?>
  <div class="alert alert-success" style="margin-bottom:1.5rem">✅ <?= epl_h($ok) ?></div>
  <?php endif; ?>
  <?php if ($err): ?>
  <div class="alert alert-error" style="margin-bottom:1.5rem">❌ <?= epl_h($err) ?></div>
  <?php endif; ?>

  <?php if (empty($disputas)): ?>
  <div class="disp-empty">
    <div style="font-size:3rem;margin-bottom:1rem">🎾</div>
    <h3><?= $filtro==='pendiente' ? 'No hay reclamos pendientes' : 'No hay reclamos registrados' ?></h3>
    <p>Todo está tranquilo por ahora.</p>
  </div>
  <?php else: ?>

  <div class="disp-grid">
    <?php foreach ($disputas as $d): ?>
    <?php
      $es_pendiente = $d['estado'] === 'pendiente';
      $expires_ts   = $d['expires_at'] ? strtotime($d['expires_at']) : 0;
      $expirado     = $expires_ts > 0 && time() > $expires_ts;

      $sets_txt = [];
      if ($d['games_s1_local'] !== null) $sets_txt[] = $d['games_s1_local'] . '–' . $d['games_s1_visitante'];
      if ($d['games_s2_local'] !== null) $sets_txt[] = $d['games_s2_local'] . '–' . $d['games_s2_visitante'];
      if ($d['games_s3_local'] !== null) $sets_txt[] = $d['games_s3_local'] . '–' . $d['games_s3_visitante'];
      $resultado = implode(' / ', $sets_txt) ?: $d['sets_local'] . '–' . $d['sets_visitante'];
    ?>
    <div class="disp-card <?= $es_pendiente ? 'disp-card--pendiente' : 'disp-card--resuelta' ?>">

      <!-- Encabezado -->
      <div class="disp-card-header">
        <div class="disp-estado-badge <?= $es_pendiente ? 'badge--warn' : 'badge--ok' ?>">
          <?= $es_pendiente ? '⚠️ Pendiente' : '✅ Resuelta' ?>
        </div>
        <span class="disp-fecha"><?= date('d/m/Y H:i', strtotime($d['created_at'])) ?></span>
      </div>

      <!-- Partido -->
      <div class="disp-match">
        <div class="disp-teams">
          <span><?= epl_h($d['local_nombre']) ?></span>
          <span class="disp-vs">VS</span>
          <span><?= epl_h($d['visitante_nombre']) ?></span>
        </div>
        <div class="disp-meta-row">
          <span class="disp-meta-tag">🏅 <?= epl_h($d['liga_nombre']) ?></span>
          <span class="disp-meta-tag">🎾 <?= epl_h($resultado) ?></span>
          <?php if ($d['ganador_nombre']): ?>
          <span class="disp-meta-tag">🏆 <?= epl_h($d['ganador_nombre']) ?></span>
          <?php endif; ?>
        </div>
      </div>

      <!-- Jugador que reclama -->
      <div class="disp-jugador">
        <img src="<?= epl_foto_jugador($d['jugador_foto'], $d['jugador_nombre'] . ' ' . $d['jugador_apellido']) ?>"
             class="disp-avatar" alt="">
        <div>
          <span class="disp-jugador-label">Reclama:</span>
          <span class="disp-jugador-nombre"><?= epl_h($d['jugador_nombre'] . ' ' . $d['jugador_apellido']) ?></span>
        </div>
      </div>

      <!-- Motivo -->
      <div class="disp-motivo">
        <span class="disp-motivo-label">💬 Motivo del reclamo</span>
        <p class="disp-motivo-texto"><?= nl2br(epl_h($d['motivo'])) ?></p>
      </div>

      <?php if ($d['notas_admin']): ?>
      <div class="disp-notas-admin">
        <span class="disp-motivo-label">📋 Notas del admin</span>
        <p class="disp-motivo-texto"><?= nl2br(epl_h($d['notas_admin'])) ?></p>
      </div>
      <?php endif; ?>

      <!-- Acción resolver (solo pendientes) -->
      <?php if ($es_pendiente): ?>
      <form method="post" class="disp-resolver-form">
        <input type="hidden" name="action" value="resolver">
        <input type="hidden" name="disputa_id" value="<?= $d['id'] ?>">
        <textarea name="notas_admin" class="disp-notas-input" rows="2"
          placeholder="Notas para el jugador (opcional): ej. 'Resultado corregido a 6-4/4-6/10-7'"></textarea>
        <div style="display:flex;gap:.75rem;flex-wrap:wrap">
          <button type="submit" class="btn btn-navy" style="flex:1;min-width:140px;font-size:.78rem"
            data-confirm="¿Marcar esta disputa como resuelta?"
            data-confirm-title="Resolver disputa"
            data-confirm-ok="Resolver"
            data-confirm-danger="false">
            ✅ Marcar como resuelta
          </button>
          <a href="liga_detalle.php?id=<?= $d['partido_id'] ?>" class="btn btn-outline" style="flex:1;min-width:140px;font-size:.78rem;text-align:center">
            ✏️ Editar resultado
          </a>
        </div>
      </form>
      <?php endif; ?>

    </div>
    <?php endforeach; ?>
  </div>

  <?php endif; ?>

</main>
</div>

<style>
.disp-empty { text-align:center; padding:4rem 2rem; color:var(--gray-400); }
.disp-empty h3 { font-family:var(--font-head); text-transform:uppercase; color:var(--navy); margin:0 0 .5rem; }

.disp-grid { display:flex; flex-direction:column; gap:1.25rem; }

.disp-card {
  background:var(--white); border-radius:18px;
  border: 1px solid var(--gray-100);
  padding: 1.5rem; box-shadow: 0 4px 20px rgba(0,0,0,.06);
  animation: rr-fade-up .3s ease both;
}
.disp-card--pendiente { border-left: 4px solid #f59e0b; }
.disp-card--resuelta  { border-left: 4px solid #22c55e; opacity: .85; }

.disp-card-header { display:flex; align-items:center; justify-content:space-between; margin-bottom:1rem; }
.disp-estado-badge {
  font-size:.7rem; font-weight:800; text-transform:uppercase; letter-spacing:.06em;
  padding:.3rem .8rem; border-radius:50px;
}
.badge--warn { background:#fef3c7; color:#92400e; }
.badge--ok   { background:#dcfce7; color:#166534; }
.disp-fecha  { font-size:.75rem; color:var(--gray-400); font-weight:600; }

.disp-match { margin-bottom:1rem; }
.disp-teams {
  display:flex; align-items:center; gap:.5rem; flex-wrap:wrap;
  font-family:var(--font-head); font-size:.9rem; color:var(--navy);
  text-transform:uppercase; margin-bottom:.5rem;
}
.disp-vs {
  background:linear-gradient(135deg, var(--navy), #2d5a8e);
  color:var(--gold); border-radius:5px; padding:.1rem .4rem;
  font-size:.58rem; font-weight:800; flex-shrink:0;
}
.disp-meta-row { display:flex; gap:.4rem; flex-wrap:wrap; }
.disp-meta-tag {
  background:var(--gray-100); color:var(--gray-500);
  font-size:.68rem; font-weight:700; padding:.2rem .55rem;
  border-radius:6px; text-transform:uppercase; letter-spacing:.04em;
}

.disp-jugador {
  display:flex; align-items:center; gap:.65rem;
  background:var(--gray-100); border-radius:12px; padding:.65rem .9rem;
  margin-bottom:1rem;
}
.disp-avatar { width:32px; height:32px; border-radius:50%; object-fit:cover; }
.disp-jugador-label { font-size:.65rem; font-weight:800; color:var(--gray-400); text-transform:uppercase; letter-spacing:.06em; display:block; }
.disp-jugador-nombre { font-size:.85rem; font-weight:700; color:var(--navy); }

.disp-motivo { margin-bottom:1rem; }
.disp-notas-admin { margin-bottom:1rem; background:#f0fdf4; border-radius:10px; padding:.75rem 1rem; }
.disp-motivo-label { font-size:.62rem; font-weight:800; text-transform:uppercase; letter-spacing:.08em; color:var(--gray-400); display:block; margin-bottom:.35rem; }
.disp-motivo-texto { font-size:.85rem; color:var(--navy); line-height:1.55; margin:0; }

.disp-resolver-form { border-top:1px solid var(--gray-100); padding-top:1rem; margin-top:1rem; }
.disp-notas-input {
  width:100%; border:1.5px solid var(--gray-200); border-radius:10px;
  padding:.7rem .9rem; font-family:var(--font-body,'Montserrat',sans-serif);
  font-size:.82rem; color:var(--navy); resize:vertical; box-sizing:border-box;
  margin-bottom:.75rem; background:#fafafa;
}
.disp-notas-input:focus { outline:none; border-color:var(--gold); box-shadow:0 0 0 3px rgba(201,167,98,.15); }

@keyframes rr-fade-up {
  from { opacity:0; transform:translateY(10px); }
  to   { opacity:1; transform:translateY(0); }
}
</style>

<?php require_once '../includes/footer.php'; ?>
