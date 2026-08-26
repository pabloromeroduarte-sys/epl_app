<?php
$page_title       = null; // usamos el default optimizado SEO del header
$active_nav       = 'inicio';
$page_css         = 'home';
$meta_description = 'Elite Padel League: ligas de pádel de 10 fechas con ranking por liga, ranking individual de 365 días y torneos americanos en Santiago.';
$og_image         = null; // usa el default del header
$og_type          = 'website';

require_once 'includes/functions.php';

$temporada = (int)date('Y');
?>
<?php require_once 'includes/header.php'; ?>

<div class="epl-body-wrapper antialiased text-gray-700">

    <!-- ==========================================
         1. HERO
         ========================================== -->
    <header class="home-hero">
        <div class="home-hero__glow" aria-hidden="true"></div>
        <div class="home-hero__court" aria-hidden="true"></div>
        <div class="home-hero__season" aria-hidden="true">26</div>

        <div class="home-hero__inner">
            <div class="home-hero__copy">
                <div class="home-live-pill">
                    <span class="home-live-pill__dot"></span>
                    Ligas EPL · Temporada <?= $temporada ?>
                </div>

                <p class="home-hero__eyebrow">Ligas de 10 fechas. Americanos para seguir compitiendo.</p>
                <h1 class="home-hero__title">
                    Diez fechas.<br>
                    <span>Una gran liga.</span>
                </h1>

                <p class="home-hero__lead">
                    Compite con tu pareja en una temporada ordenada, con fixture y tabla de posiciones.
                    <strong>Sigue el ranking de tu liga y construye también tu ranking individual EPL durante 365 días.</strong>
                </p>

                <div class="home-hero__actions">
                    <?php if (!epl_jugador_actual()): ?>
                    <a href="<?= epl_url('torneos.php') ?>" class="home-btn home-btn--primary">
                        Ver ligas y americanos
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
                    </a>
                    <?php else: ?>
                    <a href="<?= epl_url('dashboard.php') ?>" class="home-btn home-btn--primary">
                        Ir a mi panel
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
                    </a>
                    <?php endif; ?>
                    <a href="#formato-epl" class="home-btn home-btn--ghost">Conocer el formato</a>
                </div>

                <div class="home-hero__trust" aria-label="Beneficios de Elite Padel League">
                    <span><i>✓</i> Ranking por liga</span>
                    <span><i>✓</i> Ranking individual activo</span>
                    <span><i>✓</i> Americanos</span>
                </div>
            </div>

            <aside class="home-match-card" aria-label="Formato de las ligas Elite Padel League">
                <div class="home-match-card__topline">
                    <span>Formato principal</span>
                    <span class="home-match-card__status"><i></i> <?= $temporada ?></span>
                </div>

                <div class="home-match-card__league">
                    <span>Liga EPL</span>
                    <strong>Una temporada clara de principio a fin</strong>
                </div>

                <div class="home-points-grid">
                    <div class="home-points-grid__item home-points-grid__item--gold">
                        <span>01</span>
                        <strong>10</strong>
                        <small>Fechas</small>
                    </div>
                    <div class="home-points-grid__item">
                        <span>02</span>
                        <strong>1</strong>
                        <small>Fixture</small>
                    </div>
                    <div class="home-points-grid__item">
                        <span>03</span>
                        <strong>RL</strong>
                        <small>Ranking liga</small>
                    </div>
                    <div class="home-points-grid__item">
                        <span>+</span>
                        <strong>RI</strong>
                        <small>Individual</small>
                    </div>
                </div>

                <div class="home-ranking-note">
                    <span>Dos rankings complementarios</span>
                    <p>Tu pareja compite por la liga y cada jugador construye su propio recorrido dentro de EPL.</p>
                </div>

                <a href="#rankings-epl" class="home-match-card__link">Conocer los rankings EPL <span>↘</span></a>
            </aside>
        </div>
    </header>

    <!-- ==========================================
         2. STATS BAR (Social proof)
         ========================================== -->
    <section class="stats-bar" aria-label="Formato de las ligas EPL">
        <div class="stats-grid">
            <div>
                <div class="stat-num">10 fechas</div>
                <div class="stat-lbl">Por temporada de liga</div>
            </div>
            <div>
                <div class="stat-num">Doble ranking</div>
                <div class="stat-lbl">Por liga + individual</div>
            </div>
            <div>
                <div class="stat-num">+ Americanos</div>
                <div class="stat-lbl">Durante la temporada</div>
            </div>
        </div>
    </section>

    <!-- ==========================================
         3. PILARES DE LA EXPERIENCIA EPL
         ========================================== -->
    <section class="py-12 md:py-24 bg-white px-4 md:px-6 relative z-20" id="pilares">
        <div class="max-w-6xl mx-auto">
            <div class="text-center mb-8 md:mb-16">
                <span class="text-epl-gold font-black text-[10px] uppercase tracking-[0.2em] mb-2 block">La experiencia EPL</span>
                <h2 class="text-4xl md:text-5xl text-epl-blue font-primary uppercase tracking-tight">Liga competitiva. <span class="text-epl-gold">Calendario claro.</span></h2>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <article class="feature-card-tw">
                    <div class="feature-icon-wrapper-tw">
                        <svg xmlns="http://www.w3.org/2000/svg" width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 9H4.5a2.5 2.5 0 0 1 0-5H6"/><path d="M18 9h1.5a2.5 2.5 0 0 0 0-5H18"/><path d="M4 22h16"/><path d="M10 14.66V17c0 .55-.47.98-.97 1.21C7.85 18.75 7 20.24 7 22"/><path d="M14 14.66V17c0 .55.47.98.97 1.21C16.15 18.75 17 20.24 17 22"/><path d="M18 2H6v7a6 6 0 0 0 12 0V2Z"/></svg>
                    </div>
                    <h3 class="font-primary text-2xl text-epl-blue uppercase mb-3">Liga de 10 fechas</h3>
                    <p class="text-sm text-gray-500 leading-relaxed font-medium">Juega una temporada completa contra parejas de tu categoría, con una jornada programada para cada fecha.</p>
                </article>

                <article class="feature-card-tw">
                    <div class="feature-icon-wrapper-tw">
                        <svg xmlns="http://www.w3.org/2000/svg" width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M17 21v-2a4 4 0 0 0-4-4H5c-2.2 0-4 1.8-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                    </div>
                    <h3 class="font-primary text-2xl text-epl-blue uppercase mb-3">Ranking por liga</h3>
                    <p class="text-sm text-gray-500 leading-relaxed font-medium">Cada liga tiene su propia clasificación. La pareja suma con sus resultados durante las 10 fechas y compite por el primer lugar.</p>
                </article>

                <article class="feature-card-tw">
                    <div class="feature-icon-wrapper-tw">
                        <svg xmlns="http://www.w3.org/2000/svg" width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                    </div>
                    <h3 class="font-primary text-2xl text-epl-blue uppercase mb-3">Ranking individual EPL</h3>
                    <p class="text-sm text-gray-500 leading-relaxed font-medium">La siguiente evolución reunirá el rendimiento personal de cada jugador en ligas y americanos, aunque cambie de pareja.</p>
                </article>
            </div>
        </div>
    </section>

    <!-- ==========================================
         4. FORMATO DE TEMPORADA + AMERICANOS
         ========================================== -->
    <section id="formato-epl" class="home-circuit-section">
        <div class="home-circuit-section__inner">
            <article class="home-format-card">
                <div class="home-format-card__eyebrow">Temporada de liga <?= $temporada ?></div>
                <h2>10 fechas.<br><span>Una tabla.</span><br>Todo por jugar.</h2>
                <p>Tu pareja compite durante diez jornadas, enfrenta rivales de su categoría y construye su posición partido a partido.</p>

                <div class="home-format-flow">
                    <div><b>01</b><span><strong>Inscribe tu pareja</strong><small>Elige la liga correspondiente a tu categoría.</small></span></div>
                    <div><b>02</b><span><strong>Juega 10 fechas</strong><small>Un partido por jornada, con calendario y reprogramaciones.</small></span></div>
                    <div><b>03</b><span><strong>Sube en tu liga</strong><small>Cada resultado actualiza el ranking de la pareja.</small></span></div>
                </div>

                <a href="<?= epl_url('torneos.php') ?>" class="home-btn home-btn--primary">Explorar ligas →</a>
            </article>

            <article class="home-leaderboard-card" id="rankings-epl">
                <div class="home-leaderboard-card__head">
                    <div>
                        <span>Sistema EPL · Temporada <?= $temporada ?></span>
                        <h2>Dos rankings</h2>
                    </div>
                    <a href="<?= epl_url('ranking.php') ?>">Ver ranking individual ↗</a>
                </div>

                <div class="home-ranking-types">
                  <section class="home-ranking-type">
                    <b>01</b>
                    <div>
                        <span>Disponible en cada competencia</span>
                        <strong>Ranking por liga</strong>
                        <p>Ordena a las parejas según sus resultados durante las 10 fechas. Cada liga y categoría tiene su propia clasificación.</p>
                    </div>
                  </section>
                  <section class="home-ranking-type home-ranking-type--next">
                    <b>02</b>
                    <div>
                        <span>Disponible y actualizado</span>
                        <strong>Ranking individual</strong>
                        <p>Sigue el rendimiento de cada jugador durante los últimos 365 días, independiente de la pareja con la que compita.</p>
                    </div>
                  </section>
                </div>

                <div class="home-leaderboard-card__footer">
                    <span>Pareja en la liga. Jugador en EPL.</span>
                    <strong>Dos formas de medir tu temporada.</strong>
                </div>
            </article>
        </div>
    </section>

    <!-- ==========================================
         5. EL TERCER TIEMPO (Galería visual)
         ========================================== -->
    <section class="py-12 md:py-24 bg-white px-4 md:px-6" id="tercer-tiempo">
        <div class="max-w-7xl mx-auto">
            <div class="text-center mb-8 md:mb-14">
                <span class="text-epl-gold font-black text-[10px] uppercase tracking-[0.2em] mb-2 block">El ritual</span>
                <h2 class="text-4xl md:text-5xl text-epl-blue font-primary uppercase tracking-tight">El Tercer <span class="text-epl-gold">Tiempo</span></h2>
                <p class="mt-4 text-gray-500 font-medium max-w-2xl mx-auto leading-relaxed">
                    Cuando se termina el partido, recién empieza la mejor parte. La comunidad EPL se queda a compartir, conversar y celebrar. Esto es lo que nos hace diferentes.
                </p>
            </div>

            <div class="tt-grid mb-8">
                <div><img src="<?= epl_url('assets/img/landing/tercer-tiempo-1.jpg?v=2') ?>" alt="Comunidad Elite Padel League compartiendo después del partido" loading="lazy"></div>
                <div><img src="<?= epl_url('assets/img/landing/accion-padel.png?v=2') ?>" alt="Acción en cancha durante torneo EPL" loading="lazy"></div>
                <div><img src="<?= epl_url('assets/img/landing/tercer-tiempo-2.png?v=2') ?>" alt="Tercer Tiempo Elite Padel League — networking en sede" loading="lazy"></div>
                <div><img src="<?= epl_url('assets/img/landing/tercer-tiempo-3.png?v=2') ?>" alt="Brindis y celebración fin de fecha EPL" loading="lazy"></div>
            </div>

            <div class="text-center">
                <a href="https://www.instagram.com/epleaguecl/" target="_blank" rel="noopener" class="inline-flex items-center gap-3 text-epl-blue font-black text-[11px] uppercase tracking-[0.2em] hover:text-epl-gold transition-colors no-underline">
                    Mira la última fecha en Instagram
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
                </a>
            </div>
        </div>
    </section>

    <!-- ==========================================
         6. FILOSOFÍA / HISTORIA
         ========================================== -->
    <section id="nosotros" class="py-12 md:py-24 bg-[#F5F7F8] px-4 md:px-6 scroll-mt-20">
        <div class="max-w-6xl mx-auto flex flex-col lg:flex-row items-center gap-8 lg:gap-16">

            <div class="lg:w-1/2 relative pb-12 lg:pb-0">
                <div class="absolute -top-6 -left-6 w-32 h-32 bg-epl-gold/20 rounded-full blur-2xl"></div>
                <div class="absolute -bottom-6 -right-6 w-32 h-32 bg-epl-blue/10 rounded-full blur-2xl"></div>

                <img src="<?= epl_url('assets/img/landing/filosofia.jpg') ?>" alt="Comunidad Elite Padel League — más que una liga, una familia" class="rounded-3xl shadow-2xl relative z-10 w-full object-cover aspect-[4/3]" loading="lazy">

                <div class="absolute -bottom-8 right-8 bg-white p-5 rounded-2xl shadow-xl z-20 flex items-center gap-4">
                    <div class="w-12 h-12 bg-epl-blue rounded-full flex items-center justify-center text-white font-['Anton'] text-xl">1°</div>
                    <div>
                        <p class="font-black text-epl-blue text-sm uppercase leading-tight">Liga amateur</p>
                        <p class="text-xs text-gray-500 font-medium">De alto rendimiento</p>
                    </div>
                </div>
            </div>

            <div class="lg:w-1/2 mt-10 lg:mt-0">
                <span class="text-epl-gold font-black text-[10px] uppercase tracking-[0.2em] mb-4 block">Nuestra Filosofía</span>
                <h2 class="text-4xl md:text-5xl text-epl-blue font-primary uppercase tracking-tight mb-6 leading-none">Más que una liga,<br> <span class="text-epl-gold">una familia</span></h2>

                <div class="space-y-5 text-gray-600 font-medium leading-relaxed mb-8">
                    <p>Elite Padel League nació de la pasión de un grupo de jugadores que buscaban algo más que arrendar una cancha los fines de semana. Buscábamos la adrenalina de una competencia estructurada, sin perder el espíritu amateur ni la cercanía con la comunidad.</p>
                    <p>Creemos que el pádel es el deporte social por excelencia de esta década. Por eso en cada fecha nos preocupamos de que la organización sea impecable, los rivales sean de tu nivel, y que <strong>siempre haya una bebida fría esperándote al salir del 20×10</strong>.</p>
                </div>

                <a href="<?= epl_url('reglamento.php') ?>" class="inline-flex items-center gap-3 bg-gray-100 text-epl-blue px-8 py-4 rounded-xl font-black text-[11px] uppercase tracking-widest hover:bg-gray-200 transition-colors no-underline">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/></svg>
                    Conoce el reglamento
                </a>
            </div>

        </div>
    </section>

    <!-- ==========================================
         7. CÓMO FUNCIONA (Timeline)
         ========================================== -->
    <section class="py-12 md:py-24 bg-epl-blue text-white px-4 md:px-6 border-t-[6px] border-epl-gold relative overflow-hidden">
        <div class="absolute top-0 left-1/2 -translate-x-1/2 w-full h-full max-w-4xl bg-epl-gold/5 blur-[100px] pointer-events-none"></div>

        <div class="max-w-6xl mx-auto relative z-10">
            <div class="text-center mb-8 md:mb-16">
                <span class="text-epl-gold font-black text-[10px] uppercase tracking-[0.2em] mb-2 block">¿Cómo funciona?</span>
                <h2 class="text-4xl md:text-5xl font-primary uppercase tracking-tight">La Experiencia <span class="text-epl-gold">EPL</span></h2>
            </div>

            <div class="relative mt-12 mb-12 max-w-5xl mx-auto">
                <div class="hidden md:block absolute top-[48px] left-[10%] right-[10%] h-1 bg-epl-gold/20 rounded-full z-0"></div>
                <div class="md:hidden absolute top-[48px] bottom-0 left-[48px] w-1 bg-epl-gold/20 rounded-full z-0"></div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-12 md:gap-8 relative z-10">
                    <div class="timeline-step flex flex-row md:flex-col items-center md:text-center gap-6 md:gap-0 group">
                        <div class="w-24 h-24 shrink-0 rounded-full bg-epl-gold text-epl-blue flex items-center justify-center border-[6px] border-epl-blue relative shadow-[0_0_20px_rgba(201,167,98,0.3)]">
                            <span class="absolute -top-1 -right-1 bg-white text-epl-blue font-black w-7 h-7 rounded-full flex items-center justify-center text-sm shadow-md">1</span>
                            <svg xmlns="http://www.w3.org/2000/svg" width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="20 12 20 22 4 22 4 12"/><rect x="2" y="7" width="20" height="5"/><line x1="12" y1="22" x2="12" y2="7"/></svg>
                        </div>
                        <div class="md:mt-8">
                            <h3 class="text-2xl font-primary uppercase tracking-wide text-white mb-2 group-hover:text-epl-gold transition-colors">Inscríbete</h3>
                            <p class="text-gray-400 text-sm leading-relaxed font-medium">Crea tu ficha, forma tu pareja y asegura un lugar en la liga de tu categoría.</p>
                        </div>
                    </div>

                    <div class="timeline-step flex flex-row md:flex-col items-center md:text-center gap-6 md:gap-0 group">
                        <div class="w-24 h-24 shrink-0 rounded-full bg-epl-gold text-epl-blue flex items-center justify-center border-[6px] border-epl-blue relative shadow-[0_0_20px_rgba(201,167,98,0.3)]">
                            <span class="absolute -top-1 -right-1 bg-white text-epl-blue font-black w-7 h-7 rounded-full flex items-center justify-center text-sm shadow-md">2</span>
                            <svg xmlns="http://www.w3.org/2000/svg" width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M8.5 14.5A2.5 2.5 0 0 0 11 12c0-1.38-.5-2-1-3-1.072-2.143-.224-4.054 2-6 .5 2.5 2 4.9 4 6.5 2 1.6 3 3.5 3 5.5a7 7 0 1 1-14 0c0-1.153.433-2.294 1-3a2.5 2.5 0 0 0 2.5 2.5z"/></svg>
                        </div>
                        <div class="md:mt-8">
                            <h3 class="text-2xl font-primary uppercase tracking-wide text-white mb-2 group-hover:text-epl-gold transition-colors">Juega 10 fechas</h3>
                            <p class="text-gray-400 text-sm leading-relaxed font-medium">Enfrenta a parejas de tu nivel durante una temporada con fixture, tabla y resultados actualizados.</p>
                        </div>
                    </div>

                    <div class="timeline-step flex flex-row md:flex-col items-center md:text-center gap-6 md:gap-0 group">
                        <div class="w-24 h-24 shrink-0 rounded-full bg-epl-gold text-epl-blue flex items-center justify-center border-[6px] border-epl-blue relative shadow-[0_0_20px_rgba(201,167,98,0.3)]">
                            <span class="absolute -top-1 -right-1 bg-white text-epl-blue font-black w-7 h-7 rounded-full flex items-center justify-center text-sm shadow-md">3</span>
                            <svg xmlns="http://www.w3.org/2000/svg" width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M17 11h1.22a2 2 0 0 1 1.94 1.51l1.68 6.4a2 2 0 0 1-1.94 2.51H17"/><path d="M13 3h-2v4h2Z"/><path d="M9 11H5.82a2 2 0 0 0-1.94 1.51l-1.68 6.4a2 2 0 0 0 1.94 2.51H9"/><rect width="8" height="18" x="8" y="3" rx="2"/></svg>
                        </div>
                        <div class="md:mt-8">
                            <h3 class="text-2xl font-primary uppercase tracking-wide text-white mb-2 group-hover:text-epl-gold transition-colors">Sigue la competencia</h3>
                            <p class="text-gray-400 text-sm leading-relaxed font-medium">Registra resultados, revisa tu posición y participa en los americanos que complementan la temporada.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ==========================================
         8. TESTIMONIOS
         ========================================== -->
    <section class="py-12 md:py-24 bg-white px-4 md:px-6">
        <div class="max-w-6xl mx-auto">
            <div class="text-center mb-8 md:mb-14">
                <span class="text-epl-gold font-black text-[10px] uppercase tracking-[0.2em] mb-2 block">La voz de la cancha</span>
                <h2 class="text-4xl md:text-5xl text-epl-blue font-primary uppercase tracking-tight">Lo que dicen <span class="text-epl-gold">los jugadores</span></h2>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <article class="testimonio-card">
                    <div class="testimonio-stars mb-3">★★★★★</div>
                    <p class="text-gray-600 text-sm leading-relaxed mb-5 font-medium">"Ya jugué varias fechas en EPL. La organización es de otro nivel y al final del día todos terminamos siendo amigos. El Tercer Tiempo es la mejor parte."</p>
                    <div class="flex items-center gap-3 mt-auto pt-3 border-t border-gray-100">
                        <div class="w-11 h-11 rounded-full bg-epl-blue text-epl-gold flex items-center justify-center font-['Anton'] text-lg">MC</div>
                        <div>
                            <p class="font-bold text-epl-blue text-sm">Matías Castro</p>
                            <p class="text-xs text-gray-500 font-medium">Liga EPL · 4ta categoría</p>
                        </div>
                    </div>
                </article>

                <article class="testimonio-card">
                    <div class="testimonio-stars mb-3">★★★★★</div>
                    <p class="text-gray-600 text-sm leading-relaxed mb-5 font-medium">"Lo que más valoro es que las categorías están bien definidas. No te enfrentas a rivales muy por encima ni muy por debajo. Cada partido es disputado."</p>
                    <div class="flex items-center gap-3 mt-auto pt-3 border-t border-gray-100">
                        <div class="w-11 h-11 rounded-full bg-epl-blue text-epl-gold flex items-center justify-center font-['Anton'] text-lg">JR</div>
                        <div>
                            <p class="font-bold text-epl-blue text-sm">Javiera Riquelme</p>
                            <p class="text-xs text-gray-500 font-medium">Liga EPL · 5ta femenino</p>
                        </div>
                    </div>
                </article>

                <article class="testimonio-card">
                    <div class="testimonio-stars mb-3">★★★★★</div>
                    <p class="text-gray-600 text-sm leading-relaxed mb-5 font-medium">"La plataforma es lejos lo mejor: fixture, tabla, historial y resultados, todo desde el celular. Se nota que es una liga bien organizada."</p>
                    <div class="flex items-center gap-3 mt-auto pt-3 border-t border-gray-100">
                        <div class="w-11 h-11 rounded-full bg-epl-blue text-epl-gold flex items-center justify-center font-['Anton'] text-lg">DA</div>
                        <div>
                            <p class="font-bold text-epl-blue text-sm">Diego Aravena</p>
                            <p class="text-xs text-gray-500 font-medium">Liga EPL · 3ra categoría</p>
                        </div>
                    </div>
                </article>
            </div>
        </div>
    </section>

    <!-- ==========================================
         9. SPONSORS / PARTNERS
         ========================================== -->
    <section class="py-12 md:py-20 bg-[#F5F7F8] px-4 md:px-6 border-y border-gray-200">
        <div class="max-w-6xl mx-auto text-center">
            <span class="text-epl-gold font-black text-[10px] uppercase tracking-[0.2em] mb-2 block">Construido junto a</span>
                <h2 class="text-3xl md:text-4xl text-epl-blue font-primary uppercase tracking-tight mb-10">Nuestros <span class="text-epl-gold">Aliados</span></h2>

            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <div class="sponsor-card">
                    <div class="text-center">
                        <p class="font-['Anton'] text-2xl text-epl-blue">CONECTA</p>
                        <p class="text-[10px] font-bold tracking-widest text-gray-400 uppercase mt-1">Santa Blanca · Sede oficial</p>
                    </div>
                </div>
                <div class="sponsor-card">
                    <div class="text-center text-gray-400">
                        <p class="font-['Anton'] text-sm uppercase tracking-widest">Tu marca aquí</p>
                        <p class="text-[10px] mt-2">Socio principal</p>
                    </div>
                </div>
                <div class="sponsor-card">
                    <div class="text-center text-gray-400">
                        <p class="font-['Anton'] text-sm uppercase tracking-widest">Tu marca aquí</p>
                        <p class="text-[10px] mt-2">Socio oficial</p>
                    </div>
                </div>
                <div class="sponsor-card">
                    <div class="text-center text-gray-400">
                        <p class="font-['Anton'] text-sm uppercase tracking-widest">Tu marca aquí</p>
                        <p class="text-[10px] mt-2">Socio de experiencia</p>
                    </div>
                </div>
            </div>

            <p class="text-sm text-gray-500 font-medium mt-8 max-w-2xl mx-auto leading-relaxed">
                EPL es una plataforma para marcas que quieran conectar con una comunidad deportiva activa y comprometida.
                <a href="mailto:partners@epleague.cl" class="text-epl-blue font-bold hover:text-epl-gold transition-colors no-underline">Conversemos →</a>
            </p>
        </div>
    </section>

    <!-- ==========================================
         10. FAQ (SEO long-tail)
         ========================================== -->
    <section class="py-12 md:py-24 bg-white px-4 md:px-6" id="faq">
        <div class="max-w-3xl mx-auto">
            <div class="text-center mb-8 md:mb-12">
                <span class="text-epl-gold font-black text-[10px] uppercase tracking-[0.2em] mb-2 block">Preguntas frecuentes</span>
                <h2 class="text-4xl md:text-5xl text-epl-blue font-primary uppercase tracking-tight">¿Tienes <span class="text-epl-gold">dudas?</span></h2>
            </div>

            <details class="faq-item">
                <summary class="faq-q">¿Cómo me inscribo a una liga de Elite Padel League?</summary>
                <div class="faq-a">Creas tu ficha de jugador, eliges tu categoría y te inscribes con tu pareja desde la sección Ligas y torneos. Recibes la confirmación por correo electrónico y notificación.</div>
            </details>
            <details class="faq-item">
                <summary class="faq-q">¿Cuánto cuesta participar?</summary>
                <div class="faq-a">El valor depende de la liga, el americano y la categoría. Antes de inscribirte verás el precio y las condiciones de cada competencia.</div>
            </details>
            <details class="faq-item">
                <summary class="faq-q">¿Qué nivel necesito para jugar?</summary>
                <div class="faq-a">Hay categorías desde principiante hasta jugador avanzado (de 5ta a 1ra). Cuando creas tu ficha, indicas tu nivel y nosotros nos preocupamos de que enfrentes parejas equivalentes.</div>
            </details>
            <details class="faq-item">
                <summary class="faq-q">¿Dónde se juegan los partidos?</summary>
                <div class="faq-a">Nuestra sede principal es <strong>Conecta Santa Blanca</strong>, en Santiago. Es nuestro club oficial y donde se vive cada fecha y cada Tercer Tiempo.</div>
            </details>
            <details class="faq-item">
                <summary class="faq-q">¿Cuánto dura una liga EPL?</summary>
                <div class="faq-a">La modalidad principal contempla 10 fechas. Cada jornada tiene un partido programado y el fixture completo se puede revisar desde la plataforma.</div>
            </details>
            <details class="faq-item">
                <summary class="faq-q">¿Cuál es la diferencia entre el ranking por liga y el ranking individual?</summary>
                <div class="faq-a">El ranking por liga ordena a las parejas dentro de cada competencia. El ranking individual reúne los puntos vigentes de cada jugador en ligas y americanos durante los últimos 365 días, aunque cambie de pareja.</div>
            </details>
            <details class="faq-item">
                <summary class="faq-q">¿Qué pasa si no puedo jugar en la fecha programada?</summary>
                <div class="faq-a">Puedes solicitar una reprogramación desde tu panel. El cambio queda registrado, el rival y la organización pueden hacer seguimiento, y el club confirma la cancha cuando corresponde.</div>
            </details>
            <details class="faq-item">
                <summary class="faq-q">¿Qué es un americano EPL?</summary>
                <div class="faq-a">Es una competencia complementaria que se resuelve en una jornada. Permite jugar varios partidos, conocer rivales y seguir compitiendo fuera del calendario regular de liga.</div>
            </details>
            <details class="faq-item">
                <summary class="faq-q">¿Puedo jugar si no tengo pareja?</summary>
                <div class="faq-a">Para las ligas necesitas inscribirte con pareja. En algunos americanos podrás participar individualmente o solicitar apoyo para encontrar jugadores de nivel equivalente, según las condiciones publicadas.</div>
            </details>

            <!-- JSON-LD FAQ Schema -->
            <script type="application/ld+json">
            {
              "@context":"https://schema.org",
              "@type":"FAQPage",
              "mainEntity":[
                {"@type":"Question","name":"¿Cómo me inscribo a una liga de Elite Padel League?","acceptedAnswer":{"@type":"Answer","text":"Creas tu ficha, eliges categoría y te inscribes con tu pareja desde la sección Ligas y torneos."}},
                {"@type":"Question","name":"¿Cuánto cuesta participar?","acceptedAnswer":{"@type":"Answer","text":"Depende de la liga, el americano y la categoría. El precio y las condiciones aparecen antes de la inscripción."}},
                {"@type":"Question","name":"¿Qué nivel necesito para jugar?","acceptedAnswer":{"@type":"Answer","text":"Categorías desde principiante (5ta) hasta avanzado (1ra). Se asignan rivales del mismo nivel."}},
                {"@type":"Question","name":"¿Dónde se juegan los partidos?","acceptedAnswer":{"@type":"Answer","text":"Sede oficial: Conecta Santa Blanca, Santiago de Chile."}},
                {"@type":"Question","name":"¿Cuánto dura una liga EPL?","acceptedAnswer":{"@type":"Answer","text":"La modalidad principal contempla 10 fechas, con un partido por jornada y fixture disponible en la plataforma."}},
                {"@type":"Question","name":"¿Cuál es la diferencia entre el ranking por liga y el ranking individual?","acceptedAnswer":{"@type":"Answer","text":"El ranking por liga ordena a las parejas dentro de cada competencia. El ranking individual EPL reúne los puntos vigentes de cada jugador en ligas y americanos durante los últimos 365 días."}},
                {"@type":"Question","name":"¿Qué pasa si no puedo jugar en la fecha programada?","acceptedAnswer":{"@type":"Answer","text":"Puedes solicitar una reprogramación desde tu panel y seguir el cambio hasta la confirmación de la nueva cancha."}},
                {"@type":"Question","name":"¿Qué es un americano EPL?","acceptedAnswer":{"@type":"Answer","text":"Es una competencia complementaria de una jornada que permite jugar varios partidos y conocer nuevos rivales."}},
                {"@type":"Question","name":"¿Puedo jugar si no tengo pareja?","acceptedAnswer":{"@type":"Answer","text":"Las ligas requieren pareja. Algunos americanos pueden admitir inscripción individual según sus condiciones."}}
              ]
            }
            </script>
        </div>
    </section>

    <!-- ==========================================
         11. MAPA SEDE
         ========================================== -->
    <section class="py-12 md:py-20 bg-[#F5F7F8] px-4 md:px-6" id="sede">
        <div class="max-w-6xl mx-auto grid grid-cols-1 lg:grid-cols-2 gap-8 lg:gap-12 items-center">
            <div>
                <span class="text-epl-gold font-black text-[10px] uppercase tracking-[0.2em] mb-2 block">Sede oficial</span>
                <h2 class="text-4xl md:text-5xl text-epl-blue font-primary uppercase tracking-tight mb-5">Conecta <span class="text-epl-gold">Santa Blanca</span></h2>
                <p class="text-gray-600 font-medium leading-relaxed mb-5">
                    Nuestro club oficial en Santiago. Canchas y ambiente de primer nivel, con todo lo que necesitas para vivir la experiencia EPL desde el primer partido hasta el último brindis.
                </p>
                <ul class="space-y-3 text-sm text-gray-700 mb-7">
                    <li class="flex gap-3 items-start">
                        <svg width="20" height="20" fill="none" stroke="#C9A762" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                        <span><strong>Conecta Santa Blanca</strong> — Santiago, Región Metropolitana</span>
                    </li>
                    <li class="flex gap-3 items-start">
                        <svg width="20" height="20" fill="none" stroke="#C9A762" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        <span>Fechas de liga y americanos según el calendario de cada categoría</span>
                    </li>
                    <li class="flex gap-3 items-start">
                        <svg width="20" height="20" fill="none" stroke="#C9A762" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                        <span>Estacionamiento, camarines y cafetería</span>
                    </li>
                </ul>
                <a href="https://www.google.com/maps/search/?api=1&query=Conecta+Santa+Blanca+Santiago" target="_blank" rel="noopener" class="inline-flex items-center gap-3 bg-epl-blue text-white px-7 py-4 rounded-xl font-black text-[11px] uppercase tracking-widest hover:bg-epl-gold hover:text-epl-blue transition-all no-underline">
                    Cómo llegar
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M14 5l7 7-7 7M5 12h16"/></svg>
                </a>
            </div>
            <div class="rounded-2xl overflow-hidden shadow-2xl border-4 border-white">
                <iframe
                    src="https://www.google.com/maps?q=Conecta+Santa+Blanca+Santiago&output=embed"
                    width="100%" height="380"
                    style="border:0;display:block"
                    allowfullscreen=""
                    loading="lazy"
                    referrerpolicy="no-referrer-when-downgrade"
                    title="Mapa Conecta Santa Blanca — Sede oficial Elite Padel League"></iframe>
            </div>
        </div>
    </section>

    <!-- ==========================================
         12. CTA FINAL
         ========================================== -->
    <section class="py-10 md:py-24 bg-white px-4 md:px-6 text-center">
        <div class="max-w-4xl mx-auto bg-gray-50 rounded-[32px] md:rounded-[40px] p-6 md:p-16 border border-gray-100 shadow-[0_10px_40px_rgba(0,0,0,0.03)] relative overflow-hidden">
            <div class="absolute -top-20 -right-20 w-64 h-64 bg-epl-gold/10 rounded-full blur-3xl pointer-events-none"></div>

            <h2 class="text-4xl md:text-5xl text-epl-blue font-primary uppercase tracking-tight mb-6 relative z-10">¿Listo para entrar a <span class="text-epl-gold">la cancha?</span></h2>

            <p class="text-gray-500 font-medium mb-10 max-w-2xl mx-auto relative z-10 leading-relaxed">
                Crea tu ficha, arma tu pareja y elige la liga correspondiente a tu categoría.
                <strong class="text-epl-blue">Tendrás 10 fechas para competir, un ranking por liga y una plataforma para gestionar toda la temporada.</strong>
            </p>

            <?php if (!epl_jugador_actual()): ?>
              <a href="<?= epl_url('registro.php') ?>" class="inline-flex items-center justify-center gap-3 bg-epl-blue text-white px-10 py-5 rounded-xl font-black uppercase text-[14px] tracking-widest hover:bg-epl-gold transition-all shadow-xl hover:-translate-y-1 relative z-10 no-underline">
                  Buscar una liga
                  <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
              </a>
            <?php else: ?>
              <a href="<?= epl_url('dashboard.php') ?>" class="inline-flex items-center justify-center gap-3 bg-epl-blue text-white px-10 py-5 rounded-xl font-black uppercase text-[14px] tracking-widest hover:bg-epl-gold transition-all shadow-xl hover:-translate-y-1 relative z-10 no-underline">
                  Ir a mi panel →
              </a>
            <?php endif; ?>
        </div>
    </section>

</div>

<?php require_once 'includes/footer.php'; ?>
