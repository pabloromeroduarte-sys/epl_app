<?php
/**
 * Componente Partido Card V2 (Reusable)
 * Requiere la variable $p (array del partido)
 */
$is_jugado = ($p['estado'] === 'jugado');
$jor_badge = $p['jornada'] ?? '—';
$search_str = strtolower(($p['local_nombre']??'') . ' ' . ($p['visitante_nombre']??''));
$_estado_cfg = match($p['estado'] ?? 'pendiente') {
    'jugado'       => ['lbl' => 'Jugado',       'bg' => '#dcfce7', 'color' => '#15803d'],
    'reprogramado' => ['lbl' => 'Reprogramado', 'bg' => '#dbeafe', 'color' => '#1d4ed8'],
    'walkover'     => ['lbl' => 'W/O',          'bg' => '#fee2e2', 'color' => '#dc2626'],
    'no_presentado'=> ['lbl' => 'No presentado','bg' => '#fee2e2', 'color' => '#dc2626'],
    default        => ['lbl' => 'Pendiente',    'bg' => '#fef9c3', 'color' => '#92400e'],
};
?>
<div class="partido-card-v2" data-search="<?= $search_str ?>" data-jornada="<?= $jor_badge ?>">
  <!-- Columna 1: Info Fecha -->
  <div class="partido-col-info">
    <span class="fecha-label">Fecha <?= $jor_badge ?></span>
    <div class="partido-date">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
      <?= !empty($p['fecha_programada']) ? date('d M, Y', strtotime($p['fecha_programada'])) : 'TBD' ?>
    </div>
    <span class="partido-time">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
      <?= !empty($p['fecha_programada']) ? date('H:i', strtotime($p['fecha_programada'])) : '00:00' ?>
    </span>
    <span style="display:inline-block;margin-top:.35rem;padding:.18rem .55rem;border-radius:99px;font-size:.65rem;font-weight:700;letter-spacing:.04em;background:<?= $_estado_cfg['bg'] ?>;color:<?= $_estado_cfg['color'] ?>">
      <?= $_estado_cfg['lbl'] ?>
    </span>
  </div>

  <!-- Columna 2: Equipos y Marcador -->
  <div style="display:flex; align-items:center; justify-content:center; gap:2rem; flex:1">
    <div style="text-align:right; flex:1">
      <span class="equipo-nombre-card"><?= epl_h($p['local_nombre'] ?? 'TBD') ?></span>
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
        for($s=1;$s<=3;$s++){
            $gl=$p["games_s{$s}_local"];
            $gv=$p["games_s{$s}_visitante"];
            if($gl!==null && $gl !== '') $sets[]="$gl-$gv";
        }
      ?>
        <div class="set-details"><?= implode(' <span style="opacity:0.4; margin:0 2px">/</span> ', $sets) ?></div>
      <?php endif; ?>
    </div>

    <div style="text-align:left; flex:1">
      <span class="equipo-nombre-card"><?= epl_h($p['visitante_nombre'] ?? 'TBD') ?></span>
    </div>
  </div>

  <!-- Columna 3: Meta (Cancha/Sede) -->
  <div class="partido-col-meta">
    <?php 
      $r_nombre = $p['recinto_nombre'] ?? '';
      $r_sup    = $p['recinto_superior_nombre'] ?? '';
      $r_abu    = $p['recinto_abuelo_nombre'] ?? '';
      $cancha   = $p['cancha'] ?? '';
      $liga_sede= $liga['sede'] ?? '';

      // Lógica de jerarquía:
      if ($r_abu) {
        $badge_txt = $r_sup; 
        $label_txt = $r_abu . ($r_nombre ? ' - ' . $r_nombre : ''); 
      } elseif ($r_sup) {
        $badge_txt = $r_nombre; 
        $label_txt = $r_sup;    
      } else {
        $badge_txt = $r_nombre ?: ($liga_sede ?: 'Sede TBD');
        $label_txt = ($r_nombre && $liga_sede && strtolower((string)$r_nombre) !== strtolower((string)$liga_sede)) ? $liga_sede : '';
      }

      if ($cancha && !str_contains(strtolower((string)$label_txt), 'cancha')) {
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
