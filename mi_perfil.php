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

$_flash = epl_flash_get();
$ok     = ($_flash && $_flash['tipo']==='ok')    ? $_flash['msg'] : '';
$error  = ($_flash && $_flash['tipo']==='error') ? $_flash['msg'] : '';

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
                epl_redirect_ok('Perfil actualizado correctamente.');
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
            epl_redirect_ok('Contraseña actualizada.');
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

    <!-- Acceso tutoriales -->
    <a href="tutoriales.php" style="display:flex;align-items:center;gap:.85rem;background:linear-gradient(135deg,#1c2f48,#1a3a64);border-radius:14px;padding:1rem 1.25rem;margin-bottom:1rem;text-decoration:none;color:#fff;">
      <span style="font-size:1.5rem">📖</span>
      <div style="flex:1">
        <div style="font-weight:800;font-size:.88rem">Tutoriales y guías</div>
        <div style="font-size:.72rem;color:rgba(255,255,255,.65);margin-top:.1rem">Cómo puntuar, reprogramar, inscribirte y más</div>
      </div>
      <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" style="width:18px;height:18px;flex-shrink:0;opacity:.6"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
    </a>

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

  <!-- ── App & Notificaciones ─────────────────────────── -->
  <div class="card" id="app-notif" style="margin-top:1.5rem;scroll-margin-top:5rem">
    <div class="card-header">
      <h3 class="card-title">📱 App &amp; Notificaciones</h3>
    </div>
    <div class="card-body" style="display:flex;flex-direction:column;gap:.85rem">

      <!-- Instalar app -->
      <div class="pwa-action-row">
        <div class="pwa-action-info">
          <span class="pwa-action-icon" style="background:#dbeafe">📲</span>
          <div>
            <strong>Instalar la app</strong>
            <p>Agrega EPL a tu pantalla de inicio para acceso rápido.</p>
          </div>
        </div>
        <div id="pwa-install-status" class="pwa-status-badge pwa-status-check">
          <span class="pwa-dot"></span> Verificando...
        </div>
        <button id="pwa-btn-install" class="pwa-action-btn pwa-btn-navy" style="display:none">
          Instalar
        </button>
        <span id="pwa-installed-label" class="pwa-action-btn pwa-btn-done" style="display:none">
          ✅ Instalada
        </span>
      </div>

      <!-- Notificaciones -->
      <div class="pwa-action-row" style="border-top:1px solid var(--gray-100);padding-top:.85rem">
        <div class="pwa-action-info">
          <span class="pwa-action-icon" style="background:#fce7f3">🔔</span>
          <div>
            <strong>Notificaciones push</strong>
            <p>Recibe alertas de partidos, resultados y novedades.</p>
          </div>
        </div>
        <div id="notif-status" class="pwa-status-badge pwa-status-check">
          <span class="pwa-dot"></span> Verificando...
        </div>
        <button id="pwa-btn-notif" class="pwa-action-btn pwa-btn-gold" style="display:none">
          Activar
        </button>
        <span id="notif-active-label" class="pwa-action-btn pwa-btn-done" style="display:none">
          ✅ Activadas
        </span>
        <span id="notif-denied-label" class="pwa-action-btn pwa-btn-warn" style="display:none">
          ⛔ Bloqueadas
        </span>
      </div>

      <!-- Nota iOS -->
      <div id="pwa-ios-note" style="display:none" class="pwa-ios-tip">
        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
        <span>En iPhone debes instalar la app desde <strong>Safari</strong> → botón compartir → <strong>"Añadir a pantalla de inicio"</strong> y abrirla desde el ícono.</span>
      </div>

      <!-- Nota bloqueadas -->
      <div id="notif-blocked-tip" style="display:none" class="pwa-ios-tip" style="background:#fef2f2;border-color:#fecaca;color:#991b1b">
        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
        <span>Las notificaciones están bloqueadas en tu navegador. Ve a <strong>Ajustes del navegador → Permisos del sitio → Notificaciones</strong> y permite este sitio.</span>
      </div>

    </div>
  </div>

</main>
</div>

<style>
.pwa-action-row {
  display: flex; align-items: center; gap: .85rem; flex-wrap: wrap;
}
.pwa-action-info {
  display: flex; align-items: center; gap: .75rem; flex: 1; min-width: 0;
}
.pwa-action-icon {
  width: 42px; height: 42px; border-radius: 12px; font-size: 1.25rem;
  display: flex; align-items: center; justify-content: center; flex-shrink: 0;
}
.pwa-action-info strong { display: block; font-size: .85rem; color: var(--navy); margin-bottom: .1rem; }
.pwa-action-info p { font-size: .75rem; color: var(--gray-400); margin: 0; line-height: 1.3; }

.pwa-status-badge {
  font-size: .7rem; font-weight: 700; padding: .25rem .65rem;
  border-radius: 50px; white-space: nowrap; display: flex; align-items: center; gap: .35rem;
}
.pwa-status-check { background: #f1f5f9; color: #64748b; }
.pwa-dot { width: 6px; height: 6px; border-radius: 50%; background: currentColor; animation: pwa-pulse 1.2s ease infinite; }
.pwa-status-check .pwa-dot { animation: pwa-pulse 1.2s ease infinite; }
@keyframes pwa-pulse { 0%,100%{opacity:.3} 50%{opacity:1} }

.pwa-action-btn {
  flex-shrink: 0; border: none; border-radius: 10px;
  padding: .5rem 1.1rem; font-size: .78rem; font-weight: 800;
  cursor: pointer; font-family: 'Montserrat', sans-serif;
  text-transform: uppercase; letter-spacing: .04em; transition: filter .15s;
}
.pwa-btn-navy { background: var(--navy); color: #fff; }
.pwa-btn-navy:hover { filter: brightness(1.15); }
.pwa-btn-gold { background: #C9A762; color: var(--navy); }
.pwa-btn-gold:hover { filter: brightness(1.1); }
.pwa-btn-done { background: #f0fdf4; color: #166534; cursor: default; }
.pwa-btn-warn { background: #fef2f2; color: #991b1b; cursor: default; }

.pwa-ios-tip {
  display: flex; align-items: flex-start; gap: .45rem;
  background: #fefce8; border: 1px solid #fde68a; border-radius: 10px;
  padding: .6rem .85rem; font-size: .75rem; color: #92400e;
  line-height: 1.45;
}
.pwa-ios-tip svg { flex-shrink: 0; margin-top: .1rem; }
</style>

<script>
function previewFoto(input) {
  if (input.files && input.files[0]) {
    const reader = new FileReader();
    reader.onload = e => document.getElementById('fotoPreview').src = e.target.result;
    reader.readAsDataURL(input.files[0]);
  }
}
</script>

<script>
(function(){
  const ua         = navigator.userAgent;
  const isIOS      = /iphone|ipad|ipod/i.test(ua);
  const isSafari   = /safari/i.test(ua) && !/chrome|chromium|crios/i.test(ua);
  const isAndroid  = /android/i.test(ua);
  const isMobile   = isIOS || isAndroid;
  const isStandalone = window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;

  // ── Estado: app instalada ──────────────────────────
  const installStatus = document.getElementById('pwa-install-status');
  const btnInstall    = document.getElementById('pwa-btn-install');
  const installedLbl  = document.getElementById('pwa-installed-label');
  const iosNote       = document.getElementById('pwa-ios-note');

  function setInstallUI(state) {
    installStatus.style.display = 'none';
    if (state === 'installed') {
      installedLbl.style.display = '';
    } else if (state === 'available') {
      btnInstall.style.display = '';
    } else if (state === 'ios') {
      btnInstall.style.display = '';
      btnInstall.textContent = 'Ver instrucciones';
      iosNote.style.display = '';
    } else {
      // desktop o no detectado
      installedLbl.style.display = '';
      installedLbl.textContent = '💻 Solo en móvil';
      installedLbl.style.background = '#f1f5f9';
      installedLbl.style.color = '#64748b';
    }
  }

  if (isStandalone) {
    setInstallUI('installed');
  } else if (!isMobile) {
    setInstallUI('desktop');
  } else if (isIOS && isSafari) {
    setInstallUI('ios');
  } else if (isIOS && !isSafari) {
    setInstallUI('ios');
    iosNote.style.display = '';
  } else {
    // Android — esperamos beforeinstallprompt o ya instalado
    setInstallUI('installed'); // asumimos instalado si no hay evento
  }

  // Capturar evento de instalación (Android Chrome)
  let deferredPrompt = null;
  window.addEventListener('beforeinstallprompt', e => {
    e.preventDefault();
    deferredPrompt = e;
    setInstallUI('available');
  });

  btnInstall.addEventListener('click', async () => {
    if (deferredPrompt) {
      deferredPrompt.prompt();
      const { outcome } = await deferredPrompt.userChoice;
      deferredPrompt = null;
      if (outcome === 'accepted') setInstallUI('installed');
    } else if (isIOS) {
      // Mostrar el install sheet del pwa_prompts.php si existe
      const sheet = document.getElementById('epl-install-sheet');
      const iosEl = document.getElementById('epis-ios');
      if (sheet && iosEl) {
        iosEl.style.display = 'flex';
        sheet.style.display = 'block';
      }
    }
  });

  window.addEventListener('appinstalled', () => setInstallUI('installed'));

  // ── Estado: notificaciones ─────────────────────────
  const notifStatus   = document.getElementById('notif-status');
  const btnNotif      = document.getElementById('pwa-btn-notif');
  const notifActive   = document.getElementById('notif-active-label');
  const notifDenied   = document.getElementById('notif-denied-label');
  const notifBlocked  = document.getElementById('notif-blocked-tip');

  function setNotifUI(state) {
    notifStatus.style.display = 'none';
    btnNotif.style.display    = 'none';
    notifActive.style.display = 'none';
    notifDenied.style.display = 'none';
    notifBlocked.style.display= 'none';
    if      (state === 'granted') notifActive.style.display = '';
    else if (state === 'denied')  { notifDenied.style.display = ''; notifBlocked.style.display = ''; }
    else                          btnNotif.style.display = ''; // 'default'
  }

  if (!('Notification' in window)) {
    notifStatus.style.display = 'none';
    notifDenied.style.display = '';
    notifDenied.textContent = '⚠️ No soportado';
  } else {
    setNotifUI(Notification.permission);
  }

  btnNotif.addEventListener('click', async () => {
    try {
      const perm = await Notification.requestPermission();
      setNotifUI(perm);
      if (perm === 'granted' && typeof window.eplSuscribirPush === 'function') {
        window.eplSuscribirPush();
      }
    } catch(e) { console.warn(e); }
  });

})();
</script>

<?php require_once 'includes/footer.php'; ?>
