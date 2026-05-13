<?php
$page_title = 'Clasificación';
$active_nav = 'clasificacion';
require_once 'includes/functions.php';

$db   = epl_db();
$ligas = $db->query("SELECT * FROM ligas ORDER BY id DESC")->fetchAll();
$liga_id = isset($_GET['liga']) ? (int)$_GET['liga'] : ($ligas[0]['id'] ?? 0);
$liga_sel = null;
foreach ($ligas as $l) { if ($l['id'] == $liga_id) { $liga_sel = $l; break; } }
$clasificacion = $liga_id ? epl_clasificacion($liga_id) : [];
?>
<?php require_once 'includes/header.php'; ?>

<section class="section-sm" style="background:var(--navy)">
  <div class="container">
    <p class="section-eyebrow" style="color:var(--gold)">Temporada <?= $liga_sel ? epl_h($liga_sel['temporada']) : '' ?></p>
    <h1 class="section-title" style="color:var(--white)">Clasificación</h1>
  </div>
</section>

<section class="section">
  <div class="container">

    <!-- Selector de liga -->
    <?php if (count($ligas) > 1): ?>
    <div class="mb-4">
      <form method="get" style="display:flex;gap:.75rem;align-items:center;flex-wrap:wrap">
        <label class="form-label mb-0" style="white-space:nowrap">Liga:</label>
        <select name="liga" class="form-control" style="width:auto" onchange="this.form.submit()">
          <?php foreach ($ligas as $l): ?>
            <option value="<?= $l['id'] ?>" <?= $l['id']==$liga_id?'selected':'' ?>>
              <?= epl_h($l['nombre']) ?> — <?= epl_h($l['temporada'] ?? '') ?>
            </option>
          <?php endforeach; ?>
        </select>
      </form>
    </div>
    <?php endif; ?>

    <?php if ($clasificacion): ?>
    <div class="tabla-clasificacion">
      <table>
        <thead>
          <tr>
            <th>#</th>
            <th style="text-align:left">Equipo</th>
            <th title="Partidos Jugados">PJ</th>
            <th title="Ganados">PG</th>
            <th title="Perdidos">PP</th>
            <th title="Games a Favor" class="hide-mobile">GF</th>
            <th title="Games en Contra" class="hide-mobile">GC</th>
            <th title="Diferencia" class="hide-mobile">+/-</th>
            <th title="Puntos">Pts</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($clasificacion as $i => $row): ?>
          <tr>
            <td>
              <?php $posClass = match($i) { 0=>'pos-1', 1=>'pos-2', 2=>'pos-3', default=>'pos-n' }; ?>
              <span class="posicion-num <?= $posClass ?>"><?= $i+1 ?></span>
            </td>
            <td>
              <div class="equipo-cell">
                <div class="equipo-avatars">
                  <img class="equipo-avatar"
                       src="<?= epl_h(epl_foto_jugador($row['j1_foto'], $row['j1_nombre'].' '.$row['j1_apellido'])) ?>"
                       alt="<?= epl_h($row['j1_nombre']) ?>">
                  <img class="equipo-avatar"
                       src="<?= epl_h(epl_foto_jugador($row['j2_foto'], $row['j2_nombre'].' '.$row['j2_apellido'])) ?>"
                       alt="<?= epl_h($row['j2_nombre']) ?>">
                </div>
                <div>
                  <div class="equipo-nombre"><?= epl_h($row['equipo_nombre']) ?></div>
                  <div style="font-size:.75rem;color:var(--gray-400)">
                    <?= epl_h($row['j1_nombre'].' '.$row['j1_apellido']) ?> /
                    <?= epl_h($row['j2_nombre'].' '.$row['j2_apellido']) ?>
                  </div>
                </div>
              </div>
            </td>
            <td><?= $row['pj'] ?></td>
            <td style="color:var(--green);font-weight:700"><?= $row['pg'] ?></td>
            <td style="color:var(--red)"><?= $row['pp'] ?></td>
            <td class="hide-mobile"><?= $row['games_favor'] ?></td>
            <td class="hide-mobile"><?= $row['games_contra'] ?></td>
            <td class="hide-mobile" style="color:<?= ($row['games_favor']-$row['games_contra'])>=0?'var(--green)':'var(--red)' ?>">
              <?= ($row['games_favor']-$row['games_contra']) >= 0 ? '+' : '' ?><?= $row['games_favor']-$row['games_contra'] ?>
            </td>
            <td>
              <strong style="font-size:1rem;color:var(--navy)"><?= $row['puntos'] ?></strong>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <div class="mt-3" style="font-size:.78rem;color:var(--gray-400)">
      PJ=Jugados · PG=Ganados · PP=Perdidos · GF=Games favor · GC=Games contra · +/-=Diferencia · Pts=Puntos (3 por victoria)
    </div>

    <?php else: ?>
    <div class="card card-body text-center" style="padding:3rem">
      <p style="color:var(--gray-400)">No hay datos de clasificación disponibles.</p>
    </div>
    <?php endif; ?>

  </div>
</section>

<?php require_once 'includes/footer.php'; ?>
