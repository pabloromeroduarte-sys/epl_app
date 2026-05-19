<?php
$page_title = 'Admin — Notificaciones Push';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../includes/web_push.php';
epl_require_admin();

$db  = epl_db();
$ok  = '';
$err = '';
$resultado_envio = null;

// ── POST: enviar notificación ──────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $jugador_id = (int)($_POST['jugador_id'] ?? 0);   // 0 = todos
    $titulo     = trim($_POST['titulo']     ?? '');
    $mensaje    = trim($_POST['mensaje']    ?? '');
    $url        = trim($_POST['url']        ?? '/dashboard.php') ?: '/dashboard.php';

    if (!$titulo || !$mensaje) {
        $err = 'Completa título y mensaje.';
    } else {
        $enviados  = 0;
        $fallidos  = 0;
        $sin_sub   = 0;

        if ($jugador_id === 0) {
            // Todos los jugadores con suscripción activa
            $st = $db->query("SELECT DISTINCT jugador_id FROM push_subscriptions");
            foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $jid) {
                $r = epl_push_jugador((int)$jid, $titulo, $mensaje, $url);
                if ($r > 0) $enviados++; else $fallidos++;
            }
            $ok = "Notificación enviada a {$enviados} jugador(es) suscritos.";
            if ($fallidos) $ok .= " ({$fallidos} sin entregas exitosas)";
        } else {
            // Jugador específico
            $jSt = $db->prepare("SELECT nombre, apellido FROM jugadores WHERE id=?");
            $jSt->execute([$jugador_id]);
            $jug = $jSt->fetch(PDO::FETCH_ASSOC);
            if (!$jug) {
                $err = 'Jugador no encontrado.';
            } else {
                $nombre_jug = trim($jug['nombre'] . ' ' . $jug['apellido']);
                // Contar suscripciones
                $cntSt = $db->prepare("SELECT COUNT(*) FROM push_subscriptions WHERE jugador_id=?");
                $cntSt->execute([$jugador_id]);
                $cnt = (int)$cntSt->fetchColumn();
                if ($cnt === 0) {
                    $err = "{$nombre_jug} no tiene dispositivos suscritos a push.";
                } else {
                    $enviados = epl_push_jugador($jugador_id, $titulo, $mensaje, $url);
                    if ($enviados > 0) {
                        $ok = "✅ Enviada a {$nombre_jug} en {$enviados} dispositivo(s).";
                    } else {
                        $err = "❌ No se pudo entregar a ningún dispositivo de {$nombre_jug}. Puede que la suscripción haya expirado.";
                    }
                }
            }
        }
    }
}

// ── Datos: jugadores con y sin suscripción ─────────────────────
$jugadores = $db->query("
    SELECT j.id, j.nombre, j.apellido,
           COUNT(ps.id) AS dispositivos
    FROM jugadores j
    LEFT JOIN push_subscriptions ps ON ps.jugador_id = j.id
    WHERE j.estado = 'activo'
    GROUP BY j.id
    ORDER BY dispositivos DESC, j.nombre ASC
")->fetchAll(PDO::FETCH_ASSOC);

$total_suscritos   = count(array_filter($jugadores, fn($j) => $j['dispositivos'] > 0));
$total_jugadores   = count($jugadores);
?>
<?php require_once '../includes/admin_header.php'; ?>

<style>
.pn-card   { background:#fff;border-radius:14px;box-shadow:0 2px 12px rgba(0,0,0,.07);padding:1.5rem;margin-bottom:1.25rem; }
.pn-label  { font-size:.75rem;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.05em;margin-bottom:.4rem; }
.pn-input  { width:100%;padding:.6rem .85rem;border:1.5px solid #e2e8f0;border-radius:8px;font-size:.9rem;font-family:inherit;transition:border-color .2s; }
.pn-input:focus { outline:none;border-color:#1c2f48; }
.pn-btn    { background:#1c2f48;color:#C9A762;border:none;border-radius:10px;padding:.7rem 1.5rem;font-size:.85rem;font-weight:800;cursor:pointer;text-transform:uppercase;letter-spacing:.05em;transition:background .2s; }
.pn-btn:hover { background:#0f1e30; }
.pn-btn-gold { background:#C9A762;color:#1c2f48; }
.pn-btn-gold:hover { background:#b8934f; }

.pn-stat   { display:flex;align-items:center;gap:.75rem;padding:.85rem 1rem;background:#f8fafc;border-radius:10px; }
.pn-stat-num { font-size:1.5rem;font-weight:900;color:#1c2f48;line-height:1; }
.pn-stat-lbl { font-size:.75rem;color:#64748b;font-weight:600; }

.pn-table  { width:100%;border-collapse:collapse; }
.pn-table th { font-size:.72rem;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.05em;padding:.6rem .85rem;border-bottom:2px solid #f1f5f9;text-align:left; }
.pn-table td { padding:.65rem .85rem;border-bottom:1px solid #f8fafc;font-size:.85rem;vertical-align:middle; }
.pn-table tr:hover td { background:#fafbff; }
.pn-table tr.no-sub td { opacity:.5; }

.badge-sub  { display:inline-flex;align-items:center;gap:.3rem;background:#dcfce7;color:#166534;font-size:.7rem;font-weight:800;border-radius:999px;padding:.2rem .65rem; }
.badge-nosub { background:#f1f5f9;color:#94a3b8;font-size:.7rem;font-weight:700;border-radius:999px;padding:.2rem .65rem; }

.send-row  { display:flex;align-items:center;gap:.75rem;flex-wrap:wrap; }
</style>

<div class="dash-layout">
  <?php include 'partials/sidebar.php'; ?>

  <main class="dash-main">
    <div class="dash-header">
      <h1 class="dash-title">🔔 Notificaciones Push</h1>
      <p style="color:#64748b;font-size:.85rem;margin:.25rem 0 0">Envía notificaciones a jugadores específicos o a todos</p>
    </div>

    <?php if ($ok): ?>
      <div style="background:#dcfce7;border:1px solid #86efac;border-radius:10px;padding:.9rem 1rem;margin-bottom:1rem;font-size:.88rem;color:#166534;font-weight:600"><?= htmlspecialchars($ok) ?></div>
    <?php endif; ?>
    <?php if ($err): ?>
      <div style="background:#fee2e2;border:1px solid #fca5a5;border-radius:10px;padding:.9rem 1rem;margin-bottom:1rem;font-size:.88rem;color:#991b1b;font-weight:600"><?= htmlspecialchars($err) ?></div>
    <?php endif; ?>

    <!-- Stats -->
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:.85rem;margin-bottom:1.25rem">
      <div class="pn-stat">
        <div>
          <div class="pn-stat-num"><?= $total_suscritos ?></div>
          <div class="pn-stat-lbl">Jugadores suscritos</div>
        </div>
      </div>
      <div class="pn-stat">
        <div>
          <div class="pn-stat-num"><?= $total_jugadores - $total_suscritos ?></div>
          <div class="pn-stat-lbl">Sin suscripción</div>
        </div>
      </div>
      <div class="pn-stat">
        <div>
          <div class="pn-stat-num"><?= $db->query("SELECT COUNT(*) FROM push_subscriptions")->fetchColumn() ?></div>
          <div class="pn-stat-lbl">Dispositivos totales</div>
        </div>
      </div>
    </div>

    <!-- Formulario envío -->
    <div class="pn-card">
      <h2 style="font-family:'Anton',sans-serif;font-size:1rem;color:#1c2f48;margin:0 0 1.25rem;text-transform:uppercase;letter-spacing:.04em">Enviar notificación</h2>
      <form method="POST" id="formPush">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;margin-bottom:1rem">
          <div>
            <div class="pn-label">Destinatario</div>
            <select name="jugador_id" id="selectJugador" class="pn-input" required>
              <option value="0">📢 Todos los suscritos (<?= $total_suscritos ?>)</option>
              <optgroup label="Jugadores suscritos">
                <?php foreach ($jugadores as $j): ?>
                  <?php if ($j['dispositivos'] > 0): ?>
                    <option value="<?= $j['id'] ?>"><?= htmlspecialchars($j['nombre'] . ' ' . $j['apellido']) ?> (<?= $j['dispositivos'] ?> disp.)</option>
                  <?php endif; ?>
                <?php endforeach; ?>
              </optgroup>
            </select>
          </div>
          <div>
            <div class="pn-label">URL al tocar (opcional)</div>
            <input type="text" name="url" class="pn-input" value="/dashboard.php" placeholder="/dashboard.php">
          </div>
        </div>
        <div style="margin-bottom:1rem">
          <div class="pn-label">Título</div>
          <input type="text" name="titulo" class="pn-input" placeholder="ej: ⚡ Aviso importante" maxlength="80" required>
        </div>
        <div style="margin-bottom:1.25rem">
          <div class="pn-label">Mensaje</div>
          <textarea name="mensaje" class="pn-input" rows="3" placeholder="Texto del cuerpo de la notificación..." maxlength="200" required style="resize:vertical"></textarea>
        </div>
        <div class="send-row">
          <button type="submit" class="pn-btn pn-btn-gold" id="btnEnviar">
            <span id="btnTxt">🚀 Enviar notificación</span>
          </button>
          <span id="enviando" style="display:none;font-size:.82rem;color:#64748b">Enviando…</span>
          <span style="font-size:.78rem;color:#94a3b8">Solo llega a quienes tienen push activado en su dispositivo</span>
        </div>
      </form>
    </div>

    <!-- Tabla jugadores -->
    <div class="pn-card">
      <h2 style="font-family:'Anton',sans-serif;font-size:1rem;color:#1c2f48;margin:0 0 1rem;text-transform:uppercase;letter-spacing:.04em">Estado por jugador</h2>
      <div style="overflow-x:auto">
        <table class="pn-table">
          <thead>
            <tr>
              <th>Jugador</th>
              <th>Push</th>
              <th>Dispositivos</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($jugadores as $j): ?>
            <tr class="<?= $j['dispositivos'] == 0 ? 'no-sub' : '' ?>">
              <td style="font-weight:600;color:#1c2f48"><?= htmlspecialchars($j['nombre'] . ' ' . $j['apellido']) ?></td>
              <td>
                <?php if ($j['dispositivos'] > 0): ?>
                  <span class="badge-sub">✓ Activo</span>
                <?php else: ?>
                  <span class="badge-nosub">Sin suscripción</span>
                <?php endif; ?>
              </td>
              <td><?= $j['dispositivos'] > 0 ? $j['dispositivos'] . ' dispositivo' . ($j['dispositivos'] > 1 ? 's' : '') : '—' ?></td>
              <td>
                <?php if ($j['dispositivos'] > 0): ?>
                  <button
                    type="button"
                    class="pn-btn"
                    style="padding:.35rem .75rem;font-size:.72rem"
                    onclick="preselect(<?= $j['id'] ?>, <?= htmlspecialchars(json_encode($j['nombre'] . ' ' . $j['apellido']), ENT_QUOTES) ?>)"
                  >Enviar</button>
                <?php endif; ?>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

  </main>
</div>

<script>
function preselect(id, nombre) {
  document.getElementById('selectJugador').value = id;
  document.querySelector('[name="titulo"]').placeholder  = 'Notificación para ' + nombre;
  document.querySelector('[name="mensaje"]').placeholder = 'Mensaje para ' + nombre + '…';
  document.querySelector('[name="titulo"]').focus();
  window.scrollTo({ top: 0, behavior: 'smooth' });
}
document.getElementById('formPush').addEventListener('submit', function () {
  document.getElementById('btnEnviar').disabled = true;
  document.getElementById('btnTxt').textContent = '⏳ Enviando…';
  document.getElementById('enviando').style.display = 'inline';
});
</script>

<?php require_once '../includes/admin_footer.php'; ?>
