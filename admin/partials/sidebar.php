<?php
// Admin sidebar — include desde admin/*.php
$cur = basename($_SERVER['PHP_SELF']);

// Badges
$_disp_count = 0;
$_repro_count = 0;
$_insc_count = 0;
try {
    epl_ensure_disputas_schema();
    $_disp_count  = (int)epl_db()->query("SELECT COUNT(*) FROM partido_disputas WHERE estado='pendiente'")->fetchColumn();
    $_repro_count = (int)epl_db()->query("SELECT COUNT(*) FROM solicitudes_reprogramacion WHERE estado='pendiente'")->fetchColumn();
    $_insc_count  = (int)epl_db()->query("SELECT COUNT(*) FROM inscripciones WHERE estado='pendiente'")->fetchColumn();
} catch (Throwable $_e) {}

// Helper para items
function adm_link(string $href, string $cur_file, $active_files, string $icon, string $label, int $badge = 0, string $badge_color = '#dc2626'): void {
    $active_arr = is_array($active_files) ? $active_files : [$active_files];
    $active = in_array($cur_file, $active_arr);
    ?>
    <a href="<?= $href ?>" class="adm-item <?= $active ? 'active' : '' ?>">
      <span class="adm-icon"><?= $icon ?></span>
      <span class="adm-label"><?= $label ?></span>
      <?php if ($badge > 0): ?>
        <span class="adm-badge" style="background:<?= $badge_color ?>"><?= $badge ?></span>
      <?php endif; ?>
    </a>
    <?php
}

$_in_tools = in_array($cur, ['automatizaciones.php','content_studio.php','notificaciones.php','configuracion.php','erp_financiero.php','recintos.php','diagnostico_push.php','diagnostico_mail.php','cumpleanos.php','alertas.php','presupuestos.php','presupuesto_detalle.php']);
?>
<aside class="dash-sidebar dash-sidebar--admin">
  <div class="adm-brand">
    <div class="adm-brand-eyebrow">
      <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
      Modo Administrador
    </div>
    <div class="adm-brand-sub">Elite Padel League</div>
  </div>

  <nav class="adm-nav">
    <!-- ── DIARIO ── -->
    <div class="adm-group-label">Diario</div>
    <?php adm_link('index.php',           $cur, 'index.php',                            '🏠', 'Inicio'); ?>
    <?php adm_link('bi.php',              $cur, 'bi.php',                               '📊', 'Dashboard BI'); ?>
    <?php adm_link('partidos.php',        $cur, ['partidos.php', 'proximos_partidos.php', 'cargar_resultados.php'], '🎾', 'Partidos'); ?>
    <?php adm_link('calendario.php',      $cur, 'calendario.php',                      '🗓️', 'Calendario'); ?>
    <?php adm_link('mcp.php',             $cur, 'mcp.php',                             '🤖', 'Acceso IA'); ?>
    <?php adm_link('dashboard_repro.php', $cur, 'dashboard_repro.php',                  '📅', 'Reprogramaciones', $_repro_count, '#ea580c'); ?>
    <?php adm_link('inscripciones.php',   $cur, 'inscripciones.php',                    '📋', 'Inscripciones', $_insc_count, '#2563eb'); ?>
    <?php adm_link('disputas.php',        $cur, 'disputas.php',                         '⚠️', 'Disputas', $_disp_count); ?>

    <!-- ── COMPETENCIA ── -->
    <div class="adm-group-label">Competencia</div>
    <?php adm_link('ligas.php',           $cur, ['ligas.php','liga_detalle.php'],       '🏆', 'Ligas / Torneos'); ?>
    <?php adm_link('torneos.php',         $cur, 'torneos.php',                          '🥇', 'Torneo Copa'); ?>
    <?php adm_link('dashboard_resultados.php', $cur, 'dashboard_resultados.php', '📊', 'Resultados'); ?>
    <?php adm_link('jugadores.php',       $cur, 'jugadores.php',                        '👥', 'Jugadores'); ?>
    <?php adm_link('suplentes.php',       $cur, 'suplentes.php',                        '🔄', 'Suplentes'); ?>

    <!-- ── HERRAMIENTAS (colapsable) ── -->
    <button id="tools-toggle" class="adm-tools-toggle <?= $_in_tools ? 'open' : '' ?>" onclick="toggleTools()" type="button">
      <span>Herramientas</span>
      <svg class="tools-arrow" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
    </button>
    <div id="tools-menu" class="adm-tools-menu <?= $_in_tools ? 'open' : '' ?>">
      <?php adm_link('erp_financiero.php',   $cur, 'erp_financiero.php',         '💰', 'ERP Financiero'); ?>
      <?php adm_link('presupuestos.php',     $cur, ['presupuestos.php','presupuesto_detalle.php'], '📊', 'Presupuestos'); ?>
      <?php adm_link('alertas.php',          $cur, 'alertas.php',          '🔔', 'Alertas y avisos'); ?>
      <?php adm_link('notificaciones.php',   $cur, 'notificaciones.php',   '✉️', 'Mensajería'); ?>
      <?php adm_link('diagnostico_push.php', $cur, 'diagnostico_push.php', '🔔', 'Diagnóstico Push'); ?>
      <?php adm_link('diagnostico_mail.php', $cur, 'diagnostico_mail.php', '📬', 'Diagnóstico Mail'); ?>
      <?php adm_link('cumpleanos.php',       $cur, 'cumpleanos.php',       '🎂', 'Cumpleaños'); ?>
      <?php adm_link('automatizaciones.php', $cur, 'automatizaciones.php', '⚡', 'Automatizaciones'); ?>
      <?php adm_link('content_studio.php',   $cur, 'content_studio.php',   '🎬', 'Content Studio'); ?>
      <?php adm_link('recintos.php',         $cur, 'recintos.php',         '📍', 'Recintos'); ?>
      <?php adm_link('configuracion.php',    $cur, 'configuracion.php',    '⚙️', 'Configuración'); ?>
    </div>

    <!-- ── Mi cuenta ── -->
    <div class="adm-group-label">Mi cuenta</div>
    <?php adm_link('../dashboard.php', $cur, '',           '👤', 'Vista jugador'); ?>
    <?php adm_link('../mi_perfil.php', $cur, 'mi_perfil.php', '🪪', 'Mi perfil'); ?>
    <?php adm_link('../logout.php',    $cur, '',           '🚪', 'Salir'); ?>
  </nav>
</aside>

<style>
/* ────────── Sidebar admin simplificado ────────── */
.dash-sidebar--admin { padding-bottom: 1rem; }
.adm-brand { padding: 1rem .9rem .85rem; border-bottom: 1px solid rgba(28,47,72,.15); margin-bottom: .5rem; }
.adm-brand-eyebrow {
  display: flex; align-items: center; gap: .4rem;
  font-size: .65rem; font-weight: 900; color: var(--navy);
  text-transform: uppercase; letter-spacing: .12em;
}
.adm-brand-sub { color: rgba(28,47,72,.6); font-size: .72rem; font-weight: 600; margin-top: .25rem; }

.adm-nav { padding: 0 .6rem; }
.adm-group-label {
  font-size: .58rem; font-weight: 900; color: rgba(28,47,72,.5);
  text-transform: uppercase; letter-spacing: .18em;
  padding: .85rem .65rem .35rem;
}

.adm-item {
  display: flex; align-items: center; gap: .65rem;
  padding: .55rem .65rem;
  font-size: .82rem; font-weight: 700; color: rgba(28,47,72,.85);
  border-radius: 8px;
  text-decoration: none;
  transition: background .12s, color .12s;
  margin-bottom: 1px;
}
.adm-item:hover { background: rgba(28,47,72,.08); }
.adm-item.active { background: var(--navy); color: var(--gold); box-shadow: 0 2px 8px rgba(28,47,72,.25); }
.adm-icon { font-size: 1rem; line-height: 1; width: 22px; text-align: center; }
.adm-label { flex: 1; }
.adm-badge {
  background: #dc2626; color: #fff;
  font-size: .62rem; font-weight: 900;
  border-radius: 999px;
  padding: .1rem .45rem;
  min-width: 18px; text-align: center;
}

/* Tools toggle */
.adm-tools-toggle {
  width: 100%; display: flex; align-items: center; justify-content: space-between;
  background: none; border: none; cursor: pointer;
  padding: .85rem .65rem .35rem;
  font-size: .58rem; font-weight: 900; color: rgba(28,47,72,.5);
  text-transform: uppercase; letter-spacing: .18em;
  font-family: inherit;
}
.adm-tools-toggle:hover { color: var(--navy); }
.tools-arrow { stroke: rgba(28,47,72,.5); transition: transform .22s; }
.adm-tools-toggle.open .tools-arrow { transform: rotate(180deg); }
.adm-tools-menu {
  max-height: 0; overflow: hidden;
  transition: max-height .28s cubic-bezier(.4,0,.2,1);
}
.adm-tools-menu.open { max-height: 400px; }
</style>

<script>
function toggleTools() {
  var btn  = document.getElementById('tools-toggle');
  var menu = document.getElementById('tools-menu');
  var open = menu.classList.toggle('open');
  btn.classList.toggle('open', open);
  try { localStorage.setItem('epl_tools_open', open ? '1' : '0'); } catch(e){}
}
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

<!-- ═══════════════ Bottom nav móvil (SIMPLIFICADA) ═══════════════ -->
<nav class="dash-bottom-nav dash-bottom-nav--admin">
  <div class="bn-inner">
    <a href="index.php" class="bn-item <?= $cur==='index.php'?'active':'' ?>">
      <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
      <span>Inicio</span>
    </a>
    <a href="partidos.php" class="bn-item <?= in_array($cur, ['partidos.php', 'proximos_partidos.php', 'cargar_resultados.php'])?'active':'' ?>">
      <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>
      <span>Partidos</span>
    </a>
    <a href="dashboard_repro.php" class="bn-item <?= $cur==='dashboard_repro.php'?'active':'' ?>" style="position:relative">
      <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
      <span>Reprog</span>
      <?php if ($_repro_count > 0): ?><span class="bn-badge"><?= $_repro_count ?></span><?php endif; ?>
    </a>
    <a href="inscripciones.php" class="bn-item <?= $cur==='inscripciones.php'?'active':'' ?>" style="position:relative">
      <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
      <span>Inscrip.</span>
      <?php if ($_insc_count > 0): ?><span class="bn-badge" style="background:#2563eb"><?= $_insc_count ?></span><?php endif; ?>
    </a>
    <button type="button" onclick="document.getElementById('bnMasSheet').classList.add('open');document.body.style.overflow='hidden'" class="bn-item bn-item-more" style="background:none;border:none;font-family:inherit;cursor:pointer">
      <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/></svg>
      <span>Más</span>
      <?php if ($_disp_count > 0): ?><span class="bn-badge"><?= $_disp_count ?></span><?php endif; ?>
    </button>
  </div>
</nav>

<!-- Sheet "Más" (drawer abajo) -->
<div id="bnMasSheet" class="bn-sheet" onclick="if(event.target===this){this.classList.remove('open');document.body.style.overflow='';}">
  <div class="bn-sheet-inner">
    <div class="bn-sheet-handle"></div>
    <div class="bn-sheet-grid">
      <a href="ligas.php" class="bn-tile <?= in_array($cur,['ligas.php','liga_detalle.php'])?'active':'' ?>">
        <span class="bn-tile-icon">🏆</span>
        <span>Ligas</span>
      </a>
      <a href="bi.php" class="bn-tile <?= $cur==='bi.php'?'active':'' ?>">
        <span class="bn-tile-icon">📊</span>
        <span>Dashboard BI</span>
      </a>
      <a href="dashboard_resultados.php" class="bn-tile <?= $cur==='dashboard_resultados.php'?'active':'' ?>">
        <span class="bn-tile-icon">📋</span>
        <span>Resultados</span>
      </a>
      <a href="mcp.php" class="bn-tile <?= $cur==='mcp.php'?'active':'' ?>">
        <span class="bn-tile-icon">🤖</span>
        <span>Acceso IA</span>
      </a>
      <a href="jugadores.php" class="bn-tile <?= $cur==='jugadores.php'?'active':'' ?>">
        <span class="bn-tile-icon">👥</span>
        <span>Jugadores</span>
      </a>
      <a href="suplentes.php" class="bn-tile <?= $cur==='suplentes.php'?'active':'' ?>">
        <span class="bn-tile-icon">🔄</span>
        <span>Suplentes</span>
      </a>
      <a href="disputas.php" class="bn-tile <?= $cur==='disputas.php'?'active':'' ?>" style="position:relative">
        <span class="bn-tile-icon">⚠️</span>
        <span>Disputas</span>
        <?php if ($_disp_count > 0): ?><span class="bn-tile-badge"><?= $_disp_count ?></span><?php endif; ?>
      </a>
      <a href="erp_financiero.php" class="bn-tile <?= $cur==='erp_financiero.php'?'active':'' ?>">
        <span class="bn-tile-icon">💰</span>
        <span>ERP</span>
      </a>
      <a href="presupuestos.php" class="bn-tile <?= in_array($cur,['presupuestos.php','presupuesto_detalle.php'])?'active':'' ?>">
        <span class="bn-tile-icon">📊</span>
        <span>Presup.</span>
      </a>
      <a href="alertas.php" class="bn-tile <?= $cur==='alertas.php'?'active':'' ?>">
        <span class="bn-tile-icon">🔔</span>
        <span>Alertas</span>
      </a>
      <a href="notificaciones.php" class="bn-tile <?= $cur==='notificaciones.php'?'active':'' ?>">
        <span class="bn-tile-icon">✉️</span>
        <span>Mensajería</span>
      </a>
      <a href="automatizaciones.php" class="bn-tile <?= $cur==='automatizaciones.php'?'active':'' ?>">
        <span class="bn-tile-icon">⚡</span>
        <span>Automatiz.</span>
      </a>
      <a href="content_studio.php" class="bn-tile <?= $cur==='content_studio.php'?'active':'' ?>">
        <span class="bn-tile-icon">🎬</span>
        <span>Studio</span>
      </a>
      <a href="recintos.php" class="bn-tile <?= $cur==='recintos.php'?'active':'' ?>">
        <span class="bn-tile-icon">📍</span>
        <span>Recintos</span>
      </a>
      <a href="cumpleanos.php" class="bn-tile <?= $cur==='cumpleanos.php'?'active':'' ?>">
        <span class="bn-tile-icon">🎂</span>
        <span>Cumpleaños</span>
      </a>
      <a href="configuracion.php" class="bn-tile <?= $cur==='configuracion.php'?'active':'' ?>">
        <span class="bn-tile-icon">⚙️</span>
        <span>Config</span>
      </a>
      <a href="../dashboard.php" class="bn-tile">
        <span class="bn-tile-icon">👤</span>
        <span>Vista jugador</span>
      </a>
      <a href="../mi_perfil.php" class="bn-tile <?= $cur==='mi_perfil.php'?'active':'' ?>">
        <span class="bn-tile-icon">🪪</span>
        <span>Mi perfil</span>
      </a>
    </div>
    <div style="padding:.85rem 1rem 1.2rem;text-align:center">
      <a href="../logout.php" style="font-size:.72rem;color:#dc2626;font-weight:800;text-transform:uppercase;letter-spacing:.08em;text-decoration:none">🚪 Cerrar sesión</a>
    </div>
  </div>
</div>

<style>
/* ═══════════════ Bottom nav simplificada ═══════════════ */
.dash-bottom-nav {
  position: fixed !important;
  bottom: 0 !important;
  left: 0 !important;
  right: 0 !important;
  width: 100% !important;
  max-width: 100vw !important;
  z-index: 99999 !important;
  transform: none !important;
  -webkit-transform: none !important;
  will-change: auto !important;
  -webkit-backface-visibility: visible !important;
  backface-visibility: visible !important;
}
.bn-inner {
  display: flex; align-items: stretch;
  padding: 0 .25rem;
  padding-bottom: env(safe-area-inset-bottom);
}
.bn-item {
  flex: 1;
  display: flex; flex-direction: column; align-items: center; justify-content: center;
  gap: .15rem; padding: .55rem .25rem .5rem;
  color: rgba(28,47,72,.75);
  text-decoration: none;
  font-size: .65rem; font-weight: 700;
  transition: color .15s, transform .12s;
  position: relative;
  font-family: inherit;
}
.bn-item:active { transform: scale(.93); }
.bn-item svg { width: 22px; height: 22px; }
.bn-item.active {
  color: var(--gold);
  background: var(--navy);
  border-radius: 0 0 12px 12px;
}
.bn-item.active svg { color: var(--gold); }
.bn-item.active::before {
  content: '';
  position: absolute;
  top: 0; left: 50%; transform: translateX(-50%);
  width: 36px; height: 4px;
  background: var(--gold);
  border-radius: 0 0 4px 4px;
}
.bn-badge {
  position: absolute;
  top: 4px; right: calc(50% - 22px);
  background: #dc2626; color: #fff;
  font-size: .55rem; font-weight: 900;
  border-radius: 999px;
  padding: .05rem .35rem;
  min-width: 15px; text-align: center;
  line-height: 1.3;
  border: 2px solid var(--gold);
}
.bn-item-more.bn-item.active::before { display: none; }

/* Sheet "Más" */
.bn-sheet {
  position: fixed; inset: 0;
  background: rgba(10,20,33,.45);
  backdrop-filter: blur(6px);
  -webkit-backdrop-filter: blur(6px);
  z-index: 10000;
  display: none;
  align-items: flex-end;
}
.bn-sheet.open { display: flex; animation: bnFadeIn .2s ease; }
@keyframes bnFadeIn { from { background: rgba(10,20,33,0); } to { background: rgba(10,20,33,.45); } }
.bn-sheet-inner {
  width: 100%;
  background: #fff;
  border-radius: 24px 24px 0 0;
  padding: .5rem 0 0;
  max-height: 75vh;
  overflow-y: auto;
  animation: bnSlideUp .25s cubic-bezier(.4,0,.2,1);
}
@keyframes bnSlideUp { from { transform: translateY(100%); } to { transform: translateY(0); } }
.bn-sheet-handle {
  width: 40px; height: 4px;
  background: #cbd5e1; border-radius: 4px;
  margin: 0 auto .85rem;
}
.bn-sheet-grid {
  display: grid; grid-template-columns: repeat(3, 1fr);
  gap: .5rem; padding: 0 1rem 1rem;
}
.bn-tile {
  display: flex; flex-direction: column; align-items: center;
  gap: .3rem; padding: .9rem .5rem;
  background: #f8fafc;
  border-radius: 14px;
  text-decoration: none;
  color: var(--navy);
  font-size: .68rem; font-weight: 700;
  text-align: center;
  transition: all .15s;
  position: relative;
}
.bn-tile:active { transform: scale(.95); }
.bn-tile:hover { background: #f1f5f9; }
.bn-tile.active { background: var(--navy); color: var(--gold); }
.bn-tile-icon { font-size: 1.4rem; line-height: 1; }
.bn-tile-badge {
  position: absolute; top: 6px; right: 8px;
  background: #dc2626; color: #fff;
  font-size: .55rem; font-weight: 900;
  border-radius: 999px;
  padding: .05rem .35rem;
  min-width: 15px; text-align: center;
}

/* ═══════════════════════════════════════════════════════════════════════
   FIX DEFINITIVO iOS Safari — barra inferior que se "despega" al scrollear
   Causa: cuando el documento raíz scrollea, Safari colapsa/expande su barra
   de herramientas y el viewport cambia; el position:fixed queda flotando.
   Solución: el scroll NO ocurre en <html>/<body> sino dentro de .dash-main.
   Así la barra de Safari nunca colapsa y el bottom-nav queda clavado abajo.
   Acotado con :has() para afectar SOLO páginas admin (no rompe lo demás).
   ═══════════════════════════════════════════════════════════════════════ */
@media (max-width: 992px) {
  html:has(.dash-bottom-nav--admin),
  body:has(.dash-bottom-nav--admin) {
    height: 100%;
    overflow: hidden;
    overscroll-behavior-y: none;
  }
  body:has(.dash-bottom-nav--admin) .dash-main {
    height: calc(100vh - 90px);      /* fallback navegadores viejos */
    height: calc(100dvh - 90px);     /* viewport dinámico real (iOS 15.4+) */
    overflow-y: auto;
    -webkit-overflow-scrolling: touch;
    overscroll-behavior: contain;
  }
}
</style>

<script>
// Cerrar sheet con ESC
document.addEventListener('keydown', function(e){
  if (e.key === 'Escape') {
    var s = document.getElementById('bnMasSheet');
    if (s && s.classList.contains('open')) { s.classList.remove('open'); document.body.style.overflow=''; }
  }
});

// FIX iOS: mover el bottom-nav y el sheet directamente al <body> para que
// position:fixed funcione siempre (evita contextos rotos por grid/transform/contain)
(function(){
  function moverAlBody() {
    var nav = document.querySelector('.dash-bottom-nav--admin');
    var sheet = document.getElementById('bnMasSheet');
    if (nav && nav.parentNode !== document.body) document.body.appendChild(nav);
    if (sheet && sheet.parentNode !== document.body) document.body.appendChild(sheet);
  }
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', moverAlBody);
  } else {
    moverAlBody();
  }
})();
</script>
