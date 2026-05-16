<?php
declare(strict_types=1);
require_once __DIR__ . '/db.php';

function epl_h(?string $s): string {
    return htmlspecialchars($s ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function epl_url(string $path = ''): string {
    $base = rtrim(epl_env('APP_URL', '/elitepadelleague'), '/');
    return $base . '/' . ltrim($path, '/');
}

function epl_foto_jugador(?string $foto, string $nombre = ''): string {
    if ($foto && file_exists(dirname(__DIR__) . '/uploads/jugadores/' . $foto)) {
        return epl_url('uploads/jugadores/' . $foto);
    }
    $iniciales = '';
    foreach (explode(' ', $nombre) as $p) {
        if ($p !== '') $iniciales .= strtoupper($p[0]);
        if (strlen($iniciales) >= 2) break;
    }
    // Devuelve URL de avatar con iniciales via DiceBear
    return "https://api.dicebear.com/7.x/initials/svg?seed=" . urlencode($iniciales ?: 'EP') . "&backgroundColor=1C2F48&textColor=C9A762";
}

// -------------------------------------------------------
// Consultas frecuentes
// -------------------------------------------------------

function epl_liga_activa(): ?array {
    $db = epl_db();
    $st = $db->query("SELECT * FROM ligas WHERE estado = 'activa' ORDER BY id DESC LIMIT 1");
    return $st->fetch() ?: null;
}

function epl_clasificacion(int $liga_id): array {
    $db = epl_db();
    $st = $db->prepare("
        SELECT c.*, e.nombre AS equipo_nombre,
               j1.nombre AS j1_nombre, j1.apellido AS j1_apellido, j1.foto AS j1_foto,
               j2.nombre AS j2_nombre, j2.apellido AS j2_apellido, j2.foto AS j2_foto
        FROM clasificacion c
        JOIN equipos e   ON e.id = c.equipo_id
        JOIN jugadores j1 ON j1.id = e.jugador1_id
        JOIN jugadores j2 ON j2.id = e.jugador2_id
        WHERE c.liga_id = ?
        ORDER BY c.puntos DESC, (c.games_favor - c.games_contra) DESC, c.games_favor DESC
    ");
    $st->execute([$liga_id]);
    return $st->fetchAll();
}

function epl_partidos_liga(int $liga_id, string $estado = ''): array {
    $db = epl_db();
    $where = $estado ? "AND p.estado = ?" : '';
    $params = $estado ? [$liga_id, $estado] : [$liga_id];
    $st = $db->prepare("
        SELECT p.*,
               el.nombre AS local_nombre,
               ev.nombre AS visitante_nombre,
               g.nombre  AS ganador_nombre,
               r.nombre  AS recinto_nombre,
               s.nombre  AS recinto_superior_nombre,
               ss.nombre AS recinto_abuelo_nombre
        FROM partidos p
        JOIN equipos el ON el.id = p.equipo_local_id
        JOIN equipos ev ON ev.id = p.equipo_visitante_id
        LEFT JOIN equipos g ON g.id = p.ganador_id
        LEFT JOIN recintos r ON r.id = p.recinto_id
        LEFT JOIN recintos s ON s.id = r.superior_id
        LEFT JOIN recintos ss ON ss.id = s.superior_id
        WHERE p.liga_id = ? $where
        ORDER BY p.jornada ASC, p.fecha_programada ASC, r.nombre ASC
    ");
    $st->execute($params);
    return $st->fetchAll();
}

function epl_partidos_equipo(int $equipo_id): array {
    $db = epl_db();
    $st = $db->prepare("
        SELECT p.*,
               l.nombre  AS liga_nombre,
               el.nombre AS local_nombre,
               ev.nombre AS visitante_nombre,
               r.nombre  AS recinto_nombre,
               s.nombre  AS recinto_superior_nombre,
               ss.nombre AS recinto_abuelo_nombre
        FROM partidos p
        JOIN ligas l    ON l.id = p.liga_id
        JOIN equipos el ON el.id = p.equipo_local_id
        JOIN equipos ev ON ev.id = p.equipo_visitante_id
        LEFT JOIN recintos r  ON r.id  = p.recinto_id
        LEFT JOIN recintos s  ON s.id  = r.superior_id
        LEFT JOIN recintos ss ON ss.id = s.superior_id
        WHERE p.equipo_local_id = ? OR p.equipo_visitante_id = ?
        ORDER BY p.fecha_programada DESC, r.nombre ASC
    ");
    $st->execute([$equipo_id, $equipo_id]);
    return $st->fetchAll();
}

function epl_torneos_del_jugador(int $jugador_id): array {
    $db = epl_db();
    $por_id = [];

    $st = $db->prepare("
        SELECT l.*,
               i.id AS inscripcion_id,
               i.estado AS inscripcion_estado,
               i.pago_estado AS inscripcion_pago_estado,
               i.rol_equipo,
               i.fecha AS inscripcion_fecha,
               e.id AS equipo_id,
               e.nombre AS equipo_nombre
        FROM inscripciones i
        INNER JOIN ligas l ON l.id = i.liga_id
        LEFT JOIN equipos e ON e.id = i.equipo_id
        WHERE i.jugador_id = ? AND i.estado != 'rechazada'
        ORDER BY i.fecha DESC, l.id DESC
    ");
    $st->execute([$jugador_id]);
    foreach ($st->fetchAll() as $row) {
        $por_id[(int)$row['id']] = $row;
    }

    $st2 = $db->prepare("
        SELECT l.*,
               NULL AS inscripcion_id,
               'aprobada' AS inscripcion_estado,
               NULL AS inscripcion_pago_estado,
               NULL AS rol_equipo,
               NULL AS inscripcion_fecha,
               e.id AS equipo_id,
               e.nombre AS equipo_nombre
        FROM ligas l
        JOIN liga_equipos le ON le.liga_id = l.id
        JOIN equipos e ON e.id = le.equipo_id
        WHERE e.jugador1_id = ? OR e.jugador2_id = ?
        ORDER BY l.id DESC
    ");
    $st2->execute([$jugador_id, $jugador_id]);
    foreach ($st2->fetchAll() as $row) {
        $lid = (int)$row['id'];
        if (!isset($por_id[$lid])) {
            $por_id[$lid] = $row;
        } elseif (empty($por_id[$lid]['equipo_id']) && !empty($row['equipo_id'])) {
            $por_id[$lid]['equipo_id']     = $row['equipo_id'];
            $por_id[$lid]['equipo_nombre'] = $row['equipo_nombre'];
        }
    }

    $lista = array_values($por_id);
    usort($lista, static function (array $a, array $b): int {
        $sa = epl_get_liga_status($a);
        $sb = epl_get_liga_status($b);
        $prio = ['activa' => 0, 'inscripcion' => 1, 'proximamente' => 2, 'finalizada' => 3];
        $pa = $prio[$sa] ?? 9;
        $pb = $prio[$sb] ?? 9;
        if ($pa !== $pb) {
            return $pa <=> $pb;
        }
        return (int)$b['id'] <=> (int)$a['id'];
    });
    return $lista;
}

/** Etiqueta de estado para pestañas / listado en Mis Torneos. */
function epl_torneo_estado_badge(array $torneo): array {
    $liga_st = epl_get_liga_status($torneo);
    $insc    = $torneo['inscripcion_estado'] ?? 'aprobada';

    if ($insc === 'pendiente' && in_array($liga_st, ['inscripcion', 'proximamente'], true)) {
        return ['label' => 'EN PROCESO', 'tone' => 'signup'];
    }
    if ($insc === 'pendiente') {
        return ['label' => 'PENDIENTE', 'tone' => 'pending'];
    }
    if ($liga_st === 'finalizada') {
        return ['label' => 'FINALIZADO', 'tone' => 'done'];
    }
    if ($liga_st === 'activa') {
        return ['label' => 'EN JUEGO', 'tone' => 'live'];
    }
    if ($liga_st === 'inscripcion') {
        return ['label' => 'INSCRIPCIÓN', 'tone' => 'signup'];
    }
    if ($liga_st === 'proximamente') {
        return ['label' => 'PRÓXIMO', 'tone' => 'soon'];
    }
    return ['label' => strtoupper($liga_st), 'tone' => 'signup'];
}

/**
 * Mensaje para Mis Torneos cuando el torneo aún no está en juego o la inscripción no está cerrada.
 * La inscripción del equipo se confirma cuando capitán y partner validan su cupo.
 */
function epl_mensaje_torneo_inscripcion(array $liga, int $jugador_id): array {
    $liga_st  = epl_get_liga_status($liga);
    $mi_est   = $liga['inscripcion_estado'] ?? 'pendiente';
    $mi_pago  = $liga['inscripcion_pago_estado'] ?? '';
    $mi_rol   = $liga['rol_equipo'] ?? '';
    $equipo_id = (int)($liga['equipo_id'] ?? 0);

    $partner = null;
    if ($equipo_id > 0) {
        $st = epl_db()->prepare("
            SELECT i.estado, i.pago_estado, i.rol_equipo, j.nombre, j.apellido
            FROM inscripciones i
            INNER JOIN jugadores j ON j.id = i.jugador_id
            WHERE i.equipo_id = ? AND i.jugador_id != ? AND i.estado != 'rechazada'
            LIMIT 1
        ");
        $st->execute([$equipo_id, $jugador_id]);
        $partner = $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    $yo_validado = $mi_est === 'aprobada'
        || in_array($mi_pago, ['pagado', 'exento'], true);
    $partner_validado = $partner
        && (($partner['estado'] ?? '') === 'aprobada'
            || in_array($partner['pago_estado'] ?? '', ['pagado', 'exento'], true));
    $equipo_completo = $yo_validado && $partner_validado;

    $titulo = 'En proceso de inscripción';
    $texto  = '';

    if ($equipo_completo) {
        $titulo = $liga_st === 'finalizada' ? 'Torneo finalizado' : 'Inscripción confirmada';
        if ($liga_st === 'finalizada') {
            $texto = 'Este torneo ya terminó. Podés ver resultados y clasificación en la vista pública.';
        } elseif ($liga_st === 'activa') {
            $texto = 'Tu equipo ya está validado. En breve verás acá tus partidos y la clasificación cuando estén programados.';
        } else {
            $texto = 'Capitán y partner ya validaron el cupo. El torneo aún no comienza; cuando arranque la liga verás tus partidos en esta pantalla.';
        }
        return compact('titulo', 'texto');
    }

    if ($liga_st === 'proximamente') {
        return [
            'titulo' => 'Próximamente',
            'texto'  => 'Este torneo todavía no está abierto. Tu solicitud queda registrada para cuando comience el período de inscripción.',
        ];
    }

    if ($mi_rol === 'partner' && $mi_est === 'pendiente') {
        return [
            'titulo' => 'Confirmá tu cupo',
            'texto'  => 'Tu capitán te invitó a este torneo. Entrá a Inscripciones, revisá tus datos y validá tu cupo para completar el equipo.',
        ];
    }

    $pn = $partner ? trim(($partner['nombre'] ?? '') . ' ' . ($partner['apellido'] ?? '')) : '';

    if ($partner && ($partner['estado'] ?? '') === 'pendiente') {
        if ($yo_validado) {
            $texto = $pn
                ? "Ya validaste tu cupo. Falta que {$pn} confirme y valide su inscripción para habilitar el equipo."
                : 'Ya validaste tu cupo. Falta que tu partner confirme y valide su inscripción.';
        } else {
            $texto = $pn
                ? "El equipo se habilita cuando ambos validan. {$pn} aún debe confirmar; vos también tenés que completar tu inscripción."
                : 'El equipo se habilita cuando ambos jugadores validan su cupo.';
        }
    } elseif ($mi_pago !== 'pagado' && $mi_pago !== 'exento') {
        $texto = $mi_rol === 'capitan'
            ? 'Completá tu inscripción e invitá a tu partner. El cupo del equipo se confirma cuando los dos validan.'
            : 'Completá la validación de tu cupo en Inscripciones para cerrar la inscripción del equipo.';
    } elseif ($mi_rol === 'capitan' && !$partner) {
        $texto = 'Ya validaste tu cupo. Invitá a tu partner desde Inscripciones; el equipo queda listo cuando ambos confirmen.';
    } else {
        $texto = 'Este torneo aún no comienza. Estás en proceso de inscripción: el equipo queda confirmado cuando capitán y partner validan su cupo.';
    }

    if ($liga_st === 'inscripcion') {
        $texto .= ' La liga sigue en período de inscripciones.';
    }

    return compact('titulo', 'texto');
}

function epl_equipo_del_jugador(int $jugador_id, int $liga_id): ?array {
    $db = epl_db();
    $st = $db->prepare("
        SELECT e.* FROM equipos e
        JOIN liga_equipos le ON le.equipo_id = e.id AND le.liga_id = ?
        WHERE e.jugador1_id = ? OR e.jugador2_id = ?
        LIMIT 1
    ");
    $st->execute([$liga_id, $jugador_id, $jugador_id]);
    return $st->fetch() ?: null;
}

// Recalcula clasificación completa para una liga
function epl_recalcular_clasificacion(int $liga_id): void {
    $db = epl_db();

    // Obtener todos los equipos de la liga
    $equipos = $db->prepare("SELECT equipo_id FROM liga_equipos WHERE liga_id = ?");
    $equipos->execute([$liga_id]);
    $ids = $equipos->fetchAll(PDO::FETCH_COLUMN);

    // Inicializar contadores
    $stats = [];
    foreach ($ids as $eid) {
        $stats[$eid] = ['pj'=>0,'pg'=>0,'pp'=>0,'gf'=>0,'gc'=>0,'pts'=>0];
    }

    // Leer partidos jugados
    $partidos = $db->prepare("SELECT * FROM partidos WHERE liga_id = ? AND estado = 'jugado'");
    $partidos->execute([$liga_id]);
    foreach ($partidos->fetchAll() as $p) {
        $lo = $p['equipo_local_id'];
        $vi = $p['equipo_visitante_id'];
        $gf = (int)($p['games_s1_local'] ?? 0) + (int)($p['games_s2_local'] ?? 0) + (int)($p['games_s3_local'] ?? 0);
        $gc = (int)($p['games_s1_visitante'] ?? 0) + (int)($p['games_s2_visitante'] ?? 0) + (int)($p['games_s3_visitante'] ?? 0);

        if (!isset($stats[$lo])) $stats[$lo] = ['pj'=>0,'pg'=>0,'pp'=>0,'gf'=>0,'gc'=>0,'pts'=>0];
        if (!isset($stats[$vi])) $stats[$vi] = ['pj'=>0,'pg'=>0,'pp'=>0,'gf'=>0,'gc'=>0,'pts'=>0];

        $stats[$lo]['pj']++; $stats[$vi]['pj']++;
        $stats[$lo]['gf'] += $gf; $stats[$lo]['gc'] += $gc;
        $stats[$vi]['gf'] += $gc; $stats[$vi]['gc'] += $gf;

        if ($p['ganador_id'] == $lo) {
            $stats[$lo]['pg']++; $stats[$lo]['pts'] += 3;
            $stats[$vi]['pp']++;
        } elseif ($p['ganador_id'] == $vi) {
            $stats[$vi]['pg']++; $stats[$vi]['pts'] += 3;
            $stats[$lo]['pp']++;
        }
    }

    // Upsert clasificacion
    $upsert = $db->prepare("
        INSERT INTO clasificacion (liga_id,equipo_id,pj,pg,pp,games_favor,games_contra,puntos)
        VALUES (?,?,?,?,?,?,?,?)
        ON DUPLICATE KEY UPDATE pj=VALUES(pj),pg=VALUES(pg),pp=VALUES(pp),
          games_favor=VALUES(games_favor),games_contra=VALUES(games_contra),puntos=VALUES(puntos)
    ");
    foreach ($stats as $eid => $s) {
        $upsert->execute([$liga_id, $eid, $s['pj'], $s['pg'], $s['pp'], $s['gf'], $s['gc'], $s['pts']]);
    }

    // Actualizar posiciones
    $ranking = $db->prepare("
        SELECT id FROM clasificacion
        WHERE liga_id = ?
        ORDER BY puntos DESC, (games_favor - games_contra) DESC, games_favor DESC
    ");
    $ranking->execute([$liga_id]);
    $pos = 1;
    $updPos = $db->prepare("UPDATE clasificacion SET posicion = ? WHERE id = ?");
    foreach ($ranking->fetchAll() as $row) {
        $updPos->execute([$pos++, $row['id']]);
    }
}

/**
 * Calcula el estado de una liga basado en sus fechas
 */
function epl_get_liga_status(array $liga): string {
    $hoy = date('Y-m-d');
    
    // Si tiene fechas de inscripción
    if (!empty($liga['inscripcion_inicio']) && !empty($liga['inscripcion_fin'])) {
        if ($hoy < $liga['inscripcion_inicio']) return 'proximamente';
        if ($hoy >= $liga['inscripcion_inicio'] && $hoy <= $liga['inscripcion_fin']) return 'inscripcion';
    }
    
    // Si tiene fechas de liga
    if (!empty($liga['fecha_inicio']) && !empty($liga['fecha_fin'])) {
        if ($hoy > $liga['fecha_fin']) return 'finalizada';
        if ($hoy >= $liga['fecha_inicio'] && $hoy <= $liga['fecha_fin']) return 'activa';
    }

    // Fallback al estado manual si no hay fechas o no encaja
    return $liga['estado'] ?? 'inscripcion';
}

// -------------------------------------------------------
// Inscripciones — invitación partner
// -------------------------------------------------------

function epl_url_inscripciones(): string {
    return epl_url('inscribirse.php');
}

function epl_wsp_invitar_partner_con_cuenta(string $nombre_partner, string $liga_nombre = ''): string {
    $url  = epl_url_inscripciones();
    $liga = $liga_nombre !== '' ? " ({$liga_nombre})" : '';
    $msg  = "Hola {$nombre_partner}, te invité como partner en Elite Padel League{$liga}.\n\n"
          . "Entrá a tu perfil, menú Inscripciones, y aceptá la invitación:\n"
          . $url;
    return urlencode($msg);
}

function epl_wsp_invitar_partner_sin_cuenta(string $url_token): string {
    $msg = "Hola, te invité como partner en Elite Padel League.\n\n"
         . "Registrate y confirmá tu cupo con este link:\n"
         . $url_token;
    return urlencode($msg);
}

function epl_notif_invitacion_partner(int $partner_id, string $cap_nombre, string $liga_nombre): void {
    $titulo  = 'Invitación de partner';
    $mensaje = trim($cap_nombre) . ' te invitó en ' . $liga_nombre . '. Entrá a Inscripciones en tu perfil y aceptá la invitación.';
    $url     = epl_url_inscripciones();
    epl_notif_crear($partner_id, 'inscripcion', $titulo, $mensaje, $url);
    try {
        epl_mail_notificacion_jugador($partner_id, $titulo, $mensaje, $url);
    } catch (Throwable $e) {
        error_log('epl_notif_invitacion_partner mail: ' . $e->getMessage());
    }
}

/** Crea inscripción partner para jugador del sistema; devuelve su token. */
/** Botón temporal para borrar inscripciones en pruebas (Mis Torneos / Inscripciones). */
function epl_mostrar_boton_borrar_inscripcion_prueba(): bool {
    return true;
}

/**
 * Elimina inscripción pendiente del jugador (capitán). Devuelve ['ok'=>bool, 'error'=>?string].
 */
function epl_inscripcion_eliminar_jugador(int $insc_id, int $jugador_id): array {
    if ($insc_id <= 0 || $jugador_id <= 0) {
        return ['ok' => false, 'error' => 'Inscripción no válida.'];
    }
    try {
        epl_ensure_inscripciones_schema();
        $db = epl_db();
        $chk = $db->prepare("SELECT id, token, equipo_id, rol_equipo FROM inscripciones WHERE id=? AND jugador_id=? AND estado='pendiente'");
        $chk->execute([$insc_id, $jugador_id]);
        $row = $chk->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return ['ok' => false, 'error' => 'No se encontró la inscripción o ya no está pendiente.'];
        }
        if (!empty($row['token'])) {
            try {
                $db->prepare('DELETE FROM pagos WHERE token_ref=?')->execute([$row['token']]);
            } catch (Throwable $e) {
                error_log('epl_inscripcion_eliminar pagos cap: ' . $e->getMessage());
            }
        }
        $equipo_id = (int)($row['equipo_id'] ?? 0);
        if ($equipo_id > 0 && ($row['rol_equipo'] ?? '') === 'capitan') {
            $pi = $db->prepare("SELECT token FROM inscripciones WHERE equipo_id=? AND rol_equipo='partner'");
            $pi->execute([$equipo_id]);
            $pt = $pi->fetchColumn();
            if ($pt) {
                try {
                    $db->prepare('DELETE FROM pagos WHERE token_ref=?')->execute([$pt]);
                } catch (Throwable $e) {
                    error_log('epl_inscripcion_eliminar pagos partner: ' . $e->getMessage());
                }
                $db->prepare("DELETE FROM inscripciones WHERE equipo_id=? AND rol_equipo='partner'")->execute([$equipo_id]);
            }
        }
        $db->prepare('DELETE FROM inscripciones WHERE id=?')->execute([$insc_id]);
        return ['ok' => true];
    } catch (Throwable $e) {
        error_log('epl_inscripcion_eliminar: ' . $e->getMessage());
        return ['ok' => false, 'error' => 'No se pudo eliminar. Intenta de nuevo.'];
    }
}

function epl_registrar_partner_sistema(PDO $db, int $partner_id, int $liga_id, int $equipo_id, float $precio): string {
    $chkP = $db->prepare("SELECT token FROM inscripciones WHERE jugador_id=? AND liga_id=? AND estado != 'rechazada'");
    $chkP->execute([$partner_id, $liga_id]);
    $ex = $chkP->fetchColumn();
    if ($ex) {
        return (string)$ex;
    }
    $partner_token = bin2hex(random_bytes(20));
    $db->prepare("INSERT INTO inscripciones (jugador_id, liga_id, equipo_id, rol_equipo, token, estado, pago_estado, pago_monto) VALUES (?,?,?,?,?,?,?,?)")
       ->execute([$partner_id, $liga_id, $equipo_id, 'partner', $partner_token, 'pendiente', 'pendiente', $precio ?: null]);
    return $partner_token;
}

/**
 * Asigna al jugador logueado como partner usando el token del capitán (link WSP / corrección).
 * @return array{ok: bool, error?: string, redirect?: string}
 */
function epl_vincular_partner_por_token_capitan(string $token_capitan, int $jugador_id): array {
    $db = epl_db();
    $st = $db->prepare("
        SELECT i.*, l.nombre AS liga_nombre, l.precio
        FROM inscripciones i
        JOIN ligas l ON l.id = i.liga_id
        WHERE i.token = ? AND i.rol_equipo = 'capitan'
        LIMIT 1
    ");
    $st->execute([$token_capitan]);
    $cap = $st->fetch(PDO::FETCH_ASSOC);
    if (!$cap) {
        return ['ok' => false, 'error' => 'Enlace de invitación no válido.'];
    }
    if ((int)$cap['jugador_id'] === $jugador_id) {
        return ['ok' => false, 'error' => 'No podés unirte a tu propia inscripción como partner.'];
    }

    $liga_id = (int)$cap['liga_id'];
    $precio  = (float)($cap['precio'] ?? 0);
    $cap_id  = (int)$cap['jugador_id'];

    $ya = $db->prepare("SELECT id, rol_equipo FROM inscripciones WHERE jugador_id=? AND liga_id=? AND estado != 'rechazada'");
    $ya->execute([$jugador_id, $liga_id]);
    $ya_row = $ya->fetch(PDO::FETCH_ASSOC);
    if ($ya_row && ($ya_row['rol_equipo'] ?? '') === 'capitan') {
        return ['ok' => false, 'error' => 'Ya tienes una inscripción como capitán en esta liga.'];
    }
    if ($ya_row && ($ya_row['rol_equipo'] ?? '') === 'partner') {
        return ['ok' => true, 'redirect' => epl_url_inscripciones()];
    }

    $equipo_id = (int)($cap['equipo_id'] ?? 0);

    $ocupado = $db->prepare("
        SELECT id FROM inscripciones
        WHERE equipo_id = ? AND rol_equipo = 'partner' AND jugador_id != ? AND estado = 'aprobada'
    ");
    if ($equipo_id > 0) {
        $ocupado->execute([$equipo_id, $jugador_id]);
        if ($ocupado->fetch()) {
            return ['ok' => false, 'error' => 'Este cupo de partner ya fue confirmado por otro jugador.'];
        }

        $db->prepare("
            DELETE FROM inscripciones
            WHERE equipo_id = ? AND rol_equipo = 'partner' AND jugador_id != ? AND estado = 'pendiente'
        ")->execute([$equipo_id, $jugador_id]);

        $db->prepare("UPDATE equipos SET jugador2_id = ? WHERE id = ?")->execute([$jugador_id, $equipo_id]);
    } else {
        $stCap = $db->prepare("SELECT apellido FROM jugadores WHERE id = ?");
        $stCap->execute([$cap_id]);
        $cap_ape = $stCap->fetchColumn() ?: 'Capitán';
        $stMe = $db->prepare("SELECT apellido FROM jugadores WHERE id = ?");
        $stMe->execute([$jugador_id]);
        $me_ape = $stMe->fetchColumn() ?: 'Partner';
        $db->prepare("INSERT INTO equipos (nombre, jugador1_id, jugador2_id) VALUES (?,?,?)")
           ->execute([$cap_ape . ' / ' . $me_ape, $cap_id, $jugador_id]);
        $equipo_id = (int)$db->lastInsertId();
        $db->prepare("UPDATE inscripciones SET equipo_id = ? WHERE id = ?")->execute([$equipo_id, $cap['id']]);
    }

    epl_registrar_partner_sistema($db, $jugador_id, $liga_id, $equipo_id, $precio);

    $cn = $db->prepare("SELECT nombre, apellido FROM jugadores WHERE id=?");
    $cn->execute([$cap_id]);
    $capJ = $cn->fetch(PDO::FETCH_ASSOC) ?: [];
    epl_notif_invitacion_partner(
        $jugador_id,
        trim(($capJ['nombre'] ?? '') . ' ' . ($capJ['apellido'] ?? '')),
        (string)($cap['liga_nombre'] ?? 'la liga')
    );

    return ['ok' => true, 'redirect' => epl_url_inscripciones()];
}

// -------------------------------------------------------
// Notificaciones
// -------------------------------------------------------

function epl_notif_crear(int $jugador_id, string $tipo, string $titulo, string $mensaje, string $url = ''): void {
    try {
        epl_ensure_inscripciones_schema();
        $db = epl_db();
        $st = $db->prepare("INSERT INTO notificaciones (jugador_id, tipo, titulo, mensaje, url) VALUES (?,?,?,?,?)");
        $st->execute([$jugador_id, $tipo, $titulo, $mensaje, $url ?: null]);
    } catch (Throwable $e) {
        error_log('epl_notif_crear: ' . $e->getMessage());
        return;
    }

    try {
        if (!function_exists('epl_mail_notificacion_jugador')) {
            require_once __DIR__ . '/mail.php';
        }
        epl_mail_notificacion_jugador($jugador_id, $titulo, $mensaje, $url);
    } catch (Throwable $e) {
        error_log('epl_notif_crear mail: ' . $e->getMessage());
    }
}

/**
 * Notifica a todos los jugadores de un partido (ambos equipos) y opcionalmente a los admins.
 */
function epl_notif_partido(int $partido_id, string $tipo, string $titulo, string $mensaje, string $url = '', bool $incluir_admins = false, array $excluir_ids = []): void {
    $db = epl_db();

    // Jugadores de los dos equipos
    $st = $db->prepare("
        SELECT DISTINCT j.id
        FROM partidos p
        JOIN equipos el ON el.id = p.equipo_local_id
        JOIN equipos ev ON ev.id = p.equipo_visitante_id
        JOIN jugadores j ON j.id IN (el.jugador1_id, el.jugador2_id, ev.jugador1_id, ev.jugador2_id)
        WHERE p.id = ? AND j.id NOT IN (" . (count($excluir_ids) ? implode(',', array_map('intval', $excluir_ids)) : '0') . ")
    ");
    $st->execute([$partido_id]);
    foreach ($st->fetchAll() as $row) {
        epl_notif_crear((int)$row['id'], $tipo, $titulo, $mensaje, $url);
    }

    // Admins (opconal)
    if ($incluir_admins) {
        $stA = $db->query("SELECT id FROM jugadores WHERE rol='admin' AND estado='activo'");
        foreach ($stA->fetchAll() as $row) {
            if (!in_array((int)$row['id'], $excluir_ids)) {
                epl_notif_crear((int)$row['id'], $tipo, $titulo, $mensaje, $url);
            }
        }
    }
}

function epl_notif_no_leidas(int $jugador_id): int {
    $db = epl_db();
    $st = $db->prepare("SELECT COUNT(*) FROM notificaciones WHERE jugador_id = ? AND leida = 0");
    $st->execute([$jugador_id]);
    return (int) $st->fetchColumn();
}

function epl_notif_listar(int $jugador_id, int $limit = 30): array {
    $db = epl_db();
    $st = $db->prepare("SELECT * FROM notificaciones WHERE jugador_id = ? ORDER BY created_at DESC LIMIT ?");
    $st->execute([$jugador_id, $limit]);
    return $st->fetchAll();
}

function epl_notif_marcar_leida(int $id, int $jugador_id): void {
    $db = epl_db();
    $st = $db->prepare("UPDATE notificaciones SET leida = 1 WHERE id = ? AND jugador_id = ?");
    $st->execute([$id, $jugador_id]);
}

function epl_notif_marcar_todas_leidas(int $jugador_id): void {
    $db = epl_db();
    $st = $db->prepare("UPDATE notificaciones SET leida = 1 WHERE jugador_id = ?");
    $st->execute([$jugador_id]);
}

function epl_notif_icono(string $tipo): string {
    $iconos = [
        'resultado'      => '⚽',
        'reprogramacion' => '📅',
        'suplente'       => '👥',
        'inscripcion'    => '✅',
        'liga'           => '🏆',
        'admin'          => '📢',
    ];
    return $iconos[$tipo] ?? '🔔';
}

// -------------------------------------------------------
// Configuración global (clave/valor)
// -------------------------------------------------------

/** @return array<string, string> */
function &epl_config_cache(): array {
    static $cache = [];
    return $cache;
}

function epl_config_get(string $clave, string $default = ''): string {
    $cache = &epl_config_cache();
    if (!array_key_exists($clave, $cache)) {
        try {
            $st = epl_db()->prepare("SELECT valor FROM configuracion WHERE clave = ?");
            $st->execute([$clave]);
            $cache[$clave] = $st->fetchColumn() ?: '';
        } catch (\Throwable $e) {
            return $default;
        }
    }
    return $cache[$clave] !== '' ? (string)$cache[$clave] : $default;
}

function epl_config_set(string $clave, string $valor): void {
    epl_db()->prepare("INSERT INTO configuracion (clave, valor) VALUES (?,?) ON DUPLICATE KEY UPDATE valor=VALUES(valor)")
             ->execute([$clave, $valor]);
    unset(epl_config_cache()[$clave]);
}

/** Comisión MP típica (%) configurada globalmente para calcular cobro desde neto deseado. */
function epl_mp_comision_porcentaje_defecto(): float {
    $raw = trim(str_replace(',', '.', epl_config_get('mp_comision_porcentaje', '3.49')));
    if ($raw === '' || !is_numeric($raw)) {
        return 3.49;
    }
    $v = (float) $raw;
    return max(0.0, min(99.0, $v));
}

/** Paso en CLP: el cobro se redondea hacia arriba al siguiente múltiplo (ej. 10000 → 155.424 pasa a 160.000). */
function epl_mp_redondeo_escalon_clp(): int {
    $raw = trim(str_replace(',', '.', epl_config_get('mp_redondeo_escalon_clp', '10000')));
    if ($raw === '' || !is_numeric($raw)) {
        return 10000;
    }
    $v = (int) round((float) $raw);

    return max(1, min(1_000_000, $v));
}

/** Bruto CLP para cobrar vía Mercado Pago si el organizador quiere líquido $neto después de la comisión (sobre bruto). Redondeo superior por escalón. */
function epl_precio_bruto_mp_desde_neto(float $neto, float $comisionPct): int {
    if ($neto <= 0 || $comisionPct < 0 || $comisionPct >= 100) {
        return 0;
    }
    $fee = $comisionPct / 100.0;
    $bruto_exacto = $neto / (1.0 - $fee);
    $step         = epl_mp_redondeo_escalon_clp();

    return (int) max($step, ceil($bruto_exacto / $step) * $step);
}

function epl_liga_mp_comision_pct(?array $liga): float {
    if ($liga !== null) {
        $raw = $liga['mp_comision_pct'] ?? null;
        if ($raw !== null && $raw !== '' && is_numeric($raw)) {
            $x = (float) $raw;
            if ($x > 0 && $x < 100) {
                return $x;
            }
        }
    }
    return epl_mp_comision_porcentaje_defecto();
}

/** Liquido estimado después de comisión típica (solo referencia tras redondeo). */
function epl_precio_neto_estimado_desde_bruto(float $bruto, float $comisionPct): float {
    if ($bruto <= 0) {
        return 0;
    }
    return round($bruto * (1 - $comisionPct / 100.0), 2);
}

/**
 * Precio de inscripción desde el formulario admin: modo líquido (tras comisión MP) o precio fijo.
 *
 * @param string|null $err Mensaje si validación falla (by ref).
 * @return array{0:?float,1:?float,2:?float}|null [precio cobrado, precio_neto_deseado|null, mp_comision_pct|null si vacío usa global]
 */
function epl_liga_precio_desde_post(array $post, ?string &$err): ?array {
    $err = '';
    $liquido = !empty($post['precio_usar_liquido_mp']);

    if ($liquido) {
        $neto = isset($post['precio_neto_deseado']) ? (float) $post['precio_neto_deseado'] : 0.0;
        if ($neto <= 0) {
            $err = 'Si activás el cálculo con comisión MP, indica el líquido que querés recibir (mayor que 0).';
            return null;
        }

        $raw = trim(str_replace(',', '.', (string)($post['mp_comision_pct'] ?? '')));
        $pctParaDb = null;
        $pctCalc   = epl_mp_comision_porcentaje_defecto();

        if ($raw !== '') {
            if (!is_numeric($raw)) {
                $err = 'Comisión MP no válida.';
                return null;
            }
            $p = (float) $raw;
            if ($p <= 0 || $p >= 100) {
                $err = 'Comisión MP debe estar entre 0 y 100%.';
                return null;
            }
            $pctParaDb = round($p, 4);
            $pctCalc   = $p;
        }

        $bruto = (float) epl_precio_bruto_mp_desde_neto($neto, $pctCalc);
        return [$bruto, $neto, $pctParaDb];
    }

    $manual = (float) ($post['precio'] ?? 0);
    return [$manual > 0 ? $manual : null, null, null];
}

function epl_ensure_ligas_columnas_mp_precio(): void {
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    try {
        $db = epl_db();

        // Columnas nuevas que podrían no existir en instancias antiguas.
        // Clave => [tipo SQL, posición AFTER col (o null para al final)]
        $nuevas = [
            'precio_neto_deseado' => ["DECIMAL(12,2) NULL DEFAULT NULL",           'precio'],
            'mp_comision_pct'     => ["DECIMAL(8,4) NULL DEFAULT NULL",             'precio_neto_deseado'],
            'sexo'                => ["ENUM('masculino','femenino','mixto') DEFAULT 'masculino'", 'categoria'],
            'url_maps'            => ["VARCHAR(500) DEFAULT NULL",                  'sede'],
            'recinto_id'          => ["INT UNSIGNED NULL DEFAULT NULL",             null],
            'puntos_1'            => ["INT UNSIGNED NOT NULL DEFAULT 100",          null],
            'puntos_2'            => ["INT UNSIGNED NOT NULL DEFAULT 70",           'puntos_1'],
            'puntos_3'            => ["INT UNSIGNED NOT NULL DEFAULT 50",           'puntos_2'],
            'puntos_4'            => ["INT UNSIGNED NOT NULL DEFAULT 30",           'puntos_3'],
            'puntos_grupos'       => ["INT UNSIGNED NOT NULL DEFAULT 10",           'puntos_4'],
        ];

        // Obtener columnas existentes una sola vez
        $existing = [];
        foreach ($db->query("SHOW COLUMNS FROM ligas")->fetchAll() as $r) {
            $existing[] = strtolower($r['Field']);
        }

        foreach ($nuevas as $col => [$tipoDef, $after]) {
            if (in_array(strtolower($col), $existing)) {
                continue;
            }
            $afterSql = $after ? " AFTER `$after`" : '';
            $db->exec("ALTER TABLE ligas ADD COLUMN `$col` $tipoDef$afterSql");
        }
    } catch (Throwable $e) {
        // sin permisos ALTER: correr migration/update_v2.php manualmente
    }
}

// -------------------------------------------------------
// Esquema mínimo inscripciones / pagos / notificaciones
// -------------------------------------------------------

function epl_ensure_inscripciones_schema(): void {
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    try {
        $db = epl_db();

        $inscCols = [];
        foreach ($db->query('SHOW COLUMNS FROM inscripciones')->fetchAll() as $r) {
            $inscCols[] = strtolower($r['Field']);
        }
        $addInsc = static function (string $col, string $def) use ($db, &$inscCols): void {
            if (in_array(strtolower($col), $inscCols, true)) {
                return;
            }
            $db->exec("ALTER TABLE inscripciones ADD COLUMN `$col` $def");
            $inscCols[] = strtolower($col);
        };
        $addInsc('rol_equipo', "ENUM('capitan','partner') NOT NULL DEFAULT 'capitan' AFTER `equipo_id`");
        $addInsc('token', "VARCHAR(64) DEFAULT NULL AFTER `rol_equipo`");
        $addInsc('pago_monto', "DECIMAL(10,2) DEFAULT NULL AFTER `pago_estado`");
        $addInsc('pago_ref', "VARCHAR(200) DEFAULT NULL AFTER `pago_monto`");

        $db->exec("CREATE TABLE IF NOT EXISTS `pagos` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `liga_id` INT UNSIGNED DEFAULT NULL,
            `jugador_id` INT UNSIGNED DEFAULT NULL,
            `inscripcion_id` INT UNSIGNED DEFAULT NULL,
            `concepto` VARCHAR(300) NOT NULL DEFAULT '',
            `rol` VARCHAR(30) NOT NULL DEFAULT 'manual',
            `monto` DECIMAL(10,2) NOT NULL DEFAULT 0,
            `estado` ENUM('pendiente','completado','rechazado') NOT NULL DEFAULT 'pendiente',
            `metodo` VARCHAR(80) DEFAULT NULL,
            `mp_preference_id` VARCHAR(120) DEFAULT NULL,
            `mp_payment_id` VARCHAR(120) DEFAULT NULL,
            `token_ref` VARCHAR(64) DEFAULT NULL,
            `equipo_token` VARCHAR(64) DEFAULT NULL,
            `notas` TEXT DEFAULT NULL,
            `fecha` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `idx_token_ref` (`token_ref`),
            KEY `idx_inscripcion` (`inscripcion_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $db->exec("CREATE TABLE IF NOT EXISTS `notificaciones` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `jugador_id` INT UNSIGNED NOT NULL,
            `tipo` VARCHAR(50) NOT NULL,
            `titulo` VARCHAR(150) NOT NULL,
            `mensaje` TEXT NOT NULL,
            `url` VARCHAR(255) DEFAULT NULL,
            `leida` TINYINT(1) NOT NULL DEFAULT 0,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `idx_jugador_leida` (`jugador_id`, `leida`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $jugCols = [];
        foreach ($db->query('SHOW COLUMNS FROM jugadores')->fetchAll() as $r) {
            $jugCols[] = strtolower($r['Field']);
        }
        if (!in_array('alias', $jugCols, true)) {
            $db->exec("ALTER TABLE jugadores ADD COLUMN `alias` VARCHAR(100) DEFAULT NULL AFTER `apellido`");
        }
    } catch (Throwable $e) {
        error_log('epl_ensure_inscripciones_schema: ' . $e->getMessage());
    }
}

// -------------------------------------------------------
// ERP Financiero — pagos
// -------------------------------------------------------

function epl_pago_crear(array $data): int {
    epl_ensure_inscripciones_schema();
    $db = epl_db();
    $st = $db->prepare("
        INSERT INTO pagos (liga_id, jugador_id, inscripcion_id, concepto, rol, monto, estado, metodo, mp_preference_id, token_ref, equipo_token, notas)
        VALUES (:liga_id, :jugador_id, :inscripcion_id, :concepto, :rol, :monto, :estado, :metodo, :mp_preference_id, :token_ref, :equipo_token, :notas)
    ");
    $st->execute([
        ':liga_id'          => $data['liga_id']          ?? null,
        ':jugador_id'       => $data['jugador_id']       ?? null,
        ':inscripcion_id'   => $data['inscripcion_id']   ?? null,
        ':concepto'         => $data['concepto']         ?? '',
        ':rol'              => $data['rol']              ?? 'manual',
        ':monto'            => $data['monto']            ?? 0,
        ':estado'           => $data['estado']           ?? 'pendiente',
        ':metodo'           => $data['metodo']           ?? null,
        ':mp_preference_id' => $data['mp_preference_id'] ?? null,
        ':token_ref'        => $data['token_ref']        ?? null,
        ':equipo_token'     => $data['equipo_token']     ?? null,
        ':notas'            => $data['notas']            ?? null,
    ]);
    return (int)$db->lastInsertId();
}

function epl_pago_completar(string $token_ref, string $mp_payment_id): bool {
    $st = epl_db()->prepare("UPDATE pagos SET estado='completado', mp_payment_id=? WHERE token_ref=? AND estado='pendiente'");
    $st->execute([$mp_payment_id, $token_ref]);
    return $st->rowCount() > 0;
}

function epl_activar_equipo_en_liga(int $equipo_id, int $liga_id): void {
    $db = epl_db();
    $db->prepare("INSERT IGNORE INTO liga_equipos (liga_id, equipo_id) VALUES (?,?)")->execute([$liga_id, $equipo_id]);
    $db->prepare("INSERT IGNORE INTO clasificacion (liga_id, equipo_id) VALUES (?,?)")->execute([$liga_id, $equipo_id]);
}

function epl_pagos_listar(int $liga_id = 0, string $estado = ''): array {
    $db = epl_db();
    $where = [];
    $params = [];
    if ($liga_id) { $where[] = 'p.liga_id = ?'; $params[] = $liga_id; }
    if ($estado)  { $where[] = 'p.estado = ?';  $params[] = $estado; }
    $sql = "
        SELECT p.*,
               l.nombre AS liga_nombre,
               j.nombre AS jugador_nombre_real, j.apellido AS jugador_apellido
        FROM pagos p
        LEFT JOIN ligas l    ON l.id = p.liga_id
        LEFT JOIN jugadores j ON j.id = p.jugador_id
        " . ($where ? 'WHERE ' . implode(' AND ', $where) : '') . "
        ORDER BY p.fecha DESC
    ";
    $st = $db->prepare($sql);
    $st->execute($params);
    return $st->fetchAll();
}

