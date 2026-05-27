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
           'nivel','lado','pala','talla','frecuencia_juego'];
$val    = array_fill_keys($campos, '');
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

$comunas = [
    'Alhué','Alto Biobío','Alto del Carmen','Alto Hospicio','Andacollo','Angol',
    'Antártica','Antofagasta','Arauco','Arica','Aysén','Buin','Bulnes',
    'Cabo de Hornos','Cabildo','Calama','Caldera','Calera','Calera de Tango',
    'Camarones','Camiña','Cañete','Carahue','Cartagena','Castro','Catemu',
    'Cauquenes','Chanco','Chañaral','Chaitén','Chépica','Chile Chico',
    'Chiguayante','Chillán','Chillán Viejo','Chimbarongo','Cholchol',
    'Cisnes','Cobquecura','Cochrane','Codegua','Coelemu','Coihueco','Coihaique',
    'Coinco','Colbún','Colchane','Colina','Coltauco','Combarbalá','Concepción',
    'Conchalí','Concón','Constitución','Contulmo','Coquimbo','Coronel','Corral',
    'Cunco','Curacaví','Curacautín','Curanilahue','Curarrehue','Curepto','Curicó',
    'Curaco de Vélez','Dalcahue','Diego de Almagro','Doñihue',
    'El Bosque','El Carmen','El Monte','El Quisco','El Tabo',
    'Empedrado','Ercilla','Estación Central','Florida','Freire','Fresia',
    'Freirina','Frutillar','Futrono','Futaleufú','Galvarino','General Lagos',
    'Gorbea','Graneros','Guaitecas','Hijuelas','Hualaihué','Hualañé',
    'Hualqui','Hualpén','Huara','Huechuraba','Illapel','Independencia',
    'Iquique','Isla de Maipo','Isla de Pascua','Juan Fernández',
    'La Cisterna','La Cruz','La Estrella','La Florida','La Granja',
    'La Higuera','La Ligua','La Pintana','La Reina','La Serena','La Unión',
    'Lago Ranco','Lago Verde','Laguna Blanca','Laja','Lampa','Lanco',
    'Las Cabras','Las Condes','Lautaro','Lebu','Licantén','Limache',
    'Linares','Llaillay','Lo Barnechea','Lo Espejo','Lo Prado',
    'Lolol','Loncoche','Lonquimay','Los Álamos','Los Ángeles','Los Lagos',
    'Los Muermos','Los Sauces','Los Vilos','Lota','Llanquihue','Lumaco',
    'Machalí','Macul','Maipú','Malloa','María Elena','María Pinto',
    'Mariquina','Maule','Maullín','Máfil','Mejillones','Melipeuco',
    'Melipilla','Monte Patria','Mostazal','Mulchén','Nacimiento','Nancagua',
    'Natales','Navidad','Negrete','Ninhue','Nogales','Nueva Imperial',
    'Ñiquén','Ñuñoa','O\'Higgins','Olivar','Olmué','Osorno','Ovalle',
    'Padre Hurtado','Padre las Casas','Paillaco','Paihuano','Palena',
    'Palmilla','Panquehue','Panguipulli','Papudo','Paredones','Parral',
    'Pedro Aguirre Cerda','Pelarco','Pelluhue','Pemuco','Pencahue','Penco',
    'Peñaflor','Peñalolén','Peralillo','Perquenco','Petorca','Peumo',
    'Pichidegua','Pichilemu','Pinto','Pirque','Pitrufquén','Placilla',
    'Porvenir','Pozo Almonte','Primavera','Providencia','Puchuncaví',
    'Pucón','Pudahuel','Puente Alto','Pumanque','Punitaqui','Punta Arenas',
    'Puqueldón','Puerto Montt','Puerto Octay','Puerto Varas',
    'Purén','Purranque','Putaendo','Puyehue',
    'Queilén','Quellón','Quemchi','Quilaco','Quilicura','Quilleco',
    'Quillón','Quillota','Quinchao','Quinta Normal','Quinta de Tilcoco',
    'Quintero','Quirihue','Rancagua','Ránquil','Rauco','Recoleta',
    'Renca','Renaico','Rengo','Requínoa','Retiro','Río Bueno','Río Claro',
    'Río Hurtado','Río Ibáñez','Río Negro','Río Verde','Romeral',
    'Saavedra','Sagrada Familia','Salamanca','San Bernardo','San Carlos',
    'San Clemente','San Esteban','San Fabián','San Felipe','San Fernando',
    'San Gregorio','San Ignacio','San Javier','San José de Maipo',
    'San Juan de la Costa','San Miguel','San Nicolás','San Pablo',
    'San Pedro','San Pedro de Atacama','San Pedro de la Paz','San Rafael',
    'San Ramón','San Rosendo','San Vicente',
    'Santa Bárbara','Santa Cruz','Santa Juana','Santa María','Santo Domingo',
    'Santiago','Sierra Gorda','Talca','Talcahuano','Talagante','Taltal',
    'Temuco','Teno','Teodoro Schmidt','Tierra Amarilla','Tiltil','Timaukel',
    'Tirúa','Tocopilla','Toltén','Tomé','Torres del Paine','Tortel',
    'Traiguén','Trehuaco','Tucapel','Valdivia','Vallenar','Valparaíso',
    'Victoria','Vichuquén','Vicuña','Vilcún','Villa Alegre','Villarrica',
    'Viña del Mar','Vitacura','Yerbas Buenas','Yumbel','Yungay','Zapallar',
];

$marcas_pala = [
    'Adidas','Babolat','Black Crown','Bullpadel','Dunlop','Head',
    'Kuikma','NOX','Puma','Prince','Royal Padel','Shark Padel',
    'Siux','Starvie','Varlion','Vibor-A','Volt Padel','Wilson','Otra',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = strtolower(trim($_POST['email']    ?? ''));
    $password = $_POST['password']         ?? '';
    $confirma = $_POST['password_confirma'] ?? '';
    $nombre   = ucwords(mb_strtolower(trim($_POST['nombre']   ?? ''), 'UTF-8'));
    $apellido = ucwords(mb_strtolower(trim($_POST['apellido'] ?? ''), 'UTF-8'));
    $alias    = trim($_POST['alias']  ?? '') ?: null;
    $rut      = trim($_POST['rut']    ?? '') ?: null;
    $telefono = trim($_POST['telefono'] ?? '') ?: null;
    $fnac     = trim($_POST['fecha_nacimiento'] ?? '') ?: null;
    $sexo     = in_array($_POST['sexo'] ?? '', ['M','F','otro']) ? $_POST['sexo'] : null;
    $comuna   = trim($_POST['comuna']   ?? '') ?: null;
    $profesion= trim($_POST['profesion']?? '') ?: null;
    $pala     = trim($_POST['pala']     ?? '') ?: null;
    $talla    = in_array($_POST['talla'] ?? '', ['XS','S','M','L','XL','XXL','XXXL'])
                ? $_POST['talla'] : null;
    $frec     = in_array($_POST['frecuencia_juego'] ?? '',
                    ['1_semana','2_semana','3_o_mas','ocasional'])
                ? $_POST['frecuencia_juego'] : null;

    $max_nivel = ($sexo === 'F') ? 4 : 6;
    $def_nivel = ($sexo === 'F') ? 2 : 5;
    $nivel = max(1, min($max_nivel, (int)($_POST['nivel'] ?? $def_nivel)));

    $lado = in_array($_POST['lado'] ?? '', ['derecha','reves','ambos'])
            ? $_POST['lado'] : null;

    if (!$nombre) {
        $error = 'El nombre es obligatorio.';
    } elseif (!$apellido) {
        $error = 'El apellido es obligatorio.';
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
            $db->prepare("
                INSERT INTO jugadores
                    (email, password, nombre, apellido, alias, rut, telefono,
                     fecha_nacimiento, sexo, comuna, profesion, nivel, lado, pala,
                     talla, frecuencia_juego, estado, rol)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,'activo','jugador')"
            )->execute([
                $email, epl_hash_password($password),
                $nombre, $apellido, $alias, $rut, $telefono,
                $fnac, $sexo, $comuna, $profesion, $nivel, $lado, $pala,
                $talla, $frec,
            ]);

            // Correo de bienvenida automático
            try {
                require_once __DIR__ . '/includes/mail.php';
                require_once __DIR__ . '/includes/mail_automations.php';
                epl_mail_bienvenida([
                    'nombre'   => $nombre,
                    'apellido' => $apellido,
                    'email'    => $email,
                ]);
            } catch (Throwable $e) {
                error_log('epl_mail_bienvenida: ' . $e->getMessage());
            }

            epl_login($email, $password);
            header('Location: dashboard.php?bienvenido=1');
            exit;
        }
    }
}

// Pre-split saved phone into country code + number for repopulation
$tel_cc  = '+56';
$tel_num = '';
if ($val['telefono']) {
    if (preg_match('/^(\+\d{1,3})\s*(.*)$/', $val['telefono'], $m)) {
        $tel_cc  = $m[1];
        $tel_num = trim($m[2]);
    } else {
        $tel_num = $val['telefono'];
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
    .tel-wrap { display:flex; gap:.5rem }
    .tel-wrap select { width:100px; flex-shrink:0 }
    .tel-wrap input  { flex:1; min-width:0 }
  </style>
</head>
<body>
<div class="login-page">

  <!-- Izquierda — marca -->
  <div class="login-left">
    <div class="login-left-bg" style="background-image:url('<?= epl_url('assets/img/landing/hero-padel.jpg') ?>')"></div>
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
        <div class="alert alert-error"><?= $error ?></div>
      <?php endif; ?>

      <form method="post" id="regForm">

        <!-- ── Acceso ───────────────────────── -->
        <p class="section-label" style="font-size:.7rem;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:var(--navy);margin:.5rem 0 .75rem">Acceso</p>

        <div class="form-group">
          <label class="form-label" for="email">Email *</label>
          <input type="email" name="email" id="email" class="form-control"
                 value="<?= epl_h($val['email']) ?>"
                 placeholder="tucorreo@ejemplo.com" required autofocus autocomplete="email">
        </div>

        <div class="grid-2">
          <div class="form-group">
            <label class="form-label" for="password">Contraseña *</label>
            <input type="password" name="password" id="password" class="form-control"
                   placeholder="Mínimo 8 caracteres" required minlength="8" autocomplete="new-password">
          </div>
          <div class="form-group">
            <label class="form-label" for="password_confirma">Confirmar *</label>
            <input type="password" name="password_confirma" id="password_confirma" class="form-control"
                   placeholder="Repite la contraseña" required autocomplete="new-password">
          </div>
        </div>

        <!-- ── Datos personales ────────────── -->
        <p style="font-size:.7rem;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:var(--navy);margin:1.25rem 0 .75rem;border-top:1px solid var(--gray-200);padding-top:1rem">Datos personales</p>

        <div class="grid-2">
          <div class="form-group">
            <label class="form-label" for="nombre">Nombre *</label>
            <input type="text" name="nombre" id="nombre" class="form-control cap-words"
                   value="<?= epl_h($val['nombre']) ?>"
                   autocapitalize="words" autocomplete="given-name" required>
          </div>
          <div class="form-group">
            <label class="form-label" for="apellido">Apellido *</label>
            <input type="text" name="apellido" id="apellido" class="form-control cap-words"
                   value="<?= epl_h($val['apellido']) ?>"
                   autocapitalize="words" autocomplete="family-name" required>
          </div>
        </div>

        <div class="grid-2">
          <div class="form-group">
            <label class="form-label" for="alias">Alias / Apodo</label>
            <input type="text" name="alias" id="alias" class="form-control"
                   value="<?= epl_h($val['alias']) ?>" placeholder="Opcional" autocapitalize="words">
          </div>
          <div class="form-group">
            <label class="form-label" for="rut">RUT</label>
            <input type="text" name="rut" id="rut" class="form-control"
                   value="<?= epl_h($val['rut']) ?>" placeholder="11.111.111-1"
                   maxlength="12" autocomplete="off" inputmode="numeric">
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
          <label class="form-label" for="tel_num">Teléfono / WhatsApp</label>
          <div class="tel-wrap">
            <select id="tel_cc" class="form-control" aria-label="Código de país">
              <option value="+56" <?= $tel_cc==='+56'?'selected':'' ?>>🇨🇱 +56</option>
              <option value="+54" <?= $tel_cc==='+54'?'selected':'' ?>>🇦🇷 +54</option>
              <option value="+55" <?= $tel_cc==='+55'?'selected':'' ?>>🇧🇷 +55</option>
              <option value="+51" <?= $tel_cc==='+51'?'selected':'' ?>>🇵🇪 +51</option>
              <option value="+57" <?= $tel_cc==='+57'?'selected':'' ?>>🇨🇴 +57</option>
              <option value="+34" <?= $tel_cc==='+34'?'selected':'' ?>>🇪🇸 +34</option>
              <option value="+1"  <?= $tel_cc==='+1' ?'selected':'' ?>>🇺🇸 +1</option>
            </select>
            <input type="tel" id="tel_num" class="form-control"
                   value="<?= epl_h($tel_num) ?>"
                   placeholder="9 1234 5678" inputmode="tel" autocomplete="tel-national">
          </div>
          <input type="hidden" name="telefono" id="telefono" value="<?= epl_h($val['telefono']) ?>">
          <span class="form-hint" id="tel_hint">Formato Chile: 9 XXXX XXXX</span>
        </div>

        <div class="grid-2">
          <div class="form-group">
            <label class="form-label" for="comuna">Comuna</label>
            <input type="text" name="comuna" id="comuna" class="form-control"
                   value="<?= epl_h($val['comuna']) ?>"
                   list="lista_comunas" placeholder="Escribe tu comuna..." autocomplete="off">
            <datalist id="lista_comunas">
              <?php foreach ($comunas as $c): ?>
                <option value="<?= epl_h($c) ?>">
              <?php endforeach; ?>
            </datalist>
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
            <label class="form-label" for="nivel">Categoría</label>
            <select name="nivel" id="nivel" class="form-control">
              <!-- Options inserted by JS based on sexo -->
            </select>
            <span class="form-hint" id="nivel_hint"></span>
          </div>
          <div class="form-group">
            <label class="form-label" for="lado">Lado de juego</label>
            <select name="lado" id="lado" class="form-control">
              <option value="">— No definido —</option>
              <option value="derecha" <?= $val['lado']==='derecha'?'selected':'' ?>>Drive</option>
              <option value="reves"   <?= $val['lado']==='reves'  ?'selected':'' ?>>Revés</option>
              <option value="ambos"   <?= $val['lado']==='ambos'  ?'selected':'' ?>>Ambos</option>
            </select>
          </div>
        </div>

        <div class="grid-2">
          <div class="form-group">
            <label class="form-label" for="pala">Marca de pala</label>
            <input type="text" name="pala" id="pala" class="form-control"
                   value="<?= epl_h($val['pala']) ?>"
                   list="lista_palas" placeholder="Marca / Modelo" autocomplete="off">
            <datalist id="lista_palas">
              <?php foreach ($marcas_pala as $mp): ?>
                <option value="<?= epl_h($mp) ?>">
              <?php endforeach; ?>
            </datalist>
          </div>
          <div class="form-group">
            <label class="form-label" for="talla">Talla de camiseta</label>
            <select name="talla" id="talla" class="form-control">
              <option value="">— No indicar —</option>
              <?php foreach (['XS','S','M','L','XL','XXL','XXXL'] as $t): ?>
                <option value="<?= $t ?>" <?= $val['talla']===$t?'selected':'' ?>><?= $t ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <div class="form-group">
          <label class="form-label" for="frecuencia_juego">Frecuencia de juego</label>
          <select name="frecuencia_juego" id="frecuencia_juego" class="form-control">
            <option value="">— No indicar —</option>
            <option value="1_semana"  <?= $val['frecuencia_juego']==='1_semana' ?'selected':'' ?>>1 vez por semana</option>
            <option value="2_semana"  <?= $val['frecuencia_juego']==='2_semana' ?'selected':'' ?>>2 veces por semana</option>
            <option value="3_o_mas"   <?= $val['frecuencia_juego']==='3_o_mas'  ?'selected':'' ?>>3 o más por semana</option>
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

<script>
// ── Capitalizar primera letra de cada palabra ─────────────────────────────────
document.querySelectorAll('.cap-words').forEach(function(el) {
  el.addEventListener('input', function() {
    var pos  = this.selectionStart;
    var val  = this.value;
    var formatted = val.replace(/\b\w/g, function(c) { return c.toUpperCase(); });
    if (formatted !== val) {
      this.value = formatted;
      this.setSelectionRange(pos, pos);
    }
  });
});

// ── RUT: formato XX.XXX.XXX-X ────────────────────────────────────────────────
(function() {
  var rutInput = document.getElementById('rut');
  if (!rutInput) return;

  function formatRut(raw) {
    var clean = raw.replace(/[^0-9kK]/g, '').toUpperCase();
    if (clean.length < 2) return clean;
    var dv   = clean.slice(-1);
    var body = clean.slice(0, -1);
    var fmt  = '';
    for (var i = body.length - 1, cnt = 0; i >= 0; i--, cnt++) {
      if (cnt > 0 && cnt % 3 === 0) fmt = '.' + fmt;
      fmt = body[i] + fmt;
    }
    return fmt + '-' + dv;
  }

  rutInput.addEventListener('input', function() {
    var pos = this.selectionStart;
    var old = this.value;
    var fmt = formatRut(old);
    if (fmt !== old) {
      var added = fmt.length - old.length;
      this.value = fmt;
      this.setSelectionRange(pos + added, pos + added);
    }
  });

  rutInput.addEventListener('blur', function() {
    this.value = formatRut(this.value);
  });
})();

// ── Teléfono: formato +56 9 XXXX XXXX ────────────────────────────────────────
(function() {
  var ccSel   = document.getElementById('tel_cc');
  var numInp  = document.getElementById('tel_num');
  var hidden  = document.getElementById('telefono');
  var hint    = document.getElementById('tel_hint');
  if (!ccSel || !numInp || !hidden) return;

  function formatChilean(digits) {
    if (!digits) return '';
    digits = digits.replace(/\D/g, '');
    // Strip leading country code if accidentally typed
    if (digits.startsWith('569')) digits = digits.slice(2);
    else if (digits.startsWith('56')) digits = digits.slice(2);
    if (!digits) return '';
    var first = digits.slice(0, 1);
    var rest  = digits.slice(1, 9);
    var formatted = first;
    if (rest.length > 0) formatted += ' ' + rest.slice(0, 4);
    if (rest.length > 4) formatted += ' ' + rest.slice(4, 8);
    return formatted;
  }

  function updateHidden() {
    var cc  = ccSel.value;
    var num = numInp.value.trim();
    hidden.value = num ? (cc + ' ' + num) : '';
  }

  function onNumInput() {
    var cc = ccSel.value;
    if (cc === '+56') {
      var pos  = numInp.selectionStart;
      var raw  = numInp.value;
      var fmt  = formatChilean(raw);
      if (fmt !== raw) {
        var diff = fmt.length - raw.length;
        numInp.value = fmt;
        numInp.setSelectionRange(pos + diff, pos + diff);
      }
    }
    updateHidden();
  }

  function onCcChange() {
    var cc = ccSel.value;
    if (cc === '+56') {
      hint.textContent = 'Formato: 9 XXXX XXXX';
      numInp.placeholder = '9 1234 5678';
    } else {
      hint.textContent = 'Ingresa el número sin el código de país';
      numInp.placeholder = 'Número de teléfono';
    }
    updateHidden();
  }

  numInp.addEventListener('input',  onNumInput);
  numInp.addEventListener('blur',   function() { onNumInput(); });
  ccSel .addEventListener('change', onCcChange);

  // Initialize
  onCcChange();
  updateHidden();
})();

// ── Categoría dinámica según sexo ─────────────────────────────────────────────
(function() {
  var sexoSel  = document.getElementById('sexo');
  var nivelSel = document.getElementById('nivel');
  var nivelHint = document.getElementById('nivel_hint');
  if (!sexoSel || !nivelSel) return;

  var savedNivel = <?= json_encode($val['nivel'] ?: '') ?>;

  var catsMasc = [
    ['1','1ª categoría'],['2','2ª categoría'],['3','3ª categoría'],
    ['4','4ª categoría'],['5','5ª categoría'],['6','6ª categoría'],
  ];
  var catsFem = [
    ['1','Categoría A'],['2','Categoría B'],['3','Categoría C'],['4','Categoría D'],
  ];

  function buildCats() {
    var sexo = sexoSel.value;
    var cats = (sexo === 'F') ? catsFem : catsMasc;
    var def  = (sexo === 'F') ? '2' : '5';
    var cur  = savedNivel || nivelSel.value || def;
    nivelSel.innerHTML = '';
    cats.forEach(function(c) {
      var opt = document.createElement('option');
      opt.value = c[0];
      opt.textContent = c[1];
      if (c[0] === cur) opt.selected = true;
      nivelSel.appendChild(opt);
    });
    // If cur not found, select default
    if (!nivelSel.value) nivelSel.value = def;

    if (sexo === 'F') {
      nivelHint.textContent = 'A = nivel más alto, D = nivel inicial';
    } else {
      nivelHint.textContent = '1ª = nivel más alto, 6ª = nivel inicial';
    }
  }

  sexoSel.addEventListener('change', function() {
    savedNivel = ''; // reset saved on manual change
    buildCats();
  });

  buildCats();
})();

// ── Submit: asegurar que telefono hidden tenga valor ─────────────────────────
document.getElementById('regForm').addEventListener('submit', function() {
  var ccSel  = document.getElementById('tel_cc');
  var numInp = document.getElementById('tel_num');
  var hidden = document.getElementById('telefono');
  if (ccSel && numInp && hidden && numInp.value.trim()) {
    hidden.value = ccSel.value + ' ' + numInp.value.trim();
  }
});
</script>

</body>
</html>
