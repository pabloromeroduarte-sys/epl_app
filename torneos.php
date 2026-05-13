<?php
$page_title = 'Torneos';
$active_nav = 'torneos';
require_once 'includes/functions.php';

$db = epl_db();
$ligas = $db->query("
    SELECT l.*, r.nombre AS recinto_nombre, rs.nombre AS recinto_superior_nombre
    FROM ligas l
    LEFT JOIN recintos r ON r.id = l.recinto_id
    LEFT JOIN recintos rs ON rs.id = r.superior_id
    ORDER BY FIELD(l.estado,'activa','inscripcion','proximamente','finalizada'), l.id DESC
")->fetchAll();
?>
<?php require_once 'includes/header.php'; ?>

<style>
    /* RESET PARA EL CONTENIDO CENTRAL */
    .epl-body-content {
        font-family: 'Montserrat', sans-serif !important;
        background-color: #F5F7F8;
        width: 100vw !important;
        position: relative;
        left: 50%; right: 50%;
        margin-left: -50vw !important; margin-right: -50vw !important;
        min-height: 100vh;
    }

    /* HERO SECTION */
    .hero-section {
        background-color: #0A1421;
        background-image: linear-gradient(to bottom, rgba(10, 20, 33, 0.90) 0%, rgba(28, 47, 72, 0.75) 50%, rgba(10, 20, 33, 0.95) 100%), 
                          url('https://epleague.cl/wp-content/uploads/2026/03/tennis-paddles-balls-arrangement-scaled.jpg');
        background-size: cover; background-position: center center; width: 100%; display: block;
    }
    .giant-title { line-height: 0.9; letter-spacing: -0.02em; }

    /* CONTENEDORES BÁSICOS */
    .sidebar-card { background: white; border-radius: 20px; padding: 2.5rem; box-shadow: 0 10px 30px rgba(0,0,0,0.02); border-left: 6px solid #C9A762; margin-bottom: 2rem; }
    
    /* ANIMACIÓN DEL BOTÓN EN VIVO */
    @keyframes pulse-live {
        0% { opacity: 1; transform: scale(1); }
        50% { opacity: 0.5; transform: scale(1.2); }
        100% { opacity: 1; transform: scale(1); }
    }
    .animate-pulse-live { animation: pulse-live 1.5s infinite; }
</style>

<div class="epl-body-content antialiased">

    <!-- HERO SECTION TORNEOS -->
    <header class="hero-section pt-[180px] pb-24 md:pt-[240px] md:pb-44 text-center px-6">
        <div class="max-w-5xl mx-auto">
            <span class="font-secondary font-black text-epl-gold tracking-[0.6em] uppercase text-[10px] md:text-xs mb-8 block opacity-90 drop-shadow-lg">
                Plataforma de Encuentros Oficiales
            </span>
            <h1 class="giant-title text-6xl md:text-8xl lg:text-[9rem] text-white font-primary mb-10 drop-shadow-2xl uppercase">
                Ligas y <span class="text-epl-gold">Resultados</span>
            </h1>
            <p class="mt-4 max-w-2xl mx-auto text-lg md:text-xl text-gray-200 font-secondary font-medium mb-12 leading-relaxed opacity-90">
                Inscríbete en las próximas fechas, sigue el calendario oficial al instante y vive la experiencia de nuestra comunidad.
            </p>
        </div>
    </header>

    <!-- ZONA 1: DIRECTORIO DE TORNEOS -->
    <section class="max-w-7xl mx-auto px-8 py-20">
        <div class="mb-12 border-b-4 border-epl-gold/20 pb-4">
            <h2 class="text-4xl text-epl-blue font-primary uppercase tracking-tight">Directorio de <span class="text-epl-gold">Ligas</span></h2>
        </div>
        
        <!-- SHORTCODE: MUESTRA LAS TARJETAS (PHP INYECTADO) -->
        <?php if (empty($ligas)): ?>
          <p class="text-gray-500 font-medium">No hay torneos disponibles aún.</p>
        <?php else: ?>
          <?php
          $badge = [
              'proximamente' => ['label'=>'PRÓXIMAMENTE',  'text'=>'text-gray-800','bg'=>'bg-gray-200', 'eyebrow'=>'PRÓXIMO TORNEO'],
              'inscripcion'  => ['label'=>'ABIERTAS',      'text'=>'text-white',   'bg'=>'bg-green-500', 'eyebrow'=>'INSCRIPCIONES DISPONIBLES'],
              'activa'       => ['label'=>'EN JUEGO',      'text'=>'text-epl-gold','bg'=>'bg-epl-blue',  'eyebrow'=>'TORNEO ACTIVO'],
              'finalizada'   => ['label'=>'FINALIZADO',    'text'=>'text-gray-500','bg'=>'bg-gray-100',  'eyebrow'=>'TORNEO FINALIZADO'],
          ];
          ?>
          <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
            <?php foreach ($ligas as $l):
              $b = $badge[$l['estado']] ?? $badge['activa'];
              
              // Lógica de imágenes dinámicas
              $is_women = ($l['sexo'] === 'femenino');
              $is_americano = ($l['tipo'] === 'torneo');
              
              if ($l['foto_portada']) {
                  $img_src = epl_url('uploads/ligas/'.$l['foto_portada']);
              } else {
                  if ($is_americano && $is_women) {
                      $img_src = epl_url('assets/img/portada-americano-women.png');
                  } elseif ($is_americano && !$is_women) {
                      $img_src = epl_url('assets/img/portada-americano-men.png');
                  } elseif (!$is_americano && $is_women) {
                      $img_src = epl_url('assets/img/portada-liga-women.png');
                  } else {
                      $img_src = epl_url('assets/img/portada-liga-men.png');
                  }
              }

              // Nombre corto para la miniatura
              $nombre_corto = $l['categoria'] ? $l['categoria'] . ' Categoría' : ($l['tipo'] === 'liga' ? 'Liga Padel' : 'Americano');
            ?>
            <div class="bg-white rounded-[24px] overflow-hidden shadow-[0_5px_15px_rgba(0,0,0,0.05)] border border-gray-100 flex flex-col transition-all hover:shadow-[0_10px_25px_rgba(201,167,98,0.15)] hover:border-epl-gold group">
              
              <!-- Portada de la Tarjeta -->
              <div class="relative h-[200px] bg-epl-blue shrink-0 overflow-hidden">
                <img src="<?= $img_src ?>" alt="" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-700">
                <div class="absolute inset-0 bg-gradient-to-t from-black/80 via-black/10 to-transparent"></div>
                
                <!-- Badge de Estado (Top Right) -->
                <span class="absolute top-4 right-4 px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-widest <?= $b['bg'] ?> <?= $b['text'] ?> shadow-sm flex items-center gap-1.5 z-10">
                  <span class="w-1.5 h-1.5 rounded-full bg-current"></span>
                  <?= $b['label'] ?>
                </span>
                
                <!-- Nombre Corto en la Imagen (Bottom Left) -->
                <div class="absolute bottom-4 left-4 bg-[#0A1421]/80 backdrop-blur-md px-3.5 py-1.5 rounded-lg border border-white/10 z-10">
                  <span class="text-epl-gold font-primary text-sm uppercase tracking-wider"><?= epl_h($nombre_corto) ?></span>
                </div>
              </div>

              <!-- Contenido de la Tarjeta -->
              <div class="p-7 flex flex-col flex-1 bg-white">
                <!-- Eyebrow -->
                <p class="text-[10px] text-epl-gold font-black uppercase tracking-widest mb-3">
                  <?= $b['eyebrow'] ?>
                </p>
                
                <!-- Título Principal -->
                <div class="mb-5">
                    <h3 class="font-primary text-[22px] text-epl-blue uppercase leading-tight mb-1">
                      <?= epl_h($l['nombre']) ?>
                    </h3>
                    <?php 
                    $fmt_label = [
                        'liga_regular'=>'Liga regular',
                        'liga_playoff'=>'Liga + Playoff',
                        'mata_mata'=>'Llaves',
                        'grupos_mata_mata'=>'Fase de grupos + Llaves'
                    ];
                    $f_text = $fmt_label[$l['formato']] ?? '';
                    $s_text = ucfirst($l['sexo'] ?? '');
                    ?>
                    <p class="text-[10px] font-bold text-gray-400 uppercase tracking-wider">
                        <?= $s_text ?> · <?= $f_text ?>
                    </p>
                </div>
                
                <!-- Detalles de Fecha y Sede -->
                <div class="space-y-3.5 mb-6">
                  <div class="flex items-start gap-3.5 text-gray-600">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 mt-0.5 text-epl-gold shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
                    <span class="text-sm font-semibold"><?= $l['fecha_inicio'] ? date('d-m-Y', strtotime($l['fecha_inicio'])) : 'Fecha por confirmar' ?></span>
                  </div>
                  <div class="flex items-start gap-3.5 text-gray-500">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 mt-0.5 text-epl-gold shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path><circle cx="12" cy="10" r="3"></circle></svg>
                    <div class="flex flex-col">
                      <span class="text-sm font-semibold text-epl-blue">
                        <?= $l['recinto_nombre'] ? epl_h($l['recinto_nombre']) : ($l['sede'] ? epl_h($l['sede']) : 'Sede por confirmar') ?>
                      </span>
                    </div>
                  </div>
                </div>
                
                <!-- Botón Inferior -->
                <div class="mt-auto pt-2">
                  <a href="torneo.php?id=<?= $l['id'] ?>" class="w-full block text-center bg-[#1E293B] text-white py-3.5 rounded-[12px] text-[11px] font-black uppercase tracking-[0.15em] hover:bg-epl-blue transition-colors shadow-md relative overflow-hidden group-hover:bg-epl-blue">
                    <div class="relative z-10 flex items-center justify-center gap-2">
                      <?php if ($l['estado'] === 'activa'): ?>
                        <span class="w-1.5 h-1.5 rounded-full bg-red-500 animate-pulse"></span>
                        RESULTADOS EN VIVO
                      <?php elseif ($l['estado'] === 'inscripcion'): ?>
                        INSCRIBIR PAREJA AHORA
                      <?php else: ?>
                        VER DETALLES
                      <?php endif; ?>
                    </div>
                  </a>
                </div>
              </div>

            </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
    </section>

    <!-- ZONA 2: BENEFICIOS (REEMPLAZA AL ANTIGUO CENTRO DE ESTADÍSTICAS) -->
    <main class="max-w-7xl mx-auto px-4 sm:px-8 py-24 border-t border-gray-200">
        <div class="mb-10 border-b-4 border-epl-gold/20 pb-4">
            <h2 class="text-4xl text-epl-blue font-primary uppercase tracking-tight">El Circuito <span class="text-epl-gold">EPL</span></h2>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-4 gap-12">
            
            <!-- ÁREA PRINCIPAL (NUEVA ZONA DE BENEFICIOS) -->
            <div class="lg:col-span-3 order-2 lg:order-1">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    
                    <!-- Beneficio 1 -->
                    <div class="bg-white p-8 md:p-10 rounded-[30px] border border-gray-100 shadow-[0_5px_20px_rgba(0,0,0,0.03)] hover:border-epl-gold hover:shadow-xl transition-all group">
                        <div class="w-14 h-14 bg-epl-blue text-epl-gold rounded-2xl flex items-center justify-center text-3xl mb-6 group-hover:scale-110 transition-transform shadow-md">📊</div>
                        <h3 class="font-primary text-2xl text-epl-blue uppercase tracking-wide mb-3">Estadísticas Pro</h3>
                        <p class="text-gray-500 text-sm font-medium leading-relaxed">Olvídate de los Excel. Al unirte, tendrás un perfil profesional con tu win-rate, historial detallado de partidos y puntos acumulados en tiempo real.</p>
                    </div>

                    <!-- Beneficio 2 -->
                    <div class="bg-white p-8 md:p-10 rounded-[30px] border border-gray-100 shadow-[0_5px_20px_rgba(0,0,0,0.03)] hover:border-epl-gold hover:shadow-xl transition-all group">
                        <div class="w-14 h-14 bg-epl-blue text-epl-gold rounded-2xl flex items-center justify-center text-3xl mb-6 group-hover:scale-110 transition-transform shadow-md">⚖️</div>
                        <h3 class="font-primary text-2xl text-epl-blue uppercase tracking-wide mb-3">Nivel Garantizado</h3>
                        <p class="text-gray-500 text-sm font-medium leading-relaxed">Filtramos estrictamente las categorías. Jugarás partidos verdaderamente competitivos y parejos contra rivales de tu mismo nivel, sin sorpresas.</p>
                    </div>

                    <!-- Beneficio 3 -->
                    <div class="bg-white p-8 md:p-10 rounded-[30px] border border-gray-100 shadow-[0_5px_20px_rgba(0,0,0,0.03)] hover:border-epl-gold hover:shadow-xl transition-all group">
                        <div class="w-14 h-14 bg-epl-blue text-epl-gold rounded-2xl flex items-center justify-center text-3xl mb-6 group-hover:scale-110 transition-transform shadow-md">🎁</div>
                        <h3 class="font-primary text-2xl text-epl-blue uppercase tracking-wide mb-3">Welcome Pack</h3>
                        <p class="text-gray-500 text-sm font-medium leading-relaxed">En cada fecha oficial te espera hidratación, regalos de nuestros auspiciadores e increíbles premios reservados para los campeones de liga.</p>
                    </div>

                    <!-- Beneficio 4 -->
                    <div class="bg-white p-8 md:p-10 rounded-[30px] border border-gray-100 shadow-[0_5px_20px_rgba(0,0,0,0.03)] hover:border-epl-gold hover:shadow-xl transition-all group">
                        <div class="w-14 h-14 bg-epl-blue text-epl-gold rounded-2xl flex items-center justify-center text-3xl mb-6 group-hover:scale-110 transition-transform shadow-md">🍻</div>
                        <h3 class="font-primary text-2xl text-epl-blue uppercase tracking-wide mb-3">Ambiente de Club</h3>
                        <p class="text-gray-500 text-sm font-medium leading-relaxed">Fomentamos la comunidad por sobre todo. Conoce a tus rivales, haz *networking* y comparte un gran rato después de dejarlo todo en la pista.</p>
                    </div>

                </div>
            </div>

            <!-- SIDEBAR -->
            <aside class="lg:col-span-1 order-1 lg:order-2">
                
                <!-- CAJA 1: REPROGRAMACIÓN -->
                <div class="sidebar-card">
                    <h4 class="font-primary text-2xl text-epl-blue mb-4 uppercase tracking-tight leading-none">Reprogramar <br><span class="text-epl-gold">Partido</span></h4>
                    <p class="font-secondary text-xs text-gray-500 font-bold leading-relaxed mb-4">
                        ¿Necesitas reagendar un partido? Recuerda hacerlo acá.
                    </p>
                    <div class="bg-yellow-50 border-l-4 border-yellow-400 p-3 mb-6">
                        <p class="text-[10px] text-yellow-800 font-black uppercase tracking-wider">⚠️ REGLA IMPORTANTE</p>
                        <p class="text-[10px] text-yellow-700 font-medium leading-tight mt-1">Debe solicitarse con un mínimo de <strong>48 horas de anticipación</strong>.</p>
                    </div>
                    <a href="<?= epl_url('reprogramar.php') ?>" class="block w-full text-center bg-epl-blue text-white py-4 rounded-xl font-secondary font-black text-[11px] uppercase tracking-[0.15em] hover:bg-epl-gold hover:text-epl-blue transition-all text-decoration-none shadow-md">
                        SOLICITAR CAMBIO
                    </a>
                </div>

                <!-- CAJA 2: MI PERFIL -->
                <div class="bg-epl-blue p-8 rounded-3xl text-white shadow-xl relative overflow-hidden">
                    <div class="absolute -top-10 -right-10 w-32 h-32 bg-epl-gold/10 rounded-full blur-xl pointer-events-none"></div>
                    <div class="relative z-10">
                        <h4 class="font-primary text-xl text-epl-gold mb-3 uppercase leading-none">Mi Perfil</h4>
                        <p class="font-secondary text-xs text-gray-300 leading-relaxed mb-6">
                            Revisa tus estadísticas individuales, tu win-rate y el registro de tus enfrentamientos históricos.
                        </p>
                        <a href="<?= epl_url('mi_perfil.php') ?>" class="block w-full text-center bg-white text-epl-blue py-3.5 rounded-xl font-secondary font-black text-[10px] uppercase tracking-widest hover:bg-epl-gold hover:text-white transition-colors text-decoration-none shadow-lg">
                            Ver Mis Estadísticas
                        </a>
                    </div>
                </div>
                
            </aside>
            
        </div>
    </main>
</div>

<?php require_once 'includes/footer.php'; ?>
