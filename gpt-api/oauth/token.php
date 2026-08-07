<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/gpt_actions.php';
epl_gpt_require_method('POST');
epl_gpt_ensure_schema();

$db = epl_db();
[$clientId, $clientSecret] = epl_gpt_client_credentials();
$client = epl_gpt_client($clientId);
if (!$client || $clientSecret === '' || !password_verify($clientSecret, (string)$client['client_secret_hash'])) {
    header('WWW-Authenticate: Basic realm="EPL GPT OAuth"');
    epl_gpt_json(['error' => 'invalid_client'], 401);
}

$grant = (string)($_POST['grant_type'] ?? '');
if ($grant === 'authorization_code') {
    $code = (string)($_POST['code'] ?? '');
    $redirect = (string)($_POST['redirect_uri'] ?? '');
    $st = $db->prepare('SELECT * FROM gpt_oauth_codes WHERE code_hash=? AND client_id=? AND redirect_uri=? AND used_at IS NULL AND expires_at>NOW() LIMIT 1');
    $st->execute([hash('sha256', $code), $clientId, $redirect]);
    $row = $st->fetch();
    if (!$row || !epl_gpt_client_redirect_ok($client, $redirect)) epl_gpt_json(['error' => 'invalid_grant'], 400);
    $db->prepare('UPDATE gpt_oauth_codes SET used_at=NOW() WHERE code_hash=?')->execute([$row['code_hash']]);
    $access = epl_mcp_b64url(random_bytes(32));
    $refresh = epl_mcp_b64url(random_bytes(32));
    $db->prepare("INSERT INTO gpt_oauth_tokens(token_hash,refresh_hash,client_id,jugador_id,scope,expires_at,refresh_expires_at)
        VALUES(?,?,?,?,?,DATE_ADD(NOW(),INTERVAL 30 DAY),DATE_ADD(NOW(),INTERVAL 180 DAY))")
        ->execute([hash('sha256', $access), hash('sha256', $refresh), $clientId, $row['jugador_id'], $row['scope']]);
    epl_gpt_json(['access_token'=>$access,'token_type'=>'Bearer','expires_in'=>2592000,'refresh_token'=>$refresh,'scope'=>$row['scope']]);
}

if ($grant === 'refresh_token') {
    $refresh = (string)($_POST['refresh_token'] ?? '');
    $st = $db->prepare('SELECT * FROM gpt_oauth_tokens WHERE refresh_hash=? AND client_id=? AND revoked_at IS NULL AND refresh_expires_at>NOW() LIMIT 1');
    $st->execute([hash('sha256', $refresh), $clientId]);
    $row = $st->fetch();
    if (!$row) epl_gpt_json(['error' => 'invalid_grant'], 400);
    $access = epl_mcp_b64url(random_bytes(32));
    $newRefresh = epl_mcp_b64url(random_bytes(32));
    $db->prepare('UPDATE gpt_oauth_tokens SET token_hash=?,refresh_hash=?,expires_at=DATE_ADD(NOW(),INTERVAL 30 DAY),refresh_expires_at=DATE_ADD(NOW(),INTERVAL 180 DAY),last_used_at=NULL WHERE id=?')
        ->execute([hash('sha256', $access), hash('sha256', $newRefresh), $row['id']]);
    epl_gpt_json(['access_token'=>$access,'token_type'=>'Bearer','expires_in'=>2592000,'refresh_token'=>$newRefresh,'scope'=>$row['scope']]);
}

epl_gpt_json(['error' => 'unsupported_grant_type'], 400);

