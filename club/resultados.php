<?php
$page_title = 'Resultados';
$club_tab   = 'resultados';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
epl_require_club();

$club      = epl_jugador_actual();
$mis_ligas = epl_club_ligas((int)$club['id']);

// Liga seleccionada (siempre dentro de las asignadas)
$liga_id = isset($_GET['liga']) ? (int)$_GET['liga'] : ($mis_ligas[0] ?? 0);
if ($liga_id && !in_array($liga_id, $mis_ligas, true)) $liga_id = $mis_ligas[0] ?? 0;

$estado = $_GET['estado'] ?? '';
if (!in_array($estado, ['', 'jugado', 'pendiente', 'reprogramado', 'walkover'], true)) $estado = '';

// Ligas asignadas (para el selector)
$ligas_info = [];
if ($mis_ligas) {
    $in = implode(',', array_fill(0, count($mis_ligas), '?'));
    $st = epl_db()->prepare("SELECT id, nombre FROM ligas WHERE id IN ($in) ORDER BY id DESC");
    $st->execute($mis_ligas);
    $ligas_info = $st->fetchAll();
}

$partidos = $liga_id ? epl_partidos_liga($liga_id, $estado) : [];
$por_jornada = [];
foreach ($partidos as $p) { $por_jornada[$p['jornada'] ?? 0][] = $p; }
krsort($por_jornada);

function club_recinto(array $p): string {
    $n = trim((string)($p['recinto_nombre'] ?? ''));
    if ($n === '') return '—';
    $sup = trim((string)($p['recinto_superior_nombre'] ?? ''));
    $abu = trim((string)($p['recinto_abuelo_nombre'] ?? ''));
    if ($abu && $sup) return "$abu $sup - $n";
    if ($sup) return "$sup - $n";
    return $n;
}
function club_fecha(?string $f): string {
    if (!$f || date('Y-m-d', strtotime($f)) === '2026-12-31') return 'Sin fecha';
    return date('d/m/Y H:i', strtotime($f));
}
function club_badge(string $estado): array {
    return match ($estado) {
        'jugado'        => ['badge-jugado', 'Jugado'],
        'walkover'      => ['badge-walkover', 'Walkover'],
        'no_presentado' => ['badge-walkover', 'No pres.'],
        'reprogramado'  => ['badge-reprog', 'Reprogramado'],
        default         => ['badge-pendiente', 'Pendiente'],
    };
}
?>
<?php require_once '../includes/header.php'; ?>

<div class="dash-layout">
  <?php include __DIR__ . '/../includes/club_sidebar.php'; ?>
  <main class="dash-main">

    <div class="dash-header">
      <h1 class="dash-title">Resultados</h1>
      <p style="color:var(--gray-400);font-size:.88rem">Partidos de tus ligas asignadas.</p>
    </div>

    <?php if (!$mis_ligas): ?>
      <div class="alert alert-info">Todavía no tenés ligas asignadas. Pedile al administrador que te asigne una.</div>
    <?php else: ?>

      <form method="get" class="card" style="display:flex;gap:.75rem;flex-wrap:wrap;align-items:center;padding:.85rem 1rem;margin-bottom:1.25rem">
        <?php if (count($ligas_info) > 1): ?>
        <select name="liga" class="form-control" style="width:auto" onchange="this.form.submit()">
          <?php foreach ($ligas_info as $l): ?>
            <option value="<?= $l['id'] ?>" <?= $l['id']==$liga_id?'selected':'' ?>><?= epl_h($l['nombre']) ?></option>
          <?php endforeach; ?>
        </select>
        <?php else: ?>
          <input type="hidden" name="liga" value="<?= $liga_id ?>">
          <strong style="color:var(--navy)"><?= epl_h($ligas_info[0]['nombre'] ?? '') ?></strong>
        <?php endif; ?>
        <select name="estado" class="form-control" style="width:auto" onchange="this.form.submit()">
          <option value="">Todos los estados</option>
          <option value="jugado"       <?= $estado==='jugado'?'selected':'' ?>>Jugados</option>
          <option value="pendiente"    <?= $estado==='pendiente'?'selected':'' ?>>Pendientes</option>
          <option value="reprogramado" <?= $estado==='reprogramado'?'selected':'' ?>>Reprogramados</option>
          <option value="walkover"     <?= $estado==='walkover'?'selected':'' ?>>Walkover</option>
        </select>
      </form>

      <?php if (empty($por_jornada)): ?>
        <div class="card card-body text-center" style="padding:3rem"><p style="color:var(--gray-400)">No hay partidos para mostrar.</p></div>
      <?php else: ?>
        <?php foreach ($por_jornada as $jornada => $ps): ?>
          <?php if ($jornada): ?>
          <h3 style="font-family:var(--font-head);font-size:1rem;text-transform:uppercase;color:var(--navy);margin:1.5rem 0 .6rem;letter-spacing:.06em">Jornada <?= (int)$jornada ?></h3>
          <?php endif; ?>
          <div class="card" style="overflow-x:auto;margin-bottom:.5rem">
            <table style="width:100%;border-collapse:collapse;font-size:.83rem">
              <thead><tr style="background:var(--navy);color:#fff">
                <th style="padding:.55rem .8rem;text-align:left;font-size:.68rem;text-transform:uppercase">Local</th>
                <th style="padding:.55rem .8rem;text-align:center;font-size:.68rem;text-transform:uppercase">Resultado</th>
                <th style="padding:.55rem .8rem;text-align:left;font-size:.68rem;text-transform:uppercase">Visitante</th>
                <th style="padding:.55rem .8rem;text-align:left;font-size:.68rem;text-transform:uppercase">Fecha</th>
                <th style="padding:.55rem .8rem;text-align:left;font-size:.68rem;text-transform:uppercase">Cancha</th>
                <th style="padding:.55rem .8rem;text-align:center;font-size:.68rem;text-transform:uppercase">Estado</th>
              </tr></thead>
              <tbody>
              <?php foreach ($ps as $p): [$bc, $bl] = club_badge($p['estado']);
                $sets = [];
                for ($s=1;$s<=3;$s++){ $gl=$p["games_s{$s}_local"]; $gv=$p["games_s{$s}_visitante"]; if($gl!==null) $sets[]="$gl-$gv"; }
              ?>
                <tr style="border-bottom:1px solid var(--gray-100)">
                  <td style="padding:.55rem .8rem;font-weight:600;color:var(--navy)"><?= epl_h($p['local_nombre']) ?></td>
                  <td style="padding:.55rem .8rem;text-align:center">
                    <?php if ($p['estado']==='jugado'): ?>
                      <span style="font-family:var(--font-head);font-weight:700"><?= (int)$p['sets_local'] ?>–<?= (int)$p['sets_visitante'] ?></span>
                      <?php if ($sets): ?><div style="font-size:.66rem;color:var(--gray-400)"><?= implode(' · ', $sets) ?></div><?php endif; ?>
                    <?php else: ?><span style="color:var(--gray-300)">vs</span><?php endif; ?>
                  </td>
                  <td style="padding:.55rem .8rem;font-weight:600;color:var(--navy)"><?= epl_h($p['visitante_nombre']) ?></td>
                  <td style="padding:.55rem .8rem;color:var(--gray-600);font-size:.78rem;white-space:nowrap"><?= epl_h(club_fecha($p['fecha_programada'])) ?></td>
                  <td style="padding:.55rem .8rem;color:var(--gray-600);font-size:.78rem"><?= epl_h(club_recinto($p)) ?></td>
                  <td style="padding:.55rem .8rem;text-align:center"><span class="badge <?= $bc ?>" style="font-size:.62rem"><?= $bl ?></span></td>
                </tr>
              <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>

    <?php endif; ?>
  </main>
</div>

<?php require_once '../includes/footer.php'; ?>
