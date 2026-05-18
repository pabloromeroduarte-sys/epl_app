<?php
$page_title = 'Automatizaciones';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../includes/mail.php';
epl_require_admin();

$db = epl_db();

$db->exec("CREATE TABLE IF NOT EXISTS email_automatizaciones (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    nombre       VARCHAR(150) NOT NULL,
    trigger_tipo VARCHAR(60)  NOT NULL,
    destinatario VARCHAR(20)  NOT NULL DEFAULT 'jugador',
    activo       TINYINT(1)   NOT NULL DEFAULT 0,
    asunto       VARCHAR(255) NOT NULL DEFAULT '',
    cuerpo       LONGTEXT     NOT NULL,
    created_at   TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    updated_at   TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$triggers = [
    'registro'   => ['label'=>'Registro de cuenta',  'icon'=>'📝','color'=>'#6366f1','bg'=>'#eef2ff','campo'=>'Evento del sistema',        'cuando'=>'Cuando un jugador crea su cuenta'],
    'cumpleanos' => ['label'=>'Cumpleaños',           'icon'=>'🎂','color'=>'#f59e0b','bg'=>'#fffbeb','campo'=>'Campo: fecha_nacimiento',   'cuando'=>'Cuando la fecha de nacimiento coincide con hoy'],
];

$dest_labels = [
    'jugador' => ['icon'=>'👤','label'=>'Al jugador'],
    'admins'  => ['icon'=>'👥','label'=>'A los admins'],
    'ambos'   => ['icon'=>'🤝','label'=>'Jugador + Admins'],
];

$ok = $err = '';
$editing = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if (in_array($action, ['crear','actualizar'], true)) {
        $nombre = trim($_POST['nombre']      ?? '');
        $tipo   = $_POST['trigger_tipo']     ?? '';
        $dest   = $_POST['destinatario']     ?? 'jugador';
        $asunto = trim($_POST['asunto']      ?? '');
        $cuerpo = trim($_POST['cuerpo']      ?? '');
        $activo = !empty($_POST['activo']) ? 1 : 0;

        if (!$nombre)                         $err = 'El nombre es obligatorio.';
        elseif (!isset($triggers[$tipo]))     $err = 'Selecciona un tipo de disparo.';
        elseif (!isset($dest_labels[$dest]))  $err = 'Selecciona el destinatario.';
        elseif (!$asunto)                     $err = 'El asunto es obligatorio.';
        else {
            if ($activo) {
                $db->prepare("UPDATE email_automatizaciones SET activo=0 WHERE trigger_tipo=? AND destinatario=?")
                   ->execute([$tipo, $dest]);
            }
            if ($action === 'crear') {
                $db->prepare("INSERT INTO email_automatizaciones (nombre,trigger_tipo,destinatario,activo,asunto,cuerpo) VALUES (?,?,?,?,?,?)")
                   ->execute([$nombre,$tipo,$dest,$activo,$asunto,$cuerpo]);
                $ok = 'Automatización creada.';
            } else {
                $id = (int)($_POST['id'] ?? 0);
                $db->prepare("UPDATE email_automatizaciones SET nombre=?,trigger_tipo=?,destinatario=?,activo=?,asunto=?,cuerpo=?,updated_at=NOW() WHERE id=?")
                   ->execute([$nombre,$tipo,$dest,$activo,$asunto,$cuerpo,$id]);
                $ok = 'Guardado.';
            }
        }
        if ($err && $action === 'actualizar') {
            $id = (int)($_POST['id'] ?? 0);
            if ($id) {
                $st = $db->prepare("SELECT * FROM email_automatizaciones WHERE id=?");
                $st->execute([$id]);
                $editing = $st->fetch(PDO::FETCH_ASSOC) ?: null;
            }
        }
    }

    if ($action === 'toggle') {
        $id   = (int)($_POST['id'] ?? 0);
        $st   = $db->prepare("SELECT * FROM email_automatizaciones WHERE id=?");
        $st->execute([$id]);
        $auto = $st->fetch(PDO::FETCH_ASSOC);
        if ($auto) {
            $nuevo = $auto['activo'] ? 0 : 1;
            if ($nuevo) {
                $db->prepare("UPDATE email_automatizaciones SET activo=0 WHERE trigger_tipo=? AND destinatario=?")
                   ->execute([$auto['trigger_tipo'], $auto['destinatario']]);
            }
            $db->prepare("UPDATE email_automatizaciones SET activo=? WHERE id=?")->execute([$nuevo,$id]);
        }
        header('Location: automatizaciones.php'); exit;
    }

    if ($action === 'eliminar') {
        $db->prepare("DELETE FROM email_automatizaciones WHERE id=?")->execute([(int)($_POST['id'] ?? 0)]);
        header('Location: automatizaciones.php'); exit;
    }

    if ($action === 'probar') {
        $id = (int)($_POST['id'] ?? 0);
        $st = $db->prepare("SELECT * FROM email_automatizaciones WHERE id=?");
        $st->execute([$id]);
        $auto = $st->fetch(PDO::FETCH_ASSOC);
        if ($auto) {
            require_once '../includes/mail_automations.php';
            $j    = epl_jugador_actual();
            $vars = ['nombre'=>$j['nombre']??'Admin','apellido'=>$j['apellido']??'','email'=>$j['email']??''];
            $asF  = epl_auto_render($auto['asunto'], $vars);
            $coF  = epl_auto_render($auto['cuerpo'], $vars);
            $html = epl_mail_plantilla($asF, $coF);
            $res  = epl_mail_enviar($j['email']??'', $asF.' [PRUEBA]', $html);
            $ok  = $res['ok'] ? 'Prueba enviada a '.($j['email']??'').'.': '';
            $err = $res['ok'] ? '' : ($res['error']??'Error al enviar');
        }
    }
}

if (!$editing && isset($_GET['editar'])) {
    $st = $db->prepare("SELECT * FROM email_automatizaciones WHERE id=?");
    $st->execute([(int)$_GET['editar']]);
    $editing = $st->fetch(PDO::FETCH_ASSOC) ?: null;
}

$show_form = isset($_GET['nuevo']) || $editing !== null;
$lista     = $db->query("SELECT * FROM email_automatizaciones ORDER BY trigger_tipo, activo DESC, nombre")->fetchAll(PDO::FETCH_ASSOC);
$smtp_ok   = epl_smtp_habilitado();
$app_name  = epl_config_get('smtp_from_name', 'Elite Padel League');

// Datos JS: triggers y dests para el editor
$triggers_js  = json_encode($triggers,    JSON_UNESCAPED_UNICODE);
$dests_js     = json_encode($dest_labels, JSON_UNESCAPED_UNICODE);
$editing_tipo = $editing['trigger_tipo']  ?? '';
$editing_dest = $editing['destinatario']  ?? 'jugador';
?>
<?php require_once '../includes/header.php'; ?>

<style>
.auto-page { max-width:1100px }

/* Lista */
.auto-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(310px,1fr)); gap:1rem; margin-bottom:2rem }
.auto-card-item {
  background:#fff; border:1.5px solid #e2e8f0; border-radius:14px;
  padding:1.1rem 1.2rem; display:flex; flex-direction:column; gap:.75rem;
  transition:box-shadow .15s, border-color .15s;
}
.auto-card-item:hover { box-shadow:0 4px 18px rgba(0,0,0,.08); border-color:#cbd5e1 }
.auto-card-item.is-active { border-color:#22c55e; background:#f0fdf4 }

.trigger-pill { display:inline-flex;align-items:center;gap:.4rem;font-size:.73rem;font-weight:700;padding:.28rem .7rem;border-radius:999px;white-space:nowrap }
.dest-pill    { display:inline-flex;align-items:center;gap:.35rem;font-size:.73rem;font-weight:600;padding:.28rem .65rem;border-radius:999px;background:#f1f5f9;color:#475569 }

/* Toggle switch */
.sw { position:relative;display:inline-block;width:44px;height:24px;flex-shrink:0 }
.sw input { opacity:0;width:0;height:0 }
.sw-slider { position:absolute;cursor:pointer;inset:0;background:#cbd5e1;border-radius:999px;transition:.25s }
.sw-slider:before { content:'';position:absolute;width:18px;height:18px;left:3px;top:3px;background:#fff;border-radius:50%;transition:.25s;box-shadow:0 1px 4px rgba(0,0,0,.2) }
.sw input:checked + .sw-slider { background:#22c55e }
.sw input:checked + .sw-slider:before { transform:translateX(20px) }

/* Trigger cards */
.trigger-grid { display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:.75rem;margin-bottom:1.5rem }
.trigger-opt {
  border:2px solid #e2e8f0;border-radius:12px;padding:1rem;
  cursor:pointer;transition:border-color .15s,background .15s;background:#fff;
  display:flex;flex-direction:column;gap:.4rem;user-select:none;
}
.trigger-opt:hover { border-color:#94a3b8;background:#f8fafc }
.trigger-opt.sel  { border-width:2px }
.t-icon  { font-size:1.6rem }
.t-label { font-weight:700;font-size:.88rem;color:#1C2F48 }
.t-campo { font-size:.73rem;color:#64748b;font-weight:600 }
.t-when  { font-size:.72rem;color:#94a3b8;line-height:1.4 }

/* Dest cards */
.dest-grid { display:flex;flex-wrap:wrap;gap:.65rem;margin-bottom:1.5rem }
.dest-opt {
  border:2px solid #e2e8f0;border-radius:10px;padding:.65rem 1.1rem;
  cursor:pointer;transition:border-color .15s,background .15s;background:#fff;
  display:flex;align-items:center;gap:.5rem;
  font-weight:700;font-size:.85rem;color:#1C2F48;user-select:none;
}
.dest-opt:hover { border-color:#94a3b8 }
.dest-opt.sel   { border-color:#1C2F48;background:#eef2ff }

/* Editor + Preview */
.ep-wrap { display:grid;grid-template-columns:1fr 1fr;gap:1.25rem }
@media(max-width:860px){ .ep-wrap{grid-template-columns:1fr} }
.preview-iframe { width:100%;height:500px;border:1px solid #e2e8f0;border-radius:10px;background:#f1f5f9 }

/* Section steps */
.section-step { display:flex;align-items:center;gap:.6rem;font-size:.75rem;font-weight:800;text-transform:uppercase;letter-spacing:.1em;color:#64748b;margin-bottom:.75rem }
.step-num { width:22px;height:22px;border-radius:50%;background:#1C2F48;color:#fff;display:flex;align-items:center;justify-content:center;font-size:.7rem;font-weight:900;flex-shrink:0 }
.vars-chip { display:inline-block;background:#dbeafe;color:#1e40af;font-size:.72rem;font-family:monospace;padding:.1rem .45rem;border-radius:4px;margin:.1rem .2rem;font-weight:600 }
</style>

<div class="dash-layout">
  <?php include __DIR__ . '/partials/sidebar.php'; ?>
  <main class="dash-main">
  <div class="auto-page">

  <!-- Header -->
  <div class="dash-header" style="display:flex;align-items:flex-start;justify-content:space-between;flex-wrap:wrap;gap:.75rem;margin-bottom:1.5rem">
    <div>
      <h1 class="dash-title">Automatizaciones</h1>
      <p style="color:#64748b;font-size:.88rem;margin:.3rem 0 0">Correos automáticos según eventos o campos del jugador.</p>
    </div>
    <?php if (!$show_form): ?>
    <a href="?nuevo=1" class="btn btn-primary" style="white-space:nowrap">+ Nueva automatización</a>
    <?php endif; ?>
  </div>

  <?php if ($ok): ?><div class="alert alert-success"><?= epl_h($ok) ?></div><?php endif; ?>
  <?php if ($err): ?><div class="alert alert-error"><?= epl_h($err) ?></div><?php endif; ?>
  <?php if (!$smtp_ok): ?>
  <div style="background:#fffbeb;border:1px solid #fde68a;border-radius:10px;padding:.8rem 1.1rem;margin-bottom:1.25rem;font-size:.83rem;color:#92400e;font-weight:600">
    ⚠ SMTP desactivado —
    <a href="configuracion.php?tab=general" style="color:#1C2F48;text-decoration:underline">Configúralo aquí</a>
    para que los correos se envíen.
  </div>
  <?php endif; ?>

  <?php if (!$show_form): ?>
  <!-- ══════════════ LISTA ══════════════ -->
  <?php if (empty($lista)): ?>
  <div style="background:#f8fafc;border:2px dashed #e2e8f0;border-radius:14px;text-align:center;padding:3rem 1rem;max-width:640px">
    <div style="font-size:2.5rem;margin-bottom:.5rem">✉</div>
    <p style="font-weight:700;color:#1C2F48;margin:0 0 .35rem">Sin automatizaciones todavía</p>
    <p style="color:#64748b;font-size:.85rem;margin:0 0 1.25rem">Crea la primera para empezar a enviar correos automáticos.</p>
    <a href="?nuevo=1" class="btn btn-primary">+ Crear automatización</a>
  </div>
  <?php else: ?>
  <div class="auto-grid">
    <?php foreach ($lista as $auto):
      $trig = $triggers[$auto['trigger_tipo']] ?? ['label'=>$auto['trigger_tipo'],'icon'=>'✉','color'=>'#64748b','bg'=>'#f1f5f9','cuando'=>''];
      $dest = $dest_labels[$auto['destinatario']] ?? ['icon'=>'','label'=>$auto['destinatario']];
    ?>
    <div class="auto-card-item <?= $auto['activo'] ? 'is-active' : '' ?>">
      <!-- Cabecera -->
      <div style="display:flex;align-items:flex-start;gap:.75rem">
        <div style="width:42px;height:42px;border-radius:11px;background:<?= $trig['bg'] ?>;display:flex;align-items:center;justify-content:center;font-size:1.3rem;flex-shrink:0">
          <?= $trig['icon'] ?>
        </div>
        <div style="flex:1;min-width:0">
          <div style="font-weight:700;font-size:.92rem;color:#1C2F48;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="<?= epl_h($auto['nombre']) ?>">
            <?= epl_h($auto['nombre']) ?>
          </div>
          <div style="font-size:.76rem;color:#94a3b8;margin-top:.15rem;line-height:1.35">
            <?= epl_h($auto['asunto']) ?>
          </div>
        </div>
        <!-- Toggle -->
        <form method="POST" style="flex-shrink:0">
          <input type="hidden" name="action" value="toggle">
          <input type="hidden" name="id"     value="<?= (int)$auto['id'] ?>">
          <label class="sw" title="<?= $auto['activo'] ? 'Desactivar' : 'Activar' ?>">
            <input type="checkbox" <?= $auto['activo'] ? 'checked' : '' ?> onchange="this.closest('form').submit()">
            <span class="sw-slider"></span>
          </label>
        </form>
      </div>

      <!-- Chips -->
      <div style="display:flex;flex-wrap:wrap;gap:.4rem">
        <span class="trigger-pill" style="background:<?= $trig['bg'] ?>;color:<?= $trig['color'] ?>">
          <?= $trig['icon'] ?> <?= $trig['label'] ?>
        </span>
        <span class="dest-pill"><?= $dest['icon'] ?> <?= $dest['label'] ?></span>
      </div>

      <!-- Cuándo -->
      <div style="background:#f8fafc;border-radius:8px;padding:.5rem .8rem;font-size:.77rem;color:#475569">
        <strong style="color:#1C2F48">Disparo:</strong> <?= epl_h($trig['cuando']) ?>
      </div>

      <!-- Acciones -->
      <div style="display:flex;gap:.4rem;flex-wrap:wrap">
        <a href="?editar=<?= (int)$auto['id'] ?>" style="flex:1;text-align:center;padding:.45rem .5rem;background:#eff6ff;color:#1e40af;border-radius:8px;font-size:.78rem;font-weight:700;text-decoration:none">✏ Editar</a>
        <form method="POST" style="flex:1">
          <input type="hidden" name="action" value="probar">
          <input type="hidden" name="id"     value="<?= (int)$auto['id'] ?>">
          <button type="submit" style="width:100%;padding:.45rem .5rem;background:#f0fdf4;color:#15803d;border:none;border-radius:8px;font-size:.78rem;font-weight:700;cursor:pointer">▶ Probar</button>
        </form>
        <form method="POST" onsubmit="return confirm('¿Eliminar esta automatización?')">
          <input type="hidden" name="action" value="eliminar">
          <input type="hidden" name="id"     value="<?= (int)$auto['id'] ?>">
          <button type="submit" style="padding:.45rem .65rem;background:#fef2f2;color:#dc2626;border:none;border-radius:8px;font-size:.78rem;cursor:pointer">🗑</button>
        </form>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <!-- Cron info -->
  <details style="max-width:640px">
    <summary style="cursor:pointer;font-size:.83rem;font-weight:700;color:#1C2F48;padding:.6rem .9rem;background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;list-style:none">
      ⏰ Configurar cron en el servidor
    </summary>
    <div style="background:#f8fafc;border:1px solid #e2e8f0;border-top:none;border-radius:0 0 8px 8px;padding:.9rem 1.1rem">
      <p style="font-size:.82rem;color:#334155;margin:0 0 .5rem">Agrega al crontab con <code>crontab -e</code>:</p>
      <div style="background:#0f172a;border-radius:7px;padding:.7rem 1rem;font-family:monospace;font-size:.79rem;color:#a3e635;word-break:break-all;margin-bottom:.5rem">
        0 9 * * * php <?= epl_h(dirname(__DIR__)) ?>/cron/cron_cumpleanos.php
      </div>
    </div>
  </details>

  <?php else: ?>
  <!-- ══════════════ EDITOR ══════════════ -->
  <div style="margin-bottom:2rem">

    <div style="display:flex;align-items:center;gap:1rem;margin-bottom:1.5rem;flex-wrap:wrap">
      <a href="automatizaciones.php" style="color:#94a3b8;font-size:.83rem;text-decoration:none">← Volver</a>
      <h2 style="font-size:1.05rem;font-weight:800;color:#1C2F48;margin:0">
        <?= $editing ? 'Editar: '.epl_h($editing['nombre']) : 'Nueva automatización' ?>
      </h2>
    </div>

    <form method="POST" id="autoForm">
      <input type="hidden" name="action"       value="<?= $editing ? 'actualizar' : 'crear' ?>">
      <input type="hidden" name="trigger_tipo" id="triggerVal" value="<?= epl_h($editing_tipo) ?>">
      <input type="hidden" name="destinatario" id="destVal"    value="<?= epl_h($editing_dest) ?>">
      <?php if ($editing): ?>
        <input type="hidden" name="id" value="<?= (int)$editing['id'] ?>">
      <?php endif; ?>

      <!-- 1 · Nombre -->
      <div style="margin-bottom:1.75rem">
        <div class="section-step"><span class="step-num">1</span> Nombre</div>
        <input type="text" name="nombre" required placeholder="Ej: Bienvenida temporada 2026"
               value="<?= epl_h($editing['nombre'] ?? '') ?>"
               style="width:100%;max-width:480px;padding:.65rem .9rem;border:1.5px solid #e2e8f0;border-radius:10px;font-size:.9rem;font-weight:600;color:#1C2F48;box-sizing:border-box">
      </div>

      <!-- 2 · Trigger -->
      <div style="margin-bottom:1.75rem">
        <div class="section-step"><span class="step-num">2</span> ¿Cuándo se activa?</div>
        <div class="trigger-grid" id="triggerGrid">
          <?php foreach ($triggers as $tkey => $t): ?>
          <div class="trigger-opt <?= $editing_tipo === $tkey ? 'sel' : '' ?>"
               data-key="<?= epl_h($tkey) ?>"
               data-color="<?= epl_h($t['color']) ?>"
               data-bg="<?= epl_h($t['bg']) ?>">
            <div class="t-icon"><?= $t['icon'] ?></div>
            <div class="t-label"><?= $t['label'] ?></div>
            <div class="t-campo">🔑 <?= $t['campo'] ?></div>
            <div class="t-when"><?= $t['cuando'] ?></div>
          </div>
          <?php endforeach; ?>
        </div>
        <div id="triggerError" style="display:none;color:#dc2626;font-size:.8rem;margin-top:-.75rem;margin-bottom:.75rem">Selecciona un tipo de disparo.</div>
      </div>

      <!-- 3 · Destinatario -->
      <div style="margin-bottom:1.75rem">
        <div class="section-step"><span class="step-num">3</span> ¿A quién se envía?</div>
        <div class="dest-grid" id="destGrid">
          <?php foreach ($dest_labels as $dkey => $d): ?>
          <div class="dest-opt <?= $editing_dest === $dkey ? 'sel' : '' ?>" data-key="<?= epl_h($dkey) ?>">
            <span style="font-size:1.15rem"><?= $d['icon'] ?></span>
            <?= $d['label'] ?>
          </div>
          <?php endforeach; ?>
        </div>
        <div id="destError" style="display:none;color:#dc2626;font-size:.8rem;margin-top:-.75rem;margin-bottom:.75rem">Selecciona el destinatario.</div>
      </div>

      <!-- 4 · Activar -->
      <div style="margin-bottom:1.75rem">
        <div class="section-step"><span class="step-num">4</span> Estado</div>
        <div style="display:inline-flex;align-items:center;gap:.85rem;background:#f8fafc;border:1.5px solid #e2e8f0;border-radius:10px;padding:.75rem 1.1rem">
          <label class="sw">
            <input type="checkbox" name="activo" value="1" id="activoCheck" <?= !empty($editing['activo']) ? 'checked' : '' ?>>
            <span class="sw-slider"></span>
          </label>
          <div>
            <div style="font-weight:700;font-size:.88rem;color:#1C2F48">Activar esta automatización</div>
            <div style="font-size:.74rem;color:#94a3b8">Si hay otra activa del mismo tipo y destinatario, se desactivará</div>
          </div>
        </div>
      </div>

      <!-- 5 · Asunto -->
      <div style="margin-bottom:1.25rem">
        <div class="section-step"><span class="step-num">5</span> Asunto del correo</div>
        <input type="text" name="asunto" id="asuntoInput" required
               placeholder="Ej: ¡Bienvenido/a a Elite Padel League!"
               value="<?= epl_h($editing['asunto'] ?? '') ?>"
               style="width:100%;padding:.65rem .9rem;border:1.5px solid #e2e8f0;border-radius:10px;font-size:.88rem;box-sizing:border-box">
      </div>

      <!-- 6 · Cuerpo + Preview -->
      <div style="margin-bottom:1.5rem">
        <div class="section-step"><span class="step-num">6</span> Contenido y vista previa</div>
        <div style="background:#eff6ff;border:1px solid #bfdbfe;border-radius:8px;padding:.6rem .9rem;margin-bottom:.9rem;font-size:.78rem;color:#1e3a5f">
          <strong>Variables:</strong>
          <span class="vars-chip">{{nombre}}</span>
          <span class="vars-chip">{{apellido}}</span>
          <span class="vars-chip">{{email}}</span>
          <span style="color:#64748b;margin-left:.3rem">— se reemplazan por los datos del jugador al enviarse</span>
        </div>
        <div class="ep-wrap">
          <div>
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:.4rem">
              <span style="font-size:.8rem;font-weight:700;color:#1C2F48">HTML del correo</span>
              <button type="button" id="btnPlantilla" style="font-size:.73rem;font-weight:700;color:#6366f1;background:none;border:none;cursor:pointer;text-decoration:underline;padding:0">Insertar plantilla base</button>
            </div>
            <textarea name="cuerpo" id="cuerpoInput" rows="20"
                      placeholder="<p>Hola <strong>{{nombre}}</strong>,</p>"
                      style="width:100%;padding:.7rem .85rem;border:1.5px solid #e2e8f0;border-radius:10px;font-size:.8rem;font-family:monospace;resize:vertical;box-sizing:border-box"><?= epl_h($editing['cuerpo'] ?? '') ?></textarea>
          </div>
          <div>
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:.4rem">
              <span style="font-size:.8rem;font-weight:700;color:#1C2F48">Vista previa</span>
              <span style="font-size:.72rem;color:#94a3b8">Se actualiza al escribir</span>
            </div>
            <iframe id="previewFrame" class="preview-iframe" title="Vista previa del correo"></iframe>
          </div>
        </div>
      </div>

      <!-- Botones -->
      <div style="display:flex;flex-wrap:wrap;gap:.65rem;padding-top:.75rem;border-top:1px solid #f1f5f9">
        <button type="submit" class="btn btn-primary" style="min-width:180px">
          <?= $editing ? '💾 Guardar cambios' : '✅ Crear automatización' ?>
        </button>
        <a href="automatizaciones.php" class="btn" style="background:#f1f5f9;color:#1C2F48;font-weight:700">Cancelar</a>
      </div>
    </form>
  </div>
  <?php endif; ?>

  </div><!-- .auto-page -->
  </main>
</div>

<script>
(function () {
  // ── Trigger cards ──────────────────────────────────────────────────────────
  var triggerVal = document.getElementById('triggerVal');
  var triggerErr = document.getElementById('triggerError');

  document.querySelectorAll('#triggerGrid .trigger-opt').forEach(function (el) {
    // Aplicar colores al ya-seleccionado en carga
    if (el.classList.contains('sel')) applyTriggerStyle(el);

    el.addEventListener('click', function () {
      document.querySelectorAll('#triggerGrid .trigger-opt').forEach(function (e) {
        e.classList.remove('sel');
        e.style.borderColor = '';
        e.style.background  = '';
      });
      el.classList.add('sel');
      applyTriggerStyle(el);
      triggerVal.value = el.dataset.key;
      if (triggerErr) triggerErr.style.display = 'none';
    });
  });

  function applyTriggerStyle(el) {
    el.style.borderColor = el.dataset.color || '';
    el.style.background  = el.dataset.bg    || '';
  }

  // ── Dest cards ─────────────────────────────────────────────────────────────
  var destVal = document.getElementById('destVal');
  var destErr = document.getElementById('destError');

  document.querySelectorAll('#destGrid .dest-opt').forEach(function (el) {
    el.addEventListener('click', function () {
      document.querySelectorAll('#destGrid .dest-opt').forEach(function (e) {
        e.classList.remove('sel');
      });
      el.classList.add('sel');
      destVal.value = el.dataset.key;
      if (destErr) destErr.style.display = 'none';
    });
  });

  // ── Validación ─────────────────────────────────────────────────────────────
  var form = document.getElementById('autoForm');
  if (form) {
    form.addEventListener('submit', function (e) {
      var ok = true;
      if (!triggerVal || !triggerVal.value) {
        if (triggerErr) triggerErr.style.display = 'block';
        ok = false;
      }
      if (!destVal || !destVal.value) {
        if (destErr) destErr.style.display = 'block';
        ok = false;
      }
      if (!ok) e.preventDefault();
    });
  }

  // ── Vista previa ───────────────────────────────────────────────────────────
  var APP = <?= json_encode($app_name, JSON_UNESCAPED_UNICODE) ?>;

  function buildEmail(cuerpo) {
    var c = (cuerpo || '')
      .replace(/\{\{nombre\}\}/g,   'Pablo')
      .replace(/\{\{apellido\}\}/g, 'Romero')
      .replace(/\{\{email\}\}/g,    'pablo@epleague.cl');
    var empty = c.trim() === ''
      ? '<p style="color:#94a3b8;font-size:.88rem;text-align:center;padding:2rem 0">El cuerpo aparecerá aquí…</p>'
      : c;
    return '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8">'
      + '<style>body{margin:0;padding:0;background:#f1f5f9;font-family:Arial,sans-serif}</style>'
      + '</head><body>'
      + '<table width="100%" cellpadding="0" cellspacing="0" style="background:#f1f5f9;padding:20px 10px"><tr><td align="center">'
      + '<table width="100%" style="max-width:560px;background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 2px 12px rgba(0,0,0,.08)">'
      + '<tr><td style="background:#1C2F48;padding:18px 22px;text-align:center">'
      + '<span style="font-size:17px;font-weight:800;color:#C9A762;text-transform:uppercase;letter-spacing:.08em">' + APP + '</span></td></tr>'
      + '<tr><td style="padding:26px 22px">' + empty + '</td></tr>'
      + '<tr><td style="background:#f8fafc;padding:14px 22px;text-align:center;font-size:12px;color:#94a3b8">Mensaje automático — no respondas a este correo.</td></tr>'
      + '</table></td></tr></table></body></html>';
  }

  function refreshPreview() {
    var frame = document.getElementById('previewFrame');
    if (!frame) return;
    var cuerpo = (document.getElementById('cuerpoInput') || {}).value || '';
    frame.srcdoc = buildEmail(cuerpo);
  }

  var cuerpoTA = document.getElementById('cuerpoInput');
  if (cuerpoTA) cuerpoTA.addEventListener('input', refreshPreview);

  // ── Plantilla base ─────────────────────────────────────────────────────────
  var btnP = document.getElementById('btnPlantilla');
  if (btnP) {
    btnP.addEventListener('click', function () {
      if (cuerpoTA && cuerpoTA.value.trim() && !confirm('¿Reemplazar el contenido actual?')) return;
      if (cuerpoTA) {
        cuerpoTA.value = [
          '<p style="margin:0 0 1rem;color:#334155;line-height:1.6">Hola <strong>{{nombre}}</strong>,</p>',
          '<p style="margin:0 0 1rem;color:#334155;line-height:1.6">Tu mensaje principal aquí.</p>',
          '<p style="margin:0 0 1.5rem;color:#334155;line-height:1.6">Un párrafo adicional si lo necesitas.</p>',
          '<p style="margin:0">',
          '  <a href="https://epleague.cl/dashboard.php"',
          '     style="display:inline-block;background:#1C2F48;color:#C9A762;font-weight:700;',
          '            text-decoration:none;padding:.7rem 1.4rem;border-radius:8px">',
          '    Ir a mi dashboard',
          '  </a>',
          '</p>',
        ].join('\n');
        refreshPreview();
      }
    });
  }

  // Init
  refreshPreview();
})();
</script>

<?php require_once '../includes/footer.php'; ?>
