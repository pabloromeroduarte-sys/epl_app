<?php
require_once 'includes/functions.php';

$db = epl_db();
$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: torneos.php'); exit; }

$stL = $db->prepare("SELECT * FROM ligas WHERE id=?");
$stL->execute([$id]);
$liga = $stL->fetch();
if (!$liga) { header('Location: torneos.php'); exit; }

$is_women = ($liga['sexo'] === 'femenino');
$is_americano = ($liga['tipo'] === 'torneo');
$tipo_competencia = $is_americano ? 'Americano' : 'Liga de 10 fechas';

if ($liga['foto_portada']) {
    $portada_hero_url = epl_url('uploads/ligas/'.$liga['foto_portada']);
} else {
    if ($is_americano && $is_women) {
        $portada_hero_url = epl_url('assets/img/portada-americano-women.png');
    } elseif ($is_americano && !$is_women) {
        $portada_hero_url = epl_url('assets/img/portada-americano-men.png');
    } elseif (!$is_americano && $is_women) {
        $portada_hero_url = epl_url('assets/img/portada-liga-women.png');
    } else {
        $portada_hero_url = epl_url('assets/img/portada-liga-men.png');
    }
}

$page_title = $liga['nombre'] . ' — ' . $tipo_competencia;
$active_nav = 'torneos';

// ── SEO: meta tags específicos por torneo ────────────────────────────────────
$_cat_label = $liga['categoria'] ? $liga['categoria'].'ª Categoría' : '';
$_sex_label = !empty($liga['sexo']) ? ucfirst($liga['sexo']) : '';
$_estado_lbl = match($liga['estado'] ?? '') {
    'inscripcion'  => 'Inscripciones abiertas',
    'activa'       => 'En juego',
    'proximamente' => 'Próximamente',
    'finalizada'   => 'Finalizado',
    default        => $tipo_competencia,
};
$meta_description = trim("{$_estado_lbl}: ".$liga['nombre'].". "
                  . ($_cat_label ? "$_cat_label · " : '')
                  . ($_sex_label ? "$_sex_label · " : '')
                  . "$tipo_competencia de pádel organizado por Elite Padel League en Santiago. Revisa clasificación, fixture y resultados en línea.");
$meta_keywords    = $liga['nombre'].", liga padel santiago, americano padel, ".strtolower($_cat_label).", padel ".strtolower($_sex_label).", EPL";

$clasificacion = epl_clasificacion($liga['id']);
$partidos_jugados  = epl_partidos_liga($liga['id'], 'jugado');
$partidos_pendientes = epl_partidos_liga($liga['id'], 'pendiente');

// Agrupar pendientes por jornada
$por_jornada = [];
foreach ($partidos_pendientes as $p) {
    $j = $p['jornada'] ?? 0;
    $por_jornada[$j][] = $p;
}
ksort($por_jornada);

$badge_estado = [
    'proximamente' => ['label'=>'Próximamente',  'color'=>'#6366F1','bg'=>'#EDE9FE'],
    'inscripcion'  => ['label'=>'Inscripciones', 'color'=>'#D97706','bg'=>'#FEF3C7'],
    'activa'       => ['label'=>'En juego',      'color'=>'#065F46','bg'=>'#D1FAE5'],
    'finalizada'   => ['label'=>'Finalizado',    'color'=>'#374151','bg'=>'#F3F4F6'],
];
$est = $badge_estado[$liga['estado']] ?? $badge_estado['activa'];
?>
<?php require_once 'includes/header.php'; ?>

<!-- Schema.org SportsEvent — Rich Results en Google -->
<?php
$_proto = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$_host  = $_SERVER['HTTP_HOST'] ?? 'epleague.cl';
$_event_status = match($liga['estado'] ?? '') {
    'finalizada' => 'https://schema.org/EventCompleted',
    default      => 'https://schema.org/EventScheduled',
};
$_event_schema = [
    '@context' => 'https://schema.org',
    '@type'    => 'SportsEvent',
    'name'     => $liga['nombre'],
    'description' => $meta_description,
    'sport'    => 'Padel',
    'eventStatus' => $_event_status,
    'eventAttendanceMode' => 'https://schema.org/OfflineEventAttendanceMode',
    'organizer' => [
        '@type' => 'SportsOrganization',
        'name'  => 'Elite Padel League',
        'url'   => $_proto . '://' . $_host,
    ],
    'location' => [
        '@type' => 'Place',
        'name'  => 'Conecta Santa Blanca',
        'address' => [
            '@type' => 'PostalAddress',
            'addressLocality' => 'Santiago',
            'addressRegion'   => 'Región Metropolitana',
            'addressCountry'  => 'CL',
        ],
    ],
    'image' => $portada_hero_url,
    'url'   => $_proto . '://' . $_host . '/torneo.php?id=' . (int)$liga['id'],
];
if (!empty($liga['fecha_inicio'])) {
    $_event_schema['startDate'] = date('c', strtotime($liga['fecha_inicio']));
}
if (!empty($liga['fecha_fin'])) {
    $_event_schema['endDate'] = date('c', strtotime($liga['fecha_fin']));
}
?>
<script type="application/ld+json"><?= json_encode($_event_schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?></script>

<!-- Hero de liga o americano -->
<section style="background:var(--navy);position:relative;overflow:hidden">
    <div style="position:absolute;inset:0;background-image:url('<?= $portada_hero_url ?>');background-size:cover;background-position:center;opacity:.2"></div>
  <div class="container" style="position:relative;z-index:1;padding-top:3rem;padding-bottom:3rem">
    <div style="display:flex;align-items:flex-start;justify-content:space-between;flex-wrap:wrap;gap:1.5rem">
      <div>
        <span style="display:inline-block;padding:.3rem .9rem;border-radius:50px;font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.1em;background:<?= $est['bg'] ?>;color:<?= $est['color'] ?>;margin-bottom:.85rem">
          <?= $est['label'] ?>
        </span>
        <h1 style="font-family:var(--font-head);font-size:clamp(1.8rem,4vw,3rem);text-transform:uppercase;color:#fff;line-height:1.1;margin-bottom:.5rem">
          <?= epl_h($liga['nombre']) ?>
        </h1>
        <?php
        $fmt_label  = [
            'liga_regular'=>'Liga regular',
            'liga_playoff'=>'Liga + Playoff',
            'mata_mata'=>'Llaves',
            'grupos_mata_mata'=>'Fase de grupos + Llaves'
        ];
        ?>
        <?php if ($liga['categoria'] || $liga['sexo']): ?>
          <p style="color:var(--gold);font-size:.9rem;font-weight:600;text-transform:uppercase;letter-spacing:0.05em">
            <?= $liga['categoria'] ? $liga['categoria'] . 'ª Categoría' : '' ?>
            <span style="margin: 0 8px; opacity: 0.5;">|</span>
            <span style="color:#fff"><?= ucfirst($liga['sexo']) ?></span>
            <span style="margin: 0 8px; opacity: 0.5;">|</span>
            <span style="color:#fff"><?= $liga['tipo'] === 'liga' ? 'Liga' : 'Americano' ?></span>
            <span style="margin: 0 8px; opacity: 0.5;">|</span>
            <span style="color:rgba(255,255,255,0.7); font-size: 0.8rem;"><?= $fmt_label[$liga['formato']] ?? '' ?></span>
          </p>
        <?php endif; ?>
        <?php if ($liga['sede']): ?>
          <p style="color:rgba(255,255,255,.6);font-size:.85rem;margin-top:.5rem">
            📍 <?= epl_h($liga['sede']) ?>
            <?php if ($liga['url_maps']): ?>
              <a href="<?= epl_h($liga['url_maps']) ?>" target="_blank" rel="noopener" style="color:var(--gold);margin-left:.4rem">Ver mapa →</a>
            <?php endif; ?>
          </p>
        <?php endif; ?>
        <?php if ($liga['fecha_inicio'] || $liga['fecha_fin']): ?>
          <p style="color:rgba(255,255,255,.5);font-size:.8rem;margin-top:.35rem">
            📅 <?= $liga['fecha_inicio']?date('d/m/Y',strtotime($liga['fecha_inicio'])):'?' ?>
            <?= $liga['fecha_fin']?' — '.date('d/m/Y',strtotime($liga['fecha_fin'])):'' ?>
          </p>
        <?php endif; ?>
      </div>
      <?php if ($liga['precio']): ?>
      <?php
        $pct_t = epl_liga_mp_comision_pct($liga);
        $est_liq = $liga['precio'] > 0 ? epl_precio_neto_estimado_desde_bruto((float) $liga['precio'], $pct_t) : 0;
      ?>
      <div style="background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.15);border-radius:12px;padding:1.25rem 1.75rem;text-align:center;flex-shrink:0">
        <div style="font-size:.72rem;color:rgba(255,255,255,.5);text-transform:uppercase;letter-spacing:.1em;margin-bottom:.25rem">Inscripción</div>
        <div style="font-family:var(--font-head);font-size:2rem;color:var(--gold)"><?= number_format($liga['precio'],0,',','.') ?></div>
        <div style="font-size:.75rem;color:rgba(255,255,255,.4)">CLP por jugador · pago en línea</div>
        <?php if ($liga['precio'] > 0): ?>
        <div style="font-size:.68rem;color:rgba(255,255,255,.45);margin-top:.4rem;line-height:1.35">
          Comisión referencial MP <?= rtrim(rtrim(number_format($pct_t, 2, ',', ''), '0'), ',') ?>% → líquido estimado ~$<?= number_format($est_liq, 0, ',', '.') ?></div>
        <?php endif; ?>
      </div>
      <?php endif; ?>
    </div>

  </div>
</section>

<!-- Banner de Inscripción (solo cuando está abierta) -->
<?php if ($liga['estado'] === 'inscripcion'): ?>
<?php
require_once 'includes/auth.php';
$jugador_sesion = epl_jugador_actual();
$ya_inscrito    = false;
if ($jugador_sesion) {
    $chk = $db->prepare("SELECT id FROM inscripciones WHERE jugador_id=? AND liga_id=? AND estado != 'rechazada'");
    $chk->execute([$jugador_sesion['id'], $liga['id']]);
    $ya_inscrito = (bool)$chk->fetch();
}
?>
<div style="background:linear-gradient(135deg,#1C2F48 0%,#0f1e30 100%);padding:1.5rem 0;border-bottom:3px solid var(--gold)">
  <div class="container" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:1rem">
    <div>
      <div style="color:var(--gold);font-size:.75rem;font-weight:700;text-transform:uppercase;letter-spacing:.1em;margin-bottom:.25rem">🎾 Inscripciones abiertas</div>
      <div style="color:#fff;font-weight:700;font-size:1rem">
        <?php if ($liga['precio'] > 0): ?>
          Inscripción: <span style="color:var(--gold)">$<?= number_format($liga['precio'],0,',','.') ?> CLP</span> por jugador
        <?php else: ?>
          <span style="color:#4ade80">¡Inscripción gratuita!</span>
        <?php endif; ?>
      </div>
      <?php if ($liga['inscripcion_fin']): ?>
        <div style="color:rgba(255,255,255,.5);font-size:.78rem;margin-top:.2rem">Cierre: <?= date('d/m/Y', strtotime($liga['inscripcion_fin'])) ?></div>
      <?php endif; ?>
    </div>
    <div>
      <?php if (!$jugador_sesion): ?>
        <a href="<?= epl_url('login.php?back='.urlencode('/elitepadelleague/torneo.php?id='.$liga['id'])) ?>"
           style="display:inline-block;background:var(--gold);color:var(--navy);font-weight:800;font-size:.9rem;text-transform:uppercase;padding:.8rem 2rem;border-radius:10px;text-decoration:none;letter-spacing:.05em">
          Iniciar sesión para inscribirse →
        </a>
      <?php elseif ($ya_inscrito): ?>
        <span style="background:#4ade80;color:#14532d;font-weight:800;font-size:.85rem;text-transform:uppercase;padding:.7rem 1.5rem;border-radius:10px;display:inline-block">
          ✓ Ya estás inscrito
        </span>
      <?php else: ?>
        <a href="<?= epl_url('inscribirse.php?liga='.$liga['id']) ?>"
           style="display:inline-block;background:var(--gold);color:var(--navy);font-weight:800;font-size:.9rem;text-transform:uppercase;padding:.8rem 2rem;border-radius:10px;text-decoration:none;letter-spacing:.05em;box-shadow:0 4px 20px rgba(201,167,98,.4)">
          Inscribirme ahora →
        </a>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php endif; ?>

<!-- Botones de Acción Superiores -->
<div class="container" style="margin-top: 2rem; margin-bottom: 2rem;">
  <div style="display: grid; grid-template-cols: 1fr; gap: 1rem; md:grid-template-cols: 3fr 1fr 1fr;" class="actions-grid">
    <a href="torneos.php" class="action-btn">
      <svg style="width:1.4rem;height:1.4rem" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
      Volver a Ligas y torneos
    </a>
    <?php if (in_array($liga['estado'], ['activa','inscripcion'])): ?>
    <a href="reprogramar.php" class="action-btn">
      <svg style="width:1.4rem;height:1.4rem; color:#ef4444" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
      Reprogramar Partido
    </a>
    <?php endif; ?>
    <a href="#" class="action-btn">
      <svg style="width:1.4rem;height:1.4rem; color:var(--gold)" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16H9m3 0h3"></path></svg>
      Reglamento Oficial
    </a>
  </div>
</div>

<style>
.actions-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; }
.action-btn {
  background: #fff;
  border-radius: 12px;
  padding: 1.25rem;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: .75rem;
  font-family: var(--font-body);
  font-weight: 800;
  font-size: .8rem;
  text-transform: uppercase;
  letter-spacing: .05em;
  color: var(--navy);
  box-shadow: 0 2px 8px rgba(0,0,0,0.02);
  transition: transform .2s, border-color .2s;
  text-decoration: none;
  border: 1px solid var(--gray-200);
}
.action-btn:hover { transform: translateY(-3px); box-shadow: 0 8px 25px rgba(201,167,98,0.2); border-color: var(--gold); }

/* Tabs Estilo Premium */
.tabs-container {
  background: #fff;
  border-radius: 16px;
  padding: .5rem;
  display: flex;
  gap: .25rem;
  box-shadow: 0 4px 20px rgba(0,0,0,0.05);
  margin-bottom: 2rem;
  overflow-x: auto;
  scrollbar-width: none; /* Firefox */
}
.tabs-container::-webkit-scrollbar { display: none; /* Safari and Chrome */ }
.tab-link {
  flex: 1;
  text-align: center;
  padding: 1rem;
  border-radius: 12px;
  font-family: var(--font-body);
  font-size: .85rem;
  font-weight: 800;
  text-transform: uppercase;
  letter-spacing: .05em;
  color: var(--navy);
  transition: all .3s;
  text-decoration: none;
  cursor: pointer;
}
.tab-link.active {
  background: var(--navy);
  color: var(--gold);
}
.tab-content { display: none; }
.tab-content.active { display: block; }

@media (max-width: 640px) {
  .torneo-info-card { padding: 1.5rem 1rem !important; }
  .tab-link { padding: .75rem .5rem; font-size: .72rem; }
}
</style>

<!-- Navegación de Pestañas -->
<div class="container">
  <div class="tabs-container">
    <a onclick="showTab('ranking')" id="btn-ranking" class="tab-link active">Ranking</a>
    <a onclick="showTab('partidos')" id="btn-partidos" class="tab-link">Partidos</a>
    <a onclick="showTab('informacion')" id="btn-informacion" class="tab-link">Información</a>
  </div>
</div>

<div id="tab-ranking" class="tab-content active">
  <div class="container">
    <p class="section-eyebrow">Tabla de posiciones</p>
    <h2 class="section-title premium-title">Clasificación</h2>
    <?php if ($clasificacion): ?>
    <div class="tabla-clasificacion">
      <table>
        <thead>
          <tr>
            <th style="text-align:left;padding-left:1.25rem">#</th>
            <th style="text-align:left">Equipo</th>
            <th>PJ</th><th>PG</th><th>PP</th>
            <th class="hide-mobile">GF</th>
            <th class="hide-mobile">GC</th>
            <th class="hide-mobile">Dif.</th>
            <th>Pts</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($clasificacion as $i => $row): ?>
          <tr>
            <td style="padding-left:1.25rem">
              <span class="posicion-num <?= ['pos-1','pos-2','pos-3'][$i] ?? 'pos-n' ?>"><?= $i+1 ?></span>
            </td>
            <td>
              <div class="equipo-cell">
                <div class="equipo-avatars">
                  <img class="equipo-avatar" src="<?= epl_h(epl_foto_jugador($row['j1_foto'], $row['j1_nombre'].' '.$row['j1_apellido'])) ?>" alt="">
                  <img class="equipo-avatar" src="<?= epl_h(epl_foto_jugador($row['j2_foto'], $row['j2_nombre'].' '.$row['j2_apellido'])) ?>" alt="">
                </div>
                <span class="equipo-nombre"><?= epl_h($row['equipo_nombre']) ?></span>
              </div>
            </td>
            <td><?= $row['pj'] ?></td>
            <td><?= $row['pg'] ?></td>
            <td><?= $row['pp'] ?></td>
            <td class="hide-mobile"><?= $row['games_favor'] ?></td>
            <td class="hide-mobile"><?= $row['games_contra'] ?></td>
            <td class="hide-mobile" style="color:<?= $row['games_favor']-$row['games_contra']>=0?'#22c55e':'#ef4444' ?>">
              <?= ($row['games_favor']-$row['games_contra'] >= 0 ? '+' : '') . ($row['games_favor']-$row['games_contra']) ?>
            </td>
            <td><strong style="color:var(--navy)"><?= $row['puntos'] ?></strong></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <p style="font-size:.72rem;color:var(--gray-400);margin-top:.75rem">Ordenado por: Puntos → Diferencia de games → Partidos ganados</p>
    <?php else: ?>
      <p style="color:var(--gray-400)">La clasificación se actualizará cuando comiencen los partidos.</p>
    <?php endif; ?>
  </div>
</section>

</div> <!-- /tab-ranking -->

<div id="tab-partidos" class="tab-content">
  <section class="section">
    <div class="container">
      <div style="background:#fff; border-radius:24px; padding:2rem; box-shadow:0 10px 40px rgba(0,0,0,0.03); border:1px solid rgba(0,0,0,0.05)">
        <h2 style="font-family:var(--font-head); font-size:clamp(1.8rem, 3vw, 2.5rem); text-transform:uppercase; color:var(--navy); margin-bottom:1.5rem; letter-spacing:.02em; font-weight:400; display:flex; align-items:center; gap:.5rem">
          <span style="display:inline-block; width:4px; height:1em; background:var(--gold); border-radius:4px"></span>
          CALENDARIO DE PARTIDOS
        </h2>
        
        <!-- Buscador -->
        <div style="position:relative; margin-bottom:1.5rem">
          <input type="text" id="searchPartidos" placeholder="Buscar por apellido de jugador o equipo..."
                 style="width:100%; padding:.8rem 1rem .8rem 2.5rem; border:1px solid #e5e7eb; border-radius:8px; font-family:var(--font-body); font-size:1rem; color:var(--navy); outline:none; transition:border-color .2s"
                 onfocus="this.style.borderColor='var(--gold)'" onblur="this.style.borderColor='#e5e7eb'"
                 onkeyup="filterPartidos()">
          <svg style="position:absolute; left:.8rem; top:50%; transform:translateY(-50%); width:1.1rem; height:1.1rem; color:#9ca3af" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
        </div>

        <!-- Selector de Jornadas -->
        <?php 
        $todas_jornadas = [];
        $partidos_all = array_merge($partidos_pendientes, $partidos_jugados);
        foreach($partidos_all as $p) { if(isset($p['jornada']) && $p['jornada']) $todas_jornadas[] = $p['jornada']; }
        $todas_jornadas = array_unique($todas_jornadas);
        sort($todas_jornadas);
        ?>
        <div class="fechas-scroll">
          <button class="fecha-btn active" onclick="filterByJornada('all', this)">Todas</button>
          <?php foreach($todas_jornadas as $j): ?>
            <button class="fecha-btn" onclick="filterByJornada(<?= $j ?>, this)">Fecha <?= $j ?></button>
          <?php endforeach; ?>
        </div>

        <!-- Lista de Partidos Agrupados -->
        <div id="lista-partidos-dinamica">
          <?php 
          // Agrupar todos los partidos por jornada para la vista dinámica
          $agrupados = [];
          foreach($partidos_all as $p) { $agrupados[$p['jornada'] ?? 0][] = $p; }
          ksort($agrupados);
          
          if(empty($partidos_all)):
          ?>
            <p style="text-align:center; padding:3rem; color:var(--gray-400)">No hay partidos programados para esta competencia.</p>
          <?php
          else:
            foreach($agrupados as $jor => $ps): 
            ?>
              <div class="jornada-group" data-jornada="<?= $jor ?>">
                <h3 style="font-family:var(--font-head); font-size:1rem; text-transform:uppercase; color:var(--navy); margin:2rem 0 1rem; border-left:4px solid var(--gold); padding-left:.75rem">Fecha <?= $jor ?: 'Especial' ?></h3>
                <div class="partidos-list">
                  <?php foreach($ps as $p): ?>
                    <?php include 'includes/partido_card_v2.php'; ?>
                  <?php endforeach; ?>
                </div>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </section>
</div> <!-- /tab-partidos -->

<div id="tab-informacion" class="tab-content">
  <section class="section">
    <div class="container">
      <div class="torneo-info-card" style="background:#fff; border-radius:24px; padding:3rem; box-shadow:0 10px 40px rgba(0,0,0,0.03); border:1px solid rgba(0,0,0,0.05)">
        <h2 class="section-title">Información: <?= epl_h($tipo_competencia) ?></h2>
        
        <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(250px, 1fr)); gap:2rem; margin-top:2rem">
          <div>
            <h4 style="font-family:var(--font-head); text-transform:uppercase; color:var(--navy); font-size:.9rem; margin-bottom:1rem">Detalles Técnicos</h4>
            <ul style="list-style:none; padding:0; font-size:.9rem; color:var(--gray-600); line-height:2.5">
              <li><span style="opacity:.6">Sede:</span> <strong><?= $liga['sede'] ?: 'Por confirmar' ?></strong></li>
              <li><span style="opacity:.6">Categoría:</span> <strong><?= $liga['categoria'] ?>ª Categoría</strong></li>
              <li><span style="opacity:.6">Sexo:</span> <strong><?= ucfirst($liga['sexo']) ?></strong></li>
              <li><span style="opacity:.6">Precio:</span> <strong>$<?= number_format($liga['precio']??0,0,',','.') ?></strong></li>
            </ul>
          </div>
          <div>
            <h4 style="font-family:var(--font-head); text-transform:uppercase; color:var(--navy); font-size:.9rem; margin-bottom:1rem">Fechas Clave</h4>
            <ul style="list-style:none; padding:0; font-size:.9rem; color:var(--gray-600); line-height:2.5">
              <li><span style="opacity:.6">Inicio:</span> <strong><?= $liga['fecha_inicio'] ? date('d/m/Y', strtotime($liga['fecha_inicio'])) : 'Pendiente' ?></strong></li>
              <li><span style="opacity:.6">Cierre Inscrip:</span> <strong><?= $liga['inscripcion_fin'] ? date('d/m/Y', strtotime($liga['inscripcion_fin'])) : 'Pendiente' ?></strong></li>
            </ul>
          </div>
        </div>
      </div>
    </div>
  </section>
</div> <!-- /tab-informacion -->

<script>
function showTab(tabName) {
  document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
  document.querySelectorAll('.tab-link').forEach(l => l.classList.remove('active'));
  document.getElementById('tab-' + tabName).classList.add('active');
  document.getElementById('btn-' + tabName).classList.add('active');
}

function filterByJornada(jor, btn) {
    document.querySelectorAll('.fecha-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    document.querySelectorAll('.jornada-group').forEach(group => {
        if (jor === 'all' || group.getAttribute('data-jornada') == jor) group.style.display = '';
        else group.style.display = 'none';
    });
}

function filterPartidos() {
    const q = document.getElementById('searchPartidos').value.toLowerCase().trim();
    document.querySelectorAll('.partido-card-v2').forEach(card => {
        const text = (card.getAttribute('data-search') || '').toLowerCase();
        card.style.display = (!q || text.includes(q)) ? '' : 'none';
    });
    // Ocultar grupos de jornada que quedaron sin partidos visibles
    document.querySelectorAll('.jornada-group').forEach(group => {
        const visible = group.querySelectorAll('.partido-card-v2:not([style*="display: none"])').length;
        group.style.display = visible > 0 ? '' : 'none';
    });
}
</script>

<?php require_once 'includes/footer.php'; ?>
