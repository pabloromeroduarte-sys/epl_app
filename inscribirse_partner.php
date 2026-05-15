<?php
/**
 * Formulario de inscripción público — Partner (por token del Capitán)
 * Flujo: token en URL → completa perfil → paga → equipo confirmado
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

$db    = epl_db();
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
$ya_partner = $db->prepare("SELECT id FROM inscripciones WHERE token=? AND rol_equipo='partner'");
$ya_partner->execute([$token]);
if ($ya_partner->fetch()) {
    $page_title = 'Cupo ya tomado';
    require_once __DIR__ . '/includes/header.php';
    echo '<div style="padding:4rem 1rem;text-align:center"><h2 style="color:#1C2F48">Cupo ya registrado</h2><p>Este enlace ya fue utilizado por otro jugador.</p></div>';
    require_once __DIR__ . '/includes/footer.php';
    exit;
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
          El organizador revisará tu inscripción y te confirmará por WhatsApp.
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
    $nombre   = trim($_POST['nombre']   ?? '');
    $apellido = trim($_POST['apellido'] ?? '');
    $rut      = trim($_POST['rut']      ?? '');
    $telefono = trim($_POST['telefono'] ?? '');
    $nivel    = (int)($_POST['nivel']   ?? 5);
    $lado     = in_array($_POST['lado']??'',['derecha','reves','ambos']) ? $_POST['lado'] : null;
    $email    = trim($_POST['email']    ?? '');

    if (!$nombre || !$email) {
        $error = 'Nombre y email son obligatorios.';
    } else {
        $liga_id = (int)$insc_cap['liga_id'];
        $precio  = (float)($insc_cap['precio'] ?? 0);

        // Buscar jugador por email, crear si no existe
        $jug_st = $db->prepare("SELECT id FROM jugadores WHERE email=?");
        $jug_st->execute([$email]);
        $pid = $jug_st->fetchColumn();

        if (!$pid) {
            $db->prepare("INSERT INTO jugadores (email, password, nombre, apellido, rut, telefono, nivel, lado, rol) VALUES (?,?,?,?,?,?,?,?,?)")
               ->execute([$email, password_hash(bin2hex(random_bytes(8)), PASSWORD_DEFAULT), $nombre, $apellido, $rut ?: null, $telefono ?: null, $nivel, $lado, 'jugador']);
            $pid = (int)$db->lastInsertId();
        } else {
            $db->prepare("UPDATE jugadores SET nombre=?, apellido=?, rut=?, telefono=?, nivel=?, lado=? WHERE id=?")
               ->execute([$nombre, $apellido, $rut ?: null, $telefono ?: null, $nivel, $lado, $pid]);
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

// Verificar si necesita login o no
$jugador_actual = null;
if (epl_esta_logueado()) {
    $jugador_actual = epl_jugador_actual();
}
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
      <div class="alert alert-error" style="margin-bottom:1.25rem"><?= epl_h($error) ?></div>
    <?php endif; ?>

    <div style="background:#fff;border-radius:16px;box-shadow:0 2px 20px rgba(0,0,0,.06);padding:1.75rem">
      <form method="POST">
        <input type="hidden" name="epl_partner" value="1">

        <h3 style="font-size:.85rem;font-weight:700;color:var(--navy, #1C2F48);text-transform:uppercase;letter-spacing:.05em;margin:0 0 1rem">Tus datos</h3>

        <div style="margin-bottom:.85rem">
          <label style="font-size:.8rem;font-weight:700;color:#1C2F48;display:block;margin-bottom:.35rem">Email *</label>
          <input type="email" name="email" required value="<?= epl_h($jugador_actual['email'] ?? '') ?>"
                 placeholder="tu@email.com" style="width:100%;padding:.65rem .85rem;border:1px solid #e2e8f0;border-radius:9px;font-size:.9rem">
          <p style="font-size:.73rem;color:#94a3b8;margin-top:.25rem">Si ya tienes cuenta se actualizarán tus datos.</p>
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:.75rem;margin-bottom:.85rem">
          <div>
            <label style="font-size:.8rem;font-weight:700;color:#1C2F48;display:block;margin-bottom:.35rem">Nombre *</label>
            <input type="text" name="nombre" required value="<?= epl_h($jugador_actual['nombre'] ?? '') ?>" style="width:100%;padding:.65rem .85rem;border:1px solid #e2e8f0;border-radius:9px;font-size:.9rem">
          </div>
          <div>
            <label style="font-size:.8rem;font-weight:700;color:#1C2F48;display:block;margin-bottom:.35rem">Apellido</label>
            <input type="text" name="apellido" value="<?= epl_h($jugador_actual['apellido'] ?? '') ?>" style="width:100%;padding:.65rem .85rem;border:1px solid #e2e8f0;border-radius:9px;font-size:.9rem">
          </div>
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:.75rem;margin-bottom:.85rem">
          <div>
            <label style="font-size:.8rem;font-weight:700;color:#1C2F48;display:block;margin-bottom:.35rem">RUT</label>
            <input type="text" name="rut" placeholder="12.345.678-9" value="<?= epl_h($jugador_actual['rut'] ?? '') ?>" style="width:100%;padding:.65rem .85rem;border:1px solid #e2e8f0;border-radius:9px;font-size:.9rem">
          </div>
          <div>
            <label style="font-size:.8rem;font-weight:700;color:#1C2F48;display:block;margin-bottom:.35rem">WhatsApp</label>
            <input type="text" name="telefono" placeholder="+56912345678" value="<?= epl_h($jugador_actual['telefono'] ?? '') ?>" style="width:100%;padding:.65rem .85rem;border:1px solid #e2e8f0;border-radius:9px;font-size:.9rem">
          </div>
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:.75rem;margin-bottom:1.25rem">
          <div>
            <label style="font-size:.8rem;font-weight:700;color:#1C2F48;display:block;margin-bottom:.35rem">Nivel (1-7)</label>
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

        <button type="submit" style="width:100%;padding:.85rem;background:#1C2F48;color:#C9A762;font-weight:800;font-size:.95rem;text-transform:uppercase;border:none;border-radius:12px;cursor:pointer">
          <?= $insc_cap['precio'] > 0 ? 'Confirmar e ir a pagar →' : 'Confirmar mi inscripción →' ?>
        </button>
      </form>
    </div>

  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
