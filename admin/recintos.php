<?php
$page_title = 'Admin — Recintos';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
epl_require_admin();

$db     = epl_db();
$_flash = epl_flash_get();
$ok     = ($_flash && $_flash['tipo']==='ok') ? $_flash['msg'] : '';
$err    = '';

// ── POST actions ──────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'crear') {
        $nombre  = trim($_POST['nombre']      ?? '');
        $sup     = (int)($_POST['superior_id'] ?? 0) ?: null;
        $dir     = trim($_POST['direccion']   ?? '') ?: null;
        $maps    = trim($_POST['url_maps']    ?? '') ?: null;
        if (!$nombre) { $err = 'El nombre es obligatorio.'; }
        else {
            $db->prepare("INSERT INTO recintos (nombre,superior_id,direccion,url_maps) VALUES (?,?,?,?)")
               ->execute([$nombre, $sup, $dir, $maps]);
            epl_redirect_ok('Recinto '.htmlspecialchars($nombre).' creado.');
        }

    } elseif ($action === 'editar') {
        $id     = (int)($_POST['id']           ?? 0);
        $nombre = trim($_POST['nombre']        ?? '');
        $sup    = (int)($_POST['superior_id']  ?? 0) ?: null;
        $dir    = trim($_POST['direccion']     ?? '') ?: null;
        $maps   = trim($_POST['url_maps']      ?? '') ?: null;
        if (!$nombre) { $err = 'El nombre es obligatorio.'; }
        elseif ($sup === $id) { $err = 'Un recinto no puede ser su propio superior.'; }
        else {
            $db->prepare("UPDATE recintos SET nombre=?,superior_id=?,direccion=?,url_maps=? WHERE id=?")
               ->execute([$nombre, $sup, $dir, $maps, $id]);
            epl_redirect_ok('Recinto actualizado.');
        }

    } elseif ($action === 'eliminar') {
        $id = (int)($_POST['id'] ?? 0);
        // Liberar partidos y sub-recintos antes de borrar
        $db->prepare("UPDATE partidos  SET recinto_id=NULL  WHERE recinto_id=?")->execute([$id]);
        $db->prepare("UPDATE recintos  SET superior_id=NULL WHERE superior_id=?")->execute([$id]);
        $db->prepare("DELETE FROM recintos WHERE id=?")->execute([$id]);
        epl_redirect_ok('Recinto eliminado.');
    }
}

// ── Cargar árbol de recintos ──────────────────────────────
$todos = $db->query("
    SELECT r.*,
           s.nombre AS superior_nombre,
           COUNT(DISTINCT p.id)  AS total_partidos,
           COUNT(DISTINCT h.id)  AS total_hijos
    FROM recintos r
    LEFT JOIN recintos s ON s.id = r.superior_id
    LEFT JOIN partidos p ON p.recinto_id = r.id
    LEFT JOIN recintos h ON h.superior_id = r.id
    GROUP BY r.id
    ORDER BY COALESCE(r.superior_id, r.id), r.superior_id IS NULL DESC, r.nombre ASC
")->fetchAll();

// Separar raíces e hijos para render en árbol
$roots    = [];
$children = []; // superior_id => [recintos]
foreach ($todos as $r) {
    if (!$r['superior_id']) $roots[] = $r;
    else $children[$r['superior_id']][] = $r;
}

// Para el select de "superior": árbol jerárquico aplanado
$_sr_raw = $db->query("SELECT id, nombre, superior_id FROM recintos ORDER BY nombre")->fetchAll();
$_sr_roots = []; $_sr_children = [];
foreach ($_sr_raw as $_r) {
    if (!$_r['superior_id']) $_sr_roots[] = $_r;
    else $_sr_children[$_r['superior_id']][] = $_r;
}
$todos_recintos = [];
function _flatRecintos(array $nodes, array $children, int $depth, array &$out): void {
    foreach ($nodes as $n) {
        $prefix = $depth === 0 ? '🏛 ' : ($depth === 1 ? '   📍 ' : '      🎾 ');
        $out[] = ['id' => $n['id'], 'label' => $prefix . $n['nombre'], 'depth' => $depth];
        if (isset($children[$n['id']])) _flatRecintos($children[$n['id']], $children, $depth + 1, $out);
    }
}
_flatRecintos($_sr_roots, $_sr_children, 0, $todos_recintos);
?>
<?php require_once '../includes/header.php'; ?>

<div class="dash-layout">
  <?php include __DIR__ . '/partials/sidebar.php'; ?>

  <main class="dash-main">
    <div class="dash-header flex justify-between items-center" style="flex-wrap:wrap;gap:.75rem">
      <div>
        <div style="font-size:.78rem;color:var(--gray-400);margin-bottom:.25rem">
          <a href="ligas.php" style="color:var(--gold)">Ligas y Torneos</a> › Recintos
        </div>
        <h1 class="dash-title">Recintos</h1>
      </div>
      <div style="display:flex;gap:.5rem;flex-wrap:wrap;width:100%;max-width:max-content">
        <button onclick="document.getElementById('modalCrear').style.display='flex'" class="btn btn-primary btn-sm" style="flex:1;text-align:center">+ Nuevo recinto</button>
      </div>
    </div>

    <?php if ($ok): ?><div class="alert alert-success"><?= $ok ?></div><?php endif; ?>
    <?php if ($err): ?><div class="alert alert-error"><?= epl_h($err) ?></div><?php endif; ?>

    <!-- Ayuda -->
    <div class="alert alert-info" style="font-size:.83rem">
      <strong>Jerarquía:</strong> Crea primero el <strong>club o complejo</strong> (sin superior), luego las <strong>sedes</strong> (superior = club) y finalmente las <strong>canchas</strong> (superior = sede o club). Los partidos se asignan a la cancha específica.
    </div>

    <!-- Árbol de recintos -->
    <?php if (empty($roots)): ?>
      <div class="card" style="padding:3rem;text-align:center;color:var(--gray-400)">
        No hay recintos creados aún. Comienza creando un club o complejo.
      </div>
    <?php else: ?>

    <div class="card">
      <div style="overflow-x:auto">
        <table class="admin-table-cards" style="width:100%;border-collapse:collapse;font-size:.84rem">
          <thead>
            <tr style="background:var(--navy);color:#fff">
              <th style="padding:.7rem 1rem;text-align:left;font-size:.7rem;text-transform:uppercase">Nombre</th>
              <th class="hide-mobile" style="padding:.7rem 1rem;text-align:left;font-size:.7rem;text-transform:uppercase">Superior</th>
              <th class="hide-mobile" style="padding:.7rem 1rem;text-align:left;font-size:.7rem;text-transform:uppercase">Dirección</th>
              <th class="hide-mobile" style="padding:.7rem 1rem;text-align:center;font-size:.7rem;text-transform:uppercase">Sub-recintos</th>
              <th class="hide-mobile" style="padding:.7rem 1rem;text-align:center;font-size:.7rem;text-transform:uppercase">Partidos</th>
              <th style="padding:.7rem 1rem;font-size:.7rem;text-transform:uppercase"></th>
            </tr>
          </thead>
          <tbody>
            <?php
            function renderRecinto(array $r, array $children, int $depth = 0): void {
                $indent = str_repeat('', $depth); // blank, handled via CSS padding
                $prefix = $depth === 0 ? '🏛 ' : ($depth === 1 ? '📍 ' : '🎾 ');
                $pad    = $depth * 1.5;
            ?>
            <tr style="border-bottom:1px solid var(--gray-100);<?= $depth>0?'background:var(--gray-50)':'' ?>">
              <td data-label="Nombre" style="padding:.65rem 1rem;padding-left:<?= 1 + $pad ?>rem">
                <span style="font-weight:<?= $depth===0?'700':'600' ?>;color:var(--navy)">
                  <?= $prefix . epl_h($r['nombre']) ?>
                </span>
              </td>
              <td data-label="Superior" class="hide-mobile" style="padding:.65rem 1rem;font-size:.8rem;color:var(--gray-500)">
                <?= $r['superior_nombre'] ? epl_h($r['superior_nombre']) : '<span style="color:var(--gray-300)">—</span>' ?>
              </td>
              <td data-label="Dirección" class="hide-mobile" style="padding:.65rem 1rem;font-size:.8rem;color:var(--gray-600)">
                <?php if ($r['direccion']): ?>
                  <?= epl_h($r['direccion']) ?>
                  <?php if ($r['url_maps']): ?>
                    <a href="<?= epl_h($r['url_maps']) ?>" target="_blank" style="color:var(--gold);font-size:.72rem;display:block">Ver mapa ↗</a>
                  <?php endif; ?>
                <?php else: ?>
                  <span style="color:var(--gray-300)">—</span>
                <?php endif; ?>
              </td>
              <td data-label="Sub-recintos" class="hide-mobile" style="padding:.65rem 1rem;text-align:center;color:var(--gray-500)">
                <?= $r['total_hijos'] ?: '—' ?>
              </td>
              <td data-label="Partidos" class="hide-mobile" style="padding:.65rem 1rem;text-align:center">
                <?php if ($r['total_partidos'] > 0): ?>
                  <span style="font-weight:700;color:var(--navy)"><?= $r['total_partidos'] ?></span>
                <?php else: ?>
                  <span style="color:var(--gray-300)">—</span>
                <?php endif; ?>
              </td>
              <td data-label="Acciones" style="padding:.65rem 1rem">
                <div style="display:flex;gap:.35rem;justify-content:flex-end">
                  <button onclick='showEditar(<?= json_encode($r) ?>)'
                          class="btn btn-sm" style="border:1px solid var(--gold);color:var(--gold-dark);font-size:.7rem">Editar</button>
                  <?php if ($r['total_hijos'] == 0 && $r['total_partidos'] == 0): ?>
                  <form method="post" style="display:inline">
                    <input type="hidden" name="action" value="eliminar">
                    <input type="hidden" name="id" value="<?= $r['id'] ?>">
                    <button type="submit" class="btn btn-sm" style="border:1px solid #dc2626;color:#dc2626;font-size:.7rem"
                            data-confirm="¿Eliminar <?= epl_h($r['nombre']) ?>?" data-confirm-ok="Eliminar">Eliminar</button>
                  </form>
                  <?php endif; ?>
                </div>
              </td>
            </tr>
            <?php
                if (isset($children[$r['id']])) {
                    foreach ($children[$r['id']] as $child) {
                        renderRecinto($child, $children, $depth + 1);
                    }
                }
            }
            foreach ($roots as $root) renderRecinto($root, $children);
            ?>
          </tbody>
        </table>
      </div>
    </div>
    <?php endif; ?>

  </main>
</div>

<!-- Modal crear -->
<div id="modalCrear" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:99999;align-items:center;justify-content:center;padding:1rem">
  <div class="card" style="width:100%;max-width:480px">
    <div class="card-head">
      <h3 style="font-family:var(--font-head);font-size:1rem;text-transform:uppercase;color:var(--navy)">Nuevo Recinto</h3>
      <button onclick="document.getElementById('modalCrear').style.display='none'" style="background:none;font-size:1.5rem;color:var(--gray-400)">×</button>
    </div>
    <div class="card-body">
      <form method="post">
        <input type="hidden" name="action" value="crear">
        <div class="form-group">
          <label class="form-label">Nombre *</label>
          <input type="text" name="nombre" class="form-control" required placeholder="Club de Padel Las Condes / Cancha 1">
        </div>
        <div class="form-group">
          <label class="form-label">Superior <span style="font-weight:400;text-transform:none;font-size:.78rem;color:var(--gray-400)">(dejar vacío si es el nivel principal)</span></label>
          <select name="superior_id" class="form-control">
            <option value="">— Sin superior (nivel raíz) —</option>
            <?php foreach ($todos_recintos as $r): ?>
              <option value="<?= $r['id'] ?>" data-id="<?= $r['id'] ?>"><?= epl_h($r['label']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Dirección</label>
          <input type="text" name="direccion" class="form-control" placeholder="Av. Isidora Goyenechea 3000, Las Condes">
        </div>
        <div class="form-group">
          <label class="form-label">URL Google Maps</label>
          <input type="url" name="url_maps" class="form-control" placeholder="https://maps.google.com/...">
        </div>
        <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center">Crear recinto</button>
      </form>
    </div>
  </div>
</div>

<!-- Modal editar -->
<div id="modalEditar" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:99999;align-items:center;justify-content:center;padding:1rem">
  <div class="card" style="width:100%;max-width:480px">
    <div class="card-head">
      <h3 style="font-family:var(--font-head);font-size:1rem;text-transform:uppercase;color:var(--navy)" id="editarTitle">Editar Recinto</h3>
      <button onclick="document.getElementById('modalEditar').style.display='none'" style="background:none;font-size:1.5rem;color:var(--gray-400)">×</button>
    </div>
    <div class="card-body">
      <form method="post">
        <input type="hidden" name="action" value="editar">
        <input type="hidden" name="id" id="editarId">
        <div class="form-group">
          <label class="form-label">Nombre *</label>
          <input type="text" name="nombre" id="editarNombre" class="form-control" required>
        </div>
        <div class="form-group">
          <label class="form-label">Superior</label>
          <select name="superior_id" id="editarSuperior" class="form-control">
            <option value="">— Sin superior (nivel raíz) —</option>
            <?php foreach ($todos_recintos as $r): ?>
              <option value="<?= $r['id'] ?>" data-id="<?= $r['id'] ?>"><?= str_repeat('  ', $r['superior_id'] ? 1 : 0) . epl_h($r['nombre']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Dirección</label>
          <input type="text" name="direccion" id="editarDireccion" class="form-control">
        </div>
        <div class="form-group">
          <label class="form-label">URL Google Maps</label>
          <input type="url" name="url_maps" id="editarMaps" class="form-control">
        </div>
        <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center">Guardar cambios</button>
      </form>
    </div>
  </div>
</div>

<script>
function showEditar(r) {
  document.getElementById('editarId').value        = r.id;
  document.getElementById('editarTitle').textContent = 'Editar — ' + r.nombre.toUpperCase();
  document.getElementById('editarNombre').value    = r.nombre    || '';
  document.getElementById('editarDireccion').value = r.direccion || '';
  document.getElementById('editarMaps').value      = r.url_maps  || '';
  // Seleccionar superior, excluyendo el propio recinto
  const sel = document.getElementById('editarSuperior');
  Array.from(sel.options).forEach(opt => {
    opt.disabled = opt.dataset.id == r.id; // no puede ser su propio padre
  });
  sel.value = r.superior_id || '';
  document.getElementById('modalEditar').style.display = 'flex';
}
</script>

<?php require_once '../includes/footer.php'; ?>
