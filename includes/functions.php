<?php
declare(strict_types=1);
require_once __DIR__ . '/db.php';

// ── reCAPTCHA v3 ─────────────────────────────────────────────────────────────
/**
 * Verifica el token de reCAPTCHA v3 contra Google.
 * Retorna true si el puntaje es aceptable (sobre el umbral), false si es bot.
 * Si no hay clave configurada, retorna true (no bloquea, modo dev).
 */
function epl_recaptcha_verificar(string $token, string $action_esperada = ''): bool {
    $secret = epl_env('RECAPTCHA_SECRET_KEY', '');
    if (!$secret) return true; // sin clave configurada, no validar
    if (!$token) return false;

    $threshold = (float) epl_env('RECAPTCHA_THRESHOLD', '0.5');

    $url = 'https://www.google.com/recaptcha/api/siteverify';
    $payload = http_build_query([
        'secret'   => $secret,
        'response' => $token,
        'remoteip' => $_SERVER['REMOTE_ADDR'] ?? '',
    ]);

    $ctx = stream_context_create([
        'http' => [
            'method'        => 'POST',
            'header'        => 'Content-Type: application/x-www-form-urlencoded',
            'content'       => $payload,
            'timeout'       => 5,
            'ignore_errors' => true,
        ],
    ]);
    $resp = @file_get_contents($url, false, $ctx);
    if ($resp === false) {
        error_log('[recaptcha] sin respuesta de Google');
        return true; // no bloquear si Google está caído
    }
    $data = json_decode($resp, true);
    if (!is_array($data) || empty($data['success'])) {
        error_log('[recaptcha] fail: ' . substr($resp, 0, 200));
        return false;
    }
    $score = (float)($data['score'] ?? 0);
    if ($action_esperada && ($data['action'] ?? '') !== $action_esperada) {
        error_log('[recaptcha] action mismatch: ' . ($data['action'] ?? '?'));
        return false;
    }
    return $score >= $threshold;
}

/** Imprime el <script> de reCAPTCHA v3 (sitio_key) si está configurada. */
function epl_recaptcha_script(): string {
    $site_key = epl_env('RECAPTCHA_SITE_KEY', '');
    if (!$site_key) return '';
    return '<script src="https://www.google.com/recaptcha/api.js?render='
         . htmlspecialchars($site_key, ENT_QUOTES) . '"></script>';
}

/** Devuelve el site key (para usar en JS inline). */
function epl_recaptcha_site_key(): string {
    return epl_env('RECAPTCHA_SITE_KEY', '');
}

// ── Flash messages (PRG pattern) ─────────────────────────────────────────────
/**
 * Guarda un mensaje flash en sesión y redirige (Post → Redirect → Get).
 * Usar siempre después de un POST exitoso.
 */
function epl_redirect_ok(string $msg, string $url = ''): void {
    if (session_status() === PHP_SESSION_NONE) session_start();
    $_SESSION['_epl_flash'] = ['tipo' => 'ok', 'msg' => $msg];
    $dest = $url ?: strtok($_SERVER['REQUEST_URI'] ?? '/', '?');
    header('Location: ' . $dest);
    exit;
}
function epl_redirect_error(string $msg, string $url = ''): void {
    if (session_status() === PHP_SESSION_NONE) session_start();
    $_SESSION['_epl_flash'] = ['tipo' => 'error', 'msg' => $msg];
    $dest = $url ?: strtok($_SERVER['REQUEST_URI'] ?? '/', '?');
    header('Location: ' . $dest);
    exit;
}
/** Recupera y borra el flash de sesión. Retorna ['tipo','msg'] o null. */
function epl_flash_get(): ?array {
    if (session_status() === PHP_SESSION_NONE) session_start();
    $f = $_SESSION['_epl_flash'] ?? null;
    unset($_SESSION['_epl_flash']);
    return $f;
}
// ─────────────────────────────────────────────────────────────────────────────

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
    // Iniciales (hasta 2 letras, soporte UTF-8)
    $iniciales = '';
    foreach (explode(' ', $nombre) as $parte) {
        if ($parte !== '') $iniciales .= mb_strtoupper(mb_substr($parte, 0, 1, 'UTF-8'), 'UTF-8');
        if (mb_strlen($iniciales, 'UTF-8') >= 2) break;
    }
    if ($iniciales === '') $iniciales = 'EP';

    // SVG inline — sin dependencias externas, se cachea en el browser
    $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="100" height="100" viewBox="0 0 100 100">'
         . '<rect width="100" height="100" rx="50" fill="#1C2F48"/>'
         . '<text x="50" y="50" dy=".36em" text-anchor="middle" '
         . 'font-family="Arial,Helvetica,sans-serif" font-size="36" font-weight="700" fill="#C9A762">'
         . htmlspecialchars($iniciales, ENT_XML1, 'UTF-8')
         . '</text></svg>';

    return 'data:image/svg+xml;base64,' . base64_encode($svg);
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

// ── Rol "Club": esquema, ligas asignadas y partidos ──────
function epl_ensure_club_schema(): void {
    static $done = false;
    if ($done) return;
    $done = true;
    try {
        $db = epl_db();
        // Ampliar ENUM de rol con 'club' (solo si falta)
        $col = $db->query("SHOW COLUMNS FROM jugadores LIKE 'rol'")->fetch(PDO::FETCH_ASSOC);
        if ($col && stripos($col['Type'], "'club'") === false) {
            $db->exec("ALTER TABLE jugadores MODIFY rol ENUM('jugador','admin','club') NOT NULL DEFAULT 'jugador'");
        }
        // Tabla de asignación club ↔ ligas
        $db->exec("CREATE TABLE IF NOT EXISTS club_ligas (
            club_id INT UNSIGNED NOT NULL,
            liga_id INT UNSIGNED NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (club_id, liga_id),
            KEY idx_liga (liga_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    } catch (Throwable $e) {
        error_log('epl_ensure_club_schema: ' . $e->getMessage());
    }
}

/** @return int[] ids de ligas asignadas al club */
function epl_club_ligas(int $club_id): array {
    epl_ensure_club_schema();
    $st = epl_db()->prepare("SELECT liga_id FROM club_ligas WHERE club_id=? ORDER BY liga_id DESC");
    $st->execute([$club_id]);
    return array_map('intval', array_column($st->fetchAll(), 'liga_id'));
}

function epl_club_puede_liga(int $club_id, int $liga_id): bool {
    return in_array($liga_id, epl_club_ligas($club_id), true);
}

/** Partidos de un conjunto de ligas, con equipos, recinto (jerarquía) y los 4 jugadores. */
function epl_partidos_club(array $liga_ids, ?string $desde = null, ?string $hasta = null): array {
    $liga_ids = array_values(array_unique(array_map('intval', $liga_ids)));
    if (!$liga_ids) return [];
    $in = implode(',', array_fill(0, count($liga_ids), '?'));
    $params = $liga_ids;
    $extra = '';
    if ($desde) { $extra .= " AND p.fecha_programada >= ?"; $params[] = $desde; }
    if ($hasta) { $extra .= " AND p.fecha_programada <= ?"; $params[] = $hasta; }
    $st = epl_db()->prepare("
        SELECT p.*, l.nombre AS liga_nombre,
               el.nombre AS local_nombre, ev.nombre AS visitante_nombre,
               r.nombre AS recinto_nombre, s.nombre AS recinto_superior_nombre, ss.nombre AS recinto_abuelo_nombre,
               jl1.nombre AS jl1_n, jl1.apellido AS jl1_a, jl1.telefono AS jl1_t,
               jl2.nombre AS jl2_n, jl2.apellido AS jl2_a, jl2.telefono AS jl2_t,
               jv1.nombre AS jv1_n, jv1.apellido AS jv1_a, jv1.telefono AS jv1_t,
               jv2.nombre AS jv2_n, jv2.apellido AS jv2_a, jv2.telefono AS jv2_t
        FROM partidos p
        JOIN ligas l ON l.id = p.liga_id
        JOIN equipos el ON el.id = p.equipo_local_id
        JOIN equipos ev ON ev.id = p.equipo_visitante_id
        LEFT JOIN jugadores jl1 ON jl1.id = el.jugador1_id
        LEFT JOIN jugadores jl2 ON jl2.id = el.jugador2_id
        LEFT JOIN jugadores jv1 ON jv1.id = ev.jugador1_id
        LEFT JOIN jugadores jv2 ON jv2.id = ev.jugador2_id
        LEFT JOIN recintos r  ON r.id  = p.recinto_id
        LEFT JOIN recintos s  ON s.id  = r.superior_id
        LEFT JOIN recintos ss ON ss.id = s.superior_id
        WHERE p.liga_id IN ($in) $extra
        ORDER BY p.fecha_programada ASC, p.jornada ASC, p.id ASC
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
            $texto = 'Este torneo ya terminó. Puedes ver resultados y clasificación en la vista pública.';
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
           ->execute([$cap_ape . ' - ' . $me_ape, $cap_id, $jugador_id]);
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

function epl_notif_crear(int $jugador_id, string $tipo, string $titulo, string $mensaje, string $url = '', bool $skip_email = false): void {
    try {
        epl_ensure_inscripciones_schema();
        $db = epl_db();

        // Auto-override URL for rescheduling
        if ($tipo === 'reprogramacion' || str_contains($url, 'reprogramar.php') || str_contains($url, 'mis_partidos.php') || (str_contains($url, 'mis_torneos.php') && (str_contains(strtolower($titulo), 'reprogramaci') || str_contains(strtolower($mensaje), 'reprogramaci')))) {
            $stRol = $db->prepare("SELECT rol FROM jugadores WHERE id = ?");
            $stRol->execute([$jugador_id]);
            $recip_rol = $stRol->fetchColumn();
            if ($recip_rol === 'admin') {
                $url = epl_url('admin/dashboard_repro.php?tab=solicitudes');
            } else {
                $url = epl_url('reprogramar.php#mis-reprogramaciones');
            }
        }

        // ── Dedup: evitar notif idéntica al mismo jugador en los últimos 10 minutos ──
        // Previene que múltiples ediciones consecutivas envíen N notificaciones iguales
        $stDup = $db->prepare("
            SELECT id FROM notificaciones
            WHERE jugador_id = ?
              AND tipo       = ?
              AND titulo     = ?
              AND mensaje    = ?
              AND created_at >= (NOW() - INTERVAL 10 MINUTE)
            LIMIT 1
        ");
        $stDup->execute([$jugador_id, $tipo, $titulo, $mensaje]);
        if ($stDup->fetchColumn()) {
            // Ya hay una notif idéntica reciente — no duplicar ni reenviar push/email
            return;
        }

        $st = $db->prepare("INSERT INTO notificaciones (jugador_id, tipo, titulo, mensaje, url) VALUES (?,?,?,?,?)");
        $st->execute([$jugador_id, $tipo, $titulo, $mensaje, $url ?: null]);
    } catch (Throwable $e) {
        error_log('epl_notif_crear: ' . $e->getMessage());
        return;
    }

    // Push notification (OneSignal)
    try {
        if (!function_exists('epl_push_notificar')) {
            require_once __DIR__ . '/onesignal.php';
        }
        epl_push_notificar($jugador_id, $titulo, $mensaje, $url);
    } catch (Throwable $e) {
        error_log('epl_notif_crear push: ' . $e->getMessage());
    }

    if (!$skip_email) {
        try {
            if (!function_exists('epl_mail_notificacion_jugador')) {
                require_once __DIR__ . '/mail.php';
            }
            epl_mail_notificacion_jugador($jugador_id, $titulo, $mensaje, $url);
        } catch (Throwable $e) {
            error_log('epl_notif_crear mail: ' . $e->getMessage());
        }
    }
}

/**
 * Notifica a todos los jugadores de un partido (ambos equipos) y opcionalmente a los admins.
 */
function epl_notif_partido(int $partido_id, string $tipo, string $titulo, string $mensaje, string $url = '', bool $incluir_admins = false, array $excluir_ids = [], bool $skip_email = false): void {
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
        epl_notif_crear((int)$row['id'], $tipo, $titulo, $mensaje, $url, $skip_email);
    }

    // Admins (opconal)
    if ($incluir_admins) {
        $stA = $db->query("SELECT id FROM jugadores WHERE rol='admin' AND estado='activo'");
        foreach ($stA->fetchAll() as $row) {
            if (!in_array((int)$row['id'], $excluir_ids)) {
                epl_notif_crear((int)$row['id'], $tipo, $titulo, $mensaje, $url, $skip_email);
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

function epl_notif_listar(int $jugador_id, int $limit = 100, string $tipo = ''): array {
    $db = epl_db();
    if ($tipo) {
        $st = $db->prepare("SELECT * FROM notificaciones WHERE jugador_id = ? AND tipo = ? ORDER BY created_at DESC LIMIT ?");
        $st->execute([$jugador_id, $tipo, $limit]);
    } else {
        $st = $db->prepare("SELECT * FROM notificaciones WHERE jugador_id = ? ORDER BY created_at DESC LIMIT ?");
        $st->execute([$jugador_id, $limit]);
    }
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
        'disputa'        => '⚠️',
        'reprogramacion' => '📅',
        'suplente'       => '👥',
        'inscripcion'    => '✅',
        'liga'           => '🏆',
        'admin'          => '📢',
        'admin_alerta'   => '🕐',
        'partido'        => '🎾',
        'mensaje'        => '💬',
        'recordatorio'   => '⏰',
        'anuncio'        => '📣',
    ];
    return $iconos[$tipo] ?? '🔔';
}

function epl_notif_tipo_label(string $tipo): string {
    $labels = [
        'resultado'      => 'Resultado',
        'disputa'        => 'Disputa',
        'reprogramacion' => 'Reprogramación',
        'inscripcion'    => 'Inscripción',
        'liga'           => 'Liga',
        'admin'          => 'Comunicado',
        'admin_alerta'   => 'Alerta admin',
        'partido'        => 'Partido',
        'mensaje'        => 'Mensaje',
        'recordatorio'   => 'Recordatorio',
        'anuncio'        => 'Anuncio',
        'suplente'       => 'Equipo',
    ];
    return $labels[$tipo] ?? ucfirst($tipo);
}

// -------------------------------------------------------
// Tiempos de notificaciones (configurables desde admin/alertas.php)
// -------------------------------------------------------

/** Valores por defecto de los tiempos de alertas. */
function epl_notif_tiempos_default(): array {
    return [
        'recordatorio_horas'       => '72,24,3', // horas antes del partido (coma-separadas)
        'recordatorio_tol_min'     => 30,        // tolerancia en minutos de la ventana del cron
        'atrasado_horas'           => 12,        // alerta a admins si pasa N horas sin resultado
        'reprog_lock_horas'        => 24,        // no se puede reprogramar con menos de N horas
    ];
}

/** Devuelve los tiempos actuales (config + defaults). */
function epl_notif_tiempos(): array {
    $d = epl_notif_tiempos_default();
    return [
        'recordatorio_horas'   => epl_config_get('notif_recordatorio_horas',   (string)$d['recordatorio_horas']),
        'recordatorio_tol_min' => max(5, (int)epl_config_get('notif_recordatorio_tol_min', (string)$d['recordatorio_tol_min'])),
        'atrasado_horas'       => max(1, (int)epl_config_get('notif_atrasado_horas',       (string)$d['atrasado_horas'])),
        'reprog_lock_horas'    => max(0, (int)epl_config_get('notif_reprog_lock_horas',    (string)$d['reprog_lock_horas'])),
    ];
}

/**
 * Ventanas de recordatorio para el cron: [[horas, tolerancia_min, etiqueta], ...]
 * Ordenadas de mayor a menor.
 */
function epl_notif_recordatorio_ventanas(): array {
    $t   = epl_notif_tiempos();
    $tol = (int)$t['recordatorio_tol_min'];
    $horas = array_filter(array_map('intval', explode(',', $t['recordatorio_horas'])), fn($h) => $h > 0);
    $horas = array_values(array_unique($horas));
    rsort($horas);
    $out = [];
    foreach ($horas as $h) {
        if ($h > 24 && $h % 24 === 0) {
            $dias = intdiv($h, 24);
            $lbl  = $dias === 1 ? '1 día' : "{$dias} días";
        } else {
            $lbl = $h === 1 ? '1 hora' : "{$h} horas";
        }
        $out[] = [$h, $tol, $lbl];
    }
    if (empty($out)) {
        $out = [[24, $tol, '24 horas'], [12, $tol, '12 horas'], [3, $tol, '3 horas']];
    }
    return $out;
}

/** Horas mínimas de anticipación para reprogramar (lock). */
function epl_notif_reprog_lock_horas(): int {
    return (int)epl_notif_tiempos()['reprog_lock_horas'];
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
// Esquema disputas de resultados
// -------------------------------------------------------

function epl_ensure_disputas_schema(): void {
    static $done = false;
    if ($done) return;
    $done = true;
    try {
        $db = epl_db();

        // Tabla partido_disputas
        $db->exec("CREATE TABLE IF NOT EXISTS `partido_disputas` (
            `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `partido_id`  INT UNSIGNED NOT NULL,
            `jugador_id`  INT UNSIGNED NOT NULL,
            `motivo`      TEXT NOT NULL,
            `estado`      ENUM('pendiente','resuelta') NOT NULL DEFAULT 'pendiente',
            `notas_admin` TEXT DEFAULT NULL,
            `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `expires_at`  DATETIME NOT NULL,
            PRIMARY KEY (`id`),
            KEY `idx_pd_partido` (`partido_id`),
            KEY `idx_pd_jugador` (`jugador_id`),
            KEY `idx_pd_estado`  (`estado`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // Columna resultado_ingresado_at en partidos (marca cuándo se cargó el resultado)
        $cols = array_column($db->query('SHOW COLUMNS FROM partidos')->fetchAll(), 'Field');
        if (!in_array('resultado_ingresado_at', $cols, true)) {
            $db->exec("ALTER TABLE partidos ADD COLUMN `resultado_ingresado_at` DATETIME NULL AFTER `fecha_jugado`");
        }
    } catch (Throwable $e) {
        error_log('epl_ensure_disputas_schema: ' . $e->getMessage());
    }
}

/**
 * Asegura columnas fecha_original, recinto_original_id y flujo de baja de cancha
 * en la tabla partidos. Sirve para registrar la fecha/cancha PREVIA cuando un
 * partido se reprograma y trackear el estado de la baja de la reserva.
 */
function epl_ensure_partidos_columnas_originales(): void {
    static $done = false;
    if ($done) return;
    $done = true;
    try {
        $db = epl_db();
        $cols = array_column($db->query('SHOW COLUMNS FROM partidos')->fetchAll(), 'Field');
        if (!in_array('fecha_original', $cols, true)) {
            $db->exec("ALTER TABLE partidos ADD COLUMN `fecha_original` DATETIME NULL AFTER `fecha_programada`");
        }
        if (!in_array('recinto_original_id', $cols, true)) {
            $db->exec("ALTER TABLE partidos ADD COLUMN `recinto_original_id` INT UNSIGNED NULL AFTER `recinto_id`");
        }
        // Flujo de baja de cancha vía WhatsApp
        if (!in_array('baja_solicitada_at', $cols, true)) {
            $db->exec("ALTER TABLE partidos ADD COLUMN `baja_solicitada_at` DATETIME NULL AFTER `recinto_original_id`");
        }
        if (!in_array('baja_confirmada_at', $cols, true)) {
            $db->exec("ALTER TABLE partidos ADD COLUMN `baja_confirmada_at` DATETIME NULL AFTER `baja_solicitada_at`");
        }
        if (!in_array('baja_confirmada_por', $cols, true)) {
            $db->exec("ALTER TABLE partidos ADD COLUMN `baja_confirmada_por` VARCHAR(120) NULL AFTER `baja_confirmada_at`");
        }
        if (!in_array('baja_token', $cols, true)) {
            $db->exec("ALTER TABLE partidos ADD COLUMN `baja_token` VARCHAR(40) NULL UNIQUE AFTER `baja_confirmada_por`");
        }
        // Flujo de confirmación de cancha por el club vía WhatsApp
        if (!in_array('cancha_token', $cols, true)) {
            $db->exec("ALTER TABLE partidos ADD COLUMN `cancha_token` VARCHAR(40) NULL UNIQUE AFTER `baja_token`");
        }
        if (!in_array('cancha_solicitada_at', $cols, true)) {
            $db->exec("ALTER TABLE partidos ADD COLUMN `cancha_solicitada_at` DATETIME NULL AFTER `cancha_token`");
        }
        if (!in_array('cancha_confirmada_at', $cols, true)) {
            $db->exec("ALTER TABLE partidos ADD COLUMN `cancha_confirmada_at` DATETIME NULL AFTER `cancha_solicitada_at`");
        }
        if (!in_array('cancha_confirmada_por', $cols, true)) {
            $db->exec("ALTER TABLE partidos ADD COLUMN `cancha_confirmada_por` VARCHAR(120) NULL AFTER `cancha_confirmada_at`");
        }
    } catch (Throwable $e) {
        error_log('epl_ensure_partidos_columnas_originales: ' . $e->getMessage());
    }
}

/**
 * Asegura columnas de contactos (3 teléfonos por club) en la tabla recintos.
 */
function epl_ensure_recintos_contactos(): void {
    static $done = false;
    if ($done) return;
    $done = true;
    try {
        $db = epl_db();
        $cols = array_column($db->query('SHOW COLUMNS FROM recintos')->fetchAll(), 'Field');
        for ($i = 1; $i <= 3; $i++) {
            if (!in_array("contacto{$i}_nombre", $cols, true)) {
                $db->exec("ALTER TABLE recintos ADD COLUMN `contacto{$i}_nombre` VARCHAR(80) NULL");
            }
            if (!in_array("contacto{$i}_telefono", $cols, true)) {
                $db->exec("ALTER TABLE recintos ADD COLUMN `contacto{$i}_telefono` VARCHAR(30) NULL");
            }
        }
    } catch (Throwable $e) {
        error_log('epl_ensure_recintos_contactos: ' . $e->getMessage());
    }
}

/**
 * Devuelve los contactos (nombre + teléfono) de un recinto.
 * Si el recinto en cuestión no tiene contactos cargados, sube por la jerarquía
 * (superior, abuelo, etc.) hasta encontrar uno que tenga.
 * Devuelve además el ID del recinto cuyos contactos se usaron, por si hay que mostrarlo.
 *
 * Retorno: [
 *   'recinto_id'   => int,        // de qué nivel jerárquico se sacaron
 *   'recinto_nombre' => string,
 *   'contactos'    => [ ['nombre'=>'..', 'telefono'=>'..'], ... ]
 * ]
 */
function epl_recinto_contactos_jerarquico(int $recinto_id): array {
    epl_ensure_recintos_contactos();
    $db = epl_db();
    $visitados = [];
    $current   = $recinto_id;
    $out = ['recinto_id' => null, 'recinto_nombre' => null, 'contactos' => []];

    while ($current && !isset($visitados[$current])) {
        $visitados[$current] = true;
        $st = $db->prepare("SELECT id, nombre, superior_id, contacto1_nombre, contacto1_telefono, contacto2_nombre, contacto2_telefono, contacto3_nombre, contacto3_telefono FROM recintos WHERE id = ?");
        $st->execute([$current]);
        $r = $st->fetch(PDO::FETCH_ASSOC);
        if (!$r) break;

        $contactos = [];
        for ($i = 1; $i <= 3; $i++) {
            if (!empty($r["contacto{$i}_telefono"])) {
                $contactos[] = ['nombre' => $r["contacto{$i}_nombre"] ?? '', 'telefono' => $r["contacto{$i}_telefono"]];
            }
        }
        if (!empty($contactos)) {
            $out['recinto_id']     = (int)$r['id'];
            $out['recinto_nombre'] = $r['nombre'];
            $out['contactos']      = $contactos;
            return $out;
        }
        $current = $r['superior_id'] ? (int)$r['superior_id'] : null;
    }
    return $out;
}

/**
 * Devuelve los contactos del recinto/club más usado en una liga.
 * Útil cuando un partido no tiene recinto asignado (reprogramado sin fecha)
 * y aún así se necesita contactar al club habitual.
 *
 * @return array Con la misma forma que epl_recinto_contactos_jerarquico
 */
function epl_recintos_recomendados_liga(int $liga_id): array {
    if (!$liga_id) return ['contactos' => [], 'recinto_id' => null, 'recinto_nombre' => null];
    epl_ensure_recintos_contactos();
    $db = epl_db();

    // Cuenta recintos raíz (clubes) más usados en la liga, a través de la jerarquía
    // Recorre fecha_original y recinto_id de los partidos jugados/reprogramados
    $st = $db->prepare("
        SELECT COALESCE(p.recinto_id, p.recinto_original_id) AS rid, COUNT(*) AS cnt
        FROM partidos p
        WHERE p.liga_id = ?
          AND COALESCE(p.recinto_id, p.recinto_original_id) IS NOT NULL
        GROUP BY rid
        ORDER BY cnt DESC
    ");
    $st->execute([$liga_id]);
    $candidatos = $st->fetchAll(PDO::FETCH_ASSOC);

    foreach ($candidatos as $c) {
        $rid = (int)$c['rid'];
        if (!$rid) continue;
        $info = epl_recinto_contactos_jerarquico($rid);
        if (!empty($info['contactos'])) return $info;
    }

    // Fallback final: cualquier recinto del sistema que tenga contactos
    $any = $db->query("
        SELECT id FROM recintos
        WHERE contacto1_telefono IS NOT NULL AND contacto1_telefono <> ''
        ORDER BY id ASC LIMIT 1
    ")->fetchColumn();
    if ($any) {
        return epl_recinto_contactos_jerarquico((int)$any);
    }
    return ['contactos' => [], 'recinto_id' => null, 'recinto_nombre' => null];
}

/**
 * Indica si una reserva todavía merece una acción operativa. Se compara por día
 * para conservar como gestionable una reserva de hoy, y se ignora el 31/12 usado
 * históricamente como marcador de "sin fecha".
 */
function epl_reserva_fecha_vigente(?string $fecha, ?DateTimeInterface $referencia = null): bool {
    if (empty($fecha)) return false;

    $timestamp = strtotime($fecha);
    if ($timestamp === false) return false;

    $base = $referencia ?? new DateTimeImmutable('today');
    if (date('Y-m-d', $timestamp) === $base->format('Y') . '-12-31') return false;

    $hoy = new DateTimeImmutable($base->format('Y-m-d'));
    $dia_reserva = new DateTimeImmutable(date('Y-m-d', $timestamp));
    return $dia_reserva >= $hoy;
}

/**
 * Genera (o devuelve si ya existe) un token único para confirmar la baja de cancha
 * de un partido específico. El link de confirmación se incluye en el mensaje de WhatsApp.
 */
function epl_partido_baja_token(int $partido_id): string {
    epl_ensure_partidos_columnas_originales();
    $db = epl_db();
    $st = $db->prepare("SELECT baja_token FROM partidos WHERE id=?");
    $st->execute([$partido_id]);
    $tok = $st->fetchColumn();
    if ($tok) return (string)$tok;
    $tok = bin2hex(random_bytes(16)); // 32 chars hex
    // Preparar el enlace no significa que el aviso haya sido enviado. Antes se
    // llenaba baja_solicitada_at al solo renderizar una pantalla, lo que creaba
    // falsos estados de "esperando confirmación". El momento real del flujo lo
    // determina baja_confirmada_at cuando el club confirma la acción.
    $db->prepare("UPDATE partidos SET baja_token=? WHERE id=?")->execute([$tok, $partido_id]);
    return $tok;
}

/**
 * Genera (o devuelve si ya existe) un token único para que el club confirme
 * qué cancha asigna al partido reprogramado sin necesidad de login.
 */
function epl_partido_cancha_token(int $partido_id): string {
    epl_ensure_partidos_columnas_originales();
    $db = epl_db();
    $st = $db->prepare("SELECT cancha_token FROM partidos WHERE id=?");
    $st->execute([$partido_id]);
    $tok = $st->fetchColumn();
    if ($tok) return (string)$tok;
    $tok = bin2hex(random_bytes(16));
    $db->prepare("UPDATE partidos SET cancha_token=?, cancha_solicitada_at=NOW() WHERE id=?")->execute([$tok, $partido_id]);
    return $tok;
}

/**
 * Sube por la jerarquía de recintos hasta encontrar el nodo raíz (sin superior_id).
 * Devuelve el ID del recinto raíz.
 */
function epl_recinto_raiz(int $recinto_id): int {
    $db = epl_db();
    $visitados = [];
    $current = $recinto_id;
    while ($current && !isset($visitados[$current])) {
        $visitados[$current] = true;
        $st = $db->prepare("SELECT id, superior_id FROM recintos WHERE id=?");
        $st->execute([$current]);
        $r = $st->fetch(PDO::FETCH_ASSOC);
        if (!$r || !$r['superior_id']) return $current;
        $current = (int)$r['superior_id'];
    }
    return $recinto_id;
}

/**
 * Devuelve todos los recintos "hoja" (sin hijos) descendientes de un recinto raíz.
 * Esos son las canchas concretas que puede elegir el club.
 */
function epl_recintos_canchas_de_club(int $raiz_id): array {
    $db = epl_db();
    // Traer todo el árbol bajo raiz_id (incluido el propio)
    $todos = $db->query("SELECT id, nombre, superior_id FROM recintos")->fetchAll(PDO::FETCH_ASSOC);
    // Construir árbol
    $hijos = [];
    foreach ($todos as $r) {
        if ($r['superior_id']) $hijos[$r['superior_id']][] = $r;
    }
    // BFS desde raíz
    $descendientes = [];
    $queue = [$raiz_id];
    while ($queue) {
        $nodo = array_shift($queue);
        if (isset($hijos[$nodo])) {
            foreach ($hijos[$nodo] as $h) {
                $descendientes[] = $h;
                $queue[] = (int)$h['id'];
            }
        }
    }
    // Filtrar sólo los que no tienen hijos (hojas = canchas reales)
    $ids_con_hijos = array_keys($hijos);
    $canchas = array_filter($descendientes, fn($r) => !in_array((int)$r['id'], $ids_con_hijos));
    // Si no hay hojas (club sin subcanchas), devolver el propio raíz
    if (empty($canchas)) {
        $st = $db->prepare("SELECT id, nombre, superior_id FROM recintos WHERE id=?");
        $st->execute([$raiz_id]);
        $root = $st->fetch(PDO::FETCH_ASSOC);
        return $root ? [$root] : [];
    }
    return array_values($canchas);
}

/**
 * Antes de reprogramar un partido, guarda su fecha/cancha actual como "original"
 * si todavía no se guardó. Es idempotente: si ya existe valor, no lo pisa.
 *
 * Llamarla ANTES de UPDATE partidos cuando se va a cambiar fecha_programada
 * o recinto_id de un partido que entra en estado 'reprogramado'.
 */
function epl_partido_snapshot_original(int $partido_id): void {
    epl_ensure_partidos_columnas_originales();
    try {
        $db = epl_db();
        $st = $db->prepare("SELECT fecha_programada, recinto_id, fecha_original, recinto_original_id, estado FROM partidos WHERE id = ?");
        $st->execute([$partido_id]);
        $p = $st->fetch(PDO::FETCH_ASSOC);
        if (!$p) return;

        // Solo guardar si NO existe ya (no pisar el original real)
        $setFecha   = ($p['fecha_original']      === null && $p['fecha_programada'] !== null);
        $setRecinto = ($p['recinto_original_id'] === null && $p['recinto_id']       !== null);
        if (!$setFecha && !$setRecinto) return;

        // No usar el placeholder 31/12/2026 como original
        if ($setFecha && date('Y-m-d', strtotime((string)$p['fecha_programada'])) === '2026-12-31') {
            $setFecha = false;
        }
        if (!$setFecha && !$setRecinto) return;

        $sets = [];
        $args = [];
        if ($setFecha)   { $sets[] = 'fecha_original = ?';      $args[] = $p['fecha_programada']; }
        if ($setRecinto) { $sets[] = 'recinto_original_id = ?'; $args[] = $p['recinto_id'];       }
        $args[] = $partido_id;
        $db->prepare("UPDATE partidos SET " . implode(', ', $sets) . " WHERE id = ?")->execute($args);
    } catch (Throwable $e) {
        error_log('epl_partido_snapshot_original: ' . $e->getMessage());
    }
}

/** Devuelve array de IDs de todos los administradores activos. */
function epl_admins_ids(): array {
    $db = epl_db();
    $st = $db->query("SELECT id FROM jugadores WHERE rol='admin' AND estado='activo'");
    return array_column($st->fetchAll(), 'id');
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

/**
 * Notifica por push y email a los 4 jugadores y a los administradores
 * sobre la asignación de cancha para un partido reprogramado.
 */
function epl_notificar_asignacion_cancha(int $partido_id): void {
    $db = epl_db();
    
    $st = $db->prepare("
        SELECT p.*,
               el.nombre AS local_nombre,
               ev.nombre AS visitante_nombre,
               el.jugador1_id AS jl1_id, el.jugador2_id AS jl2_id,
               ev.jugador1_id AS jv1_id, ev.jugador2_id AS jv2_id,
               r.nombre AS recinto_nombre,
               rs.nombre AS superior_nombre,
               ra.nombre AS abuelo_nombre,
               ro.nombre AS recinto_orig_nombre,
               ros.nombre AS superior_orig_nombre,
               roa.nombre AS abuelo_orig_nombre
        FROM partidos p
        LEFT JOIN equipos el ON el.id = p.equipo_local_id
        LEFT JOIN equipos ev ON ev.id = p.equipo_visitante_id
        LEFT JOIN recintos r  ON r.id  = p.recinto_id
        LEFT JOIN recintos rs ON rs.id = r.superior_id
        LEFT JOIN recintos ra ON ra.id = rs.superior_id
        LEFT JOIN recintos ro  ON ro.id  = p.recinto_original_id
        LEFT JOIN recintos ros ON ros.id = ro.superior_id
        LEFT JOIN recintos roa ON roa.id = ros.superior_id
        WHERE p.id = ?
    ");
    $st->execute([$partido_id]);
    $p = $st->fetch(PDO::FETCH_ASSOC);
    if (!$p) return;

    $jornada = $p['jornada'] ?? '';
    $fecha   = $p['fecha_programada'] ? date('d/m/Y H:i', strtotime($p['fecha_programada'])) : 'Sin fecha';
    $fecha_orig = $p['fecha_original'] ? date('d/m/Y H:i', strtotime($p['fecha_original'])) : 'Sin fecha original';
    $local   = $p['local_nombre'] ?? '';
    $visita  = $p['visitante_nombre'] ?? '';
    
    // Armar nombre jerárquico de la cancha
    $cancha_parts = array_filter([$p['abuelo_nombre'], $p['superior_nombre'], $p['recinto_nombre']]);
    if (empty($cancha_parts)) {
        $cancha_str = 'Sin cancha';
    } else {
        $recinto_final = $p['recinto_nombre'];
        if (is_numeric($recinto_final)) $cancha_parts[count($cancha_parts)-1] = 'Cancha ' . $recinto_final;
        $cancha_str = implode(' - ', $cancha_parts);
    }

    // Armar nombre jerárquico de la cancha original
    $cancha_orig_parts = array_filter([$p['abuelo_orig_nombre'], $p['superior_orig_nombre'], $p['recinto_orig_nombre']]);
    if (empty($cancha_orig_parts)) {
        $cancha_orig_str = 'Sin cancha original';
    } else {
        $recinto_orig_final = $p['recinto_orig_nombre'];
        if (is_numeric($recinto_orig_final)) $cancha_orig_parts[count($cancha_orig_parts)-1] = 'Cancha ' . $recinto_orig_final;
        $cancha_orig_str = implode(' - ', $cancha_orig_parts);
    }
    // Detección de los tres casos de reprogramación
    $es_fecha_valida = function(?string $f): bool {
        if (empty($f)) return false;
        if (strpos($f, '0000-00-00') !== false) return false;
        if (date('Y-m-d', strtotime($f)) === '2026-12-31') return false;
        return true;
    };

    $had_date  = $es_fecha_valida($p['fecha_original']);
    $had_court = !empty($p['recinto_original_id']);
    $had_sched = $had_date && $had_court;

    $has_date  = $es_fecha_valida($p['fecha_programada']);
    $has_court = !empty($p['recinto_id']);
    $has_sched = $has_date && $has_court;

    $cambiado_por = $p['cancha_confirmada_por'] ? " Cambiado por: {$p['cancha_confirmada_por']}." : "";

    if ($had_sched && $has_sched) {
        // --- CASO 1: Reprogramación con fecha ---
        $tit_jugador = "🎾 Reprogramación: {$local} vs {$visita} (Jornada {$jornada})";
        $msg_jugador = "Estimados, tu partido de la jornada {$jornada} ({$local} vs {$visita}) ha sido reprogramado. La nueva fecha es el {$fecha} en la cancha {$cancha_str} (fecha original: {$fecha_orig} - cancha: {$cancha_orig_str}). Recuerden retirar agua y pelotas en el mesón de atención del club. En caso de no haber sido notificado por los rivales o no aceptar el cambio favor contactar a la organización.";

        $tit_admin = "📢 Reprogramación de Partido (Jornada {$jornada})";
        $msg_admin = "Estimados, se ha reprogramado el partido {$local} vs {$visita} (Jornada {$jornada}) con fecha anterior {$fecha_orig} (cancha {$cancha_orig_str}) a la fecha nueva {$fecha} (cancha {$cancha_str}).{$cambiado_por}";
    } elseif ($had_sched && !$has_sched) {
        // --- CASO 2: Baja de cancha / queda sin fecha ---
        $tit_jugador = "⚠️ Partido sin fecha (Baja de cancha) - Jornada {$jornada}";
        $msg_jugador = "Estimados, les informamos que se dio de baja la fecha/cancha de su partido {$local} vs {$visita} de la jornada {$jornada} (cancha original: {$cancha_orig_str} - fecha: {$fecha_orig}). El partido queda temporalmente \"Sin fecha\" pendiente de reprogramación. Nos contactaremos para coordinar la nueva fecha.";

        $tit_admin = "🚨 Alerta: Baja de Cancha (Jornada {$jornada})";
        $msg_admin = "Estimados, se dio de baja la cancha {$cancha_orig_str} con fecha {$fecha_orig} del partido {$local} vs {$visita} (Jornada {$jornada}). El partido quedó pendiente de programación (Sin fecha).{$cambiado_por}";
    } elseif (!$had_sched && $has_sched) {
        // --- CASO 3: Asignación a partido pendiente / sin fecha (B2.0) ---
        $tit_jugador = "📅 Asignación de Cancha y Fecha - Jornada {$jornada}";
        $msg_jugador = "Estimados, se ha asignado fecha y cancha para su partido pendiente de la jornada {$jornada} ({$local} vs {$visita}): se jugará el {$fecha} en la cancha {$cancha_str}. Recuerden retirar agua y pelotas en el mesón de atención del club.";

        $tit_admin = "✅ Programación de Partido sin fecha (Jornada {$jornada})";
        $msg_admin = "Estimados, se ha asignado fecha y cancha al partido que estaba sin fecha: {$local} vs {$visita} (Jornada {$jornada}) para el {$fecha} en la cancha {$cancha_str}.{$cambiado_por}";
    } else {
        // Fallback: Si no hay un cambio de estado significativo, no enviar notificaciones redundantes
        return;
    }

    // 1. Notificar a los 4 jugadores
    $url = epl_url('dashboard.php');
    $jugadores_ids = array_unique(array_filter([
        (int)$p['jl1_id'], (int)$p['jl2_id'],
        (int)$p['jv1_id'], (int)$p['jv2_id'],
    ]));
    
    foreach ($jugadores_ids as $jid) {
        if ($jid) {
            epl_notif_crear($jid, 'partido', $tit_jugador, $msg_jugador, $url, false);
        }
    }
    
    // 2. Notificar a administradores
    $stAdms = $db->query("SELECT id FROM jugadores WHERE rol = 'admin' AND estado = 'activo'");
    $admins = $stAdms->fetchAll(PDO::FETCH_COLUMN);
    
    $url_admin = epl_url('admin/partido_detalle.php?id=' . $partido_id);

    foreach ($admins as $aid) {
        if ($aid) {
            epl_notif_crear((int)$aid, 'admin', $tit_admin, $msg_admin, $url_admin, false);
        }
    }
}
