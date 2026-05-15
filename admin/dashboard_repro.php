<?php
$page_title = 'Admin — Dashboard de Reprogramaciones';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
epl_require_admin();

$db = epl_db();

// 1. Partidos reprogramados sin fecha asignada (los que tienen NULL o son de solicitudes "Rival no responde")
$repro_sin_fecha = $db->query("
    SELECT p.*, l.nombre AS liga_nombre, el.nombre AS local_nombre, ev.nombre AS visitante_nombre,
           sr.motivo, sr.created_at AS fecha_solicitud, sr.rival_no_responde
    FROM partidos p
    JOIN ligas l ON l.id = p.liga_id
    JOIN equipos el ON el.id = p.equipo_local_id
    JOIN equipos ev ON ev.id = p.equipo_visitante_id
    LEFT JOIN solicitudes_reprogramacion sr ON sr.partido_id = p.id
    WHERE p.estado = 'reprogramado' AND (p.fecha_programada IS NULL OR sr.rival_no_responde = 1)
    ORDER BY sr.created_at DESC
")->fetchAll();

// 2. Partidos reprogramados CON fecha pero pendientes de jugar
$repro_con_fecha = $db->query("
    SELECT p.*, l.nombre AS liga_nombre, el.nombre AS local_nombre, ev.nombre AS visitante_nombre,
           r.nombre AS recinto_nombre
    FROM partidos p
    JOIN ligas l ON l.id = p.liga_id
    JOIN equipos el ON el.id = p.equipo_local_id
    JOIN equipos ev ON ev.id = p.equipo_visitante_id
    LEFT JOIN recintos r ON r.id = p.recinto_id
    LEFT JOIN solicitudes_reprogramacion sr ON sr.partido_id = p.id
    WHERE p.estado = 'reprogramado' AND p.fecha_programada IS NOT NULL AND (sr.rival_no_responde = 0 OR sr.rival_no_responde IS NULL)
    ORDER BY p.fecha_programada ASC
")->fetchAll();

require_once '../includes/header.php';
?>

<div class="dash-layout">
  <?php include __DIR__ . '/partials/sidebar.php'; ?>

  <main class="dash-main">
    <div class="dash-header">
      <h1 class="dash-title">Dashboard de Reprogramaciones</h1>
      <p style="color:var(--gray-600);margin-top:.25rem">Control de partidos que necesitan nueva fecha o seguimiento.</p>
    </div>

    <div class="grid-2 mb-4">
        <div class="card" style="border-left:4px solid #ef4444">
            <div class="card-body">
                <div style="font-size:.7rem;text-transform:uppercase;font-weight:700;color:var(--gray-400)">Sin Fecha Asignada</div>
                <div style="font-size:2rem;font-weight:800;color:var(--navy)"><?= count($repro_sin_fecha) ?></div>
                <p style="font-size:.75rem;color:var(--gray-500)">Partidos en "limbo" por reprogramación o falta de respuesta.</p>
            </div>
        </div>
        <div class="card" style="border-left:4px solid #f59e0b">
            <div class="card-body">
                <div style="font-size:.7rem;text-transform:uppercase;font-weight:700;color:var(--gray-400)">Pendientes de Jugar</div>
                <div style="font-size:2rem;font-weight:800;color:var(--navy)"><?= count($repro_con_fecha) ?></div>
                <p style="font-size:.75rem;color:var(--gray-500)">Reprogramaciones ya coordinadas con fecha establecida.</p>
            </div>
        </div>
    </div>

    <h2 style="font-family:var(--font-head);font-size:.9rem;text-transform:uppercase;color:var(--navy);margin:2rem 0 1rem;display:flex;align-items:center;gap:.5rem">
        <span style="width:8px;height:8px;background:#ef4444;border-radius:50%"></span>
        Partidos Críticos (Sin Fecha)
    </h2>
    
    <div class="card">
        <div style="overflow-x:auto">
            <table class="admin-table-cards" style="width:100%;border-collapse:collapse;font-size:.85rem">
                <thead>
                    <tr style="background:var(--gray-100);color:var(--navy)">
                        <th style="padding:.75rem 1rem;text-align:left">Liga / Jornada</th>
                        <th style="padding:.75rem 1rem;text-align:left">Partido</th>
                        <th class="hide-mobile" style="padding:.75rem 1rem;text-align:left">Motivo / Alerta</th>
                        <th class="hide-mobile" style="padding:.75rem 1rem;text-align:center">Solicitado</th>
                        <th style="padding:.75rem 1rem;text-align:center">Acción</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($repro_sin_fecha as $p): ?>
                    <tr style="border-bottom:1px solid var(--gray-100)">
                        <td data-label="Liga / Jornada" style="padding:.8rem 1rem">
                            <div style="font-weight:700;color:var(--navy)"><?= epl_h($p['liga_nombre']) ?></div>
                            <div style="font-size:.7rem;color:var(--gray-400)"><?= epl_h($p['nombre_fecha']) ?></div>
                        </td>
                        <td data-label="Partido" style="padding:.8rem 1rem">
                            <div style="font-weight:600;color:var(--navy)"><?= epl_h($p['local_nombre']) ?> vs <?= epl_h($p['visitante_nombre']) ?></div>
                        </td>
                        <td data-label="Motivo / Alerta" class="hide-mobile" style="padding:.8rem 1rem">
                            <?php if ($p['rival_no_responde']): ?>
                                <span class="badge badge-walkover" style="font-size:.65rem">RIVAL NO RESPONDE</span>
                            <?php endif; ?>
                            <div style="font-size:.75rem;color:var(--gray-600);margin-top:.2rem italic"><?= epl_h($p['motivo'] ?: 'Sin motivo especificado') ?></div>
                        </td>
                        <td data-label="Solicitado" class="hide-mobile" style="padding:.8rem 1rem;text-align:center;color:var(--gray-500);font-size:.75rem">
                            <?= $p['fecha_solicitud'] ? date('d/m/Y', strtotime($p['fecha_solicitud'])) : '—' ?>
                        </td>
                        <td data-label="Acción" style="padding:.8rem 1rem;text-align:center">
                            <a href="liga_detalle.php?id=<?= $p['liga_id'] ?>&tab=partidos" class="btn btn-sm btn-navy">Gestionar</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($repro_sin_fecha)): ?>
                        <tr><td colspan="5" style="padding:2rem;text-align:center;color:var(--gray-400)">No hay partidos críticos pendientes.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <h2 style="font-family:var(--font-head);font-size:.9rem;text-transform:uppercase;color:var(--navy);margin:2rem 0 1rem;display:flex;align-items:center;gap:.5rem">
        <span style="width:8px;height:8px;background:#f59e0b;border-radius:50%"></span>
        Próximos Partidos Reprogramados
    </h2>

    <div class="card">
        <div style="overflow-x:auto">
            <table class="admin-table-cards" style="width:100%;border-collapse:collapse;font-size:.85rem">
                <thead>
                    <tr style="background:var(--gray-100);color:var(--navy)">
                        <th style="padding:.75rem 1rem;text-align:left">Nueva Fecha</th>
                        <th style="padding:.75rem 1rem;text-align:left">Partido</th>
                        <th class="hide-mobile" style="padding:.75rem 1rem;text-align:left">Liga</th>
                        <th class="hide-mobile" style="padding:.75rem 1rem;text-align:left">Cancha</th>
                        <th style="padding:.75rem 1rem;text-align:center">Acción</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($repro_con_fecha as $p): ?>
                    <tr style="border-bottom:1px solid var(--gray-100)">
                        <td data-label="Nueva Fecha" style="padding:.8rem 1rem">
                            <div style="font-weight:700;color:var(--gold)"><?= date('d/m H:i', strtotime($p['fecha_programada'])) ?></div>
                        </td>
                        <td data-label="Partido" style="padding:.8rem 1rem">
                            <div style="font-weight:600;color:var(--navy)"><?= epl_h($p['local_nombre']) ?> vs <?= epl_h($p['visitante_nombre']) ?></div>
                        </td>
                        <td data-label="Liga" class="hide-mobile" style="padding:.8rem 1rem;color:var(--gray-600)">
                            <?= epl_h($p['liga_nombre']) ?>
                        </td>
                        <td data-label="Cancha" class="hide-mobile" style="padding:.8rem 1rem;font-size:.75rem;color:var(--gray-500)">
                            <?= epl_h($p['recinto_nombre'] ?: 'No asignada') ?>
                        </td>
                        <td data-label="Acción" style="padding:.8rem 1rem;text-align:center">
                            <a href="liga_detalle.php?id=<?= $p['liga_id'] ?>&tab=partidos" class="btn btn-sm" style="border:1px solid var(--gray-200)">Ver</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($repro_con_fecha)): ?>
                        <tr><td colspan="5" style="padding:2rem;text-align:center;color:var(--gray-400)">No hay reprogramaciones con fecha futura.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

  </main>
</div>

<?php require_once '../includes/footer.php'; ?>
