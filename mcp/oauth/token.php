<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/mcp.php';
if($_SERVER['REQUEST_METHOD']!=='POST')epl_mcp_json(['error'=>'method_not_allowed'],405);
epl_mcp_ensure_schema();$db=epl_db();$grant=(string)($_POST['grant_type']??'');$clientId=(string)($_POST['client_id']??'');
if(!epl_mcp_client($clientId))epl_mcp_json(['error'=>'invalid_client'],401);
if($grant==='authorization_code'){
    $code=(string)($_POST['code']??'');$verifier=(string)($_POST['code_verifier']??'');$redirect=(string)($_POST['redirect_uri']??'');
    $st=$db->prepare('SELECT * FROM mcp_oauth_codes WHERE code_hash=? AND client_id=? AND redirect_uri=? AND used_at IS NULL AND expires_at>NOW() LIMIT 1');
    $st->execute([hash('sha256',$code),$clientId,$redirect]);$row=$st->fetch();
    if(!$row||!hash_equals($row['code_challenge'],epl_mcp_b64url(hash('sha256',$verifier,true))))epl_mcp_json(['error'=>'invalid_grant'],400);
    $db->prepare('UPDATE mcp_oauth_codes SET used_at=NOW() WHERE code_hash=?')->execute([$row['code_hash']]);
    $access=epl_mcp_b64url(random_bytes(32));$refresh=epl_mcp_b64url(random_bytes(32));
    $db->prepare("INSERT INTO mcp_oauth_tokens(token_hash,refresh_hash,client_id,jugador_id,scope,expires_at,refresh_expires_at) VALUES(?,?,?,?,?,DATE_ADD(NOW(),INTERVAL 30 DAY),DATE_ADD(NOW(),INTERVAL 90 DAY))")
        ->execute([hash('sha256',$access),hash('sha256',$refresh),$clientId,$row['jugador_id'],$row['scope']]);
    epl_mcp_json(['access_token'=>$access,'token_type'=>'Bearer','expires_in'=>2592000,'refresh_token'=>$refresh,'scope'=>$row['scope']]);
}
if($grant==='refresh_token'){
    $refresh=(string)($_POST['refresh_token']??'');$st=$db->prepare('SELECT * FROM mcp_oauth_tokens WHERE refresh_hash=? AND client_id=? AND revoked_at IS NULL AND refresh_expires_at>NOW() LIMIT 1');
    $st->execute([hash('sha256',$refresh),$clientId]);$row=$st->fetch();if(!$row)epl_mcp_json(['error'=>'invalid_grant'],400);
    $access=epl_mcp_b64url(random_bytes(32));$newRefresh=epl_mcp_b64url(random_bytes(32));
    $db->prepare('UPDATE mcp_oauth_tokens SET token_hash=?,refresh_hash=?,expires_at=DATE_ADD(NOW(),INTERVAL 30 DAY),refresh_expires_at=DATE_ADD(NOW(),INTERVAL 90 DAY) WHERE id=?')
        ->execute([hash('sha256',$access),hash('sha256',$newRefresh),$row['id']]);
    epl_mcp_json(['access_token'=>$access,'token_type'=>'Bearer','expires_in'=>2592000,'refresh_token'=>$newRefresh,'scope'=>$row['scope']]);
}
epl_mcp_json(['error'=>'unsupported_grant_type'],400);
