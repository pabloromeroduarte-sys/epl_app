<?php
$page_title = 'Inicio';
$active_nav = 'inicio';
require_once 'includes/functions.php';

$liga = epl_liga_activa();
$clasificacion = $liga ? array_slice(epl_clasificacion($liga['id']), 0, 5) : [];
$ultimos_partidos = $liga ? array_slice(epl_partidos_liga($liga['id'], 'jugado'), -5) : [];
$proximos = $liga ? array_slice(epl_partidos_liga($liga['id'], 'pendiente'), 0, 3) : [];
?>
<?php require_once 'includes/header.php'; ?>



<style>
    /* RESET GENERAL PARA LA PÁGINA - Asegura ancho completo sin scroll horizontal */
    .epl-body-wrapper {
        width: 100vw !important;
        max-width: 100vw !important;
        position: relative;
        left: 50%; right: 50%;
        margin-left: -50vw !important; margin-right: -50vw !important;
        font-family: 'Montserrat', sans-serif;
        background-color: #F5F7F8;
        overflow-x: hidden;
    }

    /* ---------------------------------------------------
       HERO SECTION (PORTADA)
       --------------------------------------------------- */
    .hero-section {
        background-color: #0A1421;
        /* Imagen de fondo original restaurada */
        background-image: linear-gradient(to bottom, rgba(10, 20, 33, 0.85) 0%, rgba(28, 47, 72, 0.80) 50%, rgba(10, 20, 33, 0.95) 100%), 
                          url('https://respaldo.epleague.cl/wp-content/uploads/2026/03/high-angle-tennis-palette-balls-arrangement-scaled.jpg');
        background-size: cover;
        background-position: center center;
        /* scroll: WebViews de IDEs suelen ir mal con background-attachment: fixed + cover */
        background-attachment: scroll;
        width: 100%;
        display: block;
    }
    .giant-title { line-height: 0.95; letter-spacing: -0.01em; }

    /* ---------------------------------------------------
       EFECTOS Y TARJETAS
       --------------------------------------------------- */
    .feature-card-tw {
        background: white; border-radius: 20px; padding: 40px 30px;
        box-shadow: 0 10px 30px rgba(0,0,0,0.03); border: 1px solid #f1f5f9;
        transition: all 0.3s ease; text-align: center;
    }
    .feature-card-tw:hover {
        transform: translateY(-5px); border-color: #C9A762; box-shadow: 0 15px 35px rgba(201,167,98,0.1);
    }
    .feature-icon-wrapper-tw {
        width: 80px; height: 80px; margin: 0 auto 20px auto; border-radius: 20px;
        background: #f8fafc; display: flex; align-items: center; justify-content: center;
        color: #1C2F48; transition: all 0.3s ease;
    }
    .feature-card-tw:hover .feature-icon-wrapper-tw { background: #1C2F48; color: #C9A762; }

    /* Estilos extra para las tablas de PHP integradas con Tailwind */
    .epl-php-section { margin-top: 3rem; }
    .epl-php-section .section-eyebrow { color: #C9A762; font-weight: 700; text-transform: uppercase; letter-spacing: 0.1em; font-size: 0.85rem; margin-bottom: 0.5rem; }
    .epl-php-section .section-title { font-family: 'Anton', sans-serif; font-size: 2.5rem; color: #1C2F48; text-transform: uppercase; margin-bottom: 2rem; }
    .epl-php-section .partido-card { margin-bottom: 1rem; border: 1px solid #e2e8f0; }
</style>

<div class="epl-body-wrapper antialiased text-gray-700">

    <!-- ==========================================
         1. HERO SECTION (ATRAPAR AL USUARIO)
         ========================================== -->
    <header class="hero-section pt-24 pb-24 md:pt-32 md:pb-40 text-center px-6">
        <div class="max-w-5xl mx-auto relative z-10">
            
            <!-- Logo/Icono Superior -->
            <div class="mx-auto w-24 h-24 mb-8 bg-epl-gold/10 rounded-full flex items-center justify-center border border-epl-gold/30 backdrop-blur-sm drop-shadow-xl shadow-[0_0_30px_rgba(201,167,98,0.2)]">
                <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="text-epl-gold">
                    <path d="M12 16c3.5 0 6.5-3 6.5-7.5S15.5 2 12 2 5.5 4 5.5 8.5 8.5 16 12 16z" />
                    <path d="M12 16v6" /><path d="M10 22h4" />
                    <circle cx="10" cy="7" r="0.5" fill="currentColor"/><circle cx="14" cy="7" r="0.5" fill="currentColor"/><circle cx="12" cy="9" r="0.5" fill="currentColor"/><circle cx="9" cy="10" r="0.5" fill="currentColor"/><circle cx="15" cy="10" r="0.5" fill="currentColor"/><circle cx="11" cy="12" r="0.5" fill="currentColor"/><circle cx="13" cy="12" r="0.5" fill="currentColor"/>
                </svg>
            </div>

            <span class="font-black text-epl-gold tracking-[0.5em] uppercase text-[10px] md:text-xs mb-4 block opacity-90 drop-shadow-lg">
                La Liga Más Competitiva de Chile
            </span>
            
            <h1 class="giant-title text-5xl md:text-7xl lg:text-[7.5rem] text-white font-primary mb-8 drop-shadow-2xl uppercase">
                Bienvenidos a la <span class="text-epl-gold">Elite</span> <br class="hidden md:block"> del Pádel
            </h1>
            
            <p class="mt-4 max-w-2xl mx-auto text-lg md:text-xl text-gray-200 font-medium mb-12 leading-relaxed opacity-90">
                Mucho más que un torneo. Somos el circuito donde el deporte de alto nivel, la competencia justa y el tercer tiempo se encuentran.
            </p>
            
            <div class="flex flex-col md:flex-row justify-center gap-5 mt-12 relative z-10">
                <a href="#el-circuito" class="bg-epl-gold text-epl-blue px-10 py-5 rounded-xl font-black uppercase text-[13px] tracking-widest hover:bg-white transition-all shadow-[0_0_20px_rgba(201,167,98,0.3)] hover:-translate-y-1 flex items-center justify-center">
                    Ver Torneos
                </a>
                <a href="#nosotros" class="border-2 border-white/30 text-white px-10 py-5 rounded-xl font-secondary font-black uppercase text-[13px] tracking-[0.15em] hover:bg-white hover:text-epl-blue transition-all duration-300 text-decoration-none hover:-translate-y-1 flex items-center justify-center gap-3 group">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="group-hover:translate-y-1 transition-transform">
                        <polyline points="6 9 12 15 18 9"></polyline>
                    </svg>
                    Saber Más
                </a>
            </div>
            
            <p class="text-gray-400 text-[10px] md:text-[11px] font-bold mt-8 uppercase tracking-widest opacity-80 relative z-10">
                * Para inscribirte a los torneos debes tener tu ficha de jugador creada.
            </p>
        </div>
    </header>

    <!-- ==========================================
         2. SECCIÓN DE INTRODUCCIÓN (¿QUÉ ES EPL?)
         ========================================== -->
    <section class="py-12 md:py-24 bg-white px-4 md:px-6 relative z-20 rounded-t-[40px] -mt-10 shadow-[0_-10px_40px_rgba(0,0,0,0.05)]">
        <div class="max-w-6xl mx-auto">
            <div class="text-center mb-8 md:mb-16">
                <span class="text-epl-gold font-black text-[10px] uppercase tracking-[0.2em] mb-2 block">Por qué elegirnos</span>
                <h2 class="text-4xl md:text-5xl text-epl-blue font-primary uppercase tracking-tight">Eleva tu <span class="text-epl-gold">Nivel</span></h2>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <!-- Pilar 1 -->
                <div class="feature-card-tw">
                    <div class="feature-icon-wrapper-tw">
                        <svg xmlns="http://www.w3.org/2000/svg" width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9H4.5a2.5 2.5 0 0 1 0-5H6"></path><path d="M18 9h1.5a2.5 2.5 0 0 0 0-5H18"></path><path d="M4 22h16"></path><path d="M10 14.66V17c0 .55-.47.98-.97 1.21C7.85 18.75 7 20.24 7 22"></path><path d="M14 14.66V17c0 .55.47.98.97 1.21C16.15 18.75 17 20.24 17 22"></path><path d="M18 2H6v7a6 6 0 0 0 12 0V2Z"></path></svg>
                    </div>
                    <h4 class="font-primary text-2xl text-epl-blue uppercase mb-3">Competencia Real</h4>
                    <p class="text-sm text-gray-500 leading-relaxed font-medium">Categorías estrictamente medidas. Te enfrentarás a parejas de tu mismo nivel garantizando partidos disputados y justos.</p>
                </div>
                <!-- Pilar 2 -->
                <div class="feature-card-tw">
                    <div class="feature-icon-wrapper-tw">
                        <svg xmlns="http://www.w3.org/2000/svg" width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5c-2.2 0-4 1.8-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
                    </div>
                    <h4 class="font-primary text-2xl text-epl-blue uppercase mb-3">Tercer Tiempo</h4>
                    <p class="text-sm text-gray-500 leading-relaxed font-medium">El partido no termina en el punto de oro. Fomentamos el networking, la camaradería y un ambiente de club inigualable.</p>
                </div>
                <!-- Pilar 3 -->
                <div class="feature-card-tw">
                    <div class="feature-icon-wrapper-tw">
                        <svg xmlns="http://www.w3.org/2000/svg" width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3v18h18"></path><path d="m19 9-5 5-4-4-3 3"></path></svg>
                    </div>
                    <h4 class="font-primary text-2xl text-epl-blue uppercase mb-3">Plataforma Pro</h4>
                    <p class="text-sm text-gray-500 leading-relaxed font-medium">Tu propio perfil de jugador, estadísticas en vivo, historial de partidos y ranking actualizado al instante desde el celular.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- ==========================================
         3. EL CIRCUITO (TARJETAS COMPACTAS MINIS)
         ========================================== -->
    <section id="el-circuito" class="py-10 md:py-24 bg-[#F5F7F8] px-4 md:px-6 border-y border-gray-200">
        <div class="max-w-7xl mx-auto">
            <div class="flex flex-col md:flex-row justify-between items-start md:items-end mb-8 md:mb-12 gap-4 md:gap-6">
                <div>
                    <h2 class="text-4xl md:text-5xl text-epl-blue font-primary uppercase tracking-tight">Próximos <span class="text-epl-gold">Encuentros</span></h2>
                </div>
                <a href="torneos.php" class="text-epl-blue font-black text-[10px] uppercase tracking-[0.2em] hover:text-epl-gold transition-colors flex items-center gap-2">
                    Ver Calendario Completo <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"></path><path d="m12 5 7 7-7 7"></path></svg>
                </a>
            </div>

            <!-- 🚨 INYECCIÓN DE FUNCIONES PHP DE LA PLATAFORMA 🚨 -->
            <div class="epl-php-section">
                <?php if ($liga && $clasificacion): ?>
                <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6 md:p-8 mb-12">
                  <p class="section-eyebrow">Liga activa</p>
                  <h2 class="section-title"><?= epl_h($liga['nombre']) ?></h2>
                  <div class="tabla-clasificacion mb-4 overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                      <thead>
                        <tr class="bg-epl-blue text-white text-xs uppercase tracking-wider">
                          <th class="p-3 text-center w-12">#</th>
                          <th class="p-3">Equipo</th>
                          <th class="p-3 text-center">PJ</th>
                          <th class="p-3 text-center">PG</th>
                          <th class="p-3 text-center">PP</th>
                          <th class="p-3 text-center">Pts</th>
                        </tr>
                      </thead>
                      <tbody>
                        <?php foreach ($clasificacion as $i => $row): ?>
                        <tr class="border-b border-gray-100 hover:bg-gray-50">
                          <td class="p-3 text-center font-bold">
                            <span class="inline-flex items-center justify-center w-6 h-6 rounded-full text-xs <?= $i == 0 ? 'bg-yellow-400 text-yellow-900' : ($i == 1 ? 'bg-gray-300 text-gray-800' : ($i == 2 ? 'bg-orange-400 text-white' : 'bg-gray-100 text-gray-500')) ?>">
                              <?= $i+1 ?>
                            </span>
                          </td>
                          <td class="p-3">
                            <div class="flex items-center gap-3">
                              <div class="flex -space-x-2">
                                <img class="w-8 h-8 rounded-full border-2 border-white object-cover" src="<?= epl_h(epl_foto_jugador($row['j1_foto'], $row['j1_nombre'].' '.$row['j1_apellido'])) ?>" alt="">
                                <img class="w-8 h-8 rounded-full border-2 border-white object-cover" src="<?= epl_h(epl_foto_jugador($row['j2_foto'], $row['j2_nombre'].' '.$row['j2_apellido'])) ?>" alt="">
                              </div>
                              <span class="font-semibold text-epl-blue text-sm"><?= epl_h($row['equipo_nombre']) ?></span>
                            </div>
                          </td>
                          <td class="p-3 text-center text-sm"><?= $row['pj'] ?></td>
                          <td class="p-3 text-center text-sm"><?= $row['pg'] ?></td>
                          <td class="p-3 text-center text-sm"><?= $row['pp'] ?></td>
                          <td class="p-3 text-center font-bold text-epl-blue"><?= $row['puntos'] ?></td>
                        </tr>
                        <?php endforeach; ?>
                      </tbody>
                    </table>
                  </div>
                  <a href="clasificacion.php" class="text-epl-blue font-bold text-xs uppercase tracking-wider hover:text-epl-gold transition-colors">Ver clasificación completa →</a>
                </div>
                <?php endif; ?>

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-12">
                    <!-- Últimos Resultados -->
                    <?php if ($ultimos_partidos): ?>
                    <div>
                      <p class="section-eyebrow">Resultados recientes</p>
                      <h2 class="section-title text-3xl">Últimos Partidos</h2>
                      <div class="partidos-list space-y-3">
                        <?php foreach (array_reverse($ultimos_partidos) as $p): ?>
                        <div class="partido-card bg-white p-4 rounded-xl flex items-center justify-between shadow-sm">
                          <div class="flex flex-col gap-1 flex-1">
                            <span class="font-bold text-sm text-epl-blue"><?= epl_h($p['local_nombre']) ?></span>
                            <?php if ($p['ganador_id'] == $p['equipo_local_id']): ?>
                              <span class="bg-green-100 text-green-800 text-[10px] font-bold px-2 py-0.5 rounded uppercase w-max">Ganador</span>
                            <?php endif; ?>
                          </div>
                          <div class="flex flex-col items-center px-4 shrink-0">
                            <span class="font-primary text-2xl text-epl-blue tracking-widest"><?= $p['sets_local'] ?> - <?= $p['sets_visitante'] ?></span>
                            <span class="text-xs text-gray-400 font-bold bg-gray-100 px-2 py-0.5 rounded mt-1">JUGADO</span>
                          </div>
                          <div class="flex flex-col gap-1 flex-1 items-end text-right">
                            <span class="font-bold text-sm text-epl-blue"><?= epl_h($p['visitante_nombre']) ?></span>
                            <?php if ($p['ganador_id'] == $p['equipo_visitante_id']): ?>
                              <span class="bg-green-100 text-green-800 text-[10px] font-bold px-2 py-0.5 rounded uppercase w-max">Ganador</span>
                            <?php endif; ?>
                          </div>
                        </div>
                        <?php endforeach; ?>
                      </div>
                      <a href="resultados.php" class="inline-block mt-4 text-epl-blue font-bold text-xs uppercase tracking-wider hover:text-epl-gold transition-colors">Ver todos los resultados →</a>
                    </div>
                    <?php endif; ?>

                    <!-- Próximos Partidos -->
                    <?php if ($proximos): ?>
                    <div>
                      <p class="section-eyebrow">Agenda</p>
                      <h2 class="section-title text-3xl">Próximos Partidos</h2>
                      <div class="partidos-list space-y-3">
                        <?php foreach ($proximos as $p): ?>
                        <div class="partido-card bg-white p-4 rounded-xl flex items-center justify-between shadow-sm border-l-4 border-l-epl-gold">
                          <div class="flex flex-col gap-1 flex-1">
                            <span class="font-bold text-sm text-epl-blue"><?= epl_h($p['local_nombre']) ?></span>
                          </div>
                          <div class="flex flex-col items-center px-4 shrink-0 text-center">
                            <span class="text-epl-gold font-bold text-xs mb-1">
                              <?= $p['fecha_programada'] ? date('d/m H:i', strtotime($p['fecha_programada'])) : 'Por definir' ?>
                            </span>
                            <span class="text-xs text-orange-800 bg-orange-100 font-bold px-2 py-0.5 rounded">PENDIENTE</span>
                          </div>
                          <div class="flex flex-col gap-1 flex-1 items-end text-right">
                            <span class="font-bold text-sm text-epl-blue"><?= epl_h($p['visitante_nombre']) ?></span>
                          </div>
                        </div>
                        <?php endforeach; ?>
                      </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <!-- FIN DE INYECCIÓN PHP -->

        </div>
    </section>

    <!-- ==========================================
         4. FILOSOFÍA / HISTORIA
         ========================================== -->
    <section id="nosotros" class="py-12 md:py-24 bg-white px-4 md:px-6 scroll-mt-20">
        <div class="max-w-6xl mx-auto flex flex-col lg:flex-row items-center gap-8 lg:gap-16">
            
            <div class="lg:w-1/2 relative pb-12 lg:pb-0">
                <!-- Decoraciones -->
                <div class="absolute -top-6 -left-6 w-32 h-32 bg-epl-gold/20 rounded-full blur-2xl"></div>
                <div class="absolute -bottom-6 -right-6 w-32 h-32 bg-epl-blue/10 rounded-full blur-2xl"></div>
                
                <!-- IMAGEN OFICIAL FILOSOFÍA -->
                <img src="https://respaldo.epleague.cl/wp-content/uploads/2026/04/Gemini_Generated_Image_hp9omohp9omohp9o-Editado-scaled.png" alt="Comunidad Elite Padel League" class="rounded-3xl shadow-2xl relative z-10 w-full object-cover aspect-[4/3]">
                
                <!-- Badge Flotante -->
                <div class="absolute -bottom-8 right-8 bg-white p-5 rounded-2xl shadow-xl z-20 flex items-center gap-4">
                    <div class="w-12 h-12 bg-epl-blue rounded-full flex items-center justify-center text-white font-['Anton'] text-xl">1°</div>
                    <div>
                        <p class="font-black text-epl-blue text-sm uppercase leading-tight">Liga Amateur</p>
                        <p class="text-xs text-gray-500 font-medium">De alto rendimiento</p>
                    </div>
                </div>
            </div>

            <div class="lg:w-1/2 mt-10 lg:mt-0">
                <span class="text-epl-gold font-black text-[10px] uppercase tracking-[0.2em] mb-4 block">Nuestra Filosofía</span>
                <h2 class="text-4xl md:text-5xl text-epl-blue font-primary uppercase tracking-tight mb-6 leading-none">Más que un torneo,<br> <span class="text-epl-gold">Una Familia</span></h2>
                
                <div class="space-y-5 text-gray-600 font-medium leading-relaxed mb-8">
                    <p>Elite Padel League nació de la pasión de un grupo de jugadores que buscaban algo más que arrendar una cancha los fines de semana. Buscábamos la adrenalina de una competencia estructurada, pero sin perder el espíritu amateur.</p>
                    <p>Creemos firmemente que el pádel es el deporte social por excelencia de esta década. Por eso, en cada fecha nos preocupamos de que la organización sea impecable, los rivales sean de tu nivel, y que siempre haya una bebida fría esperándote al salir del 20x10.</p>
                </div>

                <a href="reglamento.php" class="inline-flex items-center gap-3 bg-gray-100 text-epl-blue px-8 py-4 rounded-xl font-black text-[11px] uppercase tracking-widest hover:bg-gray-200 transition-colors">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"></path><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"></path></svg>
                    Conoce el Reglamento
                </a>
            </div>

        </div>
    </section>

    <!-- ==========================================
         5. LA EXPERIENCIA EPL (LÍNEA DE TIEMPO + INSTAGRAM)
         ========================================== -->
    <section class="py-12 md:py-24 bg-epl-blue text-white px-4 md:px-6 border-t-[6px] border-epl-gold relative overflow-hidden">
        <!-- Luces de fondo decorativas -->
        <div class="absolute top-0 left-1/2 -translate-x-1/2 w-full h-full max-w-4xl bg-epl-gold/5 blur-[100px] pointer-events-none"></div>

        <div class="max-w-6xl mx-auto relative z-10">
            
            <div class="text-center mb-8 md:mb-16">
                <span class="text-epl-gold font-black text-[10px] uppercase tracking-[0.2em] mb-2 block">¿Cómo funciona?</span>
                <h2 class="text-4xl md:text-5xl font-primary uppercase tracking-tight">La Experiencia <span class="text-epl-gold">EPL</span></h2>
            </div>

            <!-- LÍNEA DE TIEMPO (TIMELINE) -->
            <div class="relative mt-12 mb-20 max-w-5xl mx-auto">
                <!-- Línea horizontal (Desktop) -->
                <div class="hidden md:block absolute top-[48px] left-[10%] right-[10%] h-1 bg-epl-gold/20 rounded-full z-0"></div>
                <!-- Línea vertical (Mobile) -->
                <div class="md:hidden absolute top-[48px] bottom-0 left-[48px] w-1 bg-epl-gold/20 rounded-full z-0"></div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-12 md:gap-8 relative z-10">
                    
                    <!-- PASO 1 -->
                    <div class="timeline-step flex flex-row md:flex-col items-center md:text-center gap-6 md:gap-0 group">
                        <div class="w-24 h-24 shrink-0 rounded-full bg-epl-gold text-epl-blue flex items-center justify-center border-[6px] border-epl-blue relative shadow-[0_0_20px_rgba(201,167,98,0.3)]">
                            <span class="absolute -top-1 -right-1 bg-white text-epl-blue font-black w-7 h-7 rounded-full flex items-center justify-center text-sm shadow-md">1</span>
                            <svg xmlns="http://www.w3.org/2000/svg" width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 12 20 22 4 22 4 12"></polyline><rect x="2" y="7" width="20" height="5"></rect><line x1="12" y1="22" x2="12" y2="7"></line><path d="M12 7H7.5a2.5 2.5 0 0 1 0-5C11 2 12 7 12 7z"></path><path d="M12 7h4.5a2.5 2.5 0 0 0 0-5C13 2 12 7 12 7z"></path></svg>
                        </div>
                        <div class="md:mt-8">
                            <h3 class="text-2xl font-primary uppercase tracking-wide text-white mb-2 group-hover:text-epl-gold transition-colors">Llega y Recibe</h3>
                            <p class="text-gray-400 text-sm leading-relaxed font-medium">Llega a tu club y recibe en cada fecha tu <strong>Pack de Bienvenida</strong>. Hidratación, regalos de auspiciadores y todo listo para entrar a la cancha.</p>
                        </div>
                    </div>

                    <!-- PASO 2 -->
                    <div class="timeline-step flex flex-row md:flex-col items-center md:text-center gap-6 md:gap-0 group">
                        <div class="w-24 h-24 shrink-0 rounded-full bg-epl-gold text-epl-blue flex items-center justify-center border-[6px] border-epl-blue relative shadow-[0_0_20px_rgba(201,167,98,0.3)]">
                            <span class="absolute -top-1 -right-1 bg-white text-epl-blue font-black w-7 h-7 rounded-full flex items-center justify-center text-sm shadow-md">2</span>
                            <svg xmlns="http://www.w3.org/2000/svg" width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M8.5 14.5A2.5 2.5 0 0 0 11 12c0-1.38-.5-2-1-3-1.072-2.143-.224-4.054 2-6 .5 2.5 2 4.9 4 6.5 2 1.6 3 3.5 3 5.5a7 7 0 1 1-14 0c0-1.153.433-2.294 1-3a2.5 2.5 0 0 0 2.5 2.5z"></path></svg>
                        </div>
                        <div class="md:mt-8">
                            <h3 class="text-2xl font-primary uppercase tracking-wide text-white mb-2 group-hover:text-epl-gold transition-colors">Juega a Muerte</h3>
                            <p class="text-gray-400 text-sm leading-relaxed font-medium">Entra al 20x10 y dalo todo contra parejas de tu mismo nivel. Además, tendrás acceso a nuestra plataforma exclusiva para seguir de cerca a tus rivales.</p>
                        </div>
                    </div>

                    <!-- PASO 3 -->
                    <div class="timeline-step flex flex-row md:flex-col items-center md:text-center gap-6 md:gap-0 group">
                        <div class="w-24 h-24 shrink-0 rounded-full bg-epl-gold text-epl-blue flex items-center justify-center border-[6px] border-epl-blue relative shadow-[0_0_20px_rgba(201,167,98,0.3)]">
                            <span class="absolute -top-1 -right-1 bg-white text-epl-blue font-black w-7 h-7 rounded-full flex items-center justify-center text-sm shadow-md">3</span>
                            <svg xmlns="http://www.w3.org/2000/svg" width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 11h1.22a2 2 0 0 1 1.94 1.51l1.68 6.4a2 2 0 0 1-1.94 2.51H17"></path><path d="M13 3h-2v4h2Z"></path><path d="M9 11H5.82a2 2 0 0 0-1.94 1.51l-1.68 6.4a2 2 0 0 0 1.94 2.51H9"></path><rect width="8" height="18" x="8" y="3" rx="2"></rect><line x1="8" x2="16" y1="7" y2="7"></line><line x1="8" x2="16" y1="11" y2="11"></line></svg>
                        </div>
                        <div class="md:mt-8">
                            <h3 class="text-2xl font-primary uppercase tracking-wide text-white mb-2 group-hover:text-epl-gold transition-colors">El Tercer Tiempo</h3>
                            <p class="text-gray-400 text-sm leading-relaxed font-medium">El partido no termina en el punto de oro. Te invitamos a quedarte, compartir con los demás jugadores y disfrutar de un grato ambiente de club.</p>
                        </div>
                    </div>

                </div>
            </div>

            <!-- CTA INSTAGRAM -->
            <div class="mt-10 md:mt-20 border-t border-white/10 pt-8 md:pt-16 flex flex-col items-center text-center">
                <h3 class="text-3xl md:text-4xl font-primary uppercase tracking-tight text-white mb-4">Descubre más en <span class="text-epl-gold">Instagram</span></h3>
                <p class="text-gray-400 font-medium mb-10 max-w-xl mx-auto">Para conocer a fondo cómo se vive cada una de nuestras fechas, ver galerías de fotos y contenido exclusivo de la liga, únete a nuestra comunidad online.</p>
                
                <a href="https://www.instagram.com/epleaguecl/" target="_blank" class="flex items-center gap-3 bg-white text-epl-blue px-10 py-5 rounded-xl font-black text-[13px] uppercase tracking-[0.15em] hover:bg-epl-gold hover:text-white transition-all shadow-lg hover:-translate-y-1">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><rect width="20" height="20" x="2" y="2" rx="5" ry="5"/><path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z"/><line x1="17.5" x2="17.51" y1="6.5" y2="6.5"/></svg>
                    @EPLEAGUECL
                </a>
            </div>

        </div>
    </section>

    <!-- ==========================================
         6. CTA FINAL
         ========================================== -->
    <section class="py-10 md:py-24 bg-white px-4 md:px-6 text-center">
        <div class="max-w-4xl mx-auto bg-gray-50 rounded-[32px] md:rounded-[40px] p-6 md:p-16 border border-gray-100 shadow-[0_10px_40px_rgba(0,0,0,0.03)] relative overflow-hidden">
            <!-- Circulo decorativo -->
            <div class="absolute -top-20 -right-20 w-64 h-64 bg-epl-gold/10 rounded-full blur-3xl pointer-events-none"></div>

            <h2 class="text-4xl md:text-5xl text-epl-blue font-primary uppercase tracking-tight mb-6 relative z-10">¿Listo para entrar a <span class="text-epl-gold">la Cancha</span>?</h2>
            
            <p class="text-gray-500 font-medium mb-10 max-w-2xl mx-auto relative z-10 leading-relaxed">
                Crea tu ficha de jugador en menos de 2 minutos. Desbloquea el acceso a inscripciones prioritarias, lleva tus estadísticas al detalle, <strong class="text-epl-blue">recibe invitaciones a eventos exclusivos</strong> y vive la experiencia completa de la comunidad Elite.
            </p>
            
            <?php if (!epl_jugador_actual()): ?>
              <a href="registro.php" class="inline-flex items-center justify-center gap-3 bg-epl-blue text-white px-10 py-5 rounded-xl font-black uppercase text-[14px] tracking-widest hover:bg-epl-gold transition-all shadow-xl hover:-translate-y-1 relative z-10">
                  CREAR MI CUENTA GRATIS
                  <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"></path><path d="m12 5 7 7-7 7"></path></svg>
              </a>
            <?php else: ?>
              <a href="dashboard.php" class="inline-flex items-center justify-center gap-3 bg-epl-blue text-white px-10 py-5 rounded-xl font-black uppercase text-[14px] tracking-widest hover:bg-epl-gold transition-all shadow-xl hover:-translate-y-1 relative z-10">
                  Ir al Dashboard →
              </a>
            <?php endif; ?>
        </div>
    </section>

</div>

<?php require_once 'includes/footer.php'; ?>
