<?php
$page_title = 'Admin — Partidos';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
epl_require_admin();

$db  = epl_db();
$ok  = '';
$err = '';

$ligas   = $db->query("SELECT * FROM ligas ORDER BY id DESC")->fetchAll();
$liga_id = isset($_GET['liga']) ? (int)$_GET['liga'] : 0; // 0 para todas

$f_est    = trim($_GET['estado_p'] ?? '');
$f_search = trim($_GET['search'] ?? '');
$f_desde  = trim($_GET['desde'] ?? '');
$f_hasta  = trim($_GET['hasta'] ?? '');
$f_fecha  = trim($_GET['fecha'] ?? '');

$where_p = "WHERE 1=1";
$params_p = [];

if ($liga_id) { $where_p .= " AND p.liga_id=?"; $params_p[] = $liga_id; }
if ($f_fecha)  { $where_p .= " AND p.nombre_fecha=?"; $params_p[] = $f_fecha; }
if ($f_est)    { $where_p .= " AND p.estado=?";        $params_p[] = $f_est;   }
if ($f_desde)  { $where_p .= " AND p.fecha_programada >= ?"; $params_p[] = $f_desde . ' 00:00:00'; }
if ($f_hasta)  { $where_p .= " AND p.fecha_programada <= ?"; $params_p[] = $f_hasta . ' 23:59:59'; }

if ($f_search) {
    $search_parts = explode(' ', $f_search);
    foreach ($search_parts as $part) {
        $part = trim($part);
        if ($part === '') continue;
        $where_p .= " AND (
            el.nombre LIKE ? OR ev.nombre LIKE ? OR
            jl1.nombre LIKE ? OR jl1.apellido LIKE ? OR
            jl2.nombre LIKE ? OR jl2.apellido LIKE ? OR
            jv1.nombre LIKE ? OR jv1.apellido LIKE ? OR
            jv2.nombre LIKE ? OR jv2.apellido LIKE ? OR
            l.nombre LIKE ?
        )";
        $p_val = "%$part%";
        array_push($params_p, $p_val, $p_val, $p_val, $p_val, $p_val, $p_val, $p_val, $p_val, $p_val, $p_val, $p_val);
    }
}

$partidos = $db->prepare("
    SELECT p.*, el.nombre AS local_nombre, ev.nombre AS visitante_nombre,
           l.nombre AS liga_nombre, r.nombre AS recinto_nombre
    FROM partidos p
    JOIN ligas l ON l.id = p.liga_id
    JOIN equipos el ON el.id = p.equipo_local_id
    JOIN equipos ev ON ev.id = p.equipo_visitante_id
    LEFT JOIN jugadores jl1 ON jl1.id = el.jugador1_id
    LEFT JOIN jugadores jl2 ON jl2.id = el.jugador2_id
    LEFT JOIN jugadores jv1 ON jv1.id = ev.jugador1_id
    LEFT JOIN jugadores jv2 ON jv2.id = ev.jugador2_id
    LEFT JOIN recintos r ON r.id = p.recinto_id
    $where_p
    ORDER BY p.jornada ASC, p.fecha_programada ASC, p.id ASC
");
$partidos->execute($params_p);
$partidos = $partidos->fetchAll();

// Fechas disponibles para el select si hay liga elegida
$fechas_disponibles = [];
if ($liga_id) {
    $stF = $db->prepare("SELECT nombre_fecha FROM partidos WHERE liga_id=? AND nombre_fecha IS NOT NULL GROUP BY nombre_fecha ORDER BY MAX(jornada) ASC");
    $stF->execute([$liga_id]);
    $fechas_disponibles = array_column($stF->fetchAll(), 'nombre_fecha');
}

// Para crear partidos
$todos_equipos = $db->query("SELECT * FROM equipos WHERE estado='activo' ORDER BY nombre")->fetchAll();

// Recintos full path
$_recintos_raw = $db->query("SELECT id, nombre, superior_id FROM recintos ORDER BY nombre")->fetchAll();
$_rec_roots = []; $_rec_children = [];
foreach ($_recintos_raw as $_r) {
    if (!$_r['superior_id']) $_rec_roots[] = $_r;
    else $_rec_children[$_r['superior_id']][] = $_r;
}
$todos_recintos = []; $map_recintos_full = [];
function _flattenRecintos(array $nodes, array $children, int $depth, array &$out, array &$map, string $path = ''): void {
    foreach ($nodes as $n) {
        $prefix = $depth === 0 ? '🏛 ' : ($depth === 1 ? '   📍 ' : '      🎾 ');
        $out[] = ['id' => $n['id'], 'label' => $prefix . $n['nombre'], 'depth' => $depth];
        $currentPath = $path ? $path . ' · ' . $n['nombre'] : $n['nombre'];
        $map[$n['id']] = $currentPath;
        if (isset($children[$n['id']])) _flattenRecintos($children[$n['id']], $children, $depth + 1, $out, $map, $currentPath);
    }
}
_flattenRecintos($_rec_roots, $_rec_children, 0, $todos_recintos, $map_recintos_full);

?>
<?php require_once '../includes/header.php'; ?>

<div class="dash-layout">
  <?php include __DIR__ . '/partials/sidebar.php'; ?>

  <main class="dash-main">
    <div class="dash-header flex justify-between items-center" style="flex-wrap:wrap;gap:.75rem">
      <h1 class="dash-title">Partidos</h1>
      <div style="display:flex;gap:.5rem;flex-wrap:wrap;width:100%;max-width:max-content">
        <button onclick="document.getElementById('modalCrearPartido').style.display='flex'"
                class="btn btn-primary" style="flex:1;text-align:center">+ Partido</button>
      </div>
    </div>

    <?php if ($ok): ?><div class="alert alert-success"><?= epl_h($ok) ?></div><?php endif; ?>
    <?php if ($err): ?><div class="alert alert-error"><?= epl_h($err) ?></div><?php endif; ?>

    <div class="card mb-3" style="padding:.75rem 1.25rem">
      <form method="get" style="display:flex;gap:.75rem;flex-wrap:wrap;align-items:flex-end">
        <div style="flex:1;min-width:180px">
          <label style="font-size:.7rem;font-weight:700;text-transform:uppercase;color:var(--navy);display:block;margin-bottom:.25rem">Liga / Torneo</label>
          <select name="liga" class="form-control" onchange="this.form.submit()">
            <option value="">Todas las Ligas</option>
            <?php foreach ($ligas as $l): ?>
              <option value="<?= $l['id'] ?>" <?= $l['id']==$liga_id?'selected':'' ?>><?= epl_h($l['nombre']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div style="flex:1;min-width:180px">
          <label style="font-size:.7rem;font-weight:700;text-transform:uppercase;color:var(--navy);display:block;margin-bottom:.25rem">Buscar Jugador / Equipo</label>
          <input type="text" name="search" class="form-control" placeholder="Ej: Perez Gomez" value="<?= epl_h($f_search) ?>">
        </div>
        <?php if ($liga_id && !empty($fechas_disponibles)): ?>
        <div>
          <label style="font-size:.7rem;font-weight:700;text-transform:uppercase;color:var(--navy);display:block;margin-bottom:.25rem">Jornada</label>
          <select name="fecha" class="form-control">
            <option value="">Todas</option>
            <?php foreach ($fechas_disponibles as $fn): ?>
              <option value="<?= epl_h($fn) ?>" <?= $f_fecha===$fn?'selected':'' ?>><?= epl_h($fn) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <?php endif; ?>
        <div>
          <label style="font-size:.7rem;font-weight:700;text-transform:uppercase;color:var(--navy);display:block;margin-bottom:.25rem">Desde</label>
          <input type="date" name="desde" class="form-control" value="<?= epl_h($f_desde) ?>">
        </div>
        <div>
          <label style="font-size:.7rem;font-weight:700;text-transform:uppercase;color:var(--navy);display:block;margin-bottom:.25rem">Hasta</label>
          <input type="date" name="hasta" class="form-control" value="<?= epl_h($f_hasta) ?>">
        </div>
        <div>
          <label style="font-size:.7rem;font-weight:700;text-transform:uppercase;color:var(--navy);display:block;margin-bottom:.25rem">Estado</label>
          <select name="estado_p" class="form-control">
            <option value="">Todos</option>
            <option value="pendiente"    <?= $f_est==='pendiente'    ?'selected':'' ?>>Pendiente</option>
            <option value="jugado"       <?= $f_est==='jugado'       ?'selected':'' ?>>Jugado</option>
            <option value="reprogramado" <?= $f_est==='reprogramado' ?'selected':'' ?>>Reprogramado</option>
            <option value="walkover"     <?= $f_est==='walkover'     ?'selected':'' ?>>Walkover</option>
          </select>
        </div>
        <button type="submit" class="btn btn-navy btn-sm">Filtrar</button>
        <?php if ($f_fecha || $f_est || $f_search || $f_desde || $f_hasta || $liga_id): ?>
          <a href="partidos.php" class="btn btn-sm" style="border:1px solid var(--gray-200);color:var(--gray-600)">Limpiar</a>
        <?php endif; ?>
      </form>
    </div>

    <div class="card">
      <div style="overflow-x:auto">
        <table class="admin-table-cards" style="width:100%;border-collapse:collapse;font-size:.85rem">
          <thead>
            <tr style="background:var(--navy);color:#fff">
              <th style="padding:.7rem 1rem;text-align:left;font-size:.72rem;text-transform:uppercase">Fecha / Jornada</th>
              <th class="hide-mobile" style="padding:.7rem 1rem;text-align:left;font-size:.72rem;text-transform:uppercase">Liga</th>
              <th style="padding:.7rem 1rem;text-align:left;font-size:.72rem;text-transform:uppercase">Local</th>
              <th style="padding:.7rem 1rem;text-align:center;font-size:.72rem;text-transform:uppercase">Resultado</th>
              <th style="padding:.7rem 1rem;text-align:right;font-size:.72rem;text-transform:uppercase">Visitante</th>
              <th style="padding:.7rem 1rem;text-align:center;font-size:.72rem;text-transform:uppercase">Estado</th>
              <th class="hide-mobile" style="padding:.7rem 1rem;text-align:center;font-size:.72rem;text-transform:uppercase">Cancha</th>
              <th style="padding:.7rem 1rem;text-align:center;font-size:.72rem;text-transform:uppercase">Editar</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($partidos)): ?>
            <tr><td colspan="8" style="padding:2rem;text-align:center;color:var(--gray-400)">No hay partidos en esta liga.</td></tr>
            <?php endif; ?>
            <?php foreach ($partidos as $p): ?>
            <tr style="border-bottom:1px solid var(--gray-100)">
              <td data-label="Fecha / Jornada" style="padding:.65rem 1rem">
                <div style="font-weight:700;color:var(--navy);font-size:.75rem">
                  <?= $p['fecha_programada'] ? date('d/m H:i', strtotime($p['fecha_programada'])) : '—' ?>
                </div>
                <div style="font-size:.65rem;color:var(--gray-400);text-transform:uppercase;margin-top:.15rem">
                  <?= epl_h($p['nombre_fecha'] ?: 'Jornada '.$p['jornada']) ?>
                </div>
              </td>
              <td data-label="Liga" class="hide-mobile" style="padding:.65rem 1rem;font-size:.75rem;color:var(--gray-600)">
                <?= epl_h($p['liga_nombre']) ?>
              </td>
              <td data-label="Local" style="padding:.65rem 1rem;font-weight:600;color:var(--navy)"><?= epl_h($p['local_nombre']) ?></td>
              <td data-label="Resultado" style="padding:.65rem 1rem;text-align:center">
                <?php if ($p['estado']==='jugado'): ?>
                  <span style="font-family:var(--font-head);font-size:1rem"><?= $p['sets_local'] ?>–<?= $p['sets_visitante'] ?></span>
                  <?php
                  $ss=[];
                  for($s=1;$s<=3;$s++){$gl=$p["games_s{$s}_local"];$gv=$p["games_s{$s}_visitante"];if($gl!==null)$ss[]="$gl-$gv";}
                  if($ss): ?><div style="font-size:.7rem;color:var(--gray-400)"><?= implode(' ',$ss) ?></div><?php endif; ?>
                <?php else: ?><span style="color:var(--gray-400)">—</span><?php endif; ?>
              </td>
              <td data-label="Visitante" style="padding:.65rem 1rem;font-weight:600;color:var(--navy);text-align:right"><?= epl_h($p['visitante_nombre']) ?></td>
              <td data-label="Estado" style="padding:.65rem 1rem;text-align:center">
                <?php $bc=match($p['estado']){'jugado'=>'badge-jugado','pendiente'=>'badge-pendiente','walkover'=>'badge-walkover',default=>'badge-reprog'}; ?>
                <span class="badge <?= $bc ?>"><?= $p['estado'] ?></span>
              </td>
              <td data-label="Cancha" class="hide-mobile" style="padding:.65rem 1rem;text-align:center;font-size:.72rem;color:var(--gray-500)">
                <?= epl_h($p['recinto_id'] ? ($map_recintos_full[$p['recinto_id']] ?? '—') : '—') ?>
              </td>
              <td data-label="Editar" style="padding:.65rem 1rem;text-align:center">
                <button onclick='editarPartido(<?= json_encode($p) ?>)' class="btn btn-sm" style="border:1px solid var(--gray-200);font-size:.72rem">Editar</button>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </main>
</div>

<!-- Modal editar resultado -->
<div id="modalEditarPartido" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:99999;align-items:center;justify-content:center">
  <div class="card" style="width:100%;max-width:500px;max-height:90vh;overflow-y:auto">
    <div class="card-head">
      <h3 id="editPartidoTitle" style="font-family:var(--font-head);font-size:1rem;text-transform:uppercase;color:var(--navy)">Editar Partido</h3>
      <button onclick="document.getElementById('modalEditarPartido').style.display='none'" style="background:none;font-size:1.5rem;color:var(--gray-400)">×</button>
    </div>
    <div class="card-body">
      <form method="post">
        <input type="hidden" name="action" value="editar_resultado">
        <input type="hidden" name="liga" value="<?= $liga_id ?>">
        <input type="hidden" name="partido_id" id="editPartidoId">

        <div class="form-group">
          <label class="form-label">Estado</label>
          <select name="estado" id="editEstado" class="form-control">
            <option value="pendiente">Pendiente</option>
            <option value="jugado">Jugado</option>
            <option value="reprogramado">Reprogramado</option>
            <option value="walkover">Walkover</option>
          </select>
        </div>

        <div class="form-group">
          <label class="form-label">Fecha jugado</label>
          <input type="datetime-local" name="fecha_jugado" id="editFecha" class="form-control">
        </div>

        <p style="font-size:.8rem;font-weight:700;text-transform:uppercase;color:var(--navy);margin-bottom:.75rem">Resultado (sets)</p>
        <?php for ($s=1;$s<=3;$s++): ?>
        <div class="score-input-row">
          <span class="score-label">Set <?= $s ?></span>
          <div class="score-fields">
            <input type="number" name="s<?= $s ?>_l" id="s<?= $s ?>l" class="score-num form-control" min="0" max="7" placeholder="0">
            <span class="score-sep">–</span>
            <input type="number" name="s<?= $s ?>_v" id="s<?= $s ?>v" class="score-num form-control" min="0" max="7" placeholder="0">
          </div>
        </div>
        <?php endfor; ?>

        <button type="submit" class="btn btn-primary mt-3" style="width:100%;justify-content:center">Guardar cambios</button>
      </form>
    </div>
  </div>
</div>

<!-- Modal crear partido -->
<div id="modalCrearPartido" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:99999;align-items:center;justify-content:center">
  <div class="card" style="width:100%;max-width:460px">
    <div class="card-head">
      <h3 style="font-family:var(--font-head);font-size:1rem;text-transform:uppercase;color:var(--navy)">Nuevo Partido</h3>
      <button onclick="document.getElementById('modalCrearPartido').style.display='none'" style="background:none;font-size:1.5rem;color:var(--gray-400)">×</button>
    </div>
    <div class="card-body">
      <form method="post">
        <input type="hidden" name="action" value="crear_partido">
        <input type="hidden" name="liga" value="<?= $liga_id ?>">
        <div class="form-group">
          <label class="form-label">Equipo local</label>
          <select name="equipo_local_id" class="form-control" required>
            <option value="">— Selecciona —</option>
            <?php foreach ($todos_equipos as $e): ?>
              <option value="<?= $e['id'] ?>"><?= epl_h($e['nombre']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Equipo visitante</label>
          <select name="equipo_visitante_id" class="form-control" required>
            <option value="">— Selecciona —</option>
            <?php foreach ($todos_equipos as $e): ?>
              <option value="<?= $e['id'] ?>"><?= epl_h($e['nombre']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="grid-2">
          <div class="form-group">
            <label class="form-label">Nombre de fecha</label>
            <input type="text" name="nombre_fecha" class="form-control" placeholder="Fecha 1, Cuartos...">
          </div>
          <div class="form-group">
            <label class="form-label">Jornada N°</label>
            <input type="number" name="jornada" class="form-control" min="1" placeholder="1">
          </div>
        </div>
        <div class="grid-2">
          <div class="form-group">
            <label class="form-label">Cancha</label>
            <input type="text" name="cancha" class="form-control" placeholder="Cancha 1">
          </div>
          <div class="form-group">
            <label class="form-label">Fecha programada</label>
            <input type="datetime-local" name="fecha_programada" class="form-control">
          </div>
        </div>
        <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center">Crear partido</button>
      </form>
    </div>
  </div>
</div>

<script>
function editarPartido(p) {
  document.getElementById('editPartidoId').value  = p.id;
  document.getElementById('editPartidoTitle').textContent = p.local_nombre + ' vs ' + p.visitante_nombre;
  document.getElementById('editEstado').value = p.estado;
  const fd = p.fecha_jugado || p.fecha_programada;
  document.getElementById('editFecha').value = fd ? fd.replace(' ','T').substring(0,16) : '';
  [1,2,3].forEach(s => {
    const l = p['games_s'+s+'_local'];
    const v = p['games_s'+s+'_visitante'];
    document.getElementById('s'+s+'l').value = l ?? '';
    document.getElementById('s'+s+'v').value = v ?? '';
  });
  document.getElementById('modalEditarPartido').style.display = 'flex';
}
</script>

<?php require_once '../includes/footer.php'; ?>
