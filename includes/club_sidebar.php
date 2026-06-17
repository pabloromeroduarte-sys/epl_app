<?php
// Sidebar para el área del club. Variable requerida: $club_tab (string)
$_ctab = $club_tab ?? '';
$_cj   = epl_jugador_actual();
?>
<aside class="dash-sidebar">

  <div style="padding:1.5rem 1.25rem 1rem;border-bottom:1px solid rgba(255,255,255,.08);text-align:center">
    <div style="width:64px;height:64px;border-radius:50%;background:rgba(201,167,98,.15);border:2px solid var(--gold);display:flex;align-items:center;justify-content:center;margin:0 auto .6rem">
      <span style="font-size:1.6rem">🏟️</span>
    </div>
    <div style="color:var(--white);font-weight:700;font-size:.95rem;line-height:1.2"><?= epl_h(trim(($_cj['nombre'] ?? 'Club') . ' ' . ($_cj['apellido'] ?? ''))) ?></div>
    <div style="margin-top:.3rem;font-size:.7rem;color:var(--gold);font-weight:700;text-transform:uppercase;letter-spacing:.08em">Club</div>
  </div>

  <nav style="padding:1rem 0">
    <div class="dash-nav-section">
      <div class="dash-nav-label">Vista</div>

      <a href="<?= epl_url('club/resultados.php') ?>" class="dash-nav-link <?= $_ctab==='resultados'?'active':'' ?>">
        <svg class="dash-nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>
        Resultados
      </a>

      <a href="<?= epl_url('club/calendario.php') ?>" class="dash-nav-link <?= $_ctab==='calendario'?'active':'' ?>">
        <svg class="dash-nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
        Calendario
      </a>

      <a href="<?= epl_url('logout.php') ?>" class="dash-nav-link">
        <svg class="dash-nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
        Cerrar sesión
      </a>
    </div>
  </nav>
</aside>
