<?php
require_once 'includes/functions.php';

$db = epl_db();
$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: torneos.php'); exit; }

$stL = $db->prepare("SELECT * FROM ligas WHERE id=?");
$stL->execute([$id]);
$liga = $stL->fetch();
if (!$liga) { header('Location: torneos.php'); exit; }

$page_title = $liga['nombre'];
$active_nav = '';

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

<!-- Hero Torneo -->
<?php
$is_women = ($liga['sexo'] === 'femenino');
$is_americano = ($liga['tipo'] === 'torneo');

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
?>
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
      <div style="background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.15);border-radius:12px;padding:1.25rem 1.75rem;text-align:center;flex-shrink:0">
        <div style="font-size:.72rem;color:rgba(255,255,255,.5);text-transform:uppercase;letter-spacing:.1em;margin-bottom:.25rem">Inscripción</div>
        <div style="font-family:var(--font-head);font-size:2rem;color:var(--gold)"><?= number_format($liga['precio'],0,',','.') ?></div>
        <div style="font-size:.75rem;color:rgba(255,255,255,.4)">CLP por jugador</div>
      </div>
      <?php endif; ?>
    </div>

  </div>
</section>

<!-- Botones de Acción Superiores -->
<div class="container" style="margin-top: 2rem; margin-bottom: 2rem;">
  <div style="display: grid; grid-template-cols: 1fr; gap: 1rem; md:grid-template-cols: 3fr 1fr 1fr;" class="actions-grid">
    <a href="torneos.php" class="action-btn">
      <svg style="width:1.4rem;height:1.4rem" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
      Volver a Torneos
    </a>
    <a href="reprogramar.php" class="action-btn">
      <svg style="width:1.4rem;height:1.4rem; color:#ef4444" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
      Reprogramar Partido
    </a>
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
}
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
                 style="width:100%; padding:.8rem 1rem .8rem 2.5rem; border:1px solid #e5e7eb; border-radius:8px; font-family:var(--font-body); font-size:.9rem; color:var(--navy); outline:none; transition:border-color .2s"
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
            <p style="text-align:center; padding:3rem; color:var(--gray-400)">No hay partidos programados para este torneo.</p>
          <?php
          else:
            foreach($agrupados as $jor => $ps): 
            ?>
              <div class="jornada-group" data-jornada="<?= $jor ?>">
                <h3 style="font-family:var(--font-head); font-size:1rem; text-transform:uppercase; color:var(--navy); margin:2rem 0 1rem; border-left:4px solid var(--gold); padding-left:.75rem">Fecha <?= $jor ?: 'Especial' ?></h3>
                <div class="partidos-list">
                  <?php foreach($ps as $p): 
                    $is_jugado = ($p['estado'] === 'jugado');
                    $search_str = strtolower($p['local_nombre'] . ' ' . $p['visitante_nombre']);
                  ?>
                  <div class="partido-card-v2" data-search="<?= $search_str ?>">
                    <!-- Columna 1: Info Fecha -->
                    <div class="partido-col-info">
                      <span class="fecha-label">Fecha <?= $jor ?></span>
                      <div class="partido-date">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
                        <?= $p['fecha_programada'] ? date('d M, Y', strtotime($p['fecha_programada'])) : 'TBD' ?>
                      </div>
                      <span class="partido-time">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
                        <?= $p['fecha_programada'] ? date('H:i', strtotime($p['fecha_programada'])) : '00:00' ?>
                      </span>
                    </div>

                    <!-- Columna 2: Equipos y Marcador -->
                    <div style="display:flex; align-items:center; justify-content:center; gap:2rem; flex:1">
                      <div style="text-align:right; flex:1">
                        <span class="equipo-nombre-card"><?= epl_h($p['local_nombre']) ?></span>
                      </div>
                      
                      <div style="display:flex; flex-direction:column; align-items:center">
                        <div class="marcador-box">
                          <?php if($is_jugado): ?>
                            <?= $p['sets_local'] ?> - <?= $p['sets_visitante'] ?>
                          <?php else: ?>
                            VS
                          <?php endif; ?>
                        </div>
                        <?php if($is_jugado): 
                          $sets=[];
                          for($s=1;$s<=3;$s++){$gl=$p["games_s{$s}_local"];$gv=$p["games_s{$s}_visitante"];if($gl!==null)$sets[]="$gl-$gv";}
                        ?>
                          <div class="set-details"><?= implode(' <span style="opacity:0.4; margin:0 2px">/</span> ', $sets) ?></div>
                        <?php endif; ?>
                      </div>

                      <div style="text-align:left; flex:1">
                        <span class="equipo-nombre-card"><?= epl_h($p['visitante_nombre']) ?></span>
                      </div>
                    </div>

                    <!-- Columna 3: Meta (Cancha/Sede) -->
                    <div class="partido-col-meta">
                      <?php 
                        $r_nombre = $p['recinto_nombre'];
                        $r_sup    = $p['recinto_superior_nombre'];
                        $r_abu    = $p['recinto_abuelo_nombre'];
                        $cancha   = $p['cancha'];
                        $liga_sede= $liga['sede'] ?? '';

                        // Lógica de jerarquía:
                        // Si hay abuelo (Club -> Sede -> Cancha)
                        if ($r_abu) {
                          $badge_txt = $r_sup; // Sede/Sucursal
                          $label_txt = $r_abu . ($r_nombre ? ' - ' . $r_nombre : ''); // Club - Cancha
                        } 
                        // Si hay superior (Club -> Sede/Cancha)
                        elseif ($r_sup) {
                          $badge_txt = $r_nombre; // Sede/Cancha
                          $label_txt = $r_sup;    // Club
                        }
                        // Si no hay jerarquía en recintos, usamos la sede de la liga
                        else {
                          $badge_txt = $r_nombre ?: ($liga_sede ?: 'Sede TBD');
                          $label_txt = ($r_nombre && $liga_sede && strtolower($r_nombre) !== strtolower($liga_sede)) ? $liga_sede : '';
                        }

                        if ($cancha && !str_contains(strtolower($label_txt), 'cancha')) {
                          $label_txt .= ($label_txt ? ' - ' : '') . 'Cancha ' . $cancha;
                        }
                      ?>
                      <span class="cancha-badge" style="max-width:180px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap">
                        <?= epl_h($badge_txt) ?>
                      </span>
                      <div class="sede-label">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path><circle cx="12" cy="10" r="3"></circle></svg>
                        <?= epl_h($label_txt ?: 'Ver ubicación') ?>
                      </div>
                    </div>
                  </div>
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
      <div style="background:#fff; border-radius:24px; padding:3rem; box-shadow:0 10px 40px rgba(0,0,0,0.03); border:1px solid rgba(0,0,0,0.05)">
        <h2 class="section-title">Información del Torneo</h2>
        
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
    const q = document.getElementById('searchPartidos').value.toLowerCase();
    document.querySelectorAll('.partido-card').forEach(card => {
        const text = card.getAttribute('data-search');
        card.style.display = text.includes(q) ? '' : 'none';
    });
}
</script>

<?php require_once 'includes/footer.php'; ?>
