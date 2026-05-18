<?php
$page_title = 'Tutoriales';
$player_tab = 'tutoriales';
require_once 'includes/auth.php';
require_once 'includes/functions.php';
epl_require_login();
require_once 'includes/header.php';
?>

<style>
.tut-grid { display:grid; grid-template-columns:1fr 1fr; gap:.85rem; }
@media(max-width:600px){ .tut-grid { grid-template-columns:1fr; } }

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

.tut-body {
    display:none;
    padding:0 1rem 1rem;
    border-top:1px solid #f1f5f9;
    animation:fadeDown .25s ease;
}
.tut-body.open { display:block; }
@keyframes fadeDown { from{opacity:0;transform:translateY(-6px)} to{opacity:1;transform:translateY(0)} }

.tut-steps { list-style:none; padding:0; margin:.75rem 0 0; display:flex; flex-direction:column; gap:.6rem; }
.tut-step  { display:flex; align-items:flex-start; gap:.65rem; }
.step-num  {
    width:26px; height:26px; border-radius:50%;
    background:#1c2f48; color:#fff;
    font-size:.72rem; font-weight:900;
    display:flex; align-items:center; justify-content:center;
    flex-shrink:0; margin-top:.05rem;
}
.step-text { font-size:.82rem; color:#374151; line-height:1.45; }
.step-text strong { color:#1c2f48; }

.tut-tip {
    background:#fefce8; border:1px solid #fde68a;
    border-radius:10px; padding:.6rem .75rem;
    font-size:.78rem; color:#92400e;
    margin-top:.85rem; display:flex; gap:.5rem; align-items:flex-start;
}
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
</style>

<div class="dash-layout">
  <?php include 'includes/player_sidebar.php'; ?>

  <main class="dash-main">
    <div class="dash-header">
      <h1 class="dash-title">📖 Tutoriales</h1>
      <p style="color:#64748b;font-size:.85rem;margin:.25rem 0 0">Toca una guía para ver los pasos</p>
    </div>

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
            <li class="tut-step"><div class="step-num">1</div><div class="step-text">Ve a <strong>Ingresar resultado</strong> en el menú lateral.</div></li>
            <li class="tut-step"><div class="step-num">2</div><div class="step-text">Verás la lista de tus partidos pendientes de puntuar.</div></li>
            <li class="tut-step"><div class="step-num">3</div><div class="step-text">Selecciona el partido y completa el <strong>marcador set a set</strong>.</div></li>
            <li class="tut-step"><div class="step-num">4</div><div class="step-text">Presiona <strong>Guardar resultado</strong>. El rival recibirá una notificación.</div></li>
          </ul>
          <div class="tut-tip"><span>⚠️</span><div>Solo puedes registrar partidos ya jugados. Si el rival disputa el resultado, el administrador lo resolverá.</div></div>
          <a href="ingresar_resultado.php" class="tut-btn">Ir a Resultados →</a>
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
            <li class="tut-step"><div class="step-num">1</div><div class="step-text">Ve a <strong>Reprogramar partido</strong> en el menú lateral.</div></li>
            <li class="tut-step"><div class="step-num">2</div><div class="step-text">Selecciona el partido cuya fecha quieres cambiar.</div></li>
            <li class="tut-step"><div class="step-num">3</div><div class="step-text">Elige la <strong>nueva fecha y hora</strong> acordada con el rival.</div></li>
            <li class="tut-step"><div class="step-num">4</div><div class="step-text">Escribe un motivo y presiona <strong>Solicitar reprogramación</strong>.</div></li>
          </ul>
          <div class="tut-tip"><span>💡</span><div>Coordina siempre la nueva fecha con tu rival <strong>antes</strong> de solicitarla.</div></div>
          <a href="reprogramar.php" class="tut-btn">Ir a Reprogramar →</a>
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
            <li class="tut-step"><div class="step-num">1</div><div class="step-text">Desde el <strong>Dashboard</strong> verás tus próximos partidos en la sección <em>Próximos partidos</em>.</div></li>
            <li class="tut-step"><div class="step-num">2</div><div class="step-text">Toca <strong>Ver todos</strong> para ver el calendario completo de la temporada.</div></li>
            <li class="tut-step"><div class="step-num">3</div><div class="step-text">Los partidos en <span style="color:#ef4444;font-weight:700">rojo</span> tienen fecha vencida o resultado pendiente.</div></li>
            <li class="tut-step"><div class="step-num">4</div><div class="step-text">En <strong>Historial</strong> verás los resultados de partidos ya jugados.</div></li>
          </ul>
          <div class="tut-tip"><span>🔔</span><div>Activa las notificaciones para recibir alertas <strong>24h, 12h y 3h</strong> antes de cada partido.</div></div>
          <a href="dashboard.php" class="tut-btn">Ir al Dashboard →</a>
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
            <li class="tut-step"><div class="step-num">1</div><div class="step-text">Ve a <strong>Inscripciones</strong> en el menú lateral.</div></li>
            <li class="tut-step"><div class="step-num">2</div><div class="step-text">Revisa los torneos <span style="color:#16a34a;font-weight:700">abiertos</span> para inscripción.</div></li>
            <li class="tut-step"><div class="step-num">3</div><div class="step-text">Lee las bases: categoría, fechas y costo.</div></li>
            <li class="tut-step"><div class="step-num">4</div><div class="step-text">Presiona <strong>Inscribirme</strong> e ingresa los datos de tu pareja.</div></li>
            <li class="tut-step"><div class="step-num">5</div><div class="step-text">El administrador confirmará y recibirás una notificación.</div></li>
          </ul>
          <div class="tut-tip"><span>👥</span><div>Necesitas un compañero para inscribirte. Si no tienes pareja, consulta en el grupo de WhatsApp de EPL.</div></div>
          <a href="inscribirse.php" class="tut-btn">Ver Torneos →</a>
        </div>
      </div>

    </div><!-- /tut-grid -->

  </main>
</div>

<script>
function toggleTut(card) {
  const body    = card.querySelector('.tut-body');
  const wasOpen = body.classList.contains('open');
  document.querySelectorAll('.tut-body').forEach(b => b.classList.remove('open'));
  document.querySelectorAll('.tut-card').forEach(c => c.classList.remove('open'));
  if (!wasOpen) {
    body.classList.add('open');
    card.classList.add('open');
  }
}
</script>

<?php require_once 'includes/footer.php'; ?>
