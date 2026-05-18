<?php
$page_title = 'Regularizar partidos';
$player_tab = 'reprogramar';
require_once 'includes/auth.php';
require_once 'includes/functions.php';
epl_require_login();

$jugador = epl_jugador_actual();
$db      = epl_db();
$liga    = epl_liga_activa();
$equipo  = $liga ? epl_equipo_del_jugador($jugador['id'], $liga['id']) : null;

$atrasados = [];
if ($equipo) {
    $hoy = date('Y-m-d H:i:s');
    $st  = $db->prepare("
        SELECT p.*, el.nombre AS local_nombre, ev.nombre AS visitante_nombre
        FROM partidos p
        JOIN equipos el ON el.id = p.equipo_local_id
        JOIN equipos ev ON ev.id = p.equipo_visitante_id
        WHERE (p.equipo_local_id=? OR p.equipo_visitante_id=?)
          AND (
            (p.estado='pendiente' AND p.fecha_programada IS NOT NULL AND p.fecha_programada < ?)
            OR p.estado='reprogramado'
          )
        ORDER BY p.fecha_programada ASC
    ");
    $st->execute([$equipo['id'], $equipo['id'], $hoy]);
    $atrasados = $st->fetchAll();
}
?>
<?php require_once 'includes/header.php'; ?>

<div class="dash-layout">
<?php include __DIR__ . '/includes/player_sidebar.php'; ?>
<main class="dash-main">

  <div class="dash-header">
    <div>
      <h1 class="dash-title">Regularizar partidos</h1>
      <p style="color:var(--gray-500);font-size:.88rem;margin-top:.25rem">
        <?= count($atrasados) ?> partido<?= count($atrasados) !== 1 ? 's' : '' ?> con fecha vencida o pendiente de coordinar.
      </p>
    </div>
    <a href="dashboard.php" class="btn btn-sm" style="background:var(--gray-100);color:var(--navy)">← Volver</a>
  </div>

  <?php if (empty($atrasados)): ?>
  <div style="text-align:center;padding:3rem 1rem;color:var(--gray-400)">
    <div style="font-size:2.5rem;margin-bottom:.75rem">✅</div>
    <h3 style="font-family:var(--font-head);text-transform:uppercase;color:var(--navy);margin:0 0 .5rem">Todo al día</h3>
    <p>No tienes partidos pendientes de regularizar.</p>
    <a href="dashboard.php" class="btn btn-primary" style="margin-top:1rem">Volver al inicio</a>
  </div>

  <?php else: ?>
  <div style="display:flex;flex-direction:column;gap:1rem">
    <?php foreach ($atrasados as $p):
      $vencida = $p['fecha_programada'] && $p['fecha_programada'] < date('Y-m-d H:i:s');
      $label   = $vencida ? '⚠️ Fecha vencida' : '🔄 Reprogramado';
      $color   = $vencida ? '#b91c1c' : '#b45309';
      $bg      = $vencida ? '#fef2f2' : '#fffbeb';
      $border  = $vencida ? '#fca5a5' : '#fcd34d';
    ?>
    <div style="background:<?= $bg ?>;border:1.5px solid <?= $border ?>;border-radius:18px;padding:1.4rem 1.5rem">

      <!-- Cabecera -->
      <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1rem;flex-wrap:wrap;gap:.5rem">
        <div style="display:flex;align-items:center;gap:.6rem">
          <span style="font-size:.72rem;font-weight:800;color:<?= $color ?>;background:<?= $border ?>;padding:.25rem .7rem;border-radius:20px;text-transform:uppercase;letter-spacing:.05em"><?= $label ?></span>
          <?php if ($p['jornada']): ?>
            <span style="font-size:.72rem;color:var(--gray-500);font-weight:600">Fecha <?= epl_h($p['jornada']) ?></span>
          <?php endif; ?>
        </div>
        <span style="font-size:.8rem;color:<?= $color ?>;font-weight:700">
          <?= $p['fecha_programada'] ? date('d/m/Y', strtotime($p['fecha_programada'])) : 'Sin fecha' ?>
        </span>
      </div>

      <!-- Partido -->
      <div style="display:flex;align-items:center;justify-content:center;gap:1.25rem;margin-bottom:1.4rem;flex-wrap:wrap">
        <div style="text-align:right;flex:1;min-width:80px">
          <span style="font-family:var(--font-head);font-size:1rem;color:var(--navy)"><?= epl_h($p['local_nombre']) ?></span>
        </div>
        <div style="background:var(--navy);color:var(--gold);font-family:var(--font-head);font-size:.85rem;padding:.4rem .9rem;border-radius:8px;flex-shrink:0">VS</div>
        <div style="text-align:left;flex:1;min-width:80px">
          <span style="font-family:var(--font-head);font-size:1rem;color:var(--navy)"><?= epl_h($p['visitante_nombre']) ?></span>
        </div>
      </div>

      <!-- Acciones -->
      <div style="display:flex;gap:.75rem;flex-wrap:wrap">
        <a href="<?= epl_url('ingresar_resultado.php?partido_id=' . $p['id']) ?>"
           class="btn btn-primary"
           style="flex:1;justify-content:center;min-width:140px;font-size:.88rem">
          ✅ Ya se jugó — Ingresar resultado
        </a>
        <a href="<?= epl_url('reprogramar.php?partido_id=' . $p['id']) ?>"
           class="btn btn-sm"
           style="flex:1;justify-content:center;min-width:140px;font-size:.88rem;background:#fff;border:1.5px solid <?= $border ?>;color:<?= $color ?>;font-weight:700;padding:.7rem 1rem">
          📅 Cambiar fecha
        </a>
      </div>

    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

</main>
</div>

<?php require_once 'includes/footer.php'; ?>
