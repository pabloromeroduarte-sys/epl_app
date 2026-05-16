<?php
/**
 * Formulario de inscripción — Capitán + vista de invitaciones como Partner
 */
$page_title = 'Inscribirse a la Liga';
$player_tab = 'inscribirse';
require_once 'includes/auth.php';
require_once 'includes/functions.php';
epl_require_login();

$db      = epl_db();
// Leer siempre desde DB para tener datos de perfil actualizados
$jugador = epl_jugador_db() ?: epl_jugador_actual();

// Ligas en inscripción o activas
$ligas = $db->query("SELECT * FROM ligas WHERE estado IN ('inscripcion','activa') ORDER BY id DESC")->fetchAll();

$ok    = '';
$error = '';

// ── Eliminar inscripción pendiente (sólo capitán) ──────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['epl_eliminar'] ?? '')) {
    $del_id = (int)($_POST['insc_id'] ?? 0);
    if ($del_id) {
        $chk = $db->prepare("SELECT id, token, equipo_id FROM inscripciones WHERE id=? AND jugador_id=? AND estado='pendiente'");
        $chk->execute([$del_id, $jugador['id']]);
        $row = $chk->fetch();
        if ($row) {
            $db->prepare("DELETE FROM pagos WHERE token_ref=?")->execute([$row['token']]);
            if ($row['equipo_id']) {
                // Delete partner inscription too
                $pi = $db->prepare("SELECT token FROM inscripciones WHERE equipo_id=? AND rol_equipo='partner'");
                $pi->execute([$row['equipo_id']]);
                $pt = $pi->fetchColumn();
                if ($pt) {
                    $db->prepare("DELETE FROM pagos WHERE token_ref=?")->execute([$pt]);
                    $db->prepare("DELETE FROM inscripciones WHERE equipo_id=? AND rol_equipo='partner'")->execute([$row['equipo_id']]);
                }
            }
            $db->prepare("DELETE FROM inscripciones WHERE id=?")->execute([$del_id]);
            $ok = 'Inscripción eliminada.';
        }
    }
}

// ── Completar inscripción gratis atascada (pago_estado='pendiente' en liga sin precio) ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['epl_completar_gratis'] ?? '')) {
    $fix_id = (int)($_POST['insc_id'] ?? 0);
    if ($fix_id) {
        $chk = $db->prepare("
            SELECT i.id, i.token, l.precio
            FROM inscripciones i
            JOIN ligas l ON l.id = i.liga_id
            WHERE i.id=? AND i.jugador_id=? AND i.estado='pendiente' AND i.pago_estado='pendiente' AND i.rol_equipo='capitan'
        ");
        $chk->execute([$fix_id, $jugador['id']]);
        $row = $chk->fetch();
        if ($row && (float)($row['precio'] ?? 0) <= 0) {
            $db->prepare("UPDATE inscripciones SET pago_estado='pagado' WHERE id=?")->execute([$fix_id]);
            epl_pago_completar((string)$row['token'], 'Gratis');
            header('Location: ' . epl_url('inscribirse.php') . '?pago=exito&token=' . urlencode($row['token']));
            exit;
        }
    }
}

// ── Aceptar invitación como partner ────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['epl_aceptar'] ?? '')) {
    $acc_id = (int)($_POST['insc_id'] ?? 0);
    if ($acc_id) {
        $chk = $db->prepare("
            SELECT i.*, l.precio AS liga_precio, l.nombre AS liga_nombre
            FROM inscripciones i
            JOIN ligas l ON l.id = i.liga_id
            WHERE i.id=? AND i.jugador_id=? AND i.rol_equipo='partner' AND i.estado='pendiente'
        ");
        $chk->execute([$acc_id, $jugador['id']]);
        $row = $chk->fetch();
        if ($row) {
            // Actualizar perfil con datos del formulario
            $pf = ['nombre','apellido','rut','fecha_nacimiento','sexo','telefono','comuna','profesion','nivel','lado','pala','talla','frecuencia_juego'];
            $upd = []; $upd_vals = [];
            foreach ($pf as $f) {
                if (isset($_POST[$f]) && trim($_POST[$f]) !== '') {
                    $upd[] = "$f=?";
                    $upd_vals[] = trim($_POST[$f]);
                }
            }
            if ($upd) {
                $upd_vals[] = $jugador['id'];
                $db->prepare("UPDATE jugadores SET " . implode(',', $upd) . " WHERE id=?")->execute($upd_vals);
                // Refrescar sesión
                $jugador = epl_jugador_por_email($jugador['email']) ?? $jugador;
                epl_session_start();
                $_SESSION['jugador'] = array_merge($_SESSION['jugador'] ?? [], epl_jugador_sesion_desde_fila($jugador));
            }

            $precio       = (float)($row['liga_precio'] ?? 0);
            $mp_token_cfg = epl_config_get('mp_access_token');

            if ($precio <= 0 || !$mp_token_cfg) {
                // Gratis — aceptar directamente
                $db->prepare("UPDATE inscripciones SET pago_estado='pagado', estado='aprobada' WHERE id=?")->execute([$acc_id]);
                epl_pago_completar($row['token'], 'Gratis');

                // Notificar al capitán que su partner aceptó
                $cap_insc = $db->prepare("SELECT i.jugador_id, j.nombre, j.apellido FROM inscripciones i JOIN jugadores j ON j.id=i.jugador_id WHERE i.equipo_id=? AND i.rol_equipo='capitan' LIMIT 1");
                $cap_insc->execute([$row['equipo_id']]);
                if ($cap_row = $cap_insc->fetch()) {
                    $partner_nombre = ($jugador['nombre'] ?? '') . ' ' . ($jugador['apellido'] ?? '');
                    epl_notif_crear((int)$cap_row['jugador_id'], 'inscripcion',
                        '✅ Tu partner aceptó la invitación',
                        trim($partner_nombre) . ' aceptó unirse a tu equipo en ' . $row['liga_nombre'] . '.',
                        epl_url('inscribirse.php')
                    );
                }

                $ok = '¡Invitación aceptada! Ya estás inscrito en ' . epl_h($row['liga_nombre']) . '.';
            } else {
                // Paga — crear preferencia MP y redirigir
                $pago_exists = $db->prepare("SELECT id FROM pagos WHERE token_ref=?");
                $pago_exists->execute([$row['token']]);
                if (!$pago_exists->fetchColumn()) {
                    epl_pago_crear([
                        'liga_id'        => $row['liga_id'],
                        'jugador_id'     => (int)$jugador['id'],
                        'inscripcion_id' => $acc_id,
                        'concepto'       => 'Inscripción Partner EPL — ' . $row['liga_nombre'],
                        'rol'            => 'partner',
                        'monto'          => $precio,
                        'estado'         => 'pendiente',
                        'metodo'         => 'MercadoPago',
                        'token_ref'      => $row['token'],
                        'equipo_token'   => $row['token'],
                    ]);
                }

                $base_url = epl_url('inscribirse.php');
                $body_mp  = json_encode([
                    'items' => [[
                        'title'       => 'Inscripción Partner EPL — ' . $row['liga_nombre'],
                        'quantity'    => 1,
                        'unit_price'  => (int)$precio,
                        'currency_id' => 'CLP',
                    ]],
                    'back_urls' => [
                        'success' => $base_url . '?pago=exito&token=' . $row['token'],
                        'failure' => $base_url . '?pago=fallo&liga='  . $row['liga_id'],
                        'pending' => $base_url . '?pago=pendiente',
                    ],
                    'auto_return'        => 'approved',
                    'external_reference' => $row['token'],
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
                    $db->prepare("UPDATE pagos SET mp_preference_id=? WHERE token_ref=?")->execute([$mp['id'] ?? '', $row['token']]);
                    header('Location: ' . $mp['init_point']);
                    exit;
                } else {
                    $error = 'Error al conectar con MercadoPago. Contacta al organizador.';
                }
            }
        }
    }
}

// ── Retorno exitoso de MercadoPago ──────────────────────────────
if (isset($_GET['pago']) && $_GET['pago'] === 'exito' && isset($_GET['token'])) {
    $token_ret  = preg_replace('/[^a-zA-Z0-9_-]/', '', $_GET['token']);
    $payment_id = isset($_GET['payment_id']) ? preg_replace('/[^a-zA-Z0-9_-]/', '', $_GET['payment_id']) : 'Gratis';

    epl_pago_completar($token_ret, $payment_id);
    $db->prepare("UPDATE inscripciones SET pago_estado='pagado', pago_ref=?, estado='aprobada' WHERE token=?")
       ->execute([$payment_id, $token_ret]);

    $insc_row = $db->prepare("SELECT i.*, l.nombre AS liga_nombre, j.nombre AS j_nombre, j.apellido AS j_apellido FROM inscripciones i JOIN ligas l ON l.id=i.liga_id JOIN jugadores j ON j.id=i.jugador_id WHERE i.token=?");
    $insc_row->execute([$token_ret]);
    $insc_row = $insc_row->fetch();

    // Notificar al capitán si fue el partner quien pagó
    if ($insc_row && $insc_row['rol_equipo'] === 'partner' && $insc_row['equipo_id']) {
        $cap_insc = $db->prepare("SELECT i.jugador_id FROM inscripciones i WHERE i.equipo_id=? AND i.rol_equipo='capitan' LIMIT 1");
        $cap_insc->execute([$insc_row['equipo_id']]);
        if ($cap_jid = $cap_insc->fetchColumn()) {
            $partner_nombre = trim(($insc_row['j_nombre'] ?? '') . ' ' . ($insc_row['j_apellido'] ?? ''));
            epl_notif_crear((int)$cap_jid, 'inscripcion',
                '✅ Tu partner confirmó el pago',
                $partner_nombre . ' pagó su inscripción en ' . $insc_row['liga_nombre'] . '. ¡Ya están inscritos!',
                epl_url('inscribirse.php')
            );
        }
    }

    $page_title = '¡Inscripción Confirmada!';
    require_once 'includes/header.php';

    if ($insc_row && $insc_row['rol_equipo'] === 'partner') {
        // Partner pagó — mensaje simple
        ?>
        <div style="min-height:80vh;display:flex;align-items:center;justify-content:center;padding:2rem 1rem;background:#f8fafc">
          <div style="background:#fff;border-radius:20px;box-shadow:0 4px 30px rgba(0,0,0,.08);padding:2.5rem;max-width:520px;width:100%;text-align:center">
            <div style="font-size:3.5rem;margin-bottom:1rem">✅</div>
            <h1 style="font-size:1.6rem;font-weight:800;color:#1C2F48;margin:0 0 .5rem;text-transform:uppercase">¡Pago Confirmado!</h1>
            <p style="color:#64748b;font-weight:600;margin-bottom:1.5rem">Tu inscripción como partner en <strong><?= epl_h($insc_row['liga_nombre'] ?? 'la liga') ?></strong> está confirmada.</p>
            <a href="<?= epl_url('dashboard.php') ?>" style="color:#1C2F48;font-weight:700;font-size:.85rem;text-decoration:none">← Ir a mi dashboard</a>
          </div>
        </div>
        <?php
    } else {
        // Capitán — mostrar invitación partner según si ya tiene cuenta o no
        $partner_insc = null;
        if ($insc_row && $insc_row['equipo_id']) {
            $pq = $db->prepare("SELECT i.token AS p_token, j.nombre, j.apellido FROM inscripciones i JOIN jugadores j ON j.id=i.jugador_id WHERE i.equipo_id=? AND i.rol_equipo='partner' LIMIT 1");
            $pq->execute([$insc_row['equipo_id']]);
            $partner_insc = $pq->fetch();
        }

        if ($partner_insc) {
            $txt_wsp = epl_wsp_invitar_partner_con_cuenta(
                (string)$partner_insc['nombre'],
                (string)($insc_row['liga_nombre'] ?? '')
            );
            ?>
            <div style="min-height:80vh;display:flex;align-items:center;justify-content:center;padding:2rem 1rem;background:#f8fafc">
              <div style="background:#fff;border-radius:20px;box-shadow:0 4px 30px rgba(0,0,0,.08);padding:2.5rem;max-width:520px;width:100%;text-align:center">
                <div style="font-size:3.5rem;margin-bottom:1rem">✅</div>
                <h1 style="font-size:1.6rem;font-weight:800;color:#1C2F48;margin:0 0 .5rem;text-transform:uppercase">¡Cupo Confirmado!</h1>
                <p style="color:#64748b;font-weight:600;margin-bottom:1.5rem">Inscripción en <strong><?= epl_h($insc_row['liga_nombre'] ?? 'la liga') ?></strong> registrada.</p>
                <div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:12px;padding:1.5rem;margin-bottom:1.5rem">
                  <h3 style="color:#15803d;font-weight:800;font-size:.9rem;text-transform:uppercase;margin:0 0 .5rem">Avisale a tu partner</h3>
                  <p style="color:#166534;font-size:.85rem;margin-bottom:1rem;font-weight:500">
                    <strong><?= epl_h($partner_insc['nombre'] . ' ' . $partner_insc['apellido']) ?></strong> ya tiene cuenta.
                    Enviá un WhatsApp para que entre a <strong>Inscripciones</strong> y acepte.
                  </p>
                  <a href="https://wa.me/?text=<?= $txt_wsp ?>" target="_blank"
                     style="display:inline-flex;align-items:center;gap:.5rem;background:#25D366;color:#fff;font-weight:800;font-size:.9rem;text-transform:uppercase;padding:.8rem 1.5rem;border-radius:10px;text-decoration:none">
                    📲 Enviar por WhatsApp
                  </a>
                </div>
                <a href="<?= epl_url('dashboard.php') ?>" style="color:#1C2F48;font-weight:700;font-size:.85rem;text-decoration:none">← Ir a mi dashboard</a>
              </div>
            </div>
            <?php
        } else {
            $url_partner = epl_url('inscribirse_partner.php?token=' . urlencode($token_ret));
            $txt_wsp = epl_wsp_invitar_partner_sin_cuenta($url_partner);
            ?>
            <div style="min-height:80vh;display:flex;align-items:center;justify-content:center;padding:2rem 1rem;background:#f8fafc">
              <div style="background:#fff;border-radius:20px;box-shadow:0 4px 30px rgba(0,0,0,.08);padding:2.5rem;max-width:520px;width:100%;text-align:center">
                <div style="font-size:3.5rem;margin-bottom:1rem">✅</div>
                <h1 style="font-size:1.6rem;font-weight:800;color:#1C2F48;margin:0 0 .5rem;text-transform:uppercase">¡Cupo Confirmado!</h1>
                <p style="color:#64748b;font-weight:600;margin-bottom:1.5rem">Inscripción en <strong><?= epl_h($insc_row['liga_nombre'] ?? 'la liga') ?></strong> registrada.</p>
                <div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:12px;padding:1.5rem;margin-bottom:1.5rem">
                  <h3 style="color:#15803d;font-weight:800;font-size:.9rem;text-transform:uppercase;margin:0 0 .5rem">Último paso: invita a tu partner</h3>
                  <p style="color:#166534;font-size:.85rem;margin-bottom:1rem;font-weight:500">Envíale el link para que se registre y confirme el cupo del equipo.</p>
                  <a href="https://wa.me/?text=<?= $txt_wsp ?>" target="_blank"
                     style="display:inline-flex;align-items:center;gap:.5rem;background:#25D366;color:#fff;font-weight:800;font-size:.9rem;text-transform:uppercase;padding:.8rem 1.5rem;border-radius:10px;text-decoration:none">
                    📲 Enviar por WhatsApp
                  </a>
                  <div style="margin-top:.75rem;font-size:.75rem;color:#166534;word-break:break-all">
                    <a href="<?= epl_h($url_partner) ?>" style="color:#15803d"><?= epl_h($url_partner) ?></a>
                  </div>
                </div>
                <a href="<?= epl_url('dashboard.php') ?>" style="color:#1C2F48;font-weight:700;font-size:.85rem;text-decoration:none">← Ir a mi dashboard</a>
              </div>
            </div>
            <?php
        }
    }
    require_once 'includes/footer.php';
    exit;
}

// ── Retorno fallido de MercadoPago ──────────────────────────────
if (isset($_GET['pago']) && $_GET['pago'] === 'fallo') {
    $error = 'El pago no pudo completarse. Intenta nuevamente o contacta al organizador.';
}

// ── Procesar formulario (nueva inscripción como capitán) ────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['epl_inscribir'] ?? '')) {
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

            // Crear o encontrar equipo si hay partner
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
                    $nombre_equipo = $jugador['apellido'] . ' / ' . ($partner_row['apellido'] ?? 'Compañero');
                    $db->prepare("INSERT INTO equipos (nombre, jugador1_id, jugador2_id) VALUES (?,?,?)")
                       ->execute([$nombre_equipo, $jugador['id'], $partner_id]);
                    $equipo_id = (int)$db->lastInsertId();
                }
            }

            $token = bin2hex(random_bytes(20));
            $db->prepare("INSERT INTO inscripciones (jugador_id, liga_id, equipo_id, rol_equipo, token, estado, pago_estado, pago_monto) VALUES (?,?,?,?,?,?,?,?)")
               ->execute([$jugador['id'], $liga_id, $equipo_id, 'capitan', $token, 'pendiente', 'pendiente', $precio ?: null]);
            $insc_id = (int)$db->lastInsertId();

            // Partner elegido del sistema: inscripción + notificación en menú Inscripciones
            if ($partner_id && $equipo_id) {
                epl_registrar_partner_sistema($db, $partner_id, $liga_id, $equipo_id, $precio);
                epl_notif_invitacion_partner(
                    $partner_id,
                    $jugador['nombre'] . ' ' . $jugador['apellido'],
                    $liga_data['nombre']
                );
            }

            $pago_id = epl_pago_crear([
                'liga_id'        => $liga_id,
                'jugador_id'     => (int)$jugador['id'],
                'inscripcion_id' => $insc_id,
                'concepto'       => 'Inscripción Capitán EPL — ' . $liga_data['nombre'],
                'rol'            => 'capitan',
                'monto'          => $precio,
                'estado'         => $precio > 0 ? 'pendiente' : 'completado',
                'metodo'         => $precio > 0 ? 'MercadoPago' : 'Gratis',
                'token_ref'      => $token,
                'equipo_token'   => $token,
            ]);

            epl_notif_crear((int)$jugador['id'], 'inscripcion', 'Inscripción recibida',
                'Tu solicitud en ' . $liga_data['nombre'] . ' fue registrada.', epl_url('dashboard.php'));

            $base_url     = epl_url('inscribirse.php');
            $mp_token_cfg = epl_config_get('mp_access_token');

            if ($precio <= 0 || !$mp_token_cfg) {
                $db->prepare("UPDATE inscripciones SET pago_estado='pagado' WHERE id=?")->execute([$insc_id]);
                epl_pago_completar($token, 'Gratis');
                header('Location: ' . $base_url . '?pago=exito&token=' . urlencode($token));
                exit;
            }

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

// Mis inscripciones — enriquecidas con datos de partner/capitán
$mis_inscripciones = $db->prepare("
    SELECT i.*,
           l.nombre  AS liga_nombre,
           l.temporada,
           l.precio  AS liga_precio,
           e.nombre  AS equipo_nombre,
           e.jugador1_id AS eq_cap_id,
           e.jugador2_id AS eq_par_id,
           pi2.id    AS partner_insc_id,
           pi2.token AS partner_token,
           pi2.estado AS partner_insc_estado,
           pj.nombre  AS partner_nombre,
           pj.apellido AS partner_apellido,
           cj.nombre  AS capitan_nombre,
           cj.apellido AS capitan_apellido
    FROM inscripciones i
    JOIN ligas l ON l.id = i.liga_id
    LEFT JOIN equipos e ON e.id = i.equipo_id
    LEFT JOIN inscripciones pi2
           ON i.rol_equipo = 'capitan'
          AND pi2.equipo_id = i.equipo_id
          AND pi2.rol_equipo = 'partner'
    LEFT JOIN jugadores pj ON i.rol_equipo = 'capitan' AND pj.id = pi2.jugador_id
    LEFT JOIN jugadores cj ON i.rol_equipo = 'partner' AND cj.id = e.jugador1_id
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
      <p style="color:var(--gray-600);margin-top:.25rem">Inscribite como capitán o aceptá una invitación de partner. El equipo se confirma cuando ambos validan su cupo.</p>
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
          <input type="hidden" name="epl_inscribir" value="1">
          <div class="form-group">
            <label class="form-label">Liga *</label>
            <select name="liga_id" class="form-control" required>
              <option value="">— Selecciona una liga —</option>
              <?php foreach ($ligas as $l): ?>
                <option value="<?= $l['id'] ?>">
                  <?= epl_h($l['nombre']) ?><?= $l['temporada'] ? ' (' . $l['temporada'] . ')' : '' ?>
                  <?= $l['precio'] > 0 ? ' — $' . number_format($l['precio'], 0, ',', '.') . ' CLP' : ' — Gratis' ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="form-group">
            <label class="form-label">
              Compañero de equipo
              <span style="font-weight:400;text-transform:none;font-size:.75rem;color:var(--gray-400)">(opcional, puedes completarlo después)</span>
            </label>
            <select name="partner_id" class="form-control">
              <option value="">— Sin compañero definido aún —</option>
              <?php foreach ($otros_jugadores as $oj): ?>
                <option value="<?= $oj['id'] ?>"><?= epl_h($oj['nombre'] . ' ' . $oj['apellido'] . ($oj['alias'] ? ' "' . $oj['alias'] . '"' : '')) ?></option>
              <?php endforeach; ?>
            </select>
            <span class="form-hint">Si tu compañero ya tiene cuenta, selecciónalo y recibirá una notificación para aceptar.</span>
          </div>

          <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center">
            Enviar solicitud de inscripción
          </button>
        </form>
      </div>
    </div>
    <?php endif; ?>

    <!-- Mis inscripciones -->
    <?php if ($mis_inscripciones): ?>
    <div class="card">
      <div class="card-head">
        <h3 style="font-family:var(--font-head);font-size:1rem;text-transform:uppercase;color:var(--navy)">Mis inscripciones</h3>
      </div>
      <div class="card-body" style="padding:0">
        <?php
        $est_badges = ['aprobada' => 'badge-jugado', 'rechazada' => 'badge-walkover'];
        foreach ($mis_inscripciones as $insc):
            $estBadge  = $est_badges[$insc['estado']] ?? 'badge-pendiente';
            $pagoColor = $insc['pago_estado'] === 'pagado'  ? '#22c55e'
                       : ($insc['pago_estado'] === 'exento' ? 'var(--gold)' : '#ef4444');
            $esCap     = $insc['rol_equipo'] === 'capitan';
            $esPart    = $insc['rol_equipo'] === 'partner';

            // Para capitán: ¿tiene partner con cuenta?
            $partnerConCuenta = $esCap && $insc['partner_insc_id'];
            // Para capitán sin partner con cuenta: link de token
            $url_partner_token = epl_url('inscribirse_partner.php?token=' . urlencode($insc['token']));

            $wsp_cap = $partnerConCuenta
                ? epl_wsp_invitar_partner_con_cuenta((string)($insc['partner_nombre'] ?? ''), (string)$insc['liga_nombre'])
                : '';
            $wsp_token = epl_wsp_invitar_partner_sin_cuenta($url_partner_token);
        ?>
        <?php if ($esPart && $insc['estado'] === 'pendiente'): ?>
        <!-- Invitación partner pendiente: card con formulario de perfil -->
        <div style="border-bottom:1px solid var(--gray-100)">
          <div style="padding:.9rem 1.5rem;background:#fdf4ff;border-bottom:1px solid #f3e8ff;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:.5rem">
            <div>
              <div style="display:flex;align-items:center;gap:.5rem;flex-wrap:wrap">
                <span style="font-weight:700;font-size:.9rem;color:var(--navy)"><?= epl_h($insc['liga_nombre']) ?></span>
                <span style="font-size:.65rem;font-weight:700;background:#fce7f3;color:#be185d;padding:.1rem .45rem;border-radius:999px;text-transform:uppercase">Partner</span>
                <span class="badge badge-pendiente" style="font-size:.65rem">Pendiente</span>
              </div>
              <div style="font-size:.75rem;color:var(--gray-400);margin-top:.2rem">
                <?= $insc['capitan_nombre'] ? 'Capitán: ' . epl_h($insc['capitan_nombre'] . ' ' . $insc['capitan_apellido']) : '' ?>
                · <?= date('d/m/Y', strtotime($insc['fecha'])) ?>
              </div>
            </div>
            <span style="font-size:.75rem;font-weight:700;color:#be185d">¡Tienes una invitación pendiente!</span>
          </div>
          <div style="padding:1.25rem 1.5rem">
            <p style="font-size:.82rem;color:#64748b;margin:0 0 1rem;font-weight:500">
              Confirmá o actualizá tus datos antes de aceptar:
            </p>
            <form method="POST">
              <input type="hidden" name="epl_aceptar" value="1">
              <input type="hidden" name="insc_id"     value="<?= $insc['id'] ?>">
              <?php
              $pf_profesiones = ['Arquitectura / Diseño','Arte / Creatividad','Comercial / Ventas','Construcción / Inmobiliaria','Derecho / Jurídico','Educación / Docencia','Emprendedor / Independiente','Finanzas / Banca','Gerencia / Dirección','Ingeniería / Construcción','Logística / Operaciones','Minería / Energía','Publicidad / Marketing','Recursos Humanos','Salud / Medicina','Estudiante','Tecnología / IT','Otro'];
              ?>
              <div style="display:grid;grid-template-columns:1fr 1fr;gap:.65rem">
                <div class="form-group" style="margin:0">
                  <label class="form-label" style="font-size:.72rem">Nombre *</label>
                  <input type="text" name="nombre" class="form-control" style="font-size:.82rem;padding:.4rem .65rem" value="<?= epl_h($jugador['nombre'] ?? '') ?>" required>
                </div>
                <div class="form-group" style="margin:0">
                  <label class="form-label" style="font-size:.72rem">Apellido *</label>
                  <input type="text" name="apellido" class="form-control" style="font-size:.82rem;padding:.4rem .65rem" value="<?= epl_h($jugador['apellido'] ?? '') ?>" required>
                </div>
                <div class="form-group" style="margin:0">
                  <label class="form-label" style="font-size:.72rem">RUT</label>
                  <input type="text" name="rut" class="form-control" style="font-size:.82rem;padding:.4rem .65rem" value="<?= epl_h($jugador['rut'] ?? '') ?>" placeholder="12.345.678-9">
                </div>
                <div class="form-group" style="margin:0">
                  <label class="form-label" style="font-size:.72rem">Teléfono</label>
                  <input type="tel" name="telefono" class="form-control" style="font-size:.82rem;padding:.4rem .65rem" value="<?= epl_h($jugador['telefono'] ?? '') ?>" placeholder="+56 9 1234 5678">
                </div>
                <div class="form-group" style="margin:0">
                  <label class="form-label" style="font-size:.72rem">Fecha de nacimiento</label>
                  <input type="date" name="fecha_nacimiento" class="form-control" style="font-size:.82rem;padding:.4rem .65rem" value="<?= epl_h($jugador['fecha_nacimiento'] ?? '') ?>">
                </div>
                <div class="form-group" style="margin:0">
                  <label class="form-label" style="font-size:.72rem">Sexo</label>
                  <select name="sexo" class="form-control" style="font-size:.82rem;padding:.4rem .65rem">
                    <option value="">— —</option>
                    <option value="M" <?= ($jugador['sexo'] ?? '') === 'M' ? 'selected' : '' ?>>Masculino</option>
                    <option value="F" <?= ($jugador['sexo'] ?? '') === 'F' ? 'selected' : '' ?>>Femenino</option>
                  </select>
                </div>
                <div class="form-group" style="margin:0">
                  <label class="form-label" style="font-size:.72rem">Nivel</label>
                  <select name="nivel" class="form-control" style="font-size:.82rem;padding:.4rem .65rem">
                    <option value="">— —</option>
                    <?php for ($nv = 1; $nv <= 8; $nv++): ?>
                      <option value="<?= $nv ?>" <?= (int)($jugador['nivel'] ?? 0) === $nv ? 'selected' : '' ?>><?= $nv ?>ª cat.</option>
                    <?php endfor; ?>
                  </select>
                </div>
                <div class="form-group" style="margin:0">
                  <label class="form-label" style="font-size:.72rem">Lado preferido</label>
                  <select name="lado" class="form-control" style="font-size:.82rem;padding:.4rem .65rem">
                    <option value="">— No definido —</option>
                    <option value="derecha" <?= ($jugador['lado'] ?? '') === 'derecha' ? 'selected' : '' ?>>Drive</option>
                    <option value="reves"   <?= ($jugador['lado'] ?? '') === 'reves'   ? 'selected' : '' ?>>Revés</option>
                    <option value="ambos"   <?= ($jugador['lado'] ?? '') === 'ambos'   ? 'selected' : '' ?>>Ambos</option>
                  </select>
                </div>
                <div class="form-group" style="margin:0">
                  <label class="form-label" style="font-size:.72rem">Comuna</label>
                  <input type="text" name="comuna" class="form-control" style="font-size:.82rem;padding:.4rem .65rem" value="<?= epl_h($jugador['comuna'] ?? '') ?>">
                </div>
                <div class="form-group" style="margin:0">
                  <label class="form-label" style="font-size:.72rem">Profesión</label>
                  <select name="profesion" class="form-control" style="font-size:.82rem;padding:.4rem .65rem">
                    <option value="">— —</option>
                    <?php foreach ($pf_profesiones as $pr): ?>
                      <option value="<?= epl_h($pr) ?>" <?= ($jugador['profesion'] ?? '') === $pr ? 'selected' : '' ?>><?= epl_h($pr) ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="form-group" style="margin:0">
                  <label class="form-label" style="font-size:.72rem">Pala</label>
                  <input type="text" name="pala" class="form-control" style="font-size:.82rem;padding:.4rem .65rem" value="<?= epl_h($jugador['pala'] ?? '') ?>" placeholder="Marca / Modelo">
                </div>
                <div class="form-group" style="margin:0">
                  <label class="form-label" style="font-size:.72rem">Talla camiseta</label>
                  <select name="talla" class="form-control" style="font-size:.82rem;padding:.4rem .65rem">
                    <option value="">— —</option>
                    <?php foreach (['XS','S','M','L','XL','XXL','XXXL'] as $t): ?>
                      <option value="<?= $t ?>" <?= ($jugador['talla'] ?? '') === $t ? 'selected' : '' ?>><?= $t ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
              </div>
              <button type="submit"
                style="margin-top:1rem;width:100%;padding:.75rem;background:#22c55e;color:#fff;font-weight:800;font-size:.9rem;text-transform:uppercase;letter-spacing:.05em;border:none;border-radius:10px;cursor:pointer">
                ✅ Confirmar datos y aceptar invitación
              </button>
            </form>
          </div>
        </div>
        <?php else: ?>
        <!-- Fila normal (capitán o partner ya aceptado) -->
        <div style="padding:.9rem 1.5rem;border-bottom:1px solid var(--gray-100);display:flex;justify-content:space-between;align-items:flex-start;gap:1rem;flex-wrap:wrap">
          <div>
            <div style="display:flex;align-items:center;gap:.5rem;flex-wrap:wrap;margin-bottom:.25rem">
              <span style="font-weight:700;font-size:.9rem;color:var(--navy)"><?= epl_h($insc['liga_nombre']) ?></span>
              <?php if ($esCap): ?>
                <span style="font-size:.65rem;font-weight:700;background:#dbeafe;color:#1d4ed8;padding:.1rem .45rem;border-radius:999px;text-transform:uppercase">Capitán</span>
              <?php elseif ($esPart): ?>
                <span style="font-size:.65rem;font-weight:700;background:#fce7f3;color:#be185d;padding:.1rem .45rem;border-radius:999px;text-transform:uppercase">Partner</span>
              <?php endif; ?>
            </div>
            <div style="font-size:.75rem;color:var(--gray-400)">
              <?= $insc['temporada'] ? epl_h($insc['temporada']) . ' · ' : '' ?>
              <?php if ($esCap && $insc['partner_nombre']): ?>
                Partner: <?= epl_h($insc['partner_nombre'] . ' ' . $insc['partner_apellido']) ?>
                <?= $insc['partner_insc_estado'] ? ' (' . ucfirst($insc['partner_insc_estado']) . ')' : '' ?>
              <?php elseif ($esPart && $insc['capitan_nombre']): ?>
                Capitán: <?= epl_h($insc['capitan_nombre'] . ' ' . $insc['capitan_apellido']) ?>
              <?php else: ?>
                <?= $insc['equipo_nombre'] ? epl_h($insc['equipo_nombre']) : 'Sin equipo asignado' ?>
              <?php endif; ?>
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

            <?php if ($insc['estado'] === 'pendiente' && $esCap): ?>
              <form method="POST" style="margin:0">
                <input type="hidden" name="epl_eliminar" value="1">
                <input type="hidden" name="insc_id"     value="<?= $insc['id'] ?>">
                <button type="submit" onclick="return confirm('¿Eliminar esta inscripción?')"
                  style="font-size:.7rem;font-weight:700;color:#dc2626;background:none;border:none;cursor:pointer;padding:0;text-decoration:underline">
                  Eliminar
                </button>
              </form>
              <a href="https://wa.me/?text=<?= $partnerConCuenta ? $wsp_cap : $wsp_token ?>" target="_blank"
                 style="font-size:.7rem;font-weight:700;color:#25D366;text-decoration:none">
                📲 <?= $partnerConCuenta ? 'Avisar al partner' : 'Enviar link partner' ?>
              </a>
              <?php if ($insc['pago_estado'] === 'pendiente' && (float)($insc['liga_precio'] ?? 0) <= 0): ?>
                <form method="POST" style="margin:0">
                  <input type="hidden" name="epl_completar_gratis" value="1">
                  <input type="hidden" name="insc_id" value="<?= $insc['id'] ?>">
                  <button type="submit"
                    style="font-size:.7rem;font-weight:700;color:#0ea5e9;background:none;border:none;cursor:pointer;padding:0;text-decoration:underline">
                    Completar inscripción
                  </button>
                </form>
              <?php endif; ?>
            <?php endif; ?>
          </div>
        </div>
        <?php endif; ?>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

</main>
</div>

<?php require_once 'includes/footer.php'; ?>
