<?php
require_once 'includes/auth.php';
require_once 'includes/functions.php';

// Requiere estar logueado (pero NO llama a epl_require_login para evitar loop)
$sess = epl_jugador_actual();
if (!$sess) {
    header('Location: ' . epl_url('login.php'));
    exit;
}

$db    = epl_db();
$error = '';
$ok    = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nueva    = $_POST['nueva']    ?? '';
    $confirma = $_POST['confirma'] ?? '';

    if (strlen($nueva) < 8) {
        $error = 'La nueva contraseña debe tener al menos 8 caracteres.';
    } elseif ($nueva !== $confirma) {
        $error = 'Las contraseñas no coinciden.';
    } elseif (trim($nueva) === '123456' || trim($nueva) === '1234567' || trim($nueva) === '12345678') {
        $error = 'Elige una contraseña más segura, no uses una secuencia simple.';
    } else {
        $db->prepare("UPDATE jugadores SET password=?, must_change_password=0 WHERE id=?")
           ->execute([epl_hash_password($nueva), (int)$sess['id']]);

        // Actualizar sesión
        epl_session_start();
        $_SESSION['jugador']['must_change_password'] = 0;

        $dest = ($sess['rol'] === 'admin') ? epl_url('admin/') : epl_url('dashboard.php');
        header('Location: ' . $dest . '?pass_ok=1');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Cambiar contraseña — Elite Padel League</title>
  <link rel="icon" href="<?= epl_url('assets/img/favicon.png') ?>" type="image/png">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Anton&family=Montserrat:wght@400;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= epl_url('assets/css/epl.css') ?>">
</head>
<body>
<div class="login-page">

  <!-- Izquierda -->
  <div class="login-left">
    <div class="login-left-bg" style="background-image:url('<?= epl_url('assets/img/landing/hero-padel.jpg') ?>')"></div>
    <div class="login-left-content">
      <img src="<?= epl_url('assets/img/logo-epl.png') ?>" alt="Elite Padel League" class="login-left-logo">
      <h2 class="login-left-title">Elite<br><span>Padel</span><br>League</h2>
    </div>
  </div>

  <!-- Derecha -->
  <div class="login-right">
    <div class="login-box" style="max-width:420px">

      <!-- Aviso -->
      <div style="background:#fef9c3;border:1.5px solid #fbbf24;border-radius:12px;padding:1rem 1.2rem;margin-bottom:1.5rem;display:flex;gap:.75rem;align-items:flex-start">
        <span style="font-size:1.4rem;flex-shrink:0">🔑</span>
        <div>
          <div style="font-weight:800;font-size:.9rem;color:#854d0e;margin-bottom:.25rem">Debes cambiar tu contraseña</div>
          <div style="font-size:.82rem;color:#92400e;line-height:1.5">
            Tu cuenta tiene una contraseña temporal asignada por el administrador.
            Elige una contraseña personal para continuar.
          </div>
        </div>
      </div>

      <h1 class="login-title" style="font-size:1.5rem">Nueva contraseña</h1>
      <p class="login-sub">Hola, <strong><?= epl_h($sess['nombre']) ?></strong>. Elige una contraseña que solo tú conozcas.</p>

      <?php if ($error): ?>
        <div class="alert alert-error"><?= epl_h($error) ?></div>
      <?php endif; ?>

      <form method="post">
        <div class="form-group" style="margin-bottom:1rem">
          <label class="form-label">Nueva contraseña <span style="color:var(--gold)">*</span></label>
          <input type="password" name="nueva" class="form-control" required minlength="8"
                 placeholder="Mínimo 8 caracteres" autocomplete="new-password" autofocus>
          <span class="form-hint">Al menos 8 caracteres. No uses secuencias simples como 12345678.</span>
        </div>
        <div class="form-group" style="margin-bottom:1.5rem">
          <label class="form-label">Confirmar contraseña <span style="color:var(--gold)">*</span></label>
          <input type="password" name="confirma" class="form-control" required minlength="8"
                 placeholder="Repite la contraseña" autocomplete="new-password">
        </div>
        <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;font-size:1rem;padding:.85rem">
          Guardar y continuar →
        </button>
      </form>

      <p style="text-align:center;margin-top:1.25rem;font-size:.82rem;color:var(--gray-400)">
        <a href="<?= epl_url('logout.php') ?>" style="color:var(--gray-400)">Cerrar sesión</a>
      </p>

    </div>
  </div>

</div>
</body>
</html>
