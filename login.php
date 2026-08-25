<?php
require_once 'includes/auth.php';
require_once 'includes/functions.php';

// Redirigir si ya está logueado
if (epl_jugador_actual()) {
    $back = $_GET['back'] ?? 'dashboard.php';
    $es_ai_oauth = str_contains($back, '/mcp/oauth/authorize.php') || str_contains($back, '/gpt-api/oauth/authorize.php');
    if ((epl_jugador_actual()['rol'] ?? '') === 'club' && !$es_ai_oauth) { header('Location: ' . epl_url('club/resultados.php')); exit; }
    $dest = (str_starts_with($back, '/') && !str_contains($back, '//') && !str_contains($back, '..'))
        ? $back
        : ((str_contains($back, 'inscribirse') || str_contains($back, '.php'))
            ? epl_url(ltrim($back, '/'))
            : epl_url('dashboard.php'));
    header('Location: ' . $dest);
    exit;
}

$error = '';
$back  = (string)($_POST['back'] ?? $_GET['back'] ?? 'dashboard.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email']    ?? '');
    $password = trim($_POST['password'] ?? '');

    if (!$email || !$password) {
        $error = 'Completa todos los campos.';
    } elseif (epl_login($email, $password, !empty($_POST['remember']))) {
        $jugador = epl_jugador_actual();
        $es_ai_oauth = str_contains($back, '/mcp/oauth/authorize.php') || str_contains($back, '/gpt-api/oauth/authorize.php');
        if (($jugador['rol'] ?? '') === 'club' && !$es_ai_oauth) { header('Location: ' . epl_url('club/resultados.php')); exit; }
        // Redirigir al destino original (back debe ser path absoluto con /)
        $dest = (str_starts_with($back, '/') && !str_contains($back, '//') && !str_contains($back, '..'))
            ? $back
            : ((str_contains($back, 'inscribirse') || str_contains($back, '.php'))
                ? epl_url(ltrim($back, '/'))
                : epl_url('dashboard.php'));
        header('Location: ' . $dest);
        exit;
    } else {
        $error = 'Email o contraseña incorrectos.';
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Ingresar — Elite Padel League</title>
  <link rel="icon" href="<?= epl_url('assets/img/favicon.png') ?>" type="image/png">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Anton&family=Montserrat:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= epl_url('assets/css/epl.css') ?>">
  <style>
    @media (max-width: 640px) {
      .login-right { padding: 1.5rem; }
      .login-box { padding-bottom: 2rem !important; }
    }
  </style>
</head>
<body>
<div class="login-page">

  <!-- Izquierda — imagen de marca -->
  <div class="login-left">
    <div class="login-left-bg" style="background-image:url('<?= epl_url('assets/img/landing/hero-padel.jpg') ?>')"></div>
    <div class="login-left-content">
      <img src="<?= epl_url('assets/img/logo-epl.png') ?>" alt="Elite Padel League" class="login-left-logo">
      <h2 class="login-left-title">Elite<br><span>Padel</span><br>League</h2>
      <p style="color:rgba(255,255,255,.55);font-size:.9rem;margin-top:1rem">Temporada 2026</p>
    </div>
  </div>

  <!-- Derecha — formulario -->
  <div class="login-right">
    <div class="login-box">
      <a href="index.php" style="font-size:.8rem;color:var(--gray-400);display:flex;align-items:center;gap:.35rem;margin-bottom:1.5rem">
        ← Volver al inicio
      </a>

      <h1 class="login-title">Ingresar</h1>
      <p class="login-sub">Accede con tu email de jugador registrado.</p>

      <?php if ($error): ?>
        <div class="alert alert-error"><?= epl_h($error) ?></div>
      <?php endif; ?>

      <form method="post" action="">
        <input type="hidden" name="back" value="<?= epl_h($back) ?>">

        <div class="form-group">
          <label class="form-label" for="email">Email</label>
          <input type="email" name="email" id="email" class="form-control"
                 value="<?= isset($_POST['email']) ? epl_h($_POST['email']) : '' ?>"
                 placeholder="tucorreo@ejemplo.com" required autofocus>
        </div>

        <div class="form-group">
          <label class="form-label" for="password">
            Contraseña
            <a href="recuperar.php" style="font-weight:400;font-size:.75rem;color:var(--gold);float:right;text-transform:none;letter-spacing:0">
              ¿Olvidaste tu contraseña?
            </a>
          </label>
          <div style="position:relative">
            <input type="password" name="password" id="password" class="form-control"
                   placeholder="••••••••" required style="padding-right:2.8rem">
            <button type="button" onclick="togglePass()" tabindex="-1"
                    style="position:absolute;right:.75rem;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:var(--gray-400);padding:0;line-height:1"
                    title="Mostrar/ocultar contraseña">
              <svg id="eyeIcon" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
              </svg>
            </button>
          </div>
        </div>
<script>
function togglePass() {
  const inp  = document.getElementById('password');
  const icon = document.getElementById('eyeIcon');
  if (inp.type === 'password') {
    inp.type = 'text';
    icon.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/>';
  } else {
    inp.type = 'password';
    icon.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>';
  }
}
</script>

        <label style="display:flex;align-items:center;gap:.6rem;cursor:pointer;margin-bottom:.25rem;user-select:none">
          <input type="checkbox" name="remember" value="1" checked
                 style="width:17px;height:17px;accent-color:var(--gold);cursor:pointer;flex-shrink:0">
          <span style="font-size:.84rem;color:var(--gray-400);font-family:'Montserrat',sans-serif">Mantener sesión iniciada</span>
        </label>

        <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;margin-top:.75rem">
          Ingresar
        </button>

        <p style="text-align:center;margin-top:1.25rem;font-size:.85rem;color:var(--gray-400)">
          ¿No tienes cuenta?
          <a href="registro.php" style="color:var(--gold);font-weight:600">Regístrate aquí</a>
        </p>
      </form>
    </div>
  </div>

</div>
<?php require_once __DIR__ . '/includes/chat_asistente.php'; ?>
</body>
</html>
