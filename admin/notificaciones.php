<?php
$page_title = 'Admin — Mensajería';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../includes/mail.php';
epl_require_admin();

$db     = epl_db();
$_flash = epl_flash_get();
$ok     = ($_flash && $_flash['tipo']==='ok') ? $_flash['msg'] : '';
$err    = '';

// ── Destinos predefinidos ─────────────────────────────────────────────────────
$destinos_url = [
    epl_url('dashboard.php')          => '🏠 Dashboard',
    epl_url('ingresar_resultado.php') => '⚽ Ingresar resultado',
    epl_url('reprogramar.php')        => '📅 Reprogramar partido',
    epl_url('mis_torneos.php')        => '🏆 Mis torneos',
    epl_url('clasificacion.php')      => '📊 Clasificación',
    epl_url('notificaciones.php')     => '🔔 Notificaciones',
];

// ── Calcular grupos de destinatarios ─────────────────────────────────────────
// Todos activos
$todos_ids = array_column(
    $db->query("SELECT id FROM jugadores WHERE estado='activo'")->fetchAll(PDO::FETCH_ASSOC),
    'id'
);

// Por liga: se calcula al enviar

// Jugadores con partidos pendientes (aún no jugados)
$pendientes_ids = array_column($db->query("
    SELECT DISTINCT j.id
    FROM partidos p
    JOIN equipos el ON el.id = p.equipo_local_id
    JOIN equipos ev ON ev.id = p.equipo_visitante_id
    JOIN jugadores j ON j.id IN (el.jugador1_id, el.jugador2_id, ev.jugador1_id, ev.jugador2_id)
    WHERE p.estado IN ('pendiente','reprogramado') AND j.estado = 'activo'
")->fetchAll(PDO::FETCH_ASSOC), 'id');

// Jugadores con partidos reprogramados — mapa id → cantidad
$reprogs_raw = $db->query("
    SELECT j.id, j.nombre, j.apellido, COUNT(DISTINCT p.id) AS total
    FROM jugadores j
    JOIN equipos e  ON j.id = e.jugador1_id OR j.id = e.jugador2_id
    JOIN partidos p ON p.equipo_local_id = e.id OR p.equipo_visitante_id = e.id
    WHERE p.estado = 'reprogramado' AND j.estado = 'activo'
    GROUP BY j.id
    ORDER BY total DESC, j.nombre ASC
")->fetchAll(PDO::FETCH_ASSOC);

// Mapa id → count (para JS) y sets por umbral
$reprogs_mapa = [];   // id => total
foreach ($reprogs_raw as $r) {
    $reprogs_mapa[(int)$r['id']] = (int)$r['total'];
}
// Pre-calcular conteos por umbral 1..5
$reprogs_por_umbral = [];
for ($u = 1; $u <= 5; $u++) {
    $reprogs_por_umbral[$u] = count(array_filter($reprogs_mapa, fn($c) => $c >= $u));
}

// Jugadores sin push activo
$sin_push_ids = array_column($db->query("
    SELECT j.id FROM jugadores j
    LEFT JOIN push_subscriptions ps ON ps.jugador_id = j.id
    WHERE j.estado = 'activo'
    GROUP BY j.id
    HAVING COUNT(ps.id) = 0
")->fetchAll(PDO::FETCH_ASSOC), 'id');

// Jugadores con partidos reprogramados SIN fecha asignada
$sin_fecha_ids = array_column($db->query("
    SELECT DISTINCT j.id
    FROM partidos p
    JOIN equipos el ON el.id = p.equipo_local_id
    JOIN equipos ev ON ev.id = p.equipo_visitante_id
    JOIN jugadores j ON j.id IN (el.jugador1_id, el.jugador2_id, ev.jugador1_id, ev.jugador2_id)
    WHERE p.estado = 'reprogramado'
      AND (p.fecha_programada IS NULL OR p.fecha_programada = '')
      AND j.estado = 'activo'
")->fetchAll(PDO::FETCH_ASSOC), 'id');

// ── POST: enviar mensaje ───────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $dest_tipo    = $_POST['dest_tipo']    ?? 'todos';
    $jugador_id   = (int)($_POST['jugador_id']   ?? 0);
    $liga_dest    = (int)($_POST['liga_dest']    ?? 0);
    $reprogs_min  = max(1, min(5, (int)($_POST['reprogs_min'] ?? 1)));
    $tipo         = in_array($_POST['tipo'] ?? '', ['admin','mensaje','anuncio','recordatorio','liga']) ? $_POST['tipo'] : 'admin';
    $titulo       = trim($_POST['titulo']  ?? '');
    $mensaje      = trim($_POST['mensaje'] ?? '');
    $url_sel      = trim($_POST['url_sel'] ?? '');
    $url_custom   = trim($_POST['url_custom'] ?? '');
    $url          = ($url_sel === 'custom' ? $url_custom : $url_sel) ?: epl_url('dashboard.php');

    if (!$titulo || !$mensaje) {
        $err = 'Completa título y mensaje.';
    } else {
        $destinatarios = match($dest_tipo) {
            'jugador'      => $jugador_id > 0 ? [$jugador_id] : [],
            'liga'         => (function() use ($db, $liga_dest) {
                if (!$liga_dest) return [];
                $st = $db->prepare("
                    SELECT DISTINCT j.id
                    FROM liga_equipos le
                    JOIN equipos e ON e.id = le.equipo_id
                    JOIN jugadores j ON j.id IN (e.jugador1_id, e.jugador2_id)
                    WHERE le.liga_id = ? AND j.estado = 'activo'
                ");
                $st->execute([$liga_dest]);
                return array_column($st->fetchAll(PDO::FETCH_ASSOC), 'id');
            })(),
            'pendientes'   => $pendientes_ids,
            'reprogramados'=> array_keys(array_filter($reprogs_mapa, fn($c) => $c >= $reprogs_min)),
            'sin_push'     => $sin_push_ids,
            'sin_fecha'    => $sin_fecha_ids,
            default        => $todos_ids,
        };

        if (empty($destinatarios)) {
            $err = 'No hay destinatarios para ese filtro.';
        } else {
            foreach ($destinatarios as $jid) {
                epl_notif_crear((int)$jid, $tipo, $titulo, $mensaje, $url, false);
            }
            epl_redirect_ok('✅ Mensaje enviado a ' . count($destinatarios) . ' jugador(es) — push + email encolado.');
        }
    }
}

// ── Datos tabla ───────────────────────────────────────────────────────────────
$jugadores = $db->query("
    SELECT j.id, j.nombre, j.apellido, j.email,
           COUNT(ps.id) AS dispositivos
    FROM jugadores j
    LEFT JOIN push_subscriptions ps ON ps.jugador_id = j.id
    WHERE j.estado = 'activo'
    GROUP BY j.id
    ORDER BY j.nombre ASC
")->fetchAll(PDO::FETCH_ASSOC);

$ligas = $db->query("SELECT id, nombre, temporada FROM ligas ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);

$total_jugadores = count($jugadores);
$total_push      = count(array_filter($jugadores, fn($j) => $j['dispositivos'] > 0));
$total_email     = count(array_filter($jugadores, fn($j) => !empty($j['email'])));

// Historial últimos envíos admin
$historial = $db->query("
    SELECT n.tipo, n.titulo, n.mensaje, n.created_at
    FROM notificaciones n
    WHERE n.tipo IN ('admin','mensaje','anuncio','recordatorio','liga')
    ORDER BY n.created_at DESC
    LIMIT 200
")->fetchAll(PDO::FETCH_ASSOC);

$historial_agrupado = [];
foreach ($historial as $h) {
    $key = $h['titulo'] . '|' . substr($h['created_at'], 0, 16);
    if (!isset($historial_agrupado[$key])) {
        $historial_agrupado[$key] = ['tipo'=>$h['tipo'],'titulo'=>$h['titulo'],'mensaje'=>$h['mensaje'],'created_at'=>$h['created_at'],'count'=>0];
    }
    $historial_agrupado[$key]['count']++;
}
$historial_agrupado = array_values($historial_agrupado);

// Datos para JS
$conteos_js        = json_encode([
    'todos'      => count($todos_ids),
    'pendientes' => count($pendientes_ids),
    'sin_push'   => count($sin_push_ids),
    'sin_fecha'  => count($sin_fecha_ids),
]);
$reprogs_js        = json_encode($reprogs_mapa);
$reprogs_umbral_js = json_encode($reprogs_por_umbral);

// Mapa completo jugadores para el popup (id => {nombre, email, push, reprogs})
$jugadores_map_js = json_encode(array_column(
    array_map(fn($j) => [
        'id'      => (int)$j['id'],
        'nombre'  => $j['nombre'] . ' ' . $j['apellido'],
        'email'   => !empty($j['email']),
        'push'    => (int)$j['dispositivos'] > 0,
        'reprogs' => $reprogs_mapa[(int)$j['id']] ?? 0,
    ], $jugadores),
    null, 'id'
));

// IDs por grupo para popup
$grupos_ids_js = json_encode([
    'todos'      => $todos_ids,
    'pendientes' => $pendientes_ids,
    'sin_push'   => $sin_push_ids,
    'sin_fecha'  => $sin_fecha_ids,
]);
?>
<?php require_once '../includes/header.php'; ?>

<style>
.ms-card   { background:#fff;border-radius:14px;box-shadow:0 2px 12px rgba(0,0,0,.07);padding:1.5rem;margin-bottom:1.25rem; }
.ms-label  { font-size:.73rem;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.05em;margin-bottom:.4rem;display:block; }
.ms-input  { width:100%;padding:.6rem .85rem;border:1.5px solid #e2e8f0;border-radius:8px;font-size:.9rem;font-family:inherit;transition:border-color .2s;box-sizing:border-box; }
.ms-input:focus { outline:none;border-color:#1c2f48; }
.ms-btn    { background:#1c2f48;color:#C9A762;border:none;border-radius:10px;padding:.75rem 1.75rem;font-size:.85rem;font-weight:800;cursor:pointer;text-transform:uppercase;letter-spacing:.05em;transition:background .2s;display:inline-flex;align-items:center;gap:.5rem; }
.ms-btn:hover { background:#0f1e30; }
.ms-btn-gold { background:#C9A762;color:#1c2f48; }
.ms-btn-gold:hover { background:#b8934f; }

.ms-stat   { display:flex;flex-direction:column;gap:.2rem;padding:1rem 1.25rem;background:#f8fafc;border-radius:12px;border:1px solid #e2e8f0; }
.ms-stat-num { font-size:1.6rem;font-weight:900;color:#1c2f48;line-height:1; }
.ms-stat-lbl { font-size:.72rem;color:#64748b;font-weight:600;text-transform:uppercase;letter-spacing:.04em; }

.ms-dest-grid { display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:.5rem;margin-bottom:1rem; }
.ms-dest-tab  {
  padding:.65rem 1rem;border-radius:10px;border:1.5px solid #e2e8f0;
  font-size:.78rem;font-weight:700;cursor:pointer;background:#fff;color:#475569;
  transition:all .18s;display:flex;flex-direction:column;gap:.2rem;text-align:left;
}
.ms-dest-tab:hover { border-color:#1c2f48;color:#1c2f48; }
.ms-dest-tab.active { background:#1c2f48;border-color:#1c2f48;color:#C9A762; }
.ms-dest-tab .dest-count { font-size:1.1rem;font-weight:900;line-height:1; }
.ms-dest-tab.active .dest-count { color:#fff; }
.ms-dest-tab .dest-lbl { font-size:.68rem;opacity:.75; }

.ms-table  { width:100%;border-collapse:collapse; }
.ms-table th { font-size:.7rem;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.05em;padding:.6rem .85rem;border-bottom:2px solid #f1f5f9;text-align:left;white-space:nowrap; }
.ms-table td { padding:.6rem .85rem;border-bottom:1px solid #f8fafc;font-size:.84rem;vertical-align:middle; }
.ms-table tr:hover td { background:#fafbff; }
.badge-push   { background:#dcfce7;color:#166534;font-size:.68rem;font-weight:800;border-radius:999px;padding:.18rem .6rem;display:inline-block; }
.badge-nopush { background:#f1f5f9;color:#94a3b8;font-size:.68rem;font-weight:700;border-radius:999px;padding:.18rem .6rem;display:inline-block; }
.ms-hist-row { display:flex;gap:.75rem;padding:.85rem 0;border-bottom:1px solid #f1f5f9;align-items:flex-start; }
.ms-hist-row:last-child { border-bottom:none; }
.ms-hist-icon { width:36px;height:36px;border-radius:10px;background:#f1f5f9;display:flex;align-items:center;justify-content:center;font-size:1rem;flex-shrink:0; }
.ms-char-count { font-size:.72rem;color:#94a3b8;text-align:right;margin-top:.25rem; }
.ms-filter-tabs { display:flex;gap:.4rem;margin-bottom:.85rem;flex-wrap:wrap; }
.ms-filter-tab  { padding:.3rem .85rem;border-radius:20px;border:1.5px solid #e2e8f0;font-size:.75rem;font-weight:700;cursor:pointer;background:#fff;color:#64748b;transition:all .15s; }
.ms-filter-tab.active { background:#1c2f48;border-color:#1c2f48;color:#fff; }
.ms-search-wrap { position:relative;margin-bottom:.85rem; }
.ms-search-wrap svg { position:absolute;left:.75rem;top:50%;transform:translateY(-50%);color:#94a3b8; }
.ms-search-wrap input { padding-left:2.25rem; }

/* Botones umbral reprogramaciones */
.reprogs-btn {
  padding:.35rem .75rem;border-radius:8px;border:1.5px solid #fcd34d;
  font-size:.82rem;font-weight:800;cursor:pointer;background:#fff;color:#92400e;
  transition:all .15s;font-family:inherit;
}
.reprogs-btn:hover { background:#fef3c7;border-color:#f59e0b; }
.reprogs-btn.active { background:#f59e0b;border-color:#f59e0b;color:#fff; }

/* Preview pill */
.ms-preview-pill {
  display:inline-flex;align-items:center;gap:.5rem;
  background:#f0fdf4;border:1.5px solid #86efac;border-radius:10px;
  padding:.5rem 1rem;font-size:.82rem;font-weight:700;color:#166534;
}
</style>

<div class="dash-layout">
  <?php include 'partials/sidebar.php'; ?>

  <main class="dash-main">
    <div class="dash-header">
      <h1 class="dash-title">💬 Mensajería</h1>
      <p style="color:#64748b;font-size:.85rem;margin:.25rem 0 0">Envía mensajes a jugadores — notificación push + email automáticamente</p>
    </div>

    <?php if ($ok): ?>
      <div style="background:#dcfce7;border:1px solid #86efac;border-radius:10px;padding:.9rem 1rem;margin-bottom:1rem;font-size:.88rem;color:#166534;font-weight:600"><?= epl_h($ok) ?></div>
    <?php endif; ?>
    <?php if ($err): ?>
      <div style="background:#fee2e2;border:1px solid #fca5a5;border-radius:10px;padding:.9rem 1rem;margin-bottom:1rem;font-size:.88rem;color:#991b1b;font-weight:600"><?= epl_h($err) ?></div>
    <?php endif; ?>

    <!-- Stats -->
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(130px,1fr));gap:.75rem;margin-bottom:1.25rem">
      <div class="ms-stat"><div class="ms-stat-num"><?= $total_jugadores ?></div><div class="ms-stat-lbl">Jugadores activos</div></div>
      <div class="ms-stat"><div class="ms-stat-num" style="color:#166534"><?= $total_email ?></div><div class="ms-stat-lbl">Con email</div></div>
      <div class="ms-stat"><div class="ms-stat-num" style="color:#1e40af"><?= $total_push ?></div><div class="ms-stat-lbl">Con push activo</div></div>
      <div class="ms-stat"><div class="ms-stat-num" style="color:#b45309"><?= count($reprogs_mapa) ?></div><div class="ms-stat-lbl">Con reprogramaciones</div></div>
    </div>

    <!-- Formulario -->
    <div class="ms-card">
      <h2 style="font-family:'Anton',sans-serif;font-size:1rem;color:#1c2f48;margin:0 0 1.25rem;text-transform:uppercase;letter-spacing:.04em">✍️ Nuevo mensaje</h2>

      <form method="POST" id="formMsg">

        <!-- Destinatario -->
        <div style="margin-bottom:1.25rem">
          <span class="ms-label">Destinatario — <span id="previewDest" style="color:#1c2f48;font-weight:800;font-size:.85rem;text-transform:none;letter-spacing:0"><?= $total_jugadores ?> jugadores recibirán este mensaje</span></span>
          <input type="hidden" name="dest_tipo" id="dest_tipo" value="todos">

          <div class="ms-dest-grid">
            <!-- Todos -->
            <button type="button" class="ms-dest-tab active" data-tipo="todos" onclick="setDest('todos',this)">
              <span class="dest-count"><?= $total_jugadores ?></span>
              <span>📢 Todos</span>
              <span class="dest-lbl">Todos los jugadores activos</span>
            </button>
            <!-- Por liga -->
            <button type="button" class="ms-dest-tab" data-tipo="liga" onclick="setDest('liga',this)">
              <span class="dest-count">🏆</span>
              <span>Por liga</span>
              <span class="dest-lbl">Jugadores de una liga</span>
            </button>
            <!-- Jugador específico -->
            <button type="button" class="ms-dest-tab" data-tipo="jugador" onclick="setDest('jugador',this)">
              <span class="dest-count">👤</span>
              <span>Un jugador</span>
              <span class="dest-lbl">Seleccionar uno</span>
            </button>
            <!-- Partidos pendientes -->
            <button type="button" class="ms-dest-tab" data-tipo="pendientes" onclick="setDest('pendientes',this)">
              <span class="dest-count"><?= count($pendientes_ids) ?></span>
              <span>⏳ Con partido pendiente</span>
              <span class="dest-lbl">Aún no jugaron</span>
            </button>
            <!-- Reprogramados -->
            <button type="button" class="ms-dest-tab" data-tipo="reprogramados" onclick="setDest('reprogramados',this)">
              <span class="dest-count" id="cntReprog" style="color:#f59e0b"><?= $reprogs_por_umbral[1] ?? 0 ?></span>
              <span>🔄 Reprogramados</span>
              <span class="dest-lbl">Con partidos reprogramados</span>
            </button>
            <!-- Sin push -->
            <button type="button" class="ms-dest-tab" data-tipo="sin_push" onclick="setDest('sin_push',this)">
              <span class="dest-count"><?= count($sin_push_ids) ?></span>
              <span>🔕 Sin push activo</span>
              <span class="dest-lbl">Solo recibirán email</span>
            </button>
            <!-- Reprogramados sin fecha -->
            <button type="button" class="ms-dest-tab" data-tipo="sin_fecha" onclick="setDest('sin_fecha',this)">
              <span class="dest-count" style="color:#dc2626"><?= count($sin_fecha_ids) ?></span>
              <span>📭 Reprog. sin fecha</span>
              <span class="dest-lbl">Aún sin nueva fecha</span>
            </button>
          </div>

          <!-- Sub-selectores condicionales -->
          <div id="dest-liga" style="display:none;margin-top:.5rem">
            <select name="liga_dest" class="ms-input" onchange="actualizarConteoLiga(this)">
              <option value="">— Seleccionar liga —</option>
              <?php foreach ($ligas as $l): ?>
                <option value="<?= $l['id'] ?>" data-count="?"><?= epl_h($l['nombre']) ?> — <?= epl_h($l['temporada'] ?? '') ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <!-- Selector umbral reprogramaciones -->
          <div id="dest-reprogramados" style="display:none;margin-top:.75rem">
            <div style="background:#fffbeb;border:1.5px solid #fcd34d;border-radius:10px;padding:.85rem 1rem">
              <div style="font-size:.75rem;font-weight:700;color:#92400e;margin-bottom:.6rem;text-transform:uppercase;letter-spacing:.04em">¿Cuántas reprogramaciones mínimo?</div>
              <div style="display:flex;gap:.4rem;flex-wrap:wrap;align-items:center">
                <?php for ($u = 1; $u <= 5; $u++): ?>
                <button type="button"
                        class="reprogs-btn <?= $u===1?'active':'' ?>"
                        data-umbral="<?= $u ?>"
                        onclick="setUmbral(<?= $u ?>, this)">
                  <?= $u ?>+ <span style="font-size:.65rem;opacity:.7">(<?= $reprogs_por_umbral[$u] ?? 0 ?>)</span>
                </button>
                <?php endfor; ?>
                <span style="font-size:.78rem;color:#92400e;margin-left:.5rem">→ <strong id="reprogs-preview"><?= $reprogs_por_umbral[1] ?? 0 ?></strong> jugador(es)</span>
              </div>
              <input type="hidden" name="reprogs_min" id="reprogs_min" value="1">
            </div>
          </div>

          <div id="dest-jugador" style="display:none;margin-top:.5rem">
            <select name="jugador_id" class="ms-input" onchange="document.getElementById('previewDest').textContent='1 jugador recibirá este mensaje'">
              <option value="">— Seleccionar jugador —</option>
              <?php foreach ($jugadores as $j): ?>
                <option value="<?= $j['id'] ?>"><?= epl_h($j['nombre'] . ' ' . $j['apellido']) ?><?= $j['dispositivos'] > 0 ? ' 🔔' : '' ?><?= $j['email'] ? ' ✉️' : '' ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <!-- Tipo y Destino URL -->
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;margin-bottom:1rem">
          <div>
            <span class="ms-label">Tipo de mensaje</span>
            <select name="tipo" class="ms-input">
              <option value="admin">📢 Comunicado oficial</option>
              <option value="anuncio">📣 Anuncio</option>
              <option value="recordatorio">⏰ Recordatorio</option>
              <option value="mensaje">💬 Mensaje general</option>
              <option value="liga">🏆 Novedad de liga</option>
            </select>
          </div>
          <div>
            <span class="ms-label">Al tocar la notificación, ir a…</span>
            <select name="url_sel" id="urlSel" class="ms-input" onchange="toggleUrlCustom(this.value)">
              <?php foreach ($destinos_url as $u => $label): ?>
                <option value="<?= epl_h($u) ?>"><?= epl_h($label) ?></option>
              <?php endforeach; ?>
              <option value="custom">✏️ URL personalizada…</option>
            </select>
            <div id="urlCustomWrap" style="display:none;margin-top:.4rem">
              <input type="text" name="url_custom" class="ms-input" placeholder="https://…">
            </div>
          </div>
        </div>

        <!-- Título -->
        <div style="margin-bottom:1rem">
          <span class="ms-label">Título <span style="font-weight:400;text-transform:none;letter-spacing:0">(máx. 80 caracteres)</span></span>
          <input type="text" name="titulo" id="inputTitulo" class="ms-input"
                 placeholder="ej: ⚡ Aviso importante para todos los jugadores"
                 maxlength="80" required
                 oninput="updateCount('inputTitulo','cntTitulo',80)">
          <div class="ms-char-count"><span id="cntTitulo">0</span>/80</div>
        </div>

        <!-- Mensaje -->
        <div style="margin-bottom:1.25rem">
          <span class="ms-label">Mensaje <span style="font-weight:400;text-transform:none;letter-spacing:0">(máx. 300 caracteres)</span></span>
          <textarea name="mensaje" id="inputMensaje" class="ms-input" rows="4"
                    placeholder="Escribe aquí el cuerpo del mensaje…"
                    maxlength="300" required style="resize:vertical"
                    oninput="updateCount('inputMensaje','cntMensaje',300)"></textarea>
          <div class="ms-char-count"><span id="cntMensaje">0</span>/300</div>
        </div>

        <!-- Canales + Enviar -->
        <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:.85rem">
          <div style="display:flex;gap:.75rem;flex-wrap:wrap;align-items:center">
            <span class="ms-preview-pill" id="pillDest">
              📤 <span id="pillCount"><?= $total_jugadores ?></span> destinatario(s)
            </span>
            <button type="button" onclick="verDestinatarios()"
                    style="font-size:.78rem;font-weight:700;color:#1c2f48;background:none;border:1.5px solid #e2e8f0;border-radius:8px;padding:.4rem .85rem;cursor:pointer;transition:all .15s"
                    onmouseover="this.style.borderColor='#1c2f48'" onmouseout="this.style.borderColor='#e2e8f0'">
              👁 Ver quiénes son
            </button>
            <span style="font-size:.75rem;color:#64748b">✉️ Email + 🔔 Push + 📋 Historial</span>
          </div>
          <button type="submit" class="ms-btn ms-btn-gold" id="btnEnviar">
            🚀 Enviar mensaje
          </button>
        </div>
      </form>
    </div>

    <!-- Tabla jugadores -->
    <div class="ms-card">
      <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:.75rem;margin-bottom:1rem">
        <h2 style="font-family:'Anton',sans-serif;font-size:1rem;color:#1c2f48;margin:0;text-transform:uppercase;letter-spacing:.04em">👥 Jugadores</h2>
        <div style="font-size:.78rem;color:#94a3b8"><?= $total_jugadores ?> activos · <?= $total_email ?> con email · <?= $total_push ?> con push</div>
      </div>
      <div class="ms-search-wrap">
        <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
        <input type="text" class="ms-input" id="searchJugador" placeholder="Buscar jugador…" oninput="filtrarJugadores()">
      </div>
      <div class="ms-filter-tabs">
        <div class="ms-filter-tab active" data-filter="todos"   onclick="setFilter('todos',this)">Todos (<?= $total_jugadores ?>)</div>
        <div class="ms-filter-tab"        data-filter="push"    onclick="setFilter('push',this)">🔔 Con push (<?= $total_push ?>)</div>
        <div class="ms-filter-tab"        data-filter="nopush"  onclick="setFilter('nopush',this)">Sin push (<?= $total_jugadores - $total_push ?>)</div>
        <div class="ms-filter-tab"        data-filter="email"   onclick="setFilter('email',this)">✉️ Con email (<?= $total_email ?>)</div>
      </div>
      <div style="overflow-x:auto">
        <table class="ms-table" id="tablaJugadores">
          <thead><tr><th>Jugador</th><th>Email</th><th>Push</th><th></th></tr></thead>
          <tbody>
            <?php foreach ($jugadores as $j): ?>
            <tr data-nombre="<?= strtolower($j['nombre'] . ' ' . $j['apellido']) ?>"
                data-push="<?= $j['dispositivos'] > 0 ? '1' : '0' ?>"
                data-email="<?= !empty($j['email']) ? '1' : '0' ?>">
              <td style="font-weight:600;color:#1c2f48"><?= epl_h($j['nombre'] . ' ' . $j['apellido']) ?></td>
              <td style="font-size:.8rem;color:#64748b"><?= epl_h($j['email'] ?? '—') ?></td>
              <td><?= $j['dispositivos'] > 0 ? '<span class="badge-push">✓ Activo</span>' : '<span class="badge-nopush">Sin push</span>' ?></td>
              <td>
                <button type="button" class="ms-btn" style="padding:.3rem .75rem;font-size:.7rem"
                        onclick="preselectJugador(<?= $j['id'] ?>, '<?= epl_h(addslashes($j['nombre'] . ' ' . $j['apellido'])) ?>')">
                  Enviar
                </button>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Historial -->
    <?php if ($historial_agrupado): ?>
    <div class="ms-card">
      <h2 style="font-family:'Anton',sans-serif;font-size:1rem;color:#1c2f48;margin:0 0 1rem;text-transform:uppercase;letter-spacing:.04em">📋 Historial de envíos</h2>
      <?php
      $ico_tipo = ['admin'=>'📢','anuncio'=>'📣','recordatorio'=>'⏰','mensaje'=>'💬','liga'=>'🏆'];
      foreach (array_slice($historial_agrupado, 0, 25) as $h):
        $ico = $ico_tipo[$h['tipo']] ?? '🔔';
      ?>
      <div class="ms-hist-row">
        <div class="ms-hist-icon"><?= $ico ?></div>
        <div style="flex:1;min-width:0">
          <div style="font-weight:700;font-size:.88rem;color:#1c2f48;margin-bottom:.15rem"><?= epl_h($h['titulo']) ?></div>
          <div style="font-size:.8rem;color:#475569;margin-bottom:.25rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= epl_h($h['mensaje']) ?></div>
          <div style="display:flex;gap:.75rem;align-items:center">
            <span style="font-size:.7rem;color:#94a3b8"><?= date('d/m/Y H:i', strtotime($h['created_at'])) ?></span>
            <span style="font-size:.7rem;background:#f1f5f9;color:#475569;border-radius:6px;padding:.1rem .5rem;font-weight:700"><?= $h['count'] ?> destinatario<?= $h['count'] !== 1 ? 's' : '' ?></span>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

  </main>
</div>

<script>
const _conteos        = <?= $conteos_js ?>;
const _reprogsMapa    = <?= $reprogs_js ?>;
const _regrogsUmbral  = <?= $reprogs_umbral_js ?>;

// ── Destinatario tabs ─────────────────────────────────────────────────────────
function setDest(tipo, el) {
  document.querySelectorAll('.ms-dest-tab').forEach(t => t.classList.remove('active'));
  el.classList.add('active');
  document.getElementById('dest_tipo').value = tipo;
  document.getElementById('dest-liga').style.display          = tipo === 'liga'          ? '' : 'none';
  document.getElementById('dest-jugador').style.display       = tipo === 'jugador'       ? '' : 'none';
  document.getElementById('dest-reprogramados').style.display = tipo === 'reprogramados' ? '' : 'none';

  const umbral = parseInt(document.getElementById('reprogs_min')?.value || 1);
  const cntReprogs = _regrogsUmbral[umbral] || 0;

  const textos = {
    todos:          _conteos.todos      + ' jugadores recibirán este mensaje',
    pendientes:     _conteos.pendientes + ' jugadores con partido pendiente',
    reprogramados:  cntReprogs          + ' jugadores con ' + umbral + '+ reprogramaciones',
    sin_push:       _conteos.sin_push   + ' jugadores (solo email, sin push)',
    sin_fecha:      _conteos.sin_fecha  + ' jugadores con reprogramación sin fecha',
    liga:           'Seleccioná una liga ↓',
    jugador:        '1 jugador recibirá este mensaje',
  };
  const cnt = {
    todos: _conteos.todos, pendientes: _conteos.pendientes,
    reprogramados: cntReprogs, sin_push: _conteos.sin_push,
    sin_fecha: _conteos.sin_fecha,
    liga: '?', jugador: 1
  };
  document.getElementById('previewDest').textContent = textos[tipo] || '';
  document.getElementById('pillCount').textContent   = cnt[tipo] ?? '?';
}

// ── Umbral de reprogramaciones ────────────────────────────────────────────────
function setUmbral(u, btn) {
  document.querySelectorAll('.reprogs-btn').forEach(b => b.classList.remove('active'));
  btn.classList.add('active');
  document.getElementById('reprogs_min').value = u;
  const cnt = _regrogsUmbral[u] || 0;
  document.getElementById('reprogs-preview').textContent = cnt;
  document.getElementById('cntReprog').textContent       = cnt;
  document.getElementById('previewDest').textContent     = cnt + ' jugadores con ' + u + '+ reprogramaciones';
  document.getElementById('pillCount').textContent       = cnt;
}

function actualizarConteoLiga(sel) {
  const txt = sel.options[sel.selectedIndex]?.text || 'esta liga';
  const nombre = txt.split('—')[0].trim();
  document.getElementById('previewDest').textContent = 'Jugadores de ' + nombre;
  document.getElementById('pillCount').textContent = '?';
}

// ── Preseleccionar jugador desde tabla ────────────────────────────────────────
function preselectJugador(id, nombre) {
  document.querySelectorAll('.ms-dest-tab').forEach(t => t.classList.remove('active'));
  document.querySelector('.ms-dest-tab[data-tipo="jugador"]').classList.add('active');
  document.getElementById('dest_tipo').value = 'jugador';
  document.getElementById('dest-liga').style.display    = 'none';
  document.getElementById('dest-jugador').style.display = '';
  document.querySelector('[name="jugador_id"]').value    = id;
  document.getElementById('previewDest').textContent = nombre + ' recibirá este mensaje';
  document.getElementById('pillCount').textContent   = 1;
  document.getElementById('inputTitulo').focus();
  window.scrollTo({ top: 0, behavior: 'smooth' });
}

// ── URL custom ────────────────────────────────────────────────────────────────
function toggleUrlCustom(val) {
  document.getElementById('urlCustomWrap').style.display = val === 'custom' ? '' : 'none';
}

// ── Contador de caracteres ────────────────────────────────────────────────────
function updateCount(inputId, countId, max) {
  const len = document.getElementById(inputId).value.length;
  const el  = document.getElementById(countId);
  el.textContent = len;
  el.style.color = len > max * 0.9 ? '#ef4444' : '#94a3b8';
}

// ── Filtro tabla ──────────────────────────────────────────────────────────────
let activeFilter = 'todos';
function setFilter(f, el) {
  activeFilter = f;
  document.querySelectorAll('.ms-filter-tab').forEach(t => t.classList.remove('active'));
  el.classList.add('active');
  filtrarJugadores();
}
function filtrarJugadores() {
  const q = (document.getElementById('searchJugador').value || '').toLowerCase();
  document.querySelectorAll('#tablaJugadores tbody tr').forEach(row => {
    const nombre  = row.getAttribute('data-nombre') || '';
    const hasPush = row.getAttribute('data-push')  === '1';
    const hasEmail= row.getAttribute('data-email') === '1';
    const matchQ  = !q || nombre.includes(q);
    const matchF  = activeFilter === 'todos'
      || (activeFilter === 'push'   && hasPush)
      || (activeFilter === 'nopush' && !hasPush)
      || (activeFilter === 'email'  && hasEmail);
    row.style.display = (matchQ && matchF) ? '' : 'none';
  });
}

// ── Submit ────────────────────────────────────────────────────────────────────
document.getElementById('formMsg').addEventListener('submit', function() {
  document.getElementById('btnEnviar').disabled = true;
  document.getElementById('btnEnviar').textContent = '⏳ Enviando…';
});

// ── Ver destinatarios popup ───────────────────────────────────────────────────
const _jugadoresMap = <?= $jugadores_map_js ?>;
const _gruposIds    = <?= $grupos_ids_js ?>;

function verDestinatarios() {
  const tipo   = document.getElementById('dest_tipo').value;
  const umbral = parseInt(document.getElementById('reprogs_min')?.value || 1);

  // Obtener lista de IDs según el tipo actual
  let ids = [];
  if (tipo === 'reprogramados') {
    ids = Object.keys(_reprogsMapa)
                .filter(id => _reprogsMapa[id] >= umbral)
                .map(Number);
  } else if (tipo === 'jugador') {
    const sel = document.querySelector('[name="jugador_id"]');
    if (sel?.value) ids = [parseInt(sel.value)];
  } else if (_gruposIds[tipo]) {
    ids = _gruposIds[tipo];
  } else {
    ids = Object.keys(_jugadoresMap).map(Number);
  }

  // Ordenar: reprogramados desc, luego nombre asc
  const lista = ids
    .map(id => _jugadoresMap[id])
    .filter(Boolean)
    .sort((a,b) => (b.reprogs - a.reprogs) || a.nombre.localeCompare(b.nombre));

  // Títulos por tipo
  const titulos = {
    todos:          '📢 Todos los jugadores activos',
    pendientes:     '⏳ Jugadores con partido pendiente',
    reprogramados:  `🔄 Con ${umbral}+ reprogramaciones`,
    sin_push:       '🔕 Sin push activo',
    sin_fecha:      '📭 Reprogramados sin fecha asignada',
    liga:           '🏆 Jugadores de la liga',
    jugador:        '👤 Jugador seleccionado',
  };

  const modal   = document.getElementById('modalDest');
  const titulo  = document.getElementById('modalDestTitulo');
  const cuerpo  = document.getElementById('modalDestBody');

  titulo.textContent = (titulos[tipo] || tipo) + ` — ${lista.length} jugador(es)`;

  if (lista.length === 0) {
    cuerpo.innerHTML = '<p style="text-align:center;padding:2rem;color:#94a3b8">Sin destinatarios para este filtro.</p>';
  } else {
    cuerpo.innerHTML = lista.map(j => `
      <div style="display:flex;align-items:center;gap:.75rem;padding:.6rem 0;border-bottom:1px solid #f1f5f9">
        <div style="width:36px;height:36px;background:#1c2f48;border-radius:50%;display:flex;align-items:center;justify-content:center;flex-shrink:0;font-size:.8rem;font-weight:800;color:#C9A762">
          ${j.nombre.trim().split(' ').map(p=>p[0]).slice(0,2).join('')}
        </div>
        <div style="flex:1;min-width:0">
          <div style="font-weight:700;font-size:.88rem;color:#1c2f48;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">${j.nombre}</div>
          <div style="display:flex;gap:.4rem;margin-top:.2rem;flex-wrap:wrap">
            ${j.email ? '<span style="font-size:.65rem;background:#dbeafe;color:#1e40af;border-radius:4px;padding:.1rem .4rem;font-weight:700">✉️ Email</span>' : ''}
            ${j.push  ? '<span style="font-size:.65rem;background:#dcfce7;color:#166534;border-radius:4px;padding:.1rem .4rem;font-weight:700">🔔 Push</span>' : ''}
          </div>
        </div>
        ${j.reprogs > 0 ? `<span style="font-size:.72rem;background:#fef3c7;color:#92400e;border-radius:6px;padding:.2rem .55rem;font-weight:800;flex-shrink:0">${j.reprogs} reprog.</span>` : ''}
      </div>`).join('');
  }

  modal.style.display = 'flex';
  document.body.style.overflow = 'hidden';
}

function cerrarModalDest() {
  document.getElementById('modalDest').style.display = 'none';
  document.body.style.overflow = '';
}
document.addEventListener('keydown', e => { if (e.key === 'Escape') cerrarModalDest(); });
</script>

<!-- Modal destinatarios -->
<div id="modalDest" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:99999;align-items:flex-start;justify-content:center;padding:1rem;overflow-y:auto"
     onclick="if(event.target===this)cerrarModalDest()">
  <div style="background:#fff;border-radius:16px;width:100%;max-width:460px;margin:2rem auto;overflow:hidden;box-shadow:0 20px 60px rgba(0,0,0,.25)">
    <!-- Header -->
    <div style="background:#1c2f48;padding:1rem 1.25rem;display:flex;justify-content:space-between;align-items:center">
      <div id="modalDestTitulo" style="font-family:'Anton',sans-serif;font-size:.95rem;color:#C9A762;text-transform:uppercase;letter-spacing:.04em"></div>
      <button onclick="cerrarModalDest()" style="background:none;border:none;color:rgba(255,255,255,.6);font-size:1.5rem;cursor:pointer;line-height:1;padding:0 .2rem">&times;</button>
    </div>
    <!-- Cuerpo -->
    <div id="modalDestBody" style="padding:.75rem 1.25rem;max-height:65vh;overflow-y:auto"></div>
    <!-- Footer -->
    <div style="padding:.85rem 1.25rem;border-top:1px solid #f1f5f9;text-align:right">
      <button onclick="cerrarModalDest()" class="ms-btn" style="padding:.5rem 1.25rem;font-size:.8rem">Cerrar</button>
    </div>
  </div>
</div>

<?php require_once '../includes/footer.php'; ?>
