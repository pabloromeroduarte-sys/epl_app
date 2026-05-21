<?php
require_once 'includes/functions.php';

$db = epl_db();
$id = (int)($_GET['id'] ?? 0);

if (!$id) { header('Location: jugadores.php'); exit; }

$st = $db->prepare("SELECT * FROM jugadores WHERE id=? AND estado='activo'");
$st->execute([$id]);
$j = $st->fetch();
if (!$j) { header('Location: jugadores.php'); exit; }

$page_title = $j['nombre'].' '.$j['apellido'].' — Jugador';
$active_nav = 'jugadores';

// ── SEO: meta tags específicos por jugador ───────────────────────────────────
$_nombre_completo  = $j['nombre'].' '.$j['apellido'];
$meta_description  = "Perfil del jugador {$_nombre_completo} en Elite Padel League. "
                   . "Ranking, historial de partidos, estadísticas y temporada actual.";
$meta_keywords     = "{$_nombre_completo}, jugador padel, perfil padel, EPL, elite padel league, ranking";
$og_image          = epl_foto_jugador($j['foto'] ?? null, $_nombre_completo);

// JSON-LD adicional Person
$_proto = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$_host  = $_SERVER['HTTP_HOST'] ?? 'epleague.cl';
$_jugador_schema = json_encode([
    '@context' => 'https://schema.org',
    '@type'    => 'Person',
    'name'     => $_nombre_completo,
    'image'    => $og_image,
    'memberOf' => [
        '@type' => 'SportsOrganization',
        'name'  => 'Elite Padel League',
        'url'   => $_proto . '://' . $_host,
    ],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

// Equipo del jugador en la liga activa
$liga = epl_liga_activa();
$equipo = $liga ? epl_equipo_del_jugador($j['id'], $liga['id']) : null;

// Estadísticas del equipo
$stats = null;
if ($equipo && $liga) {
    $stS = $db->prepare("SELECT * FROM clasificacion WHERE liga_id=? AND equipo_id=?");
    $stS->execute([$liga['id'], $equipo['id']]);
    $stats = $stS->fetch();
}

// Compañero de equipo
$compañero = null;
if ($equipo) {
    $comp_id = ($equipo['jugador1_id'] == $j['id']) ? $equipo['jugador2_id'] : $equipo['jugador1_id'];
    $stC = $db->prepare("SELECT * FROM jugadores WHERE id=?");
    $stC->execute([$comp_id]);
    $compañero = $stC->fetch();
}

// Historial de partidos jugados (últimos 10)
$partidos = [];
if ($equipo) {
    $stP = $db->prepare("
        SELECT p.*,
               el.nombre AS local_nombre,
               ev.nombre AS visitante_nombre
        FROM partidos p
        JOIN equipos el ON el.id = p.equipo_local_id
        JOIN equipos ev ON ev.id = p.equipo_visitante_id
        WHERE (p.equipo_local_id=? OR p.equipo_visitante_id=?)
          AND p.estado='jugado'
        ORDER BY p.fecha_jugado DESC
        LIMIT 10
    ");
    $stP->execute([$equipo['id'], $equipo['id']]);
    $partidos = $stP->fetchAll();
}
?>
<?php require_once 'includes/header.php'; ?>

<!-- Schema.org Person -->
<script type="application/ld+json"><?= $_jugador_schema ?></script>

<!-- Hero jugador -->
<section class="section-sm" style="background:var(--navy)">
  <div class="container">
    <div style="display:flex;align-items:center;gap:2rem;flex-wrap:wrap">
      <img src="<?= epl_h(epl_foto_jugador($j['foto'], $j['nombre'].' '.$j['apellido'])) ?>"
           alt="<?= epl_h($j['nombre'].' '.$j['apellido']) ?>"
           style="width:100px;height:100px;border-radius:50%;object-fit:cover;border:3px solid var(--gold);flex-shrink:0">
      <div>
        <p class="section-eyebrow" style="color:var(--gold)">Jugador</p>
        <h1 style="font-family:var(--font-head);font-size:clamp(1.8rem,4vw,2.8rem);text-transform:uppercase;color:var(--white);line-height:1.1">
          <?= epl_h($j['nombre'].' '.$j['apellido']) ?>
        </h1>
        <?php if ($j['alias']): ?>
          <p style="color:var(--gold);font-size:.9rem;font-weight:600;margin-top:.3rem">"<?= epl_h($j['alias']) ?>"</p>
        <?php endif; ?>
        <?php if ($equipo): ?>
          <p style="color:rgba(255,255,255,.6);font-size:.85rem;margin-top:.4rem"><?= epl_h($equipo['nombre']) ?></p>
        <?php endif; ?>
      </div>
    </div>
  </div>
</section>

<section class="section">
  <div class="container">
    <div style="display:grid;grid-template-columns:1fr 2fr;gap:2rem;align-items:start" class="jugador-detail-grid">
    <style>@media(max-width:768px){.jugador-detail-grid{grid-template-columns:1fr !important;}}</style>

      <!-- Columna izquierda: info y compañero -->
      <div>
        <!-- Stats -->
        <?php if ($stats): ?>
        <div class="card mb-4">
          <div class="card-head">
            <h3 style="font-family:var(--font-head);font-size:.9rem;text-transform:uppercase;color:var(--navy)">Estadísticas</h3>
            <?php if ($liga): ?>
              <span style="font-size:.72rem;color:var(--gray-400)"><?= epl_h($liga['nombre']) ?></span>
            <?php endif; ?>
          </div>
          <div class="card-body" style="display:grid;grid-template-columns:repeat(3,1fr);gap:1rem;text-align:center">
            <div>
              <div style="font-family:var(--font-head);font-size:1.8rem;color:var(--gold)"><?= $stats['posicion'] ?? '—' ?></div>
              <div style="font-size:.68rem;color:var(--gray-400);text-transform:uppercase;font-weight:700">Posición</div>
            </div>
            <div>
              <div style="font-family:var(--font-head);font-size:1.8rem;color:var(--navy)"><?= $stats['pj'] ?></div>
              <div style="font-size:.68rem;color:var(--gray-400);text-transform:uppercase;font-weight:700">Jugados</div>
            </div>
            <div>
              <div style="font-family:var(--font-head);font-size:1.8rem;color:var(--navy)"><?= $stats['puntos'] ?></div>
              <div style="font-size:.68rem;color:var(--gray-400);text-transform:uppercase;font-weight:700">Puntos</div>
            </div>
            <div>
              <div style="font-family:var(--font-head);font-size:1.5rem;color:#22c55e"><?= $stats['pg'] ?></div>
              <div style="font-size:.68rem;color:var(--gray-400);text-transform:uppercase;font-weight:700">Ganados</div>
            </div>
            <div>
              <div style="font-family:var(--font-head);font-size:1.5rem;color:#ef4444"><?= $stats['pp'] ?></div>
              <div style="font-size:.68rem;color:var(--gray-400);text-transform:uppercase;font-weight:700">Perdidos</div>
            </div>
            <div>
              <div style="font-family:var(--font-head);font-size:1.5rem;color:var(--navy)"><?= $stats['games_favor'] ?>–<?= $stats['games_contra'] ?></div>
              <div style="font-size:.68rem;color:var(--gray-400);text-transform:uppercase;font-weight:700">Games</div>
            </div>
          </div>
        </div>
        <?php endif; ?>

        <!-- Compañero de equipo -->
        <?php if ($compañero): ?>
        <div class="card mb-4">
          <div class="card-head">
            <h3 style="font-family:var(--font-head);font-size:.9rem;text-transform:uppercase;color:var(--navy)">Compañero</h3>
          </div>
          <div class="card-body">
            <a href="jugador.php?id=<?= $compañero['id'] ?>" style="display:flex;align-items:center;gap:.85rem;text-decoration:none">
              <img src="<?= epl_h(epl_foto_jugador($compañero['foto'], $compañero['nombre'].' '.$compañero['apellido'])) ?>"
                   alt="" style="width:50px;height:50px;border-radius:50%;object-fit:cover;border:2px solid var(--gold)">
              <div>
                <div style="font-weight:700;color:var(--navy);font-size:.9rem"><?= epl_h($compañero['nombre'].' '.$compañero['apellido']) ?></div>
                <?php if ($compañero['alias']): ?>
                  <div style="font-size:.75rem;color:var(--gold)">"<?= epl_h($compañero['alias']) ?>"</div>
                <?php endif; ?>
              </div>
            </a>
          </div>
        </div>
        <?php endif; ?>

        <a href="jugadores.php" style="font-size:.8rem;color:var(--gray-400)">← Todos los jugadores</a>
      </div>

      <!-- Columna derecha: historial de partidos -->
      <div>
        <h2 style="font-family:var(--font-head);font-size:1.3rem;text-transform:uppercase;color:var(--navy);margin-bottom:1rem">
          Historial de partidos
        </h2>

        <?php if (empty($partidos)): ?>
          <div class="alert alert-info">No hay partidos registrados aún.</div>
        <?php else: ?>
          <div class="partidos-list">
            <?php foreach ($partidos as $p):
              $gano = $equipo && ($p['ganador_id'] == $equipo['id']);
              $sets = [];
              for ($s=1; $s<=3; $s++) {
                $gl = $p["games_s{$s}_local"]; $gv = $p["games_s{$s}_visitante"];
                if ($gl !== null) $sets[] = "$gl-$gv";
              }
            ?>
            <div class="partido-card-v2" style="border-left:4px solid <?= $gano?'var(--green)':'var(--red)' ?>">
              <div class="partido-col-info">
                <span class="fecha-label">Fecha <?= $p['jornada'] ?? '' ?></span>
                <div class="partido-date">🗓 <?= $p['fecha_jugado'] ? date('d/m/y', strtotime($p['fecha_jugado'])) : 'TBD' ?></div>
              </div>
              <div style="display:flex;align-items:center;justify-content:center;gap:1.5rem;flex:1">
                <div style="text-align:right;flex:1"><span class="equipo-nombre-card"><?= epl_h($p['local_nombre']) ?></span></div>
                <div style="display:flex;flex-direction:column;align-items:center">
                  <div class="marcador-box"><?= $p['sets_local'] ?>-<?= $p['sets_visitante'] ?></div>
                  <?php if($sets): ?><div class="set-details"><?= implode(' <span style="opacity:0.4; margin:0 2px">/</span> ', $sets) ?></div><?php endif; ?>
                </div>
                <div style="text-align:left;flex:1"><span class="equipo-nombre-card"><?= epl_h($p['visitante_nombre']) ?></span></div>
              </div>
              <div class="partido-col-meta">
                <span class="badge <?= $gano?'badge-jugado':'badge-walkover' ?>"><?= $gano?'Victoria':'Derrota' ?></span>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</section>

<style>
@media(max-width:768px){
  .jugador-detail-grid { grid-template-columns: 1fr !important; }
  .jugador-hero-inner { flex-direction: column; gap: 1rem !important; }
}
</style>

<?php require_once 'includes/footer.php'; ?>
