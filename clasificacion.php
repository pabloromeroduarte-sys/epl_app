<?php
$page_title = 'Clasificación';
$active_nav = 'clasificacion';
require_once 'includes/functions.php';

$db   = epl_db();
$ligas = $db->query("SELECT * FROM ligas ORDER BY id DESC")->fetchAll();
$liga_id = isset($_GET['liga']) ? (int)$_GET['liga'] : ($ligas[0]['id'] ?? 0);
$liga_sel = null;
foreach ($ligas as $l) { if ($l['id'] == $liga_id) { $liga_sel = $l; break; } }
$clasificacion = $liga_id ? epl_clasificacion($liga_id) : [];

// Cargar todos los partidos jugados de la liga para el popup
$partidos_liga = [];
if ($liga_id) {
    $stP = $db->prepare("
        SELECT p.id, p.jornada, p.fecha_jugado, p.fecha_programada,
               p.sets_local, p.sets_visitante,
               p.games_s1_local, p.games_s1_visitante,
               p.games_s2_local, p.games_s2_visitante,
               p.games_s3_local, p.games_s3_visitante,
               p.ganador_id,
               p.equipo_local_id, p.equipo_visitante_id,
               el.nombre AS local_nombre, ev.nombre AS visitante_nombre
        FROM partidos p
        JOIN equipos el ON el.id = p.equipo_local_id
        JOIN equipos ev ON ev.id = p.equipo_visitante_id
        WHERE p.liga_id = ? AND p.estado = 'jugado'
        ORDER BY p.fecha_jugado DESC, p.jornada DESC
    ");
    $stP->execute([$liga_id]);
    foreach ($stP->fetchAll() as $partido) {
        $partidos_liga[$partido['equipo_local_id']][]    = $partido;
        $partidos_liga[$partido['equipo_visitante_id']][] = $partido;
    }
}
?>
<?php require_once 'includes/header.php'; ?>

<section class="section-sm" style="background:var(--navy)">
  <div class="container">
    <p class="section-eyebrow" style="color:var(--gold)">Temporada <?= $liga_sel ? epl_h($liga_sel['temporada']) : '' ?></p>
    <h1 class="section-title" style="color:var(--white)">Clasificación</h1>
  </div>
</section>

<section class="section">
  <div class="container">

    <!-- Selector de liga -->
    <?php if (count($ligas) > 1): ?>
    <div class="mb-4">
      <form method="get" style="display:flex;gap:.75rem;align-items:center;flex-wrap:wrap">
        <label class="form-label mb-0" style="white-space:nowrap">Liga:</label>
        <select name="liga" class="form-control" style="width:auto" onchange="this.form.submit()">
          <?php foreach ($ligas as $l): ?>
            <option value="<?= $l['id'] ?>" <?= $l['id']==$liga_id?'selected':'' ?>>
              <?= epl_h($l['nombre']) ?> — <?= epl_h($l['temporada'] ?? '') ?>
            </option>
          <?php endforeach; ?>
        </select>
      </form>
    </div>
    <?php endif; ?>

    <?php if ($clasificacion): ?>
    <div class="tabla-clasificacion">
      <table>
        <thead>
          <tr>
            <th>#</th>
            <th style="text-align:left">Equipo</th>
            <th title="Partidos Jugados">PJ</th>
            <th title="Ganados">PG</th>
            <th title="Perdidos">PP</th>
            <th title="Games a Favor" class="hide-mobile">GF</th>
            <th title="Games en Contra" class="hide-mobile">GC</th>
            <th title="Diferencia" class="hide-mobile">+/-</th>
            <th title="Puntos">Pts</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($clasificacion as $i => $row): ?>
          <?php
            $eq_partidos = $partidos_liga[$row['equipo_id']] ?? [];
            $eq_json = htmlspecialchars(json_encode($eq_partidos, JSON_UNESCAPED_UNICODE), ENT_QUOTES);
          ?>
          <tr style="cursor:pointer" onclick="verPartidos(<?= $row['equipo_id'] ?>, '<?= epl_h($row['equipo_nombre']) ?>', this)" title="Ver partidos jugados">
            <td>
              <?php $posClass = match($i) { 0=>'pos-1', 1=>'pos-2', 2=>'pos-3', default=>'pos-n' }; ?>
              <span class="posicion-num <?= $posClass ?>"><?= $i+1 ?></span>
            </td>
            <td>
              <div class="equipo-cell">
                <div class="equipo-avatars">
                   <img class="equipo-avatar"
                        src="<?= epl_h(epl_foto_jugador($row['j1_foto'], $row['j1_nombre'].' '.$row['j1_apellido'])) ?>"
                        alt="<?= epl_h($row['j1_nombre']) ?>">
                   <img class="equipo-avatar"
                        src="<?= epl_h(epl_foto_jugador($row['j2_foto'], $row['j2_nombre'].' '.$row['j2_apellido'])) ?>"
                        alt="<?= epl_h($row['j2_nombre']) ?>">
                </div>
                <div>
                  <div class="equipo-nombre"><?= epl_h($row['equipo_nombre']) ?></div>
                  <div style="font-size:.75rem;color:var(--gray-400)">
                    <?= epl_h($row['j1_nombre'].' '.$row['j1_apellido']) ?> /
                    <?= epl_h($row['j2_nombre'].' '.$row['j2_apellido']) ?>
                  </div>
                </div>
              </div>
            </td>
            <td><?= $row['pj'] ?></td>
            <td style="color:var(--green);font-weight:700"><?= $row['pg'] ?></td>
            <td style="color:var(--red)"><?= $row['pp'] ?></td>
            <td class="hide-mobile"><?= $row['games_favor'] ?></td>
            <td class="hide-mobile"><?= $row['games_contra'] ?></td>
            <td class="hide-mobile" style="color:<?= ($row['games_favor']-$row['games_contra'])>=0?'var(--green)':'var(--red)' ?>">
              <?= ($row['games_favor']-$row['games_contra']) >= 0 ? '+' : '' ?><?= $row['games_favor']-$row['games_contra'] ?>
            </td>
            <td>
              <strong style="font-size:1rem;color:var(--navy)"><?= $row['puntos'] ?></strong>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <div class="mt-3" style="font-size:.78rem;color:var(--gray-400)">
      PJ=Jugados · PG=Ganados · PP=Perdidos · GF=Games favor · GC=Games contra · +/-=Diferencia · Pts=Puntos (3 por victoria)
    </div>

    <?php else: ?>
    <div class="card card-body text-center" style="padding:3rem">
      <p style="color:var(--gray-400)">No hay datos de clasificación disponibles.</p>
    </div>
    <?php endif; ?>

  </div>
</section>

<?php
// Pasar todos los partidos al JS como mapa equipo_id → partidos[]
$partidos_js = json_encode($partidos_liga, JSON_UNESCAPED_UNICODE);
?>

<!-- Modal historial de partidos -->
<div id="modalHistorial" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.55);z-index:99999;align-items:flex-start;justify-content:center;padding:1rem;overflow-y:auto"
     onclick="if(event.target===this)cerrarHistorial()">
  <div style="background:#fff;border-radius:16px;width:100%;max-width:500px;margin:2rem auto;overflow:hidden;box-shadow:0 20px 60px rgba(0,0,0,.3)">
    <!-- Header -->
    <div style="background:var(--navy);padding:1.1rem 1.4rem;display:flex;justify-content:space-between;align-items:center">
      <div>
        <div id="modalEquipoNombre" style="font-family:var(--font-head);font-size:1.05rem;color:var(--gold);text-transform:uppercase;letter-spacing:.05em"></div>
        <div id="modalEquipoSub" style="font-size:.75rem;color:rgba(255,255,255,.6);margin-top:.1rem"></div>
      </div>
      <button onclick="cerrarHistorial()" style="background:none;border:none;color:rgba(255,255,255,.7);font-size:1.6rem;cursor:pointer;line-height:1;padding:0 .25rem">&times;</button>
    </div>
    <!-- Contenido -->
    <div id="modalHistorialBody" style="padding:1rem 1.25rem;max-height:70vh;overflow-y:auto"></div>
  </div>
</div>

<script>
const _partidos = <?= $partidos_js ?>;

function verPartidos(equipoId, nombre, rowEl) {
  const partidos = _partidos[equipoId] || [];
  const modal    = document.getElementById('modalHistorial');
  const body     = document.getElementById('modalHistorialBody');

  document.getElementById('modalEquipoNombre').textContent = nombre;
  document.getElementById('modalEquipoSub').textContent =
    partidos.length > 0 ? partidos.length + ' partido(s) jugado(s)' : 'Sin partidos jugados aún';

  if (partidos.length === 0) {
    body.innerHTML = '<p style="text-align:center;color:var(--gray-400);padding:2rem 0">Sin partidos jugados todavía.</p>';
  } else {
    body.innerHTML = partidos.map(p => {
      const esLocal   = p.equipo_local_id == equipoId;
      const miSets    = esLocal ? p.sets_local    : p.sets_visitante;
      const rivalSets = esLocal ? p.sets_visitante : p.sets_local;
      const gane      = p.ganador_id == equipoId;
      const rival     = esLocal ? p.visitante_nombre : p.local_nombre;

      // Construir sets detallados
      const setsPares = [
        [p.games_s1_local, p.games_s1_visitante],
        [p.games_s2_local, p.games_s2_visitante],
        [p.games_s3_local, p.games_s3_visitante],
      ].filter(([a,b]) => a !== null && b !== null);

      const setsStr = setsPares.map(([a,b]) => {
        const miG    = esLocal ? a : b;
        const rivalG = esLocal ? b : a;
        return `<span style="font-size:.8rem;background:${miG>rivalG?'#dcfce7':'#fee2e2'};color:${miG>rivalG?'#166534':'#991b1b'};border-radius:4px;padding:1px 6px">${miG}-${rivalG}</span>`;
      }).join(' ');

      const fecha = p.fecha_jugado
        ? new Date(p.fecha_jugado.replace(' ','T')).toLocaleDateString('es-CL',{day:'2-digit',month:'short',year:'numeric'})
        : (p.jornada ? 'J' + p.jornada : '—');

      const colorBorde = gane ? 'var(--green)' : 'var(--red)';
      const resultado  = gane ? 'Victoria' : 'Derrota';
      const emoji      = gane ? '✅' : '❌';

      return `
        <div style="display:flex;align-items:center;gap:1rem;padding:.75rem 0;border-bottom:1px solid var(--gray-100)">
          <div style="width:4px;align-self:stretch;background:${colorBorde};border-radius:4px;flex-shrink:0"></div>
          <div style="flex:1;min-width:0">
            <div style="display:flex;align-items:center;gap:.4rem;margin-bottom:.25rem">
              <span style="font-size:.7rem;font-weight:700;color:${colorBorde};text-transform:uppercase">${emoji} ${resultado}</span>
              <span style="font-size:.7rem;color:var(--gray-400)">${fecha}</span>
            </div>
            <div style="font-weight:700;font-size:.88rem;color:var(--navy);white-space:nowrap;overflow:hidden;text-overflow:ellipsis">vs ${rival}</div>
            <div style="margin-top:.3rem;display:flex;gap:.3rem;flex-wrap:wrap">${setsStr}</div>
          </div>
          <div style="text-align:right;flex-shrink:0">
            <div style="font-size:1.4rem;font-weight:900;color:${colorBorde};line-height:1">${miSets}-${rivalSets}</div>
            <div style="font-size:.65rem;color:var(--gray-400);text-transform:uppercase">Sets</div>
          </div>
        </div>`;
    }).join('');
  }

  modal.style.display = 'flex';
  document.body.style.overflow = 'hidden';
}

function cerrarHistorial() {
  document.getElementById('modalHistorial').style.display = 'none';
  document.body.style.overflow = '';
}

document.addEventListener('keydown', e => { if (e.key === 'Escape') cerrarHistorial(); });
</script>

<?php require_once 'includes/footer.php'; ?>
