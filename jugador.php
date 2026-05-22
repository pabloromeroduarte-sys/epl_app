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

<!-- Hero jugador premium -->
<section class="epl-hero" style="background-image: linear-gradient(135deg, rgba(28,47,72,.94) 0%, rgba(10,20,33,.96) 100%), url('<?= epl_url('assets/img/landing/accion-padel.jpg') ?>');padding:3rem 1.5rem 2.5rem;text-align:left">
  <div class="epl-container" style="display:flex;align-items:center;gap:2rem;flex-wrap:wrap">
    <div style="position:relative">
      <img src="<?= epl_h(epl_foto_jugador($j['foto'], $j['nombre'].' '.$j['apellido'])) ?>"
           alt="<?= epl_h($j['nombre'].' '.$j['apellido']) ?>"
           style="width:120px;height:120px;border-radius:50%;object-fit:cover;border:4px solid var(--gold);flex-shrink:0;box-shadow:0 8px 30px rgba(201,167,98,.3)">
      <?php if ($stats && ($stats['posicion'] ?? 0) > 0 && $stats['posicion'] <= 3): ?>
        <span style="position:absolute;bottom:-4px;right:-4px;width:36px;height:36px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-family:var(--font-head);font-size:1.1rem;background:<?= $stats['posicion']==1?'#fbbf24':($stats['posicion']==2?'#d1d5db':'#d97706') ?>;color:#1f2937;border:3px solid var(--navy);box-shadow:0 2px 8px rgba(0,0,0,.3)"><?= $stats['posicion'] ?>°</span>
      <?php endif; ?>
    </div>
    <div style="flex:1;min-width:0">
      <span class="epl-eyebrow">Perfil de jugador</span>
      <h1 style="font-family:var(--font-head);font-size:clamp(1.8rem,4.5vw,3.2rem);text-transform:uppercase;color:var(--white);line-height:.95;margin:0">
        <?= epl_h($j['nombre']) ?> <span style="color:var(--gold)"><?= epl_h($j['apellido']) ?></span>
      </h1>
      <?php if ($j['alias']): ?>
        <p style="color:var(--gold);font-size:1rem;font-weight:700;margin-top:.4rem;font-style:italic">"<?= epl_h($j['alias']) ?>"</p>
      <?php endif; ?>
      <?php if ($equipo): ?>
        <p style="color:rgba(255,255,255,.7);font-size:.85rem;margin-top:.5rem;display:inline-flex;align-items:center;gap:.4rem;background:rgba(255,255,255,.08);padding:.3rem .8rem;border-radius:999px;backdrop-filter:blur(8px)">
          <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
          <?= epl_h($equipo['nombre']) ?>
        </p>
      <?php endif; ?>
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
