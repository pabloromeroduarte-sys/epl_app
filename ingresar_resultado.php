<?php
$page_title = 'Ingresar Resultado';
$player_tab = 'resultado';
require_once 'includes/auth.php';
require_once 'includes/functions.php';
epl_require_login();

$jugador = epl_jugador_actual();
$db      = epl_db();
$liga    = epl_liga_activa();
$equipo  = $liga ? epl_equipo_del_jugador($jugador['id'], $liga['id']) : null;

$ok    = false;
$error = '';

// Partidos pendientes del equipo
$partidos_pendientes = [];
if ($equipo) {
    $st = $db->prepare("
        SELECT p.*,
               el.nombre AS local_nombre,
               ev.nombre AS visitante_nombre
        FROM partidos p
        JOIN equipos el ON el.id = p.equipo_local_id
        JOIN equipos ev ON ev.id = p.equipo_visitante_id
        WHERE p.liga_id = ?
          AND (p.equipo_local_id = ? OR p.equipo_visitante_id = ?)
          AND p.estado = 'pendiente'
        ORDER BY p.fecha_programada ASC
    ");
    $st->execute([$liga['id'], $equipo['id'], $equipo['id']]);
    $partidos_pendientes = $st->fetchAll();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $equipo) {
    $partido_id = (int)($_POST['partido_id'] ?? 0);
    $fecha_jugado = trim($_POST['fecha_jugado'] ?? '');

    // Validar que el partido le pertenece
    $stP = $db->prepare("
        SELECT * FROM partidos WHERE id=? AND (equipo_local_id=? OR equipo_visitante_id=?) AND estado='pendiente'
    ");
    $stP->execute([$partido_id, $equipo['id'], $equipo['id']]);
    $partido = $stP->fetch();

    if (!$partido) {
        $error = 'Partido no válido.';
    } else {
        // Leer sets
        $sets = [];
        for ($s = 1; $s <= 3; $s++) {
            $gl = isset($_POST["s{$s}_local"])      ? (int)$_POST["s{$s}_local"]      : null;
            $gv = isset($_POST["s{$s}_visitante"])  ? (int)$_POST["s{$s}_visitante"]  : null;
            if ($gl !== null && $gv !== null && ($gl > 0 || $gv > 0)) {
                $sets[] = ['local' => $gl, 'visitante' => $gv];
            }
        }

        if (empty($sets)) {
            $error = 'Debes ingresar al menos un set.';
        } else {
            // Calcular ganador
            $sets_local = 0; $sets_vis = 0;
            foreach ($sets as $sv) {
                if ($sv['local'] > $sv['visitante']) $sets_local++;
                else $sets_vis++;
            }
            $ganador_id = $sets_local > $sets_vis
                ? $partido['equipo_local_id']
                : $partido['equipo_visitante_id'];

            $db->prepare("
                UPDATE partidos SET
                  estado='jugado',
                  fecha_jugado=?,
                  sets_local=?, sets_visitante=?,
                  games_s1_local=?, games_s1_visitante=?,
                  games_s2_local=?, games_s2_visitante=?,
                  games_s3_local=?, games_s3_visitante=?,
                  ganador_id=?,
                  ingresado_por=?
                WHERE id=?
            ")->execute([
                $fecha_jugado ?: date('Y-m-d H:i:s'),
                $sets_local, $sets_vis,
                $sets[0]['local'] ?? null, $sets[0]['visitante'] ?? null,
                $sets[1]['local'] ?? null, $sets[1]['visitante'] ?? null,
                $sets[2]['local'] ?? null, $sets[2]['visitante'] ?? null,
                $ganador_id,
                $jugador['id'],
                $partido_id
            ]);

            epl_recalcular_clasificacion($liga['id']);
            $ok = true;

            // Recargar pendientes
            $st->execute([$liga['id'], $equipo['id'], $equipo['id']]);
            $partidos_pendientes = $st->fetchAll();
        }
    }
}
?>
<?php require_once 'includes/header.php'; ?>


<div class="dash-layout">
<?php include __DIR__ . '/includes/player_sidebar.php'; ?>
<main class="dash-main">
    <div class="dash-header">
      <h1 class="dash-title">Ingresar Resultado</h1>
    </div>

    <?php if ($ok): ?>
      <div class="alert alert-success">Resultado registrado. La clasificación ha sido actualizada.</div>
    <?php endif; ?>
    <?php if ($error): ?>
      <div class="alert alert-error"><?= epl_h($error) ?></div>
    <?php endif; ?>

    <?php if (!$equipo): ?>
      <div class="alert alert-info">No estás inscrito en ningún equipo de la liga activa.</div>

    <?php elseif (empty($partidos_pendientes)): ?>
      <div class="card card-body text-center" style="padding:3rem">
        <p style="color:var(--gray-400)">No tienes partidos pendientes de resultado.</p>
        <a href="dashboard.php" class="btn btn-outline-navy mt-3" style="display:inline-flex">Volver al Dashboard</a>
      </div>

    <?php else: ?>
    <div class="card">
      <div class="card-head">
        <h3 style="font-family:var(--font-head);font-size:1rem;text-transform:uppercase;color:var(--navy)">Registrar resultado</h3>
      </div>
      <div class="card-body">
        <form method="post" id="formResultado">

          <div class="form-group">
            <label class="form-label">Partido</label>
            <select name="partido_id" id="selectPartido" class="form-control" required onchange="actualizarPartido()">
              <option value="">— Selecciona un partido —</option>
              <?php foreach ($partidos_pendientes as $p): ?>
                <option value="<?= $p['id'] ?>"
                        data-local="<?= epl_h($p['local_nombre']) ?>"
                        data-visitante="<?= epl_h($p['visitante_nombre']) ?>"
                        data-fecha="<?= epl_h($p['fecha_programada'] ?? '') ?>">
                  <?= epl_h($p['local_nombre']) ?> vs <?= epl_h($p['visitante_nombre']) ?>
                  <?= $p['fecha_programada'] ? '— '.date('d/m/Y', strtotime($p['fecha_programada'])) : '' ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div id="seccionSets" style="display:none">
            <hr class="divider">

            <div style="display:grid;grid-template-columns:1fr auto 1fr;gap:1rem;align-items:center;margin-bottom:1.5rem">
              <div style="font-weight:700;color:var(--navy)" id="nombreLocal">Local</div>
              <div style="text-align:center;font-size:.8rem;color:var(--gray-400)">vs</div>
              <div style="font-weight:700;color:var(--navy);text-align:right" id="nombreVisitante">Visitante</div>
            </div>

            <!-- Sets -->
            <?php for ($s = 1; $s <= 3; $s++): ?>
            <div class="score-input-row">
              <span class="score-label">Set <?= $s ?><?= $s===3?' (si aplica)':'' ?></span>
              <div class="score-fields">
                <input type="number" name="s<?= $s ?>_local" class="score-num form-control"
                       min="0" max="7" placeholder="0" <?= $s===3?'':'required' ?>>
                <span class="score-sep">–</span>
                <input type="number" name="s<?= $s ?>_visitante" class="score-num form-control"
                       min="0" max="7" placeholder="0" <?= $s===3?'':'required' ?>>
              </div>
            </div>
            <?php endfor; ?>

            <hr class="divider">
            <div class="form-group">
              <label class="form-label">Fecha en que se jugó</label>
              <input type="datetime-local" name="fecha_jugado" class="form-control"
                     value="<?= date('Y-m-d\TH:i') ?>">
            </div>

            <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center">
              Confirmar resultado
            </button>
          </div>

        </form>
      </div>
    </div>
    <?php endif; ?>
</main>
</div>

<script>
function actualizarPartido() {
  const sel = document.getElementById('selectPartido');
  const opt = sel.options[sel.selectedIndex];
  const sec = document.getElementById('seccionSets');
  if (!sel.value) { sec.style.display='none'; return; }
  document.getElementById('nombreLocal').textContent = opt.dataset.local;
  document.getElementById('nombreVisitante').textContent = opt.dataset.visitante;
  sec.style.display = 'block';
}
</script>

<?php require_once 'includes/footer.php'; ?>
