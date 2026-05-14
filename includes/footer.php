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
    <footer class="text-white py-10 md:py-24 border-t-8 border-epl-gold">
        <div class="max-w-7xl mx-auto px-4 md:px-8 grid grid-cols-1 md:grid-cols-3 gap-8 md:gap-24 text-center md:text-left">

            <!-- Columna 1: Logo y Definición -->
            <div class="space-y-4 md:space-y-10">
                <img src="<?= epl_url('assets/img/logo-epl-lateral.png') ?>" class="brightness-0 invert h-12 md:h-16 mx-auto md:mx-0 opacity-80" alt="Logo Elite Padel League">

                <p class="text-gray-400 text-sm font-secondary leading-loose max-w-sm mx-auto md:mx-0">
                    <strong class="text-white">Elite Padel League</strong> es una plataforma anual de experiencias deportivas, construida sobre comunidad, recurrencia y pertenencia, donde el pádel es el punto de encuentro y no el fin en sí mismo.
                </p>

                <div class="flex justify-center md:justify-start pt-1 md:pt-2">
                    <a href="https://www.instagram.com/epleaguecl/" target="_blank" class="flex items-center gap-4 text-gray-400 hover:text-epl-gold transition-all duration-300 group">
                        <div class="bg-white/5 p-3 md:p-4 rounded-full group-hover:bg-epl-gold/10 group-hover:scale-110 transition-all duration-300 border border-white/5 group-hover:border-epl-gold/30">
                            <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="20" height="20" x="2" y="2" rx="5" ry="5"/><path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z"/><line x1="17.5" x2="17.51" y1="6.5" y2="6.5"/></svg>
                        </div>
                        <div class="text-left">
                            <p class="font-secondary font-black text-[10px] uppercase tracking-[0.2em] text-white group-hover:text-epl-gold transition-colors">Síguenos en</p>
                            <p class="font-primary tracking-widest text-lg">INSTAGRAM</p>
                        </div>
                    </a>
                </div>
            </div>

            <!-- Columna 2: Navegación -->
            <div>
                <h4 class="font-primary text-xl md:text-2xl mb-4 md:mb-12 text-epl-gold uppercase tracking-widest">Navegación</h4>
                <nav class="flex flex-col space-y-3 md:space-y-5 font-secondary font-black text-xs uppercase tracking-widest text-gray-400">
                    <a href="<?= epl_url() ?>" class="hover:text-white transition-colors">Inicio</a>
                    <a href="<?= epl_url('torneos.php') ?>" class="hover:text-white transition-colors">Torneos</a>
                    <a href="<?= epl_url('clasificacion.php') ?>" class="hover:text-white transition-colors">Clasificación</a>
                    <a href="<?= epl_url('resultados.php') ?>" class="hover:text-white transition-colors">Resultados</a>
                    <a href="<?= epl_url('jugadores.php') ?>" class="hover:text-white transition-colors">Jugadores</a>
                </nav>
            </div>

            <!-- Columna 3: CTA -->
            <div>
                <h4 class="font-primary text-xl md:text-2xl mb-4 md:mb-12 text-epl-gold uppercase tracking-widest">Experiencia EPL</h4>
                <div class="space-y-4 md:space-y-8">
                    <p class="text-gray-500 text-xs font-secondary leading-relaxed">Únete a nuestra plataforma, sé parte de la comunidad y asegura tu lugar en la próxima fecha.</p>
                    <a href="<?= epl_url('registro.php') ?>" class="inline-block border-2 border-white/20 px-6 md:px-8 py-4 rounded-xl font-secondary font-black text-[10px] uppercase tracking-[0.2em] hover:bg-white hover:text-epl-blue transition-all">
                        Inscribirme
                    </a>
                </div>
            </div>
        </div>

        <!-- Derechos de Autor -->
        <div class="max-w-7xl mx-auto px-4 md:px-8 mt-8 md:mt-24 pt-5 md:pt-12 border-t border-white/5 text-center">
            <p class="text-gray-600 font-secondary text-[10px] uppercase tracking-[0.4em]">&copy; <?= date('Y') ?> Elite Padel League. Más que un torneo.</p>
        </div>
    </footer>
</div>

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
