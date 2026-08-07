<?php
declare(strict_types=1);
$page_title = 'Admin — Acceso MCP';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/mcp.php';
epl_require_admin();
epl_mcp_ensure_schema();
$db=epl_db();
epl_session_start();
if(empty($_SESSION['mcp_admin_csrf']))$_SESSION['mcp_admin_csrf']=epl_mcp_b64url(random_bytes(24));

if($_SERVER['REQUEST_METHOD']==='POST'){
    if(!hash_equals((string)$_SESSION['mcp_admin_csrf'],(string)($_POST['csrf']??''))){http_response_code(403);exit('Sesión inválida.');}
    $action=(string)($_POST['action']??'');$uid=(int)($_POST['jugador_id']??0);
    if($action==='toggle'&&$uid>0){
        $enabled=!empty($_POST['habilitado'])?1:0;
        $db->prepare('UPDATE jugadores SET mcp_habilitado=? WHERE id=?')->execute([$enabled,$uid]);
        if(!$enabled)$db->prepare('UPDATE mcp_oauth_tokens SET revoked_at=NOW() WHERE jugador_id=? AND revoked_at IS NULL')->execute([$uid]);
    }elseif($action==='revoke'&&$uid>0){
        $db->prepare('UPDATE mcp_oauth_tokens SET revoked_at=NOW() WHERE jugador_id=? AND revoked_at IS NULL')->execute([$uid]);
    }
    header('Location: '.epl_url('admin/mcp.php'));exit;
}

$users=$db->query("SELECT j.id,j.nombre,j.apellido,j.email,j.rol,j.estado,j.mcp_habilitado,
    COUNT(t.id) conexiones,MAX(t.last_used_at) ultimo_uso
    FROM jugadores j LEFT JOIN mcp_oauth_tokens t ON t.jugador_id=j.id AND t.revoked_at IS NULL AND t.expires_at>NOW()
    WHERE j.estado='activo' GROUP BY j.id ORDER BY j.mcp_habilitado DESC,FIELD(j.rol,'admin','club','jugador'),j.nombre,j.apellido")->fetchAll();
$audit=$db->query("SELECT a.*,CONCAT(j.nombre,' ',j.apellido) usuario FROM mcp_audit_log a JOIN jugadores j ON j.id=a.jugador_id ORDER BY a.id DESC LIMIT 50")->fetchAll();
require_once __DIR__ . '/../includes/header.php';
?>
<div class="admin-layout">
<?php require __DIR__ . '/partials/sidebar.php'; ?>
<main class="admin-main">
  <div class="dash-header"><div><h1 class="dash-title">Acceso MCP</h1><p class="dash-subtitle">Controla quién puede conectar Claude o ChatGPT con EPL.</p></div></div>
  <div class="card" style="margin-bottom:1.25rem"><div class="card-body">
    <p style="margin:0"><strong>URL del conector:</strong> <code><?=epl_h(epl_mcp_base_url().'/')?></code></p>
    <p style="margin:.5rem 0 0;color:var(--gray-500);font-size:.85rem">Deshabilitar una cuenta revoca inmediatamente todas sus conexiones. Los permisos efectivos siempre dependen del rol vigente en EPL.</p>
  </div></div>
  <div class="card"><div class="card-head"><h2>Usuarios</h2></div><div class="card-body" style="padding:0;overflow-x:auto">
    <table class="table"><thead><tr><th>Usuario</th><th>Rol</th><th>Conexiones</th><th>Último uso</th><th>Acceso</th></tr></thead><tbody>
    <?php foreach($users as $u):?><tr><td><strong><?=epl_h(trim($u['nombre'].' '.$u['apellido']))?></strong><br><small><?=epl_h($u['email'])?></small></td><td><?=epl_h($u['rol'])?></td><td><?=(int)$u['conexiones']?></td><td><?=$u['ultimo_uso']?date('d/m/Y H:i',strtotime($u['ultimo_uso'])):'—'?></td><td>
      <form method="post" style="display:inline-flex;gap:.5rem;align-items:center"><input type="hidden" name="csrf" value="<?=epl_h($_SESSION['mcp_admin_csrf'])?>"><input type="hidden" name="action" value="toggle"><input type="hidden" name="jugador_id" value="<?=$u['id']?>">
      <input type="hidden" name="habilitado" value="<?=$u['mcp_habilitado']?'0':'1'?>"><button class="btn <?=$u['mcp_habilitado']?'btn-secondary':'btn-primary'?>" type="submit"><?=$u['mcp_habilitado']?'Deshabilitar':'Habilitar'?></button></form>
      <?php if((int)$u['conexiones']>0):?><form method="post" style="display:inline"><input type="hidden" name="csrf" value="<?=epl_h($_SESSION['mcp_admin_csrf'])?>"><input type="hidden" name="action" value="revoke"><input type="hidden" name="jugador_id" value="<?=$u['id']?>"><button class="btn btn-secondary" type="submit">Revocar sesiones</button></form><?php endif;?>
    </td></tr><?php endforeach;?></tbody></table>
  </div></div>
  <div class="card" style="margin-top:1.25rem"><div class="card-head"><h2>Últimas acciones MCP</h2></div><div class="card-body" style="padding:0;overflow-x:auto">
    <table class="table"><thead><tr><th>Fecha</th><th>Usuario</th><th>Herramienta</th><th>Resultado</th><th>Objetivo</th></tr></thead><tbody>
    <?php if(!$audit):?><tr><td colspan="5" style="text-align:center;padding:2rem">Todavía no hay acciones.</td></tr><?php endif;?>
    <?php foreach($audit as $a):?><tr><td><?=date('d/m/Y H:i',strtotime($a['created_at']))?></td><td><?=epl_h($a['usuario'])?></td><td><code><?=epl_h($a['tool_name'])?></code></td><td><?=$a['success']?'✅ Correcto':'⛔ '.epl_h((string)$a['error_message'])?></td><td><?=epl_h(($a['target_type']?:'—').($a['target_id']?' #'.$a['target_id']:''))?></td></tr><?php endforeach;?></tbody></table>
  </div></div>
</main></div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
