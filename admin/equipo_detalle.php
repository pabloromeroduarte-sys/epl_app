<?php
$page_title = 'Admin — Detalle Equipo';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
epl_require_admin();

$db  = epl_db();
$eid = (int)($_GET['id'] ?? 0);
$lid = (int)($_GET['liga'] ?? 0);
if (!$eid) { header('Location: ligas.php'); exit; }

// ── Equipo + jugadores ───────────────────────────────────
$stE = $db->prepare("
    SELECT e.*,
           j1.nombre j1n, j1.apellido j1a, j1.foto j1foto, j1.telefono j1tel, j1.email j1email,
           j2.nombre j2n, j2.apellido j2a, j2.foto j2foto, j2.telefono j2tel, j2.email j2email
    FROM equipos e
    JOIN jugadores j1 ON j1.id = e.jugador1_id
    JOIN jugadores j2 ON j2.id = e.jugador2_id
    WHERE e.id = ?
");
$stE->execute([$eid]);
$equipo = $stE->fetch();
if (!$equipo) { header('Location: ligas.php'); exit; }

// Si no vino la liga, tomar la última liga del equipo
if (!$lid) {
    $stLid = $db->prepare("SELECT liga_id FROM liga_equipos WHERE equipo_id=? ORDER BY liga_id DESC LIMIT 1");
    $stLid->execute([$eid]);
    $lid = (int)$stLid->fetchColumn();
}

$liga = null;
if ($lid) {
    $stL = $db->prepare("SELECT id, nombre FROM ligas WHERE id=?");
    $stL->execute([$lid]);
    $liga = $stL->fetch();
}

// ── Clasificación del equipo en la liga ──────────────────
$clasif = null;
if ($lid) {
    $stC = $db->prepare("SELECT * FROM clasificacion WHERE liga_id=? AND equipo_id=?");
    $stC->execute([$lid, $eid]);
    $clasif = $stC->fetch();
}

// ── Partidos del equipo en la liga ───────────────────────
$partidos = [];
if ($lid) {
    $stP = $db->prepare("
        SELECT p.*,
               el.nombre AS local_nombre, ev.nombre AS visitante_nombre,
               r.nombre AS recinto_nombre
        FROM partidos p
        JOIN equipos el ON el.id = p.equipo_local_id
        JOIN equipos ev ON ev.id = p.equipo_visitante_id
        LEFT JOIN recintos r ON r.id = p.recinto_id
        WHERE p.liga_id = ? AND (p.equipo_local_id = ? OR p.equipo_visitante_id = ?)
        ORDER BY p.jornada ASC, p.fecha_programada IS NULL ASC, p.fecha_programada ASC, p.id ASC
    ");
    $stP->execute([$lid, $eid, $eid]);
    $partidos = $stP->fetchAll();
}

// ── Cálculos: galletas (sets 6-0), games, jugados/pendientes ─
$galletas_favor  = 0;   // sets ganados 6-0
$galletas_contra = 0;   // sets perdidos 0-6
$sets_favor = 0; $sets_contra = 0;
$games_favor = 0; $games_contra = 0;
$jugados = []; $pendientes = [];

foreach ($partidos as $p) {
    $es_local = ((int)$p['equipo_local_id'] === $eid);

    if (in_array($p['estado'], ['jugado','walkover','no_presentado'], true)) {
        $jugados[] = $p;
    } else {
        $pendientes[] = $p;
        continue;
    }

    if ($p['estado'] !== 'jugado') continue;

    // Recorrer los 3 sets desde la perspectiva del equipo
    for ($s = 1; $s <= 3; $s++) {
        $gl = $p["games_s{$s}_local"];
        $gv = $p["games_s{$s}_visitante"];
        if ($gl === null || $gv === null) continue;
        $mios   = $es_local ? (int)$gl : (int)$gv;
        $rival  = $es_local ? (int)$gv : (int)$gl;
        $games_favor  += $mios;
        $games_contra += $rival;
        if ($mios > $rival) $sets_favor++; elseif ($rival > $mios) $sets_contra++;
        if ($mios == 6 && $rival == 0) $galletas_favor++;
        if ($mios == 0 && $rival == 6) $galletas_contra++;
    }
}

$pj  = (int)($clasif['pj'] ?? count(array_filter($jugados, fn($p)=>$p['estado']==='jugado')));
$pg  = (int)($clasif['pg'] ?? 0);
$pp  = (int)($clasif['pp'] ?? 0);
$pts = (int)($clasif['puntos'] ?? 0);
$pos = $clasif['posicion'] ?? null;
$gf  = (int)($clasif['games_favor']  ?? $games_favor);
$gc  = (int)($clasif['games_contra'] ?? $games_contra);
$pct_victorias = $pj > 0 ? round($pg / $pj * 100) : 0;

$badge_map = [
    'jugado'        => ['badge-jugado',   'Jugado'],
    'walkover'      => ['badge-walkover',  'Walkover'],
    'no_presentado' => ['badge-walkover',  'No pres.'],
    'reprogramado'  => ['badge-reprog',    'Reprogramado'],
    'pendiente'     => ['badge-pendiente', 'Pendiente'],
];

function ed_fecha_fmt(?string $f): array {
    // [texto, es_sin_fecha]
    if (!$f) return ['Sin fecha', true];
    if (date('Y-m-d', strtotime($f)) === '2026-12-31') return ['Sin fecha', true];
    return [date('d/m/Y H:i', strtotime($f)), false];
}
?>
<?php require_once '../includes/header.php'; ?>
<style>
.ed-stats { display:grid; grid-template-columns:repeat(auto-fit,minmax(110px,1fr)); gap:.75rem; margin-bottom:1.5rem }
.ed-stat  { background:var(--white); border:1px solid var(--gray-100); border-radius:14px; padding:1rem; text-align:center; box-shadow:0 2px 12px rgba(0,0,0,.04) }
.ed-stat .num { font-family:var(--font-head); font-size:1.6rem; color:var(--navy); line-height:1 }
.ed-stat .lbl { font-size:.62rem; font-weight:800; text-transform:uppercase; letter-spacing:.06em; color:var(--gray-400); margin-top:.35rem }
.ed-jugador { display:flex; align-items:center; gap:.6rem }
.ed-jugador img { width:44px; height:44px; border-radius:50%; object-fit:cover; border:2px solid var(--gold) }
.ed-table { width:100%; border-collapse:collapse; font-size:.83rem }
.ed-table th { background:var(--navy); color:#fff; padding:.6rem .8rem; text-align:left; font-size:.68rem; text-transform:uppercase }
.ed-table td { padding:.6rem .8rem; border-bottom:1px solid var(--gray-100) }
@media(max-width:600px){ .ed-hide-sm{ display:none } }
</style>

<div class="dash-layout">
  <?php include __DIR__ . '/partials/sidebar.php'; ?>
  <main class="dash-main">

    <!-- Breadcrumb -->
    <div style="margin-bottom:1rem;font-size:.82rem;color:var(--gray-400)">
      <a href="ligas.php" style="color:var(--gold)">Ligas y Torneos</a>
      <?php if ($liga): ?>
        <span style="margin:0 .4rem">›</span>
        <a href="liga_detalle.php?id=<?= $lid ?>&tab=equipos" style="color:var(--gold)"><?= epl_h($liga['nombre']) ?></a>
      <?php endif; ?>
      <span style="margin:0 .4rem">›</span>
      <span style="color:var(--navy);font-weight:600"><?= epl_h($equipo['nombre']) ?></span>
    </div>

    <!-- Header equipo -->
    <div class="card mb-3">
      <div class="card-body">
        <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:1rem">
          <div>
            <h2 style="font-family:var(--font-head);font-size:1.4rem;color:var(--navy);margin:0 0 1rem"><?= epl_h($equipo['nombre']) ?></h2>
            <div style="display:flex;gap:1.5rem;flex-wrap:wrap">
              <div class="ed-jugador">
                <img src="<?= epl_h(epl_foto_jugador($equipo['j1foto'], $equipo['j1n'].' '.$equipo['j1a'])) ?>">
                <div>
                  <div style="font-weight:700;color:var(--navy);font-size:.9rem"><?= epl_h($equipo['j1n'].' '.$equipo['j1a']) ?></div>
                  <?php if ($equipo['j1tel']): ?><div style="font-size:.75rem;color:var(--gray-400)">📞 <?= epl_h($equipo['j1tel']) ?></div><?php endif; ?>
                </div>
              </div>
              <div class="ed-jugador">
                <img src="<?= epl_h(epl_foto_jugador($equipo['j2foto'], $equipo['j2n'].' '.$equipo['j2a'])) ?>">
                <div>
                  <div style="font-weight:700;color:var(--navy);font-size:.9rem"><?= epl_h($equipo['j2n'].' '.$equipo['j2a']) ?></div>
                  <?php if ($equipo['j2tel']): ?><div style="font-size:.75rem;color:var(--gray-400)">📞 <?= epl_h($equipo['j2tel']) ?></div><?php endif; ?>
                </div>
              </div>
            </div>
          </div>
          <?php if ($liga): ?>
          <a href="liga_detalle.php?id=<?= $lid ?>&tab=equipos" class="btn btn-sm" style="border:1px solid var(--gray-200);color:var(--gray-600)">← Volver</a>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <?php if (!$liga): ?>
      <div class="alert alert-info">Este equipo no está asociado a ninguna liga.</div>
    <?php else: ?>

    <!-- Estadísticas -->
    <div class="ed-stats">
      <div class="ed-stat"><div class="num" style="color:var(--gold)"><?= $pos ? $pos.'°' : '—' ?></div><div class="lbl">Posición</div></div>
      <div class="ed-stat"><div class="num"><?= $pts ?></div><div class="lbl">Puntos</div></div>
      <div class="ed-stat"><div class="num"><?= $pj ?></div><div class="lbl">Jugados</div></div>
      <div class="ed-stat"><div class="num" style="color:#16a34a"><?= $pg ?></div><div class="lbl">Ganados</div></div>
      <div class="ed-stat"><div class="num" style="color:#dc2626"><?= $pp ?></div><div class="lbl">Perdidos</div></div>
      <div class="ed-stat"><div class="num"><?= $pct_victorias ?>%</div><div class="lbl">% Victorias</div></div>
      <div class="ed-stat"><div class="num" style="font-size:1.2rem"><?= $gf ?>/<?= $gc ?></div><div class="lbl">Games F/C</div></div>
      <div class="ed-stat"><div class="num" style="color:<?= ($gf-$gc)>=0?'#16a34a':'#dc2626' ?>"><?= ($gf-$gc)>=0?'+':'' ?><?= $gf-$gc ?></div><div class="lbl">Dif. Games</div></div>
    </div>

    <!-- Galletas (sets 6-0) -->
    <div class="card mb-3">
      <div class="card-body" style="display:flex;align-items:center;gap:1.5rem;flex-wrap:wrap">
        <div style="font-family:var(--font-head);font-size:.95rem;text-transform:uppercase;color:var(--navy)">🍪 Galletas (sets 6-0)</div>
        <div style="display:flex;gap:2rem">
          <div style="text-align:center">
            <div style="font-family:var(--font-head);font-size:1.8rem;color:#16a34a;line-height:1"><?= $galletas_favor ?></div>
            <div style="font-size:.65rem;font-weight:800;text-transform:uppercase;color:var(--gray-400)">A favor</div>
          </div>
          <div style="text-align:center">
            <div style="font-family:var(--font-head);font-size:1.8rem;color:#dc2626;line-height:1"><?= $galletas_contra ?></div>
            <div style="font-size:.65rem;font-weight:800;text-transform:uppercase;color:var(--gray-400)">En contra</div>
          </div>
          <div style="text-align:center">
            <div style="font-family:var(--font-head);font-size:1.8rem;color:var(--navy);line-height:1"><?= $sets_favor ?>/<?= $sets_contra ?></div>
            <div style="font-size:.65rem;font-weight:800;text-transform:uppercase;color:var(--gray-400)">Sets F/C</div>
          </div>
        </div>
      </div>
    </div>

    <!-- Historial de partidos jugados -->
    <h3 style="font-family:var(--font-head);font-size:1rem;text-transform:uppercase;color:var(--navy);margin:1.5rem 0 .75rem">Historial de partidos (<?= count(array_filter($jugados, fn($p)=>$p['estado']==='jugado')) ?>)</h3>
    <div class="card mb-3">
      <div style="overflow-x:auto">
        <table class="ed-table">
          <thead><tr>
            <th>Jornada</th><th>Rival</th><th style="text-align:center">Resultado</th>
            <th style="text-align:center">Sets</th><th class="ed-hide-sm">Estado</th><th class="ed-hide-sm">Fecha</th>
          </tr></thead>
          <tbody>
          <?php if (empty($jugados)): ?>
            <tr><td colspan="6" style="text-align:center;padding:1.5rem;color:var(--gray-400)">Sin partidos jugados todavía.</td></tr>
          <?php endif; ?>
          <?php foreach ($jugados as $p):
            $es_local = ((int)$p['equipo_local_id'] === $eid);
            $rival = $es_local ? $p['visitante_nombre'] : $p['local_nombre'];
            $mis_sets   = $es_local ? (int)$p['sets_local'] : (int)$p['sets_visitante'];
            $sus_sets   = $es_local ? (int)$p['sets_visitante'] : (int)$p['sets_local'];
            $gano = ((int)$p['ganador_id'] === $eid);
            // detalle por sets
            $sets_txt = [];
            for ($s=1;$s<=3;$s++) {
                $gl = $p["games_s{$s}_local"]; $gv = $p["games_s{$s}_visitante"];
                if ($gl === null || $gv === null) continue;
                $mios = $es_local ? (int)$gl : (int)$gv;
                $riv  = $es_local ? (int)$gv : (int)$gl;
                $sets_txt[] = "$mios-$riv";
            }
            [$badge_cls, $badge_lbl] = $badge_map[$p['estado']] ?? ['badge-pendiente', $p['estado']];
            [$fecha_txt] = ed_fecha_fmt($p['fecha_jugado'] ?: $p['fecha_programada']);
          ?>
            <tr>
              <td style="color:var(--gray-500)"><?= $p['jornada'] ? 'J'.$p['jornada'] : '—' ?></td>
              <td style="font-weight:600;color:var(--navy)"><?= epl_h($rival) ?></td>
              <td style="text-align:center">
                <?php if ($p['estado']==='jugado'): ?>
                  <span style="font-weight:800;color:<?= $gano?'#16a34a':'#dc2626' ?>"><?= $gano?'G':'P' ?></span>
                  <span style="margin-left:.4rem;color:var(--gray-500)"><?= $mis_sets ?>-<?= $sus_sets ?></span>
                <?php else: ?>
                  <span class="badge <?= $badge_cls ?>"><?= $badge_lbl ?></span>
                <?php endif; ?>
              </td>
              <td style="text-align:center;font-size:.78rem;color:var(--gray-500)"><?= $sets_txt ? implode(' / ', $sets_txt) : '—' ?></td>
              <td class="ed-hide-sm"><span class="badge <?= $badge_cls ?>"><?= $badge_lbl ?></span></td>
              <td class="ed-hide-sm" style="font-size:.78rem;color:var(--gray-400)"><?= epl_h($fecha_txt) ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Fechas / partidos pendientes -->
    <h3 style="font-family:var(--font-head);font-size:1rem;text-transform:uppercase;color:var(--navy);margin:1.5rem 0 .75rem">Partidos pendientes (<?= count($pendientes) ?>)</h3>
    <div class="card">
      <div style="overflow-x:auto">
        <table class="ed-table">
          <thead><tr>
            <th>Jornada</th><th>Rival</th><th class="ed-hide-sm">Cancha</th><th>Fecha</th><th style="text-align:center">Estado</th>
          </tr></thead>
          <tbody>
          <?php if (empty($pendientes)): ?>
            <tr><td colspan="5" style="text-align:center;padding:1.5rem;color:var(--gray-400)">No hay partidos pendientes. 🎉</td></tr>
          <?php endif; ?>
          <?php foreach ($pendientes as $p):
            $es_local = ((int)$p['equipo_local_id'] === $eid);
            $rival = $es_local ? $p['visitante_nombre'] : $p['local_nombre'];
            [$badge_cls, $badge_lbl] = $badge_map[$p['estado']] ?? ['badge-pendiente', $p['estado']];
            [$fecha_txt, $sin_fecha] = ed_fecha_fmt($p['fecha_programada']);
            $vencido = !$sin_fecha && strtotime($p['fecha_programada']) < time();
          ?>
            <tr>
              <td style="color:var(--gray-500)"><?= $p['jornada'] ? 'J'.$p['jornada'] : '—' ?></td>
              <td style="font-weight:600;color:var(--navy)"><?= epl_h($rival) ?></td>
              <td class="ed-hide-sm" style="font-size:.78rem;color:var(--gray-500)"><?= epl_h($p['recinto_nombre'] ?? '—') ?></td>
              <td style="font-size:.8rem">
                <?php if ($sin_fecha): ?>
                  <span style="background:#ede9fe;color:#7c3aed;padding:.1rem .4rem;border-radius:4px;font-size:.7rem;font-weight:700">Sin fecha</span>
                <?php else: ?>
                  <span style="<?= $vencido?'color:#dc2626;font-weight:800':'color:var(--gray-600)' ?>"><?= epl_h($fecha_txt) ?><?= $vencido?' ⚠️':'' ?></span>
                <?php endif; ?>
              </td>
              <td style="text-align:center"><span class="badge <?= $badge_cls ?>"><?= $badge_lbl ?></span></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

    <?php endif; ?>

  </main>
</div>

<?php require_once '../includes/footer.php'; ?>
