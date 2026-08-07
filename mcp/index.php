<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/mcp.php';

/** Cola efimera para clientes MCP que aun usan el transporte HTTP+SSE. */
function epl_mcp_sse_dir(): string {
    $dir = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'epl_mcp_sse';
    if (!is_dir($dir) && !@mkdir($dir, 0700, true) && !is_dir($dir)) {
        throw new RuntimeException('No fue posible crear el canal temporal MCP.');
    }
    return $dir;
}

function epl_mcp_sse_file(string $sessionId, string $extension): string {
    return epl_mcp_sse_dir() . DIRECTORY_SEPARATOR . $sessionId . '.' . $extension;
}

function epl_mcp_sse_create(array $auth): string {
    foreach (glob(epl_mcp_sse_dir() . DIRECTORY_SEPARATOR . '*.{meta,queue}', GLOB_BRACE) ?: [] as $oldFile) {
        if (is_file($oldFile) && filemtime($oldFile) < time() - 600) @unlink($oldFile);
    }
    $sessionId = bin2hex(random_bytes(32));
    file_put_contents(epl_mcp_sse_file($sessionId, 'meta'), json_encode([
        'jugador_id' => (int)$auth['jugador_id'],
        'client_id' => (string)$auth['client_id'],
        'created_at' => time(),
    ]), LOCK_EX);
    file_put_contents(epl_mcp_sse_file($sessionId, 'queue'), '', LOCK_EX);
    return $sessionId;
}

function epl_mcp_sse_authorized(string $sessionId, array $auth): bool {
    if (!preg_match('/^[a-f0-9]{64}$/', $sessionId)) return false;
    $file = epl_mcp_sse_file($sessionId, 'meta');
    if (!is_file($file)) return false;
    $meta = json_decode((string)file_get_contents($file), true);
    return is_array($meta)
        && (int)($meta['jugador_id'] ?? 0) === (int)$auth['jugador_id']
        && hash_equals((string)($meta['client_id'] ?? ''), (string)$auth['client_id']);
}

function epl_mcp_sse_enqueue(string $sessionId, array $message): bool {
    $file = epl_mcp_sse_file($sessionId, 'queue');
    if (!is_file($file)) return false;
    $line = json_encode($message, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
    return file_put_contents($file, $line, FILE_APPEND | LOCK_EX) !== false;
}

/** @return string[] */
function epl_mcp_sse_take(string $sessionId): array {
    $fp = @fopen(epl_mcp_sse_file($sessionId, 'queue'), 'c+');
    if (!$fp) return [];
    $raw = '';
    if (flock($fp, LOCK_EX)) {
        rewind($fp);
        $raw = (string)stream_get_contents($fp);
        ftruncate($fp, 0);
        fflush($fp);
        flock($fp, LOCK_UN);
    }
    fclose($fp);
    return array_values(array_filter(preg_split('/\r?\n/', trim($raw)) ?: []));
}

function epl_mcp_open_sse(array $auth): never {
    $sessionId = epl_mcp_sse_create($auth);
    @set_time_limit(0);
    @ini_set('zlib.output_compression', '0');
    ignore_user_abort(false);
    header('Content-Type: text/event-stream; charset=utf-8');
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Connection: keep-alive');
    header('X-Accel-Buffering: no');

    echo "event: endpoint\n";
    echo 'data: ' . epl_mcp_url('?session_id=' . rawurlencode($sessionId)) . "\n\n";
    echo ": EPL MCP conectado\n\n";
    @ob_flush(); @flush();

    $started = time();
    $lastPing = $started;
    while (!connection_aborted() && (time() - $started) < 85) {
        foreach (epl_mcp_sse_take($sessionId) as $json) {
            echo "event: message\n";
            echo 'data: ' . $json . "\n\n";
        }
        if ((time() - $lastPing) >= 10) {
            echo ': ping ' . time() . "\n\n";
            $lastPing = time();
        }
        @ob_flush(); @flush();
        usleep(200000);
    }
    @unlink(epl_mcp_sse_file($sessionId, 'queue'));
    @unlink(epl_mcp_sse_file($sessionId, 'meta'));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $auth = epl_mcp_require_token();
    epl_mcp_open_sse($auth);
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') epl_mcp_json(['error'=>'method_not_allowed'],405);

$auth = epl_mcp_require_token();
$sessionId = (string)($_GET['session_id'] ?? '');
if ($sessionId !== '') {
    if (!epl_mcp_sse_authorized($sessionId, $auth)) {
        epl_mcp_json(['error' => 'invalid_or_expired_session'], 404);
    }
    // El transporte SSE recibe la respuesta JSON-RPC por el GET abierto. El
    // POST solo confirma recepcion con 202, como exige el protocolo legado.
    ob_start();
    register_shutdown_function(static function () use ($sessionId): void {
        $raw = (string)ob_get_clean();
        if (trim($raw) !== '') {
            $payload = json_decode($raw, true);
            if (is_array($payload)) epl_mcp_sse_enqueue($sessionId, $payload);
        }
        if (!headers_sent()) {
            header_remove('Content-Type');
            header_remove('Content-Length');
            header('Cache-Control: no-store');
            http_response_code(202);
        }
    });
}
$body = json_decode(file_get_contents('php://input') ?: '', true);
if (!is_array($body)) epl_mcp_json(['jsonrpc'=>'2.0','id'=>null,'error'=>['code'=>-32700,'message'=>'Parse error']],400);
$id = $body['id'] ?? null;
$method = (string)($body['method'] ?? '');
$result = null;

if ($method === 'initialize') {
    $result = ['protocolVersion'=>EPL_MCP_PROTOCOL_VERSION,'capabilities'=>['tools'=>['listChanged'=>false]],
        'serverInfo'=>['name'=>'Elite Padel League','version'=>'1.0.0'],'instructions'=>'Usa solo datos autorizados para la cuenta conectada. Antes de una escritura, resume el cambio y pide confirmación explícita.'];
} elseif ($method === 'notifications/initialized') {
    http_response_code(202); exit;
} elseif ($method === 'ping') {
    $result = new stdClass();
} elseif ($method === 'tools/list') {
    $result = ['tools'=>epl_mcp_tools()];
} elseif ($method === 'tools/call') {
    $params = is_array($body['params'] ?? null) ? $body['params'] : [];
    $toolName=(string)($params['name']??'');
    $toolArgs=is_array($params['arguments']??null)?$params['arguments']:[];
    $recent=epl_db()->prepare('SELECT COUNT(*) FROM mcp_audit_log WHERE jugador_id=? AND created_at>DATE_SUB(NOW(),INTERVAL 1 MINUTE)');
    $recent->execute([$auth['jugador_id']]);
    if((int)$recent->fetchColumn()>=120){$result=epl_mcp_text_result(['ok'=>false,'error'=>'Límite temporal de solicitudes alcanzado.'],true);}
    else{
        $result=epl_mcp_call($auth,$toolName,$toolArgs);
        if(in_array($toolName,['quien_soy','listar_ligas','buscar_partidos','ver_partido','ver_reprogramaciones','listar_recintos'],true)){
            epl_mcp_audit($auth,$toolName,$toolArgs,empty($result['isError']));
        }
    }
} else {
    epl_mcp_json(['jsonrpc'=>'2.0','id'=>$id,'error'=>['code'=>-32601,'message'=>'Method not found']]);
}
epl_mcp_json(['jsonrpc'=>'2.0','id'=>$id,'result'=>$result]);
