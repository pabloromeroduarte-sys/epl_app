<?php
$page_title       = 'Resultados de Partidos';
$active_nav       = 'resultados';
$meta_description = 'Resultados oficiales Elite Padel League: partidos jugados, pendientes y reprogramados de cada liga, organizados por jornada. Actualizado al instante.';
$meta_keywords    = 'resultados padel, partidos padel chile, EPL resultados, fixture padel santiago, jornadas padel';
require_once 'includes/functions.php';

$db    = epl_db();
$ligas = $db->query("SELECT * FROM ligas ORDER BY id DESC")->fetchAll();
$liga_id = isset($_GET['liga']) ? (int)$_GET['liga'] : ($ligas[0]['id'] ?? 0);
$estado  = $_GET['estado'] ?? '';
$estados_validos = ['','jugado','pendiente','reprogramado','walkover'];
if (!in_array($estado, $estados_validos)) $estado = '';

$partidos = $liga_id ? epl_partidos_liga($liga_id, $estado) : [];

// Agrupar por jornada
$por_jornada = [];
foreach ($partidos as $p) {
    $j = $p['jornada'] ?? 0;
    $por_jornada[$j][] = $p;
}
krsort($por_jornada);
?>
<?php require_once 'includes/header.php'; ?>

<!-- HERO premium -->
<section class="epl-hero epl-hero-sm" style="background-image: linear-gradient(135deg, rgba(28,47,72,.95) 0%, rgba(10,20,33,.92) 100%), url('<?= epl_url('assets/img/landing/accion-padel.jpg') ?>')">
  <div class="epl-container">
    <span class="epl-eyebrow">Fixture & resultados</span>
    <h1>Partidos <span class="epl-hero-gold">de la liga</span></h1>
    <p>Todos los resultados oficiales, organizados por jornada. Filtrá por estado para ver lo que te interesa.</p>
  </div>
</section>

<section class="section">
  <div class="container">

    <!-- Filtros -->
    <form method="get" class="flex gap-2 mb-4" style="flex-wrap:wrap;align-items:center">
      <?php if (count($ligas) > 1): ?>
      <select name="liga" class="form-control" style="width:auto" onchange="this.form.submit()">
        <?php foreach ($ligas as $l): ?>
          <option value="<?= $l['id'] ?>" <?= $l['id']==$liga_id?'selected':'' ?>><?= epl_h($l['nombre']) ?></option>
        <?php endforeach; ?>
      </select>
      <?php else: ?>
        <input type="hidden" name="liga" value="<?= $liga_id ?>">
      <?php endif; ?>

      <select name="estado" class="form-control" style="width:auto" onchange="this.form.submit()">
        <option value="">Todos</option>
        <option value="jugado"      <?= $estado==='jugado'?'selected':'' ?>>Jugados</option>
        <option value="pendiente"   <?= $estado==='pendiente'?'selected':'' ?>>Pendientes</option>
        <option value="reprogramado"<?= $estado==='reprogramado'?'selected':'' ?>>Reprogramados</option>
        <option value="walkover"    <?= $estado==='walkover'?'selected':'' ?>>Walkover</option>
      </select>
    </form>

    <?php if (empty($por_jornada)): ?>
      <div class="card card-body text-center" style="padding:3rem">
        <p style="color:var(--gray-400)">No hay partidos para mostrar.</p>
      </div>
    <?php else: ?>
      <?php foreach ($por_jornada as $jornada => $ps): ?>
        <?php if ($jornada): ?>
        <h3 style="font-family:var(--font-head);font-size:1.1rem;text-transform:uppercase;color:var(--navy);margin-bottom:.75rem;margin-top:2rem;letter-spacing:.08em">
          Jornada <?= $jornada ?>
        </h3>
        <?php endif; ?>
        <div class="partidos-list mb-3">
          <?php foreach ($ps as $p): ?>
            <?php include 'includes/partido_card_v2.php'; ?>
          <?php endforeach; ?>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>

  </div>
</section>

<?php require_once 'includes/footer.php'; ?>
