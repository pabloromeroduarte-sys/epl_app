<?php
$page_title = 'Admin — Calendario';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
epl_require_admin();

$db = epl_db();
$cal_ligas_opciones = $db->query("SELECT id, nombre FROM ligas ORDER BY id DESC")->fetchAll();
$cal_liga_ids = array_map(static fn($l) => (int)$l['id'], $cal_ligas_opciones);
?>
<?php require_once '../includes/header.php'; ?>

<div class="dash-layout">
  <?php include __DIR__ . '/partials/sidebar.php'; ?>
  <main class="dash-main">

    <div class="dash-header">
      <h1 class="dash-title">Calendario</h1>
      <p style="color:var(--gray-400);font-size:.88rem">Todos los partidos por día. Filtrá por liga.</p>
    </div>

    <?php if (!$cal_liga_ids): ?>
      <div class="alert alert-info">No hay ligas creadas todavía.</div>
    <?php else: ?>
      <?php include __DIR__ . '/../includes/calendario_partial.php'; ?>
    <?php endif; ?>
  </main>
</div>

<?php require_once '../includes/footer.php'; ?>
