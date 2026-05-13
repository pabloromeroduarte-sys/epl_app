<?php
declare(strict_types=1);
$page_title = 'Mis Torneos';
$player_tab = 'mis_torneos';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

$j = epl_jugador_actual();
if (!$j) { header('Location: login.php'); exit; }

$todos_torneos = epl_torneos_del_jugador($j['id']);

$activos    = [];
$finalizados = [];

foreach ($todos_torneos as $t) {
    $status = epl_get_liga_status($t);
    if ($status === 'activa' || $status === 'inscripcion' || $status === 'proximamente') {
        $activos[] = $t;
    } else {
        $finalizados[] = $t;
    }
}

// Torneo seleccionado por defecto (el primero activo, o el primer finalizado si no hay activos)
$sel_id = (int)($_GET['id'] ?? ($activos[0]['id'] ?? ($finalizados[0]['id'] ?? 0)));
$liga = null;
foreach ($todos_torneos as $t) {
    if ($t['id'] == $sel_id) { $liga = $t; break; }
}

// Si hay liga seleccionada, cargar sus datos
$clasificacion = $liga ? epl_clasificacion($liga['id']) : [];
$partidos_all  = $liga ? epl_partidos_liga($liga['id']) : [];

// Filtrar "Mis Partidos"
$mis_partidos = [];
if ($liga) {
    $equipo = epl_equipo_del_jugador($j['id'], $liga['id']);
    if ($equipo) {
        foreach ($partidos_all as $p) {
            if ($p['equipo_local_id'] == $equipo['id'] || $p['equipo_visitante_id'] == $equipo['id']) {
                $mis_partidos[] = $p;
            }
        }
    }
}

// Agrupar todos los partidos por jornada
$por_jornada = [];
foreach ($partidos_all as $p) {
    $jor = $p['jornada'] ?? 0;
    $por_jornada[$jor][] = $p;
}
ksort($por_jornada);

require_once 'includes/header.php';
?>

<div class="dash-layout">
  <?php include 'includes/player_sidebar.php'; ?>

  <main class="dash-main">
    <div class="dash-header">
      <h1 class="dash-title">Mis Torneos</h1>
      <p style="color:var(--gray-400);font-size:.9rem">Gestiona tu participación en las ligas y torneos activos.</p>
    </div>

    <!-- Pestañas de Torneos Activos -->
    <?php if ($activos): ?>
      <div class="tournament-tabs">
        <?php foreach ($activos as $at): ?>
          <a href="?id=<?= $at['id'] ?>" class="t-tab <?= $sel_id == $at['id'] ? 'active' : '' ?>">
            <span class="t-tab-tag">EN JUEGO</span>
            <span class="t-tab-name"><?= epl_h($at['nombre']) ?></span>
          </a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <?php if ($liga): ?>
      <?php $is_active = (epl_get_liga_status($liga) !== 'finalizada'); ?>
      
      <div class="card mb-4" style="overflow:hidden">
        <div style="background:var(--navy);padding:1.5rem 2rem;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:1rem">
          <div>
            <h2 style="font-family:var(--font-head);color:var(--white);text-transform:uppercase;font-size:1.5rem;margin:0">
              <?= epl_h($liga['nombre']) ?>
            </h2>
            <p style="color:var(--gold);font-size:.8rem;font-weight:700;text-transform:uppercase;margin-top:.2rem">
              <?= epl_h($liga['temporada']) ?> — <?= $liga['categoria'] ?>ª Cat. <?= ucfirst($liga['sexo']) ?>
            </p>
          </div>
          <?php if (!$is_active): ?>
            <span class="badge badge-walkover">FINALIZADO</span>
          <?php endif; ?>
        </div>

        <?php if ($is_active): ?>
          <!-- Sub-navegación interna del torneo activo -->
          <div class="subtabs">
            <button onclick="switchMisTorneos('ranking')" class="subtab-btn active" id="btn-sub-ranking">Clasificación</button>
            <button onclick="switchMisTorneos('mispartidos')" class="subtab-btn" id="btn-sub-mispartidos">Mis Partidos</button>
            <button onclick="switchMisTorneos('calendario')" class="subtab-btn" id="btn-sub-calendario">Calendario Completo</button>
          </div>

          <div class="p-4">
            <!-- Pestaña Clasificación -->
            <div id="sub-ranking" class="subtab-content active">
              <div class="tabla-clasificacion">
                <table>
                  <thead>
                    <tr>
                      <th style="text-align:left">#</th>
                      <th style="text-align:left">Equipo</th>
                      <th>PJ</th><th>PG</th><th>Pts</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($clasificacion as $idx => $row): 
                      $is_my_team = ($equipo && $row['equipo_id'] == $equipo['id']);
                    ?>
                      <tr style="<?= $is_my_team ? 'background:rgba(201,167,98,0.15)' : '' ?>">
                        <td><span class="posicion-num <?= $idx<3?'pos-'.($idx+1):'pos-n' ?>"><?= $idx+1 ?></span></td>
                        <td>
                          <div class="equipo-cell">
                            <span class="equipo-nombre"><?= epl_h($row['equipo_nombre']) ?></span>
                            <?php if ($is_my_team): ?><span class="badge badge-jugado" style="font-size:.6rem;padding:.1rem .4rem">TU EQUIPO</span><?php endif; ?>
                          </div>
                        </td>
                        <td><?= $row['pj'] ?></td>
                        <td><?= $row['pg'] ?></td>
                        <td><strong><?= $row['puntos'] ?></strong></td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
              <div class="text-right mt-3">
                <a href="torneo.php?id=<?= $liga['id'] ?>" class="btn btn-sm btn-outline-navy">Ver clasificación completa →</a>
              </div>
            </div>

            <!-- Pestaña Mis Partidos -->
            <div id="sub-mispartidos" class="subtab-content">
              <?php if ($mis_partidos): ?>
                <div class="partidos-grid">
                  <?php foreach ($mis_partidos as $p): ?>
                    <?php include 'includes/partido_card_v2.php'; ?>
                  <?php endforeach; ?>
                </div>
              <?php else: ?>
                <div class="empty-state">No tienes partidos registrados en este torneo.</div>
              <?php endif; ?>
            </div>

            <!-- Pestaña Calendario Completo -->
            <div id="sub-calendario" class="subtab-content">
               <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem">
                  <h3 style="font-family:var(--font-head);font-size:1.1rem;color:var(--navy);text-transform:uppercase">Calendario General</h3>
                  <a href="torneo.php?id=<?= $liga['id'] ?>" class="btn btn-sm btn-navy">Ver modo público →</a>
               </div>
               <?php foreach ($por_jornada as $jor => $ps): ?>
                  <div class="jornada-header">Fecha <?= $jor ?></div>
                  <div class="partidos-grid">
                    <?php foreach ($ps as $p): ?>
                       <?php include 'includes/partido_card_v2.php'; ?>
                    <?php endforeach; ?>
                  </div>
               <?php endforeach; ?>
            </div>
          </div>
        <?php else: ?>
          <!-- Torneo Finalizado: Vista resumida -->
          <div class="p-5 text-center">
             <div style="font-size:3rem;margin-bottom:1rem">🏆</div>
             <h3 style="font-family:var(--font-head);text-transform:uppercase;color:var(--navy)">Este torneo ha finalizado</h3>
             <p style="color:var(--gray-600);margin-bottom:1.5rem">Puedes consultar los resultados finales y la clasificación histórica en la página del torneo.</p>
             <a href="torneo.php?id=<?= $liga['id'] ?>" class="btn btn-navy">Ver resultados finales</a>
          </div>
        <?php endif; ?>
      </div>
    <?php endif; ?>

    <!-- Torneos Finalizados (Miniaturas) -->
    <?php if ($finalizados): ?>
      <div style="margin-top:3rem">
        <h3 style="font-family:var(--font-head);color:var(--navy);text-transform:uppercase;font-size:1rem;margin-bottom:1.5rem;display:flex;align-items:center;gap:.5rem">
          <svg style="width:20px;height:20px" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
          Historial de Torneos
        </h3>
        <div class="history-grid">
          <?php foreach ($finalizados as $ft): ?>
            <a href="?id=<?= $ft['id'] ?>" class="history-card <?= $sel_id == $ft['id'] ? 'active' : '' ?>">
               <div class="history-date"><?= epl_h($ft['temporada']) ?></div>
               <div class="history-name"><?= epl_h($ft['nombre']) ?></div>
               <div class="history-meta"><?= $ft['categoria'] ?>ª Cat.</div>
            </a>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endif; ?>

    <?php if (!$liga): ?>
       <div class="card p-5 text-center">
          <p style="color:var(--gray-400)">Aún no participas en ningún torneo. ¡Inscríbete para empezar a jugar!</p>
          <a href="inscribirse.php" class="btn btn-gold mt-3">Ver torneos disponibles</a>
       </div>
    <?php endif; ?>
  </main>
</div>

<style>
.tournament-tabs { display: flex; gap: .75rem; overflow-x: auto; padding-bottom: 1rem; margin-bottom: 1.5rem; }
.t-tab {
  background: var(--white);
  border: 1px solid var(--gray-200);
  border-radius: 12px;
  padding: 1rem 1.5rem;
  min-width: 200px;
  transition: all .2s;
  display: flex;
  flex-direction: column;
  gap: .25rem;
}
.t-tab:hover { border-color: var(--gold); transform: translateY(-2px); }
.t-tab.active { background: var(--navy); border-color: var(--navy); }
.t-tab-tag { font-size: .6rem; font-weight: 800; color: var(--gold); letter-spacing: .1em; }
.t-tab-name { font-family: var(--font-head); font-size: 1.1rem; color: var(--navy); text-transform: uppercase; }
.t-tab.active .t-tab-name { color: var(--white); }

.subtabs { display: flex; background: var(--gray-100); padding: .4rem; gap: .4rem; }
.subtab-btn {
  flex: 1;
  background: none;
  border: none;
  padding: .75rem;
  font-weight: 700;
  font-size: .8rem;
  text-transform: uppercase;
  color: var(--gray-600);
  border-radius: 8px;
  transition: all .2s;
}
.subtab-btn.active { background: var(--white); color: var(--navy); box-shadow: var(--shadow-sm); }

.subtab-content { display: none; padding-top: 1.5rem; }
.subtab-content.active { display: block; }

.jornada-header {
  font-family: var(--font-head);
  font-size: .9rem;
  text-transform: uppercase;
  color: var(--gold);
  background: var(--navy);
  padding: .5rem 1rem;
  border-radius: 6px;
  margin: 1.5rem 0 1rem;
}

.history-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 1rem; }
.history-card {
  background: var(--white);
  border: 1px solid var(--gray-100);
  padding: 1.25rem;
  border-radius: 12px;
  transition: all .2s;
}
.history-card:hover { border-color: var(--gray-400); }
.history-card.active { border-color: var(--gold); background: rgba(201,167,98,0.05); }
.history-date { font-size: .7rem; font-weight: 700; color: var(--gray-400); text-transform: uppercase; }
.history-name { font-family: var(--font-head); font-size: 1rem; color: var(--navy); text-transform: uppercase; margin: .25rem 0; }
.history-meta { font-size: .75rem; color: var(--gray-600); }

.empty-state { padding: 3rem; text-align: center; color: var(--gray-400); font-style: italic; }
</style>

<script>
function switchMisTorneos(tab) {
    document.querySelectorAll('.subtab-content').forEach(c => c.classList.remove('active'));
    document.querySelectorAll('.subtab-btn').forEach(b => b.classList.remove('active'));
    document.getElementById('sub-' + tab).classList.add('active');
    document.getElementById('btn-sub-' + tab).classList.add('active');
}
</script>

<?php require_once 'includes/footer.php'; ?>
