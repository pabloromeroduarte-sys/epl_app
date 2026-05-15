<?php
// header.php — Navbar compartido (pública + privada)
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/functions.php';

// No sobrescribir $jugador si la página ya cargó el perfil completo desde la BD (p. ej. mi_perfil.php)
$jugador_nav = epl_jugador_actual();
if (!isset($jugador)) {
    $jugador = $jugador_nav;
}
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
  
  <!-- 1. CEREBRO DE DISEÑO Y FUENTES -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Anton&family=Montserrat:wght@400;600;700;800;900&display=swap" rel="stylesheet">

  <script src="https://cdn.tailwindcss.com"></script>
  <script>
      tailwind.config = {
          theme: {
              extend: {
                  colors: {
                      epl: { blue: '#1C2F48', gold: '#C9A762', dark: '#0A1421', light: '#F5F7F8' }
                  },
                  fontFamily: { primary: ['Anton', 'sans-serif'], secondary: ['Montserrat', 'sans-serif'] }
              }
          }
      }
  </script>

  <!-- Estilos antiguos (se mantienen para las vistas que no son el index o que usan clases epl.css) -->
  <link rel="stylesheet" href="<?= epl_url('assets/css/epl.css?v=' . time()) ?>">

  <style>
      /* RESET Y CONTENEDOR (Header) */
      body, html { overflow-x: hidden; background-color: #F5F7F8; }
      
      .epl-nav-container {
          font-family: 'Montserrat', sans-serif;
          width: 100vw !important;
          position: relative;
          left: 50%; right: 50%;
          margin-left: -50vw !important; margin-right: -50vw !important;
      }

      /* BARRA DE NAVEGACIÓN FIJA BLINDADA */
      .nav-fixed {
          position: fixed !important; top: 0 !important; left: 0 !important; width: 100% !important;
          z-index: 99999 !important; height: 90px !important; background-color: #ffffff;
          border-bottom: 4px solid #C9A762; display: flex; align-items: center; box-shadow: 0 10px 40px rgba(0,0,0,0.1);
      }

      /* Control Estricto del Logo */
      .logo-wrapper { display: flex; align-items: center; height: 100%; }
      .logo-img { 
          height: 60px !important; 
          width: auto !important; 
          max-height: 60px !important;
          object-fit: contain !important;
          display: block !important;
      }
      
      /* Links de Navegación */
      .nav-link-new { 
          color: #1C2F48 !important; 
          font-weight: 800 !important; 
          text-transform: uppercase !important; 
          font-size: 0.8rem !important; 
          letter-spacing: 0.15em !important; 
          text-decoration: none !important; 
          transition: all 0.3s ease; 
          position: relative;
      }
      .nav-link-new:hover, .nav-link-new.active { color: #C9A762 !important; }
      
      /* Botón de Contacto / Acceso */
      .nav-btn-contacto {
          background-color: #C9A762 !important; 
          color: #1C2F48 !important; 
          padding: 0.8rem 1.8rem !important;
          border-radius: 0.75rem !important; 
          font-weight: 900 !important; 
          font-size: 0.7rem !important;
          text-transform: uppercase !important; 
          letter-spacing: 0.1em !important; 
          text-decoration: none !important;
          transition: transform 0.3s ease, background-color 0.3s ease !important;
      }
      .nav-btn-contacto:hover { transform: scale(1.05); background-color: #b8934f !important; }

      /* Separador */
      .nav-divider { width: 1px; height: 30px; background-color: #e2e8f0; margin: 0 5px; }

      /* MENÚ MÓVIL */
      .mobile-menu-overlay {
          position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; background: #0A1421; z-index: 100000;
          transform: translateX(100%); transition: transform 0.4s cubic-bezier(0.77, 0, 0.175, 1);
          display: flex; flex-direction: column; padding: 40px;
      }
      .mobile-menu-overlay.open { transform: translateX(0); }
  </style>
</head>
<body>

<div class="epl-nav-container antialiased">
    <nav class="nav-fixed">
        <div class="w-full max-w-7xl mx-auto flex justify-between items-center h-full px-6 md:px-10">
            
            <!-- LOGO IZQUIERDA -->
            <a href="<?= epl_url() ?>" class="logo-wrapper">
                <img src="<?= epl_url('assets/img/logo-epl-lateral.png') ?>" class="logo-img" alt="Elite Padel League">
            </a>
            
            <!-- MENÚ DERECHA -->
            <div class="hidden lg:flex items-center gap-10">
                <a href="<?= epl_url() ?>" class="nav-link-new <?= $active==='inicio'?'active':'' ?>">Inicio</a>
                <a href="<?= epl_url('torneos.php') ?>" class="nav-link-new <?= $active==='torneos'?'active':'' ?>">Torneos</a>
                <a href="<?= epl_url('clasificacion.php') ?>" class="nav-link-new <?= $active==='clasificacion'?'active':'' ?>">Clasificación</a>
                <a href="<?= epl_url('resultados.php') ?>" class="nav-link-new <?= $active==='resultados'?'active':'' ?>">Resultados</a>
                <a href="<?= epl_url('jugadores.php') ?>" class="nav-link-new <?= $active==='jugadores'?'active':'' ?>">Jugadores</a>
                
                <div class="nav-divider"></div>
                
                <!-- ZONA DE USUARIO DINÁMICA (PHP) -->
                <div class="flex items-center gap-4">
                  <?php if ($jugador_nav): ?>
                      <?php
                      $dest = $jugador_nav['rol'] === 'admin' ? epl_url('admin/') : epl_url('dashboard.php');
                      $notif_count = epl_notif_no_leidas((int)$jugador_nav['id']);
                    ?>
                    <!-- Campana notificaciones -->
                    <a href="<?= epl_url('notificaciones.php') ?>" class="relative flex items-center justify-center w-10 h-10 rounded-full hover:bg-gray-100 transition-colors" title="Notificaciones">
                      <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-epl-blue" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                      </svg>
                      <?php if ($notif_count > 0): ?>
                        <span id="notif-badge" class="absolute -top-1 -right-1 bg-red-500 text-white text-[10px] font-black rounded-full min-w-[18px] h-[18px] flex items-center justify-center px-1"><?= $notif_count > 99 ? '99+' : $notif_count ?></span>
                      <?php else: ?>
                        <span id="notif-badge" class="absolute -top-1 -right-1 bg-red-500 text-white text-[10px] font-black rounded-full min-w-[18px] h-[18px] items-center justify-center px-1 hidden"></span>
                      <?php endif; ?>
                    </a>
                    <a href="<?= $dest ?>" class="flex items-center gap-2 group text-decoration-none">
                      <div class="w-10 h-10 rounded-full border-2 border-epl-gold overflow-hidden group-hover:scale-105 transition-transform flex items-center justify-center bg-epl-blue text-white">
                        <img src="<?= epl_h(epl_foto_jugador($jugador_nav['foto'], $jugador_nav['nombre'] . ' ' . $jugador_nav['apellido'])) ?>"
                             alt="<?= epl_h($jugador_nav['nombre']) ?>" class="w-full h-full object-cover">
                      </div>
                      <span class="font-secondary font-black text-xs uppercase tracking-widest text-epl-blue group-hover:text-epl-gold transition-colors"><?= epl_h($jugador_nav['nombre']) ?></span>
                    </a>
                    <a href="<?= epl_url('logout.php') ?>" class="font-secondary font-bold text-[10px] uppercase tracking-widest text-gray-400 hover:text-red-500 transition-colors ml-2 no-underline">SALIR</a>
                  <?php else: ?>
                    <a href="<?= epl_url('login.php') ?>" class="nav-btn-contacto">
                        Ingresar
                    </a>
                  <?php endif; ?>
                </div>
            </div>

            <!-- BOTÓN MÓVIL -->
            <button id="mobileBtn" class="lg:hidden text-epl-blue focus:outline-none p-2">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" /></svg>
            </button>
        </div>
    </nav>

    <!-- MENÚ MÓVIL DESPLEGABLE -->
    <div id="mobileMenu" class="mobile-menu-overlay">
        <div class="flex justify-between items-center mb-16">
            <img src="<?= epl_url('assets/img/logo-epl-lateral.png') ?>" class="h-10 brightness-0 invert" alt="Logo">
            <button id="closeBtn" class="text-white p-2">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path d="M6 18L18 6M6 6l12 12" /></svg>
            </button>
        </div>
        <div class="flex flex-col gap-8 font-primary text-4xl uppercase text-white tracking-widest">
            <a href="<?= epl_url() ?>" class="text-white no-underline hover:text-epl-gold transition-colors">Inicio</a>
            <a href="<?= epl_url('torneos.php') ?>" class="text-white no-underline hover:text-epl-gold transition-colors">Torneos</a>
            <a href="<?= epl_url('clasificacion.php') ?>" class="text-white no-underline hover:text-epl-gold transition-colors">Clasificación</a>
            <a href="<?= epl_url('resultados.php') ?>" class="text-white no-underline hover:text-epl-gold transition-colors">Resultados</a>
            <a href="<?= epl_url('jugadores.php') ?>" class="text-white no-underline hover:text-epl-gold transition-colors">Jugadores</a>
            
            <?php if ($jugador_nav): ?>
              <?php $dest = $jugador_nav['rol'] === 'admin' ? epl_url('admin/') : epl_url('dashboard.php'); ?>
              <a href="<?= $dest ?>" class="flex items-center justify-center gap-4 w-full border-2 border-epl-gold text-epl-gold py-5 rounded-2xl font-secondary font-black text-sm uppercase tracking-[0.2em] no-underline mt-4">
                  <div class="w-8 h-8 rounded-full overflow-hidden border-2 border-epl-gold">
                    <img src="<?= epl_h(epl_foto_jugador($jugador_nav['foto'], $jugador_nav['nombre'] . ' ' . $jugador_nav['apellido'])) ?>" alt="" class="w-full h-full object-cover">
                  </div>
                  <?= epl_h($jugador_nav['nombre']) ?>
              </a>
              <a href="<?= epl_url('logout.php') ?>" class="text-red-400 no-underline hover:text-red-300 transition-colors text-xl mt-4 text-center">Salir</a>
            <?php else: ?>
              <a href="<?= epl_url('login.php') ?>" class="flex items-center justify-center gap-4 w-full border-2 border-epl-gold bg-epl-gold text-epl-blue py-5 rounded-2xl font-secondary font-black text-sm uppercase tracking-[0.2em] no-underline mt-4 hover:bg-white hover:border-white transition-all">
                  Ingresar
              </a>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Espaciador para nav fijo -->
<div style="height: 90px;"></div>

<script>
    // Polling notificaciones cada 60s
    (function pollNotif() {
        const badge = document.getElementById('notif-badge');
        if (!badge) return;
        setInterval(() => {
            fetch('<?= epl_url('api/notif_count.php') ?>')
                .then(r => r.json())
                .then(data => {
                    if (data.count > 0) {
                        badge.textContent = data.count > 99 ? '99+' : data.count;
                        badge.classList.remove('hidden');
                        badge.style.display = 'flex';
                    } else {
                        badge.classList.add('hidden');
                        badge.style.display = 'none';
                    }
                }).catch(() => {});
        }, 60000);
    })();

    document.addEventListener('DOMContentLoaded', function() {
        const btnOpen = document.getElementById('mobileBtn');
        const btnClose = document.getElementById('closeBtn');
        const menu = document.getElementById('mobileMenu');

        if(btnOpen && btnClose && menu) {
            btnOpen.addEventListener('click', () => {
                menu.classList.add('open');
                document.body.style.overflow = 'hidden';
            });
            btnClose.addEventListener('click', () => {
                menu.classList.remove('open');
                document.body.style.overflow = '';
            });
        }
    });
</script>
