<?php
require_once 'includes/auth.php';
require_once 'includes/functions.php';

if (epl_jugador_actual()) {
    header('Location: dashboard.php');
    exit;
}

$error  = '';
$ok     = '';
$campos = ['email','nombre','apellido','alias','rut','telefono',
           'fecha_nacimiento','sexo','comuna','profesion',
           'nivel','lado','frecuencia_juego'];
$val    = array_fill_keys($campos, '');
// Restore values on validation error
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($campos as $c) {
        $val[$c] = trim($_POST[$c] ?? '');
    }
}

$profesiones = [
    'Arquitectura / Diseño','Arte / Creatividad','Comercial / Ventas',
    'Construcción / Inmobiliaria','Derecho / Jurídico','Educación / Docencia',
    'Emprendedor / Independiente','Finanzas / Banca','Gerencia / Dirección',
    'Ingeniería / Construcción','Logística / Operaciones','Minería / Energía',
    'Publicidad / Marketing','Recursos Humanos','Salud / Medicina',
    'Estudiante','Tecnología / IT','Otro',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = strtolower(trim($_POST['email']    ?? ''));
    $password = $_POST['password']         ?? '';
    $confirma = $_POST['password_confirma'] ?? '';
    $nombre   = trim($_POST['nombre']   ?? '');
    $apellido = trim($_POST['apellido'] ?? '');
    $alias    = trim($_POST['alias']    ?? '') ?: null;
    $rut      = trim($_POST['rut']      ?? '') ?: null;
    $telefono = trim($_POST['telefono'] ?? '') ?: null;
    $fnac     = trim($_POST['fecha_nacimiento'] ?? '') ?: null;
    $sexo     = in_array($_POST['sexo'] ?? '', ['M','F','otro']) ? $_POST['sexo'] : null;
    $comuna   = trim($_POST['comuna']   ?? '') ?: null;
    $profesion= trim($_POST['profesion']?? '') ?: null;
    $nivel    = max(1, min(8, (int)($_POST['nivel'] ?? 5)));
    $lado     = in_array($_POST['lado'] ?? '', ['derecha','reves','ambos']) ? $_POST['lado'] : null;
    $frec     = in_array($_POST['frecuencia_juego'] ?? '',
                    ['1_semana','2_semana','3_o_mas','ocasional'])
                ? $_POST['frecuencia_juego'] : null;

    // Validaciones
    if (!$nombre) {
        $error = 'El nombre es obligatorio.';
    } elseif (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Ingresa un email válido.';
    } elseif (strlen($password) < 8) {
        $error = 'La contraseña debe tener al menos 8 caracteres.';
    } elseif ($password !== $confirma) {
        $error = 'Las contraseñas no coinciden.';
    } else {
        $db = epl_db();
        $existe = $db->prepare("SELECT id FROM jugadores WHERE email = ?");
        $existe->execute([$email]);
        if ($existe->fetch()) {
            $error = 'Ya existe una cuenta con ese email. <a href="login.php" style="color:var(--gold)">Inicia sesión</a>.';
        } else {
            $db->prepare("INSERT INTO jugadores
                (email,password,nombre,apellido,alias,rut,telefono,
                 fecha_nacimiento,sexo,comuna,profesion,nivel,lado,frecuencia_juego,
                 estado,rol)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,'activo','jugador')"
            )->execute([
                $email, epl_hash_password($password),
                $nombre, $apellido, $alias, $rut, $telefono,
                $fnac, $sexo, $comuna, $profesion, $nivel, $lado, $frec,
            ]);

            // Auto-login
            epl_login($email, $password);
            header('Location: dashboard.php?bienvenido=1');
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Crear cuenta — Elite Padel League</title>
  <link rel="icon" href="<?= epl_url('assets/img/favicon.png') ?>" type="image/png">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Anton&family=Montserrat:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= epl_url('assets/css/epl.css') ?>">
  <style>
    @media (max-width: 640px) {
      .login-right { padding: 1.5rem; overflow-y: auto; -webkit-overflow-scrolling: touch; }
      .login-box { padding-top: 1rem !important; padding-bottom: 2rem !important; }
    }
  </style>
</head>
<body>
<div class="login-page">

  <!-- Izquierda — marca -->
  <div class="login-left">
    <div class="login-left-bg" style="background-image:url('<?= epl_url('assets/img/hero-padel.jpg') ?>')"></div>
    <div class="login-left-content">
      <img src="<?= epl_url('assets/img/logo-epl.png') ?>" alt="Elite Padel League" class="login-left-logo">
      <h2 class="login-left-title">Elite<br><span>Padel</span><br>League</h2>
      <p style="color:rgba(255,255,255,.55);font-size:.9rem;margin-top:1rem">Temporada 2026</p>
    </div>
  </div>

  <!-- Derecha — formulario -->
  <div class="login-right" style="overflow-y:auto">
    <div class="login-box" style="max-width:520px;padding-top:2rem;padding-bottom:3rem">

      <a href="login.php" style="font-size:.8rem;color:var(--gray-400);display:flex;align-items:center;gap:.35rem;margin-bottom:1.5rem">
        ← Ya tengo cuenta
      </a>

      <h1 class="login-title">Crear cuenta</h1>
      <p class="login-sub">Completa tus datos para unirte a la liga.</p>

      <?php if ($error): ?>
        <div class="alert alert-error"><?= $error /* contains trusted HTML link */ ?></div>
      <?php endif; ?>

      <form method="post" action="">

        <!-- ── Acceso ───────────────────────── -->
        <p style="font-size:.7rem;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:var(--navy);margin:.5rem 0 .75rem">Acceso</p>

        <div class="form-group">
          <label class="form-label" for="email">Email *</label>
          <input type="email" name="email" id="email" class="form-control"
                 value="<?= epl_h($val['email']) ?>"
                 placeholder="tucorreo@ejemplo.com" required autofocus>
        </div>

        <div class="grid-2">
          <div class="form-group">
            <label class="form-label" for="password">Contraseña *</label>
            <input type="password" name="password" id="password" class="form-control"
                   placeholder="Mínimo 8 caracteres" required minlength="8" autocomplete="new-password">
          </div>
          <div class="form-group">
            <label class="form-label" for="password_confirma">Confirmar contraseña *</label>
            <input type="password" name="password_confirma" id="password_confirma" class="form-control"
                   placeholder="Repite la contraseña" required autocomplete="new-password">
          </div>
        </div>

        <!-- ── Datos personales ────────────── -->
        <p style="font-size:.7rem;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:var(--navy);margin:1.25rem 0 .75rem;border-top:1px solid var(--gray-200);padding-top:1rem">Datos personales</p>

        <div class="grid-2">
          <div class="form-group">
            <label class="form-label" for="nombre">Nombre *</label>
            <input type="text" name="nombre" id="nombre" class="form-control"
                   value="<?= epl_h($val['nombre']) ?>" required>
          </div>
          <div class="form-group">
            <label class="form-label" for="apellido">Apellido *</label>
            <input type="text" name="apellido" id="apellido" class="form-control"
                   value="<?= epl_h($val['apellido']) ?>" required>
          </div>
        </div>

        <div class="grid-2">
          <div class="form-group">
            <label class="form-label" for="alias">Alias / Apodo</label>
            <input type="text" name="alias" id="alias" class="form-control"
                   value="<?= epl_h($val['alias']) ?>" placeholder="Opcional">
          </div>
          <div class="form-group">
            <label class="form-label" for="rut">RUT</label>
            <input type="text" name="rut" id="rut" class="form-control"
                   value="<?= epl_h($val['rut']) ?>" placeholder="12.345.678-9">
          </div>
        </div>

        <div class="grid-2">
          <div class="form-group">
            <label class="form-label" for="fecha_nacimiento">Fecha de nacimiento</label>
            <input type="date" name="fecha_nacimiento" id="fecha_nacimiento" class="form-control"
                   value="<?= epl_h($val['fecha_nacimiento']) ?>">
          </div>
          <div class="form-group">
            <label class="form-label" for="sexo">Sexo</label>
            <select name="sexo" id="sexo" class="form-control">
              <option value="">— No indicar —</option>
              <option value="M"    <?= $val['sexo']==='M'   ?'selected':'' ?>>Masculino</option>
              <option value="F"    <?= $val['sexo']==='F'   ?'selected':'' ?>>Femenino</option>
              <option value="otro" <?= $val['sexo']==='otro'?'selected':'' ?>>Otro</option>
            </select>
          </div>
        </div>

        <div class="form-group">
          <label class="form-label" for="telefono">Teléfono / WhatsApp</label>
          <input type="tel" name="telefono" id="telefono" class="form-control"
                 value="<?= epl_h($val['telefono']) ?>" placeholder="+56 9 1234 5678">
          <span class="form-hint">Formato: +56 9 1234 5678</span>
        </div>

        <div class="grid-2">
          <div class="form-group">
            <label class="form-label" for="comuna">Comuna</label>
            <input type="text" name="comuna" id="comuna" class="form-control"
                   value="<?= epl_h($val['comuna']) ?>" placeholder="Las Condes, Lo Barnechea...">
          </div>
          <div class="form-group">
            <label class="form-label" for="profesion">Sector / Profesión</label>
            <select name="profesion" id="profesion" class="form-control">
              <option value="">— Selecciona —</option>
              <?php foreach ($profesiones as $pr): ?>
                <option value="<?= epl_h($pr) ?>" <?= $val['profesion']===$pr?'selected':'' ?>><?= epl_h($pr) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <!-- ── Perfil deportivo ─────────────── -->
        <p style="font-size:.7rem;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:var(--navy);margin:1.25rem 0 .75rem;border-top:1px solid var(--gray-200);padding-top:1rem">Perfil deportivo</p>

        <div class="grid-2">
          <div class="form-group">
            <label class="form-label" for="nivel">Nivel de juego</label>
            <div style="display:flex;gap:.5rem">
              <select name="nivel" id="nivel" class="form-control" style="flex:1">
                <?php for ($n=1;$n<=8;$n++): ?>
                  <option value="<?= $n ?>" <?= ((int)$val['nivel']||5)===$n?'selected':'' ?> data-standard="<?= $n===5?'1':'0' ?>"><?= $n ?>ª categoría</option>
                <?php endfor; ?>
              </select>
              <button type="button" id="btnOtrasCat" class="btn btn-sm" style="border:1px solid var(--gold);color:var(--gold-dark);white-space:nowrap;font-size:.7rem">Otras categorías</button>
            </div>
            <span class="form-hint" id="nivelHint">Por defecto se muestra 5ta categoría.</span>
          </div>
          <script>
            document.getElementById('btnOtrasCat').addEventListener('click', function() {
              const select = document.getElementById('nivel');
              const options = select.options;
              for (let i = 0; i < options.length; i++) {
                options[i].style.display = 'block';
                options[i].disabled = false;
              }
              this.style.display = 'none';
              document.getElementById('nivelHint').textContent = 'Ahora puedes seleccionar cualquier categoría.';
            });
            // Initial state: hide others if not 5
            window.addEventListener('DOMContentLoaded', function() {
              const select = document.getElementById('nivel');
              const options = select.options;
              let hasNonStandardSelected = false;
              for (let i = 0; i < options.length; i++) {
                if (options[i].value != '5') {
                  if (options[i].selected) hasNonStandardSelected = true;
                  else {
                    options[i].style.display = 'none';
                    options[i].disabled = true;
                  }
                }
              }
              if (hasNonStandardSelected) {
                document.getElementById('btnOtrasCat').style.display = 'none';
                for (let i = 0; i < options.length; i++) {
                  options[i].style.display = 'block';
                  options[i].disabled = false;
                }
              }
            });
          </script>
          <div class="form-group">
            <label class="form-label" for="lado">Lado de juego</label>
            <select name="lado" id="lado" class="form-control">
              <option value="">— No definido —</option>
              <option value="derecha" <?= $val['lado']==='derecha'?'selected':'' ?>>Derecha (Drive)</option>
              <option value="reves"   <?= $val['lado']==='reves'  ?'selected':'' ?>>Revés (Backhand)</option>
              <option value="ambos"   <?= $val['lado']==='ambos'  ?'selected':'' ?>>Ambos lados</option>
            </select>
          </div>
        </div>

        <div class="form-group">
          <label class="form-label" for="frecuencia_juego">Frecuencia de juego</label>
          <select name="frecuencia_juego" id="frecuencia_juego" class="form-control">
            <option value="">— No indicar —</option>
            <option value="1_semana"  <?= $val['frecuencia_juego']==='1_semana' ?'selected':'' ?>>1 vez por semana</option>
            <option value="2_semana"  <?= $val['frecuencia_juego']==='2_semana' ?'selected':'' ?>>2 veces por semana</option>
            <option value="3_o_mas"   <?= $val['frecuencia_juego']==='3_o_mas'  ?'selected':'' ?>>3 o más veces por semana</option>
            <option value="ocasional" <?= $val['frecuencia_juego']==='ocasional'?'selected':'' ?>>Ocasional</option>
          </select>
        </div>

        <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;font-size:1rem;padding:.85rem;margin-top:.75rem">
          Crear mi cuenta
        </button>

        <p style="text-align:center;margin-top:1.25rem;font-size:.85rem;color:var(--gray-400)">
          ¿Ya tienes cuenta?
          <a href="login.php" style="color:var(--gold);font-weight:600">Inicia sesión</a>
        </p>

      </form>
    </div>
  </div>

</div>
</body>
</html>
