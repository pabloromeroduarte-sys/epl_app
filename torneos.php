<?php
$page_title = 'Torneos';
$active_nav = 'torneos';
require_once 'includes/functions.php';

$db = epl_db();
$ligas = $db->query("SELECT * FROM ligas ORDER BY FIELD(estado,'activa','inscripcion','proximamente','finalizada'), id DESC")->fetchAll();
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
              'proximamente' => ['label'=>'Próximamente',  'text'=>'text-purple-800','bg'=>'bg-purple-100'],
              'inscripcion'  => ['label'=>'Inscripciones', 'text'=>'text-amber-800', 'bg'=>'bg-amber-100'],
              'activa'       => ['label'=>'En juego',      'text'=>'text-emerald-800','bg'=>'bg-emerald-100'],
              'finalizada'   => ['label'=>'Finalizado',    'text'=>'text-gray-800',  'bg'=>'bg-gray-100'],
          ];
          ?>
          <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
            <?php foreach ($ligas as $l):
              $b = $badge[$l['estado']] ?? $badge['activa'];
              $n_equipos = $db->query("SELECT COUNT(*) FROM liga_equipos WHERE liga_id={$l['id']}")->fetchColumn();
              $n_jugados = $db->query("SELECT COUNT(*) FROM partidos WHERE liga_id={$l['id']} AND estado='jugado'")->fetchColumn();
            ?>
            <a href="torneo.php?id=<?= $l['id'] ?>" class="bg-white rounded-2xl overflow-hidden shadow-[0_5px_20px_rgba(0,0,0,0.03)] border border-gray-100 hover:border-epl-gold hover:shadow-xl transition-all group text-decoration-none block flex flex-col h-full">
              <!-- Portada -->
              <div class="h-40 bg-epl-blue relative overflow-hidden shrink-0">
                <?php if ($l['foto_portada']): ?>
                  <img src="<?= epl_url('uploads/ligas/'.$l['foto_portada']) ?>" alt="" class="w-full h-full object-cover opacity-80 group-hover:scale-105 transition-transform duration-500">
                <?php else: ?>
                  <div class="absolute inset-0 flex items-center justify-center font-primary text-5xl text-white/10 group-hover:scale-110 transition-transform duration-500">EPL</div>
                <?php endif; ?>
                <span class="absolute top-4 left-4 px-3 py-1.5 rounded-full text-[10px] font-black uppercase tracking-widest <?= $b['bg'] ?> <?= $b['text'] ?> shadow-sm">
                  <?= $b['label'] ?>
                </span>
              </div>
              <!-- Info -->
              <div class="p-6 flex flex-col flex-1">
                <h3 class="font-primary text-2xl text-epl-blue uppercase leading-tight mb-2 group-hover:text-epl-gold transition-colors"><?= epl_h($l['nombre']) ?></h3>
                
                <?php if ($l['temporada'] || $l['categoria']): ?>
                  <p class="text-xs text-epl-gold font-black uppercase tracking-widest mb-3">
                    <?= $l['temporada'] ? epl_h($l['temporada']) . ' ' : '' ?><?= $l['categoria'] ? '· ' . $l['categoria'] . 'ª cat.' : '' ?>
                  </p>
                <?php endif; ?>

                <?php if ($l['sede']): ?>
                  <p class="text-sm text-gray-500 font-medium mb-2 flex items-center gap-2">📍 <?= epl_h($l['sede']) ?></p>
                <?php endif; ?>

                <?php if ($l['fecha_inicio']): ?>
                  <p class="text-xs text-gray-400 font-bold mb-5 flex items-center gap-2">
                    📅 <?= date('d/m/Y', strtotime($l['fecha_inicio'])) ?>
                    <?= $l['fecha_fin'] ? ' → ' . date('d/m/Y', strtotime($l['fecha_fin'])) : '' ?>
                  </p>
                <?php endif; ?>

                <div class="mt-auto flex items-center justify-between border-t border-gray-100 pt-4">
                  <div class="flex gap-6">
                    <div class="text-center">
                      <div class="font-primary text-2xl text-epl-blue leading-none"><?= $n_equipos ?></div>
                      <div class="text-[10px] text-gray-400 uppercase tracking-widest font-bold mt-1">Equipos</div>
                    </div>
                    <div class="text-center">
                      <div class="font-primary text-2xl text-epl-blue leading-none"><?= $n_jugados ?></div>
                      <div class="text-[10px] text-gray-400 uppercase tracking-widest font-bold mt-1">Jugados</div>
                    </div>
                    <?php if ($l['precio']): ?>
                    <div class="text-center">
                      <div class="font-primary text-2xl text-epl-gold leading-none"><?= '$'.number_format($l['precio'],0,',','.') ?></div>
                      <div class="text-[10px] text-gray-400 uppercase tracking-widest font-bold mt-1">Ins. c/u</div>
                    </div>
                    <?php endif; ?>
                  </div>
                  <div class="w-10 h-10 rounded-full bg-gray-50 flex items-center justify-center text-epl-blue group-hover:bg-epl-gold group-hover:text-white transition-colors shrink-0">
                      <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"></path><path d="m12 5 7 7-7 7"></path></svg>
                  </div>
                </div>
              </div>
            </a>
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
