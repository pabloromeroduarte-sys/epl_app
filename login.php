<?php
require_once 'includes/auth.php';
require_once 'includes/functions.php';

// Redirigir si ya está logueado
if (epl_jugador_actual()) {
    $back = $_GET['back'] ?? 'dashboard.php';
    $dest = (str_starts_with($back, '/') && !str_contains($back, '//') && !str_contains($back, '..'))
        ? $back
        : ((str_contains($back, 'inscribirse') || str_contains($back, '.php'))
            ? epl_url(ltrim($back, '/'))
            : epl_url('dashboard.php'));
    header('Location: ' . $dest);
    exit;
}

$error = '';
$back  = isset($_GET['back']) ? $_GET['back'] : 'dashboard.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email']    ?? '');
    $password = trim($_POST['password'] ?? '');

    if (!$email || !$password) {
        $error = 'Completa todos los campos.';
    } elseif (epl_login($email, $password)) {
        $jugador = epl_jugador_actual();
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
    <div class="login-left-bg" style="background-image:url('<?= epl_url('assets/img/hero-padel.jpg') ?>')"></div>
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
          <input type="password" name="password" id="password" class="form-control"
                 placeholder="••••••••" required>
        </div>

        <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;margin-top:.5rem">
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
</body>
</html>
