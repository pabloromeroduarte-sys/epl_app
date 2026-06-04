<?php
$page_title = 'Reprogramar Partido';
$player_tab = 'reprogramar';
require_once 'includes/auth.php';
require_once 'includes/functions.php';
require_once 'includes/mail.php';
epl_require_login();

$jugador = epl_jugador_actual();
$db      = epl_db();
$liga    = epl_liga_activa();
$equipo  = $liga ? epl_equipo_del_jugador($jugador['id'], $liga['id']) : null;

// Horas mínimas de anticipación para reprogramar (configurable en admin/alertas.php)
$REPROG_LOCK_HORAS = epl_notif_reprog_lock_horas();

$_flash = epl_flash_get();
$ok     = ($_flash && $_flash['tipo']==='ok');
$error  = ($_flash && $_flash['tipo']==='error') ? $_flash['msg'] : '';

// ── Límite de reprogramaciones ────────────────────────────────────────────────
$MAX_REPROGS = 2;
$total_reprogramados = 0;
$bloqueado_reprogs   = false;
$partidos_reprogramados_actuales = []; // Para mostrar al usuario cuáles bloquean

if ($equipo) {
    // Consultar todos los partidos reprogramados del equipo, incluyendo quién lo solicitó
    $stCnt = $db->prepare("
        SELECT p.id, p.jornada, p.fecha_programada,
               el.nombre AS local_nombre, ev.nombre AS visitante_nombre,
               sr.solicitante_id
        FROM partidos p
        JOIN equipos el ON el.id = p.equipo_local_id
        JOIN equipos ev ON ev.id = p.equipo_visitante_id
        LEFT JOIN solicitudes_reprogramacion sr ON sr.id = (
            SELECT sr2.id 
            FROM solicitudes_reprogramacion sr2
            WHERE sr2.partido_id = p.id AND sr2.estado = 'aprobada'
            ORDER BY sr2.id DESC LIMIT 1
        )
        WHERE p.liga_id = ?
          AND (p.equipo_local_id = ? OR p.equipo_visitante_id = ?)
          AND p.estado = 'reprogramado'
        ORDER BY p.jornada ASC
    ");
    $stCnt->execute([$liga['id'], $equipo['id'], $equipo['id']]);
    $todos_reprogramados = $stCnt->fetchAll();

    // Filtrar solo los que fueron solicitados por nuestro equipo
    $partidos_reprogramados_actuales = [];
    foreach ($todos_reprogramados as $p) {
        if (empty($p['solicitante_id'])) {
            continue;
        }
        $solicitante = (int)$p['solicitante_id'];
        $nuestro_jugador1 = (int)($equipo['jugador1_id'] ?? 0);
        $nuestro_jugador2 = (int)($equipo['jugador2_id'] ?? 0);
        
        if ($solicitante === $nuestro_jugador1 || $solicitante === $nuestro_jugador2) {
            $partidos_reprogramados_actuales[] = $p;
        }
    }

    $total_reprogramados = count($partidos_reprogramados_actuales);
    // Admins nunca quedan bloqueados (para demos y gestión)
    $es_admin = ($jugador['rol'] ?? '') === 'admin';
    $bloqueado_reprogs   = !$es_admin && ($total_reprogramados >= $MAX_REPROGS);
}
// ─────────────────────────────────────────────────────────────────────────────

$partidos_pendientes = [];
if ($equipo && !$bloqueado_reprogs) {
    $stP = $db->prepare("
        SELECT p.*, el.nombre AS local_nombre, ev.nombre AS visitante_nombre,
               jl1.nombre AS l1n, jl1.apellido AS l1a, jl1.telefono AS l1t,
               jl2.nombre AS l2n, jl2.apellido AS l2a, jl2.telefono AS l2t,
               jv1.nombre AS v1n, jv1.apellido AS jv1a, jv1.telefono AS v1t,
               jv2.nombre AS v2n, jv2.apellido AS v2a, jv2.telefono AS v2t
        FROM partidos p
        JOIN equipos el ON el.id = p.equipo_local_id
        JOIN equipos ev ON ev.id = p.equipo_visitante_id
        LEFT JOIN jugadores jl1 ON jl1.id = el.jugador1_id
        LEFT JOIN jugadores jl2 ON jl2.id = el.jugador2_id
        LEFT JOIN jugadores jv1 ON jv1.id = ev.jugador1_id
        LEFT JOIN jugadores jv2 ON jv2.id = ev.jugador2_id
        WHERE p.liga_id=? AND (p.equipo_local_id=? OR p.equipo_visitante_id=?)
          AND p.estado IN ('pendiente','reprogramado')
          AND (
            p.fecha_programada IS NULL
            OR p.fecha_programada < NOW()
            OR p.fecha_programada >= DATE_ADD(NOW(), INTERVAL {$REPROG_LOCK_HORAS} HOUR)
          )
        ORDER BY p.fecha_programada ASC
    ");
    $stP->execute([$liga['id'], $equipo['id'], $equipo['id']]);
    $partidos_pendientes = $stP->fetchAll();
}

$mis_solicitudes = [];
if ($equipo) {
    // Solo trae la solicitud MÁS RECIENTE por partido (no duplica si hubo rechazada + aprobada)
    // y excluye solicitudes de partidos que ya se jugaron (ruido)
    $stS = $db->prepare("
        SELECT sr.*, el.nombre AS local_nombre, ev.nombre AS visitante_nombre, p.estado AS partido_estado
        FROM solicitudes_reprogramacion sr
        JOIN partidos p  ON p.id = sr.partido_id
        JOIN equipos el  ON el.id = p.equipo_local_id
        JOIN equipos ev  ON ev.id = p.equipo_visitante_id
        WHERE sr.solicitante_id = ?
          AND sr.id = (
              SELECT MAX(sr2.id) FROM solicitudes_reprogramacion sr2
              WHERE sr2.partido_id = sr.partido_id AND sr2.solicitante_id = sr.solicitante_id
          )
          AND p.estado NOT IN ('jugado', 'walkover', 'no_presentado')
        ORDER BY sr.created_at DESC LIMIT 10
    ");
    $stS->execute([$jugador['id']]);
    $mis_solicitudes = $stS->fetchAll();
}

$otros_reprogramados = [];
$rivales_jugados = [];
if ($liga) {
    $equipo_id = $equipo ? (int)$equipo['id'] : 0;
    if ($equipo_id > 0) {
        $stRivals = $db->prepare("
            SELECT DISTINCT 
                CASE WHEN equipo_local_id = ? THEN equipo_visitante_id ELSE equipo_local_id END AS rival_id
            FROM partidos
            WHERE liga_id = ?
              AND (equipo_local_id = ? OR equipo_visitante_id = ?)
              AND estado IN ('jugado', 'walkover', 'no_presentado')
        ");
        $stRivals->execute([$equipo_id, $liga['id'], $equipo_id, $equipo_id]);
        $rivales_jugados = array_column($stRivals->fetchAll(), 'rival_id');
    }

    $stOtros = $db->prepare("
        SELECT p.id, p.jornada, p.fecha_programada,
               el.nombre AS local_nombre, ev.nombre AS visitante_nombre,
               el.id AS local_equipo_id, ev.id AS visitante_equipo_id,
               el.jugador1_id AS l1, el.jugador2_id AS l2,
               ev.jugador1_id AS v1, ev.jugador2_id AS v2,
               jl1.nombre AS l1n, jl1.apellido AS l1a, jl1.telefono AS l1t,
               jl2.nombre AS l2n, jl2.apellido AS l2a, jl2.telefono AS l2t,
               jv1.nombre AS v1n, jv1.apellido AS jv1a, jv1.telefono AS v1t,
               jv2.nombre AS v2n, jv2.apellido AS jv2a, jv2.telefono AS v2t,
               sr.solicitante_id
        FROM partidos p
        JOIN equipos el ON el.id = p.equipo_local_id
        JOIN equipos ev ON ev.id = p.equipo_visitante_id
        LEFT JOIN jugadores jl1 ON jl1.id = el.jugador1_id
        LEFT JOIN jugadores jl2 ON jl2.id = el.jugador2_id
        LEFT JOIN jugadores jv1 ON jv1.id = ev.jugador1_id
        LEFT JOIN jugadores jv2 ON jv2.id = ev.jugador2_id
        LEFT JOIN solicitudes_reprogramacion sr ON sr.id = (
            SELECT MAX(sr2.id) FROM solicitudes_reprogramacion sr2
            WHERE sr2.partido_id = p.id
        )
        WHERE p.liga_id = ?
          AND p.estado = 'reprogramado'
        ORDER BY p.jornada ASC, p.fecha_programada ASC
    ");
    $stOtros->execute([$liga['id']]);
    $otros_reprogramados = $stOtros->fetchAll();

    // Determinar jornada reciente de forma inteligente:
    // 1. Jornada con más partidos programados en la semana actual (lunes a domingo)
    $lunes = date('Y-m-d 00:00:00', strtotime('monday this week'));
    $domingo = date('Y-m-d 23:59:59', strtotime('sunday this week'));

    $stSemana = $db->prepare("
        SELECT p.jornada, COUNT(*) as cnt
        FROM partidos p
        WHERE p.liga_id = ?
          AND p.fecha_programada >= ?
          AND p.fecha_programada <= ?
          AND p.jornada IS NOT NULL AND p.jornada > 0
        GROUP BY p.jornada
        ORDER BY cnt DESC
        LIMIT 1
    ");
    $stSemana->execute([$liga['id'], $lunes, $domingo]);
    $resSemana = $stSemana->fetch();

    $jornada_reciente = null;
    if ($resSemana) {
        $jornada_reciente = (int)$resSemana['jornada'];
    } else {
        // Fallback 1: Primer partido en el futuro
        $stFuturo = $db->prepare("
            SELECT p.jornada
            FROM partidos p
            WHERE p.liga_id = ?
              AND p.fecha_programada >= NOW()
              AND p.jornada IS NOT NULL AND p.jornada > 0
            ORDER BY p.fecha_programada ASC
            LIMIT 1
        ");
        $stFuturo->execute([$liga['id']]);
        $resFuturo = $stFuturo->fetch();
        if ($resFuturo) {
            $jornada_reciente = (int)$resFuturo['jornada'];
        } else {
            // Fallback 2: Partido más cercano absoluto
            $stAbs = $db->prepare("
                SELECT p.jornada, ABS(TIMESTAMPDIFF(SECOND, p.fecha_programada, NOW())) as diff
                FROM partidos p
                WHERE p.liga_id = ?
                  AND p.fecha_programada IS NOT NULL
                  AND p.fecha_programada > '1900-01-01'
                  AND p.jornada IS NOT NULL AND p.jornada > 0
                ORDER BY diff ASC
                LIMIT 1
            ");
            $stAbs->execute([$liga['id']]);
            $resAbs = $stAbs->fetch();
            if ($resAbs) {
                $jornada_reciente = (int)$resAbs['jornada'];
            }
        }
    }
}

$WA_SVG = '<svg width="14" height="14" fill="currentColor" viewBox="0 0 24 24" style="display:inline-block;vertical-align:middle"><path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.588-5.946 0-6.556 5.332-11.888 11.888-11.888 3.176 0 6.161 1.237 8.404 3.48s3.481 5.229 3.481 8.404c0 6.556-5.332 11.888-11.888 11.888-2.003 0-3.963-.505-5.698-1.465l-6.305 1.693zm6.443-4.045c1.474.873 3.103 1.332 4.775 1.332 5.054 0 9.163-4.109 9.163-9.163s-4.109-9.163-9.163-9.163-9.163 4.109-9.163 9.163c0 1.95.623 3.856 1.799 5.437l-1.002 3.659 3.743-.999zm10.742-5.466c-.303-.151-1.788-.882-2.067-.981-.278-.099-.481-.151-.683.151-.202.303-.783.981-.96 1.183-.177.202-.354.227-.657.076-.303-.151-1.28-.471-2.438-1.504-.901-.803-1.508-1.796-1.685-2.098-.177-.302-.019-.465.132-.615.136-.135.303-.354.455-.53.151-.177.202-.303.303-.505.101-.202.051-.379-.025-.53-.076-.151-.683-1.643-.935-2.249-.245-.59-.495-.51-.683-.52l-.582-.01c-.202 0-.531.076-.809.379-.278.303-1.062 1.037-1.062 2.529 0 1.492 1.087 2.932 1.239 3.134.151.202 2.14 3.268 5.184 4.582.724.312 1.29.499 1.731.639.727.231 1.388.199 1.911.121.582-.087 1.788-.731 2.041-1.439.253-.708.253-1.313.177-1.439-.076-.126-.278-.202-.581-.353z"/></svg>';

// ─── NUEVA ACCIÓN: Asignar fecha a un partido reprogramado SIN fecha ───
// Se usa cuando el jugador inicialmente reprogramó con "rival no responde" y luego coordinó.
// Crea una nueva solicitud y anula las pendientes anteriores.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $equipo && ($_POST['action'] ?? '') === 'asignar_fecha') {
    $partido_id      = (int)($_POST['partido_id']     ?? 0);
    $fecha_propuesta = trim($_POST['fecha_propuesta']  ?? '');
    $motivo          = trim($_POST['motivo']           ?? '');
    $mutuo_acuerdo   = isset($_POST['mutuo_acuerdo'])  ? 1 : 0;

    // Validar partido: reprogramado, sin fecha, del equipo del usuario
    $stVal = $db->prepare("
        SELECT p.*, el.nombre AS local_nombre, ev.nombre AS visitante_nombre
        FROM partidos p
        JOIN equipos el ON el.id = p.equipo_local_id
        JOIN equipos ev ON ev.id = p.equipo_visitante_id
        WHERE p.id = ?
          AND p.estado = 'reprogramado'
          AND (p.equipo_local_id = ? OR p.equipo_visitante_id = ?)
          AND (p.fecha_programada IS NULL OR DATE(p.fecha_programada) = '2026-12-31')
    ");
    $stVal->execute([$partido_id, $equipo['id'], $equipo['id']]);
    $partido_af = $stVal->fetch();

    if (!$partido_af) {
        $error = 'Partido no válido o ya tiene fecha asignada.';
    } elseif (!$fecha_propuesta) {
        $error = 'Debes proponer una fecha.';
    } else {
        if (!$motivo) $motivo = 'Asignación de fecha tras coordinación con rival';

        // Anular solicitudes pendientes anteriores
        $db->prepare("UPDATE solicitudes_reprogramacion
                      SET estado='rechazada'
                      WHERE partido_id=? AND estado='pendiente'")
           ->execute([$partido_id]);

        // Crear nueva solicitud
        $auto_aprobada = $mutuo_acuerdo ? 1 : 0;
        $estado_sol    = $auto_aprobada ? 'aprobada' : 'pendiente';
        $fecha_aprob   = $auto_aprobada ? $fecha_propuesta : null;

        $db->prepare("
            INSERT INTO solicitudes_reprogramacion
              (partido_id, solicitante_id, motivo, fecha_propuesta, rival_no_responde, mutuo_acuerdo, estado, fecha_aprobada)
            VALUES (?,?,?,?,0,?,?,?)
        ")->execute([$partido_id, $jugador['id'], $motivo, $fecha_propuesta, $mutuo_acuerdo, $estado_sol, $fecha_aprob]);

        // Si auto-aprobada (mutuo acuerdo), aplicar nueva fecha al partido
        if ($auto_aprobada) {
            if (empty($partido_af['fecha_original']) && function_exists('epl_partido_snapshot_original')) {
                epl_partido_snapshot_original($partido_id);
            }
            $db->prepare("UPDATE partidos SET fecha_programada=? WHERE id=?")
               ->execute([$fecha_propuesta, $partido_id]);
        }

        // Notificar
        $solic_nombre = $jugador['nombre'] . ' ' . $jugador['apellido'];
        $partido_str  = $partido_af['local_nombre'] . ' vs ' . $partido_af['visitante_nombre'];
        $fecha_str    = date('d/m/Y H:i', strtotime($fecha_propuesta));

        $titulo = $auto_aprobada
            ? "📅 Partido reprogramado: {$partido_str}"
            : "📅 Asignación de fecha: {$partido_str}";
        $msg = $auto_aprobada
            ? "El partido se reprogramó por mutuo acuerdo para el {$fecha_str}."
            : "{$solic_nombre} asignó una nueva fecha al partido: {$fecha_str}";

        epl_notif_partido($partido_id, 'reprogramacion', $titulo, $msg,
            epl_url('reprogramar.php#mis-reprogramaciones'), true, [(int)$jugador['id']], true);

        epl_redirect_ok($auto_aprobada ? 'reprog_auto_ok' : 'fecha_asignada_ok',
            'reprogramar.php#mis-reprogramaciones');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $equipo && $bloqueado_reprogs) {
    $error = "Tu equipo ya tiene {$total_reprogramados} partidos reprogramados. Por bases del torneo no se permite más de {$MAX_REPROGS}.";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $equipo && !$bloqueado_reprogs) {
    $partido_id      = (int)($_POST['partido_id']       ?? 0);
    $fecha_propuesta = trim($_POST['fecha_propuesta']   ?? '');
    $motivo          = trim($_POST['motivo']             ?? '');
    $mutuo_acuerdo   = isset($_POST['mutuo_acuerdo'])    ? 1 : 0;
    $rival_no_resp   = isset($_POST['rival_no_responde']) ? 1 : 0;

    $stVal = $db->prepare("
        SELECT p.*, el.jugador1_id AS l1, el.jugador2_id AS l2, ev.jugador1_id AS v1, ev.jugador2_id AS v2,
               el.nombre AS local_nombre, ev.nombre AS visitante_nombre,
               r.nombre AS recinto_nombre, rs.nombre AS recinto_superior, rss.nombre AS recinto_raiz
        FROM partidos p
        JOIN equipos el ON el.id = p.equipo_local_id
        JOIN equipos ev ON ev.id = p.equipo_visitante_id
        LEFT JOIN recintos r   ON r.id   = p.recinto_id
        LEFT JOIN recintos rs  ON rs.id  = r.superior_id
        LEFT JOIN recintos rss ON rss.id = rs.superior_id
        WHERE p.id=? AND (p.equipo_local_id=? OR p.equipo_visitante_id=?)
          AND p.estado IN ('pendiente','reprogramado')
          AND (
            p.fecha_programada IS NULL
            OR p.fecha_programada < NOW()
            OR p.fecha_programada >= DATE_ADD(NOW(), INTERVAL {$REPROG_LOCK_HORAS} HOUR)
          )
    ");
    $stVal->execute([$partido_id, $equipo['id'], $equipo['id']]);
    $partido = $stVal->fetch();

    if (!$partido) {
        $error = 'Partido no válido o no autorizado.';
    } elseif (!$motivo) {
        $error = 'Debes ingresar el motivo.';
    } elseif (!$rival_no_resp && !$fecha_propuesta) {
        $error = 'Debes proponer una fecha, o marcar que el rival no respondió.';
    } elseif (!$rival_no_resp && !$mutuo_acuerdo) {
        $error = 'Debes confirmar que coordinaste con el equipo rival.';
    } else {
        $fecha_final    = $rival_no_resp ? null : $fecha_propuesta;
        $auto_aprobada  = ($mutuo_acuerdo && !$rival_no_resp && $fecha_final) ? 1 : 0;
        $estado_sol     = $auto_aprobada ? 'aprobada' : 'pendiente';

        $db->prepare("
            INSERT INTO solicitudes_reprogramacion
              (partido_id, solicitante_id, motivo, fecha_propuesta, rival_no_responde, mutuo_acuerdo,
               estado, fecha_aprobada)
            VALUES (?,?,?,?,?,?,?,?)
        ")->execute([$partido_id, $jugador['id'], $motivo, $fecha_final, $rival_no_resp, $mutuo_acuerdo,
                     $estado_sol, $auto_aprobada ? $fecha_final : null]);

        // ─── NUEVA REGLA: el partido NO cambia fecha hasta que el admin apruebe ───
        // Solo se marca como 'reprogramado' y se resetean tokens viejos de baja/cancha.
        // La fecha propuesta vive en solicitudes_reprogramacion.fecha_propuesta hasta que admin apruebe.
        $db->prepare("UPDATE partidos SET estado='reprogramado',
                        baja_token=NULL, baja_solicitada_at=NULL, baja_confirmada_at=NULL, baja_confirmada_por=NULL,
                        cancha_token=NULL, cancha_solicitada_at=NULL, cancha_confirmada_at=NULL, cancha_confirmada_por=NULL
                      WHERE id=?")->execute([$partido_id]);

        // Notificar in-app + email (SMTP) a jugadores del partido rival y a los admins
        $solicitante_nombre = $jugador['nombre'] . ' ' . $jugador['apellido'];
        $partido_str = $partido['local_nombre'] . ' vs ' . $partido['visitante_nombre'];
        $fecha_str   = $fecha_final ? date('d/m/Y H:i', strtotime($fecha_final)) : 'a coordinar';

        // Recinto: construir ruta completa (raíz › sede › cancha)
        $recinto_partes = array_filter([
            $partido['recinto_raiz']     ?? null,
            $partido['recinto_superior'] ?? null,
            $partido['recinto_nombre']   ?? null,
        ]);
        $recinto_txt = $recinto_partes ? implode(' › ', $recinto_partes) : 'Sin recinto asignado';

        // Jornada / fecha del torneo
        $jornada_txt = '';
        if (!empty($partido['jornada']))      $jornada_txt .= 'Jornada ' . $partido['jornada'];
        if (!empty($partido['nombre_fecha'])) $jornada_txt .= ($jornada_txt ? ' — ' : '') . $partido['nombre_fecha'];

        // Fecha original del partido
        $fecha_original_txt = $partido['fecha_programada']
            ? date('d/m/Y H:i', strtotime($partido['fecha_programada']))
            : 'Sin fecha asignada';

        $lineas = [
            "Partido: {$partido_str}",
            "Liga: {$liga['nombre']}",
        ];
        if ($jornada_txt)  $lineas[] = "Jornada: {$jornada_txt}";
        $lineas[] = "Fecha original: {$fecha_original_txt}";
        $lineas[] = "Recinto: {$recinto_txt}";
        $lineas[] = "Fecha propuesta: {$fecha_str}";
        if ($motivo)       $lineas[] = "Motivo: {$motivo}";

        $mensaje_notif = "{$solicitante_nombre} solicitó reprogramar el partido.\n\n" . implode("\n", $lineas);

        // ── Notificaciones y emails ──────────────────────────────────────────
        $filas_repr = array_values(array_filter([
            ['icon' => '🏆', 'label' => 'Liga',            'valor' => $liga['nombre']],
            $jornada_txt ? ['icon' => '🗓️', 'label' => 'Jornada',        'valor' => $jornada_txt]      : null,
            ['icon' => '📅', 'label' => 'Fecha original',  'valor' => $fecha_original_txt],
            ['icon' => '🕐', 'label' => $auto_aprobada ? 'Nueva fecha' : 'Fecha propuesta',
                             'valor' => $fecha_str],
            ['icon' => '🏟️', 'label' => 'Recinto',         'valor' => $recinto_txt],
            $motivo ? ['icon' => '💬', 'label' => 'Motivo','valor' => $motivo] : null,
        ]));

        if ($auto_aprobada) {
            // Auto-aprobado: notificar a TODOS los jugadores (incluido solicitante) + admins
            $asunto_repr = epl_mail_asunto('📅 Partido reprogramado', $partido['local_nombre'], $partido['visitante_nombre'], $partido['jornada'] ?? null);
            $titulo_notif = "📅 Partido reprogramado: {$partido_str}";
            $msg_notif    = "El partido se reprogramó por mutuo acuerdo para el {$fecha_str}.";
            $tip_email    = '✅ Ambos equipos confirmaron la nueva fecha. Recuerda llegar 10 minutos antes.';
            $subtitulo    = 'El partido fue reprogramado por mutuo acuerdo.';

            epl_notif_partido($partido_id, 'reprogramacion', $titulo_notif, $msg_notif,
                epl_url('reprogramar.php#mis-reprogramaciones'), true, [], true);

            $st_todos = $db->prepare("
                SELECT DISTINCT j.id FROM partidos p
                JOIN equipos el ON el.id = p.equipo_local_id
                JOIN equipos ev ON ev.id = p.equipo_visitante_id
                JOIN jugadores j ON j.id IN (el.jugador1_id, el.jugador2_id, ev.jugador1_id, ev.jugador2_id)
                WHERE p.id = ?
            ");
            $st_todos->execute([$partido_id]);
            $admins_r = $db->query("SELECT id FROM jugadores WHERE rol='admin' AND estado='activo'")->fetchAll();
            $ids_todos = array_unique(array_merge(
                array_column($st_todos->fetchAll(), 'id'),
                array_column($admins_r, 'id')
            ));
            foreach ($ids_todos as $jid_r) {
                epl_mail_partido_visual((int)$jid_r, $asunto_repr,
                    $partido['local_nombre'], $partido['visitante_nombre'],
                    $filas_repr, $subtitulo, $tip_email, epl_url('reprogramar.php#mis-reprogramaciones'));
            }

            epl_redirect_ok('reprog_auto_ok', 'reprogramar.php#mis-reprogramaciones');
        } else {
            // Sin mutuo acuerdo → flujo normal: admin debe aprobar
            $asunto_repr = epl_mail_asunto('📅 Solicitud de reprogramación', $partido['local_nombre'], $partido['visitante_nombre'], $partido['jornada'] ?? null);

            epl_notif_partido($partido_id, 'reprogramacion',
                "📅 Solicitud de reprogramación: {$partido_str}", $mensaje_notif,
                epl_url('reprogramar.php#mis-reprogramaciones'), true, [(int)$jugador['id']], true);

            $st_jug_repr = $db->prepare("
                SELECT DISTINCT j.id FROM partidos p
                JOIN equipos el ON el.id = p.equipo_local_id
                JOIN equipos ev ON ev.id = p.equipo_visitante_id
                JOIN jugadores j ON j.id IN (el.jugador1_id, el.jugador2_id, ev.jugador1_id, ev.jugador2_id)
                WHERE p.id = ? AND j.id != ?
            ");
            $st_jug_repr->execute([$partido_id, (int)$jugador['id']]);
            $admins_repr = $db->query("SELECT id FROM jugadores WHERE rol='admin' AND estado='activo'")->fetchAll();
            $ids_repr    = array_unique(array_merge(
                array_column($st_jug_repr->fetchAll(), 'id'),
                array_filter(array_column($admins_repr, 'id'), fn($aid) => (int)$aid !== (int)$jugador['id'])
            ));
            foreach ($ids_repr as $jid_r) {
                epl_mail_partido_visual((int)$jid_r, $asunto_repr,
                    $partido['local_nombre'], $partido['visitante_nombre'],
                    $filas_repr, "{$solicitante_nombre} solicitó reprogramar el partido.",
                    '⏳ El administrador revisará la solicitud y confirmará la nueva fecha.',
                    epl_url('reprogramar.php#mis-reprogramaciones'));
            }

            epl_redirect_ok('solicitud_ok', 'reprogramar.php#mis-reprogramaciones');
        }
    }
}
?>
<?php require_once 'includes/header.php'; ?>

<div class="dash-layout">
<?php include __DIR__ . '/includes/player_sidebar.php'; ?>
<main class="dash-main">

  <div class="dash-header">
    <h1 class="dash-title">Reprogramar Partido</h1>
    <p style="color:var(--gray-400);font-size:.88rem">Coordina con tu rival y propone una nueva fecha.</p>
  </div>

  <?php if ($ok): ?>
  <div class="rp-success">
    <svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
    <div><strong>Solicitud enviada.</strong> El administrador la revisará y confirmará la nueva fecha.</div>
  </div>
  <?php endif; ?>
  <?php if ($error): ?>
  <div class="alert alert-error"><?= epl_h($error) ?></div>
  <?php endif; ?>

  <div class="rp-tabs" style="margin-bottom:1.5rem">
    <button type="button" class="rp-tab-btn active" data-tab="solicitar" onclick="rpSwitchTab('solicitar')">
      <span>🔄</span> <span class="rp-tab-text">Solicitar</span>
    </button>
    <button type="button" class="rp-tab-btn" data-tab="mis-reprogramaciones" onclick="rpSwitchTab('mis-reprogramaciones')">
      <span>📅</span> <span class="rp-tab-text">Mis <span class="rp-tab-text-long">Reprogramaciones</span><span class="rp-tab-text-short">Reprogs.</span></span>
      <?php if ($total_reprogramados > 0): ?>
        <span class="rp-tab-badge"><?= $total_reprogramados ?></span>
      <?php endif; ?>
    </button>
    <button type="button" class="rp-tab-btn" data-tab="otros-reprogramados" onclick="rpSwitchTab('otros-reprogramados')">
      <span>🏆</span> <span class="rp-tab-text">Todos<span class="rp-tab-text-long"> los Reprogramados</span></span>
      <?php if (count($otros_reprogramados) > 0): ?>
        <span class="rp-tab-badge" style="background:#0369a1"><?= count($otros_reprogramados) ?></span>
      <?php endif; ?>
    </button>
  </div>

  <!-- Pestaña 1: Solicitar Reprogramación -->
  <div id="tab-solicitar" class="rp-tab-panel active">
    <?php if (!$equipo): ?>
      <div class="alert alert-info">No estás inscrito en ningún equipo activo en esta liga.</div>

    <?php elseif ($bloqueado_reprogs): ?>
      <!-- BLOQUEO: límite de reprogramaciones alcanzado -->
      <div style="background:#fff;border-radius:16px;border:2px solid #fca5a5;padding:2rem 1.5rem;max-width:560px">
        <div style="display:flex;gap:1rem;align-items:flex-start">
          <div style="font-size:2.5rem;flex-shrink:0;line-height:1">🚫</div>
          <div>
            <h3 style="font-family:var(--font-head);font-size:1.1rem;text-transform:uppercase;color:#991b1b;margin:0 0 .6rem">Límite de reprogramaciones alcanzado</h3>
            <p style="color:#7f1d1d;font-size:.9rem;line-height:1.55;margin:0 0 .75rem">
              Tu equipo ya tiene <strong><?= $total_reprogramados ?> partido<?= $total_reprogramados > 1 ? 's' : '' ?> reprogramado<?= $total_reprogramados > 1 ? 's' : '' ?> solicitado<?= $total_reprogramados > 1 ? 's' : '' ?> por ustedes</strong>.
              Según las bases del torneo, <strong>no está permitido solicitar más de <?= $MAX_REPROGS ?> reprogramaciones por equipo</strong>.
            </p>
            <div style="background:#fee2e2;border-radius:10px;padding:.75rem 1rem;font-size:.82rem;color:#991b1b;font-weight:600;margin-bottom:1rem">
              📋 Si tienes algún inconveniente especial, contacta directamente al administrador del torneo.
            </div>

            <?php if (!empty($partidos_reprogramados_actuales)): ?>
            <!-- Mostrar al jugador CUÁLES son los partidos que lo bloquean -->
            <div style="background:#fef9f9;border:1px solid #fecaca;border-radius:10px;padding:.85rem 1rem;margin-top:.5rem">
              <div style="font-size:.7rem;font-weight:800;color:#991b1b;text-transform:uppercase;letter-spacing:.08em;margin-bottom:.5rem">
                ⚠ Tus reprogramaciones activas
              </div>
              <?php foreach ($partidos_reprogramados_actuales as $pr):
                $rival = ($pr['local_nombre'] === $equipo['nombre']) ? $pr['visitante_nombre'] : $pr['local_nombre'];
                $fecha_lbl = $pr['fecha_programada'] && date('Y-m-d', strtotime($pr['fecha_programada'])) !== '2026-12-31'
                    ? date('d/m H:i', strtotime($pr['fecha_programada']))
                    : 'Sin fecha';
              ?>
              <div style="display:flex;align-items:center;gap:.6rem;padding:.45rem 0;border-top:1px solid #fee2e2;font-size:.82rem;color:#7f1d1d">
                <span style="background:#fee2e2;color:#991b1b;font-weight:800;font-size:.65rem;border-radius:4px;padding:.1rem .4rem">J<?= $pr['jornada'] ?: '?' ?></span>
                <span style="flex:1">vs <strong><?= epl_h($rival) ?></strong></span>
                <span style="font-size:.75rem;color:#9f1239;font-weight:600"><?= $fecha_lbl ?></span>
              </div>
              <?php endforeach; ?>
              <p style="font-size:.72rem;color:#7f1d1d;margin-top:.6rem;font-weight:600;line-height:1.4">
                💡 Cuando alguno de estos partidos se juegue, podrás reprogramar otro.
              </p>
            </div>
            <?php endif; ?>
          </div>
        </div>
        <div style="margin-top:1.25rem;display:flex;gap:.75rem;flex-wrap:wrap">
          <a href="mis_torneos.php" class="btn btn-sm" style="background:var(--navy);color:#fff;text-decoration:none">← Volver a mis torneos</a>
          <a href="dashboard.php"   class="btn btn-sm" style="border:1px solid var(--gray-200);color:var(--gray-600);text-decoration:none">Ir al dashboard</a>
        </div>
      </div>

    <?php elseif (empty($partidos_pendientes)): ?>
      <div class="rp-empty">
        <div style="font-size:2.5rem;margin-bottom:.75rem">📅</div>
        <h3>Sin partidos para reprogramar</h3>
        <p>No tienes partidos pendientes o todos tienen fecha con menos de 24h de anticipación.</p>
      </div>
    <?php else: ?>

      <form method="post" id="formReprog">

        <!-- Paso 1: Seleccionar partido -->
        <div class="rp-card">
          <div class="rp-card-header">
            <span class="rp-step-dot">1</span>
            <div>
              <div class="rp-card-title">Selecciona el partido</div>
              <div class="rp-card-sub">Partidos pendientes o con fecha vencida.</div>
            </div>
          </div>
          <input type="hidden" name="partido_id" id="hiddenPartidoIdRp">
          <div class="rp-partidos-list" style="margin-top:1rem" id="rpListaPartidos">
            <?php
              $pre_id = (int)($_GET['partido_id'] ?? 0);
              foreach ($partidos_pendientes as $p):
                $esLocal = ($p['equipo_local_id'] == $equipo['id']);
                $rivales = [];
                if ($esLocal) {
                  $rivales[] = ['n'=>$p['v1n'],  'a'=>$p['jv1a'], 't'=>$p['v1t']];
                  $rivales[] = ['n'=>$p['v2n'],  'a'=>$p['v2a'],  't'=>$p['v2t']];
                } else {
                  $rivales[] = ['n'=>$p['l1n'],  'a'=>$p['l1a'],  't'=>$p['l1t']];
                  $rivales[] = ['n'=>$p['l2n'],  'a'=>$p['l2a'],  't'=>$p['l2t']];
                }
                $dataRivales = htmlspecialchars(base64_encode(json_encode($rivales)), ENT_QUOTES);
                $checked = ($p['id'] == $pre_id) ? 'checked' : '';
            ?>
            <label class="rp-partido-option" for="rpp<?= $p['id'] ?>">
              <input type="radio" name="_rp_partido_pick" id="rpp<?= $p['id'] ?>" value="<?= $p['id'] ?>"
                     data-rivales="<?= $dataRivales ?>" <?= $checked ?>
                     onchange="rpSelPartido(this)">
              <div class="rp-partido-content">
                <div class="rp-partido-fecha">
                  <?php if ($p['fecha_programada']): ?>
                    <span class="rp-dia"><?= date('d', strtotime($p['fecha_programada'])) ?></span>
                    <span class="rp-mes"><?= strtoupper(date('M', strtotime($p['fecha_programada']))) ?></span>
                  <?php else: ?>
                    <span class="rp-dia">?</span><span class="rp-mes">TBD</span>
                  <?php endif; ?>
                </div>
                <div class="rp-partido-teams">
                  <span><?= epl_h($p['local_nombre']) ?></span>
                  <span class="rp-vs">VS</span>
                  <span><?= epl_h($p['visitante_nombre']) ?></span>
                </div>
                <div class="rp-partido-meta">
                  <span class="rp-jornada">F.<?= $p['jornada'] ?? '?' ?></span>
                  <?php if ($p['estado']==='reprogramado'): ?>
                    <span class="badge badge-walkover" style="font-size:.55rem">Reprog.</span>
                  <?php endif; ?>
                </div>
              </div>
              <div class="rp-partido-check">
                <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
              </div>
            </label>
            <?php endforeach; ?>
          </div>
        </div>

        <!-- Paso 2: Contactar rivales -->
        <div id="seccionRivales" class="rp-card rp-card-rivales" style="display:none">
          <div class="rp-card-header">
            <span class="rp-step-dot" style="background:#0369A1">2</span>
            <div>
              <div class="rp-card-title" style="color:#0369A1">Contacta a tus rivales</div>
              <div class="rp-card-sub">Coordina la nueva fecha antes de enviar la solicitud.</div>
            </div>
          </div>
          <div id="listaRivales" style="display:flex;flex-direction:column;gap:.6rem;margin-top:1rem"></div>
        </div>

        <!-- Paso 3: Motivo -->
        <div class="rp-card">
          <div class="rp-card-header">
            <span class="rp-step-dot">3</span>
            <div>
              <div class="rp-card-title">Motivo de la reprogramación</div>
            </div>
          </div>
          <textarea name="motivo" class="form-control" rows="3" required
                    placeholder="Explica brevemente el motivo del cambio..."
                    style="margin-top:1rem;resize:vertical"></textarea>
        </div>

        <!-- Paso 4: Rival no responde -->
        <div class="rp-card">
          <div class="rp-card-header">
            <span class="rp-step-dot">4</span>
            <div>
              <div class="rp-card-title">¿El rival no responde?</div>
            </div>
          </div>
          <label class="rp-toggle-row" style="margin-top:1rem">
            <div class="rp-toggle-info">
              <div style="font-weight:700;font-size:.9rem;color:var(--navy)">El rival no responde ni coordina</div>
              <div style="font-size:.78rem;color:var(--gray-500);margin-top:.2rem">El partido quedará sin fecha y la organización tomará acción.</div>
            </div>
            <label class="rp-switch">
              <input type="checkbox" name="rival_no_responde" id="chkRivalNoResp" onchange="toggleRivalNoResp(this)">
              <span class="rp-slider"></span>
            </label>
          </label>
        </div>

        <!-- Paso 5: Nueva fecha + mutuo acuerdo -->
        <div id="seccionFecha" class="rp-card rp-card-fecha">
          <div class="rp-card-header">
            <span class="rp-step-dot" style="background:#1d4ed8">5</span>
            <div>
              <div class="rp-card-title" style="color:#1d4ed8">Nueva fecha propuesta</div>
              <div class="rp-card-sub">Cualquier fecha futura (mínimo 1 hora desde ahora).</div>
            </div>
          </div>
          <input type="text" name="fecha_propuesta" id="fpFechaPropuesta" class="form-control"
                 placeholder="Seleccioná día y hora"
                 data-min="<?= date('Y-m-d H:i', strtotime('+1 hour')) ?>"
                 autocomplete="off" readonly
                 style="margin-top:1rem;max-width:320px;cursor:pointer;background:#fff">

          <label class="rp-acuerdo-row">
            <input type="checkbox" name="mutuo_acuerdo" id="chkMutuo" required>
            <div>
              <div style="font-weight:700;font-size:.88rem;color:#1e40af">Confirmo mutuo acuerdo</div>
              <div style="font-size:.78rem;color:#3b82f6;margin-top:.1rem">He coordinado esta fecha con el equipo rival y ambos estamos de acuerdo.</div>
            </div>
          </label>
        </div>

        <button type="submit" class="rp-submit-btn">
          <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/></svg>
          Enviar solicitud
        </button>

      </form>
    <?php endif; ?>
  </div>

  <!-- Pestaña 2: Mis Reprogramaciones (Activas e Historial) -->
  <div id="tab-mis-reprogramaciones" class="rp-tab-panel" style="display:none">
    
    <!-- Reprogramaciones Activas (Limite) -->
    <?php if ($equipo && !empty($partidos_reprogramados_actuales)): ?>
      <div style="margin-bottom:2.5rem">
        <h3 style="font-family:var(--font-head);font-size:.85rem;text-transform:uppercase;letter-spacing:.08em;color:var(--navy);margin-bottom:1rem">
          🔄 Mis Reprogramaciones Activas (<?= $total_reprogramados ?>/<?= $MAX_REPROGS ?>)
        </h3>
        <p style="font-size:.8rem;color:var(--gray-500);margin-bottom:1rem">
          Partidos reprogramados solicitados por tu equipo. Deberán jugarse antes de poder solicitar nuevas reprogramaciones.
        </p>
        <div style="display:flex;flex-direction:column;gap:.6rem">
          <?php foreach ($partidos_reprogramados_actuales as $pr):
            $rival = ($pr['local_nombre'] === $equipo['nombre']) ? $pr['visitante_nombre'] : $pr['local_nombre'];
            $sin_fecha = !$pr['fecha_programada'] || date('Y-m-d', strtotime($pr['fecha_programada'])) === '2026-12-31';
            $fecha_lbl = !$sin_fecha ? date('d/m/Y H:i', strtotime($pr['fecha_programada'])) : 'A coordinar';
          ?>
          <div class="rp-historial-row" style="border-left:4px solid <?= $sin_fecha ? '#f97316' : 'var(--gold)' ?>;flex-wrap:wrap">
            <div style="flex:1;min-width:0">
              <div style="font-weight:700;font-size:.88rem;color:var(--navy)">
                <?= epl_h($pr['local_nombre'] . ' vs ' . $pr['visitante_nombre']) ?>
              </div>
              <div style="font-size:.75rem;color:var(--gray-500);margin-top:.2rem">
                <span>Jornada <?= $pr['jornada'] ?: '?' ?></span> ·
                <span style="color:<?= $sin_fecha ? '#c2410c' : 'var(--navy)' ?>;font-weight:600">Fecha: <?= $fecha_lbl ?></span>
              </div>
            </div>
            <span class="badge badge-walkover" style="font-size:.65rem;flex-shrink:0;align-self:flex-start">Reprogramado</span>
            <?php if ($sin_fecha): ?>
              <button type="button" class="btn-asignar-fecha"
                      data-partido-id="<?= (int)$pr['id'] ?>"
                      data-partido-str="<?= epl_h($pr['local_nombre'] . ' vs ' . $pr['visitante_nombre']) ?>"
                      onclick="abrirModalAsignar(this)">
                📅 Asignar fecha
              </button>
            <?php endif; ?>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endif; ?>

    <!-- Historial de Solicitudes -->
    <div>
      <h3 style="font-family:var(--font-head);font-size:.85rem;text-transform:uppercase;letter-spacing:.08em;color:var(--navy);margin-bottom:1rem">
        📋 Historial de Solicitudes
      </h3>
      <?php if ($mis_solicitudes): ?>
        <div style="display:flex;flex-direction:column;gap:.6rem">
          <?php foreach ($mis_solicitudes as $s):
            $badgeCls = match($s['estado']) { 'aprobada'=>'badge-jugado', 'rechazada'=>'badge-walkover', default=>'badge-pendiente' };
          ?>
          <div class="rp-historial-row">
            <div style="flex:1;min-width:0">
              <div style="font-weight:700;font-size:.88rem;color:var(--navy)"><?= epl_h($s['local_nombre'].' vs '.$s['visitante_nombre']) ?></div>
              <div style="font-size:.75rem;color:var(--gray-400);margin-top:.2rem">
                <?= $s['fecha_propuesta'] ? date('d/m/Y H:i', strtotime($s['fecha_propuesta'])) : 'Sin fecha' ?>
                <?php if ($s['rival_no_responde']): ?><span class="badge badge-walkover" style="font-size:.6rem;margin-left:.4rem">Rival no respondió</span><?php endif; ?>
                <?php if ($s['mutuo_acuerdo']): ?><span class="badge badge-jugado" style="font-size:.6rem;margin-left:.4rem">Mutuo acuerdo</span><?php endif; ?>
              </div>
              <?php if ($s['fecha_aprobada'] && $s['estado']==='aprobada'): ?>
                <div style="font-size:.75rem;color:#22c55e;margin-top:.2rem;font-weight:600">✓ <?= date('d/m/Y H:i', strtotime($s['fecha_aprobada'])) ?> <?= $s['cancha_aprobada']?'· '.$s['cancha_aprobada']:'' ?></div>
              <?php endif; ?>
              <div style="font-size:.72rem;color:var(--gray-500);margin-top:.2rem;font-style:italic"><?= epl_h(mb_strimwidth($s['motivo'], 0, 80, '...')) ?></div>
            </div>
            <span class="badge <?= $badgeCls ?>" style="flex-shrink:0;align-self:flex-start"><?= ucfirst($s['estado']) ?></span>
          </div>
          <?php endforeach; ?>
        </div>
      <?php else: ?>
        <div class="rp-empty" style="padding:1.5rem 0">
          <p>No tienes solicitudes de reprogramación anteriores.</p>
        </div>
      <?php endif; ?>
    </div>

  </div>

  <!-- Pestaña 3: Todos los Reprogramados -->
  <div id="tab-otros-reprogramados" class="rp-tab-panel" style="display:none">
    <div style="margin-bottom:1.5rem">
      <h3 style="font-family:var(--font-head);font-size:.85rem;text-transform:uppercase;letter-spacing:.08em;color:var(--navy);margin-bottom:.5rem">
        🏆 Todos los Partidos Reprogramados
      </h3>
      <p style="color:var(--gray-500);font-size:.8rem;line-height:1.4">
        Revisa todos los partidos reprogramados en la liga. El equipo que solicitó la reprogramación se destaca en <strong style="color:#e11d48">rojo</strong> con la etiqueta <span class="rp-solicito-badge" style="margin-left:0;font-size:0.55rem;padding:0.1rem 0.3rem">Solicitó</span> para saber a quién debes contactar.
      </p>
    </div>

    <?php if (empty($otros_reprogramados)): ?>
      <div class="rp-empty">
        <div style="font-size:2.5rem;margin-bottom:.75rem">🥎</div>
        <h3>No hay partidos reprogramados</h3>
        <p>Actualmente no hay partidos marcados como reprogramados en esta liga.</p>
      </div>
    <?php else:
      // Agrupar por jornada
      $por_jornada = [];
      foreach ($otros_reprogramados as $p) {
          $j = $p['jornada'] ?: 'Sin Jornada';
          $por_jornada[$j][] = $p;
      }
      ksort($por_jornada);
      
      // Filtro de Jornadas (fechas)
      $jornadas_disponibles = array_keys($por_jornada);
      sort($jornadas_disponibles);
      ?>
      
      <div class="fechas-scroll" style="margin-bottom:1.5rem">
        <button type="button" class="fecha-btn active" onclick="rpFilterJornada('all', this)">Todas</button>
        <?php foreach ($jornadas_disponibles as $j):
          $is_recent = ($j == $jornada_reciente);
          $btn_label = "Fecha " . $j;
          if ($is_recent) {
              $btn_label .= " ⭐";
          }
        ?>
          <button type="button" class="fecha-btn<?= $is_recent ? ' fecha-btn-actual' : '' ?>" onclick="rpFilterJornada('<?= $j ?>', this)">
            <?= $btn_label ?>
          </button>
        <?php endforeach; ?>
      </div>

      <?php
      $equipo_id = $equipo ? (int)$equipo['id'] : 0;
      if ($equipo_id > 0):
      ?>
      <div style="margin-bottom:1.5rem;display:flex;align-items:center;gap:.6rem;background:#f8fafc;border:1px solid #e2e8f0;border-radius:12px;padding:.75rem 1rem">
        <div class="rp-toggle-row" style="padding:0;margin:0;width:100%;display:flex;align-items:center;justify-content:space-between">
          <div class="rp-toggle-info" style="min-width:0;flex:1;padding-right:1rem;">
            <div style="font-weight:700;font-size:.85rem;color:var(--navy)">Ocultar partidos ya jugados</div>
            <div style="font-size:.75rem;color:var(--gray-500);margin-top:.1rem">Oculta partidos donde ya jugaste contra ambos equipos.</div>
          </div>
          <label class="rp-switch" style="flex-shrink:0;">
            <input type="checkbox" id="chkOcultarJugados" onchange="rpToggleOcultarJugados(this)">
            <span class="rp-slider"></span>
          </label>
        </div>
      </div>
      <?php endif; ?>

      <div id="rp-otros-list-container">
      <?php
      foreach ($por_jornada as $jornada => $partidos):
      ?>
      <div class="jornada-group" data-jornada="<?= $jornada ?>" style="margin-bottom:2rem">
        <div style="background:#f1f5f9;color:var(--navy);font-weight:800;font-size:.78rem;text-transform:uppercase;letter-spacing:.08em;padding:.5rem 1rem;border-radius:8px;margin-bottom:.85rem;display:inline-block">
          Jornada <?= $jornada ?>
          <?php if ($jornada == $jornada_reciente): ?>
            <span style="color:var(--gold);margin-left:.4rem">★ Esta semana</span>
          <?php endif; ?>
        </div>
        
        <div style="display:flex;flex-direction:column;gap:1rem">
          <?php foreach ($partidos as $p):
            $fecha_orig = $p['fecha_programada'] && date('Y-m-d', strtotime($p['fecha_programada'])) !== '2026-12-31'
                ? date('d/m/Y H:i', strtotime($p['fecha_programada']))
                : 'A coordinar';
            $solicitante_id = $p['solicitante_id'] ? (int)$p['solicitante_id'] : 0;
            $local_solicito = ($solicitante_id > 0 && ($solicitante_id === (int)$p['l1'] || $solicitante_id === (int)$p['l2']));
            $visitante_solicito = ($solicitante_id > 0 && ($solicitante_id === (int)$p['v1'] || $solicitante_id === (int)$p['v2']));

            // Determinar si ya se jugó contra estos equipos
            $ya_jugado_ambos = 0;
            if ($equipo_id > 0) {
                if ((int)$p['local_equipo_id'] !== $equipo_id && (int)$p['visitante_equipo_id'] !== $equipo_id) {
                    $l_jugado = in_array((int)$p['local_equipo_id'], $rivales_jugados);
                    $v_jugado = in_array((int)$p['visitante_equipo_id'], $rivales_jugados);
                    if ($l_jugado && $v_jugado) {
                        $ya_jugado_ambos = 1;
                    }
                }
            }
          ?>
          <div class="rp-card-otro" data-ya-jugado-ambos="<?= $ya_jugado_ambos ?>">
            <!-- Encabezado del partido -->
            <div class="rp-otro-header">
              <div style="flex:1">
                <div class="rp-otro-teams">
                  <span class="rp-team-name<?= $local_solicito ? ' rp-team-solicitante' : '' ?>"><?= epl_h($p['local_nombre']) ?></span>
                  <span class="rp-vs-small">VS</span>
                  <span class="rp-team-name<?= $visitante_solicito ? ' rp-team-solicitante' : '' ?>"><?= epl_h($p['visitante_nombre']) ?></span>
                </div>
                <div class="rp-otro-fecha">
                  <span>📅 Fecha original: <strong><?= $fecha_orig ?></strong></span>
                </div>
              </div>
              <span class="badge badge-walkover" style="font-size:.62rem;align-self:center">Reprogramado</span>
            </div>
            
            <!-- Jugadores y contacto -->
            <div class="rp-otro-players-section">
              <!-- Equipo Local -->
              <div class="rp-otro-equipo-box<?= $local_solicito ? ' rp-equipo-solicitante-box' : '' ?>">
                <div class="rp-otro-equipo-title<?= $local_solicito ? ' rp-title-solicitante' : '' ?>">
                  Local: <?= epl_h($p['local_nombre']) ?>
                  <?php if ($equipo_id > 0): ?>
                    <?php if ((int)$p['local_equipo_id'] === $equipo_id): ?>
                      <span class="badge-propio">Tú</span>
                    <?php elseif (in_array((int)$p['local_equipo_id'], $rivales_jugados)): ?>
                      <span class="badge-jugado-rival">Ya jugado</span>
                    <?php else: ?>
                      <span class="badge-pendiente-rival">Pendiente</span>
                    <?php endif; ?>
                  <?php endif; ?>
                  <?= $local_solicito ? '<span class="rp-solicito-badge">Solicitó</span>' : '' ?>
                </div>
                <div class="rp-otro-players-list">
                  <?php
                    $jugadores_locales = [
                      ['n' => $p['l1n'], 'a' => $p['l1a'], 't' => $p['l1t']],
                      ['n' => $p['l2n'], 'a' => $p['l2a'], 't' => $p['l2t']],
                    ];
                    foreach ($jugadores_locales as $jl):
                      if (empty($jl['n'])) continue;
                      $tel = preg_replace('/\D/', '', $jl['t'] ?? '');
                      $clean = str_starts_with($tel, '56') ? $tel : '56' . $tel;
                      $msg = rawurlencode("Hola {$jl['n']}, te contacto porque vi que tienen un partido reprogramado en la Elite Padel League. ¿Les interesaría coordinar para adelantar partido?");
                      $wsp = $tel ? "https://wa.me/{$clean}?text={$msg}" : null;
                      $iniciales = mb_strtoupper(mb_substr($jl['n'], 0, 1) . mb_substr($jl['a'] ?? '', 0, 1));
                  ?>
                  <div class="rp-otro-player-row">
                    <div class="rp-otro-player-avatar"><?= $iniciales ?></div>
                    <div class="rp-otro-player-info">
                      <span class="rp-otro-player-name"><?= epl_h($jl['n'] . ' ' . ($jl['a'] ?? '')) ?></span>
                    </div>
                    <?php if ($wsp): ?>
                      <a href="<?= $wsp ?>" target="_blank" class="rp-otro-contact-btn wsp" title="Contactar por WhatsApp">
                        <?= $WA_SVG ?>
                      </a>
                    <?php else: ?>
                      <span class="rp-otro-no-contact">Sin tel.</span>
                    <?php endif; ?>
                  </div>
                  <?php endforeach; ?>
                </div>
              </div>

              <!-- Divisor -->
              <div class="rp-otro-divider"></div>

              <!-- Equipo Visitante -->
              <div class="rp-otro-equipo-box<?= $visitante_solicito ? ' rp-equipo-solicitante-box' : '' ?>">
                <div class="rp-otro-equipo-title<?= $visitante_solicito ? ' rp-title-solicitante' : '' ?>">
                  Visitante: <?= epl_h($p['visitante_nombre']) ?>
                  <?php if ($equipo_id > 0): ?>
                    <?php if ((int)$p['visitante_equipo_id'] === $equipo_id): ?>
                      <span class="badge-propio">Tú</span>
                    <?php elseif (in_array((int)$p['visitante_equipo_id'], $rivales_jugados)): ?>
                      <span class="badge-jugado-rival">Ya jugado</span>
                    <?php else: ?>
                      <span class="badge-pendiente-rival">Pendiente</span>
                    <?php endif; ?>
                  <?php endif; ?>
                  <?= $visitante_solicito ? '<span class="rp-solicito-badge">Solicitó</span>' : '' ?>
                </div>
                <div class="rp-otro-players-list">
                  <?php
                    $jugadores_visitantes = [
                      ['n' => $p['v1n'], 'a' => $p['jv1a'], 't' => $p['v1t']],
                      ['n' => $p['v2n'], 'a' => $p['jv2a'], 't' => $p['v2t']],
                    ];
                    foreach ($jugadores_visitantes as $jv):
                      if (empty($jv['n'])) continue;
                      $tel = preg_replace('/\D/', '', $jv['t'] ?? '');
                      $clean = str_starts_with($tel, '56') ? $tel : '56' . $tel;
                      $msg = rawurlencode("Hola {$jv['n']}, te contacto porque vi que tienen un partido reprogramado en la Elite Padel League. ¿Les interesaría coordinar para adelantar partido?");
                      $wsp = $tel ? "https://wa.me/{$clean}?text={$msg}" : null;
                      $iniciales = mb_strtoupper(mb_substr($jv['n'], 0, 1) . mb_substr($jv['a'] ?? '', 0, 1));
                  ?>
                  <div class="rp-otro-player-row">
                    <div class="rp-otro-player-avatar"><?= $iniciales ?></div>
                    <div class="rp-otro-player-info">
                      <span class="rp-otro-player-name"><?= epl_h($jv['n'] . ' ' . ($jv['a'] ?? '')) ?></span>
                    </div>
                    <?php if ($wsp): ?>
                      <a href="<?= $wsp ?>" target="_blank" class="rp-otro-contact-btn wsp" title="Contactar por WhatsApp">
                        <?= $WA_SVG ?>
                      </a>
                    <?php else: ?>
                      <span class="rp-otro-no-contact">Sin tel.</span>
                    <?php endif; ?>
                  </div>
                  <?php endforeach; ?>
                </div>
              </div>
            </div>

          </div>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

  <!-- Modal: Asignar fecha a partido reprogramado sin fecha -->
  <div id="modalAsignarFecha" class="modal-af-overlay" style="display:none" onclick="cerrarModalAsignar(event)">
    <div class="modal-af-content" onclick="event.stopPropagation()">
      <div class="modal-af-header">
        <h3>📅 Asignar fecha al partido</h3>
        <button type="button" class="modal-af-close" onclick="cerrarModalAsignar()" aria-label="Cerrar">×</button>
      </div>
      <p class="modal-af-partido" id="modalPartidoStr"></p>
      <p class="modal-af-info">
        Ahora que coordinaste con el rival, propone una fecha nueva. Esto creará una solicitud que el administrador deberá aprobar para asignar cancha.
      </p>
      <form method="post">
        <input type="hidden" name="action" value="asignar_fecha">
        <input type="hidden" name="partido_id" id="modalPartidoId">

        <label class="modal-af-label">Nueva fecha y hora</label>
        <input type="text" name="fecha_propuesta" id="fpModalFecha" class="form-control"
               placeholder="Seleccioná día y hora"
               data-min="<?= date('Y-m-d H:i', strtotime('+48 hours')) ?>"
               autocomplete="off" readonly required
               style="cursor:pointer;background:#fff">

        <label class="modal-af-label" style="margin-top:1rem">Motivo (opcional)</label>
        <textarea name="motivo" class="form-control" rows="2"
                  placeholder="Ej: ya coordinamos con el rival"></textarea>

        <label class="rp-acuerdo-row" style="margin-top:1rem">
          <input type="checkbox" name="mutuo_acuerdo">
          <div>
            <div style="font-weight:700;font-size:.88rem;color:#1e40af">Confirmo mutuo acuerdo</div>
            <div style="font-size:.78rem;color:#3b82f6;margin-top:.1rem">El rival ya está de acuerdo con esta fecha (aplica inmediatamente sin esperar al admin).</div>
          </div>
        </label>

        <div class="modal-af-actions">
          <button type="button" class="modal-af-btn-cancel" onclick="cerrarModalAsignar()">Cancelar</button>
          <button type="submit" class="modal-af-btn-submit">Enviar solicitud</button>
        </div>
      </form>
    </div>
  </div>

</main>
</div>

<style>
@keyframes rp-fade-up {
  from { opacity: 0; transform: translateY(10px); }
  to   { opacity: 1; transform: translateY(0); }
}
@keyframes rp-pop {
  0%   { transform: scale(.9); opacity: 0; }
  60%  { transform: scale(1.05); }
  100% { transform: scale(1); opacity: 1; }
}

.rp-success {
  display: flex; align-items: flex-start; gap: 1rem;
  background: linear-gradient(135deg, #f0fdf4, #dcfce7);
  border: 1px solid #86efac; border-radius: 14px;
  padding: 1.25rem 1.5rem; margin-bottom: 1.5rem; color: #166534;
  box-shadow: 0 4px 16px rgba(34,197,94,.12);
  animation: rp-fade-up .35s ease both;
}
.rp-empty { text-align: center; padding: 3rem 1rem; color: var(--gray-400); animation: rp-fade-up .4s ease both; }
.rp-empty h3 { font-family: var(--font-head); text-transform: uppercase; color: var(--navy); margin: 0 0 .5rem; }

/* Pestañas */
.rp-tabs {
  display: flex;
  gap: .25rem;
  border-bottom: 2px solid var(--gray-100);
  margin-bottom: 1.5rem;
  overflow-x: auto;
  scrollbar-width: none; /* Firefox */
  -webkit-overflow-scrolling: touch;
}
.rp-tabs::-webkit-scrollbar {
  display: none; /* Chrome/Safari */
}
.rp-tab-btn {
  background: transparent;
  border: none;
  border-bottom: 3px solid transparent;
  color: var(--gray-500);
  font-family: var(--font-head);
  font-size: .88rem;
  font-weight: 700;
  padding: .75rem 1rem;
  cursor: pointer;
  white-space: nowrap;
  display: flex;
  align-items: center;
  gap: .5rem;
  transition: all .25s ease;
  margin-bottom: -2px;
}
.rp-tab-btn:hover {
  color: var(--navy);
  border-bottom-color: var(--gray-300);
}
.rp-tab-btn.active {
  color: var(--navy);
  border-bottom-color: var(--gold);
}
.rp-tab-badge {
  background: var(--navy);
  color: var(--white);
  font-size: .7rem;
  font-weight: 800;
  padding: .15rem .45rem;
  border-radius: 20px;
  min-width: 18px;
  text-align: center;
  box-shadow: 0 2px 5px rgba(28,47,72,.2);
}
.rp-tab-btn.active .rp-tab-badge {
  background: var(--navy);
  color: var(--gold);
}
.rp-tab-panel {
  display: none;
  animation: rp-fade-up .3s ease both;
}
.rp-tab-panel.active {
  display: block;
}

/* Tarjetas de otros partidos reprogramados */
.rp-card-otro {
  background: var(--white);
  border: 1px solid var(--gray-100);
  border-radius: 18px;
  padding: 1.25rem;
  box-shadow: 0 4px 18px rgba(0,0,0,.03);
  transition: all .2s ease;
}
.rp-card-otro:hover {
  box-shadow: 0 6px 24px rgba(0,0,0,.06);
  border-color: var(--gray-200);
}
.rp-otro-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  border-bottom: 1px solid var(--gray-100);
  padding-bottom: .85rem;
  margin-bottom: .85rem;
  flex-wrap: wrap;
  gap: .5rem;
}
.rp-otro-teams {
  display: flex;
  align-items: center;
  gap: .55rem;
  flex-wrap: wrap;
}
.rp-team-name {
  font-family: var(--font-head);
  font-weight: 800;
  color: var(--navy);
  font-size: .92rem;
}
.rp-vs-small {
  background: var(--navy);
  color: var(--gold);
  border-radius: 5px;
  padding: .1rem .35rem;
  font-size: .55rem;
  font-weight: 800;
}
.rp-otro-fecha {
  font-size: .75rem;
  color: var(--gray-500);
  margin-top: .25rem;
}
.rp-otro-players-section {
  display: flex;
  gap: 1.25rem;
  align-items: stretch;
}
.rp-otro-equipo-box {
  flex: 1;
  min-width: 0;
}
.rp-otro-equipo-title {
  font-size: .72rem;
  font-weight: 800;
  color: var(--gray-400);
  text-transform: uppercase;
  letter-spacing: .05em;
  margin-bottom: .5rem;
}
.rp-otro-players-list {
  display: flex;
  flex-direction: column;
  gap: .5rem;
}
.rp-otro-player-row {
  display: flex;
  align-items: center;
  background: var(--gray-50);
  border-radius: 12px;
  padding: .45rem .75rem;
  gap: .6rem;
}
.rp-otro-player-avatar {
  width: 28px;
  height: 28px;
  border-radius: 50%;
  background: var(--navy);
  color: var(--gold);
  font-size: .68rem;
  font-weight: 800;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}
.rp-otro-player-info {
  flex: 1;
  min-width: 0;
}
.rp-otro-player-name {
  font-size: .8rem;
  font-weight: 700;
  color: var(--navy);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  display: block;
}
.rp-otro-contact-btn {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 28px;
  height: 28px;
  border-radius: 50%;
  border: none;
  cursor: pointer;
  transition: all .2s;
  flex-shrink: 0;
  text-decoration: none;
}
.rp-otro-contact-btn.wsp {
  background: #25d366;
  color: white;
}
.rp-otro-contact-btn.wsp:hover {
  background: #20ba5a;
  transform: scale(1.1);
  box-shadow: 0 3px 8px rgba(37,211,102,.3);
}
.rp-otro-no-contact {
  font-size: .7rem;
  color: var(--gray-400);
  font-style: italic;
  white-space: nowrap;
}
.rp-otro-divider {
  width: 1px;
  background: var(--gray-100);
  align-self: stretch;
}

@media (max-width: 768px) {
  .rp-otro-players-section {
    flex-direction: column;
    gap: .85rem;
  }
  .rp-otro-divider {
    width: 100%;
    height: 1px;
  }
}

.rp-team-solicitante {
  color: #e11d48 !important;
}
.rp-solicito-badge {
  background: #ffe4e6;
  color: #be123c;
  font-size: .62rem;
  font-weight: 800;
  padding: .15rem .45rem;
  border-radius: 6px;
  margin-left: .4rem;
  text-transform: uppercase;
  letter-spacing: .05em;
  display: inline-block;
  border: 1px solid #fecdd3;
  vertical-align: middle;
}
.rp-title-solicitante {
  color: #be123c !important;
}
.rp-equipo-solicitante-box {
  border-left: 3px solid #e11d48;
  padding-left: .6rem;
}

.badge-propio {
  background: #e0f2fe;
  color: #0369a1;
  border: 1px solid #bae6fd;
  font-size: .62rem;
  font-weight: 800;
  padding: .15rem .45rem;
  border-radius: 6px;
  margin-left: .4rem;
  text-transform: uppercase;
  letter-spacing: .05em;
  display: inline-block;
  vertical-align: middle;
}
.badge-jugado-rival {
  background: #f1f5f9;
  color: #475569;
  border: 1px solid #cbd5e1;
  font-size: .62rem;
  font-weight: 800;
  padding: .15rem .45rem;
  border-radius: 6px;
  margin-left: .4rem;
  text-transform: uppercase;
  letter-spacing: .05em;
  display: inline-block;
  vertical-align: middle;
}
.badge-pendiente-rival {
  background: #dcfce7;
  color: #15803d;
  border: 1px solid #bbf7d0;
  font-size: .62rem;
  font-weight: 800;
  padding: .15rem .45rem;
  border-radius: 6px;
  margin-left: .4rem;
  text-transform: uppercase;
  letter-spacing: .05em;
  display: inline-block;
  vertical-align: middle;
}

.rp-team-name {
  word-break: break-word;
  white-space: normal !important;
}

.rp-tab-text-short {
  display: none;
}

.rp-partido-teams span {
  white-space: normal !important;
  word-break: break-word;
  line-height: 1.25;
}

.fechas-scroll {
  display: flex;
  flex-wrap: wrap;
  gap: .35rem;
  padding: .3rem 0 .25rem;
  width: 100%;
  box-sizing: border-box;
}

/* En desktop si hay muchos, scroll horizontal */
@media (min-width: 768px) {
  .fechas-scroll {
    flex-wrap: nowrap;
    overflow-x: auto;
    overflow-y: hidden;
    touch-action: pan-x;
    scrollbar-width: thin;
    scrollbar-color: var(--navy) #f1f5f9;
    padding-bottom: .6rem;
  }
  .fechas-scroll::-webkit-scrollbar { height: 6px; }
  .fechas-scroll::-webkit-scrollbar-track { background: #f1f5f9; border-radius: 10px; }
  .fechas-scroll::-webkit-scrollbar-thumb { background: var(--navy); border-radius: 10px; }
}

@media (max-width: 576px) {
  .rp-partido-teams, .rp-otro-teams {
    flex-direction: column !important;
    align-items: flex-start !important;
    gap: .25rem !important;
  }
  .rp-vs, .rp-vs-small {
    align-self: flex-start !important;
    padding: .1rem .35rem !important;
    font-size: .55rem !important;
    margin: .1rem 0 !important;
  }
  .rp-partido-option {
    padding: .75rem .6rem !important;
    gap: .5rem !important;
  }
  .rp-partido-content {
    gap: .5rem !important;
  }
  .rp-partido-fecha {
    padding: .3rem .45rem !important;
    min-width: 38px !important;
  }
  .rp-dia {
    font-size: .95rem !important;
  }
  .rp-mes {
    font-size: .48rem !important;
  }
}

@media (max-width: 480px) {
  .rp-tab-text-long {
    display: none;
  }
  .rp-tab-text-short {
    display: inline;
  }
  .rp-tabs {
    gap: 0;
  }
  .rp-tab-btn {
    font-size: .75rem !important;
    padding: .6rem .4rem !important;
    gap: .25rem !important;
  }
  .rp-tab-badge {
    font-size: .65rem !important;
    padding: .1rem .35rem !important;
    min-width: 14px !important;
  }
}

/* Stepper layout */
.rp-stepper { position: relative; }
.rp-card {
  background: var(--white); border-radius: 20px;
  border: 1px solid var(--gray-100); padding: 1.5rem; margin-bottom: 1rem;
  box-shadow: 0 4px 20px rgba(0,0,0,.04);
  transition: box-shadow .2s;
  animation: rp-fade-up .35s ease both;
}
.rp-card:hover { box-shadow: 0 6px 28px rgba(0,0,0,.08); }
.rp-card-rivales { background: linear-gradient(135deg, #f0f9ff, #e0f2fe); border-color: #bae6fd; }
.rp-card-fecha   { background: linear-gradient(135deg, #eff6ff, #dbeafe); border-color: #bfdbfe; }
.rp-card-header  { display: flex; align-items: flex-start; gap: 1rem; }
.rp-step-dot {
  width: 36px; height: 36px; border-radius: 50%;
  background: linear-gradient(135deg, var(--navy), #1a3a64);
  color: var(--white); font-weight: 800; font-size: .85rem;
  display: flex; align-items: center; justify-content: center; flex-shrink: 0;
  box-shadow: 0 4px 12px rgba(28,47,72,.25);
}
.rp-card-title { font-weight: 800; font-size: .95rem; color: var(--navy); }
.rp-card-sub   { font-size: .78rem; color: var(--gray-500); margin-top: .2rem; }

/* Partido radio cards */
.rp-partidos-list {
  display: flex; flex-direction: column; gap: .5rem;
  max-height: 280px; overflow-y: auto;
  padding-right: .25rem;
  scrollbar-width: thin; scrollbar-color: var(--gray-200) transparent;
}
.rp-partidos-list::-webkit-scrollbar { width: 5px; }
.rp-partidos-list::-webkit-scrollbar-track { background: transparent; }
.rp-partidos-list::-webkit-scrollbar-thumb { background: var(--gray-200); border-radius: 4px; }
.rp-partido-option {
  display: flex; align-items: center; gap: .85rem;
  border: 1.5px solid var(--gray-100); border-radius: 14px;
  padding: .85rem 1rem; cursor: pointer;
  transition: all .22s cubic-bezier(.4,0,.2,1); background: var(--white);
}
.rp-partido-option:hover {
  border-color: var(--gold); background: rgba(201,167,98,.04);
  transform: translateX(2px); box-shadow: 0 2px 10px rgba(201,167,98,.12);
}
.rp-partido-option input[type=radio] { display: none; }
.rp-partido-option:has(input:checked) {
  border-color: var(--navy); background: linear-gradient(135deg, rgba(28,47,72,.04), rgba(28,47,72,.07));
  box-shadow: 0 4px 14px rgba(28,47,72,.1);
}
.rp-partido-content { display: flex; align-items: center; gap: .85rem; flex: 1; min-width: 0; }
.rp-partido-fecha {
  background: linear-gradient(135deg, #f3f4f6, #e5e7eb);
  border-radius: 10px; padding: .4rem .6rem; text-align: center; min-width: 42px; flex-shrink: 0;
  transition: all .22s;
}
.rp-dia { display: block; font-family: var(--font-head); font-size: 1.1rem; color: var(--navy); line-height: 1; }
.rp-mes { display: block; font-size: .5rem; font-weight: 800; color: var(--gray-400); letter-spacing: .08em; text-transform: uppercase; }
.rp-partido-option:has(input:checked) .rp-partido-fecha { background: linear-gradient(135deg, var(--navy), #1e3a5f); }
.rp-partido-option:has(input:checked) .rp-dia,
.rp-partido-option:has(input:checked) .rp-mes { color: var(--gold); }
.rp-partido-teams { flex: 1; display: flex; align-items: center; gap: .55rem; font-size: .82rem; font-weight: 700; color: var(--navy); min-width: 0; flex-wrap: wrap; }
.rp-vs { background: linear-gradient(135deg, var(--navy), #2d5a8e); color: var(--gold); border-radius: 6px; padding: .12rem .4rem; font-size: .6rem; font-weight: 800; flex-shrink: 0; }
.rp-partido-option:has(input:checked) .rp-vs { background: linear-gradient(135deg, var(--gold), #b8975a); color: var(--navy); }
.rp-partido-meta { display: flex; flex-direction: column; align-items: flex-end; gap: .25rem; flex-shrink: 0; }
.rp-jornada { font-size: .62rem; font-weight: 700; color: var(--gray-400); }
.rp-partido-check {
  width: 28px; height: 28px; border-radius: 50%; border: 2px solid var(--gray-200);
  display: flex; align-items: center; justify-content: center; flex-shrink: 0;
  color: transparent; transition: all .22s;
}
.rp-partido-option:has(input:checked) .rp-partido-check {
  background: linear-gradient(135deg, var(--navy), #1e3a5f);
  border-color: var(--navy); color: var(--white);
  box-shadow: 0 3px 10px rgba(28,47,72,.3);
  animation: rp-pop .3s ease both;
}

/* Toggle switch */
.rp-toggle-row { display: flex; align-items: center; gap: 1rem; cursor: pointer; padding: .5rem 0; }
.rp-toggle-info { flex: 1; }
.rp-switch { position: relative; display: inline-block; width: 52px; height: 28px; flex-shrink: 0; }
.rp-switch input { opacity: 0; width: 0; height: 0; }
.rp-slider {
  position: absolute; cursor: pointer; inset: 0;
  background: var(--gray-200); border-radius: 28px; transition: .3s;
  box-shadow: inset 0 2px 4px rgba(0,0,0,.1);
}
.rp-slider:before {
  position: absolute; content: ""; height: 22px; width: 22px;
  left: 3px; bottom: 3px; background: white; border-radius: 50%; transition: .3s;
  box-shadow: 0 2px 6px rgba(0,0,0,.18);
}
.rp-switch input:checked + .rp-slider { background: linear-gradient(135deg, #ef4444, #dc2626); }
.rp-switch input:checked + .rp-slider:before { transform: translateX(24px); }

/* Mutuo acuerdo */
.rp-acuerdo-row {
  display: flex; align-items: flex-start; gap: .85rem; margin-top: 1rem; cursor: pointer;
  background: linear-gradient(135deg, #dbeafe, #bfdbfe);
  border-radius: 14px; padding: 1rem; border: 1px solid #bfdbfe;
}
.rp-acuerdo-row input[type=checkbox] { width: 18px; height: 18px; margin-top: .15rem; flex-shrink: 0; accent-color: #1d4ed8; }

.rp-submit-btn {
  width: 100%; display: flex; align-items: center; justify-content: center; gap: .7rem;
  background: linear-gradient(135deg, var(--navy), #1a3a64);
  color: var(--white); border: none; border-radius: 16px; padding: 1.2rem;
  font-family: var(--font-head); font-size: 1rem; text-transform: uppercase; letter-spacing: .08em;
  cursor: pointer; transition: all .25s cubic-bezier(.4,0,.2,1); margin-bottom: 1rem;
  box-shadow: 0 6px 20px rgba(28,47,72,.25);
}
.rp-submit-btn:hover { background: linear-gradient(135deg, var(--gold), #b8975a); color: var(--navy); transform: translateY(-2px); box-shadow: 0 10px 28px rgba(201,167,98,.4); }
.rp-submit-btn:active { transform: translateY(0); }

.rp-historial-row {
  background: var(--white); border: 1px solid var(--gray-100);
  border-radius: 14px; padding: 1rem 1.25rem;
  display: flex; align-items: flex-start; gap: 1rem;
  transition: box-shadow .2s;
}
.rp-historial-row:hover { box-shadow: 0 4px 16px rgba(0,0,0,.07); }

/* Rival cards creadas en JS */
.rp-rival-card {
  display: flex; align-items: center; justify-content: space-between;
  background: white; border: 1px solid #bae6fd; border-radius: 12px;
  padding: .9rem 1rem; gap: .75rem;
  transition: box-shadow .18s;
}
.rp-rival-card:hover { box-shadow: 0 3px 12px rgba(3,105,161,.1); }
.rp-rival-name { font-weight: 700; color: var(--navy); font-size: .9rem; }
.rp-rival-avatar {
  width: 36px; height: 36px; border-radius: 50%;
  background: linear-gradient(135deg, var(--navy), #1a3a64);
  color: var(--white); font-weight: 800; font-size: .8rem;
  display: flex; align-items: center; justify-content: center; flex-shrink: 0;
}

/* ─── Botón Asignar Fecha + Modal ─── */
.btn-asignar-fecha {
  background: linear-gradient(135deg, #f97316, #ea580c);
  color: #fff;
  border: none;
  border-radius: 10px;
  padding: .55rem 1rem;
  font-family: var(--font-head);
  font-weight: 700;
  font-size: .72rem;
  text-transform: uppercase;
  letter-spacing: .05em;
  cursor: pointer;
  transition: all .2s;
  box-shadow: 0 3px 10px rgba(249,115,22,.3);
  margin-top: .5rem;
  width: 100%;
}
.btn-asignar-fecha:hover {
  background: linear-gradient(135deg, #ea580c, #c2410c);
  transform: translateY(-1px);
  box-shadow: 0 5px 14px rgba(249,115,22,.4);
}
@media (min-width: 576px) {
  .btn-asignar-fecha { width: auto; margin-top: 0; align-self: center; }
}

.modal-af-overlay {
  position: fixed; inset: 0;
  background: rgba(15,23,42,.55);
  backdrop-filter: blur(4px);
  z-index: 9998;
  display: flex; align-items: center; justify-content: center;
  padding: 1rem;
  animation: rp-fade-up .25s ease both;
}
.modal-af-content {
  background: #fff;
  border-radius: 20px;
  max-width: 480px;
  width: 100%;
  padding: 1.5rem;
  box-shadow: 0 25px 60px rgba(15,23,42,.3);
  max-height: 90vh;
  overflow-y: auto;
}
.modal-af-header {
  display: flex; align-items: center; justify-content: space-between;
  margin-bottom: .5rem;
}
.modal-af-header h3 {
  font-family: var(--font-head);
  font-size: 1.1rem;
  color: var(--navy);
  margin: 0;
}
.modal-af-close {
  background: transparent; border: none;
  font-size: 1.8rem; color: var(--gray-400);
  cursor: pointer; line-height: 1;
  width: 32px; height: 32px;
  border-radius: 50%;
  transition: all .15s;
}
.modal-af-close:hover { background: var(--gray-100); color: var(--navy); }
.modal-af-partido {
  font-weight: 700; color: var(--navy); font-size: .92rem;
  margin: 0 0 .5rem; padding: .65rem .9rem;
  background: #f8fafc; border-radius: 10px;
}
.modal-af-info {
  font-size: .78rem; color: var(--gray-500); line-height: 1.5;
  margin: 0 0 1rem;
}
.modal-af-label {
  display: block;
  font-size: .75rem; font-weight: 800;
  text-transform: uppercase; letter-spacing: .05em;
  color: var(--navy); margin-bottom: .35rem;
}
.modal-af-actions {
  display: flex; gap: .65rem; margin-top: 1.25rem;
  flex-wrap: wrap;
}
.modal-af-btn-cancel, .modal-af-btn-submit {
  flex: 1; min-width: 120px;
  padding: .8rem 1rem;
  border-radius: 12px;
  font-family: var(--font-head);
  font-weight: 700; font-size: .82rem;
  text-transform: uppercase; letter-spacing: .05em;
  cursor: pointer; transition: all .2s;
  border: none;
}
.modal-af-btn-cancel {
  background: var(--gray-100); color: var(--gray-600);
}
.modal-af-btn-cancel:hover { background: var(--gray-200); }
.modal-af-btn-submit {
  background: linear-gradient(135deg, var(--navy), #1a3a64);
  color: #fff;
  box-shadow: 0 4px 12px rgba(28,47,72,.25);
}
.modal-af-btn-submit:hover {
  background: linear-gradient(135deg, #f97316, #ea580c);
  box-shadow: 0 6px 18px rgba(249,115,22,.35);
}
</style>

<script>
const WA_SVG = `<svg width="14" height="14" fill="currentColor" viewBox="0 0 24 24"><path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.588-5.946 0-6.556 5.332-11.888 11.888-11.888 3.176 0 6.161 1.237 8.404 3.48s3.481 5.229 3.481 8.404c0 6.556-5.332 11.888-11.888 11.888-2.003 0-3.963-.505-5.698-1.465l-6.305 1.693zm6.443-4.045c1.474.873 3.103 1.332 4.775 1.332 5.054 0 9.163-4.109 9.163-9.163s-4.109-9.163-9.163-9.163-9.163 4.109-9.163 9.163c0 1.95.623 3.856 1.799 5.437l-1.002 3.659 3.743-.999zm10.742-5.466c-.303-.151-1.788-.882-2.067-.981-.278-.099-.481-.151-.683.151-.202.303-.783.981-.96 1.183-.177.202-.354.227-.657.076-.303-.151-1.28-.471-2.438-1.504-.901-.803-1.508-1.796-1.685-2.098-.177-.302-.019-.465.132-.615.136-.135.303-.354.455-.53.151-.177.202-.303.303-.505.101-.202.051-.379-.025-.53-.076-.151-.683-1.643-.935-2.249-.245-.59-.495-.51-.683-.52l-.582-.01c-.202 0-.531.076-.809.379-.278.303-1.062 1.037-1.062 2.529 0 1.492 1.087 2.932 1.239 3.134.151.202 2.14 3.268 5.184 4.582.724.312 1.29.499 1.731.639.727.231 1.388.199 1.911.121.582-.087 1.788-.731 2.041-1.439.253-.708.253-1.313.177-1.439-.076-.126-.278-.202-.581-.353z"/></svg>`;

function toggleRivalNoResp(chk) {
  const sec = document.getElementById('seccionFecha');
  const chkMutuo = document.getElementById('chkMutuo');
  if (chk.checked) {
    sec.style.display = 'none';
    chkMutuo.removeAttribute('required');
  } else {
    sec.style.display = 'block';
    chkMutuo.setAttribute('required', '');
  }
}

function rpSelPartido(radio) {
  document.getElementById('hiddenPartidoIdRp').value = radio.value;
  cargarRivales(radio.dataset.rivales);
}

function cargarRivales(b64) {
  const wrapper = document.getElementById('seccionRivales');
  const lista   = document.getElementById('listaRivales');
  if (!b64) { wrapper.style.display = 'none'; return; }

  const rivales = JSON.parse(atob(b64));
  lista.innerHTML = '';
  let alguno = false;

  rivales.forEach(r => {
    if (!r.n) return;
    alguno = true;
    const initials = ((r.n||'').charAt(0) + (r.a||'').charAt(0)).toUpperCase();
    const tel   = (r.t || '').replace(/\D/g,'');
    const clean = tel.startsWith('56') ? tel : '56' + tel;
    const msg   = encodeURIComponent('Hola ' + r.n + ', te contacto por el partido de la Elite Padel League. ¿Podemos coordinar la reprogramación?');
    const wsp   = tel ? `https://wa.me/${clean}?text=${msg}` : null;

    const div = document.createElement('div');
    div.className = 'rp-rival-card';
    div.innerHTML =
      `<div class="rp-rival-avatar">${initials}</div>
       <div style="flex:1"><span class="rp-rival-name">${r.n} ${r.a||''}</span></div>` +
      (wsp
        ? `<a href="${wsp}" target="_blank" class="btn btn-sm" style="background:linear-gradient(135deg,#16a34a,#15803d);color:#fff;border:none;gap:.4rem;font-size:.72rem;box-shadow:0 3px 10px rgba(22,163,74,.3)">${WA_SVG} WhatsApp</a>`
        : `<span style="font-size:.75rem;color:var(--gray-400);font-style:italic">Sin teléfono</span>`);
    lista.appendChild(div);
  });

  wrapper.style.display = alguno ? 'block' : 'none';
}

function rpFilterJornada(jor, btn) {
  // Desactivar todos los botones de jornada
  document.querySelectorAll('#tab-otros-reprogramados .fecha-btn').forEach(b => {
    b.classList.remove('active');
  });
  // Activar el seleccionado
  btn.classList.add('active');
  
  actualizarVisibilidadGruposJornada();
}

function rpToggleOcultarJugados(chk) {
  actualizarVisibilidadGruposJornada();
}

function actualizarVisibilidadGruposJornada() {
  const chk = document.getElementById('chkOcultarJugados');
  const hidePlayed = chk ? chk.checked : false;
  
  // Buscar qué botón de fecha está activo
  const activeBtn = document.querySelector('#tab-otros-reprogramados .fecha-btn.active');
  let selectedJor = 'all';
  if (activeBtn) {
    const onclickStr = activeBtn.getAttribute('onclick') || '';
    const match = onclickStr.match(/'([^']+)'/);
    if (match) {
      selectedJor = match[1];
    }
  }

  document.querySelectorAll('#tab-otros-reprogramados .jornada-group').forEach(group => {
    const jor = group.getAttribute('data-jornada');
    
    // Si la jornada no coincide con la seleccionada, ocultamos el grupo completo
    if (selectedJor !== 'all' && jor != selectedJor) {
      group.style.display = 'none';
      return;
    }
    
    let cardsVisibles = 0;
    group.querySelectorAll('.rp-card-otro').forEach(card => {
      const yaJugadoAmbos = card.getAttribute('data-ya-jugado-ambos') == '1';
      if (hidePlayed && yaJugadoAmbos) {
        card.style.display = 'none';
      } else {
        card.style.display = '';
        cardsVisibles++;
      }
    });
    
    // Si no hay partidos visibles en esta jornada, ocultamos el grupo completo
    if (cardsVisibles === 0) {
      group.style.display = 'none';
    } else {
      group.style.display = '';
    }
  });
}

function rpSwitchTab(tabId) {
  // Ocultar todos los paneles
  document.querySelectorAll('.rp-tab-panel').forEach(p => {
    p.style.display = 'none';
    p.classList.remove('active');
  });
  // Desactivar todos los botones
  document.querySelectorAll('.rp-tab-btn').forEach(b => {
    b.classList.remove('active');
  });
  
  // Mostrar el panel seleccionado
  const targetPanel = document.getElementById('tab-' + tabId);
  if (targetPanel) {
    targetPanel.style.display = 'block';
    targetPanel.classList.add('active');
  }
  
  // Activar el botón seleccionado
  const targetBtn = document.querySelector(`.rp-tab-btn[data-tab="${tabId}"]`);
  if (targetBtn) {
    targetBtn.classList.add('active');
  }
  
  // Actualizar hash en la URL sin saltar el scroll
  if (history.pushState) {
    history.pushState(null, null, '#' + tabId);
  } else {
    window.location.hash = tabId;
  }
}

document.addEventListener('DOMContentLoaded', () => {
  const checked = document.querySelector('input[name="_rp_partido_pick"]:checked');
  if (checked) {
    document.getElementById('hiddenPartidoIdRp').value = checked.value;
    cargarRivales(checked.dataset.rivales);
    // Scroll suave al partido pre-seleccionado
    setTimeout(() => {
      checked.closest('.rp-partido-option')?.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }, 200);
  }

  // Inicialización de pestañas al cargar
  let defaultTab = 'solicitar';
  const hash = window.location.hash.replace('#', '');
  if (hash && ['solicitar', 'mis-reprogramaciones', 'otros-reprogramados'].includes(hash)) {
    defaultTab = hash;
  }
  rpSwitchTab(defaultTab);
});
</script>

<!-- ── Flatpickr: calendario moderno ── -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/flatpickr.min.css">
<script src="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/flatpickr.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/l10n/es.js"></script>
<style>
  /* ─── Flatpickr · estilo limpio y moderno ─── */
  .flatpickr-calendar {
    background:#fff !important;
    border-radius:16px !important;
    box-shadow:0 20px 50px rgba(15,23,42,.15), 0 0 0 1px rgba(15,23,42,.06) !important;
    font-family:'Montserrat',sans-serif !important;
    border:none !important;
    overflow:hidden;
    padding:.4rem;
  }
  .flatpickr-calendar.open { z-index:9999 !important; }
  .flatpickr-calendar::before, .flatpickr-calendar::after { display:none !important; }

  /* Header limpio */
  .flatpickr-months { padding:.55rem .35rem .3rem; }
  .flatpickr-months .flatpickr-month { background:transparent !important; color:#0f172a !important; height:38px; }
  .flatpickr-current-month { padding-top:.35rem !important; font-size:.95rem !important; color:#0f172a !important; font-weight:800 !important; display:flex; align-items:center; justify-content:center; gap:.35rem; }
  .flatpickr-current-month .flatpickr-monthDropdown-months,
  .flatpickr-current-month input.cur-year { color:#0f172a !important; font-weight:800 !important; font-size:.95rem !important; }
  .flatpickr-current-month .flatpickr-monthDropdown-months:hover { background:#f1f5f9 !important; }
  .flatpickr-monthDropdown-months option { background:#fff; color:#0f172a; }
  .flatpickr-prev-month, .flatpickr-next-month {
    color:#475569 !important; padding:.4rem !important; border-radius:8px; transition:all .12s;
    top:.45rem !important;
  }
  .flatpickr-prev-month:hover, .flatpickr-next-month:hover { background:#f1f5f9 !important; }
  .flatpickr-prev-month svg, .flatpickr-next-month svg { fill:#475569 !important; width:13px; height:13px; }

  /* Días de la semana */
  .flatpickr-weekdays { background:transparent !important; height:30px; }
  .flatpickr-weekday {
    color:#94a3b8 !important; font-weight:700 !important; font-size:.68rem !important;
    text-transform:uppercase; letter-spacing:.04em;
  }

  /* Días del mes */
  .flatpickr-days { padding:0 .15rem .25rem; }
  .dayContainer { padding:0 !important; }
  .flatpickr-day {
    font-weight:600 !important; color:#0f172a !important;
    border-radius:10px !important; border:none !important;
    height:38px !important; line-height:38px !important;
    margin:1px 0 !important; transition:all .12s;
    max-width:38px;
  }
  .flatpickr-day:hover { background:#f1f5f9 !important; color:#0f172a !important; }
  .flatpickr-day.today {
    border:none !important; color:#C9A762 !important; font-weight:800 !important;
    background:transparent !important; position:relative;
  }
  .flatpickr-day.today::after {
    content:''; position:absolute; bottom:5px; left:50%; transform:translateX(-50%);
    width:4px; height:4px; border-radius:50%; background:#C9A762;
  }
  .flatpickr-day.today.selected::after { background:#fff; }
  .flatpickr-day.selected, .flatpickr-day.selected:hover,
  .flatpickr-day.startRange, .flatpickr-day.endRange {
    background:#1c2f48 !important; color:#fff !important;
    box-shadow:0 4px 12px rgba(28,47,72,.25);
    font-weight:800 !important;
  }
  .flatpickr-day.flatpickr-disabled,
  .flatpickr-day.prevMonthDay, .flatpickr-day.nextMonthDay {
    color:#cbd5e1 !important; background:transparent !important;
  }
  .flatpickr-day.flatpickr-disabled { text-decoration:line-through; opacity:.5; }

  /* Sección de horas */
  .flatpickr-time {
    border-top:1px solid #f1f5f9 !important;
    background:#f8fafc !important;
    margin:.35rem -.4rem -.4rem !important;
    padding:.7rem !important;
    border-radius:0 0 12px 12px;
    height:auto !important;
  }
  .flatpickr-time .numInputWrapper { background:#fff !important; border-radius:8px; box-shadow:0 1px 2px rgba(15,23,42,.05); }
  .flatpickr-time input {
    color:#0f172a !important; font-weight:800 !important; font-size:1.05rem !important;
    background:#fff !important; border-radius:8px;
  }
  .flatpickr-time input:hover, .flatpickr-time input:focus { background:#fff !important; }
  .flatpickr-time .flatpickr-time-separator { color:#0f172a !important; font-weight:800 !important; font-size:1.05rem !important; }
  .numInputWrapper:hover { background:#f8fafc !important; }
  .numInputWrapper span { border-color:#e2e8f0 !important; }
  .numInputWrapper span:after { border-color:#94a3b8 !important; }
  .numInputWrapper span.arrowUp:after { border-bottom-color:#475569 !important; }
  .numInputWrapper span.arrowDown:after { border-top-color:#475569 !important; }
  .numInputWrapper span:hover:after { border-bottom-color:#C9A762 !important; border-top-color:#C9A762 !important; }

  /* Input principal */
  #fpFechaPropuesta {
    padding:.95rem 1.1rem !important;
    border:1.5px solid #e2e8f0 !important;
    border-radius:12px !important;
    font-size:.95rem !important;
    font-weight:700 !important;
    color:#0f172a !important;
    background:#fff !important;
    transition:all .15s;
    box-shadow:0 1px 2px rgba(15,23,42,.03);
  }
  #fpFechaPropuesta:hover { border-color:#cbd5e1 !important; }
  #fpFechaPropuesta:focus { border-color:#1c2f48 !important; box-shadow:0 0 0 4px rgba(28,47,72,.08) !important; outline:none !important; }
  #fpFechaPropuesta::placeholder { color:#94a3b8; font-weight:500; }
</style>
<script>
document.addEventListener('DOMContentLoaded', function() {
  if (typeof flatpickr === 'undefined') return;

  const fpOpts = {
    locale: 'es',
    enableTime: true,
    time_24hr: true,
    dateFormat: 'Y-m-d H:i',
    altInput: true,
    altFormat: 'l j \\d\\e F · H:i',
    minuteIncrement: 15,
    defaultHour: 21,
    defaultMinute: 0,
    disableMobile: false,
    position: 'auto center'
  };

  const el = document.getElementById('fpFechaPropuesta');
  if (el) {
    flatpickr(el, Object.assign({}, fpOpts, {
      minDate: el.getAttribute('data-min') || 'today'
    }));
  }

  const elModal = document.getElementById('fpModalFecha');
  if (elModal) {
    flatpickr(elModal, Object.assign({}, fpOpts, {
      minDate: elModal.getAttribute('data-min') || 'today'
    }));
  }
});

function abrirModalAsignar(btn) {
  document.getElementById('modalPartidoId').value = btn.dataset.partidoId;
  document.getElementById('modalPartidoStr').textContent = btn.dataset.partidoStr;
  document.getElementById('modalAsignarFecha').style.display = 'flex';
  document.body.style.overflow = 'hidden';
}
function cerrarModalAsignar(evt) {
  if (evt && evt.target && evt.target.classList && !evt.target.classList.contains('modal-af-overlay') && evt.type === 'click') return;
  document.getElementById('modalAsignarFecha').style.display = 'none';
  document.body.style.overflow = '';
}
</script>

<?php require_once 'includes/footer.php'; ?>
