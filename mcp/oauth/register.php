<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/mcp.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') epl_mcp_json(['error'=>'method_not_allowed'],405);
epl_mcp_ensure_schema();
$recent=(int)epl_db()->query("SELECT COUNT(*) FROM mcp_oauth_clients WHERE created_at>DATE_SUB(NOW(),INTERVAL 1 HOUR)")->fetchColumn();
if($recent>=60)epl_mcp_json(['error'=>'temporarily_unavailable','error_description'=>'Demasiados registros recientes.'],429);
$in=json_decode(file_get_contents('php://input')?:'',true);
$uris=$in['redirect_uris']??[];
if(!is_array($uris)||!$uris||count($uris)>10)epl_mcp_json(['error'=>'invalid_client_metadata'],400);
foreach($uris as $uri){if(!is_string($uri)||!epl_mcp_redirect_uri_ok($uri))epl_mcp_json(['error'=>'invalid_redirect_uri'],400);}
$clientId='epl_'.epl_mcp_b64url(random_bytes(24));
$name=mb_substr(trim((string)($in['client_name']??'Cliente MCP')),0,190) ?: 'Cliente MCP';
epl_db()->prepare('INSERT INTO mcp_oauth_clients(client_id,client_name,redirect_uris) VALUES(?,?,?)')
    ->execute([$clientId,$name,json_encode(array_values(array_unique($uris)),JSON_UNESCAPED_SLASHES)]);
epl_mcp_json(['client_id'=>$clientId,'client_name'=>$name,'redirect_uris'=>$uris,
    'grant_types'=>['authorization_code','refresh_token'],'response_types'=>['code'],'token_endpoint_auth_method'=>'none'],201);
