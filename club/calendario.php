<?php
$page_title = 'Calendario';
$club_tab   = 'calendario';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
epl_require_club();

$club = epl_jugador_actual();
$cal_liga_ids = epl_club_ligas((int)$club['id']);
$cal_ligas_opciones = [];
if ($cal_liga_ids) {
    $in = implode(',', array_fill(0, count($cal_liga_ids), '?'));
    $st = epl_db()->prepare("SELECT id, nombre FROM ligas WHERE id IN ($in) ORDER BY id DESC");
    $st->execute($cal_liga_ids);
    $cal_ligas_opciones = $st->fetchAll();
}
?>
<?php require_once '../includes/header.php'; ?>

<div class="dash-layout">
  <?php include __DIR__ . '/../includes/club_sidebar.php'; ?>
  <main class="dash-main">

    <div class="dash-header">
      <h1 class="dash-title">Calendario</h1>
      <p style="color:var(--gray-400);font-size:.88rem">Pinchá un día para ver los partidos.</p>
    </div>

    <?php if (!$cal_liga_ids): ?>
      <div class="alert alert-info">Todavía no tenés ligas asignadas. Pedile al administrador que te asigne una.</div>
    <?php else: ?>
      <?php include __DIR__ . '/../includes/calendario_partial.php'; ?>
    <?php endif; ?>
  </main>
</div>

<?php require_once '../includes/footer.php'; ?>
