<?php
$page_title = 'Ingresar Resultado';
$player_tab = 'resultado';
require_once 'includes/auth.php';
require_once 'includes/functions.php';
require_once 'includes/mail.php';
epl_require_login();

$jugador = epl_jugador_actual();
$db      = epl_db();
$liga    = epl_liga_activa();
$equipo  = $liga ? epl_equipo_del_jugador($jugador['id'], $liga['id']) : null;

$_flash = epl_flash_get();
$ok     = ($_flash && $_flash['tipo']==='ok');
$error  = ($_flash && $_flash['tipo']==='error') ? $_flash['msg'] : '';

$partidos_pendientes = [];
if ($equipo) {
    $st = $db->prepare("
        SELECT p.*,
               el.nombre AS local_nombre,
               ev.nombre AS visitante_nombre,
               CASE WHEN p.fecha_programada < NOW() THEN 1 ELSE 0 END AS vencido
        FROM partidos p
        JOIN equipos el ON el.id = p.equipo_local_id
        JOIN equipos ev ON ev.id = p.equipo_visitante_id
        WHERE p.liga_id = ?
          AND (p.equipo_local_id = ? OR p.equipo_visitante_id = ?)
          AND p.estado IN ('pendiente', 'reprogramado')
        ORDER BY
            ABS(DATEDIFF(p.fecha_programada, CURDATE())) ASC,
            p.fecha_programada ASC
    ");
    $st->execute([$liga['id'], $equipo['id'], $equipo['id']]);
    $partidos_pendientes = $st->fetchAll();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $equipo) {
    $partido_id   = (int)($_POST['partido_id'] ?? 0);
    $fecha_jugado = trim($_POST['fecha_jugado'] ?? '');

    epl_ensure_disputas_schema();
    $stP = $db->prepare("SELECT * FROM partidos WHERE id=? AND (equipo_local_id=? OR equipo_visitante_id=?) AND estado IN ('pendiente','reprogramado')");
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

            $ahora = date('Y-m-d H:i:s');
            $db->prepare("
                UPDATE partidos SET
                  estado='jugado', fecha_jugado=?,
                  sets_local=?, sets_visitante=?,
                  games_s1_local=?, games_s1_visitante=?,
                  games_s2_local=?, games_s2_visitante=?,
                  games_s3_local=?, games_s3_visitante=?,
                  ganador_id=?, ingresado_por=?,
                  resultado_ingresado_at=?
                WHERE id=?
            ")->execute([
                $fecha_jugado ?: $ahora,
                $sets_local, $sets_vis,
                $sets[0]['local'] ?? null, $sets[0]['visitante'] ?? null,
                $sets[1]['local'] ?? null, $sets[1]['visitante'] ?? null,
                $sets[2]['local'] ?? null, $sets[2]['visitante'] ?? null,
                $ganador_id, $jugador['id'], $ahora, $partido_id
            ]);

            epl_recalcular_clasificacion($liga['id']);

            // Notificar al equipo rival
            $rival_id = $partido['equipo_local_id'] == $equipo['id']
                ? $partido['equipo_visitante_id']
                : $partido['equipo_local_id'];
            $rival_nombre = $partido['equipo_local_id'] == $equipo['id']
                ? $partido['visitante_nombre']
                : $partido['local_nombre'];
            $mi_nombre = $equipo['nombre'];
            $resultado = "{$sets_local}-{$sets_vis}";
            $gano = $ganador_id == $equipo['id'];

            // Notificar a jugadores del equipo rival
            $asunto_res = epl_mail_asunto(
                '⚽ Resultado ingresado',
                $partido['local_nombre'],
                $partido['visitante_nombre'],
                $partido['jornada'] ?? null
            );
            $ganador_nombre  = $gano ? $mi_nombre : $rival_nombre;
            $resultado_sets  = implode(' / ', array_map(fn($s) => "{$s['local']}-{$s['visitante']}", $sets));
            $url_reclamar    = epl_url("reclamar_resultado.php?partido_id={$partido_id}");
            $rivales = $db->prepare("SELECT jugador_id FROM liga_equipos WHERE equipo_id = ? AND liga_id = ?");
            $rivales->execute([$rival_id, $liga['id']]);
            foreach ($rivales->fetchAll() as $r) {
                epl_notif_crear(
                    (int)$r['jugador_id'],
                    'resultado',
                    $asunto_res,
                    ($gano ? "{$mi_nombre} ganó" : "{$rival_nombre} ganó") . " {$resultado}. Tienes 24 horas para reclamar si hay un error.",
                    $url_reclamar,
                    true // skip_email: enviamos visual por separado
                );
                epl_mail_partido_visual(
                    (int)$r['jugador_id'],
                    $asunto_res,
                    $mi_nombre,
                    $rival_nombre,
                    [
                        ['icon' => '🏆', 'label' => 'Ganador',   'valor' => $ganador_nombre],
                        ['icon' => '🎾', 'label' => 'Resultado', 'valor' => $resultado_sets],
                    ],
                    ($gano ? "{$mi_nombre} ganó" : "{$rival_nombre} ganó") . ' el partido.',
                    '⚠️ Tienes 24 horas para reclamar si el marcador es incorrecto. Pasado ese plazo, el resultado queda confirmado.',
                    $url_reclamar,
                    '⚠️ Reclamar Resultado'
                );
            }

            epl_redirect_ok('resultado_ok');
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
      <?php foreach ($partidos_pendientes as $i => $p):
        $vencido = !empty($p['vencido']);
        $reprog  = ($p['estado'] === 'reprogramado');
        $primero = ($i === 0);
      ?>
      <label class="ir-partido-option<?= $vencido ? ' ir-vencido' : '' ?>" for="p<?= $p['id'] ?>">
        <input type="radio" name="_partido_pick" id="p<?= $p['id'] ?>" value="<?= $p['id'] ?>"
               data-local="<?= epl_h($p['local_nombre']) ?>"
               data-visitante="<?= epl_h($p['visitante_nombre']) ?>"
               data-fecha="<?= epl_h($p['fecha_programada'] ?? '') ?>"
               <?= $primero ? 'checked' : '' ?>
               onchange="seleccionarPartido(this)">
        <div class="ir-partido-content">
          <div class="ir-partido-fecha<?= $vencido ? ' ir-fecha-vencida' : '' ?>">
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
          <div style="display:flex;flex-direction:column;align-items:flex-end;gap:.25rem;flex-shrink:0">
            <div class="ir-partido-jornada">F.<?= $p['jornada'] ?? '—' ?></div>
            <?php if ($vencido): ?>
              <span style="font-size:.55rem;font-weight:800;color:#dc2626;background:#fee2e2;border-radius:4px;padding:1px 5px;text-transform:uppercase;letter-spacing:.04em">Vencido</span>
            <?php elseif ($reprog): ?>
              <span style="font-size:.55rem;font-weight:800;color:#d97706;background:#fef3c7;border-radius:4px;padding:1px 5px;text-transform:uppercase;letter-spacing:.04em">Reprog.</span>
            <?php endif; ?>
            <?php if ($primero): ?>
              <span style="font-size:.55rem;font-weight:800;color:#16a34a;background:#dcfce7;border-radius:4px;padding:1px 5px;text-transform:uppercase;letter-spacing:.04em">⭐ Próximo</span>
            <?php endif; ?>
          </div>
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
@keyframes ir-fade-up {
  from { opacity: 0; transform: translateY(12px); }
  to   { opacity: 1; transform: translateY(0); }
}
@keyframes ir-pop {
  0%   { transform: scale(.94); opacity: 0; }
  60%  { transform: scale(1.02); }
  100% { transform: scale(1); opacity: 1; }
}

.ir-success {
  display: flex; align-items: flex-start; gap: 1rem;
  background: linear-gradient(135deg, #f0fdf4, #dcfce7);
  border: 1px solid #86efac; border-radius: 14px;
  padding: 1.25rem 1.5rem; margin-bottom: 1.5rem; color: #166534;
  animation: ir-fade-up .35s ease both;
  box-shadow: 0 4px 16px rgba(34,197,94,.12);
}
.ir-success svg { flex-shrink: 0; margin-top: .1rem; }

.ir-empty { text-align: center; padding: 4rem 2rem; animation: ir-fade-up .4s ease both; }
.ir-empty-icon { font-size: 3rem; margin-bottom: 1rem; }
.ir-empty h3 { font-family: var(--font-head); text-transform: uppercase; color: var(--navy); margin: 0 0 .5rem; }
.ir-empty p { color: var(--gray-400); }

.ir-step-label {
  font-size: .62rem; font-weight: 800; text-transform: uppercase; letter-spacing: .12em;
  color: var(--gold); margin: 0;
  display: flex; align-items: center; gap: .5rem;
}
.ir-step-label::before {
  content: ''; display: inline-block; width: 18px; height: 2px;
  background: var(--gold); border-radius: 2px;
}

/* Selector */
.ir-selector-card {
  background: var(--white); border-radius: 20px;
  border: 1px solid var(--gray-100); padding: 1.5rem; margin-bottom: 1.5rem;
  box-shadow: 0 4px 24px rgba(0,0,0,.06);
  animation: ir-fade-up .35s ease both;
}
.ir-partidos-list { display: flex; flex-direction: column; gap: .5rem; margin-top: 1.1rem; }
/* Partido vencido (sin resultado y fecha pasada) */
.ir-vencido { border-color: rgba(220,38,38,.3) !important; background: rgba(220,38,38,.03) !important; }
.ir-vencido:hover { border-color: #dc2626 !important; }
.ir-fecha-vencida { background: linear-gradient(135deg, #fee2e2, #fecaca) !important; }
.ir-fecha-vencida .ir-dia { color: #dc2626 !important; }
.ir-fecha-vencida .ir-mes { color: #ef4444 !important; }
.ir-partido-option {
  display: flex; align-items: center; gap: 1rem;
  border: 1.5px solid var(--gray-100); border-radius: 14px;
  padding: .9rem 1rem; cursor: pointer;
  transition: all .22s cubic-bezier(.4,0,.2,1);
  background: var(--white);
}
.ir-partido-option:hover {
  border-color: var(--gold); background: rgba(201,167,98,.04);
  box-shadow: 0 2px 12px rgba(201,167,98,.14); transform: translateX(2px);
}
.ir-partido-option input[type=radio] { display: none; }
.ir-partido-option:has(input:checked) {
  border-color: var(--navy); background: linear-gradient(135deg, rgba(28,47,72,.04), rgba(28,47,72,.07));
  box-shadow: 0 4px 16px rgba(28,47,72,.1);
}
.ir-partido-content { display: flex; align-items: center; gap: 1rem; flex: 1; min-width: 0; }
.ir-partido-fecha {
  background: linear-gradient(135deg, #f3f4f6, #e5e7eb);
  border-radius: 10px; padding: .45rem .65rem; text-align: center; min-width: 44px; flex-shrink: 0;
  transition: all .22s;
}
.ir-dia { display: block; font-family: var(--font-head); font-size: 1.15rem; color: var(--navy); line-height: 1; }
.ir-mes { display: block; font-size: .52rem; font-weight: 800; color: var(--gray-400); letter-spacing: .08em; text-transform: uppercase; }
.ir-partido-option:has(input:checked) .ir-partido-fecha {
  background: linear-gradient(135deg, var(--navy), #1e3a5f);
  box-shadow: 0 4px 12px rgba(28,47,72,.3);
}
.ir-partido-option:has(input:checked) .ir-dia,
.ir-partido-option:has(input:checked) .ir-mes { color: var(--gold); }
.ir-partido-teams { flex: 1; display: flex; align-items: center; gap: .6rem; font-size: .85rem; font-weight: 700; color: var(--navy); min-width: 0; flex-wrap: wrap; }
.ir-vs {
  background: linear-gradient(135deg, var(--navy), #2d5a8e);
  color: var(--gold); border-radius: 6px; padding: .15rem .45rem;
  font-size: .62rem; font-weight: 800; flex-shrink: 0;
  box-shadow: 0 2px 6px rgba(28,47,72,.2);
}
.ir-partido-option:has(input:checked) .ir-vs { background: linear-gradient(135deg, var(--gold), #b8975a); color: var(--navy); }
.ir-partido-jornada { font-size: .65rem; font-weight: 700; color: var(--gray-400); flex-shrink: 0; }
.ir-partido-check {
  width: 28px; height: 28px; border-radius: 50%; border: 2px solid var(--gray-200);
  display: flex; align-items: center; justify-content: center; flex-shrink: 0;
  color: transparent; transition: all .22s cubic-bezier(.4,0,.2,1);
}
.ir-partido-option:has(input:checked) .ir-partido-check {
  background: linear-gradient(135deg, var(--navy), #1e3a5f);
  border-color: var(--navy); color: var(--white);
  box-shadow: 0 3px 10px rgba(28,47,72,.3);
  animation: ir-pop .3s ease both;
}

/* Match header */
.ir-match-header {
  display: grid; grid-template-columns: 1fr auto 1fr; align-items: center; gap: 1rem;
  background: linear-gradient(135deg, #0f1f38, var(--navy), #1a3a64);
  border-radius: 20px; padding: 1.75rem 2rem; margin-bottom: 1.5rem;
  box-shadow: 0 8px 32px rgba(28,47,72,.25);
  position: relative; overflow: hidden;
  animation: ir-fade-up .3s ease both;
}
.ir-match-header::before {
  content: ''; position: absolute; top: -40%; right: -10%;
  width: 200px; height: 200px; border-radius: 50%;
  background: rgba(201,167,98,.08); pointer-events: none;
}
.ir-team-name {
  font-family: var(--font-head); font-size: clamp(.8rem, 2vw, 1.15rem);
  text-transform: uppercase; color: var(--white); line-height: 1.2;
  text-shadow: 0 1px 3px rgba(0,0,0,.3);
}
.ir-team-right { text-align: right; }
.ir-match-badge {
  background: linear-gradient(135deg, var(--gold), #b8975a);
  color: var(--navy); font-size: .62rem; font-weight: 800;
  text-transform: uppercase; letter-spacing: .12em;
  border-radius: 20px; padding: .45rem 1rem; text-align: center; white-space: nowrap;
  box-shadow: 0 4px 14px rgba(201,167,98,.4);
}

/* Sets */
.ir-sets-container {
  background: var(--white); border-radius: 20px;
  border: 1px solid var(--gray-100); padding: 1.5rem; margin-bottom: 1rem;
  box-shadow: 0 4px 24px rgba(0,0,0,.05);
  animation: ir-fade-up .35s .05s ease both;
}
.ir-set-row {
  display: flex; align-items: center; justify-content: space-between; gap: 1rem;
  padding: 1rem 0; border-bottom: 1px solid var(--gray-100);
  transition: background .18s;
}
.ir-set-row:last-child { border-bottom: none; }
.ir-set-optional { opacity: .65; }
.ir-set-label { display: flex; align-items: center; gap: .5rem; min-width: 80px; }
.ir-set-num {
  font-weight: 800; font-size: .9rem; color: var(--navy);
  background: linear-gradient(135deg, #f3f4f6, #e5e7eb);
  border-radius: 8px; padding: .3rem .65rem;
}
.ir-set-opt-tag {
  font-size: .58rem; font-weight: 700; color: var(--gray-400);
  background: var(--gray-100); border-radius: 4px; padding: .1rem .4rem;
  text-transform: uppercase; letter-spacing: .06em;
}
.ir-score-group { display: flex; align-items: center; gap: .75rem; }
.ir-score-input {
  width: 68px; height: 68px;
  border: 2px solid var(--gray-200); border-radius: 14px;
  font-family: var(--font-head); font-size: 2rem; color: var(--navy);
  text-align: center; background: linear-gradient(135deg, #fafafa, var(--white));
  transition: border-color .22s, box-shadow .22s, transform .15s;
  -moz-appearance: textfield;
  box-shadow: 0 2px 8px rgba(0,0,0,.05);
}
.ir-score-input::-webkit-outer-spin-button,
.ir-score-input::-webkit-inner-spin-button { -webkit-appearance: none; }
.ir-score-input:focus {
  outline: none; border-color: var(--gold);
  box-shadow: 0 0 0 4px rgba(201,167,98,.18), 0 4px 16px rgba(201,167,98,.15);
  transform: scale(1.04);
}
.ir-score-dash { font-family: var(--font-head); font-size: 1.6rem; color: var(--gray-300); }

.ir-fecha-card {
  background: var(--white); border-radius: 20px;
  border: 1px solid var(--gray-100); padding: 1.25rem 1.5rem; margin-bottom: 1.5rem;
  box-shadow: 0 4px 24px rgba(0,0,0,.05);
  animation: ir-fade-up .35s .1s ease both;
}

.ir-submit-btn {
  width: 100%; display: flex; align-items: center; justify-content: center; gap: .7rem;
  background: linear-gradient(135deg, var(--navy), #1a3a64);
  color: var(--white); border: none; border-radius: 16px; padding: 1.2rem;
  font-family: var(--font-head); font-size: 1rem; text-transform: uppercase; letter-spacing: .08em;
  cursor: pointer; transition: all .25s cubic-bezier(.4,0,.2,1);
  box-shadow: 0 6px 20px rgba(28,47,72,.25);
  animation: ir-fade-up .35s .15s ease both;
}
.ir-submit-btn:hover {
  background: linear-gradient(135deg, var(--gold), #b8975a);
  color: var(--navy); transform: translateY(-2px);
  box-shadow: 0 10px 28px rgba(201,167,98,.4);
}
.ir-submit-btn:active { transform: translateY(0); }

@media(max-width:480px){
  .ir-match-header { padding: 1.1rem; gap: .5rem; }
  .ir-score-input { width: 58px; height: 58px; font-size: 1.6rem; }
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
// Auto-seleccionar el primer partido (más cercano / vencido)
document.addEventListener('DOMContentLoaded', function() {
  const first = document.querySelector('#listaPartidos input[type=radio]');
  if (first) {
    first.checked = true;
    seleccionarPartido(first);
  }
});
</script>

<?php require_once 'includes/footer.php'; ?>
