<?php
$page_title = 'Mis Suplentes';
$player_tab = 'suplentes';
require_once 'includes/auth.php';
require_once 'includes/functions.php';
epl_require_login();

$jugador = epl_jugador_actual();
$db      = epl_db();
$liga    = epl_liga_activa();
$equipo  = $liga ? epl_equipo_del_jugador($jugador['id'], $liga['id']) : null;

$ok    = '';
$error = '';

$suplentes = [];
if ($equipo && $liga) {
    $stS = $db->prepare("
        SELECT s.*, j.nombre, j.apellido, j.alias, j.foto, j.nivel, j.lado
        FROM suplentes s
        JOIN jugadores j ON j.id = s.jugador_id
        WHERE s.equipo_id=? AND s.liga_id=? AND s.estado='activo'
        ORDER BY s.created_at ASC
    ");
    $stS->execute([$equipo['id'], $liga['id']]);
    $suplentes = $stS->fetchAll();
}

$cupos_usados    = count($suplentes);
$cupos_restantes = max(0, 2 - $cupos_usados);

$partidos_suplente = [];
if ($equipo && $liga) {
    $stPS = $db->prepare("
        SELECT sp.suplente_id, COUNT(*) AS total
        FROM suplente_partidos sp
        JOIN suplentes s ON s.id = sp.suplente_id
        WHERE s.equipo_id=? AND s.liga_id=?
        GROUP BY sp.suplente_id
    ");
    $stPS->execute([$equipo['id'], $liga['id']]);
    foreach ($stPS->fetchAll() as $r) $partidos_suplente[$r['suplente_id']] = $r['total'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $equipo && $liga) {
    $action = $_POST['action'] ?? '';

    if ($action === 'agregar') {
        $jugador_id = (int)($_POST['jugador_id'] ?? 0);
        if (!$jugador_id) {
            $error = 'Selecciona un jugador.';
        } elseif ($cupos_usados >= 2) {
            $error = 'Ya alcanzaste el máximo de 2 suplentes por equipo.';
        } elseif ($jugador_id == $jugador['id']) {
            $error = 'No puedes agregarte a ti mismo como suplente.';
        } else {
            $stTit = $db->prepare("SELECT COUNT(*) FROM liga_equipos le JOIN equipos e ON e.id=le.equipo_id WHERE le.liga_id=? AND (e.jugador1_id=? OR e.jugador2_id=?)");
            $stTit->execute([$liga['id'], $jugador_id, $jugador_id]);
            if ($stTit->fetchColumn() > 0) {
                $error = 'Ese jugador ya es titular en este torneo.';
            } else {
                $stOtro = $db->prepare("SELECT COUNT(*) FROM suplentes WHERE liga_id=? AND jugador_id=? AND equipo_id!=? AND estado='activo'");
                $stOtro->execute([$liga['id'], $jugador_id, $equipo['id']]);
                if ($stOtro->fetchColumn() > 0) {
                    $error = 'Ese jugador ya es suplente de otro equipo en este torneo.';
                } else {
                    try {
                        $db->prepare("INSERT INTO suplentes (liga_id, equipo_id, jugador_id, registrado_por) VALUES (?,?,?,?)")
                           ->execute([$liga['id'], $equipo['id'], $jugador_id, $jugador['id']]);
                        $ok = 'Suplente registrado correctamente.';
                        $stS->execute([$equipo['id'], $liga['id']]);
                        $suplentes = $stS->fetchAll();
                        $cupos_usados = count($suplentes);
                        $cupos_restantes = max(0, 2 - $cupos_usados);
                    } catch (PDOException $e) {
                        $error = 'Ese jugador ya está registrado como suplente de tu equipo.';
                    }
                }
            }
        }

    } elseif ($action === 'registrar_partido') {
        $suplente_id = (int)($_POST['suplente_id'] ?? 0);
        $partido_id  = (int)($_POST['partido_id']  ?? 0);
        $stV = $db->prepare("SELECT * FROM suplentes WHERE id=? AND equipo_id=? AND liga_id=?");
        $stV->execute([$suplente_id, $equipo['id'], $liga['id']]);
        $sup = $stV->fetch();
        if (!$sup) {
            $error = 'Suplente no válido.';
        } elseif (($partidos_suplente[$suplente_id] ?? 0) >= 9) {
            $error = 'Este suplente ya alcanzó el máximo de 9 partidos.';
        } else {
            try {
                $db->prepare("INSERT INTO suplente_partidos (suplente_id, partido_id, registrado_por) VALUES (?,?,?)")
                   ->execute([$suplente_id, $partido_id, $jugador['id']]);
                $db->prepare("UPDATE suplentes SET partidos_jugados = partidos_jugados + 1 WHERE id=?")->execute([$suplente_id]);
                $ok = 'Partido de suplente registrado.';
                $stPS->execute([$equipo['id'], $liga['id']]);
                foreach ($stPS->fetchAll() as $r) $partidos_suplente[$r['suplente_id']] = $r['total'];
            } catch (PDOException $e) {
                $error = 'Ese partido ya fue registrado para este suplente.';
            }
        }

    } elseif ($action === 'desactivar') {
        $suplente_id = (int)($_POST['suplente_id'] ?? 0);
        $db->prepare("UPDATE suplentes SET estado='inactivo' WHERE id=? AND equipo_id=?")->execute([$suplente_id, $equipo['id']]);
        $ok = 'Suplente removido.';
        $stS->execute([$equipo['id'], $liga['id']]);
        $suplentes = $stS->fetchAll();
        $cupos_usados = count($suplentes);
        $cupos_restantes = max(0, 2 - $cupos_usados);
    }
}

$disponibles = [];
if ($equipo && $liga) {
    $titulares_ids = $db->query("
        SELECT DISTINCT CASE WHEN e.jugador1_id IS NOT NULL THEN e.jugador1_id END AS id FROM liga_equipos le JOIN equipos e ON e.id=le.equipo_id WHERE le.liga_id={$liga['id']}
        UNION
        SELECT DISTINCT e.jugador2_id FROM liga_equipos le JOIN equipos e ON e.id=le.equipo_id WHERE le.liga_id={$liga['id']}
    ")->fetchAll(PDO::FETCH_COLUMN);
    $suplentes_ids = array_column($suplentes, 'jugador_id');
    $excluir = array_unique(array_merge($titulares_ids, $suplentes_ids, [$jugador['id']]));
    $placeholders = implode(',', array_fill(0, count($excluir), '?'));
    $stDisp = $db->prepare("SELECT id, nombre, apellido, alias, nivel, lado FROM jugadores WHERE estado='activo' AND id NOT IN ($placeholders) ORDER BY apellido, nombre");
    $stDisp->execute($excluir);
    $disponibles = $stDisp->fetchAll();
}

$partidos_para_suplente = [];
if ($equipo && $liga) {
    $stPP = $db->prepare("
        SELECT p.id, el.nombre AS local_nombre, ev.nombre AS visitante_nombre, p.fecha_programada
        FROM partidos p
        JOIN equipos el ON el.id=p.equipo_local_id
        JOIN equipos ev ON ev.id=p.equipo_visitante_id
        WHERE p.liga_id=? AND (p.equipo_local_id=? OR p.equipo_visitante_id=?)
          AND p.estado IN ('pendiente','jugado')
        ORDER BY p.fecha_programada DESC LIMIT 20
    ");
    $stPP->execute([$liga['id'], $equipo['id'], $equipo['id']]);
    $partidos_para_suplente = $stPP->fetchAll();
}
?>
<?php require_once 'includes/header.php'; ?>

<div class="dash-layout">
<?php include __DIR__ . '/includes/player_sidebar.php'; ?>
<main class="dash-main">

  <div class="dash-header">
    <h1 class="dash-title">Mis Suplentes <span style="font-size:.6em;color:var(--gold)">(Galletas)</span></h1>
    <?php if ($equipo): ?>
    <p style="color:var(--gray-400);font-size:.88rem"><?= epl_h($equipo['nombre']) ?></p>
    <?php endif; ?>
  </div>

  <?php if ($ok): ?><div class="alert alert-success"><?= epl_h($ok) ?></div><?php endif; ?>
  <?php if ($error): ?><div class="alert alert-error"><?= epl_h($error) ?></div><?php endif; ?>

  <?php if (!$equipo): ?>
    <div class="alert alert-info">No estás inscrito en ningún equipo de la liga activa.</div>
  <?php else: ?>

  <!-- Indicador de cupos -->
  <div class="sup-cupos-bar">
    <div class="sup-cupos-info">
      <div style="font-family:var(--font-head);font-size:1.4rem;color:var(--navy)"><?= $cupos_usados ?><span style="font-size:.75rem;color:var(--gray-400);font-family:var(--font-body);font-weight:600"> / 2 cupos</span></div>
      <div style="font-size:.72rem;color:var(--gray-400);text-transform:uppercase;letter-spacing:.08em;font-weight:700">Suplentes registrados</div>
    </div>
    <div class="sup-cupos-pips">
      <div class="sup-pip <?= $cupos_usados >= 1 ? 'sup-pip-on' : '' ?>"></div>
      <div class="sup-pip <?= $cupos_usados >= 2 ? 'sup-pip-on' : '' ?>"></div>
    </div>
    <?php if ($cupos_restantes > 0): ?>
      <span class="badge badge-jugado"><?= $cupos_restantes ?> cupo<?= $cupos_restantes!=1?'s':'' ?> libre<?= $cupos_restantes!=1?'s':'' ?></span>
    <?php else: ?>
      <span class="badge badge-walkover">Cupos completos</span>
    <?php endif; ?>
  </div>

  <!-- Info reglas -->
  <div class="sup-rules">
    <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
    <span>Máx. <strong>2 galletas</strong> por equipo · Máx. <strong>9 partidos</strong> por suplente · No puede ser titular ni suplente de otro equipo en el mismo torneo.</span>
  </div>

  <div class="sup-grid">

    <!-- Suplentes registrados -->
    <div>
      <h3 class="sup-section-title">Suplentes activos</h3>
      <?php if (empty($suplentes)): ?>
        <div class="sup-empty-card">
          <div style="font-size:2rem;margin-bottom:.5rem">👤</div>
          <p>Sin suplentes registrados aún.</p>
        </div>
      <?php else: ?>
        <div style="display:flex;flex-direction:column;gap:.85rem">
          <?php foreach ($suplentes as $sup):
            $pj  = $partidos_suplente[$sup['id']] ?? 0;
            $pct = $pj > 0 ? min(100, round($pj/9*100)) : 0;
            $bar_color = $pj>=9 ? '#ef4444' : ($pj>=7 ? '#f59e0b' : '#22c55e');
          ?>
          <div class="sup-card">
            <div class="sup-card-top">
              <img src="<?= epl_h(epl_foto_jugador($sup['foto'], $sup['nombre'].' '.$sup['apellido'])) ?>"
                   class="sup-avatar" alt="">
              <div style="flex:1;min-width:0">
                <div style="font-weight:800;color:var(--navy);font-size:.95rem"><?= epl_h($sup['nombre'].' '.$sup['apellido']) ?></div>
                <?php if ($sup['alias']): ?>
                  <div style="font-size:.75rem;color:var(--gold);font-weight:600">"<?= epl_h($sup['alias']) ?>"</div>
                <?php endif; ?>
                <div style="font-size:.72rem;color:var(--gray-400);margin-top:.1rem">
                  <?= $sup['nivel'] ?>ª cat.<?= $sup['lado'] ? ' · '.ucfirst($sup['lado']) : '' ?>
                </div>
              </div>
              <div style="text-align:right;flex-shrink:0">
                <div style="font-family:var(--font-head);font-size:1.6rem;color:<?= $pj>=9?'#ef4444':'var(--navy)' ?>;line-height:1"><?= $pj ?></div>
                <div style="font-size:.6rem;color:var(--gray-400);text-transform:uppercase;letter-spacing:.06em">/ 9 partidos</div>
              </div>
            </div>
            <!-- Barra de progreso -->
            <div class="sup-progress-track">
              <div class="sup-progress-fill" style="width:<?= $pct ?>%;background:<?= $bar_color ?>"></div>
            </div>
            <!-- Acciones -->
            <div class="sup-card-actions">
              <?php if ($pj < 9 && !empty($partidos_para_suplente)): ?>
              <button type="button" onclick="showModalSuplente(<?= $sup['id'] ?>, '<?= epl_h($sup['nombre']) ?>')"
                      class="btn btn-sm btn-primary" style="font-size:.72rem">
                + Registrar partido
              </button>
              <?php endif; ?>
              <form method="post" style="display:inline">
                <input type="hidden" name="action" value="desactivar">
                <input type="hidden" name="suplente_id" value="<?= $sup['id'] ?>">
                <button type="submit" class="btn btn-sm" style="border:1px solid #ef4444;color:#ef4444;font-size:.72rem"
                        onclick="return confirm('¿Remover este suplente?')">Remover</button>
              </form>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>

    <!-- Agregar suplente -->
    <div>
      <h3 class="sup-section-title">Agregar suplente</h3>
      <?php if ($cupos_restantes <= 0): ?>
        <div class="sup-empty-card" style="background:rgba(239,68,68,.05);border-color:rgba(239,68,68,.2)">
          <div style="font-size:2rem;margin-bottom:.5rem">🏓</div>
          <p style="font-weight:700;color:var(--navy)">Cupos completos</p>
          <p style="font-size:.82rem;color:var(--gray-400)">Has usado los 2 cupos de esta temporada.</p>
        </div>
      <?php elseif (empty($disponibles)): ?>
        <div class="sup-empty-card">
          <div style="font-size:2rem;margin-bottom:.5rem">🔍</div>
          <p style="color:var(--gray-400)">No hay jugadores disponibles. Deben estar activos y no ser titulares ni suplentes de otro equipo.</p>
        </div>
      <?php else: ?>
        <div class="sup-add-card">
          <form method="post">
            <input type="hidden" name="action" value="agregar">
            <div class="form-group" style="margin-bottom:1.25rem">
              <label class="form-label">Jugador</label>
              <select name="jugador_id" class="form-control" required>
                <option value="">— Selecciona un jugador —</option>
                <?php foreach ($disponibles as $d): ?>
                  <option value="<?= $d['id'] ?>">
                    <?= epl_h($d['nombre'].' '.$d['apellido']) ?>
                    <?= $d['alias'] ? ' ("'.$d['alias'].'")' : '' ?>
                    — <?= $d['nivel'] ?>ª cat.<?= $d['lado'] ? ' · '.ucfirst($d['lado']) : '' ?>
                  </option>
                <?php endforeach; ?>
              </select>
              <span class="form-hint">Solo aparecen jugadores disponibles para este torneo.</span>
            </div>
            <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center">
              Registrar como suplente
            </button>
          </form>
        </div>
      <?php endif; ?>
    </div>

  </div><!-- /sup-grid -->
  <?php endif; ?>

</main>
</div>

<!-- Modal registrar partido -->
<div id="modalPartidoSup" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.55);z-index:99999;align-items:center;justify-content:center;padding:1rem">
  <div style="background:var(--white);border-radius:20px;width:100%;max-width:420px;overflow:hidden;box-shadow:0 25px 60px rgba(0,0,0,.25)">
    <div style="background:var(--navy);padding:1.25rem 1.5rem;display:flex;justify-content:space-between;align-items:center">
      <h3 id="modalSupTitle" style="font-family:var(--font-head);font-size:1rem;text-transform:uppercase;color:var(--white);margin:0">Registrar Partido</h3>
      <button onclick="document.getElementById('modalPartidoSup').style.display='none'"
              style="background:rgba(255,255,255,.15);border:none;color:var(--white);width:32px;height:32px;border-radius:50%;font-size:1.2rem;cursor:pointer;display:flex;align-items:center;justify-content:center">×</button>
    </div>
    <div style="padding:1.5rem">
      <form method="post">
        <input type="hidden" name="action" value="registrar_partido">
        <input type="hidden" name="suplente_id" id="modalSupId">
        <div class="form-group" style="margin-bottom:1.25rem">
          <label class="form-label">Partido en que jugó</label>
          <select name="partido_id" class="form-control" required>
            <option value="">— Selecciona el partido —</option>
            <?php foreach ($partidos_para_suplente as $p): ?>
              <option value="<?= $p['id'] ?>">
                <?= epl_h($p['local_nombre'].' vs '.$p['visitante_nombre']) ?>
                <?= $p['fecha_programada'] ? ' — '.date('d/m/Y', strtotime($p['fecha_programada'])) : '' ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center">Confirmar</button>
      </form>
    </div>
  </div>
</div>

<style>
.sup-cupos-bar { display:flex; align-items:center; gap:1.5rem; background:var(--white); border:1px solid var(--gray-100); border-radius:16px; padding:1.25rem 1.5rem; margin-bottom:1rem; box-shadow:0 2px 10px rgba(0,0,0,.03); flex-wrap:wrap; }
.sup-cupos-info { flex:1; min-width:120px; }
.sup-cupos-pips { display:flex; gap:.6rem; }
.sup-pip { width:32px; height:10px; border-radius:5px; background:var(--gray-200); transition:background .3s; }
.sup-pip-on { background:var(--gold); }

.sup-rules { display:flex; align-items:flex-start; gap:.6rem; background:#fefce8; border:1px solid #fde68a; border-radius:10px; padding:.85rem 1rem; margin-bottom:1.5rem; font-size:.8rem; color:#92400e; }
.sup-rules svg { flex-shrink:0; margin-top:.1rem; }

.sup-grid { display:grid; grid-template-columns:1fr 1fr; gap:1.5rem; align-items:start; }
@media(max-width:768px){ .sup-grid { grid-template-columns:1fr; } }

.sup-section-title { font-family:var(--font-head); font-size:.82rem; text-transform:uppercase; letter-spacing:.08em; color:var(--navy); margin:0 0 1rem; }

.sup-card { background:var(--white); border:1px solid var(--gray-100); border-radius:16px; padding:1.25rem; box-shadow:0 2px 10px rgba(0,0,0,.04); }
.sup-card-top { display:flex; align-items:center; gap:.85rem; margin-bottom:.85rem; }
.sup-avatar { width:52px; height:52px; border-radius:50%; object-fit:cover; border:2px solid var(--gold); flex-shrink:0; }
.sup-progress-track { background:var(--gray-100); border-radius:4px; height:6px; margin-bottom:.85rem; overflow:hidden; }
.sup-progress-fill { height:6px; border-radius:4px; transition:width .4s ease; }
.sup-card-actions { display:flex; gap:.5rem; flex-wrap:wrap; }

.sup-empty-card { background:var(--white); border:1px dashed var(--gray-200); border-radius:16px; padding:2rem; text-align:center; color:var(--gray-400); }

.sup-add-card { background:var(--white); border:1px solid var(--gray-100); border-radius:16px; padding:1.5rem; box-shadow:0 2px 10px rgba(0,0,0,.04); }
</style>

<script>
function showModalSuplente(supId, nombre) {
  document.getElementById('modalSupId').value = supId;
  document.getElementById('modalSupTitle').textContent = 'Partido de ' + nombre;
  document.getElementById('modalPartidoSup').style.display = 'flex';
}
document.getElementById('modalPartidoSup').addEventListener('click', function(e) {
  if (e.target === this) this.style.display = 'none';
});
</script>

<?php require_once 'includes/footer.php'; ?>
