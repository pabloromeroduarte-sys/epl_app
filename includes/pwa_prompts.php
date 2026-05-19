<!-- =====================================================
     EPL PWA PROMPTS
     1) Bottom sheet: instalar la app (Android / iOS)
     2) Banner: activar notificaciones
     ===================================================== -->

<!-- ── NOTIF BANNER ───────────────────────────────────── -->
<div id="epl-notif-banner" style="display:none">
  <div class="epb-inner">
    <div class="epb-icon">🔔</div>
    <p class="epb-text">Activa las notificaciones para no perderte ningún partido</p>
    <button id="epb-btn-activar" class="epb-btn-on">Activar</button>
    <button id="epb-btn-cerrar" class="epb-btn-close" aria-label="Cerrar">✕</button>
  </div>
</div>

<!-- ── INSTALL BOTTOM SHEET ──────────────────────────── -->
<div id="epl-install-sheet" style="display:none" aria-modal="true" role="dialog">
  <div class="epis-backdrop" id="epis-backdrop"></div>
  <div class="epis-sheet" id="epis-sheet">
    <div class="epis-handle"></div>

    <div class="epis-logo-wrap">
      <img src="<?= epl_url('assets/img/logo-epl-lateral.png') ?>" class="epis-logo" alt="EPL">
    </div>

    <h2 class="epis-title">Instala la app</h2>
    <p class="epis-sub">Accede más rápido, recibe notificaciones de tus partidos y úsala como una app nativa.</p>

    <!-- Android steps -->
    <div id="epis-android" class="epis-steps" style="display:none">
      <div class="epis-step">
        <div class="epis-step-num">1</div>
        <div class="epis-step-txt">Toca el menú de <strong>3 puntos</strong> (arriba a la derecha en Chrome)</div>
      </div>
      <div class="epis-step">
        <div class="epis-step-num">2</div>
        <div class="epis-step-txt">Selecciona <strong>"Instalar app"</strong> o <strong>"Añadir a pantalla de inicio"</strong></div>
      </div>
      <div class="epis-step">
        <div class="epis-step-num">3</div>
        <div class="epis-step-txt">Toca <strong>Instalar</strong> en la ventana que aparece. ¡Listo!</div>
      </div>
      <button id="epis-btn-install" class="epis-btn-main">
        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
        Instalar ahora
      </button>
    </div>

    <!-- iOS steps -->
    <div id="epis-ios" class="epis-steps" style="display:none">
      <div class="epis-step">
        <div class="epis-step-num">1</div>
        <div class="epis-step-txt">Toca el botón de <strong>compartir</strong>
          <span class="epis-share-icon">
            <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 12v8a2 2 0 002 2h12a2 2 0 002-2v-8"/><polyline points="16 6 12 2 8 6"/><line x1="12" y1="2" x2="12" y2="15"/></svg>
          </span>
          en la barra inferior de <strong>Safari</strong>
        </div>
      </div>
      <div class="epis-step">
        <div class="epis-step-num">2</div>
        <div class="epis-step-txt">Desplázate y toca <strong>"Añadir a pantalla de inicio"</strong></div>
      </div>
      <div class="epis-step">
        <div class="epis-step-num">3</div>
        <div class="epis-step-txt">Toca <strong>Añadir</strong> arriba a la derecha. El ícono queda en tu Home.</div>
      </div>
      <div class="epis-ios-note">
        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
        Solo funciona desde <strong>Safari</strong>. Si usas Chrome en iPhone, cambia a Safari primero.
      </div>
      <button class="epis-btn-done" id="epis-btn-done-ios">
        ✅ Ya la instalé
      </button>
    </div>

    <button class="epis-btn-skip" id="epis-btn-skip">Ahora no</button>
  </div>
</div>

<!-- ── ESTILOS ───────────────────────────────────────── -->
<style>
/* ── Notif banner ──────────────────────────────────── */
#epl-notif-banner {
  position: fixed;
  bottom: calc(64px + env(safe-area-inset-bottom, 0px));
  left: 0; right: 0;
  z-index: 9980;
  padding: 0 .75rem .5rem;
  animation: epb-slide-up .3s ease both;
}
@media(min-width:768px){
  #epl-notif-banner { bottom: 1rem; left: auto; right: 1rem; max-width: 420px; padding: 0; }
}
.epb-inner {
  display: flex;
  align-items: center;
  gap: .6rem;
  background: #1C2F48;
  border: 1px solid rgba(201,167,98,.3);
  border-radius: 14px;
  padding: .7rem 1rem;
  box-shadow: 0 8px 32px rgba(0,0,0,.25);
}
.epb-icon { font-size: 1.2rem; flex-shrink: 0; }
.epb-text { flex: 1; font-size: .78rem; color: rgba(255,255,255,.85); margin: 0; line-height: 1.35; font-family: 'Montserrat', sans-serif; font-weight: 600; }
.epb-btn-on {
  background: #C9A762; color: #1C2F48;
  border: none; border-radius: 8px;
  padding: .4rem .85rem; font-size: .72rem; font-weight: 900;
  text-transform: uppercase; letter-spacing: .05em;
  cursor: pointer; white-space: nowrap; flex-shrink: 0;
  font-family: 'Montserrat', sans-serif;
  transition: filter .15s;
}
.epb-btn-on:hover { filter: brightness(1.1); }
.epb-btn-close {
  background: none; border: none; color: rgba(255,255,255,.4);
  font-size: 1rem; cursor: pointer; flex-shrink: 0;
  padding: .2rem; line-height: 1; transition: color .15s;
}
.epb-btn-close:hover { color: #fff; }
@keyframes epb-slide-up { from { opacity:0; transform:translateY(12px) } to { opacity:1; transform:translateY(0) } }

/* ── Install sheet ─────────────────────────────────── */
.epis-backdrop {
  position: fixed; inset: 0;
  background: rgba(10,20,33,.6);
  backdrop-filter: blur(4px);
  -webkit-backdrop-filter: blur(4px);
  z-index: 9981;
  animation: epis-fade-in .25s ease both;
}
.epis-sheet {
  position: fixed;
  bottom: 0; left: 0; right: 0;
  z-index: 9982;
  background: #fff;
  border-radius: 24px 24px 0 0;
  padding: .75rem 1.5rem calc(1.5rem + env(safe-area-inset-bottom, 0px));
  max-width: 520px;
  margin: 0 auto;
  box-shadow: 0 -12px 48px rgba(0,0,0,.18);
  animation: epis-slide-up .32s cubic-bezier(.4,0,.2,1) both;
}
@media(min-width:520px){
  .epis-sheet {
    left: 50%; right: auto;
    transform: translateX(-50%);
    border-radius: 24px;
    bottom: 1.5rem;
    width: 100%;
  }
}
.epis-handle {
  width: 40px; height: 4px; border-radius: 2px;
  background: #e2e8f0; margin: 0 auto .75rem;
}
.epis-logo-wrap { text-align: center; margin-bottom: .75rem; }
.epis-logo {
  height: 36px; opacity: .9;
}
.epis-title {
  font-family: 'Anton', 'Arial Black', sans-serif;
  font-size: 1.3rem; text-transform: uppercase;
  color: #1C2F48; text-align: center; margin: 0 0 .3rem;
  letter-spacing: .04em;
}
.epis-sub {
  font-size: .82rem; color: #64748b; text-align: center;
  margin: 0 0 1.25rem; line-height: 1.5;
  font-family: 'Montserrat', sans-serif;
}
.epis-steps { display: flex; flex-direction: column; gap: .7rem; margin-bottom: 1.25rem; }
.epis-step {
  display: flex; align-items: flex-start; gap: .75rem;
  background: #f8fafc; border-radius: 12px; padding: .65rem .9rem;
}
.epis-step-num {
  width: 26px; height: 26px; border-radius: 50%;
  background: #1C2F48; color: #C9A762;
  font-size: .72rem; font-weight: 900; flex-shrink: 0;
  display: flex; align-items: center; justify-content: center;
  font-family: 'Montserrat', sans-serif;
}
.epis-step-txt {
  font-size: .82rem; color: #374151; line-height: 1.4;
  font-family: 'Montserrat', sans-serif;
}
.epis-step-txt strong { color: #1C2F48; }
.epis-share-icon {
  display: inline-flex; align-items: center;
  background: #e2e8f0; border-radius: 6px;
  padding: .1rem .3rem; margin: 0 .15rem;
  vertical-align: middle; color: #1C2F48;
}
.epis-ios-note {
  display: flex; align-items: flex-start; gap: .4rem;
  background: #fefce8; border: 1px solid #fde68a;
  border-radius: 10px; padding: .55rem .75rem;
  font-size: .75rem; color: #92400e; line-height: 1.4;
  font-family: 'Montserrat', sans-serif;
}
.epis-ios-note svg { flex-shrink: 0; margin-top: .1rem; }
.epis-btn-main {
  width: 100%; display: flex; align-items: center; justify-content: center; gap: .5rem;
  background: linear-gradient(135deg, #1C2F48, #1a3a64);
  color: #fff; border: none; border-radius: 14px; padding: 1rem;
  font-family: 'Anton', 'Arial Black', sans-serif;
  font-size: .95rem; text-transform: uppercase; letter-spacing: .06em;
  cursor: pointer; transition: all .2s;
  box-shadow: 0 6px 20px rgba(28,47,72,.25);
}
.epis-btn-main:hover { background: linear-gradient(135deg, #C9A762, #b8975a); color: #1C2F48; }
.epis-btn-done {
  width: 100%; background: #f0fdf4; border: 1.5px solid #86efac;
  color: #166534; border-radius: 14px; padding: .85rem;
  font-family: 'Montserrat', sans-serif; font-size: .85rem; font-weight: 800;
  cursor: pointer; transition: background .15s;
}
.epis-btn-done:hover { background: #dcfce7; }
.epis-btn-skip {
  width: 100%; background: none; border: none;
  color: #94a3b8; font-size: .78rem; font-weight: 700;
  font-family: 'Montserrat', sans-serif; cursor: pointer;
  padding: .6rem; margin-top: .25rem; transition: color .15s;
}
.epis-btn-skip:hover { color: #64748b; }

@keyframes epis-fade-in { from{opacity:0} to{opacity:1} }
@keyframes epis-slide-up { from{transform:translateY(100%)} to{transform:translateY(0)} }
@media(min-width:520px){
  @keyframes epis-slide-up { from{opacity:0;transform:translateX(-50%) translateY(20px)} to{opacity:1;transform:translateX(-50%) translateY(0)} }
}
</style>

<!-- ── JAVASCRIPT ────────────────────────────────────── -->
<script>
(function(){
'use strict';

// ── Detección de plataforma ──────────────────────────
const ua         = navigator.userAgent;
const isIOS      = /iphone|ipad|ipod/i.test(ua);
const isSafari   = /safari/i.test(ua) && !/chrome|chromium|crios/i.test(ua);
const isAndroid  = /android/i.test(ua);
const isMobile   = isIOS || isAndroid;
const isStandalone = window.matchMedia('(display-mode: standalone)').matches
                  || window.navigator.standalone === true;

// ── Claves de almacenamiento ─────────────────────────
const KEY_INSTALL_DISMISSED = 'epl_install_dismissed_at';
const KEY_INSTALL_DONE      = 'epl_install_done';
const KEY_NOTIF_DISMISSED   = 'epl_notif_banner_dismissed';

// ── 1. NOTIFICACIONES BANNER ──────────────────────────
function initNotifBanner() {
  if (!('Notification' in window)) return;
  if (Notification.permission === 'granted') return;
  if (sessionStorage.getItem(KEY_NOTIF_DISMISSED)) return;

  const banner  = document.getElementById('epl-notif-banner');
  const btnOn   = document.getElementById('epb-btn-activar');
  const btnClose= document.getElementById('epb-btn-cerrar');
  if (!banner) return;

  // Mostrar tras 2 segundos
  setTimeout(() => {
    banner.style.display = 'block';
  }, 2000);

  btnClose.addEventListener('click', () => {
    banner.style.display = 'none';
    sessionStorage.setItem(KEY_NOTIF_DISMISSED, '1');
  });

  btnOn.addEventListener('click', async () => {
    banner.style.display = 'none';
    try {
      const perm = await Notification.requestPermission();
      if (perm === 'granted') {
        // Intentar suscribir al push (si existe el service worker de EPL)
        if ('serviceWorker' in navigator) {
          const reg = await navigator.serviceWorker.ready;
          if (reg && typeof reg.pushManager !== 'undefined') {
            // Re-usar la función de suscripción existente si está disponible
            if (typeof window.eplSuscribirPush === 'function') {
              window.eplSuscribirPush();
            }
          }
        }
      }
    } catch(e) { console.warn('Notif request error:', e); }
  });
}

// ── 2. INSTALL SHEET ──────────────────────────────────
let deferredInstallPrompt = null;

function shouldShowInstall() {
  if (!isMobile)    return false;  // solo en móvil
  if (isStandalone) return false;  // ya está instalada
  if (localStorage.getItem(KEY_INSTALL_DONE)) return false; // ya instaló
  const dismissedAt = parseInt(localStorage.getItem(KEY_INSTALL_DISMISSED) || '0');
  const daysSince   = (Date.now() - dismissedAt) / 86400000;
  if (daysSince < 5) return false; // cerrada hace menos de 5 días
  return true;
}

function showInstallSheet(platform) {
  if (!shouldShowInstall()) return;
  const sheet   = document.getElementById('epl-install-sheet');
  const android = document.getElementById('epis-android');
  const ios     = document.getElementById('epis-ios');
  if (!sheet) return;

  if (platform === 'android') android.style.display = 'flex';
  else                        ios.style.display     = 'flex';

  sheet.style.display = 'block';
}

function hideInstallSheet(done) {
  const sheet = document.getElementById('epl-install-sheet');
  if (sheet) sheet.style.display = 'none';
  if (done) {
    localStorage.setItem(KEY_INSTALL_DONE, '1');
  } else {
    localStorage.setItem(KEY_INSTALL_DISMISSED, Date.now().toString());
  }
}

// Capturar el evento de instalación nativa (Android/Chrome)
window.addEventListener('beforeinstallprompt', e => {
  e.preventDefault();
  deferredInstallPrompt = e;
  if (shouldShowInstall()) {
    setTimeout(() => showInstallSheet('android'), 3000);
  }
});

// Botón Instalar ahora (Android)
const btnInstall = document.getElementById('epis-btn-install');
if (btnInstall) {
  btnInstall.addEventListener('click', async () => {
    if (deferredInstallPrompt) {
      deferredInstallPrompt.prompt();
      const { outcome } = await deferredInstallPrompt.userChoice;
      deferredInstallPrompt = null;
      hideInstallSheet(outcome === 'accepted');
    } else {
      hideInstallSheet(false);
    }
  });
}

// Cuando se instala desde Chrome
window.addEventListener('appinstalled', () => {
  localStorage.setItem(KEY_INSTALL_DONE, '1');
  hideInstallSheet(true);
});

// iOS: mostrar después de 3 segundos si aplica
if (isIOS && isSafari && !isStandalone) {
  setTimeout(() => showInstallSheet('ios'), 3000);
}

// Botón "Ya la instalé" (iOS)
const btnDoneIos = document.getElementById('epis-btn-done-ios');
if (btnDoneIos) {
  btnDoneIos.addEventListener('click', () => hideInstallSheet(true));
}

// Botón "Ahora no"
const btnSkip = document.getElementById('epis-btn-skip');
if (btnSkip) {
  btnSkip.addEventListener('click', () => hideInstallSheet(false));
}

// Backdrop click = cerrar
const backdrop = document.getElementById('epis-backdrop');
if (backdrop) {
  backdrop.addEventListener('click', () => hideInstallSheet(false));
}

// ── Init ─────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
  initNotifBanner();
});

})();
</script>
