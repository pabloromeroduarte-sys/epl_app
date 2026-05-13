<?php
declare(strict_types=1);
require_once __DIR__ . '/db.php';

function epl_h(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
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
               l.nombre AS liga_nombre,
               el.nombre AS local_nombre,
               ev.nombre AS visitante_nombre,
               r.nombre AS recinto_nombre
        FROM partidos p
        JOIN ligas l    ON l.id = p.liga_id
        JOIN equipos el ON el.id = p.equipo_local_id
        JOIN equipos ev ON ev.id = p.equipo_visitante_id
        LEFT JOIN recintos r ON r.id = p.recinto_id
        WHERE p.equipo_local_id = ? OR p.equipo_visitante_id = ?
        ORDER BY p.fecha_programada DESC, r.nombre ASC
    ");
    $st->execute([$equipo_id, $equipo_id]);
    return $st->fetchAll();
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
