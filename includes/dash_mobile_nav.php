<?php
// Barra de navegación inferior para móvil en páginas del dashboard
// Uso: include __DIR__ . '/includes/dash_mobile_nav.php';
// Variable opcional: $dash_active (string) con el nombre de la página activa
$_dact = $dash_active ?? '';
$_notif_count = 0;
$_nav_jugador = epl_jugador_actual();
if ($_nav_jugador) {
    $_notif_count = epl_notif_no_leidas((int)$_nav_jugador['id']);
}
?>
<nav class="dash-bottom-nav">
  <div class="dash-bottom-nav-inner">

    <a href="dashboard.php" class="dash-bottom-link <?= $_dact==='dashboard'?'active':'' ?>">
      <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
      Inicio
    </a>

    <a href="ingresar_resultado.php" class="dash-bottom-link <?= $_dact==='resultado'?'active':'' ?>">
      <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>
      Resultado
    </a>

    <a href="reprogramar.php" class="dash-bottom-link <?= ($_dact==='reprogramar'||$_dact==='suplentes')?'active':'' ?>">
      <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
      Reprog.
    </a>

    <a href="clasificacion.php" class="dash-bottom-link <?= $_dact==='clasificacion'?'active':'' ?>">
      <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
      Tabla
    </a>

    <a href="<?= epl_url('notificaciones.php') ?>" class="dash-bottom-link <?= $_dact==='notificaciones'?'active':'' ?>" style="position:relative">
      <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
      <?php if ($_notif_count > 0): ?>
        <span style="position:absolute;top:2px;right:14px;background:#ef4444;color:#fff;font-size:10px;font-weight:800;border-radius:999px;min-width:16px;height:16px;display:flex;align-items:center;justify-content:center;padding:0 3px"><?= $_notif_count > 9 ? '9+' : $_notif_count ?></span>
      <?php endif; ?>
      Notif.
    </a>

    <a href="mi_perfil.php" class="dash-bottom-link <?= ($_dact==='perfil'||$_dact==='inscribirse')?'active':'' ?>">
      <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
      Perfil
    </a>

  </div>
</nav>
