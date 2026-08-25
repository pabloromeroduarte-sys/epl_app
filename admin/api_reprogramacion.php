<?php
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../includes/mail.php';
epl_require_admin();

$db     = epl_db();
$id     = (int)($_POST['id']     ?? 0);
$accion = $_POST['accion']       ?? '';
$admin  = epl_jugador_actual();
$return_to = (string)($_POST['return_to'] ?? 'index.php');
if (!preg_match('/^(?:index|dashboard_repro)\.php(?:\?[a-z0-9_=&-]+)?$/i', $return_to)) {
    $return_to = 'index.php';
}

if ($accion === 'reenviar_notif_cancha') {
    $partido_id = (int)($_POST['partido_id'] ?? 0);
    if ($partido_id) {
        epl_notificar_asignacion_cancha($partido_id);
    }
    header('Content-Type: application/json');
    echo json_encode(['ok' => true]);
    exit;
}

if (!$id || !in_array($accion, ['aprobar','rechazar'])) {
    header('Location: index.php');
    exit;
}

$stI = $db->prepare("SELECT * FROM solicitudes_reprogramacion WHERE id=?");
$stI->execute([$id]);
$sol = $stI->fetch();

if (!$sol) { header('Location: index.php?err=notfound'); exit; }

// Obtener jugadores de ambos equipos del partido para notificarlos
function reprog_jugadores_partido(PDO $db, int $partido_id): array {
    $st = $db->prepare("
        SELECT j.id, j.nombre, j.apellido
        FROM partidos p
        JOIN equipos el ON el.id = p.equipo_local_id
        JOIN equipos ev ON ev.id = p.equipo_visitante_id
        JOIN jugadores j ON j.id IN (el.jugador1_id, el.jugador2_id, ev.jugador1_id, ev.jugador2_id)
        WHERE p.id = ?
    ");
    $st->execute([$partido_id]);
    return $st->fetchAll();
}

if ($accion === 'aprobar') {
    $fecha_aprobada  = trim($_POST['fecha_aprobada']  ?? '') ?: $sol['fecha_propuesta'];

    $db->prepare("UPDATE solicitudes_reprogramacion SET
        estado='aprobada', fecha_aprobada=?, cancha_aprobada=NULL, aprobado_por=?
        WHERE id=?
    ")->execute([$fecha_aprobada, $admin['id'], $id]);

    // Capturar snapshot original ANTES de cambiar fecha/cancha
    epl_partido_snapshot_original((int)$sol['partido_id']);

    // La organización aprueba la fecha. La cancha nueva queda separada de la
    // reserva original y solo se confirma cuando responde el club (o cuando el
    // admin la asigna explícitamente en la ficha).
    $db->prepare("UPDATE partidos SET estado='reprogramado', fecha_programada=?, recinto_id=NULL, cancha=NULL,
                    cancha_token=NULL, cancha_solicitada_at=NULL,
                    cancha_confirmada_at=NULL, cancha_confirmada_por=NULL
                  WHERE id=?")
       ->execute([$fecha_aprobada, $sol['partido_id']]);

    // Notificar a todos los jugadores del partido
    $fecha_fmt = $fecha_aprobada ? date('d/m/Y H:i', strtotime($fecha_aprobada)) : 'Sin fecha definida';

    // Cargar nombres de equipos para el email visual
    $stPN = $db->prepare("
        SELECT el.nombre AS local_nombre, ev.nombre AS visitante_nombre, p.jornada
        FROM partidos p
        JOIN equipos el ON el.id = p.equipo_local_id
        JOIN equipos ev ON ev.id = p.equipo_visitante_id
        WHERE p.id = ?
    ");
    $stPN->execute([$sol['partido_id']]);
    $pnombres = $stPN->fetch(PDO::FETCH_ASSOC);

    $filas_aprob = [
        ['icon' => '📅', 'label' => 'Nueva fecha', 'valor' => $fecha_fmt],
        ['icon' => '🏟️', 'label' => 'Cancha', 'valor' => $fecha_aprobada ? 'Por confirmar con el club' : 'Se coordinará cuando exista una fecha'],
    ];

    foreach (reprog_jugadores_partido($db, $sol['partido_id']) as $j) {
        $msg = $fecha_aprobada
            ? 'La reprogramación fue aprobada para el ' . $fecha_fmt . '. La cancha será confirmada por el club.'
            : 'La reprogramación fue aprobada sin fecha definida. Coordina una nueva fecha con el rival.';
        $msg .= ' Revisa tus partidos para más detalles.';
        epl_notif_crear((int)$j['id'], 'reprogramacion', '📅 Partido reprogramado', $msg, epl_url('reprogramar.php#mis-reprogramaciones'), true);
        epl_mail_partido_visual(
            (int)$j['id'],
            epl_mail_asunto('📅 Partido reprogramado', $pnombres['local_nombre'] ?? null, $pnombres['visitante_nombre'] ?? null, $pnombres['jornada'] ?? null),
            $pnombres ? $pnombres['local_nombre']     : null,
            $pnombres ? $pnombres['visitante_nombre'] : null,
            $filas_aprob,
            'La organización aprobó la reprogramación.',
            $fecha_aprobada
                ? '✅ La fecha quedó aprobada. El club debe confirmar la cancha antes del partido.'
                : '✅ El cambio quedó aprobado sin fecha definida. Coordina una nueva fecha con el rival.',
            epl_url('reprogramar.php#mis-reprogramaciones')
        );
    }

    $sep = str_contains($return_to, '?') ? '&' : '?';
    header('Location: ' . $return_to . $sep . 'ok=reprog_aprobada');
} else {
    $db->prepare("UPDATE solicitudes_reprogramacion SET estado='rechazada', aprobado_por=? WHERE id=?")
       ->execute([$admin['id'], $id]);

    // Restaurar partido a pendiente con su fecha/cancha original
    $pid = (int)$sol['partido_id'];
    $row = $db->prepare("SELECT fecha_original, recinto_original_id FROM partidos WHERE id=?");
    $row->execute([$pid]);
    $orig = $row->fetch(PDO::FETCH_ASSOC);

    $sets = "estado='pendiente', fecha_original=NULL, recinto_original_id=NULL";
    if ($orig && !empty($orig['fecha_original'])) {
        $sets .= ", fecha_programada=" . $db->quote($orig['fecha_original']);
    }
    if ($orig && !empty($orig['recinto_original_id'])) {
        $sets .= ", recinto_id=" . (int)$orig['recinto_original_id'];
    }
    $db->exec("UPDATE partidos SET $sets WHERE id=$pid");

    // Notificar al solicitante que fue rechazada
    if ($sol['solicitante_id']) {
        epl_notif_crear((int)$sol['solicitante_id'], 'reprogramacion',
            '❌ Reprogramación no aprobada',
            'Tu solicitud de reprogramación no fue aprobada. Coordina directamente con tu rival.',
            epl_url('reprogramar.php#mis-reprogramaciones')
        );
    }

    $sep = str_contains($return_to, '?') ? '&' : '?';
    header('Location: ' . $return_to . $sep . 'ok=reprog_rechazada');
}
exit;
