<?php
declare(strict_types=1);
$page_title = 'Mis Torneos';
$player_tab = 'mis_torneos';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

$j = epl_jugador_actual();
if (!$j) { header('Location: login.php'); exit; }

$flash_ok  = '';
$flash_err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['epl_borrar_insc'] ?? '')) {
    $res = epl_inscripcion_eliminar_jugador((int)($_POST['insc_id'] ?? 0), (int)$j['id']);
    if ($res['ok']) {
        $redir = 'mis_torneos.php?ok=borrado';
        if (!empty($_POST['liga_id'])) {
            $redir = 'mis_torneos.php?vista=todos&ok=borrado';
        }
        header('Location: ' . epl_url($redir));
        exit;
    }
    $flash_err = $res['error'] ?? 'No se pudo eliminar.';
}
if (isset($_GET['ok']) && $_GET['ok'] === 'borrado') {
    $flash_ok = 'Inscripción eliminada.';
}

$todos_torneos = epl_torneos_del_jugador($j['id']);
$vista_todos   = isset($_GET['vista']) && $_GET['vista'] === 'todos';
$sel_id        = $vista_todos ? 0 : (int)($_GET['id'] ?? 0);

if (!$vista_todos && !$sel_id && $todos_torneos) {
    $sel_id = (int)$todos_torneos[0]['id'];
}

$liga = null;
foreach ($todos_torneos as $t) {
    if ((int)$t['id'] === $sel_id) {
        $liga = $t;
        break;
    }
}

$finalizados = array_values(array_filter(
    $todos_torneos,
    static fn(array $t): bool => epl_get_liga_status($t) === 'finalizada'
));

$clasificacion = $liga ? epl_clasificacion($liga['id']) : [];
$partidos_all  = $liga ? epl_partidos_liga($liga['id']) : [];

$equipo      = null;
$mis_partidos = [];
$proximos    = [];
$jugados     = [];

if ($liga) {
    $equipo = epl_equipo_del_jugador($j['id'], $liga['id']);
    if (!$equipo && !empty($liga['equipo_id'])) {
        $stEq = epl_db()->prepare('SELECT * FROM equipos WHERE id = ? LIMIT 1');
        $stEq->execute([(int)$liga['equipo_id']]);
        $equipo = $stEq->fetch(PDO::FETCH_ASSOC) ?: null;
    }
    if ($equipo) {
        foreach ($partidos_all as $p) {
            if ($p['equipo_local_id'] == $equipo['id'] || $p['equipo_visitante_id'] == $equipo['id']) {
                $mis_partidos[] = $p;
                if ($p['estado'] === 'pendiente' || $p['estado'] === 'reprogramado') {
                    $proximos[] = $p;
                } elseif ($p['estado'] === 'jugado') {
                    $jugados[] = $p;
                }
            }
        }
    }
}

// Mi posición en la clasificación
$mi_pos = null;
if ($equipo && $clasificacion) {
    foreach ($clasificacion as $idx => $row) {
        if ($row['equipo_id'] == $equipo['id']) {
            $mi_pos = $idx + 1;
            break;
        }
    }
}

require_once 'includes/header.php';
?>

<div class="dash-layout">
  <?php include 'includes/player_sidebar.php'; ?>

  <main class="dash-main">
    <div class="dash-header" style="background:linear-gradient(135deg,#1c2f48 0%, #0f1e30 100%);border-radius:18px;padding:1.5rem 1.75rem;color:#fff;margin-bottom:1.5rem;position:relative;overflow:hidden;box-shadow:0 8px 28px rgba(28,47,72,.18)">
      <div style="position:absolute;top:-40px;right:-40px;width:180px;height:180px;background:radial-gradient(circle,rgba(201,167,98,.18) 0%,transparent 70%);pointer-events:none"></div>
      <div style="position:relative;z-index:1">
        <span style="font-size:.65rem;font-weight:900;letter-spacing:.25em;color:#C9A762;text-transform:uppercase">Tu actividad</span>
        <h1 class="dash-title" style="color:#fff;margin:.2rem 0 .15rem;font-size:clamp(1.5rem,3.5vw,2rem);font-family:'Anton',sans-serif;text-transform:uppercase">Mis <span style="color:#C9A762">Torneos</span></h1>
        <p style="color:rgba(255,255,255,.7);margin-top:.2rem;font-size:.82rem">Tu actividad completa en ligas y torneos del circuito EPL.</p>
      </div>
    </div>
    <?php if ($flash_ok): ?>
      <div class="alert alert-success" style="margin-top:.75rem"><?= epl_h($flash_ok) ?></div>
    <?php endif; ?>
    <?php if ($flash_err): ?>
      <div class="alert alert-error" style="margin-top:.75rem"><?= epl_h($flash_err) ?></div>
    <?php endif; ?>

    <!-- Pestañas: todos los torneos inscritos -->
    <?php if ($todos_torneos): ?>
    <div class="mt-tabs">
      <a href="?vista=todos" class="mt-tab <?= $vista_todos ? 'active' : '' ?>">
        <span class="mt-tab-badge">TODOS</span>
        <span class="mt-tab-name"><?= count($todos_torneos) ?> torneo<?= count($todos_torneos) !== 1 ? 's' : '' ?></span>
      </a>
      <?php foreach ($todos_torneos as $at):
        $badge = epl_torneo_estado_badge($at);
      ?>
        <a href="?id=<?= (int)$at['id'] ?>" class="mt-tab <?= !$vista_todos && $sel_id === (int)$at['id'] ? 'active' : '' ?>">
          <span class="mt-tab-badge mt-tab-badge--<?= epl_h($badge['tone']) ?>"><?= epl_h($badge['label']) ?></span>
          <span class="mt-tab-name"><?= epl_h($at['nombre']) ?></span>
        </a>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php if ($vista_todos && $todos_torneos): ?>
    <div class="mt-all-grid">
      <?php foreach ($todos_torneos as $at):
        $badge = epl_torneo_estado_badge($at);
        $rol = $at['rol_equipo'] ?? '';
        $insc_st = $at['inscripcion_estado'] ?? '';
        $pago = $at['inscripcion_pago_estado'] ?? '';
      ?>
      <div class="mt-all-card">
        <a href="?id=<?= (int)$at['id'] ?>" class="mt-all-card-link">
        <div class="mt-all-card-top">
          <span class="mt-tab-badge mt-tab-badge--<?= epl_h($badge['tone']) ?>"><?= epl_h($badge['label']) ?></span>
          <?php if ($rol): ?>
            <span class="mt-all-rol"><?= $rol === 'capitan' ? 'Capitán' : 'Partner' ?></span>
          <?php endif; ?>
        </div>
        <h3 class="mt-all-title"><?= epl_h($at['nombre']) ?></h3>
        <p class="mt-all-sub"><?= epl_h($at['temporada'] ?? '') ?> — <?= (int)($at['categoria'] ?? 0) ?>ª Cat. <?= ucfirst($at['sexo'] ?? '') ?></p>
        <?php if (!empty($at['equipo_nombre'])): ?>
          <p class="mt-all-meta">Equipo: <strong><?= epl_h($at['equipo_nombre']) ?></strong></p>
        <?php endif; ?>
        <?php if ($insc_st === 'pendiente'): ?>
          <p class="mt-all-meta mt-all-meta--warn">En proceso — falta validar cupo del equipo</p>
        <?php elseif ($pago && $pago !== 'pagado' && $pago !== 'exento'): ?>
          <p class="mt-all-meta mt-all-meta--warn">Pago: <?= epl_h(ucfirst($pago)) ?></p>
        <?php endif; ?>
        <span class="mt-all-link">Ver detalle →</span>
        </a>
        <?php if (epl_mostrar_boton_borrar_inscripcion_prueba() && !empty($at['inscripcion_id']) && $insc_st === 'pendiente' && $rol === 'capitan'): ?>
        <form method="post" class="mt-all-borrar" onsubmit="return confirm('¿Eliminar esta inscripción de prueba?');">
          <input type="hidden" name="epl_borrar_insc" value="1">
          <input type="hidden" name="insc_id" value="<?= (int)$at['inscripcion_id'] ?>">
          <input type="hidden" name="liga_id" value="<?= (int)$at['id'] ?>">
          <button type="submit">Eliminar inscripción (prueba)</button>
        </form>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>
    </div>
    <p style="margin-top:1.25rem;text-align:center">
      <a href="<?= epl_url('inscribirse.php') ?>" class="btn btn-sm" style="border:1px solid var(--navy);color:var(--navy)">+ Nueva inscripción</a>
    </p>

    <?php elseif ($liga): ?>
      <?php
        $status_liga = epl_get_liga_status($liga);
        $is_active = ($status_liga !== 'finalizada');
        $badge_liga = epl_torneo_estado_badge($liga);
        $tiene_competencia = !empty($clasificacion) || !empty($partidos_all);
        $es_solo_inscripcion = ($status_liga === 'inscripcion' && !$tiene_competencia);
        $msg_insc = $es_solo_inscripcion ? epl_mensaje_torneo_inscripcion($liga, $j['id']) : null;

        // Agrupar todos los partidos por jornada para el tab de Fixture
        $partidos_por_jornada = [];
        foreach ($partidos_all as $p) {
            $j_num = $p['jornada'] ?? 0;
            $partidos_por_jornada[$j_num][] = $p;
        }
        ksort($partidos_por_jornada);
        $jornadas_disponibles = array_keys($partidos_por_jornada);
        sort($jornadas_disponibles);
      ?>

      <!-- Cabecera del torneo -->
      <div class="mt-hero">
        <div>
          <span class="mt-tab-badge mt-tab-badge--<?= epl_h($badge_liga['tone']) ?>" style="margin-bottom:.5rem;display:inline-block"><?= epl_h($badge_liga['label']) ?></span>
          <h2 class="mt-hero-title"><?= epl_h($liga['nombre']) ?></h2>
          <p class="mt-hero-sub"><?php if (!empty($liga['temporada'])): ?><?= epl_h($liga['temporada']) ?> — <?php endif; ?><?= (int)($liga['categoria'] ?? 0) ?>ª Cat. <?= ucfirst($liga['sexo'] ?? '') ?></p>
          <?php if (!empty($liga['equipo_nombre'])): ?>
            <p style="color:rgba(255,255,255,.65);font-size:.82rem;margin:.35rem 0 0">Equipo: <strong><?= epl_h($liga['equipo_nombre']) ?></strong></p>
          <?php endif; ?>
        </div>
        <div style="display:flex;align-items:center;gap:1rem;flex-wrap:wrap">
          <?php if ($mi_pos): ?>
            <div style="text-align:center;background:rgba(201,167,98,.15);border:1px solid rgba(201,167,98,.4);border-radius:12px;padding:.6rem 1.2rem">
              <div style="font-family:var(--font-head);font-size:1.8rem;color:var(--gold);line-height:1"><?= $mi_pos ?>º</div>
              <div style="font-size:.62rem;color:rgba(255,255,255,.7);text-transform:uppercase;letter-spacing:.08em">Posición</div>
            </div>
          <?php endif; ?>
          <?php if (!$is_active): ?>
            <span class="badge badge-walkover" style="font-size:.72rem;padding:.4rem .8rem">FINALIZADO</span>
          <?php endif; ?>
          <a href="torneo.php?id=<?= $liga['id'] ?>" class="btn btn-sm" style="background:rgba(255,255,255,.1);color:#fff;border:1px solid rgba(255,255,255,.25)">Ver torneo público →</a>
          <?php if (epl_mostrar_boton_borrar_inscripcion_prueba() && !empty($liga['inscripcion_id']) && ($liga['inscripcion_estado'] ?? '') === 'pendiente' && ($liga['rol_equipo'] ?? '') === 'capitan'): ?>
          <form method="post" onsubmit="return confirm('¿Eliminar esta inscripción de prueba?');">
            <input type="hidden" name="epl_borrar_insc" value="1">
            <input type="hidden" name="insc_id" value="<?= (int)$liga['inscripcion_id'] ?>">
            <button type="submit" class="btn btn-sm" style="background:rgba(220,38,38,.25);color:#fecaca;border:1px solid rgba(248,113,113,.5)">Eliminar inscripción (prueba)</button>
          </form>
          <?php endif; ?>
        </div>
      </div>

      <?php if ($es_solo_inscripcion && $msg_insc): ?>
      <div class="card" style="margin-bottom:1.5rem">
        <div class="card-body" style="padding:1.5rem">
          <h3 style="font-family:var(--font-head);font-size:.95rem;text-transform:uppercase;color:var(--navy);margin:0 0 .75rem">
            <?= epl_h($msg_insc['titulo']) ?>
          </h3>
          <p style="margin:0 0 1rem;color:var(--gray-600);line-height:1.55"><?= epl_h($msg_insc['texto']) ?></p>
          <p style="margin:0 0 1rem;font-size:.8rem;color:var(--gray-400)">
            El cupo del equipo se confirma cuando <strong>capitán y partner</strong> validan su inscripción.
            Seguí el avance en <a href="<?= epl_url('inscribirse.php') ?>" style="color:var(--gold);font-weight:600">Inscripciones</a>.
          </p>
          <a href="<?= epl_url('inscribirse.php') ?>" class="btn btn-gold btn-sm">Ir a inscripciones</a>
          <a href="torneo.php?id=<?= (int)$liga['id'] ?>" class="btn btn-sm" style="margin-left:.5rem;border:1px solid var(--gray-200)">Ver torneo público</a>
        </div>
      </div>

      <?php else: ?>

      <!-- Sub-pestañas integradas del Torneo -->
      <div class="mt-subtabs">
        <button type="button" class="mt-subtab active" data-subtab="posiciones" onclick="mtShowTab('posiciones', this)">
          <span>📊</span> <span class="mt-subtab-text">Tabla de Posiciones</span>
        </button>
        <button type="button" class="mt-subtab" data-subtab="mis-partidos" onclick="mtShowTab('mis-partidos', this)">
          <span>🎾</span> <span class="mt-subtab-text">Mis Partidos</span>
          <?php if (!empty($proximos)): ?>
            <span class="mt-subtab-count"><?= count($proximos) ?></span>
          <?php endif; ?>
        </button>
        <button type="button" class="mt-subtab" data-subtab="fixture" onclick="mtShowTab('fixture', this)">
          <span>📅</span> <span class="mt-subtab-text">Fixture del Torneo</span>
          <?php if (!empty($partidos_all)): ?>
            <span class="mt-subtab-count" style="background:#e2e8f0;color:var(--navy)"><?= count($partidos_all) ?></span>
          <?php endif; ?>
        </button>
      </div>

      <!-- Panel 1: Tabla de Posiciones Completa -->
      <div id="mt-tab-posiciones" class="mt-subtab-panel active">
        <div style="background:var(--white);border:1px solid var(--gray-100);border-radius:16px;padding:1.5rem;box-shadow:0 2px 10px rgba(0,0,0,0.02);margin-bottom:1.5rem">
          <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:.75rem;margin-bottom:1.25rem">
            <div>
              <h3 style="font-family:var(--font-head);font-size:1.15rem;text-transform:uppercase;color:var(--navy);margin:0;display:flex;align-items:center;gap:.5rem">
                <span style="display:inline-block;width:4px;height:1.1em;background:var(--gold);border-radius:4px"></span>
                Clasificación General
              </h3>
              <p style="font-size:.78rem;color:var(--gray-500);margin:.25rem 0 0">
                Puntos, estadísticas y rendimiento de todas las parejas en el torneo.
              </p>
            </div>
            <?php if ($mi_pos): ?>
              <div style="display:inline-flex;align-items:center;gap:.5rem;background:rgba(201,167,98,.12);border:1px solid rgba(201,167,98,.35);border-radius:10px;padding:.45rem .85rem">
                <span style="font-size:1.1rem">🏆</span>
                <span style="font-size:.8rem;font-weight:700;color:var(--navy)">Tu lugar: <strong><?= $mi_pos ?>º de <?= count($clasificacion) ?></strong></span>
              </div>
            <?php endif; ?>
          </div>

          <?php if ($clasificacion): ?>
          <div class="tabla-clasificacion">
            <table>
              <thead>
                <tr>
                  <th style="text-align:left;padding-left:1.25rem">#</th>
                  <th style="text-align:left">Equipo</th>
                  <th>PJ</th><th>PG</th><th>PP</th>
                  <th class="hide-mobile">GF</th>
                  <th class="hide-mobile">GC</th>
                  <th class="hide-mobile">Dif.</th>
                  <th>Pts</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($clasificacion as $i => $row):
                  $is_me = ($equipo && (int)$row['equipo_id'] === (int)$equipo['id']);
                  $dif = (int)$row['games_favor'] - (int)$row['games_contra'];
                ?>
                <tr class="<?= $is_me ? 'mt-rank-me' : '' ?>" style="<?= $is_me ? 'background:rgba(201,167,98,.14);' : '' ?>">
                  <td style="padding-left:1.25rem">
                    <span class="posicion-num <?= ['pos-1','pos-2','pos-3'][$i] ?? 'pos-n' ?>"><?= $i+1 ?></span>
                  </td>
                  <td>
                    <div class="equipo-cell">
                      <div class="equipo-avatars">
                        <img class="equipo-avatar" src="<?= epl_h(epl_foto_jugador($row['j1_foto'], $row['j1_nombre'].' '.$row['j1_apellido'])) ?>" alt="" width="32" height="32">
                        <img class="equipo-avatar" src="<?= epl_h(epl_foto_jugador($row['j2_foto'], $row['j2_nombre'].' '.$row['j2_apellido'])) ?>" alt="" width="32" height="32">
                      </div>
                      <span class="equipo-nombre" style="display:flex;align-items:center;gap:.4rem;flex-wrap:wrap">
                        <?= epl_h($row['equipo_nombre']) ?>
                        <?php if ($is_me): ?>
                          <span style="font-size:.62rem;background:var(--gold);color:var(--navy);padding:.12rem .45rem;border-radius:4px;font-weight:800;letter-spacing:.04em">MI EQUIPO</span>
                        <?php endif; ?>
                      </span>
                    </div>
                  </td>
                  <td><?= (int)$row['pj'] ?></td>
                  <td><strong style="color:#16a34a"><?= (int)$row['pg'] ?></strong></td>
                  <td><?= (int)$row['pp'] ?></td>
                  <td class="hide-mobile"><?= (int)$row['games_favor'] ?></td>
                  <td class="hide-mobile"><?= (int)$row['games_contra'] ?></td>
                  <td class="hide-mobile" style="font-weight:700;color:<?= $dif >= 0 ? '#16a34a' : '#dc2626' ?>">
                    <?= ($dif >= 0 ? '+' : '') . $dif ?>
                  </td>
                  <td><strong style="color:var(--navy);font-size:1.05rem"><?= (int)$row['puntos'] ?></strong></td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:.5rem;margin-top:.85rem">
            <p style="font-size:.72rem;color:var(--gray-400);margin:0">
              Desempates: Puntos → Diferencia de games → Partidos ganados → Games a favor.
            </p>
            <a href="torneo.php?id=<?= (int)$liga['id'] ?>" style="font-size:.75rem;color:var(--gold);font-weight:700;text-decoration:none">
              Ver vista pública completa →
            </a>
          </div>
          <?php else: ?>
            <div class="mt-empty">
              <div style="font-size:2.5rem;margin-bottom:.5rem">📊</div>
              <h3 style="font-family:var(--font-head);color:var(--navy);font-size:1.1rem;margin-bottom:.25rem">Sin clasificación disponible</h3>
              <p style="color:var(--gray-500);margin:0">La tabla de posiciones se actualizará en cuanto se jueguen y carguen los resultados.</p>
            </div>
          <?php endif; ?>
        </div>
      </div>

      <!-- Panel 2: Mis Partidos -->
      <div id="mt-tab-mis-partidos" class="mt-subtab-panel" style="display:none">
        <div class="mt-main-grid">
          <!-- Próximos partidos -->
          <div>
            <div class="mt-section-head">
              <h3 class="mt-section-title">
                <svg style="width:16px;height:16px" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                Próximos partidos
              </h3>
              <a href="ingresar_resultado.php" class="btn btn-primary btn-sm">+ Resultado</a>
            </div>
            <?php if (empty($proximos)): ?>
              <div class="mt-empty">No tienes partidos pendientes en este torneo.</div>
            <?php else: ?>
              <div class="mt-partidos-list">
                <?php foreach ($proximos as $p):
                  $es_local = ($equipo && $p['equipo_local_id'] == $equipo['id']);
                  $rival = $es_local ? $p['visitante_nombre'] : $p['local_nombre'];
                  $estado_txt = $p['estado'] === 'reprogramado' ? 'Reprogramado' : 'Pendiente';
                  $estado_color = $p['estado'] === 'reprogramado' ? '#f59e0b' : 'var(--gray-400)';
                  
                  $r_n_wa = $p['recinto_nombre'] ?? '';
                  $r_s_wa = $p['recinto_superior_nombre'] ?? '';
                  $r_a_wa = $p['recinto_abuelo_nombre'] ?? '';
                  $c_wa   = $p['cancha'] ?? '';
                  $sede_wa = '';
                  if ($r_a_wa)      $sede_wa = $r_a_wa . ($r_s_wa ? ' · ' . $r_s_wa : '') . ($r_n_wa ? ' · ' . $r_n_wa : '');
                  elseif ($r_s_wa)  $sede_wa = $r_s_wa . ($r_n_wa ? ' · ' . $r_n_wa : '');
                  elseif ($r_n_wa)  $sede_wa = $r_n_wa;
                  if ($c_wa && !str_contains(strtolower($sede_wa), 'cancha')) $sede_wa .= ($sede_wa ? ' · C.' : 'C.') . $c_wa;
                  $fecha_wa  = $p['fecha_programada'] ? date('d/m/Y H:i', strtotime($p['fecha_programada'])) : 'fecha por confirmar';
                  $wa_prox   = "🎾 ¡Partido de pádel!\n\nvs {$rival}\n📅 {$fecha_wa}" . ($sede_wa ? "\n🏟️ {$sede_wa}" : '') . "\n\n#ElitePadelLeague";
                  $wa_prox_url = 'https://wa.me/?text=' . rawurlencode($wa_prox);
                ?>
                <div class="mt-partido-row">
                  <div class="mt-partido-fecha">
                    <?php if ($p['fecha_programada']): ?>
                      <span class="mt-dia"><?= date('d', strtotime($p['fecha_programada'])) ?></span>
                      <span class="mt-mes"><?= strtoupper(date('M', strtotime($p['fecha_programada']))) ?></span>
                    <?php else: ?>
                      <span class="mt-dia">—</span>
                      <span class="mt-mes">TBD</span>
                    <?php endif; ?>
                  </div>
                  <div class="mt-partido-info">
                    <div style="font-weight:700;font-size:.88rem;color:var(--navy)">vs <?= epl_h($rival) ?></div>
                    <div style="font-size:.72rem;color:var(--gray-400);margin-top:.1rem">
                      Fecha <?= $p['jornada'] ?? '—' ?>
                      <?php
                        $r_n = $p['recinto_nombre'] ?? '';
                        $r_s = $p['recinto_superior_nombre'] ?? '';
                        $r_a = $p['recinto_abuelo_nombre'] ?? '';
                        $c   = $p['cancha'] ?? '';
                        $sede_txt = '';
                        if ($r_a)      $sede_txt = $r_a . ($r_s ? ' · ' . $r_s : '') . ($r_n ? ' · ' . $r_n : '');
                        elseif ($r_s)  $sede_txt = $r_s . ($r_n ? ' · ' . $r_n : '');
                        elseif ($r_n)  $sede_txt = $r_n;
                        if ($c && !str_contains(strtolower($sede_txt), 'cancha')) $sede_txt .= ($sede_txt ? ' · C.' : 'C.') . $c;
                      ?>
                      <?php if ($sede_txt): ?>· <?= epl_h($sede_txt) ?><?php endif; ?>
                    </div>
                  </div>
                  <div style="display:flex;flex-direction:column;align-items:flex-end;gap:.35rem;flex-shrink:0">
                    <span style="font-size:.65rem;font-weight:700;color:<?= $estado_color ?>;text-transform:uppercase;white-space:nowrap"><?= $estado_txt ?></span>
                    <div style="display:flex;align-items:center;gap:.35rem">
                      <a href="<?= epl_h($wa_prox_url) ?>" target="_blank" rel="noopener" class="wa-share-btn" title="Compartir por WhatsApp">
                        <svg viewBox="0 0 24 24" fill="currentColor" width="14" height="14"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
                        Compartir
                      </a>
                      <a href="<?= epl_url('ingresar_resultado.php?partido_id=' . $p['id']) ?>" class="mt-marcador-btn" title="Ingresar resultado de este partido">
                        + Marcador
                      </a>
                    </div>
                  </div>
                </div>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </div>

          <!-- Últimos resultados -->
          <div>
            <div class="mt-section-head">
              <h3 class="mt-section-title">
                <svg style="width:16px;height:16px" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                Últimos resultados
              </h3>
            </div>
            <?php if (empty($jugados)): ?>
              <div class="mt-empty">Aún no hay resultados registrados de tu equipo.</div>
            <?php else: ?>
              <div class="mt-partidos-list">
                <?php foreach (array_reverse($jugados) as $p):
                  $gane = ($equipo && $p['ganador_id'] == $equipo['id']);
                  $es_local = ($equipo && $p['equipo_local_id'] == $equipo['id']);
                  $rival = $es_local ? $p['visitante_nombre'] : $p['local_nombre'];
                  $sets_yo = $es_local ? $p['sets_local'] : $p['sets_visitante'];
                  $sets_rival = $es_local ? $p['sets_visitante'] : $p['sets_local'];
                  
                  $emoji_wa  = $gane ? '🏆' : '🎾';
                  $fecha_j_wa = $p['fecha_jugado'] ? date('d/m/Y', strtotime($p['fecha_jugado'])) : '';
                  $wa_res    = $emoji_wa . ' ' . ($gane ? '¡Victoria en pádel!' : 'Partido jugado') . "\n\nvs {$rival}\nSets: {$sets_yo}-{$sets_rival}" . ($fecha_j_wa ? "\n📅 {$fecha_j_wa}" : '') . "\n\n#ElitePadelLeague";
                  $wa_res_url = 'https://wa.me/?text=' . rawurlencode($wa_res);
                ?>
                <div class="mt-partido-row">
                  <div class="mt-partido-fecha" style="background:<?= $gane ? 'rgba(34,197,94,.1)' : 'rgba(239,68,68,.1)' ?>">
                    <span class="mt-dia" style="color:<?= $gane ? '#22c55e' : '#ef4444' ?>"><?= $sets_yo ?>-<?= $sets_rival ?></span>
                    <span class="mt-mes" style="color:<?= $gane ? '#22c55e' : '#ef4444' ?>"><?= $gane ? 'WIN' : 'LOSS' ?></span>
                  </div>
                  <div class="mt-partido-info">
                    <div style="font-weight:700;font-size:.88rem;color:var(--navy)">vs <?= epl_h($rival) ?></div>
                    <div style="font-size:.72rem;color:var(--gray-400);margin-top:.1rem">
                      <?= $p['fecha_jugado'] ? date('d/m/Y', strtotime($p['fecha_jugado'])) : '' ?>
                      · Fecha <?= $p['jornada'] ?? '—' ?>
                    </div>
                  </div>
                  <a href="<?= epl_h($wa_res_url) ?>" target="_blank" rel="noopener" class="wa-share-btn wa-share-btn--<?= $gane ? 'win' : 'loss' ?>" title="Compartir resultado por WhatsApp">
                    <svg viewBox="0 0 24 24" fill="currentColor" width="14" height="14"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
                    <?= $gane ? '¡Compartir!' : 'Compartir' ?>
                  </a>
                </div>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <!-- Panel 3: Fixture del Torneo -->
      <div id="mt-tab-fixture" class="mt-subtab-panel" style="display:none">
        <div style="background:var(--white);border:1px solid var(--gray-100);border-radius:16px;padding:1.5rem;box-shadow:0 2px 10px rgba(0,0,0,0.02)">
          <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:.75rem;margin-bottom:1.25rem">
            <div>
              <h3 style="font-family:var(--font-head);font-size:1.15rem;text-transform:uppercase;color:var(--navy);margin:0;display:flex;align-items:center;gap:.5rem">
                <span style="display:inline-block;width:4px;height:1.1em;background:var(--gold);border-radius:4px"></span>
                Calendario de Partidos
              </h3>
              <p style="font-size:.78rem;color:var(--gray-500);margin:.25rem 0 0">
                Revisa los resultados y próximos enfrentamientos de todas las fechas.
              </p>
            </div>
            <!-- Buscador de partidos -->
            <div style="position:relative;min-width:240px;flex:1;max-width:340px">
              <input type="text" id="mtSearchPartidos" placeholder="Buscar por equipo..."
                     style="width:100%;padding:.55rem .85rem .55rem 2.2rem;border:1px solid #e2e8f0;border-radius:10px;font-size:.82rem;color:var(--navy);outline:none"
                     onkeyup="mtFilterPartidos()">
              <svg style="position:absolute;left:.75rem;top:50%;transform:translateY(-50%);width:.95rem;height:.95rem;color:#94a3b8" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
            </div>
          </div>

          <?php if (!empty($jornadas_disponibles)): ?>
          <div class="fechas-scroll" style="margin-bottom:1.5rem">
            <button type="button" class="fecha-btn active" onclick="mtFilterByJornada('all', this)">Todas las fechas</button>
            <?php foreach ($jornadas_disponibles as $j_btn): ?>
              <button type="button" class="fecha-btn" onclick="mtFilterByJornada('<?= $j_btn ?>', this)">
                Fecha <?= $j_btn ?: 'Especial' ?>
              </button>
            <?php endforeach; ?>
          </div>
          <?php endif; ?>

          <div id="mt-fixture-container">
            <?php if (empty($partidos_all)): ?>
              <div class="mt-empty">No hay partidos programados para este torneo.</div>
            <?php else: ?>
              <?php foreach ($partidos_por_jornada as $j_num => $partidos_j): ?>
                <div class="mt-jornada-group" data-jornada="<?= $j_num ?>" style="margin-bottom:2rem">
                  <div style="background:#f8fafc;color:var(--navy);font-weight:800;font-size:.8rem;text-transform:uppercase;letter-spacing:.08em;padding:.45rem .9rem;border-radius:8px;margin-bottom:1rem;display:inline-block;border:1px solid #e2e8f0">
                    Fecha <?= $j_num ?: 'Especial' ?>
                  </div>
                  <div class="partidos-list" style="display:flex;flex-direction:column;gap:.75rem">
                    <?php foreach ($partidos_j as $p): ?>
                      <?php include 'includes/partido_card_v2.php'; ?>
                    <?php endforeach; ?>
                  </div>
                </div>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <?php endif; ?>

    <?php elseif (empty($todos_torneos)): ?>
      <div class="card p-5 text-center">
        <p style="color:var(--gray-400)">Aún no tienes inscripciones ni torneos asignados.</p>
        <a href="<?= epl_url('inscribirse.php') ?>" class="btn btn-gold mt-3">Inscribirme a un torneo</a>
      </div>
    <?php endif; ?>

    <!-- Torneos finalizados (miniaturas) — solo en vista detalle -->
    <?php if (!$vista_todos && $finalizados && $liga && epl_get_liga_status($liga) !== 'finalizada'): ?>
    <div style="margin-top:2.5rem">
      <h3 style="font-family:var(--font-head);color:var(--navy);text-transform:uppercase;font-size:.85rem;letter-spacing:.08em;margin-bottom:1rem;display:flex;align-items:center;gap:.5rem">
        <svg style="width:16px;height:16px;color:var(--gray-400)" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        Historial
      </h3>
      <div class="mt-history-grid">
        <?php foreach ($finalizados as $ft): ?>
          <a href="torneo.php?id=<?= $ft['id'] ?>" class="mt-history-card">
            <div style="font-size:.6rem;font-weight:800;color:var(--gray-400);text-transform:uppercase;letter-spacing:.1em"><?= epl_h($ft['temporada']) ?></div>
            <div style="font-family:var(--font-head);font-size:.9rem;color:var(--navy);text-transform:uppercase;margin:.2rem 0;line-height:1.2"><?= epl_h($ft['nombre']) ?></div>
            <div style="font-size:.7rem;color:var(--gray-600)"><?= $ft['categoria'] ?>ª Cat. · Ver resultados →</div>
          </a>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

  </main>
</div>

<style>
/* ── Tabs ── */
.mt-tabs { display:flex; gap:.75rem; overflow-x:auto; padding-bottom:1rem; margin-bottom:1.5rem; scrollbar-width:none; }
.mt-tabs::-webkit-scrollbar { display:none; }
.mt-tab { background:var(--white); border:2px solid var(--gray-200); border-radius:12px; padding:.85rem 1.25rem; min-width:160px; display:flex; flex-direction:column; gap:.2rem; transition:all .2s; text-decoration:none; }
.mt-tab:hover { border-color:var(--gold); }
.mt-tab.active { background:var(--navy); border-color:var(--navy); }
.mt-tab-badge { font-size:.58rem; font-weight:800; color:var(--gold); letter-spacing:.1em; text-transform:uppercase; }
.mt-tab-badge--live { color:#4ade80; }
.mt-tab-badge--signup { color:#93c5fd; }
.mt-tab-badge--pending { color:#fbbf24; }
.mt-tab-badge--done { color:#9ca3af; }
.mt-tab-badge--soon { color:#c4b5fd; }
.mt-tab.active .mt-tab-badge { color:var(--gold); }
.mt-tab-name { font-family:var(--font-head); font-size:.95rem; color:var(--navy); text-transform:uppercase; line-height:1.2; }
.mt-tab.active .mt-tab-name { color:var(--white); }

/* ── Vista Todos ── */
.mt-all-grid { display:grid; grid-template-columns:repeat(auto-fill, minmax(260px, 1fr)); gap:1rem; margin-bottom:1rem; }
.mt-all-card { background:var(--white); border:2px solid var(--gray-200); border-radius:14px; padding:1.15rem 1.25rem; transition:all .2s; display:flex; flex-direction:column; gap:.35rem; }
.mt-all-card:hover { border-color:var(--gold); transform:translateY(-2px); box-shadow:0 6px 20px rgba(28,47,72,.08); }
.mt-all-card-link { text-decoration:none; color:inherit; display:flex; flex-direction:column; gap:.35rem; flex:1; }
.mt-all-borrar { margin-top:.5rem; padding-top:.65rem; border-top:1px dashed var(--gray-200); }
.mt-all-borrar button { font-size:.72rem; font-weight:700; color:#dc2626; background:none; border:none; cursor:pointer; text-decoration:underline; padding:0; }
.mt-all-card-top { display:flex; justify-content:space-between; align-items:center; gap:.5rem; flex-wrap:wrap; }
.mt-all-rol { font-size:.62rem; font-weight:800; color:var(--navy); background:var(--gray-100); padding:.15rem .5rem; border-radius:999px; text-transform:uppercase; }
.mt-all-title { font-family:var(--font-head); font-size:1rem; color:var(--navy); text-transform:uppercase; margin:0; line-height:1.2; }
.mt-all-sub { font-size:.72rem; color:var(--gray-400); margin:0; text-transform:uppercase; font-weight:600; }
.mt-all-meta { font-size:.78rem; color:var(--gray-600); margin:0; }
.mt-all-meta--warn { color:#b45309; font-weight:600; }
.mt-all-link { font-size:.75rem; font-weight:700; color:var(--gold); margin-top:.5rem; }

/* ── Hero ── */
.mt-hero { background:var(--navy); border-radius:16px; padding:1.5rem 2rem; display:flex; justify-content:space-between; align-items:center; gap:1rem; flex-wrap:wrap; margin-bottom:1.5rem; }
.mt-hero-title { font-family:var(--font-head); font-size:1.5rem; text-transform:uppercase; color:var(--white); margin:0; line-height:1.1; }
.mt-hero-sub { color:var(--gold); font-size:.78rem; font-weight:700; text-transform:uppercase; margin:.3rem 0 0; letter-spacing:.05em; }

/* ── Grid principal ── */
.mt-main-grid { display:grid; grid-template-columns:1fr 1fr; gap:1.5rem; align-items:start; }
@media(max-width:768px){ .mt-main-grid { grid-template-columns:1fr; } }

/* ── Section heads ── */
.mt-section-head { display:flex; justify-content:space-between; align-items:center; margin-bottom:.85rem; }
.mt-section-title { font-family:var(--font-head); font-size:.82rem; text-transform:uppercase; color:var(--navy); letter-spacing:.06em; margin:0; display:flex; align-items:center; gap:.4rem; }

/* ── Partido row ── */
.mt-partidos-list { background:var(--white); border-radius:12px; border:1px solid var(--gray-100); overflow:hidden; }
.mt-partido-row { display:flex; align-items:center; gap:1rem; padding:.85rem 1rem; border-bottom:1px solid var(--gray-100); }
.mt-partido-row:last-child { border-bottom:none; }
.mt-partido-fecha { background:var(--gray-100); border-radius:8px; padding:.4rem .6rem; text-align:center; min-width:44px; flex-shrink:0; }
.mt-dia { display:block; font-family:var(--font-head); font-size:1.1rem; color:var(--navy); line-height:1; }
.mt-mes { display:block; font-size:.55rem; font-weight:800; color:var(--gray-400); letter-spacing:.08em; text-transform:uppercase; }
.mt-partido-info { flex:1; min-width:0; }

/* ── Ranking ── */
.mt-ranking-card { background:var(--white); border-radius:12px; border:1px solid var(--gray-100); overflow:hidden; }
.mt-rank-row { display:flex; align-items:center; gap:.85rem; padding:.8rem 1rem; border-bottom:1px solid var(--gray-100); transition:background .15s; }
.mt-rank-row:last-child { border-bottom:none; }
.mt-rank-row:hover { background:var(--gray-100); }
.mt-rank-me { background:rgba(201,167,98,.08) !important; }
.mt-rank-pos { font-family:var(--font-head); font-size:1.1rem; min-width:24px; text-align:center; font-weight:700; }

/* ── Empty ── */
.mt-empty { background:var(--white); border-radius:12px; border:1px solid var(--gray-100); padding:2rem; text-align:center; color:var(--gray-400); font-size:.85rem; font-style:italic; }

/* ── History ── */
.mt-history-grid { display:grid; grid-template-columns:repeat(auto-fill, minmax(160px, 1fr)); gap:.75rem; }
.mt-history-card { background:var(--white); border:1px solid var(--gray-200); border-radius:10px; padding:1rem; text-decoration:none; transition:all .2s; }
.mt-history-card:hover { border-color:var(--gold); transform:translateY(-2px); box-shadow:0 4px 12px rgba(201,167,98,.15); }

@media(max-width:480px){
  .mt-hero { padding:1.25rem; }
  .mt-hero-title { font-size:1.2rem; }
  .mt-tab { min-width:140px; padding:.7rem 1rem; }
}

/* ── WhatsApp share button ── */
.wa-share-btn {
  display:inline-flex; align-items:center; gap:.3rem;
  font-size:.65rem; font-weight:700; letter-spacing:.03em;
  color:#25D366; background:rgba(37,211,102,.08);
  border:1px solid rgba(37,211,102,.3); border-radius:20px;
  padding:.25rem .65rem; text-decoration:none; white-space:nowrap;
  flex-shrink:0; transition:all .18s;
}
.wa-share-btn:hover { background:rgba(37,211,102,.18); border-color:#25D366; color:#128C7E; }
.mt-marcador-btn {
  display:inline-flex; align-items:center;
  font-size:.65rem; font-weight:700; letter-spacing:.03em;
  color:var(--navy); background:rgba(28,47,72,.08);
  border:1px solid rgba(28,47,72,.25); border-radius:20px;
  padding:.25rem .65rem; text-decoration:none; white-space:nowrap;
  flex-shrink:0; transition:all .18s;
}
.mt-marcador-btn:hover { background:var(--navy); color:var(--gold); border-color:var(--navy); }
.wa-share-btn--win { color:#16a34a; background:rgba(34,197,94,.1); border-color:rgba(34,197,94,.35); }
.wa-share-btn--win:hover { background:rgba(34,197,94,.2); border-color:#16a34a; }
.wa-share-btn--loss { color:#6b7280; background:rgba(107,114,128,.08); border-color:rgba(107,114,128,.25); }
.wa-share-btn--loss:hover { background:rgba(37,211,102,.12); color:#25D366; border-color:#25D366; }

/* ── Subtabs dentro del torneo ── */
.mt-subtabs {
  display:flex;
  background:var(--white);
  border:1.5px solid var(--gray-200);
  border-radius:14px;
  padding:.35rem;
  gap:.35rem;
  margin-bottom:1.5rem;
  box-shadow:0 2px 8px rgba(28,47,72,.04);
  overflow-x:auto;
  scrollbar-width:none;
}
.mt-subtabs::-webkit-scrollbar { display:none; }
.mt-subtab {
  flex:1;
  display:flex;
  align-items:center;
  justify-content:center;
  gap:.5rem;
  padding:.75rem 1.25rem;
  border-radius:10px;
  font-family:var(--font-body);
  font-size:.82rem;
  font-weight:800;
  text-transform:uppercase;
  letter-spacing:.04em;
  color:var(--navy);
  background:transparent;
  border:none;
  cursor:pointer;
  transition:all .2s;
  white-space:nowrap;
}
.mt-subtab:hover {
  background:var(--gray-100);
}
.mt-subtab.active {
  background:var(--navy);
  color:var(--gold);
  box-shadow:0 4px 12px rgba(28,47,72,.18);
}
.mt-subtab-count {
  background:var(--gold);
  color:var(--navy);
  font-size:.65rem;
  font-weight:900;
  padding:.12rem .45rem;
  border-radius:999px;
}
.mt-subtab.active .mt-subtab-count {
  background:#fff;
  color:var(--navy);
}
.mt-subtab-panel {
  display:none;
}
.mt-subtab-panel.active {
  display:block;
}
@media(max-width:640px){
  .mt-subtab { padding:.65rem .75rem; font-size:.72rem; }
}
</style>

<script>
function mtShowTab(tabId, btn) {
  document.querySelectorAll('.mt-subtab-panel').forEach(p => {
    p.style.display = 'none';
    p.classList.remove('active');
  });
  document.querySelectorAll('.mt-subtab').forEach(b => {
    b.classList.remove('active');
  });
  const target = document.getElementById('mt-tab-' + tabId);
  if (target) {
    target.style.display = 'block';
    target.classList.add('active');
  }
  if (btn) {
    btn.classList.add('active');
  } else {
    const defaultBtn = document.querySelector(`.mt-subtab[data-subtab="${tabId}"]`);
    if (defaultBtn) defaultBtn.classList.add('active');
  }
  if (history.replaceState) {
    history.replaceState(null, null, '#tab-' + tabId);
  }
}

function mtFilterByJornada(jor, btn) {
  document.querySelectorAll('#mt-tab-fixture .fecha-btn').forEach(b => b.classList.remove('active'));
  if (btn) btn.classList.add('active');
  document.querySelectorAll('.mt-jornada-group').forEach(group => {
    if (jor === 'all' || group.getAttribute('data-jornada') == jor) {
      group.style.display = '';
    } else {
      group.style.display = 'none';
    }
  });
}

function mtFilterPartidos() {
  const q = (document.getElementById('mtSearchPartidos')?.value || '').toLowerCase().trim();
  document.querySelectorAll('#mt-fixture-container .partido-card-v2').forEach(card => {
    const text = (card.getAttribute('data-search') || '').toLowerCase();
    card.style.display = (!q || text.includes(q)) ? '' : 'none';
  });
  document.querySelectorAll('.mt-jornada-group').forEach(group => {
    const visible = group.querySelectorAll('.partido-card-v2:not([style*="display: none"])').length;
    group.style.display = visible > 0 ? '' : 'none';
  });
}

document.addEventListener('DOMContentLoaded', () => {
  const hash = window.location.hash.replace('#tab-', '').replace('#', '');
  if (hash && ['posiciones', 'mis-partidos', 'fixture'].includes(hash)) {
    mtShowTab(hash);
  }
});
</script>

<?php require_once 'includes/footer.php'; ?>
