<?php
// Admin sidebar — include desde admin/*.php
$cur = basename($_SERVER['PHP_SELF']);

// Badge de disputas pendientes
$_disp_count = 0;
try {
    epl_ensure_disputas_schema();
    $_disp_count = (int)epl_db()->query("SELECT COUNT(*) FROM partido_disputas WHERE estado='pendiente'")->fetchColumn();
} catch (Throwable $_e) {}

// Detectar si la página actual está en el grupo Herramientas
$_in_tools = in_array($cur, ['automatizaciones.php','content_studio.php','notificaciones.php','configuracion.php']);
?>
<aside class="dash-sidebar">
  <div style="padding:1.25rem 1rem 1rem;border-bottom:1px solid rgba(255,255,255,.08)">
    <div style="color:var(--gold);font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.15em">Administración</div>
    <div style="color:rgba(255,255,255,.6);font-size:.78rem;margin-top:.25rem">Elite Padel League</div>
  </div>
  <nav style="padding:1rem 0">

    <!-- ── General ── -->
    <div class="dash-nav-section">
      <div class="dash-nav-label">General</div>
      <a href="index.php"           class="dash-nav-link <?= $cur==='index.php'?'active':'' ?>">Dashboard</a>
      <a href="jugadores.php"       class="dash-nav-link <?= $cur==='jugadores.php'?'active':'' ?>">Jugadores</a>
      <a href="ligas.php"           class="dash-nav-link <?= in_array($cur,['ligas.php','liga_detalle.php'])?'active':'' ?>">Ligas / Torneos</a>
      <a href="recintos.php"        class="dash-nav-link <?= $cur==='recintos.php'?'active':'' ?>" style="padding-left:1.75rem;font-size:.8rem;color:rgba(255,255,255,.65)">↳ Recintos</a>
      <a href="partidos.php"        class="dash-nav-link <?= $cur==='partidos.php'?'active':'' ?>">Partidos</a>
      <a href="dashboard_repro.php" class="dash-nav-link <?= $cur==='dashboard_repro.php'?'active':'' ?>">Reprogramaciones</a>
      <a href="inscripciones.php"   class="dash-nav-link <?= $cur==='inscripciones.php'?'active':'' ?>">Inscripciones</a>
      <a href="suplentes.php"       class="dash-nav-link <?= $cur==='suplentes.php'?'active':'' ?>">Suplentes</a>
      <a href="erp_financiero.php"  class="dash-nav-link <?= $cur==='erp_financiero.php'?'active':'' ?>" style="color:var(--gold)">💰 ERP Financiero</a>
      <a href="disputas.php"        class="dash-nav-link <?= $cur==='disputas.php'?'active':'' ?>" style="display:flex;align-items:center;justify-content:space-between">
        <span>⚠️ Disputas</span>
        <?php if ($_disp_count > 0): ?>
        <span style="background:#dc2626;color:#fff;border-radius:50px;padding:.1rem .45rem;font-size:.65rem;font-weight:800;line-height:1.4"><?= $_disp_count ?></span>
        <?php endif; ?>
      </a>
    </div>

    <!-- ── Herramientas del sistema (colapsable) ── -->
    <div class="dash-nav-section">
      <button id="tools-toggle" class="dash-tools-toggle <?= $_in_tools ? 'open' : '' ?>" onclick="toggleTools()" type="button">
        <span class="dash-nav-label" style="margin:0;pointer-events:none">Herramientas</span>
        <svg class="tools-arrow" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
        </svg>
      </button>
      <div id="tools-menu" class="dash-tools-menu <?= $_in_tools ? 'open' : '' ?>">
        <a href="automatizaciones.php" class="dash-nav-link <?= $cur==='automatizaciones.php'?'active':'' ?>">✉ Automatizaciones</a>
        <a href="content_studio.php"   class="dash-nav-link <?= $cur==='content_studio.php'?'active':'' ?>">🎬 Content Studio</a>
        <a href="notificaciones.php"   class="dash-nav-link <?= $cur==='notificaciones.php'?'active':'' ?>">🔔 Notif. Push</a>
        <a href="configuracion.php"    class="dash-nav-link <?= $cur==='configuracion.php'?'active':'' ?>">⚙️ Configuración</a>
      </div>
    </div>

    <!-- ── Acciones ── -->
    <div class="dash-nav-section">
      <div class="dash-nav-label">Acciones</div>
      <a href="../clasificacion.php" class="dash-nav-link">Ver Clasificación</a>
      <a href="../resultados.php"    class="dash-nav-link">Ver Resultados</a>
    </div>

    <!-- ── Mi cuenta ── -->
    <div class="dash-nav-section">
      <div class="dash-nav-label">Mi cuenta</div>
      <a href="../dashboard.php" class="dash-nav-link">Mi Dashboard</a>
      <a href="../mi_perfil.php" class="dash-nav-link <?= $cur==='mi_perfil.php'?'active':'' ?>">
        <svg style="width:15px;height:15px;flex-shrink:0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
        Mi Perfil
      </a>
    </div>

  </nav>
</aside>

<style>
.dash-tools-toggle {
  width: 100%;
  display: flex;
  align-items: center;
  justify-content: space-between;
  background: none;
  border: none;
  cursor: pointer;
  padding: .3rem 1rem .4rem;
  border-radius: 0;
  gap: .5rem;
}
.dash-tools-toggle:hover { background: rgba(255,255,255,.04); }
.tools-arrow {
  stroke: rgba(255,255,255,.4);
  transition: transform .22s ease;
  flex-shrink: 0;
}
.dash-tools-toggle.open .tools-arrow { transform: rotate(180deg); }

.dash-tools-menu {
  max-height: 0;
  overflow: hidden;
  transition: max-height .28s cubic-bezier(.4,0,.2,1);
}
.dash-tools-menu.open { max-height: 300px; }
</style>

<script>
function toggleTools() {
  var btn  = document.getElementById('tools-toggle');
  var menu = document.getElementById('tools-menu');
  var open = menu.classList.toggle('open');
  btn.classList.toggle('open', open);
  try { localStorage.setItem('epl_tools_open', open ? '1' : '0'); } catch(e){}
}
// Restaurar estado del menú desde localStorage (si no es página activa)
(function(){
  <?php if (!$_in_tools): ?>
  try {
    if (localStorage.getItem('epl_tools_open') === '1') {
      document.getElementById('tools-toggle').classList.add('open');
      document.getElementById('tools-menu').classList.add('open');
    }
  } catch(e){}
  <?php endif; ?>
})();
</script>

<!-- ── Bottom nav (móvil) ── -->
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
    <a href="disputas.php" class="dash-bottom-link <?= $cur==='disputas.php'?'active':'' ?>" style="position:relative">
      <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v4m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>
      <?php if ($_disp_count > 0): ?><span style="position:absolute;top:2px;right:6px;background:#dc2626;color:#fff;border-radius:50%;width:14px;height:14px;font-size:.55rem;font-weight:900;display:flex;align-items:center;justify-content:center;line-height:1"><?= $_disp_count ?></span><?php endif; ?>
      <span>Disputas</span>
    </a>
    <a href="content_studio.php" class="dash-bottom-link <?= $cur==='content_studio.php'?'active':'' ?>">
      <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 10l4.553-2.276A1 1 0 0121 8.677v6.646a1 1 0 01-1.447.894L15 14M3 8a2 2 0 012-2h8a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2V8z"/></svg>
      <span>Studio</span>
    </a>
    <a href="configuracion.php" class="dash-bottom-link <?= $cur==='configuracion.php'?'active':'' ?>">
      <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
      <span>Config.</span>
    </a>
    <a href="../clasificacion.php" class="dash-bottom-link">
      <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
      <span>Tablas</span>
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
(function () {
  var nav    = document.querySelector('.dash-bottom-nav-inner');
  var active = document.querySelector('.dash-bottom-link.active');
  if (!nav || !active) return;
  nav.scrollLeft = active.offsetLeft - (nav.offsetWidth / 2) + (active.offsetWidth / 2);
})();
</script>
