<style>
    /* Reset para el Footer */
    .epl-global-footer-wrapper {
        font-family: 'Montserrat', sans-serif !important;
        background-color: #1C2F48 !important;
        width: 100vw !important;
        position: relative;
        left: 50%;
        right: 50%;
        margin-left: -50vw !important;
        margin-right: -50vw !important;
        color: white !important;
    }
    .epl-global-footer-wrapper a { text-decoration: none !important; }
</style>

<div class="epl-global-footer-wrapper antialiased">
    <!-- FOOTER PREMIUM -->
    <footer class="text-white py-5 md:py-24 border-t-8 border-epl-gold">
        <div class="max-w-7xl mx-auto px-4 md:px-8 grid grid-cols-1 md:grid-cols-3 gap-5 md:gap-24 text-center md:text-left">

            <!-- Columna 1: Logo + Instagram -->
            <div class="flex md:flex-col items-center md:items-start justify-between md:justify-start gap-3 md:space-y-10">
                <img src="<?= epl_url('assets/img/logo-epl-lateral.png') ?>" class="brightness-0 invert h-8 md:h-16 opacity-80" alt="Logo Elite Padel League">

                <p class="hidden md:block text-gray-400 text-sm font-secondary leading-loose max-w-sm">
                    <strong class="text-white">Elite Padel League</strong> es una plataforma anual de experiencias deportivas, construida sobre comunidad, recurrencia y pertenencia, donde el pádel es el punto de encuentro y no el fin en sí mismo.
                </p>

                <a href="https://www.instagram.com/epleaguecl/" target="_blank" class="flex items-center gap-2 md:gap-4 text-gray-400 hover:text-epl-gold transition-all duration-300 group">
                    <div class="bg-white/5 p-2 md:p-4 rounded-full group-hover:bg-epl-gold/10 group-hover:scale-110 transition-all duration-300 border border-white/5 group-hover:border-epl-gold/30">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="20" height="20" x="2" y="2" rx="5" ry="5"/><path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z"/><line x1="17.5" x2="17.51" y1="6.5" y2="6.5"/></svg>
                    </div>
                    <div class="text-left hidden md:block">
                        <p class="font-secondary font-black text-[10px] uppercase tracking-[0.2em] text-white group-hover:text-epl-gold transition-colors">Síguenos en</p>
                        <p class="font-primary tracking-widest text-lg">INSTAGRAM</p>
                    </div>
                    <span class="md:hidden font-secondary font-black text-[10px] uppercase tracking-widest text-gray-400 group-hover:text-epl-gold transition-colors">Instagram</span>
                </a>
            </div>

            <!-- Columna 2: Navegación -->
            <div>
                <h4 class="font-primary text-base md:text-2xl mb-2 md:mb-12 text-epl-gold uppercase tracking-widest">Navegación</h4>
                <nav class="grid grid-cols-2 md:flex md:flex-col gap-y-1 gap-x-4 md:space-y-5 font-secondary font-black text-xs uppercase tracking-widest text-gray-400">
                    <a href="<?= epl_url() ?>" class="hover:text-white transition-colors">Inicio</a>
                    <a href="<?= epl_url('torneos.php') ?>" class="hover:text-white transition-colors">Torneos</a>
                    <a href="<?= epl_url('clasificacion.php') ?>" class="hover:text-white transition-colors">Clasificación</a>
                    <a href="<?= epl_url('resultados.php') ?>" class="hover:text-white transition-colors">Resultados</a>
                    <a href="<?= epl_url('jugadores.php') ?>" class="hover:text-white transition-colors">Jugadores</a>
                </nav>
            </div>

            <!-- Columna 3: CTA (oculta en móvil) -->
            <div class="hidden md:block">
                <h4 class="font-primary text-2xl mb-12 text-epl-gold uppercase tracking-widest">Experiencia EPL</h4>
                <div class="space-y-8">
                    <p class="text-gray-500 text-xs font-secondary leading-relaxed">Únete a nuestra plataforma, sé parte de la comunidad y asegura tu lugar en la próxima fecha.</p>
                    <a href="<?= epl_url('registro.php') ?>" class="inline-block border-2 border-white/20 px-8 py-4 rounded-xl font-secondary font-black text-[10px] uppercase tracking-[0.2em] hover:bg-white hover:text-epl-blue transition-all">
                        Inscribirme
                    </a>
                </div>
            </div>
        </div>

        <!-- Derechos de Autor -->
        <div class="max-w-7xl mx-auto px-4 md:px-8 mt-4 md:mt-24 pt-3 md:pt-12 border-t border-white/5 text-center">
            <p class="text-gray-600 font-secondary text-[10px] uppercase tracking-[0.4em]">&copy; <?= date('Y') ?> Elite Padel League. Más que un torneo.</p>
        </div>
    </footer>
</div>

<!-- =======================================================
     EPL CONFIRM MODAL
     ======================================================= -->
<div id="epl-confirm-overlay" style="display:none;position:fixed;top:0;left:0;width:100vw;height:100vh;background:rgba(10,20,33,.88);backdrop-filter:blur(5px);-webkit-backdrop-filter:blur(5px);z-index:9999998;align-items:center;justify-content:center;padding:1rem">
  <div style="background:#fff;border-radius:18px;padding:2rem 1.75rem 1.75rem;max-width:380px;width:100%;box-shadow:0 30px 70px rgba(0,0,0,.45);animation:eplModalIn .17s ease">
    <div style="text-align:center;margin-bottom:1.1rem">
      <div id="epl-confirm-icon-wrap" style="width:54px;height:54px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;background:rgba(220,38,38,.1)">
        <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="#dc2626" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
      </div>
    </div>
    <h3 id="epl-confirm-title" style="font-size:1rem;font-weight:800;color:#1C2F48;text-align:center;margin:0 0 .55rem;font-family:'Montserrat',sans-serif">Confirmar acción</h3>
    <p id="epl-confirm-msg" style="font-size:.875rem;color:#64748b;text-align:center;line-height:1.55;margin:0 0 1.6rem;font-family:'Montserrat',sans-serif"></p>
    <div style="display:flex;gap:.6rem">
      <button id="epl-confirm-cancel" type="button" style="flex:1;padding:.7rem;border:1.5px solid #e2e8f0;border-radius:10px;font-size:.84rem;font-weight:700;color:#64748b;background:#f8fafc;cursor:pointer;font-family:'Montserrat',sans-serif;transition:background .12s">Cancelar</button>
      <button id="epl-confirm-ok" type="button" style="flex:1;padding:.7rem;border:none;border-radius:10px;font-size:.84rem;font-weight:700;color:#fff;background:#dc2626;cursor:pointer;font-family:'Montserrat',sans-serif;transition:background .12s">Confirmar</button>
    </div>
  </div>
</div>
<style>
@keyframes eplModalIn { from { opacity:0;transform:scale(.93) translateY(6px) } to { opacity:1;transform:scale(1) translateY(0) } }
#epl-confirm-cancel:hover { background:#f1f5f9 !important }
#epl-confirm-ok:hover { filter:brightness(1.1) }
</style>
<script>
(function(){
  var overlay  = document.getElementById('epl-confirm-overlay');
  var titleEl  = document.getElementById('epl-confirm-title');
  var msgEl    = document.getElementById('epl-confirm-msg');
  var okBtn    = document.getElementById('epl-confirm-ok');
  var cancelBtn= document.getElementById('epl-confirm-cancel');
  var iconWrap = document.getElementById('epl-confirm-icon-wrap');

  function eplConfirmClose() { overlay.style.display = 'none'; }

  window.eplConfirm = function(msg, onConfirm, opts) {
    opts = opts || {};
    titleEl.textContent = opts.title || 'Confirmar acción';
    msgEl.textContent   = msg;
    okBtn.textContent   = opts.confirmText || 'Confirmar';
    var danger = opts.danger !== false;
    okBtn.style.background    = danger ? '#dc2626' : '#C9A762';
    iconWrap.style.background = danger ? 'rgba(220,38,38,.1)' : 'rgba(201,167,98,.15)';
    iconWrap.querySelector('svg').setAttribute('stroke', danger ? '#dc2626' : '#C9A762');
    overlay.style.display = 'flex';
    okBtn.onclick = function() { eplConfirmClose(); onConfirm(); };
  };

  cancelBtn.onclick = eplConfirmClose;
  overlay.addEventListener('click', function(e){ if(e.target===overlay) eplConfirmClose(); });
  document.addEventListener('keydown', function(e){ if(e.key==='Escape') eplConfirmClose(); });

  document.addEventListener('click', function(e){
    var btn = e.target.closest('[data-confirm]');
    if (!btn || btn._eplOk) return;
    e.preventDefault();
    var msg  = btn.dataset.confirm;
    var danger = btn.dataset.confirmDanger !== 'false';
    var title= btn.dataset.confirmTitle || undefined;
    var okTxt= btn.dataset.confirmOk || undefined;
    window.eplConfirm(msg, function(){
      btn._eplOk = true;
      btn.click();
      btn._eplOk = false;
    }, { danger: danger, title: title, confirmText: okTxt });
  }, true);
})();
</script>

<!-- =======================================================
     EPL GLOBAL LOADER (Bloqueo de pantalla al enviar formularios)
     ======================================================= -->
<script>
    document.addEventListener('DOMContentLoaded', function() {
        
        // 1. Inyectar el HTML del Loader si no existe en la página
        if (!document.getElementById('epl-loader-overlay')) {
            const loaderHTML = `
                <div id="epl-loader-overlay" style="display:none; position:fixed; top:0; left:0; width:100vw; height:100vh; background:rgba(10, 20, 33, 0.90); backdrop-filter:blur(8px); -webkit-backdrop-filter:blur(8px); z-index:9999999; flex-direction:column; justify-content:center; align-items:center;">
                    <div style="width: 70px; height: 70px; border: 4px solid rgba(201,167,98,0.2); border-top-color: #C9A762; border-radius: 50%; animation: epl-spin 1s linear infinite; margin-bottom: 20px;"></div>
                    <p style="color: #C9A762; font-family: 'Montserrat', sans-serif; font-weight: 900; text-transform: uppercase; letter-spacing: 4px; font-size: 14px; text-shadow: 0 2px 10px rgba(0,0,0,0.5);">Procesando...</p>
                    <p style="color: #94a3b8; font-family: 'Montserrat', sans-serif; font-size: 10px; margin-top: 10px; letter-spacing: 1px;">Asegurando tu lugar en la Elite</p>
                </div>
                <style>@keyframes epl-spin { to { transform: rotate(360deg); } }</style>
            `;
            document.body.insertAdjacentHTML('beforeend', loaderHTML);
        }

        const loader = document.getElementById('epl-loader-overlay');

        // 2. Interceptar envíos de formularios nativos
        document.addEventListener('submit', function(e) {
            const form = e.target;
            
            // Si el form no es get, mostramos el loader
            if (form.method && form.method.toLowerCase() === 'post') {
                loader.style.display = 'flex';
                // Seguro anti-congelamiento
                setTimeout(() => {
                    loader.style.display = 'none';
                }, 12000);
            }
        }, true);
    });
</script>

</body>
</html>
