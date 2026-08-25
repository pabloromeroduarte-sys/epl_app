<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

/**
 * Servicios para la aplicación EPL Score de Wear OS.
 *
 * Los secretos entregados al reloj nunca se guardan en texto plano. Tanto los
 * códigos de dispositivo como los access tokens se almacenan con SHA-256.
 */

function epl_watch_ensure_schema(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    $db = epl_db();
    $db->exec("CREATE TABLE IF NOT EXISTS epl_watch_pair_codes (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        device_hash CHAR(64) NOT NULL,
        user_code VARCHAR(8) NOT NULL,
        jugador_id INT UNSIGNED NULL,
        device_name VARCHAR(100) NOT NULL DEFAULT 'Galaxy Watch',
        status ENUM('pending','approved','consumed') NOT NULL DEFAULT 'pending',
        expires_at DATETIME NOT NULL,
        approved_at DATETIME NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY uk_watch_device_hash (device_hash),
        UNIQUE KEY uk_watch_user_code (user_code),
        KEY idx_watch_pair_expiry (status, expires_at),
        KEY idx_watch_pair_player (jugador_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $db->exec("CREATE TABLE IF NOT EXISTS epl_watch_tokens (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        jugador_id INT UNSIGNED NOT NULL,
        token_hash CHAR(64) NOT NULL,
        token_hint VARCHAR(12) NOT NULL,
        device_name VARCHAR(100) NOT NULL DEFAULT 'Galaxy Watch',
        expires_at DATETIME NOT NULL,
        last_used_at DATETIME NULL,
        revoked_at DATETIME NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY uk_watch_token_hash (token_hash),
        KEY idx_watch_token_player (jugador_id, revoked_at),
        KEY idx_watch_token_expiry (expires_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $db->exec("CREATE TABLE IF NOT EXISTS epl_watch_result_requests (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        jugador_id INT UNSIGNED NOT NULL,
        partido_id INT UNSIGNED NOT NULL,
        idempotency_hash CHAR(64) NOT NULL,
        response_json TEXT NOT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY uk_watch_result_idempotency (idempotency_hash),
        KEY idx_watch_result_match (partido_id),
        KEY idx_watch_result_player (jugador_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

function epl_watch_clean_code(string $code): string
{
    return strtoupper((string)preg_replace('/[^A-Z0-9]/i', '', $code));
}

function epl_watch_random_user_code(): string
{
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $last = strlen($alphabet) - 1;
    $raw = '';
    for ($i = 0; $i < 6; $i++) {
        $raw .= $alphabet[random_int(0, $last)];
    }
    return substr($raw, 0, 3) . '-' . substr($raw, 3);
}

function epl_watch_pair_start(string $deviceName): array
{
    epl_watch_ensure_schema();
    $db = epl_db();
    $deviceName = trim($deviceName) ?: 'Galaxy Watch';
    $deviceName = mb_substr($deviceName, 0, 100);

    $db->exec("DELETE FROM epl_watch_pair_codes WHERE expires_at < DATE_SUB(NOW(), INTERVAL 1 DAY)");

    $deviceCode = bin2hex(random_bytes(32));
    $deviceHash = hash('sha256', $deviceCode);
    $expiresAt = date('Y-m-d H:i:s', time() + 10 * 60);

    for ($attempt = 0; $attempt < 8; $attempt++) {
        $userCode = epl_watch_random_user_code();
        try {
            $st = $db->prepare("INSERT INTO epl_watch_pair_codes
                (device_hash, user_code, device_name, expires_at)
                VALUES (?, ?, ?, ?)");
            $st->execute([$deviceHash, $userCode, $deviceName, $expiresAt]);
            return [
                'device_code' => $deviceCode,
                'user_code' => $userCode,
                'verification_url' => epl_url('reloj.php'),
                'expires_in' => 600,
                'poll_interval' => 3,
            ];
        } catch (PDOException $e) {
            if ((string)$e->getCode() !== '23000') {
                throw $e;
            }
        }
    }

    throw new RuntimeException('No fue posible generar el código de vinculación.');
}

function epl_watch_authorize_code(string $userCode, int $jugadorId): array
{
    epl_watch_ensure_schema();
    $clean = epl_watch_clean_code($userCode);
    if (strlen($clean) !== 6) {
        return ['ok' => false, 'error' => 'El código debe tener seis caracteres.'];
    }
    $formatted = substr($clean, 0, 3) . '-' . substr($clean, 3);

    $st = epl_db()->prepare("UPDATE epl_watch_pair_codes
        SET jugador_id = ?, status = 'approved', approved_at = NOW()
        WHERE user_code = ? AND status = 'pending' AND expires_at > NOW()");
    $st->execute([$jugadorId, $formatted]);

    if ($st->rowCount() !== 1) {
        return ['ok' => false, 'error' => 'El código no existe, venció o ya fue utilizado.'];
    }
    return ['ok' => true, 'message' => 'Reloj vinculado correctamente.'];
}

function epl_watch_pair_status(string $deviceCode): array
{
    epl_watch_ensure_schema();
    if (!preg_match('/^[a-f0-9]{64}$/i', $deviceCode)) {
        return ['status' => 'invalid'];
    }

    $db = epl_db();
    $hash = hash('sha256', strtolower($deviceCode));
    $db->beginTransaction();
    try {
        $st = $db->prepare("SELECT * FROM epl_watch_pair_codes WHERE device_hash = ? FOR UPDATE");
        $st->execute([$hash]);
        $pair = $st->fetch(PDO::FETCH_ASSOC);
        if (!$pair) {
            $db->rollBack();
            return ['status' => 'invalid'];
        }
        if (strtotime((string)$pair['expires_at']) <= time()) {
            $db->rollBack();
            return ['status' => 'expired'];
        }
        if ($pair['status'] === 'pending') {
            $db->rollBack();
            return ['status' => 'pending'];
        }
        if ($pair['status'] !== 'approved' || empty($pair['jugador_id'])) {
            $db->rollBack();
            return ['status' => 'consumed'];
        }

        $accessToken = 'eplw_' . bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $accessToken);
        $tokenHint = substr($accessToken, -8);
        $expiresAt = date('Y-m-d H:i:s', time() + 180 * 24 * 3600);
        $insert = $db->prepare("INSERT INTO epl_watch_tokens
            (jugador_id, token_hash, token_hint, device_name, expires_at)
            VALUES (?, ?, ?, ?, ?)");
        $insert->execute([
            (int)$pair['jugador_id'],
            $tokenHash,
            $tokenHint,
            (string)$pair['device_name'],
            $expiresAt,
        ]);
        $db->prepare("UPDATE epl_watch_pair_codes SET status = 'consumed' WHERE id = ?")
            ->execute([(int)$pair['id']]);
        $db->commit();

        return [
            'status' => 'approved',
            'access_token' => $accessToken,
            'token_type' => 'Bearer',
            'expires_in' => 180 * 24 * 3600,
        ];
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        throw $e;
    }
}

function epl_watch_bearer_token(): string
{
    $header = (string)($_SERVER['HTTP_AUTHORIZATION'] ?? '');
    if ($header === '' && function_exists('getallheaders')) {
        $headers = getallheaders();
        $header = (string)($headers['Authorization'] ?? $headers['authorization'] ?? '');
    }
    return preg_match('/^Bearer\s+(.+)$/i', trim($header), $m) ? trim($m[1]) : '';
}

function epl_watch_authenticate(string $accessToken): ?array
{
    epl_watch_ensure_schema();
    if (!preg_match('/^eplw_[a-f0-9]{64}$/i', $accessToken)) {
        return null;
    }

    $st = epl_db()->prepare("SELECT wt.id AS token_id, wt.jugador_id, wt.device_name,
                                   j.nombre, j.apellido, j.email, j.rol
        FROM epl_watch_tokens wt
        JOIN jugadores j ON j.id = wt.jugador_id
        WHERE wt.token_hash = ?
          AND wt.revoked_at IS NULL
          AND wt.expires_at > NOW()
          AND j.estado = 'activo'
        LIMIT 1");
    $st->execute([hash('sha256', $accessToken)]);
    $player = $st->fetch(PDO::FETCH_ASSOC);
    if (!$player || ($player['rol'] ?? '') === 'club') {
        return null;
    }

    epl_db()->prepare("UPDATE epl_watch_tokens SET last_used_at = NOW() WHERE id = ?")
        ->execute([(int)$player['token_id']]);
    return $player;
}

function epl_watch_devices_for_player(int $jugadorId): array
{
    epl_watch_ensure_schema();
    $st = epl_db()->prepare("SELECT id, device_name, token_hint, created_at, last_used_at, expires_at
        FROM epl_watch_tokens
        WHERE jugador_id = ? AND revoked_at IS NULL AND expires_at > NOW()
        ORDER BY created_at DESC");
    $st->execute([$jugadorId]);
    return $st->fetchAll(PDO::FETCH_ASSOC);
}

function epl_watch_revoke_device(int $tokenId, int $jugadorId): bool
{
    epl_watch_ensure_schema();
    $st = epl_db()->prepare("UPDATE epl_watch_tokens SET revoked_at = NOW()
        WHERE id = ? AND jugador_id = ? AND revoked_at IS NULL");
    $st->execute([$tokenId, $jugadorId]);
    return $st->rowCount() === 1;
}

function epl_watch_match_rows(int $jugadorId): array
{
    $placeholder = date('Y') . '-12-31';
    $st = epl_db()->prepare("SELECT p.id, p.liga_id, p.equipo_local_id, p.equipo_visitante_id,
               p.jornada, p.nombre_fecha, p.fecha_programada, p.estado,
               l.nombre AS liga_nombre,
               el.nombre AS local_nombre, ev.nombre AS visitante_nombre,
               r.nombre AS recinto_nombre,
               CASE WHEN el.jugador1_id = :j1 OR el.jugador2_id = :j2 THEN 'local' ELSE 'visitante' END AS mi_lado
        FROM partidos p
        JOIN ligas l ON l.id = p.liga_id
        JOIN equipos el ON el.id = p.equipo_local_id
        JOIN equipos ev ON ev.id = p.equipo_visitante_id
        LEFT JOIN recintos r ON r.id = p.recinto_id
        WHERE p.estado IN ('pendiente','reprogramado')
          AND (el.jugador1_id = :j3 OR el.jugador2_id = :j4
               OR ev.jugador1_id = :j5 OR ev.jugador2_id = :j6)
        ORDER BY
          CASE WHEN p.fecha_programada IS NULL OR DATE(p.fecha_programada) = :placeholder THEN 2
               WHEN p.fecha_programada < NOW() THEN 0 ELSE 1 END,
          p.fecha_programada ASC, p.id ASC");
    $st->execute([
        ':j1' => $jugadorId,
        ':j2' => $jugadorId,
        ':j3' => $jugadorId,
        ':j4' => $jugadorId,
        ':j5' => $jugadorId,
        ':j6' => $jugadorId,
        ':placeholder' => $placeholder,
    ]);

    $rows = $st->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as &$row) {
        $validation = epl_watch_match_date_validation($row, $jugadorId);
        $row['can_submit'] = $validation['ok'];
        $row['blocked_reason'] = $validation['ok'] ? null : $validation['error'];
    }
    unset($row);
    return $rows;
}

function epl_watch_match_date_validation(array $match, int $jugadorId): array
{
    $dateRaw = (string)($match['fecha_programada'] ?? '');
    $placeholder = date('Y') . '-12-31';
    if ($dateRaw === '' || date('Y-m-d', strtotime($dateRaw)) === $placeholder) {
        return ['ok' => false, 'error' => 'El partido todavía no tiene fecha.'];
    }

    $matchDate = date('Y-m-d', strtotime($dateRaw));
    if ($matchDate > date('Y-m-d', strtotime('+1 day'))) {
        return ['ok' => false, 'error' => 'Podrás registrar el resultado desde el día anterior.'];
    }

    $isLate = strtotime($dateRaw) < time();
    if (!$isLate) {
        $st = epl_db()->prepare("SELECT COUNT(*)
            FROM partidos px
            JOIN equipos exl ON exl.id = px.equipo_local_id
            JOIN equipos exv ON exv.id = px.equipo_visitante_id
            WHERE px.liga_id = ?
              AND px.estado IN ('pendiente','reprogramado')
              AND px.fecha_programada IS NOT NULL
              AND px.fecha_programada < NOW()
              AND DATE(px.fecha_programada) <> ?
              AND (exl.jugador1_id = ? OR exl.jugador2_id = ?
                   OR exv.jugador1_id = ? OR exv.jugador2_id = ?)");
        $st->execute([
            (int)$match['liga_id'],
            $placeholder,
            $jugadorId, $jugadorId, $jugadorId, $jugadorId,
        ]);
        if ((int)$st->fetchColumn() > 0) {
            return ['ok' => false, 'error' => 'Primero debes registrar tus partidos atrasados.'];
        }
    }
    return ['ok' => true];
}

function epl_watch_find_match_for_player(int $partidoId, int $jugadorId, bool $forUpdate = false): ?array
{
    $lock = $forUpdate ? ' FOR UPDATE' : '';
    $st = epl_db()->prepare("SELECT p.*, l.nombre AS liga_nombre,
               el.nombre AS local_nombre, ev.nombre AS visitante_nombre,
               CASE WHEN el.jugador1_id = :j1 OR el.jugador2_id = :j2 THEN p.equipo_local_id ELSE p.equipo_visitante_id END AS mi_equipo_id
        FROM partidos p
        JOIN ligas l ON l.id = p.liga_id
        JOIN equipos el ON el.id = p.equipo_local_id
        JOIN equipos ev ON ev.id = p.equipo_visitante_id
        WHERE p.id = :partido
          AND (el.jugador1_id = :j3 OR el.jugador2_id = :j4
               OR ev.jugador1_id = :j5 OR ev.jugador2_id = :j6)
        LIMIT 1" . $lock);
    $st->execute([
        ':j1' => $jugadorId,
        ':j2' => $jugadorId,
        ':partido' => $partidoId,
        ':j3' => $jugadorId,
        ':j4' => $jugadorId,
        ':j5' => $jugadorId,
        ':j6' => $jugadorId,
    ]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function epl_watch_normalize_sets(array $rawSets): array
{
    $sets = [];
    foreach (array_slice($rawSets, 0, 3) as $set) {
        if (!is_array($set) || !array_key_exists('local', $set) || !array_key_exists('visitante', $set)) {
            throw new InvalidArgumentException('El formato de los sets no es válido.');
        }
        $local = filter_var($set['local'], FILTER_VALIDATE_INT);
        $visitor = filter_var($set['visitante'], FILTER_VALIDATE_INT);
        if ($local === false || $visitor === false || $local < 0 || $visitor < 0 || $local > 99 || $visitor > 99 || $local === $visitor) {
            throw new InvalidArgumentException('El marcador de un set no es válido.');
        }
        $sets[] = ['local' => $local, 'visitante' => $visitor];
    }
    if (count($sets) < 2) {
        throw new InvalidArgumentException('El resultado debe incluir al menos dos sets.');
    }
    $localWins = count(array_filter($sets, static fn(array $s): bool => $s['local'] > $s['visitante']));
    $visitorWins = count($sets) - $localWins;
    if (max($localWins, $visitorWins) !== 2 || $localWins === $visitorWins) {
        throw new InvalidArgumentException('El resultado debe definir un ganador al mejor de tres sets.');
    }
    return $sets;
}

function epl_watch_submit_result(int $jugadorId, int $partidoId, array $rawSets, string $idempotencyKey): array
{
    epl_watch_ensure_schema();
    if (!preg_match('/^[A-Za-z0-9._:-]{16,128}$/', $idempotencyKey)) {
        throw new InvalidArgumentException('Falta una clave de envío válida.');
    }
    $sets = epl_watch_normalize_sets($rawSets);
    $keyHash = hash('sha256', $idempotencyKey);
    $db = epl_db();

    $existing = $db->prepare("SELECT response_json FROM epl_watch_result_requests WHERE idempotency_hash = ? LIMIT 1");
    $existing->execute([$keyHash]);
    $stored = $existing->fetchColumn();
    if ($stored) {
        $decoded = json_decode((string)$stored, true);
        if (is_array($decoded)) {
            $decoded['idempotent_replay'] = true;
            return $decoded;
        }
    }

    $db->beginTransaction();
    try {
        $match = epl_watch_find_match_for_player($partidoId, $jugadorId, true);
        if (!$match || !in_array($match['estado'], ['pendiente', 'reprogramado'], true)) {
            throw new RuntimeException('El partido no está disponible para registrar resultado.');
        }
        $validation = epl_watch_match_date_validation($match, $jugadorId);
        if (!$validation['ok']) {
            throw new RuntimeException((string)$validation['error']);
        }

        $localWins = count(array_filter($sets, static fn(array $s): bool => $s['local'] > $s['visitante']));
        $visitorWins = count($sets) - $localWins;
        $winnerId = $localWins > $visitorWins
            ? (int)$match['equipo_local_id']
            : (int)$match['equipo_visitante_id'];
        $now = date('Y-m-d H:i:s');
        $playedAt = $match['fecha_programada'] ?: $now;

        $update = $db->prepare("UPDATE partidos SET
            estado = 'jugado', fecha_jugado = ?,
            sets_local = ?, sets_visitante = ?,
            games_s1_local = ?, games_s1_visitante = ?,
            games_s2_local = ?, games_s2_visitante = ?,
            games_s3_local = ?, games_s3_visitante = ?,
            ganador_id = ?, ingresado_por = ?, resultado_ingresado_at = ?
            WHERE id = ? AND estado IN ('pendiente','reprogramado')");
        $update->execute([
            $playedAt,
            $localWins,
            $visitorWins,
            $sets[0]['local'] ?? null,
            $sets[0]['visitante'] ?? null,
            $sets[1]['local'] ?? null,
            $sets[1]['visitante'] ?? null,
            $sets[2]['local'] ?? null,
            $sets[2]['visitante'] ?? null,
            $winnerId,
            $jugadorId,
            $now,
            $partidoId,
        ]);
        if ($update->rowCount() !== 1) {
            throw new RuntimeException('El partido cambió mientras enviabas el resultado. Actualiza e inténtalo nuevamente.');
        }

        $response = [
            'ok' => true,
            'message' => 'Resultado registrado correctamente.',
            'partido_id' => $partidoId,
            'sets_local' => $localWins,
            'sets_visitante' => $visitorWins,
            'sets' => $sets,
        ];
        $insert = $db->prepare("INSERT INTO epl_watch_result_requests
            (jugador_id, partido_id, idempotency_hash, response_json)
            VALUES (?, ?, ?, ?)");
        $insert->execute([
            $jugadorId,
            $partidoId,
            $keyHash,
            json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);
        $db->commit();

        try {
            epl_recalcular_clasificacion((int)$match['liga_id']);
            epl_watch_notify_result($match, $jugadorId, $sets, $localWins, $visitorWins);
        } catch (Throwable $notificationError) {
            error_log('EPL Watch resultado guardado; notificación falló: ' . $notificationError->getMessage());
        }
        return $response;
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        throw $e;
    }
}

function epl_watch_notify_result(array $match, int $jugadorId, array $sets, int $localWins, int $visitorWins): void
{
    $db = epl_db();
    $rivalId = (int)$match['mi_equipo_id'] === (int)$match['equipo_local_id']
        ? (int)$match['equipo_visitante_id']
        : (int)$match['equipo_local_id'];
    $st = $db->prepare("SELECT jugador1_id, jugador2_id FROM equipos WHERE id = ?");
    $st->execute([$rivalId]);
    $rival = $st->fetch(PDO::FETCH_ASSOC) ?: [];
    $result = implode(' / ', array_map(
        static fn(array $set): string => $set['local'] . '-' . $set['visitante'],
        $sets
    ));
    $title = 'Resultado registrado desde EPL Score';
    $message = sprintf(
        '%s vs %s terminó %d-%d en sets (%s). Puedes reclamar el resultado durante las próximas 24 horas.',
        $match['local_nombre'],
        $match['visitante_nombre'],
        $localWins,
        $visitorWins,
        $result
    );
    $url = epl_url('reclamar_resultado.php?partido_id=' . (int)$match['id']);
    foreach (array_unique(array_filter([(int)($rival['jugador1_id'] ?? 0), (int)($rival['jugador2_id'] ?? 0)])) as $rivalPlayerId) {
        epl_notif_crear((int)$rivalPlayerId, 'resultado', $title, $message, $url);
    }

    $admins = $db->query("SELECT id FROM jugadores WHERE rol = 'admin' AND estado = 'activo'")
        ->fetchAll(PDO::FETCH_COLUMN);
    $adminMessage = $message . ' Ingresado por el jugador #' . $jugadorId . '.';
    foreach ($admins as $adminId) {
        epl_notif_crear(
            (int)$adminId,
            'resultado',
            $title,
            $adminMessage,
            epl_url('admin/partido_detalle.php?id=' . (int)$match['id'])
        );
    }
}

