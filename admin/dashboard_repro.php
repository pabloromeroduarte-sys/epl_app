<?php
$page_title = 'Admin — Reprogramaciones por Equipo';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
epl_require_admin();

$db = epl_db();

// Todos los reprogramados — fechas reales primero (ASC), sin fecha al final
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
    ORDER BY
        (p.fecha_programada IS NULL OR DATE(p.fecha_programada)='2026-12-31') ASC,
        p.fecha_programada ASC
")->fetchAll();

// Pendientes ordenados por fecha ASC (sin fecha al final)
$pendientes = $db->query("
    SELECT p.id, p.jornada, p.nombre_fecha, p.fecha_programada,
           l.id AS liga_id, l.nombre AS liga_nombre,
           el.nombre AS local_nombre,
           ev.nombre AS visitante_nombre,
           r.nombre AS recinto_nombre
    FROM partidos p
    JOIN ligas l ON l.id = p.liga_id
    JOIN equipos el ON el.id = p.equipo_local_id
    JOIN equipos ev ON ev.id = p.equipo_visitante_id
    LEFT JOIN recintos r ON r.id = p.recinto_id
    WHERE p.estado = 'pendiente'
    ORDER BY
        (p.fecha_programada IS NULL) ASC,
        p.fecha_programada ASC
")->fetchAll();
$n_pendientes = count($pendientes);

// Helper: ¿es sin fecha? (NULL o placeholder 31/12/2026)
$es_sin_fecha = fn($p) => !$p['fecha_programada'] || date('Y-m-d', strtotime($p['fecha_programada'])) === '2026-12-31';

// Helper: ¿ya venció? (tiene fecha real, está en el pasado y sigue sin jugarse)
$hoy = new DateTime();
$es_vencida = fn($p) => !$es_sin_fecha($p) && new DateTime($p['fecha_programada']) < $hoy;

// KPI counts
$n_sin_fecha = count(array_filter($reprogramados, $es_sin_fecha));
$n_con_fecha = count($reprogramados) - $n_sin_fecha;
$n_vencidas  = count(array_filter($reprogramados, $es_vencida));
$n_proximas  = $n_con_fecha - $n_vencidas; // con fecha futura

// Porcentaje de atraso = vencidas / total * 100
$total = count($reprogramados);
$pct_atraso = $total > 0 ? round(($n_vencidas / $total) * 100) : 0;

// Avance general
$total_jugados  = (int)$db->query("SELECT COUNT(*) FROM partidos WHERE estado IN ('jugado','walkover','no_presentado')")->fetchColumn();
$total_partidos = (int)$db->query("SELECT COUNT(*) FROM partidos")->fetchColumn();
$pct_avance = $total_partidos > 0 ? round(($total_jugados / $total_partidos) * 100) : 0;

// Reprogramados por equipo, ordenados por cantidad DESC
$por_equipo = [];
foreach ($reprogramados as $p) {
    foreach ([
        ['id' => (int)$p['local_id'],    'nombre' => $p['local_nombre']],
        ['id' => (int)$p['visitante_id'],'nombre' => $p['visitante_nombre']],
    ] as $eq) {
        if (!$eq['id']) continue;
        $por_equipo[$eq['id']]['nombre']    = $eq['nombre'];
        $por_equipo[$eq['id']]['liga']      = $p['liga_nombre'];
        $por_equipo[$eq['id']]['liga_id']   = $p['liga_id'];
        $por_equipo[$eq['id']]['partidos'][] = $p;
    }
}
uasort($por_equipo, fn($a,$b) => count($b['partidos']) - count($a['partidos']));

require_once '../includes/header.php';
?>

<div class="dash-layout">
  <?php include __DIR__ . '/partials/sidebar.php'; ?>

  <main class="dash-main">
    <div class="dash-header" style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:.5rem">
      <div>
        <h1 class="dash-title">Reprogramaciones</h1>
        <p style="color:var(--gray-600);margin-top:.25rem">Partidos que debían jugarse y fueron reprogramados.</p>
      </div>
      <button id="btnLimpiarFiltro" onclick="limpiarFiltro()" style="display:none;align-items:center;gap:.4rem;background:#f3f4f6;border:1px solid #d1d5db;color:var(--navy);font-size:.75rem;font-weight:700;padding:.4rem .9rem;border-radius:99px;cursor:pointer">
        ✕ Limpiar filtro
      </button>
    </div>

    <!-- Filtro activo -->
    <div id="filtroActivo" style="display:none;margin:.5rem 0 1rem;padding:.5rem .9rem;background:#eff6ff;border:1px solid #bfdbfe;border-radius:8px;font-size:.78rem;color:#1d4ed8;font-weight:600"></div>

    <!-- PANEL DE ATRASO -->
    <div class="card mb-4" style="border:2px solid <?= $pct_atraso >= 50 ? '#dc2626' : ($pct_atraso >= 25 ? '#f59e0b' : '#10b981') ?>">
      <div class="card-body" style="padding:1rem 1.25rem">
        <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:1rem">

          <!-- Bloque principal atraso -->
          <div style="flex:1;min-width:220px">
            <div style="font-size:.7rem;font-weight:700;text-transform:uppercase;color:var(--gray-400);letter-spacing:.05em;margin-bottom:.4rem">Estado de Reprogramaciones</div>
            <div style="display:flex;align-items:baseline;gap:.5rem;flex-wrap:wrap">
              <span style="font-size:2.2rem;font-weight:900;color:<?= $pct_atraso >= 50 ? '#dc2626' : ($pct_atraso >= 25 ? '#b45309' : '#059669') ?>"><?= $pct_atraso ?>%</span>
              <span style="font-size:.8rem;color:var(--gray-500);font-weight:600">de atraso</span>
            </div>
            <!-- Barra de atraso -->
            <div style="margin-top:.6rem;height:8px;background:#f3f4f6;border-radius:99px;overflow:hidden">
              <div style="height:100%;width:<?= $pct_atraso ?>%;background:<?= $pct_atraso >= 50 ? '#dc2626' : ($pct_atraso >= 25 ? '#f59e0b' : '#10b981') ?>;border-radius:99px;transition:width .4s"></div>
            </div>
            <div style="margin-top:.4rem;font-size:.72rem;color:var(--gray-500)">
              <strong style="color:<?= $pct_atraso >= 50 ? '#dc2626' : ($pct_atraso >= 25 ? '#b45309' : '#059669') ?>"><?= $n_vencidas ?></strong> de <?= $total ?> reprogramados ya deberían haberse jugado
            </div>
          </div>

          <!-- Desglose -->
          <div style="display:flex;gap:1.5rem;flex-wrap:wrap;align-items:center">
            <div class="kpi-card" id="kpi-vencidas" onclick="filtrarKpi('vencidas')" style="text-align:center;cursor:pointer;padding:.5rem .75rem;border-radius:8px;transition:all .15s;min-width:80px">
              <div style="font-size:1.6rem;font-weight:900;color:#dc2626"><?= $n_vencidas ?></div>
              <div style="font-size:.65rem;font-weight:700;text-transform:uppercase;color:#9ca3af">Vencidas 🔴</div>
              <div style="font-size:.6rem;color:#9ca3af">fecha pasó</div>
            </div>
            <div class="kpi-card" id="kpi-proximas" onclick="filtrarKpi('proximas')" style="text-align:center;cursor:pointer;padding:.5rem .75rem;border-radius:8px;transition:all .15s;min-width:80px">
              <div style="font-size:1.6rem;font-weight:900;color:#2563eb"><?= $n_proximas ?></div>
              <div style="font-size:.65rem;font-weight:700;text-transform:uppercase;color:#9ca3af">Próximas 📅</div>
              <div style="font-size:.6rem;color:#9ca3af">fecha futura</div>
            </div>
            <div style="text-align:center;padding:.5rem .75rem;border-radius:8px;min-width:80px">
              <div style="font-size:1.6rem;font-weight:900;color:#dc2626"><?= $n_sin_fecha ?></div>
              <div style="font-size:.65rem;font-weight:700;text-transform:uppercase;color:#9ca3af">Sin fecha ⚠</div>
              <div style="font-size:.6rem;color:#9ca3af">sin agendar</div>
            </div>
          </div>

          <!-- Avance general -->
          <div style="flex:1;min-width:180px;border-left:1px solid var(--gray-100);padding-left:1.25rem">
            <div style="font-size:.7rem;font-weight:700;text-transform:uppercase;color:var(--gray-400);letter-spacing:.05em;margin-bottom:.4rem">Avance General</div>
            <div style="display:flex;align-items:baseline;gap:.5rem">
              <span style="font-size:2.2rem;font-weight:900;color:var(--navy)"><?= $pct_avance ?>%</span>
              <span style="font-size:.8rem;color:var(--gray-500);font-weight:600">jugados</span>
            </div>
            <div style="margin-top:.6rem;height:8px;background:#f3f4f6;border-radius:99px;overflow:hidden">
              <div style="height:100%;width:<?= $pct_avance ?>%;background:var(--navy);border-radius:99px"></div>
            </div>
            <div style="margin-top:.4rem;font-size:.72rem;color:var(--gray-500)">
              <strong><?= $total_jugados ?></strong> jugados · <strong><?= $n_pendientes ?></strong> pendientes · <strong><?= $total ?></strong> reprog. · <strong><?= $total_partidos ?></strong> total
            </div>
          </div>

        </div>
      </div>
    </div>

    <!-- KPIs clickeables -->
    <div class="grid-4 mb-4">
      <div class="card kpi-card" id="kpi-sin-fecha" onclick="filtrarKpi('sin-fecha')" style="border-left:4px solid #ef4444;cursor:pointer;transition:box-shadow .15s,transform .15s">
        <div class="card-body">
          <div style="font-size:.7rem;text-transform:uppercase;font-weight:700;color:var(--gray-400)">Sin fecha ⚠</div>
          <div style="font-size:2rem;font-weight:800;color:#dc2626"><?= $n_sin_fecha ?></div>
          <div style="font-size:.68rem;color:#9ca3af;margin-top:.15rem">Clic para filtrar</div>
        </div>
      </div>
      <div class="card kpi-card" id="kpi-con-fecha" onclick="filtrarKpi('con-fecha')" style="border-left:4px solid #f59e0b;cursor:pointer;transition:box-shadow .15s,transform .15s">
        <div class="card-body">
          <div style="font-size:.7rem;text-transform:uppercase;font-weight:700;color:var(--gray-400)">Con fecha</div>
          <div style="font-size:2rem;font-weight:800;color:var(--navy)"><?= $n_con_fecha ?></div>
          <div style="font-size:.68rem;color:#9ca3af;margin-top:.15rem">Clic para filtrar</div>
        </div>
      </div>
      <div class="card" style="border-left:4px solid #3b82f6">
        <div class="card-body">
          <div style="font-size:.7rem;text-transform:uppercase;font-weight:700;color:var(--gray-400)">Equipos afectados</div>
          <div style="font-size:2rem;font-weight:800;color:var(--navy)"><?= count($por_equipo) ?></div>
        </div>
      </div>
      <div class="card kpi-card" id="kpi-total" onclick="filtrarKpi('total')" style="border-left:4px solid #10b981;cursor:pointer;transition:box-shadow .15s,transform .15s">
        <div class="card-body">
          <div style="font-size:.7rem;text-transform:uppercase;font-weight:700;color:var(--gray-400)">Total reprogramados</div>
          <div style="font-size:2rem;font-weight:800;color:var(--navy)"><?= count($reprogramados) ?></div>
          <div style="font-size:.68rem;color:#9ca3af;margin-top:.15rem">Clic para ver todos</div>
        </div>
      </div>
    </div>

    <!-- CHIPS DE EQUIPOS (ordenados por cantidad) -->
    <?php if (!empty($por_equipo)): ?>
    <div style="margin-bottom:1.25rem">
      <div style="font-size:.7rem;font-weight:700;text-transform:uppercase;color:var(--gray-400);margin-bottom:.6rem;letter-spacing:.05em">Filtrar por equipo</div>
      <div style="display:flex;flex-wrap:wrap;gap:.4rem" id="equipoChips">
        <?php foreach ($por_equipo as $eq_id => $eq): ?>
        <?php $cnt = count($eq['partidos']); $color = $cnt >= 3 ? '#dc2626' : ($cnt === 2 ? '#b45309' : '#374151'); $bg = $cnt >= 3 ? '#fee2e2' : ($cnt === 2 ? '#fef3c7' : '#f3f4f6'); ?>
        <button
          class="equipo-chip"
          data-eq-id="<?= $eq_id ?>"
          onclick="filtrarEquipo(<?= $eq_id ?>, this)"
          style="background:<?= $bg ?>;color:<?= $color ?>;border:1.5px solid transparent;padding:.3rem .75rem;border-radius:99px;font-size:.75rem;font-weight:700;cursor:pointer;transition:all .15s;display:flex;align-items:center;gap:.35rem"
        >
          <span><?= epl_h($eq['nombre']) ?></span>
          <span style="background:<?= $color ?>;color:#fff;border-radius:99px;padding:.05rem .4rem;font-size:.65rem"><?= $cnt ?></span>
        </button>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

    <!-- TABLA REPROGRAMADOS -->
    <div style="display:flex;justify-content:space-between;align-items:center;margin:1rem 0 .75rem;flex-wrap:wrap;gap:.5rem">
      <h2 style="font-family:var(--font-head);font-size:.9rem;text-transform:uppercase;color:var(--navy);display:flex;align-items:center;gap:.5rem;margin:0">
        <span style="width:8px;height:8px;background:#f59e0b;border-radius:50%;display:inline-block"></span>
        <span id="tablaTitle">Todos los Partidos Reprogramados (<?= count($reprogramados) ?>)</span>
      </h2>
    </div>

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
              <th style="padding:.6rem .75rem;text-align:center">Acción</th>
            </tr>
          </thead>
          <tbody id="tablaBody">
            <?php foreach ($reprogramados as $p): ?>
            <?php $sin_fecha = $es_sin_fecha($p); $vencida = $es_vencida($p); ?>
            <tr class="repro-row"
                data-sf="<?= $sin_fecha ? '1' : '0' ?>"
                data-vencida="<?= $vencida ? '1' : '0' ?>"
                data-proxima="<?= (!$sin_fecha && !$vencida) ? '1' : '0' ?>"
                data-eq="<?= $p['local_id'] ?>,<?= $p['visitante_id'] ?>"
                style="border-bottom:1px solid var(--gray-100);<?= $sin_fecha ? 'background:#fff7f7' : ($vencida ? 'background:#fff0f0' : '') ?>">
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
                <a href="liga_detalle.php?id=<?= $p['liga_id'] ?>&tab=partidos" class="btn btn-sm btn-navy" style="font-size:.7rem;padding:.25rem .6rem">Gestionar</a>
              </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($reprogramados)): ?>
              <tr><td colspan="8" style="padding:2rem;text-align:center;color:var(--gray-400)">No hay partidos reprogramados.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
        <div id="sinResultados" style="display:none;padding:2rem;text-align:center;color:var(--gray-400);font-size:.85rem">
          No hay partidos que coincidan con el filtro.
        </div>
      </div>
    </div>

    <!-- SECCIÓN PENDIENTES -->
    <div style="margin-top:2.5rem">
      <h2 style="font-family:var(--font-head);font-size:.9rem;text-transform:uppercase;color:var(--navy);margin:0 0 .75rem;display:flex;align-items:center;gap:.5rem">
        <span style="width:8px;height:8px;background:#3b82f6;border-radius:50%;display:inline-block"></span>
        Partidos Pendientes (<?= $n_pendientes ?>)
        <span style="font-size:.7rem;font-weight:400;color:var(--gray-400);text-transform:none">— ordenados por fecha</span>
      </h2>

      <?php if (empty($pendientes)): ?>
        <div class="card"><div class="card-body" style="text-align:center;color:var(--gray-400);padding:2rem">No hay partidos pendientes.</div></div>
      <?php else: ?>
      <div class="card">
        <div style="overflow-x:auto">
          <table style="width:100%;border-collapse:collapse;font-size:.82rem">
            <thead>
              <tr style="background:var(--navy);color:#fff">
                <th style="padding:.6rem .75rem;text-align:left">Liga</th>
                <th style="padding:.6rem .75rem;text-align:center">Jornada</th>
                <th style="padding:.6rem .75rem;text-align:left">Nombre Fecha</th>
                <th style="padding:.6rem .75rem;text-align:left">Partido</th>
                <th style="padding:.6rem .75rem;text-align:left">Fecha</th>
                <th style="padding:.6rem .75rem;text-align:left">Cancha</th>
                <th style="padding:.6rem .75rem;text-align:center">Acción</th>
              </tr>
            </thead>
            <tbody>
              <?php
                $ultima_fecha_p = null;
                foreach ($pendientes as $p):
                  $fecha_p = $p['fecha_programada'] ? date('Y-m-d', strtotime($p['fecha_programada'])) : null;
                  $es_nueva_fecha = $fecha_p !== $ultima_fecha_p;
                  $ultima_fecha_p = $fecha_p;
              ?>
              <?php if ($es_nueva_fecha && $fecha_p): ?>
              <tr style="background:#f0f4ff">
                <td colspan="7" style="padding:.35rem .75rem;font-size:.72rem;font-weight:800;color:#3730a3;letter-spacing:.04em">
                  📅 <?= date('l d \d\e F Y', strtotime($p['fecha_programada'])) ?>
                </td>
              </tr>
              <?php elseif ($es_nueva_fecha && !$fecha_p): ?>
              <tr style="background:#fff7f7">
                <td colspan="7" style="padding:.35rem .75rem;font-size:.72rem;font-weight:800;color:#dc2626;letter-spacing:.04em">
                  ⚠ Sin fecha asignada
                </td>
              </tr>
              <?php endif; ?>
              <tr style="border-bottom:1px solid var(--gray-100)">
                <td style="padding:.6rem .75rem;font-weight:600;color:var(--navy)"><?= epl_h($p['liga_nombre']) ?></td>
                <td style="padding:.6rem .75rem;text-align:center">
                  <?php if ($p['jornada']): ?>
                    <span style="background:#e0e7ff;color:#3730a3;padding:.15rem .5rem;border-radius:99px;font-size:.7rem;font-weight:700">J<?= $p['jornada'] ?></span>
                  <?php else: ?>—<?php endif; ?>
                </td>
                <td style="padding:.6rem .75rem;color:var(--gray-600);font-size:.78rem"><?= epl_h($p['nombre_fecha'] ?: '—') ?></td>
                <td style="padding:.6rem .75rem;font-weight:600">
                  <?= epl_h($p['local_nombre']) ?> <span style="color:var(--gray-400)">vs</span> <?= epl_h($p['visitante_nombre']) ?>
                </td>
                <td style="padding:.6rem .75rem">
                  <?php if ($p['fecha_programada']): ?>
                    <div style="font-weight:700;color:var(--navy)"><?= date('d/m/Y', strtotime($p['fecha_programada'])) ?></div>
                    <div style="font-size:.72rem;color:var(--gray-400)"><?= date('H:i', strtotime($p['fecha_programada'])) ?></div>
                  <?php else: ?>
                    <span style="background:#fee2e2;color:#dc2626;padding:.2rem .5rem;border-radius:99px;font-size:.7rem;font-weight:700">Sin fecha</span>
                  <?php endif; ?>
                </td>
                <td style="padding:.6rem .75rem;font-size:.78rem;color:var(--gray-600)"><?= epl_h($p['recinto_nombre'] ?: '—') ?></td>
                <td style="padding:.6rem .75rem;text-align:center">
                  <a href="liga_detalle.php?id=<?= $p['liga_id'] ?>&tab=partidos" class="btn btn-sm btn-navy" style="font-size:.7rem;padding:.25rem .6rem">Gestionar</a>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
      <?php endif; ?>
    </div>

  </main>
</div>

<style>
.kpi-card:hover { box-shadow:0 4px 16px rgba(0,0,0,.12); transform:translateY(-2px); }
.kpi-card.activo { box-shadow:0 0 0 3px var(--navy); transform:translateY(-2px); }
.equipo-chip:hover { box-shadow:0 2px 8px rgba(0,0,0,.12); transform:translateY(-1px); }
.equipo-chip.activo { box-shadow:0 0 0 2.5px var(--navy) !important; border-color:var(--navy) !important; transform:translateY(-1px); }
</style>

<script>
var filtroActual = null; // null=todos, 'sin-fecha', 'con-fecha', 'eq:ID'

function aplicarFiltro() {
  var rows = document.querySelectorAll('.repro-row');
  var visible = 0;
  rows.forEach(function(row) {
    var show = true;
    if (filtroActual === 'sin-fecha') {
      show = row.dataset.sf === '1';
    } else if (filtroActual === 'con-fecha') {
      show = row.dataset.sf === '0';
    } else if (filtroActual === 'vencidas') {
      show = row.dataset.vencida === '1';
    } else if (filtroActual === 'proximas') {
      show = row.dataset.proxima === '1';
    } else if (filtroActual && filtroActual.startsWith('eq:')) {
      var eqId = filtroActual.split(':')[1];
      var eqs = row.dataset.eq.split(',');
      show = eqs.indexOf(eqId) !== -1;
    }
    row.style.display = show ? '' : 'none';
    if (show) visible++;
  });

  var sinRes = document.getElementById('sinResultados');
  sinRes.style.display = visible === 0 ? 'block' : 'none';

  var btn = document.getElementById('btnLimpiarFiltro');
  btn.style.display = filtroActual ? 'flex' : 'none';

  var fa = document.getElementById('filtroActivo');
  var titulo = document.getElementById('tablaTitle');
  if (filtroActual === 'sin-fecha') {
    fa.style.display = 'block';
    fa.innerHTML = '⚠ Mostrando solo partidos <strong>sin fecha programada</strong> (' + visible + ')';
    titulo.textContent = 'Sin Fecha (' + visible + ')';
  } else if (filtroActual === 'vencidas') {
    fa.style.display = 'block';
    fa.innerHTML = '🔴 Mostrando solo partidos <strong>vencidos</strong> — fecha ya pasó y siguen sin jugarse (' + visible + ')';
    titulo.textContent = 'Vencidos (' + visible + ')';
  } else if (filtroActual === 'proximas') {
    fa.style.display = 'block';
    fa.innerHTML = '📅 Mostrando solo partidos <strong>con fecha futura</strong> (' + visible + ')';
    titulo.textContent = 'Próximos (' + visible + ')';
  } else if (filtroActual === 'con-fecha') {
    fa.style.display = 'block';
    fa.innerHTML = '📅 Mostrando solo partidos <strong>con fecha programada</strong> (' + visible + ')';
    titulo.textContent = 'Con Fecha (' + visible + ')';
  } else if (filtroActual && filtroActual.startsWith('eq:')) {
    var chip = document.querySelector('.equipo-chip.activo');
    var nombre = chip ? chip.querySelector('span').textContent : '';
    fa.style.display = 'block';
    fa.innerHTML = '🎾 Mostrando partidos de <strong>' + nombre + '</strong> (' + visible + ')';
    titulo.textContent = nombre + ' (' + visible + ')';
  } else {
    fa.style.display = 'none';
    titulo.textContent = 'Todos los Partidos Reprogramados (<?= count($reprogramados) ?>)';
  }
}

function filtrarKpi(tipo) {
  // Limpiar chips
  document.querySelectorAll('.equipo-chip').forEach(function(c){ c.classList.remove('activo'); });
  // Limpiar KPIs
  document.querySelectorAll('.kpi-card').forEach(function(c){ c.classList.remove('activo'); });

  if (filtroActual === tipo) {
    filtroActual = null;
  } else {
    filtroActual = tipo;
    var kpi = document.getElementById('kpi-' + tipo);
    if (kpi) kpi.classList.add('activo');
  }
  aplicarFiltro();
  // Scroll suave a la tabla
  if (filtroActual) document.querySelector('.card.mb-4').scrollIntoView({behavior:'smooth', block:'start'});
}

function filtrarEquipo(eqId, el) {
  // Limpiar KPIs
  document.querySelectorAll('.kpi-card').forEach(function(c){ c.classList.remove('activo'); });

  var clave = 'eq:' + eqId;
  if (filtroActual === clave) {
    filtroActual = null;
    el.classList.remove('activo');
  } else {
    filtroActual = clave;
    document.querySelectorAll('.equipo-chip').forEach(function(c){ c.classList.remove('activo'); });
    el.classList.add('activo');
  }
  aplicarFiltro();
  if (filtroActual) document.querySelector('.card.mb-4').scrollIntoView({behavior:'smooth', block:'start'});
}

function limpiarFiltro() {
  filtroActual = null;
  document.querySelectorAll('.kpi-card').forEach(function(c){ c.classList.remove('activo'); });
  document.querySelectorAll('.equipo-chip').forEach(function(c){ c.classList.remove('activo'); });
  aplicarFiltro();
}
</script>

<?php require_once '../includes/footer.php'; ?>
