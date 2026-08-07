<?php
declare(strict_types=1);
$page_title = 'Admin — Acceso IA';
$page_css = 'mcp-admin';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/mcp.php';
require_once __DIR__ . '/../includes/gpt_actions.php';
epl_require_admin();
epl_mcp_ensure_schema();
epl_gpt_ensure_schema();
$db=epl_db();
epl_session_start();
if(empty($_SESSION['mcp_admin_csrf']))$_SESSION['mcp_admin_csrf']=epl_mcp_b64url(random_bytes(24));

if($_SERVER['REQUEST_METHOD']==='POST'){
    if(!hash_equals((string)$_SESSION['mcp_admin_csrf'],(string)($_POST['csrf']??''))){http_response_code(403);exit('Sesión inválida.');}
    $action=(string)($_POST['action']??'');$uid=(int)($_POST['jugador_id']??0);
    if($action==='gpt_generate'){
        $credentials=epl_gpt_generate_client();
        $_SESSION['gpt_new_credentials']=$credentials;
        $_SESSION['gpt_admin_flash']='Credenciales creadas. Copia el Client Secret ahora: se muestra una sola vez.';
    }elseif($action==='gpt_redirect'){
        try{
            epl_gpt_set_redirect_uri((string)($_POST['client_id']??''),trim((string)($_POST['redirect_uri']??'')));
            $_SESSION['gpt_admin_flash']='Callback de ChatGPT guardado correctamente.';
        }catch(Throwable $e){$_SESSION['gpt_admin_error']=$e->getMessage();}
    }elseif($action==='gpt_share'){
        try{
            epl_gpt_set_share_url((string)($_POST['client_id']??''),trim((string)($_POST['gpt_share_url']??'')));
            $_SESSION['gpt_admin_flash']='Enlace del GPT guardado. Ya puede mostrarse a los jugadores en Android.';
        }catch(Throwable $e){$_SESSION['gpt_admin_error']=$e->getMessage();}
    }elseif($action==='toggle'&&$uid>0){
        $enabled=!empty($_POST['habilitado'])?1:0;
        $db->prepare('UPDATE jugadores SET mcp_habilitado=? WHERE id=?')->execute([$enabled,$uid]);
        if(!$enabled){
            $db->prepare('UPDATE mcp_oauth_tokens SET revoked_at=NOW() WHERE jugador_id=? AND revoked_at IS NULL')->execute([$uid]);
            $db->prepare('UPDATE gpt_oauth_tokens SET revoked_at=NOW() WHERE jugador_id=? AND revoked_at IS NULL')->execute([$uid]);
        }
    }elseif($action==='revoke'&&$uid>0){
        $db->prepare('UPDATE mcp_oauth_tokens SET revoked_at=NOW() WHERE jugador_id=? AND revoked_at IS NULL')->execute([$uid]);
        $db->prepare('UPDATE gpt_oauth_tokens SET revoked_at=NOW() WHERE jugador_id=? AND revoked_at IS NULL')->execute([$uid]);
    }
    header('Location: '.epl_url('admin/mcp.php'));exit;
}

$users=$db->query("SELECT j.id,j.nombre,j.apellido,j.email,j.rol,j.estado,j.mcp_habilitado,
    ((SELECT COUNT(*) FROM mcp_oauth_tokens mt WHERE mt.jugador_id=j.id AND mt.revoked_at IS NULL AND mt.expires_at>NOW())+
     (SELECT COUNT(*) FROM gpt_oauth_tokens gt WHERE gt.jugador_id=j.id AND gt.revoked_at IS NULL AND gt.expires_at>NOW())) conexiones,
    CASE WHEN (SELECT MAX(mt.last_used_at) FROM mcp_oauth_tokens mt WHERE mt.jugador_id=j.id) IS NULL
               AND (SELECT MAX(gt.last_used_at) FROM gpt_oauth_tokens gt WHERE gt.jugador_id=j.id) IS NULL THEN NULL
         ELSE GREATEST(COALESCE((SELECT MAX(mt.last_used_at) FROM mcp_oauth_tokens mt WHERE mt.jugador_id=j.id),'1000-01-01'),
                       COALESCE((SELECT MAX(gt.last_used_at) FROM gpt_oauth_tokens gt WHERE gt.jugador_id=j.id),'1000-01-01')) END ultimo_uso
    FROM jugadores j WHERE j.estado='activo'
    ORDER BY j.mcp_habilitado DESC,FIELD(j.rol,'admin','club','jugador'),j.nombre,j.apellido")->fetchAll();
$audit=$db->query("SELECT a.*,CONCAT(j.nombre,' ',j.apellido) usuario FROM mcp_audit_log a JOIN jugadores j ON j.id=a.jugador_id ORDER BY a.id DESC LIMIT 50")->fetchAll();
$gptClient=epl_gpt_active_client();
$gptCredentials=$_SESSION['gpt_new_credentials']??null;
$gptFlash=(string)($_SESSION['gpt_admin_flash']??'');
$gptError=(string)($_SESSION['gpt_admin_error']??'');
unset($_SESSION['gpt_new_credentials'],$_SESSION['gpt_admin_flash'],$_SESSION['gpt_admin_error']);
$gptRedirects=$gptClient?json_decode((string)$gptClient['redirect_uris'],true):[];
$gptRedirect=is_array($gptRedirects)&&$gptRedirects?(string)$gptRedirects[0]:'';
$gptShareUrl=$gptClient?trim((string)($gptClient['gpt_share_url']??'')):'';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="dash-layout">
  <?php require __DIR__ . '/partials/sidebar.php'; ?>
  <main class="dash-main mcp-admin-main">
    <section class="mcp-admin-hero">
      <div><span class="mcp-eyebrow">CONTROL DE ACCESO</span><h1>Conexiones de Inteligencia Artificial</h1><p>Habilita quién puede usar EPL desde ChatGPT, Claude o Gemini. Cada usuario mantiene exactamente los permisos de su rol.</p></div>
      <a href="<?=epl_url('conectar_ia.php')?>" class="mcp-guide-btn">📱 Ver tutorial de instalación</a>
    </section>

    <section class="gpt-setup-card">
      <header class="gpt-setup-head">
        <div class="gpt-brand-icon">GPT</div>
        <div><span class="mcp-eyebrow">RECOMENDADO PARA ANDROID</span><h2>ChatGPT mediante GPT Actions</h2><p>Configura un GPT privado que use la API segura de EPL y respete los permisos de cada usuario.</p></div>
        <span class="gpt-ready <?=($gptClient&&$gptRedirect&&$gptShareUrl)?'on':'pending'?>"><?=($gptClient&&$gptRedirect&&$gptShareUrl)?'Listo para Android':'Configuración pendiente'?></span>
      </header>

      <?php if($gptFlash):?><div class="gpt-flash ok"><?=epl_h($gptFlash)?></div><?php endif;?>
      <?php if($gptError):?><div class="gpt-flash error"><?=epl_h($gptError)?></div><?php endif;?>

      <div class="gpt-setup-steps">
        <span class="<?= $gptClient?'done':'' ?>"><b>1</b>Credenciales</span>
        <span class="<?= $gptRedirect?'done':'' ?>"><b>2</b>Callback</span>
        <span class="<?= $gptShareUrl?'done':'' ?>"><b>3</b>Crear el GPT</span>
        <span class="<?= $gptShareUrl?'done':'' ?>"><b>4</b>Usar en Android</span>
      </div>

      <?php if(!$gptClient):?>
        <div class="gpt-empty-setup"><div><strong>Primero genera las credenciales OAuth</strong><p>ChatGPT las utilizará para conectar cada cuenta EPL de manera segura.</p></div>
          <form method="post"><input type="hidden" name="csrf" value="<?=epl_h($_SESSION['mcp_admin_csrf'])?>"><input type="hidden" name="action" value="gpt_generate"><button type="submit">Generar credenciales GPT</button></form>
        </div>
      <?php else:?>
        <div class="gpt-config-grid">
          <div class="gpt-config-item wide"><small>URL del esquema OpenAPI</small><code id="gptSchema"><?=epl_h(epl_gpt_url('openapi.php'))?></code><button type="button" onclick="copyMcpValue('gptSchema',this)">Copiar</button></div>
          <div class="gpt-config-item"><small>URL de autorización</small><code id="gptAuthUrl"><?=epl_h(epl_gpt_url('oauth/authorize.php'))?></code><button type="button" onclick="copyMcpValue('gptAuthUrl',this)">Copiar</button></div>
          <div class="gpt-config-item"><small>URL del token</small><code id="gptTokenUrl"><?=epl_h(epl_gpt_url('oauth/token.php'))?></code><button type="button" onclick="copyMcpValue('gptTokenUrl',this)">Copiar</button></div>
          <div class="gpt-config-item"><small>Client ID</small><code id="gptClientId"><?=epl_h($gptClient['client_id'])?></code><button type="button" onclick="copyMcpValue('gptClientId',this)">Copiar</button></div>
          <div class="gpt-config-item <?=!empty($gptCredentials['client_secret'])?'secret':''?>"><small>Client Secret</small>
            <?php if(!empty($gptCredentials['client_secret'])):?><code id="gptClientSecret"><?=epl_h($gptCredentials['client_secret'])?></code><button type="button" onclick="copyMcpValue('gptClientSecret',this)">Copiar ahora</button>
            <?php else:?><code>••••••••••••••••••••</code><span class="gpt-once">Se mostró una sola vez</span><?php endif;?>
          </div>
          <div class="gpt-config-item"><small>Scope</small><code id="gptScope">epl.read epl.write</code><button type="button" onclick="copyMcpValue('gptScope',this)">Copiar</button></div>
          <div class="gpt-config-item"><small>Método del token</small><code>POST</code></div>
          <div class="gpt-config-item wide"><small>URL de política de privacidad</small><code id="gptPrivacy"><?=epl_h(epl_url('privacidad_ia.php'))?></code><button type="button" onclick="copyMcpValue('gptPrivacy',this)">Copiar</button></div>
        </div>

        <div class="gpt-callback-box">
          <div><strong>Callback entregado por ChatGPT</strong><p>Después de configurar OAuth en el editor del GPT, copia la URL de devolución exacta y guárdala aquí.</p></div>
          <form method="post"><input type="hidden" name="csrf" value="<?=epl_h($_SESSION['mcp_admin_csrf'])?>"><input type="hidden" name="action" value="gpt_redirect"><input type="hidden" name="client_id" value="<?=epl_h($gptClient['client_id'])?>"><input type="url" name="redirect_uri" value="<?=epl_h($gptRedirect)?>" placeholder="https://chatgpt.com/aip/.../oauth/callback" required><button type="submit">Guardar callback</button></form>
        </div>

        <div class="gpt-callback-box">
          <div><strong>Enlace para instalarlo en Android</strong><p>Cuando publiques el GPT como “Cualquiera con el enlace”, pega aquí la dirección que entrega ChatGPT.</p></div>
          <form method="post"><input type="hidden" name="csrf" value="<?=epl_h($_SESSION['mcp_admin_csrf'])?>"><input type="hidden" name="action" value="gpt_share"><input type="hidden" name="client_id" value="<?=epl_h($gptClient['client_id'])?>"><input type="url" name="gpt_share_url" value="<?=epl_h($gptShareUrl)?>" placeholder="https://chatgpt.com/g/g-..." required><button type="submit">Guardar enlace</button></form>
        </div>

        <details class="gpt-builder-help" <?=!$gptRedirect?'open':''?>>
          <summary>Ver instrucciones para crear el GPT en ChatGPT</summary>
          <ol><li>En ChatGPT web abre <strong>Explorar GPTs → Crear</strong>.</li><li>Nombre: <strong>Elite Padel League</strong>.</li><li>En <strong>Acciones</strong>, importa la URL del esquema OpenAPI indicada arriba.</li><li>En Autenticación selecciona <strong>OAuth</strong> e ingresa las URLs, Client ID, Client Secret, scope y método POST.</li><li>Ingresa también la URL de política de privacidad mostrada arriba.</li><li>Copia el callback que muestra ChatGPT, vuelve a esta pantalla y guárdalo.</li><li>Prueba “¿Quién soy?” y luego comparte el GPT mediante enlace.</li></ol>
          <label for="gptInstructions">Instrucciones del GPT</label>
          <textarea id="gptInstructions" readonly>Eres el asistente oficial de Elite Padel League (EPL). Usa las Actions para responder con datos reales y nunca inventes partidos, fechas, recintos ni resultados. Al comenzar una conversación usa obtenerMiPerfilEPL para identificar el rol y los permisos. Un jugador solo puede consultar sus partidos; un club solo sus ligas asignadas; un administrador puede consultar y gestionar todo. Para cualquier modificación, primero muestra un resumen exacto del cambio y pide confirmación explícita. Solo después de recibir una respuesta afirmativa llama la Action con confirmar=true. Si falta un dato, pregunta antes de ejecutar. Nunca solicites contraseñas ni expongas identificadores técnicos innecesarios.</textarea>
          <div class="gpt-builder-actions"><button type="button" onclick="copyMcpValue('gptInstructions',this)">Copiar instrucciones</button><a href="https://chatgpt.com/gpts/editor" target="_blank" rel="noopener">Abrir creador de GPT</a></div>
        </details>

        <form method="post" class="gpt-regenerate" data-confirm="Regenerar las credenciales desconectará el GPT configurado actualmente. ¿Continuar?"><input type="hidden" name="csrf" value="<?=epl_h($_SESSION['mcp_admin_csrf'])?>"><input type="hidden" name="action" value="gpt_generate"><button type="submit">Regenerar credenciales</button></form>
      <?php endif;?>
    </section>

    <section class="mcp-url-box">
      <div><small>URL MCP para Claude y Gemini</small><code id="adminMcpUrl"><?=epl_h(epl_mcp_base_url().'/')?></code></div>
      <button type="button" onclick="copyAdminMcp(this)">Copiar URL</button>
    </section>

    <div class="mcp-kpis">
      <div><span><?=count($users)?></span><small>Usuarios activos</small></div>
      <div><span><?=count(array_filter($users,fn($u)=>(bool)$u['mcp_habilitado']))?></span><small>Habilitados</small></div>
      <div><span><?=array_sum(array_map(fn($u)=>(int)$u['conexiones'],$users))?></span><small>Conexiones activas</small></div>
      <div><span><?=count($audit)?></span><small>Acciones recientes</small></div>
    </div>

    <section class="mcp-users-card">
      <header><div><h2>Usuarios</h2><p>Busca una persona y habilita o revoca su acceso.</p></div></header>
      <div class="mcp-filters">
        <input type="search" id="mcpSearch" placeholder="Buscar por nombre o correo…" oninput="filterMcpUsers()">
        <select id="mcpRole" onchange="filterMcpUsers()"><option value="">Todos los roles</option><option value="admin">Administradores</option><option value="club">Clubes</option><option value="jugador">Jugadores</option></select>
        <select id="mcpAccess" onchange="filterMcpUsers()"><option value="">Todos los accesos</option><option value="on">Habilitados</option><option value="off">No habilitados</option></select>
      </div>
      <div class="mcp-user-grid" id="mcpUserGrid">
      <?php foreach($users as $u): $on=(bool)$u['mcp_habilitado']; ?>
        <article class="mcp-user" data-search="<?=epl_h(mb_strtolower(trim($u['nombre'].' '.$u['apellido'].' '.$u['email'])))?>" data-role="<?=epl_h($u['rol'])?>" data-access="<?=$on?'on':'off'?>">
          <div class="mcp-user-top"><div class="mcp-avatar"><?=epl_h(mb_strtoupper(mb_substr($u['nombre'],0,1).mb_substr($u['apellido'],0,1)))?></div><div class="mcp-user-who"><strong><?=epl_h(trim($u['nombre'].' '.$u['apellido']))?></strong><small><?=epl_h($u['email'])?></small></div><span class="mcp-state <?=$on?'on':'off'?>"><?=$on?'Habilitado':'Sin acceso'?></span></div>
          <div class="mcp-user-meta"><span><b><?=epl_h(ucfirst($u['rol']))?></b>Rol</span><span><b><?=(int)$u['conexiones']?></b>Conexiones</span><span><b><?=$u['ultimo_uso']?date('d/m H:i',strtotime($u['ultimo_uso'])):'—'?></b>Último uso</span></div>
          <div class="mcp-user-actions"><form method="post"><input type="hidden" name="csrf" value="<?=epl_h($_SESSION['mcp_admin_csrf'])?>"><input type="hidden" name="action" value="toggle"><input type="hidden" name="jugador_id" value="<?=$u['id']?>"><input type="hidden" name="habilitado" value="<?=$on?'0':'1'?>"><button class="mcp-toggle <?=$on?'disable':'enable'?>" type="submit"><?=$on?'Deshabilitar acceso':'Habilitar acceso'?></button></form>
          <?php if((int)$u['conexiones']>0):?><form method="post"><input type="hidden" name="csrf" value="<?=epl_h($_SESSION['mcp_admin_csrf'])?>"><input type="hidden" name="action" value="revoke"><input type="hidden" name="jugador_id" value="<?=$u['id']?>"><button class="mcp-revoke" type="submit">Cerrar sesiones</button></form><?php endif;?></div>
        </article>
      <?php endforeach; ?>
      </div>
      <div id="mcpNoResults" class="mcp-empty" hidden>No encontramos usuarios con esos filtros.</div>
    </section>

    <details class="mcp-audit"><summary>Ver últimas acciones de IA <span><?=count($audit)?></span></summary><div class="mcp-audit-list">
      <?php if(!$audit):?><p class="mcp-empty">Todavía no hay acciones registradas.</p><?php endif;?>
      <?php foreach($audit as $a):?><div class="mcp-audit-row"><span class="mcp-audit-icon"><?=$a['success']?'✓':'!'?></span><div><strong><?=epl_h($a['usuario'])?></strong> usó <code><?=epl_h($a['tool_name'])?></code><small><?=date('d/m/Y H:i',strtotime($a['created_at']))?> · <?=epl_h(($a['target_type']?:'consulta').($a['target_id']?' #'.$a['target_id']:''))?></small></div><span class="mcp-audit-result <?=$a['success']?'ok':'bad'?>"><?=$a['success']?'Correcto':'Error'?></span></div><?php endforeach; ?>
    </div></details>
  </main>
</div>
<script>
function copyAdminMcp(btn){navigator.clipboard.writeText(document.getElementById('adminMcpUrl').textContent.trim()).then(function(){btn.textContent='¡Copiado!';setTimeout(function(){btn.textContent='Copiar URL'},1400)})}
function copyMcpValue(id,btn){var el=document.getElementById(id),value=('value' in el?el.value:el.textContent).trim();navigator.clipboard.writeText(value).then(function(){var old=btn.textContent;btn.textContent='✓ Copiado';setTimeout(function(){btn.textContent=old},1400)})}
function filterMcpUsers(){var q=document.getElementById('mcpSearch').value.toLowerCase().trim(),r=document.getElementById('mcpRole').value,a=document.getElementById('mcpAccess').value,n=0;document.querySelectorAll('.mcp-user').forEach(function(el){var ok=(!q||el.dataset.search.includes(q))&&(!r||el.dataset.role===r)&&(!a||el.dataset.access===a);el.hidden=!ok;if(ok)n++});document.getElementById('mcpNoResults').hidden=n>0}
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
