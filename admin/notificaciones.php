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

// ── POST: enviar mensaje ───────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $dest_tipo  = $_POST['dest_tipo']  ?? 'todos';   // todos | liga | jugador
    $jugador_id = (int)($_POST['jugador_id'] ?? 0);
    $liga_dest  = (int)($_POST['liga_dest']  ?? 0);
    $tipo       = in_array($_POST['tipo'] ?? '', ['admin','mensaje','anuncio','recordatorio','liga']) ? $_POST['tipo'] : 'admin';
    $titulo     = trim($_POST['titulo']  ?? '');
    $mensaje    = trim($_POST['mensaje'] ?? '');
    $url        = trim($_POST['url']     ?? '') ?: epl_url('dashboard.php');

    if (!$titulo || !$mensaje) {
        $err = 'Completa título y mensaje.';
    } else {
        // Construir lista de destinatarios
        $destinatarios = [];

        if ($dest_tipo === 'jugador' && $jugador_id > 0) {
            $destinatarios = [$jugador_id];
        } elseif ($dest_tipo === 'liga' && $liga_dest > 0) {
            $st = $db->prepare("
                SELECT DISTINCT j.id
                FROM liga_equipos le
                JOIN equipos e ON e.id = le.equipo_id
                JOIN jugadores j ON j.id IN (e.jugador1_id, e.jugador2_id)
                WHERE le.liga_id = ? AND j.estado = 'activo'
            ");
            $st->execute([$liga_dest]);
            $destinatarios = array_column($st->fetchAll(PDO::FETCH_ASSOC), 'id');
        } else {
            // Todos los jugadores activos
            $st = $db->query("SELECT id FROM jugadores WHERE estado = 'activo'");
            $destinatarios = array_column($st->fetchAll(PDO::FETCH_ASSOC), 'id');
        }

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

// ── Datos ─────────────────────────────────────────────────────────────────────
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

// Historial últimos 30 mensajes enviados por admin
$historial = $db->query("
    SELECT n.tipo, n.titulo, n.mensaje, n.created_at,
           COUNT(*) OVER (PARTITION BY n.titulo, n.created_at) AS total_dest,
           j.nombre, j.apellido
    FROM notificaciones n
    JOIN jugadores j ON j.id = n.jugador_id
    WHERE n.tipo IN ('admin','mensaje','anuncio','recordatorio','liga')
    ORDER BY n.created_at DESC
    LIMIT 200
")->fetchAll(PDO::FETCH_ASSOC);

// Agrupar historial por título + fecha (mismo envío)
$historial_agrupado = [];
foreach ($historial as $h) {
    $key = $h['titulo'] . '|' . substr($h['created_at'], 0, 16);
    if (!isset($historial_agrupado[$key])) {
        $historial_agrupado[$key] = [
            'tipo'       => $h['tipo'],
            'titulo'     => $h['titulo'],
            'mensaje'    => $h['mensaje'],
            'created_at' => $h['created_at'],
            'count'      => 0,
        ];
    }
    $historial_agrupado[$key]['count']++;
}
$historial_agrupado = array_values($historial_agrupado);
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
.ms-stat   { display:flex;flex-direction:column;align-items:flex-start;gap:.2rem;padding:1rem 1.25rem;background:#f8fafc;border-radius:12px;border:1px solid #e2e8f0; }
.ms-stat-num { font-size:1.6rem;font-weight:900;color:#1c2f48;line-height:1; }
.ms-stat-lbl { font-size:.72rem;color:#64748b;font-weight:600;text-transform:uppercase;letter-spacing:.04em; }
.ms-table  { width:100%;border-collapse:collapse; }
.ms-table th { font-size:.7rem;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.05em;padding:.6rem .85rem;border-bottom:2px solid #f1f5f9;text-align:left;white-space:nowrap; }
.ms-table td { padding:.6rem .85rem;border-bottom:1px solid #f8fafc;font-size:.84rem;vertical-align:middle; }
.ms-table tr:hover td { background:#fafbff; }
.badge-push   { background:#dcfce7;color:#166534;font-size:.68rem;font-weight:800;border-radius:999px;padding:.18rem .6rem;display:inline-block; }
.badge-nopush { background:#f1f5f9;color:#94a3b8;font-size:.68rem;font-weight:700;border-radius:999px;padding:.18rem .6rem;display:inline-block; }
.badge-email  { background:#dbeafe;color:#1e40af;font-size:.68rem;font-weight:800;border-radius:999px;padding:.18rem .6rem;display:inline-block; }
.ms-dest-tabs { display:flex;gap:.5rem;margin-bottom:1rem;flex-wrap:wrap; }
.ms-dest-tab  { padding:.45rem 1rem;border-radius:8px;border:1.5px solid #e2e8f0;font-size:.8rem;font-weight:700;cursor:pointer;background:#fff;color:#475569;transition:all .18s; }
.ms-dest-tab.active { background:#1c2f48;border-color:#1c2f48;color:#C9A762; }
.ms-hist-row { display:flex;gap:.75rem;padding:.85rem 0;border-bottom:1px solid #f1f5f9;align-items:flex-start; }
.ms-hist-row:last-child { border-bottom:none; }
.ms-hist-icon { width:36px;height:36px;border-radius:10px;background:#f1f5f9;display:flex;align-items:center;justify-content:center;font-size:1rem;flex-shrink:0; }
.ms-char-count { font-size:.72rem;color:#94a3b8;text-align:right;margin-top:.25rem; }
.ms-search-wrap { position:relative;margin-bottom:.85rem; }
.ms-search-wrap svg { position:absolute;left:.75rem;top:50%;transform:translateY(-50%);color:#94a3b8; }
.ms-search-wrap input { padding-left:2.25rem; }
.ms-filter-tabs { display:flex;gap:.4rem;margin-bottom:.85rem;flex-wrap:wrap; }
.ms-filter-tab  { padding:.3rem .85rem;border-radius:20px;border:1.5px solid #e2e8f0;font-size:.75rem;font-weight:700;cursor:pointer;background:#fff;color:#64748b;transition:all .15s; }
.ms-filter-tab.active { background:#1c2f48;border-color:#1c2f48;color:#fff; }
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
      <div class="ms-stat">
        <div class="ms-stat-num"><?= $total_jugadores ?></div>
        <div class="ms-stat-lbl">Jugadores activos</div>
      </div>
      <div class="ms-stat">
        <div class="ms-stat-num" style="color:#166534"><?= $total_email ?></div>
        <div class="ms-stat-lbl">Con email</div>
      </div>
      <div class="ms-stat">
        <div class="ms-stat-num" style="color:#1e40af"><?= $total_push ?></div>
        <div class="ms-stat-lbl">Con push activo</div>
      </div>
      <div class="ms-stat">
        <div class="ms-stat-num" style="color:#C9A762"><?= count($historial_agrupado) ?></div>
        <div class="ms-stat-lbl">Envíos realizados</div>
      </div>
    </div>

    <!-- Formulario -->
    <div class="ms-card">
      <h2 style="font-family:'Anton',sans-serif;font-size:1rem;color:#1c2f48;margin:0 0 1.25rem;text-transform:uppercase;letter-spacing:.04em">✍️ Nuevo mensaje</h2>

      <form method="POST" id="formMsg">

        <!-- Destinatario -->
        <div style="margin-bottom:1rem">
          <span class="ms-label">Destinatario</span>
          <div class="ms-dest-tabs">
            <div class="ms-dest-tab active" onclick="setDest('todos',this)">📢 Todos (<?= $total_jugadores ?>)</div>
            <div class="ms-dest-tab" onclick="setDest('liga',this)">🏆 Por liga</div>
            <div class="ms-dest-tab" onclick="setDest('jugador',this)">👤 Un jugador</div>
          </div>
          <input type="hidden" name="dest_tipo" id="dest_tipo" value="todos">

          <div id="dest-liga" style="display:none">
            <select name="liga_dest" class="ms-input">
              <option value="">— Seleccionar liga —</option>
              <?php foreach ($ligas as $l): ?>
                <option value="<?= $l['id'] ?>"><?= epl_h($l['nombre']) ?> — <?= epl_h($l['temporada'] ?? '') ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div id="dest-jugador" style="display:none">
            <select name="jugador_id" class="ms-input">
              <option value="">— Seleccionar jugador —</option>
              <?php foreach ($jugadores as $j): ?>
                <option value="<?= $j['id'] ?>"><?= epl_h($j['nombre'] . ' ' . $j['apellido']) ?><?= $j['dispositivos'] > 0 ? ' 🔔' : '' ?><?= $j['email'] ? ' ✉️' : '' ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <!-- Tipo y URL -->
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
            <span class="ms-label">URL al tocar (opcional)</span>
            <input type="text" name="url" class="ms-input" value="<?= epl_h(epl_url('dashboard.php')) ?>" placeholder="<?= epl_h(epl_url('dashboard.php')) ?>">
          </div>
        </div>

        <!-- Título -->
        <div style="margin-bottom:1rem">
          <span class="ms-label">Título <span style="font-weight:400;text-transform:none;letter-spacing:0">(máx. 80 caracteres)</span></span>
          <input type="text" name="titulo" id="inputTitulo" class="ms-input" placeholder="ej: ⚡ Aviso importante para todos los jugadores" maxlength="80" required
                 oninput="updateCount('inputTitulo','cntTitulo',80)">
          <div class="ms-char-count"><span id="cntTitulo">0</span>/80</div>
        </div>

        <!-- Mensaje -->
        <div style="margin-bottom:1.25rem">
          <span class="ms-label">Mensaje <span style="font-weight:400;text-transform:none;letter-spacing:0">(máx. 300 caracteres)</span></span>
          <textarea name="mensaje" id="inputMensaje" class="ms-input" rows="4" placeholder="Escribe aquí el cuerpo del mensaje…" maxlength="300" required style="resize:vertical"
                    oninput="updateCount('inputMensaje','cntMensaje',300)"></textarea>
          <div class="ms-char-count"><span id="cntMensaje">0</span>/300</div>
        </div>

        <!-- Canales info -->
        <div style="background:#f8fafc;border-radius:10px;padding:.75rem 1rem;margin-bottom:1.25rem;display:flex;gap:1.25rem;flex-wrap:wrap;align-items:center">
          <span style="font-size:.78rem;color:#475569;font-weight:600">Se enviará por:</span>
          <span style="font-size:.78rem;color:#1e40af;font-weight:700">✉️ Email (a todos)</span>
          <span style="font-size:.78rem;color:#1e40af;font-weight:700">🔔 Push (a quienes tienen suscripción activa)</span>
          <span style="font-size:.78rem;color:#64748b">📋 Registro en historial</span>
        </div>

        <div style="display:flex;align-items:center;gap:1rem;flex-wrap:wrap">
          <button type="submit" class="ms-btn ms-btn-gold" id="btnEnviar">
            🚀 Enviar mensaje
          </button>
          <span id="enviando" style="display:none;font-size:.82rem;color:#64748b;font-weight:600">⏳ Enviando…</span>
        </div>
      </form>
    </div>

    <!-- Tabla jugadores -->
    <div class="ms-card">
      <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:.75rem;margin-bottom:1rem">
        <h2 style="font-family:'Anton',sans-serif;font-size:1rem;color:#1c2f48;margin:0;text-transform:uppercase;letter-spacing:.04em">👥 Jugadores</h2>
        <div style="font-size:.78rem;color:#94a3b8"><?= $total_jugadores ?> jugadores · <?= $total_email ?> con email · <?= $total_push ?> con push</div>
      </div>

      <!-- Búsqueda y filtros -->
      <div class="ms-search-wrap">
        <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
        <input type="text" class="ms-input" id="searchJugador" placeholder="Buscar jugador…" oninput="filtrarJugadores()">
      </div>
      <div class="ms-filter-tabs" id="filterTabs">
        <div class="ms-filter-tab active" data-filter="todos" onclick="setFilter('todos',this)">Todos (<?= $total_jugadores ?>)</div>
        <div class="ms-filter-tab" data-filter="push" onclick="setFilter('push',this)">🔔 Con push (<?= $total_push ?>)</div>
        <div class="ms-filter-tab" data-filter="nopush" onclick="setFilter('nopush',this)">Sin push (<?= $total_jugadores - $total_push ?>)</div>
        <div class="ms-filter-tab" data-filter="email" onclick="setFilter('email',this)">✉️ Con email (<?= $total_email ?>)</div>
      </div>

      <div style="overflow-x:auto">
        <table class="ms-table" id="tablaJugadores">
          <thead>
            <tr>
              <th>Jugador</th>
              <th>Email</th>
              <th>Push</th>
              <th>Dispositivos</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($jugadores as $j): ?>
            <tr data-nombre="<?= strtolower(epl_h($j['nombre'] . ' ' . $j['apellido'])) ?>"
                data-push="<?= $j['dispositivos'] > 0 ? '1' : '0' ?>"
                data-email="<?= !empty($j['email']) ? '1' : '0' ?>">
              <td style="font-weight:600;color:#1c2f48"><?= epl_h($j['nombre'] . ' ' . $j['apellido']) ?></td>
              <td style="font-size:.8rem;color:#64748b"><?= epl_h($j['email'] ?? '—') ?></td>
              <td>
                <?php if ($j['dispositivos'] > 0): ?>
                  <span class="badge-push">✓ Activo</span>
                <?php else: ?>
                  <span class="badge-nopush">Sin push</span>
                <?php endif; ?>
              </td>
              <td style="color:#64748b;font-size:.82rem"><?= $j['dispositivos'] > 0 ? $j['dispositivos'] . ' disp.' : '—' ?></td>
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

    <!-- Historial de envíos -->
    <?php if ($historial_agrupado): ?>
    <div class="ms-card">
      <h2 style="font-family:'Anton',sans-serif;font-size:1rem;color:#1c2f48;margin:0 0 1rem;text-transform:uppercase;letter-spacing:.04em">📋 Historial de envíos</h2>
      <?php
      $iconos_tipo = ['admin'=>'📢','anuncio'=>'📣','recordatorio'=>'⏰','mensaje'=>'💬','liga'=>'🏆'];
      foreach (array_slice($historial_agrupado, 0, 20) as $h):
        $ico = $iconos_tipo[$h['tipo']] ?? '🔔';
        $fecha = date('d/m/Y H:i', strtotime($h['created_at']));
      ?>
      <div class="ms-hist-row">
        <div class="ms-hist-icon"><?= $ico ?></div>
        <div style="flex:1;min-width:0">
          <div style="font-weight:700;font-size:.88rem;color:#1c2f48;margin-bottom:.15rem"><?= epl_h($h['titulo']) ?></div>
          <div style="font-size:.8rem;color:#475569;margin-bottom:.25rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= epl_h($h['mensaje']) ?></div>
          <div style="display:flex;gap:.75rem;align-items:center">
            <span style="font-size:.7rem;color:#94a3b8"><?= $fecha ?></span>
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
// ── Destinatario tabs ──────────────────────────────────────────────────────────
function setDest(tipo, el) {
  document.querySelectorAll('.ms-dest-tab').forEach(t => t.classList.remove('active'));
  el.classList.add('active');
  document.getElementById('dest_tipo').value = tipo;
  document.getElementById('dest-liga').style.display    = tipo === 'liga'    ? '' : 'none';
  document.getElementById('dest-jugador').style.display = tipo === 'jugador' ? '' : 'none';
}

// ── Preseleccionar jugador desde tabla ────────────────────────────────────────
function preselectJugador(id, nombre) {
  // activar tab jugador
  document.querySelectorAll('.ms-dest-tab').forEach(t => t.classList.remove('active'));
  document.querySelectorAll('.ms-dest-tab')[2].classList.add('active');
  document.getElementById('dest_tipo').value = 'jugador';
  document.getElementById('dest-liga').style.display    = 'none';
  document.getElementById('dest-jugador').style.display = '';
  document.querySelector('[name="jugador_id"]').value = id;
  document.getElementById('inputTitulo').focus();
  window.scrollTo({ top: 0, behavior: 'smooth' });
}

// ── Contador de caracteres ─────────────────────────────────────────────────────
function updateCount(inputId, countId, max) {
  const len = document.getElementById(inputId).value.length;
  const el  = document.getElementById(countId);
  el.textContent = len;
  el.style.color = len > max * 0.9 ? '#ef4444' : '#94a3b8';
}

// ── Filtro tabla jugadores ────────────────────────────────────────────────────
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
    const hasPush = row.getAttribute('data-push') === '1';
    const hasEmail= row.getAttribute('data-email') === '1';
    const matchQ  = !q || nombre.includes(q);
    const matchF  = activeFilter === 'todos'
      || (activeFilter === 'push'   && hasPush)
      || (activeFilter === 'nopush' && !hasPush)
      || (activeFilter === 'email'  && hasEmail);
    row.style.display = (matchQ && matchF) ? '' : 'none';
  });
}

// ── Submit spinner ─────────────────────────────────────────────────────────────
document.getElementById('formMsg').addEventListener('submit', function() {
  document.getElementById('btnEnviar').disabled = true;
  document.getElementById('btnEnviar').textContent = '⏳ Enviando…';
  document.getElementById('enviando').style.display = 'inline';
});
</script>

<?php require_once '../includes/footer.php'; ?>
