<?php
require_once '../includes/auth.php';
require_once '../includes/functions.php';
epl_require_admin();

$db     = epl_db();
$id     = (int)($_POST['id']     ?? 0);
$accion = $_POST['accion']       ?? '';
$admin  = epl_jugador_actual();

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
    $cancha_aprobada = trim($_POST['cancha_aprobada'] ?? '');

    $db->prepare("UPDATE solicitudes_reprogramacion SET
        estado='aprobada', fecha_aprobada=?, cancha_aprobada=?, aprobado_por=?
        WHERE id=?
    ")->execute([$fecha_aprobada, $cancha_aprobada?:null, $admin['id'], $id]);

    // Actualizar el partido con la nueva fecha y cancha
    $db->prepare("UPDATE partidos SET estado='reprogramado', fecha_programada=?, cancha=? WHERE id=?")
       ->execute([$fecha_aprobada, $cancha_aprobada?:null, $sol['partido_id']]);

    // Notificar a todos los jugadores del partido
    $fecha_fmt = $fecha_aprobada ? date('d/m/Y H:i', strtotime($fecha_aprobada)) : 'nueva fecha';
    foreach (reprog_jugadores_partido($db, $sol['partido_id']) as $j) {
        $msg = 'Tu partido fue reprogramado para el ' . $fecha_fmt;
        if ($cancha_aprobada) $msg .= ' en ' . $cancha_aprobada;
        $msg .= '. Revisa tus partidos para más detalles.';
        epl_notif_crear((int)$j['id'], 'reprogramacion', '📅 Partido reprogramado', $msg, epl_url('mis_partidos.php'));
    }

    header('Location: index.php?ok=reprog_aprobada');
} else {
    $db->prepare("UPDATE solicitudes_reprogramacion SET estado='rechazada', aprobado_por=? WHERE id=?")
       ->execute([$admin['id'], $id]);

    // Restaurar partido a pendiente
    $db->prepare("UPDATE partidos SET estado='pendiente' WHERE id=?")->execute([$sol['partido_id']]);

    // Notificar al solicitante que fue rechazada
    if ($sol['solicitante_id']) {
        epl_notif_crear((int)$sol['solicitante_id'], 'reprogramacion',
            '❌ Reprogramación no aprobada',
            'Tu solicitud de reprogramación no fue aprobada. Coordina directamente con tu rival.',
            epl_url('mis_partidos.php')
        );
    }

    header('Location: index.php?ok=reprog_rechazada');
}
exit;
