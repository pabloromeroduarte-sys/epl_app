<?php
$page_title = 'Admin — Reprogramaciones por Equipo';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
epl_require_admin();

$db = epl_db();

// Todos los reprogramados con info completa
$reprogramados = $db->query("
    SELECT p.id, p.jornada, p.nombre_fecha, p.fecha_programada, p.estado, p.alerta_admin,
           l.id AS liga_id, l.nombre AS liga_nombre,
           el.id AS local_id, el.nombre AS local_nombre,
           ev.id AS visitante_id, ev.nombre AS visitante_nombre,
           r.nombre AS recinto_nombre,
           sr.motivo, sr.rival_no_responde, sr.created_at AS fecha_solicitud
    FROM partidos p
    JOIN ligas l ON l.id = p.liga_id
    JOIN equipos el ON el.id = p.equipo_local_id
    JOIN equipos ev ON ev.id = p.equipo_visitante_id
    LEFT JOIN recintos r ON r.id = p.recinto_id
    LEFT JOIN solicitudes_reprogramacion sr ON sr.partido_id = p.id
    WHERE p.estado = 'reprogramado'
    ORDER BY p.fecha_programada IS NULL DESC, p.fecha_programada ASC
")->fetchAll();

// Reprogramados por equipo
$por_equipo = [];
foreach ($reprogramados as $p) {
    foreach ([
        ['id' => (int)$p['local_id'] ?? 0,     'nombre' => $p['local_nombre']],
        ['id' => (int)$p['visitante_id'] ?? 0,  'nombre' => $p['visitante_nombre']],
    ] as $eq) {
        if (!$eq['id']) continue;
        $por_equipo[$eq['id']]['nombre']   = $eq['nombre'];
        $por_equipo[$eq['id']]['liga']     = $p['liga_nombre'];
        $por_equipo[$eq['id']]['liga_id']  = $p['liga_id'];
        $por_equipo[$eq['id']]['partidos'][] = $p;
    }
}
uasort($por_equipo, fn($a,$b) => strcmp($a['nombre'], $b['nombre']));

require_once '../includes/header.php';
?>

<div class="dash-layout">
  <?php include __DIR__ . '/partials/sidebar.php'; ?>

  <main class="dash-main">
    <div class="dash-header">
      <h1 class="dash-title">Reprogramaciones</h1>
      <p style="color:var(--gray-600);margin-top:.25rem">Partidos que debían jugarse y fueron reprogramados.</p>
    </div>

    <!-- KPIs -->
    <div class="grid-4 mb-4">
      <?php
        $es_sin_fecha = fn($p) => !$p['fecha_programada'] || date('Y-m-d', strtotime($p['fecha_programada'])) === '2026-12-31';
        $n_sin_fecha  = count(array_filter($reprogramados, $es_sin_fecha));
        $n_con_fecha  = count($reprogramados) - $n_sin_fecha;
      ?>
      <div class="card" style="border-left:4px solid #ef4444">
        <div class="card-body">
          <div style="font-size:.7rem;text-transform:uppercase;font-weight:700;color:var(--gray-400)">Reprogramados sin fecha</div>
          <div style="font-size:2rem;font-weight:800;color:#dc2626"><?= $n_sin_fecha ?></div>
        </div>
      </div>
      <div class="card" style="border-left:4px solid #f59e0b">
        <div class="card-body">
          <div style="font-size:.7rem;text-transform:uppercase;font-weight:700;color:var(--gray-400)">Reprogramados con fecha</div>
          <div style="font-size:2rem;font-weight:800;color:var(--navy)"><?= $n_con_fecha ?></div>
        </div>
      </div>
      <div class="card" style="border-left:4px solid #3b82f6">
        <div class="card-body">
          <div style="font-size:.7rem;text-transform:uppercase;font-weight:700;color:var(--gray-400)">Equipos afectados</div>
          <div style="font-size:2rem;font-weight:800;color:var(--navy)"><?= count($por_equipo) ?></div>
        </div>
      </div>
      <div class="card" style="border-left:4px solid #10b981">
        <div class="card-body">
          <div style="font-size:.7rem;text-transform:uppercase;font-weight:700;color:var(--gray-400)">Total reprogramados</div>
          <div style="font-size:2rem;font-weight:800;color:var(--navy)"><?= count($reprogramados) ?></div>
        </div>
      </div>
    </div>

    <!-- TABLA REPROGRAMADOS -->
    <h2 style="font-family:var(--font-head);font-size:.9rem;text-transform:uppercase;color:var(--navy);margin:1.5rem 0 .75rem;display:flex;align-items:center;gap:.5rem">
      <span style="width:8px;height:8px;background:#f59e0b;border-radius:50%;display:inline-block"></span>
      Todos los Partidos Reprogramados (<?= count($reprogramados) ?>)
    </h2>

    <div class="card mb-4">
      <div style="overflow-x:auto">
        <table style="width:100%;border-collapse:collapse;font-size:.82rem">
          <thead>
            <tr style="background:var(--navy);color:#fff">
              <th style="padding:.6rem .75rem;text-align:left">Liga</th>
              <th style="padding:.6rem .75rem;text-align:center">Jornada</th>
              <th style="padding:.6rem .75rem;text-align:left">Nombre Fecha</th>
              <th style="padding:.6rem .75rem;text-align:left">Partido</th>
              <th style="padding:.6rem .75rem;text-align:left">Fecha Prog.</th>
              <th style="padding:.6rem .75rem;text-align:left">Cancha</th>
              <th style="padding:.6rem .75rem;text-align:left">Motivo</th>
              <th style="padding:.6rem .75rem;text-align:center">Estado</th>
              <th style="padding:.6rem .75rem;text-align:center">Acción</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($reprogramados as $p): ?>
            <?php $sin_fecha = $es_sin_fecha($p); ?>
            <tr style="border-bottom:1px solid var(--gray-100);<?= $sin_fecha ? 'background:#fff7f7' : '' ?>">
              <td style="padding:.65rem .75rem;font-weight:600;color:var(--navy)"><?= epl_h($p['liga_nombre']) ?></td>
              <td style="padding:.65rem .75rem;text-align:center">
                <?php if ($p['jornada']): ?>
                  <span style="background:#e0e7ff;color:#3730a3;padding:.15rem .5rem;border-radius:99px;font-size:.7rem;font-weight:700">J<?= $p['jornada'] ?></span>
                <?php else: ?>—<?php endif; ?>
              </td>
              <td style="padding:.65rem .75rem;color:var(--gray-600);font-size:.78rem"><?= epl_h($p['nombre_fecha'] ?: '—') ?></td>
              <td style="padding:.65rem .75rem;font-weight:600"><?= epl_h($p['local_nombre']) ?> <span style="color:var(--gray-400)">vs</span> <?= epl_h($p['visitante_nombre']) ?></td>
              <td style="padding:.65rem .75rem">
                <?php if ($sin_fecha): ?>
                  <span style="background:#fee2e2;color:#dc2626;padding:.2rem .5rem;border-radius:99px;font-size:.7rem;font-weight:700">⚠ Sin fecha</span>
                <?php else: ?>
                  <div style="font-weight:700;color:#b45309"><?= date('d/m/Y', strtotime($p['fecha_programada'])) ?></div>
                  <div style="font-size:.72rem;color:var(--gray-400)"><?= date('H:i', strtotime($p['fecha_programada'])) ?></div>
                <?php endif; ?>
              </td>
              <td style="padding:.65rem .75rem;font-size:.78rem;color:var(--gray-600)"><?= epl_h($p['recinto_nombre'] ?: '—') ?></td>
              <td style="padding:.65rem .75rem;font-size:.75rem;color:var(--gray-500);max-width:160px">
                <?php if ($p['rival_no_responde']): ?>
                  <span style="background:#fee2e2;color:#dc2626;padding:.1rem .4rem;border-radius:4px;font-size:.68rem;font-weight:700;display:block;margin-bottom:.2rem">RIVAL NO RESPONDE</span>
                <?php endif; ?>
                <?= epl_h(mb_strimwidth($p['motivo'] ?? '', 0, 60, '…')) ?>
              </td>
              <td style="padding:.65rem .75rem;text-align:center">
                <span style="background:#dbeafe;color:#1d4ed8;padding:.2rem .55rem;border-radius:99px;font-size:.68rem;font-weight:700">Reprogramado</span>
              </td>
              <td style="padding:.65rem .75rem;text-align:center">
                <a href="liga_detalle.php?id=<?= $p['liga_id'] ?>&tab=partidos" class="btn btn-sm btn-navy" style="font-size:.7rem;padding:.25rem .6rem">Gestionar</a>
              </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($reprogramados)): ?>
              <tr><td colspan="9" style="padding:2rem;text-align:center;color:var(--gray-400)">No hay partidos reprogramados.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- PENDIENTES POR EQUIPO -->
    <h2 style="font-family:var(--font-head);font-size:.9rem;text-transform:uppercase;color:var(--navy);margin:2rem 0 .75rem;display:flex;align-items:center;gap:.5rem">
      <span style="width:8px;height:8px;background:#3b82f6;border-radius:50%;display:inline-block"></span>
      Reprogramados por Equipo
    </h2>

    <?php if (empty($por_equipo)): ?>
      <div class="card"><div class="card-body" style="text-align:center;color:var(--gray-400);padding:2rem">No hay partidos pendientes.</div></div>
    <?php else: ?>
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(340px,1fr));gap:1rem">
      <?php foreach ($por_equipo as $eq_id => $eq): ?>
      <div class="card" style="border-top:3px solid var(--navy)">
        <div style="padding:.75rem 1rem .5rem;display:flex;justify-content:space-between;align-items:center">
          <div>
            <div style="font-weight:800;font-size:.9rem;color:var(--navy)"><?= epl_h($eq['nombre']) ?></div>
            <div style="font-size:.72rem;color:var(--gray-400)"><?= epl_h($eq['liga']) ?></div>
          </div>
          <span style="background:#fef3c7;color:#b45309;font-weight:700;font-size:.75rem;padding:.2rem .6rem;border-radius:99px"><?= count($eq['partidos']) ?> reprogramado<?= count($eq['partidos'])>1?'s':'' ?></span>
        </div>
        <div style="overflow-x:auto">
          <table style="width:100%;border-collapse:collapse;font-size:.75rem">
            <thead>
              <tr style="background:var(--gray-100)">
                <th style="padding:.4rem .75rem;text-align:center;color:var(--gray-500)">Jornada</th>
                <th style="padding:.4rem .75rem;text-align:left;color:var(--gray-500)">Rival</th>
                <th style="padding:.4rem .75rem;text-align:left;color:var(--gray-500)">Fecha</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($eq['partidos'] as $pp): ?>
              <?php
                $rival = ($pp['local_id'] == $eq_id) ? $pp['visitante_nombre'] : $pp['local_nombre'];
                $es_local = ($pp['local_id'] == $eq_id);
              ?>
              <tr style="border-bottom:1px solid var(--gray-100)">
                <td style="padding:.4rem .75rem;text-align:center">
                  <?php if ($pp['jornada']): ?>
                    <span style="background:#e0e7ff;color:#3730a3;padding:.1rem .4rem;border-radius:99px;font-size:.68rem;font-weight:700">J<?= $pp['jornada'] ?></span>
                  <?php else: ?>—<?php endif; ?>
                </td>
                <td style="padding:.4rem .75rem">
                  <span style="font-size:.65rem;color:var(--gray-400);margin-right:.25rem"><?= $es_local ? 'L' : 'V' ?></span>
                  <?= epl_h($rival) ?>
                </td>
                <td style="padding:.4rem .75rem;color:var(--gray-500)">
                  <?php $_sf = $es_sin_fecha($pp); ?>
                  <?= !$_sf ? date('d/m H:i', strtotime($pp['fecha_programada'])) : '<span style="color:#dc2626;font-weight:700;font-size:.68rem">Sin fecha</span>' ?>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <div style="padding:.5rem .75rem;text-align:right">
          <a href="liga_detalle.php?id=<?= $eq['liga_id'] ?>&tab=partidos" style="font-size:.72rem;color:var(--navy)">Ver en liga →</a>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

  </main>
</div>

<?php require_once '../includes/footer.php'; ?>
