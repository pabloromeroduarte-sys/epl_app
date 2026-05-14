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
    <div class="dash-header">
      <h1 class="dash-title">Suplentes (Galletas)</h1>
    </div>

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
        <table style="width:100%;border-collapse:collapse;font-size:.85rem">
          <thead>
            <tr style="background:var(--navy);color:#fff">
              <th style="padding:.7rem 1rem;text-align:left;font-size:.72rem;text-transform:uppercase">Suplente</th>
              <th style="padding:.7rem 1rem;font-size:.72rem;text-transform:uppercase">Equipo</th>
              <th style="padding:.7rem 1rem;font-size:.72rem;text-transform:uppercase">Nivel</th>
              <th style="padding:.7rem 1rem;font-size:.72rem;text-transform:uppercase">Partidos jugados</th>
              <th style="padding:.7rem 1rem;font-size:.72rem;text-transform:uppercase">Estado</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($suplentes as $s):
              $pj = $s['pj_real'];
              $pct = min(100, round($pj/9*100));
            ?>
            <tr style="border-bottom:1px solid var(--gray-100)">
              <td style="padding:.7rem 1rem">
                <div style="font-weight:700;color:var(--navy)"><?= epl_h($s['j_nombre'].' '.$s['j_apellido']) ?></div>
                <?php if ($s['j_alias']): ?><div style="font-size:.7rem;color:var(--gold)">"<?= epl_h($s['j_alias']) ?>"</div><?php endif; ?>
              </td>
              <td style="padding:.7rem 1rem;color:var(--gray-600)"><?= epl_h($s['equipo_nombre']) ?></td>
              <td style="padding:.7rem 1rem;text-align:center"><?= $s['j_nivel'] ?>ª</td>
              <td style="padding:.7rem 1rem;min-width:180px">
                <div style="display:flex;align-items:center;gap:.75rem">
                  <div style="flex:1;background:var(--gray-200);border-radius:4px;height:8px">
                    <div style="width:<?= $pct ?>%;background:<?= $pj>=9?'var(--red)':($pj>=7?'var(--gold)':'var(--green)') ?>;height:8px;border-radius:4px"></div>
                  </div>
                  <span style="font-weight:700;font-size:.88rem;color:<?= $pj>=9?'var(--red)':'var(--navy)' ?>"><?= $pj ?>/9</span>
                </div>
              </td>
              <td style="padding:.7rem 1rem;text-align:center">
                <span class="badge <?= $pj>=9?'badge-walkover':'badge-jugado' ?>">
                  <?= $pj>=9?'Máximo alcanzado':'Activo' ?>
                </span>
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

<?php require_once '../includes/footer.php'; ?>
