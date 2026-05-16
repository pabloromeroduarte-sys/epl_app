<?php
$page_title = 'Mi Perfil';
$player_tab = 'perfil';
require_once 'includes/auth.php';
require_once 'includes/functions.php';
epl_require_login();

header("Cache-Control: no-cache, no-store, must-revalidate");

$jugador_sess = epl_jugador_actual();
$db = epl_db();
$jugador = epl_jugador_db();

if (!$jugador) {
    session_destroy();
    header('Location: /elitepadelleague/login.php');
    exit;
}

$jugador_id = (int)$jugador['id'];

$_epl_debug = false; // cambiar a true para ver diagnóstico

$ok    = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // ── Guardar perfil completo ──────────────────────────────
    if ($action === 'perfil') {
        $nombre    = trim($_POST['nombre']   ?? '');
        $apellido  = trim($_POST['apellido'] ?? '');
        $alias     = trim($_POST['alias']    ?? '');
        $rut       = trim($_POST['rut']      ?? '');
        $telefono  = trim($_POST['telefono'] ?? '');
        $fnac      = trim($_POST['fecha_nacimiento'] ?? '');
        $sexo      = in_array($_POST['sexo']??'',['M','F','otro']) ? $_POST['sexo'] : null;
        $comuna    = trim($_POST['comuna']   ?? '');
        $profesion = trim($_POST['profesion'] ?? '');
        $nivel     = (int)($_POST['nivel']   ?? 5);
        $lado      = in_array($_POST['lado']??'',['derecha','reves','ambos']) ? $_POST['lado'] : null;
        $pala      = trim($_POST['pala']     ?? '');
        $talla     = trim($_POST['talla']    ?? '');
        $frecuencia = in_array($_POST['frecuencia_juego']??'',['1_semana','2_semana','3_o_mas','ocasional']) ? $_POST['frecuencia_juego'] : null;

        if (!$nombre) {
            $error = 'El nombre es obligatorio.';
        } else {
            $foto = $jugador['foto'];
            if (!empty($_FILES['foto']['tmp_name'])) {
                $ext = strtolower(pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION));
                if (!in_array($ext, ['jpg','jpeg','png','webp'])) {
                    $error = 'Formato no permitido (jpg, png, webp).';
                } elseif ($_FILES['foto']['size'] > 3*1024*1024) {
                    $error = 'La foto no puede superar 3 MB.';
                } else {
                    $uploadDir = __DIR__ . '/uploads/jugadores/';
                    if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
                    $newName = $jugador_id . '_' . time() . '.' . $ext;
                    if (move_uploaded_file($_FILES['foto']['tmp_name'], $uploadDir . $newName)) {
                        if ($foto && file_exists($uploadDir . $foto)) unlink($uploadDir . $foto);
                        $foto = $newName;
                    }
                }
            }
            if (!$error) {
                $db->prepare("UPDATE jugadores SET
                    nombre=?,apellido=?,alias=?,rut=?,telefono=?,
                    fecha_nacimiento=?,sexo=?,comuna=?,profesion=?,foto=?,
                    nivel=?,lado=?,pala=?,talla=?,frecuencia_juego=?
                    WHERE id=?
                ")->execute([
                    $nombre,$apellido,$alias?:null,$rut?:null,$telefono?:null,
                    $fnac?:null,$sexo,$comuna?:null,$profesion?:null,$foto,
                    $nivel,$lado,$pala?:null,$talla?:null,$frecuencia,
                    $jugador_id
                ]);
                epl_session_start();
                $_SESSION['jugador']['nombre']   = $nombre;
                $_SESSION['jugador']['apellido'] = $apellido;
                $_SESSION['jugador']['foto']     = $foto;
                $jugador = epl_jugador_por_email($jugador['email']) ?? $jugador;
                $ok = 'Perfil actualizado correctamente.';
            }
        }

    // ── Cambiar contraseña ───────────────────────────────────
    } elseif ($action === 'password') {
        $actual    = $_POST['password_actual']    ?? '';
        $nuevo     = $_POST['password_nuevo']     ?? '';
        $confirmar = $_POST['password_confirmar'] ?? '';

        if (!password_verify($actual, $jugador['password'])) {
            $error = 'La contraseña actual no es correcta.';
        } elseif (strlen($nuevo) < 8) {
            $error = 'Mínimo 8 caracteres.';
        } elseif ($nuevo !== $confirmar) {
            $error = 'Las contraseñas no coinciden.';
        } else {
            $db->prepare("UPDATE jugadores SET password=? WHERE id=?")
               ->execute([epl_hash_password($nuevo), $jugador_id]);
            $ok = 'Contraseña actualizada.';
        }
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
?>
<?php require_once 'includes/header.php'; ?>


<div class="dash-layout">
<?php include __DIR__ . '/includes/player_sidebar.php'; ?>
<main class="dash-main">
  <div class="dash-header">
    <h1 class="dash-title">Mi Perfil</h1>
  </div>

  <?php if ($ok):  ?><div class="alert alert-success"><?= epl_h($ok) ?></div><?php endif; ?>
  <?php if ($error): ?><div class="alert alert-error"><?= epl_h($error) ?></div><?php endif; ?>

  <?php if ($_epl_debug ?? false): ?>
  <div style="background:#1e293b;color:#94a3b8;font-size:.75rem;font-family:monospace;padding:1rem;border-radius:8px;margin-bottom:1rem;overflow-x:auto">
    <strong style="color:#f0b429">DEBUG — ID en sesión: <?= (int)$jugador_sess['id'] ?> | ID en DB: <?= (int)$jugador['id'] ?></strong><br>
    <?php foreach (['rut','telefono','sexo','fecha_nacimiento','nivel','lado','pala','talla','comuna','profesion','frecuencia_juego'] as $df): ?>
      <span style="color:#64748b"><?= $df ?>:</span>
      <span style="color:<?= ($jugador[$df] ?? null) !== null ? '#22c55e' : '#ef4444' ?>">
        <?= ($jugador[$df] ?? null) !== null ? epl_h((string)$jugador[$df]) : 'NULL' ?>
      </span> &nbsp;
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <!-- ── FORMULARIO ÚNICO ─────────────────────────────── -->
  <form method="post" enctype="multipart/form-data">
    <input type="hidden" name="action" value="perfil">

    <!-- Foto -->
    <div class="card mb-3">
      <div class="card-head">
        <h3 style="font-family:var(--font-head);font-size:.9rem;text-transform:uppercase;color:var(--navy)">Foto de perfil</h3>
      </div>
      <div class="card-body">
        <div style="display:flex;align-items:center;gap:1.5rem;flex-wrap:wrap">
          <img src="<?= epl_h(epl_foto_jugador($jugador['foto'], $jugador['nombre'].' '.$jugador['apellido'])) ?>"
               id="fotoPreview"
               style="width:80px;height:80px;border-radius:50%;object-fit:cover;border:3px solid var(--gold);flex-shrink:0">
          <div>
            <label class="btn btn-sm" style="border:1px solid var(--navy);color:var(--navy);cursor:pointer">
              Cambiar foto
              <input type="file" name="foto" accept="image/*" style="display:none" onchange="previewFoto(this)">
            </label>
            <p class="form-hint" style="margin-top:.4rem">JPG, PNG o WebP · Máx 3 MB</p>
          </div>
        </div>
      </div>
    </div>

    <!-- Datos personales -->
    <div class="card mb-3">
      <div class="card-head">
        <h3 style="font-family:var(--font-head);font-size:.9rem;text-transform:uppercase;color:var(--navy)">Datos personales</h3>
      </div>
      <div class="card-body">

        <div class="grid-2">
          <div class="form-group">
            <label class="form-label">Nombre *</label>
            <input type="text" name="nombre" class="form-control" value="<?= epl_h($jugador['nombre']) ?>" required>
          </div>
          <div class="form-group">
            <label class="form-label">Apellido</label>
            <input type="text" name="apellido" class="form-control" value="<?= epl_h($jugador['apellido']) ?>">
          </div>
        </div>

        <div class="grid-2">
          <div class="form-group">
            <label class="form-label">Alias / Apodo</label>
            <input type="text" name="alias" class="form-control" value="<?= epl_h($jugador['alias'] ?? '') ?>" placeholder="Opcional">
          </div>
          <div class="form-group">
            <label class="form-label">RUT</label>
            <input type="text" name="rut" class="form-control" value="<?= epl_h($jugador['rut'] ?? '') ?>" placeholder="12.345.678-9">
          </div>
        </div>

        <div class="grid-2">
          <div class="form-group">
            <label class="form-label">Fecha de nacimiento</label>
            <input type="date" name="fecha_nacimiento" class="form-control" value="<?= epl_h($jugador['fecha_nacimiento'] ?? '') ?>">
          </div>
          <div class="form-group">
            <label class="form-label">Sexo</label>
            <select name="sexo" class="form-control">
              <option value="">— No indicar —</option>
              <option value="M"    <?= ($jugador['sexo']??'')==='M'   ?'selected':'' ?>>Masculino</option>
              <option value="F"    <?= ($jugador['sexo']??'')==='F'   ?'selected':'' ?>>Femenino</option>
              <option value="otro" <?= ($jugador['sexo']??'')==='otro'?'selected':'' ?>>Otro</option>
            </select>
          </div>
        </div>

        <div class="form-group">
          <label class="form-label">Teléfono / WhatsApp</label>
          <input type="tel" name="telefono" class="form-control" value="<?= epl_h($jugador['telefono'] ?? '') ?>" placeholder="+56 9 1234 5678">
          <span class="form-hint">Formato: +56 9 1234 5678</span>
        </div>

        <div class="grid-2">
          <div class="form-group">
            <label class="form-label">Comuna</label>
            <input type="text" name="comuna" class="form-control" value="<?= epl_h($jugador['comuna'] ?? '') ?>" placeholder="Las Condes, Lo Barnechea...">
          </div>
          <div class="form-group">
            <label class="form-label">Sector / Profesión</label>
            <select name="profesion" class="form-control">
              <option value="">— Selecciona —</option>
              <?php foreach ($profesiones as $pr): ?>
                <option value="<?= epl_h($pr) ?>" <?= ($jugador['profesion']??'')===$pr?'selected':'' ?>><?= epl_h($pr) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <div class="form-group">
          <label class="form-label">Email</label>
          <input type="email" class="form-control" value="<?= epl_h($jugador['email']) ?>" disabled style="background:var(--gray-100);color:var(--gray-400)">
          <span class="form-hint">Para cambiar el email contacta al administrador.</span>
        </div>

      </div>
    </div>

    <!-- Perfil deportivo -->
    <div class="card mb-3">
      <div class="card-head">
        <h3 style="font-family:var(--font-head);font-size:.9rem;text-transform:uppercase;color:var(--navy)">Perfil deportivo</h3>
      </div>
      <div class="card-body">

        <div class="grid-2">
          <div class="form-group">
            <label class="form-label">Nivel de juego</label>
            <select name="nivel" class="form-control">
              <?php for ($n=1;$n<=8;$n++): ?>
                <option value="<?= $n ?>" <?= ($jugador['nivel']==$n)?'selected':'' ?>><?= $n ?>ª categoría</option>
              <?php endfor; ?>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Lado de juego</label>
            <select name="lado" class="form-control">
              <option value="">— No definido —</option>
              <option value="derecha" <?= ($jugador['lado']??'')==='derecha'?'selected':'' ?>>Drive</option>
              <option value="reves"   <?= ($jugador['lado']??'')==='reves'  ?'selected':'' ?>>Revés</option>
              <option value="ambos"   <?= ($jugador['lado']??'')==='ambos'  ?'selected':'' ?>>Ambos</option>
            </select>
          </div>
        </div>

        <div class="grid-2">
          <div class="form-group">
            <label class="form-label">Frecuencia de juego</label>
            <select name="frecuencia_juego" class="form-control">
              <option value="">— No indicar —</option>
              <option value="1_semana"  <?= ($jugador['frecuencia_juego']??'')==='1_semana' ?'selected':'' ?>>1 vez por semana</option>
              <option value="2_semana"  <?= ($jugador['frecuencia_juego']??'')==='2_semana' ?'selected':'' ?>>2 veces por semana</option>
              <option value="3_o_mas"   <?= ($jugador['frecuencia_juego']??'')==='3_o_mas'  ?'selected':'' ?>>3 o más veces por semana</option>
              <option value="ocasional" <?= ($jugador['frecuencia_juego']??'')==='ocasional'?'selected':'' ?>>Ocasional</option>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Talla de camiseta</label>
            <select name="talla" class="form-control">
              <option value="">— No indicar —</option>
              <?php foreach (['XS','S','M','L','XL','XXL','XXXL'] as $t): ?>
                <option value="<?= $t ?>" <?= ($jugador['talla']??'')===$t?'selected':'' ?>><?= $t ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <div class="form-group">
          <label class="form-label">Marca / Modelo de pala</label>
          <input type="text" name="pala" class="form-control" value="<?= epl_h($jugador['pala'] ?? '') ?>" placeholder="Ej: Nox ML10 Pro Cup, Babolat Viper">
        </div>

      </div>
    </div>

    <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;font-size:1rem;padding:.85rem">
      Guardar todos los cambios
    </button>
  </form>

  <!-- ── CONTRASEÑA (formulario separado) ─────────────── -->
  <div class="card mt-4" style="max-width:480px">
    <div class="card-head">
      <h3 style="font-family:var(--font-head);font-size:.9rem;text-transform:uppercase;color:var(--navy)">Cambiar contraseña</h3>
    </div>
    <div class="card-body">
      <form method="post">
        <input type="hidden" name="action" value="password">
        <div class="form-group">
          <label class="form-label">Contraseña actual</label>
          <input type="password" name="password_actual" class="form-control" required autocomplete="current-password">
        </div>
        <div class="form-group">
          <label class="form-label">Nueva contraseña</label>
          <input type="password" name="password_nuevo" class="form-control" required minlength="8" autocomplete="new-password">
          <span class="form-hint">Mínimo 8 caracteres.</span>
        </div>
        <div class="form-group">
          <label class="form-label">Confirmar nueva contraseña</label>
          <input type="password" name="password_confirmar" class="form-control" required autocomplete="new-password">
        </div>
        <button type="submit" class="btn btn-sm" style="border:1px solid var(--navy);color:var(--navy);width:100%;justify-content:center">
          Cambiar contraseña
        </button>
      </form>
    </div>
  </div>

</main>
</div>

<script>
function previewFoto(input) {
  if (input.files && input.files[0]) {
    const reader = new FileReader();
    reader.onload = e => document.getElementById('fotoPreview').src = e.target.result;
    reader.readAsDataURL(input.files[0]);
  }
}
</script>

<?php require_once 'includes/footer.php'; ?>
