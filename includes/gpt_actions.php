<?php
declare(strict_types=1);

require_once __DIR__ . '/mcp.php';

function epl_gpt_base_url(): string {
    $app = rtrim(epl_env('APP_URL', ''), '/');
    if ($app !== '') return $app . '/gpt-api';
    $https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    return ($https ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . '/gpt-api';
}

function epl_gpt_url(string $path = ''): string {
    return epl_gpt_base_url() . ($path !== '' ? '/' . ltrim($path, '/') : '');
}

function epl_gpt_ensure_schema(): void {
    static $done = false;
    if ($done) return;
    $done = true;
    epl_mcp_ensure_schema();
    $db = epl_db();
    $db->exec("CREATE TABLE IF NOT EXISTS gpt_oauth_clients (
        client_id VARCHAR(96) NOT NULL,
        client_secret_hash VARCHAR(255) NOT NULL,
        client_name VARCHAR(190) NOT NULL,
        redirect_uris TEXT NOT NULL,
        active TINYINT(1) NOT NULL DEFAULT 1,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NULL,
        PRIMARY KEY (client_id), KEY idx_gpt_client_active (active)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $shareCol = $db->query("SHOW COLUMNS FROM gpt_oauth_clients LIKE 'gpt_share_url'")->fetch();
    if (!$shareCol) $db->exec("ALTER TABLE gpt_oauth_clients ADD COLUMN gpt_share_url VARCHAR(500) NULL AFTER redirect_uris");
    $db->exec("CREATE TABLE IF NOT EXISTS gpt_oauth_codes (
        code_hash CHAR(64) NOT NULL,
        client_id VARCHAR(96) NOT NULL,
        jugador_id INT UNSIGNED NOT NULL,
        redirect_uri VARCHAR(500) NOT NULL,
        scope VARCHAR(255) NOT NULL,
        expires_at DATETIME NOT NULL,
        used_at DATETIME NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (code_hash), KEY idx_gpt_code_exp (expires_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $db->exec("CREATE TABLE IF NOT EXISTS gpt_oauth_tokens (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        token_hash CHAR(64) NOT NULL,
        refresh_hash CHAR(64) NULL,
        client_id VARCHAR(96) NOT NULL,
        jugador_id INT UNSIGNED NOT NULL,
        scope VARCHAR(255) NOT NULL,
        expires_at DATETIME NOT NULL,
        refresh_expires_at DATETIME NULL,
        revoked_at DATETIME NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        last_used_at DATETIME NULL,
        PRIMARY KEY (id), UNIQUE KEY uk_gpt_access (token_hash), UNIQUE KEY uk_gpt_refresh (refresh_hash),
        KEY idx_gpt_token_user (jugador_id), KEY idx_gpt_token_exp (expires_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

function epl_gpt_json(array $data, int $status = 200): never {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    header('X-Content-Type-Options: nosniff');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function epl_gpt_client(string $clientId): ?array {
    epl_gpt_ensure_schema();
    $st = epl_db()->prepare('SELECT * FROM gpt_oauth_clients WHERE client_id=? AND active=1 LIMIT 1');
    $st->execute([$clientId]);
    return $st->fetch() ?: null;
}

function epl_gpt_active_client(): ?array {
    epl_gpt_ensure_schema();
    $row = epl_db()->query('SELECT * FROM gpt_oauth_clients WHERE active=1 ORDER BY created_at DESC LIMIT 1')->fetch();
    return $row ?: null;
}

/** @return array{client_id:string,client_secret:string} */
function epl_gpt_generate_client(): array {
    epl_gpt_ensure_schema();
    $db = epl_db();
    $clientId = 'epl_gpt_' . epl_mcp_b64url(random_bytes(18));
    $clientSecret = 'epl_secret_' . epl_mcp_b64url(random_bytes(32));
    $db->beginTransaction();
    try {
        $db->exec('UPDATE gpt_oauth_clients SET active=0,updated_at=NOW() WHERE active=1');
        $db->prepare('INSERT INTO gpt_oauth_clients(client_id,client_secret_hash,client_name,redirect_uris) VALUES(?,?,?,?)')
            ->execute([$clientId, password_hash($clientSecret, PASSWORD_DEFAULT), 'Elite Padel League para ChatGPT', '[]']);
        $db->commit();
    } catch (Throwable $e) {
        if ($db->inTransaction()) $db->rollBack();
        throw $e;
    }
    return ['client_id' => $clientId, 'client_secret' => $clientSecret];
}

function epl_gpt_set_redirect_uri(string $clientId, string $redirectUri): void {
    if (!epl_gpt_redirect_uri_is_openai($redirectUri)) {
        throw new InvalidArgumentException('La URL debe ser el callback exacto entregado por ChatGPT.');
    }
    $client = epl_gpt_client($clientId);
    if (!$client) throw new InvalidArgumentException('Cliente GPT no encontrado.');
    epl_db()->prepare('UPDATE gpt_oauth_clients SET redirect_uris=?,updated_at=NOW() WHERE client_id=?')
        ->execute([json_encode([$redirectUri], JSON_UNESCAPED_SLASHES), $clientId]);
}

function epl_gpt_set_share_url(string $clientId, string $shareUrl): void {
    $p = parse_url($shareUrl);
    $host = strtolower((string)($p['host'] ?? ''));
    $path = (string)($p['path'] ?? '');
    if (($p['scheme'] ?? '') !== 'https' || !in_array($host, ['chatgpt.com','chat.openai.com'], true) || !str_starts_with($path, '/g/')) {
        throw new InvalidArgumentException('Debes pegar el enlace oficial del GPT que comienza con https://chatgpt.com/g/.');
    }
    $client = epl_gpt_client($clientId);
    if (!$client) throw new InvalidArgumentException('Cliente GPT no encontrado.');
    epl_db()->prepare('UPDATE gpt_oauth_clients SET gpt_share_url=?,updated_at=NOW() WHERE client_id=?')->execute([$shareUrl, $clientId]);
}

function epl_gpt_redirect_uri_is_openai(string $uri): bool {
    $p = parse_url($uri);
    if (!$p || strtolower((string)($p['scheme'] ?? '')) !== 'https') return false;
    $host = strtolower((string)($p['host'] ?? ''));
    if (!in_array($host, ['chatgpt.com', 'chat.openai.com'], true)) return false;
    $path = (string)($p['path'] ?? '');
    return str_starts_with($path, '/aip/') || str_starts_with($path, '/connector/oauth/');
}

function epl_gpt_client_redirect_ok(array $client, string $redirectUri): bool {
    if (!epl_gpt_redirect_uri_is_openai($redirectUri)) return false;
    $uris = json_decode((string)$client['redirect_uris'], true);
    return is_array($uris) && in_array($redirectUri, $uris, true);
}

function epl_gpt_client_credentials(): array {
    $clientId = (string)($_POST['client_id'] ?? '');
    $clientSecret = (string)($_POST['client_secret'] ?? '');
    $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if ($header === '' && function_exists('getallheaders')) {
        $headers = getallheaders();
        $header = (string)($headers['Authorization'] ?? $headers['authorization'] ?? '');
    }
    if (preg_match('/^Basic\s+(.+)$/i', trim($header), $m)) {
        $decoded = base64_decode($m[1], true);
        if (is_string($decoded) && str_contains($decoded, ':')) {
            [$clientId, $clientSecret] = explode(':', $decoded, 2);
        }
    }
    return [trim($clientId), $clientSecret];
}

function epl_gpt_require_token(): array {
    epl_gpt_ensure_schema();
    $token = epl_mcp_bearer();
    if ($token === '') epl_gpt_json(['error' => 'authorization_required'], 401);
    $st = epl_db()->prepare("SELECT t.*,j.email,j.nombre,j.apellido,j.rol,j.estado,j.mcp_habilitado
        FROM gpt_oauth_tokens t JOIN jugadores j ON j.id=t.jugador_id
        JOIN gpt_oauth_clients c ON c.client_id=t.client_id AND c.active=1
        WHERE t.token_hash=? AND t.revoked_at IS NULL AND t.expires_at>NOW()
          AND j.estado='activo' AND j.mcp_habilitado=1 LIMIT 1");
    $st->execute([hash('sha256', $token)]);
    $row = $st->fetch();
    if (!$row) epl_gpt_json(['error' => 'invalid_token'], 401);
    epl_db()->prepare('UPDATE gpt_oauth_tokens SET last_used_at=NOW() WHERE id=?')->execute([$row['id']]);
    return $row;
}

function epl_gpt_input(): array {
    $input = $_GET;
    if (in_array($_SERVER['REQUEST_METHOD'] ?? 'GET', ['POST', 'PUT', 'PATCH'], true)) {
        $json = json_decode(file_get_contents('php://input') ?: '', true);
        if (is_array($json)) $input = array_replace($input, $json);
        elseif (!empty($_POST)) $input = array_replace($input, $_POST);
    }
    return is_array($input) ? $input : [];
}

function epl_gpt_run(string $tool, array $args = []): never {
    $auth = epl_gpt_require_token();
    $scopes = preg_split('/\s+/', trim((string)$auth['scope'])) ?: [];
    $writes = ['solicitar_reprogramacion', 'administrar_partido'];
    $requiredScope = in_array($tool, $writes, true) ? 'epl.write' : 'epl.read';
    if (!in_array($requiredScope, $scopes, true)) {
        epl_gpt_json(['ok' => false, 'error' => 'La conexión no tiene el permiso requerido: ' . $requiredScope], 403);
    }
    $recent = epl_db()->prepare('SELECT COUNT(*) FROM mcp_audit_log WHERE jugador_id=? AND created_at>DATE_SUB(NOW(),INTERVAL 1 MINUTE)');
    $recent->execute([$auth['jugador_id']]);
    if ((int)$recent->fetchColumn() >= 120) {
        epl_gpt_json(['ok' => false, 'error' => 'Límite temporal de solicitudes alcanzado.'], 429);
    }
    $result = epl_mcp_call($auth, $tool, $args);
    if (in_array($tool, ['quien_soy','listar_ligas','buscar_partidos','ver_partido','ver_reprogramaciones','listar_recintos'], true)) {
        epl_mcp_audit($auth, 'gpt_' . $tool, $args, empty($result['isError']));
    }
    $text = (string)($result['content'][0]['text'] ?? '');
    $payload = json_decode($text, true);
    if (!is_array($payload)) $payload = ['mensaje' => $text];
    if (!empty($result['isError'])) {
        epl_gpt_json(['ok' => false, 'error' => (string)($payload['error'] ?? $text ?: 'No se pudo completar la acción.')], 400);
    }
    epl_gpt_json(['ok' => true, 'data' => $payload]);
}

function epl_gpt_require_method(string ...$allowed): void {
    $method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    if (!in_array($method, $allowed, true)) {
        header('Allow: ' . implode(', ', $allowed));
        epl_gpt_json(['error' => 'method_not_allowed'], 405);
    }
}
