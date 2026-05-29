<?php
$page_title = 'Admin — Registrar Resultados';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../includes/mail.php';
epl_require_admin();

$db = epl_db();

// ────────────────────────────────────────────────────────────────────────
// POST handler: Registrar Resultado Rápido
// ────────────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'registrar_resultado_rapido') {
    $partido_id   = (int)($_POST['partido_id'] ?? 0);
    $fecha_jugado = trim($_POST['fecha_jugado'] ?? '');
    
    // Validar partido
    $stP = $db->prepare("SELECT * FROM partidos WHERE id=? AND estado IN ('pendiente', 'reprogramado')");
    $stP->execute([$partido_id]);
    $partido = $stP->fetch();
    
    if (!$partido) {
        $_SESSION['_epl_flash'] = ['tipo'=>'error', 'msg'=>'Partido no válido o ya jugado.'];
    } else {
        $sets = [];
        for ($s = 1; $s <= 3; $s++) {
            $gl = isset($_POST["s{$s}_l"]) && $_POST["s{$s}_l"] !== '' ? (int)$_POST["s{$s}_l"] : null;
            $gv = isset($_POST["s{$s}_v"]) && $_POST["s{$s}_v"] !== '' ? (int)$_POST["s{$s}_v"] : null;
            if ($gl !== null && $gv !== null) {
                $sets[] = ['l' => $gl, 'v' => $gv];
            }
        }
        
        if (empty($sets)) {
            $_SESSION['_epl_flash'] = ['tipo'=>'error', 'msg'=>'Debes ingresar al menos un set.'];
        } else {
            $sets_local = 0; $sets_vis = 0;
            foreach ($sets as $sv) {
                if ($sv['l'] > $sv['v']) $sets_local++;
                else $sets_vis++;
            }
            $ganador_id = $sets_local > $sets_vis ? $partido['equipo_local_id'] : $partido['equipo_visitante_id'];
            $ahora = date('Y-m-d H:i:s');
            
            $db->prepare("
                UPDATE partidos SET
                  estado='jugado', fecha_jugado=?,
                  sets_local=?, sets_visitante=?,
                  games_s1_local=?, games_s1_visitante=?,
                  games_s2_local=?, games_s2_visitante=?,
                  games_s3_local=?, games_s3_visitante=?,
                  ganador_id=?, ingresado_por=?,
                  resultado_ingresado_at=?
                WHERE id=?
            ")->execute([
                $fecha_jugado ?: $ahora,
                $sets_local, $sets_vis,
                $sets[0]['l'] ?? null, $sets[0]['v'] ?? null,
                $sets[1]['l'] ?? null, $sets[1]['v'] ?? null,
                $sets[2]['l'] ?? null, $sets[2]['v'] ?? null,
                $ganador_id, null, $ahora, $partido_id
            ]);
            
            epl_recalcular_clasificacion($partido['liga_id']);
            
            // Notificar a los jugadores de ambos equipos
            try {
                $stLocal = $db->prepare("SELECT nombre FROM equipos WHERE id=?");
                $stLocal->execute([$partido['equipo_local_id']]);
                $local_nombre = $stLocal->fetchColumn();
                
                $stVis = $db->prepare("SELECT nombre FROM equipos WHERE id=?");
                $stVis->execute([$partido['equipo_visitante_id']]);
                $visitante_nombre = $stVis->fetchColumn();
                
                $resultado_sets = implode(' / ', array_map(fn($s) => "{$s['l']}-{$s['v']}", $sets));
                $ganador_nombre = $ganador_id == $partido['equipo_local_id'] ? $local_nombre : $visitante_nombre;
                
                $asunto_res = epl_mail_asunto(
                    '⚽ Resultado registrado por Admin',
                    $local_nombre,
                    $visitante_nombre,
                    $partido['jornada'] ?? null
                );
                
                $texto_subtitulo = "La organización registró el resultado {$resultado_sets} de tu partido vs " . ($ganador_id == $partido['equipo_local_id'] ? $visitante_nombre : $local_nombre) . ".";
                $texto_tip = "⚠️ Puedes revisar la clasificación actualizada en tu panel de jugador.";
                $url_reclamar = epl_url("dashboard.php");
                
                // Jugadores a notificar
                $st_jug = $db->prepare("SELECT jugador1_id, jugador2_id FROM equipos WHERE id IN (?,?)");
                $st_jug->execute([$partido['equipo_local_id'], $partido['equipo_visitante_id']]);
                
                $jugadores_ids = [];
                foreach ($st_jug->fetchAll() as $row) {
                    if ($row['jugador1_id']) $jugadores_ids[] = (int)$row['jugador1_id'];
                    if ($row['jugador2_id']) $jugadores_ids[] = (int)$row['jugador2_id'];
                }
                $jugadores_ids = array_unique($jugadores_ids);
                
                foreach ($jugadores_ids as $jid) {
                    epl_notif_crear(
                        $jid,
                        'resultado',
                        $asunto_res,
                        $texto_subtitulo . ' ' . $texto_tip,
                        $url_reclamar,
                        true // skip_email
                    );
                    
                    epl_mail_partido_visual(
                        $jid,
                        $asunto_res,
                        $local_nombre,
                        $visitante_nombre,
                        [
                            ['icon' => '🏆', 'label' => 'Ganador',   'valor' => $ganador_nombre],
                            ['icon' => '🎾', 'label' => 'Resultado', 'valor' => $resultado_sets],
                        ],
                        $texto_subtitulo,
                        $texto_tip,
                        $url_reclamar,
                        'Ver Clasificación'
                    );
                }
            } catch (Throwable $notif_err) {
                // Ignorar fallos de notificaciones
            }
            
            $_SESSION['_epl_flash'] = ['tipo'=>'ok', 'msg'=>'Resultado registrado exitosamente.'];
        }
    }
    
    // Redirigir preservando filtros
    $redirect_url = 'cargar_resultados.php';
    $params = [];
    foreach (['liga', 'jornada', 'search', 'fecha'] as $k) {
        if (!empty($_POST["_f_$k"])) $params[$k] = $_POST["_f_$k"];
    }
    if (!empty($params)) $redirect_url .= '?' . http_build_query($params);
    header('Location: ' . $redirect_url);
    exit;
}

// ────────────────────────────────────────────────────────────────────────
// Filtros y Carga de Partidos Pendientes
// ────────────────────────────────────────────────────────────────────────
$ligas = $db->query("SELECT * FROM ligas ORDER BY id DESC")->fetchAll();
$liga_id = isset($_GET['liga']) ? (int)$_GET['liga'] : 0;
if (!$liga_id && !empty($ligas)) {
    $liga_id = (int)$ligas[0]['id']; // Seleccionar la más reciente por defecto
}
$liga_sel = null;
foreach ($ligas as $l) { if ($l['id'] == $liga_id) { $liga_sel = $l; break; } }

$f_fecha  = trim($_GET['fecha']   ?? '');
$f_search = trim($_GET['search']  ?? '');
$f_jornada = isset($_GET['jornada']) ? (int)$_GET['jornada'] : 0;

$where_p = "WHERE p.liga_id = ? AND p.estado IN ('pendiente', 'reprogramado')";
$params_p = [$liga_id];

if ($f_fecha) {
    $where_p .= " AND p.nombre_fecha = ?";
    $params_p[] = $f_fecha;
}
if ($f_jornada > 0) {
    $where_p .= " AND p.jornada = ?";
    $params_p[] = $f_jornada;
}
if ($f_search) {
    foreach (explode(' ', $f_search) as $part) {
        $part = trim($part);
        if ($part === '') continue;
        $where_p .= " AND (el.nombre LIKE ? OR ev.nombre LIKE ? OR
                           jl1.nombre LIKE ? OR jl1.apellido LIKE ? OR jl2.nombre LIKE ? OR jl2.apellido LIKE ? OR
                           jv1.nombre LIKE ? OR jv1.apellido LIKE ? OR jv2.nombre LIKE ? OR jv2.apellido LIKE ?)";
        $p_val = "%$part%";
        for ($i = 0; $i < 10; $i++) $params_p[] = $p_val;
    }
}

// Obtener partidos
$stP = $db->prepare("
    SELECT p.*, el.nombre AS local_nombre, ev.nombre AS visitante_nombre,
           r.nombre AS recinto_nombre,
           jl1.nombre AS jl1_n, jl1.apellido AS jl1_a,
           jl2.nombre AS jl2_n, jl2.apellido AS jl2_a,
           jv1.nombre AS jv1_n, jv1.apellido AS jv1_a,
           jv2.nombre AS jv2_n, jv2.apellido AS jv2_a
    FROM partidos p
    JOIN equipos el ON el.id = p.equipo_local_id
    JOIN equipos ev ON ev.id = p.equipo_visitante_id
    LEFT JOIN jugadores jl1 ON jl1.id = el.jugador1_id
    LEFT JOIN jugadores jl2 ON jl2.id = el.jugador2_id
    LEFT JOIN jugadores jv1 ON jv1.id = ev.jugador1_id
    LEFT JOIN jugadores jv2 ON jv2.id = ev.jugador2_id
    LEFT JOIN recintos r ON r.id = p.recinto_id
    $where_p
    ORDER BY p.jornada ASC, p.fecha_programada ASC, p.id ASC
");
$stP->execute($params_p);
$partidos = $stP->fetchAll();

// Obtener jornadas disponibles para filtros
$jornadas_disponibles = [];
$fechas_disponibles = [];
if ($liga_id) {
    $stJ = $db->prepare("SELECT jornada, nombre_fecha FROM partidos WHERE liga_id=? AND estado IN ('pendiente','reprogramado') GROUP BY jornada, nombre_fecha ORDER BY jornada ASC");
    $stJ->execute([$liga_id]);
    $jornadas_res = $stJ->fetchAll();
    foreach ($jornadas_res as $jr) {
        if ($jr['jornada'] && !in_array($jr['jornada'], $jornadas_disponibles)) {
            $jornadas_disponibles[] = (int)$jr['jornada'];
        }
        if ($jr['nombre_fecha'] && !in_array($jr['nombre_fecha'], $fechas_disponibles)) {
            $fechas_disponibles[] = $jr['nombre_fecha'];
        }
    }
}

require_once '../includes/header.php';
?>

<div class="dash-layout">
  <?php include __DIR__ . '/partials/sidebar.php'; ?>

  <main class="dash-main">

    <div class="dash-header" style="background:linear-gradient(135deg,#1c2f48 0%, #0f1e30 100%);border-radius:18px;padding:1.1rem 1.25rem;color:#fff;margin-bottom:1rem;position:relative;overflow:hidden;box-shadow:0 8px 28px rgba(28,47,72,.18)">
      <div style="position:absolute;top:-40px;right:-40px;width:180px;height:180px;background:radial-gradient(circle,rgba(201,167,98,.18) 0%,transparent 70%);pointer-events:none"></div>
      <div class="ld-header-row" style="position:relative;z-index:1;display:flex;justify-content:space-between;align-items:center;gap:.75rem;flex-wrap:wrap">
        <div style="flex:1;min-width:0">
          <span style="font-size:.6rem;font-weight:900;letter-spacing:.22em;color:#C9A762;text-transform:uppercase">Panel admin</span>
          <h1 class="dash-title" style="color:#fff;margin:.2rem 0 .15rem;font-size:clamp(1.3rem,5vw,1.9rem);font-family:'Anton',sans-serif;text-transform:uppercase;line-height:1">Registrar <span style="color:#C9A762">Resultados</span></h1>
          <p style="color:rgba(255,255,255,.7);margin-top:.15rem;font-size:.75rem;line-height:1.3"><?= count($partidos) ?> partido<?= count($partidos)!==1?'s':'' ?> pendientes de resultado · <?= $liga_sel ? epl_h($liga_sel['nombre']) : 'Sin liga' ?></p>
        </div>
      </div>
    </div>

    <?php $_flash = epl_flash_get(); if ($_flash): ?>
      <div class="alert alert-<?= $_flash['tipo'] === 'ok' ? 'success' : 'error' ?>" style="margin-bottom:1rem"><?= epl_h($_flash['msg']) ?></div>
    <?php endif; ?>

    <!-- PESTAÑAS DE NAVEGACIÓN -->
    <div style="display:flex;gap:.35rem;margin-bottom:1rem;background:#f1f5f9;padding:.3rem;border-radius:10px;width:max-content;max-width:100%">
      <a href="partidos.php" style="text-decoration:none;font-size:.72rem;font-weight:800;text-transform:uppercase;letter-spacing:.05em;padding:.5rem .9rem;border-radius:7px;transition:all .15s;display:inline-flex;align-items:center;gap:.35rem;<?= $cur==='partidos.php'?'background:#1c2f48;color:#fff;box-shadow:0 2px 6px rgba(28,47,72,.15)':'color:#64748b' ?>">
        🎾 Todos los Partidos
      </a>
      <a href="proximos_partidos.php" style="text-decoration:none;font-size:.72rem;font-weight:800;text-transform:uppercase;letter-spacing:.05em;padding:.5rem .9rem;border-radius:7px;transition:all .15s;display:inline-flex;align-items:center;gap:.35rem;<?= $cur==='proximos_partidos.php'?'background:#1c2f48;color:#fff;box-shadow:0 2px 6px rgba(28,47,72,.15)':'color:#64748b' ?>">
        🔜 Próximos Partidos
      </a>
      <a href="cargar_resultados.php" style="text-decoration:none;font-size:.72rem;font-weight:800;text-transform:uppercase;letter-spacing:.05em;padding:.5rem .9rem;border-radius:7px;transition:all .15s;display:inline-flex;align-items:center;gap:.35rem;<?= $cur==='cargar_resultados.php'?'background:#1c2f48;color:#fff;box-shadow:0 2px 6px rgba(28,47,72,.15)':'color:#64748b' ?>">
        📝 Registrar Resultados
      </a>
    </div>

    <!-- FILTROS DE BÚSQUEDA RÁPIDA -->
    <div class="card" style="padding:1rem;margin-bottom:1.5rem;box-shadow: 0 4px 12px rgba(0,0,0,.03)">
      <form method="get" style="display:grid;grid-template-columns:repeat(auto-fit, minmax(180px, 1fr));gap:.75rem;align-items:end">
        
        <div>
          <label style="font-size:.65rem;font-weight:800;text-transform:uppercase;color:var(--navy);display:block;margin-bottom:.3rem">Liga / Torneo</label>
          <select name="liga" class="form-control" onchange="this.form.submit()">
            <?php foreach ($ligas as $l): ?>
              <option value="<?= $l['id'] ?>" <?= $l['id'] == $liga_id ? 'selected' : '' ?>><?= epl_h($l['nombre']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div>
          <label style="font-size:.65rem;font-weight:800;text-transform:uppercase;color:var(--navy);display:block;margin-bottom:.3rem">Jornada</label>
          <select name="jornada" class="form-control" onchange="this.form.submit()">
            <option value="">Todas</option>
            <?php foreach ($jornadas_disponibles as $jor): ?>
              <option value="<?= $jor ?>" <?= $f_jornada == $jor ? 'selected' : '' ?>>Jornada <?= $jor ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div>
          <label style="font-size:.65rem;font-weight:800;text-transform:uppercase;color:var(--navy);display:block;margin-bottom:.3rem">Nombre de Fecha</label>
          <select name="fecha" class="form-control" onchange="this.form.submit()">
            <option value="">Todas</option>
            <?php foreach ($fechas_disponibles as $fn): ?>
              <option value="<?= epl_h($fn) ?>" <?= $f_fecha === $fn ? 'selected' : '' ?>><?= epl_h($fn) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div style="grid-column: span 2">
          <label style="font-size:.65rem;font-weight:800;text-transform:uppercase;color:var(--navy);display:block;margin-bottom:.3rem">Buscar equipo o jugador</label>
          <div style="position:relative;display:flex;align-items:center">
            <input type="text" name="search" class="form-control" placeholder="Nombre equipo, apellido jugador..." value="<?= epl_h($f_search) ?>" style="padding-right:2.5rem">
            <button type="submit" style="position:absolute;right:8px;background:none;border:none;cursor:pointer;color:var(--navy)">🔍</button>
          </div>
        </div>

        <div>
          <a href="cargar_resultados.php?liga=<?= $liga_id ?>" class="btn btn-navy" style="justify-content:center;height:38px;box-sizing:border-box;font-size:.75rem;padding:0 1rem;background:var(--gray-100);color:var(--gray-700);border:1px solid var(--gray-200);border-radius:8px">✕ Limpiar</a>
        </div>

      </form>
    </div>

    <!-- LISTADO DE PARTIDOS PENDIENTES CON FORMULARIO INLINE -->
    <div style="display:flex;flex-direction:column;gap:1rem">
      <?php foreach ($partidos as $p):
        $es_reprog = $p['estado'] === 'reprogramado';
        $fecha_p = $p['fecha_programada'];
        $es_ph = $fecha_p && date('Y-m-d', strtotime($fecha_p)) === '2026-12-31';
        $fecha_lbl = (!$fecha_p || $es_ph) ? 'Sin fecha' : date('d/m/Y H:i', strtotime($fecha_p));
      ?>
      <div class="card" style="border:1px solid var(--gray-100);border-radius:18px;padding:1.25rem;box-shadow: 0 4px 16px rgba(0,0,0,.02)">
        
        <!-- Cabecera de partido -->
        <div style="display:flex;align-items:center;justify-content:between;flex-wrap:wrap;gap:.5rem;margin-bottom:1rem;border-bottom:1px solid var(--gray-100);padding-bottom:.75rem">
          <div style="display:flex;align-items:center;gap:.5rem">
            <span style="font-size:.65rem;font-weight:800;background:var(--navy);color:#fff;padding:.2rem .5rem;border-radius:6px;text-transform:uppercase">F.<?= $p['jornada'] ?? '—' ?></span>
            <?php if ($p['nombre_fecha']): ?>
              <span style="font-size:.72rem;font-weight:700;color:var(--gray-500)"><?= epl_h($p['nombre_fecha']) ?></span>
            <?php endif; ?>
            <?php if ($es_reprog): ?>
              <span style="font-size:.6rem;font-weight:800;color:#d97706;background:#fef3c7;border-radius:4px;padding:1px 5px;text-transform:uppercase">Reprog.</span>
            <?php endif; ?>
          </div>
          <div style="font-size:.75rem;color:var(--gray-600);font-weight:700;margin-left:auto">
            🗓 <?= $fecha_lbl ?> <?= $p['recinto_nombre'] ? '· 🏟️ ' . epl_h($p['recinto_nombre']) : '' ?>
          </div>
        </div>

        <form method="post">
          <input type="hidden" name="action" value="registrar_resultado_rapido">
          <input type="hidden" name="partido_id" value="<?= $p['id'] ?>">
          
          <!-- Filtros de persistencia de URL -->
          <input type="hidden" name="_f_liga" value="<?= $liga_id ?>">
          <input type="hidden" name="_f_jornada" value="<?= $f_jornada ?>">
          <input type="hidden" name="_f_fecha" value="<?= epl_h($f_fecha) ?>">
          <input type="hidden" name="_f_search" value="<?= epl_h($f_search) ?>">

          <div style="display:grid;grid-template-columns:1fr auto 1fr;align-items:center;gap:1.5rem;margin-bottom:1.25rem">
            
            <!-- Equipo Local -->
            <div style="text-align:right">
              <div style="font-family:var(--font-head);font-size:1.05rem;color:var(--navy);text-transform:uppercase;font-weight:900"><?= epl_h($p['local_nombre']) ?></div>
              <div style="font-size:.65rem;color:var(--gray-400);margin-top:.2rem">
                👥 <?= epl_h(trim(($p['jl1_n'] ? $p['jl1_n'].' '.$p['jl1_a'] : '') . ($p['jl2_n'] ? ' / '.$p['jl2_n'].' '.$p['jl2_a'] : ''))) ?>
              </div>
            </div>

            <!-- VS Badge -->
            <div style="background:var(--gold);color:var(--navy);font-family:var(--font-head);font-size:.8rem;padding:.3rem .75rem;border-radius:8px;font-weight:900;text-align:center;box-shadow:0 2px 6px rgba(201,167,98,.3)">VS</div>

            <!-- Equipo Visitante -->
            <div style="text-align:left">
              <div style="font-family:var(--font-head);font-size:1.05rem;color:var(--navy);text-transform:uppercase;font-weight:900"><?= epl_h($p['visitante_nombre']) ?></div>
              <div style="font-size:.65rem;color:var(--gray-400);margin-top:.2rem">
                👥 <?= epl_h(trim(($p['jv1_n'] ? $p['jv1_n'].' '.$p['jv1_a'] : '') . ($p['jv2_n'] ? ' / '.$p['jv2_n'].' '.$p['jv2_a'] : ''))) ?>
              </div>
            </div>

          </div>

          <!-- Campos de sets -->
          <div style="background:#f8fafc;border-radius:14px;padding:1rem;display:flex;flex-direction:column;gap:.75rem;margin-bottom:1rem">
            
            <?php for ($s = 1; $s <= 3; $s++): ?>
            <div class="score-input-row" style="display:flex;align-items:center;justify-content:between;gap:1.5rem">
              <span style="font-size:.78rem;font-weight:800;color:var(--navy);min-width:60px">Set <?= $s ?> <?= $s===3 ? '<span style="font-size:.65rem;color:var(--gray-400);font-weight:600">(opc)</span>':'' ?></span>
              
              <div style="display:flex;align-items:center;gap:.5rem">
                <input type="number" name="s<?= $s ?>_l" id="s<?= $s ?>_l_<?= $p['id'] ?>" class="form-control" style="width:54px;text-align:center;font-family:var(--font-head);font-size:1.25rem;padding:.25rem" min="0" max="7" placeholder="0" oninput="clearChips(<?= $s ?>, <?= $p['id'] ?>)">
                <span style="color:var(--gray-300);font-weight:bold">—</span>
                <input type="number" name="s<?= $s ?>_v" id="s<?= $s ?>_v_<?= $p['id'] ?>" class="form-control" style="width:54px;text-align:center;font-family:var(--font-head);font-size:1.25rem;padding:.25rem" min="0" max="7" placeholder="0" oninput="clearChips(<?= $s ?>, <?= $p['id'] ?>)">
              </div>

              <!-- Chips rápidos -->
              <div id="chips_s<?= $s ?>_<?= $p['id'] ?>" style="display:flex;align-items:center;gap:.25rem;overflow-x:auto;scrollbar-width:none">
                <span style="font-size:.58rem;font-weight:800;color:var(--gray-400);text-transform:uppercase;margin-right:.25rem">L:</span>
                <?php foreach ([[6,0],[6,1],[6,2],[6,3],[6,4],[7,5],[7,6]] as [$a,$b]): ?>
                  <button type="button" class="quick-chip" onclick="setInlineChip(<?= $s ?>, <?= $a ?>, <?= $b ?>, <?= $p['id'] ?>, this)" style="background:var(--white);border:1px solid var(--gray-200);border-radius:12px;font-size:.65rem;padding:.15rem .45rem;cursor:pointer;font-family:var(--font-head);transition:all .15s;color:var(--gray-500)"><?= $a ?>-<?= $b ?></button>
                <?php endforeach; ?>
                
                <span style="color:var(--gray-200);margin:0 .2rem">|</span>
                
                <span style="font-size:.58rem;font-weight:800;color:var(--gray-400);text-transform:uppercase;margin-right:.25rem">V:</span>
                <?php foreach ([[0,6],[1,6],[2,6],[3,6],[4,6],[5,7],[6,7]] as [$a,$b]): ?>
                  <button type="button" class="quick-chip" onclick="setInlineChip(<?= $s ?>, <?= $a ?>, <?= $b ?>, <?= $p['id'] ?>, this)" style="background:var(--white);border:1px solid var(--gray-200);border-radius:12px;font-size:.65rem;padding:.15rem .45rem;cursor:pointer;font-family:var(--font-head);transition:all .15s;color:var(--gray-500)"><?= $a ?>-<?= $b ?></button>
                <?php endforeach; ?>
              </div>
            </div>
            <?php endfor; ?>

          </div>

          <!-- Fecha de partido jugado + registrar button -->
          <div style="display:flex;align-items:center;justify-content:between;gap:1.5rem;flex-wrap:wrap">
            <div style="display:flex;align-items:center;gap:.5rem">
              <label style="font-size:.72rem;font-weight:800;color:var(--navy)">Fecha jugado:</label>
              <input type="datetime-local" name="fecha_jugado" class="form-control" value="<?= $fecha_p && !$es_ph ? date('Y-m-d\TH:i', strtotime($fecha_p)) : date('Y-m-d\TH:i') ?>" style="max-width:200px;font-size:.78rem">
            </div>
            
            <button type="submit" class="btn btn-primary" style="padding:.6rem 1.5rem;border-radius:10px;font-weight:800;letter-spacing:.03em;text-transform:uppercase;font-size:.78rem;background:linear-gradient(135deg,var(--green),#059669);color:#fff;border:none;box-shadow:0 3px 8px rgba(16,185,129,.35)">
              💾 Registrar Marcador
            </button>
          </div>

        </form>

      </div>
      <?php endforeach; ?>

      <?php if (empty($partidos)): ?>
        <div class="card" style="padding:4rem 2rem;text-align:center;color:var(--gray-400)">
          <div style="font-size:3rem">🎾</div>
          <h3 style="font-family:var(--font-head);text-transform:uppercase;color:var(--navy);margin:.5rem 0">Sin partidos pendientes</h3>
          <p style="font-size:.9rem">No se encontraron partidos pendientes para ingresar resultados con los filtros elegidos.</p>
        </div>
      <?php endif; ?>
    </div>

  </main>
</div>

<style>
.quick-chip:hover {
  background: rgba(201,167,98,.12) !important;
  color: var(--navy) !important;
  border-color: var(--gold) !important;
}
.quick-chip.active {
  background: var(--navy) !important;
  color: var(--gold) !important;
  border-color: var(--navy) !important;
}
</style>

<script>
function setInlineChip(setNum, valL, valV, partidoId, btn) {
  document.getElementById('s' + setNum + '_l_' + partidoId).value = valL;
  document.getElementById('s' + setNum + '_v_' + partidoId).value = valV;
  
  // Desmarcar otros del mismo set
  const container = document.getElementById('chips_s' + setNum + '_' + partidoId);
  container.querySelectorAll('.quick-chip').forEach(c => c.classList.remove('active'));
  btn.classList.add('active');
}

function clearChips(setNum, partidoId) {
  const container = document.getElementById('chips_s' + setNum + '_' + partidoId);
  if (container) {
    container.querySelectorAll('.quick-chip').forEach(c => c.classList.remove('active'));
  }
}
</script>

<?php require_once '../includes/footer.php'; ?>
