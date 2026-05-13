<?php
require_once 'includes/functions.php';

$db = epl_db();
$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: torneos.php'); exit; }

$stL = $db->prepare("SELECT * FROM ligas WHERE id=?");
$stL->execute([$id]);
$liga = $stL->fetch();
if (!$liga) { header('Location: torneos.php'); exit; }

$page_title = $liga['nombre'];
$active_nav = '';

$clasificacion = epl_clasificacion($liga['id']);
$partidos_jugados  = epl_partidos_liga($liga['id'], 'jugado');
$partidos_pendientes = epl_partidos_liga($liga['id'], 'pendiente');

// Agrupar pendientes por jornada
$por_jornada = [];
foreach ($partidos_pendientes as $p) {
    $j = $p['jornada'] ?? 0;
    $por_jornada[$j][] = $p;
}
ksort($por_jornada);

$badge_estado = [
    'proximamente' => ['label'=>'Próximamente',  'color'=>'#6366F1','bg'=>'#EDE9FE'],
    'inscripcion'  => ['label'=>'Inscripciones', 'color'=>'#D97706','bg'=>'#FEF3C7'],
    'activa'       => ['label'=>'En juego',      'color'=>'#065F46','bg'=>'#D1FAE5'],
    'finalizada'   => ['label'=>'Finalizado',    'color'=>'#374151','bg'=>'#F3F4F6'],
];
$est = $badge_estado[$liga['estado']] ?? $badge_estado['activa'];
?>
<?php require_once 'includes/header.php'; ?>

<!-- Hero Torneo -->
<?php
$nombre_lower = strtolower($liga['nombre']);
$is_women = (strpos($nombre_lower, 'women') !== false || strpos($nombre_lower, 'femenin') !== false);
$is_americano = (strpos($nombre_lower, 'americano') !== false);

if ($liga['foto_portada']) {
    $portada_hero_url = epl_url('uploads/ligas/'.$liga['foto_portada']);
} else {
    if ($is_americano && $is_women) {
        $portada_hero_url = epl_url('assets/img/portada-americano-women.jpg');
    } elseif ($is_americano && !$is_women) {
        $portada_hero_url = epl_url('assets/img/portada-americano-men.jpg');
    } elseif (!$is_americano && $is_women) {
        $portada_hero_url = epl_url('assets/img/portada-liga-women.jpg');
    } else {
        $portada_hero_url = epl_url('assets/img/portada-liga-men.jpg');
    }
}
?>
<section style="background:var(--navy);position:relative;overflow:hidden">
    <div style="position:absolute;inset:0;background-image:url('<?= $portada_hero_url ?>');background-size:cover;background-position:center;opacity:.2"></div>
  <div class="container" style="position:relative;z-index:1;padding-top:3rem;padding-bottom:3rem">
    <div style="display:flex;align-items:flex-start;justify-content:space-between;flex-wrap:wrap;gap:1.5rem">
      <div>
        <span style="display:inline-block;padding:.3rem .9rem;border-radius:50px;font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.1em;background:<?= $est['bg'] ?>;color:<?= $est['color'] ?>;margin-bottom:.85rem">
          <?= $est['label'] ?>
        </span>
        <h1 style="font-family:var(--font-head);font-size:clamp(1.8rem,4vw,3rem);text-transform:uppercase;color:#fff;line-height:1.1;margin-bottom:.5rem">
          <?= epl_h($liga['nombre']) ?>
        </h1>
        <?php if ($liga['temporada']): ?>
          <p style="color:var(--gold);font-size:.9rem;font-weight:600"><?= epl_h($liga['temporada']) ?><?= $liga['categoria']?' · '.$liga['categoria'].'ª categoría':'' ?></p>
        <?php endif; ?>
        <?php if ($liga['sede']): ?>
          <p style="color:rgba(255,255,255,.6);font-size:.85rem;margin-top:.5rem">
            📍 <?= epl_h($liga['sede']) ?>
            <?php if ($liga['url_maps']): ?>
              <a href="<?= epl_h($liga['url_maps']) ?>" target="_blank" rel="noopener" style="color:var(--gold);margin-left:.4rem">Ver mapa →</a>
            <?php endif; ?>
          </p>
        <?php endif; ?>
        <?php if ($liga['fecha_inicio'] || $liga['fecha_fin']): ?>
          <p style="color:rgba(255,255,255,.5);font-size:.8rem;margin-top:.35rem">
            📅 <?= $liga['fecha_inicio']?date('d/m/Y',strtotime($liga['fecha_inicio'])):'?' ?>
            <?= $liga['fecha_fin']?' — '.date('d/m/Y',strtotime($liga['fecha_fin'])):'' ?>
          </p>
        <?php endif; ?>
      </div>
      <?php if ($liga['precio']): ?>
      <div style="background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.15);border-radius:12px;padding:1.25rem 1.75rem;text-align:center;flex-shrink:0">
        <div style="font-size:.72rem;color:rgba(255,255,255,.5);text-transform:uppercase;letter-spacing:.1em;margin-bottom:.25rem">Inscripción</div>
        <div style="font-family:var(--font-head);font-size:2rem;color:var(--gold)"><?= number_format($liga['precio'],0,',','.') ?></div>
        <div style="font-size:.75rem;color:rgba(255,255,255,.4)">CLP por jugador</div>
      </div>
      <?php endif; ?>
    </div>

    <?php if ($liga['estado'] === 'inscripcion'): ?>
      <?php $jugador = epl_jugador_actual(); ?>
      <div style="margin-top:1.5rem">
        <?php if ($jugador): ?>
          <a href="inscribirse.php?liga_id=<?= $liga['id'] ?>" class="btn btn-primary btn-lg">Inscribirme ahora →</a>
        <?php else: ?>
          <a href="login.php?back=inscribirse.php%3Fliga_id%3D<?= $liga['id'] ?>" class="btn btn-primary btn-lg">Ingresar para inscribirme →</a>
        <?php endif; ?>
      </div>
    <?php endif; ?>
  </div>
</section>

<!-- Tabs: Ranking / Fixture / Resultados -->
<div style="background:var(--white);border-bottom:2px solid var(--gray-200);position:sticky;top:var(--nav-height);z-index:100">
  <div class="container" style="display:flex;gap:0">
    <a href="#ranking"   class="torneo-tab active">Clasificación</a>
    <a href="#fixture"   class="torneo-tab">Próximos</a>
    <a href="#resultados" class="torneo-tab">Resultados</a>
  </div>
</div>

<style>
.torneo-tab {
  padding: .85rem 1.5rem;
  font-size: .82rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: .08em;
  color: var(--gray-600);
  border-bottom: 2px solid transparent;
  margin-bottom: -2px;
  transition: all .2s;
  text-decoration: none;
}
.torneo-tab:hover, .torneo-tab.active { color: var(--navy); border-bottom-color: var(--gold); }
</style>

<!-- Clasificación -->
<section id="ranking" class="section" style="background:var(--white)">
  <div class="container">
    <p class="section-eyebrow">Tabla de posiciones</p>
    <h2 class="section-title">Clasificación</h2>
    <?php if ($clasificacion): ?>
    <div class="tabla-clasificacion">
      <table>
        <thead>
          <tr>
            <th style="text-align:left;padding-left:1.25rem">#</th>
            <th style="text-align:left">Equipo</th>
            <th>PJ</th><th>PG</th><th>PP</th>
            <th class="hide-mobile">GF</th>
            <th class="hide-mobile">GC</th>
            <th class="hide-mobile">Dif.</th>
            <th>Pts</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($clasificacion as $i => $row): ?>
          <tr>
            <td style="padding-left:1.25rem">
              <span class="posicion-num <?= ['pos-1','pos-2','pos-3'][$i] ?? 'pos-n' ?>"><?= $i+1 ?></span>
            </td>
            <td>
              <div class="equipo-cell">
                <div class="equipo-avatars">
                  <img class="equipo-avatar" src="<?= epl_h(epl_foto_jugador($row['j1_foto'], $row['j1_nombre'].' '.$row['j1_apellido'])) ?>" alt="">
                  <img class="equipo-avatar" src="<?= epl_h(epl_foto_jugador($row['j2_foto'], $row['j2_nombre'].' '.$row['j2_apellido'])) ?>" alt="">
                </div>
                <span class="equipo-nombre"><?= epl_h($row['equipo_nombre']) ?></span>
              </div>
            </td>
            <td><?= $row['pj'] ?></td>
            <td><?= $row['pg'] ?></td>
            <td><?= $row['pp'] ?></td>
            <td class="hide-mobile"><?= $row['games_favor'] ?></td>
            <td class="hide-mobile"><?= $row['games_contra'] ?></td>
            <td class="hide-mobile" style="color:<?= $row['games_favor']-$row['games_contra']>=0?'#22c55e':'#ef4444' ?>">
              <?= ($row['games_favor']-$row['games_contra'] >= 0 ? '+' : '') . ($row['games_favor']-$row['games_contra']) ?>
            </td>
            <td><strong style="color:var(--navy)"><?= $row['puntos'] ?></strong></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <p style="font-size:.72rem;color:var(--gray-400);margin-top:.75rem">Ordenado por: Puntos → Diferencia de games → Partidos ganados</p>
    <?php else: ?>
      <p style="color:var(--gray-400)">La clasificación se actualizará cuando comiencen los partidos.</p>
    <?php endif; ?>
  </div>
</section>

<!-- Próximos partidos por jornada -->
<section id="fixture" class="section">
  <div class="container">
    <p class="section-eyebrow">Agenda</p>
    <h2 class="section-title">Próximos Partidos</h2>
    <?php if (empty($partidos_pendientes)): ?>
      <p style="color:var(--gray-400)">No hay partidos pendientes programados.</p>
    <?php else: ?>
      <?php foreach ($por_jornada as $jornada => $ps): ?>
        <div style="margin-bottom:2rem">
          <?php if ($jornada): ?>
            <h3 style="font-family:var(--font-head);font-size:1rem;text-transform:uppercase;color:var(--navy);margin-bottom:.85rem;display:flex;align-items:center;gap:.75rem">
              Jornada <?= $jornada ?>
              <span style="background:var(--gold);color:var(--navy);padding:.15rem .6rem;border-radius:50px;font-size:.72rem"><?= count($ps) ?> partido<?= count($ps)>1?'s':'' ?></span>
            </h3>
          <?php endif; ?>
          <div class="partidos-list">
            <?php foreach ($ps as $p): ?>
            <div class="partido-card">
              <div class="partido-equipo">
                <span class="partido-nombre"><?= epl_h($p['local_nombre']) ?></span>
              </div>
              <div class="partido-resultado">
                <?php if ($p['fecha_programada']): ?>
                  <span style="font-size:.85rem;font-weight:700;color:var(--navy)"><?= date('d/m', strtotime($p['fecha_programada'])) ?></span>
                  <span style="font-size:.78rem;color:var(--gold);font-weight:600"><?= date('H:i', strtotime($p['fecha_programada'])) ?></span>
                <?php else: ?>
                  <span style="font-size:.8rem;color:var(--gray-400)">Por definir</span>
                <?php endif; ?>
                <?php if ($p['cancha']): ?>
                  <span style="font-size:.68rem;color:var(--gray-400)"><?= epl_h($p['cancha']) ?></span>
                <?php endif; ?>
                <span class="badge badge-pendiente">Pendiente</span>
              </div>
              <div class="partido-equipo right">
                <span class="partido-nombre"><?= epl_h($p['visitante_nombre']) ?></span>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</section>

<!-- Resultados recientes -->
<section id="resultados" class="section" style="background:var(--white)">
  <div class="container">
    <p class="section-eyebrow">Historial</p>
    <h2 class="section-title">Resultados</h2>
    <?php if (empty($partidos_jugados)): ?>
      <p style="color:var(--gray-400)">Aún no hay partidos jugados.</p>
    <?php else: ?>
      <div class="partidos-list">
        <?php foreach (array_reverse($partidos_jugados) as $p): ?>
        <div class="partido-card">
          <div class="partido-equipo">
            <span class="partido-nombre"><?= epl_h($p['local_nombre']) ?></span>
            <?php if ($p['ganador_id'] == $p['equipo_local_id']): ?>
              <span class="badge badge-jugado" style="font-size:.63rem">Ganador</span>
            <?php endif; ?>
          </div>
          <div class="partido-resultado">
            <span class="resultado-score"><?= $p['sets_local'] ?> – <?= $p['sets_visitante'] ?></span>
            <?php
              $sets=[];
              for($s=1;$s<=3;$s++){$gl=$p["games_s{$s}_local"];$gv=$p["games_s{$s}_visitante"];if($gl!==null)$sets[]="$gl-$gv";}
              if($sets): ?><span class="resultado-sets"><?= implode('  ',$sets) ?></span><?php endif;
            ?>
            <span class="badge badge-jugado">Jugado</span>
            <?php if ($p['fecha_jugado']): ?>
              <span style="font-size:.65rem;color:var(--gray-400)"><?= date('d/m/Y', strtotime($p['fecha_jugado'])) ?></span>
            <?php endif; ?>
          </div>
          <div class="partido-equipo right">
            <span class="partido-nombre"><?= epl_h($p['visitante_nombre']) ?></span>
            <?php if ($p['ganador_id'] == $p['equipo_visitante_id']): ?>
              <span class="badge badge-jugado" style="font-size:.63rem">Ganador</span>
            <?php endif; ?>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</section>

<?php require_once 'includes/footer.php'; ?>
