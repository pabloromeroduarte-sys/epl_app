<?php
$page_title  = 'Tutoriales';
$dash_active = 'tutoriales';
require_once 'includes/auth.php';
require_once 'includes/functions.php';
epl_require_login();
require_once 'includes/header.php';
?>

<style>
/* ── Layout ── */
.tut-wrap { max-width: 680px; margin: 0 auto; padding: 1.25rem 1rem 6rem; }

/* ── Hero ── */
.tut-hero {
    background: linear-gradient(135deg,#1c2f48,#1a3a64);
    border-radius: 18px;
    padding: 1.5rem 1.25rem;
    margin-bottom: 1.5rem;
    text-align: center;
    color: #fff;
}
.tut-hero h1 { font-family:'Anton',sans-serif; font-size:1.6rem; letter-spacing:.05em; margin:0 0 .35rem; }
.tut-hero p  { font-size:.82rem; color:rgba(255,255,255,.7); margin:0; }

/* ── Grid de tarjetas ── */
.tut-grid { display:grid; grid-template-columns:1fr 1fr; gap:.85rem; margin-bottom:1.5rem; }
@media(max-width:480px){ .tut-grid { grid-template-columns:1fr; } }

.tut-card {
    background:#fff;
    border-radius:14px;
    box-shadow:0 2px 12px rgba(0,0,0,.07);
    overflow:hidden;
    cursor:pointer;
    transition:transform .2s, box-shadow .2s;
    border:2px solid transparent;
}
.tut-card:hover { transform:translateY(-2px); box-shadow:0 6px 20px rgba(0,0,0,.12); }
.tut-card.open  { border-color:#C9A762; }

.tut-card-head {
    padding:1rem 1rem .75rem;
    display:flex; align-items:center; gap:.75rem;
}
.tut-icon {
    width:44px; height:44px; border-radius:12px;
    display:flex; align-items:center; justify-content:center;
    font-size:1.4rem; flex-shrink:0;
}
.tut-card-head h2 {
    font-family:'Anton',sans-serif; font-size:.95rem;
    letter-spacing:.04em; color:#1c2f48; margin:0;
    text-transform:uppercase; line-height:1.2;
}
.tut-card-head small { font-size:.7rem; color:#94a3b8; font-weight:600; }

/* ── Contenido expandible ── */
.tut-body {
    display:none;
    padding:0 1rem 1rem;
    border-top:1px solid #f1f5f9;
    animation:fadeDown .25s ease;
}
.tut-body.open { display:block; }
@keyframes fadeDown { from{opacity:0;transform:translateY(-6px)} to{opacity:1;transform:translateY(0)} }

/* ── Pasos ── */
.tut-steps { list-style:none; padding:0; margin:.75rem 0 0; display:flex; flex-direction:column; gap:.6rem; }
.tut-step {
    display:flex; align-items:flex-start; gap:.65rem;
}
.step-num {
    width:26px; height:26px; border-radius:50%;
    background:#1c2f48; color:#fff;
    font-size:.72rem; font-weight:900;
    display:flex; align-items:center; justify-content:center;
    flex-shrink:0; margin-top:.05rem;
}
.step-text { font-size:.82rem; color:#374151; line-height:1.45; }
.step-text strong { color:#1c2f48; }

/* ── Tip ── */
.tut-tip {
    background:#fefce8; border:1px solid #fde68a;
    border-radius:10px; padding:.6rem .75rem;
    font-size:.78rem; color:#92400e;
    margin-top:.85rem; display:flex; gap:.5rem; align-items:flex-start;
}
.tut-tip span { flex-shrink:0; }

/* ── Botón ir ── */
.tut-btn {
    display:inline-flex; align-items:center; gap:.4rem;
    background:#C9A762; color:#1c2f48;
    border:none; border-radius:10px;
    padding:.55rem 1rem; font-size:.78rem; font-weight:900;
    text-decoration:none; text-transform:uppercase;
    letter-spacing:.05em; margin-top:.85rem;
    transition:background .2s;
}
.tut-btn:hover { background:#b8934f; }

/* ── Sección extra ── */
.tut-section-title {
    font-family:'Anton',sans-serif; font-size:.8rem;
    letter-spacing:.1em; color:#94a3b8;
    text-transform:uppercase; margin:1.25rem 0 .65rem;
}
</style>

<div class="tut-wrap">

  <!-- Hero -->
  <div class="tut-hero">
    <div style="font-size:2rem;margin-bottom:.4rem">📖</div>
    <h1>Tutoriales</h1>
    <p>Aprende a usar la plataforma paso a paso</p>
  </div>

  <p class="tut-section-title">Toca una guía para ver los pasos</p>

  <div class="tut-grid">

    <!-- ── 1. Registrar resultado ── -->
    <div class="tut-card" onclick="toggleTut(this)">
      <div class="tut-card-head">
        <div class="tut-icon" style="background:#dbeafe;">🏆</div>
        <div>
          <h2>Registrar resultado</h2>
          <small>Puntuar partido</small>
        </div>
      </div>
      <div class="tut-body">
        <ul class="tut-steps">
          <li class="tut-step">
            <div class="step-num">1</div>
            <div class="step-text">Toca <strong>Resultado</strong> en el menú inferior (ícono de planilla).</div>
          </li>
          <li class="tut-step">
            <div class="step-num">2</div>
            <div class="step-text">Verás la lista de tus partidos pendientes de puntuar.</div>
          </li>
          <li class="tut-step">
            <div class="step-num">3</div>
            <div class="step-text">Toca el partido que quieres puntuar y selecciónalo.</div>
          </li>
          <li class="tut-step">
            <div class="step-num">4</div>
            <div class="step-text">Ingresa el <strong>marcador set a set</strong>: cuántos games ganó cada equipo en cada set.</div>
          </li>
          <li class="tut-step">
            <div class="step-num">5</div>
            <div class="step-text">Presiona <strong>Guardar resultado</strong>. El rival recibirá una notificación.</div>
          </li>
        </ul>
        <div class="tut-tip">
          <span>⚠️</span>
          <div>Solo puedes registrar partidos que ya se jugaron. Si el rival disputa el resultado, el administrador lo resolverá.</div>
        </div>
        <a href="ingresar_resultado.php" class="tut-btn">
          Ir a Resultados →
        </a>
      </div>
    </div>

    <!-- ── 2. Reprogramar fecha ── -->
    <div class="tut-card" onclick="toggleTut(this)">
      <div class="tut-card-head">
        <div class="tut-icon" style="background:#dcfce7;">📅</div>
        <div>
          <h2>Reprogramar fecha</h2>
          <small>Cambiar día/hora</small>
        </div>
      </div>
      <div class="tut-body">
        <ul class="tut-steps">
          <li class="tut-step">
            <div class="step-num">1</div>
            <div class="step-text">Toca <strong>Reprog.</strong> en el menú inferior (ícono de calendario).</div>
          </li>
          <li class="tut-step">
            <div class="step-num">2</div>
            <div class="step-text">Selecciona el partido cuya fecha quieres cambiar.</div>
          </li>
          <li class="tut-step">
            <div class="step-num">3</div>
            <div class="step-text">Elige la <strong>nueva fecha y hora</strong> acordada con el rival.</div>
          </li>
          <li class="tut-step">
            <div class="step-num">4</div>
            <div class="step-text">Escribe un <strong>motivo</strong> (ej: "cancha ocupada", "viaje de trabajo").</div>
          </li>
          <li class="tut-step">
            <div class="step-num">5</div>
            <div class="step-text">Presiona <strong>Solicitar reprogramación</strong>. El administrador aprobará o rechazará.</div>
          </li>
        </ul>
        <div class="tut-tip">
          <span>💡</span>
          <div>Coordina siempre la nueva fecha con tu rival <strong>antes</strong> de solicitarla. Puedes escribirle por WhatsApp desde la sección de partidos.</div>
        </div>
        <a href="reprogramar.php" class="tut-btn">
          Ir a Reprogramar →
        </a>
      </div>
    </div>

    <!-- ── 3. Ver tus partidos ── -->
    <div class="tut-card" onclick="toggleTut(this)">
      <div class="tut-card-head">
        <div class="tut-icon" style="background:#fce7f3;">🎾</div>
        <div>
          <h2>Ver tus partidos</h2>
          <small>Calendario y resultados</small>
        </div>
      </div>
      <div class="tut-body">
        <ul class="tut-steps">
          <li class="tut-step">
            <div class="step-num">1</div>
            <div class="step-text">Desde el <strong>Inicio</strong> (dashboard) verás tus próximos partidos en la sección <em>Próximos partidos</em>.</div>
          </li>
          <li class="tut-step">
            <div class="step-num">2</div>
            <div class="step-text">Toca <strong>Ver todos</strong> para ver todos los partidos de la temporada.</div>
          </li>
          <li class="tut-step">
            <div class="step-num">3</div>
            <div class="step-text">Los partidos con <span style="color:#ef4444;font-weight:700">fondo rojo</span> tienen fecha vencida o resultado pendiente.</div>
          </li>
          <li class="tut-step">
            <div class="step-num">4</div>
            <div class="step-text">En la sección <strong>Historial</strong> del dashboard verás los resultados de partidos ya jugados.</div>
          </li>
        </ul>
        <div class="tut-tip">
          <span>🔔</span>
          <div>Activa las notificaciones push para recibir alertas automáticas <strong>24h, 12h y 3h</strong> antes de cada partido.</div>
        </div>
        <a href="dashboard.php" class="tut-btn">
          Ir al Inicio →
        </a>
      </div>
    </div>

    <!-- ── 4. Inscribirse a torneos ── -->
    <div class="tut-card" onclick="toggleTut(this)">
      <div class="tut-card-head">
        <div class="tut-icon" style="background:#fef3c7;">🏅</div>
        <div>
          <h2>Inscribirse a torneo</h2>
          <small>Unirse a liga o torneo</small>
        </div>
      </div>
      <div class="tut-body">
        <ul class="tut-steps">
          <li class="tut-step">
            <div class="step-num">1</div>
            <div class="step-text">Toca <strong>Torneos</strong> en el menú superior o inferior.</div>
          </li>
          <li class="tut-step">
            <div class="step-num">2</div>
            <div class="step-text">Revisa los torneos <span style="color:#16a34a;font-weight:700">abiertos</span> para inscripción.</div>
          </li>
          <li class="tut-step">
            <div class="step-num">3</div>
            <div class="step-text">Toca el torneo que te interesa y lee las bases (categoría, fechas, costo).</div>
          </li>
          <li class="tut-step">
            <div class="step-num">4</div>
            <div class="step-text">Presiona <strong>Inscribirme</strong> e ingresa los datos de tu pareja de juego.</div>
          </li>
          <li class="tut-step">
            <div class="step-num">5</div>
            <div class="step-text">El administrador confirmará tu inscripción y recibirás una notificación.</div>
          </li>
        </ul>
        <div class="tut-tip">
          <span>👥</span>
          <div>Necesitas un compañero para inscribirte. Si no tienes pareja, puedes buscar en el grupo de WhatsApp de EPL.</div>
        </div>
        <a href="torneos.php" class="tut-btn">
          Ver Torneos →
        </a>
      </div>
    </div>

  </div>

  <!-- ── Sección contacto ── -->
  <p class="tut-section-title">¿Tienes dudas?</p>
  <div style="background:#fff;border-radius:14px;box-shadow:0 2px 12px rgba(0,0,0,.07);padding:1rem 1.25rem;display:flex;align-items:center;gap:1rem;">
    <div style="font-size:1.8rem;flex-shrink:0">💬</div>
    <div style="flex:1">
      <div style="font-weight:800;font-size:.88rem;color:#1c2f48">Contacta al organizador</div>
      <div style="font-size:.75rem;color:#64748b;margin-top:.15rem">Si algo no está claro o tienes un problema, escríbenos por WhatsApp.</div>
    </div>
    <a href="https://wa.me/56999999999?text=Hola%2C+tengo+una+pregunta+sobre+EPL" target="_blank"
       style="background:#25d366;color:#fff;border:none;border-radius:10px;padding:.55rem .9rem;font-size:.75rem;font-weight:900;text-decoration:none;text-transform:uppercase;white-space:nowrap;flex-shrink:0">
      WhatsApp
    </a>
  </div>

</div>

<script>
function toggleTut(card) {
  const body    = card.querySelector('.tut-body');
  const wasOpen = body.classList.contains('open');

  // Cerrar todos
  document.querySelectorAll('.tut-body').forEach(b => b.classList.remove('open'));
  document.querySelectorAll('.tut-card').forEach(c => c.classList.remove('open'));

  // Abrir si estaba cerrado
  if (!wasOpen) {
    body.classList.add('open');
    card.classList.add('open');
    card.scrollIntoView({ behavior:'smooth', block:'nearest' });
  }
}
</script>

<?php
$dash_active = 'tutoriales';
require_once 'includes/dash_mobile_nav.php';
require_once 'includes/footer.php';
?>
