<?php
// header.php — Navbar compartido (pública + privada)
// Uso: require_once 'includes/header.php';
// Variables opcionales: $page_title (string), $active_nav (string)
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/functions.php';

$jugador = epl_jugador_actual();
$title   = isset($page_title) ? epl_h($page_title) . ' — Elite Padel League' : 'Elite Padel League';
$active  = $active_nav ?? '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= $title ?></title>
  <link rel="icon" href="<?= epl_url('assets/img/favicon.png') ?>" type="image/png">
  <!-- Fuentes -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Anton&family=Montserrat:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
  <!-- Estilos -->
  <link rel="stylesheet" href="<?= epl_url('assets/css/epl.css') ?>">
</head>
<body>

<!-- ===================== NAVBAR ===================== -->
<nav class="nav-bar" id="navbar">
  <div class="nav-inner">

    <!-- Logo -->
    <a href="<?= epl_url() ?>" class="nav-logo-link">
      <img src="<?= epl_url('assets/img/logo-epl-lateral.png') ?>"
           alt="Elite Padel League" class="nav-logo-img">
    </a>

    <!-- Links desktop -->
    <ul class="nav-links" id="navLinks">
      <li><a href="<?= epl_url() ?>"                    class="nav-link <?= $active==='inicio'        ?'active':'' ?>">Inicio</a></li>
      <li><a href="<?= epl_url('torneos.php') ?>"       class="nav-link <?= $active==='torneos'       ?'active':'' ?>">Torneos</a></li>
      <li><a href="<?= epl_url('clasificacion.php') ?>" class="nav-link <?= $active==='clasificacion' ?'active':'' ?>">Clasificación</a></li>
      <li><a href="<?= epl_url('resultados.php') ?>"    class="nav-link <?= $active==='resultados'    ?'active':'' ?>">Resultados</a></li>
      <li><a href="<?= epl_url('jugadores.php') ?>"     class="nav-link <?= $active==='jugadores'     ?'active':'' ?>">Jugadores</a></li>
    </ul>

    <!-- Zona de usuario -->
    <div class="nav-user-zone">
      <?php if ($jugador): ?>
        <?php $dest = $jugador['rol'] === 'admin' ? epl_url('admin/') : epl_url('dashboard.php'); ?>
        <a href="<?= $dest ?>" class="nav-user-btn">
          <img src="<?= epl_h(epl_foto_jugador($jugador['foto'], $jugador['nombre'] . ' ' . $jugador['apellido'])) ?>"
               alt="<?= epl_h($jugador['nombre']) ?>" class="nav-avatar">
          <span class="nav-user-name"><?= epl_h($jugador['nombre']) ?></span>
        </a>
        <a href="<?= epl_url('logout.php') ?>" class="nav-btn nav-btn-ghost">Salir</a>
      <?php else: ?>
        <a href="<?= epl_url('login.php') ?>" class="nav-btn nav-btn-primary">Ingresar</a>
      <?php endif; ?>
    </div>

    <!-- Hamburger mobile -->
    <button class="nav-hamburger" id="navHamburger" aria-label="Menú">
      <span></span><span></span><span></span>
    </button>

  </div>
</nav>

<!-- Overlay mobile -->
<div class="nav-overlay" id="navOverlay"></div>

<!-- Espaciador para nav fijo -->
<div style="height:var(--nav-height)"></div>

<script>
(function(){
  const ham = document.getElementById('navHamburger');
  const links = document.getElementById('navLinks');
  const overlay = document.getElementById('navOverlay');
  function toggle(){
    links.classList.toggle('open');
    overlay.classList.toggle('open');
    ham.classList.toggle('open');
  }
  ham.addEventListener('click', toggle);
  overlay.addEventListener('click', toggle);
})();
</script>
