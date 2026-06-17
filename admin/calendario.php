<?php
$page_title = 'Admin — Calendario';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
epl_require_admin();

$db = epl_db();
$cal_ligas_opciones = $db->query("SELECT id, nombre FROM ligas ORDER BY id DESC")->fetchAll();
$cal_liga_ids = array_map(static fn($l) => (int)$l['id'], $cal_ligas_opciones);
$cal_editable = true; // el admin puede abrir el modal de edición

// Árbol de recintos para el modal de edición de partido
$_recintos_raw = $db->query("SELECT id, nombre, superior_id FROM recintos ORDER BY nombre")->fetchAll();
$_rec_roots = []; $_rec_children = [];
foreach ($_recintos_raw as $_r) {
    if (!$_r['superior_id']) $_rec_roots[] = $_r;
    else $_rec_children[$_r['superior_id']][] = $_r;
}
$todos_recintos = [];
function _flattenRecintosCal(array $nodes, array $children, int $depth, array &$out): void {
    foreach ($nodes as $n) {
        $prefix = $depth === 0 ? '🏛 ' : ($depth === 1 ? '   📍 ' : '      🎾 ');
        $out[] = ['id' => $n['id'], 'label' => $prefix . $n['nombre']];
        if (isset($children[$n['id']])) _flattenRecintosCal($children[$n['id']], $children, $depth + 1, $out);
    }
}
_flattenRecintosCal($_rec_roots, $_rec_children, 0, $todos_recintos);
?>
<?php require_once '../includes/header.php'; ?>

<div class="dash-layout">
  <?php include __DIR__ . '/partials/sidebar.php'; ?>
  <main class="dash-main">

    <div class="dash-header">
      <h1 class="dash-title">Calendario</h1>
      <p style="color:var(--gray-400);font-size:.88rem">Todos los partidos por día. Pinchá un día y luego un partido para editarlo.</p>
    </div>

    <?php if (!$cal_liga_ids): ?>
      <div class="alert alert-info">No hay ligas creadas todavía.</div>
    <?php else: ?>
      <?php include __DIR__ . '/../includes/calendario_partial.php'; ?>
      <?php $modal_return_to = 'calendario.php?mes=' . $mes . '&liga=' . $liga_sel; ?>
      <?php include __DIR__ . '/../includes/modal_editar_partido.php'; ?>
    <?php endif; ?>
  </main>
</div>

<?php require_once '../includes/footer.php'; ?>
