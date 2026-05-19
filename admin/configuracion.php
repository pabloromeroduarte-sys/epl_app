<?php
$page_title = 'Configuración';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../includes/mail.php';
epl_require_admin();

$db     = epl_db();
$_flash = epl_flash_get();
$ok     = ($_flash && $_flash['tipo']==='ok') ? $_flash['msg'] : '';
$err    = '';
$tab = $_GET['tab'] ?? 'integraciones';
if (!in_array($tab, ['integraciones', 'general'], true)) {
    $tab = 'integraciones';
}

try {
    $db->exec("CREATE TABLE IF NOT EXISTS configuracion (
        clave VARCHAR(100) PRIMARY KEY,
        valor TEXT NOT NULL,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (Throwable $e) {
    // tabla ya existe
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'guardar_mp') {
        epl_config_set('mp_access_token', trim($_POST['mp_access_token'] ?? ''));
        epl_config_set('mp_modo', in_array($_POST['mp_modo'] ?? 'sandbox', ['sandbox', 'produccion'], true)
            ? $_POST['mp_modo'] : 'sandbox');
        $mpPct = trim(str_replace(',', '.', $_POST['mp_comision_porcentaje'] ?? ''));
        if ($mpPct === '' || !is_numeric($mpPct)) {
            $mpPct = '3.49';
        }
        $mpPctF = max(0.0, min(99.0, (float) $mpPct));
        epl_config_set('mp_comision_porcentaje', (string) $mpPctF);
        $digitsEsc = preg_replace('/\D+/', '', trim((string) ($_POST['mp_redondeo_escalon_clp'] ?? '')));
        $mpEsc     = ($digitsEsc === '') ? 10000 : max(1, min(1000000, (int) $digitsEsc));
        epl_config_set('mp_redondeo_escalon_clp', (string) $mpEsc);
        epl_redirect_ok('Integración Mercado Pago guardada.', '?tab=integraciones');
    }

    if ($action === 'pausar_mail') {
        $nuevo = epl_config_get('mail_pausado', '0') === '1' ? '0' : '1';
        epl_config_set('mail_pausado', $nuevo);
        epl_redirect_ok($nuevo === '1' ? '⏸ Correos pausados. No se enviará nada hasta que lo reactives.' : '▶ Correos reactivados.', '?tab=general');
    }

    if ($action === 'guardar_smtp' || $action === 'probar_smtp') {
        epl_config_set('smtp_enabled', !empty($_POST['smtp_enabled']) ? '1' : '0');
        epl_config_set('smtp_host', trim($_POST['smtp_host'] ?? ''));
        epl_config_set('smtp_port', trim($_POST['smtp_port'] ?? '587') ?: '587');
        $enc = $_POST['smtp_encryption'] ?? 'tls';
        epl_config_set('smtp_encryption', in_array($enc, ['none', 'tls', 'ssl'], true) ? $enc : 'tls');
        epl_config_set('smtp_user', trim($_POST['smtp_user'] ?? ''));
        $pass = trim($_POST['smtp_pass'] ?? '');
        if ($pass !== '') {
            epl_config_set('smtp_pass', $pass);
        }
        epl_config_set('smtp_from_email', trim($_POST['smtp_from_email'] ?? ''));
        epl_config_set('smtp_from_name', trim($_POST['smtp_from_name'] ?? 'Elite Padel League') ?: 'Elite Padel League');
        epl_config_set('smtp_reply_to', trim($_POST['smtp_reply_to'] ?? ''));
        $tab = 'general';

        if ($action === 'guardar_smtp') {
            epl_redirect_ok('Configuración SMTP guardada.', '?tab=general');
        }

        if ($action === 'probar_smtp') {
            $destino = trim($_POST['test_email'] ?? '');
            if (!filter_var($destino, FILTER_VALIDATE_EMAIL)) {
                $err = 'Indica un email válido para la prueba.';
            } else {
                $body = epl_mail_plantilla(
                    'Prueba SMTP',
                    '<p style="margin:0;color:#334155;line-height:1.5">Si recibes este correo, el servidor SMTP está configurado correctamente para las notificaciones del sistema.</p>'
                );
                $res = epl_mail_enviar($destino, 'Prueba SMTP — Elite Padel League', $body);
                if ($res['ok']) {
                    epl_redirect_ok('Configuración aplicada. Correo de prueba enviado a ' . $destino . '.', '?tab=general');
                } else {
                    $err = $res['error'] ?? 'No se pudo enviar el correo de prueba.';
                }
            }
        }
    }
}

$mp_token = epl_config_get('mp_access_token');
$mp_modo  = epl_config_get('mp_modo', 'sandbox');
$mp_pv    = trim(epl_config_get('mp_comision_porcentaje'));
$mp_pv    = ($mp_pv !== '' && is_numeric(str_replace(',', '.', $mp_pv))) ? str_replace(',', '.', $mp_pv) : '3.49';
$mp_escalon = (string) epl_mp_redondeo_escalon_clp();
$smtp = [
    'enabled'    => epl_config_get('smtp_enabled', '0') === '1',
    'host'       => epl_config_get('smtp_host'),
    'port'       => epl_config_get('smtp_port', '587'),
    'encryption' => epl_config_get('smtp_encryption', 'tls'),
    'user'       => epl_config_get('smtp_user'),
    'pass_set'   => epl_config_get('smtp_pass') !== '',
    'from_email' => epl_config_get('smtp_from_email'),
    'from_name'  => epl_config_get('smtp_from_name', 'Elite Padel League'),
    'reply_to'   => epl_config_get('smtp_reply_to'),
];

$mp_webhook = epl_url('api/mp_webhook.php');
$adminEmail = epl_jugador_actual()['email'] ?? '';
?>
<?php require_once '../includes/header.php'; ?>

<div class="dash-layout">
  <?php include __DIR__ . '/partials/sidebar.php'; ?>

  <main class="dash-main">
    <div class="dash-header">
      <h1 class="dash-title">Configuración</h1>
      <p style="color:var(--gray-500);font-size:.88rem;margin:.35rem 0 0">Integraciones externas y correo del sistema</p>
    </div>

    <?php if ($ok): ?>
      <div class="alert alert-success"><?= epl_h($ok) ?></div>
    <?php endif; ?>
    <?php if ($err): ?>
      <div class="alert alert-error"><?= epl_h($err) ?></div>
    <?php endif; ?>

    <div style="display:flex;gap:.5rem;flex-wrap:wrap;margin-bottom:1.25rem">
      <?php foreach (['integraciones' => 'Integraciones', 'general' => 'Configuraciones'] as $t => $label): ?>
        <a href="?tab=<?= $t ?>" style="padding:.45rem 1rem;border-radius:8px;font-size:.83rem;font-weight:700;text-decoration:none;
           background:<?= $tab === $t ? 'var(--navy)' : '#f1f5f9' ?>;
           color:<?= $tab === $t ? '#fff' : 'var(--gray-600)' ?>">
          <?= $label ?>
        </a>
      <?php endforeach; ?>
    </div>

    <?php if ($tab === 'integraciones'): ?>
    <div class="card" style="max-width:640px;margin-bottom:1.25rem">
      <div style="padding:1rem 1.25rem;border-bottom:1px solid var(--gray-100)">
        <h2 style="font-size:1rem;font-weight:700;color:var(--navy);margin:0">Mercado Pago</h2>
        <p style="font-size:.8rem;color:var(--gray-500);margin:.35rem 0 0">Pagos de inscripciones y cobros en línea.</p>
      </div>
      <form method="POST" style="padding:1.5rem">
        <input type="hidden" name="action" value="guardar_mp">
        <div style="margin-bottom:1rem">
          <label style="display:block;font-size:.82rem;font-weight:700;color:var(--navy);margin-bottom:.4rem">Access Token (privado)</label>
          <input type="text" name="mp_access_token" value="<?= epl_h($mp_token) ?>"
                 placeholder="APP_USR-..." style="width:100%;padding:.6rem .8rem;border:1px solid var(--gray-200);border-radius:8px;font-size:.85rem;font-family:monospace">
          <p style="font-size:.75rem;color:var(--gray-400);margin-top:.3rem">Credenciales en <strong>mercadopago.com/developers</strong> → Tu App.</p>
        </div>
        <div style="margin-bottom:1rem">
          <label style="display:block;font-size:.82rem;font-weight:700;color:var(--navy);margin-bottom:.4rem">Modo</label>
          <select name="mp_modo" style="width:100%;max-width:240px;padding:.6rem .8rem;border:1px solid var(--gray-200);border-radius:8px;font-size:.85rem">
            <option value="sandbox"    <?= $mp_modo === 'sandbox' ? 'selected' : '' ?>>Sandbox (pruebas)</option>
            <option value="produccion" <?= $mp_modo === 'produccion' ? 'selected' : '' ?>>Producción (real)</option>
          </select>
        </div>
        <div style="margin-bottom:1rem">
          <label style="display:block;font-size:.82rem;font-weight:700;color:var(--navy);margin-bottom:.4rem">Comisión típica MP (%)</label>
          <input type="number" name="mp_comision_porcentaje" step="0.01" min="0" max="99" value="<?= epl_h($mp_pv) ?>"
                 style="width:100%;max-width:200px;padding:.6rem .8rem;border:1px solid var(--gray-200);border-radius:8px;font-size:.85rem">
          <p style="font-size:.75rem;color:var(--gray-400);margin-top:.35rem;line-height:1.45">
            Usada en el cálculo de inscripción: <strong>bruto = líquido ÷ (1 − comisión/100)</strong>, luego redondeo superior (ver abajo). Podés cambiar el % en cada liga.</p>
        </div>
        <div style="margin-bottom:1rem">
          <label style="display:block;font-size:.82rem;font-weight:700;color:var(--navy);margin-bottom:.4rem">Redondeo cobro (↑ a múltiplos en CLP)</label>
          <input type="number" name="mp_redondeo_escalon_clp" step="1" min="1" max="1000000" value="<?= epl_h($mp_escalon) ?>"
                 style="width:100%;max-width:200px;padding:.6rem .8rem;border:1px solid var(--gray-200);border-radius:8px;font-size:.85rem">
          <p style="font-size:.75rem;color:var(--gray-400);margin-top:.35rem;line-height:1.45">
            El monto que paga el jugador sube al <strong>siguiente múltiplo</strong> de este valor (por defecto <strong>10.000</strong>: 155.424 → 160.000). Poné <strong>1</strong> si solo querés techo al peso siguiente.</p>
        </div>
        <div style="margin-bottom:1.25rem;padding:.85rem;background:#f8fafc;border:1px solid var(--gray-200);border-radius:8px">
          <div style="font-size:.78rem;font-weight:700;color:var(--navy);margin-bottom:.35rem">URL Webhook (notificaciones de pago)</div>
          <code style="font-size:.75rem;word-break:break-all;color:var(--gray-600)"><?= epl_h($mp_webhook) ?></code>
          <p style="font-size:.72rem;color:var(--gray-400);margin:.4rem 0 0">Configúrala en Mercado Pago → Webhooks → eventos de pago.</p>
        </div>
        <?php if ($mp_token): ?>
        <div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;padding:.75rem;margin-bottom:1rem;font-size:.82rem;color:#16a34a;font-weight:600">
          ✓ Token configurado. <?= $mp_modo === 'produccion' ? 'Modo producción activo.' : 'Modo sandbox.' ?>
        </div>
        <?php else: ?>
        <div style="background:#fffbeb;border:1px solid #fde68a;border-radius:8px;padding:.75rem;margin-bottom:1rem;font-size:.82rem;color:#92400e;font-weight:600">
          Sin token: inscripciones con pago no funcionarán. Las gratuitas (precio 0) sí.
        </div>
        <?php endif; ?>
        <button type="submit" class="btn btn-primary">Guardar Mercado Pago</button>
      </form>
    </div>
    <?php endif; ?>

    <?php if ($tab === 'general'): ?>
    <?php $mail_pausado = epl_config_get('mail_pausado', '0') === '1'; ?>
    <div class="card" style="max-width:640px">
      <div style="padding:1rem 1.25rem;border-bottom:1px solid var(--gray-100);display:flex;align-items:center;justify-content:space-between;gap:1rem;flex-wrap:wrap">
        <div>
          <h2 style="font-size:1rem;font-weight:700;color:var(--navy);margin:0">Correo SMTP</h2>
          <p style="font-size:.8rem;color:var(--gray-500);margin:.35rem 0 0">Envía emails cuando se crean notificaciones en la app (invitaciones, resultados, etc.).</p>
        </div>
        <form method="POST" style="margin:0">
          <input type="hidden" name="action" value="pausar_mail">
          <button type="submit" class="btn btn-sm" style="background:<?= $mail_pausado ? '#dc2626' : '#f59e0b' ?>;color:#fff;border:none;cursor:pointer;font-weight:700;white-space:nowrap">
            <?= $mail_pausado ? '▶ Reactivar correos' : '⏸ Pausar correos' ?>
          </button>
        </form>
      </div>
      <?php if ($mail_pausado): ?>
      <div style="background:#fef2f2;border-bottom:1px solid #fecaca;padding:.75rem 1.25rem;font-size:.82rem;color:#dc2626;font-weight:600">
        ⏸ Correos pausados — no se está enviando ningún mail. Pulsá "Reactivar correos" cuando termines.
      </div>
      <?php endif; ?>

      <form method="POST" style="padding:1.5rem">
        <?php
          $gmailAlias = $smtp['user'] !== '' && $smtp['from_email'] !== ''
              && strcasecmp($smtp['user'], $smtp['from_email']) !== 0;
        ?>
        <div style="background:#eff6ff;border:1px solid #bfdbfe;border-radius:8px;padding:1rem;margin-bottom:1.25rem;font-size:.8rem;color:#1e3a5f;line-height:1.55">
          <strong style="display:block;margin-bottom:.5rem;color:var(--navy)">Gmail + dominio contacto@epleague.cl</strong>
          <ol style="margin:0 0 .5rem 1.1rem;padding:0">
            <li>Crea <strong>epleague@gmail.com</strong> y activa verificación en 2 pasos.</li>
            <li>En tu hosting de <strong>epleague.cl</strong>, crea el buzón o reenvío <strong>contacto@epleague.cl</strong> (debe recibir el código de Gmail).</li>
            <li>En Gmail → Configuración → <strong>Cuentas</strong> → <strong>Enviar correo como</strong> → agregar <strong>contacto@epleague.cl</strong> y verificar.</li>
            <li>Aquí: usuario <code>epleague@gmail.com</code>, remitente <code>contacto@epleague.cl</code>, contraseña de <a href="https://myaccount.google.com/apppasswords" target="_blank" rel="noopener">aplicación</a>.</li>
          </ol>
          Sin el paso 3, Gmail rechazará el envío o mostrará “vía gmail.com” / spam.
        </div>
        <?php if ($gmailAlias): ?>
        <div style="background:#fffbeb;border:1px solid #fde68a;border-radius:8px;padding:.75rem;margin-bottom:1rem;font-size:.82rem;color:#92400e">
          Remitente distinto al usuario SMTP: confirma que <strong>contacto@epleague.cl</strong> está verificado en Gmail → Enviar correo como.
        </div>
        <?php endif; ?>
        <label style="display:flex;align-items:center;gap:.6rem;margin-bottom:1.25rem;cursor:pointer;font-size:.88rem;font-weight:600;color:var(--navy)">
          <input type="checkbox" name="smtp_enabled" value="1" <?= $smtp['enabled'] ? 'checked' : '' ?> style="width:18px;height:18px">
          Activar envío de correos por SMTP
        </label>
        <div style="display:grid;grid-template-columns:1fr 120px;gap:.75rem;margin-bottom:1rem">
          <div>
            <label style="display:block;font-size:.82rem;font-weight:700;color:var(--navy);margin-bottom:.4rem">Servidor SMTP</label>
            <input type="text" name="smtp_host" value="<?= epl_h($smtp['host']) ?>" placeholder="smtp.gmail.com"
                   title="Gmail: smtp.gmail.com"
                   style="width:100%;padding:.6rem .8rem;border:1px solid var(--gray-200);border-radius:8px;font-size:.85rem">
          </div>
          <div>
            <label style="display:block;font-size:.82rem;font-weight:700;color:var(--navy);margin-bottom:.4rem">Puerto</label>
            <input type="number" name="smtp_port" value="<?= epl_h($smtp['port']) ?>" min="1" max="65535"
                   style="width:100%;padding:.6rem .8rem;border:1px solid var(--gray-200);border-radius:8px;font-size:.85rem">
          </div>
        </div>
        <div style="margin-bottom:1rem">
          <label style="display:block;font-size:.82rem;font-weight:700;color:var(--navy);margin-bottom:.4rem">Cifrado</label>
          <select name="smtp_encryption" style="width:100%;max-width:200px;padding:.6rem .8rem;border:1px solid var(--gray-200);border-radius:8px;font-size:.85rem">
            <option value="tls"  <?= $smtp['encryption'] === 'tls' ? 'selected' : '' ?>>TLS (recomendado, puerto 587)</option>
            <option value="ssl"  <?= $smtp['encryption'] === 'ssl' ? 'selected' : '' ?>>SSL (puerto 465)</option>
            <option value="none" <?= $smtp['encryption'] === 'none' ? 'selected' : '' ?>>Ninguno</option>
          </select>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:.75rem;margin-bottom:1rem">
          <div>
            <label style="display:block;font-size:.82rem;font-weight:700;color:var(--navy);margin-bottom:.4rem">Usuario SMTP</label>
            <input type="email" name="smtp_user" value="<?= epl_h($smtp['user']) ?>" autocomplete="off"
                   placeholder="epleague@gmail.com"
                   style="width:100%;padding:.6rem .8rem;border:1px solid var(--gray-200);border-radius:8px;font-size:.85rem">
          </div>
          <div>
            <label style="display:block;font-size:.82rem;font-weight:700;color:var(--navy);margin-bottom:.4rem">Contraseña</label>
            <input type="password" name="smtp_pass" value="" placeholder="<?= $smtp['pass_set'] ? '•••••••• (dejar vacío para no cambiar)' : 'Contraseña o app password' ?>"
                   autocomplete="new-password"
                   style="width:100%;padding:.6rem .8rem;border:1px solid var(--gray-200);border-radius:8px;font-size:.85rem">
          </div>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:.75rem;margin-bottom:1.25rem">
          <div>
            <label style="display:block;font-size:.82rem;font-weight:700;color:var(--navy);margin-bottom:.4rem">Email remitente (From)</label>
            <input type="email" name="smtp_from_email" value="<?= epl_h($smtp['from_email']) ?>" placeholder="contacto@epleague.cl"
                   style="width:100%;padding:.6rem .8rem;border:1px solid var(--gray-200);border-radius:8px;font-size:.85rem">
            <p style="font-size:.72rem;color:var(--gray-400);margin:.3rem 0 0">Lo que ven los jugadores. Debe estar en Gmail → Enviar correo como.</p>
          </div>
          <div>
            <label style="display:block;font-size:.82rem;font-weight:700;color:var(--navy);margin-bottom:.4rem">Nombre visible (marca)</label>
            <input type="text" name="smtp_from_name" value="<?= epl_h($smtp['from_name']) ?>" placeholder="Elite Padel League"
                   style="width:100%;padding:.6rem .8rem;border:1px solid var(--gray-200);border-radius:8px;font-size:.85rem">
            <p style="font-size:.72rem;color:var(--gray-400);margin:.3rem 0 0">Lo que el jugador lee primero en la bandeja.</p>
          </div>
        </div>
        <div style="margin-bottom:1.25rem">
          <label style="display:block;font-size:.82rem;font-weight:700;color:var(--navy);margin-bottom:.4rem">Responder a (Reply-To) — opcional</label>
          <input type="email" name="smtp_reply_to" value="<?= epl_h($smtp['reply_to']) ?>" placeholder="contacto@epleague.cl"
                 style="width:100%;max-width:420px;padding:.6rem .8rem;border:1px solid var(--gray-200);border-radius:8px;font-size:.85rem">
          <p style="font-size:.72rem;color:var(--gray-400);margin:.3rem 0 0">Opcional si es distinto del From. Si From ya es contacto@epleague.cl, déjalo vacío.</p>
        </div>
        <?php if ($smtp['enabled'] && $smtp['host'] && $smtp['from_email']): ?>
        <div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;padding:.75rem;margin-bottom:1rem;font-size:.82rem;color:#16a34a">
          SMTP activo — las notificaciones del sistema también enviarán email a los jugadores con correo registrado.
        </div>
        <?php elseif ($smtp['enabled']): ?>
        <div style="background:#fffbeb;border:1px solid #fde68a;border-radius:8px;padding:.75rem;margin-bottom:1rem;font-size:.82rem;color:#92400e">
          Activo pero faltan host o email remitente.
        </div>
        <?php else: ?>
        <div style="background:#f1f5f9;border-radius:8px;padding:.75rem;margin-bottom:1rem;font-size:.82rem;color:var(--gray-600)">
          Desactivado: solo notificaciones in-app (campana), sin email.
        </div>
        <?php endif; ?>
        <div style="margin-top:1rem;padding-top:1rem;border-top:1px solid var(--gray-100)">
          <h3 style="font-size:.92rem;font-weight:700;color:var(--navy);margin:0 0 .75rem">Probar conexión</h3>
          <div style="margin-bottom:.5rem">
            <label style="display:block;font-size:.78rem;font-weight:700;color:var(--navy);margin-bottom:.35rem">Enviar prueba a</label>
            <input type="email" name="test_email" value="<?= epl_h($adminEmail) ?>" required
                   style="width:100%;max-width:360px;padding:.55rem .75rem;border:1px solid var(--gray-200);border-radius:8px;font-size:.85rem">
          </div>
          <p style="font-size:.72rem;color:var(--gray-500);margin:0 0 1rem;line-height:1.45">
            La prueba <strong>guarda primero</strong> los datos de este formulario (incluido “Activar SMTP”). Si no marcás la casilla, quedará desactivado y verás “SMTP desactivado”.
          </p>
          <div style="display:flex;flex-wrap:wrap;gap:.65rem;align-items:center">
            <button type="submit" name="action" value="guardar_smtp" class="btn btn-primary">Guardar SMTP</button>
            <button type="submit" name="action" value="probar_smtp" class="btn" style="background:#f1f5f9;color:var(--navy);font-weight:700">Guardar y enviar correo de prueba</button>
          </div>
        </div>
      </form>
    </div>
    <?php endif; ?>

  </main>
</div>

<?php require_once '../includes/footer.php'; ?>
