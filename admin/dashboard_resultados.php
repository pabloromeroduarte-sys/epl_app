<?php
$page_title = 'Admin — Resultados';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../includes/mail.php';
epl_require_admin();

$db = epl_db();

// --- POST handlers ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'reenviar_alerta_jugadores' || $action === 'reenviar_alerta_admins') {
        $partido_id = (int)($_POST['partido_id'] ?? 0);
        if ($partido_id) {
            // Obtener info del partido
            $st = $db->prepare("
                SELECT p.*,
                       el.nombre AS local_nombre,
                       ev.nombre AS visitante_nombre,
                       l.nombre AS liga_nombre
                FROM partidos p
                JOIN equipos el ON el.id = p.equipo_local_id
                JOIN equipos ev ON ev.id = p.equipo_visitante_id
                JOIN ligas l ON l.id = p.liga_id
                WHERE p.id = ?
            ");
            $st->execute([$partido_id]);
            $partido = $st->fetch();

            if ($partido && $partido['estado'] === 'jugado') {
                $ganador_id = $partido['ganador_id'];
                $sets_local = $partido['sets_local'];
                $sets_vis = $partido['sets_visitante'];
                
                $gano_local = $ganador_id == $partido['equipo_local_id'];
                $ganador_nombre = $gano_local ? $partido['local_nombre'] : $partido['visitante_nombre'];
                $resultado_str = "{$sets_local}-{$sets_vis}";
                
                // Tratar de recuperar los sets
                $sets = [];
                for ($s=1; $s<=3; $s++) {
                    if ($partido["games_s{$s}_local"] !== null) {
                        $sets[] = ['local' => $partido["games_s{$s}_local"], 'visitante' => $partido["games_s{$s}_visitante"]];
                    }
                }
                $resultado_sets = implode(' / ', array_map(fn($s) => "{$s['local']}-{$s['visitante']}", $sets));
                if (!$resultado_sets) $resultado_sets = $resultado_str;
                
                $url_reclamar = epl_url("reclamar_resultado.php?partido_id={$partido_id}");
                $asunto_res = epl_mail_asunto(
                    '⚽ Resultado ingresado',
                    $partido['local_nombre'],
                    $partido['visitante_nombre'],
                    $partido['jornada'] ?? null
                );

                // Obtener quién ingresó (si sabemos)
                $nombre_quien_ingresa = "Un jugador";
                if ($partido['ingresado_por']) {
                    $st_ing = $db->prepare("SELECT nombre, apellido FROM jugadores WHERE id = ?");
                    $st_ing->execute([$partido['ingresado_por']]);
                    $ing = $st_ing->fetch();
                    if ($ing) {
                        $nombre_quien_ingresa = trim($ing['nombre'] . ' ' . $ing['apellido']);
                    }
                }

                if ($action === 'reenviar_alerta_jugadores') {
                    $texto_rival_subtitulo = "El jugador {$nombre_quien_ingresa} ingresó el resultado de tu partido {$partido['local_nombre']} vs {$partido['visitante_nombre']} (Jornada " . ($partido['jornada'] ?? '—') . ").";
                    $texto_rival_tip = "⚠️ En caso de tener algún problema con el resultado contáctate con los organizadores (tienes 24 horas para reclamar).";

                    // Enviar a todos los jugadores de ambos equipos
                    $st_jug = $db->prepare("SELECT jugador1_id, jugador2_id FROM equipos WHERE id IN (?, ?)");
                    $st_jug->execute([$partido['equipo_local_id'], $partido['equipo_visitante_id']]);
                    
                    $jugadores_ids = [];
                    foreach ($st_jug->fetchAll() as $row) {
                        if ($row['jugador1_id']) $jugadores_ids[] = $row['jugador1_id'];
                        if ($row['jugador2_id']) $jugadores_ids[] = $row['jugador2_id'];
                    }
                    $jugadores_ids = array_unique($jugadores_ids);

                    foreach ($jugadores_ids as $jid) {
                        epl_notif_crear(
                            (int)$jid,
                            'resultado',
                            $asunto_res,
                            $texto_rival_subtitulo . ' ' . $texto_rival_tip,
                            $url_reclamar,
                            true
                        );
                        epl_mail_partido_visual(
                            (int)$jid,
                            $asunto_res,
                            $partido['local_nombre'],
                            $partido['visitante_nombre'],
                            [
                                ['icon' => '🏆', 'label' => 'Ganador',   'valor' => $ganador_nombre],
                                ['icon' => '🎾', 'label' => 'Resultado', 'valor' => $resultado_sets],
                            ],
                            $texto_rival_subtitulo,
                            $texto_rival_tip,
                            $url_reclamar,
                            '⚠️ Reclamar Resultado'
                        );
                    }
                    if (session_status() === PHP_SESSION_NONE) session_start();
                    $_SESSION['_epl_flash'] = ['tipo' => 'ok', 'msg' => 'Alerta enviada a los jugadores.'];
                }

                if ($action === 'reenviar_alerta_admins') {
                    $admins_st = $db->query("SELECT id FROM jugadores WHERE rol = 'admin'");
                    $admins_ids = $admins_st->fetchAll(PDO::FETCH_COLUMN);
                    
                    $fecha_hora_fmt = $partido['resultado_ingresado_at'] ? date('d/m/Y H:i', strtotime($partido['resultado_ingresado_at'])) : date('d/m/Y H:i');
                    
                    $texto_admin_subtitulo = "El jugador {$nombre_quien_ingresa} registró el resultado {$resultado_sets} del partido {$partido['local_nombre']} vs {$partido['visitante_nombre']} de la jornada " . ($partido['jornada'] ?? '—') . ".";
                    $texto_admin_tip = "Fecha y hora de registro: {$fecha_hora_fmt}";
                    $url_admin = epl_url("admin/partido_detalle.php?id={$partido_id}");

                    foreach ($admins_ids as $admin_id) {
                        epl_notif_crear(
                            (int)$admin_id,
                            'resultado',
                            $asunto_res,
                            $texto_admin_subtitulo . ' ' . $texto_admin_tip,
                            $url_admin,
                            true
                        );
                        epl_mail_partido_visual(
                            (int)$admin_id,
                            $asunto_res,
                            $partido['local_nombre'],
                            $partido['visitante_nombre'],
                            [
                                ['icon' => '🏆', 'label' => 'Ganador',   'valor' => $ganador_nombre],
                                ['icon' => '🎾', 'label' => 'Resultado', 'valor' => $resultado_sets],
                            ],
                            $texto_admin_subtitulo,
                            $texto_admin_tip,
                            $url_admin,
                            'Ver en panel admin'
                        );
                    }
                    if (session_status() === PHP_SESSION_NONE) session_start();
                    $_SESSION['_epl_flash'] = ['tipo' => 'ok', 'msg' => 'Alerta enviada a los administradores.'];
                }
            }
        }
        header('Location: dashboard_resultados.php'); exit;
    }
}

// Obtener todos los partidos jugados (con resultado)
$partidos_jugados = $db->query("
    SELECT p.id, p.jornada, p.fecha_jugado, p.estado,
           p.sets_local, p.sets_visitante, p.ganador_id,
           p.games_s1_local, p.games_s1_visitante,
           p.games_s2_local, p.games_s2_visitante,
           p.games_s3_local, p.games_s3_visitante,
           p.ingresado_por, p.resultado_ingresado_at,
           l.id AS liga_id, l.nombre AS liga_nombre,
           el.id AS local_id, el.nombre AS local_nombre,
           ev.id AS visitante_id, ev.nombre AS visitante_nombre,
           ji.nombre AS ingresado_nombre, ji.apellido AS ingresado_apellido
    FROM partidos p
    JOIN ligas l ON l.id = p.liga_id
    JOIN equipos el ON el.id = p.equipo_local_id
    JOIN equipos ev ON ev.id = p.equipo_visitante_id
    LEFT JOIN jugadores ji ON ji.id = p.ingresado_por
    WHERE p.estado IN ('jugado', 'walkover', 'no_presentado')
    ORDER BY p.resultado_ingresado_at DESC, p.fecha_jugado DESC
")->fetchAll();

// Separar recientes (últimos 10)
$recientes = array_slice($partidos_jugados, 0, 10);
$resto = array_slice($partidos_jugados, 10);

$ligas = $db->query("SELECT id, nombre FROM ligas ORDER BY nombre")->fetchAll();

require_once '../includes/header.php';
?>
<div class="dash-layout">
  <?php include __DIR__ . '/partials/sidebar.php'; ?>

  <main class="dash-main">

    <?php $_flash = epl_flash_get(); if ($_flash): ?>
      <div class="alert alert-<?= $_flash['tipo'] === 'ok' ? 'success' : 'error' ?>" style="margin-bottom:1rem"><?= epl_h($_flash['msg']) ?></div>
    <?php endif; ?>

    <!-- HEADER hero -->
    <div class="dash-header" style="background:linear-gradient(135deg,#1c2f48 0%, #0f1e30 100%);border-radius:18px;padding:1.5rem 1.75rem;color:#fff;margin-bottom:1.5rem;position:relative;overflow:hidden;box-shadow:0 8px 28px rgba(28,47,72,.18)">
      <div style="position:relative;z-index:1;display:flex;justify-content:space-between;align-items:flex-end;gap:1rem;flex-wrap:wrap">
        <div>
          <span style="font-size:.65rem;font-weight:900;letter-spacing:.25em;color:#C9A762;text-transform:uppercase">Panel admin</span>
          <h1 style="color:#fff;margin:.2rem 0 .15rem;font-size:clamp(1.5rem,3.5vw,2rem);font-family:'Anton',sans-serif;text-transform:uppercase;line-height:1">Resultados <span style="color:#C9A762">Ingresados</span></h1>
          <p style="color:rgba(255,255,255,.7);margin-top:.2rem;font-size:.82rem">Revisión de resultados cargados por los jugadores y reenvío de notificaciones.</p>
        </div>
      </div>
    </div>

    <!-- BUSCADOR Y FILTROS -->
    <div class="filtros-container">
      <div class="busqueda" style="flex: 1; min-width: 250px;">
        <svg width="16" height="16" fill="none" stroke="#94a3b8" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
        <input type="text" id="buscar" placeholder="Buscar por equipo, liga o fecha..." oninput="filtrarResultados()">
      </div>
      
      <div class="filtro-item">
        <select id="filtro-liga" onchange="filtrarResultados()">
          <option value="">Todas las ligas</option>
          <?php foreach ($ligas as $l): ?>
            <option value="<?= $l['id'] ?>"><?= epl_h($l['nombre']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="filtro-item">
        <input type="number" id="filtro-jornada" placeholder="Jornada" style="width: 100px;" oninput="filtrarResultados()">
      </div>

      <div class="filtro-item" style="display: flex; gap: .5rem; align-items: center;">
        <span style="font-size: .8rem; color: var(--navy); font-weight: 700;">Desde</span>
        <input type="date" id="filtro-desde" onchange="filtrarResultados()">
      </div>
      <div class="filtro-item" style="display: flex; gap: .5rem; align-items: center;">
        <span style="font-size: .8rem; color: var(--navy); font-weight: 700;">Hasta</span>
        <input type="date" id="filtro-hasta" onchange="filtrarResultados()">
      </div>
      
      <button class="btn-limpiar" onclick="limpiarFiltros()" style="display: none;" id="btn-limpiar">Limpiar filtros</button>
    </div>

    <div class="tabs-bar">
      <button class="tab-btn active" data-tab="recientes" onclick="cambiarTab('recientes')">
        🆕 Últimos 10
      </button>
      <button class="tab-btn" data-tab="todos" onclick="cambiarTab('todos')">
        📊 Historial Completo
      </button>
    </div>

    <!-- PESTAÑA RECIENTES -->
    <div id="tab-recientes" class="tab-content" style="display: block;">
      <section class="sec-card" style="border-left:5px solid #10b981;margin-bottom:1.25rem;">
        <div class="sec-body" id="lista-recientes">
          <?php if (empty($recientes)): ?>
            <div style="padding: 2rem; text-align: center; color: var(--gray-400);">No hay resultados recientes.</div>
          <?php else: ?>
            <?php foreach ($recientes as $p): ?>
              <?= render_resultado_row($p, true) ?>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </section>
    </div>

    <!-- PESTAÑA TODOS -->
    <div id="tab-todos" class="tab-content" style="display: none;">
      <section class="sec-card" style="border-left:5px solid #94a3b8">
        <div class="sec-body" id="lista-resultados">
          <?php foreach ($partidos_jugados as $p): ?>
            <?= render_resultado_row($p, true) ?>
          <?php endforeach; ?>
        </div>
      </section>
    </div>

  </main>
</div>

<?php
function render_resultado_row($p, $for_list = false) {
    $estado = $p['estado'];
    $es_w_o = in_array($estado, ['walkover', 'no_presentado']);
    
    // Armar string de sets
    $sets = [];
    if (!$es_w_o) {
        for ($s=1; $s<=3; $s++) {
            if ($p["games_s{$s}_local"] !== null) {
                $sets[] = $p["games_s{$s}_local"] . '-' . $p["games_s{$s}_visitante"];
            }
        }
    }
    $sets_str = $sets ? implode(' / ', $sets) : ($es_w_o ? ucfirst(str_replace('_',' ',$estado)) : "{$p['sets_local']}-{$p['sets_visitante']}");
    
    $ingresado_por = trim(($p['ingresado_nombre'] ?? '') . ' ' . ($p['ingresado_apellido'] ?? ''));
    if (!$ingresado_por) $ingresado_por = 'Admin';
    $fecha_ingreso = $p['resultado_ingresado_at'] ? date('d/m/Y H:i', strtotime($p['resultado_ingresado_at'])) : date('d/m/Y H:i', strtotime($p['fecha_jugado'] ?? 'today'));
    
    $search_str = strtolower("{$p['local_nombre']} {$p['visitante_nombre']} {$p['liga_nombre']} {$fecha_ingreso}");
    
    // Filtros extra
    $fecha_iso = $p['fecha_jugado'] ? date('Y-m-d', strtotime($p['fecha_jugado'])) : date('Y-m-d', strtotime($p['resultado_ingresado_at'] ?? 'today'));
    
    ob_start();
?>
    <div class="partido-row" 
         <?= $for_list ? "data-search=\"".epl_h($search_str)."\"" : "" ?>
         <?= $for_list ? "data-liga-id=\"".epl_h($p['liga_id'])."\"" : "" ?>
         <?= $for_list ? "data-jornada=\"".epl_h($p['jornada'])."\"" : "" ?>
         <?= $for_list ? "data-fecha=\"".epl_h($fecha_iso)."\"" : "" ?>>
      <div class="partido-row-main">
        <div class="partido-meta">
          <span class="partido-liga"><?= epl_h($p['liga_nombre']) ?></span>
          <?php if ($p['jornada']): ?>
            <span class="partido-jornada">J<?= $p['jornada'] ?></span>
          <?php endif; ?>
        </div>
        <div class="partido-equipos" style="margin-top:.4rem">
          <strong style="color:<?= $p['ganador_id'] == $p['local_id'] ? '#15803d' : '#1c2f48' ?>"><?= epl_h($p['local_nombre']) ?></strong>
          <span class="vs">vs</span>
          <strong style="color:<?= $p['ganador_id'] == $p['visitante_id'] ? '#15803d' : '#1c2f48' ?>"><?= epl_h($p['visitante_nombre']) ?></strong>
        </div>
        <div class="partido-extra" style="margin-top:.5rem">
          <span class="extra-item" style="color:#1e40af;font-weight:800;background:#dbeafe;padding:.2rem .5rem;border-radius:4px">
            🎾 <?= epl_h($sets_str) ?>
          </span>
          <span class="extra-item">
            Ingresado por: <strong><?= epl_h($ingresado_por) ?></strong>
          </span>
          <span class="extra-item" style="color:#64748b">
            📅 <?= $fecha_ingreso ?>
          </span>
        </div>
      </div>
      
      <?php if (!$es_w_o): ?>
      <div style="display:flex;flex-direction:column;gap:.35rem;align-items:stretch">
        <form method="post" style="margin:0">
            <input type="hidden" name="action" value="reenviar_alerta_jugadores">
            <input type="hidden" name="partido_id" value="<?= $p['id'] ?>">
            <button type="submit" class="btn-gestionar" style="width:100%;background:#f8fafc;color:#1c2f48;border:1px solid #cbd5e1"
                    data-confirm="¿Reenviar alerta de resultado a JUGADORES?" data-confirm-ok="Sí, reenviar">
                📧 Reenviar Jugadores
            </button>
        </form>
        <form method="post" style="margin:0">
            <input type="hidden" name="action" value="reenviar_alerta_admins">
            <input type="hidden" name="partido_id" value="<?= $p['id'] ?>">
            <button type="submit" class="btn-gestionar" style="width:100%;background:#e0f2fe;color:#0369a1;border:1px solid #7dd3fc"
                    data-confirm="¿Reenviar alerta de resultado a ADMINS?" data-confirm-ok="Sí, reenviar">
                📢 Reenviar Admins
            </button>
        </form>
        <a href="partido_detalle.php?id=<?= $p['id'] ?>" class="btn-gestionar" style="text-align:center;background:#1e293b;color:#fff">Ver Partido</a>
      </div>
      <?php endif; ?>
    </div>
<?php
    return ob_get_clean();
}
?>

<style>
.partido-row {
  display: flex; gap: 1rem; padding: 1.25rem; border-bottom: 1px solid var(--gray-100);
  background: var(--white); transition: background .2s;
}
.partido-row:last-child { border-bottom: none; }
.partido-row:hover { background: #f8fafc; }
@media (max-width: 640px) {
  .partido-row { flex-direction: column; }
}
.partido-row-main { flex: 1; min-width: 0; }
.partido-meta { display: flex; align-items: center; gap: .5rem; flex-wrap: wrap; margin-bottom: .25rem; }
.partido-liga { font-size: .65rem; font-weight: 800; text-transform: uppercase; letter-spacing: .08em; color: var(--navy); background: #f1f5f9; padding: .2rem .5rem; border-radius: 6px; }
.partido-jornada { font-size: .65rem; font-weight: 800; color: #475569; background: #e2e8f0; padding: .2rem .5rem; border-radius: 6px; }
.partido-equipos { display: flex; align-items: center; gap: .5rem; font-size: 1.1rem; font-family: var(--font-head); text-transform: uppercase; flex-wrap: wrap; }
.vs { font-size: .65rem; font-weight: 800; color: var(--gold); background: var(--navy); padding: .15rem .45rem; border-radius: 6px; font-family: var(--font-base); }
.partido-extra { display: flex; align-items: center; gap: .8rem; flex-wrap: wrap; font-size: .75rem; color: #475569; }
.extra-item { display: inline-flex; align-items: center; gap: .3rem; }

.sec-card { background: var(--white); border-radius: 16px; border: 1px solid var(--gray-200); margin-bottom: 2rem; box-shadow: 0 4px 20px rgba(0,0,0,.04); overflow: hidden; }
.sec-head { display: flex; align-items: center; justify-content: space-between; padding: 1.25rem 1.5rem; background: #f8fafc; border-bottom: 1px solid var(--gray-100); }
.sec-title { font-family: var(--font-head); font-size: 1.1rem; color: var(--navy); text-transform: uppercase; margin: 0; }

.btn-gestionar {
  display: inline-flex; align-items: center; justify-content: center;
  background: var(--navy); color: var(--gold); font-size: .75rem; font-weight: 700;
  text-transform: uppercase; letter-spacing: .05em; padding: .5rem .85rem;
  border-radius: 8px; text-decoration: none; border: 1px solid transparent; cursor: pointer;
  transition: all .2s; white-space: nowrap;
}
.btn-gestionar:hover { transform: translateY(-1px); box-shadow: 0 4px 12px rgba(28,47,72,.15); opacity: .9; }

.filtros-container { display: flex; align-items: center; gap: 1rem; margin-bottom: 1.5rem; flex-wrap: wrap; background: #f8fafc; padding: 1rem; border-radius: 12px; border: 1px solid var(--gray-200); }
.busqueda { position: relative; }
.busqueda svg { position: absolute; left: 1rem; top: 50%; transform: translateY(-50%); }
.busqueda input {
  width: 100%; padding: .65rem 1rem .65rem 2.5rem; border-radius: 8px;
  border: 1px solid var(--gray-300); font-size: .85rem; background: var(--white);
  transition: all .2s;
}
.busqueda input:focus { outline: none; border-color: var(--navy); box-shadow: 0 0 0 3px rgba(28,47,72,.1); }

.filtro-item select, .filtro-item input[type="number"], .filtro-item input[type="date"] {
  padding: .65rem; border-radius: 8px; border: 1px solid var(--gray-300);
  font-size: .85rem; background: var(--white); outline: none;
  font-family: inherit; transition: all .2s;
}
.filtro-item select:focus, .filtro-item input:focus { border-color: var(--navy); }

.btn-limpiar { background: #fee2e2; color: #991b1b; font-size: .75rem; font-weight: 700; border: none; padding: .65rem 1rem; border-radius: 8px; cursor: pointer; transition: all .2s; }
.btn-limpiar:hover { background: #fca5a5; }

.tabs-bar { display: flex; gap: .5rem; margin-bottom: 1.5rem; overflow-x: auto; padding-bottom: .2rem; }
.tab-btn { background: #e2e8f0; color: #475569; font-weight: 800; font-size: .8rem; text-transform: uppercase; letter-spacing: .05em; padding: .75rem 1.25rem; border-radius: 8px; border: none; cursor: pointer; transition: all .2s; white-space: nowrap; }
.tab-btn:hover { background: #cbd5e1; }
.tab-btn.active { background: var(--navy); color: var(--gold); box-shadow: 0 4px 12px rgba(28,47,72,.15); }
</style>

<script>
function cambiarTab(tab) {
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    document.querySelector(`[data-tab="${tab}"]`).classList.add('active');
    document.querySelectorAll('.tab-content').forEach(c => c.style.display = 'none');
    document.getElementById(`tab-${tab}`).style.display = 'block';
}

function filtrarResultados() {
    const q = document.getElementById('buscar').value.toLowerCase();
    const l = document.getElementById('filtro-liga').value;
    const j = document.getElementById('filtro-jornada').value;
    const d = document.getElementById('filtro-desde').value;
    const h = document.getElementById('filtro-hasta').value;
    
    const rows = document.querySelectorAll('.tab-content .partido-row');
    const btnLimpiar = document.getElementById('btn-limpiar');
    
    let count = 0;
    rows.forEach(r => {
        const searchStr = r.getAttribute('data-search') || '';
        const rowLiga = r.getAttribute('data-liga-id') || '';
        const rowJornada = r.getAttribute('data-jornada') || '';
        const rowFecha = r.getAttribute('data-fecha') || '';
        
        let show = true;
        
        if (q && !searchStr.includes(q)) show = false;
        if (l && rowLiga !== l) show = false;
        if (j && rowJornada !== j) show = false;
        if (d && rowFecha < d) show = false;
        if (h && rowFecha > h) show = false;
        
        if (show) {
            r.style.display = 'flex';
            count++;
        } else {
            r.style.display = 'none';
        }
    });

    if (q || l || j || d || h) {
        btnLimpiar.style.display = 'inline-block';
    } else {
        btnLimpiar.style.display = 'none';
    }
}

function limpiarFiltros() {
    document.getElementById('buscar').value = '';
    document.getElementById('filtro-liga').value = '';
    document.getElementById('filtro-jornada').value = '';
    document.getElementById('filtro-desde').value = '';
    document.getElementById('filtro-hasta').value = '';
    filtrarResultados();
}
</script>

<?php require_once '../includes/footer.php'; ?>
