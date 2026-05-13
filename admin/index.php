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
          <input type="text" name="cancha_aprobada" class="form-control" placeholder="Ej: Easycancha — Cancha 3">
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
  document.getElementById('modalAprobar').style.display = 'flex';
}
</script>

<?php require_once '../includes/footer.php'; ?>
