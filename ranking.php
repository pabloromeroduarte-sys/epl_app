<?php
declare(strict_types=1);

$page_title       = 'Ranking Individual';
$active_nav       = 'ranking';
$page_css         = 'ranking';
$meta_description = 'Ranking individual Elite Padel League: resultados vigentes de los últimos 365 días en ligas de 4ta y 5ta categoría.';
$meta_keywords    = 'ranking individual padel chile, ranking padel 52 semanas, ligas padel santiago, elite padel league ranking';

require_once 'includes/functions.php';

$db = epl_db();
$ranking = epl_ranking_top(100);
$totalPuntos = array_sum(array_map(static fn(array $r): int => (int)$r['puntos'], $ranking));
$movimientosVigentes = (int)$db->query("SELECT COUNT(*) FROM ranking_movimientos WHERE anulado_at IS NULL AND fecha_vencimiento>CURDATE()")->fetchColumn();
$escala4 = epl_ranking_escala_categoria(4);
$escala5 = epl_ranking_escala_categoria(5);

require_once 'includes/header.php';
?>

<div class="ranking-page">
  <header class="ranking-hero">
    <div class="ranking-hero__number" aria-hidden="true">52</div>
    <div class="ranking-hero__inner">
      <div class="ranking-hero__copy">
        <span class="ranking-kicker"><i></i> Actualizado con cada resultado</span>
        <p>Ranking global Elite Padel League</p>
        <h1>Tu nivel.<br><span>Tu recorrido.</span></h1>
        <div class="ranking-hero__lead">Cada victoria de liga y cada posición final suman a tu cuenta personal. Los puntos duran exactamente 365 días, por lo que el ranking siempre refleja tu rendimiento más reciente.</div>
        <a href="#tabla-ranking" class="ranking-btn ranking-btn--gold">Ver Top 100 →</a>
      </div>
      <div class="ranking-hero__stats">
        <div><strong><?= count($ranking) ?></strong><span>Jugadores<br>con puntos</span></div>
        <div><strong><?= number_format($totalPuntos, 0, ',', '.') ?></strong><span>Puntos<br>vigentes</span></div>
        <div><strong><?= $movimientosVigentes ?></strong><span>Resultados y<br>premios activos</span></div>
      </div>
    </div>
  </header>

  <main class="ranking-main">
    <section class="ranking-toolbar">
      <div><span>Ventana móvil de 365 días</span><strong>Ranking individual</strong></div>
      <div style="max-width:420px;color:#667789;font-size:.75rem;line-height:1.6">Sin divisiones separadas: 4ta y 5ta alimentan una sola clasificación. En igualdad de puntos se consideran las victorias y luego el mejor resultado en 4ta y 5ta.</div>
    </section>

    <?php if ($ranking): ?>
    <section class="ranking-podium" aria-label="Podio individual">
      <?php foreach ([1, 0, 2] as $indice): if (empty($ranking[$indice])) continue;
        $r = $ranking[$indice]; $pos = $indice + 1; $nombre = trim($r['nombre'].' '.$r['apellido']); ?>
      <article class="ranking-podium__card ranking-podium__card--<?= $pos ?>">
        <span class="ranking-podium__pos"><?= str_pad((string)$pos, 2, '0', STR_PAD_LEFT) ?></span>
        <div class="ranking-podium__duo"><img src="<?= epl_h(epl_foto_jugador($r['foto'], $nombre)) ?>" alt="<?= epl_h($nombre) ?>"></div>
        <strong><?= epl_h($nombre) ?></strong>
        <small><?= $r['alias'] ? '“'.epl_h($r['alias']).'”' : 'Elite Padel League' ?></small>
        <span class="ranking-podium__meta"><?= (int)$r['victorias'] ?> victorias · <?= (int)$r['premios'] ?> premios</span>
        <b><?= (int)$r['puntos'] ?> <i>pts</i></b>
      </article>
      <?php endforeach; ?>
    </section>

    <section class="ranking-table-card" id="tabla-ranking">
      <div class="ranking-table-card__head"><span>Posición</span><span>Jugador</span><span>Victorias</span><span>Premios</span><span>Puntos</span></div>
      <?php foreach ($ranking as $i => $r):
        $nombre = trim($r['nombre'].' '.$r['apellido']); $mejor = [];
        if ($r['mejor_4ta']) $mejor[] = (int)$r['mejor_4ta'].'° en 4ta';
        if ($r['mejor_5ta']) $mejor[] = (int)$r['mejor_5ta'].'° en 5ta'; ?>
      <div class="ranking-table-row">
        <b><?= str_pad((string)($i + 1), 2, '0', STR_PAD_LEFT) ?></b>
        <span class="ranking-table-row__player">
          <span class="ranking-table-row__duo"><img src="<?= epl_h(epl_foto_jugador($r['foto'], $nombre)) ?>" alt="" loading="lazy"></span>
          <span><strong><?= epl_h($nombre) ?></strong><small><?= $mejor ? epl_h(implode(' · ', $mejor)) : 'Puntos por partidos ganados' ?><?= (int)$r['vence_30_dias'] > 0 ? ' · Vencen '.(int)$r['vence_30_dias'].' pts pronto' : '' ?></small></span>
        </span>
        <span><?= (int)$r['victorias'] ?></span><span><?= (int)$r['premios'] ?></span><em><?= (int)$r['puntos'] ?> <small>pts</small></em>
      </div>
      <?php endforeach; ?>
    </section>
    <?php else: ?>
    <section class="ranking-zero"><span>00</span><div><p>El sistema ya está listo</p><h2>El primer resultado abrirá el ranking.</h2><div>Cada triunfo registrado sumará 3 puntos individuales. Al finalizar una liga o americano, el Top 5 recibirá además el premio correspondiente a 4ta o 5ta.</div></div></section>
    <?php endif; ?>

    <section class="ranking-rules">
      <div class="ranking-rules__intro">
        <span>Cómo suma</span><h2>Todo cuenta durante un año.</h2>
        <p>Una victoria de liga entrega 3 puntos a cada jugador que realmente disputó el partido, incluyendo WO. Los americanos y el cierre de cada liga entregan premios al Top 5. Cada movimiento vence 365 días después de su propia fecha.</p>
      </div>
      <div>
        <p style="margin:0 0 .6rem;color:#7b8a9b;font-size:.55rem;font-weight:900;letter-spacing:.12em;text-transform:uppercase">Premios 4ta</p>
        <div class="ranking-points-scale"><?php foreach ($escala4 as $puesto => $puntos): ?><div><span><?= str_pad((string)$puesto, 2, '0', STR_PAD_LEFT) ?></span><strong><?= $puntos ?></strong><small><?= $puesto ?>° lugar</small></div><?php endforeach; ?></div>
        <p style="margin:1rem 0 .6rem;color:#7b8a9b;font-size:.55rem;font-weight:900;letter-spacing:.12em;text-transform:uppercase">Premios 5ta</p>
        <div class="ranking-points-scale"><?php foreach ($escala5 as $puesto => $puntos): ?><div><span><?= str_pad((string)$puesto, 2, '0', STR_PAD_LEFT) ?></span><strong><?= $puntos ?></strong><small><?= $puesto ?>° lugar</small></div><?php endforeach; ?></div>
      </div>
    </section>
  </main>
</div>

<?php require_once 'includes/footer.php'; ?>
