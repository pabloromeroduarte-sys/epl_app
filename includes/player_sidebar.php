<?php
// Sidebar lateral para páginas del jugador — reemplaza player_subnav
// Variable requerida: $player_tab (string)
$_ptab    = $player_tab ?? '';
$_j       = epl_jugador_actual();
$_liga    = epl_liga_activa();
$_equipo  = ($_liga && $_j) ? epl_equipo_del_jugador($_j['id'], $_liga['id']) : null;

// Ranking global del jugador (ventana 52 semanas)
$_db = epl_db();
$_rk = $_db->prepare("
    SELECT SUM(puntos) as total,
           RANK() OVER (ORDER BY SUM(puntos) DESC) as posicion
    FROM ranking_puntos
    WHERE jugador_id = ?
      AND fecha_competicion >= DATE_SUB(CURDATE(), INTERVAL 52 WEEK)
");
$_rk->execute([$_j['id']]);
$_ranking = $_rk->fetch();
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

      <a href="<?= epl_url('inscribirse.php') ?>" class="dash-nav-link <?= $_ptab==='inscribirse'?'active':'' ?>">
        <svg class="dash-nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
        Inscribirme
      </a>
    </div>

    <div class="dash-nav-section">
      <div class="dash-nav-label">Mi cuenta</div>

      <a href="<?= epl_url('mi_perfil.php') ?>" class="dash-nav-link <?= $_ptab==='perfil'?'active':'' ?>">
        <svg class="dash-nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
        Mi perfil
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
    <a href="<?= epl_url('mis_torneos.php') ?>" class="dash-bottom-link <?= $_ptab==='mis_torneos'?'active':'' ?>">
      <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
      <span>Torneos</span>
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
