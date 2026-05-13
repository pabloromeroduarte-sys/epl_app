<?php
$page_title = 'Torneos';
$active_nav = 'torneos';
require_once 'includes/functions.php';

$db = epl_db();
$ligas = $db->query("SELECT * FROM ligas ORDER BY FIELD(estado,'activa','inscripcion','proximamente','finalizada'), id DESC")->fetchAll();
?>
<?php require_once 'includes/header.php'; ?>

<section class="section-sm" style="background:var(--navy)">
  <div class="container">
    <p class="section-eyebrow" style="color:var(--gold)">Competencias EPL</p>
    <h1 class="section-title" style="color:var(--white)">Torneos</h1>
  </div>
</section>

<section class="section">
  <div class="container">

    <?php if (empty($ligas)): ?>
      <p style="color:var(--gray-400)">No hay torneos disponibles aún.</p>
    <?php else: ?>

    <?php
    $badge = [
      'proximamente' => ['label'=>'Próximamente',  'color'=>'#5B21B6','bg'=>'#EDE9FE'],
      'inscripcion'  => ['label'=>'Inscripciones', 'color'=>'#92400E','bg'=>'#FEF3C7'],
      'activa'       => ['label'=>'En juego',      'color'=>'#065F46','bg'=>'#D1FAE5'],
      'finalizada'   => ['label'=>'Finalizado',    'color'=>'#374151','bg'=>'#F3F4F6'],
    ];
    ?>

    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(310px,1fr));gap:1.5rem">
      <?php foreach ($ligas as $l):
        $b = $badge[$l['estado']] ?? $badge['activa'];
        // Contar equipos y partidos
        $n_equipos = $db->query("SELECT COUNT(*) FROM liga_equipos WHERE liga_id={$l['id']}")->fetchColumn();
        $n_jugados = $db->query("SELECT COUNT(*) FROM partidos WHERE liga_id={$l['id']} AND estado='jugado'")->fetchColumn();
      ?>
      <a href="torneo.php?id=<?= $l['id'] ?>" style="text-decoration:none;display:block" class="card">
        <!-- Portada -->
        <div style="height:140px;background:var(--navy);position:relative;overflow:hidden">
          <?php if ($l['foto_portada']): ?>
            <img src="<?= epl_url('uploads/ligas/'.$l['foto_portada']) ?>" alt="" style="width:100%;height:100%;object-fit:cover;opacity:.7">
          <?php else: ?>
            <div style="position:absolute;inset:0;display:flex;align-items:center;justify-content:center;font-family:var(--font-head);font-size:3rem;color:rgba(255,255,255,.08)">EPL</div>
          <?php endif; ?>
          <span style="position:absolute;top:.75rem;left:.75rem;padding:.25rem .75rem;border-radius:50px;font-size:.68rem;font-weight:700;text-transform:uppercase;letter-spacing:.08em;background:<?= $b['bg'] ?>;color:<?= $b['color'] ?>">
            <?= $b['label'] ?>
          </span>
        </div>

        <!-- Info -->
        <div style="padding:1.25rem">
          <h3 style="font-family:var(--font-head);font-size:1.1rem;text-transform:uppercase;color:var(--navy);line-height:1.2;margin-bottom:.35rem"><?= epl_h($l['nombre']) ?></h3>

          <?php if ($l['temporada'] || $l['categoria']): ?>
            <p style="font-size:.78rem;color:var(--gold);font-weight:600;margin-bottom:.5rem">
              <?= $l['temporada']?epl_h($l['temporada']).' ':'' ?><?= $l['categoria']?'· '.$l['categoria'].'ª cat.':'' ?>
            </p>
          <?php endif; ?>

          <?php if ($l['sede']): ?>
            <p style="font-size:.78rem;color:var(--gray-400);margin-bottom:.4rem">📍 <?= epl_h($l['sede']) ?></p>
          <?php endif; ?>

          <?php if ($l['fecha_inicio']): ?>
            <p style="font-size:.75rem;color:var(--gray-400);margin-bottom:.75rem">
              📅 <?= date('d/m/Y', strtotime($l['fecha_inicio'])) ?>
              <?= $l['fecha_fin']?' → '.date('d/m/Y', strtotime($l['fecha_fin'])):'' ?>
            </p>
          <?php endif; ?>

          <div style="display:flex;align-items:center;justify-content:space-between">
            <div style="display:flex;gap:1rem">
              <div style="text-align:center">
                <div style="font-family:var(--font-head);font-size:1.3rem;color:var(--navy)"><?= $n_equipos ?></div>
                <div style="font-size:.65rem;color:var(--gray-400);text-transform:uppercase">Equipos</div>
              </div>
              <div style="text-align:center">
                <div style="font-family:var(--font-head);font-size:1.3rem;color:var(--navy)"><?= $n_jugados ?></div>
                <div style="font-size:.65rem;color:var(--gray-400);text-transform:uppercase">Jugados</div>
              </div>
              <?php if ($l['precio']): ?>
              <div style="text-align:center">
                <div style="font-family:var(--font-head);font-size:1.3rem;color:var(--gold)"><?= '$'.number_format($l['precio'],0,',','.') ?></div>
                <div style="font-size:.65rem;color:var(--gray-400);text-transform:uppercase">Ins. c/u</div>
              </div>
              <?php endif; ?>
            </div>
            <span style="font-size:.8rem;font-weight:700;color:var(--gold)">Ver →</span>
          </div>
        </div>
      </a>
      <?php endforeach; ?>
    </div>

    <?php endif; ?>
  </div>
</section>

<?php require_once 'includes/footer.php'; ?>
