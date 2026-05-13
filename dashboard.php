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

$proximos  = array_filter($partidos, fn($p) => $p['estado'] === 'pendiente');
$jugados   = array_filter($partidos, fn($p) => $p['estado'] === 'jugado');
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
        WHERE (p.equipo_local_id=? OR p.equipo_visitante_id=?)
          AND (
            (p.estado='pendiente' AND p.fecha_programada IS NOT NULL AND p.fecha_programada < ?)
            OR p.estado='reprogramado'
          )
        ORDER BY p.fecha_programada ASC
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
      <div>
        <strong>Tienes <?= count($atrasados) ?> partido<?= count($atrasados)>1?'s':'' ?> atrasado<?= count($atrasados)>1?'s':'' ?>.</strong>
        Tienes partidos con fecha vencida o pendientes de reprogramación.
        <a href="reprogramar.php" style="color:inherit;font-weight:700;text-decoration:underline;margin-left:.5rem">Regularizar →</a>
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
          <div class="partido-card" style="padding:.85rem 1rem">
            <div class="partido-equipo">
              <span class="partido-nombre" style="font-size:.85rem"><?= epl_h($p['local_nombre']) ?></span>
              <?php if ($p['equipo_local_id'] == $equipo['id']): ?><span style="font-size:.68rem;color:var(--gold);font-weight:700">TÚ</span><?php endif; ?>
            </div>
            <div class="partido-resultado">
              <span style="font-size:.78rem;font-weight:700;color:var(--gold)">
                <?= $p['fecha_programada'] ? date('d/m H:i', strtotime($p['fecha_programada'])) : 'Por definir' ?>
              </span>
              <span class="badge badge-pendiente" style="font-size:.65rem">Pendiente</span>
            </div>
            <div class="partido-equipo right">
              <span class="partido-nombre" style="font-size:.85rem"><?= epl_h($p['visitante_nombre']) ?></span>
              <?php if ($p['equipo_visitante_id'] == $equipo['id']): ?><span style="font-size:.68rem;color:var(--gold);font-weight:700">TÚ</span><?php endif; ?>
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
          <div class="partido-card" style="padding:.85rem 1rem;border-left:3px solid <?= $gane?'var(--green)':'var(--red)' ?>">
            <div class="partido-equipo">
              <span class="partido-nombre" style="font-size:.85rem"><?= epl_h($p['local_nombre']) ?></span>
            </div>
            <div class="partido-resultado">
              <span class="resultado-score" style="font-size:1.25rem"><?= $p['sets_local'] ?> – <?= $p['sets_visitante'] ?></span>
              <?php
                $sets=[];
                for($s=1;$s<=3;$s++){$gl=$p["games_s{$s}_local"];$gv=$p["games_s{$s}_visitante"];if($gl!==null)$sets[]="$gl-$gv";}
                if($sets): ?><span class="resultado-sets"><?= implode(' ', $sets) ?></span><?php endif; ?>
              <span class="badge <?= $gane?'badge-jugado':'badge-walkover' ?>" style="font-size:.65rem"><?= $gane?'Victoria':'Derrota' ?></span>
            </div>
            <div class="partido-equipo right">
              <span class="partido-nombre" style="font-size:.85rem"><?= epl_h($p['visitante_nombre']) ?></span>
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
