<?php
// Sidebar lateral para páginas del jugador — reemplaza player_subnav
// Variable requerida: $player_tab (string)
$_ptab    = $player_tab ?? '';
$_j       = epl_jugador_actual();
$_liga    = epl_liga_activa();
$_equipo  = ($_liga && $_j) ? epl_equipo_del_jugador($_j['id'], $_liga['id']) : null;

// Puntos de la pareja activa durante la temporada anual.
$_db = epl_db();
$_ranking = null;
if ($_equipo) {
  $_rk = $_db->prepare("
      SELECT SUM(LEAST(rp1.puntos,rp2.puntos)) AS total
      FROM liga_equipos le
      JOIN ranking_puntos rp1 ON rp1.liga_id=le.liga_id AND rp1.jugador_id=?
      JOIN ranking_puntos rp2 ON rp2.liga_id=le.liga_id AND rp2.jugador_id=?
      WHERE le.equipo_id=? AND YEAR(rp1.fecha_competicion)=YEAR(CURDATE())
  ");
  $_rk->execute([(int)$_equipo['jugador1_id'],(int)$_equipo['jugador2_id'],(int)$_equipo['id']]);
  $_ranking = $_rk->fetch();
}
?>
<aside class="dash-sidebar">

  <!-- Avatar + info jugador -->
  <div style="padding:1.5rem 1.25rem 1rem;border-bottom:1px solid rgba(255,255,255,.08);text-align:center">
    <a href="<?= epl_url('mi_perfil.php') ?>" style="display:inline-block;position:relative;margin-bottom:.75rem">
      <img src="<?= epl_h(epl_foto_jugador($_j['foto'], $_j['nombre'].' '.$_j['apellido'])) ?>"
           style="width:72px;height:72px;border-radius:50%;object-fit:cover;border:3px solid var(--gold);display:block">
    </a>
    <div style="color:var(--white);font-weight:700;font-size:.95rem;line-height:1.2">
      <?= epl_h($_j['nombre']) ?><br>
      <span style="font-size:.82rem;font-weight:600"><?= epl_h($_j['apellido']) ?></span>
    </div>
    <?php if ($_equipo): ?>
      <div style="margin-top:.4rem;font-size:.72rem;color:var(--gold);font-weight:600;text-transform:uppercase;letter-spacing:.06em">
        <?= epl_h($_equipo['nombre']) ?>
      </div>
    <?php endif; ?>
    <?php if (!empty($_ranking['total'])): ?>
      <div style="margin-top:.6rem;display:inline-flex;align-items:center;gap:.4rem;background:rgba(201,167,98,.15);border:1px solid rgba(201,167,98,.3);border-radius:20px;padding:.2rem .65rem">
        <span style="color:var(--gold);font-size:.72rem;font-weight:700">🏅 <?= (int)$_ranking['total'] ?> pts</span>
      </div>
    <?php endif; ?>
  </div>

  <!-- Navegación -->
  <nav style="padding:1rem 0">
    <div class="dash-nav-section">
      <div class="dash-nav-label">Mi área</div>

      <a href="<?= epl_url('dashboard.php') ?>" class="dash-nav-link <?= $_ptab==='dashboard'?'active':'' ?>">
        <svg class="dash-nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
        Dashboard
      </a>

      <a href="<?= epl_url('mis_torneos.php') ?>" class="dash-nav-link <?= $_ptab==='mis_torneos'?'active':'' ?>">
        <svg class="dash-nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
        Mis Torneos
      </a>

      <a href="<?= epl_url('ingresar_resultado.php') ?>" class="dash-nav-link <?= $_ptab==='resultado'?'active':'' ?>">
        <svg class="dash-nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>
        Ingresar resultado
      </a>

      <a href="<?= epl_url('reprogramar.php') ?>" class="dash-nav-link <?= $_ptab==='reprogramar'?'active':'' ?>">
        <svg class="dash-nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
        Reprogramar partido
      </a>

      <a href="<?= epl_url('mis_suplentes.php') ?>" class="dash-nav-link <?= $_ptab==='suplentes'?'active':'' ?>">
        <svg class="dash-nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
        Mis suplentes
      </a>

      <a href="<?= epl_url('buscar_jugadores.php') ?>" class="dash-nav-link <?= $_ptab==='buscar_jugadores'?'active':'' ?>">
        <svg class="dash-nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
        Buscar jugadores
      </a>

      <a href="<?= epl_url('inscribirse.php') ?>" class="dash-nav-link <?= $_ptab==='inscribirse'?'active':'' ?>">
        <svg class="dash-nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
        Inscripciones
      </a>

      <a href="<?= epl_url('tutoriales.php') ?>" class="dash-nav-link <?= $_ptab==='tutoriales'?'active':'' ?>">
        <svg class="dash-nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
        Tutoriales
      </a>

      <a href="<?= epl_url('conectar_ia.php') ?>" class="dash-nav-link <?= $_ptab==='conectar_ia'?'active':'' ?>" <?= $_ptab==='conectar_ia'?'aria-current="page"':'' ?>>
        <svg class="dash-nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 3.104v5.714a2.25 2.25 0 01-.659 1.591L5 14.5m9.25-11.396v5.714c0 .597.237 1.169.659 1.591L19 14.5M5 14.5h14M7.5 19.5h9M12 14.5v5"/></svg>
        Conectar IA
      </a>
    </div>

    <div class="dash-nav-section">
      <div class="dash-nav-label">Mi cuenta</div>

      <a href="<?= epl_url('mi_perfil.php') ?>" class="dash-nav-link <?= $_ptab==='perfil'?'active':'' ?>">
        <svg class="dash-nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
        Mi perfil
      </a>

      <a href="<?= epl_url('mi_perfil.php') ?>#app-notif" class="dash-nav-link <?= $_ptab==='app'?'active':'' ?>" id="sidebar-app-link">
        <svg class="dash-nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
        <span style="flex:1">App &amp; Notificaciones</span>
        <span id="sidebar-app-badge" style="display:none;font-size:9px;background:#C9A762;color:#1C2F48;font-weight:800;border-radius:999px;padding:2px 6px;letter-spacing:.04em">!</span>
      </a>

      <?php if ($_j['rol'] === 'admin'): ?>
      <a href="<?= epl_url('admin/') ?>" class="dash-nav-link" style="color:var(--gold);border:1px solid rgba(201,167,98,.3);margin-top:.5rem">
        <svg class="dash-nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
        Ir al panel admin
      </a>
      <?php endif; ?>
    </div>
  </nav>

</aside>

<nav class="dash-bottom-nav">
  <div class="dash-bottom-nav-inner">
    <a href="<?= epl_url('dashboard.php') ?>" class="dash-bottom-link <?= $_ptab==='dashboard'?'active':'' ?>">
      <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
      <span>Inicio</span>
    </a>
    <a href="<?= epl_url('conectar_ia.php') ?>" class="dash-bottom-link <?= $_ptab==='conectar_ia'?'active':'' ?>" <?= $_ptab==='conectar_ia'?'aria-current="page"':'' ?>>
      <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9.75 3.104v5.714a2.25 2.25 0 01-.659 1.591L5 14.5m9.25-11.396v5.714c0 .597.237 1.169.659 1.591L19 14.5M5 14.5h14M7.5 19.5h9M12 14.5v5"/></svg>
      <span>IA</span>
    </a>
    <a href="<?= epl_url('mis_torneos.php') ?>" class="dash-bottom-link <?= $_ptab==='mis_torneos'?'active':'' ?>">
      <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
      <span>Torneos</span>
    </a>
    <a href="<?= epl_url('buscar_jugadores.php') ?>" class="dash-bottom-link <?= $_ptab==='buscar_jugadores'?'active':'' ?>">
      <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
      <span>Buscar</span>
    </a>
    <a href="<?= epl_url('ingresar_resultado.php') ?>" class="dash-bottom-link <?= $_ptab==='resultado'?'active':'' ?>">
      <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>
      <span>Puntuar</span>
    </a>
    <a href="<?= epl_url('reprogramar.php') ?>" class="dash-bottom-link <?= $_ptab==='reprogramar'?'active':'' ?>">
      <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
      <span>Reprog.</span>
    </a>
    <a href="<?= epl_url('mis_suplentes.php') ?>" class="dash-bottom-link <?= $_ptab==='suplentes'?'active':'' ?>">
      <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
      <span>Suplentes</span>
    </a>
    <a href="<?= epl_url('inscribirse.php') ?>" class="dash-bottom-link <?= $_ptab==='inscribirse'?'active':'' ?>">
      <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
      <span>Inscrip.</span>
    </a>
    <a href="<?= epl_url('notificaciones.php') ?>" class="dash-bottom-link <?= $_ptab==='notificaciones'?'active':'' ?>" style="position:relative">
      <?php $_nb = epl_notif_no_leidas((int)$_j['id']); ?>
      <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
      <?php if ($_nb > 0): ?>
        <span style="position:absolute;top:4px;right:10px;background:#ef4444;color:#fff;font-size:9px;font-weight:800;border-radius:999px;min-width:15px;height:15px;display:flex;align-items:center;justify-content:center;padding:0 3px"><?= $_nb > 9 ? '9+' : $_nb ?></span>
      <?php endif; ?>
      <span>Notif.</span>
    </a>
    <a href="<?= epl_url('tutoriales.php') ?>" class="dash-bottom-link <?= $_ptab==='tutoriales'?'active':'' ?>">
      <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
      <span>Guías</span>
    </a>

    <a href="<?= epl_url('mi_perfil.php') ?>#app-notif" class="dash-bottom-link <?= $_ptab==='app'?'active':'' ?>" style="position:relative" id="bottom-app-link">
      <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
      <span id="bottom-app-badge" style="display:none;position:absolute;top:4px;right:6px;background:#C9A762;color:#1C2F48;font-size:9px;font-weight:800;border-radius:999px;min-width:15px;height:15px;display:flex;align-items:center;justify-content:center;padding:0 3px">!</span>
      <span>App</span>
    </a>
    <a href="<?= epl_url('mi_perfil.php') ?>" class="dash-bottom-link <?= $_ptab==='perfil'?'active':'' ?>">
      <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
      <span>Perfil</span>
    </a>
    <?php if ($_j['rol'] === 'admin'): ?>
    <a href="<?= epl_url('admin/') ?>" class="dash-bottom-link">
      <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
      <span>Admin</span>
    </a>
    <?php endif; ?>
  </div>
</nav>

<style>
/* Reset controls to prevent iOS Safari fixed position bug */
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
</style>

<script>
(function(){
  // FIX iOS: mover el bottom-nav directamente al <body> para que position:fixed funcione siempre
  function moverAlBody() {
    var nav = document.querySelector('.dash-bottom-nav:not(.dash-bottom-nav--admin)');
    if (nav && nav.parentNode !== document.body) document.body.appendChild(nav);
  }

  // Muestra badge "!" en App si notificaciones no están activadas o la app no está instalada
  function checkAppBadge() {
    var needsBadge = false;
    var isStandalone = window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone;
    var notifPerm = (typeof Notification !== 'undefined') ? Notification.permission : 'granted';
    // Badge si notificaciones no otorgadas
    if (notifPerm !== 'granted') needsBadge = true;
    // Badge si no está instalada como app (solo en móvil)
    var isMobile = /iphone|ipad|android/i.test(navigator.userAgent);
    if (isMobile && !isStandalone) needsBadge = true;

    if (needsBadge) {
      var sb = document.getElementById('sidebar-app-badge');
      var bb = document.getElementById('bottom-app-badge');
      if (sb) sb.style.display = 'inline-flex';
      if (bb) bb.style.display = 'flex';
    }
  }

  function init() {
    moverAlBody();
    checkAppBadge();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
</script>
