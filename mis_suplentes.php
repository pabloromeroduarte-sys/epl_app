<?php
$page_title = 'Mis Suplentes (Galletas)';
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

// Suplentes actuales del equipo
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

$cupos_usados   = count($suplentes);
$cupos_restantes = max(0, 2 - $cupos_usados);

// Registro de partidos por suplente
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
    foreach ($stPS->fetchAll() as $r) {
        $partidos_suplente[$r['suplente_id']] = $r['total'];
    }
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
            // Verificar que no sea titular en este torneo
            $stTit = $db->prepare("
                SELECT COUNT(*) FROM liga_equipos le
                JOIN equipos e ON e.id = le.equipo_id
                WHERE le.liga_id=?
                  AND (e.jugador1_id=? OR e.jugador2_id=?)
            ");
            $stTit->execute([$liga['id'], $jugador_id, $jugador_id]);
            if ($stTit->fetchColumn() > 0) {
                $error = 'Ese jugador ya es titular en este torneo y no puede ser suplente.';
            } else {
                // Verificar que no sea suplente en otro equipo del mismo torneo
                $stOtro = $db->prepare("
                    SELECT COUNT(*) FROM suplentes
                    WHERE liga_id=? AND jugador_id=? AND equipo_id!=? AND estado='activo'
                ");
                $stOtro->execute([$liga['id'], $jugador_id, $equipo['id']]);
                if ($stOtro->fetchColumn() > 0) {
                    $error = 'Ese jugador ya es suplente de otro equipo en este torneo.';
                } else {
                    try {
                        $db->prepare("
                            INSERT INTO suplentes (liga_id, equipo_id, jugador_id, registrado_por)
                            VALUES (?,?,?,?)
                        ")->execute([$liga['id'], $equipo['id'], $jugador_id, $jugador['id']]);
                        $ok = 'Suplente registrado correctamente.';
                        $cupos_usados++;
                        $cupos_restantes = max(0, 2 - $cupos_usados);
                        $stS->execute([$equipo['id'], $liga['id']]);
                        $suplentes = $stS->fetchAll();
                    } catch (PDOException $e) {
                        $error = 'Ese jugador ya está registrado como suplente de tu equipo.';
                    }
                }
            }
        }

    } elseif ($action === 'registrar_partido') {
        $suplente_id = (int)($_POST['suplente_id'] ?? 0);
        $partido_id  = (int)($_POST['partido_id']  ?? 0);

        // Verificar que el suplente pertenece al equipo
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
                $db->prepare("UPDATE suplentes SET partidos_jugados = partidos_jugados + 1 WHERE id=?")
                   ->execute([$suplente_id]);
                $ok = 'Partido de suplente registrado.';
                $stPS->execute([$equipo['id'], $liga['id']]);
                foreach ($stPS->fetchAll() as $r) $partidos_suplente[$r['suplente_id']] = $r['total'];
            } catch (PDOException $e) {
                $error = 'Ese partido ya fue registrado para este suplente.';
            }
        }

    } elseif ($action === 'desactivar') {
        $suplente_id = (int)($_POST['suplente_id'] ?? 0);
        $db->prepare("UPDATE suplentes SET estado='inactivo' WHERE id=? AND equipo_id=?")
           ->execute([$suplente_id, $equipo['id']]);
        $ok = 'Suplente removido.';
        $stS->execute([$equipo['id'], $liga['id']]);
        $suplentes = $stS->fetchAll();
        $cupos_usados = count($suplentes);
        $cupos_restantes = max(0, 2 - $cupos_usados);
    }
}

// Jugadores disponibles como suplentes (activos, no titulares en el torneo)
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

// Partidos pendientes del equipo para registrar suplente
$partidos_para_suplente = [];
if ($equipo && $liga) {
    $stPP = $db->prepare("
        SELECT p.id, el.nombre AS local_nombre, ev.nombre AS visitante_nombre, p.fecha_programada
        FROM partidos p
        JOIN equipos el ON el.id=p.equipo_local_id
        JOIN equipos ev ON ev.id=p.equipo_visitante_id
        WHERE p.liga_id=? AND (p.equipo_local_id=? OR p.equipo_visitante_id=?)
          AND p.estado IN ('pendiente','jugado')
        ORDER BY p.fecha_programada DESC
        LIMIT 20
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
      <h1 class="dash-title">Mis Suplentes (Galletas)</h1>
      <?php if ($equipo): ?>
        <p style="color:var(--gray-600);margin-top:.25rem;font-size:.88rem">
          <?= epl_h($equipo['nombre']) ?> —
          <strong style="color:<?= $cupos_restantes>0?'var(--navy)':'var(--red)' ?>"><?= $cupos_usados ?>/2 cupos usados</strong>
        </p>
      <?php endif; ?>
    </div>

    <?php if ($ok): ?><div class="alert alert-success"><?= epl_h($ok) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-error"><?= epl_h($error) ?></div><?php endif; ?>

    <?php if (!$equipo): ?>
      <div class="alert alert-info">No estás inscrito en ningún equipo de la liga activa.</div>
    <?php else: ?>

    <!-- Reglas -->
    <div style="background:#FEF3C7;border:1px solid #FCD34D;border-radius:8px;padding:1rem 1.25rem;margin-bottom:1.5rem;font-size:.82rem;color:#92400E">
      <strong>Reglas de suplentes:</strong> Cada equipo puede registrar hasta <strong>2 galletas</strong> por temporada.
      Cada suplente puede jugar un máximo de <strong>9 partidos</strong>. Un jugador no puede ser suplente si ya es titular
      o suplente de otro equipo en este mismo torneo.
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:1.5rem;align-items:start" class="suplentes-grid">

      <!-- Suplentes actuales -->
      <div class="card">
        <div class="card-head">
          <h3 style="font-family:var(--font-head);font-size:1rem;text-transform:uppercase;color:var(--navy)">
            Suplentes registrados
          </h3>
          <span style="font-size:.75rem;color:<?= $cupos_restantes>0?'var(--gray-400)':'var(--red)' ?>">
            <?= $cupos_restantes ?> cupo<?= $cupos_restantes!=1?'s':'' ?> disponible<?= $cupos_restantes!=1?'s':'' ?>
          </span>
        </div>
        <?php if (empty($suplentes)): ?>
          <div class="card-body" style="text-align:center;color:var(--gray-400);padding:2rem">
            No hay suplentes registrados aún.
          </div>
        <?php else: ?>
          <div class="card-body" style="padding:0">
            <?php foreach ($suplentes as $sup):
              $pj = $partidos_suplente[$sup['id']] ?? 0;
              $pct = $pj > 0 ? min(100, round($pj/9*100)) : 0;
            ?>
            <div style="padding:1rem 1.5rem;border-bottom:1px solid var(--gray-100)">
              <div style="display:flex;align-items:center;gap:.75rem;margin-bottom:.75rem">
                <img src="<?= epl_h(epl_foto_jugador($sup['foto'], $sup['nombre'].' '.$sup['apellido'])) ?>"
                     style="width:44px;height:44px;border-radius:50%;object-fit:cover;border:2px solid var(--gold)" alt="">
                <div style="flex:1">
                  <div style="font-weight:700;color:var(--navy);font-size:.9rem"><?= epl_h($sup['nombre'].' '.$sup['apellido']) ?></div>
                  <?php if ($sup['alias']): ?><div style="font-size:.72rem;color:var(--gold)">"<?= epl_h($sup['alias']) ?>"</div><?php endif; ?>
                  <div style="font-size:.72rem;color:var(--gray-400)"><?= $sup['nivel'] ?>ª cat. <?= $sup['lado']?'· '.ucfirst($sup['lado']):'' ?></div>
                </div>
                <div style="text-align:right">
                  <span style="font-family:var(--font-head);font-size:1.5rem;color:<?= $pj>=9?'var(--red)':'var(--navy)' ?>"><?= $pj ?></span>
                  <div style="font-size:.65rem;color:var(--gray-400);text-transform:uppercase">/ 9 partidos</div>
                </div>
              </div>

              <!-- Barra de progreso -->
              <div style="background:var(--gray-200);border-radius:4px;height:6px;margin-bottom:.75rem">
                <div style="width:<?= $pct ?>%;background:<?= $pj>=9?'var(--red)':($pj>=7?'var(--gold)':'var(--green)') ?>;height:6px;border-radius:4px;transition:width .3s"></div>
              </div>

              <div style="display:flex;gap:.5rem;flex-wrap:wrap">
                <!-- Registrar partido -->
                <?php if ($pj < 9 && !empty($partidos_para_suplente)): ?>
                <button onclick="showRegistrarPartido(<?= $sup['id'] ?>, '<?= epl_h($sup['nombre']) ?>')"
                        class="btn btn-sm btn-primary" style="font-size:.72rem">+ Partido jugado</button>
                <?php endif; ?>
                <!-- Desactivar -->
                <form method="post" style="display:inline">
                  <input type="hidden" name="action" value="desactivar">
                  <input type="hidden" name="suplente_id" value="<?= $sup['id'] ?>">
                  <button type="submit" class="btn btn-sm" style="border:1px solid var(--red);color:var(--red);font-size:.72rem"
                          onclick="return confirm('¿Remover este suplente?')">Remover</button>
                </form>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>

      <!-- Agregar nuevo suplente -->
      <?php if ($cupos_restantes > 0): ?>
      <div class="card">
        <div class="card-head">
          <h3 style="font-family:var(--font-head);font-size:1rem;text-transform:uppercase;color:var(--navy)">Agregar suplente</h3>
        </div>
        <div class="card-body">
          <?php if (empty($disponibles)): ?>
            <p style="color:var(--gray-400);font-size:.88rem">No hay jugadores disponibles. Los jugadores deben estar activos y no ser titulares ni suplentes de otro equipo en este torneo.</p>
          <?php else: ?>
          <form method="post">
            <input type="hidden" name="action" value="agregar">
            <div class="form-group">
              <label class="form-label">Jugador</label>
              <select name="jugador_id" class="form-control" required>
                <option value="">— Selecciona un jugador —</option>
                <?php foreach ($disponibles as $d): ?>
                  <option value="<?= $d['id'] ?>">
                    <?= epl_h($d['nombre'].' '.$d['apellido']) ?>
                    <?= $d['alias']?'("'.$d['alias'].'") ':'' ?>
                    — <?= $d['nivel'] ?>ª cat.
                    <?= $d['lado']?'· '.ucfirst($d['lado']):'' ?>
                  </option>
                <?php endforeach; ?>
              </select>
              <span class="form-hint">Solo aparecen jugadores que no son titulares ni suplentes en otro equipo de este torneo.</span>
            </div>
            <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center">Registrar suplente</button>
          </form>
          <?php endif; ?>
        </div>
      </div>
      <?php else: ?>
      <div class="card">
        <div class="card-body" style="text-align:center;padding:2.5rem 1.5rem">
          <div style="font-size:2.5rem;margin-bottom:.5rem">🏓</div>
          <p style="color:var(--gray-600);font-weight:600">Cupos de suplentes completos</p>
          <p style="font-size:.82rem;color:var(--gray-400);margin-top:.5rem">Has usado los 2 cupos disponibles para esta temporada.</p>
        </div>
      </div>
      <?php endif; ?>
    </div>
    <?php endif; ?>

</div>

<!-- Modal registrar partido suplente -->
<div id="modalPartidoSup" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:99999;align-items:center;justify-content:center;padding:1rem">
  <div class="card" style="width:100%;max-width:420px">
    <div class="card-head">
      <h3 style="font-family:var(--font-head);font-size:1rem;text-transform:uppercase;color:var(--navy)" id="modalSupTitle">Registrar Partido</h3>
      <button onclick="document.getElementById('modalPartidoSup').style.display='none'" style="background:none;font-size:1.5rem;color:var(--gray-400)">×</button>
    </div>
    <div class="card-body">
      <form method="post">
        <input type="hidden" name="action" value="registrar_partido">
        <input type="hidden" name="suplente_id" id="modalSupId">
        <div class="form-group">
          <label class="form-label">Partido</label>
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
</main>
</div>

<style>
@media(max-width:768px){ .suplentes-grid { grid-template-columns:1fr !important; } }
</style>

<script>
function showRegistrarPartido(supId, nombre) {
  document.getElementById('modalSupId').value = supId;
  document.getElementById('modalSupTitle').textContent = 'Partido de ' + nombre;
  document.getElementById('modalPartidoSup').style.display = 'flex';
}
</script>

<?php require_once 'includes/footer.php'; ?>
