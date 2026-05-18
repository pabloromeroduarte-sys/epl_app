<?php
$page_title = 'Automatizaciones de correo';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../includes/mail.php';
epl_require_admin();

$db = epl_db();

// ─── Crear tabla si no existe ─────────────────────────────────────────────────
$db->exec("CREATE TABLE IF NOT EXISTS email_automatizaciones (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    nombre      VARCHAR(150) NOT NULL,
    trigger_tipo ENUM('bienvenida','cumpleanos_jugador','cumpleanos_admin') NOT NULL,
    activo      TINYINT(1) NOT NULL DEFAULT 0,
    asunto      VARCHAR(255) NOT NULL DEFAULT '',
    cuerpo      LONGTEXT NOT NULL,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$ok  = '';
$err = '';
$editing = null; // automatización siendo editada

// ─── POST actions ─────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // Crear o actualizar
    if (in_array($action, ['crear', 'actualizar'], true)) {
        $nombre  = trim($_POST['nombre']       ?? '');
        $tipo    = $_POST['trigger_tipo']      ?? '';
        $asunto  = trim($_POST['asunto']       ?? '');
        $cuerpo  = trim($_POST['cuerpo']       ?? '');
        $activo  = !empty($_POST['activo']) ? 1 : 0;

        $tipos_validos = ['bienvenida', 'cumpleanos_jugador', 'cumpleanos_admin'];
        if (!$nombre) {
            $err = 'El nombre es obligatorio.';
        } elseif (!in_array($tipo, $tipos_validos, true)) {
            $err = 'Tipo de disparo inválido.';
        } else {
            // Si se activa, desactivar las demás del mismo tipo
            if ($activo) {
                $db->prepare("UPDATE email_automatizaciones SET activo=0 WHERE trigger_tipo=?")->execute([$tipo]);
            }

            if ($action === 'crear') {
                $db->prepare("INSERT INTO email_automatizaciones (nombre, trigger_tipo, activo, asunto, cuerpo) VALUES (?,?,?,?,?)")
                   ->execute([$nombre, $tipo, $activo, $asunto, $cuerpo]);
                $ok = 'Automatización creada.';
            } else {
                $id = (int)($_POST['id'] ?? 0);
                $db->prepare("UPDATE email_automatizaciones SET nombre=?, trigger_tipo=?, activo=?, asunto=?, cuerpo=?, updated_at=NOW() WHERE id=?")
                   ->execute([$nombre, $tipo, $activo, $asunto, $cuerpo, $id]);
                $ok = 'Automatización actualizada.';
            }
        }
    }

    // Toggle activo/inactivo
    if ($action === 'toggle') {
        $id     = (int)($_POST['id'] ?? 0);
        $st     = $db->prepare("SELECT * FROM email_automatizaciones WHERE id=?");
        $st->execute([$id]);
        $auto   = $st->fetch(PDO::FETCH_ASSOC);
        if ($auto) {
            $nuevo = $auto['activo'] ? 0 : 1;
            if ($nuevo) {
                // Desactivar las del mismo tipo
                $db->prepare("UPDATE email_automatizaciones SET activo=0 WHERE trigger_tipo=?")->execute([$auto['trigger_tipo']]);
            }
            $db->prepare("UPDATE email_automatizaciones SET activo=? WHERE id=?")->execute([$nuevo, $id]);
            $ok = $nuevo ? 'Automatización activada.' : 'Automatización desactivada.';
        }
    }

    // Eliminar
    if ($action === 'eliminar') {
        $id = (int)($_POST['id'] ?? 0);
        $db->prepare("DELETE FROM email_automatizaciones WHERE id=?")->execute([$id]);
        $ok = 'Automatización eliminada.';
    }

    // Probar
    if ($action === 'probar') {
        $id = (int)($_POST['id'] ?? 0);
        $st = $db->prepare("SELECT * FROM email_automatizaciones WHERE id=?");
        $st->execute([$id]);
        $auto = $st->fetch(PDO::FETCH_ASSOC);
        if ($auto) {
            require_once '../includes/mail_automations.php';
            $j = epl_jugador_actual();
            $vars = [
                'nombre'   => $j['nombre']   ?? 'Admin',
                'apellido' => $j['apellido'] ?? '',
                'email'    => $j['email']    ?? '',
            ];
            $asuntoFinal = epl_auto_render($auto['asunto'], $vars);
            $cuerpoFinal = epl_auto_render($auto['cuerpo'], $vars);
            $html = epl_mail_plantilla($asuntoFinal, $cuerpoFinal);
            $res  = epl_mail_enviar($j['email'] ?? '', $asuntoFinal . ' [PRUEBA]', $html);
            if ($res['ok']) {
                $ok = 'Correo de prueba enviado a ' . ($j['email'] ?? '') . '.';
            } else {
                $err = 'No se pudo enviar: ' . ($res['error'] ?? 'error desconocido');
            }
        }
    }

    // Si venía editando, mantener la fila abierta si hay error
    if ($err && in_array($action, ['crear', 'actualizar'], true) && $action === 'actualizar') {
        $id_err = (int)($_POST['id'] ?? 0);
        if ($id_err) {
            $st = $db->prepare("SELECT * FROM email_automatizaciones WHERE id=?");
            $st->execute([$id_err]);
            $editing = $st->fetch(PDO::FETCH_ASSOC);
        }
    }
}

// ─── Abrir editor desde GET ?editar=id ───────────────────────────────────────
if (!$editing && isset($_GET['editar'])) {
    $st = $db->prepare("SELECT * FROM email_automatizaciones WHERE id=?");
    $st->execute([(int)$_GET['editar']]);
    $editing = $st->fetch(PDO::FETCH_ASSOC) ?: null;
}

// ─── Lista de automatizaciones ────────────────────────────────────────────────
$lista = $db->query("SELECT * FROM email_automatizaciones ORDER BY trigger_tipo, activo DESC, nombre")->fetchAll(PDO::FETCH_ASSOC);

$tipos_label = [
    'bienvenida'         => ['label' => 'Bienvenida',              'icon' => '👋', 'desc' => 'Al crear cuenta'],
    'cumpleanos_jugador' => ['label' => 'Cumpleaños — jugador',    'icon' => '🎂', 'desc' => 'Al jugador ese día'],
    'cumpleanos_admin'   => ['label' => 'Cumpleaños — admins',     'icon' => '📬', 'desc' => 'A admins ese día'],
];

$smtp_ok = epl_smtp_habilitado();

// ─── Plantilla de email para preview JS ──────────────────────────────────────
$app_name      = htmlspecialchars(epl_config_get('smtp_from_name', 'Elite Padel League'), ENT_QUOTES);
?>
<?php require_once '../includes/header.php'; ?>

<style>
  .auto-card {
    background:#fff;border:1px solid var(--gray-200);border-radius:12px;
    display:flex;align-items:center;gap:1rem;padding:.85rem 1.1rem;
    transition:box-shadow .15s;
  }
  .auto-card:hover { box-shadow:0 2px 12px rgba(0,0,0,.07); }
  .auto-tipo-badge {
    font-size:.72rem;font-weight:700;padding:.25rem .65rem;border-radius:999px;
    background:#eff6ff;color:#1e40af;white-space:nowrap;
  }
  .auto-activo-badge {
    font-size:.72rem;font-weight:700;padding:.25rem .65rem;border-radius:999px;
    white-space:nowrap;
  }
  .badge-on  { background:#dcfce7;color:#15803d; }
  .badge-off { background:#f1f5f9;color:#64748b; }
  .editor-wrap {
    display:grid;grid-template-columns:1fr 1fr;gap:1.25rem;
  }
  @media(max-width:900px){ .editor-wrap{grid-template-columns:1fr} }
  .preview-frame {
    width:100%;height:520px;border:1px solid var(--gray-200);
    border-radius:10px;background:#f1f5f9;
  }
  .field-label {
    display:block;font-size:.82rem;font-weight:700;color:var(--navy);margin-bottom:.4rem;
  }
  .field-input {
    width:100%;padding:.6rem .8rem;border:1px solid var(--gray-200);border-radius:8px;
    font-size:.85rem;box-sizing:border-box;
  }
  .field-textarea {
    width:100%;padding:.65rem .8rem;border:1px solid var(--gray-200);border-radius:8px;
    font-size:.82rem;font-family:monospace;resize:vertical;box-sizing:border-box;
  }
  .btn-icon {
    display:inline-flex;align-items:center;gap:.35rem;
    padding:.4rem .85rem;border-radius:8px;font-size:.8rem;font-weight:700;
    border:none;cursor:pointer;text-decoration:none;transition:opacity .15s;
  }
  .btn-icon:hover { opacity:.8; }
  .vars-hint {
    background:#eff6ff;border:1px solid #bfdbfe;border-radius:8px;
    padding:.65rem .9rem;font-size:.78rem;color:#1e3a5f;line-height:1.6;
  }
</style>

<div class="dash-layout">
  <?php include __DIR__ . '/partials/sidebar.php'; ?>

  <main class="dash-main">
    <div class="dash-header" style="display:flex;align-items:flex-start;justify-content:space-between;flex-wrap:wrap;gap:.75rem">
      <div>
        <h1 class="dash-title">Automatizaciones de correo</h1>
        <p style="color:var(--gray-500);font-size:.88rem;margin:.35rem 0 0">Crea y gestiona los correos automáticos del sistema.</p>
      </div>
      <a href="automatizaciones.php?nuevo=1" class="btn btn-primary" style="white-space:nowrap">+ Nueva automatización</a>
    </div>

    <?php if ($ok): ?>
      <div class="alert alert-success"><?= epl_h($ok) ?></div>
    <?php endif; ?>
    <?php if ($err): ?>
      <div class="alert alert-error"><?= epl_h($err) ?></div>
    <?php endif; ?>

    <?php if (!$smtp_ok): ?>
    <div style="background:#fffbeb;border:1px solid #fde68a;border-radius:10px;padding:.85rem 1.2rem;margin-bottom:1.25rem;font-size:.85rem;color:#92400e;font-weight:600">
      ⚠ SMTP desactivado — las automatizaciones no enviarán hasta que lo configures en
      <a href="configuracion.php?tab=general" style="color:#1C2F48;text-decoration:underline">Configuración → Configuraciones</a>.
    </div>
    <?php endif; ?>

    <!-- ══════════════════════ LISTA ══════════════════════ -->
    <?php if (!empty($lista)): ?>
    <div style="display:flex;flex-direction:column;gap:.65rem;margin-bottom:1.75rem;max-width:860px">
      <?php foreach ($lista as $auto): ?>
      <?php $tipo = $tipos_label[$auto['trigger_tipo']] ?? ['label'=>$auto['trigger_tipo'],'icon'=>'✉','desc'=>'']; ?>
      <div class="auto-card">
        <!-- Icono -->
        <div style="font-size:1.4rem;flex-shrink:0"><?= $tipo['icon'] ?></div>

        <!-- Info -->
        <div style="flex:1;min-width:0">
          <div style="font-weight:700;font-size:.92rem;color:var(--navy);white-space:nowrap;overflow:hidden;text-overflow:ellipsis">
            <?= epl_h($auto['nombre']) ?>
          </div>
          <div style="display:flex;align-items:center;gap:.5rem;flex-wrap:wrap;margin-top:.2rem">
            <span class="auto-tipo-badge"><?= $tipo['label'] ?></span>
            <span style="font-size:.78rem;color:var(--gray-400)"><?= $tipo['desc'] ?></span>
          </div>
        </div>

        <!-- Estado toggle -->
        <form method="POST" style="flex-shrink:0">
          <input type="hidden" name="action" value="toggle">
          <input type="hidden" name="id" value="<?= $auto['id'] ?>">
          <button type="submit" class="auto-activo-badge <?= $auto['activo'] ? 'badge-on' : 'badge-off' ?>"
                  style="border:none;cursor:pointer;font-weight:700"
                  title="<?= $auto['activo'] ? 'Clic para desactivar' : 'Clic para activar' ?>">
            <?= $auto['activo'] ? '● Activa' : '○ Inactiva' ?>
          </button>
        </form>

        <!-- Acciones -->
        <div style="display:flex;gap:.4rem;flex-shrink:0">
          <!-- Probar -->
          <form method="POST">
            <input type="hidden" name="action" value="probar">
            <input type="hidden" name="id" value="<?= $auto['id'] ?>">
            <button type="submit" class="btn-icon" style="background:#f1f5f9;color:var(--navy)" title="Enviar prueba a tu correo">
              <svg style="width:14px;height:14px" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
              Probar
            </button>
          </form>
          <!-- Editar -->
          <a href="automatizaciones.php?editar=<?= $auto['id'] ?>" class="btn-icon" style="background:#eff6ff;color:#1e40af">
            <svg style="width:14px;height:14px" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
            Editar
          </a>
          <!-- Eliminar -->
          <form method="POST" onsubmit="return confirm('¿Eliminar esta automatización?')">
            <input type="hidden" name="action" value="eliminar">
            <input type="hidden" name="id" value="<?= $auto['id'] ?>">
            <button type="submit" class="btn-icon" style="background:#fef2f2;color:#dc2626">
              <svg style="width:14px;height:14px" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
            </button>
          </form>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php else: ?>
    <div style="background:#f8fafc;border:2px dashed var(--gray-200);border-radius:12px;text-align:center;padding:2.5rem 1rem;margin-bottom:1.75rem;max-width:640px">
      <div style="font-size:2rem;margin-bottom:.5rem">✉</div>
      <p style="color:var(--gray-500);font-size:.9rem;margin:0">Sin automatizaciones todavía. Crea la primera con el botón de arriba.</p>
    </div>
    <?php endif; ?>

    <!-- ══════════════════════ EDITOR ══════════════════════ -->
    <?php $show_form = isset($_GET['nuevo']) || $editing !== null; ?>
    <?php if ($show_form): ?>
    <div style="max-width:1200px;margin-bottom:2rem">

      <h2 style="font-size:1rem;font-weight:700;color:var(--navy);margin:0 0 1rem">
        <?= $editing ? 'Editar: ' . epl_h($editing['nombre']) : 'Nueva automatización' ?>
      </h2>

      <!-- Hint variables -->
      <div class="vars-hint" style="margin-bottom:1.25rem">
        <strong>Variables disponibles:</strong>
        <code style="margin:.2rem .4rem;background:#dbeafe;padding:.1rem .4rem;border-radius:4px">{{nombre}}</code>
        <code style="margin:.2rem .4rem;background:#dbeafe;padding:.1rem .4rem;border-radius:4px">{{apellido}}</code>
        <code style="margin:.2rem .4rem;background:#dbeafe;padding:.1rem .4rem;border-radius:4px">{{email}}</code>
        — Se reemplazan por los datos del jugador al enviarse.
      </div>

      <form method="POST" id="autoForm">
        <input type="hidden" name="action" value="<?= $editing ? 'actualizar' : 'crear' ?>">
        <?php if ($editing): ?>
          <input type="hidden" name="id" value="<?= (int)$editing['id'] ?>">
        <?php endif; ?>

        <!-- Fila superior: nombre + tipo + activo -->
        <div style="display:grid;grid-template-columns:1fr 220px auto;gap:.75rem;align-items:end;margin-bottom:1rem;flex-wrap:wrap">
          <div>
            <label class="field-label">Nombre de la automatización *</label>
            <input type="text" name="nombre" class="field-input" required placeholder="Ej: Bienvenida temporada 2026"
                   value="<?= epl_h($editing['nombre'] ?? '') ?>">
          </div>
          <div>
            <label class="field-label">Tipo de disparo *</label>
            <select name="trigger_tipo" class="field-input" id="tipoSel" required>
              <option value="">— Selecciona —</option>
              <?php foreach ($tipos_label as $k => $t): ?>
                <option value="<?= $k ?>" <?= ($editing['trigger_tipo'] ?? '') === $k ? 'selected' : '' ?>>
                  <?= $t['icon'] ?> <?= $t['label'] ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div style="padding-bottom:.05rem">
            <label class="field-label">Estado</label>
            <label style="display:flex;align-items:center;gap:.5rem;cursor:pointer;font-size:.88rem;font-weight:600;color:var(--navy);white-space:nowrap;height:38px">
              <input type="checkbox" name="activo" value="1" id="activoCheck"
                     <?= !empty($editing['activo']) ? 'checked' : '' ?>
                     style="width:18px;height:18px">
              Activa
            </label>
          </div>
        </div>

        <!-- Asunto -->
        <div style="margin-bottom:1rem">
          <label class="field-label">Asunto del correo *</label>
          <input type="text" name="asunto" id="asuntoInput" class="field-input" required
                 placeholder="Ej: ¡Bienvenido/a a Elite Padel League!"
                 value="<?= epl_h($editing['asunto'] ?? '') ?>"
                 oninput="actualizarPreview()">
        </div>

        <!-- Editor + Preview -->
        <div class="editor-wrap">
          <!-- Editor -->
          <div>
            <label class="field-label">Cuerpo del correo (HTML)</label>
            <textarea name="cuerpo" id="cuerpoInput" class="field-textarea" rows="18"
                      placeholder="<p>Hola <strong>{{nombre}}</strong>,</p>&#10;<p>Tu mensaje aquí...</p>"
                      oninput="actualizarPreview()"><?= epl_h($editing['cuerpo'] ?? '') ?></textarea>
            <p style="font-size:.73rem;color:var(--gray-400);margin:.3rem 0 0">HTML libre. El contenido va dentro de la plantilla corporativa (encabezado azul + footer).</p>
          </div>

          <!-- Preview -->
          <div>
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:.4rem">
              <label class="field-label" style="margin:0">Vista previa</label>
              <span style="font-size:.73rem;color:var(--gray-400)">Se actualiza al escribir</span>
            </div>
            <iframe id="previewFrame" class="preview-frame" title="Vista previa del correo"></iframe>
          </div>
        </div>

        <!-- Botones -->
        <div style="display:flex;flex-wrap:wrap;gap:.65rem;margin-top:1.25rem">
          <button type="submit" class="btn btn-primary">
            <?= $editing ? 'Guardar cambios' : 'Crear automatización' ?>
          </button>
          <a href="automatizaciones.php" class="btn" style="background:#f1f5f9;color:var(--navy);font-weight:700">Cancelar</a>
        </div>
      </form>
    </div>
    <?php endif; ?>

    <!-- Cron info (colapsable) -->
    <details style="max-width:720px;margin-bottom:2rem">
      <summary style="cursor:pointer;font-size:.85rem;font-weight:700;color:var(--navy);padding:.65rem .9rem;background:#f8fafc;border:1px solid var(--gray-200);border-radius:8px;list-style:none">
        ⏰ Cómo configurar el cron de cumpleaños en el servidor
      </summary>
      <div style="background:#f8fafc;border:1px solid var(--gray-200);border-top:none;border-radius:0 0 8px 8px;padding:1rem 1.1rem">
        <p style="font-size:.83rem;color:#334155;margin:0 0 .65rem">Agrega esta línea al crontab con <code>crontab -e</code> en el servidor:</p>
        <div style="background:#0f172a;border-radius:8px;padding:.75rem 1rem;font-family:monospace;font-size:.8rem;color:#a3e635;word-break:break-all;margin-bottom:.65rem">
          0 9 * * * php <?= epl_h(dirname(__DIR__)) ?>/cron/cron_cumpleanos.php
        </div>
        <p style="font-size:.75rem;color:var(--gray-400);margin:0">Se ejecuta a las 9:00 AM cada día. Ajusta la hora según necesites.</p>
      </div>
    </details>

  </main>
</div>

<script>
// ─── Preview en vivo ──────────────────────────────────────────────────────────
var APP_NAME = <?= json_encode($app_name) ?>;

function buildEmailHtml(asunto, cuerpoHtml) {
    // Replica exacta de epl_mail_plantilla()
    return '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8">'
        + '<style>body{margin:0;padding:0;background:#f1f5f9;font-family:Montserrat,Arial,sans-serif}</style>'
        + '</head><body>'
        + '<table width="100%" cellpadding="0" cellspacing="0" style="background:#f1f5f9;padding:24px 12px"><tr><td align="center">'
        + '<table width="100%" style="max-width:560px;background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 2px 12px rgba(0,0,0,.06)">'
        + '<tr><td style="background:#1C2F48;padding:20px 24px;text-align:center">'
        + '<span style="font-family:Arial,sans-serif;font-size:18px;font-weight:800;color:#C9A762;text-transform:uppercase;letter-spacing:.08em">'
        + APP_NAME + '</span></td></tr>'
        + '<tr><td style="padding:28px 24px">' + (cuerpoHtml || '<p style="color:#94a3b8;font-size:.9rem">El cuerpo aparecerá aquí…</p>') + '</td></tr>'
        + '<tr><td style="background:#f8fafc;padding:16px 24px;text-align:center;font-size:12px;color:#94a3b8">'
        + 'Mensaje automático — no respondas a este correo.</td></tr>'
        + '</table></td></tr></table></body></html>';
}

function actualizarPreview() {
    var frame  = document.getElementById('previewFrame');
    var asunto = (document.getElementById('asuntoInput')?.value || '');
    var cuerpo = (document.getElementById('cuerpoInput')?.value || '');
    if (!frame) return;
    // Preview de variables
    var preview = cuerpo
        .replace(/\{\{nombre\}\}/g, 'Pablo')
        .replace(/\{\{apellido\}\}/g, 'Romero')
        .replace(/\{\{email\}\}/g, 'pablo@epleague.cl');
    frame.srcdoc = buildEmailHtml(asunto, preview);
}

// Inicializar preview al cargar
document.addEventListener('DOMContentLoaded', function() {
    actualizarPreview();
});
</script>

<?php require_once '../includes/footer.php'; ?>
