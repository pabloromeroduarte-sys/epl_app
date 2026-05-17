<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
$page_title = 'Admin — Suplentes';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
epl_require_admin();

$db = epl_db();
$liga = epl_liga_activa();

$filtro_liga = (int)($_GET['liga_id'] ?? ($liga['id'] ?? 0));
$ligas = $db->query("SELECT id, nombre, temporada FROM ligas ORDER BY id DESC")->fetchAll();

$ok  = '';
$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'agregar_suplente') {
    $liga_id    = (int)($_POST['liga_id']    ?? 0);
    $equipo_id  = (int)($_POST['equipo_id']  ?? 0);
    $jugador_id = (int)($_POST['jugador_id'] ?? 0);

    if (!$liga_id || !$equipo_id || !$jugador_id) {
        $err = 'Completa todos los campos.';
    } else {
        // Verificar que no sea titular en la liga
        $stTit = $db->prepare("SELECT COUNT(*) FROM equipos e JOIN liga_equipos le ON le.equipo_id=e.id WHERE le.liga_id=? AND (e.jugador1_id=? OR e.jugador2_id=?)");
        $stTit->execute([$liga_id, $jugador_id, $jugador_id]);
        if ($stTit->fetchColumn() > 0) {
            $err = 'Ese jugador ya es titular en este torneo.';
        } else {
            // Verificar que no sea ya suplente del mismo equipo
            $stDup = $db->prepare("SELECT COUNT(*) FROM suplentes WHERE liga_id=? AND equipo_id=? AND jugador_id=? AND estado='activo'");
            $stDup->execute([$liga_id, $equipo_id, $jugador_id]);
            if ($stDup->fetchColumn() > 0) {
                $err = 'Ese jugador ya es suplente de ese equipo.';
            } else {
                $db->prepare("INSERT INTO suplentes (liga_id, equipo_id, jugador_id, registrado_por) VALUES (?,?,?,?)")
                   ->execute([$liga_id, $equipo_id, $jugador_id, epl_jugador_actual()['id'] ?? null]);
                $ok = 'Galleta agregada correctamente.';
                $filtro_liga = $liga_id;
            }
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'eliminar_suplente') {
    $sid = (int)($_POST['suplente_id'] ?? 0);
    if ($sid) {
        $db->prepare("UPDATE suplentes SET estado='inactivo' WHERE id=?")->execute([$sid]);
        $ok = 'Galleta eliminada.';
    }
}

$suplentes = [];
if ($filtro_liga) {
    $suplentes = $db->prepare("
        SELECT s.*,
               j.nombre AS j_nombre, j.apellido AS j_apellido, j.alias AS j_alias, j.nivel AS j_nivel,
               e.nombre AS equipo_nombre,
               (SELECT COUNT(*) FROM suplente_partidos sp WHERE sp.suplente_id=s.id) AS pj_real
        FROM suplentes s
        JOIN jugadores j ON j.id = s.jugador_id
        JOIN equipos   e ON e.id = s.equipo_id
        WHERE s.liga_id=? AND s.estado='activo'
        ORDER BY e.nombre, j.apellido
    ");
    $suplentes->execute([$filtro_liga]);
    $suplentes = $suplentes->fetchAll();
}
?>
<?php require_once '../includes/header.php'; ?>

<div class="dash-layout">
  <?php include __DIR__ . '/partials/sidebar.php'; ?>

  <main class="dash-main">
    <div class="dash-header" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:.75rem">
      <h1 class="dash-title">Suplentes (Galletas)</h1>
      <?php if ($filtro_liga): ?>
      <button onclick="document.getElementById('modalAgregarGalleta').style.display='flex'" class="btn btn-primary btn-sm">+ Agregar Galleta</button>
      <?php endif; ?>
    </div>

    <?php if ($ok): ?><div class="alert alert-success"><?= epl_h($ok) ?></div><?php endif; ?>
    <?php if ($err): ?><div class="alert alert-error"><?= epl_h($err) ?></div><?php endif; ?>

    <div class="card mb-4" style="padding:1rem 1.5rem">
      <form method="get" style="display:flex;align-items:center;gap:1rem;flex-wrap:wrap">
        <label style="font-size:.8rem;font-weight:700;text-transform:uppercase;color:var(--navy)">Liga:</label>
        <select name="liga_id" class="form-control" style="width:auto" onchange="this.form.submit()">
          <option value="">Selecciona liga</option>
          <?php foreach ($ligas as $l): ?>
            <option value="<?= $l['id'] ?>" <?= $filtro_liga==$l['id']?'selected':'' ?>>
              <?= epl_h($l['nombre']) ?> <?= $l['temporada']?'('.$l['temporada'].')':'' ?>
            </option>
          <?php endforeach; ?>
        </select>
      </form>
    </div>

    <?php if ($filtro_liga): ?>
    <div class="card">
      <div style="overflow-x:auto">
        <table class="admin-table-cards" style="width:100%;border-collapse:collapse;font-size:.85rem">
          <thead>
            <tr style="background:var(--navy);color:#fff">
              <th style="padding:.7rem 1rem;text-align:left;font-size:.72rem;text-transform:uppercase">Suplente</th>
              <th style="padding:.7rem 1rem;font-size:.72rem;text-transform:uppercase">Equipo</th>
              <th class="hide-mobile" style="padding:.7rem 1rem;font-size:.72rem;text-transform:uppercase">Nivel</th>
              <th class="hide-mobile" style="padding:.7rem 1rem;font-size:.72rem;text-transform:uppercase">Partidos jugados</th>
              <th style="padding:.7rem 1rem;font-size:.72rem;text-transform:uppercase">Estado</th>
              <th style="padding:.7rem 1rem;font-size:.72rem;text-transform:uppercase"></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($suplentes as $s):
              $pj = $s['pj_real'];
              $pct = min(100, round($pj/9*100));
            ?>
            <tr style="border-bottom:1px solid var(--gray-100)">
              <td data-label="Suplente" style="padding:.7rem 1rem">
                <div style="font-weight:700;color:var(--navy)"><?= epl_h($s['j_nombre'].' '.$s['j_apellido']) ?></div>
                <?php if ($s['j_alias']): ?><div style="font-size:.7rem;color:var(--gold)">"<?= epl_h($s['j_alias']) ?>"</div><?php endif; ?>
              </td>
              <td data-label="Equipo" style="padding:.7rem 1rem;color:var(--gray-600)"><?= epl_h($s['equipo_nombre']) ?></td>
              <td data-label="Nivel" class="hide-mobile" style="padding:.7rem 1rem;text-align:center"><?= $s['j_nivel'] ?>ª</td>
              <td data-label="Partidos jugados" class="hide-mobile" style="padding:.7rem 1rem;min-width:180px">
                <div style="display:flex;align-items:center;gap:.75rem">
                  <div style="flex:1;background:var(--gray-200);border-radius:4px;height:8px">
                    <div style="width:<?= $pct ?>%;background:<?= $pj>=9?'var(--red)':($pj>=7?'var(--gold)':'var(--green)') ?>;height:8px;border-radius:4px"></div>
                  </div>
                  <span style="font-weight:700;font-size:.88rem;color:<?= $pj>=9?'var(--red)':'var(--navy)' ?>"><?= $pj ?>/9</span>
                </div>
              </td>
              <td data-label="Estado" style="padding:.7rem 1rem;text-align:center">
                <span class="badge <?= $pj>=9?'badge-walkover':'badge-jugado' ?>">
                  <?= $pj>=9?'Máximo alcanzado':'Activo' ?>
                </span>
              </td>
              <td style="padding:.7rem 1rem;text-align:center">
                <form method="post" onsubmit="return confirm('¿Eliminar esta galleta?')">
                  <input type="hidden" name="action" value="eliminar_suplente">
                  <input type="hidden" name="suplente_id" value="<?= $s['id'] ?>">
                  <input type="hidden" name="liga_id" value="<?= $filtro_liga ?>">
                  <button type="submit" class="btn btn-sm" style="background:#dc2626;color:#fff;border:none;cursor:pointer;font-size:.7rem">Eliminar</button>
                </form>
              </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($suplentes)): ?>
              <tr><td colspan="5" style="padding:2rem;text-align:center;color:var(--gray-400)">No hay suplentes registrados en esta liga.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
    <?php endif; ?>

  </main>
</div>

<?php if ($filtro_liga):
  $equipos_liga = $db->prepare("SELECT e.id, e.nombre FROM equipos e JOIN liga_equipos le ON le.equipo_id=e.id WHERE le.liga_id=? ORDER BY e.nombre");
  $equipos_liga->execute([$filtro_liga]);
  $equipos_liga = $equipos_liga->fetchAll();

  $jugadores_todos = $db->query("SELECT id, nombre, apellido FROM jugadores WHERE estado='activo' ORDER BY apellido, nombre")->fetchAll();
?>
<div id="modalAgregarGalleta" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:99999;align-items:center;justify-content:center;padding:1rem">
  <div class="card" style="width:100%;max-width:420px">
    <div class="card-head">
      <h3 style="font-family:var(--font-head);font-size:1rem;text-transform:uppercase;color:var(--navy)">Agregar Galleta</h3>
      <button onclick="document.getElementById('modalAgregarGalleta').style.display='none'" style="background:none;font-size:1.5rem;color:var(--gray-400)">×</button>
    </div>
    <div class="card-body">
      <form method="post">
        <input type="hidden" name="action" value="agregar_suplente">
        <input type="hidden" name="liga_id" value="<?= $filtro_liga ?>">

        <div class="form-group">
          <label class="form-label">Jugador *</label>
          <select name="jugador_id" class="form-control" required>
            <option value="">— Selecciona jugador —</option>
            <?php foreach ($jugadores_todos as $j): ?>
              <option value="<?= $j['id'] ?>"><?= epl_h($j['apellido'] . ', ' . $j['nombre']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="form-group">
          <label class="form-label">Equipo al que suple *</label>
          <select name="equipo_id" class="form-control" required>
            <option value="">— Selecciona equipo —</option>
            <?php foreach ($equipos_liga as $eq): ?>
              <option value="<?= $eq['id'] ?>"><?= epl_h($eq['nombre']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;margin-top:.5rem">Agregar galleta</button>
      </form>
    </div>
  </div>
</div>
<?php endif; ?>

<?php require_once '../includes/footer.php'; ?>
