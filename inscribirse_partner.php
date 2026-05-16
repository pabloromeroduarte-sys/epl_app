<?php
/**
 * Formulario de inscripción público — Partner (por token del Capitán)
 * Flujo: token en URL → completa perfil → paga → equipo confirmado
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

$db = epl_db();
epl_ensure_inscripciones_schema();
$error = '';

// Leer token
$token = preg_replace('/[^a-zA-Z0-9_-]/', '', $_GET['token'] ?? '');

if (!$token) {
    $page_title = 'Enlace inválido';
    require_once __DIR__ . '/includes/header.php';
    echo '<div style="padding:4rem 1rem;text-align:center"><h2 style="color:#1C2F48">Enlace no válido</h2><p>Este enlace de inscripción no existe o ya fue utilizado.</p></div>';
    require_once __DIR__ . '/includes/footer.php';
    exit;
}

// Buscar inscripción del capitán con ese token
$insc_cap = $db->prepare("
    SELECT i.*, l.nombre AS liga_nombre, l.precio,
           j.nombre AS cap_nombre, j.apellido AS cap_apellido
    FROM inscripciones i
    JOIN ligas l    ON l.id = i.liga_id
    JOIN jugadores j ON j.id = i.jugador_id
    WHERE i.token = ? AND i.rol_equipo = 'capitan'
    LIMIT 1
");
$insc_cap->execute([$token]);
$insc_cap = $insc_cap->fetch();

if (!$insc_cap) {
    $page_title = 'Enlace expirado';
    require_once __DIR__ . '/includes/header.php';
    echo '<div style="padding:4rem 1rem;text-align:center"><h2 style="color:#1C2F48">Enlace no encontrado</h2><p>Este enlace no existe, ya venció, o el equipo ya está completo.</p></div>';
    require_once __DIR__ . '/includes/footer.php';
    exit;
}

// Verificar si ya hay partner inscrito con este token
$equipo_cap = (int)($insc_cap['equipo_id'] ?? 0);
$ya_partner = $equipo_cap > 0
    ? $db->prepare("SELECT id FROM inscripciones WHERE equipo_id=? AND rol_equipo='partner' AND estado='aprobada' LIMIT 1")
    : null;
if ($ya_partner) {
    $ya_partner->execute([$equipo_cap]);
}
if ($ya_partner && $ya_partner->fetch()) {
    $page_title = 'Cupo ya tomado';
    require_once __DIR__ . '/includes/header.php';
    echo '<div style="padding:4rem 1rem;text-align:center"><h2 style="color:#1C2F48">Cupo ya confirmado</h2><p>Este equipo ya tiene un partner confirmado.</p></div>';
    require_once __DIR__ . '/includes/footer.php';
    exit;
}

$jugador_sesion = epl_jugador_actual();
if ($jugador_sesion && empty($_GET['pago'])) {
    $vinc = epl_vincular_partner_por_token_capitan($token, (int)$jugador_sesion['id']);
    if (!empty($vinc['ok']) && !empty($vinc['redirect'])) {
        header('Location: ' . $vinc['redirect']);
        exit;
    }
    if (empty($vinc['ok']) && !empty($vinc['error'])) {
        $error = $vinc['error'];
    }
}

// ── Retorno exitoso de MercadoPago ──────────────────────────────
if (isset($_GET['pago']) && $_GET['pago'] === 'exito') {
    $payment_id = isset($_GET['payment_id']) ? preg_replace('/[^a-zA-Z0-9_-]/', '', $_GET['payment_id']) : 'Gratis';
    $token_p    = preg_replace('/[^a-zA-Z0-9_-]/', '', $_GET['token_partner'] ?? '');

    if ($token_p) {
        epl_pago_completar($token_p, $payment_id);
        $db->prepare("UPDATE inscripciones SET pago_estado='pagado', pago_ref=? WHERE token=? AND rol_equipo='partner'")
           ->execute([$payment_id, $token_p]);
    }

    $page_title = '¡Equipo Confirmado!';
    require_once __DIR__ . '/includes/header.php';
    ?>
    <div style="min-height:80vh;display:flex;align-items:center;justify-content:center;padding:2rem 1rem;background:#f8fafc">
      <div style="background:#fff;border-radius:20px;box-shadow:0 4px 30px rgba(0,0,0,.08);padding:2.5rem;max-width:480px;width:100%;text-align:center">
        <div style="font-size:3.5rem;margin-bottom:1rem">🎾</div>
        <h1 style="font-size:1.5rem;font-weight:800;color:#1C2F48;text-transform:uppercase;margin:0 0 .75rem">¡Equipo Confirmado!</h1>
        <p style="color:#64748b;font-weight:600;margin-bottom:1.5rem">
          Tú y <strong><?= epl_h($insc_cap['cap_nombre'] . ' ' . $insc_cap['cap_apellido']) ?></strong>
          están inscritos en <strong><?= epl_h($insc_cap['liga_nombre']) ?></strong>.
        </p>
        <div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:12px;padding:1.25rem;margin-bottom:1.5rem;font-size:.88rem;color:#16a34a;font-weight:700">
          Cupo confirmado. Cuando tu capitán también valide, el equipo quedará listo para jugar.
        </div>
        <a href="<?= epl_url('dashboard.php') ?>" style="display:inline-block;background:#1C2F48;color:#C9A762;font-weight:800;font-size:.9rem;text-transform:uppercase;padding:.8rem 1.75rem;border-radius:10px;text-decoration:none">
          Ir a mi Dashboard
        </a>
      </div>
    </div>
    <?php
    require_once __DIR__ . '/includes/footer.php';
    exit;
}

// ── Retorno fallido ─────────────────────────────────────────────
if (isset($_GET['pago']) && $_GET['pago'] === 'fallo') {
    $error = 'El pago no pudo completarse. Intenta nuevamente.';
}

// ── Procesar formulario ─────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['epl_partner'])) {
    $nombre     = trim($_POST['nombre']    ?? '');
    $apellido   = trim($_POST['apellido']  ?? '');
    $rut        = trim($_POST['rut']       ?? '') ?: null;
    $telefono   = trim($_POST['telefono']  ?? '');
    $nivel      = (int)($_POST['nivel']    ?? 5);
    $lado       = in_array($_POST['lado']??'',['derecha','reves','ambos']) ? $_POST['lado'] : null;
    $email      = trim($_POST['email']     ?? '');
    $sexo       = in_array($_POST['sexo']??'',['M','F','otro']) ? $_POST['sexo'] : null;
    $fn         = trim($_POST['fecha_nacimiento'] ?? '') ?: null;
    $comuna     = trim($_POST['comuna']    ?? '') ?: null;
    $profesion  = trim($_POST['profesion'] ?? '') ?: null;
    $pala       = trim($_POST['pala']      ?? '') ?: null;
    $talla      = trim($_POST['talla']     ?? '') ?: null;
    $frecuencia = in_array($_POST['frecuencia_juego']??'',['1_semana','2_semana','3_o_mas','ocasional']) ? $_POST['frecuencia_juego'] : null;

    if (!$nombre || !$apellido || !$email || !$telefono) {
        $error = 'Nombre, apellido, email y WhatsApp son obligatorios.';
    } else {
        $liga_id = (int)$insc_cap['liga_id'];
        $precio  = (float)($insc_cap['precio'] ?? 0);

        // Si está logueado, usar su id; si no, buscar/crear por email (fila canónica)
        $email = strtolower(trim($email));
        $canon = epl_jugador_por_email($email, false);

        if (!$canon) {
            $db->prepare("INSERT INTO jugadores (email, password, nombre, apellido, rut, telefono, nivel, lado, sexo, fecha_nacimiento, comuna, profesion, pala, talla, frecuencia_juego, rol) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)")
               ->execute([$email, password_hash(bin2hex(random_bytes(8)), PASSWORD_DEFAULT), $nombre, $apellido, $rut, $telefono, $nivel, $lado, $sexo, $fn, $comuna, $profesion, $pala, $talla, $frecuencia, 'jugador']);
            $pid = (int)$db->lastInsertId();
        } else {
            $pid = (int)$canon['id'];
            $db->prepare("UPDATE jugadores SET nombre=?, apellido=?, rut=?, telefono=?, nivel=?, lado=?, sexo=?, fecha_nacimiento=?, comuna=?, profesion=?, pala=?, talla=?, frecuencia_juego=? WHERE id=?")
               ->execute([$nombre, $apellido, $rut, $telefono, $nivel, $lado, $sexo, $fn, $comuna, $profesion, $pala, $talla, $frecuencia, $pid]);
        }

        // Crear/buscar equipo con el capitán
        $cap_id = (int)$insc_cap['jugador_id'];
        $stEq   = $db->prepare("SELECT id FROM equipos WHERE (jugador1_id=? AND jugador2_id=?) OR (jugador1_id=? AND jugador2_id=?) LIMIT 1");
        $stEq->execute([$cap_id, $pid, $pid, $cap_id]);
        $equipo_id = $stEq->fetchColumn();

        if (!$equipo_id) {
            $cap_row = $db->prepare("SELECT apellido FROM jugadores WHERE id=?");
            $cap_row->execute([$cap_id]);
            $cap_ape = $cap_row->fetchColumn() ?? 'Capitán';
            $db->prepare("INSERT INTO equipos (nombre, jugador1_id, jugador2_id) VALUES (?,?,?)")
               ->execute([$cap_ape . ' / ' . ($apellido ?: $nombre), $cap_id, $pid]);
            $equipo_id = (int)$db->lastInsertId();
        }

        // Actualizar equipo en inscripción del capitán
        $db->prepare("UPDATE inscripciones SET equipo_id=? WHERE token=? AND rol_equipo='capitan'")->execute([$equipo_id, $token]);

        $token_partner = bin2hex(random_bytes(20));

        // Registrar inscripción del partner
        $db->prepare("INSERT INTO inscripciones (jugador_id, liga_id, equipo_id, rol_equipo, token, estado, pago_estado, pago_monto) VALUES (?,?,?,?,?,?,?,?)")
           ->execute([$pid, $liga_id, $equipo_id, 'partner', $token_partner, 'pendiente', 'pendiente', $precio ?: null]);
        $insc_id = (int)$db->lastInsertId();

        epl_notif_invitacion_partner(
            $pid,
            trim($insc_cap['cap_nombre'] . ' ' . $insc_cap['cap_apellido']),
            (string)$insc_cap['liga_nombre']
        );

        $pago_id = epl_pago_crear([
            'liga_id'      => $liga_id,
            'jugador_id'   => $pid,
            'inscripcion_id' => $insc_id,
            'concepto'     => 'Inscripción Partner EPL — ' . $insc_cap['liga_nombre'],
            'rol'          => 'partner',
            'monto'        => $precio,
            'estado'       => $precio > 0 ? 'pendiente' : 'completado',
            'metodo'       => $precio > 0 ? 'MercadoPago' : 'Gratis',
            'token_ref'    => $token_partner,
            'equipo_token' => $token,
        ]);

        $base_url     = epl_url('inscribirse_partner.php');
        $mp_token_cfg = epl_config_get('mp_access_token');

        if ($precio <= 0 || !$mp_token_cfg) {
            $db->prepare("UPDATE inscripciones SET pago_estado='pagado' WHERE id=?")->execute([$insc_id]);
            epl_pago_completar($token_partner, 'Gratis');
            header('Location: ' . $base_url . '?token=' . $token . '&pago=exito&token_partner=' . $token_partner);
            exit;
        }

        $body_mp = json_encode([
            'items' => [[
                'title'       => 'Inscripción Partner EPL — ' . $insc_cap['liga_nombre'],
                'quantity'    => 1,
                'unit_price'  => (int)$precio,
                'currency_id' => 'CLP',
            ]],
            'back_urls' => [
                'success' => $base_url . '?token=' . $token . '&pago=exito&token_partner=' . $token_partner,
                'failure' => $base_url . '?token=' . $token . '&pago=fallo',
                'pending' => $base_url . '?token=' . $token . '&pago=pendiente',
            ],
            'auto_return'        => 'approved',
            'external_reference' => $token_partner,
        ]);

        $ch = curl_init('https://api.mercadopago.com/checkout/preferences');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $body_mp,
            CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $mp_token_cfg, 'Content-Type: application/json'],
            CURLOPT_TIMEOUT        => 15,
        ]);
        $mp = json_decode(curl_exec($ch), true);
        curl_close($ch);

        if (isset($mp['init_point'])) {
            $db->prepare("UPDATE pagos SET mp_preference_id=? WHERE id=?")->execute([$mp['id'] ?? '', $pago_id]);
            header('Location: ' . $mp['init_point']);
            exit;
        } else {
            $error = 'Error al conectar con MercadoPago: ' . ($mp['message'] ?? 'Sin respuesta');
            $db->prepare("DELETE FROM inscripciones WHERE id=?")->execute([$insc_id]);
            $db->prepare("DELETE FROM pagos WHERE id=?")->execute([$pago_id]);
        }
    }
}

$page_title = 'Confirmar inscripción — Partner';
require_once __DIR__ . '/includes/header.php';

$jugador_actual = epl_jugador_actual();
?>

<div style="min-height:80vh;background:#f8fafc;padding:2rem 1rem">
  <div style="max-width:560px;margin:0 auto">

    <div style="text-align:center;margin-bottom:2rem">
      <div style="font-size:.75rem;font-weight:700;color:#C9A762;text-transform:uppercase;letter-spacing:.15em;margin-bottom:.5rem">Elite Padel League</div>
      <h1 style="font-size:1.7rem;font-weight:800;color:#1C2F48;text-transform:uppercase;margin:0 0 .5rem">Confirma tu inscripción</h1>
      <p style="color:#64748b;font-weight:500;font-size:.9rem">
        <strong><?= epl_h($insc_cap['cap_nombre'] . ' ' . $insc_cap['cap_apellido']) ?></strong>
        te invitó a jugar en <strong><?= epl_h($insc_cap['liga_nombre']) ?></strong>.
      </p>
      <?php if ($insc_cap['precio'] > 0): ?>
        <div style="display:inline-block;background:#1C2F48;color:#C9A762;font-weight:800;padding:.4rem 1rem;border-radius:8px;font-size:.85rem;margin-top:.5rem">
          Costo: $<?= number_format($insc_cap['precio'],0,',','.') ?> CLP
        </div>
      <?php else: ?>
        <div style="display:inline-block;background:#f0fdf4;color:#16a34a;font-weight:800;padding:.4rem 1rem;border-radius:8px;font-size:.85rem;margin-top:.5rem;border:1px solid #bbf7d0">
          ¡Inscripción gratuita!
        </div>
      <?php endif; ?>
    </div>

    <?php if ($error): ?>
      <div style="background:#fef2f2;border:1px solid #fecaca;color:#dc2626;padding:.9rem 1.1rem;border-radius:10px;font-weight:600;font-size:.88rem;margin-bottom:1.25rem"><?= epl_h($error) ?></div>
    <?php endif; ?>

    <?php if (!$jugador_actual): ?>
    <!-- Banner login si ya tiene cuenta -->
    <div style="background:#fff;border-radius:12px;border:1px solid #e2e8f0;padding:1rem 1.25rem;margin-bottom:1rem;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:.75rem">
      <div style="font-size:.85rem;color:#475569;font-weight:600">¿Ya tenés cuenta en Elite Padel League?</div>
      <a href="<?= epl_url('login.php?back=' . urlencode('inscribirse_partner.php?token=' . $token)) ?>"
         style="background:#1C2F48;color:#C9A762;font-weight:800;font-size:.82rem;text-transform:uppercase;padding:.55rem 1.1rem;border-radius:8px;text-decoration:none;letter-spacing:.04em;white-space:nowrap">
        Iniciar sesión →
      </a>
    </div>
    <?php else: ?>
    <div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:10px;padding:.75rem 1rem;margin-bottom:1rem;font-size:.83rem;color:#15803d;font-weight:700">
      ✓ Logueado como <?= epl_h($jugador_actual['nombre'].' '.$jugador_actual['apellido']) ?> — tus datos están pre-completados.
    </div>
    <?php endif; ?>

    <div style="background:#fff;border-radius:16px;box-shadow:0 2px 20px rgba(0,0,0,.06);padding:1.75rem">
      <form method="POST">
        <input type="hidden" name="epl_partner" value="1">

        <?php $f = fn(string $k) => epl_h($jugador_actual[$k] ?? ''); ?>

        <!-- Email -->
        <div style="margin-bottom:.85rem">
          <label style="font-size:.8rem;font-weight:700;color:#1C2F48;display:block;margin-bottom:.35rem">Email *</label>
          <input type="email" name="email" required value="<?= $f('email') ?>"
                 placeholder="tu@email.com" style="width:100%;padding:.65rem .85rem;border:1px solid #e2e8f0;border-radius:9px;font-size:.9rem"
                 <?= $jugador_actual ? 'readonly style="background:#f8fafc;width:100%;padding:.65rem .85rem;border:1px solid #e2e8f0;border-radius:9px;font-size:.9rem"' : '' ?>>
          <?php if (!$jugador_actual): ?>
          <p style="font-size:.73rem;color:#94a3b8;margin-top:.2rem">Si ya tenés cuenta, se actualizarán tus datos.</p>
          <?php endif; ?>
        </div>

        <!-- Nombre / Apellido -->
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:.75rem;margin-bottom:.85rem">
          <div>
            <label style="font-size:.8rem;font-weight:700;color:#1C2F48;display:block;margin-bottom:.35rem">Nombre *</label>
            <input type="text" name="nombre" required value="<?= $f('nombre') ?>" style="width:100%;padding:.65rem .85rem;border:1px solid #e2e8f0;border-radius:9px;font-size:.9rem">
          </div>
          <div>
            <label style="font-size:.8rem;font-weight:700;color:#1C2F48;display:block;margin-bottom:.35rem">Apellido *</label>
            <input type="text" name="apellido" required value="<?= $f('apellido') ?>" style="width:100%;padding:.65rem .85rem;border:1px solid #e2e8f0;border-radius:9px;font-size:.9rem">
          </div>
        </div>

        <!-- RUT / Fecha nacimiento -->
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:.75rem;margin-bottom:.85rem">
          <div>
            <label style="font-size:.8rem;font-weight:700;color:#1C2F48;display:block;margin-bottom:.35rem">RUT</label>
            <input type="text" name="rut" placeholder="12.345.678-9" value="<?= $f('rut') ?>" style="width:100%;padding:.65rem .85rem;border:1px solid #e2e8f0;border-radius:9px;font-size:.9rem">
          </div>
          <div>
            <label style="font-size:.8rem;font-weight:700;color:#1C2F48;display:block;margin-bottom:.35rem">Fecha de nacimiento</label>
            <input type="date" name="fecha_nacimiento" value="<?= $f('fecha_nacimiento') ?>" style="width:100%;padding:.65rem .85rem;border:1px solid #e2e8f0;border-radius:9px;font-size:.9rem">
          </div>
        </div>

        <!-- Sexo / Teléfono -->
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:.75rem;margin-bottom:.85rem">
          <div>
            <label style="font-size:.8rem;font-weight:700;color:#1C2F48;display:block;margin-bottom:.35rem">Sexo</label>
            <select name="sexo" style="width:100%;padding:.65rem .85rem;border:1px solid #e2e8f0;border-radius:9px;font-size:.9rem">
              <option value="">— Selecciona —</option>
              <option value="M"    <?= ($jugador_actual['sexo']??'')==='M'?'selected':'' ?>>Masculino</option>
              <option value="F"    <?= ($jugador_actual['sexo']??'')==='F'?'selected':'' ?>>Femenino</option>
              <option value="otro" <?= ($jugador_actual['sexo']??'')==='otro'?'selected':'' ?>>Otro</option>
            </select>
          </div>
          <div>
            <label style="font-size:.8rem;font-weight:700;color:#1C2F48;display:block;margin-bottom:.35rem">WhatsApp *</label>
            <input type="text" name="telefono" required placeholder="+56912345678" value="<?= $f('telefono') ?>" style="width:100%;padding:.65rem .85rem;border:1px solid #e2e8f0;border-radius:9px;font-size:.9rem">
          </div>
        </div>

        <!-- Comuna / Profesión -->
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:.75rem;margin-bottom:.85rem">
          <div>
            <label style="font-size:.8rem;font-weight:700;color:#1C2F48;display:block;margin-bottom:.35rem">Comuna</label>
            <input type="text" name="comuna" placeholder="Ej: Providencia" value="<?= $f('comuna') ?>" style="width:100%;padding:.65rem .85rem;border:1px solid #e2e8f0;border-radius:9px;font-size:.9rem">
          </div>
          <div>
            <label style="font-size:.8rem;font-weight:700;color:#1C2F48;display:block;margin-bottom:.35rem">Profesión</label>
            <input type="text" name="profesion" placeholder="Ej: Ingeniero" value="<?= $f('profesion') ?>" style="width:100%;padding:.65rem .85rem;border:1px solid #e2e8f0;border-radius:9px;font-size:.9rem">
          </div>
        </div>

        <div style="border-top:1px solid #f1f5f9;margin:1.1rem 0 1rem;padding-top:1rem">
          <div style="font-size:.78rem;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:.07em;margin-bottom:.85rem">Datos deportivos</div>

          <!-- Nivel / Lado -->
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:.75rem;margin-bottom:.85rem">
            <div>
              <label style="font-size:.8rem;font-weight:700;color:#1C2F48;display:block;margin-bottom:.35rem">Nivel de juego (1-7)</label>
              <input type="number" name="nivel" min="1" max="7" value="<?= (int)($jugador_actual['nivel'] ?? 5) ?>" style="width:100%;padding:.65rem .85rem;border:1px solid #e2e8f0;border-radius:9px;font-size:.9rem">
            </div>
            <div>
              <label style="font-size:.8rem;font-weight:700;color:#1C2F48;display:block;margin-bottom:.35rem">Lado en cancha</label>
              <select name="lado" style="width:100%;padding:.65rem .85rem;border:1px solid #e2e8f0;border-radius:9px;font-size:.9rem">
                <option value="">— Selecciona —</option>
                <option value="derecha" <?= ($jugador_actual['lado']??'')==='derecha'?'selected':'' ?>>Derecha</option>
                <option value="reves"   <?= ($jugador_actual['lado']??'')==='reves'?'selected':'' ?>>Revés</option>
                <option value="ambos"   <?= ($jugador_actual['lado']??'')==='ambos'?'selected':'' ?>>Ambos</option>
              </select>
            </div>
          </div>

          <!-- Pala / Talla -->
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:.75rem;margin-bottom:.85rem">
            <div>
              <label style="font-size:.8rem;font-weight:700;color:#1C2F48;display:block;margin-bottom:.35rem">Marca de pala</label>
              <input type="text" name="pala" placeholder="Ej: Babolat, Head..." value="<?= $f('pala') ?>" style="width:100%;padding:.65rem .85rem;border:1px solid #e2e8f0;border-radius:9px;font-size:.9rem">
            </div>
            <div>
              <label style="font-size:.8rem;font-weight:700;color:#1C2F48;display:block;margin-bottom:.35rem">Talla de camiseta</label>
              <select name="talla" style="width:100%;padding:.65rem .85rem;border:1px solid #e2e8f0;border-radius:9px;font-size:.9rem">
                <option value="">— Selecciona —</option>
                <?php foreach (['XS','S','M','L','XL','XXL'] as $t): ?>
                  <option value="<?= $t ?>" <?= ($jugador_actual['talla']??'')===$t?'selected':'' ?>><?= $t ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>

          <!-- Frecuencia de juego -->
          <div style="margin-bottom:.85rem">
            <label style="font-size:.8rem;font-weight:700;color:#1C2F48;display:block;margin-bottom:.35rem">Frecuencia de juego</label>
            <select name="frecuencia_juego" style="width:100%;padding:.65rem .85rem;border:1px solid #e2e8f0;border-radius:9px;font-size:.9rem">
              <option value="">— Selecciona —</option>
              <option value="1_semana"   <?= ($jugador_actual['frecuencia_juego']??'')==='1_semana'?'selected':'' ?>>1 vez por semana</option>
              <option value="2_semana"   <?= ($jugador_actual['frecuencia_juego']??'')==='2_semana'?'selected':'' ?>>2 veces por semana</option>
              <option value="3_o_mas"    <?= ($jugador_actual['frecuencia_juego']??'')==='3_o_mas'?'selected':'' ?>>3 o más veces</option>
              <option value="ocasional"  <?= ($jugador_actual['frecuencia_juego']??'')==='ocasional'?'selected':'' ?>>Ocasional</option>
            </select>
          </div>
        </div>

        <button type="submit" style="width:100%;padding:.9rem;background:#1C2F48;color:#C9A762;font-weight:800;font-size:.95rem;text-transform:uppercase;border:none;border-radius:12px;cursor:pointer;letter-spacing:.04em">
          <?= $insc_cap['precio'] > 0 ? 'Confirmar e ir a pagar →' : 'Confirmar mi inscripción →' ?>
        </button>
      </form>
    </div>

  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
