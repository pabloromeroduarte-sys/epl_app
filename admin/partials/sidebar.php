<?php
// Admin sidebar — include desde admin/*.php
$cur = basename($_SERVER['PHP_SELF']);
// Badge de disputas pendientes
$_disp_count = 0;
try {
    epl_ensure_disputas_schema();
    $_disp_count = (int)epl_db()->query("SELECT COUNT(*) FROM partido_disputas WHERE estado='pendiente'")->fetchColumn();
} catch (Throwable $_e) {}
?>
<aside class="dash-sidebar">
  <div style="padding:1.25rem 1rem 1rem;border-bottom:1px solid rgba(255,255,255,.08)">
    <div style="color:var(--gold);font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.15em">Administración</div>
    <div style="color:rgba(255,255,255,.6);font-size:.78rem;margin-top:.25rem">Elite Padel League</div>
  </div>
  <nav style="padding:1rem 0">
    <div class="dash-nav-section">
      <div class="dash-nav-label">General</div>
      <a href="index.php"     class="dash-nav-link <?= $cur==='index.php'    ?'active':'' ?>">Dashboard</a>
      <a href="jugadores.php"    class="dash-nav-link <?= $cur==='jugadores.php'   ?'active':'' ?>">Jugadores</a>
      <a href="ligas.php"        class="dash-nav-link <?= in_array($cur,['ligas.php','liga_detalle.php'])?'active':'' ?>">Ligas / Torneos</a>
      <a href="recintos.php"    class="dash-nav-link <?= $cur==='recintos.php'?'active':'' ?>" style="padding-left:1.75rem;font-size:.8rem;color:rgba(255,255,255,.65)">↳ Recintos</a>
      <a href="partidos.php"     class="dash-nav-link <?= $cur==='partidos.php'    ?'active':'' ?>">Partidos</a>
      <a href="dashboard_repro.php" class="dash-nav-link <?= $cur==='dashboard_repro.php'?'active':'' ?>">Reprogramaciones</a>
      <a href="inscripciones.php" class="dash-nav-link <?= $cur==='inscripciones.php'?'active':'' ?>">Inscripciones</a>
      <a href="suplentes.php"    class="dash-nav-link <?= $cur==='suplentes.php'   ?'active':'' ?>">Suplentes</a>
      <a href="erp_financiero.php" class="dash-nav-link <?= $cur==='erp_financiero.php'?'active':'' ?>" style="color:var(--gold)">💰 ERP Financiero</a>
      <a href="automatizaciones.php" class="dash-nav-link <?= $cur==='automatizaciones.php'?'active':'' ?>">✉ Automatizaciones</a>
      <a href="disputas.php" class="dash-nav-link <?= $cur==='disputas.php'?'active':'' ?>" style="display:flex;align-items:center;justify-content:space-between">
        <span>⚠️ Disputas</span>
        <?php if ($_disp_count > 0): ?>
        <span style="background:#dc2626;color:#fff;border-radius:50px;padding:.1rem .45rem;font-size:.65rem;font-weight:800;line-height:1.4"><?= $_disp_count ?></span>
        <?php endif; ?>
      </a>
      <a href="notificaciones.php" class="dash-nav-link <?= $cur==='notificaciones.php'?'active':'' ?>">🔔 Notificaciones Push</a>
      <a href="configuracion.php" class="dash-nav-link <?= $cur==='configuracion.php'?'active':'' ?>">⚙ Configuración</a>
    </div>
    <div class="dash-nav-section">
      <div class="dash-nav-label">Acciones</div>
      <a href="../clasificacion.php" class="dash-nav-link">Ver Clasificación</a>
      <a href="../resultados.php"    class="dash-nav-link">Ver Resultados</a>
    </div>
    <div class="dash-nav-section">
      <div class="dash-nav-label">Mi cuenta</div>
      <a href="../dashboard.php"  class="dash-nav-link <?= $cur==='dashboard.php'?'active':'' ?>">Mi Dashboard</a>
      <a href="../mi_perfil.php"  class="dash-nav-link <?= $cur==='mi_perfil.php'?'active':'' ?>">
        <svg style="width:16px;height:16px;flex-shrink:0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
        Mi Perfil
      </a>
    </div>
  </nav>
</aside>

<nav class="dash-bottom-nav">
  <div class="dash-bottom-nav-inner">
    <a href="index.php" class="dash-bottom-link <?= $cur==='index.php'?'active':'' ?>">
      <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
      <span>Panel</span>
    </a>
    <a href="jugadores.php" class="dash-bottom-link <?= $cur==='jugadores.php'?'active':'' ?>">
      <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
      <span>Jugadores</span>
    </a>
    <a href="ligas.php" class="dash-bottom-link <?= in_array($cur,['ligas.php','liga_detalle.php'])?'active':'' ?>">
      <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
      <span>Ligas</span>
    </a>
    <a href="partidos.php" class="dash-bottom-link <?= $cur==='partidos.php'?'active':'' ?>">
      <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>
      <span>Partidos</span>
    </a>
    <a href="recintos.php" class="dash-bottom-link <?= $cur==='recintos.php'?'active':'' ?>">
      <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
      <span>Sedes</span>
    </a>
    <a href="dashboard_repro.php" class="dash-bottom-link <?= $cur==='dashboard_repro.php'?'active':'' ?>">
      <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
      <span>Reprog.</span>
    </a>
    <a href="inscripciones.php" class="dash-bottom-link <?= $cur==='inscripciones.php'?'active':'' ?>">
      <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
      <span>Inscrip.</span>
    </a>
    <a href="suplentes.php" class="dash-bottom-link <?= $cur==='suplentes.php'?'active':'' ?>">
      <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
      <span>Suplentes</span>
    </a>
    <a href="erp_financiero.php" class="dash-bottom-link <?= $cur==='erp_financiero.php'?'active':'' ?>">
      <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
      <span>ERP</span>
    </a>
    <a href="automatizaciones.php" class="dash-bottom-link <?= $cur==='automatizaciones.php'?'active':'' ?>">
      <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
      <span>Automát.</span>
    </a>
    <a href="notificaciones.php" class="dash-bottom-link <?= $cur==='notificaciones.php'?'active':'' ?>">
      <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
      <span>Push</span>
    </a>
    <a href="configuracion.php" class="dash-bottom-link <?= $cur==='configuracion.php'?'active':'' ?>">
      <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
      <span>Config.</span>
    </a>
    <a href="../clasificacion.php" class="dash-bottom-link">
      <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
      <span>Tablas</span>
    </a>
    <a href="../resultados.php" class="dash-bottom-link">
      <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m2 4H10m0 0l3-3m-3 3l3 3"/></svg>
      <span>Resultados</span>
    </a>
    <a href="../mi_perfil.php" class="dash-bottom-link <?= $cur==='mi_perfil.php'?'active':'' ?>">
      <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
      <span>Perfil</span>
    </a>
    <a href="../dashboard.php" class="dash-bottom-link">
      <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
      <span>Volver</span>
    </a>
  </div>
</nav>
<script>
// Centrar el ítem activo en la barra de navegación inferior al cargar la página
(function () {
  var nav = document.querySelector('.dash-bottom-nav-inner');
  var active = document.querySelector('.dash-bottom-link.active');
  if (!nav || !active) return;
  // Calcular offset para centrar el ítem activo
  var navW    = nav.offsetWidth;
  var itemLeft = active.offsetLeft;
  var itemW    = active.offsetWidth;
  nav.scrollLeft = itemLeft - (navW / 2) + (itemW / 2);
})();
</script>
