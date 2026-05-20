<?php
$page_title = 'Admin — Dashboard';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
epl_require_admin();

$db = epl_db();

$totals = [
    'jugadores' => $db->query("SELECT COUNT(*) FROM jugadores WHERE estado='activo'")->fetchColumn(),
    'equipos'   => $db->query("SELECT COUNT(*) FROM equipos WHERE estado='activo'")->fetchColumn(),
    'partidos'  => $db->query("SELECT COUNT(*) FROM partidos")->fetchColumn(),
    'jugados'   => $db->query("SELECT COUNT(*) FROM partidos WHERE estado='jugado'")->fetchColumn(),
    'pendientes'=> $db->query("SELECT COUNT(*) FROM partidos WHERE estado='pendiente'")->fetchColumn(),
    'reprog'       => $db->query("SELECT COUNT(*) FROM solicitudes_reprogramacion WHERE estado='pendiente'")->fetchColumn(),
    'inscripciones'=> $db->query("SELECT COUNT(*) FROM inscripciones WHERE estado='pendiente'")->fetchColumn(),
];

$solicitudes = $db->query("
    SELECT sr.*, j.nombre AS sol_nombre, j.apellido AS sol_apellido,
           el.nombre AS local_nombre, ev.nombre AS visitante_nombre
    FROM solicitudes_reprogramacion sr
    JOIN jugadores j ON j.id = sr.solicitante_id
    JOIN partidos p  ON p.id = sr.partido_id
    JOIN equipos el  ON el.id = p.equipo_local_id
    JOIN equipos ev  ON ev.id = p.equipo_visitante_id
    WHERE sr.estado = 'pendiente'
    ORDER BY sr.created_at DESC
")->fetchAll();

// Árbol de recintos para el select del modal
$_rec_raw = $db->query("SELECT id, nombre, superior_id FROM recintos ORDER BY nombre")->fetchAll();
$_rec_roots = []; $_rec_children = [];
foreach ($_rec_raw as $_r) {
    if (!$_r['superior_id']) $_rec_roots[] = $_r;
    else $_rec_children[$_r['superior_id']][] = $_r;
}
$recintos_select = [];
function _flatRecSelect(array $nodes, array $children, int $depth, array &$out): void {
    foreach ($nodes as $n) {
        $pad   = str_repeat('　', $depth); // espacio ideográfico para indentar
        $icon  = $depth === 0 ? '🏛 ' : ($depth === 1 ? '📍 ' : '🎾 ');
        $out[] = ['id' => $n['id'], 'label' => $pad . $icon . $n['nombre'], 'depth' => $depth];
        if (isset($children[$n['id']])) _flatRecSelect($children[$n['id']], $children, $depth + 1, $out);
    }
}
_flatRecSelect($_rec_roots, $_rec_children, 0, $recintos_select);
?>
<?php require_once '../includes/header.php'; ?>

<div class="dash-layout">
  <?php include __DIR__ . '/partials/sidebar.php'; ?>

  <main class="dash-main">
    <div class="dash-header">
      <h1 class="dash-title">Panel de Administración</h1>
    </div>

    <!-- Stats -->
    <div class="stats-grid">
      <div class="stat-card">
        <div class="stat-value"><?= $totals['jugadores'] ?></div>
        <div class="stat-label">Jugadores activos</div>
      </div>
      <div class="stat-card">
        <div class="stat-value"><?= $totals['equipos'] ?></div>
        <div class="stat-label">Equipos</div>
      </div>
      <div class="stat-card">
        <div class="stat-value"><?= $totals['partidos'] ?></div>
        <div class="stat-label">Total partidos</div>
      </div>
      <div class="stat-card">
        <div class="stat-value" style="color:var(--green)"><?= $totals['jugados'] ?></div>
        <div class="stat-label">Jugados</div>
      </div>
      <div class="stat-card">
        <div class="stat-value" style="color:var(--gold)"><?= $totals['pendientes'] ?></div>
        <div class="stat-label">Pendientes</div>
      </div>
      <div class="stat-card">
        <div class="stat-value" style="color:var(--red)"><?= $totals['reprog'] ?></div>
        <div class="stat-label">Reprog. pendientes</div>
      </div>
      <div class="stat-card">
        <div class="stat-value" style="color:var(--gold)"><?= $totals['inscripciones'] ?></div>
        <div class="stat-label">Inscripciones pend.</div>
      </div>
    </div>

    <!-- Test de notificaciones -->
    <div class="card mb-4">
      <div class="card-head">
        <h3 style="font-family:var(--font-head);font-size:1rem;text-transform:uppercase;color:var(--navy)">🔔 Probar notificaciones</h3>
      </div>
      <div class="card-body" style="display:flex;align-items:center;gap:1.25rem;flex-wrap:wrap">
        <div style="flex:1;min-width:200px">
          <p style="font-size:.85rem;color:var(--gray-500);margin:0">
            Envía una notificación de prueba a tu propio dispositivo para verificar que el sistema funciona.
            Debes tener la app instalada y haber aceptado los permisos de notificación.
          </p>
        </div>
        <button id="btnTestPush" onclick="testPush()" class="btn btn-primary" style="flex-shrink:0;gap:.5rem">
          <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
          Enviar notificación de prueba
        </button>
        <div id="pushResult" style="display:none;font-size:.85rem;font-weight:600;padding:.5rem 1rem;border-radius:8px"></div>
      </div>
    </div>

    <!-- Solicitudes de reprogramación pendientes -->
    <?php if ($solicitudes): ?>
    <div class="card">
      <div class="card-head">
        <h3 style="font-family:var(--font-head);font-size:1rem;text-transform:uppercase;color:var(--navy)">
          Solicitudes de reprogramación <span style="color:var(--red)">(<?= count($solicitudes) ?>)</span>
        </h3>
      </div>
      <div class="card-body">
        <?php foreach ($solicitudes as $s): ?>
        <div style="padding:.85rem 0;border-bottom:1px solid var(--gray-100)">
          <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:1rem">
            <div>
              <div style="font-weight:700;font-size:.9rem;color:var(--navy)">
                <?= epl_h($s['local_nombre'].' vs '.$s['visitante_nombre']) ?>
              </div>
              <div style="font-size:.78rem;color:var(--gray-600);margin-top:.2rem">
                Solicitado por: <?= epl_h($s['sol_nombre'].' '.$s['sol_apellido']) ?> •
                Fecha propuesta: <?= $s['fecha_propuesta'] ? date('d/m/Y H:i', strtotime($s['fecha_propuesta'])) : '—' ?>
              </div>
              <div style="font-size:.78rem;color:var(--gray-600)">Motivo: <?= epl_h($s['motivo']) ?></div>
            </div>
            <div style="display:flex;gap:.5rem;flex-shrink:0;flex-direction:column;align-items:flex-end">
              <?php if ($s['rival_no_responde']): ?>
                <span class="badge badge-walkover" style="font-size:.65rem">⚠ Rival no respondió</span>
              <?php endif; ?>
              <div style="display:flex;gap:.4rem">
                <button onclick="showAprobar(<?= $s['id'] ?>, '<?= epl_h($s['fecha_propuesta']) ?>')"
                        class="btn btn-primary btn-sm">Aprobar</button>
                <form method="post" action="api_reprogramacion.php" style="display:inline">
                  <input type="hidden" name="id" value="<?= $s['id'] ?>">
                  <input type="hidden" name="accion" value="rechazar">
                  <button type="submit" class="btn btn-sm" style="border:2px solid var(--red);color:var(--red)">Rechazar</button>
                </form>
              </div>
            </div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

    <!-- Accesos rápidos -->
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:1rem;margin-top:2rem">
      <a href="jugadores.php" class="card card-body text-center" style="text-decoration:none">
        <div style="font-family:var(--font-head);font-size:1.5rem;color:var(--navy);text-transform:uppercase">Jugadores</div>
        <div style="font-size:.8rem;color:var(--gray-400);margin-top:.25rem">Gestionar jugadores</div>
      </a>
      <a href="equipos.php" class="card card-body text-center" style="text-decoration:none">
        <div style="font-family:var(--font-head);font-size:1.5rem;color:var(--navy);text-transform:uppercase">Equipos</div>
        <div style="font-size:.8rem;color:var(--gray-400);margin-top:.25rem">Gestionar equipos</div>
      </a>
      <a href="partidos.php" class="card card-body text-center" style="text-decoration:none">
        <div style="font-family:var(--font-head);font-size:1.5rem;color:var(--navy);text-transform:uppercase">Partidos</div>
        <div style="font-size:.8rem;color:var(--gray-400);margin-top:.25rem">Gestionar partidos</div>
      </a>
      <a href="inscripciones.php" class="card card-body text-center" style="text-decoration:none">
        <div style="font-family:var(--font-head);font-size:1.5rem;color:var(--navy);text-transform:uppercase">Inscripciones</div>
        <div style="font-size:.8rem;color:var(--gray-400);margin-top:.25rem">Aprobar inscripciones</div>
      </a>
      <a href="ligas.php" class="card card-body text-center" style="text-decoration:none">
        <div style="font-family:var(--font-head);font-size:1.5rem;color:var(--navy);text-transform:uppercase">Ligas</div>
        <div style="font-size:.8rem;color:var(--gray-400);margin-top:.25rem">Gestionar ligas</div>
      </a>
      <a href="dashboard_repro.php" class="card card-body text-center" style="text-decoration:none">
        <div style="font-family:var(--font-head);font-size:1.5rem;color:var(--navy);text-transform:uppercase">Reprog.</div>
        <div style="font-size:.8rem;color:var(--gray-400);margin-top:.25rem">Reprogramaciones</div>
      </a>
      <a href="suplentes.php" class="card card-body text-center" style="text-decoration:none">
        <div style="font-family:var(--font-head);font-size:1.5rem;color:var(--navy);text-transform:uppercase">Suplentes</div>
        <div style="font-size:.8rem;color:var(--gray-400);margin-top:.25rem">Gestionar suplentes</div>
      </a>
      <a href="recintos.php" class="card card-body text-center" style="text-decoration:none">
        <div style="font-family:var(--font-head);font-size:1.5rem;color:var(--navy);text-transform:uppercase">Sedes</div>
        <div style="font-size:.8rem;color:var(--gray-400);margin-top:.25rem">Gestionar recintos</div>
      </a>
      <a href="../clasificacion.php" class="card card-body text-center" style="text-decoration:none">
        <div style="font-family:var(--font-head);font-size:1.5rem;color:var(--navy);text-transform:uppercase">Tablas</div>
        <div style="font-size:.8rem;color:var(--gray-400);margin-top:.25rem">Ver clasificación</div>
      </a>
      <a href="../resultados.php" class="card card-body text-center" style="text-decoration:none">
        <div style="font-family:var(--font-head);font-size:1.5rem;color:var(--navy);text-transform:uppercase">Resultados</div>
        <div style="font-size:.8rem;color:var(--gray-400);margin-top:.25rem">Ver resultados</div>
      </a>
    </div>

  </main>
</div>

<!-- Modal aprobar reprogramación -->
<div id="modalAprobar" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:99999;align-items:center;justify-content:center;padding:1rem">
  <div class="card" style="width:100%;max-width:420px">
    <div class="card-head">
      <h3 style="font-family:var(--font-head);font-size:1rem;text-transform:uppercase;color:var(--navy)">Aprobar Reprogramación</h3>
      <button onclick="document.getElementById('modalAprobar').style.display='none'" style="background:none;font-size:1.5rem;color:var(--gray-400)">×</button>
    </div>
    <div class="card-body">
      <form method="post" action="api_reprogramacion.php">
        <input type="hidden" name="accion" value="aprobar">
        <input type="hidden" name="id" id="aprobarId">
        <div class="form-group">
          <label class="form-label">Fecha y hora definitiva</label>
          <input type="datetime-local" name="fecha_aprobada" id="aprobarFecha" class="form-control" required>
        </div>
        <div class="form-group">
          <label class="form-label">Cancha / Recinto</label>
          <select name="cancha_aprobada" id="aprobarCancha" class="form-control"
                  onchange="toggleOtraCancha(this.value)">
            <option value="">— Sin cancha asignada —</option>
            <?php foreach ($recintos_select as $rec): ?>
            <option value="<?= epl_h($rec['label']) ?>"
                    <?= $rec['depth'] === 0 ? 'style="font-weight:700"' : '' ?>>
              <?= epl_h($rec['label']) ?>
            </option>
            <?php endforeach; ?>
            <option value="__otro__">✏️ Escribir manualmente…</option>
          </select>
          <input type="text" id="otraCanchaInput" name="cancha_aprobada_manual"
                 class="form-control" placeholder="Escribe el nombre de la cancha"
                 style="display:none;margin-top:.5rem">
        </div>
        <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center">Confirmar aprobación</button>
      </form>
    </div>
  </div>
</div>

<script>
function showAprobar(id, fechaPropuesta) {
  document.getElementById('aprobarId').value = id;
  if (fechaPropuesta) {
    document.getElementById('aprobarFecha').value = fechaPropuesta.substring(0, 16);
  }
  // Resetear cancha
  document.getElementById('aprobarCancha').value = '';
  document.getElementById('otraCanchaInput').style.display = 'none';
  document.getElementById('otraCanchaInput').value = '';
  document.getElementById('modalAprobar').style.display = 'flex';
}

function toggleOtraCancha(val) {
  const manualInput = document.getElementById('otraCanchaInput');
  const selectEl    = document.getElementById('aprobarCancha');
  if (val === '__otro__') {
    manualInput.style.display = 'block';
    manualInput.required = true;
    selectEl.name = '_cancha_select_ignored'; // desactivar select del POST
    manualInput.name = 'cancha_aprobada';
    manualInput.focus();
  } else {
    manualInput.style.display = 'none';
    manualInput.required = false;
    selectEl.name = 'cancha_aprobada';
    manualInput.name = 'cancha_aprobada_manual';
  }
}

async function testPush() {
  const btn = document.getElementById('btnTestPush');
  const res = document.getElementById('pushResult');
  btn.disabled = true;
  btn.textContent = 'Enviando...';
  res.style.display = 'none';

  try {
    const r = await fetch('/api/test_push.php', { method: 'POST' });
    const data = await r.json();
    res.style.display = 'block';
    res.style.background = data.ok ? '#f0fdf4' : '#fef2f2';
    res.style.color = data.ok ? '#166534' : '#b91c1c';
    res.style.border = '1px solid ' + (data.ok ? '#86efac' : '#fca5a5');
    res.textContent = data.msg || (data.ok ? '✅ Enviado' : '❌ Error');
  } catch(e) {
    res.style.display = 'block';
    res.style.background = '#fef2f2';
    res.style.color = '#b91c1c';
    res.style.border = '1px solid #fca5a5';
    res.textContent = '❌ Error de conexión';
  }

  btn.disabled = false;
  btn.innerHTML = '<svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg> Enviar notificación de prueba';
}
</script>

<?php require_once '../includes/footer.php'; ?>
