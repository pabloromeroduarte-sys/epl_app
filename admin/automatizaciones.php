<?php
$page_title = 'Automatizaciones de correo';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../includes/mail.php';
epl_require_admin();

$db  = epl_db();
$ok  = '';
$err = '';

// ─── Guardar ──────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // ── Bienvenida ──────────────────────────────────────────────────────────
    if ($action === 'guardar_bienvenida') {
        epl_config_set('auto_bienvenida_activo', !empty($_POST['auto_bienvenida_activo']) ? '1' : '0');
        epl_config_set('auto_bienvenida_asunto', trim($_POST['auto_bienvenida_asunto'] ?? '') ?: '¡Bienvenido/a a Elite Padel League!');
        epl_config_set('auto_bienvenida_cuerpo', trim($_POST['auto_bienvenida_cuerpo'] ?? ''));
        $ok = 'Automatización de bienvenida guardada.';
    }

    // ── Cumpleaños jugador ───────────────────────────────────────────────────
    if ($action === 'guardar_cumple') {
        epl_config_set('auto_cumple_activo', !empty($_POST['auto_cumple_activo']) ? '1' : '0');
        epl_config_set('auto_cumple_asunto', trim($_POST['auto_cumple_asunto'] ?? '') ?: '¡Feliz cumpleaños, {{nombre}}! 🎉');
        epl_config_set('auto_cumple_cuerpo', trim($_POST['auto_cumple_cuerpo'] ?? ''));
        $ok = 'Automatización de cumpleaños (jugador) guardada.';
    }

    // ── Cumpleaños admin ─────────────────────────────────────────────────────
    if ($action === 'guardar_cumple_admin') {
        epl_config_set('auto_cumple_admin_activo', !empty($_POST['auto_cumple_admin_activo']) ? '1' : '0');
        epl_config_set('auto_cumple_admin_asunto', trim($_POST['auto_cumple_admin_asunto'] ?? '') ?: 'Cumpleaños hoy: {{nombre}} {{apellido}}');
        epl_config_set('auto_cumple_admin_cuerpo', trim($_POST['auto_cumple_admin_cuerpo'] ?? ''));
        $ok = 'Aviso de cumpleaños a admins guardado.';
    }

    // ── Probar bienvenida ───────────────────────────────────────────────────
    if ($action === 'probar_bienvenida') {
        require_once '../includes/mail_automations.php';
        $j = epl_jugador_actual();
        if ($j) {
            epl_mail_bienvenida($j);
            $ok = 'Correo de bienvenida de prueba enviado a ' . $j['email'] . '.';
        } else {
            $err = 'No hay sesión activa.';
        }
    }

    // ── Probar cumpleaños ───────────────────────────────────────────────────
    if ($action === 'probar_cumple') {
        require_once '../includes/mail_automations.php';
        $j = epl_jugador_actual();
        if ($j) {
            epl_mail_cumpleanos_jugador($j);
            epl_mail_cumpleanos_admins($j);
            $ok = 'Correo de cumpleaños de prueba enviado (a ti como jugador y a todos los admins).';
        } else {
            $err = 'No hay sesión activa.';
        }
    }
}

// ─── Leer valores actuales ────────────────────────────────────────────────────
$bienvenida = [
    'activo' => epl_config_get('auto_bienvenida_activo', '1') === '1',
    'asunto' => epl_config_get('auto_bienvenida_asunto', '¡Bienvenido/a a Elite Padel League!'),
    'cuerpo' => epl_config_get('auto_bienvenida_cuerpo',
        '<p style="margin:0 0 1rem;color:#334155;line-height:1.6">Hola <strong>{{nombre}}</strong>,</p>'
        . '<p style="margin:0 0 1rem;color:#334155;line-height:1.6">¡Tu cuenta en <strong>Elite Padel League</strong> ha sido creada con éxito! Ya puedes iniciar sesión y explorar todos los torneos disponibles.</p>'
        . '<p style="margin:0 0 1.5rem;color:#334155;line-height:1.6">Estamos muy contentos de tenerte con nosotros. ¡A jugar!</p>'
        . '<p style="margin:0"><a href="https://epleague.cl/dashboard.php" style="display:inline-block;background:#1C2F48;color:#C9A762;font-weight:700;text-decoration:none;padding:.7rem 1.4rem;border-radius:8px">Ir a mi dashboard</a></p>'
    ),
];

$cumple = [
    'activo' => epl_config_get('auto_cumple_activo', '1') === '1',
    'asunto' => epl_config_get('auto_cumple_asunto', '¡Feliz cumpleaños, {{nombre}}! 🎉'),
    'cuerpo' => epl_config_get('auto_cumple_cuerpo',
        '<p style="margin:0 0 1rem;color:#334155;line-height:1.6">¡Hola <strong>{{nombre}}</strong>!</p>'
        . '<p style="margin:0 0 1rem;color:#334155;line-height:1.6">Todo el equipo de <strong>Elite Padel League</strong> te desea un feliz cumpleaños. 🎂</p>'
        . '<p style="margin:0 0 1.5rem;color:#334155;line-height:1.6">¡Que este nuevo año esté lleno de grandes partidos y victorias en la cancha!</p>'
        . '<p style="margin:0"><a href="https://epleague.cl/dashboard.php" style="display:inline-block;background:#1C2F48;color:#C9A762;font-weight:700;text-decoration:none;padding:.7rem 1.4rem;border-radius:8px">Ver mis partidos</a></p>'
    ),
];

$cumple_admin = [
    'activo' => epl_config_get('auto_cumple_admin_activo', '1') === '1',
    'asunto' => epl_config_get('auto_cumple_admin_asunto', 'Cumpleaños hoy: {{nombre}} {{apellido}}'),
    'cuerpo' => epl_config_get('auto_cumple_admin_cuerpo',
        '<p style="margin:0 0 1rem;color:#334155;line-height:1.6">Hola, solo un recordatorio:</p>'
        . '<p style="margin:0 0 1rem;color:#334155;line-height:1.6"><strong>{{nombre}} {{apellido}}</strong> '
        . '(<a href="mailto:{{email}}" style="color:#1C2F48">{{email}}</a>) cumple años hoy.</p>'
        . '<p style="margin:0 0 1.5rem;color:#334155;line-height:1.6">¡No olvides saludarlo/a! 🎂</p>'
        . '<p style="margin:0"><a href="https://epleague.cl/admin/jugadores.php" style="display:inline-block;background:#1C2F48;color:#C9A762;font-weight:700;text-decoration:none;padding:.7rem 1.4rem;border-radius:8px">Ver jugadores</a></p>'
    ),
];

// Variables disponibles para el editor
$vars_jugador = ['{{nombre}}', '{{apellido}}', '{{email}}'];
$smtp_ok = epl_smtp_habilitado();
?>
<?php require_once '../includes/header.php'; ?>

<div class="dash-layout">
  <?php include __DIR__ . '/partials/sidebar.php'; ?>

  <main class="dash-main">
    <div class="dash-header">
      <h1 class="dash-title">Automatizaciones de correo</h1>
      <p style="color:var(--gray-500);font-size:.88rem;margin:.35rem 0 0">Configura los correos automáticos que envía el sistema.</p>
    </div>

    <?php if ($ok): ?>
      <div class="alert alert-success"><?= epl_h($ok) ?></div>
    <?php endif; ?>
    <?php if ($err): ?>
      <div class="alert alert-error"><?= epl_h($err) ?></div>
    <?php endif; ?>

    <?php if (!$smtp_ok): ?>
    <div style="background:#fffbeb;border:1px solid #fde68a;border-radius:10px;padding:.9rem 1.2rem;margin-bottom:1.25rem;font-size:.85rem;color:#92400e;font-weight:600">
      ⚠ SMTP no está activado. Las automatizaciones no enviarán correos hasta que lo configures en
      <a href="configuracion.php?tab=general" style="color:#1C2F48;text-decoration:underline">Configuración → Configuraciones</a>.
    </div>
    <?php endif; ?>

    <!-- Info variables -->
    <div style="background:#eff6ff;border:1px solid #bfdbfe;border-radius:10px;padding:.85rem 1.2rem;margin-bottom:1.5rem;font-size:.82rem;color:#1e3a5f;line-height:1.55">
      <strong>Variables disponibles en asunto y cuerpo:</strong>
      <code style="margin-left:.5rem">{{nombre}}</code>
      <code style="margin-left:.5rem">{{apellido}}</code>
      <code style="margin-left:.5rem">{{email}}</code>
      — El sistema las reemplaza por los datos del jugador.
    </div>

    <!-- ═══════════════════════════════════ BIENVENIDA ═══════════════════════════════════ -->
    <div class="card" style="max-width:720px;margin-bottom:1.5rem">
      <div style="padding:1rem 1.25rem;border-bottom:1px solid var(--gray-100);display:flex;align-items:center;justify-content:space-between;gap:.75rem;flex-wrap:wrap">
        <div>
          <h2 style="font-size:1rem;font-weight:700;color:var(--navy);margin:0">
            <span style="margin-right:.4rem">👋</span> Bienvenida
          </h2>
          <p style="font-size:.8rem;color:var(--gray-500);margin:.3rem 0 0">Se envía automáticamente cuando un jugador crea su cuenta.</p>
        </div>
        <span style="font-size:.78rem;font-weight:700;padding:.3rem .75rem;border-radius:999px;background:<?= $bienvenida['activo'] ? '#dcfce7' : '#f1f5f9' ?>;color:<?= $bienvenida['activo'] ? '#16a34a' : 'var(--gray-500)' ?>">
          <?= $bienvenida['activo'] ? 'Activa' : 'Inactiva' ?>
        </span>
      </div>
      <form method="POST" style="padding:1.5rem">
        <input type="hidden" name="action" value="guardar_bienvenida">
        <label style="display:flex;align-items:center;gap:.6rem;margin-bottom:1.25rem;cursor:pointer;font-size:.88rem;font-weight:600;color:var(--navy)">
          <input type="checkbox" name="auto_bienvenida_activo" value="1" <?= $bienvenida['activo'] ? 'checked' : '' ?> style="width:18px;height:18px">
          Activar esta automatización
        </label>
        <div style="margin-bottom:1rem">
          <label style="display:block;font-size:.82rem;font-weight:700;color:var(--navy);margin-bottom:.4rem">Asunto del correo</label>
          <input type="text" name="auto_bienvenida_asunto" value="<?= epl_h($bienvenida['asunto']) ?>"
                 style="width:100%;padding:.6rem .8rem;border:1px solid var(--gray-200);border-radius:8px;font-size:.85rem">
        </div>
        <div style="margin-bottom:1.25rem">
          <label style="display:block;font-size:.82rem;font-weight:700;color:var(--navy);margin-bottom:.4rem">Cuerpo del correo (HTML)</label>
          <textarea name="auto_bienvenida_cuerpo" rows="8"
                    style="width:100%;padding:.65rem .8rem;border:1px solid var(--gray-200);border-radius:8px;font-size:.82rem;font-family:monospace;resize:vertical"><?= epl_h($bienvenida['cuerpo']) ?></textarea>
          <p style="font-size:.73rem;color:var(--gray-400);margin:.3rem 0 0">HTML libre. Usa las variables indicadas arriba. El cuerpo va dentro de la plantilla corporativa de la liga.</p>
        </div>
        <div style="display:flex;flex-wrap:wrap;gap:.65rem">
          <button type="submit" class="btn btn-primary">Guardar bienvenida</button>
          <button type="submit" formaction="automatizaciones.php" name="action" value="probar_bienvenida"
                  class="btn" style="background:#f1f5f9;color:var(--navy);font-weight:700"
                  onclick="this.form.elements['action'].value='probar_bienvenida'">
            Enviar prueba a mi correo
          </button>
        </div>
      </form>
    </div>

    <!-- ═══════════════════════════════ CUMPLEAÑOS JUGADOR ═══════════════════════════════ -->
    <div class="card" style="max-width:720px;margin-bottom:1.5rem">
      <div style="padding:1rem 1.25rem;border-bottom:1px solid var(--gray-100);display:flex;align-items:center;justify-content:space-between;gap:.75rem;flex-wrap:wrap">
        <div>
          <h2 style="font-size:1rem;font-weight:700;color:var(--navy);margin:0">
            <span style="margin-right:.4rem">🎂</span> Cumpleaños — correo al jugador
          </h2>
          <p style="font-size:.8rem;color:var(--gray-500);margin:.3rem 0 0">Se envía al jugador el día de su cumpleaños (requiere fecha de nacimiento registrada).</p>
        </div>
        <span style="font-size:.78rem;font-weight:700;padding:.3rem .75rem;border-radius:999px;background:<?= $cumple['activo'] ? '#dcfce7' : '#f1f5f9' ?>;color:<?= $cumple['activo'] ? '#16a34a' : 'var(--gray-500)' ?>">
          <?= $cumple['activo'] ? 'Activa' : 'Inactiva' ?>
        </span>
      </div>
      <form method="POST" style="padding:1.5rem">
        <input type="hidden" name="action" value="guardar_cumple">
        <label style="display:flex;align-items:center;gap:.6rem;margin-bottom:1.25rem;cursor:pointer;font-size:.88rem;font-weight:600;color:var(--navy)">
          <input type="checkbox" name="auto_cumple_activo" value="1" <?= $cumple['activo'] ? 'checked' : '' ?> style="width:18px;height:18px">
          Activar esta automatización
        </label>
        <div style="margin-bottom:1rem">
          <label style="display:block;font-size:.82rem;font-weight:700;color:var(--navy);margin-bottom:.4rem">Asunto del correo</label>
          <input type="text" name="auto_cumple_asunto" value="<?= epl_h($cumple['asunto']) ?>"
                 style="width:100%;padding:.6rem .8rem;border:1px solid var(--gray-200);border-radius:8px;font-size:.85rem">
        </div>
        <div style="margin-bottom:1.25rem">
          <label style="display:block;font-size:.82rem;font-weight:700;color:var(--navy);margin-bottom:.4rem">Cuerpo del correo (HTML)</label>
          <textarea name="auto_cumple_cuerpo" rows="8"
                    style="width:100%;padding:.65rem .8rem;border:1px solid var(--gray-200);border-radius:8px;font-size:.82rem;font-family:monospace;resize:vertical"><?= epl_h($cumple['cuerpo']) ?></textarea>
        </div>
        <button type="submit" class="btn btn-primary">Guardar correo de cumpleaños</button>
      </form>
    </div>

    <!-- ═══════════════════════════════ CUMPLEAÑOS ADMINS ════════════════════════════════ -->
    <div class="card" style="max-width:720px;margin-bottom:1.5rem">
      <div style="padding:1rem 1.25rem;border-bottom:1px solid var(--gray-100);display:flex;align-items:center;justify-content:space-between;gap:.75rem;flex-wrap:wrap">
        <div>
          <h2 style="font-size:1rem;font-weight:700;color:var(--navy);margin:0">
            <span style="margin-right:.4rem">📬</span> Cumpleaños — aviso a administradores
          </h2>
          <p style="font-size:.8rem;color:var(--gray-500);margin:.3rem 0 0">Notifica a todos los admins cuando un jugador cumple años.</p>
        </div>
        <span style="font-size:.78rem;font-weight:700;padding:.3rem .75rem;border-radius:999px;background:<?= $cumple_admin['activo'] ? '#dcfce7' : '#f1f5f9' ?>;color:<?= $cumple_admin['activo'] ? '#16a34a' : 'var(--gray-500)' ?>">
          <?= $cumple_admin['activo'] ? 'Activa' : 'Inactiva' ?>
        </span>
      </div>
      <form method="POST" style="padding:1.5rem">
        <input type="hidden" name="action" value="guardar_cumple_admin">
        <label style="display:flex;align-items:center;gap:.6rem;margin-bottom:1.25rem;cursor:pointer;font-size:.88rem;font-weight:600;color:var(--navy)">
          <input type="checkbox" name="auto_cumple_admin_activo" value="1" <?= $cumple_admin['activo'] ? 'checked' : '' ?> style="width:18px;height:18px">
          Activar aviso a admins
        </label>
        <div style="margin-bottom:1rem">
          <label style="display:block;font-size:.82rem;font-weight:700;color:var(--navy);margin-bottom:.4rem">Asunto del correo a admins</label>
          <input type="text" name="auto_cumple_admin_asunto" value="<?= epl_h($cumple_admin['asunto']) ?>"
                 style="width:100%;padding:.6rem .8rem;border:1px solid var(--gray-200);border-radius:8px;font-size:.85rem">
        </div>
        <div style="margin-bottom:1.25rem">
          <label style="display:block;font-size:.82rem;font-weight:700;color:var(--navy);margin-bottom:.4rem">Cuerpo del correo a admins (HTML)</label>
          <textarea name="auto_cumple_admin_cuerpo" rows="8"
                    style="width:100%;padding:.65rem .8rem;border:1px solid var(--gray-200);border-radius:8px;font-size:.82rem;font-family:monospace;resize:vertical"><?= epl_h($cumple_admin['cuerpo']) ?></textarea>
        </div>
        <div style="display:flex;flex-wrap:wrap;gap:.65rem">
          <button type="submit" class="btn btn-primary">Guardar aviso admins</button>
          <button type="submit" formaction="automatizaciones.php" name="action" value="probar_cumple"
                  class="btn" style="background:#f1f5f9;color:var(--navy);font-weight:700"
                  onclick="this.form.elements['action'].value='probar_cumple'">
            Enviar prueba completa (jugador + admins)
          </button>
        </div>
      </form>
    </div>

    <!-- ══════════════════════════════════ CRON INFO ══════════════════════════════════════ -->
    <div class="card" style="max-width:720px;margin-bottom:2rem">
      <div style="padding:1rem 1.25rem;border-bottom:1px solid var(--gray-100)">
        <h2 style="font-size:1rem;font-weight:700;color:var(--navy);margin:0">⏰ Cron de cumpleaños</h2>
        <p style="font-size:.8rem;color:var(--gray-500);margin:.3rem 0 0">El script que revisa los cumpleaños se debe ejecutar una vez al día en el servidor.</p>
      </div>
      <div style="padding:1.25rem">
        <p style="font-size:.85rem;color:#334155;margin:0 0 .75rem">Agrega esta línea al crontab del servidor (ejecutar en la terminal con <code>crontab -e</code>):</p>
        <div style="background:#0f172a;border-radius:8px;padding:.85rem 1rem;font-family:monospace;font-size:.82rem;color:#a3e635;word-break:break-all;margin-bottom:.75rem">
          0 9 * * * php <?= epl_h(dirname(__DIR__)) ?>/cron/cron_cumpleanos.php
        </div>
        <p style="font-size:.78rem;color:var(--gray-400);margin:0">Esto ejecuta el script todos los días a las 9:00 AM. Ajusta la hora según tu zona horaria.</p>
      </div>
    </div>

  </main>
</div>

<script>
// Evitar doble envío en botones de prueba
document.querySelectorAll('form').forEach(function(form) {
    form.addEventListener('submit', function(e) {
        var btn = e.submitter;
        if (!btn) return;
        // Actualizar el hidden action antes de enviar si el botón tiene un value propio
        var hiddenAction = form.querySelector('input[name="action"]');
        if (hiddenAction && btn.value && btn.name === 'action') {
            hiddenAction.value = btn.value;
        }
    });
});
</script>

<?php require_once '../includes/footer.php'; ?>
