<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

epl_require_login();
$jugador = epl_jugador_actual();
$jid = (int)$jugador['id'];

// Marcar como leída individual via AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['marcar_id'])) {
    epl_notif_marcar_leida((int)$_POST['marcar_id'], $jid);
    echo json_encode(['ok' => true]);
    exit;
}

// Marcar todas como leídas si se pide
if (isset($_GET['marcar_todas'])) {
    epl_notif_marcar_todas_leidas($jid);
    header('Location: notificaciones.php');
    exit;
}

$notifs = epl_notif_listar($jid, 100);

// Conteos por categoría (antes de marcar como leídas)
$counts = ['total' => count($notifs), 'noleidas' => 0];
$grupos = [
    'comunicados'   => ['tipos' => ['admin','mensaje','anuncio','recordatorio','liga'], 'count' => 0],
    'partidos'      => ['tipos' => ['resultado','disputa'], 'count' => 0],
    'reprogramacion'=> ['tipos' => ['reprogramacion'], 'count' => 0],
    'inscripcion'   => ['tipos' => ['inscripcion','suplente'], 'count' => 0],
];

foreach ($notifs as $n) {
    if (!$n['leida']) $counts['noleidas']++;
    foreach ($grupos as $key => &$g) {
        if (in_array($n['tipo'], $g['tipos'])) { $g['count']++; break; }
    }
    unset($g);
}

$page_title = 'Notificaciones';
$active_nav = '';
require_once __DIR__ . '/includes/header.php';
?>

<div class="dash-layout">
  <?php include __DIR__ . '/includes/player_sidebar.php'; ?>

  <main class="dash-main">
    <div style="max-width:740px">

      <!-- Cabecera -->
      <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1.25rem;flex-wrap:wrap;gap:.75rem">
        <div>
          <h1 class="dash-title" style="margin:0">Notificaciones</h1>
          <p class="dash-subtitle" style="margin:.2rem 0 0">Tu actividad reciente en Elite Padel League.</p>
        </div>
        <?php if ($counts['noleidas'] > 0): ?>
        <a href="notificaciones.php?marcar_todas=1"
           style="font-size:.78rem;color:#64748b;border:1px solid #e2e8f0;border-radius:8px;padding:.4rem .9rem;text-decoration:none;font-weight:600;transition:all .18s"
           onmouseover="this.style.borderColor='var(--navy)';this.style.color='var(--navy)'"
           onmouseout="this.style.borderColor='#e2e8f0';this.style.color='#64748b'">
          ✓ Marcar todas leídas
        </a>
        <?php endif; ?>
      </div>

      <!-- Filtros chips -->
      <div style="display:flex;gap:.5rem;flex-wrap:wrap;margin-bottom:1.25rem" id="filterChips">
        <button class="nf-chip active" data-filter="todas" onclick="filtrar('todas',this)">
          Todas
          <span class="nf-chip-cnt"><?= $counts['total'] ?></span>
        </button>
        <?php if ($counts['noleidas'] > 0): ?>
        <button class="nf-chip" data-filter="noleidas" onclick="filtrar('noleidas',this)">
          🔴 No leídas
          <span class="nf-chip-cnt" style="background:var(--red);color:#fff"><?= $counts['noleidas'] ?></span>
        </button>
        <?php endif; ?>
        <?php if ($grupos['comunicados']['count'] > 0): ?>
        <button class="nf-chip" data-filter="comunicados" onclick="filtrar('comunicados',this)">
          📢 Comunicados
          <span class="nf-chip-cnt"><?= $grupos['comunicados']['count'] ?></span>
        </button>
        <?php endif; ?>
        <?php if ($grupos['partidos']['count'] > 0): ?>
        <button class="nf-chip" data-filter="partidos" onclick="filtrar('partidos',this)">
          ⚽ Partidos
          <span class="nf-chip-cnt"><?= $grupos['partidos']['count'] ?></span>
        </button>
        <?php endif; ?>
        <?php if ($grupos['reprogramacion']['count'] > 0): ?>
        <button class="nf-chip" data-filter="reprogramacion" onclick="filtrar('reprogramacion',this)">
          📅 Reprogramaciones
          <span class="nf-chip-cnt"><?= $grupos['reprogramacion']['count'] ?></span>
        </button>
        <?php endif; ?>
        <?php if ($grupos['inscripcion']['count'] > 0): ?>
        <button class="nf-chip" data-filter="inscripcion" onclick="filtrar('inscripcion',this)">
          ✅ Inscripciones
          <span class="nf-chip-cnt"><?= $grupos['inscripcion']['count'] ?></span>
        </button>
        <?php endif; ?>
      </div>

      <!-- Lista -->
      <?php if (empty($notifs)): ?>
        <div style="text-align:center;padding:4rem 2rem;background:#fff;border-radius:16px;border:1px solid #e2e8f0">
          <div style="font-size:3rem;margin-bottom:1rem">🔔</div>
          <p style="color:#64748b;font-weight:600">No tenés notificaciones aún</p>
          <p style="color:#94a3b8;font-size:.875rem;margin-top:.5rem">Acá vas a ver avisos de resultados, reprogramaciones y más.</p>
        </div>
      <?php else: ?>
        <div id="notifList" style="display:flex;flex-direction:column;gap:.6rem">
          <?php foreach ($notifs as $n):
            // Clasificar en grupo JS
            $grupo_js = 'comunicados';
            if (in_array($n['tipo'], ['resultado','disputa']))       $grupo_js = 'partidos';
            elseif (in_array($n['tipo'], ['reprogramacion']))        $grupo_js = 'reprogramacion';
            elseif (in_array($n['tipo'], ['inscripcion','suplente'])) $grupo_js = 'inscripcion';

            $leida = (bool)$n['leida'];
            $borde_color = match($n['tipo']) {
                'resultado','disputa'           => '#22c55e',
                'reprogramacion'                => '#3b82f6',
                'inscripcion','suplente'        => '#a855f7',
                'admin','anuncio','recordatorio','mensaje','liga' => '#f59e0b',
                default                          => '#e2e8f0',
            };
          ?>
          <div class="nf-item <?= $leida ? 'nf-read' : 'nf-unread' ?>"
               data-grupo="<?= $grupo_js ?>"
               data-leida="<?= $leida ? '1' : '0' ?>"
               data-id="<?= $n['id'] ?>"
               style="border-left:4px solid <?= $leida ? '#e2e8f0' : $borde_color ?>">
            <div style="font-size:1.35rem;flex-shrink:0;margin-top:.1rem"><?= epl_notif_icono($n['tipo']) ?></div>
            <div style="flex:1;min-width:0">
              <div style="display:flex;align-items:center;gap:.5rem;margin-bottom:.2rem;flex-wrap:wrap">
                <span style="font-weight:<?= $leida ? '600' : '800' ?>;font-size:.9rem;color:#1C2F48"><?= epl_h($n['titulo']) ?></span>
                <?php if (!$leida): ?>
                  <span style="width:8px;height:8px;background:<?= $borde_color ?>;border-radius:50%;flex-shrink:0;display:inline-block"></span>
                <?php endif; ?>
                <span style="font-size:.68rem;background:#f1f5f9;color:#64748b;border-radius:6px;padding:.1rem .5rem;font-weight:700;margin-left:auto;white-space:nowrap"><?= epl_notif_tipo_label($n['tipo']) ?></span>
              </div>
              <div style="color:#475569;font-size:.85rem;margin-bottom:.4rem;line-height:1.45"><?= epl_h($n['mensaje']) ?></div>
              <div style="color:#94a3b8;font-size:.72rem"><?= date('d/m/Y H:i', strtotime($n['created_at'])) ?></div>
            </div>
            <?php if ($n['url']): ?>
              <a href="<?= epl_h($n['url']) ?>"
                 onclick="marcarLeida(<?= $n['id'] ?>)"
                 style="flex-shrink:0;background:#1C2F48;color:#C9A762;padding:.4rem .9rem;border-radius:8px;font-size:.75rem;font-weight:700;text-decoration:none;white-space:nowrap;align-self:flex-start">Ver →</a>
            <?php endif; ?>
          </div>
          <?php endforeach; ?>
        </div>

        <!-- Empty state por filtro -->
        <div id="noResults" style="display:none;text-align:center;padding:3rem 1rem;background:#fff;border-radius:16px;border:1px solid #e2e8f0;margin-top:.5rem">
          <div style="font-size:2.5rem;margin-bottom:.75rem">🔍</div>
          <p style="color:#94a3b8;font-weight:600;font-size:.9rem">Sin notificaciones en este filtro</p>
        </div>
      <?php endif; ?>

    </div>
  </main>
</div>

<style>
.nf-chip {
  display:inline-flex;align-items:center;gap:.4rem;
  padding:.4rem .9rem;border-radius:20px;border:1.5px solid #e2e8f0;
  font-size:.78rem;font-weight:700;cursor:pointer;background:#fff;color:#475569;
  transition:all .18s;font-family:inherit;
}
.nf-chip:hover { border-color:var(--navy);color:var(--navy); }
.nf-chip.active { background:var(--navy);border-color:var(--navy);color:#fff; }
.nf-chip-cnt {
  background:#f1f5f9;color:#64748b;
  font-size:.65rem;font-weight:800;border-radius:999px;
  padding:.1rem .45rem;min-width:18px;text-align:center;
}
.nf-chip.active .nf-chip-cnt { background:rgba(255,255,255,.2);color:#fff; }

.nf-item {
  background:#fff;border-radius:12px;border:1px solid #e2e8f0;
  padding:1rem 1.1rem;display:flex;gap:.85rem;align-items:flex-start;
  transition:box-shadow .18s, border-color .18s;
}
.nf-item:hover { box-shadow:0 4px 16px rgba(0,0,0,.07); }
.nf-unread { background:#fafeff; }
.nf-read   { opacity:.75; }
</style>

<script>
// Filtrado client-side
function filtrar(tipo, el) {
  document.querySelectorAll('.nf-chip').forEach(c => c.classList.remove('active'));
  el.classList.add('active');

  let visible = 0;
  document.querySelectorAll('.nf-item').forEach(item => {
    let show = false;
    if (tipo === 'todas')         show = true;
    else if (tipo === 'noleidas') show = item.getAttribute('data-leida') === '0';
    else                          show = item.getAttribute('data-grupo') === tipo;
    item.style.display = show ? '' : 'none';
    if (show) visible++;
  });
  document.getElementById('noResults').style.display = visible === 0 ? '' : 'none';
}

// Marcar notif individual como leída
function marcarLeida(id) {
  const item = document.querySelector('.nf-item[data-id="' + id + '"]');
  if (!item || item.getAttribute('data-leida') === '1') return;
  item.setAttribute('data-leida', '1');
  item.classList.remove('nf-unread');
  item.classList.add('nf-read');
  item.style.borderLeftColor = '#e2e8f0';
  item.querySelector('span[style*="border-radius:50%"]')?.remove();
  fetch('notificaciones.php', {
    method: 'POST',
    headers: {'Content-Type':'application/x-www-form-urlencoded'},
    body: 'marcar_id=' + id
  });
}

// Marcar como leída al hacer clic en cualquier parte de la notificación
document.querySelectorAll('.nf-item').forEach(item => {
  item.addEventListener('click', () => {
    marcarLeida(parseInt(item.getAttribute('data-id')));
  });
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
