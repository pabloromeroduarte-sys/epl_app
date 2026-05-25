<?php
$page_title = 'Admin — Partido';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../includes/mail.php';
epl_require_admin();
epl_ensure_partidos_columnas_originales();
epl_ensure_recintos_contactos();

$db = epl_db();
$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: partidos.php'); exit; }

// ── POST: marcar baja manual ──
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'marcar_baja_manual') {
        $quien = trim($_POST['quien'] ?? '') ?: 'Admin (manual)';
        $db->prepare("UPDATE partidos SET baja_confirmada_at = NOW(), baja_confirmada_por = ? WHERE id = ?")
           ->execute([$quien, $id]);
        if (session_status() === PHP_SESSION_NONE) session_start(); $_SESSION['_epl_flash'] = ['tipo'=>'ok','msg'=>'Baja marcada como confirmada manualmente.'];
        header("Location: partido_detalle.php?id=$id"); exit;
    }
    if ($action === 'reset_baja') {
        $db->prepare("UPDATE partidos SET baja_solicitada_at=NULL, baja_confirmada_at=NULL, baja_confirmada_por=NULL, baja_token=NULL WHERE id = ?")
           ->execute([$id]);
        if (session_status() === PHP_SESSION_NONE) session_start(); $_SESSION['_epl_flash'] = ['tipo'=>'ok','msg'=>'Estado de baja reseteado.'];
        header("Location: partido_detalle.php?id=$id"); exit;
    }
    if ($action === 'aprobar_reprog') {
        // 1) Buscar la solicitud pendiente más reciente para sacar fecha_propuesta
        $sol_st = $db->prepare("SELECT id, fecha_propuesta FROM solicitudes_reprogramacion WHERE partido_id=? AND estado='pendiente' ORDER BY id DESC LIMIT 1");
        $sol_st->execute([$id]);
        $sol_row = $sol_st->fetch(PDO::FETCH_ASSOC);
        $fecha_nueva = $sol_row['fecha_propuesta'] ?? null;

        // Cancha nueva opcional (si admin la asigna directamente)
        $rec_nuevo_id = (int)($_POST['recinto_nuevo_id'] ?? 0) ?: null;

        // 2) Guardar snapshot original ANTES de tocar fecha_programada/recinto_id
        epl_partido_snapshot_original($id);

        // 3) Aplicar nueva fecha (y cancha si el admin la asignó)
        if ($fecha_nueva) {
            if ($rec_nuevo_id) {
                $db->prepare("UPDATE partidos SET estado='pendiente', fecha_programada=?,
                                recinto_id=?, cancha_token=NULL, cancha_solicitada_at=NULL,
                                cancha_confirmada_at=NOW(), cancha_confirmada_por='Admin (manual)'
                              WHERE id = ?")->execute([$fecha_nueva, $rec_nuevo_id, $id]);
            } else {
                $db->prepare("UPDATE partidos SET estado='pendiente', fecha_programada=?,
                                recinto_id=NULL, cancha_token=NULL, cancha_solicitada_at=NULL,
                                cancha_confirmada_at=NULL, cancha_confirmada_por=NULL
                              WHERE id = ?")->execute([$fecha_nueva, $id]);
            }
        } else {
            // Sin fecha propuesta → solo pasar a pendiente
            $db->prepare("UPDATE partidos SET estado='pendiente' WHERE id = ?")->execute([$id]);
        }

        // 4) Marcar la solicitud como aprobada
        try {
            $db->prepare("UPDATE solicitudes_reprogramacion SET estado='aprobada', fecha_aprobada=NOW() WHERE partido_id=? AND estado='pendiente'")
               ->execute([$id]);
        } catch (Throwable $e) {}

        if (session_status() === PHP_SESSION_NONE) session_start();
        if ($fecha_nueva) {
            $_SESSION['_epl_flash'] = ['tipo'=>'ok','msg'=> $rec_nuevo_id
                ? 'Reprogramación aprobada con cancha asignada manualmente.'
                : 'Reprogramación aprobada. El partido quedó con la nueva fecha y sin cancha (el club debe asignarla).'];
        } else {
            $_SESSION['_epl_flash'] = ['tipo'=>'ok','msg'=>'Reprogramación aprobada.'];
        }
        header("Location: partido_detalle.php?id=$id"); exit;
    }
    if ($action === 'revertir_pendiente') {
        $db->prepare("UPDATE partidos SET estado='pendiente' WHERE id = ?")->execute([$id]);
        if (session_status() === PHP_SESSION_NONE) session_start(); $_SESSION['_epl_flash'] = ['tipo'=>'ok','msg'=>'Partido revertido a Pendiente.'];
        header("Location: partido_detalle.php?id=$id"); exit;
    }
    if ($action === 'cancelar_partido') {
        $nuevo_estado = in_array($_POST['nuevo_estado'] ?? '', ['walkover','no_presentado']) ? $_POST['nuevo_estado'] : 'walkover';
        $nota = trim($_POST['nota_cancelar'] ?? '');
        $db->prepare("UPDATE partidos SET estado=? WHERE id = ?")->execute([$nuevo_estado, $id]);
        // Rechazar solicitud pendiente si existe
        try {
            $db->prepare("UPDATE solicitudes_reprogramacion SET estado='rechazada', updated_at=NOW() WHERE partido_id=? AND estado='pendiente'")
               ->execute([$id]);
        } catch (Throwable $e) {}
        $lbl = $nuevo_estado === 'walkover' ? 'Walkover' : 'No presentado';
        if (session_status() === PHP_SESSION_NONE) session_start(); $_SESSION['_epl_flash'] = ['tipo'=>'ok','msg'=>"Partido marcado como $lbl."];
        header("Location: partido_detalle.php?id=$id"); exit;
    }
    if ($action === 'guardar_original') {
        $fecha_o = trim($_POST['fecha_original'] ?? '');
        $rec_o   = (int)($_POST['recinto_original_id'] ?? 0) ?: null;
        if ($fecha_o) {
            // Acepta tanto 'Y-m-d H:i' (flatpickr) como 'Y-m-dTH:i' (datetime-local legacy)
            $ts = strtotime(str_replace('T', ' ', $fecha_o));
            $fecha_db = $ts ? date('Y-m-d H:i:s', $ts) : null;
            if ($fecha_db) $db->prepare("UPDATE partidos SET fecha_original=? WHERE id=?")->execute([$fecha_db, $id]);
        } else {
            $db->prepare("UPDATE partidos SET fecha_original=NULL WHERE id=?")->execute([$id]);
        }
        $db->prepare("UPDATE partidos SET recinto_original_id=? WHERE id=?")->execute([$rec_o, $id]);
        if (session_status() === PHP_SESSION_NONE) session_start(); $_SESSION['_epl_flash'] = ['tipo'=>'ok','msg'=>'Reserva original actualizada.'];
        header("Location: partido_detalle.php?id=$id"); exit;
    }
}

// ── Cargar partido completo ──
// Estrategia: traer contactos del recinto ORIGINAL si está seteado,
// pero también traer los del recinto ACTUAL como fallback (la mayoría de veces
// el club a contactar es el mismo y si no hay snapshot histórico, usamos el actual).
$st = $db->prepare("
    SELECT p.*,
           el.id AS local_id,   el.nombre AS local_nombre,
           ev.id AS visitante_id,ev.nombre AS visitante_nombre,
           l.nombre AS liga_nombre, l.id AS liga_id_n,
           r.nombre  AS recinto_nombre,  rs.nombre AS recinto_sup,
           r.contacto1_nombre AS rc1n, r.contacto1_telefono AS rc1t,
           r.contacto2_nombre AS rc2n, r.contacto2_telefono AS rc2t,
           r.contacto3_nombre AS rc3n, r.contacto3_telefono AS rc3t,
           ro.id AS rec_o_id, ro.nombre AS recinto_original_nombre,
           ro.contacto1_nombre, ro.contacto1_telefono,
           ro.contacto2_nombre, ro.contacto2_telefono,
           ro.contacto3_nombre, ro.contacto3_telefono,
           rop.nombre AS recinto_original_sup,
           jl1.nombre AS jl1_n, jl1.apellido AS jl1_a, jl1.telefono AS jl1_t,
           jl2.nombre AS jl2_n, jl2.apellido AS jl2_a, jl2.telefono AS jl2_t,
           jv1.nombre AS jv1_n, jv1.apellido AS jv1_a, jv1.telefono AS jv1_t,
           jv2.nombre AS jv2_n, jv2.apellido AS jv2_a, jv2.telefono AS jv2_t
    FROM partidos p
    JOIN ligas l ON l.id = p.liga_id
    JOIN equipos el ON el.id = p.equipo_local_id
    JOIN equipos ev ON ev.id = p.equipo_visitante_id
    LEFT JOIN recintos r  ON r.id  = p.recinto_id
    LEFT JOIN recintos rs ON rs.id = r.superior_id
    LEFT JOIN recintos ro ON ro.id = p.recinto_original_id
    LEFT JOIN recintos rop ON rop.id = ro.superior_id
    LEFT JOIN jugadores jl1 ON jl1.id = el.jugador1_id
    LEFT JOIN jugadores jl2 ON jl2.id = el.jugador2_id
    LEFT JOIN jugadores jv1 ON jv1.id = ev.jugador1_id
    LEFT JOIN jugadores jv2 ON jv2.id = ev.jugador2_id
    WHERE p.id = ?
");
$st->execute([$id]);
$p = $st->fetch(PDO::FETCH_ASSOC);
if (!$p) { header('Location: partidos.php'); exit; }

// Última solicitud no rechazada
$sol = null;
try {
    $st = $db->prepare("
        SELECT sr.*, j.nombre AS sol_n, j.apellido AS sol_a
        FROM solicitudes_reprogramacion sr
        JOIN jugadores j ON j.id = sr.solicitante_id
        WHERE sr.partido_id = ?
        ORDER BY sr.id DESC LIMIT 1
    ");
    $st->execute([$id]);
    $sol = $st->fetch(PDO::FETCH_ASSOC);
} catch (Throwable $e) {}

// Recintos para selector
$_recintos_raw = $db->query("SELECT id, nombre, superior_id FROM recintos ORDER BY nombre")->fetchAll();
$_rec_roots = []; $_rec_children = [];
foreach ($_recintos_raw as $_r) {
    if (!$_r['superior_id']) $_rec_roots[] = $_r;
    else $_rec_children[$_r['superior_id']][] = $_r;
}
$todos_recintos = [];
function _flatRecD(array $nodes, array $children, int $depth, array &$out): void {
    foreach ($nodes as $n) {
        $prefix = $depth === 0 ? '🏛 ' : ($depth === 1 ? '   📍 ' : '      🎾 ');
        $out[] = ['id' => $n['id'], 'label' => $prefix . $n['nombre']];
        if (isset($children[$n['id']])) _flatRecD($children[$n['id']], $children, $depth + 1, $out);
    }
}
_flatRecD($_rec_roots, $_rec_children, 0, $todos_recintos);

$_flash = epl_flash_get();
$ok  = ($_flash && $_flash['tipo'] === 'ok')    ? $_flash['msg'] : '';
$err = ($_flash && $_flash['tipo'] === 'error') ? $_flash['msg'] : '';

// Estados
$_sf = !$p['fecha_programada'] || date('Y-m-d', strtotime($p['fecha_programada'])) === '2026-12-31';
$_fecha_lbl = $_sf ? 'Sin fecha' : date('d/m/Y H:i', strtotime($p['fecha_programada']));
$_fo_lbl = $p['fecha_original'] ? date('d/m/Y H:i', strtotime($p['fecha_original'])) : null;
$es_reprog = $p['estado'] === 'reprogramado';
$tiene_original = !empty($p['fecha_original']) || !empty($p['rec_o_id']);
$baja_confirmada = !empty($p['baja_confirmada_at']);
$baja_solicitada = !empty($p['baja_solicitada_at']) && !$baja_confirmada;

// ── Estado del flujo de reprogramación ──────────────────────────────
// Hay 2 momentos:
//   PRE-APROBADO  → estado='reprogramado' y fecha_original=NULL
//                   ⇒ fecha_programada SIGUE siendo la original
//                   ⇒ la fecha propuesta vive en solicitudes_reprogramacion.fecha_propuesta
//   POST-APROBADO → fecha_original ya tiene el snapshot
//                   ⇒ fecha_programada YA es la nueva fecha
$_post_aprobado = !empty($p['fecha_original']);

// Fecha propuesta (nueva) — desde la solicitud si pre-aprobado, desde fecha_programada si post
if ($_post_aprobado) {
    $_propuesta_raw = $p['fecha_programada'];
    $_propuesta_recinto = $p['recinto_nombre'];   // puede estar NULL si aún no asignaron
} else {
    $_propuesta_raw = $sol['fecha_propuesta'] ?? null;
    $_propuesta_recinto = null; // pre-aprobado: aún no se eligió cancha
}
$_propuesta_lbl = ($_propuesta_raw && date('Y-m-d', strtotime($_propuesta_raw)) !== '2026-12-31')
                  ? date('d/m/Y H:i', strtotime($_propuesta_raw)) : null;
$_tiene_fecha_nueva = !empty($_propuesta_lbl);

// Fecha original (lo que hay que dar de baja en el club)
if ($_post_aprobado) {
    $_baja_fecha       = $p['fecha_original'];
    $_baja_recinto_id  = $p['recinto_original_id'];
    $_baja_recinto_nom = $p['recinto_original_nombre'];
} else {
    // PRE-aprobado: fecha_programada todavía es la fecha original
    $_baja_fecha       = $p['fecha_programada'];
    $_baja_recinto_id  = $p['recinto_id'];
    $_baja_recinto_nom = $p['recinto_nombre'];
}
$_baja_fecha_lbl = ($_baja_fecha && date('Y-m-d', strtotime($_baja_fecha)) !== '2026-12-31')
                   ? date('d/m/Y H:i', strtotime($_baja_fecha)) : null;

// ¿Necesita asignar cancha nueva? (post-aprobado con fecha nueva pero sin cancha)
$_necesita_cancha = $_post_aprobado && $_tiene_fecha_nueva && empty($p['recinto_id']);
$_pedir_cancha    = $_necesita_cancha;

// ── Canchas disponibles del club (para que admin asigne manualmente al aprobar) ──
$_canchas_club = [];
$_club_ref = $p['recinto_original_id'] ?: $p['recinto_id'];
if ($_club_ref) {
    try {
        $_raiz = epl_recinto_raiz((int)$_club_ref);
        $_canchas_club = epl_recintos_canchas_de_club($_raiz);
    } catch (Throwable $e) {}
}

// Contactos del club (jerarquía: cancha original → sede → club)
$_recinto_para_baja = $p['recinto_original_id'] ?: $p['recinto_id'];
$contactos_info     = $_recinto_para_baja ? epl_recinto_contactos_jerarquico((int)$_recinto_para_baja) : ['contactos' => [], 'recinto_id' => null, 'recinto_nombre' => null];
$contactos_club     = $contactos_info['contactos'];
$contactos_origen   = $contactos_info['recinto_nombre'];

$_proto = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$_host  = $_SERVER['HTTP_HOST'] ?? 'localhost';

// ── Token y link unificado (baja + cancha nueva en una sola página) ──────────
$_baja_link = '';
if (!$baja_confirmada) {
    $_token = epl_partido_baja_token($id);
    $_baja_link = "$_proto://$_host/confirmar_baja.php?t=$_token";
}

// ── Mensaje WhatsApp ──────────────────────────────────────────────────────────
// Dos escenarios:
// (A) Reprogramado SIN fecha propuesta → solo pedir baja de la reserva original
// (B) Reprogramado CON fecha propuesta → pedir baja de la original + asignar cancha nueva
$_partido_str = $p['local_nombre'] . " vs " . $p['visitante_nombre'];
$_msg_wsp = "Hola, te hablo de Elite Padel League.\n\n";
$_msg_wsp .= "👥 Partido: $_partido_str\n\n";

if ($_tiene_fecha_nueva) {
    // ── ESCENARIO B: hay fecha propuesta (aprobada o no) ──
    $_msg_wsp .= "Tenemos una REPROGRAMACIÓN:\n\n";
    $_msg_wsp .= "🚫 Fecha original (DAR DE BAJA):\n";
    $_msg_wsp .= "   📅 " . ($_baja_fecha_lbl ?: '⚠ pendiente de registrar') . "\n";
    if ($_baja_recinto_nom) $_msg_wsp .= "   🎾 $_baja_recinto_nom\n";
    $_msg_wsp .= "\n";
    $_msg_wsp .= "✅ Fecha nueva del partido:\n";
    $_msg_wsp .= "   📅 $_propuesta_lbl\n";
    $_msg_wsp .= "   🎾 Necesitamos que nos asignen una cancha para esta fecha.\n";
} else {
    // ── ESCENARIO A: solo baja de la reserva original ──
    $_msg_wsp .= "Necesitamos DAR DE BAJA esta reserva:\n";
    $_msg_wsp .= "📅 " . ($_baja_fecha_lbl ?: '⚠ fecha pendiente de registrar') . "\n";
    if ($_baja_recinto_nom) $_msg_wsp .= "🎾 $_baja_recinto_nom\n";
}

// Línea de acción con el link
if ($_baja_link) {
    if ($_tiene_fecha_nueva) {
        $_msg_wsp .= "\nDesde este link podés confirmar la baja Y elegir la cancha nueva en un solo paso:\n$_baja_link";
    } else {
        $_msg_wsp .= "\nConfirmá la baja desde este link:\n$_baja_link";
    }
}
$_msg_wsp .= "\n\n¡Gracias!";

// Compatibilidad con secciones legacy
$_msg_cancha = $_msg_wsp;

// ¿Está OK enviar el mensaje? Solo requiere fecha original conocida.
$_msg_listo = !empty($_baja_fecha_lbl);

require_once '../includes/header.php';
?>

<div class="dash-layout">
  <?php include __DIR__ . '/partials/sidebar.php'; ?>

  <main class="dash-main">

    <!-- Header con back -->
    <div style="margin-bottom:1rem">
      <a href="dashboard_repro.php" style="font-size:.78rem;color:#64748b;text-decoration:none;font-weight:600">← Volver a Reprogramaciones</a>
    </div>

    <div class="dash-header" style="background:linear-gradient(135deg,#1c2f48 0%, #0f1e30 100%);border-radius:18px;padding:1.5rem 1.75rem;color:#fff;margin-bottom:1.5rem;position:relative;overflow:hidden">
      <div style="position:absolute;top:-40px;right:-40px;width:180px;height:180px;background:radial-gradient(circle,rgba(201,167,98,.2),transparent 70%)"></div>
      <div style="position:relative;z-index:1">
        <span style="font-size:.6rem;font-weight:900;letter-spacing:.22em;color:#C9A762;text-transform:uppercase"><?= epl_h($p['liga_nombre']) ?> · J<?= $p['jornada'] ?: '?' ?></span>
        <h1 style="color:#fff;margin:.25rem 0 .2rem;font-size:clamp(1.3rem,4vw,2rem);font-family:'Anton',sans-serif;text-transform:uppercase;line-height:1">
          <?= epl_h($p['local_nombre']) ?> <span style="color:#C9A762">vs</span> <?= epl_h($p['visitante_nombre']) ?>
        </h1>
        <div style="margin-top:.65rem;display:flex;gap:.5rem;flex-wrap:wrap">
          <?php
            $est_lbl = match($p['estado']) {
                'jugado' => '✓ Jugado', 'pendiente' => '⏳ Pendiente',
                'reprogramado' => '🔄 Reprogramado', 'walkover' => '❌ Walkover',
                'no_presentado' => '❌ No presentado', default => $p['estado'],
            };
            $est_bg = match($p['estado']) {
                'jugado' => '#10b981', 'reprogramado' => '#f59e0b',
                'walkover','no_presentado' => '#dc2626', default => '#3b82f6',
            };
          ?>
          <span style="background:<?= $est_bg ?>;color:#fff;padding:.25rem .7rem;border-radius:999px;font-size:.72rem;font-weight:800"><?= $est_lbl ?></span>
          <span style="background:rgba(255,255,255,.12);color:#fff;padding:.25rem .7rem;border-radius:999px;font-size:.72rem;font-weight:600">📅 <?= $_fecha_lbl ?></span>
          <?php if ($p['recinto_nombre']): ?>
            <span style="background:rgba(255,255,255,.12);color:#fff;padding:.25rem .7rem;border-radius:999px;font-size:.72rem;font-weight:600">🎾 <?= epl_h($p['recinto_nombre']) ?></span>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <?php if ($ok): ?><div class="alert alert-success" style="margin-bottom:1rem"><?= epl_h($ok) ?></div><?php endif; ?>
    <?php if ($err): ?><div class="alert alert-error" style="margin-bottom:1rem"><?= epl_h($err) ?></div><?php endif; ?>

    <!-- ═══════════ FLUJO BAJA DE CANCHA ═══════════ -->
    <?php if ($es_reprog): ?>
    <section class="card mb-3" style="border-left:5px solid <?= $baja_confirmada ? '#10b981' : ($baja_solicitada ? '#f59e0b' : '#dc2626') ?>">
      <div class="card-head" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:.5rem">
        <h2 style="font-family:'Anton',sans-serif;font-size:1.05rem;color:var(--navy);text-transform:uppercase;margin:0">
          🏟️ Baja de cancha
        </h2>
        <span style="background:<?= $baja_confirmada ? '#dcfce7' : ($baja_solicitada ? '#fef3c7' : '#fee2e2') ?>;color:<?= $baja_confirmada ? '#15803d' : ($baja_solicitada ? '#92400e' : '#991b1b') ?>;padding:.3rem .85rem;border-radius:999px;font-size:.72rem;font-weight:800;text-transform:uppercase;letter-spacing:.05em">
          <?= $baja_confirmada ? '✅ Confirmada' : ($baja_solicitada ? '⏳ Esperando' : '🚫 Pendiente') ?>
        </span>
      </div>
      <div class="card-body">

        <?php
          $_hay_info = $_baja_fecha_lbl || $_baja_recinto_nom;
        ?>
        <?php if (!$_hay_info): ?>
          <!-- Sin ningún dato útil: pedir registrar -->
          <div style="background:#fef3c7;border:1px solid #fbbf24;border-radius:10px;padding:1rem;font-size:.88rem;color:#92400e;margin-bottom:1rem">
            ⚠️ Este partido no tiene fecha ni cancha asignada. Registrá manualmente abajo qué reservar dar de baja.
          </div>
        <?php else: ?>
          <!-- Cards: lo que se va a dar de baja + (si hay snapshot) la nueva fecha -->
          <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:.75rem;margin-bottom:1rem">
            <div style="background:#fee2e2;border-left:4px solid #dc2626;padding:.85rem 1rem;border-radius:8px">
              <div style="font-size:.65rem;color:#991b1b;font-weight:800;text-transform:uppercase;letter-spacing:.08em">📅 Fecha original del partido</div>
              <div style="font-size:.7rem;color:#b91c1c;margin-top:.15rem;margin-bottom:.25rem">La reserva que hay que DAR DE BAJA en el club</div>
              <?php if ($_baja_fecha_lbl): ?>
                <div style="font-weight:900;color:#7f1d1d;font-size:1rem;margin-top:.2rem"><?= $_baja_fecha_lbl ?></div>
              <?php else: ?>
                <div style="font-weight:700;color:#b91c1c;font-size:.85rem;margin-top:.2rem;background:#fecaca;padding:.4rem .6rem;border-radius:6px">
                  ⚠ No registrada — completá con el formulario de abajo
                </div>
              <?php endif; ?>
              <?php if ($_baja_recinto_nom): ?>
                <div style="font-size:.82rem;color:#991b1b;margin-top:.25rem;font-weight:700">🎾 <?= epl_h($_baja_recinto_nom) ?></div>
              <?php endif; ?>
            </div>
            <?php if ($_tiene_fecha_nueva): ?>
            <div style="background:#dbeafe;border-left:4px solid #2563eb;padding:.85rem 1rem;border-radius:8px">
              <div style="font-size:.65rem;color:#1e40af;font-weight:800;text-transform:uppercase;letter-spacing:.08em">📅 Fecha propuesta (nueva)</div>
              <div style="font-size:.7rem;color:#1e40af;margin-top:.15rem;margin-bottom:.25rem">
                <?= $_post_aprobado ? 'Nueva fecha del partido (ya aprobada)' : 'Propuesta del jugador — falta aprobar' ?>
              </div>
              <div style="font-weight:900;color:#1e3a8a;font-size:1rem;margin-top:.2rem"><?= $_propuesta_lbl ?></div>
              <?php if (!$_post_aprobado): ?>
                <div style="font-size:.72rem;color:#92400e;margin-top:.35rem;background:#fef3c7;padding:.35rem .55rem;border-radius:6px;font-weight:700">
                  ⏳ Aprobá la reprogramación abajo para aplicar esta fecha
                </div>
              <?php elseif (!empty($p['cancha_confirmada_at']) && $p['recinto_nombre']): ?>
                <div style="font-size:.82rem;color:#15803d;margin-top:.25rem;font-weight:700">🎾 <?= epl_h($p['recinto_nombre']) ?></div>
              <?php else: ?>
                <div style="font-size:.76rem;color:#2563eb;margin-top:.25rem;font-weight:700;font-style:italic">⚠ Sin cancha asignada aún</div>
              <?php endif; ?>
            </div>
            <?php endif; ?>
          </div>

          <?php if ($baja_confirmada): ?>
            <div style="background:#dcfce7;border:1px solid #86efac;border-radius:10px;padding:1rem;text-align:center;margin-bottom:1rem">
              <div style="font-size:2rem">🎉</div>
              <div style="font-weight:800;color:#15803d;margin-top:.3rem">Baja confirmada por el club</div>
              <div style="font-size:.78rem;color:#15803d;margin-top:.2rem">
                <?= date('d/m/Y H:i', strtotime($p['baja_confirmada_at'])) ?>
                <?php if ($p['baja_confirmada_por']): ?> · por <strong><?= epl_h($p['baja_confirmada_por']) ?></strong><?php endif; ?>
              </div>
              <form method="post" style="margin-top:.85rem">
                <input type="hidden" name="action" value="reset_baja">
                <button type="submit" class="btn btn-sm" style="background:#fff;border:1px solid #cbd5e1;color:#64748b;font-size:.7rem" data-confirm="¿Resetear el estado de la baja?" data-confirm-ok="Resetear">↻ Resetear</button>
              </form>
            </div>
          <?php else: ?>
            <?php if (empty($contactos_club)): ?>
              <div style="background:#fee2e2;border:1px solid #fca5a5;border-radius:10px;padding:1rem;font-size:.88rem;color:#991b1b">
                ⚠️ Este recinto (ni sus superiores) tiene contactos cargados.
                <a href="recintos.php" style="color:#1c2f48;font-weight:800;text-decoration:underline">Cargá los contactos del club acá →</a>
              </div>
            <?php else: ?>
              <?php if (empty($_baja_fecha_lbl)): ?>
                <div style="background:#fef3c7;border:2px solid #f59e0b;border-radius:10px;padding:1rem;font-size:.88rem;color:#92400e;margin-bottom:1rem">
                  <strong>⚠ Falta registrar la fecha original</strong><br>
                  No podés enviar el mensaje sin saber qué reserva dar de baja. Usá el formulario <strong>"Registrar manualmente reserva original"</strong> abajo y volvé a entrar.
                </div>
              <?php else: ?>
                <p style="font-size:.85rem;color:#475569;margin-bottom:.5rem;font-weight:600">
                  📱 Enviar aviso al club por WhatsApp
                  <?php if ($contactos_origen && $contactos_origen !== ($p['recinto_original_nombre'] ?: $p['recinto_nombre'])): ?>
                    <span style="font-weight:500;color:#64748b;font-size:.78rem">(contactos de <?= epl_h($contactos_origen) ?>)</span>
                  <?php endif; ?>
                </p>
                <div style="display:flex;gap:.5rem;flex-wrap:wrap;margin-bottom:1rem">
                  <?php foreach ($contactos_club as $c):
                    $_tel = preg_replace('/[^0-9]/', '', $c['telefono']);
                    if (!$_tel) continue;
                    if (substr($_tel, 0, 2) !== '56') $_tel = '56' . $_tel;
                    $_wsp = "https://wa.me/{$_tel}?text=" . rawurlencode($_msg_wsp);
                  ?>
                  <a href="<?= $_wsp ?>" target="_blank" rel="noopener" style="display:inline-flex;align-items:center;gap:.5rem;background:#25D366;color:#fff;padding:.65rem 1.2rem;border-radius:10px;font-size:.85rem;font-weight:800;text-decoration:none;box-shadow:0 4px 12px rgba(37,211,102,.3)">
                    <svg width="18" height="18" fill="currentColor" viewBox="0 0 24 24"><path d="M17.6 6.32A7.85 7.85 0 0012.05 4a7.94 7.94 0 00-6.88 11.93L4 20l4.21-1.1a7.95 7.95 0 003.84.98h.01a7.94 7.94 0 005.54-13.56M12.05 18.5a6.62 6.62 0 01-3.36-.92l-.24-.14-2.5.66.67-2.44-.16-.25a6.59 6.59 0 0110.21-8.16 6.55 6.55 0 011.93 4.66 6.62 6.62 0 01-6.55 6.59"/></svg>
                    <?= epl_h($c['nombre'] ?: 'Contacto') ?>
                    <span style="font-size:.7rem;opacity:.85">· <?= epl_h($c['telefono']) ?></span>
                  </a>
                  <?php endforeach; ?>
                </div>

                <!-- Preview del mensaje -->
                <details style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;padding:.75rem 1rem;margin-bottom:.5rem">
                  <summary style="cursor:pointer;font-size:.78rem;font-weight:700;color:#1c2f48">👁 Ver el mensaje que se va a enviar</summary>
                  <pre style="margin:.6rem 0 0;padding:.85rem;background:#fff;border-radius:8px;font-family:inherit;font-size:.78rem;color:#475569;white-space:pre-wrap;line-height:1.5"><?= epl_h($_msg_wsp) ?></pre>
                </details>

                <?php if ($baja_solicitada): ?>
                  <div style="margin-top:.5rem;padding:.65rem .85rem;background:#fef3c7;border-radius:8px;font-size:.78rem;color:#92400e">
                    ⏳ Ya enviaste el mensaje (<?= date('d/m/Y H:i', strtotime($p['baja_solicitada_at'])) ?>). Esperando que el club confirme el link.
                  </div>
                <?php endif; ?>
              <?php endif; ?>  <!-- _baja_fecha_lbl -->
            <?php endif; ?>  <!-- contactos_club -->
          <?php endif; ?>  <!-- baja_confirmada -->

        <?php endif; ?>  <!-- _hay_info -->

        <!-- Editar/registrar reserva original -->
        <details id="dlReservaOriginal" style="margin-top:1rem;background:#fefce8;border:1px solid #fde68a;border-radius:10px;padding:.75rem 1rem">
          <summary style="cursor:pointer;font-size:.78rem;font-weight:700;color:#92400e">
            <?= $tiene_original ? '✏️ Editar' : '➕ Registrar manualmente' ?> reserva original
          </summary>
          <form method="post" style="margin-top:.85rem">
            <input type="hidden" name="action" value="guardar_original">
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:.6rem;margin-bottom:.7rem">
              <div>
                <label style="font-size:.7rem;color:#92400e;font-weight:700;display:block;margin-bottom:.25rem">Fecha original</label>
                <input type="text" name="fecha_original" id="fpFechaOriginal" class="form-control"
                       placeholder="Día y hora (opcional)" autocomplete="off" readonly
                       style="cursor:pointer;background:#fff"
                       value="<?= $p['fecha_original'] ? date('Y-m-d H:i', strtotime($p['fecha_original'])) : '' ?>">
              </div>
              <div>
                <label style="font-size:.7rem;color:#92400e;font-weight:700;display:block;margin-bottom:.25rem">Cancha original</label>
                <select name="recinto_original_id" class="form-control">
                  <option value="">— Sin registrar —</option>
                  <?php foreach ($todos_recintos as $rc): ?>
                    <option value="<?= $rc['id'] ?>" <?= ((int)$p['recinto_original_id'] === (int)$rc['id'])?'selected':'' ?>>
                      <?= epl_h($rc['label']) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>
            <button type="submit" class="btn btn-sm" style="background:#92400e;color:#fff">Guardar</button>
          </form>
        </details>

      </div>
    </section>
    <?php endif; ?>

    <!-- ═══════════ PEDIR CANCHA NUEVA ═══════════ -->
    <?php
      // ¿Ya confirmó cancha el club vía el link?
      $_cancha_ya_conf = !empty($p['cancha_confirmada_at']);
      $_pedir_cancha_visible = $es_reprog && $_tiene_fecha_nueva && (empty($p['recinto_id']) || !empty($p['cancha_solicitada_at']));
    ?>
    <?php if ($_pedir_cancha_visible): ?>
    <section class="card mb-3" style="border-left:5px solid <?= $_cancha_ya_conf ? '#10b981' : '#3b82f6' ?>">
      <div class="card-head" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:.5rem">
        <h2 style="font-family:'Anton',sans-serif;font-size:1.05rem;color:var(--navy);text-transform:uppercase;margin:0">
          🎾 Cancha nueva
        </h2>
        <?php if ($_cancha_ya_conf): ?>
          <span style="background:#dcfce7;color:#15803d;padding:.3rem .85rem;border-radius:999px;font-size:.72rem;font-weight:800;text-transform:uppercase;letter-spacing:.05em">✅ Confirmada por el club</span>
        <?php elseif (!empty($p['cancha_solicitada_at'])): ?>
          <span style="background:#dbeafe;color:#1e40af;padding:.3rem .85rem;border-radius:999px;font-size:.72rem;font-weight:800;text-transform:uppercase;letter-spacing:.05em">⏳ Link enviado</span>
        <?php else: ?>
          <span style="background:#dbeafe;color:#1e40af;padding:.3rem .85rem;border-radius:999px;font-size:.72rem;font-weight:800;text-transform:uppercase;letter-spacing:.05em">Falta cancha</span>
        <?php endif; ?>
      </div>
      <div class="card-body">

        <?php if ($_cancha_ya_conf): ?>
          <!-- ✅ Ya confirmada -->
          <div style="background:#dcfce7;border:1px solid #86efac;border-radius:10px;padding:1rem;margin-bottom:.75rem;text-align:center">
            <div style="font-size:2rem">🎾</div>
            <div style="font-weight:800;color:#15803d;margin:.3rem 0"><?= epl_h($p['recinto_nombre'] ?: '—') ?></div>
            <div style="font-size:.78rem;color:#15803d">
              Confirmada <?= date('d/m/Y H:i', strtotime($p['cancha_confirmada_at'])) ?>
              <?php if ($p['cancha_confirmada_por']): ?> · por <strong><?= epl_h($p['cancha_confirmada_por']) ?></strong><?php endif; ?>
            </div>
          </div>
        <?php else: ?>
          <!-- Info de la fecha sin cancha -->
          <div style="background:#dbeafe;border-left:4px solid #2563eb;padding:.85rem 1rem;border-radius:8px;margin-bottom:1rem">
            <div style="font-size:.65rem;color:#1e40af;font-weight:800;text-transform:uppercase;letter-spacing:.08em">📅 Nueva fecha (sin cancha asignada)</div>
            <div style="font-weight:800;color:#1e3a8a;font-size:1rem;margin-top:.3rem"><?= date('d/m/Y H:i', strtotime($p['fecha_programada'])) ?></div>
            <div style="font-size:.78rem;color:#1e40af;margin-top:.15rem">Enviá el link al club para que elijan la cancha desde el celular.</div>
          </div>

          <!-- Link de confirmación -->
          <?php if ($_cancha_link): ?>
          <div style="background:#f0f9ff;border:1px solid #bfdbfe;border-radius:10px;padding:.85rem 1rem;margin-bottom:1rem">
            <div style="font-size:.68rem;color:#1e40af;font-weight:800;text-transform:uppercase;letter-spacing:.06em;margin-bottom:.4rem">🔗 Link para el club</div>
            <div style="display:flex;gap:.5rem;align-items:center;flex-wrap:wrap">
              <code style="flex:1;font-size:.72rem;color:#1e3a8a;background:#dbeafe;padding:.4rem .6rem;border-radius:6px;word-break:break-all;line-height:1.4"><?= epl_h($_cancha_link) ?></code>
              <button onclick="navigator.clipboard.writeText('<?= epl_h($_cancha_link) ?>');this.textContent='✓ Copiado!';setTimeout(()=>this.textContent='📋 Copiar',1500)"
                      type="button" style="background:#1e40af;color:#fff;border:none;padding:.4rem .8rem;border-radius:8px;font-size:.72rem;font-weight:700;cursor:pointer;white-space:nowrap">
                📋 Copiar
              </button>
            </div>
          </div>
          <?php endif; ?>

          <?php if (empty($contactos_club)): ?>
            <div style="background:#fee2e2;border:1px solid #fca5a5;border-radius:10px;padding:1rem;font-size:.88rem;color:#991b1b">
              ⚠️ Este recinto no tiene contactos cargados.
              <a href="recintos.php" style="color:#1c2f48;font-weight:800;text-decoration:underline">Cargá los contactos del club acá →</a>
            </div>
          <?php else: ?>
            <p style="font-size:.85rem;color:#475569;margin-bottom:.5rem;font-weight:600">
              📱 Enviar link al club por WhatsApp
              <?php if ($contactos_origen && $contactos_origen !== ($p['recinto_original_nombre'] ?: $p['recinto_nombre'])): ?>
                <span style="font-weight:500;color:#64748b;font-size:.78rem">(contactos de <?= epl_h($contactos_origen) ?>)</span>
              <?php endif; ?>
            </p>
            <div style="display:flex;gap:.5rem;flex-wrap:wrap;margin-bottom:1rem">
              <?php foreach ($contactos_club as $c):
                $_tel = preg_replace('/[^0-9]/', '', $c['telefono']);
                if (!$_tel) continue;
                if (substr($_tel, 0, 2) !== '56') $_tel = '56' . $_tel;
                $_wsp_c = "https://wa.me/{$_tel}?text=" . rawurlencode($_msg_cancha);
              ?>
              <a href="<?= $_wsp_c ?>" target="_blank" rel="noopener" style="display:inline-flex;align-items:center;gap:.5rem;background:#25D366;color:#fff;padding:.65rem 1.2rem;border-radius:10px;font-size:.85rem;font-weight:800;text-decoration:none;box-shadow:0 4px 12px rgba(37,211,102,.3)">
                <svg width="18" height="18" fill="currentColor" viewBox="0 0 24 24"><path d="M17.6 6.32A7.85 7.85 0 0012.05 4a7.94 7.94 0 00-6.88 11.93L4 20l4.21-1.1a7.95 7.95 0 003.84.98h.01a7.94 7.94 0 005.54-13.56M12.05 18.5a6.62 6.62 0 01-3.36-.92l-.24-.14-2.5.66.67-2.44-.16-.25a6.59 6.59 0 0110.21-8.16 6.55 6.55 0 011.93 4.66 6.62 6.62 0 01-6.55 6.59"/></svg>
                <?= epl_h($c['nombre'] ?: 'Contacto') ?>
                <span style="font-size:.7rem;opacity:.85">· <?= epl_h($c['telefono']) ?></span>
              </a>
              <?php endforeach; ?>
            </div>

            <!-- Preview del mensaje -->
            <details style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;padding:.75rem 1rem">
              <summary style="cursor:pointer;font-size:.78rem;font-weight:700;color:#1c2f48">👁 Ver el mensaje que se va a enviar</summary>
              <pre style="margin:.6rem 0 0;padding:.85rem;background:#fff;border-radius:8px;font-family:inherit;font-size:.78rem;color:#475569;white-space:pre-wrap;line-height:1.5"><?= epl_h($_msg_cancha) ?></pre>
            </details>
          <?php endif; ?>
        <?php endif; ?>

      </div>
    </section>
    <?php endif; ?>

    <!-- ═══════════ INFO DEL PARTIDO ═══════════ -->
    <section class="card mb-3">
      <div class="card-head">
        <h2 style="font-family:'Anton',sans-serif;font-size:1rem;color:var(--navy);text-transform:uppercase;margin:0">📋 Información del partido</h2>
      </div>
      <div class="card-body">
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:.85rem">
          <div>
            <div style="font-size:.68rem;color:#64748b;font-weight:700;text-transform:uppercase">Liga</div>
            <div style="font-weight:700;color:#1c2f48"><?= epl_h($p['liga_nombre']) ?></div>
          </div>
          <div>
            <div style="font-size:.68rem;color:#64748b;font-weight:700;text-transform:uppercase">Jornada</div>
            <div style="font-weight:700;color:#1c2f48">J<?= $p['jornada'] ?: '—' ?><?= $p['nombre_fecha'] ? ' · ' . epl_h($p['nombre_fecha']) : '' ?></div>
          </div>
          <div>
            <div style="font-size:.68rem;color:#64748b;font-weight:700;text-transform:uppercase">Fecha</div>
            <div style="font-weight:700;color:#1c2f48"><?= $_fecha_lbl ?></div>
          </div>
          <div>
            <div style="font-size:.68rem;color:#64748b;font-weight:700;text-transform:uppercase">Cancha actual</div>
            <div style="font-weight:700;color:#1c2f48"><?= epl_h($p['recinto_nombre'] ?: '—') ?></div>
            <?php if ($p['recinto_sup']): ?><div style="font-size:.72rem;color:#94a3b8"><?= epl_h($p['recinto_sup']) ?></div><?php endif; ?>
          </div>
          <?php if ($p['estado'] === 'jugado'): ?>
          <div>
            <div style="font-size:.68rem;color:#64748b;font-weight:700;text-transform:uppercase">Resultado</div>
            <div style="font-weight:800;font-family:'Anton',sans-serif;font-size:1.4rem;color:#1c2f48"><?= $p['sets_local'] ?>–<?= $p['sets_visitante'] ?></div>
          </div>
          <?php endif; ?>
        </div>

        <?php if ($p['alerta_admin']): ?>
        <div style="margin-top:1rem;padding:.65rem .85rem;background:#fee2e2;border-left:3px solid #dc2626;border-radius:6px;font-size:.82rem;color:#991b1b">
          <strong>⚠ Alerta admin:</strong> <?= epl_h($p['alerta_admin']) ?>
        </div>
        <?php endif; ?>
      </div>
    </section>

    <!-- ═══════════ JUGADORES + WHATSAPP ═══════════ -->
    <section class="card mb-3">
      <div class="card-head">
        <h2 style="font-family:'Anton',sans-serif;font-size:1rem;color:var(--navy);text-transform:uppercase;margin:0">👥 Jugadores</h2>
      </div>
      <div class="card-body" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:.75rem">
        <?php
        $jugadores_box = [
          ['n'=>$p['jl1_n'], 'a'=>$p['jl1_a'], 't'=>$p['jl1_t'], 'rol'=>$p['local_nombre']],
          ['n'=>$p['jl2_n'], 'a'=>$p['jl2_a'], 't'=>$p['jl2_t'], 'rol'=>$p['local_nombre']],
          ['n'=>$p['jv1_n'], 'a'=>$p['jv1_a'], 't'=>$p['jv1_t'], 'rol'=>$p['visitante_nombre']],
          ['n'=>$p['jv2_n'], 'a'=>$p['jv2_a'], 't'=>$p['jv2_t'], 'rol'=>$p['visitante_nombre']],
        ];
        foreach ($jugadores_box as $jb):
          if (!$jb['n']) continue;
          $tel = $jb['t'] ? preg_replace('/[^0-9]/', '', $jb['t']) : '';
          $wsp = '';
          if ($tel) {
            $cl = substr($tel,0,2) === '56' ? $tel : '56'.$tel;
            $jmsg = "Hola " . $jb['n'] . ", te hablo de Elite Padel League sobre tu partido " . $p['local_nombre'] . " vs " . $p['visitante_nombre'];
            $wsp = "https://wa.me/{$cl}?text=" . rawurlencode($jmsg);
          }
        ?>
        <div style="background:#f8fafc;padding:.85rem;border-radius:10px;border:1px solid #e2e8f0">
          <div style="font-size:.65rem;color:#94a3b8;font-weight:700;text-transform:uppercase"><?= epl_h($jb['rol']) ?></div>
          <div style="font-weight:700;color:#1c2f48;font-size:.92rem;margin:.2rem 0 .5rem"><?= epl_h($jb['n'] . ' ' . ($jb['a']??'')) ?></div>
          <?php if ($wsp): ?>
            <a href="<?= $wsp ?>" target="_blank" style="display:inline-flex;align-items:center;gap:.35rem;background:#25D366;color:#fff;padding:.35rem .7rem;border-radius:6px;font-size:.72rem;font-weight:700;text-decoration:none">📱 WhatsApp</a>
          <?php else: ?>
            <span style="font-size:.7rem;color:#94a3b8;font-style:italic">Sin teléfono</span>
          <?php endif; ?>
        </div>
        <?php endforeach; ?>
      </div>
    </section>

    <!-- ═══════════ SOLICITUD DE REPROGRAMACIÓN ═══════════ -->
    <?php if ($sol): ?>
    <section class="card mb-3">
      <div class="card-head">
        <h2 style="font-family:'Anton',sans-serif;font-size:1rem;color:var(--navy);text-transform:uppercase;margin:0">📨 Última solicitud de reprogramación</h2>
      </div>
      <div class="card-body">
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:.75rem;font-size:.82rem">
          <div>
            <div style="font-size:.65rem;color:#64748b;font-weight:700;text-transform:uppercase">Solicitante</div>
            <div style="font-weight:700;color:#1c2f48"><?= epl_h($sol['sol_n'] . ' ' . $sol['sol_a']) ?></div>
          </div>
          <div>
            <div style="font-size:.65rem;color:#64748b;font-weight:700;text-transform:uppercase">Estado</div>
            <?php
              $sol_color = match($sol['estado']) { 'aprobada' => '#15803d', 'rechazada' => '#991b1b', default => '#92400e' };
              $sol_bg    = match($sol['estado']) { 'aprobada' => '#dcfce7', 'rechazada' => '#fee2e2', default => '#fef3c7' };
            ?>
            <span style="background:<?= $sol_bg ?>;color:<?= $sol_color ?>;padding:.2rem .6rem;border-radius:6px;font-size:.7rem;font-weight:800;text-transform:uppercase"><?= $sol['estado'] ?></span>
          </div>
          <div>
            <div style="font-size:.65rem;color:#64748b;font-weight:700;text-transform:uppercase">Solicitado</div>
            <div style="font-weight:700;color:#1c2f48"><?= date('d/m/Y H:i', strtotime($sol['created_at'])) ?></div>
          </div>
          <div>
            <div style="font-size:.65rem;color:#64748b;font-weight:700;text-transform:uppercase">Fecha propuesta</div>
            <div style="font-weight:700;color:#1c2f48"><?= $sol['fecha_propuesta'] ? date('d/m/Y H:i', strtotime($sol['fecha_propuesta'])) : '—' ?></div>
          </div>
        </div>
        <?php if (!empty($sol['motivo'])): ?>
        <div style="margin-top:.85rem;padding:.65rem .85rem;background:#f8fafc;border-left:3px solid #C9A762;border-radius:6px;font-size:.82rem;color:#475569;font-style:italic">
          "<?= epl_h($sol['motivo']) ?>"
        </div>
        <?php endif; ?>
      </div>
    </section>
    <?php endif; ?>

    <!-- ═══════════ ACCIONES MANUALES ═══════════ -->
    <section class="card mb-3" style="border-left:5px solid #8b5cf6">
      <div class="card-head" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:.5rem">
        <h2 style="font-family:'Anton',sans-serif;font-size:1.05rem;color:var(--navy);text-transform:uppercase;margin:0">
          ⚡ Acciones manuales
        </h2>
        <span style="background:#ede9fe;color:#6d28d9;padding:.3rem .85rem;border-radius:999px;font-size:.72rem;font-weight:800;text-transform:uppercase;letter-spacing:.05em">Admin</span>
      </div>
      <div class="card-body">
        <p style="font-size:.82rem;color:#64748b;margin-bottom:1rem">
          Usá estas acciones cuando resolvés la gestión sin esperar confirmaciones del club o los jugadores.
        </p>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:1rem">

          <!-- APROBAR REPROGRAMACIÓN -->
          <?php if ($p['estado'] === 'reprogramado'): ?>
          <div style="background:#f0fdf4;border:1px solid #86efac;border-radius:12px;padding:1rem">
            <div style="font-weight:800;color:#14532d;font-size:.9rem;margin-bottom:.4rem">✅ Aprobar reprogramación</div>
            <div style="font-size:.78rem;color:#15803d;margin-bottom:.85rem;line-height:1.4">
              Aplica la nueva fecha y deja el partido como <strong>Pendiente</strong>.
              <?php if ($_propuesta_lbl): ?>
                <br>Propuesta: <strong><?= epl_h($_propuesta_lbl) ?></strong>
              <?php endif; ?>
            </div>
            <form method="post">
              <input type="hidden" name="action" value="aprobar_reprog">
              <?php if (!empty($_canchas_club)): ?>
                <label style="font-size:.7rem;color:#15803d;font-weight:800;display:block;margin-bottom:.3rem;text-transform:uppercase;letter-spacing:.05em">
                  🎾 Cancha (opcional)
                </label>
                <select name="recinto_nuevo_id" class="form-control" style="margin-bottom:.6rem;font-size:.85rem">
                  <option value="">— Que el club elija desde el link —</option>
                  <?php foreach ($_canchas_club as $c): ?>
                    <option value="<?= (int)$c['id'] ?>"><?= epl_h($c['nombre']) ?></option>
                  <?php endforeach; ?>
                </select>
                <div style="font-size:.68rem;color:#15803d;margin-bottom:.65rem;line-height:1.35;font-style:italic">
                  Si ya sabés qué cancha asignan, elegila acá. Si no, dejá que el club la elija desde el WhatsApp.
                </div>
              <?php endif; ?>
              <button type="submit" class="btn btn-sm" style="background:#15803d;color:#fff;width:100%"
                      data-confirm="¿Aprobar la reprogramación y aplicar la nueva fecha?" data-confirm-ok="Sí, aprobar">
                ✅ Aprobar reprogramación
              </button>
            </form>
          </div>
          <?php endif; ?>

          <!-- CANCELAR PARTIDO -->
          <?php if (!in_array($p['estado'], ['jugado'])): ?>
          <div style="background:#fef2f2;border:1px solid #fca5a5;border-radius:12px;padding:1rem">
            <div style="font-weight:800;color:#7f1d1d;font-size:.9rem;margin-bottom:.4rem">❌ Cancelar partido</div>
            <div style="font-size:.78rem;color:#991b1b;margin-bottom:.85rem;line-height:1.4">
              Marcá el partido como Walkover o No presentado. La solicitud pendiente se rechaza automáticamente.
            </div>
            <form method="post" id="formCancelar">
              <input type="hidden" name="action" value="cancelar_partido">
              <div style="display:flex;gap:.5rem;margin-bottom:.6rem">
                <label style="flex:1;display:flex;align-items:center;gap:.4rem;background:<?= $p['estado']==='walkover'?'#fee2e2':'#fff' ?>;border:1.5px solid <?= $p['estado']==='walkover'?'#dc2626':'#e2e8f0' ?>;border-radius:8px;padding:.5rem .7rem;cursor:pointer;font-size:.8rem;font-weight:700;color:#7f1d1d">
                  <input type="radio" name="nuevo_estado" value="walkover" <?= $p['estado']==='walkover'?'checked':'' ?> style="margin:0"> Walkover
                </label>
                <label style="flex:1;display:flex;align-items:center;gap:.4rem;background:<?= $p['estado']==='no_presentado'?'#fee2e2':'#fff' ?>;border:1.5px solid <?= $p['estado']==='no_presentado'?'#dc2626':'#e2e8f0' ?>;border-radius:8px;padding:.5rem .7rem;cursor:pointer;font-size:.8rem;font-weight:700;color:#7f1d1d">
                  <input type="radio" name="nuevo_estado" value="no_presentado" <?= $p['estado']==='no_presentado'?'checked':'' ?> style="margin:0"> No presentado
                </label>
              </div>
              <input type="text" name="nota_cancelar" class="form-control" placeholder="Nota (opcional)" style="margin-bottom:.6rem;font-size:.8rem">
              <button type="submit" class="btn btn-sm" style="background:#dc2626;color:#fff;width:100%"
                      data-confirm="¿Cancelar el partido?" data-confirm-ok="Sí, cancelar">
                ❌ Cancelar partido
              </button>
            </form>
          </div>
          <?php endif; ?>

          <!-- VOLVER A PENDIENTE (si está en walkover/no_presentado) -->
          <?php if (in_array($p['estado'], ['walkover','no_presentado'])): ?>
          <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:12px;padding:1rem">
            <div style="font-weight:800;color:#1c2f48;font-size:.9rem;margin-bottom:.4rem">↩️ Revertir a Pendiente</div>
            <div style="font-size:.78rem;color:#64748b;margin-bottom:.85rem;line-height:1.4">
              Si el partido fue cancelado por error, revertilo a estado pendiente.
            </div>
            <form method="post">
              <input type="hidden" name="action" value="revertir_pendiente">
              <button type="submit" class="btn btn-sm" style="background:#1c2f48;color:#C9A762;width:100%"
                      data-confirm="¿Revertir el partido a Pendiente?" data-confirm-ok="Sí, revertir">
                ↩️ Revertir a Pendiente
              </button>
            </form>
          </div>
          <?php endif; ?>

        </div>
      </div>
    </section>

    <!-- ═══════════ ACCIONES ═══════════ -->
    <section class="card">
      <div class="card-body" style="display:flex;gap:.5rem;flex-wrap:wrap">
        <a href="partidos.php?liga=<?= $p['liga_id'] ?>#p<?= $p['id'] ?>" class="btn btn-sm" style="background:var(--navy);color:var(--gold);font-weight:800">✏️ Editar partido</a>
        <a href="dashboard_repro.php" class="btn btn-sm" style="background:#f1f5f9;color:#475569;border:1px solid #cbd5e1">← Volver al panel</a>
      </div>
    </section>

  </main>
</div>

<!-- ── Flatpickr: calendario moderno ── -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/flatpickr.min.css">
<script src="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/flatpickr.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/l10n/es.js"></script>
<style>
  .flatpickr-calendar {
    background:#fff !important; border-radius:16px !important;
    box-shadow:0 20px 50px rgba(15,23,42,.15), 0 0 0 1px rgba(15,23,42,.06) !important;
    font-family:'Montserrat',sans-serif !important; border:none !important;
    overflow:hidden; padding:.4rem;
  }
  .flatpickr-calendar.open { z-index:9999 !important; }
  .flatpickr-calendar::before, .flatpickr-calendar::after { display:none !important; }
  .flatpickr-months { padding:.55rem .35rem .3rem; }
  .flatpickr-months .flatpickr-month { background:transparent !important; color:#0f172a !important; height:38px; }
  .flatpickr-current-month { padding-top:.35rem !important; font-size:.95rem !important; color:#0f172a !important; font-weight:800 !important; display:flex; align-items:center; justify-content:center; gap:.35rem; }
  .flatpickr-current-month .flatpickr-monthDropdown-months,
  .flatpickr-current-month input.cur-year { color:#0f172a !important; font-weight:800 !important; font-size:.95rem !important; }
  .flatpickr-current-month .flatpickr-monthDropdown-months:hover { background:#f1f5f9 !important; }
  .flatpickr-monthDropdown-months option { background:#fff; color:#0f172a; }
  .flatpickr-prev-month, .flatpickr-next-month { color:#475569 !important; padding:.4rem !important; border-radius:8px; top:.45rem !important; }
  .flatpickr-prev-month:hover, .flatpickr-next-month:hover { background:#f1f5f9 !important; }
  .flatpickr-prev-month svg, .flatpickr-next-month svg { fill:#475569 !important; width:13px; height:13px; }
  .flatpickr-weekdays { background:transparent !important; height:30px; }
  .flatpickr-weekday { color:#94a3b8 !important; font-weight:700 !important; font-size:.68rem !important; text-transform:uppercase; letter-spacing:.04em; }
  .flatpickr-days { padding:0 .15rem .25rem; }
  .dayContainer { padding:0 !important; }
  .flatpickr-day { font-weight:600 !important; color:#0f172a !important; border-radius:10px !important; border:none !important; height:38px !important; line-height:38px !important; margin:1px 0 !important; max-width:38px; }
  .flatpickr-day:hover { background:#f1f5f9 !important; color:#0f172a !important; }
  .flatpickr-day.today { border:none !important; color:#C9A762 !important; font-weight:800 !important; background:transparent !important; position:relative; }
  .flatpickr-day.today::after { content:''; position:absolute; bottom:5px; left:50%; transform:translateX(-50%); width:4px; height:4px; border-radius:50%; background:#C9A762; }
  .flatpickr-day.today.selected::after { background:#fff; }
  .flatpickr-day.selected, .flatpickr-day.selected:hover,
  .flatpickr-day.startRange, .flatpickr-day.endRange {
    background:#1c2f48 !important; color:#fff !important;
    box-shadow:0 4px 12px rgba(28,47,72,.25); font-weight:800 !important;
  }
  .flatpickr-day.flatpickr-disabled,
  .flatpickr-day.prevMonthDay, .flatpickr-day.nextMonthDay { color:#cbd5e1 !important; background:transparent !important; }
  .flatpickr-day.flatpickr-disabled { text-decoration:line-through; opacity:.5; }
  .flatpickr-time { border-top:1px solid #f1f5f9 !important; background:#f8fafc !important; margin:.35rem -.4rem -.4rem !important; padding:.7rem !important; border-radius:0 0 12px 12px; height:auto !important; }
  .flatpickr-time .numInputWrapper { background:#fff !important; border-radius:8px; box-shadow:0 1px 2px rgba(15,23,42,.05); }
  .flatpickr-time input { color:#0f172a !important; font-weight:800 !important; font-size:1.05rem !important; background:#fff !important; border-radius:8px; }
  .flatpickr-time .flatpickr-time-separator { color:#0f172a !important; font-weight:800 !important; font-size:1.05rem !important; }
  .numInputWrapper span { border-color:#e2e8f0 !important; }
  .numInputWrapper span.arrowUp:after { border-bottom-color:#475569 !important; }
  .numInputWrapper span.arrowDown:after { border-top-color:#475569 !important; }
  .numInputWrapper span:hover:after { border-bottom-color:#C9A762 !important; border-top-color:#C9A762 !important; }

  /* Input EDIT reserva original (estilo dorado suave) */
  #fpFechaOriginal { padding:.8rem 1rem !important; border:1.5px solid #fde68a !important; border-radius:10px !important; font-size:.92rem !important; font-weight:700 !important; color:#0f172a !important; background:#fff !important; }
  #fpFechaOriginal:hover { border-color:#fbbf24 !important; }
  #fpFechaOriginal:focus { border-color:#92400e !important; box-shadow:0 0 0 3px rgba(146,64,14,.08) !important; outline:none !important; }
</style>
<script>
document.addEventListener('DOMContentLoaded', function() {
  const el = document.getElementById('fpFechaOriginal');
  if (!el || typeof flatpickr === 'undefined') return;
  flatpickr(el, {
    locale: 'es',
    enableTime: true,
    time_24hr: true,
    dateFormat: 'Y-m-d H:i',
    altInput: true,
    altFormat: 'l j \\d\\e F · H:i',
    allowInput: false,
    minuteIncrement: 15,
    defaultHour: 21,
    defaultMinute: 0,
    disableMobile: false,
    position: 'auto center'
  });
});
</script>

<?php require_once '../includes/footer.php'; ?>
