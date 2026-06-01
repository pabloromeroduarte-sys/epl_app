<?php
// Determinar contexto del chat de forma segura
if (!isset($chat_context)) {
    $j_actual = epl_jugador_actual();
    if (strpos($_SERVER['REQUEST_URI'], '/admin/') !== false) {
        $chat_context = ($j_actual && $j_actual['rol'] === 'admin') ? 'admin' : 'player';
    } else {
        $chat_context = $j_actual ? 'player' : 'public';
    }
}
?>
<!-- ── Asistente EPL — widget flotante ─────────────────────── -->
<style>
/* ── Botón flotante ── */
#epl-chat-btn {
  position: fixed !important;
  right: 20px !important;
  bottom: 88px !important; /* sobre bottom-nav en móvil */
  width: 52px !important; height: 52px !important;
  border-radius: 50% !important;
  background: linear-gradient(135deg,#1c2f48,#1a3a64) !important;
  color: #C9A762 !important;
  border: 2px solid #C9A762 !important;
  box-shadow: 0 4px 18px rgba(0,0,0,.35) !important;
  cursor: pointer !important;
  display: flex !important; align-items: center !important; justify-content: center !important;
  z-index: 9990 !important;
  transition: transform .2s, box-shadow .2s;
  padding: 0 !important;
  visibility: visible !important;
  opacity: 1 !important;
}
#epl-chat-btn:hover { transform: scale(1.08); box-shadow: 0 6px 24px rgba(0,0,0,.45); }
#epl-chat-btn svg   { width: 24px !important; height: 24px !important; }

/* Contador de mensajes no leídos */
#epl-chat-badge {
  position: absolute;
  top: -4px; right: -4px;
  background: #ef4444; color: #fff;
  font-size: 10px; font-weight: 800;
  border-radius: 999px;
  min-width: 18px; height: 18px;
  display: none; align-items: center; justify-content: center;
  padding: 0 4px;
}

@media (min-width: 993px) {
  #epl-chat-btn { bottom: 28px !important; right: 28px !important; }
}

/* ── Panel de chat ── */
#epl-chat-panel {
  position: fixed;
  right: 16px;
  bottom: 150px;
  width: min(360px, calc(100vw - 32px));
  max-height: min(540px, calc(100vh - 180px));
  background: #fff;
  border-radius: 18px;
  box-shadow: 0 8px 40px rgba(0,0,0,.22);
  display: flex; flex-direction: column;
  z-index: 1099;
  overflow: hidden;
  transform: scale(.9) translateY(20px);
  opacity: 0;
  pointer-events: none;
  transition: transform .25s cubic-bezier(.34,1.56,.64,1), opacity .2s;
}
#epl-chat-panel.open {
  transform: scale(1) translateY(0);
  opacity: 1;
  pointer-events: all;
}
@media (min-width: 993px) {
  #epl-chat-panel { bottom: 94px; right: 28px; }
}

/* Header */
#epl-chat-header {
  background: linear-gradient(135deg,#1c2f48,#1a3a64);
  padding: .85rem 1rem;
  display: flex; align-items: center; gap: .65rem;
  flex-shrink: 0;
}
.epl-chat-avatar {
  width: 34px; height: 34px; border-radius: 50%;
  background: #C9A762;
  display: flex; align-items: center; justify-content: center;
  font-size: 18px; flex-shrink: 0;
}
#epl-chat-header h3 {
  flex: 1; margin: 0;
  color: #fff; font-size: .88rem; font-weight: 800;
  letter-spacing: .03em;
}
#epl-chat-header p {
  margin: .1rem 0 0; color: #93c5fd; font-size: .7rem;
}
#epl-chat-close {
  background: none; border: none; color: rgba(255,255,255,.6);
  cursor: pointer; padding: 4px; border-radius: 6px;
  display: flex; align-items: center; transition: color .15s;
}
#epl-chat-close:hover { color: #fff; }

/* Mensajes */
#epl-chat-messages {
  flex: 1; overflow-y: auto;
  padding: .85rem 1rem;
  display: flex; flex-direction: column; gap: .65rem;
  scroll-behavior: smooth;
}
#epl-chat-messages::-webkit-scrollbar { width: 4px; }
#epl-chat-messages::-webkit-scrollbar-track { background: transparent; }
#epl-chat-messages::-webkit-scrollbar-thumb { background: #e2e8f0; border-radius: 4px; }

.epl-msg {
  display: flex; flex-direction: column;
  max-width: 85%;
  animation: msgIn .2s ease;
}
@keyframes msgIn { from { opacity:0; transform:translateY(6px) } to { opacity:1; transform:none } }

.epl-msg.bot  { align-self: flex-start; }
.epl-msg.user { align-self: flex-end;   }

.epl-msg-bubble {
  padding: .55rem .8rem;
  border-radius: 14px;
  font-size: .82rem; line-height: 1.5;
  color: #1c2f48;
}
.epl-msg.bot  .epl-msg-bubble { background: #f1f5f9; border-bottom-left-radius: 4px; }
.epl-msg.user .epl-msg-bubble { background: #1c2f48; color: #C9A762; border-bottom-right-radius: 4px; font-weight: 600; }

/* Negrita dentro del bubble del bot */
.epl-msg.bot .epl-msg-bubble strong { color: #1c2f48; font-weight: 800; }

/* Link de navegación */
.epl-msg-link {
  display: inline-flex; align-items: center; gap: .35rem;
  margin-top: .45rem;
  background: #C9A762; color: #1c2f48;
  font-size: .75rem; font-weight: 900;
  padding: .4rem .85rem;
  border-radius: 999px;
  text-decoration: none;
  align-self: flex-start;
  text-transform: uppercase; letter-spacing: .04em;
  transition: background .15s;
}
.epl-msg-link:hover { background: #b8934f; }

/* Indicador "escribiendo" */
.epl-typing .epl-msg-bubble {
  display: flex; gap: 4px; align-items: center; padding: .65rem .85rem;
}
.epl-typing .dot {
  width: 6px; height: 6px; border-radius: 50%;
  background: #94a3b8;
  animation: bounce .9s infinite;
}
.epl-typing .dot:nth-child(2) { animation-delay: .18s; }
.epl-typing .dot:nth-child(3) { animation-delay: .36s; }
@keyframes bounce {
  0%,60%,100% { transform: translateY(0); }
  30%         { transform: translateY(-5px); }
}

/* Sugerencias rápidas */
#epl-chat-suggestions {
  padding: .4rem 1rem .5rem;
  display: flex; gap: .4rem; overflow-x: auto; flex-shrink: 0;
  scrollbar-width: none;
}
#epl-chat-suggestions::-webkit-scrollbar { display: none; }

.epl-sug-chip {
  flex-shrink: 0;
  background: #f1f5f9; color: #1c2f48;
  border: 1.5px solid #e2e8f0;
  border-radius: 999px;
  padding: .3rem .75rem;
  font-size: .72rem; font-weight: 700;
  cursor: pointer; white-space: nowrap;
  transition: background .15s, border-color .15s;
}
.epl-sug-chip:hover { background: #1c2f48; color: #C9A762; border-color: #1c2f48; }

/* Input */
#epl-chat-input-area {
  padding: .65rem .85rem;
  border-top: 1px solid #f1f5f9;
  display: flex; gap: .5rem; align-items: flex-end;
  flex-shrink: 0;
}
#epl-chat-input {
  flex: 1;
  border: 1.5px solid #e2e8f0;
  border-radius: 12px;
  padding: .5rem .75rem;
  font-size: .83rem; font-family: inherit;
  resize: none; min-height: 38px; max-height: 90px;
  outline: none;
  transition: border-color .2s;
  line-height: 1.4;
}
#epl-chat-input:focus { border-color: #1c2f48; }
#epl-chat-send {
  width: 38px; height: 38px;
  background: #1c2f48; color: #C9A762;
  border: none; border-radius: 10px;
  cursor: pointer; flex-shrink: 0;
  display: flex; align-items: center; justify-content: center;
  transition: background .15s;
}
#epl-chat-send:hover { background: #0f1e30; }
#epl-chat-send svg  { width: 18px; height: 18px; }
</style>

<!-- Botón flotante -->
<button id="epl-chat-btn" title="Asistente EPL" aria-label="Abrir asistente">
  <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
    <path stroke-linecap="round" stroke-linejoin="round"
      d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/>
  </svg>
  <span id="epl-chat-badge"></span>
</button>

<!-- Panel de chat -->
<div id="epl-chat-panel" role="dialog" aria-label="Asistente EPL">
  <div id="epl-chat-header">
    <div class="epl-chat-avatar">🎾</div>
    <div>
      <h3>Asistente EPL</h3>
      <p>Respondo al instante</p>
    </div>
    <button id="epl-chat-close" aria-label="Cerrar">
      <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
      </svg>
    </button>
  </div>

  <div id="epl-chat-messages"></div>

  <div id="epl-chat-suggestions"></div>

  <div id="epl-chat-input-area">
    <textarea id="epl-chat-input" placeholder="Escribe tu pregunta…" rows="1" maxlength="300"></textarea>
    <button id="epl-chat-send" aria-label="Enviar">
      <svg fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/>
      </svg>
    </button>
  </div>
</div>

<!-- Modal de Asistente de Registro de Resultados -->
<div id="epl-asist-modal" style="display:none;position:fixed;inset:0;background:rgba(10,20,33,.78);backdrop-filter:blur(6px);z-index:99999;align-items:center;justify-content:center;padding:1rem">
  <div style="background:#fff;border-radius:18px;width:100%;max-width:440px;box-shadow:0 20px 60px rgba(0,0,0,.3);overflow:hidden;animation:ir-pop .3s ease both">
    <!-- Header -->
    <div style="background:linear-gradient(135deg,#1c2f48,#0f1e30);padding:1.1rem 1.4rem;color:#fff;display:flex;justify-content:space-between;align-items:center">
      <h3 style="margin:0;font-family:'Anton',sans-serif;font-size:1.05rem;text-transform:uppercase;color:#C9A762;letter-spacing:.03em">📝 Registrar Marcador</h3>
      <button onclick="window.closeAsistModal()" style="background:none;border:none;color:rgba(255,255,255,.6);font-size:1.6rem;cursor:pointer;line-height:1;padding:0 .3rem">&times;</button>
    </div>
    
    <!-- Form -->
    <form id="epl-asist-form" onsubmit="window.submitAsistResult(event)" style="padding:1.25rem 1.4rem;display:flex;flex-direction:column;gap:1rem">
      <div id="epl-asist-error" style="display:none;background:#fee2e2;border-left:4px solid #dc2626;padding:.65rem .85rem;border-radius:8px;font-size:.8rem;color:#991b1b;font-weight:700"></div>
      
      <!-- Match Selector inside Modal -->
      <div style="display:flex;flex-direction:column;gap:.4rem">
        <label style="font-size:.7rem;font-weight:800;text-transform:uppercase;color:#64748b;letter-spacing:.05em">Selecciona el Partido</label>
        <select id="epl-asist-match-select" onchange="window.updateAsistModalTeams(this)" style="width:100%;border:1.5px solid #cbd5e1;border-radius:10px;padding:.55rem;font-size:.82rem;outline:none;font-weight:700;color:#1c2f48;background:#fff"></select>
      </div>

      <!-- Teams Display -->
      <div style="display:grid;grid-template-columns:1fr auto 1fr;align-items:center;gap:.75rem;background:#f8fafc;border:1px solid #e2e8f0;border-radius:14px;padding:.9rem 1.1rem;text-align:center">
        <div id="epl-asist-local-name" style="font-weight:800;font-size:.82rem;color:#1c2f48;text-transform:uppercase;word-break:break-word">Local</div>
        <div style="background:linear-gradient(135deg,#c9a762,#b8934f);color:#1c2f48;font-size:.58rem;font-weight:800;border-radius:12px;padding:.2rem .5rem">VS</div>
        <div id="epl-asist-visitante-name" style="font-weight:800;font-size:.82rem;color:#1c2f48;text-transform:uppercase;word-break:break-word">Visitante</div>
      </div>

      <!-- Sets Inputs -->
      <div style="display:flex;flex-direction:column;gap:.75rem">
        <label style="font-size:.7rem;font-weight:800;text-transform:uppercase;color:#64748b;letter-spacing:.05em">Marcador por Set</label>
        
        <!-- Set 1 -->
        <div style="display:flex;align-items:center;justify-content:space-between;border-bottom:1px solid #f1f5f9;padding-bottom:.5rem">
          <span style="font-size:.78rem;font-weight:800;color:#1c2f48;background:#f1f5f9;padding:.2rem .5rem;border-radius:6px">Set 1</span>
          <div style="display:flex;align-items:center;gap:.5rem">
            <input type="number" name="s1_local" id="asist_s1_local" min="0" max="7" placeholder="0" required style="width:50px;height:45px;border:1.5px solid #cbd5e1;border-radius:8px;font-size:1.2rem;font-weight:700;text-align:center;color:#1c2f48">
            <span style="color:#94a3b8;font-weight:800">—</span>
            <input type="number" name="s1_visitante" id="asist_s1_visitante" min="0" max="7" placeholder="0" required style="width:50px;height:45px;border:1.5px solid #cbd5e1;border-radius:8px;font-size:1.2rem;font-weight:700;text-align:center;color:#1c2f48">
          </div>
        </div>

        <!-- Set 2 -->
        <div style="display:flex;align-items:center;justify-content:space-between;border-bottom:1px solid #f1f5f9;padding-bottom:.5rem">
          <span style="font-size:.78rem;font-weight:800;color:#1c2f48;background:#f1f5f9;padding:.2rem .5rem;border-radius:6px">Set 2</span>
          <div style="display:flex;align-items:center;gap:.5rem">
            <input type="number" name="s2_local" id="asist_s2_local" min="0" max="7" placeholder="0" required style="width:50px;height:45px;border:1.5px solid #cbd5e1;border-radius:8px;font-size:1.2rem;font-weight:700;text-align:center;color:#1c2f48">
            <span style="color:#94a3b8;font-weight:800">—</span>
            <input type="number" name="s2_visitante" id="asist_s2_visitante" min="0" max="7" placeholder="0" required style="width:50px;height:45px;border:1.5px solid #cbd5e1;border-radius:8px;font-size:1.2rem;font-weight:700;text-align:center;color:#1c2f48">
          </div>
        </div>

        <!-- Set 3 -->
        <div style="display:flex;align-items:center;justify-content:space-between">
          <div style="display:flex;align-items:center;gap:.35rem">
            <span style="font-size:.78rem;font-weight:800;color:#1c2f48;background:#f1f5f9;padding:.2rem .5rem;border-radius:6px">Set 3</span>
            <span style="font-size:.58rem;font-weight:700;color:#94a3b8;background:#f1f5f9;padding:.1rem .3rem;border-radius:4px;text-transform:uppercase">opcional</span>
          </div>
          <div style="display:flex;align-items:center;gap:.5rem">
            <input type="number" name="s3_local" id="asist_s3_local" min="0" max="7" placeholder="0" style="width:50px;height:45px;border:1.5px solid #cbd5e1;border-radius:8px;font-size:1.2rem;font-weight:700;text-align:center;color:#1c2f48">
            <span style="color:#94a3b8;font-weight:800">—</span>
            <input type="number" name="s3_visitante" id="asist_s3_visitante" min="0" max="7" placeholder="0" style="width:50px;height:45px;border:1.5px solid #cbd5e1;border-radius:8px;font-size:1.2rem;font-weight:700;text-align:center;color:#1c2f48">
          </div>
        </div>
      </div>

      <!-- Action Button -->
      <button type="submit" id="epl-asist-submit-btn" style="width:100%;background:linear-gradient(135deg,#1c2f48,#1a3a64);color:#fff;border:none;border-radius:12px;padding:.85rem;font-size:.85rem;font-weight:800;text-transform:uppercase;letter-spacing:.05em;cursor:pointer;transition:all .2s;margin-top:.5rem;display:flex;align-items:center;justify-content:center;gap:.5rem">
        ✓ Confirmar Marcador
      </button>
    </form>
  </div>
</div>

<script>
(function () {
  'use strict';
  console.log('[EPL Asistente] widget cargado');

  const btn      = document.getElementById('epl-chat-btn');
  const panel    = document.getElementById('epl-chat-panel');
  const closeBtn = document.getElementById('epl-chat-close');
  const msgArea  = document.getElementById('epl-chat-messages');
  const sugArea  = document.getElementById('epl-chat-suggestions');
  const input    = document.getElementById('epl-chat-input');
  const sendBtn  = document.getElementById('epl-chat-send');
  const badge    = document.getElementById('epl-chat-badge');

  let unread   = 0;
  let isOpen   = false;
  let loading  = false;

  // ── Abrir / cerrar ──────────────────────────────────────────
  function open() {
    isOpen = true;
    panel.classList.add('open');
    unread = 0;
    badge.style.display = 'none';
    badge.textContent = '';
    setTimeout(() => input.focus(), 250);
    if (msgArea.childElementCount === 0) welcome();
  }
  function close() {
    isOpen = false;
    panel.classList.remove('open');
  }

  btn.addEventListener('click', () => isOpen ? close() : open());
  closeBtn.addEventListener('click', close);

  // Cerrar al click fuera — usa composedPath para no fallar si el elemento
  // fue removido del DOM antes de que el listener evalúe (ej: chips de sugerencias)
  document.addEventListener('click', e => {
    if (!isOpen) return;
    const path = e.composedPath ? e.composedPath() : [];
    if (!path.includes(panel) && !path.includes(btn)) {
      close();
    }
  });

  // ── Mensaje de bienvenida ───────────────────────────────────
  function welcome() {
    const context = <?= json_encode($chat_context) ?>;
    if (context === 'admin') {
      addBotMsg(
        '¡Hola, Administrador! 👋 Soy tu asistente de *Elite Padel League*. Puedo ayudarte con dudas sobre gestión de ligas, partidos, jugadores, finanzas y reprogramaciones. ¿Qué deseas consultar?',
        null,
        ['¿Cómo edito un resultado?','¿Cómo apruebo una inscripción?','¿Cómo gestiono reprogramaciones?','¿Cómo envío notificaciones masivas?']
      );
    } else if (context === 'public') {
      addBotMsg(
        '¡Hola! 👋 Soy el asistente de *Elite Padel League*. Estoy aquí para ayudarte a registrarte, inscribirte en torneos o resolver dudas generales de la liga. ¿En qué te puedo colaborar?',
        null,
        ['¿Cómo me registro?','¿Cómo me inscribo a un torneo?','¿Qué pasa si no tengo pareja?','No me llegó la contraseña']
      );
    } else {
      addBotMsg(
        '¡Hola! 👋 Soy el asistente de *Elite Padel League*. ¿En qué puedo ayudarte?',
        null,
        ['¿Cómo registro un resultado?','¿Cómo veo mis partidos?','¿Cómo activo notificaciones?','¿Cómo reprogramo?']
      );
    }
  }

  // ── Renderizar mensaje del bot ──────────────────────────────
  function addBotMsg(texto, link, sugerencias) {
    // Render markdown básico: *texto* → <em>, **texto** → <strong>, \n → <br>
    function md(s) {
      return s
        .replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>')
        .replace(/\*(.+?)\*/g,   '<em>$1</em>')
        .replace(/\\n/g, '<br>')
        .replace(/\n/g,  '<br>');
    }
    const wrap = document.createElement('div');
    wrap.className = 'epl-msg bot';

    const bubble = document.createElement('div');
    bubble.className = 'epl-msg-bubble';
    bubble.innerHTML = md(texto);
    wrap.appendChild(bubble);

    if (link) {
      const a = document.createElement('a');
      a.className = 'epl-msg-link';
      a.href = link.url;
      a.textContent = link.texto;
      wrap.appendChild(a);
    }

    msgArea.appendChild(wrap);
    scrollBottom();
    setSugerencias(sugerencias || []);
    if (!isOpen) showBadge();
  }

  function addUserMsg(texto) {
    const wrap   = document.createElement('div');
    wrap.className = 'epl-msg user';
    const bubble = document.createElement('div');
    bubble.className = 'epl-msg-bubble';
    bubble.textContent = texto;
    wrap.appendChild(bubble);
    msgArea.appendChild(wrap);
    scrollBottom();
    setSugerencias([]);
  }

  // ── Indicador "escribiendo" ─────────────────────────────────
  function showTyping() {
    const wrap = document.createElement('div');
    wrap.className = 'epl-msg bot epl-typing';
    wrap.id = 'epl-typing-indicator';
    const bubble = document.createElement('div');
    bubble.className = 'epl-msg-bubble';
    bubble.innerHTML = '<span class="dot"></span><span class="dot"></span><span class="dot"></span>';
    wrap.appendChild(bubble);
    msgArea.appendChild(wrap);
    scrollBottom();
  }
  function hideTyping() {
    const el = document.getElementById('epl-typing-indicator');
    if (el) el.remove();
  }

  // ── Sugerencias ─────────────────────────────────────────────
  function setSugerencias(list) {
    sugArea.innerHTML = '';
    list.forEach(txt => {
      const chip = document.createElement('button');
      chip.className = 'epl-sug-chip';
      chip.textContent = txt;
      chip.addEventListener('click', () => enviar(txt));
      sugArea.appendChild(chip);
    });
  }

  // ── Badge ───────────────────────────────────────────────────
  function showBadge() {
    unread++;
    badge.textContent = unread > 9 ? '9+' : unread;
    badge.style.display = 'flex';
  }

  // ── Scroll al fondo ─────────────────────────────────────────
  function scrollBottom() {
    msgArea.scrollTop = msgArea.scrollHeight;
  }

  // ── Enviar mensaje ──────────────────────────────────────────
  function enviar(texto) {
    texto = texto.trim();
    if (!texto || loading) return;
    loading = true;
    addUserMsg(texto);
    input.value = '';
    input.style.height = 'auto';
    showTyping();

    const fd = new FormData();
    fd.append('pregunta', texto);
    fd.append('context', <?= json_encode($chat_context) ?>);

    fetch('/api_asistente.php', { method: 'POST', body: fd })
      .then(r => r.json())
      .then(data => {
        hideTyping();
        addBotMsg(data.respuesta, data.link || null, data.sugerencias || []);
        
        if (data.action === 'show_result_modal') {
          window.openAsistModal(data.partidos, data.default_partido_id);
        }
      })
      .catch(() => {
        hideTyping();
        addBotMsg('Hubo un error al conectar. Intenta de nuevo 😅', null, []);
      })
      .finally(() => { loading = false; });
  }

  sendBtn.addEventListener('click', () => enviar(input.value));

  input.addEventListener('keydown', e => {
    if (e.key === 'Enter' && !e.shiftKey) {
      e.preventDefault();
      enviar(input.value);
    }
  });

  // Auto-resize del textarea
  input.addEventListener('input', () => {
    input.style.height = 'auto';
    input.style.height = Math.min(input.scrollHeight, 90) + 'px';
  });

  // ── Modal Emergente de Resultados ──────────────────────────
  window.openAsistModal = function(partidos, defaultPartidoId) {
    const modal = document.getElementById('epl-asist-modal');
    const select = document.getElementById('epl-asist-match-select');
    const errorDiv = document.getElementById('epl-asist-error');
    
    errorDiv.style.display = 'none';
    errorDiv.textContent = '';
    document.getElementById('epl-asist-form').reset();
    
    select.innerHTML = '';
    partidos.forEach(p => {
      const opt = document.createElement('option');
      opt.value = p.id;
      opt.textContent = `F.${p.jornada} — vs ${p.local_nombre === p.visitante_nombre ? 'Mismo Equipo' : (p.local_nombre)} vs ${p.visitante_nombre} (${p.fecha_programada})`;
      if (p.disabled) {
        opt.disabled = true;
        opt.textContent = `[🔒 Bloqueado] ` + opt.textContent;
      }
      opt.dataset.local = p.local_nombre;
      opt.dataset.visitante = p.visitante_nombre;
      select.appendChild(opt);
    });
    
    select.value = defaultPartidoId;
    window.updateAsistModalTeams(select);
    modal.style.display = 'flex';
  };

  window.updateAsistModalTeams = function(select) {
    const opt = select.options[select.selectedIndex];
    if (opt) {
      document.getElementById('epl-asist-local-name').textContent = opt.dataset.local;
      document.getElementById('epl-asist-visitante-name').textContent = opt.dataset.visitante;
    }
  };

  window.closeAsistModal = function() {
    document.getElementById('epl-asist-modal').style.display = 'none';
  };

  window.submitAsistResult = function(event) {
    event.preventDefault();
    const btnSubmit = document.getElementById('epl-asist-submit-btn');
    const errorDiv = document.getElementById('epl-asist-error');
    
    btnSubmit.disabled = true;
    btnSubmit.textContent = '⏳ Registrando resultado...';
    errorDiv.style.display = 'none';
    
    const formData = new FormData(document.getElementById('epl-asist-form'));
    formData.append('action', 'submit_result');
    formData.append('context', 'player');
    formData.append('partido_id', document.getElementById('epl-asist-match-select').value);
    
    fetch('/api_asistente.php', {
      method: 'POST',
      body: formData
    })
    .then(r => r.json())
    .then(data => {
      if (data.success) {
        window.closeAsistModal();
        if (typeof hideTyping === 'function') hideTyping();
        if (typeof addBotMsg === 'function') {
          addBotMsg(data.respuesta, null, data.sugerencias || []);
        }
        
        const t = document.createElement('div');
        t.textContent = '✅ Marcador registrado. Recargando página...';
        t.style.cssText = 'position:fixed;bottom:80px;left:50%;transform:translateX(-50%);background:#15803d;color:#fff;padding:.75rem 1.25rem;border-radius:10px;font-weight:700;font-size:.85rem;z-index:999999;box-shadow:0 8px 24px rgba(0,0,0,.2);max-width:90vw;text-align:center';
        document.body.appendChild(t);
        
        setTimeout(() => {
          location.reload();
        }, 2200);
      } else {
        errorDiv.textContent = '❌ ' + (data.error || 'Hubo un error al registrar.');
        errorDiv.style.display = 'block';
        btnSubmit.disabled = false;
        btnSubmit.textContent = '✓ Confirmar Marcador';
      }
    })
    .catch(err => {
      errorDiv.textContent = '❌ Error de conexión al servidor.';
      errorDiv.style.display = 'block';
      btnSubmit.disabled = false;
      btnSubmit.textContent = '✓ Confirmar Marcador';
    });
  };

})();
</script>
