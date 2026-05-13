<?php
$page_title = 'Resultados';
$active_nav = 'resultados';
require_once 'includes/functions.php';

$db    = epl_db();
$ligas = $db->query("SELECT * FROM ligas ORDER BY id DESC")->fetchAll();
$liga_id = isset($_GET['liga']) ? (int)$_GET['liga'] : ($ligas[0]['id'] ?? 0);
$estado  = $_GET['estado'] ?? '';
$estados_validos = ['','jugado','pendiente','reprogramado','walkover'];
if (!in_array($estado, $estados_validos)) $estado = '';

$partidos = $liga_id ? epl_partidos_liga($liga_id, $estado) : [];

// Agrupar por jornada
$por_jornada = [];
foreach ($partidos as $p) {
    $j = $p['jornada'] ?? 0;
    $por_jornada[$j][] = $p;
}
krsort($por_jornada);
?>
<?php require_once 'includes/header.php'; ?>

<section class="section-sm" style="background:var(--navy)">
  <div class="container">
    <p class="section-eyebrow" style="color:var(--gold)">Liga activa</p>
    <h1 class="section-title" style="color:var(--white)">Resultados</h1>
  </div>
</section>

<section class="section">
  <div class="container">

    <!-- Filtros -->
    <form method="get" class="flex gap-2 mb-4" style="flex-wrap:wrap;align-items:center">
      <?php if (count($ligas) > 1): ?>
      <select name="liga" class="form-control" style="width:auto" onchange="this.form.submit()">
        <?php foreach ($ligas as $l): ?>
          <option value="<?= $l['id'] ?>" <?= $l['id']==$liga_id?'selected':'' ?>><?= epl_h($l['nombre']) ?></option>
        <?php endforeach; ?>
      </select>
      <?php else: ?>
        <input type="hidden" name="liga" value="<?= $liga_id ?>">
      <?php endif; ?>

      <select name="estado" class="form-control" style="width:auto" onchange="this.form.submit()">
        <option value="">Todos</option>
        <option value="jugado"      <?= $estado==='jugado'?'selected':'' ?>>Jugados</option>
        <option value="pendiente"   <?= $estado==='pendiente'?'selected':'' ?>>Pendientes</option>
        <option value="reprogramado"<?= $estado==='reprogramado'?'selected':'' ?>>Reprogramados</option>
        <option value="walkover"    <?= $estado==='walkover'?'selected':'' ?>>Walkover</option>
      </select>
    </form>

    <?php if (empty($por_jornada)): ?>
      <div class="card card-body text-center" style="padding:3rem">
        <p style="color:var(--gray-400)">No hay partidos para mostrar.</p>
      </div>
    <?php else: ?>
      <?php foreach ($por_jornada as $jornada => $ps): ?>
        <?php if ($jornada): ?>
        <h3 style="font-family:var(--font-head);font-size:1.1rem;text-transform:uppercase;color:var(--navy);margin-bottom:.75rem;margin-top:2rem;letter-spacing:.08em">
          Jornada <?= $jornada ?>
        </h3>
        <?php endif; ?>
        <div class="partidos-list mb-3">
          <?php foreach ($ps as $p): 
            $is_jugado = ($p['estado'] === 'jugado');
          ?>
          <div class="partido-card-v2">
            <!-- Columna 1: Info Fecha -->
            <div class="partido-col-info">
              <span class="fecha-label">Fecha <?= $jornada ?></span>
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

                // Intentar sacar la sede de la liga desde el array global si no hay jerarquía en recintos
                $liga_sede = '';
                foreach($ligas as $l) { if($l['id'] == $liga_id) { $liga_sede = $l['sede']; break; } }

                if ($r_abu) {
                  $badge_txt = $r_sup;
                  $label_txt = $r_abu . ($r_nombre ? ' - ' . $r_nombre : '');
                } elseif ($r_sup) {
                  $badge_txt = $r_nombre;
                  $label_txt = $r_sup;
                } else {
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
      <?php endforeach; ?>
    <?php endif; ?>

  </div>
</section>

<?php require_once 'includes/footer.php'; ?>
