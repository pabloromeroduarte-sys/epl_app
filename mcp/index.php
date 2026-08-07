<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/mcp.php';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    http_response_code(405);
    header('Allow: POST');
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') epl_mcp_json(['error'=>'method_not_allowed'],405);

$auth = epl_mcp_require_token();
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
