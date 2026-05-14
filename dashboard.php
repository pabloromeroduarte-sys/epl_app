<?php
$page_title = 'Mi Dashboard';
$player_tab = 'dashboard';
require_once 'includes/auth.php';
require_once 'includes/functions.php';
epl_require_login();

$jugador = epl_jugador_actual();
$liga    = epl_liga_activa();
$db      = epl_db();
$equipo  = $liga ? epl_equipo_del_jugador($jugador['id'], $liga['id']) : null;
$partidos = $equipo ? epl_partidos_equipo($equipo['id']) : [];

// Stats del equipo en la liga
$stats = null;
if ($equipo && $liga) {
    $st = $db->prepare("SELECT * FROM clasificacion WHERE liga_id=? AND equipo_id=?");
    $st->execute([$liga['id'], $equipo['id']]);
    $stats = $st->fetch();
}

// Calcular % rendimiento
$rendimiento = 0;
if ($stats && $stats['pj'] > 0) {
    $rendimiento = round(($stats['pg'] / $stats['pj']) * 100);
}

$proximos  = array_filter($partidos, fn($p) => $p['estado'] === 'pendiente' || $p['estado'] === 'reprogramado');
$jugados   = array_filter($partidos, fn($p) => $p['estado'] === 'jugado');
// Ordenar próximos de más cercano a más lejano
usort($proximos, fn($a, $b) => strtotime($a['fecha_programada'] ?? '9999-12-31') <=> strtotime($b['fecha_programada'] ?? '9999-12-31'));
$proximos  = array_slice(array_values($proximos), 0, 3);
$recientes = array_slice(array_values($jugados), 0, 5);

// Alerta de atrasos: partidos con fecha pasada sin resultado, o en estado reprogramado
$atrasados = [];
if ($equipo) {
    $hoy = date('Y-m-d H:i:s');
    $stA = $db->prepare("
        SELECT p.*, el.nombre AS local_nombre, ev.nombre AS visitante_nombre
        FROM partidos p
        JOIN equipos el ON el.id = p.equipo_local_id
        JOIN equipos ev ON ev.id = p.equipo_visitante_id
        LEFT JOIN recintos r ON r.id = p.recinto_id
        WHERE (p.equipo_local_id=? OR p.equipo_visitante_id=?)
          AND (
            (p.estado='pendiente' AND p.fecha_programada IS NOT NULL AND p.fecha_programada < ?)
            OR p.estado='reprogramado'
          )
        ORDER BY p.fecha_programada ASC, r.nombre ASC
    ");
    $stA->execute([$equipo['id'], $equipo['id'], $hoy]);
    $atrasados = $stA->fetchAll();
}
?>
<?php require_once 'includes/header.php'; ?>


<div class="dash-layout">
<?php include __DIR__ . '/includes/player_sidebar.php'; ?>
<main class="dash-main">
    <div class="dash-header">
      <h1 class="dash-title">Hola, <?= epl_h($jugador['nombre']) ?></h1>
      <?php if ($liga): ?>
        <p style="color:var(--gray-600);margin-top:.25rem"><?= epl_h($liga['nombre']) ?> — <?= epl_h($liga['temporada'] ?? '') ?></p>
      <?php endif; ?>
    </div>

    <!-- Bienvenida nuevo registro -->
    <?php if (isset($_GET['bienvenido'])): ?>
    <div class="alert alert-success" style="display:flex;align-items:flex-start;gap:.75rem;margin-bottom:1.5rem">
      <svg style="width:20px;height:20px;flex-shrink:0;margin-top:.1rem" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
      <div>
        <strong>¡Bienvenido a Elite Padel League!</strong>
        Tu cuenta fue creada correctamente. Completa tu perfil y espera que el organizador te asigne a un equipo.
      </div>
    </div>
    <?php endif; ?>

    <!-- Alerta de atrasos -->
    <?php if ($atrasados): ?>
    <div class="alert alert-error" style="display:flex;align-items:flex-start;gap:.75rem;margin-bottom:1.5rem">
      <svg style="width:20px;height:20px;flex-shrink:0;margin-top:.1rem" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
      <div style="flex:1">
        <div style="font-weight:700;margin-bottom:.25rem">Tienes <?= count($atrasados) ?> partido<?= count($atrasados)>1?'s':'' ?> con fecha vencida o pendiente<?= count($atrasados)>1?'s':'' ?>:</div>
        <ul style="margin:0 0 .75rem 0;padding:0;list-style:none;font-size:.85rem;opacity:.9">
          <?php foreach($atrasados as $at): ?>
            <li style="margin-bottom:.2rem">
              • <?= epl_h($at['local_nombre'] . ' vs ' . $at['visitante_nombre']) ?> 
              <span style="opacity:.7">(<?= $at['fecha_programada'] ? date('d/m/Y', strtotime($at['fecha_programada'])) : 'Sin fecha' ?>)</span>
            </li>
          <?php endforeach; ?>
        </ul>
        <?php 
          $link = "reprogramar.php";
          if (count($atrasados) === 1) $link .= "?partido_id=" . $atrasados[0]['id'];
        ?>
        <a href="<?= $link ?>" class="btn btn-sm" style="background:#fff;color:#b91c1c;font-weight:700;border:none">Regularizar ahora →</a>
      </div>
    </div>
    <?php endif; ?>

    <!-- Stats del equipo -->
    <?php if ($stats): ?>
    <div class="stats-grid">
      <div class="stat-card">
        <div class="stat-value" style="color:var(--gold)"><?= $stats['posicion'] ?? '—' ?></div>
        <div class="stat-label">Posición</div>
      </div>
      <div class="stat-card">
        <div class="stat-value"><?= $stats['pj'] ?></div>
        <div class="stat-label">Jugados</div>
      </div>
      <div class="stat-card">
        <div class="stat-value" style="color:var(--green)"><?= $stats['pg'] ?></div>
        <div class="stat-label">Ganados</div>
      </div>
      <div class="stat-card">
        <div class="stat-value" style="color:var(--red)"><?= $stats['pp'] ?></div>
        <div class="stat-label">Perdidos</div>
      </div>
      <div class="stat-card">
        <div class="stat-value"><?= $stats['puntos'] ?></div>
        <div class="stat-label">Puntos</div>
      </div>
      <div class="stat-card">
        <div class="stat-value" style="color:<?= $rendimiento>=50?'var(--green)':'var(--red)' ?>"><?= $rendimiento ?>%</div>
        <div class="stat-label">Rendimiento</div>
      </div>
    </div>
    <?php endif; ?>

    <!-- Próximos partidos -->
    <?php if ($proximos): ?>
    <div class="card mb-4">
      <div class="card-head">
        <h3 style="font-family:var(--font-head);font-size:1rem;text-transform:uppercase;color:var(--navy)">Próximos partidos</h3>
        <a href="ingresar_resultado.php" class="btn btn-primary btn-sm">+ Ingresar resultado</a>
      </div>
      <div class="card-body">
        <div class="partidos-list">
          <?php foreach ($proximos as $p): ?>
          <div class="partido-card-v2" style="padding:1rem">
            <div class="partido-col-info" style="border:none">
              <span class="fecha-label" style="font-size:.6rem">Fecha <?= $p['jornada'] ?? '' ?></span>
              <div class="partido-date" style="font-size:.7rem">🗓 <?= $p['fecha_programada'] ? date('d/m', strtotime($p['fecha_programada'])) : 'TBD' ?></div>
            </div>
            <div style="display:flex;align-items:center;justify-content:center;gap:1.5rem;flex:1">
              <div style="text-align:right;flex:1"><span class="equipo-nombre-card" style="font-size:.75rem"><?= epl_h($p['local_nombre']) ?></span></div>
              <div class="marcador-box" style="font-size:1rem;padding:.4rem .8rem;min-width:60px">VS</div>
              <div style="text-align:left;flex:1"><span class="equipo-nombre-card" style="font-size:.75rem"><?= epl_h($p['visitante_nombre']) ?></span></div>
            </div>
            <div class="partido-col-meta" style="border:none;padding-left:0">
              <?php
                $r_nombre = $p['recinto_nombre'] ?? '';
                $r_sup    = $p['recinto_superior_nombre'] ?? '';
                $r_abu    = $p['recinto_abuelo_nombre'] ?? '';
                $cancha   = $p['cancha'] ?? '';
                if ($r_abu) {
                    $badge_txt = $r_sup;
                    $label_txt = $r_abu . ($r_nombre ? ' - ' . $r_nombre : '');
                } elseif ($r_sup) {
                    $badge_txt = $r_nombre;
                    $label_txt = $r_sup;
                } else {
                    $badge_txt = $r_nombre ?: 'TBD';
                    $label_txt = '';
                }
                if ($cancha && !str_contains(strtolower((string)$label_txt), 'cancha')) {
                    $label_txt .= ($label_txt ? ' - ' : '') . 'Cancha ' . $cancha;
                }
              ?>
              <span class="cancha-badge" style="margin-bottom:0;font-size:.6rem"><?= epl_h($badge_txt) ?></span>
              <?php if ($label_txt): ?>
                <div class="sede-label" style="font-size:.55rem;margin-top:.2rem"><?= epl_h($label_txt) ?></div>
              <?php endif; ?>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
    <?php endif; ?>

    <!-- Historial -->
    <?php if ($recientes): ?>
    <div class="card mb-4">
      <div class="card-head">
        <h3 style="font-family:var(--font-head);font-size:1rem;text-transform:uppercase;color:var(--navy)">Últimos resultados</h3>
        <a href="resultados.php" style="font-size:.78rem;color:var(--gold);font-weight:600">Ver todos →</a>
      </div>
      <div class="card-body">
        <div class="partidos-list">
          <?php foreach ($recientes as $p):
            $gane = $p['ganador_id'] == ($equipo['id'] ?? -1);
          ?>
          <div class="partido-card-v2" style="padding:1rem;border-left:4px solid <?= $gane?'var(--green)':'var(--red)' ?>">
            <div class="partido-col-info" style="border:none">
              <span class="fecha-label" style="font-size:.6rem">Fecha <?= $p['jornada'] ?? '' ?></span>
              <div class="partido-date" style="font-size:.7rem">🗓 <?= date('d/m', strtotime($p['fecha_jugado'])) ?></div>
            </div>
            <div style="display:flex;align-items:center;justify-content:center;gap:1.5rem;flex:1">
              <div style="text-align:right;flex:1"><span class="equipo-nombre-card" style="font-size:.75rem"><?= epl_h($p['local_nombre']) ?></span></div>
              <div style="display:flex;flex-direction:column;align-items:center">
                <div class="marcador-box" style="font-size:1.1rem;padding:.4rem .8rem;min-width:60px"><?= $p['sets_local'] ?>-<?= $p['sets_visitante'] ?></div>
                <?php
                  $sets=[];
                  for($s=1;$s<=3;$s++){$gl=$p["games_s{$s}_local"];$gv=$p["games_s{$s}_visitante"];if($gl!==null)$sets[]="$gl-$gv";}
                  if($sets): ?><div class="set-details" style="font-size:.6rem"><?= implode(' <span style="opacity:0.4; margin:0 2px">/</span> ', $sets) ?></div><?php endif; ?>
              </div>
              <div style="text-align:left;flex:1"><span class="equipo-nombre-card" style="font-size:.75rem"><?= epl_h($p['visitante_nombre']) ?></span></div>
            </div>
            <div class="partido-col-meta" style="border:none;padding-left:0">
              <span class="badge <?= $gane?'badge-jugado':'badge-walkover' ?>" style="font-size:.6rem"><?= $gane?'Victoria':'Derrota' ?></span>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
    <?php endif; ?>

    <?php if (!$equipo): ?>
    <div class="alert alert-info">No estás inscrito en ningún equipo de la liga activa.</div>
    <?php endif; ?>

</main>
</div>

<?php require_once 'includes/footer.php'; ?>
