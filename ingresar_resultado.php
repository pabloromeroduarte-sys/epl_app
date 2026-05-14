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
    $partido_id   = (int)($_POST['partido_id'] ?? 0);
    $fecha_jugado = trim($_POST['fecha_jugado'] ?? '');

    $stP = $db->prepare("SELECT * FROM partidos WHERE id=? AND (equipo_local_id=? OR equipo_visitante_id=?) AND estado='pendiente'");
    $stP->execute([$partido_id, $equipo['id'], $equipo['id']]);
    $partido = $stP->fetch();

    if (!$partido) {
        $error = 'Partido no válido.';
    } else {
        $sets = [];
        for ($s = 1; $s <= 3; $s++) {
            $gl = isset($_POST["s{$s}_local"])     ? (int)$_POST["s{$s}_local"]     : null;
            $gv = isset($_POST["s{$s}_visitante"]) ? (int)$_POST["s{$s}_visitante"] : null;
            if ($gl !== null && $gv !== null && ($gl > 0 || $gv > 0)) {
                $sets[] = ['local' => $gl, 'visitante' => $gv];
            }
        }
        if (empty($sets)) {
            $error = 'Debes ingresar al menos un set.';
        } else {
            $sets_local = 0; $sets_vis = 0;
            foreach ($sets as $sv) {
                if ($sv['local'] > $sv['visitante']) $sets_local++;
                else $sets_vis++;
            }
            $ganador_id = $sets_local > $sets_vis ? $partido['equipo_local_id'] : $partido['equipo_visitante_id'];

            $db->prepare("
                UPDATE partidos SET
                  estado='jugado', fecha_jugado=?,
                  sets_local=?, sets_visitante=?,
                  games_s1_local=?, games_s1_visitante=?,
                  games_s2_local=?, games_s2_visitante=?,
                  games_s3_local=?, games_s3_visitante=?,
                  ganador_id=?, ingresado_por=?
                WHERE id=?
            ")->execute([
                $fecha_jugado ?: date('Y-m-d H:i:s'),
                $sets_local, $sets_vis,
                $sets[0]['local'] ?? null, $sets[0]['visitante'] ?? null,
                $sets[1]['local'] ?? null, $sets[1]['visitante'] ?? null,
                $sets[2]['local'] ?? null, $sets[2]['visitante'] ?? null,
                $ganador_id, $jugador['id'], $partido_id
            ]);

            epl_recalcular_clasificacion($liga['id']);
            $ok = true;
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
    <p style="color:var(--gray-400);font-size:.88rem">Registra el marcador de tu partido.</p>
  </div>

  <?php if ($ok): ?>
  <div class="ir-success">
    <svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
    <div>
      <strong>¡Resultado registrado!</strong>
      <p style="margin:.2rem 0 0;font-size:.85rem;opacity:.85">La clasificación ha sido actualizada automáticamente.</p>
    </div>
  </div>
  <?php endif; ?>

  <?php if ($error): ?>
  <div class="alert alert-error"><?= epl_h($error) ?></div>
  <?php endif; ?>

  <?php if (!$equipo): ?>
    <div class="alert alert-info">No estás inscrito en ningún equipo de la liga activa.</div>

  <?php elseif (empty($partidos_pendientes)): ?>
  <div class="ir-empty">
    <div class="ir-empty-icon">🎾</div>
    <h3>Sin partidos pendientes</h3>
    <p>Todos tus partidos tienen resultado registrado.</p>
    <a href="dashboard.php" class="btn btn-navy" style="margin-top:1rem">Volver al Dashboard</a>
  </div>

  <?php else: ?>

  <!-- Selector de partido -->
  <div class="ir-selector-card">
    <p class="ir-step-label">Paso 1 — Selecciona el partido</p>
    <div class="ir-partidos-list" id="listaPartidos">
      <?php foreach ($partidos_pendientes as $p): ?>
      <label class="ir-partido-option" for="p<?= $p['id'] ?>">
        <input type="radio" name="_partido_pick" id="p<?= $p['id'] ?>" value="<?= $p['id'] ?>"
               data-local="<?= epl_h($p['local_nombre']) ?>"
               data-visitante="<?= epl_h($p['visitante_nombre']) ?>"
               data-fecha="<?= epl_h($p['fecha_programada'] ?? '') ?>"
               onchange="seleccionarPartido(this)">
        <div class="ir-partido-content">
          <div class="ir-partido-fecha">
            <?php if ($p['fecha_programada']): ?>
              <span class="ir-dia"><?= date('d', strtotime($p['fecha_programada'])) ?></span>
              <span class="ir-mes"><?= strtoupper(date('M', strtotime($p['fecha_programada']))) ?></span>
            <?php else: ?>
              <span class="ir-dia">—</span><span class="ir-mes">TBD</span>
            <?php endif; ?>
          </div>
          <div class="ir-partido-teams">
            <span><?= epl_h($p['local_nombre']) ?></span>
            <span class="ir-vs">VS</span>
            <span><?= epl_h($p['visitante_nombre']) ?></span>
          </div>
          <div class="ir-partido-jornada">F.<?= $p['jornada'] ?? '—' ?></div>
        </div>
        <div class="ir-partido-check">
          <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
        </div>
      </label>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- Formulario de marcador -->
  <form method="post" id="formResultado">
    <input type="hidden" name="partido_id" id="hiddenPartidoId">

    <div id="seccionSets" style="display:none">

      <!-- Teams display -->
      <div class="ir-match-header">
        <div class="ir-team-name" id="nombreLocal">—</div>
        <div class="ir-match-badge">RESULTADO</div>
        <div class="ir-team-name ir-team-right" id="nombreVisitante">—</div>
      </div>

      <!-- Sets -->
      <div class="ir-sets-container">
        <p class="ir-step-label" style="margin-bottom:1.25rem">Paso 2 — Ingresa el marcador por set</p>

        <?php for ($s = 1; $s <= 3; $s++): ?>
        <div class="ir-set-row <?= $s===3?'ir-set-optional':'' ?>">
          <div class="ir-set-label">
            <span class="ir-set-num">Set <?= $s ?></span>
            <?php if ($s===3): ?><span class="ir-set-opt-tag">opcional</span><?php endif; ?>
          </div>
          <div class="ir-score-group">
            <input type="number" name="s<?= $s ?>_local" class="ir-score-input"
                   min="0" max="7" placeholder="0" inputmode="numeric" <?= $s===3?'':'required' ?>>
            <span class="ir-score-dash">—</span>
            <input type="number" name="s<?= $s ?>_visitante" class="ir-score-input"
                   min="0" max="7" placeholder="0" inputmode="numeric" <?= $s===3?'':'required' ?>>
          </div>
        </div>
        <?php endfor; ?>
      </div>

      <!-- Fecha -->
      <div class="ir-fecha-card">
        <p class="ir-step-label" style="margin-bottom:.75rem">Paso 3 — Fecha en que se jugó</p>
        <input type="datetime-local" name="fecha_jugado" class="form-control"
               value="<?= date('Y-m-d\TH:i') ?>" style="max-width:280px">
      </div>

      <button type="submit" class="ir-submit-btn">
        <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        Confirmar resultado
      </button>

    </div>
  </form>

  <?php endif; ?>
</main>
</div>

<style>
.ir-success {
  display: flex; align-items: flex-start; gap: 1rem;
  background: #f0fdf4; border: 1px solid #86efac; border-radius: 12px;
  padding: 1.25rem 1.5rem; margin-bottom: 1.5rem; color: #166534;
}
.ir-success svg { flex-shrink: 0; margin-top: .1rem; }

.ir-empty { text-align: center; padding: 4rem 2rem; }
.ir-empty-icon { font-size: 3rem; margin-bottom: 1rem; }
.ir-empty h3 { font-family: var(--font-head); text-transform: uppercase; color: var(--navy); margin: 0 0 .5rem; }
.ir-empty p { color: var(--gray-400); }

.ir-step-label { font-size: .68rem; font-weight: 800; text-transform: uppercase; letter-spacing: .1em; color: var(--gray-400); margin: 0; }

/* Selector */
.ir-selector-card { background: var(--white); border-radius: 16px; border: 1px solid var(--gray-100); padding: 1.5rem; margin-bottom: 1.5rem; box-shadow: 0 2px 12px rgba(0,0,0,.04); }
.ir-partidos-list { display: flex; flex-direction: column; gap: .6rem; margin-top: 1rem; }
.ir-partido-option { display: flex; align-items: center; gap: 1rem; border: 2px solid var(--gray-100); border-radius: 12px; padding: .85rem 1rem; cursor: pointer; transition: all .18s; }
.ir-partido-option:hover { border-color: var(--gold); background: rgba(201,167,98,.04); }
.ir-partido-option input[type=radio] { display: none; }
.ir-partido-option:has(input:checked) { border-color: var(--navy); background: rgba(28,47,72,.04); }
.ir-partido-content { display: flex; align-items: center; gap: 1rem; flex: 1; min-width: 0; }
.ir-partido-fecha { background: var(--gray-100); border-radius: 8px; padding: .4rem .6rem; text-align: center; min-width: 42px; flex-shrink: 0; }
.ir-dia { display: block; font-family: var(--font-head); font-size: 1.1rem; color: var(--navy); line-height: 1; }
.ir-mes { display: block; font-size: .55rem; font-weight: 800; color: var(--gray-400); letter-spacing: .06em; }
.ir-partido-option:has(input:checked) .ir-partido-fecha { background: var(--navy); }
.ir-partido-option:has(input:checked) .ir-dia,
.ir-partido-option:has(input:checked) .ir-mes { color: var(--gold); }
.ir-partido-teams { flex: 1; display: flex; align-items: center; gap: .6rem; font-size: .85rem; font-weight: 700; color: var(--navy); min-width: 0; flex-wrap: wrap; }
.ir-vs { background: var(--navy); color: var(--gold); border-radius: 6px; padding: .15rem .45rem; font-size: .65rem; font-weight: 800; flex-shrink: 0; }
.ir-partido-option:has(input:checked) .ir-vs { background: var(--gold); color: var(--navy); }
.ir-partido-jornada { font-size: .68rem; font-weight: 700; color: var(--gray-400); flex-shrink: 0; }
.ir-partido-check { width: 28px; height: 28px; border-radius: 50%; border: 2px solid var(--gray-200); display: flex; align-items: center; justify-content: center; flex-shrink: 0; color: transparent; transition: all .18s; }
.ir-partido-option:has(input:checked) .ir-partido-check { background: var(--navy); border-color: var(--navy); color: var(--white); }

/* Match header */
.ir-match-header { display: grid; grid-template-columns: 1fr auto 1fr; align-items: center; gap: 1rem; background: var(--navy); border-radius: 16px; padding: 1.5rem 2rem; margin-bottom: 1.5rem; }
.ir-team-name { font-family: var(--font-head); font-size: clamp(.85rem, 2vw, 1.2rem); text-transform: uppercase; color: var(--white); line-height: 1.2; }
.ir-team-right { text-align: right; }
.ir-match-badge { background: var(--gold); color: var(--navy); font-size: .65rem; font-weight: 800; text-transform: uppercase; letter-spacing: .1em; border-radius: 20px; padding: .4rem .9rem; text-align: center; white-space: nowrap; }

/* Sets */
.ir-sets-container { background: var(--white); border-radius: 16px; border: 1px solid var(--gray-100); padding: 1.5rem; margin-bottom: 1rem; box-shadow: 0 2px 12px rgba(0,0,0,.04); }
.ir-set-row { display: flex; align-items: center; justify-content: space-between; gap: 1rem; padding: .85rem 0; border-bottom: 1px solid var(--gray-100); }
.ir-set-row:last-child { border-bottom: none; }
.ir-set-optional { opacity: .7; }
.ir-set-label { display: flex; align-items: center; gap: .5rem; min-width: 70px; }
.ir-set-num { font-weight: 800; font-size: .88rem; color: var(--navy); }
.ir-set-opt-tag { font-size: .6rem; font-weight: 700; color: var(--gray-400); background: var(--gray-100); border-radius: 4px; padding: .1rem .4rem; text-transform: uppercase; letter-spacing: .06em; }
.ir-score-group { display: flex; align-items: center; gap: .75rem; }
.ir-score-input {
  width: 64px; height: 64px;
  border: 2px solid var(--gray-200); border-radius: 12px;
  font-family: var(--font-head); font-size: 1.8rem; color: var(--navy);
  text-align: center; background: var(--white);
  transition: border-color .18s, box-shadow .18s;
  -moz-appearance: textfield;
}
.ir-score-input::-webkit-outer-spin-button,
.ir-score-input::-webkit-inner-spin-button { -webkit-appearance: none; }
.ir-score-input:focus { outline: none; border-color: var(--gold); box-shadow: 0 0 0 3px rgba(201,167,98,.2); }
.ir-score-dash { font-family: var(--font-head); font-size: 1.5rem; color: var(--gray-300); }

.ir-fecha-card { background: var(--white); border-radius: 16px; border: 1px solid var(--gray-100); padding: 1.25rem 1.5rem; margin-bottom: 1.5rem; box-shadow: 0 2px 12px rgba(0,0,0,.04); }

.ir-submit-btn {
  width: 100%; display: flex; align-items: center; justify-content: center; gap: .6rem;
  background: var(--navy); color: var(--white);
  border: none; border-radius: 14px; padding: 1.1rem;
  font-family: var(--font-head); font-size: 1rem; text-transform: uppercase; letter-spacing: .06em;
  cursor: pointer; transition: all .2s;
}
.ir-submit-btn:hover { background: var(--gold); color: var(--navy); transform: translateY(-1px); box-shadow: 0 6px 20px rgba(201,167,98,.35); }

@media(max-width:480px){
  .ir-match-header { padding: 1rem; gap: .5rem; }
  .ir-score-input { width: 56px; height: 56px; font-size: 1.5rem; }
  .ir-partido-teams { font-size: .78rem; }
}
</style>

<script>
function seleccionarPartido(radio) {
  document.getElementById('hiddenPartidoId').value = radio.value;
  document.getElementById('nombreLocal').textContent    = radio.dataset.local;
  document.getElementById('nombreVisitante').textContent = radio.dataset.visitante;
  const sec = document.getElementById('seccionSets');
  sec.style.display = 'block';
  setTimeout(() => sec.scrollIntoView({ behavior: 'smooth', block: 'start' }), 50);
}
</script>

<?php require_once 'includes/footer.php'; ?>
