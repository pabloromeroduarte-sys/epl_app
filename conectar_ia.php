<?php
declare(strict_types=1);

$page_title = 'Conectar Inteligencia Artificial';
$player_tab = 'conectar_ia';
$page_css = 'mcp-guide';

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/mcp.php';
require_once __DIR__ . '/includes/gpt_actions.php';

epl_require_login();
epl_mcp_ensure_schema();

$jugador = epl_jugador_actual();
$st = epl_db()->prepare('SELECT mcp_habilitado FROM jugadores WHERE id = ?');
$st->execute([(int)$jugador['id']]);
$mcp_habilitado = (bool)$st->fetchColumn();
$mcp_url = epl_mcp_base_url() . '/';
$gpt_client = epl_gpt_active_client();
$gpt_share_url = $gpt_client ? trim((string)($gpt_client['gpt_share_url'] ?? '')) : '';

require_once __DIR__ . '/includes/header.php';
?>
<div class="dash-layout">
  <?php include __DIR__ . '/includes/player_sidebar.php'; ?>

  <main class="dash-main ai-main">
    <section class="ai-hero">
      <div class="ai-hero-copy">
        <span class="ai-kicker">EPL EN TU TELÉFONO</span>
        <h1>Habla con la liga desde tu IA favorita</h1>
        <p>Conecta tu cuenta una sola vez y luego pregunta por tus partidos, resultados o reprogramaciones desde ChatGPT, Claude o Gemini.</p>
      </div>
      <div class="ai-status <?= $mcp_habilitado ? 'is-on' : 'is-off' ?>">
        <span class="ai-status-dot"></span>
        <div>
          <small>Estado de tu cuenta</small>
          <strong><?= $mcp_habilitado ? 'Lista para conectar' : 'Falta habilitación' ?></strong>
        </div>
      </div>
    </section>

    <?php if (!$mcp_habilitado): ?>
      <div class="ai-alert">
        <span>!</span>
        <div><strong>Antes de conectarte</strong><br>Pide al administrador de EPL que habilite tu acceso a Inteligencia Artificial.</div>
      </div>
    <?php endif; ?>

    <section class="ai-start-card <?= $gpt_share_url ? 'chatgpt-ready' : '' ?>">
      <div class="ai-start-number">GPT</div>
      <div class="ai-start-copy">
        <span>CHATGPT ANDROID</span>
        <h2><?= $gpt_share_url ? 'Abre el asistente EPL en ChatGPT' : 'El asistente de ChatGPT está en preparación' ?></h2>
        <p><?= $gpt_share_url ? 'Usa el enlace con la misma cuenta de tu aplicación Android.' : 'El administrador publicará aquí el enlace cuando finalice la configuración.' ?></p>
      </div>
      <?php if ($gpt_share_url): ?><code id="gptShareUrl"><?= epl_h($gpt_share_url) ?></code><a class="ai-copy" href="<?= epl_h($gpt_share_url) ?>" target="_blank" rel="noopener">Abrir en ChatGPT</a>
      <?php else: ?><code>Próximamente disponible</code><button type="button" class="ai-copy" disabled>Configurando</button><?php endif; ?>
    </section>

    <section class="ai-url-card"><div><small>Dirección MCP para Claude o Gemini</small><code id="mcpUrl"><?= epl_h($mcp_url) ?></code></div><button type="button" class="ai-copy" onclick="copyMcp(this)">Copiar dirección</button></section>

    <section class="ai-guide">
      <div class="ai-guide-head">
        <div>
          <span class="ai-section-kicker">GUÍA CON PANTALLAS</span>
          <h2>Elige la aplicación que usas</h2>
          <p>Desliza las pantallas hacia el lado en tu teléfono.</p>
        </div>
        <div class="ai-platform-tabs" role="tablist" aria-label="Aplicaciones compatibles">
          <button type="button" class="ai-platform-tab active" data-platform="chatgpt" onclick="selectAiPlatform('chatgpt', this)" role="tab" aria-selected="true"><span class="ai-tab-icon chatgpt">◎</span>ChatGPT <small>Android</small></button>
          <button type="button" class="ai-platform-tab" data-platform="claude" onclick="selectAiPlatform('claude', this)" role="tab" aria-selected="false"><span class="ai-tab-icon claude">C</span>Claude</button>
          <button type="button" class="ai-platform-tab" data-platform="gemini" onclick="selectAiPlatform('gemini', this)" role="tab" aria-selected="false"><span class="ai-tab-icon gemini">✦</span>Gemini</button>
        </div>
      </div>

      <!-- CHATGPT -->
      <div class="ai-platform-panel active" data-panel="chatgpt" role="tabpanel">
        <div class="ai-platform-note">
          <span class="ai-note-icon chatgpt">◎</span>
          <div><strong>ChatGPT Android mediante GPT Actions</strong><p>No necesitas instalar un MCP. Abre el GPT oficial de EPL, conecta tu cuenta y úsalo normalmente desde la aplicación.</p></div>
          <?php if ($gpt_share_url): ?><a href="<?= epl_h($gpt_share_url) ?>" target="_blank" rel="noopener">Abrir GPT de EPL</a><?php else: ?><span class="ai-waiting-link">Enlace en preparación</span><?php endif; ?>
        </div>
        <div class="ai-screens">
          <article class="ai-screen-step">
            <header><span>1</span><div><b>Abre el enlace de EPL</b><small>Desde tu teléfono Android</small></div></header>
            <div class="device phone-device"><div class="phone-notch"></div><div class="phone-status">9:41 <span>● ◔</span></div><div class="chat-mobile"><div class="chat-title">ChatGPT <span>GPTs</span></div><div class="gpt-profile"><div class="epl-mini-logo">EPL</div><strong>Elite Padel League</strong><small>Asistente oficial</small><button>Abrir en ChatGPT</button></div></div></div>
            <p>Toca el enlace publicado por EPL y ábrelo con la misma cuenta que usas en ChatGPT Android.</p>
          </article>

          <article class="ai-screen-step">
            <header><span>2</span><div><b>Inicia una conversación</b><small>GPT Elite Padel League</small></div></header>
            <div class="device phone-device"><div class="phone-notch"></div><div class="phone-status">9:41 <span>● ◔</span></div><div class="chat-mobile"><div class="chat-title">Elite Padel League <span>•••</span></div><div class="chat-answer">Hola. Para consultar tus partidos necesito conectar tu cuenta EPL.</div><div class="chat-input"><span>¿Cuándo juego?</span><b>↑</b></div></div></div>
            <p>Escribe una consulta. La primera vez ChatGPT mostrará el botón para iniciar sesión en EPL.</p>
          </article>

          <article class="ai-screen-step">
            <header><span>3</span><div><b>Autoriza tu cuenta</b><small>Con tu acceso EPL</small></div></header>
            <div class="device phone-device">
              <div class="phone-notch"></div><div class="phone-status">9:41 <span>● ◔</span></div>
              <div class="epl-connect"><div class="epl-mini-logo">EPL</div><strong>Conectar Elite Padel League</strong><p>ChatGPT solicita acceso según tu perfil.</p><div class="epl-user">👤 <?= epl_h(trim(($jugador['nombre'] ?? '') . ' ' . ($jugador['apellido'] ?? ''))) ?></div><button>Conectar EPL</button><small>Acceso seguro mediante OAuth</small></div>
            </div>
            <p>Inicia sesión con tu cuenta EPL y presiona <b>Conectar EPL</b>.</p>
          </article>

          <article class="ai-screen-step">
            <header><span>4</span><div><b>Trabaja desde Android</b><small>Consultas y acciones EPL</small></div></header>
            <div class="device phone-device"><div class="phone-notch"></div><div class="phone-status">9:41 <span>● ◔</span></div><div class="chat-mobile"><div class="chat-title">Elite Padel League <span>•••</span></div><div class="app-pill">◎ Cuenta EPL conectada <b>✓</b></div><div class="chat-answer">Tu próximo partido es el jueves a las 20:30 en Club Central.</div><div class="chat-input"><span>Ver mis reprogramaciones</span><b>↑</b></div></div></div>
            <p>Desde ese momento puedes consultar o gestionar lo permitido por tu perfil directamente en la app.</p>
          </article>
        </div>
        <div class="ai-requirement"><strong>Importante:</strong> el GPT se crea una sola vez desde ChatGPT web. Los jugadores no configuran APIs ni credenciales; únicamente abren el enlace y autorizan su propia cuenta EPL.</div>
      </div>

      <!-- CLAUDE -->
      <div class="ai-platform-panel" data-panel="claude" role="tabpanel" hidden>
        <div class="ai-platform-note">
          <span class="ai-note-icon claude">C</span>
          <div><strong>Claude — recomendado para Android</strong><p>Agrega el conector una sola vez en <b>claude.ai</b>. Luego podrás activarlo desde la aplicación Claude en tu teléfono.</p></div>
          <a href="https://claude.ai/settings/connectors" target="_blank" rel="noopener">Abrir configuración</a>
        </div>
        <div class="ai-screens">
          <article class="ai-screen-step">
            <header><span>1</span><div><b>Abre Conectores</b><small>En claude.ai</small></div></header>
            <div class="device browser-device claude-mock"><div class="browser-bar"><i></i><i></i><i></i><span>claude.ai/settings</span></div><div class="mock-layout"><div class="mock-sidebar"><span class="mock-brand">Claude</span><i></i><i></i><b>⚙ Configuración</b></div><div class="mock-page"><small>CONFIGURACIÓN</small><strong>Conectores</strong><div class="mock-row selected"><span>Tus conectores</span><em>›</em></div><button class="mock-add">+ Agregar conector</button></div></div></div>
            <p>Entra en <b>Configuración → Conectores</b>.</p>
          </article>
          <article class="ai-screen-step">
            <header><span>2</span><div><b>Agrega EPL</b><small>Conector personalizado</small></div></header>
            <div class="device browser-device claude-mock"><div class="browser-bar"><i></i><i></i><i></i><span>Nuevo conector</span></div><div class="mock-form"><small>NOMBRE</small><div class="mock-input">Elite Padel League</div><small>URL DEL CONECTOR</small><div class="mock-input url">https://epleague.cl/mcp/</div><button>Agregar</button></div></div>
            <p>Presiona <b>Agregar conector personalizado</b> y pega la dirección EPL.</p>
          </article>
          <article class="ai-screen-step">
            <header><span>3</span><div><b>Autoriza tu cuenta</b><small>Con tu acceso EPL</small></div></header>
            <div class="device phone-device"><div class="phone-notch"></div><div class="phone-status">9:41 <span>● ◔</span></div><div class="epl-connect"><div class="epl-mini-logo">EPL</div><strong>Conectar Elite Padel League</strong><p>Claude solicita acceso según tu perfil.</p><div class="epl-user">👤 Tu cuenta EPL</div><button>Conectar EPL</button><small>Acceso seguro mediante OAuth</small></div></div>
            <p>Inicia sesión en EPL y presiona <b>Conectar EPL</b>.</p>
          </article>
          <article class="ai-screen-step">
            <header><span>4</span><div><b>Úsalo en tu teléfono</b><small>App Claude</small></div></header>
            <div class="device phone-device claude-phone"><div class="phone-notch"></div><div class="phone-status">9:41 <span>● ◔</span></div><div class="chat-mobile"><div class="chat-title">Claude <span>•••</span></div><div class="app-pill">C Elite Padel League <b>✓</b></div><div class="chat-answer">Puedo consultar tus datos de EPL.</div><div class="chat-input"><span>Muéstrame mis partidos pendientes</span><b>↑</b></div></div></div>
            <p>En un chat toca <b>Buscar y herramientas</b> y activa Elite Padel League.</p>
          </article>
        </div>
        <div class="ai-requirement"><strong>Requisito:</strong> Claude Pro, Max, Team o Enterprise. Usa la misma cuenta en web y teléfono.</div>
      </div>

      <!-- GEMINI -->
      <div class="ai-platform-panel" data-panel="gemini" role="tabpanel" hidden>
        <div class="ai-platform-note">
          <span class="ai-note-icon gemini">✦</span>
          <div><strong>Gemini</strong><p>La aplicación personalizada se agrega desde Gemini web y luego se utiliza dentro de <b>Gemini Spark</b> en el teléfono.</p></div>
          <a href="https://gemini.google.com" target="_blank" rel="noopener">Abrir configuración</a>
        </div>
        <div class="ai-screens">
          <article class="ai-screen-step">
            <header><span>1</span><div><b>Abre Aplicaciones</b><small>En Gemini web</small></div></header>
            <div class="device browser-device gemini-mock"><div class="browser-bar"><i></i><i></i><i></i><span>gemini.google.com</span></div><div class="mock-layout"><div class="mock-sidebar"><span class="mock-brand">✦ Gemini</span><i></i><i></i><b>⚙ Configuración</b></div><div class="mock-page"><small>CONFIGURACIÓN</small><strong>Aplicaciones conectadas</strong><div class="mock-row selected"><span>Apps personalizadas</span><em>›</em></div><button class="mock-add">+ Agregar aplicación</button></div></div></div>
            <p>Abre <b>Configuración → Aplicaciones conectadas</b>.</p>
          </article>
          <article class="ai-screen-step">
            <header><span>2</span><div><b>Agrega EPL</b><small>Aplicación personalizada</small></div></header>
            <div class="device browser-device gemini-mock"><div class="browser-bar"><i></i><i></i><i></i><span>Nueva aplicación</span></div><div class="mock-form"><small>URL DEL SERVIDOR MCP</small><div class="mock-input url">https://epleague.cl/mcp/</div><div class="mock-check">✓ OAuth detectado automáticamente</div><button>Siguiente</button></div></div>
            <p>Elige <b>Agregar aplicación personalizada</b>, pega la dirección y presiona Siguiente.</p>
          </article>
          <article class="ai-screen-step">
            <header><span>3</span><div><b>Autoriza tu cuenta</b><small>Con tu acceso EPL</small></div></header>
            <div class="device phone-device"><div class="phone-notch"></div><div class="phone-status">9:41 <span>● ◔</span></div><div class="epl-connect"><div class="epl-mini-logo">EPL</div><strong>Conectar Elite Padel League</strong><p>Gemini solicita acceso según tu perfil.</p><div class="epl-user">👤 Tu cuenta EPL</div><button>Conectar EPL</button><small>Acceso seguro mediante OAuth</small></div></div>
            <p>Inicia sesión en EPL y presiona <b>Conectar EPL</b>.</p>
          </article>
          <article class="ai-screen-step">
            <header><span>4</span><div><b>Úsalo en tu teléfono</b><small>Gemini Spark</small></div></header>
            <div class="device phone-device gemini-phone"><div class="phone-notch"></div><div class="phone-status">9:41 <span>● ◔</span></div><div class="chat-mobile"><div class="chat-title">✦ Gemini <span>Spark⌄</span></div><div class="app-pill">✦ Elite Padel League <b>✓</b></div><div class="chat-answer">EPL está lista para ayudarte.</div><div class="chat-input"><span>¿Tengo reprogramaciones?</span><b>↑</b></div></div></div>
            <p>Cambia a <b>Spark</b>, abre una tarea y selecciona Elite Padel League.</p>
          </article>
        </div>
        <div class="ai-requirement"><strong>Importante:</strong> Gemini Spark y las aplicaciones personalizadas pueden depender del país y del plan de Google AI.</div>
      </div>

      <p class="ai-ui-note">Las aplicaciones pueden cambiar levemente el nombre o la ubicación de sus botones después de una actualización.</p>
    </section>

    <section class="ai-examples">
      <div><span class="ai-section-kicker">YA CONECTADO</span><h2>¿Qué puedes pedirle?</h2><p>Toca una pregunta para copiarla y pégala en tu IA.</p></div>
      <div class="ai-prompts">
        <button type="button" onclick="copyPrompt(this)"><span>📅</span>¿Cuándo es mi próximo partido y dónde juego?</button>
        <button type="button" onclick="copyPrompt(this)"><span>🎾</span>Muéstrame todos mis partidos pendientes.</button>
        <button type="button" onclick="copyPrompt(this)"><span>🔄</span>¿Qué reprogramaciones tengo pendientes?</button>
        <button type="button" onclick="copyPrompt(this)"><span>✍️</span>Quiero solicitar reprogramar mi próximo partido.</button>
      </div>
      <div class="ai-permissions"><span>🔒</span><p><strong>Tus datos están protegidos.</strong> Un jugador solo puede consultar y gestionar sus propios partidos. Los administradores mantienen sus permisos administrativos.</p></div>
    </section>
  </main>
</div>

<script>
function aiCopy(text, button) {
  if (!navigator.clipboard) return;
  navigator.clipboard.writeText(text).then(function () {
    var original = button.textContent;
    button.textContent = '✓ Copiado';
    button.classList.add('copied');
    setTimeout(function () { button.textContent = original; button.classList.remove('copied'); }, 1600);
  });
}
function copyMcp(button) {
  aiCopy(document.getElementById('mcpUrl').textContent.trim(), button);
}
function copyPrompt(button) {
  aiCopy(button.textContent.trim(), button);
}
function selectAiPlatform(platform, button) {
  document.querySelectorAll('.ai-platform-tab').forEach(function (tab) {
    var active = tab.dataset.platform === platform;
    tab.classList.toggle('active', active);
    tab.setAttribute('aria-selected', active ? 'true' : 'false');
  });
  document.querySelectorAll('.ai-platform-panel').forEach(function (panel) {
    var active = panel.dataset.panel === platform;
    panel.hidden = !active;
    panel.classList.toggle('active', active);
  });
}
</script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
