<?php
$page_title = 'Admin — Reprogramaciones';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
epl_require_admin();

$db = epl_db();
epl_ensure_partidos_columnas_originales();

// ────────────────────────────────────────────────────────────────────────
// QUERY: SOLO partidos REPROGRAMADOS (los pendientes con fecha futura
// son del calendario normal del torneo, no son problema del admin acá)
// ────────────────────────────────────────────────────────────────────────
$partidos_open = $db->query("
    SELECT p.id, p.jornada, p.nombre_fecha, p.fecha_programada, p.fecha_original,
           p.estado, p.alerta_admin, p.recinto_original_id,
           l.id AS liga_id, l.nombre AS liga_nombre,
           el.id AS local_id, el.nombre AS local_nombre,
           ev.id AS visitante_id, ev.nombre AS visitante_nombre,
           r.nombre AS recinto_nombre,
           ro.nombre AS recinto_original_nombre,
           sr.motivo, sr.rival_no_responde, sr.created_at AS fecha_solicitud
    FROM partidos p
    JOIN ligas l ON l.id = p.liga_id
    JOIN equipos el ON el.id = p.equipo_local_id
    JOIN equipos ev ON ev.id = p.equipo_visitante_id
    LEFT JOIN recintos r  ON r.id  = p.recinto_id
    LEFT JOIN recintos ro ON ro.id = p.recinto_original_id
    LEFT JOIN solicitudes_reprogramacion sr ON sr.id = (
        SELECT MAX(sr2.id) FROM solicitudes_reprogramacion sr2
        WHERE sr2.partido_id = p.id AND sr2.estado != 'rechazada'
    )
    WHERE p.estado = 'reprogramado'
    ORDER BY
        (p.fecha_programada IS NULL OR DATE(p.fecha_programada)='2026-12-31') ASC,
        p.fecha_programada ASC
")->fetchAll();

// Reservas a dar de baja (partidos reprogramados con fecha/cancha original conocida)
$reservas_baja = array_values(array_filter($partidos_open, function($p) {
    return !empty($p['fecha_original']) || !empty($p['recinto_original_id']);
}));
// Ordenar por fecha_original ascendente para que las más cercanas aparezcan primero
usort($reservas_baja, function($a, $b) {
    $ta = $a['fecha_original'] ? strtotime($a['fecha_original']) : PHP_INT_MAX;
    $tb = $b['fecha_original'] ? strtotime($b['fecha_original']) : PHP_INT_MAX;
    return $ta <=> $tb;
});

// Helpers
$hoy = new DateTime('today');
$es_sin_fecha = fn($p) => !$p['fecha_programada'] || date('Y-m-d', strtotime($p['fecha_programada'])) === '2026-12-31';
$es_vencido   = fn($p) => !$es_sin_fecha($p) && new DateTime($p['fecha_programada']) < $hoy;

// Segmentar
$sin_fecha = array_values(array_filter($partidos_open, $es_sin_fecha));
$vencidos  = array_values(array_filter($partidos_open, $es_vencido));
$con_fecha = array_values(array_filter($partidos_open, fn($p) => !$es_sin_fecha($p) && !$es_vencido($p)));

// Avance del torneo
$total_jugados  = (int)$db->query("SELECT COUNT(*) FROM partidos WHERE estado IN ('jugado','walkover','no_presentado')")->fetchColumn();
$total_partidos = (int)$db->query("SELECT COUNT(*) FROM partidos")->fetchColumn();
$pct_avance     = $total_partidos > 0 ? round(($total_jugados / $total_partidos) * 100) : 0;

// ────────────────────────────────────────────────────────────────────────
// SOLICITUDES (para tab Solicitudes): pendientes + procesadas últimos 14 días
// ────────────────────────────────────────────────────────────────────────
$solicitudes_pendientes = $db->query("
    SELECT sr.id AS solicitud_id, sr.partido_id, sr.solicitante_id, sr.motivo,
           sr.fecha_propuesta, sr.rival_no_responde, sr.mutuo_acuerdo, sr.estado AS sol_estado, sr.created_at,
           p.jornada, p.fecha_programada,
           l.id AS liga_id, l.nombre AS liga_nombre,
           el.nombre AS local_nombre, ev.nombre AS visitante_nombre,
           j.nombre AS sol_nombre, j.apellido AS sol_apellido
    FROM solicitudes_reprogramacion sr
    JOIN partidos p   ON p.id = sr.partido_id
    JOIN ligas l      ON l.id = p.liga_id
    JOIN equipos el   ON el.id = p.equipo_local_id
    JOIN equipos ev   ON ev.id = p.equipo_visitante_id
    JOIN jugadores j  ON j.id = sr.solicitante_id
    WHERE sr.estado = 'pendiente'
      AND p.estado NOT IN ('jugado','walkover','no_presentado')
    ORDER BY sr.created_at DESC
")->fetchAll();
$n_solicitudes = count($solicitudes_pendientes);

// Solicitudes procesadas recientes (aprobadas o rechazadas en últimos 14 días)
$solicitudes_procesadas = $db->query("
    SELECT sr.id AS solicitud_id, sr.partido_id, sr.solicitante_id, sr.motivo,
           sr.fecha_propuesta, sr.fecha_aprobada, sr.cancha_aprobada, sr.estado AS sol_estado, sr.created_at,
           p.jornada, p.estado AS partido_estado,
           l.id AS liga_id, l.nombre AS liga_nombre,
           el.nombre AS local_nombre, ev.nombre AS visitante_nombre,
           j.nombre AS sol_nombre, j.apellido AS sol_apellido
    FROM solicitudes_reprogramacion sr
    JOIN partidos p   ON p.id = sr.partido_id
    JOIN ligas l      ON l.id = p.liga_id
    JOIN equipos el   ON el.id = p.equipo_local_id
    JOIN equipos ev   ON ev.id = p.equipo_visitante_id
    JOIN jugadores j  ON j.id = sr.solicitante_id
    WHERE sr.estado IN ('aprobada','rechazada')
      AND sr.created_at >= DATE_SUB(NOW(), INTERVAL 14 DAY)
    ORDER BY sr.created_at DESC
    LIMIT 20
")->fetchAll();
$n_procesadas = count($solicitudes_procesadas);

// Top equipos con más reprogramaciones (para llamar la atención a los que cuelgan más)
$por_equipo = [];
foreach ($partidos_open as $p) {
    foreach ([
        ['id' => (int)$p['local_id'],    'nombre' => $p['local_nombre']],
        ['id' => (int)$p['visitante_id'],'nombre' => $p['visitante_nombre']],
    ] as $eq) {
        if (!$eq['id']) continue;
        $por_equipo[$eq['id']]['nombre']    = $eq['nombre'];
        $por_equipo[$eq['id']]['liga_id']   = $p['liga_id'];
        $por_equipo[$eq['id']]['partidos'][] = $p;
    }
}
uasort($por_equipo, fn($a,$b) => count($b['partidos']) - count($a['partidos']));

// Función de fila partido (reutilizable)
function repro_fila_partido(array $p, bool $sin_fecha, bool $vencido): string {
    ob_start(); ?>
    <div class="partido-row" data-sf="<?= $sin_fecha?'1':'0' ?>" data-venc="<?= $vencido?'1':'0' ?>" data-est="<?= epl_h($p['estado']) ?>" data-eq="<?= $p['local_id'] ?>,<?= $p['visitante_id'] ?>" data-search="<?= epl_h(strtolower($p['local_nombre'].' '.$p['visitante_nombre'].' '.$p['liga_nombre'])) ?>">
      <div class="partido-row-main">
        <div class="partido-meta">
          <span class="partido-liga"><?= epl_h($p['liga_nombre']) ?></span>
          <?php if ($p['jornada']): ?>
            <span class="partido-jornada">J<?= $p['jornada'] ?></span>
          <?php endif; ?>
          <?php if ($p['rival_no_responde']): ?>
            <span class="partido-tag tag-norespon">⚠ Rival no responde</span>
          <?php endif; ?>
        </div>
        <div class="partido-equipos">
          <strong><?= epl_h($p['local_nombre']) ?></strong>
          <span class="vs">vs</span>
          <strong><?= epl_h($p['visitante_nombre']) ?></strong>
        </div>
        <div class="partido-extra">
          <?php if (!$sin_fecha): ?>
            <span class="extra-item"><svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg> <?= date('d/m H:i', strtotime($p['fecha_programada'])) ?></span>
          <?php endif; ?>
          <?php if ($p['recinto_nombre']): ?>
            <span class="extra-item" style="color:#15803d;font-weight:600">✅ <?= epl_h($p['recinto_nombre']) ?></span>
          <?php endif; ?>
          <?php if (!empty($p['motivo'])): ?>
            <span class="extra-item motivo">"<?= epl_h(mb_strimwidth($p['motivo'], 0, 70, '…')) ?>"</span>
          <?php endif; ?>
        </div>
        <?php
          // Mostrar la reserva original (cancha + fecha) destacada para que el admin la pueda dar de baja
          $_tiene_original = !empty($p['fecha_original']) || !empty($p['recinto_original_nombre']);
          if ($_tiene_original):
            $_fo = $p['fecha_original'] ?: null;
        ?>
          <div style="margin-top:.5rem;padding:.45rem .7rem;background:#fee2e2;border-left:3px solid #dc2626;border-radius:6px;font-size:.75rem;color:#991b1b;line-height:1.4;display:inline-flex;align-items:center;gap:.4rem;flex-wrap:wrap">
            <span style="font-weight:800">🚫 DAR DE BAJA:</span>
            <?php if ($_fo): ?><span><?= date('d/m H:i', strtotime($_fo)) ?></span><?php endif; ?>
            <?php if (!empty($p['recinto_original_nombre'])): ?><span>· <?= epl_h($p['recinto_original_nombre']) ?></span><?php endif; ?>
          </div>
        <?php endif; ?>
      </div>
      <a href="liga_detalle.php?id=<?= $p['liga_id'] ?>&tab=partidos" class="btn-gestionar">Gestionar</a>
    </div>
    <?php
    return ob_get_clean();
}

require_once '../includes/header.php';
?>

<div class="dash-layout">
  <?php include __DIR__ . '/partials/sidebar.php'; ?>

  <main class="dash-main">

    <!-- HEADER hero compacto -->
    <div class="dash-header" style="background:linear-gradient(135deg,#1c2f48 0%, #0f1e30 100%);border-radius:18px;padding:1.5rem 1.75rem;color:#fff;margin-bottom:1.5rem;position:relative;overflow:hidden;box-shadow:0 8px 28px rgba(28,47,72,.18)">
      <div style="position:absolute;top:-40px;right:-40px;width:180px;height:180px;background:radial-gradient(circle,rgba(201,167,98,.18) 0%,transparent 70%);pointer-events:none"></div>
      <div style="position:relative;z-index:1;display:flex;justify-content:space-between;align-items:flex-end;gap:1rem;flex-wrap:wrap">
        <div>
          <span style="font-size:.65rem;font-weight:900;letter-spacing:.25em;color:#C9A762;text-transform:uppercase">Panel admin</span>
          <h1 style="color:#fff;margin:.2rem 0 .15rem;font-size:clamp(1.5rem,3.5vw,2rem);font-family:'Anton',sans-serif;text-transform:uppercase;line-height:1">Re<span style="color:#C9A762">programaciones</span></h1>
          <p style="color:rgba(255,255,255,.7);margin-top:.2rem;font-size:.82rem">Solo partidos reprogramados — primero los urgentes, después los agendados.</p>
        </div>
        <div style="text-align:right">
          <div style="font-size:.65rem;color:rgba(255,255,255,.55);text-transform:uppercase;letter-spacing:.15em;font-weight:700">Avance del torneo</div>
          <div style="font-family:'Anton',sans-serif;font-size:2.4rem;color:#C9A762;line-height:1"><?= $pct_avance ?>%</div>
          <div style="font-size:.7rem;color:rgba(255,255,255,.6)"><?= $total_jugados ?>/<?= $total_partidos ?> partidos</div>
        </div>
      </div>
    </div>

    <!-- TABS: Solicitudes | Informe -->
    <?php $tab_inicial = isset($_GET['tab']) && $_GET['tab'] === 'informe' ? 'informe' : ($n_solicitudes > 0 ? 'solicitudes' : 'informe'); ?>
    <div class="tabs-bar">
      <button class="tab-btn <?= $tab_inicial==='solicitudes'?'active':'' ?>" data-tab="solicitudes" onclick="cambiarTab('solicitudes')">
        📨 Solicitudes
        <?php if ($n_solicitudes > 0): ?>
          <span class="tab-badge"><?= $n_solicitudes ?></span>
        <?php endif; ?>
      </button>
      <button class="tab-btn <?= $tab_inicial==='informe'?'active':'' ?>" data-tab="informe" onclick="cambiarTab('informe')">
        📊 Informe
      </button>
    </div>

    <!-- ═══════════════════ TAB SOLICITUDES ═══════════════════ -->
    <div id="tab-solicitudes" class="tab-content" style="display:<?= $tab_inicial==='solicitudes'?'block':'none' ?>">
      <?php if (empty($solicitudes_pendientes) && empty($solicitudes_procesadas)): ?>
        <section class="sec-card">
          <div style="padding:3rem;text-align:center;color:var(--gray-400)">
            <div style="font-size:3rem">✅</div>
            <p style="font-weight:700;margin-top:.5rem">No hay solicitudes</p>
            <p style="font-size:.85rem">Cuando un jugador solicite reprogramar un partido, aparecerá acá.</p>
          </div>
        </section>
      <?php else: ?>

      <?php if (empty($solicitudes_pendientes)): ?>
        <section class="sec-card" style="border-left:5px solid #10b981">
          <div style="padding:1.5rem;text-align:center;color:#15803d">
            <div style="font-size:2rem">✅</div>
            <p style="font-weight:700;margin-top:.3rem;font-size:.95rem">No hay solicitudes pendientes</p>
            <p style="font-size:.82rem;color:#64748b">Más abajo podés ver las últimas procesadas.</p>
          </div>
        </section>
      <?php endif; ?>

      <?php if (!empty($solicitudes_pendientes)): ?>
        <section class="sec-card sec-urgente">
          <div class="sec-head">
            <div>
              <h2 class="sec-title">📨 Solicitudes de reprogramación pendientes</h2>
              <p class="sec-sub">Revisa cada una, aprueba o rechaza desde la página del torneo</p>
            </div>
            <div class="sec-count danger"><?= $n_solicitudes ?></div>
          </div>
          <div class="sec-body">
            <?php foreach ($solicitudes_pendientes as $s):
              $fecha_pp = $s['fecha_propuesta']
                  ? date('d/m/Y H:i', strtotime($s['fecha_propuesta']))
                  : 'Sin fecha propuesta';
              $fecha_solicitud = date('d/m H:i', strtotime($s['created_at']));
            ?>
            <div class="partido-row">
              <div class="partido-row-main">
                <div class="partido-meta">
                  <span class="partido-liga"><?= epl_h($s['liga_nombre']) ?></span>
                  <?php if ($s['jornada']): ?>
                    <span class="partido-jornada">J<?= $s['jornada'] ?></span>
                  <?php endif; ?>
                  <?php if ($s['mutuo_acuerdo']): ?>
                    <span class="partido-tag tag-acuerdo">🤝 Mutuo acuerdo</span>
                  <?php endif; ?>
                  <?php if ($s['rival_no_responde']): ?>
                    <span class="partido-tag tag-norespon">⚠ Rival no responde</span>
                  <?php endif; ?>
                </div>
                <div class="partido-equipos">
                  <strong><?= epl_h($s['local_nombre']) ?></strong>
                  <span class="vs">vs</span>
                  <strong><?= epl_h($s['visitante_nombre']) ?></strong>
                </div>
                <div class="partido-extra">
                  <span class="extra-item">
                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                    Solicita <strong><?= epl_h($s['sol_nombre'].' '.$s['sol_apellido']) ?></strong>
                  </span>
                  <span class="extra-item">
                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/></svg>
                    Propone: <strong><?= $fecha_pp ?></strong>
                  </span>
                  <span class="extra-item" style="color:#94a3b8">hace <?= $fecha_solicitud ?></span>
                  <?php if (!empty($s['motivo'])): ?>
                    <span class="extra-item motivo">"<?= epl_h(mb_strimwidth($s['motivo'], 0, 80, '…')) ?>"</span>
                  <?php endif; ?>
                </div>
              </div>
              <a href="liga_detalle.php?id=<?= $s['liga_id'] ?>&tab=partidos" class="btn-gestionar">Revisar</a>
            </div>
            <?php endforeach; ?>
          </div>
        </section>
      <?php endif; ?>

      <!-- Solicitudes ya procesadas (aprobadas / rechazadas) -->
      <?php if (!empty($solicitudes_procesadas)): ?>
        <section class="sec-card" style="border-left:5px solid #94a3b8">
          <div class="sec-head">
            <div>
              <h2 class="sec-title">🗂️ Procesadas recientemente</h2>
              <p class="sec-sub">Últimos 14 días — aprobadas y rechazadas</p>
            </div>
            <div class="sec-count" style="background:#f1f5f9;color:#64748b"><?= $n_procesadas ?></div>
          </div>
          <div class="sec-body">
            <?php foreach ($solicitudes_procesadas as $s):
              $es_aprobada = $s['sol_estado'] === 'aprobada';
              $estado_color = $es_aprobada ? '#15803d' : '#dc2626';
              $estado_bg    = $es_aprobada ? '#dcfce7' : '#fee2e2';
              $estado_label = $es_aprobada ? '✓ APROBADA' : '✗ RECHAZADA';
              $fecha_pp = $s['fecha_aprobada']
                  ? date('d/m/Y H:i', strtotime($s['fecha_aprobada']))
                  : ($s['fecha_propuesta'] ? date('d/m/Y H:i', strtotime($s['fecha_propuesta'])) : 'Sin fecha');
              $fecha_solicitud = date('d/m H:i', strtotime($s['created_at']));
            ?>
            <div class="partido-row" style="opacity:.92">
              <div class="partido-row-main">
                <div class="partido-meta">
                  <span class="partido-tag" style="background:<?= $estado_bg ?>;color:<?= $estado_color ?>"><?= $estado_label ?></span>
                  <span class="partido-liga"><?= epl_h($s['liga_nombre']) ?></span>
                  <?php if ($s['jornada']): ?>
                    <span class="partido-jornada">J<?= $s['jornada'] ?></span>
                  <?php endif; ?>
                </div>
                <div class="partido-equipos">
                  <strong><?= epl_h($s['local_nombre']) ?></strong>
                  <span class="vs">vs</span>
                  <strong><?= epl_h($s['visitante_nombre']) ?></strong>
                </div>
                <div class="partido-extra">
                  <span class="extra-item">
                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                    Solicitó <strong><?= epl_h($s['sol_nombre'].' '.$s['sol_apellido']) ?></strong>
                  </span>
                  <?php if ($es_aprobada): ?>
                    <span class="extra-item" style="color:#15803d;font-weight:700">
                      <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                      Nueva fecha: <?= $fecha_pp ?>
                      <?php if (!empty($s['cancha_aprobada'])): ?> · <?= epl_h(trim($s['cancha_aprobada'])) ?><?php endif; ?>
                    </span>
                  <?php endif; ?>
                  <span class="extra-item" style="color:#94a3b8">solicitado <?= $fecha_solicitud ?></span>
                </div>
              </div>
              <a href="liga_detalle.php?id=<?= $s['liga_id'] ?>&tab=partidos" class="btn-gestionar" style="background:#94a3b8;color:#fff">Ver</a>
            </div>
            <?php endforeach; ?>
          </div>
        </section>
      <?php endif; ?>

      <?php endif; ?>
    </div>

    <!-- ═══════════════════ TAB INFORME ═══════════════════ -->
    <div id="tab-informe" class="tab-content" style="display:<?= $tab_inicial==='informe'?'block':'none' ?>">

    <!-- 4 KPI cards grandes y simples -->
    <div class="kpi-row">
      <button class="kpi" data-filter="all" onclick="filtrar('all', this)">
        <div class="kpi-num kpi-blue"><?= count($partidos_open) ?></div>
        <div class="kpi-label">Reprogramados totales</div>
        <div class="kpi-sub">Todos los que faltan jugar</div>
      </button>
      <button class="kpi kpi-danger" data-filter="sf" onclick="filtrar('sf', this)">
        <div class="kpi-num kpi-red"><?= count($sin_fecha) ?></div>
        <div class="kpi-label">⚠ Sin fecha</div>
        <div class="kpi-sub">No están agendados</div>
      </button>
      <button class="kpi kpi-warn" data-filter="venc" onclick="filtrar('venc', this)">
        <div class="kpi-num kpi-orange"><?= count($vencidos) ?></div>
        <div class="kpi-label">🔴 Vencidos</div>
        <div class="kpi-sub">Fecha pasó sin jugar</div>
      </button>
      <button class="kpi" data-filter="cf" onclick="filtrar('cf', this)">
        <div class="kpi-num kpi-green"><?= count($con_fecha) ?></div>
        <div class="kpi-label">📅 Con fecha futura</div>
        <div class="kpi-sub">En agenda</div>
      </button>
    </div>

    <!-- Buscador y filtros activos -->
    <div class="filtros-bar">
      <div class="busqueda">
        <svg width="16" height="16" fill="none" stroke="#94a3b8" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
        <input type="text" id="buscar" placeholder="Buscar partido, equipo o liga…" oninput="aplicarFiltros()">
      </div>
      <div id="filtroActivoMsg" class="filtro-activo" style="display:none"></div>
      <button id="btnLimpiar" class="btn-limpiar" onclick="limpiarFiltro()" style="display:none">✕ Limpiar</button>
    </div>

    <!-- ═════════════════════ SECCIÓN 1: SIN FECHA (URGENTE) ═════════════════════ -->
    <?php if (!empty($sin_fecha)): ?>
    <section class="sec-card sec-urgente" data-section="sf">
      <div class="sec-head">
        <div>
          <h2 class="sec-title">⚠ Sin fecha asignada</h2>
          <p class="sec-sub">Resolvé estos primero — los equipos están esperando que se agende</p>
        </div>
        <div class="sec-count danger"><?= count($sin_fecha) ?></div>
      </div>
      <div class="sec-body">
        <?php foreach ($sin_fecha as $p): ?>
          <?= repro_fila_partido($p, true, false) ?>
        <?php endforeach; ?>
      </div>
    </section>
    <?php endif; ?>

    <!-- ═════════════════════ SECCIÓN 2: VENCIDOS ═════════════════════ -->
    <?php if (!empty($vencidos)): ?>
    <section class="sec-card sec-vencido" data-section="venc">
      <div class="sec-head">
        <div>
          <h2 class="sec-title">🔴 Vencidos</h2>
          <p class="sec-sub">La fecha ya pasó y todavía no se jugaron. Hay que reagendar o cargar el resultado.</p>
        </div>
        <div class="sec-count warn"><?= count($vencidos) ?></div>
      </div>
      <div class="sec-body">
        <?php foreach ($vencidos as $p): ?>
          <?= repro_fila_partido($p, false, true) ?>
        <?php endforeach; ?>
      </div>
    </section>
    <?php endif; ?>

    <!-- ═════════════════════ SECCIÓN 3: CON FECHA FUTURA (agrupado por día) ═════════════════════ -->
    <?php if (!empty($con_fecha)): ?>
    <?php
      // Agrupar por fecha
      $por_dia = [];
      foreach ($con_fecha as $p) {
          $dia = date('Y-m-d', strtotime($p['fecha_programada']));
          $por_dia[$dia][] = $p;
      }
      ksort($por_dia);
      $dias_es = ['Mon'=>'Lunes','Tue'=>'Martes','Wed'=>'Miércoles','Thu'=>'Jueves','Fri'=>'Viernes','Sat'=>'Sábado','Sun'=>'Domingo'];
      $meses_es = ['01'=>'enero','02'=>'febrero','03'=>'marzo','04'=>'abril','05'=>'mayo','06'=>'junio','07'=>'julio','08'=>'agosto','09'=>'septiembre','10'=>'octubre','11'=>'noviembre','12'=>'diciembre'];
    ?>
    <section class="sec-card sec-futuro" data-section="cf">
      <div class="sec-head">
        <div>
          <h2 class="sec-title">📅 Con fecha futura</h2>
          <p class="sec-sub">Agendados — en orden cronológico, los más próximos arriba</p>
        </div>
        <div class="sec-count info"><?= count($con_fecha) ?></div>
      </div>
      <div class="sec-body">
        <?php foreach ($por_dia as $dia => $ps):
          $ts = strtotime($dia);
          $dia_label = $dias_es[date('D', $ts)] . ' ' . date('d', $ts) . ' de ' . $meses_es[date('m', $ts)] . ' ' . date('Y', $ts);
          $delta_dias = (int)floor(($ts - $hoy->getTimestamp()) / 86400);
          $delta_label = $delta_dias === 0 ? 'HOY' : ($delta_dias === 1 ? 'mañana' : "en $delta_dias días");
        ?>
        <div class="dia-group">
          <div class="dia-header">
            <span class="dia-fecha"><?= $dia_label ?></span>
            <span class="dia-delta"><?= $delta_label ?></span>
            <span class="dia-count"><?= count($ps) ?> <?= count($ps)===1?'partido':'partidos' ?></span>
          </div>
          <?php foreach ($ps as $p): ?>
            <?= repro_fila_partido($p, false, false) ?>
          <?php endforeach; ?>
        </div>
        <?php endforeach; ?>
      </div>
    </section>
    <?php endif; ?>

    <!-- ═════════════════════ SECCIÓN 4: TOP equipos con más reprogramaciones ═════════════════════ -->
    <?php if (!empty($por_equipo)):
      $top_equipos = array_slice($por_equipo, 0, 10, true);
    ?>
    <section class="sec-card sec-equipos">
      <div class="sec-head">
        <div>
          <h2 class="sec-title">🏷️ Equipos con más partidos reprogramados</h2>
          <p class="sec-sub">Los que más cuelgan — top 10. Click en un equipo para filtrar sus partidos.</p>
        </div>
      </div>
      <div class="sec-body equipos-grid">
        <?php foreach ($top_equipos as $eq_id => $eq):
          $cnt = count($eq['partidos']);
          $sev = $cnt >= 3 ? 'critico' : ($cnt === 2 ? 'medio' : 'normal');
        ?>
          <button class="equipo-card equipo-<?= $sev ?>" data-filter="eq:<?= $eq_id ?>" onclick="filtrar('eq:<?= $eq_id ?>', this)">
            <div class="equipo-cnt"><?= $cnt ?></div>
            <div class="equipo-nombre"><?= epl_h($eq['nombre']) ?></div>
            <div class="equipo-tag"><?= $cnt===1?'partido reprogramado':'partidos reprogramados' ?></div>
          </button>
        <?php endforeach; ?>
      </div>
    </section>
    <?php endif; ?>

    <?php if (empty($partidos_open)): ?>
      <section class="sec-card">
        <div style="padding:3rem;text-align:center;color:var(--gray-400)">
          <div style="font-size:3rem">🎉</div>
          <p style="font-weight:700;margin-top:.5rem">No hay partidos reprogramados</p>
          <p style="font-size:.85rem">Todo el calendario está al día.</p>
        </div>
      </section>
    <?php endif; ?>

    <div id="noResults" style="display:none;padding:2.5rem;text-align:center;background:#fff;border-radius:14px;border:1px solid #e2e8f0;color:#94a3b8;margin-top:1rem">
      <div style="font-size:2.5rem">🔍</div>
      <p style="font-weight:600;margin-top:.5rem">Sin resultados</p>
      <p style="font-size:.82rem">No hay partidos que coincidan con el filtro.</p>
    </div>

    </div><!-- /tab-informe -->

  </main>
</div>

<style>
/* ── KPIs ─────────────────────────────────────────────── */
.kpi-row {
  display:grid; grid-template-columns:repeat(auto-fit, minmax(180px, 1fr));
  gap:.85rem; margin-bottom:1.25rem;
}
.kpi {
  background:#fff; border:1.5px solid #e2e8f0; border-radius:14px;
  padding:1.1rem 1.25rem; text-align:left; cursor:pointer;
  transition:all .18s ease; font-family:inherit;
  display:flex; flex-direction:column; gap:.2rem;
}
.kpi:hover { border-color:var(--navy); transform:translateY(-2px); box-shadow:0 6px 20px rgba(0,0,0,.08); }
.kpi.activo { border-color:var(--navy); box-shadow:0 0 0 3px rgba(28,47,72,.12); }
.kpi-num { font-family:'Anton',sans-serif; font-size:2.4rem; line-height:1; }
.kpi-blue   { color:var(--navy); }
.kpi-red    { color:#dc2626; }
.kpi-orange { color:#ea580c; }
.kpi-green  { color:#059669; }
.kpi-label { font-size:.82rem; font-weight:800; color:var(--navy); text-transform:uppercase; letter-spacing:.05em; margin-top:.25rem; }
.kpi-sub   { font-size:.7rem; color:#94a3b8; }
.kpi-danger { border-left:4px solid #dc2626; }
.kpi-warn   { border-left:4px solid #ea580c; }

/* ── Filtros bar ─────────────────────────────────────── */
.filtros-bar { display:flex; align-items:center; gap:.85rem; margin-bottom:1.5rem; flex-wrap:wrap; }
.busqueda { position:relative; flex:1; min-width:240px; max-width:480px; }
.busqueda svg { position:absolute; left:.85rem; top:50%; transform:translateY(-50%); }
.busqueda input {
  width:100%; padding:.7rem 1rem .7rem 2.5rem; border:1.5px solid #e2e8f0;
  border-radius:10px; font-size:.88rem; font-family:'Montserrat',sans-serif;
  background:#fff; transition:border-color .15s;
}
.busqueda input:focus { outline:none; border-color:var(--gold); }
.filtro-activo { font-size:.78rem; font-weight:700; color:#1d4ed8; background:#eff6ff;
                 border:1px solid #bfdbfe; border-radius:8px; padding:.5rem .85rem; }
.btn-limpiar { background:#f1f5f9; border:1px solid #cbd5e1; color:var(--navy);
               padding:.5rem .9rem; border-radius:8px; font-size:.75rem;
               font-weight:700; cursor:pointer; font-family:inherit; }
.btn-limpiar:hover { background:#e2e8f0; }

/* ── Sección Card ────────────────────────────────────── */
.sec-card { background:#fff; border-radius:18px; border:1px solid #e2e8f0;
            box-shadow:0 4px 20px rgba(0,0,0,.03); margin-bottom:1.25rem; overflow:hidden; }
.sec-urgente { border-left:5px solid #dc2626; }
.sec-vencido { border-left:5px solid #ea580c; }
.sec-futuro  { border-left:5px solid #2563eb; }
.sec-equipos { border-left:5px solid #C9A762; }
.sec-head { display:flex; justify-content:space-between; align-items:center; padding:1.15rem 1.5rem;
            border-bottom:1px solid #f1f5f9; gap:1rem; flex-wrap:wrap; }
.sec-title { font-family:'Anton',sans-serif; font-size:1.05rem; color:var(--navy); text-transform:uppercase;
             margin:0; letter-spacing:.03em; }
.sec-sub { font-size:.78rem; color:#64748b; margin:.25rem 0 0; font-weight:500; }
.sec-count { font-family:'Anton',sans-serif; font-size:1.6rem; padding:.3rem .85rem; border-radius:10px; line-height:1; }
.sec-count.danger { background:#fee2e2; color:#dc2626; }
.sec-count.warn   { background:#fed7aa; color:#ea580c; }
.sec-count.info   { background:#dbeafe; color:#2563eb; }
.sec-body { padding:.5rem .5rem 1rem; }

/* ── Día agrupado ────────────────────────────────────── */
.dia-group { margin-bottom:.5rem; }
.dia-header { display:flex; align-items:center; gap:.85rem; padding:.7rem 1rem .5rem;
              font-size:.7rem; font-weight:800; text-transform:uppercase; letter-spacing:.06em;
              color:#3730a3; }
.dia-fecha { background:#e0e7ff; padding:.3rem .7rem; border-radius:8px; }
.dia-delta { color:#64748b; font-weight:600; }
.dia-count { margin-left:auto; color:#94a3b8; font-weight:600; }

/* ── Partido row ──────────────────────────────────────── */
.partido-row {
  display:flex; align-items:center; justify-content:space-between; gap:1rem;
  padding:.85rem 1rem; margin:.25rem .5rem; background:#fafbfc;
  border-radius:10px; border:1px solid transparent; transition:all .15s ease;
}
.partido-row:hover { background:#fff; border-color:#e2e8f0; box-shadow:0 2px 12px rgba(0,0,0,.05); }
.partido-row-main { flex:1; min-width:0; display:flex; flex-direction:column; gap:.3rem; }
.partido-meta { display:flex; gap:.4rem; flex-wrap:wrap; align-items:center; }
.partido-liga { font-size:.66rem; font-weight:800; color:#64748b; text-transform:uppercase; letter-spacing:.08em; }
.partido-jornada { background:#e0e7ff; color:#3730a3; font-size:.62rem; font-weight:800;
                    border-radius:5px; padding:.1rem .4rem; }
.partido-tag { font-size:.62rem; font-weight:800; border-radius:5px; padding:.1rem .45rem; }
.tag-reprog   { background:#fef3c7; color:#92400e; }
.tag-norespon { background:#fee2e2; color:#991b1b; }
.partido-equipos { font-size:.92rem; color:var(--navy); }
.partido-equipos .vs { color:#94a3b8; margin:0 .35rem; font-weight:500; }
.partido-extra { display:flex; gap:1rem; flex-wrap:wrap; font-size:.72rem; color:#64748b; margin-top:.2rem; }
.extra-item { display:inline-flex; align-items:center; gap:.3rem; }
.extra-item.motivo { color:#94a3b8; font-style:italic; max-width:260px; overflow:hidden;
                     text-overflow:ellipsis; white-space:nowrap; }
.btn-gestionar {
  background:var(--navy); color:#C9A762; padding:.55rem 1.1rem;
  border-radius:8px; font-size:.72rem; font-weight:900; text-decoration:none;
  text-transform:uppercase; letter-spacing:.08em; flex-shrink:0;
  transition:all .15s; white-space:nowrap;
}
.btn-gestionar:hover { background:#C9A762; color:var(--navy); transform:translateY(-1px); }

/* ── Equipos grid ─────────────────────────────────────── */
.equipos-grid { display:grid; grid-template-columns:repeat(auto-fill, minmax(180px, 1fr));
                gap:.65rem; padding:1rem 1.25rem 1.25rem; }
.equipo-card {
  background:#fff; border:1.5px solid #e2e8f0; border-radius:12px;
  padding:.85rem .9rem; cursor:pointer; transition:all .15s;
  display:flex; flex-direction:column; gap:.1rem; text-align:left;
  font-family:inherit;
}
.equipo-card:hover { transform:translateY(-2px); box-shadow:0 6px 16px rgba(0,0,0,.08); }
.equipo-card.activo { border-color:var(--navy); box-shadow:0 0 0 3px rgba(28,47,72,.1); }
.equipo-cnt { font-family:'Anton',sans-serif; font-size:1.6rem; line-height:1; }
.equipo-normal  { border-left:4px solid #94a3b8; } .equipo-normal  .equipo-cnt { color:#475569; }
.equipo-medio   { border-left:4px solid #f59e0b; } .equipo-medio   .equipo-cnt { color:#b45309; }
.equipo-critico { border-left:4px solid #dc2626; } .equipo-critico .equipo-cnt { color:#dc2626; }
.equipo-nombre { font-weight:700; font-size:.82rem; color:var(--navy); line-height:1.2; margin-top:.25rem;
                 white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.equipo-tag { font-size:.65rem; color:#94a3b8; font-weight:600; text-transform:uppercase; letter-spacing:.04em; }

@media (max-width:640px) {
  .partido-row { flex-direction:column; align-items:flex-start; }
  .btn-gestionar { width:100%; text-align:center; }
}

/* ── Tabs ─────────────────────────────────────────────── */
.tabs-bar {
  display:flex; gap:.5rem; margin-bottom:1.5rem;
  border-bottom:2px solid #e2e8f0;
}
.tab-btn {
  background:none; border:none; padding:.85rem 1.5rem;
  font-family:inherit; font-size:.85rem; font-weight:800;
  text-transform:uppercase; letter-spacing:.05em; color:#64748b;
  cursor:pointer; border-bottom:3px solid transparent;
  margin-bottom:-2px; display:inline-flex; align-items:center; gap:.5rem;
  transition:all .15s;
}
.tab-btn:hover { color:var(--navy); }
.tab-btn.active { color:var(--navy); border-bottom-color:var(--gold); }
.tab-badge {
  background:#dc2626; color:#fff; font-size:.65rem; font-weight:900;
  border-radius:999px; padding:.1rem .5rem; min-width:20px; text-align:center;
}
.tab-content { animation: tabFadeIn .25s ease; }
@keyframes tabFadeIn { from { opacity:0; transform:translateY(4px); } to { opacity:1; transform:translateY(0); } }

/* Tag mutuo acuerdo */
.tag-acuerdo { background:#dcfce7; color:#15803d; }
</style>

<script>
function cambiarTab(tab) {
  document.querySelectorAll('.tab-btn').forEach(b => b.classList.toggle('active', b.dataset.tab === tab));
  document.querySelectorAll('.tab-content').forEach(c => c.style.display = 'none');
  const el = document.getElementById('tab-' + tab);
  if (el) el.style.display = 'block';
  // Persistir tab activa en URL sin recargar
  const url = new URL(window.location.href);
  url.searchParams.set('tab', tab);
  window.history.replaceState({}, '', url);
}

let filtroActual = null;

function aplicarFiltros() {
  const q = (document.getElementById('buscar').value || '').toLowerCase().trim();
  const sections = document.querySelectorAll('section.sec-card[data-section]');
  let totalVisibles = 0;

  // Si hay filtro de sección o equipo, ocultar las demás secciones
  sections.forEach(sec => {
    const secType = sec.dataset.section;
    let mostrarSec = true;
    if (filtroActual === 'sf'   && secType !== 'sf')   mostrarSec = false;
    if (filtroActual === 'venc' && secType !== 'venc') mostrarSec = false;
    if (filtroActual === 'cf'   && secType !== 'cf')   mostrarSec = false;
    sec.style.display = mostrarSec ? '' : 'none';
  });

  // Filtrar filas por búsqueda o equipo
  document.querySelectorAll('.partido-row').forEach(row => {
    let show = true;
    if (q) {
      show = (row.dataset.search || '').includes(q);
    }
    if (filtroActual && filtroActual.startsWith('eq:')) {
      const eqId = filtroActual.split(':')[1];
      const eqs = (row.dataset.eq || '').split(',');
      if (eqs.indexOf(eqId) === -1) show = false;
    }
    row.style.display = show ? '' : 'none';
    if (show) totalVisibles++;
  });

  // Ocultar grupos de día vacíos
  document.querySelectorAll('.dia-group').forEach(g => {
    const visibles = g.querySelectorAll('.partido-row:not([style*="display: none"])').length;
    g.style.display = visibles > 0 ? '' : 'none';
  });

  // Mensaje filtro activo
  const fa = document.getElementById('filtroActivoMsg');
  const btn = document.getElementById('btnLimpiar');
  let msg = '';
  if (filtroActual === 'sf')   msg = '⚠ Solo sin fecha';
  else if (filtroActual === 'venc') msg = '🔴 Solo vencidos';
  else if (filtroActual === 'cf')   msg = '📅 Solo con fecha';
  else if (filtroActual && filtroActual.startsWith('eq:')) {
    const card = document.querySelector('.equipo-card.activo .equipo-nombre');
    msg = '🎾 ' + (card ? card.textContent : 'Equipo');
  }
  if (msg || q) {
    fa.style.display = 'block';
    fa.textContent = (msg || 'Búsqueda activa') + ' · ' + totalVisibles + ' resultado' + (totalVisibles===1?'':'s');
    btn.style.display = 'inline-flex';
  } else {
    fa.style.display = 'none';
    btn.style.display = 'none';
  }

  // No results global
  const noRes = document.getElementById('noResults');
  if (noRes) noRes.style.display = totalVisibles === 0 ? 'block' : 'none';
}

function filtrar(tipo, btn) {
  // Limpiar visual
  document.querySelectorAll('.kpi').forEach(k => k.classList.remove('activo'));
  document.querySelectorAll('.equipo-card').forEach(e => e.classList.remove('activo'));

  if (filtroActual === tipo || tipo === 'all') {
    filtroActual = null;
  } else {
    filtroActual = tipo;
    if (btn) btn.classList.add('activo');
  }
  aplicarFiltros();
  // Scroll suave si filtro aplicado
  if (filtroActual && filtroActual !== 'all') {
    const target = filtroActual.startsWith('eq:')
      ? document.querySelector('.sec-urgente, .sec-vencido, .sec-futuro')
      : document.querySelector('section[data-section]:not([style*="display: none"])');
    if (target) target.scrollIntoView({behavior:'smooth', block:'start'});
  }
}

function limpiarFiltro() {
  document.getElementById('buscar').value = '';
  filtroActual = null;
  document.querySelectorAll('.kpi').forEach(k => k.classList.remove('activo'));
  document.querySelectorAll('.equipo-card').forEach(e => e.classList.remove('activo'));
  aplicarFiltros();
}
</script>

<?php require_once '../includes/footer.php'; ?>
