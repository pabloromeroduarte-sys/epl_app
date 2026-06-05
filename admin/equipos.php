<?php
$page_title = 'Admin — Equipos';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
epl_require_admin();

$db     = epl_db();
$_flash = epl_flash_get();
$ok     = ($_flash && $_flash['tipo']==='ok') ? $_flash['msg'] : '';
$err    = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'eliminar') {
        $eid = (int)($_POST['equipo_id'] ?? 0);
        if ($eid) {
            // Verificar que no tiene partidos asociados
            $np = (int)$db->query("SELECT COUNT(*) FROM partidos WHERE equipo_local_id={$eid} OR equipo_visitante_id={$eid}")->fetchColumn();
            if ($np > 0) {
                $err = "No se puede eliminar: el equipo tiene {$np} partido(s) asociado(s).";
            } else {
                $db->prepare("DELETE FROM clasificacion  WHERE equipo_id=?")->execute([$eid]);
                $db->prepare("DELETE FROM liga_equipos   WHERE equipo_id=?")->execute([$eid]);
                $db->prepare("DELETE FROM suplentes      WHERE equipo_id=?")->execute([$eid]);
                $db->prepare("DELETE FROM inscripciones  WHERE equipo_id=?")->execute([$eid]);
                $db->prepare("DELETE FROM equipos        WHERE id=?")->execute([$eid]);
                epl_redirect_ok('Equipo eliminado correctamente.');
            }
        }
    }

    if ($action === 'crear') {
        $nombre = trim($_POST['nombre'] ?? '');
        $j1     = (int)($_POST['jugador1_id'] ?? 0);
        $j2     = (int)($_POST['jugador2_id'] ?? 0);
        $liga   = (int)($_POST['liga_id'] ?? 0);

        if (!$nombre || !$j1 || !$j2 || $j1 === $j2) { $err = 'Datos inválidos.'; }
        else {
            $db->prepare("INSERT INTO equipos (nombre,jugador1_id,jugador2_id) VALUES (?,?,?)")->execute([$nombre,$j1,$j2]);
            $eid = $db->lastInsertId();
            if ($liga) {
                $db->prepare("INSERT IGNORE INTO liga_equipos (liga_id,equipo_id) VALUES (?,?)")->execute([$liga,$eid]);
                $db->prepare("INSERT IGNORE INTO clasificacion (liga_id,equipo_id) VALUES (?,?)")->execute([$liga,$eid]);
            }
            epl_redirect_ok('Equipo creado.');
        }
    }
}

$ligas    = $db->query("SELECT * FROM ligas ORDER BY id DESC")->fetchAll();
$equipos  = $db->query("
    SELECT e.*,
           j1.nombre AS j1n, j1.apellido AS j1a,
           j2.nombre AS j2n, j2.apellido AS j2a
    FROM equipos e
    JOIN jugadores j1 ON j1.id = e.jugador1_id
    JOIN jugadores j2 ON j2.id = e.jugador2_id
    ORDER BY e.nombre
")->fetchAll();
$jugadores = $db->query("SELECT id,nombre,apellido FROM jugadores WHERE estado='activo' ORDER BY apellido,nombre")->fetchAll();
?>
<?php require_once '../includes/header.php'; ?>

<div class="dash-layout">
  <?php include __DIR__ . '/partials/sidebar.php'; ?>

  <main class="dash-main">
    <div class="dash-header flex justify-between items-center" style="flex-wrap:wrap;gap:.75rem">
      <h1 class="dash-title">Equipos</h1>
      <div style="display:flex;gap:.5rem;flex-wrap:wrap;width:100%;max-width:max-content">
        <button onclick="document.getElementById('modalCrearEquipo').style.display='flex'" class="btn btn-primary" style="flex:1;text-align:center">+ Nuevo equipo</button>
      </div>
    </div>

    <?php if ($ok): ?><div class="alert alert-success"><?= epl_h($ok) ?></div><?php endif; ?>
    <?php if ($err): ?><div class="alert alert-error"><?= epl_h($err) ?></div><?php endif; ?>

    <div class="card">
      <div style="overflow-x:auto">
        <table class="admin-table-cards" style="width:100%;border-collapse:collapse;font-size:.85rem">
          <thead>
            <tr style="background:var(--navy);color:#fff">
              <th style="padding:.7rem 1rem;text-align:left;font-size:.72rem;text-transform:uppercase">Equipo</th>
              <th class="hide-mobile" style="padding:.7rem 1rem;text-align:left;font-size:.72rem;text-transform:uppercase">Jugador 1</th>
              <th class="hide-mobile" style="padding:.7rem 1rem;text-align:left;font-size:.72rem;text-transform:uppercase">Jugador 2</th>
              <th style="padding:.7rem 1rem;text-align:center;font-size:.72rem;text-transform:uppercase">Estado</th>
              <th style="padding:.7rem 1rem;text-align:center;font-size:.72rem;text-transform:uppercase"></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($equipos as $e):
              $n_partidos = (int)$db->query("SELECT COUNT(*) FROM partidos WHERE equipo_local_id={$e['id']} OR equipo_visitante_id={$e['id']}")->fetchColumn();
            ?>
            <tr style="border-bottom:1px solid var(--gray-100)">
              <td data-label="Equipo" style="padding:.7rem 1rem;font-weight:700;color:var(--navy)">
                <?= epl_h($e['nombre']) ?>
                <?php if (strpos($e['nombre'], '/') !== false): ?>
                  <span style="font-size:.65rem;font-weight:700;background:#fef2f2;color:#dc2626;padding:.1rem .4rem;border-radius:4px;margin-left:.4rem">⚠ nombre inusual</span>
                <?php endif; ?>
              </td>
              <td data-label="Jugador 1" class="hide-mobile" style="padding:.7rem 1rem"><?= epl_h($e['j1n'].' '.$e['j1a']) ?></td>
              <td data-label="Jugador 2" class="hide-mobile" style="padding:.7rem 1rem"><?= epl_h($e['j2n'].' '.$e['j2a']) ?></td>
              <td data-label="Estado" style="padding:.7rem 1rem;text-align:center">
                <span class="badge <?= $e['estado']==='activo'?'badge-jugado':'badge-walkover' ?>"><?= $e['estado'] ?></span>
              </td>
              <td style="padding:.7rem 1rem;text-align:center">
                <?php if ($n_partidos === 0): ?>
                  <form method="POST" onsubmit="return confirm('⚠ Eliminar equipo «<?= epl_h(addslashes($e['nombre'])) ?>»?\nEsta acción no se puede deshacer.')">
                    <input type="hidden" name="action"    value="eliminar">
                    <input type="hidden" name="equipo_id" value="<?= (int)$e['id'] ?>">
                    <button type="submit" style="background:#fef2f2;color:#dc2626;border:1px solid #fecaca;border-radius:7px;padding:.3rem .6rem;font-size:.78rem;font-weight:700;cursor:pointer" title="Eliminar equipo">🗑</button>
                  </form>
                <?php else: ?>
                  <span style="font-size:.72rem;color:#94a3b8" title="Tiene <?= $n_partidos ?> partido(s)"><?= $n_partidos ?> part.</span>
                <?php endif; ?>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </main>
</div>

<!-- Modal crear equipo -->
<div id="modalCrearEquipo" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:99999;align-items:center;justify-content:center">
  <div class="card" style="width:100%;max-width:480px">
    <div class="card-head">
      <h3 style="font-family:var(--font-head);font-size:1rem;text-transform:uppercase;color:var(--navy)">Nuevo Equipo</h3>
      <button onclick="document.getElementById('modalCrearEquipo').style.display='none'" style="background:none;font-size:1.5rem;color:var(--gray-400)">×</button>
    </div>
    <div class="card-body">
      <form method="post">
        <input type="hidden" name="action" value="crear">
        <div class="form-group">
          <label class="form-label">Nombre del equipo</label>
          <input type="text" name="nombre" class="form-control" required placeholder="Apellido1 - Apellido2">
        </div>
        <div class="grid-2">
          <div class="form-group">
            <label class="form-label">Jugador 1</label>
            <select name="jugador1_id" class="form-control" required>
              <option value="">— Selecciona —</option>
              <?php foreach ($jugadores as $j): ?>
                <option value="<?= $j['id'] ?>"><?= epl_h($j['nombre'].' '.$j['apellido']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Jugador 2</label>
            <select name="jugador2_id" class="form-control" required>
              <option value="">— Selecciona —</option>
              <?php foreach ($jugadores as $j): ?>
                <option value="<?= $j['id'] ?>"><?= epl_h($j['nombre'].' '.$j['apellido']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Inscribir en liga</label>
          <select name="liga_id" class="form-control">
            <option value="">— Sin liga —</option>
            <?php foreach ($ligas as $l): ?>
              <option value="<?= $l['id'] ?>"><?= epl_h($l['nombre']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center">Crear equipo</button>
      </form>
    </div>
  </div>
</div>

<?php require_once '../includes/footer.php'; ?>
