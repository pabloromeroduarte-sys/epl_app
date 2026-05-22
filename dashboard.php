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
    <div id="bannerPush" style="background:linear-gradient(135deg,#1c2f48,#1a3a64);border-radius:14px;padding:1rem 1.25rem;margin-bottom:1.25rem;display:flex;align-items:center;gap:.85rem;flex-wrap:wrap">
      <span style="font-size:1.4rem;flex-shrink:0">🔔</span>
      <div style="flex:1;min-width:160px">
        <div style="font-weight:800;font-size:.88rem;color:#fff">Activá las notificaciones</div>
        <div style="font-size:.75rem;color:rgba(255,255,255,.7);margin-top:.15rem">Recibí alertas de partidos y resultados en tu celular.</div>
      </div>
      <div style="display:flex;gap:.5rem;flex-shrink:0">
        <button onclick="activarPush()" style="background:var(--gold);color:var(--navy);border:none;border-radius:8px;padding:.5rem 1rem;font-weight:800;font-size:.8rem;cursor:pointer">Activar</button>
        <button onclick="this.closest('#bannerPush').remove()" style="background:rgba(255,255,255,.15);color:#fff;border:none;border-radius:8px;padding:.5rem .75rem;font-size:.8rem;cursor:pointer">Ahora no</button>
      </div>
    </div>
    <script>
    async function activarPush() {
      const banner = document.getElementById('bannerPush');
      if (banner) banner.remove();

      const VAPID_PUBLIC = "<?= htmlspecialchars(epl_env('VAPID_PUBLIC_KEY'), ENT_QUOTES) ?>";
      function urlB64(b64){const pad='='.repeat((4-b64.length%4)%4);const raw=atob((b64+pad).replace(/-/g,'+').replace(/_/g,'/'));return Uint8Array.from([...raw].map(c=>c.charCodeAt(0)));}

      try {
        const perm = await Notification.requestPermission();
        if (perm !== 'granted') return;
        const reg = await navigator.serviceWorker.ready;
        let sub = await reg.pushManager.getSubscription();
        if (!sub && VAPID_PUBLIC) {
          sub = await reg.pushManager.subscribe({
            userVisibleOnly: true,
            applicationServerKey: urlB64(VAPID_PUBLIC)
          });
        }
        if (sub) {
          await fetch('/push_subscribe.php', {
            method: 'POST',
            headers: {'Content-Type':'application/json'},
            body: JSON.stringify(sub)
          });
        }
      } catch(e) { /* silencioso */ }
    }
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
