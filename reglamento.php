<?php
$page_title       = 'Reglamento del Circuito';
$active_nav       = 'reglamento';
$meta_description = 'Reglamento oficial Elite Padel League: sistema de juego, categorías, puntuación, reprogramaciones, fair play y normativa completa de torneos amateur.';
$meta_keywords    = 'reglamento padel, normativa torneo padel, reglas EPL, categorias padel chile, sistema puntuacion padel';
require_once 'includes/functions.php';
require_once 'includes/header.php';
?>

<!-- 1. ESTILOS DE LA PÁGINA -->
<style>
    .epl-body-content {
        font-family: 'Montserrat', sans-serif !important;
        background-color: #F5F7F8;
        width: 100vw !important;
        position: relative;
        left: 50%; right: 50%;
        margin-left: -50vw !important; margin-right: -50vw !important;
    }
    .hero-section {
        background-color: #0A1421;
        background-image:
            linear-gradient(to bottom, rgba(10,20,33,.90) 0%, rgba(28,47,72,.75) 50%, rgba(10,20,33,.95) 100%),
            url('<?= epl_url('assets/img/padel-bg.jpg') ?>');
        background-size: cover;
        background-position: center center;
        width: 100%;
        display: block;
    }
    .giant-title { line-height: .9; letter-spacing: -.02em; }

    .rule-card {
        background: white;
        border-radius: 30px;
        padding: 3rem;
        box-shadow: 0 20px 50px rgba(0,0,0,.04);
        border: 1px solid rgba(0,0,0,.02);
        position: relative;
        overflow: hidden;
        transition: transform .3s ease;
    }
    .rule-card:hover { transform: translateY(-5px); }

    .rule-number {
        position: absolute;
        top: -20px; right: -10px;
        font-family: 'Anton', sans-serif;
        font-size: 8rem;
        color: rgba(201,167,98,.05);
        line-height: 1;
        pointer-events: none;
    }
    .rule-list li {
        position: relative;
        padding-left: 1.5rem;
        margin-bottom: 1.2rem;
    }
    .rule-list li::before {
        content: '■';
        position: absolute;
        left: 0;
        color: #C9A762;
        font-size: .8rem;
        top: 2px;
    }
    .sub-rule {
        padding-left: 1rem;
        border-left: 2px solid #e2e8f0;
        margin-top: .8rem;
        font-size: .85rem;
        color: #64748b;
        display: flex;
        flex-direction: column;
        gap: .5rem;
    }
    .sidebar-card {
        background: white;
        border-radius: 20px;
        padding: 2.5rem;
        box-shadow: 0 10px 30px rgba(0,0,0,.02);
        border-left: 6px solid #C9A762;
        position: sticky;
        top: 120px;
    }
</style>

<div class="epl-body-content antialiased">

    <!-- HERO -->
    <header class="hero-section pt-32 pb-24 md:pt-48 md:pb-44 text-center px-6">
        <div class="max-w-5xl mx-auto">
            <span class="font-secondary font-black text-epl-gold tracking-[0.6em] uppercase text-[10px] md:text-xs mb-8 block opacity-90">
                Acuerdos de Convivencia &bull; Temporada 2026
            </span>
            <h1 class="giant-title text-6xl md:text-8xl lg:text-[8rem] text-white font-primary mb-10 drop-shadow-2xl uppercase">
                Reglamento del <span class="text-epl-gold">Circuito</span>
            </h1>
            <p class="mt-4 max-w-2xl mx-auto text-lg md:text-xl text-gray-200 font-secondary font-medium mb-12 leading-relaxed opacity-90">
                Garantizamos un entorno de respeto, pertenencia y el más puro nivel deportivo, donde la comunidad es lo primordial.
            </p>
        </div>
    </header>

    <!-- CONTENIDO -->
    <main class="max-w-7xl mx-auto px-6 md:px-8 py-24">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-16">

            <!-- Columna reglas -->
            <div class="lg:col-span-2 space-y-10">

                <!-- Regla 01 -->
                <div class="rule-card">
                    <div class="rule-number">01</div>
                    <h2 class="text-3xl font-primary text-epl-blue uppercase tracking-tight mb-6 relative z-10">Formato de Encuentros</h2>
                    <ul class="rule-list text-gray-600 space-y-3 relative z-10">
                        <li><strong class="text-epl-blue">Sistema de Juego:</strong> Partidos al mejor de 3 sets.</li>
                        <li><strong class="text-epl-blue">Punto de Oro:</strong> Sistema sin ventajas. Al llegar al deuce (40-40), la pareja receptora elige el lado del saque y el que gana el punto, gana el game.</li>
                        <li><strong class="text-epl-blue">Duración:</strong> Los bloques tienen un máximo de 90 minutos por partido (incluye 5 minutos de calentamiento previo).</li>
                        <li><strong class="text-epl-blue">Término del Tiempo:</strong> Si se acaba el bloque de 90 minutos, el partido se puede seguir jugando <strong>SIEMPRE</strong> y cuando el club no cierre o no dé por terminado el turno de la cancha. Además, si se está disputando un game al momento de cumplirse el tiempo, dicho game <strong>debe terminarse obligatoriamente</strong>.</li>
                        <li><strong class="text-epl-blue">Definición por tiempo (Tercer Set):</strong> Si posterior a lo anterior el partido debe finalizar por exigencia del club estando en el tercer set, la pareja que tenga más games ganados en ese momento ganará el set de manera automática. En caso de que una pareja tenga un saque menos, se debe jugar ese game para tener la misma cantidad de saques en el set, siempre que los tiempos del club lo permitan. Si tras esto hay igualdad absoluta en games, el encuentro se registrará como empate definitivo.</li>
                    </ul>
                </div>

                <!-- Regla 02 -->
                <div class="rule-card">
                    <div class="rule-number">02</div>
                    <h2 class="text-3xl font-primary text-epl-blue uppercase tracking-tight mb-6 relative z-10">Sistema de Puntuación</h2>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 relative z-10 mb-6">
                        <div class="bg-gray-50 border border-gray-100 p-6 rounded-2xl text-center">
                            <p class="text-4xl font-primary text-epl-blue mb-1">3</p>
                            <p class="text-[10px] font-black uppercase text-epl-gold tracking-widest">Puntos</p>
                            <p class="text-sm font-bold text-gray-500 mt-2">Partido Ganado</p>
                        </div>
                        <div class="bg-gray-50 border border-gray-100 p-6 rounded-2xl text-center">
                            <p class="text-4xl font-primary text-epl-blue mb-1">1</p>
                            <p class="text-[10px] font-black uppercase text-epl-gold tracking-widest">Punto</p>
                            <p class="text-sm font-bold text-gray-500 mt-2">Partido Empatado</p>
                        </div>
                        <div class="bg-gray-50 border border-gray-100 p-6 rounded-2xl text-center">
                            <p class="text-4xl font-primary text-gray-300 mb-1">0</p>
                            <p class="text-[10px] font-black uppercase text-gray-400 tracking-widest">Puntos</p>
                            <p class="text-sm font-bold text-gray-500 mt-2">Partido Perdido</p>
                        </div>
                    </div>
                    <div class="bg-blue-50 border-l-4 border-epl-blue p-5 rounded-r-xl relative z-10">
                        <p class="text-sm text-epl-blue leading-relaxed">
                            <strong class="font-black uppercase tracking-wide">Nota Importante:</strong> La pareja ganadora es la responsable de reportar el resultado exacto a la organización inmediatamente finalizado el encuentro, a través del excel compartido que estará disponible en el grupo de Whatsapp de la liga, esto hasta que se encuentre operativo el registro de puntos desde la plataforma Elite Padel League, dentro de las 24 horas finalizado el partido, con lo cual se actualizará la tabla de la posiciones disponible en el mismo sitio web.
                        </p>
                    </div>
                </div>

                <!-- Regla 03 -->
                <div class="rule-card">
                    <div class="rule-number">03</div>
                    <h2 class="text-3xl font-primary text-epl-blue uppercase tracking-tight mb-6 relative z-10">Criterios de Desempate <span class="text-sm text-gray-400 font-secondary">(Tabla de Posiciones)</span></h2>
                    <p class="text-gray-600 mb-6 relative z-10">En caso de igualdad de puntos entre dos o más parejas al final de la liga, se definirá por:</p>
                    <ul class="rule-list text-gray-600 space-y-3 relative z-10">
                        <li><strong class="text-epl-blue">Diferencia de Games:</strong> Games ganados menos games perdidos.</li>
                        <li><strong class="text-epl-blue">Enfrentamiento Directo:</strong> El resultado del partido jugado entre las parejas empatadas.</li>
                        <li><strong class="text-epl-blue">Partido de Definición:</strong> Si persiste el empate exacto y están disputando puestos de premiación, se jugará un partido extra a 3 sets.</li>
                    </ul>
                </div>

                <!-- Regla 04 -->
                <div class="rule-card">
                    <div class="rule-number">04</div>
                    <h2 class="text-3xl font-primary text-epl-blue uppercase tracking-tight mb-6 relative z-10">Reprogramaciones, Lluvia y Feriados</h2>
                    <ul class="rule-list text-gray-600 space-y-3 relative z-10">
                        <li><strong class="text-epl-blue">Reprogramación de Partidos:</strong> Solo se permitirá reprogramar un encuentro si se avisa con un mínimo de <strong>48 horas de anticipación.</strong>
                            <div class="sub-rule">
                                <p>Y se deberá informar el día y hora de la fecha reprogramada, la cual deberá ser antes de la siguiente fecha regular. Situaciones de excepción que requieran un plazo mayor, deberán ser expuestas a la organización para su resolución en instancia única y final.</p>
                                <p>El partido reprogramado, se podrá jugar cualquier día de lunes a domingo en Conecta Santa Blanca, con la sola excepción del horario de <strong>19:30 (de lunes a jueves) y de 10:30 (sábado, domingos y festivos).</strong></p>
                                <p>El torneo y los participantes adscriben a los principios del fair play, por lo que con la inscripción en la liga aceptan desde ya la posibilidad de reprogramar la fecha por una situación de fuerza mayor imputable al jugador, su partner y/o sus rivales.</p>
                            </div>
                        </li>
                        <li><strong class="text-epl-blue">Beneficio de Cerveza (Tercer Tiempo):</strong> El beneficio de la cerveza para la pareja ganadora aplica <strong>exclusivamente</strong> cuando el partido se juega en la fecha y día oficial programado por la liga. Los partidos jugados en días reprogramados pierden este beneficio.</li>
                        <li><strong class="text-epl-blue">Lluvia y Clima:</strong> En caso de lluvia o condiciones climáticas que impidan el juego, la fecha completa de la liga se correrá automáticamente para la semana siguiente. Dicha determinación será informada por el grupo de Whatsapp a más tardar a las <strong>18:00 hrs. del día del encuentro</strong>, previa revisión de las condiciones de las canchas y el pronóstico meteorológico.</li>
                        <li><strong class="text-epl-blue">Feriados:</strong> Cuando la fecha coincida con un feriado legal la fecha se suspenderá y se correrá una semana.
                            <div class="sub-rule">
                                <p>En caso de ser un "viernes feriado" (que interfiere con la logística del jueves de liga), la fecha se anticipará para el miércoles previo.</p>
                            </div>
                        </li>
                    </ul>
                </div>

                <!-- Regla 05 -->
                <div class="rule-card">
                    <div class="rule-number">05</div>
                    <h2 class="text-3xl font-primary text-epl-blue uppercase tracking-tight mb-6 relative z-10">Reemplazos, Lesiones y "Galletas"</h2>
                    <ul class="rule-list text-gray-600 space-y-3 relative z-10">
                        <li><strong class="text-epl-blue">Límite de Suplentes:</strong> Durante todo el torneo, una pareja puede utilizar un máximo de <strong>dos (2) jugadores de reemplazo</strong> o "galletas" en total.</li>
                        <li><strong class="text-epl-blue">Aviso Obligatorio:</strong> Todo uso de un jugador de reemplazo debe ser informado y registrado oficialmente con los organizadores de la liga vía WhatsApp <strong>antes</strong> de que se juegue el partido.</li>
                        <li><strong class="text-epl-blue">Cambio de Titularidad:</strong> Si uno de los jugadores "galleta" excede el límite de 10 partidos jugados, pasará automáticamente a ser el jugador titular oficial de la liga.</li>
                        <li><strong class="text-epl-blue">Inhabilitación:</strong> Al efectuarse el cambio a titular definitivo de la "galleta" (ya sea por cumplir los 10 partidos o por una lesión grave del titular), el ex-compañero reemplazado queda inhabilitado y no podrá volver a jugar en lo que reste del torneo, sin perjuicio que mantendrá los beneficios de jugador titular de la liga por el periodo que dura la misma.
                            <div class="sub-rule mt-2">
                                <p>El jugador reemplazado no tendrá derecho a reembolso por los partidos no jugados, y el reemplazante no tendrá derecho a los beneficios de los titulares de la liga.</p>
                            </div>
                        </li>
                    </ul>
                </div>

                <!-- Regla 06 -->
                <div class="rule-card">
                    <div class="rule-number">06</div>
                    <h2 class="text-3xl font-primary text-epl-blue uppercase tracking-tight mb-6 relative z-10">W.O. (Walkover) y Puntualidad</h2>
                    <ul class="rule-list text-gray-600 space-y-3 relative z-10">
                        <li><strong class="text-epl-blue">Tolerancia:</strong> Se dará un tiempo de gracia máximo de <strong>15 minutos</strong> desde la hora programada.</li>
                        <li><strong class="text-epl-blue">Penalización W.O.:</strong> No presentarse al partido o llegar después del tiempo de tolerancia penaliza a la pareja infractora con una derrota por <strong>doble 6-0 (6-0 / 6-0) en contra</strong>. La organización no asume responsabilidad por situaciones de esta índole por lo cual no existirá compensación alguna por el partido no realizado.</li>
                    </ul>
                </div>

                <!-- Regla 07 -->
                <div class="rule-card">
                    <div class="rule-number">07</div>
                    <h2 class="text-3xl font-primary text-epl-blue uppercase tracking-tight mb-6 relative z-10">Fair Play y Conducta Deportiva</h2>
                    <ul class="rule-list text-gray-600 space-y-3 relative z-10">
                        <li><strong class="text-epl-blue">Nivel Adecuado:</strong> La organización se reserva el derecho de retirar a un jugador si su nivel es desproporcionadamente superior al de la categoría inscrita (4ta - 5ta), para mantener la competitividad justa.</li>
                        <li><strong class="text-epl-blue">Conducta y Respeto:</strong> El respeto hacia los rivales, la organización y el personal del club es intransable. Si un jugador o pareja presenta actitudes violentas (físicas o verbales) de manera reiterativa, será <strong>expulsado de la liga inmediatamente</strong>, sin derecho a ningún tipo de devolución de dinero.</li>
                    </ul>
                </div>

                <!-- Resolución final -->
                <div class="bg-gray-100 border border-gray-200 rounded-2xl p-6 text-center mt-6">
                    <p class="font-secondary font-bold text-gray-500 text-sm">
                        Cualquier aspecto o controversia que se presente durante el torneo y que no esté especificado en este reglamento, será resuelto por la organización.
                    </p>
                </div>

            </div>

            <!-- Sidebar -->
            <aside class="lg:col-span-1 space-y-8">
                <div class="sidebar-card">
                    <h4 class="font-primary text-2xl text-epl-blue mb-4 uppercase">Espíritu Élite</h4>
                    <p class="text-sm text-gray-600 leading-relaxed mb-8">
                        Elite Padel League es más que un torneo; es una comunidad donde el pádel es el punto de encuentro y no el fin en sí mismo. El cumplimiento de estas pautas asegura la mejor experiencia para todos.
                    </p>
                    <div class="bg-gray-50 rounded-xl p-6 border border-gray-100">
                        <h5 class="font-primary text-lg text-epl-gold mb-2 uppercase">¿Tienes Dudas?</h5>
                        <p class="font-secondary text-xs text-gray-500 mb-4 leading-relaxed">Si necesitas aclarar algún punto o situación específica sobre las reglas, comunícate con nosotros.</p>
                        <a href="https://www.instagram.com/epleaguecl/" target="_blank" rel="noopener"
                           class="block w-full text-center bg-epl-blue text-white py-3 rounded-lg font-secondary font-black text-[10px] uppercase tracking-widest hover:bg-epl-gold transition-colors" style="text-decoration:none">
                            Contactar a la Liga
                        </a>
                    </div>

                    <div class="mt-8 pt-8 border-t border-gray-100">
                        <h5 class="font-primary text-lg text-epl-blue mb-4 uppercase">Navegar</h5>
                        <nav class="flex flex-col gap-2">
                            <a href="#" onclick="document.querySelectorAll('.rule-card')[0].scrollIntoView({behavior:'smooth'});return false" class="text-xs font-black uppercase tracking-widest text-gray-400 hover:text-epl-gold transition-colors">01 · Formato</a>
                            <a href="#" onclick="document.querySelectorAll('.rule-card')[1].scrollIntoView({behavior:'smooth'});return false" class="text-xs font-black uppercase tracking-widest text-gray-400 hover:text-epl-gold transition-colors">02 · Puntuación</a>
                            <a href="#" onclick="document.querySelectorAll('.rule-card')[2].scrollIntoView({behavior:'smooth'});return false" class="text-xs font-black uppercase tracking-widest text-gray-400 hover:text-epl-gold transition-colors">03 · Desempates</a>
                            <a href="#" onclick="document.querySelectorAll('.rule-card')[3].scrollIntoView({behavior:'smooth'});return false" class="text-xs font-black uppercase tracking-widest text-gray-400 hover:text-epl-gold transition-colors">04 · Reprogramaciones</a>
                            <a href="#" onclick="document.querySelectorAll('.rule-card')[4].scrollIntoView({behavior:'smooth'});return false" class="text-xs font-black uppercase tracking-widest text-gray-400 hover:text-epl-gold transition-colors">05 · Galletas</a>
                            <a href="#" onclick="document.querySelectorAll('.rule-card')[5].scrollIntoView({behavior:'smooth'});return false" class="text-xs font-black uppercase tracking-widest text-gray-400 hover:text-epl-gold transition-colors">06 · W.O.</a>
                            <a href="#" onclick="document.querySelectorAll('.rule-card')[6].scrollIntoView({behavior:'smooth'});return false" class="text-xs font-black uppercase tracking-widest text-gray-400 hover:text-epl-gold transition-colors">07 · Fair Play</a>
                        </nav>
                    </div>
                </div>
            </aside>

        </div>
    </main>

</div>

<?php require_once 'includes/footer.php'; ?>
