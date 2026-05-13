<?php
// Admin sidebar — include desde admin/*.php
$cur = basename($_SERVER['PHP_SELF']);
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
      <a href="dashboard_repro.php" class="dash-nav-link <?= $cur==='dashboard_repro.php'?'active':'' ?>" style="color:var(--gold)">Reprogramaciones</a>
      <a href="inscripciones.php" class="dash-nav-link <?= $cur==='inscripciones.php'?'active':'' ?>">Inscripciones</a>
      <a href="suplentes.php"    class="dash-nav-link <?= $cur==='suplentes.php'   ?'active':'' ?>">Suplentes</a>
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
