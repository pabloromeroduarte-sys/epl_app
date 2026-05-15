<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

epl_require_login();
$jugador = epl_jugador_actual();
$jid = (int)$jugador['id'];

// Marcar todas como leídas al abrir
epl_notif_marcar_todas_leidas($jid);

$notifs = epl_notif_listar($jid, 50);

$page_title = 'Notificaciones';
$active_nav = '';
require_once __DIR__ . '/includes/header.php';
?>

<div class="dash-layout">
  <?php include __DIR__ . '/includes/player_sidebar.php'; ?>

  <main class="dash-main">
    <div style="max-width:700px">

      <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1.5rem">
        <div>
          <h1 class="dash-title">Notificaciones</h1>
          <p class="dash-subtitle">Tu actividad reciente en Elite Padel League.</p>
        </div>
      </div>

      <?php if (empty($notifs)): ?>
        <div style="text-align:center;padding:4rem 2rem;background:#fff;border-radius:16px;border:1px solid #e2e8f0">
          <div style="font-size:3rem;margin-bottom:1rem">🔔</div>
          <p style="color:#64748b;font-weight:600">No tenés notificaciones aún</p>
          <p style="color:#94a3b8;font-size:.875rem;margin-top:.5rem">Acá vas a ver avisos de resultados, reprogramaciones y más.</p>
        </div>
      <?php else: ?>
        <div style="display:flex;flex-direction:column;gap:.75rem">
          <?php foreach ($notifs as $n): ?>
            <div style="background:#fff;border-radius:12px;border:1px solid #e2e8f0;padding:1rem 1.25rem;display:flex;gap:1rem;align-items:flex-start">
              <div style="font-size:1.5rem;flex-shrink:0;margin-top:.1rem"><?= epl_notif_icono($n['tipo']) ?></div>
              <div style="flex:1;min-width:0">
                <div style="font-weight:700;font-size:.9rem;color:#1C2F48;margin-bottom:.25rem"><?= epl_h($n['titulo']) ?></div>
                <div style="color:#475569;font-size:.85rem;margin-bottom:.5rem"><?= epl_h($n['mensaje']) ?></div>
                <div style="color:#94a3b8;font-size:.75rem"><?= date('d/m/Y H:i', strtotime($n['created_at'])) ?></div>
              </div>
              <?php if ($n['url']): ?>
                <a href="<?= epl_h($n['url']) ?>" style="flex-shrink:0;background:#1C2F48;color:#C9A762;padding:.4rem .9rem;border-radius:8px;font-size:.75rem;font-weight:700;text-decoration:none;white-space:nowrap">Ver →</a>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

    </div>
  </main>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
