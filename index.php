<?php
$page_title       = null; // usamos el default optimizado SEO del header
$active_nav       = 'inicio';
$meta_description = 'Elite Padel League: el circuito amateur más competitivo de Santiago. Inscríbete a torneos de pádel por categoría, sigue tu ranking en vivo y vive el Tercer Tiempo con la comunidad EPL.';
$og_image         = null; // usa el default del header
$og_type          = 'website';

require_once 'includes/functions.php';

$liga             = epl_liga_activa();
$clasificacion    = $liga ? array_slice(epl_clasificacion($liga['id']), 0, 5) : [];
$ultimos_partidos = $liga ? array_slice(epl_partidos_liga($liga['id'], 'jugado'), -5) : [];
$proximos         = $liga ? array_slice(epl_partidos_liga($liga['id'], 'pendiente'), 0, 3) : [];

// Social proof stats (defensivo)
$db = epl_db();
$stats = [
    'jugadores' => (int)$db->query("SELECT COUNT(*) FROM jugadores WHERE estado='activo'")->fetchColumn(),
    'partidos'  => (int)$db->query("SELECT COUNT(*) FROM partidos WHERE estado='jugado'")->fetchColumn(),
    'ligas'     => (int)$db->query("SELECT COUNT(*) FROM ligas")->fetchColumn(),
];
?>
<?php require_once 'includes/header.php'; ?>

<style>
    /* ── PÁGINA INICIO ────────────────────────────────────────────── */
    .epl-body-wrapper {
        width:100vw !important; max-width:100vw !important;
        position:relative; left:50%; right:50%;
        margin-left:-50vw !important; margin-right:-50vw !important;
        font-family:'Montserrat',sans-serif;
        background:#F5F7F8; overflow-x:hidden;
    }

    /* HERO con foto real de pádel */
    .hero-section {
        background:#0A1421;
        background-image:
            linear-gradient(to bottom, rgba(10,20,33,.82) 0%, rgba(28,47,72,.78) 50%, rgba(10,20,33,.95) 100%),
            url('<?= epl_url('assets/img/landing/hero-padel.jpg') ?>');
        background-size:cover; background-position:center;
        background-attachment:scroll;
        width:100%; display:block;
    }
    .giant-title { line-height:.95; letter-spacing:-.01em; }

    /* Pilares */
    .feature-card-tw {
        background:#fff; border-radius:20px; padding:40px 30px;
        box-shadow:0 10px 30px rgba(0,0,0,.03); border:1px solid #f1f5f9;
        transition:all .3s ease; text-align:center;
    }
    .feature-card-tw:hover { transform:translateY(-5px); border-color:#C9A762; box-shadow:0 15px 35px rgba(201,167,98,.1); }
    .feature-icon-wrapper-tw {
        width:80px; height:80px; margin:0 auto 20px; border-radius:20px;
        background:#f8fafc; display:flex; align-items:center; justify-content:center;
        color:#1C2F48; transition:all .3s ease;
    }
    .feature-card-tw:hover .feature-icon-wrapper-tw { background:#1C2F48; color:#C9A762; }

    /* Sección PHP */
    .epl-php-section { margin-top:3rem; }
    .epl-php-section .section-eyebrow { color:#C9A762; font-weight:700; text-transform:uppercase; letter-spacing:.1em; font-size:.85rem; margin-bottom:.5rem; }
    .epl-php-section .section-title { font-family:'Anton',sans-serif; font-size:2.5rem; color:#1C2F48; text-transform:uppercase; margin-bottom:2rem; }
    .epl-php-section .partido-card { margin-bottom:1rem; border:1px solid #e2e8f0; }

    /* Stats / social proof */
    .stats-bar { background:#1C2F48; color:#fff; padding:2.25rem 1rem; }
    .stats-grid { display:grid; grid-template-columns:repeat(3,1fr); gap:1rem; max-width:900px; margin:0 auto; text-align:center; }
    .stat-num { font-family:'Anton',sans-serif; font-size:3rem; color:#C9A762; line-height:1; }
    .stat-lbl { font-size:.72rem; color:#cbd5e1; font-weight:700; text-transform:uppercase; letter-spacing:.12em; margin-top:.4rem; }

    /* Galería tercer tiempo */
    .tt-grid { display:grid; grid-template-columns:2fr 1fr 1fr; grid-template-rows:1fr 1fr; gap:.75rem; height:480px; }
    .tt-grid > div { border-radius:18px; overflow:hidden; position:relative; }
    .tt-grid > div:nth-child(1) { grid-row:span 2; }
    .tt-grid img { width:100%; height:100%; object-fit:cover; transition:transform .6s ease; }
    .tt-grid > div:hover img { transform:scale(1.06); }
    @media (max-width:768px) {
      .tt-grid { grid-template-columns:1fr 1fr; grid-template-rows:auto; height:auto; }
      .tt-grid > div:nth-child(1) { grid-row:auto; aspect-ratio:1/1; }
      .tt-grid > div { aspect-ratio:1/1; }
    }

    /* Sponsors */
    .sponsor-card {
        background:#fff; border:1.5px solid #e2e8f0; border-radius:14px;
        padding:1.5rem 1rem; text-align:center; transition:all .25s ease;
        display:flex; align-items:center; justify-content:center;
        min-height:110px;
    }
    .sponsor-card:hover { border-color:#C9A762; box-shadow:0 8px 24px rgba(201,167,98,.12); transform:translateY(-3px); }
    .sponsor-logo { max-height:50px; max-width:140px; object-fit:contain; filter:grayscale(60%); opacity:.85; transition:all .25s; }
    .sponsor-card:hover .sponsor-logo { filter:grayscale(0); opacity:1; }

    /* Testimonios */
    .testimonio-card {
        background:#fff; border-radius:18px; padding:2rem 1.75rem;
        border:1px solid #f1f5f9; box-shadow:0 4px 20px rgba(0,0,0,.04);
        position:relative;
    }
    .testimonio-card::before {
        content:"\201C"; position:absolute; top:-14px; left:24px;
        font-family:'Anton',sans-serif; font-size:5rem; color:#C9A762; line-height:1;
    }
    .testimonio-stars { color:#fbbf24; font-size:.95rem; letter-spacing:.05em; }

    /* FAQ */
    .faq-item { background:#fff; border:1px solid #e2e8f0; border-radius:12px; margin-bottom:.6rem; overflow:hidden; transition:all .2s; }
    .faq-item[open] { border-color:#C9A762; box-shadow:0 6px 16px rgba(201,167,98,.08); }
    .faq-q { padding:1.1rem 1.25rem; font-weight:800; color:#1C2F48; cursor:pointer; list-style:none; display:flex; justify-content:space-between; align-items:center; font-size:.93rem; }
    .faq-q::-webkit-details-marker { display:none; }
    .faq-q::after { content:"+"; font-family:'Anton',sans-serif; font-size:1.6rem; color:#C9A762; line-height:1; transition:transform .25s; }
    .faq-item[open] .faq-q::after { transform:rotate(45deg); }
    .faq-a { padding:0 1.25rem 1.25rem; color:#475569; font-size:.88rem; line-height:1.65; }
</style>

<div class="epl-body-wrapper antialiased text-gray-700">

    <!-- ==========================================
         1. HERO
         ========================================== -->
    <header class="hero-section pt-24 pb-24 md:pt-32 md:pb-40 text-center px-6">
        <div class="max-w-5xl mx-auto relative z-10">

            <!-- Insignia superior -->
            <div class="mx-auto w-24 h-24 mb-8 bg-epl-gold/10 rounded-full flex items-center justify-center border border-epl-gold/30 backdrop-blur-sm drop-shadow-xl shadow-[0_0_30px_rgba(201,167,98,0.2)]">
                <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="text-epl-gold" aria-hidden="true">
                    <path d="M12 16c3.5 0 6.5-3 6.5-7.5S15.5 2 12 2 5.5 4 5.5 8.5 8.5 16 12 16z"/>
                    <path d="M12 16v6"/><path d="M10 22h4"/>
                </svg>
            </div>

            <span class="font-black text-epl-gold tracking-[0.5em] uppercase text-[10px] md:text-xs mb-4 block opacity-90 drop-shadow-lg">
                La Liga de Pádel Más Competitiva de Santiago
            </span>

            <h1 class="giant-title text-5xl md:text-7xl lg:text-[7.5rem] text-white font-primary mb-8 drop-shadow-2xl uppercase">
                Bienvenido a la <span class="text-epl-gold">Elite</span><br class="hidden md:block"> del Pádel
            </h1>

            <p class="mt-4 max-w-2xl mx-auto text-lg md:text-xl text-gray-200 font-medium mb-12 leading-relaxed opacity-90">
                No organizamos torneos. <strong class="text-white">Construimos temporadas.</strong>
                Competí, compartí y pertenecé a la comunidad de pádel premium de Chile.
            </p>

            <div class="flex flex-col md:flex-row justify-center gap-5 mt-12 relative z-10">
                <?php if (!epl_jugador_actual()): ?>
                <a href="<?= epl_url('registro.php') ?>" class="bg-epl-gold text-epl-blue px-10 py-5 rounded-xl font-black uppercase text-[13px] tracking-widest hover:bg-white transition-all shadow-[0_0_20px_rgba(201,167,98,0.3)] hover:-translate-y-1 flex items-center justify-center gap-2">
                    Crear mi ficha gratis
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
                </a>
                <?php else: ?>
                <a href="<?= epl_url('dashboard.php') ?>" class="bg-epl-gold text-epl-blue px-10 py-5 rounded-xl font-black uppercase text-[13px] tracking-widest hover:bg-white transition-all shadow-[0_0_20px_rgba(201,167,98,0.3)] hover:-translate-y-1 flex items-center justify-center gap-2">
                    Ir a mi panel
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
                </a>
                <?php endif; ?>
                <a href="<?= epl_url('torneos.php') ?>" class="border-2 border-white/30 text-white px-10 py-5 rounded-xl font-secondary font-black uppercase text-[13px] tracking-[0.15em] hover:bg-white hover:text-epl-blue transition-all duration-300 hover:-translate-y-1 flex items-center justify-center gap-3">
                    Ver torneos abiertos
                </a>
            </div>

            <p class="text-gray-400 text-[10px] md:text-[11px] font-bold mt-8 uppercase tracking-widest opacity-80 relative z-10">
                Inscripción rápida · Categorías por nivel · Tercer Tiempo incluido
            </p>
        </div>
    </header>

    <!-- ==========================================
         2. STATS BAR (Social proof)
         ========================================== -->
    <section class="stats-bar" aria-label="Estadísticas de la comunidad">
        <div class="stats-grid">
            <div>
                <div class="stat-num"><?= $stats['jugadores'] ?>+</div>
                <div class="stat-lbl">Jugadores activos</div>
            </div>
            <div>
                <div class="stat-num"><?= $stats['partidos'] ?>+</div>
                <div class="stat-lbl">Partidos disputados</div>
            </div>
            <div>
                <div class="stat-num"><?= $stats['ligas'] ?></div>
                <div class="stat-lbl">Temporadas EPL</div>
            </div>
        </div>
    </section>

    <!-- ==========================================
         3. PILARES DE MARCA (Competir · Compartir · Pertenecer)
         ========================================== -->
    <section class="py-12 md:py-24 bg-white px-4 md:px-6 relative z-20" id="pilares">
        <div class="max-w-6xl mx-auto">
            <div class="text-center mb-8 md:mb-16">
                <span class="text-epl-gold font-black text-[10px] uppercase tracking-[0.2em] mb-2 block">Nuestros pilares</span>
                <h2 class="text-4xl md:text-5xl text-epl-blue font-primary uppercase tracking-tight">Competir · Compartir · <span class="text-epl-gold">Pertenecer</span></h2>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <article class="feature-card-tw">
                    <div class="feature-icon-wrapper-tw">
                        <svg xmlns="http://www.w3.org/2000/svg" width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 9H4.5a2.5 2.5 0 0 1 0-5H6"/><path d="M18 9h1.5a2.5 2.5 0 0 0 0-5H18"/><path d="M4 22h16"/><path d="M10 14.66V17c0 .55-.47.98-.97 1.21C7.85 18.75 7 20.24 7 22"/><path d="M14 14.66V17c0 .55.47.98.97 1.21C16.15 18.75 17 20.24 17 22"/><path d="M18 2H6v7a6 6 0 0 0 12 0V2Z"/></svg>
                    </div>
                    <h3 class="font-primary text-2xl text-epl-blue uppercase mb-3">Competir</h3>
                    <p class="text-sm text-gray-500 leading-relaxed font-medium">Categorías estrictamente medidas. Te enfrentás a parejas de tu mismo nivel y subís de liga con cada temporada.</p>
                </article>

                <article class="feature-card-tw">
                    <div class="feature-icon-wrapper-tw">
                        <svg xmlns="http://www.w3.org/2000/svg" width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M17 21v-2a4 4 0 0 0-4-4H5c-2.2 0-4 1.8-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                    </div>
                    <h3 class="font-primary text-2xl text-epl-blue uppercase mb-3">Compartir</h3>
                    <p class="text-sm text-gray-500 leading-relaxed font-medium">El partido no termina en el punto de oro. El Tercer Tiempo es nuestro ritual: networking, conversación y la mejor previa para volver la próxima fecha.</p>
                </article>

                <article class="feature-card-tw">
                    <div class="feature-icon-wrapper-tw">
                        <svg xmlns="http://www.w3.org/2000/svg" width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                    </div>
                    <h3 class="font-primary text-2xl text-epl-blue uppercase mb-3">Pertenecer</h3>
                    <p class="text-sm text-gray-500 leading-relaxed font-medium">Tu ficha de jugador, tu ranking, tu historial. Sos parte de la comunidad EPL que vive el pádel todo el año, no solo un fin de semana.</p>
                </article>
            </div>
        </div>
    </section>

    <!-- ==========================================
         4. LIGA ACTIVA + CALENDARIO
         ========================================== -->
    <section id="el-circuito" class="py-10 md:py-24 bg-[#F5F7F8] px-4 md:px-6 border-y border-gray-200">
        <div class="max-w-7xl mx-auto">
            <div class="flex flex-col md:flex-row justify-between items-start md:items-end mb-8 md:mb-12 gap-4 md:gap-6">
                <div>
                    <span class="text-epl-gold font-black text-[10px] uppercase tracking-[0.2em] mb-2 block">En vivo</span>
                    <h2 class="text-4xl md:text-5xl text-epl-blue font-primary uppercase tracking-tight">Liga <span class="text-epl-gold">Activa</span></h2>
                </div>
                <a href="<?= epl_url('torneos.php') ?>" class="text-epl-blue font-black text-[10px] uppercase tracking-[0.2em] hover:text-epl-gold transition-colors flex items-center gap-2 no-underline">
                    Ver calendario completo
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
                </a>
            </div>

            <div class="epl-php-section">
                <?php if ($liga && $clasificacion): ?>
                <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6 md:p-8 mb-12">
                  <p class="section-eyebrow">Liga activa</p>
                  <h3 class="section-title"><?= epl_h($liga['nombre']) ?></h3>
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
                                <img class="w-8 h-8 rounded-full border-2 border-white object-cover" src="<?= epl_h(epl_foto_jugador($row['j1_foto'], $row['j1_nombre'].' '.$row['j1_apellido'])) ?>" alt="<?= epl_h($row['j1_nombre'].' '.$row['j1_apellido']) ?>" loading="lazy">
                                <img class="w-8 h-8 rounded-full border-2 border-white object-cover" src="<?= epl_h(epl_foto_jugador($row['j2_foto'], $row['j2_nombre'].' '.$row['j2_apellido'])) ?>" alt="<?= epl_h($row['j2_nombre'].' '.$row['j2_apellido']) ?>" loading="lazy">
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
                  <a href="<?= epl_url('clasificacion.php') ?>" class="text-epl-blue font-bold text-xs uppercase tracking-wider hover:text-epl-gold transition-colors no-underline">Ver clasificación completa →</a>
                </div>
                <?php endif; ?>

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-12">
                    <?php if ($ultimos_partidos): ?>
                    <div>
                      <p class="section-eyebrow">Resultados recientes</p>
                      <h3 class="section-title text-3xl">Últimos Partidos</h3>
                      <div class="partidos-list space-y-3">
                        <?php foreach (array_reverse($ultimos_partidos) as $p): ?>
                        <article class="partido-card bg-white p-4 rounded-xl flex items-center justify-between shadow-sm">
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
                        </article>
                        <?php endforeach; ?>
                      </div>
                      <a href="<?= epl_url('resultados.php') ?>" class="inline-block mt-4 text-epl-blue font-bold text-xs uppercase tracking-wider hover:text-epl-gold transition-colors no-underline">Ver todos los resultados →</a>
                    </div>
                    <?php endif; ?>

                    <?php if ($proximos): ?>
                    <div>
                      <p class="section-eyebrow">Agenda</p>
                      <h3 class="section-title text-3xl">Próximos Partidos</h3>
                      <div class="partidos-list space-y-3">
                        <?php foreach ($proximos as $p): ?>
                        <article class="partido-card bg-white p-4 rounded-xl flex items-center justify-between shadow-sm border-l-4 border-l-epl-gold">
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
                        </article>
                        <?php endforeach; ?>
                      </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
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
                <div><img src="<?= epl_url('assets/img/landing/tercer-tiempo-1.jpg') ?>" alt="Comunidad Elite Padel League compartiendo después del partido" loading="lazy"></div>
                <div><img src="<?= epl_url('assets/img/landing/accion-padel.jpg') ?>" alt="Acción en cancha durante torneo EPL" loading="lazy"></div>
                <div><img src="<?= epl_url('assets/img/landing/tercer-tiempo-2.jpg') ?>" alt="Tercer Tiempo Elite Padel League — networking en sede" loading="lazy"></div>
                <div><img src="<?= epl_url('assets/img/landing/tercer-tiempo-3.jpg') ?>" alt="Brindis y celebración fin de fecha EPL" loading="lazy"></div>
            </div>

            <div class="text-center">
                <a href="https://www.instagram.com/epleaguecl/" target="_blank" rel="noopener" class="inline-flex items-center gap-3 text-epl-blue font-black text-[11px] uppercase tracking-[0.2em] hover:text-epl-gold transition-colors no-underline">
                    Mirá la última fecha en Instagram
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
                        <p class="font-black text-epl-blue text-sm uppercase leading-tight">Liga Amateur</p>
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
                    Conocé el reglamento
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
                            <p class="text-gray-400 text-sm leading-relaxed font-medium">Creá tu ficha en 2 minutos, elegí tu categoría e inscribite con tu pareja al próximo torneo.</p>
                        </div>
                    </div>

                    <div class="timeline-step flex flex-row md:flex-col items-center md:text-center gap-6 md:gap-0 group">
                        <div class="w-24 h-24 shrink-0 rounded-full bg-epl-gold text-epl-blue flex items-center justify-center border-[6px] border-epl-blue relative shadow-[0_0_20px_rgba(201,167,98,0.3)]">
                            <span class="absolute -top-1 -right-1 bg-white text-epl-blue font-black w-7 h-7 rounded-full flex items-center justify-center text-sm shadow-md">2</span>
                            <svg xmlns="http://www.w3.org/2000/svg" width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M8.5 14.5A2.5 2.5 0 0 0 11 12c0-1.38-.5-2-1-3-1.072-2.143-.224-4.054 2-6 .5 2.5 2 4.9 4 6.5 2 1.6 3 3.5 3 5.5a7 7 0 1 1-14 0c0-1.153.433-2.294 1-3a2.5 2.5 0 0 0 2.5 2.5z"/></svg>
                        </div>
                        <div class="md:mt-8">
                            <h3 class="text-2xl font-primary uppercase tracking-wide text-white mb-2 group-hover:text-epl-gold transition-colors">Jugá a muerte</h3>
                            <p class="text-gray-400 text-sm leading-relaxed font-medium">Entrá al 20×10 contra parejas de tu mismo nivel. Tu ranking se actualiza en vivo desde el celular.</p>
                        </div>
                    </div>

                    <div class="timeline-step flex flex-row md:flex-col items-center md:text-center gap-6 md:gap-0 group">
                        <div class="w-24 h-24 shrink-0 rounded-full bg-epl-gold text-epl-blue flex items-center justify-center border-[6px] border-epl-blue relative shadow-[0_0_20px_rgba(201,167,98,0.3)]">
                            <span class="absolute -top-1 -right-1 bg-white text-epl-blue font-black w-7 h-7 rounded-full flex items-center justify-center text-sm shadow-md">3</span>
                            <svg xmlns="http://www.w3.org/2000/svg" width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M17 11h1.22a2 2 0 0 1 1.94 1.51l1.68 6.4a2 2 0 0 1-1.94 2.51H17"/><path d="M13 3h-2v4h2Z"/><path d="M9 11H5.82a2 2 0 0 0-1.94 1.51l-1.68 6.4a2 2 0 0 0 1.94 2.51H9"/><rect width="8" height="18" x="8" y="3" rx="2"/></svg>
                        </div>
                        <div class="md:mt-8">
                            <h3 class="text-2xl font-primary uppercase tracking-wide text-white mb-2 group-hover:text-epl-gold transition-colors">Tercer Tiempo</h3>
                            <p class="text-gray-400 text-sm leading-relaxed font-medium">Quedate a celebrar, compartir y conocer a los próximos rivales. El partido no termina en el punto de oro.</p>
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
                    <p class="text-gray-600 text-sm leading-relaxed mb-5 font-medium">"Llevo tres temporadas en EPL. La organización es de otro nivel y al final del día todos terminamos siendo amigos. El Tercer Tiempo es la mejor parte."</p>
                    <div class="flex items-center gap-3 mt-auto pt-3 border-t border-gray-100">
                        <div class="w-11 h-11 rounded-full bg-epl-blue text-epl-gold flex items-center justify-center font-['Anton'] text-lg">MC</div>
                        <div>
                            <p class="font-bold text-epl-blue text-sm">Matías Castro</p>
                            <p class="text-xs text-gray-500 font-medium">Liga 4ta · Temporada 2026</p>
                        </div>
                    </div>
                </article>

                <article class="testimonio-card">
                    <div class="testimonio-stars mb-3">★★★★★</div>
                    <p class="text-gray-600 text-sm leading-relaxed mb-5 font-medium">"Lo que más valoro es que las categorías están bien medidas. No te toca rivales muy por encima ni muy por debajo. Cada partido es disputado."</p>
                    <div class="flex items-center gap-3 mt-auto pt-3 border-t border-gray-100">
                        <div class="w-11 h-11 rounded-full bg-epl-blue text-epl-gold flex items-center justify-center font-['Anton'] text-lg">JR</div>
                        <div>
                            <p class="font-bold text-epl-blue text-sm">Javiera Riquelme</p>
                            <p class="text-xs text-gray-500 font-medium">Liga 5ta Femenino · Temporada 2026</p>
                        </div>
                    </div>
                </article>

                <article class="testimonio-card">
                    <div class="testimonio-stars mb-3">★★★★★</div>
                    <p class="text-gray-600 text-sm leading-relaxed mb-5 font-medium">"La plataforma es lejos lo mejor: ranking actualizado al instante, historial, todo desde el celular. Se nota que es una liga profesional."</p>
                    <div class="flex items-center gap-3 mt-auto pt-3 border-t border-gray-100">
                        <div class="w-11 h-11 rounded-full bg-epl-blue text-epl-gold flex items-center justify-center font-['Anton'] text-lg">DA</div>
                        <div>
                            <p class="font-bold text-epl-blue text-sm">Diego Aravena</p>
                            <p class="text-xs text-gray-500 font-medium">Liga 3ra · Temporada 2026</p>
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
            <h2 class="text-3xl md:text-4xl text-epl-blue font-primary uppercase tracking-tight mb-10">Nuestros <span class="text-epl-gold">Partners</span></h2>

            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <div class="sponsor-card">
                    <div class="text-center">
                        <p class="font-['Anton'] text-2xl text-epl-blue">CONECTA</p>
                        <p class="text-[10px] font-bold tracking-widest text-gray-400 uppercase mt-1">Santa Blanca · Sede oficial</p>
                    </div>
                </div>
                <div class="sponsor-card">
                    <div class="text-center text-gray-400">
                        <p class="font-['Anton'] text-sm uppercase tracking-widest">Tu marca acá</p>
                        <p class="text-[10px] mt-2">Main Partner</p>
                    </div>
                </div>
                <div class="sponsor-card">
                    <div class="text-center text-gray-400">
                        <p class="font-['Anton'] text-sm uppercase tracking-widest">Tu marca acá</p>
                        <p class="text-[10px] mt-2">Official Partner</p>
                    </div>
                </div>
                <div class="sponsor-card">
                    <div class="text-center text-gray-400">
                        <p class="font-['Anton'] text-sm uppercase tracking-widest">Tu marca acá</p>
                        <p class="text-[10px] mt-2">Experience Partner</p>
                    </div>
                </div>
            </div>

            <p class="text-sm text-gray-500 font-medium mt-8 max-w-2xl mx-auto leading-relaxed">
                EPL es una plataforma para marcas que quieran conectar con una audiencia premium y comprometida.
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
                <h2 class="text-4xl md:text-5xl text-epl-blue font-primary uppercase tracking-tight">¿Tenés <span class="text-epl-gold">dudas?</span></h2>
            </div>

            <details class="faq-item">
                <summary class="faq-q">¿Cómo me inscribo a un torneo de Elite Padel League?</summary>
                <div class="faq-a">Creás tu ficha de jugador gratis en menos de 2 minutos, elegís tu categoría y te inscribís con tu pareja desde la sección Torneos. Recibís confirmación inmediata por email y notificación.</div>
            </details>
            <details class="faq-item">
                <summary class="faq-q">¿Cuánto cuesta participar?</summary>
                <div class="faq-a">El valor depende del torneo y la categoría. Cada inscripción incluye pack de bienvenida, agua, Tercer Tiempo y acceso completo a la plataforma con tu ranking actualizado en vivo.</div>
            </details>
            <details class="faq-item">
                <summary class="faq-q">¿Qué nivel necesito para jugar?</summary>
                <div class="faq-a">Hay categorías desde principiante hasta jugador avanzado (de 5ta a 1ra). Cuando creás tu ficha indicás tu nivel y nosotros nos preocupamos de que enfrentes parejas equivalentes.</div>
            </details>
            <details class="faq-item">
                <summary class="faq-q">¿Dónde se juegan los partidos?</summary>
                <div class="faq-a">Nuestra sede principal es <strong>Conecta Santa Blanca</strong>, en Santiago. Es nuestro club oficial y donde se vive cada fecha y cada Tercer Tiempo.</div>
            </details>
            <details class="faq-item">
                <summary class="faq-q">¿Cuándo son los torneos?</summary>
                <div class="faq-a">EPL construye temporadas anuales. Cada temporada incluye múltiples fechas mensuales con diferentes formatos: ligas regulares, americanos mixtos y eventos especiales. Mirá el calendario completo en la sección Torneos.</div>
            </details>
            <details class="faq-item">
                <summary class="faq-q">¿Puedo jugar si no tengo pareja?</summary>
                <div class="faq-a">Sí. Tenemos un sistema de búsqueda de pareja y formato americano donde podés inscribirte solo y te asignamos compañero/a por sorteo o por nivel.</div>
            </details>

            <!-- JSON-LD FAQ Schema -->
            <script type="application/ld+json">
            {
              "@context":"https://schema.org",
              "@type":"FAQPage",
              "mainEntity":[
                {"@type":"Question","name":"¿Cómo me inscribo a un torneo de Elite Padel League?","acceptedAnswer":{"@type":"Answer","text":"Creás tu ficha gratis en menos de 2 minutos, elegís categoría y te inscribís con tu pareja desde la sección Torneos."}},
                {"@type":"Question","name":"¿Cuánto cuesta participar?","acceptedAnswer":{"@type":"Answer","text":"Depende del torneo y la categoría. Incluye pack de bienvenida, Tercer Tiempo y acceso a plataforma."}},
                {"@type":"Question","name":"¿Qué nivel necesito para jugar?","acceptedAnswer":{"@type":"Answer","text":"Categorías desde principiante (5ta) hasta avanzado (1ra). Se asignan rivales del mismo nivel."}},
                {"@type":"Question","name":"¿Dónde se juegan los partidos?","acceptedAnswer":{"@type":"Answer","text":"Sede oficial: Conecta Santa Blanca, Santiago de Chile."}},
                {"@type":"Question","name":"¿Cuándo son los torneos?","acceptedAnswer":{"@type":"Answer","text":"Temporadas anuales con múltiples fechas mensuales: ligas, americanos y eventos especiales."}},
                {"@type":"Question","name":"¿Puedo jugar si no tengo pareja?","acceptedAnswer":{"@type":"Answer","text":"Sí, hay sistema de búsqueda de pareja y formato americano para inscribirse solo."}}
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
                    Nuestro club oficial en Santiago. Canchas de primer nivel, ambiente premium y todo lo que necesitás para vivir la experiencia EPL desde el primer partido hasta el último brindis.
                </p>
                <ul class="space-y-3 text-sm text-gray-700 mb-7">
                    <li class="flex gap-3 items-start">
                        <svg width="20" height="20" fill="none" stroke="#C9A762" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                        <span><strong>Conecta Santa Blanca</strong> — Santiago, Región Metropolitana</span>
                    </li>
                    <li class="flex gap-3 items-start">
                        <svg width="20" height="20" fill="none" stroke="#C9A762" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        <span>Fechas: viernes, sábados y domingos según temporada</span>
                    </li>
                    <li class="flex gap-3 items-start">
                        <svg width="20" height="20" fill="none" stroke="#C9A762" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                        <span>Estacionamiento, vestidores y cafetería</span>
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
                Creá tu ficha gratis en 2 minutos. Desbloqueá inscripciones prioritarias, llevá tus estadísticas al detalle,
                <strong class="text-epl-blue">recibí invitaciones a eventos exclusivos</strong> y viví la experiencia EPL completa.
            </p>

            <?php if (!epl_jugador_actual()): ?>
              <a href="<?= epl_url('registro.php') ?>" class="inline-flex items-center justify-center gap-3 bg-epl-blue text-white px-10 py-5 rounded-xl font-black uppercase text-[14px] tracking-widest hover:bg-epl-gold transition-all shadow-xl hover:-translate-y-1 relative z-10 no-underline">
                  Crear mi cuenta gratis
                  <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
              </a>
            <?php else: ?>
              <a href="<?= epl_url('dashboard.php') ?>" class="inline-flex items-center justify-center gap-3 bg-epl-blue text-white px-10 py-5 rounded-xl font-black uppercase text-[14px] tracking-widest hover:bg-epl-gold transition-all shadow-xl hover:-translate-y-1 relative z-10 no-underline">
                  Ir a mi dashboard →
              </a>
            <?php endif; ?>
        </div>
    </section>

</div>

<?php require_once 'includes/footer.php'; ?>
