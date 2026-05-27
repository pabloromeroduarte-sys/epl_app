<?php
require_once 'includes/auth.php';
require_once 'includes/functions.php';
require_once 'includes/mail.php';
require_once 'includes/mail_automations.php';

$ok    = false;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validar reCAPTCHA antes de procesar
    $captcha_token = $_POST['recaptcha_token'] ?? '';
    if (!epl_recaptcha_verificar($captcha_token, 'recuperar')) {
        $error = 'No pudimos verificar que sos humano. Intenta de nuevo.';
        $ok = false;
    } else {

    $email = strtolower(trim($_POST['email'] ?? ''));
    // Siempre mostrar OK (no revelar si el email existe)
    $ok = true;

    if ($email && filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $db = epl_db();
        $st = $db->prepare("SELECT id, nombre, apellido, email FROM jugadores WHERE email = ? AND estado = 'activo' LIMIT 1");
        $st->execute([$email]);
        $jug = $st->fetch(PDO::FETCH_ASSOC);
        if ($jug) {
            // Asigna password temporal + manda mail
            $pwd_plain = epl_asignar_password_temporal((int)$jug['id']);
            epl_mail_password_temporal($jug, $pwd_plain, 'reset');
        }
    }
    } // fin else captcha
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Recuperar contraseña — Elite Padel League</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Anton&family=Montserrat:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= epl_url('assets/css/epl.css') ?>">
  <style>.grecaptcha-badge { visibility: hidden; }</style>
  <?= epl_recaptcha_script() ?>
</head>
<body style="min-height:100vh;display:flex;align-items:center;justify-content:center;background:var(--bg)">
  <div style="width:100%;max-width:420px;padding:1.5rem">
    <a href="login.php" style="font-size:.8rem;color:var(--gray-400);display:block;margin-bottom:1.5rem">← Volver al login</a>
    <div class="form-card">
      <h1 style="font-family:var(--font-head);font-size:1.8rem;text-transform:uppercase;color:var(--navy);margin-bottom:.5rem">Recuperar</h1>
      <p style="font-size:.9rem;color:var(--gray-600);margin-bottom:1.5rem">Ingresa tu email y te enviaremos un link para restablecer tu contraseña.</p>

      <?php if ($ok): ?>
        <div class="alert alert-success">Si tu email está registrado, recibirás las instrucciones en unos minutos.</div>
        <a href="login.php" class="btn btn-navy" style="width:100%;justify-content:center">Volver al login</a>
      <?php else: ?>
        <?php if (!empty($error)): ?>
          <div class="alert alert-error"><?= epl_h($error) ?></div>
        <?php endif; ?>
        <form method="post" id="recForm">
          <input type="hidden" name="recaptcha_token" id="recCaptchaToken">
          <div class="form-group">
            <label class="form-label">Email</label>
            <input type="email" name="email" class="form-control" placeholder="tucorreo@ejemplo.com" required autofocus>
          </div>
          <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center">Enviar instrucciones</button>
        </form>
        <script>
          (function() {
            var form = document.getElementById('recForm');
            if (!form) return;
            var siteKey = <?= json_encode(epl_recaptcha_site_key()) ?>;
            form.addEventListener('submit', function(e) {
              var tokenInp = document.getElementById('recCaptchaToken');
              if (!siteKey || !tokenInp || tokenInp.value || typeof grecaptcha === 'undefined') return;
              e.preventDefault();
              grecaptcha.ready(function() {
                grecaptcha.execute(siteKey, { action: 'recuperar' }).then(function(token) {
                  tokenInp.value = token;
                  form.submit();
                }).catch(function() { form.submit(); });
              });
            });
          })();
        </script>
      <?php endif; ?>
    </div>
  </div>
<?php require_once __DIR__ . '/includes/chat_asistente.php'; ?>
</body>
</html>
