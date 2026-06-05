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
           el.nombre AS local_nombre, ev.nombre AS visitante_nombre,
           p.fecha_programada, p.recinto_id, p.liga_id,
           r.nombre AS recinto_nombre, rs.nombre AS recinto_sup,
           r.contacto1_nombre, r.contacto1_telefono,
           r.contacto2_nombre, r.contacto2_telefono,
           r.contacto3_nombre, r.contacto3_telefono
    FROM solicitudes_reprogramacion sr
    JOIN jugadores j ON j.id = sr.solicitante_id
    JOIN partidos p  ON p.id = sr.partido_id
    JOIN equipos el  ON el.id = p.equipo_local_id
    JOIN equipos ev  ON ev.id = p.equipo_visitante_id
    LEFT JOIN recintos r ON r.id = p.recinto_id
    LEFT JOIN recintos rs ON rs.id = r.superior_id
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
      <a href="jugadores.php?estado=activo" class="stat-card stat-card--link">
        <div class="stat-value"><?= $totals['jugadores'] ?></div>
        <div class="stat-label">Jugadores activos</div>
      </a>
      <a href="equipos.php" class="stat-card stat-card--link">
        <div class="stat-value"><?= $totals['equipos'] ?></div>
        <div class="stat-label">Equipos</div>
      </a>
      <a href="partidos.php" class="stat-card stat-card--link">
        <div class="stat-value"><?= $totals['partidos'] ?></div>
        <div class="stat-label">Total partidos</div>
      </a>
      <a href="partidos.php?estado_p=jugado" class="stat-card stat-card--link">
        <div class="stat-value" style="color:var(--green)"><?= $totals['jugados'] ?></div>
        <div class="stat-label">Jugados</div>
      </a>
      <a href="partidos.php?estado_p=pendiente" class="stat-card stat-card--link">
        <div class="stat-value" style="color:var(--gold)"><?= $totals['pendientes'] ?></div>
        <div class="stat-label">Pendientes</div>
      </a>
      <a href="dashboard_repro.php?tab=solicitudes" class="stat-card stat-card--link">
        <div class="stat-value" style="color:var(--red)"><?= $totals['reprog'] ?></div>
        <div class="stat-label">Reprog. pendientes</div>
      </a>
      <a href="inscripciones.php?estado=pendiente" class="stat-card stat-card--link">
        <div class="stat-value" style="color:var(--gold)"><?= $totals['inscripciones'] ?></div>
        <div class="stat-label">Inscripciones pend.</div>
      </a>
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
              <?php foreach ($solicitudes as $s): 
          $es_sin_fecha = empty($s['fecha_propuesta']) || $s['rival_no_responde'];
        ?>
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
              <?php if ($es_sin_fecha): ?>
                <div style="margin-top:.45rem;padding:.45rem .75rem;background:#fef3c7;border-left:3px solid #f59e0b;border-radius:4px;font-size:.75rem;color:#92400e;font-weight:600">
                  ⚠️ Solicita reprogramar SIN FECHA. Se debe liberar la cancha original:
                  <div style="margin-top:.2rem;font-weight:700;font-size:.72rem">
                    <?php if ($s['fecha_programada']): ?>📅 <?= date('d/m/Y H:i', strtotime($s['fecha_programada'])) ?><?php endif; ?>
                    <?php if ($s['recinto_nombre']): ?> · 🏟️ <?= epl_h($s['recinto_nombre']) ?><?php endif; ?>
                  </div>
                </div>
                <?php
                  $contactos = [];
                  for ($i = 1; $i <= 3; $i++) {
                      if (!empty($s["contacto{$i}_telefono"])) {
                          $contactos[] = ['nombre' => $s["contacto{$i}_nombre"] ?? '', 'telefono' => $s["contacto{$i}_telefono"]];
                      }
                  }
                  if (empty($contactos) && !empty($s['recinto_id'])) {
                      $h = epl_recinto_contactos_jerarquico((int)$s['recinto_id']);
                      if (!empty($h['contactos'])) {
                          $contactos = $h['contactos'];
                      }
                  }
                  if (empty($contactos) && !empty($s['liga_id'])) {
                      $h = epl_recintos_recomendados_liga((int)$s['liga_id']);
                      if (!empty($h['contactos'])) {
                          $contactos = $h['contactos'];
                      }
                  }
                  if (!empty($contactos)) {
                      $fo_lbl = ($s['fecha_programada'] && date('Y-m-d', strtotime($s['fecha_programada'])) !== '2026-12-31') 
                          ? date('d/m/Y H:i', strtotime($s['fecha_programada'])) 
                          : null;
                      $rec_orig = $s['recinto_nombre'];
                      $rec_orig_sup = $s['recinto_sup'];
                      $rec_orig_full = $rec_orig ? $rec_orig . ($rec_orig_sup ? " ($rec_orig_sup)" : '') : null;
                      
                      $wsp_msg = "Hola, te hablo de Elite Padel League.\n\n"
                               . "Necesitamos DAR DE BAJA esta reserva porque el partido se reprogramó sin fecha:\n";
                      if ($fo_lbl)        $wsp_msg .= "📅 $fo_lbl\n";
                      if ($rec_orig_full) $wsp_msg .= "🏟️ $rec_orig_full\n";
                      $wsp_msg .= "👥 {$s['local_nombre']} vs {$s['visitante_nombre']}\n\n"
                                . "Por favor, confírmanos cuando esté liberada.\n\n¡Gracias!";
                      
                      echo '<div style="margin-top:.45rem;display:flex;flex-wrap:wrap;gap:.35rem">';
                      foreach ($contactos as $c) {
                          $tel = preg_replace('/[^0-9]/', '', $c['telefono']);
                          if (!$tel) continue;
                          if (substr($tel, 0, 2) !== '56') $tel = '56' . $tel;
                          $wsp_url = "https://wa.me/{$tel}?text=" . rawurlencode($wsp_msg);
                          ?>
                          <a href="<?= $wsp_url ?>" target="_blank" rel="noopener"
                             style="display:inline-flex;align-items:center;gap:.3rem;background:#25D366;color:#fff;padding:.3rem .6rem;border-radius:6px;font-size:.68rem;font-weight:800;text-decoration:none">
                            <svg width="12" height="12" fill="currentColor" viewBox="0 0 24 24"><path d="M17.6 6.32A7.85 7.85 0 0012.05 4a7.94 7.94 0 00-6.88 11.93L4 20l4.21-1.1a7.95 7.95 0 003.84.98h.01a7.94 7.94 0 005.54-13.56M12.05 18.5a6.62 6.62 0 01-3.36-.92l-.24-.14-2.5.66.67-2.44-.16-.25a6.59 6.59 0 0110.21-8.16 6.55 6.55 0 011.93 4.66 6.62 6.62 0 01-6.55 6.59"/></svg>
                            <?= epl_h($c['nombre'] ?: 'WhatsApp') ?>
                          </a>
                          <?php
                      }
                      echo '</div>';
                  } else {
                      ?>
                      <div style="font-size:.68rem;color:#dc2626;margin-top:.45rem;font-weight:700">
                        ⚠️ Sin contactos del club configurados.
                      </div>
                      <?php
                  }
                ?>
              <?php endif; ?>
            </div>
            <div style="display:flex;gap:.5rem;flex-shrink:0;flex-direction:column;align-items:flex-end">
              <?php if ($s['rival_no_responde']): ?>
                <span class="badge badge-walkover" style="font-size:.65rem">⚠ Rival no respondió</span>
              <?php endif; ?>
              <div style="display:flex;gap:.4rem">
                <?php if ($es_sin_fecha): ?>
                  <form method="post" action="api_reprogramacion.php" style="display:inline"
                        data-confirm="¿Aprobar esta reprogramación sin fecha? El partido quedará 'A coordinar' y se liberará la cancha original."
                        data-confirm-ok="Sí, liberar cancha">
                    <input type="hidden" name="id" value="<?= $s['id'] ?>">
                    <input type="hidden" name="accion" value="aprobar">
                    <input type="hidden" name="fecha_aprobada" value="">
                    <button type="submit" class="btn btn-primary btn-sm" style="background:#d97706;border-color:#d97706;color:#fff;font-weight:700">Aprobar (Lib.)</button>
                  </form>
                <?php else: ?>
                  <button onclick="showAprobar(<?= $s['id'] ?>, '<?= epl_h($s['fecha_propuesta']) ?>')"
                          class="btn btn-primary btn-sm">Aprobar</button>
                <?php endif; ?>
                <form method="post" action="api_reprogramacion.php" style="display:inline"
                      data-confirm="¿Rechazar la solicitud de reprogramación?"
                      data-confirm-ok="Sí, rechazar">
                  <input type="hidden" name="id" value="<?= $s['id'] ?>">
                  <input type="hidden" name="accion" value="rechazar">
                  <button type="submit" class="btn btn-sm" style="border:2px solid var(--red);color:var(--red)">Rechazar</button>
                </form>
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
