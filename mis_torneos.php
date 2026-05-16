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
    <div class="dash-header">
      <h1 class="dash-title">Mis Torneos</h1>
      <p style="color:var(--gray-400);font-size:.9rem">Tu actividad en ligas y torneos.</p>
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
        $is_active = (epl_get_liga_status($liga) !== 'finalizada');
        $badge_liga = epl_torneo_estado_badge($liga);
        $liga_en_juego = epl_get_liga_status($liga) === 'activa';
        $insc_ok = ($liga['inscripcion_estado'] ?? '') === 'aprobada';
        $tiene_actividad = !empty($proximos) || !empty($jugados);
        $solo_inscripcion = !$liga_en_juego || !$insc_ok || !$tiene_actividad;
        $msg_insc = $solo_inscripcion ? epl_mensaje_torneo_inscripcion($liga, $j['id']) : null;
      ?>

      <!-- Cabecera del torneo -->
      <div class="mt-hero">
        <div>
          <span class="mt-tab-badge mt-tab-badge--<?= epl_h($badge_liga['tone']) ?>" style="margin-bottom:.5rem;display:inline-block"><?= epl_h($badge_liga['label']) ?></span>
          <h2 class="mt-hero-title"><?= epl_h($liga['nombre']) ?></h2>
          <p class="mt-hero-sub"><?php if (!empty($liga['temporada'])): ?><?= epl_h($liga['temporada']) ?> — <?php endif; ?><?= (int)($liga['categoria'] ?? 0) ?>ª Cat. <?= ucfirst($liga['sexo'] ?? '') ?></p>
          <?php if (!empty($liga['equipo_nombre'])): ?>
            <p style="color:rgba(255,255,255,.6);font-size:.78rem;margin:.35rem 0 0">Equipo: <?= epl_h($liga['equipo_nombre']) ?></p>
          <?php endif; ?>
        </div>
        <div style="display:flex;align-items:center;gap:1rem;flex-wrap:wrap">
          <?php if ($mi_pos): ?>
            <div style="text-align:center;background:rgba(201,167,98,.15);border:1px solid rgba(201,167,98,.4);border-radius:12px;padding:.6rem 1.2rem">
              <div style="font-family:var(--font-head);font-size:1.8rem;color:var(--gold);line-height:1"><?= $mi_pos ?>º</div>
              <div style="font-size:.62rem;color:rgba(255,255,255,.5);text-transform:uppercase;letter-spacing:.08em">Posición</div>
            </div>
          <?php endif; ?>
          <?php if (!$is_active): ?>
            <span class="badge badge-walkover">FINALIZADO</span>
          <?php endif; ?>
          <a href="torneo.php?id=<?= $liga['id'] ?>" class="btn btn-sm" style="background:rgba(255,255,255,.1);color:#fff;border:1px solid rgba(255,255,255,.2)">Ver torneo público →</a>
          <?php if (epl_mostrar_boton_borrar_inscripcion_prueba() && !empty($liga['inscripcion_id']) && ($liga['inscripcion_estado'] ?? '') === 'pendiente' && ($liga['rol_equipo'] ?? '') === 'capitan'): ?>
          <form method="post" onsubmit="return confirm('¿Eliminar esta inscripción de prueba?');">
            <input type="hidden" name="epl_borrar_insc" value="1">
            <input type="hidden" name="insc_id" value="<?= (int)$liga['inscripcion_id'] ?>">
            <button type="submit" class="btn btn-sm" style="background:rgba(220,38,38,.25);color:#fecaca;border:1px solid rgba(248,113,113,.5)">Eliminar inscripción (prueba)</button>
          </form>
          <?php endif; ?>
        </div>
      </div>

      <?php if ($solo_inscripcion && $msg_insc): ?>
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

      <?php elseif ($is_active): ?>

      <!-- Grid principal: Próximos partidos + Ranking -->
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
            <div class="mt-empty">No tienes partidos pendientes.</div>
          <?php else: ?>
            <div class="mt-partidos-list">
              <?php foreach (array_slice($proximos, 0, 3) as $p):
                $es_local = ($p['equipo_local_id'] == $equipo['id']);
                $rival = $es_local ? $p['visitante_nombre'] : $p['local_nombre'];
                $estado_txt = $p['estado'] === 'reprogramado' ? 'Reprogramado' : 'Pendiente';
                $estado_color = $p['estado'] === 'reprogramado' ? '#f59e0b' : 'var(--gray-400)';
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
                <span style="font-size:.65rem;font-weight:700;color:<?= $estado_color ?>;text-transform:uppercase;white-space:nowrap"><?= $estado_txt ?></span>
              </div>
              <?php endforeach; ?>
              <?php if (count($proximos) > 3): ?>
                <div style="text-align:center;padding:.75rem;font-size:.78rem;color:var(--gold);font-weight:600;border-top:1px solid var(--gray-100)">
                  +<?= count($proximos) - 3 ?> partidos más
                </div>
              <?php endif; ?>
            </div>
          <?php endif; ?>

          <!-- Últimos resultados -->
          <?php if ($jugados): ?>
          <div class="mt-section-head" style="margin-top:1.5rem">
            <h3 class="mt-section-title">
              <svg style="width:16px;height:16px" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
              Últimos resultados
            </h3>
          </div>
          <div class="mt-partidos-list">
            <?php foreach (array_slice(array_reverse($jugados), 0, 3) as $p):
              $gane = ($p['ganador_id'] == $equipo['id']);
              $es_local = ($p['equipo_local_id'] == $equipo['id']);
              $rival = $es_local ? $p['visitante_nombre'] : $p['local_nombre'];
              $sets_yo = $es_local ? $p['sets_local'] : $p['sets_visitante'];
              $sets_rival = $es_local ? $p['sets_visitante'] : $p['sets_local'];
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
            </div>
            <?php endforeach; ?>
          </div>
          <?php endif; ?>
        </div>

        <!-- Ranking simplificado -->
        <div>
          <div class="mt-section-head">
            <h3 class="mt-section-title">
              <svg style="width:16px;height:16px" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
              Clasificación
            </h3>
            <a href="torneo.php?id=<?= $liga['id'] ?>" style="font-size:.75rem;color:var(--gold);font-weight:600">Ver completa →</a>
          </div>

          <?php if ($clasificacion): ?>
          <div class="mt-ranking-card">
            <?php
            // Mostrar top 5, siempre incluyendo el equipo del jugador
            $mostrar = [];
            $ya_incluido = false;
            foreach ($clasificacion as $idx => $row) {
                if ($idx < 5) {
                    $mostrar[] = ['idx' => $idx, 'row' => $row, 'separator' => false];
                    if ($equipo && $row['equipo_id'] == $equipo['id']) $ya_incluido = true;
                }
            }
            // Si mi equipo está fuera del top 5, añadirlo al final
            if (!$ya_incluido && $equipo) {
                foreach ($clasificacion as $idx => $row) {
                    if ($row['equipo_id'] == $equipo['id']) {
                        $mostrar[] = ['idx' => $idx, 'row' => $row, 'separator' => ($idx > 5)];
                        break;
                    }
                }
            }
            ?>
            <?php foreach ($mostrar as $item):
              $idx = $item['idx'];
              $row = $item['row'];
              $is_me = ($equipo && $row['equipo_id'] == $equipo['id']);
              $pos_colors = ['#C9A762','#9CA3AF','#CD7F32'];
            ?>
              <?php if ($item['separator']): ?>
                <div style="padding:.3rem 1rem;font-size:.65rem;color:var(--gray-400);text-align:center;letter-spacing:.1em">• • •</div>
              <?php endif; ?>
              <div class="mt-rank-row <?= $is_me ? 'mt-rank-me' : '' ?>">
                <span class="mt-rank-pos" style="color:<?= $pos_colors[$idx] ?? 'var(--gray-400)' ?>">
                  <?= $idx + 1 ?>
                </span>
                <div style="flex:1;min-width:0">
                  <div style="font-weight:<?= $is_me ? '800' : '600' ?>;font-size:.85rem;color:var(--navy);white-space:nowrap;overflow:hidden;text-overflow:ellipsis">
                    <?= epl_h($row['equipo_nombre']) ?>
                    <?php if ($is_me): ?><span style="font-size:.6rem;background:var(--gold);color:var(--navy);padding:.1rem .35rem;border-radius:4px;margin-left:.4rem;font-weight:800">TÚ</span><?php endif; ?>
                  </div>
                  <div style="font-size:.68rem;color:var(--gray-400)">
                    <?= $row['pj'] ?> PJ · <?= $row['pg'] ?>G · <?= $row['pp'] ?>P
                  </div>
                </div>
                <span style="font-family:var(--font-head);font-size:1.1rem;color:var(--navy);font-weight:700"><?= $row['puntos'] ?></span>
              </div>
            <?php endforeach; ?>
          </div>
          <?php else: ?>
            <div class="mt-empty">La clasificación se actualizará cuando empiecen los partidos.</div>
          <?php endif; ?>
        </div>

      </div><!-- /mt-main-grid -->

      <?php else: ?>
      <!-- Torneo finalizado -->
      <div style="text-align:center;padding:3rem 1rem">
        <div style="font-size:3rem;margin-bottom:1rem">🏆</div>
        <h3 style="font-family:var(--font-head);text-transform:uppercase;color:var(--navy)">Torneo finalizado</h3>
        <p style="color:var(--gray-600);margin-bottom:1.5rem">Consulta los resultados y clasificación final.</p>
        <a href="torneo.php?id=<?= $liga['id'] ?>" class="btn btn-navy">Ver resultados finales</a>
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
</style>

<?php require_once 'includes/footer.php'; ?>
