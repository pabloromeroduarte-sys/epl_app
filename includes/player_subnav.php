<?php
// Barra de sub-navegación horizontal para páginas del jugador
// Uso: include __DIR__ . '/includes/player_subnav.php';
// Variable opcional: $player_tab (string) — clave de la tab activa
$_ptab    = $player_tab ?? '';
$_jugador = epl_jugador_actual();
$_liga    = epl_liga_activa();
$_equipo  = ($_liga && $_jugador) ? epl_equipo_del_jugador($_jugador['id'], $_liga['id']) : null;
?>
<div class="player-subnav">
  <div class="container">
    <div class="player-subnav-inner">

      <!-- Info resumida del jugador/equipo -->
      <div class="player-subnav-who">
        <img src="<?= epl_h(epl_foto_jugador($_jugador['foto'], $_jugador['nombre'].' '.$_jugador['apellido'])) ?>"
             alt="" class="player-subnav-avatar">
        <div>
          <span class="player-subnav-name"><?= epl_h($_jugador['nombre'].' '.$_jugador['apellido']) ?></span>
          <?php if ($_equipo): ?>
            <span class="player-subnav-team"><?= epl_h($_equipo['nombre']) ?></span>
          <?php endif; ?>
        </div>
      </div>

      <!-- Links de navegación -->
      <nav class="player-subnav-links" id="playerSubnav">
        <a href="dashboard.php"          class="player-subnav-link <?= $_ptab==='dashboard'    ?'active':'' ?>">Dashboard</a>
        <a href="ingresar_resultado.php" class="player-subnav-link <?= $_ptab==='resultado'    ?'active':'' ?>">Resultado</a>
        <a href="reprogramar.php"        class="player-subnav-link <?= $_ptab==='reprogramar'  ?'active':'' ?>">Reprogramar</a>
        <a href="mis_suplentes.php"      class="player-subnav-link <?= $_ptab==='suplentes'    ?'active':'' ?>">Suplentes</a>
        <a href="inscribirse.php"        class="player-subnav-link <?= $_ptab==='inscribirse'  ?'active':'' ?>">Inscripciones</a>
        <a href="mi_perfil.php"          class="player-subnav-link <?= $_ptab==='perfil'       ?'active':'' ?>">Mi Perfil</a>
        <?php if ($_jugador['rol']==='admin'): ?>
          <a href="admin/"               class="player-subnav-link player-subnav-admin">Admin</a>
        <?php endif; ?>
      </nav>

    </div>
  </div>
</div>
