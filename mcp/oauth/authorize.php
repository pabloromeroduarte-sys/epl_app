<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/mcp.php';
epl_mcp_ensure_schema();
epl_session_start();

$input=$_SERVER['REQUEST_METHOD']==='POST'?$_POST:$_GET;
$clientId=(string)($input['client_id']??'');
$redirect=(string)($input['redirect_uri']??'');
$state=(string)($input['state']??'');
$challenge=(string)($input['code_challenge']??'');
$method=(string)($input['code_challenge_method']??'');
$scope=trim((string)($input['scope']??'epl.read epl.write'));
$client=epl_mcp_client($clientId);
if(!$client||!epl_mcp_client_redirect_ok($client,$redirect)||$challenge===''||$method!=='S256'){
    http_response_code(400);echo 'Solicitud OAuth inválida.';exit;
}
$user=epl_jugador_actual();
if(!$user){$back=$_SERVER['REQUEST_URI'];header('Location: '.epl_url('login.php?back='.urlencode($back)));exit;}
$stEnabled=epl_db()->prepare("SELECT mcp_habilitado FROM jugadores WHERE id=? AND estado='activo'");
$stEnabled->execute([$user['id']]);
if(!(int)$stEnabled->fetchColumn()){http_response_code(403);exit('Tu cuenta no tiene habilitado el acceso MCP. Solicítalo a un administrador EPL.');}

if($_SERVER['REQUEST_METHOD']==='POST'){
    if(!hash_equals((string)($_SESSION['mcp_csrf']??''),(string)($_POST['csrf']??''))){http_response_code(403);exit('Sesión inválida.');}
    if(($_POST['decision']??'')!=='allow'){$sep=str_contains($redirect,'?')?'&':'?';header('Location: '.$redirect.$sep.http_build_query(['error'=>'access_denied','state'=>$state]));exit;}
    $code=epl_mcp_b64url(random_bytes(32));
    epl_db()->prepare('INSERT INTO mcp_oauth_codes(code_hash,client_id,jugador_id,redirect_uri,scope,code_challenge,expires_at) VALUES(?,?,?,?,?,?,DATE_ADD(NOW(),INTERVAL 5 MINUTE))')
        ->execute([hash('sha256',$code),$clientId,$user['id'],$redirect,$scope,$challenge]);
    $sep=str_contains($redirect,'?')?'&':'?';header('Location: '.$redirect.$sep.http_build_query(['code'=>$code,'state'=>$state]));exit;
}
$_SESSION['mcp_csrf']=epl_mcp_b64url(random_bytes(24));
?><!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Conectar EPL</title><style>body{font-family:Arial,sans-serif;background:#071b33;color:#162033;margin:0;padding:24px}.box{max-width:520px;margin:5vh auto;background:#fff;border-radius:18px;padding:28px;box-shadow:0 20px 60px #0005}.brand{color:#b68a2a;font-weight:900;letter-spacing:.08em}.who{background:#f3f6fa;border-radius:10px;padding:12px;margin:16px 0}li{margin:.55rem 0}.actions{display:flex;gap:10px;margin-top:22px}button{border:0;border-radius:10px;padding:13px 18px;font-weight:700;cursor:pointer}.allow{background:#d4a62a;color:#071b33;flex:1}.deny{background:#e8edf3;color:#334155}</style></head><body><main class="box">
<div class="brand">ELITE PADEL LEAGUE</div><h1>Conectar asistente</h1><p><strong><?=htmlspecialchars($client['client_name'])?></strong> solicita acceso a tu cuenta EPL.</p>
<div class="who"><?=htmlspecialchars(trim($user['nombre'].' '.$user['apellido']))?> · <?=htmlspecialchars($user['rol'])?></div>
<ul><li>Consultar solo las ligas y partidos permitidos por tu rol.</li><li>Crear solicitudes o cambios únicamente cuando tu rol lo autorice.</li><li>Registrar cada modificación en la auditoría.</li></ul>
<p><strong>No permite</strong> acceso a SQL, archivos, SSH ni eliminación de datos.</p>
<form method="post"><input type="hidden" name="csrf" value="<?=htmlspecialchars($_SESSION['mcp_csrf'])?>">
<?php foreach(['client_id'=>$clientId,'redirect_uri'=>$redirect,'state'=>$state,'code_challenge'=>$challenge,'code_challenge_method'=>$method,'scope'=>$scope] as $k=>$v):?><input type="hidden" name="<?=$k?>" value="<?=htmlspecialchars($v)?>"><?php endforeach;?>
<div class="actions"><button class="deny" name="decision" value="deny">Cancelar</button><button class="allow" name="decision" value="allow">Conectar EPL</button></div></form></main></body></html>
