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

    header('Location: index.php?ok=reprog_aprobada');
} else {
    $db->prepare("UPDATE solicitudes_reprogramacion SET estado='rechazada', aprobado_por=? WHERE id=?")
       ->execute([$admin['id'], $id]);

    // Restaurar partido a pendiente
    $db->prepare("UPDATE partidos SET estado='pendiente' WHERE id=?")->execute([$sol['partido_id']]);

    header('Location: index.php?ok=reprog_rechazada');
}
exit;
