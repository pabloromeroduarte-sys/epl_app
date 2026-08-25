<?php
$page_title       = null; // usamos el default optimizado SEO del header
$active_nav       = 'inicio';
$page_css         = 'home';
$meta_description = 'Elite Padel League: circuito anual de torneos de pádel en Santiago. Compite en pareja, acumula puntos y clasifica al Máster Final EPL.';
$og_image         = null; // usa el default del header
$og_type          = 'website';

require_once 'includes/functions.php';

$db = epl_db();
$temporada = (int)date('Y');
$modelo_puntos = $db->query("SELECT puntos_1,puntos_2,puntos_3,puntos_4,puntos_grupos FROM ligas ORDER BY (tipo='torneo') DESC,id DESC LIMIT 1")->fetch() ?: [];
$puntos = [
    1 => (int)($modelo_puntos['puntos_1'] ?? 100),
    2 => (int)($modelo_puntos['puntos_2'] ?? 70),
    3 => (int)($modelo_puntos['puntos_3'] ?? 50),
    4 => (int)($modelo_puntos['puntos_4'] ?? 30),
    5 => (int)($modelo_puntos['puntos_grupos'] ?? 10),
];

$ranking_preview = $db->query("
    SELECT e.id,e.nombre AS equipo_nombre,
           e.jugador1_id,e.jugador2_id,
           j1.nombre AS j1_nombre,j1.apellido AS j1_apellido,j1.foto AS j1_foto,
           j2.nombre AS j2_nombre,j2.apellido AS j2_apellido,j2.foto AS j2_foto,
           SUM(LEAST(rp1.puntos,rp2.puntos)) AS puntos_total,
           COUNT(DISTINCT le.liga_id) AS torneos,
           SUM(LEAST(rp1.posicion_final,rp2.posicion_final)=1) AS titulos
    FROM liga_equipos le
    JOIN equipos e ON e.id=le.equipo_id
    JOIN jugadores j1 ON j1.id=e.jugador1_id
    JOIN jugadores j2 ON j2.id=e.jugador2_id
    JOIN ranking_puntos rp1 ON rp1.liga_id=le.liga_id AND rp1.jugador_id=e.jugador1_id
    JOIN ranking_puntos rp2 ON rp2.liga_id=le.liga_id AND rp2.jugador_id=e.jugador2_id
    WHERE YEAR(rp1.fecha_competicion)=YEAR(CURDATE())
    GROUP BY e.id,e.nombre,e.jugador1_id,e.jugador2_id,
             j1.nombre,j1.apellido,j1.foto,j2.nombre,j2.apellido,j2.foto
    ORDER BY puntos_total DESC,titulos DESC,torneos DESC,e.nombre
    LIMIT 5
")->fetchAll();
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
                    Circuito anual EPL · Temporada <?= $temporada ?>
                </div>

                <p class="home-hero__eyebrow">Torneos de un día. Una temporada para clasificar.</p>
                <h1 class="home-hero__title">
                    Todo el año.<br>
                    <span>Una gran final.</span>
                </h1>

                <p class="home-hero__lead">
                    Un calendario de torneos por categoría para competir fecha a fecha.
                    <strong>Acumula puntos con tu pareja y clasifica al Máster Final.</strong>
                </p>

                <div class="home-hero__actions">
                    <?php if (!epl_jugador_actual()): ?>
                    <a href="<?= epl_url('torneos.php') ?>" class="home-btn home-btn--primary">
                        Ver calendario y competir
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
                    </a>
                    <?php else: ?>
                    <a href="<?= epl_url('dashboard.php') ?>" class="home-btn home-btn--primary">
                        Ir a mi panel
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
                    </a>
                    <?php endif; ?>
                    <a href="<?= epl_url('ranking.php') ?>" class="home-btn home-btn--ghost">Ver carrera al Máster</a>
                </div>

                <div class="home-hero__trust" aria-label="Beneficios de Elite Padel League">
                    <span><i>✓</i> Calendario anual</span>
                    <span><i>✓</i> Ranking de parejas</span>
                    <span><i>✓</i> Máster Final</span>
                </div>
            </div>

            <aside class="home-match-card" aria-label="Sistema de puntos de la carrera al Máster Final">
                <div class="home-match-card__topline">
                    <span>Rumbo al Máster</span>
                    <span class="home-match-card__status"><i></i> <?= $temporada ?></span>
                </div>

                <div class="home-match-card__league">
                    <span>Sistema de puntos</span>
                    <strong>Cada fecha acerca a la final</strong>
                </div>

                <div class="home-points-grid">
                    <div class="home-points-grid__item home-points-grid__item--gold">
                        <span>01</span>
                        <strong><?= $puntos[1] ?></strong>
                        <small>Campeón</small>
                    </div>
                    <div class="home-points-grid__item">
                        <span>02</span>
                        <strong><?= $puntos[2] ?></strong>
                        <small>Finalista</small>
                    </div>
                    <div class="home-points-grid__item">
                        <span>03</span>
                        <strong><?= $puntos[3] ?></strong>
                        <small>Tercer lugar</small>
                    </div>
                    <div class="home-points-grid__item">
                        <span>+</span>
                        <strong><?= $puntos[5] ?></strong>
                        <small>Participar</small>
                    </div>
                </div>

                <div class="home-ranking-note">
                    <span>La pareja construye su camino</span>
                    <p>Compite junto a tu pareja durante el calendario. Cada resultado suma en la carrera al Máster.</p>
                </div>

                <a href="<?= epl_url('ranking.php') ?>" class="home-match-card__link">Explorar carrera al Máster <span>↘</span></a>
            </aside>
        </div>
    </header>

    <!-- ==========================================
         2. STATS BAR (Social proof)
         ========================================== -->
    <section class="stats-bar" aria-label="Formato del circuito EPL">
        <div class="stats-grid">
            <div>
                <div class="stat-num">+ fechas</div>
                <div class="stat-lbl">Durante todo el año</div>
            </div>
            <div>
                <div class="stat-num">+ puntos</div>
                <div class="stat-lbl">Cada fecha suma</div>
            </div>
            <div>
                <div class="stat-num">1 Máster</div>
                <div class="stat-lbl">Las mejores parejas</div>
            </div>
        </div>
    </section>

    <!-- ==========================================
         3. PILARES DEL NUEVO CIRCUITO
         ========================================== -->
    <section class="py-12 md:py-24 bg-white px-4 md:px-6 relative z-20" id="pilares">
        <div class="max-w-6xl mx-auto">
            <div class="text-center mb-8 md:mb-16">
                <span class="text-epl-gold font-black text-[10px] uppercase tracking-[0.2em] mb-2 block">El circuito anual EPL</span>
                <h2 class="text-4xl md:text-5xl text-epl-blue font-primary uppercase tracking-tight">Muchas fechas. <span class="text-epl-gold">Un solo objetivo.</span></h2>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <article class="feature-card-tw">
                    <div class="feature-icon-wrapper-tw">
                        <svg xmlns="http://www.w3.org/2000/svg" width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 9H4.5a2.5 2.5 0 0 1 0-5H6"/><path d="M18 9h1.5a2.5 2.5 0 0 0 0-5H18"/><path d="M4 22h16"/><path d="M10 14.66V17c0 .55-.47.98-.97 1.21C7.85 18.75 7 20.24 7 22"/><path d="M14 14.66V17c0 .55.47.98.97 1.21C16.15 18.75 17 20.24 17 22"/><path d="M18 2H6v7a6 6 0 0 0 12 0V2Z"/></svg>
                    </div>
                    <h3 class="font-primary text-2xl text-epl-blue uppercase mb-3">Elige tus fechas</h3>
                    <p class="text-sm text-gray-500 leading-relaxed font-medium">El calendario se extiende durante el año con torneos por categoría, cada uno resuelto en una sola jornada.</p>
                </article>

                <article class="feature-card-tw">
                    <div class="feature-icon-wrapper-tw">
                        <svg xmlns="http://www.w3.org/2000/svg" width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M17 21v-2a4 4 0 0 0-4-4H5c-2.2 0-4 1.8-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                    </div>
                    <h3 class="font-primary text-2xl text-epl-blue uppercase mb-3">Suma con tu pareja</h3>
                    <p class="text-sm text-gray-500 leading-relaxed font-medium">Campeones, finalistas, semifinalistas o participantes: cada resultado agrega puntos a la misma pareja.</p>
                </article>

                <article class="feature-card-tw">
                    <div class="feature-icon-wrapper-tw">
                        <svg xmlns="http://www.w3.org/2000/svg" width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                    </div>
                    <h3 class="font-primary text-2xl text-epl-blue uppercase mb-3">Clasifica al Máster</h3>
                    <p class="text-sm text-gray-500 leading-relaxed font-medium">Al cerrar el calendario, las mejores parejas de cada categoría avanzan al gran Máster Final EPL.</p>
                </article>
            </div>
        </div>
    </section>

    <!-- ==========================================
         4. RUTA DE TEMPORADA + RANKING DE PAREJAS
         ========================================== -->
    <section id="el-circuito" class="home-circuit-section">
        <div class="home-circuit-section__inner">
            <article class="home-format-card">
                <div class="home-format-card__eyebrow">Ruta de la temporada <?= $temporada ?></div>
                <h2>Fecha a fecha.<br><span>Hasta el</span><br>Máster Final.</h2>
                <p>Cada torneo empieza y termina el mismo día, pero la historia continúa durante todo el año. La pareja acumula resultados hasta conseguir su lugar en la gran final.</p>

                <div class="home-format-flow">
                    <div><b>01</b><span><strong>Calendario anual</strong><small>Elige las fechas de tu categoría.</small></span></div>
                    <div><b>02</b><span><strong>Ranking de parejas</strong><small>Cada posición suma puntos en la temporada.</small></span></div>
                    <div><b>03</b><span><strong>Máster Final</strong><small>Las mejores parejas clasifican a la definición.</small></span></div>
                </div>

                <a href="<?= epl_url('torneos.php') ?>" class="home-btn home-btn--primary">Explorar calendario →</a>
            </article>

            <article class="home-leaderboard-card">
                <div class="home-leaderboard-card__head">
                    <div>
                        <span>Temporada <?= $temporada ?></span>
                        <h2>Carrera al Máster</h2>
                    </div>
                    <a href="<?= epl_url('ranking.php') ?>">Ver completo ↗</a>
                </div>

                <?php if ($ranking_preview): ?>
                <div class="home-leaderboard-list">
                    <?php foreach ($ranking_preview as $i => $r):
                        $j1_ranking = trim($r['j1_nombre'].' '.$r['j1_apellido']);
                        $j2_ranking = trim($r['j2_nombre'].' '.$r['j2_apellido']);
                        $nombre_ranking = trim($r['equipo_nombre']) ?: $j1_ranking.' / '.$j2_ranking;
                    ?>
                    <div class="home-leaderboard-row">
                        <b><?= str_pad((string)($i+1), 2, '0', STR_PAD_LEFT) ?></b>
                        <span class="home-leaderboard-row__duo" aria-hidden="true">
                            <img src="<?= epl_h(epl_foto_jugador($r['j1_foto'], $j1_ranking)) ?>" alt="" loading="lazy">
                            <img src="<?= epl_h(epl_foto_jugador($r['j2_foto'], $j2_ranking)) ?>" alt="" loading="lazy">
                        </span>
                        <span><strong><?= epl_h($nombre_ranking) ?></strong><small><?= epl_h($j1_ranking) ?> · <?= epl_h($j2_ranking) ?> · <?= (int)$r['torneos'] ?> fecha<?= (int)$r['torneos'] === 1 ? '' : 's' ?></small></span>
                        <em><?= (int)$r['puntos_total'] ?> pts</em>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <div class="home-ranking-empty">
                    <span class="home-ranking-empty__number">01</span>
                    <div>
                        <strong>La carrera comienza en la primera fecha</strong>
                        <p>Todas las parejas parten desde cero. Cada torneo las acerca al Máster Final.</p>
                    </div>
                </div>
                <div class="home-ranking-scale">
                    <span><b><?= $puntos[1] ?></b> Campeón</span>
                    <span><b><?= $puntos[2] ?></b> Finalista</span>
                    <span><b><?= $puntos[3] ?></b> Tercero</span>
                </div>
                <?php endif; ?>

                <div class="home-leaderboard-card__footer">
                    <span>Compite. Suma. Clasifica.</span>
                    <strong>El Máster te espera.</strong>
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

                <img src="<?= epl_url('assets/img/landing/filosofia.jpg') ?>" alt="Comunidad Elite Padel League — más que un torneo, una familia" class="rounded-3xl shadow-2xl relative z-10 w-full object-cover aspect-[4/3]" loading="lazy">

                <div class="absolute -bottom-8 right-8 bg-white p-5 rounded-2xl shadow-xl z-20 flex items-center gap-4">
                    <div class="w-12 h-12 bg-epl-blue rounded-full flex items-center justify-center text-white font-['Anton'] text-xl">1°</div>
                    <div>
                        <p class="font-black text-epl-blue text-sm uppercase leading-tight">Circuito amateur</p>
                        <p class="text-xs text-gray-500 font-medium">De alto rendimiento</p>
                    </div>
                </div>
            </div>

            <div class="lg:w-1/2 mt-10 lg:mt-0">
                <span class="text-epl-gold font-black text-[10px] uppercase tracking-[0.2em] mb-4 block">Nuestra Filosofía</span>
                <h2 class="text-4xl md:text-5xl text-epl-blue font-primary uppercase tracking-tight mb-6 leading-none">Más que un torneo,<br> <span class="text-epl-gold">una familia</span></h2>

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
                            <p class="text-gray-400 text-sm leading-relaxed font-medium">Crea tu ficha, elige tu categoría y asegura tu lugar en la próxima fecha de un día.</p>
                        </div>
                    </div>

                    <div class="timeline-step flex flex-row md:flex-col items-center md:text-center gap-6 md:gap-0 group">
                        <div class="w-24 h-24 shrink-0 rounded-full bg-epl-gold text-epl-blue flex items-center justify-center border-[6px] border-epl-blue relative shadow-[0_0_20px_rgba(201,167,98,0.3)]">
                            <span class="absolute -top-1 -right-1 bg-white text-epl-blue font-black w-7 h-7 rounded-full flex items-center justify-center text-sm shadow-md">2</span>
                            <svg xmlns="http://www.w3.org/2000/svg" width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M8.5 14.5A2.5 2.5 0 0 0 11 12c0-1.38-.5-2-1-3-1.072-2.143-.224-4.054 2-6 .5 2.5 2 4.9 4 6.5 2 1.6 3 3.5 3 5.5a7 7 0 1 1-14 0c0-1.153.433-2.294 1-3a2.5 2.5 0 0 0 2.5 2.5z"/></svg>
                        </div>
                        <div class="md:mt-8">
                            <h3 class="text-2xl font-primary uppercase tracking-wide text-white mb-2 group-hover:text-epl-gold transition-colors">Compite en un día</h3>
                            <p class="text-gray-400 text-sm leading-relaxed font-medium">Juega la fase de grupos y avanza a las finales contra parejas de tu mismo nivel.</p>
                        </div>
                    </div>

                    <div class="timeline-step flex flex-row md:flex-col items-center md:text-center gap-6 md:gap-0 group">
                        <div class="w-24 h-24 shrink-0 rounded-full bg-epl-gold text-epl-blue flex items-center justify-center border-[6px] border-epl-blue relative shadow-[0_0_20px_rgba(201,167,98,0.3)]">
                            <span class="absolute -top-1 -right-1 bg-white text-epl-blue font-black w-7 h-7 rounded-full flex items-center justify-center text-sm shadow-md">3</span>
                            <svg xmlns="http://www.w3.org/2000/svg" width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M17 11h1.22a2 2 0 0 1 1.94 1.51l1.68 6.4a2 2 0 0 1-1.94 2.51H17"/><path d="M13 3h-2v4h2Z"/><path d="M9 11H5.82a2 2 0 0 0-1.94 1.51l-1.68 6.4a2 2 0 0 0 1.94 2.51H9"/><rect width="8" height="18" x="8" y="3" rx="2"/></svg>
                        </div>
                        <div class="md:mt-8">
                            <h3 class="text-2xl font-primary uppercase tracking-wide text-white mb-2 group-hover:text-epl-gold transition-colors">Suma puntos</h3>
                            <p class="text-gray-400 text-sm leading-relaxed font-medium">El resultado suma al ranking anual de la pareja y los acerca al Máster Final. Después, quédate a vivir el Tercer Tiempo.</p>
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
                            <p class="text-xs text-gray-500 font-medium">Circuito 4ta · Ranking 2026</p>
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
                            <p class="text-xs text-gray-500 font-medium">Circuito 5ta Femenino · Ranking 2026</p>
                        </div>
                    </div>
                </article>

                <article class="testimonio-card">
                    <div class="testimonio-stars mb-3">★★★★★</div>
                    <p class="text-gray-600 text-sm leading-relaxed mb-5 font-medium">"La plataforma es lejos lo mejor: ranking actualizado, historial, todo desde el celular. Se nota que es un circuito profesional."</p>
                    <div class="flex items-center gap-3 mt-auto pt-3 border-t border-gray-100">
                        <div class="w-11 h-11 rounded-full bg-epl-blue text-epl-gold flex items-center justify-center font-['Anton'] text-lg">DA</div>
                        <div>
                            <p class="font-bold text-epl-blue text-sm">Diego Aravena</p>
                            <p class="text-xs text-gray-500 font-medium">Circuito 3ra · Ranking 2026</p>
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
                <summary class="faq-q">¿Cómo me inscribo a un torneo de Elite Padel League?</summary>
                <div class="faq-a">Creas tu ficha de jugador gratis en menos de 2 minutos, eliges tu categoría y te inscribes con tu pareja desde la sección Torneos. Recibes confirmación inmediata por correo electrónico y notificación.</div>
            </details>
            <details class="faq-item">
                <summary class="faq-q">¿Cuánto cuesta participar?</summary>
                <div class="faq-a">El valor depende del torneo y la categoría. Cada inscripción incluye paquete de bienvenida, agua, Tercer Tiempo y acceso completo a la plataforma con la carrera anual actualizada.</div>
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
                <summary class="faq-q">¿Cuándo son los torneos?</summary>
                <div class="faq-a">Cada torneo EPL se juega y se define en una sola jornada. Las nuevas fechas se publican en la sección Torneos con su día, categoría, sede y cupos disponibles.</div>
            </details>
            <details class="faq-item">
                <summary class="faq-q">¿Cómo funciona el ranking de parejas?</summary>
                <div class="faq-a">Cada resultado suma puntos a la pareja durante la temporada: 100 para los campeones, 70 para los finalistas, 50 para el tercer lugar y puntos por participación. Todas las fechas del calendario ayudan a escalar.</div>
            </details>
            <details class="faq-item">
                <summary class="faq-q">¿Cómo se clasifica al Máster Final?</summary>
                <div class="faq-a">Al terminar el calendario anual, las mejores parejas de cada categoría en el ranking EPL obtienen su lugar en el Máster Final.</div>
            </details>
            <details class="faq-item">
                <summary class="faq-q">¿Puedo jugar si no tengo pareja?</summary>
                <div class="faq-a">Sí. Tenemos un sistema de búsqueda de pareja y un formato americano donde puedes inscribirte sin compañero o compañera; te ayudamos a encontrar una pareja de nivel equivalente.</div>
            </details>

            <!-- JSON-LD FAQ Schema -->
            <script type="application/ld+json">
            {
              "@context":"https://schema.org",
              "@type":"FAQPage",
              "mainEntity":[
                {"@type":"Question","name":"¿Cómo me inscribo a un torneo de Elite Padel League?","acceptedAnswer":{"@type":"Answer","text":"Creas tu ficha gratis en menos de 2 minutos, eliges categoría y te inscribes con tu pareja desde la sección Torneos."}},
                {"@type":"Question","name":"¿Cuánto cuesta participar?","acceptedAnswer":{"@type":"Answer","text":"Depende del torneo y la categoría. Incluye paquete de bienvenida, Tercer Tiempo y acceso a la plataforma."}},
                {"@type":"Question","name":"¿Qué nivel necesito para jugar?","acceptedAnswer":{"@type":"Answer","text":"Categorías desde principiante (5ta) hasta avanzado (1ra). Se asignan rivales del mismo nivel."}},
                {"@type":"Question","name":"¿Dónde se juegan los partidos?","acceptedAnswer":{"@type":"Answer","text":"Sede oficial: Conecta Santa Blanca, Santiago de Chile."}},
                {"@type":"Question","name":"¿Cuándo son los torneos?","acceptedAnswer":{"@type":"Answer","text":"Cada torneo se juega y se define en una sola jornada. Las fechas se publican con día, categoría, sede y cupos disponibles."}},
                {"@type":"Question","name":"¿Cómo funciona el ranking de parejas?","acceptedAnswer":{"@type":"Answer","text":"Cada resultado suma puntos a la pareja durante la temporada y todas las fechas del calendario ayudan a escalar."}},
                {"@type":"Question","name":"¿Cómo se clasifica al Máster Final?","acceptedAnswer":{"@type":"Answer","text":"Las mejores parejas de cada categoría al cerrar el calendario anual obtienen un lugar en el Máster Final."}},
                {"@type":"Question","name":"¿Puedo jugar si no tengo pareja?","acceptedAnswer":{"@type":"Answer","text":"Sí, hay un sistema de búsqueda y un formato americano para encontrar una pareja de nivel equivalente."}}
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
                        <span>Torneos de un día: viernes, sábados o domingos según calendario</span>
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
                Crea tu ficha, arma tu pareja y elige las fechas con las que quieres construir tu temporada.
                <strong class="text-epl-blue">Cada torneo es un paso más hacia el Máster Final.</strong>
            </p>

            <?php if (!epl_jugador_actual()): ?>
              <a href="<?= epl_url('registro.php') ?>" class="inline-flex items-center justify-center gap-3 bg-epl-blue text-white px-10 py-5 rounded-xl font-black uppercase text-[14px] tracking-widest hover:bg-epl-gold transition-all shadow-xl hover:-translate-y-1 relative z-10 no-underline">
                  Comenzar el camino al Máster
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
