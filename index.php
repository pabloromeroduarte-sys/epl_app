<?php
$page_title = 'Inicio';
$active_nav = 'inicio';
require_once 'includes/functions.php';

$liga = epl_liga_activa();
$clasificacion = $liga ? array_slice(epl_clasificacion($liga['id']), 0, 5) : [];
$ultimos_partidos = $liga ? array_slice(epl_partidos_liga($liga['id'], 'jugado'), -5) : [];
$proximos = $liga ? array_slice(epl_partidos_liga($liga['id'], 'pendiente'), 0, 3) : [];
?>
<?php require_once 'includes/header.php'; ?>

<!-- ======================================================
     HERO
     ====================================================== -->
<section class="hero">
  <div class="hero-bg" style="background-image:url('<?= epl_url('assets/img/hero-padel.jpg') ?>')"></div>
  <div class="hero-content container">
    <p class="hero-eyebrow">Temporada <?= $liga ? epl_h($liga['temporada']) : '2026' ?> • Categoría <?= $liga ? $liga['categoria'].'ra' : '5ta' ?></p>
    <h1 class="hero-title">
      Elite<br>
      <span>Padel</span><br>
      League
    </h1>
    <p class="hero-subtitle">Competencia organizada, resultados en tiempo real y tu historial de partidos en un solo lugar.</p>
    <div class="hero-ctas">
      <a href="clasificacion.php" class="btn btn-primary btn-lg">Ver Clasificación</a>
      <a href="resultados.php"    class="btn btn-outline btn-lg">Últimos Resultados</a>
    </div>
  </div>
</section>

<!-- ======================================================
     CLASIFICACIÓN PARCIAL
     ====================================================== -->
<?php if ($liga && $clasificacion): ?>
<section class="section" style="background:var(--white)">
  <div class="container">
    <p class="section-eyebrow">Liga activa</p>
    <h2 class="section-title"><?= epl_h($liga['nombre']) ?></h2>
    <p class="section-desc">Top 5 de la clasificación actual.</p>

    <div class="tabla-clasificacion mb-4">
      <table>
        <thead>
          <tr>
            <th>#</th>
            <th style="text-align:left">Equipo</th>
            <th>PJ</th>
            <th>PG</th>
            <th>PP</th>
            <th class="hide-mobile">GF</th>
            <th class="hide-mobile">GC</th>
            <th>Pts</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($clasificacion as $i => $row): ?>
          <tr>
            <td>
              <span class="posicion-num <?= ['pos-1','pos-2','pos-3'][$i] ?? 'pos-n' ?>">
                <?= $i+1 ?>
              </span>
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
            <td><strong><?= $row['puntos'] ?></strong></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <a href="clasificacion.php" class="btn btn-outline-navy">Ver clasificación completa →</a>
  </div>
</section>
<?php endif; ?>

<!-- ======================================================
     ÚLTIMOS RESULTADOS
     ====================================================== -->
<?php if ($ultimos_partidos): ?>
<section class="section">
  <div class="container">
    <p class="section-eyebrow">Resultados recientes</p>
    <h2 class="section-title">Últimos Partidos</h2>

    <div class="partidos-list mb-4">
      <?php foreach (array_reverse($ultimos_partidos) as $p): ?>
      <div class="partido-card">
        <div class="partido-equipo">
          <span class="partido-nombre"><?= epl_h($p['local_nombre']) ?></span>
          <?php if ($p['ganador_id'] == $p['equipo_local_id']): ?>
            <span class="badge badge-jugado">Ganador</span>
          <?php endif; ?>
        </div>
        <div class="partido-resultado">
          <span class="resultado-score"><?= $p['sets_local'] ?> – <?= $p['sets_visitante'] ?></span>
          <?php
            $sets = [];
            for ($s=1;$s<=3;$s++) {
              $gl = $p["games_s{$s}_local"]; $gv = $p["games_s{$s}_visitante"];
              if ($gl !== null) $sets[] = "$gl-$gv";
            }
          ?>
          <span class="resultado-sets"><?= implode('  ', $sets) ?></span>
          <span class="badge badge-jugado">Jugado</span>
        </div>
        <div class="partido-equipo right">
          <span class="partido-nombre"><?= epl_h($p['visitante_nombre']) ?></span>
          <?php if ($p['ganador_id'] == $p['equipo_visitante_id']): ?>
            <span class="badge badge-jugado">Ganador</span>
          <?php endif; ?>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <a href="resultados.php" class="btn btn-outline-navy">Ver todos los resultados →</a>
  </div>
</section>
<?php endif; ?>

<!-- ======================================================
     PRÓXIMOS PARTIDOS
     ====================================================== -->
<?php if ($proximos): ?>
<section class="section" style="background:var(--navy); color:var(--white)">
  <div class="container">
    <p class="section-eyebrow" style="color:var(--gold)">Agenda</p>
    <h2 class="section-title" style="color:var(--white)">Próximos Partidos</h2>

    <div class="partidos-list">
      <?php foreach ($proximos as $p): ?>
      <div class="partido-card" style="background:rgba(255,255,255,.06); color:var(--white)">
        <div class="partido-equipo">
          <span class="partido-nombre" style="color:var(--white)"><?= epl_h($p['local_nombre']) ?></span>
        </div>
        <div class="partido-resultado">
          <span style="font-size:.8rem;color:var(--gold);font-weight:700">
            <?= $p['fecha_programada'] ? date('d/m H:i', strtotime($p['fecha_programada'])) : 'Por definir' ?>
          </span>
          <span class="badge badge-pendiente">Pendiente</span>
        </div>
        <div class="partido-equipo right">
          <span class="partido-nombre" style="color:var(--white)"><?= epl_h($p['visitante_nombre']) ?></span>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<!-- ======================================================
     CTA JUGADORES
     ====================================================== -->
<section class="section">
  <div class="container text-center">
    <p class="section-eyebrow">¿Eres jugador?</p>
    <h2 class="section-title">Ingresa a tu cuenta</h2>
    <p class="section-desc" style="margin:0 auto 2rem">Revisa tu agenda, registra resultados y ve tu posición en la tabla.</p>
    <?php if (!epl_jugador_actual()): ?>
      <a href="login.php" class="btn btn-primary btn-lg">Iniciar Sesión</a>
    <?php else: ?>
      <a href="dashboard.php" class="btn btn-primary btn-lg">Ir al Dashboard →</a>
    <?php endif; ?>
  </div>
</section>

<?php require_once 'includes/footer.php'; ?>
