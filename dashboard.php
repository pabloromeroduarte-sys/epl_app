<?php
$page_title = 'Mi Dashboard';
$player_tab = 'dashboard';
require_once 'includes/auth.php';
require_once 'includes/functions.php';
epl_require_login();

$jugador = epl_jugador_actual();
$liga    = epl_liga_activa();
$db      = epl_db();
$equipo  = $liga ? epl_equipo_del_jugador($jugador['id'], $liga['id']) : null;
$partidos = $equipo ? epl_partidos_equipo($equipo['id']) : [];

// Stats del equipo en la liga
$stats = null;
if ($equipo && $liga) {
    $st = $db->prepare("SELECT * FROM clasificacion WHERE liga_id=? AND equipo_id=?");
    $st->execute([$liga['id'], $equipo['id']]);
    $stats = $st->fetch();
}

// Calcular % rendimiento
$rendimiento = 0;
if ($stats && $stats['pj'] > 0) {
    $rendimiento = round(($stats['pg'] / $stats['pj']) * 100);
}

$proximos  = array_filter($partidos, fn($p) => $p['estado'] === 'pendiente' || $p['estado'] === 'reprogramado');
$jugados   = array_filter($partidos, fn($p) => $p['estado'] === 'jugado');
// Ordenar próximos de más cercano a más lejano
usort($proximos, fn($a, $b) => strtotime($a['fecha_programada'] ?? '9999-12-31') <=> strtotime($b['fecha_programada'] ?? '9999-12-31'));
$proximos  = array_values($proximos); // todos, el JS limita a 3 visibles
$recientes = array_slice(array_values($jugados), 0, 5);

// ── Partidos como Galleta (suplente) ────────────────────────────────────────
$partidos_galleta = [];
if ($liga) {
    try {
        $stG = $db->prepare("
            SELECT p.id, p.fecha_programada, p.fecha_jugado, p.estado,
                   p.sets_local, p.sets_visitante,
                   p.games_s1_local, p.games_s1_visitante,
                   p.games_s2_local, p.games_s2_visitante,
                   p.games_s3_local, p.games_s3_visitante,
                   p.ganador_id, p.jornada,
                   el.nombre AS local_nombre, ev.nombre AS visitante_nombre,
                   eq.nombre AS equipo_sup_nombre, eq.id AS equipo_sup_id
            FROM suplente_partidos sp
            JOIN suplentes s  ON s.id  = sp.suplente_id
            JOIN partidos p   ON p.id  = sp.partido_id
            JOIN equipos el   ON el.id = p.equipo_local_id
            JOIN equipos ev   ON ev.id = p.equipo_visitante_id
            JOIN equipos eq   ON eq.id = s.equipo_id
            WHERE s.jugador_id = ? AND s.liga_id = ?
            ORDER BY p.fecha_programada DESC
        ");
        $stG->execute([$jugador['id'], $liga['id']]);
        $partidos_galleta = $stG->fetchAll();
    } catch (Throwable $e) { /* tabla puede no existir aún */ }
}

// Alerta de atrasos: partidos con fecha pasada sin resultado, o en estado reprogramado
$atrasados = [];
$partido_urgente = null; // el más cercano sin resultado (para el CTA grande)
if ($equipo) {
    $hoy = date('Y-m-d H:i:s');
    $stA = $db->prepare("
        SELECT p.*, el.nombre AS local_nombre, ev.nombre AS visitante_nombre
        FROM partidos p
        JOIN equipos el ON el.id = p.equipo_local_id
        JOIN equipos ev ON ev.id = p.equipo_visitante_id
        LEFT JOIN recintos r ON r.id = p.recinto_id
        WHERE (p.equipo_local_id=? OR p.equipo_visitante_id=?)
          AND (
            (p.estado IN ('pendiente','reprogramado') AND p.fecha_programada IS NOT NULL AND p.fecha_programada < ?)
          )
        ORDER BY p.fecha_programada DESC
    ");
    $stA->execute([$equipo['id'], $equipo['id'], $hoy]);
    $atrasados = $stA->fetchAll();
    // El primero (más reciente) es el más urgente
    if (!empty($atrasados)) {
        $partido_urgente = $atrasados[0];
    }
}
?>
<?php require_once 'includes/header.php'; ?>


<div class="dash-layout">
<?php include __DIR__ . '/includes/player_sidebar.php'; ?>
<main class="dash-main">
    <div class="dash-header" style="background:linear-gradient(135deg,#1c2f48 0%, #0f1e30 100%);border-radius:18px;padding:1.5rem 1.75rem;color:#fff;margin-bottom:1.5rem;position:relative;overflow:hidden;box-shadow:0 8px 28px rgba(28,47,72,.18)">
      <div style="position:absolute;top:-40px;right:-40px;width:180px;height:180px;background:radial-gradient(circle,rgba(201,167,98,.18) 0%,transparent 70%);pointer-events:none"></div>
      <div style="position:relative;z-index:1">
        <span style="font-size:.65rem;font-weight:900;letter-spacing:.25em;color:#C9A762;text-transform:uppercase">Tu panel</span>
        <h1 class="dash-title" style="color:#fff;margin:.2rem 0 .15rem;font-size:clamp(1.6rem,4vw,2.2rem);font-family:'Anton',sans-serif;text-transform:uppercase;line-height:1">Hola, <span style="color:#C9A762"><?= epl_h($jugador['nombre']) ?></span></h1>
        <?php if ($liga): ?>
          <p style="color:rgba(255,255,255,.7);margin-top:.3rem;font-size:.85rem;font-weight:600"><?= epl_h($liga['nombre']) ?> · <?= epl_h($liga['temporada'] ?? 'Temporada en curso') ?></p>
        <?php else: ?>
          <p style="color:rgba(255,255,255,.6);margin-top:.3rem;font-size:.85rem">Listo para tu próxima temporada en EPL</p>
        <?php endif; ?>
      </div>
    </div>

    <?php
    // Mostrar banner solo si el usuario NO tiene suscripción push guardada en BD
    $tiene_push = false;
    try {
        $st = epl_db()->prepare("SELECT 1 FROM push_subscriptions WHERE jugador_id = ? LIMIT 1");
        $st->execute([$jugador['id']]);
        $tiene_push = (bool)$st->fetchColumn();
    } catch (Throwable $e) { }
    ?>
    <?php if ($partido_urgente): ?>
    <?php
      $pu = $partido_urgente;
      $pu_fecha = $pu['fecha_programada'] ? date('d/m/Y', strtotime($pu['fecha_programada'])) : null;
      $pu_hoy   = $pu['fecha_programada'] && date('Y-m-d', strtotime($pu['fecha_programada'])) === date('Y-m-d');
      $pu_rival = $pu['equipo_local_id'] == $equipo['id'] ? $pu['visitante_nombre'] : $pu['local_nombre'];
      $pu_dias  = $pu['fecha_programada'] ? (int)floor((time() - strtotime($pu['fecha_programada'])) / 86400) : 0;
      $pu_label = $pu_hoy ? 'HOY' : ($pu_dias <= 1 ? 'AYER' : "HACE {$pu_dias} DÍAS");
    ?>
    <a href="<?= epl_url('ingresar_resultado.php') ?>" style="display:flex;align-items:center;gap:1rem;background:linear-gradient(135deg,#c9a762,#b8934f);border-radius:14px;padding:1rem 1.25rem;margin-bottom:1.25rem;text-decoration:none;box-shadow:0 4px 20px rgba(201,167,98,.35)">
      <span style="font-size:2rem;flex-shrink:0">📝</span>
      <div style="flex:1;min-width:0">
        <div style="font-weight:900;font-size:.95rem;color:#1c2f48;text-transform:uppercase;letter-spacing:.03em">
          Ingresar resultado — <?= epl_h($pu_rival) ?>
        </div>
        <div style="font-size:.78rem;color:rgba(28,47,72,.75);margin-top:.15rem">
          Partido del <?= epl_h($pu_label) ?><?= $pu_fecha ? ' · ' . $pu_fecha : '' ?> · Pendiente de registro
        </div>
      </div>
      <svg style="flex-shrink:0;color:#1c2f48" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
    </a>
    <?php endif; ?>

    <?php if (!$tiene_push): ?>
    <!-- Banner push: 1 botón único que hace TODO -->
    <div id="bannerPush" style="display:none;background:linear-gradient(135deg,#1c2f48,#1a3a64);border-radius:14px;padding:1rem 1.25rem;margin-bottom:1.25rem;align-items:center;gap:.85rem;flex-wrap:wrap">
      <span id="bpIcon" style="font-size:1.4rem;flex-shrink:0">🔔</span>
      <div style="flex:1;min-width:160px">
        <div id="bpTitulo" style="font-weight:800;font-size:.88rem;color:#fff">Activá las notificaciones</div>
        <div id="bpSub" style="font-size:.75rem;color:rgba(255,255,255,.7);margin-top:.15rem">Recibí alertas de partidos y resultados.</div>
      </div>
      <div style="display:flex;gap:.5rem;flex-shrink:0">
        <button id="bpBtn" onclick="bpAccion()" style="background:var(--gold);color:var(--navy);border:none;border-radius:8px;padding:.6rem 1.1rem;font-weight:800;font-size:.8rem;cursor:pointer">Activar</button>
        <button onclick="this.closest('#bannerPush').remove()" title="Ocultar" style="background:rgba(255,255,255,.15);color:#fff;border:none;border-radius:8px;padding:.5rem .75rem;font-size:.8rem;cursor:pointer">×</button>
      </div>
    </div>

    <!-- Modal: 1 solo mensaje claro cuando está bloqueado tras intentar resetear -->
    <style>
    .puls { animation: bpPulse 1.4s ease-in-out infinite; transform-origin: center; }
    @keyframes bpPulse { 0%,100% { opacity:1; transform:scale(1);} 50% { opacity:.65; transform:scale(1.15);} }
    </style>
    <div id="bpModal" style="display:none;position:fixed;inset:0;background:rgba(10,20,33,.78);backdrop-filter:blur(6px);z-index:99999;align-items:center;justify-content:center;padding:1rem" onclick="if(event.target===this)bpCerrarModal()">
      <div style="background:#fff;border-radius:18px;width:100%;max-width:420px;box-shadow:0 20px 60px rgba(0,0,0,.3);overflow:hidden">
        <div style="background:linear-gradient(135deg,#1c2f48,#0f1e30);padding:1.1rem 1.4rem;color:#fff;display:flex;justify-content:space-between;align-items:center">
          <h3 style="margin:0;font-family:'Anton',sans-serif;font-size:1.05rem;text-transform:uppercase">🔕 Bloqueado por el navegador</h3>
          <button onclick="bpCerrarModal()" style="background:none;border:none;color:rgba(255,255,255,.6);font-size:1.6rem;cursor:pointer;line-height:1;padding:0 .3rem">×</button>
        </div>
        <div id="bpInstrucciones" style="padding:1.4rem"></div>
      </div>
    </div>

    <script>
    (function() {
      const VAPID_PUBLIC = "<?= htmlspecialchars(epl_env('VAPID_PUBLIC_KEY'), ENT_QUOTES) ?>";
      const banner = document.getElementById('bannerPush');
      const iconEl = document.getElementById('bpIcon');
      const titEl  = document.getElementById('bpTitulo');
      const subEl  = document.getElementById('bpSub');
      const btnEl  = document.getElementById('bpBtn');

      function urlB64(b64){const pad='='.repeat((4-b64.length%4)%4);const raw=atob((b64+pad).replace(/-/g,'+').replace(/_/g,'/'));return Uint8Array.from([...raw].map(c=>c.charCodeAt(0)));}

      function toast(msg, ok = true) {
        const t = document.createElement('div');
        t.textContent = msg;
        t.style.cssText = 'position:fixed;bottom:80px;left:50%;transform:translateX(-50%);background:'+(ok?'#15803d':'#dc2626')+';color:#fff;padding:.75rem 1.25rem;border-radius:10px;font-weight:700;font-size:.85rem;z-index:99999;box-shadow:0 8px 24px rgba(0,0,0,.2);max-width:90vw;text-align:center';
        document.body.appendChild(t);
        setTimeout(()=>t.remove(), 4000);
      }

      function detectBrowser() {
        const ua = navigator.userAgent;
        if (/iPhone|iPad|iPod/.test(ua)) return 'ios';
        if (/SamsungBrowser/.test(ua))   return 'samsung';
        if (/Chrome/.test(ua) && /Android/.test(ua)) return 'chrome-android';
        if (/Firefox/.test(ua)) return 'firefox';
        return 'otro';
      }

      function estadoActual() {
        if (!('Notification' in window)) return 'no-soportado';
        return Notification.permission;
      }

      function renderBanner() {
        const est = estadoActual();
        if (est === 'no-soportado') return;
        if (est === 'granted') {
          // Tiene permiso pero no hay sub: resuscribir silencioso
          window.bpAccion(true);
          return;
        }
        banner.style.display = 'flex';
        if (est === 'denied') {
          iconEl.textContent = '🔕';
          titEl.textContent  = 'Notificaciones bloqueadas';
          subEl.innerHTML    = 'Tocá <strong>Restablecer</strong> para limpiar y reactivar.';
          btnEl.textContent  = '🔄 Restablecer';
        } else {
          iconEl.textContent = '🔔';
          titEl.textContent  = 'Activá las notificaciones';
          subEl.textContent  = 'Recibí alertas de partidos y resultados.';
          btnEl.textContent  = 'Activar';
        }
      }

      /**
       * Botón único — hace TODO:
       * 1. Desuscribe del browser (unsubscribe)
       * 2. Borra del backend (push_unsubscribe.php)
       * 3. Desinstala el SW para forzar contexto limpio
       * 4. Reintenta requestPermission()
       * 5. Si permiso = granted → suscribe limpio
       * 6. Si permiso = denied → abre modal con ÚNICO paso visual claro
       */
      window.bpAccion = function(silent = false) {
        btnEl.disabled = true;
        btnEl.textContent = '⏳ Procesando…';

        var handlePerm = async function(perm) {
          try {
            if (perm === 'granted') {
              // Si fue granted, ahora limpiamos sub vieja si existe y suscribimos
              if ('serviceWorker' in navigator) {
                try {
                  const reg = await navigator.serviceWorker.ready;
                  const sub = await reg.pushManager.getSubscription();
                  if (sub) await sub.unsubscribe();
                } catch(e) {}
              }
              try {
                await fetch('/push_unsubscribe.php', { method: 'POST', headers: {'Content-Type':'application/json'}, body: '{}' });
              } catch(e) {}

              const reg = await navigator.serviceWorker.ready;
              const sub = await reg.pushManager.subscribe({
                userVisibleOnly: true,
                applicationServerKey: urlB64(VAPID_PUBLIC)
              });
              await fetch('/push_subscribe.php', {
                method: 'POST',
                headers: {'Content-Type':'application/json'},
                body: JSON.stringify(sub)
              });
              banner.style.display = 'none';
              if (!silent) toast('✅ Notificaciones activadas correctamente');
            } else if (perm === 'denied') {
              bpAbrirModal();
            }
          } catch(e) {
            console.warn('push:', e);
            toast('Error al activar. Recargá e intentá de nuevo.', false);
          } finally {
            btnEl.disabled = false;
            renderBanner();
          }
        };

        try {
          var promise = Notification.requestPermission(handlePerm);
          if (promise && typeof promise.then === 'function') {
            promise.then(handlePerm).catch(function(e){ 
               console.warn(e); 
               btnEl.disabled = false;
               renderBanner();
            });
          }
        } catch(e) { 
          console.warn(e); 
          btnEl.disabled = false;
          renderBanner();
        }
      };

      // Instrucción única (1 paso) por browser
      const PASO_UNICO = {
        'samsung': {
          titulo: 'Samsung Internet',
          msg:    'Tocá el <strong>candado 🔒</strong> en la barra de URL arriba → <strong>Permisos</strong> → activá <strong>Notificaciones</strong>.',
          svg: `<svg viewBox="0 0 320 80" xmlns="http://www.w3.org/2000/svg" style="width:100%;height:auto">
            <rect x="10" y="15" width="300" height="50" rx="25" fill="#f1f5f9" stroke="#cbd5e1" stroke-width="1.5"/>
            <circle cx="40" cy="40" r="16" fill="#fef3c7" stroke="#f59e0b" stroke-width="3" class="puls"/>
            <path d="M34 38 v-4 a6 6 0 0 1 12 0 v4 M31 38 h18 v12 h-18 z" fill="none" stroke="#92400e" stroke-width="2"/>
            <text x="78" y="46" font-family="monospace" font-size="14" fill="#475569">epleague.cl</text>
          </svg>`
        },
        'chrome-android': {
          titulo: 'Chrome Android',
          msg:    'Tocá el <strong>candado 🔒</strong> en la barra arriba → <strong>Permisos y privacidad</strong> → activá <strong>Notificaciones</strong>.',
          svg: `<svg viewBox="0 0 320 80" xmlns="http://www.w3.org/2000/svg" style="width:100%;height:auto">
            <rect x="10" y="15" width="300" height="50" rx="25" fill="#f1f5f9" stroke="#cbd5e1" stroke-width="1.5"/>
            <circle cx="40" cy="40" r="16" fill="#fef3c7" stroke="#f59e0b" stroke-width="3" class="puls"/>
            <path d="M34 38 v-4 a6 6 0 0 1 12 0 v4 M31 38 h18 v12 h-18 z" fill="none" stroke="#92400e" stroke-width="2"/>
            <text x="78" y="46" font-family="monospace" font-size="14" fill="#475569">epleague.cl</text>
          </svg>`
        },
        'ios': {
          titulo: 'iPhone / iPad',
          msg:    'En iOS necesitás <strong>instalar la app primero</strong>: en Safari → botón compartir ⬆ → <strong>Añadir a pantalla de inicio</strong>. Después abrila desde el icono nuevo.',
          svg: `<svg viewBox="0 0 320 100" xmlns="http://www.w3.org/2000/svg" style="width:100%;height:auto">
            <rect x="120" y="10" width="80" height="80" rx="18" fill="#1c2f48" stroke="#C9A762" stroke-width="2" class="puls"/>
            <text x="160" y="55" text-anchor="middle" font-family="sans-serif" font-size="22" fill="#C9A762">🏆</text>
            <text x="160" y="100" text-anchor="middle" font-family="sans-serif" font-size="11" font-weight="700" fill="#1c2f48">EPL</text>
          </svg>`
        },
        'firefox': {
          titulo: 'Firefox',
          msg:    'Click en el <strong>candado 🔒</strong> al lado de la URL → buscá <strong>Notificaciones</strong> → click en la <strong>X</strong> al lado de "Bloqueado temporalmente".',
          svg: ''
        },
        'otro': {
          titulo: 'Tu navegador',
          msg:    'Tocá el <strong>candado 🔒</strong> al lado de <code>epleague.cl</code> en la barra de arriba → buscá <strong>Notificaciones</strong> → cambialo a <strong>Permitir</strong>.',
          svg: ''
        }
      };

      window.bpAbrirModal = function() {
        const b = detectBrowser();
        const info = PASO_UNICO[b] || PASO_UNICO['otro'];
        const cont = document.getElementById('bpInstrucciones');
        cont.innerHTML = `
          <p style="text-align:center;color:#64748b;font-size:.8rem;margin:0 0 1rem">
            Tu navegador no permite que activemos las notificaciones desde la app — pero te toma <strong>30 segundos</strong>:
          </p>
          ${info.svg ? '<div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:12px;padding:1rem;margin-bottom:1rem">' + info.svg + '</div>' : ''}
          <div style="background:#fef3c7;border-left:4px solid #f59e0b;padding:.85rem 1rem;border-radius:8px;font-size:.88rem;line-height:1.55;color:#1c2f48">
            ${info.msg}
          </div>
          <button onclick="bpVerificar()" style="width:100%;background:var(--gold);color:var(--navy);border:none;padding:.85rem;border-radius:10px;font-weight:800;text-transform:uppercase;letter-spacing:.05em;font-size:.78rem;cursor:pointer;margin-top:1rem;font-family:inherit">
            ✓ Ya lo hice
          </button>
        `;
        document.getElementById('bpModal').style.display = 'flex';
      };

      // Pasos visuales por browser (NO USADO ya — mantenido por compatibilidad)
      const PASOS_legacy = {
        'samsung': [
          {
            txt: 'Tocá el <strong>candado 🔒</strong> a la izquierda de <code>epleague.cl</code> en la barra de arriba',
            svg: `<svg viewBox="0 0 320 80" xmlns="http://www.w3.org/2000/svg">
              <rect x="10" y="15" width="300" height="50" rx="25" fill="#f1f5f9" stroke="#cbd5e1" stroke-width="1.5"/>
              <circle cx="40" cy="40" r="15" fill="#fef3c7" stroke="#f59e0b" stroke-width="3" class="puls"/>
              <path d="M35 38 v-4 a5 5 0 0 1 10 0 v4 M32 38 h16 v10 h-16 z" fill="none" stroke="#92400e" stroke-width="2"/>
              <text x="75" y="46" font-family="monospace" font-size="14" fill="#475569">epleague.cl</text>
              <path d="M65 25 L40 35" stroke="#dc2626" stroke-width="2.5" fill="none" marker-end="url(#arr)"/>
              <defs><marker id="arr" markerWidth="10" markerHeight="10" refX="9" refY="3" orient="auto"><path d="M0,0 L0,6 L9,3 z" fill="#dc2626"/></marker></defs>
            </svg>`
          },
          {
            txt: 'Se abre un menú. Tocá <strong>"Permisos"</strong>',
            svg: `<svg viewBox="0 0 320 180" xmlns="http://www.w3.org/2000/svg">
              <rect x="20" y="10" width="280" height="160" rx="12" fill="#fff" stroke="#cbd5e1" stroke-width="1.5"/>
              <text x="40" y="40" font-family="sans-serif" font-size="13" font-weight="700" fill="#1c2f48">Información del sitio</text>
              <line x1="20" y1="55" x2="300" y2="55" stroke="#e2e8f0"/>
              <text x="40" y="80" font-family="sans-serif" font-size="13" fill="#64748b">🌐 Conexión segura</text>
              <rect x="20" y="100" width="280" height="36" fill="#fef3c7" stroke="#f59e0b" stroke-width="2" class="puls"/>
              <text x="40" y="123" font-family="sans-serif" font-size="14" font-weight="800" fill="#92400e">🔐 Permisos</text>
              <text x="280" y="123" font-family="sans-serif" font-size="14" fill="#92400e" text-anchor="end">›</text>
              <text x="40" y="158" font-family="sans-serif" font-size="13" fill="#64748b">🍪 Cookies</text>
            </svg>`
          },
          {
            txt: 'Buscá <strong>"Notificaciones"</strong> y cambialo a <strong>Permitir</strong>',
            svg: `<svg viewBox="0 0 320 180" xmlns="http://www.w3.org/2000/svg">
              <rect x="20" y="10" width="280" height="160" rx="12" fill="#fff" stroke="#cbd5e1" stroke-width="1.5"/>
              <text x="40" y="35" font-family="sans-serif" font-size="13" font-weight="700" fill="#1c2f48">Permisos</text>
              <line x1="20" y1="50" x2="300" y2="50" stroke="#e2e8f0"/>
              <text x="40" y="75" font-family="sans-serif" font-size="13" fill="#64748b">📷 Cámara</text>
              <text x="280" y="75" font-family="sans-serif" font-size="13" fill="#94a3b8" text-anchor="end">Bloqueado</text>
              <rect x="20" y="92" width="280" height="40" fill="#fef3c7" stroke="#f59e0b" stroke-width="2" class="puls"/>
              <text x="40" y="118" font-family="sans-serif" font-size="14" font-weight="800" fill="#92400e">🔔 Notificaciones</text>
              <rect x="225" y="103" width="55" height="22" rx="11" fill="#dc2626"/>
              <circle cx="240" cy="114" r="9" fill="#fff"/>
              <text x="280" y="118" font-family="sans-serif" font-size="11" fill="#fff" text-anchor="end">OFF</text>
              <path d="M170 142 L260 132" stroke="#dc2626" stroke-width="2.5" fill="none" marker-end="url(#arr2)"/>
              <defs><marker id="arr2" markerWidth="10" markerHeight="10" refX="9" refY="3" orient="auto"><path d="M0,0 L0,6 L9,3 z" fill="#dc2626"/></marker></defs>
              <text x="40" y="160" font-family="sans-serif" font-size="13" fill="#64748b">📍 Ubicación</text>
            </svg>`
          },
          {
            txt: 'Activá el switch → queda <strong>verde</strong>. Volvé acá y tocá <strong>"Ya las activé"</strong>',
            svg: `<svg viewBox="0 0 320 120" xmlns="http://www.w3.org/2000/svg">
              <rect x="20" y="20" width="280" height="80" rx="12" fill="#dcfce7" stroke="#10b981" stroke-width="2"/>
              <text x="40" y="55" font-family="sans-serif" font-size="14" font-weight="800" fill="#15803d">🔔 Notificaciones</text>
              <rect x="225" y="40" width="55" height="22" rx="11" fill="#10b981"/>
              <circle cx="265" cy="51" r="9" fill="#fff" class="puls"/>
              <text x="280" y="55" font-family="sans-serif" font-size="11" fill="#fff" text-anchor="end">ON</text>
              <text x="160" y="85" font-family="sans-serif" font-size="11" fill="#15803d" text-anchor="middle">✓ Activado correctamente</text>
            </svg>`
          },
        ],
        'chrome-android': [
          {
            txt: 'Tocá el <strong>candado 🔒</strong> al lado de la URL arriba',
            svg: `<svg viewBox="0 0 320 80" xmlns="http://www.w3.org/2000/svg">
              <rect x="10" y="15" width="300" height="50" rx="25" fill="#f1f5f9"/>
              <circle cx="40" cy="40" r="15" fill="#fef3c7" stroke="#f59e0b" stroke-width="3" class="puls"/>
              <path d="M35 38 v-4 a5 5 0 0 1 10 0 v4 M32 38 h16 v10 h-16 z" fill="none" stroke="#92400e" stroke-width="2"/>
              <text x="75" y="46" font-family="monospace" font-size="14" fill="#475569">epleague.cl</text>
            </svg>`
          },
          {
            txt: 'Tocá <strong>"Permisos y privacidad"</strong>',
            svg: `<svg viewBox="0 0 320 140" xmlns="http://www.w3.org/2000/svg">
              <rect x="20" y="10" width="280" height="120" rx="12" fill="#fff" stroke="#cbd5e1"/>
              <text x="40" y="40" font-family="sans-serif" font-size="13" fill="#64748b">🛡️ Conexión segura</text>
              <rect x="20" y="60" width="280" height="40" fill="#fef3c7" stroke="#f59e0b" stroke-width="2" class="puls"/>
              <text x="40" y="85" font-family="sans-serif" font-size="14" font-weight="800" fill="#92400e">🔐 Permisos y privacidad</text>
              <text x="280" y="85" font-family="sans-serif" font-size="14" fill="#92400e" text-anchor="end">›</text>
            </svg>`
          },
          {
            txt: 'Activá <strong>"Notificaciones"</strong> → switch verde',
            svg: `<svg viewBox="0 0 320 120" xmlns="http://www.w3.org/2000/svg">
              <rect x="20" y="20" width="280" height="80" rx="12" fill="#dcfce7" stroke="#10b981" stroke-width="2"/>
              <text x="40" y="55" font-family="sans-serif" font-size="14" font-weight="800" fill="#15803d">🔔 Notificaciones</text>
              <rect x="225" y="40" width="55" height="22" rx="11" fill="#10b981"/>
              <circle cx="265" cy="51" r="9" fill="#fff" class="puls"/>
            </svg>`
          },
        ],
        'ios': [
          {
            txt: '⚠️ <strong>Importante:</strong> en iPhone solo funciona si instalás la app primero',
            svg: `<svg viewBox="0 0 320 60" xmlns="http://www.w3.org/2000/svg">
              <rect x="20" y="10" width="280" height="40" rx="20" fill="#fee2e2" stroke="#dc2626" stroke-width="2"/>
              <text x="160" y="35" text-anchor="middle" font-family="sans-serif" font-size="13" font-weight="800" fill="#991b1b">📱 Requiere instalar la PWA</text>
            </svg>`
          },
          {
            txt: 'En Safari, tocá el botón <strong>compartir ⬆</strong> abajo (cuadrado con flecha arriba)',
            svg: `<svg viewBox="0 0 320 120" xmlns="http://www.w3.org/2000/svg">
              <rect x="60" y="10" width="200" height="80" rx="12" fill="#fff" stroke="#cbd5e1"/>
              <rect x="80" y="60" width="160" height="25" rx="5" fill="#f1f5f9"/>
              <g transform="translate(150,72)">
                <rect x="-12" y="-10" width="24" height="20" fill="none" stroke="#1c2f48" stroke-width="2" rx="3" class="puls"/>
                <path d="M0,-5 L0,-15 M-5,-10 L0,-15 L5,-10" stroke="#1c2f48" stroke-width="2" fill="none"/>
              </g>
              <text x="160" y="110" text-anchor="middle" font-family="sans-serif" font-size="11" fill="#64748b">Botón compartir en Safari</text>
            </svg>`
          },
          {
            txt: 'Buscá y tocá <strong>"Añadir a pantalla de inicio"</strong>',
            svg: `<svg viewBox="0 0 320 140" xmlns="http://www.w3.org/2000/svg">
              <rect x="20" y="10" width="280" height="120" rx="12" fill="#fff" stroke="#cbd5e1"/>
              <text x="40" y="40" font-family="sans-serif" font-size="13" fill="#64748b">📋 Copiar</text>
              <text x="40" y="65" font-family="sans-serif" font-size="13" fill="#64748b">🔖 Marcadores</text>
              <rect x="20" y="80" width="280" height="40" fill="#fef3c7" stroke="#f59e0b" stroke-width="2" class="puls"/>
              <text x="40" y="105" font-family="sans-serif" font-size="14" font-weight="800" fill="#92400e">➕ Añadir a inicio</text>
            </svg>`
          },
          {
            txt: 'Cerrá Safari y abrí EPL desde el <strong>icono nuevo</strong> en tu pantalla',
            svg: `<svg viewBox="0 0 320 120" xmlns="http://www.w3.org/2000/svg">
              <rect x="120" y="10" width="80" height="80" rx="18" fill="#1c2f48" stroke="#C9A762" stroke-width="2" class="puls"/>
              <text x="160" y="55" text-anchor="middle" font-family="sans-serif" font-size="22" fill="#C9A762">🏆</text>
              <text x="160" y="110" text-anchor="middle" font-family="sans-serif" font-size="11" font-weight="700" fill="#1c2f48">EPL</text>
            </svg>`
          },
          {
            txt: 'Cuando te pida permisos, tocá <strong>Permitir</strong>',
            svg: `<svg viewBox="0 0 320 100" xmlns="http://www.w3.org/2000/svg">
              <rect x="60" y="10" width="200" height="80" rx="12" fill="#fff" stroke="#cbd5e1"/>
              <text x="160" y="35" text-anchor="middle" font-family="sans-serif" font-size="11" fill="#1c2f48">¿Recibir notificaciones?</text>
              <rect x="80" y="55" width="80" height="25" rx="6" fill="#e2e8f0"/>
              <text x="120" y="72" text-anchor="middle" font-family="sans-serif" font-size="11" fill="#64748b">No</text>
              <rect x="170" y="55" width="80" height="25" rx="6" fill="#10b981" class="puls"/>
              <text x="210" y="72" text-anchor="middle" font-family="sans-serif" font-size="11" font-weight="800" fill="#fff">Permitir</text>
            </svg>`
          },
        ],
        'chrome-desktop': [
          { txt: 'Click en el <strong>🔒 candado</strong> al lado de la URL', svg: `<svg viewBox="0 0 320 80"><rect x="10" y="15" width="300" height="50" rx="5" fill="#f1f5f9"/><circle cx="40" cy="40" r="14" fill="#fef3c7" stroke="#f59e0b" stroke-width="2" class="puls"/><text x="75" y="46" font-family="monospace" font-size="14" fill="#475569">epleague.cl</text></svg>` },
          { txt: 'Buscá <strong>Notificaciones</strong> y elegí <strong>Permitir</strong>', svg: `<svg viewBox="0 0 320 120"><rect x="20" y="20" width="280" height="80" rx="12" fill="#dcfce7" stroke="#10b981" stroke-width="2"/><text x="40" y="55" font-family="sans-serif" font-size="14" font-weight="800" fill="#15803d">🔔 Notificaciones</text><text x="280" y="55" font-family="sans-serif" font-size="13" fill="#15803d" text-anchor="end">Permitir ✓</text></svg>` },
        ],
        'firefox': [
          { txt: 'Click en el <strong>🔒 candado</strong> al lado de la URL', svg: `<svg viewBox="0 0 320 80"><rect x="10" y="15" width="300" height="50" rx="5" fill="#f1f5f9"/><circle cx="40" cy="40" r="14" fill="#fef3c7" stroke="#f59e0b" stroke-width="2" class="puls"/></svg>` },
          { txt: 'Click en la <strong>X</strong> al lado de "Bloqueado temporalmente" en Notificaciones', svg: `<svg viewBox="0 0 320 80"><rect x="20" y="10" width="280" height="60" rx="8" fill="#fff" stroke="#cbd5e1"/><text x="40" y="35" font-family="sans-serif" font-size="13" font-weight="700" fill="#1c2f48">🔔 Enviar notificaciones</text><text x="40" y="55" font-family="sans-serif" font-size="11" fill="#dc2626">Bloqueado temporalmente</text><circle cx="270" cy="40" r="14" fill="#fee2e2" stroke="#dc2626" stroke-width="2" class="puls"/><text x="270" y="46" text-anchor="middle" font-family="sans-serif" font-size="16" font-weight="800" fill="#dc2626">×</text></svg>` },
        ],
      };

      let bpPasoActual = 0;
      let bpPasosArr = [];

      // (modal de carrusel viejo eliminado — ahora usamos bpAbrirModal único definido arriba)

      window.bpCerrarModal = function() {
        document.getElementById('bpModal').style.display = 'none';
      };

      window.bpVerificar = function() {
        bpCerrarModal();
        if (Notification.permission === 'granted') {
          location.reload();
        } else {
          alert('Las notificaciones todavía están bloqueadas. Seguí los pasos del modal y recargá la página.');
        }
      };

      renderBanner();
    })();
    </script>
    <?php endif; ?>

    <!-- Bienvenida nuevo registro -->
    <?php if (isset($_GET['bienvenido'])): ?>
    <div class="alert alert-success" style="display:flex;align-items:flex-start;gap:.75rem;margin-bottom:1.5rem">
      <svg style="width:20px;height:20px;flex-shrink:0;margin-top:.1rem" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
      <div>
        <strong>¡Bienvenido a Elite Padel League!</strong>
        Tu cuenta fue creada correctamente. Completa tu perfil y espera que el organizador te asigne a un equipo.
      </div>
    </div>
    <?php endif; ?>

    <!-- Alerta de atrasos (solo si hay MÁS de 1, el primero ya se muestra arriba) -->
    <?php $atrasados_extra = count($atrasados) > 1 ? array_slice($atrasados, 1) : []; ?>
    <?php if ($atrasados_extra): ?>
    <div class="alert alert-error" style="display:flex;align-items:flex-start;gap:.75rem;margin-bottom:1.5rem">
      <svg style="width:20px;height:20px;flex-shrink:0;margin-top:.1rem" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
      <div style="flex:1">
        <div style="font-weight:700;margin-bottom:.25rem">También tienes <?= count($atrasados_extra) ?> partido<?= count($atrasados_extra)>1?'s':'' ?> más sin resultado:</div>
        <ul style="margin:0 0 .75rem 0;padding:0;list-style:none;font-size:.85rem;opacity:.9">
          <?php foreach($atrasados_extra as $at): ?>
            <li style="margin-bottom:.2rem">
              • <?= epl_h($at['local_nombre'] . ' vs ' . $at['visitante_nombre']) ?>
              <span style="opacity:.7">(<?= $at['fecha_programada'] ? date('d/m/Y', strtotime($at['fecha_programada'])) : 'Sin fecha' ?>)</span>
            </li>
          <?php endforeach; ?>
        </ul>
        <a href="<?= epl_url('ingresar_resultado.php') ?>" class="btn btn-sm" style="background:#fff;color:#b91c1c;font-weight:700;border:none">Ingresar resultados →</a>
      </div>
    </div>
    <?php endif; ?>

    <!-- Stats del equipo -->
    <?php if ($stats): ?>
    <div class="stats-grid">
      <div class="stat-card">
        <div class="stat-value" style="color:var(--gold)"><?= $stats['posicion'] ?? '—' ?></div>
        <div class="stat-label">Posición</div>
      </div>
      <div class="stat-card">
        <div class="stat-value"><?= $stats['pj'] ?></div>
        <div class="stat-label">Jugados</div>
      </div>
      <div class="stat-card">
        <div class="stat-value" style="color:var(--green)"><?= $stats['pg'] ?></div>
        <div class="stat-label">Ganados</div>
      </div>
      <div class="stat-card">
        <div class="stat-value" style="color:var(--red)"><?= $stats['pp'] ?></div>
        <div class="stat-label">Perdidos</div>
      </div>
      <div class="stat-card">
        <div class="stat-value"><?= $stats['puntos'] ?></div>
        <div class="stat-label">Puntos</div>
      </div>
      <div class="stat-card">
        <div class="stat-value" style="color:<?= $rendimiento>=50?'var(--green)':'var(--red)' ?>"><?= $rendimiento ?>%</div>
        <div class="stat-label">Rendimiento</div>
      </div>
    </div>
    <?php endif; ?>

    <!-- Próximos partidos -->
    <?php if ($proximos): ?>
    <div class="card mb-4">
      <div class="card-head">
        <h3 style="font-family:var(--font-head);font-size:1rem;text-transform:uppercase;color:var(--navy)">Próximos partidos</h3>
        <div style="display:flex;align-items:center;gap:.75rem">
          <?php if (count($proximos) > 3): ?>
            <button id="btnVerTodosProximos" onclick="verTodosProximos()" style="font-size:.78rem;color:var(--gold);font-weight:600;background:none;border:none;cursor:pointer;padding:0">Ver todos →</button>
          <?php endif; ?>
          <a href="ingresar_resultado.php" class="btn btn-primary btn-sm">+ Ingresar resultado</a>
        </div>
      </div>
      <div class="card-body">
        <div class="partidos-list">
          <?php foreach ($proximos as $i => $p): ?>
          <div class="partido-card-v2 proximo-item<?= $i >= 3 ? ' proximo-extra' : '' ?>" style="padding:1rem<?= $i >= 3 ? ';display:none' : '' ?>">
            <div class="partido-col-info" style="border:none">
              <span class="fecha-label" style="font-size:.6rem">Fecha <?= $p['jornada'] ?? '' ?></span>
              <div class="partido-date" style="font-size:.7rem">🗓 <?= $p['fecha_programada'] ? date('d/m', strtotime($p['fecha_programada'])) : 'TBD' ?></div>
            </div>
            <div style="display:flex;align-items:center;justify-content:center;gap:1.5rem;flex:1">
              <div style="text-align:right;flex:1"><span class="equipo-nombre-card" style="font-size:.75rem"><?= epl_h($p['local_nombre']) ?></span></div>
              <div class="marcador-box" style="font-size:1rem;padding:.4rem .8rem;min-width:60px">VS</div>
              <div style="text-align:left;flex:1"><span class="equipo-nombre-card" style="font-size:.75rem"><?= epl_h($p['visitante_nombre']) ?></span></div>
            </div>
            <div class="partido-col-meta" style="border:none;padding-left:0">
              <?php
                $r_nombre = $p['recinto_nombre'] ?? '';
                $r_sup    = $p['recinto_superior_nombre'] ?? '';
                $r_abu    = $p['recinto_abuelo_nombre'] ?? '';
                $cancha   = $p['cancha'] ?? '';
                if ($r_abu) {
                    $badge_txt = $r_sup;
                    $label_txt = $r_abu . ($r_nombre ? ' - ' . $r_nombre : '');
                } elseif ($r_sup) {
                    $badge_txt = $r_nombre;
                    $label_txt = $r_sup;
                } else {
                    $badge_txt = $r_nombre ?: 'TBD';
                    $label_txt = '';
                }
                if ($cancha && !str_contains(strtolower((string)$label_txt), 'cancha')) {
                    $label_txt .= ($label_txt ? ' - ' : '') . 'Cancha ' . $cancha;
                }
              ?>
              <span class="cancha-badge" style="margin-bottom:0;font-size:.6rem"><?= epl_h($badge_txt) ?></span>
              <?php if ($label_txt): ?>
                <div class="sede-label" style="font-size:.55rem;margin-top:.2rem"><?= epl_h($label_txt) ?></div>
              <?php endif; ?>
              <a href="<?= epl_url('ingresar_resultado.php?partido_id=' . $p['id']) ?>" class="btn btn-primary btn-sm" style="font-size:.62rem;padding:.2rem .5rem;margin-top:.45rem;border-radius:6px;font-weight:700">
                + Resultado
              </a>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
    <?php endif; ?>

    <!-- Historial -->
    <?php if ($recientes): ?>
    <div class="card mb-4">
      <div class="card-head">
        <h3 style="font-family:var(--font-head);font-size:1rem;text-transform:uppercase;color:var(--navy)">Últimos resultados</h3>
        <a href="resultados.php" style="font-size:.78rem;color:var(--gold);font-weight:600">Ver todos →</a>
      </div>
      <div class="card-body">
        <div class="partidos-list">
          <?php foreach ($recientes as $p):
            $gane = $p['ganador_id'] == ($equipo['id'] ?? -1);
          ?>
          <div class="partido-card-v2" style="padding:1rem;border-left:4px solid <?= $gane?'var(--green)':'var(--red)' ?>">
            <div class="partido-col-info" style="border:none">
              <span class="fecha-label" style="font-size:.6rem">Fecha <?= $p['jornada'] ?? '' ?></span>
              <div class="partido-date" style="font-size:.7rem">🗓 <?= date('d/m', strtotime($p['fecha_jugado'])) ?></div>
            </div>
            <div style="display:flex;align-items:center;justify-content:center;gap:1.5rem;flex:1">
              <div style="text-align:right;flex:1"><span class="equipo-nombre-card" style="font-size:.75rem"><?= epl_h($p['local_nombre']) ?></span></div>
              <div style="display:flex;flex-direction:column;align-items:center">
                <div class="marcador-box" style="font-size:1.1rem;padding:.4rem .8rem;min-width:60px"><?= $p['sets_local'] ?>-<?= $p['sets_visitante'] ?></div>
                <?php
                  $sets=[];
                  for($s=1;$s<=3;$s++){$gl=$p["games_s{$s}_local"];$gv=$p["games_s{$s}_visitante"];if($gl!==null)$sets[]="$gl-$gv";}
                  if($sets): ?><div class="set-details" style="font-size:.6rem"><?= implode(' <span style="opacity:0.4; margin:0 2px">/</span> ', $sets) ?></div><?php endif; ?>
              </div>
              <div style="text-align:left;flex:1"><span class="equipo-nombre-card" style="font-size:.75rem"><?= epl_h($p['visitante_nombre']) ?></span></div>
            </div>
            <div class="partido-col-meta" style="border:none;padding-left:0">
              <span class="badge <?= $gane?'badge-jugado':'badge-walkover' ?>" style="font-size:.6rem"><?= $gane?'Victoria':'Derrota' ?></span>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
    <?php endif; ?>

    <?php if (!$equipo): ?>
    <div class="alert alert-info">No estás inscrito en ningún equipo de la liga activa.</div>
    <?php endif; ?>

    <!-- ── Partidos como Galleta ──────────────────────────────────────────── -->
    <?php if (!empty($partidos_galleta)): ?>
    <?php
        $galleta_proximos = array_values(array_filter($partidos_galleta, fn($p) =>
            in_array($p['estado'], ['pendiente','reprogramado'], true)));
        $galleta_jugados  = array_values(array_filter($partidos_galleta, fn($p) =>
            $p['estado'] === 'jugado'));
    ?>
    <div class="card mb-4" style="border-top:4px solid var(--gold)">
      <div class="card-head" style="background:linear-gradient(135deg,#fefce8,#fff)">
        <h3 style="font-family:var(--font-head);font-size:1rem;text-transform:uppercase;color:var(--navy);display:flex;align-items:center;gap:.5rem">
          <span style="font-size:1.3rem">🥐</span> Partidos como Galleta
        </h3>
        <span style="font-size:.72rem;background:var(--gold);color:var(--navy);font-weight:800;padding:.2rem .65rem;border-radius:20px;text-transform:uppercase;letter-spacing:.05em">
          <?= count($partidos_galleta) ?> partido<?= count($partidos_galleta)!=1?'s':'' ?>
        </span>
      </div>
      <div class="card-body">
        <div class="partidos-list">

          <?php foreach ($partidos_galleta as $p):
            $pendiente = in_array($p['estado'], ['pendiente','reprogramado'], true);
            $gane = !$pendiente && ($p['ganador_id'] == $p['equipo_sup_id']);
            $border = $pendiente ? 'var(--gold)' : ($gane ? 'var(--green)' : 'var(--red)');
          ?>
          <div class="partido-card-v2" style="padding:1rem;border-left:4px solid <?= $border ?>">

            <!-- Info fecha + equipo -->
            <div class="partido-col-info" style="border:none">
              <span class="fecha-label" style="font-size:.6rem">Fecha <?= $p['jornada'] ?? '' ?></span>
              <div class="partido-date" style="font-size:.7rem">
                🗓 <?= $p['fecha_programada'] ? date('d/m', strtotime($p['fecha_programada'])) : 'TBD' ?>
              </div>
              <div style="margin-top:.25rem;font-size:.6rem;color:var(--gold);font-weight:700;text-transform:uppercase;letter-spacing:.05em">
                🥐 <?= epl_h($p['equipo_sup_nombre']) ?>
              </div>
            </div>

            <!-- Equipos + marcador -->
            <div style="display:flex;align-items:center;justify-content:center;gap:1.5rem;flex:1">
              <div style="text-align:right;flex:1">
                <span class="equipo-nombre-card" style="font-size:.75rem"><?= epl_h($p['local_nombre']) ?></span>
              </div>
              <?php if ($pendiente): ?>
                <div class="marcador-box" style="font-size:1rem;padding:.4rem .8rem;min-width:60px">VS</div>
              <?php else: ?>
                <div style="display:flex;flex-direction:column;align-items:center">
                  <div class="marcador-box" style="font-size:1.1rem;padding:.4rem .8rem;min-width:60px">
                    <?= $p['sets_local'] ?>-<?= $p['sets_visitante'] ?>
                  </div>
                  <?php
                    $sets=[];
                    for($s=1;$s<=3;$s++){$gl=$p["games_s{$s}_local"];$gv=$p["games_s{$s}_visitante"];if($gl!==null)$sets[]="$gl-$gv";}
                    if($sets): ?>
                    <div class="set-details" style="font-size:.6rem"><?= implode(' <span style="opacity:.4;margin:0 2px">/</span> ', $sets) ?></div>
                  <?php endif; ?>
                </div>
              <?php endif; ?>
              <div style="text-align:left;flex:1">
                <span class="equipo-nombre-card" style="font-size:.75rem"><?= epl_h($p['visitante_nombre']) ?></span>
              </div>
            </div>

            <!-- Badge resultado -->
            <div class="partido-col-meta" style="border:none;padding-left:0">
              <?php if ($pendiente): ?>
                <span class="badge badge-pendiente" style="font-size:.6rem">Próximo</span>
              <?php else: ?>
                <span class="badge <?= $gane?'badge-jugado':'badge-walkover' ?>" style="font-size:.6rem">
                  <?= $gane?'Victoria':'Derrota' ?>
                </span>
              <?php endif; ?>
            </div>

          </div>
          <?php endforeach; ?>

        </div>
      </div>
    </div>
    <?php endif; ?>

</main>
<script>
function verTodosProximos() {
  document.querySelectorAll('.proximo-extra').forEach(el => el.style.display = '');
  var btn = document.getElementById('btnVerTodosProximos');
  if (btn) btn.style.display = 'none';
}
</script>
</div>

<?php require_once 'includes/footer.php'; ?>
