<?php
$page_title       = 'Ligas y Americanos';
$active_nav       = 'torneos';
$page_css         = 'torneos-public';
$meta_description = 'Ligas de pádel de 10 fechas con ranking por liga y ranking individual EPL de 365 días, más torneos americanos en Santiago.';
$meta_keywords    = 'ligas padel santiago, ranking liga padel, ranking individual padel, liga padel 10 fechas, americano padel santiago, elite padel league';
require_once 'includes/functions.php';

$db = epl_db();
$temporada = (int)date('Y');
$ligas = $db->query("
    SELECT l.*, r.nombre AS recinto_nombre, rs.nombre AS recinto_superior_nombre
    FROM ligas l
    LEFT JOIN recintos r ON r.id = l.recinto_id
    LEFT JOIN recintos rs ON rs.id = r.superior_id
    ORDER BY FIELD(l.estado,'activa','inscripcion','proximamente','finalizada'), l.id DESC
")->fetchAll();
?>
<?php require_once 'includes/header.php'; ?>

<div class="epl-body-content antialiased">

    <!-- HERO SECTION TORNEOS -->
    <header class="tournaments-hero">
        <div class="tournaments-hero__outline" aria-hidden="true">10F</div>
        <div class="tournaments-hero__inner">
            <div class="tournaments-hero__copy">
                <span class="tournaments-hero__pill"><i></i> Ligas EPL + Americanos <?= $temporada ?></span>
                <p>Competencias por categoría · Santiago</p>
                <h1>Diez fechas.<br><span>Más pádel.</span></h1>
                <div class="tournaments-hero__lead">Compite con tu pareja durante 10 fechas, sube en el ranking de tu liga y construye también tu ranking individual EPL. Los americanos complementan la temporada.</div>
                <div class="tournaments-hero__actions">
                    <a href="#proximas-fechas">Ver ligas y americanos ↓</a>
                    <a href="#como-funciona">Conocer los formatos ↗</a>
                </div>
            </div>

            <aside class="tournaments-score-card" aria-label="Formatos de competencia Elite Padel League">
                <div class="tournaments-score-card__head"><span>Formato principal</span><b>Liga EPL</b></div>
                <div class="tournaments-score-card__row tournaments-score-card__row--first"><span>01 <small>Temporada</small></span><strong>10 <i>fechas</i></strong></div>
                <div class="tournaments-score-card__row"><span>02 <small>Programación</small></span><strong>1 <i>fixture</i></strong></div>
                <div class="tournaments-score-card__row"><span>03 <small>Parejas</small></span><strong>RL <i>por liga</i></strong></div>
                <div class="tournaments-score-card__row"><span>+ <small>Jugadores</small></span><strong>RI <i>365 días</i></strong></div>
                <p>Tu pareja compite por su liga y el ranking individual sigue tu recorrido EPL, incluso si cambias de compañero.</p>
            </aside>
        </div>
    </header>

    <section class="tournaments-road" id="como-funciona" aria-label="Cómo funcionan las ligas y americanos EPL">
        <div class="tournaments-road__inner">
            <div class="tournaments-road__head">
                <span>Temporada EPL <?= $temporada ?></span>
                <h2>Dos formatos. Una comunidad.</h2>
            </div>
            <div class="tournaments-road__steps">
                <article><b>01</b><span><strong>Elige tu liga</strong><small>Categoría y nivel definidos.</small></span></article>
                <article><b>02</b><span><strong>Juega 10 fechas</strong><small>Un partido por jornada.</small></span></article>
                <article><b>03</b><span><strong>Ranking por liga</strong><small>Tu pareja suma durante las 10 fechas.</small></span></article>
                <article class="tournaments-road__master"><b>+</b><span><strong>Americanos</strong><small>Competencias especiales de una jornada.</small></span></article>
            </div>
        </div>
    </section>

    <!-- ZONA 1: DIRECTORIO DE TORNEOS -->
    <section class="max-w-7xl mx-auto px-4 md:px-8 py-10 md:py-20" id="proximas-fechas">
        <div class="mb-8 md:mb-12 border-b-4 border-epl-gold/20 pb-4 flex flex-col md:flex-row md:items-end md:justify-between gap-4">
            <div>
                <span class="text-epl-gold font-black text-[10px] uppercase tracking-[0.2em] mb-2 block">Temporada <?= $temporada ?></span>
                <h2 class="text-4xl text-epl-blue font-primary uppercase tracking-tight">Ligas y <span class="text-epl-gold">Americanos</span></h2>
            </div>
            <p class="text-xs text-gray-500 font-medium"><?= count($ligas) ?> competencia<?= count($ligas)!==1?'s':'' ?> disponible<?= count($ligas)!==1?'s':'' ?></p>
        </div>

        <?php if (empty($ligas)): ?>
          <div class="text-center py-16 bg-white rounded-3xl border border-gray-100">
            <div class="text-6xl mb-4">🏆</div>
            <p class="text-gray-500 font-medium">No hay ligas ni americanos disponibles por ahora.</p>
            <p class="text-xs text-gray-400 mt-2">Vuelve pronto: estamos preparando las próximas competencias EPL.</p>
          </div>
        <?php else:
          // Conteos por estado y formato para filtros
          $cnt = ['activa'=>0, 'inscripcion'=>0, 'proximamente'=>0, 'finalizada'=>0];
          $cnt_tipo = ['liga'=>0, 'americano'=>0];
          foreach ($ligas as $l) {
              $cnt[$l['estado']] = ($cnt[$l['estado']] ?? 0) + 1;
              $cnt_tipo[$l['tipo'] === 'torneo' ? 'americano' : 'liga']++;
          }
        ?>
          <div class="flex flex-wrap gap-2 mb-3" id="tipoFilters" aria-label="Filtrar por formato">
            <button class="filter-chip active" data-filter="todos" onclick="filtrarTipo('todos',this)">Todas <?= count($ligas) ?></button>
            <?php if ($cnt_tipo['liga']>0): ?><button class="filter-chip" data-filter="liga" onclick="filtrarTipo('liga',this)">🏆 Ligas <?= $cnt_tipo['liga'] ?></button><?php endif; ?>
            <?php if ($cnt_tipo['americano']>0): ?><button class="filter-chip" data-filter="americano" onclick="filtrarTipo('americano',this)">🎾 Americanos <?= $cnt_tipo['americano'] ?></button><?php endif; ?>
          </div>
          <div class="flex flex-wrap gap-2 mb-8" id="torneoFilters" aria-label="Filtrar por estado">
            <button class="filter-chip active" data-filter="todos" onclick="filtrarTorneos('todos',this)">Cualquier estado</button>
            <?php if ($cnt['activa']>0): ?><button class="filter-chip" data-filter="activa" onclick="filtrarTorneos('activa',this)">🟢 En juego <?= $cnt['activa'] ?></button><?php endif; ?>
            <?php if ($cnt['inscripcion']>0): ?><button class="filter-chip" data-filter="inscripcion" onclick="filtrarTorneos('inscripcion',this)">📝 Inscripciones <?= $cnt['inscripcion'] ?></button><?php endif; ?>
            <?php if ($cnt['proximamente']>0): ?><button class="filter-chip" data-filter="proximamente" onclick="filtrarTorneos('proximamente',this)">⏳ Próximos <?= $cnt['proximamente'] ?></button><?php endif; ?>
            <?php if ($cnt['finalizada']>0): ?><button class="filter-chip" data-filter="finalizada" onclick="filtrarTorneos('finalizada',this)">🏁 Finalizados <?= $cnt['finalizada'] ?></button><?php endif; ?>
          </div>
        <?php endif; ?>
        <?php if (!empty($ligas)): ?>
          <?php
          $badge = [
              'proximamente' => ['label'=>'PRÓXIMAMENTE',  'text'=>'text-gray-800','bg'=>'bg-gray-200', 'eyebrow'=>'PRÓXIMA COMPETENCIA'],
              'inscripcion'  => ['label'=>'ABIERTAS',      'text'=>'text-white',   'bg'=>'bg-green-500', 'eyebrow'=>'INSCRIPCIONES DISPONIBLES'],
              'activa'       => ['label'=>'EN JUEGO',      'text'=>'text-epl-gold','bg'=>'bg-epl-blue',  'eyebrow'=>'COMPETENCIA ACTIVA'],
              'finalizada'   => ['label'=>'FINALIZADO',    'text'=>'text-gray-500','bg'=>'bg-gray-100',  'eyebrow'=>'COMPETENCIA FINALIZADA'],
          ];
          ?>
          <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8" id="torneosGrid">
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
              $nombre_corto = ($is_americano ? 'Americano' : 'Liga 10 fechas')
                  . ($l['categoria'] ? ' · ' . $l['categoria'] . 'ª categoría' : '');
            ?>
            <div class="torneo-card bg-white rounded-[24px] overflow-hidden shadow-[0_5px_15px_rgba(0,0,0,0.05)] border border-gray-100 flex flex-col hover:shadow-[0_10px_25px_rgba(201,167,98,0.15)] hover:border-epl-gold group" data-estado="<?= epl_h($l['estado']) ?>" data-tipo="<?= $is_americano ? 'americano' : 'liga' ?>">
              
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
                    <span class="text-sm font-semibold"><?= $is_americano ? 'Fecha: ' : 'Inicio: ' ?><?= $l['fecha_inicio'] ? date('d-m-Y', strtotime($l['fecha_inicio'])) : 'por confirmar' ?></span>
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
                        ASEGURAR MI CUPO
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

    <!-- ZONA 2: BENEFICIOS Y FORMATOS -->
    <main class="max-w-7xl mx-auto px-4 sm:px-8 py-10 md:py-24 border-t border-gray-200">
        <div class="mb-10 border-b-4 border-epl-gold/20 pb-4">
            <h2 class="text-4xl text-epl-blue font-primary uppercase tracking-tight">Compite y mide tu <span class="text-epl-gold">temporada</span></h2>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-4 gap-12">
            
            <!-- ÁREA PRINCIPAL (NUEVA ZONA DE BENEFICIOS) -->
            <div class="lg:col-span-3 order-2 lg:order-1">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    
                    <!-- Beneficio 1 -->
                    <div class="bg-white p-8 md:p-10 rounded-[30px] border border-gray-100 shadow-[0_5px_20px_rgba(0,0,0,0.03)] hover:border-epl-gold hover:shadow-xl transition-all group">
                        <div class="w-14 h-14 bg-epl-blue text-epl-gold rounded-2xl flex items-center justify-center text-3xl mb-6 group-hover:scale-110 transition-transform shadow-md">📊</div>
                        <h3 class="font-primary text-2xl text-epl-blue uppercase tracking-wide mb-3">Liga de 10 fechas</h3>
                        <p class="text-gray-500 text-sm font-medium leading-relaxed">Una temporada completa por categoría, con fixture, resultados y tabla de posiciones para seguir la competencia jornada a jornada.</p>
                    </div>

                    <!-- Beneficio 2 -->
                    <div class="bg-white p-8 md:p-10 rounded-[30px] border border-gray-100 shadow-[0_5px_20px_rgba(0,0,0,0.03)] hover:border-epl-gold hover:shadow-xl transition-all group">
                        <div class="w-14 h-14 bg-epl-blue text-epl-gold rounded-2xl flex items-center justify-center text-3xl mb-6 group-hover:scale-110 transition-transform shadow-md">🏆</div>
                        <h3 class="font-primary text-2xl text-epl-blue uppercase tracking-wide mb-3">Ranking por liga</h3>
                        <p class="text-gray-500 text-sm font-medium leading-relaxed">Cada competencia tiene su propia clasificación. Las parejas suman con sus resultados y avanzan durante las 10 fechas.</p>
                    </div>

                    <!-- Beneficio 3 -->
                    <div class="bg-white p-8 md:p-10 rounded-[30px] border border-gray-100 shadow-[0_5px_20px_rgba(0,0,0,0.03)] hover:border-epl-gold hover:shadow-xl transition-all group">
                        <div class="w-14 h-14 bg-epl-blue text-epl-gold rounded-2xl flex items-center justify-center text-3xl mb-6 group-hover:scale-110 transition-transform shadow-md">👤</div>
                        <h3 class="font-primary text-2xl text-epl-blue uppercase tracking-wide mb-3">Ranking individual EPL</h3>
                        <p class="text-gray-500 text-sm font-medium leading-relaxed">Reúne el rendimiento de cada jugador en ligas y americanos durante los últimos 365 días, independiente de la pareja.</p>
                    </div>

                    <!-- Beneficio 4 -->
                    <div class="bg-white p-8 md:p-10 rounded-[30px] border border-gray-100 shadow-[0_5px_20px_rgba(0,0,0,0.03)] hover:border-epl-gold hover:shadow-xl transition-all group">
                        <div class="w-14 h-14 bg-epl-blue text-epl-gold rounded-2xl flex items-center justify-center text-3xl mb-6 group-hover:scale-110 transition-transform shadow-md">🎾</div>
                        <h3 class="font-primary text-2xl text-epl-blue uppercase tracking-wide mb-3">Americanos EPL</h3>
                        <p class="text-gray-500 text-sm font-medium leading-relaxed">Competencias de una jornada para jugar varios partidos, conocer nuevos rivales y mantener el ritmo entre fechas de liga.</p>
                    </div>

                </div>
            </div>

            <!-- SIDEBAR -->
            <aside class="lg:col-span-1 order-1 lg:order-2">
                
                <!-- CAJA 1: FORMATOS -->
                <div class="sidebar-card">
                    <h4 class="font-primary text-2xl text-epl-blue mb-4 uppercase tracking-tight leading-none">Elige cómo <br><span class="text-epl-gold">quieres competir.</span></h4>
                    <p class="font-secondary text-xs text-gray-500 font-bold leading-relaxed mb-4">
                        La liga es el formato principal: tu pareja compite en su categoría y sube en un ranking propio durante 10 fechas.
                    </p>
                    <div class="bg-yellow-50 border-l-4 border-yellow-400 p-3 mb-6">
                        <p class="text-[10px] text-yellow-800 font-black uppercase tracking-wider">RANKING POR LIGA + RANKING INDIVIDUAL</p>
                        <p class="text-[10px] text-yellow-700 font-medium leading-tight mt-1">El ranking individual EPL convive con la clasificación de parejas de cada liga y considera los últimos 365 días.</p>
                    </div>
                    <a href="#proximas-fechas" class="block w-full text-center bg-epl-blue text-white py-4 rounded-xl font-secondary font-black text-[11px] uppercase tracking-[0.15em] hover:bg-epl-gold hover:text-epl-blue transition-all text-decoration-none shadow-md">
                        VER COMPETENCIAS
                    </a>
                </div>

                <!-- CAJA 2: MI PERFIL -->
                <div class="bg-epl-blue p-8 rounded-3xl text-white shadow-xl relative overflow-hidden">
                    <div class="absolute -top-10 -right-10 w-32 h-32 bg-epl-gold/10 rounded-full blur-xl pointer-events-none"></div>
                    <div class="relative z-10">
                        <h4 class="font-primary text-xl text-epl-gold mb-3 uppercase leading-none">Mi Perfil</h4>
                        <p class="font-secondary text-xs text-gray-300 leading-relaxed mb-6">
                            Revisa tus estadísticas individuales, tu porcentaje de victorias y el historial de tus enfrentamientos.
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

<script>
let filtroEstado = 'todos';
let filtroTipo = 'todos';

function aplicarFiltrosTorneos() {
    document.querySelectorAll('.torneo-card').forEach(card => {
        const coincideEstado = filtroEstado === 'todos' || card.dataset.estado === filtroEstado;
        const coincideTipo = filtroTipo === 'todos' || card.dataset.tipo === filtroTipo;
        card.style.display = coincideEstado && coincideTipo ? '' : 'none';
    });
}

function filtrarTorneos(estado, btn) {
    document.querySelectorAll('#torneoFilters .filter-chip').forEach(c => c.classList.remove('active'));
    btn.classList.add('active');
    filtroEstado = estado;
    aplicarFiltrosTorneos();
}

function filtrarTipo(tipo, btn) {
    document.querySelectorAll('#tipoFilters .filter-chip').forEach(c => c.classList.remove('active'));
    btn.classList.add('active');
    filtroTipo = tipo;
    aplicarFiltrosTorneos();
}
</script>

<?php require_once 'includes/footer.php'; ?>
