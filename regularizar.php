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
        SELECT p.*,
               el.nombre AS local_nombre,  ev.nombre AS visitante_nombre,
               jl1.nombre AS l1n, jl1.apellido AS l1a, jl1.telefono AS l1t,
               jl2.nombre AS l2n, jl2.apellido AS l2a, jl2.telefono AS l2t,
               jv1.nombre AS v1n, jv1.apellido AS v1a, jv1.telefono AS v1t,
               jv2.nombre AS v2n, jv2.apellido AS v2a, jv2.telefono AS v2t
        FROM partidos p
        JOIN equipos el ON el.id = p.equipo_local_id
        JOIN equipos ev ON ev.id = p.equipo_visitante_id
        LEFT JOIN jugadores jl1 ON jl1.id = el.jugador1_id
        LEFT JOIN jugadores jl2 ON jl2.id = el.jugador2_id
        LEFT JOIN jugadores jv1 ON jv1.id = ev.jugador1_id
        LEFT JOIN jugadores jv2 ON jv2.id = ev.jugador2_id
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
      $vencida  = $p['fecha_programada'] && $p['fecha_programada'] < date('Y-m-d H:i:s');
      $label    = $vencida ? '⚠️ Fecha vencida' : '🔄 Reprogramado';
      $color    = $vencida ? '#b91c1c' : '#b45309';
      $bg       = $vencida ? '#fef2f2' : '#fffbeb';
      $border   = $vencida ? '#fca5a5' : '#fcd34d';

      // Determinar quién es local/visitante y extraer rivales
      $esLocal  = ($p['equipo_local_id'] == $equipo['id']);
      $rivales  = [];
      if ($esLocal) {
          $rivales[] = ['n' => $p['v1n'], 'a' => $p['v1a'], 't' => $p['v1t']];
          $rivales[] = ['n' => $p['v2n'], 'a' => $p['v2a'], 't' => $p['v2t']];
      } else {
          $rivales[] = ['n' => $p['l1n'], 'a' => $p['l1a'], 't' => $p['l1t']];
          $rivales[] = ['n' => $p['l2n'], 'a' => $p['l2a'], 't' => $p['l2t']];
      }
      $rivales = array_filter($rivales, fn($r) => !empty($r['n']));
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

      <!-- Contacto rival -->
      <?php if ($rivales): ?>
      <div style="background:rgba(255,255,255,.7);border-radius:12px;padding:.9rem 1rem;margin-bottom:1rem">
        <div style="font-size:.72rem;font-weight:800;color:var(--gray-500);text-transform:uppercase;letter-spacing:.06em;margin-bottom:.6rem">📲 Contactar rivales</div>
        <div style="display:flex;flex-wrap:wrap;gap:.6rem">
          <?php foreach ($rivales as $r):
            $tel   = preg_replace('/\D/', '', $r['t'] ?? '');
            $clean = $tel ? (str_starts_with($tel, '56') ? $tel : '56' . $tel) : '';
            $msg   = rawurlencode('Hola ' . $r['n'] . ', te contacto por el partido de la Liga Elite Padel. ¿Podemos coordinar la fecha?');
            $wsp   = $clean ? 'https://wa.me/' . $clean . '?text=' . $msg : null;
            $iniciales = strtoupper(substr($r['n'], 0, 1) . substr($r['a'] ?? '', 0, 1));
          ?>
          <div style="display:flex;align-items:center;gap:.6rem;background:#fff;border:1px solid #e5e7eb;border-radius:10px;padding:.55rem .85rem;flex:1;min-width:160px">
            <div style="width:32px;height:32px;border-radius:50%;background:linear-gradient(135deg,var(--navy),#1a3a64);color:#fff;font-weight:800;font-size:.72rem;display:flex;align-items:center;justify-content:center;flex-shrink:0"><?= $iniciales ?></div>
            <div style="flex:1;min-width:0">
              <div style="font-weight:700;font-size:.82rem;color:var(--navy);white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?= epl_h($r['n'] . ' ' . ($r['a'] ?? '')) ?></div>
            </div>
            <?php if ($wsp): ?>
              <a href="<?= $wsp ?>" target="_blank"
                 style="display:inline-flex;align-items:center;gap:.35rem;background:#16a34a;color:#fff;font-size:.72rem;font-weight:700;padding:.35rem .75rem;border-radius:8px;text-decoration:none;white-space:nowrap;flex-shrink:0">
                <svg width="13" height="13" fill="currentColor" viewBox="0 0 24 24"><path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.588-5.946 0-6.556 5.332-11.888 11.888-11.888 3.176 0 6.161 1.237 8.404 3.48s3.481 5.229 3.481 8.404c0 6.556-5.332 11.888-11.888 11.888-2.003 0-3.963-.505-5.698-1.465l-6.305 1.693zm6.443-4.045c1.474.873 3.103 1.332 4.775 1.332 5.054 0 9.163-4.109 9.163-9.163s-4.109-9.163-9.163-9.163-9.163 4.109-9.163 9.163c0 1.95.623 3.856 1.799 5.437l-1.002 3.659 3.743-.999zm10.742-5.466c-.303-.151-1.788-.882-2.067-.981-.278-.099-.481-.151-.683.151-.202.303-.783.981-.96 1.183-.177.202-.354.227-.657.076-.303-.151-1.28-.471-2.438-1.504-.901-.803-1.508-1.796-1.685-2.098-.177-.302-.019-.465.132-.615.136-.135.303-.354.455-.53.151-.177.202-.303.303-.505.101-.202.051-.379-.025-.53-.076-.151-.683-1.643-.935-2.249-.245-.59-.495-.51-.683-.52l-.582-.01c-.202 0-.531.076-.809.379-.278.303-1.062 1.037-1.062 2.529 0 1.492 1.087 2.932 1.239 3.134.151.202 2.14 3.268 5.184 4.582.724.312 1.29.499 1.731.639.727.231 1.388.199 1.911.121.582-.087 1.788-.731 2.041-1.439.253-.708.253-1.313.177-1.439-.076-.126-.278-.202-.581-.353z"/></svg>
                WhatsApp
              </a>
            <?php else: ?>
              <span style="font-size:.7rem;color:var(--gray-400);font-style:italic">Sin teléfono</span>
            <?php endif; ?>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>

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
