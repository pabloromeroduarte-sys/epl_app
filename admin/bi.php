<?php
$page_title = 'Dashboard BI';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
epl_require_admin();

$db = epl_db();

// ── Filtro de liga ──────────────────────────────────────────────────────────
$f_liga = (int)($_GET['liga_id'] ?? 0);
$ligas  = $db->query("SELECT id, nombre, estado FROM ligas ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
$liga_where = $f_liga ? " AND p.liga_id = " . $f_liga . " " : "";

// ── KPIs de progreso de partidos ────────────────────────────────────────────
function bi_count($db, $sql) { return (int)$db->query($sql)->fetchColumn(); }

$base = "FROM partidos p WHERE 1=1 {$liga_where}";
$prog = [
    'total'        => bi_count($db, "SELECT COUNT(*) {$base}"),
    'jugados'      => bi_count($db, "SELECT COUNT(*) {$base} AND p.estado IN ('jugado','walkover','no_presentado')"),
    'vencidos'     => bi_count($db, "SELECT COUNT(*) {$base} AND p.estado IN ('pendiente','reprogramado') AND p.fecha_programada IS NOT NULL AND p.fecha_programada < NOW()"),
    'futuros'      => bi_count($db, "SELECT COUNT(*) {$base} AND p.estado IN ('pendiente','reprogramado') AND p.fecha_programada >= NOW()"),
    'sin_fecha'    => bi_count($db, "SELECT COUNT(*) {$base} AND p.estado IN ('pendiente','reprogramado') AND p.fecha_programada IS NULL"),
    'reprogramados'=> bi_count($db, "SELECT COUNT(*) {$base} AND p.estado = 'reprogramado'"),
];
$prog['por_jugar'] = $prog['total'] - $prog['jugados'];
$pct_jugado = $prog['total'] > 0 ? round($prog['jugados'] / $prog['total'] * 100) : 0;

// ── Partidos pendientes de atención (vencidos sin resultado + sin fecha) ──────
$stPend = $db->prepare("
    SELECT p.id, p.jornada, p.fecha_programada, p.estado,
           l.nombre AS liga_nombre,
           el.nombre AS local_nombre, ev.nombre AS visitante_nombre,
           DATEDIFF(NOW(), p.fecha_programada) AS dias_atraso
    FROM partidos p
    JOIN ligas l ON l.id = p.liga_id
    JOIN equipos el ON el.id = p.equipo_local_id
    JOIN equipos ev ON ev.id = p.equipo_visitante_id
    WHERE p.estado IN ('pendiente','reprogramado')
      AND (p.fecha_programada < NOW() OR p.fecha_programada IS NULL)
      " . ($f_liga ? " AND p.liga_id = {$f_liga} " : "") . "
    ORDER BY (p.fecha_programada IS NULL) ASC, p.fecha_programada ASC
    LIMIT 40
");
$stPend->execute();
$pendientes = $stPend->fetchAll(PDO::FETCH_ASSOC);

// ── Últimos resultados ───────────────────────────────────────────────────────
$stRes = $db->prepare("
    SELECT p.id, p.jornada, p.estado, p.sets_local, p.sets_visitante, p.ganador_id,
           p.resultado_ingresado_at,
           l.nombre AS liga_nombre,
           el.id AS local_id, el.nombre AS local_nombre,
           ev.id AS visitante_id, ev.nombre AS visitante_nombre
    FROM partidos p
    JOIN ligas l ON l.id = p.liga_id
    JOIN equipos el ON el.id = p.equipo_local_id
    JOIN equipos ev ON ev.id = p.equipo_visitante_id
    WHERE p.estado IN ('jugado','walkover','no_presentado')
      " . ($f_liga ? " AND p.liga_id = {$f_liga} " : "") . "
    ORDER BY p.resultado_ingresado_at DESC, p.fecha_jugado DESC
    LIMIT 8
");
$stRes->execute();
$resultados = $stRes->fetchAll(PDO::FETCH_ASSOC);

// ── Equipos con partidos pendientes (pendiente + reprogramado, sin resultado) ─
$stEq = $db->prepare("
    SELECT p.id, p.jornada, p.fecha_programada, p.estado,
           p.equipo_local_id, p.equipo_visitante_id,
           el.nombre AS local_nombre, ev.nombre AS visitante_nombre,
           l.nombre AS liga_nombre
    FROM partidos p
    JOIN equipos el ON el.id = p.equipo_local_id
    JOIN equipos ev ON ev.id = p.equipo_visitante_id
    JOIN ligas l ON l.id = p.liga_id
    WHERE p.estado IN ('pendiente','reprogramado')
      AND (
            p.estado = 'reprogramado'
            OR (p.fecha_programada IS NOT NULL AND p.fecha_programada < NOW())
          )
      " . ($f_liga ? " AND p.liga_id = {$f_liga} " : "") . "
    ORDER BY (p.fecha_programada IS NULL) ASC, p.fecha_programada ASC
");
$stEq->execute();
$equipos_pend = []; // equipo_id => [nombre, total, reprog, vencidos, partidos[]]
foreach ($stEq->fetchAll(PDO::FETCH_ASSOC) as $p) {
    $vencido = !empty($p['fecha_programada']) && strtotime($p['fecha_programada']) < time();
    foreach ([
        ['id'=>$p['equipo_local_id'],     'nombre'=>$p['local_nombre'],     'rival'=>$p['visitante_nombre']],
        ['id'=>$p['equipo_visitante_id'], 'nombre'=>$p['visitante_nombre'], 'rival'=>$p['local_nombre']],
    ] as $eq) {
        $eid = (int)$eq['id'];
        if (!isset($equipos_pend[$eid])) {
            $equipos_pend[$eid] = ['nombre'=>$eq['nombre'], 'total'=>0, 'reprog'=>0, 'vencidos'=>0, 'partidos'=>[]];
        }
        $equipos_pend[$eid]['total']++;
        if ($p['estado'] === 'reprogramado') $equipos_pend[$eid]['reprog']++;
        if ($vencido) $equipos_pend[$eid]['vencidos']++;
        $equipos_pend[$eid]['partidos'][] = [
            'id'=>$p['id'], 'rival'=>$eq['rival'], 'liga'=>$p['liga_nombre'],
            'jornada'=>$p['jornada'], 'estado'=>$p['estado'],
            'fecha'=>$p['fecha_programada'], 'vencido'=>$vencido,
        ];
    }
}
// Ordenar por cantidad de pendientes (desc)
uasort($equipos_pend, fn($a,$b) => $b['total'] <=> $a['total']);

// ── Ritmo: partidos jugados por semana (últimas 10) ──────────────────────────
$ritmo = $db->query("
    SELECT DATE_FORMAT(p.fecha_jugado, '%x-%v') AS sem,
           MIN(DATE(p.fecha_jugado)) AS desde,
           COUNT(*) AS n
    FROM partidos p
    WHERE p.estado IN ('jugado','walkover','no_presentado')
      AND p.fecha_jugado IS NOT NULL
      " . ($f_liga ? " AND p.liga_id = {$f_liga} " : "") . "
    GROUP BY sem ORDER BY sem DESC LIMIT 10
")->fetchAll(PDO::FETCH_ASSOC);
$ritmo = array_reverse($ritmo);

// ── Demografía de jugadores (global) ─────────────────────────────────────────
$demo = [];

// Nivel / categoría
$demo['nivel'] = [];
foreach ($db->query("SELECT nivel, COUNT(*) n FROM jugadores WHERE estado='activo' AND nivel IS NOT NULL GROUP BY nivel ORDER BY nivel")->fetchAll(PDO::FETCH_ASSOC) as $r)
    $demo['nivel'][$r['nivel'] . 'ª cat.'] = (int)$r['n'];

// Comuna (top 12)
$demo['comuna'] = [];
foreach ($db->query("SELECT comuna, COUNT(*) n FROM jugadores WHERE estado='activo' AND comuna<>'' GROUP BY comuna ORDER BY n DESC LIMIT 12")->fetchAll(PDO::FETCH_ASSOC) as $r)
    $demo['comuna'][$r['comuna']] = (int)$r['n'];

// Sexo
$demo['sexo'] = [];
foreach ($db->query("SELECT sexo, COUNT(*) n FROM jugadores WHERE estado='activo' AND sexo IS NOT NULL GROUP BY sexo")->fetchAll(PDO::FETCH_ASSOC) as $r) {
    $lbl = ['M'=>'Masculino','F'=>'Femenino','otro'=>'Otro'][$r['sexo']] ?? $r['sexo'];
    $demo['sexo'][$lbl] = (int)$r['n'];
}

// Edad (rangos, calculado en PHP)
$rangos = ['18-24'=>0,'25-34'=>0,'35-44'=>0,'45-54'=>0,'55+'=>0];
foreach ($db->query("SELECT fecha_nacimiento FROM jugadores WHERE estado='activo' AND fecha_nacimiento IS NOT NULL")->fetchAll(PDO::FETCH_COLUMN) as $fn) {
    $edad = (int)((time() - strtotime($fn)) / 31557600);
    if ($edad < 25) $rangos['18-24']++;
    elseif ($edad < 35) $rangos['25-34']++;
    elseif ($edad < 45) $rangos['35-44']++;
    elseif ($edad < 55) $rangos['45-54']++;
    else $rangos['55+']++;
}
$demo['edad'] = array_filter($rangos, fn($v) => $v > 0);

// Marca de pala (top 10)
$demo['pala'] = [];
foreach ($db->query("SELECT pala, COUNT(*) n FROM jugadores WHERE estado='activo' AND pala<>'' GROUP BY pala ORDER BY n DESC LIMIT 10")->fetchAll(PDO::FETCH_ASSOC) as $r)
    $demo['pala'][$r['pala']] = (int)$r['n'];

// Frecuencia de juego
$demo['frecuencia'] = [];
$frecLbl = ['1_semana'=>'1 vez/semana','2_semana'=>'2 veces/semana','3_o_mas'=>'3 o más/semana','ocasional'=>'Ocasional'];
foreach ($db->query("SELECT frecuencia_juego, COUNT(*) n FROM jugadores WHERE estado='activo' AND frecuencia_juego IS NOT NULL GROUP BY frecuencia_juego")->fetchAll(PDO::FETCH_ASSOC) as $r)
    $demo['frecuencia'][$frecLbl[$r['frecuencia_juego']] ?? $r['frecuencia_juego']] = (int)$r['n'];

// Lado de cancha
$demo['lado'] = [];
$ladoLbl = ['derecha'=>'Derecha (drive)','reves'=>'Revés','ambos'=>'Ambos'];
foreach ($db->query("SELECT lado, COUNT(*) n FROM jugadores WHERE estado='activo' AND lado IS NOT NULL GROUP BY lado")->fetchAll(PDO::FETCH_ASSOC) as $r)
    $demo['lado'][$ladoLbl[$r['lado']] ?? $r['lado']] = (int)$r['n'];

// Profesión (top 10)
$demo['profesion'] = [];
foreach ($db->query("SELECT profesion, COUNT(*) n FROM jugadores WHERE estado='activo' AND profesion<>'' GROUP BY profesion ORDER BY n DESC LIMIT 10")->fetchAll(PDO::FETCH_ASSOC) as $r)
    $demo['profesion'][$r['profesion']] = (int)$r['n'];

$total_jug = bi_count($db, "SELECT COUNT(*) FROM jugadores p WHERE p.estado='activo'");
?>
<?php require_once '../includes/header.php'; ?>
<style>
.bi-kpi-grid { display:grid;grid-template-columns:repeat(auto-fill,minmax(150px,1fr));gap:.85rem;margin-bottom:1.5rem }
.bi-kpi { background:#fff;border:1.5px solid #e2e8f0;border-radius:14px;padding:1rem 1.1rem;position:relative;overflow:hidden }
.bi-kpi-num { font-family:var(--font-head);font-size:1.9rem;line-height:1;color:#1C2F48 }
.bi-kpi-lbl { font-size:.72rem;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.04em;margin-top:.35rem }
.bi-kpi-bar { position:absolute;left:0;bottom:0;height:4px;border-radius:0 4px 0 0 }
.bi-section { background:#fff;border:1.5px solid #e2e8f0;border-radius:16px;margin-bottom:1.5rem;overflow:hidden }
.bi-section-head { display:flex;align-items:center;justify-content:space-between;gap:.75rem;padding:.9rem 1.25rem;border-bottom:1.5px solid #e2e8f0;flex-wrap:wrap }
.bi-section-title { font-weight:800;font-size:.95rem;color:#1C2F48;display:flex;align-items:center;gap:.5rem }
.bi-row { display:flex;align-items:center;gap:1rem;padding:.7rem 1.25rem;border-bottom:1px solid #f1f5f9;font-size:.85rem }
.bi-row:last-child { border-bottom:none }
.bi-pill { font-size:.68rem;font-weight:800;padding:.18rem .5rem;border-radius:999px;white-space:nowrap }
.bi-link { font-size:.8rem;font-weight:700;color:#1e40af;text-decoration:none }
@media(max-width:600px){ .bi-row{flex-wrap:wrap;gap:.4rem} }
/* Desplegable equipos */
.bi-equipo { border-bottom:1px solid #f1f5f9 }
.bi-equipo:last-child { border-bottom:none }
.bi-equipo summary { display:flex;align-items:center;justify-content:space-between;gap:.75rem;padding:.7rem 1.25rem;cursor:pointer;list-style:none;user-select:none;transition:background .12s }
.bi-equipo summary::-webkit-details-marker { display:none }
.bi-equipo summary:hover { background:#f8fafc }
.bi-equipo[open] summary { background:#f1f5f9 }
.bi-chev { color:#94a3b8;transition:transform .18s;flex-shrink:0 }
.bi-equipo[open] .bi-chev { transform:rotate(90deg) }
.bi-equipo-body { background:#fafbfc;padding:.25rem 0 }
.bi-equipo-partido { display:flex;align-items:center;gap:.65rem;padding:.55rem 1.25rem .55rem 2.5rem;border-top:1px solid #f1f5f9;font-size:.84rem }
@media(max-width:600px){ .bi-equipo-partido{flex-wrap:wrap;gap:.4rem;padding-left:1.5rem} }
</style>

<div class="dash-layout">
  <?php include __DIR__ . '/partials/sidebar.php'; ?>
  <main class="dash-main">

    <!-- Header + filtro -->
    <div class="dash-header" style="display:flex;align-items:flex-end;justify-content:space-between;flex-wrap:wrap;gap:1rem;margin-bottom:1.25rem">
      <div>
        <h1 class="dash-title">📊 Dashboard BI</h1>
        <p style="color:#64748b;font-size:.88rem;margin:.25rem 0 0">Control de torneos, partidos y jugadores en un solo lugar.</p>
      </div>
      <form method="GET" style="display:flex;align-items:center;gap:.5rem">
        <label style="font-size:.78rem;font-weight:700;color:#64748b">Liga:</label>
        <select name="liga_id" onchange="this.form.submit()" style="padding:.5rem .8rem;border:1.5px solid #e2e8f0;border-radius:9px;font-size:.85rem;font-weight:600;color:#1C2F48;background:#fff">
          <option value="0">Todas las ligas</option>
          <?php foreach ($ligas as $l): ?>
            <option value="<?= $l['id'] ?>" <?= $f_liga===(int)$l['id']?'selected':'' ?>><?= epl_h($l['nombre']) ?></option>
          <?php endforeach; ?>
        </select>
      </form>
    </div>

    <!-- ══ Progreso ══ -->
    <div class="bi-section" style="border:none;background:linear-gradient(135deg,#1C2F48,#2a4365);color:#fff;padding:1.5rem 1.75rem">
      <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:1rem;margin-bottom:1rem">
        <div>
          <div style="font-size:.72rem;font-weight:800;color:#C9A762;text-transform:uppercase;letter-spacing:.1em">Progreso de partidos</div>
          <div style="font-family:var(--font-head);font-size:2.4rem;line-height:1.1;margin-top:.3rem">
            <?= $prog['jugados'] ?> <span style="color:rgba(255,255,255,.45);font-size:1.5rem">/ <?= $prog['total'] ?></span>
          </div>
          <div style="font-size:.85rem;color:rgba(255,255,255,.7)">Faltan <strong style="color:#fff"><?= $prog['por_jugar'] ?></strong> partidos por jugar</div>
        </div>
        <div style="text-align:right">
          <div style="font-family:var(--font-head);font-size:2.4rem;color:#4ade80;line-height:1"><?= $pct_jugado ?>%</div>
          <div style="font-size:.72rem;color:rgba(255,255,255,.6);text-transform:uppercase;letter-spacing:.05em">Completado</div>
        </div>
      </div>
      <!-- Barra -->
      <div style="height:14px;background:rgba(255,255,255,.15);border-radius:999px;overflow:hidden">
        <div style="height:100%;width:<?= $pct_jugado ?>%;background:linear-gradient(90deg,#22c55e,#4ade80);border-radius:999px;transition:width .5s"></div>
      </div>
    </div>

    <!-- ══ KPI cards ══ -->
    <div class="bi-kpi-grid">
      <div class="bi-kpi"><div class="bi-kpi-num"><?= $prog['total'] ?></div><div class="bi-kpi-lbl">Total partidos</div><div class="bi-kpi-bar" style="width:100%;background:#1C2F48"></div></div>
      <div class="bi-kpi"><div class="bi-kpi-num" style="color:#16a34a"><?= $prog['jugados'] ?></div><div class="bi-kpi-lbl">Jugados</div><div class="bi-kpi-bar" style="width:100%;background:#16a34a"></div></div>
      <div class="bi-kpi"><div class="bi-kpi-num" style="color:#2563eb"><?= $prog['por_jugar'] ?></div><div class="bi-kpi-lbl">Por jugar</div><div class="bi-kpi-bar" style="width:100%;background:#2563eb"></div></div>
      <a href="#pendientes" class="bi-kpi" style="text-decoration:none;<?= $prog['vencidos']>0?'border-color:#fca5a5;background:#fff5f5':'' ?>">
        <div class="bi-kpi-num" style="color:#dc2626"><?= $prog['vencidos'] ?></div><div class="bi-kpi-lbl">⚠ Vencidos s/result.</div><div class="bi-kpi-bar" style="width:100%;background:#dc2626"></div>
      </a>
      <div class="bi-kpi"><div class="bi-kpi-num" style="color:#d97706"><?= $prog['sin_fecha'] ?></div><div class="bi-kpi-lbl">Sin fecha</div><div class="bi-kpi-bar" style="width:100%;background:#d97706"></div></div>
      <div class="bi-kpi"><div class="bi-kpi-num" style="color:#0891b2"><?= $prog['futuros'] ?></div><div class="bi-kpi-lbl">Futuros</div><div class="bi-kpi-bar" style="width:100%;background:#0891b2"></div></div>
    </div>

    <!-- ══ Layout 2 columnas ══ -->
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:1.5rem;align-items:start">
      <style>@media(max-width:900px){.bi-2col{grid-template-columns:1fr !important}}</style>
      <div class="bi-2col" style="display:contents">

      <!-- Pendientes de atención -->
      <div class="bi-section" id="pendientes" style="margin:0">
        <div class="bi-section-head">
          <span class="bi-section-title">⚠️ Pendientes de atención <span style="font-weight:600;color:#94a3b8;font-size:.8rem">(<?= count($pendientes) ?>)</span></span>
          <a href="partidos.php?estado_p=pendiente" class="bi-link">Ver todos →</a>
        </div>
        <div style="max-height:420px;overflow-y:auto">
          <?php if (empty($pendientes)): ?>
            <div style="padding:2rem;text-align:center;color:#94a3b8;font-size:.85rem">🎉 No hay partidos vencidos sin resultado.</div>
          <?php else: foreach ($pendientes as $p):
            $sinFecha = empty($p['fecha_programada']);
            $atraso = (int)$p['dias_atraso'];
          ?>
          <div class="bi-row">
            <div style="flex:1;min-width:0">
              <div style="font-weight:700;color:#1C2F48;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">
                <?= epl_h($p['local_nombre']) ?> <span style="color:#cbd5e1">vs</span> <?= epl_h($p['visitante_nombre']) ?>
              </div>
              <div style="font-size:.72rem;color:#94a3b8;margin-top:.15rem">
                <?= epl_h($p['liga_nombre']) ?><?= $p['jornada'] ? ' · J'.$p['jornada'] : '' ?>
              </div>
            </div>
            <?php if ($sinFecha): ?>
              <span class="bi-pill" style="background:#fffbeb;color:#92400e">Sin fecha</span>
            <?php else: ?>
              <span class="bi-pill" style="background:#fef2f2;color:#dc2626"><?= $atraso ?>d atraso</span>
            <?php endif; ?>
            <a href="partido_detalle.php?id=<?= $p['id'] ?>" class="bi-link" style="font-size:.75rem">Gestionar</a>
          </div>
          <?php endforeach; endif; ?>
        </div>
      </div>

      <!-- Últimos resultados -->
      <div class="bi-section" style="margin:0">
        <div class="bi-section-head">
          <span class="bi-section-title">🎾 Últimos resultados</span>
          <a href="dashboard_resultados.php" class="bi-link">Ver todos →</a>
        </div>
        <div style="max-height:420px;overflow-y:auto">
          <?php if (empty($resultados)): ?>
            <div style="padding:2rem;text-align:center;color:#94a3b8;font-size:.85rem">Sin resultados todavía.</div>
          <?php else: foreach ($resultados as $r):
            $loc_gana = $r['ganador_id'] == $r['local_id'];
            $vis_gana = $r['ganador_id'] == $r['visitante_id'];
            $fecha = $r['resultado_ingresado_at'] ? date('d/m H:i', strtotime($r['resultado_ingresado_at'])) : '';
          ?>
          <div class="bi-row">
            <div style="flex:1;min-width:0">
              <div style="font-weight:700;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">
                <span style="color:<?= $loc_gana?'#15803d':'#1C2F48' ?>"><?= epl_h($r['local_nombre']) ?></span>
                <span style="color:#cbd5e1">vs</span>
                <span style="color:<?= $vis_gana?'#15803d':'#1C2F48' ?>"><?= epl_h($r['visitante_nombre']) ?></span>
              </div>
              <div style="font-size:.72rem;color:#94a3b8;margin-top:.15rem"><?= epl_h($r['liga_nombre']) ?> · <?= $fecha ?></div>
            </div>
            <span class="bi-pill" style="background:#dbeafe;color:#1e40af">🎾 <?= (int)$r['sets_local'] ?>-<?= (int)$r['sets_visitante'] ?></span>
            <a href="partido_detalle.php?id=<?= $r['id'] ?>" class="bi-link" style="font-size:.75rem">Ver</a>
          </div>
          <?php endforeach; endif; ?>
        </div>
      </div>

      </div>
    </div>

    <!-- ══ Equipos con partidos pendientes ══ -->
    <div class="bi-section">
      <div class="bi-section-head">
        <span class="bi-section-title">📋 Equipos con partidos atrasados / reprogramados
          <span style="font-weight:600;color:#94a3b8;font-size:.8rem">(<?= count($equipos_pend) ?> equipos)</span>
        </span>
        <span style="font-size:.74rem;color:#94a3b8">Reprogramados + vencidos sin resultado · tocá para ver detalle</span>
      </div>
      <div style="max-height:560px;overflow-y:auto">
        <?php if (empty($equipos_pend)): ?>
          <div style="padding:2rem;text-align:center;color:#94a3b8;font-size:.85rem">🎉 Ningún equipo tiene partidos pendientes.</div>
        <?php else: foreach ($equipos_pend as $eid => $eq): ?>
        <details class="bi-equipo">
          <summary>
            <span style="display:flex;align-items:center;gap:.6rem;flex:1;min-width:0">
              <svg class="bi-chev" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
              <span style="font-weight:700;color:#1C2F48;white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?= epl_h($eq['nombre']) ?></span>
            </span>
            <span style="display:flex;align-items:center;gap:.4rem;flex-shrink:0">
              <?php if ($eq['vencidos'] > 0): ?>
                <span class="bi-pill" style="background:#fef2f2;color:#dc2626" title="Vencidos sin resultado">⚠ <?= $eq['vencidos'] ?></span>
              <?php endif; ?>
              <?php if ($eq['reprog'] > 0): ?>
                <span class="bi-pill" style="background:#fff7ed;color:#ea580c" title="Reprogramados">🔁 <?= $eq['reprog'] ?></span>
              <?php endif; ?>
              <span class="bi-pill" style="background:#1C2F48;color:#fff;min-width:24px;text-align:center"><?= $eq['total'] ?></span>
            </span>
          </summary>
          <div class="bi-equipo-body">
            <?php foreach ($eq['partidos'] as $pt):
              $sinFecha = empty($pt['fecha']);
              $fechaStr = $sinFecha ? 'Sin fecha' : date('d/m/Y H:i', strtotime($pt['fecha']));
            ?>
            <div class="bi-equipo-partido">
              <div style="flex:1;min-width:0">
                <span style="color:#94a3b8;font-size:.78rem">vs</span>
                <span style="font-weight:600;color:#1C2F48"><?= epl_h($pt['rival']) ?></span>
                <div style="font-size:.7rem;color:#94a3b8;margin-top:.1rem">
                  <?= epl_h($pt['liga']) ?><?= $pt['jornada'] ? ' · J'.$pt['jornada'] : '' ?>
                </div>
              </div>
              <?php if ($pt['estado'] === 'reprogramado'): ?>
                <span class="bi-pill" style="background:#fff7ed;color:#ea580c">Reprog.</span>
              <?php endif; ?>
              <span class="bi-pill" style="background:<?= $pt['vencido']?'#fef2f2':($sinFecha?'#fffbeb':'#eff6ff') ?>;color:<?= $pt['vencido']?'#dc2626':($sinFecha?'#92400e':'#1e40af') ?>">
                <?= $fechaStr ?>
              </span>
              <a href="partido_detalle.php?id=<?= $pt['id'] ?>" class="bi-link" style="font-size:.74rem">Gestionar</a>
            </div>
            <?php endforeach; ?>
          </div>
        </details>
        <?php endforeach; endif; ?>
      </div>
    </div>

    <!-- ══ Ritmo de juego ══ -->
    <div class="bi-section">
      <div class="bi-section-head">
        <span class="bi-section-title">📈 Ritmo de juego (partidos por semana)</span>
      </div>
      <div style="padding:1.25rem">
        <canvas id="chartRitmo" height="90"></canvas>
      </div>
    </div>

    <!-- ══ Explorador de jugadores ══ -->
    <div class="bi-section">
      <div class="bi-section-head">
        <span class="bi-section-title">👥 Explorador de jugadores <span style="font-weight:600;color:#94a3b8;font-size:.8rem">(<?= $total_jug ?> activos)</span></span>
        <div style="display:flex;gap:.5rem;align-items:center;flex-wrap:wrap">
          <select id="dimSel" onchange="renderDemo()" style="padding:.45rem .75rem;border:1.5px solid #e2e8f0;border-radius:8px;font-size:.82rem;font-weight:600;color:#1C2F48">
            <option value="nivel">Categoría / Nivel</option>
            <option value="comuna">Comuna</option>
            <option value="edad">Rango de edad</option>
            <option value="sexo">Sexo</option>
            <option value="pala">Marca de pala</option>
            <option value="frecuencia">Frecuencia de juego</option>
            <option value="lado">Lado de cancha</option>
            <option value="profesion">Profesión</option>
          </select>
          <div style="display:flex;gap:.25rem;background:#f1f5f9;border-radius:8px;padding:.2rem">
            <button type="button" id="btnBar" onclick="setTipo('bar')" style="border:none;background:#1C2F48;color:#fff;padding:.35rem .7rem;border-radius:6px;font-size:.75rem;font-weight:700;cursor:pointer">Barras</button>
            <button type="button" id="btnPie" onclick="setTipo('doughnut')" style="border:none;background:transparent;color:#64748b;padding:.35rem .7rem;border-radius:6px;font-size:.75rem;font-weight:700;cursor:pointer">Torta</button>
          </div>
        </div>
      </div>
      <div style="padding:1.25rem">
        <canvas id="chartDemo" height="110"></canvas>
      </div>
    </div>

  </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
var BI_RITMO = <?= json_encode(array_map(fn($r)=>['sem'=>date('d/m', strtotime($r['desde'])), 'n'=>(int)$r['n']], $ritmo), JSON_UNESCAPED_UNICODE) ?>;
var BI_DEMO  = <?= json_encode($demo, JSON_UNESCAPED_UNICODE) ?>;
var PALETA = ['#1C2F48','#C9A762','#2563eb','#16a34a','#dc2626','#d97706','#0891b2','#7c3aed','#db2777','#0d9488','#65a30d','#475569'];

// Ritmo
if (window.Chart && document.getElementById('chartRitmo')) {
  new Chart(document.getElementById('chartRitmo'), {
    type: 'bar',
    data: {
      labels: BI_RITMO.map(r => r.sem),
      datasets: [{ label: 'Partidos jugados', data: BI_RITMO.map(r => r.n), backgroundColor: '#C9A762', borderRadius: 6 }]
    },
    options: {
      plugins: { legend: { display:false } },
      scales: { y: { beginAtZero:true, ticks:{ precision:0 } } }
    }
  });
}

// Explorador demográfico
var demoChart = null;
var demoTipo = 'bar';
function setTipo(t) {
  demoTipo = t;
  document.getElementById('btnBar').style.background = t==='bar' ? '#1C2F48' : 'transparent';
  document.getElementById('btnBar').style.color      = t==='bar' ? '#fff' : '#64748b';
  document.getElementById('btnPie').style.background = t==='doughnut' ? '#1C2F48' : 'transparent';
  document.getElementById('btnPie').style.color      = t==='doughnut' ? '#fff' : '#64748b';
  renderDemo();
}
function renderDemo() {
  var dim = document.getElementById('dimSel').value;
  var data = BI_DEMO[dim] || {};
  var labels = Object.keys(data);
  var values = labels.map(k => data[k]);
  if (demoChart) demoChart.destroy();
  if (!window.Chart) return;
  demoChart = new Chart(document.getElementById('chartDemo'), {
    type: demoTipo,
    data: {
      labels: labels,
      datasets: [{
        label: 'Jugadores',
        data: values,
        backgroundColor: demoTipo === 'bar' ? '#1C2F48' : PALETA,
        borderRadius: demoTipo === 'bar' ? 6 : 0,
        borderWidth: demoTipo === 'doughnut' ? 2 : 0,
        borderColor: '#fff'
      }]
    },
    options: {
      indexAxis: demoTipo === 'bar' ? 'y' : 'x',
      plugins: { legend: { display: demoTipo === 'doughnut', position:'right' } },
      scales: demoTipo === 'bar' ? { x: { beginAtZero:true, ticks:{ precision:0 } } } : {}
    }
  });
}
renderDemo();
</script>

<?php require_once '../includes/footer.php'; ?>
