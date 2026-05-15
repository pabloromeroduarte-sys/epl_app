<?php
/**
 * Formulario de inscripción público — Capitán
 * Flujo: selecciona liga → completa perfil → paga vía MercadoPago (o gratis) → link partner
 */
$page_title = 'Inscribirse a la Liga';
$player_tab = 'inscribirse';
require_once 'includes/auth.php';
require_once 'includes/functions.php';
epl_require_login();

$jugador = epl_jugador_actual();
$db      = epl_db();

// Ligas en inscripción o activas
$ligas = $db->query("SELECT * FROM ligas WHERE estado IN ('inscripcion','activa') ORDER BY id DESC")->fetchAll();

$ok    = '';
$error = '';

// ── Retorno exitoso de MercadoPago ──────────────────────────────
if (isset($_GET['pago']) && $_GET['pago'] === 'exito' && isset($_GET['token'])) {
    $token_ret  = preg_replace('/[^a-zA-Z0-9_-]/', '', $_GET['token']);
    $payment_id = isset($_GET['payment_id']) ? preg_replace('/[^a-zA-Z0-9_-]/', '', $_GET['payment_id']) : 'Gratis';

    epl_pago_completar($token_ret, $payment_id);
    $db->prepare("UPDATE inscripciones SET pago_estado='pagado', pago_ref=? WHERE token=?")
       ->execute([$payment_id, $token_ret]);

    $insc_row = $db->prepare("SELECT i.*, l.nombre AS liga_nombre FROM inscripciones i JOIN ligas l ON l.id=i.liga_id WHERE i.token=?");
    $insc_row->execute([$token_ret]);
    $insc_row = $insc_row->fetch();

    $url_partner = epl_url('inscribirse_partner.php?token=' . urlencode($token_ret));
    $txt_wsp     = urlencode("🎾 ¡Hola! Ya pagué mi inscripción en Elite Padel League y te elegí como mi partner.\n\nIngresa al link, completa tus datos y confirma nuestro cupo:\n\n" . $url_partner);

    $page_title = '¡Inscripción Confirmada!';
    require_once 'includes/header.php';
    ?>
    <div style="min-height:80vh;display:flex;align-items:center;justify-content:center;padding:2rem 1rem;background:#f8fafc">
      <div style="background:#fff;border-radius:20px;box-shadow:0 4px 30px rgba(0,0,0,.08);padding:2.5rem;max-width:520px;width:100%;text-align:center">
        <div style="font-size:3.5rem;margin-bottom:1rem">✅</div>
        <h1 style="font-size:1.6rem;font-weight:800;color:#1C2F48;margin:0 0 .5rem;text-transform:uppercase">¡Cupo Confirmado!</h1>
        <p style="color:#64748b;font-weight:600;margin-bottom:1.5rem">Inscripción en <strong><?= epl_h($insc_row['liga_nombre'] ?? 'la liga') ?></strong> registrada.</p>
        <div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:12px;padding:1.5rem;margin-bottom:1.5rem">
          <h3 style="color:#15803d;font-weight:800;font-size:.9rem;text-transform:uppercase;margin:0 0 .5rem">Último paso: invita a tu partner</h3>
          <p style="color:#166534;font-size:.85rem;margin-bottom:1rem;font-weight:500">Envíale este link para que confirme sus datos.</p>
          <a href="https://wa.me/?text=<?= $txt_wsp ?>" target="_blank"
             style="display:inline-flex;align-items:center;gap:.5rem;background:#25D366;color:#fff;font-weight:800;font-size:.9rem;text-transform:uppercase;padding:.8rem 1.5rem;border-radius:10px;text-decoration:none">
            📲 Enviar por WhatsApp
          </a>
          <div style="margin-top:.75rem;font-size:.75rem;color:#166534;word-break:break-all"><a href="<?= epl_h($url_partner) ?>" style="color:#15803d"><?= epl_h($url_partner) ?></a></div>
        </div>
        <a href="<?= epl_url('dashboard.php') ?>" style="color:#1C2F48;font-weight:700;font-size:.85rem;text-decoration:none">← Ir a mi dashboard</a>
      </div>
    </div>
    <?php
    require_once 'includes/footer.php';
    exit;
}

// ── Retorno fallido de MercadoPago ──────────────────────────────
if (isset($_GET['pago']) && $_GET['pago'] === 'fallo') {
    $error = 'El pago no pudo completarse. Intenta nuevamente o contacta al organizador.';
}

// ── Procesar formulario ─────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $liga_id    = (int)($_POST['liga_id']    ?? 0);
    $partner_id = (int)($_POST['partner_id'] ?? 0);

    if (!$liga_id) {
        $error = 'Selecciona una liga.';
    } elseif ($partner_id && $partner_id === (int)$jugador['id']) {
        $error = 'No puedes seleccionarte a ti mismo como compañero.';
    } else {
        $stCheck = $db->prepare("SELECT id FROM inscripciones WHERE jugador_id=? AND liga_id=? AND estado != 'rechazada'");
        $stCheck->execute([$jugador['id'], $liga_id]);
        if ($stCheck->fetch()) {
            $error = 'Ya tienes una inscripción activa en esta liga.';
        } else {
            $liga_data = $db->prepare("SELECT * FROM ligas WHERE id=?");
            $liga_data->execute([$liga_id]);
            $liga_data = $liga_data->fetch();
            $precio    = (float)($liga_data['precio'] ?? 0);

            $equipo_id = null;
            if ($partner_id) {
                $stEq = $db->prepare("SELECT id FROM equipos WHERE (jugador1_id=? AND jugador2_id=?) OR (jugador1_id=? AND jugador2_id=?) LIMIT 1");
                $stEq->execute([$jugador['id'], $partner_id, $partner_id, $jugador['id']]);
                $eq = $stEq->fetchColumn();
                if ($eq) {
                    $equipo_id = $eq;
                } else {
                    $stP = $db->prepare("SELECT nombre, apellido FROM jugadores WHERE id=?");
                    $stP->execute([$partner_id]);
                    $partner_row   = $stP->fetch();
                    $nombre_equipo = $jugador['apellido'].' / '.($partner_row['apellido'] ?? 'Compañero');
                    $db->prepare("INSERT INTO equipos (nombre, jugador1_id, jugador2_id) VALUES (?,?,?)")
                       ->execute([$nombre_equipo, $jugador['id'], $partner_id]);
                    $equipo_id = (int)$db->lastInsertId();
                }
            }

            $token = bin2hex(random_bytes(20));
            $db->prepare("INSERT INTO inscripciones (jugador_id, liga_id, equipo_id, rol_equipo, token, estado, pago_estado, pago_monto) VALUES (?,?,?,?,?,?,?,?)")
               ->execute([$jugador['id'], $liga_id, $equipo_id, 'capitan', $token, 'pendiente', 'pendiente', $precio ?: null]);
            $insc_id = (int)$db->lastInsertId();

            $pago_id = epl_pago_crear([
                'liga_id'     => $liga_id,
                'jugador_id'  => (int)$jugador['id'],
                'inscripcion_id' => $insc_id,
                'concepto'    => 'Inscripción Capitán EPL — ' . $liga_data['nombre'],
                'rol'         => 'capitan',
                'monto'       => $precio,
                'estado'      => $precio > 0 ? 'pendiente' : 'completado',
                'metodo'      => $precio > 0 ? 'MercadoPago' : 'Gratis',
                'token_ref'   => $token,
                'equipo_token'=> $token,
            ]);

            epl_notif_crear((int)$jugador['id'], 'inscripcion', 'Inscripción recibida',
                'Tu solicitud en ' . $liga_data['nombre'] . ' fue registrada.', epl_url('dashboard.php'));

            $base_url    = epl_url('inscribirse.php');
            $mp_token_cfg = epl_config_get('mp_access_token');

            if ($precio <= 0 || !$mp_token_cfg) {
                $db->prepare("UPDATE inscripciones SET pago_estado='pagado' WHERE id=?")->execute([$insc_id]);
                epl_pago_completar($token, 'Gratis');
                header('Location: ' . $base_url . '?pago=exito&token=' . urlencode($token));
                exit;
            }

            // MercadoPago checkout
            $body_mp = json_encode([
                'items' => [[
                    'title'       => 'Inscripción Capitán EPL — ' . $liga_data['nombre'],
                    'quantity'    => 1,
                    'unit_price'  => (int)$precio,
                    'currency_id' => 'CLP',
                ]],
                'back_urls' => [
                    'success' => $base_url . '?pago=exito&token=' . $token,
                    'failure' => $base_url . '?pago=fallo&liga='  . $liga_id,
                    'pending' => $base_url . '?pago=pendiente',
                ],
                'auto_return'        => 'approved',
                'external_reference' => $token,
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
}

// Otros jugadores disponibles como compañeros
$otros_jugadores = $db->query("
    SELECT id, nombre, apellido, alias FROM jugadores
    WHERE estado='activo' AND id != {$jugador['id']}
    ORDER BY apellido, nombre
")->fetchAll();

// Mis inscripciones
$mis_inscripciones = $db->prepare("
    SELECT i.*, l.nombre AS liga_nombre, l.temporada, e.nombre AS equipo_nombre
    FROM inscripciones i
    JOIN ligas l ON l.id = i.liga_id
    LEFT JOIN equipos e ON e.id = i.equipo_id
    WHERE i.jugador_id = ?
    ORDER BY i.fecha DESC
");
$mis_inscripciones->execute([$jugador['id']]);
$mis_inscripciones = $mis_inscripciones->fetchAll();
?>
<?php require_once 'includes/header.php'; ?>


<div class="dash-layout">
<?php include __DIR__ . '/includes/player_sidebar.php'; ?>
<main class="dash-main">
    <div class="dash-header">
      <h1 class="dash-title">Inscribirse a la Liga</h1>
      <p style="color:var(--gray-600);margin-top:.25rem">Solicita tu inscripción y el administrador la confirmará.</p>
    </div>

    <?php if ($ok): ?>
      <div class="alert alert-success"><?= epl_h($ok) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
      <div class="alert alert-error"><?= epl_h($error) ?></div>
    <?php endif; ?>

    <?php if (empty($ligas)): ?>
      <div class="alert alert-info">No hay ligas abiertas para inscripción en este momento.</div>
    <?php else: ?>
    <div class="card mb-4">
      <div class="card-head">
        <h3 style="font-family:var(--font-head);font-size:1rem;text-transform:uppercase;color:var(--navy)">Nueva inscripción</h3>
      </div>
      <div class="card-body">
        <form method="post">
          <div class="form-group">
            <label class="form-label">Liga *</label>
            <select name="liga_id" class="form-control" required>
              <option value="">— Selecciona una liga —</option>
              <?php foreach ($ligas as $l): ?>
                <option value="<?= $l['id'] ?>"><?= epl_h($l['nombre']) ?> <?= $l['temporada']?'('.$l['temporada'].')':'' ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="form-group">
            <label class="form-label">Compañero de equipo <span style="font-weight:400;text-transform:none;font-size:.75rem;color:var(--gray-400)">(opcional, puedes completarlo después)</span></label>
            <select name="partner_id" class="form-control">
              <option value="">— Sin compañero definido aún —</option>
              <?php foreach ($otros_jugadores as $oj): ?>
                <option value="<?= $oj['id'] ?>"><?= epl_h($oj['nombre'].' '.$oj['apellido'].($oj['alias']?' "'.$oj['alias'].'"':'')) ?></option>
              <?php endforeach; ?>
            </select>
            <span class="form-hint">Si tu compañero ya tiene cuenta, lo puedes seleccionar aquí.</span>
          </div>

          <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center">
            Enviar solicitud de inscripción
          </button>
        </form>
      </div>
    </div>
    <?php endif; ?>

    <!-- Mis inscripciones previas -->
    <?php if ($mis_inscripciones): ?>
    <div class="card">
      <div class="card-head">
        <h3 style="font-family:var(--font-head);font-size:1rem;text-transform:uppercase;color:var(--navy)">Mis inscripciones</h3>
      </div>
      <div class="card-body" style="padding:0">
        <?php foreach ($mis_inscripciones as $insc):
          $estBadge = match($insc['estado']) {
            'aprobada'  => 'badge-jugado',
            'rechazada' => 'badge-walkover',
            default     => 'badge-pendiente'
          };
          $pagoColor = $insc['pago_estado'] === 'pagado' ? '#22c55e' : ($insc['pago_estado'] === 'exento' ? 'var(--gold)' : '#ef4444');
        ?>
        <div style="padding:.9rem 1.5rem;border-bottom:1px solid var(--gray-100);display:flex;justify-content:space-between;align-items:center;gap:1rem;flex-wrap:wrap">
          <div>
            <div style="font-weight:700;font-size:.9rem;color:var(--navy)"><?= epl_h($insc['liga_nombre']) ?></div>
            <div style="font-size:.75rem;color:var(--gray-400)">
              <?= $insc['temporada'] ? epl_h($insc['temporada']).' · ' : '' ?>
              <?= $insc['equipo_nombre'] ? epl_h($insc['equipo_nombre']) : 'Sin equipo asignado' ?>
            </div>
            <div style="font-size:.72rem;color:var(--gray-400);margin-top:.2rem">
              <?= date('d/m/Y', strtotime($insc['fecha'])) ?>
            </div>
          </div>
          <div style="display:flex;flex-direction:column;align-items:flex-end;gap:.4rem">
            <span class="badge <?= $estBadge ?>"><?= ucfirst($insc['estado']) ?></span>
            <span style="font-size:.7rem;font-weight:700;color:<?= $pagoColor ?>">
              Pago: <?= ucfirst($insc['pago_estado']) ?>
            </span>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

</main>
</div>

<?php require_once 'includes/footer.php'; ?>
