<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/gpt_actions.php';
epl_gpt_ensure_schema();
epl_session_start();

$input = $_SERVER['REQUEST_METHOD'] === 'POST' ? $_POST : $_GET;
$clientId = trim((string)($input['client_id'] ?? ''));
$redirect = trim((string)($input['redirect_uri'] ?? ''));
$state = (string)($input['state'] ?? '');
$responseType = (string)($input['response_type'] ?? 'code');
$requestedScope = preg_split('/\s+/', trim((string)($input['scope'] ?? 'epl.read epl.write'))) ?: [];
$scope = implode(' ', array_values(array_intersect($requestedScope, ['epl.read', 'epl.write'])));
if ($scope === '') $scope = 'epl.read';

$client = epl_gpt_client($clientId);
if (!$client || $responseType !== 'code' || !epl_gpt_client_redirect_ok($client, $redirect)) {
    http_response_code(400);
    exit('Solicitud OAuth inválida. Verifica el Client ID y registra en EPL la URL de callback exacta que entrega ChatGPT.');
}

$user = epl_jugador_actual();
if (!$user) {
    $back = $_SERVER['REQUEST_URI'] ?? epl_gpt_url('oauth/authorize.php');
    header('Location: ' . epl_url('login.php?back=' . urlencode($back)));
    exit;
}

$st = epl_db()->prepare("SELECT mcp_habilitado FROM jugadores WHERE id=? AND estado='activo'");
$st->execute([(int)$user['id']]);
if (!(int)$st->fetchColumn()) {
    http_response_code(403);
    exit('Tu cuenta no tiene habilitado el acceso a Inteligencia Artificial. Solicítalo a un administrador EPL.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals((string)($_SESSION['gpt_oauth_csrf'] ?? ''), (string)($_POST['csrf'] ?? ''))) {
        http_response_code(403);
        exit('Sesión inválida.');
    }
    if (($_POST['decision'] ?? '') !== 'allow') {
        $sep = str_contains($redirect, '?') ? '&' : '?';
        header('Location: ' . $redirect . $sep . http_build_query(['error' => 'access_denied', 'state' => $state]));
        exit;
    }
    $code = epl_mcp_b64url(random_bytes(32));
    epl_db()->prepare('INSERT INTO gpt_oauth_codes(code_hash,client_id,jugador_id,redirect_uri,scope,expires_at) VALUES(?,?,?,?,?,DATE_ADD(NOW(),INTERVAL 5 MINUTE))')
        ->execute([hash('sha256', $code), $clientId, (int)$user['id'], $redirect, $scope]);
    $sep = str_contains($redirect, '?') ? '&' : '?';
    header('Location: ' . $redirect . $sep . http_build_query(['code' => $code, 'state' => $state]));
    exit;
}

$_SESSION['gpt_oauth_csrf'] = epl_mcp_b64url(random_bytes(24));
$name = trim((string)($user['nombre'] ?? '') . ' ' . (string)($user['apellido'] ?? ''));
?><!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Conectar ChatGPT con EPL</title>
  <style>
    *{box-sizing:border-box}body{font-family:Arial,sans-serif;background:linear-gradient(145deg,#071b33,#123d68);color:#172033;margin:0;padding:20px;min-height:100vh}.box{max-width:540px;margin:4vh auto;background:#fff;border-radius:20px;padding:30px;box-shadow:0 24px 70px #0006}.brand{color:#b68a2a;font-weight:900;letter-spacing:.1em;font-size:.8rem}.app{display:flex;align-items:center;gap:12px;margin:20px 0}.app-icon{display:grid;place-items:center;width:48px;height:48px;border-radius:14px;background:#102b49;color:#d4ac4f;font-weight:900}.app h1{font-size:1.3rem;margin:0}.app p{font-size:.8rem;color:#64748b;margin:.25rem 0 0}.who{background:#f3f6fa;border-radius:11px;padding:13px;margin:16px 0;font-size:.85rem}.permissions{padding:0;margin:18px 0 0;list-style:none}.permissions li{display:flex;gap:9px;margin:.7rem 0;font-size:.82rem;line-height:1.4}.permissions li:before{content:'✓';color:#15803d;font-weight:900}.safe{font-size:.74rem;color:#64748b;background:#f8fafc;padding:10px;border-radius:9px}.actions{display:flex;gap:10px;margin-top:22px}button{border:0;border-radius:10px;padding:13px 18px;font-weight:800;cursor:pointer}.allow{background:#d4a62a;color:#071b33;flex:1}.deny{background:#e8edf3;color:#334155}@media(max-width:520px){.box{padding:22px;margin:1vh auto}.actions{flex-direction:column-reverse}.deny{width:100%}}
  </style>
</head>
<body><main class="box">
  <div class="brand">ELITE PADEL LEAGUE</div>
  <div class="app"><div class="app-icon">EPL</div><div><h1>Conectar con ChatGPT</h1><p><?= htmlspecialchars((string)$client['client_name']) ?></p></div></div>
  <div class="who"><strong><?= htmlspecialchars($name) ?></strong><br><?= htmlspecialchars((string)$user['email']) ?> · <?= htmlspecialchars((string)$user['rol']) ?></div>
  <p>ChatGPT solicita permiso para:</p>
  <ul class="permissions">
    <li>Consultar únicamente las ligas y partidos autorizados por tu perfil.</li>
    <li>Solicitar cambios solo cuando tu rol lo permita y después de tu confirmación.</li>
    <li>Registrar cada modificación en la auditoría de EPL.</li>
  </ul>
  <div class="safe">No entrega tu contraseña a ChatGPT y no permite acceso a SQL, archivos, SSH ni eliminaciones.</div>
  <form method="post">
    <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['gpt_oauth_csrf']) ?>">
    <?php foreach (['client_id'=>$clientId,'redirect_uri'=>$redirect,'state'=>$state,'response_type'=>$responseType,'scope'=>$scope] as $key=>$value): ?>
      <input type="hidden" name="<?= $key ?>" value="<?= htmlspecialchars($value) ?>">
    <?php endforeach; ?>
    <div class="actions"><button class="deny" name="decision" value="deny">Cancelar</button><button class="allow" name="decision" value="allow">Conectar EPL con ChatGPT</button></div>
  </form>
</main></body></html>

