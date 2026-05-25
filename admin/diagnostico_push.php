<?php
$page_title = 'Admin — Diagnóstico Push';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../includes/web_push.php';
epl_require_admin();

$db = epl_db();

// ── POST: probar push ──────────────────────────────────────────
$test_results = null;
$test_jugador = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!empty($_POST['test_jugador_id'])) {
        $jid = (int)$_POST['test_jugador_id'];
        $st  = $db->prepare("SELECT nombre, apellido FROM jugadores WHERE id = ?");
        $st->execute([$jid]);
        $test_jugador = $st->fetch(PDO::FETCH_ASSOC);

        $st = $db->prepare("SELECT id, endpoint, p256dh, auth FROM push_subscriptions WHERE jugador_id = ?");
        $st->execute([$jid]);
        $subs = $st->fetchAll(PDO::FETCH_ASSOC);

        $test_results = [];
        foreach ($subs as $sub) {
            $res = epl_web_push_send($sub, '🔔 EPL · Diagnóstico', 'Test desde panel admin — ¿la viste?', '/dashboard.php');
            $plataforma = 'Otro';
            if (strpos($sub['endpoint'], 'fcm.googleapis.com') !== false) $plataforma = 'Android/Chrome';
            elseif (strpos($sub['endpoint'], 'push.apple.com') !== false) $plataforma = 'iOS Safari';
            elseif (strpos($sub['endpoint'], 'mozilla') !== false)        $plataforma = 'Firefox';
            elseif (strpos($sub['endpoint'], 'windows.com') !== false)    $plataforma = 'Edge';

            $borrado = false;
            if (in_array($res['status'], [404, 410])) {
                try { $db->prepare("DELETE FROM push_subscriptions WHERE id = ?")->execute([$sub['id']]); $borrado = true; } catch (Throwable $e) {}
            }
            $test_results[] = ['plataforma' => $plataforma, 'res' => $res, 'borrado' => $borrado, 'sub_id' => $sub['id']];
        }
    }

    // Limpiar endpoints muertos UNO POR UNO (AJAX): action=limpiar_uno&id=X
    if (($_POST['action'] ?? '') === 'limpiar_uno') {
        header('Content-Type: application/json');
        @set_time_limit(20);
        $sid = (int)($_POST['id'] ?? 0);
        $st = $db->prepare("SELECT id, endpoint, p256dh, auth FROM push_subscriptions WHERE id = ?");
        $st->execute([$sid]);
        $sub = $st->fetch(PDO::FETCH_ASSOC);
        if (!$sub) {
            echo json_encode(['ok' => false, 'borrado' => false, 'status' => 0, 'error' => 'No existe']);
            exit;
        }
        $res = epl_web_push_send($sub, 'EPL', 'ping', '/');
        $borrado = false;
        if (in_array($res['status'], [404, 410])) {
            $db->prepare("DELETE FROM push_subscriptions WHERE id=?")->execute([$sub['id']]);
            $borrado = true;
        }
        echo json_encode([
            'ok'      => $res['ok'],
            'status'  => $res['status'],
            'borrado' => $borrado,
            'error'   => $res['error'] ? substr($res['error'], 0, 100) : '',
        ]);
        exit;
    }
}

// Lista de IDs para el limpiador AJAX
$_ids_para_limpiar = [];
try {
    $_ids_para_limpiar = array_column($db->query("SELECT id FROM push_subscriptions ORDER BY id")->fetchAll(PDO::FETCH_ASSOC), 'id');
} catch (Throwable $_e) {}

// ── Stats ──────────────────────────────────────────────────────
$stats = [
    'total_subs'         => (int)$db->query("SELECT COUNT(*) FROM push_subscriptions")->fetchColumn(),
    'jugadores_con_push' => (int)$db->query("SELECT COUNT(DISTINCT jugador_id) FROM push_subscriptions")->fetchColumn(),
    'jugadores_activos'  => (int)$db->query("SELECT COUNT(*) FROM jugadores WHERE estado='activo'")->fetchColumn(),
    'android'            => (int)$db->query("SELECT COUNT(*) FROM push_subscriptions WHERE endpoint LIKE '%fcm.googleapis.com%'")->fetchColumn(),
    'ios'                => (int)$db->query("SELECT COUNT(*) FROM push_subscriptions WHERE endpoint LIKE '%push.apple.com%'")->fetchColumn(),
    'firefox'            => (int)$db->query("SELECT COUNT(*) FROM push_subscriptions WHERE endpoint LIKE '%mozilla%'")->fetchColumn(),
];

// Lista todos los jugadores activos con su estado de push
$jugadores_lista = $db->query("
    SELECT j.id, j.nombre, j.apellido,
           COUNT(ps.id) AS subs,
           GROUP_CONCAT(
             CASE
               WHEN ps.endpoint LIKE '%fcm.googleapis.com%' THEN '🤖'
               WHEN ps.endpoint LIKE '%push.apple.com%'    THEN '🍎'
               WHEN ps.endpoint LIKE '%mozilla%'           THEN '🦊'
               WHEN ps.endpoint LIKE '%windows.com%'       THEN '🪟'
               ELSE '❓'
             END
             SEPARATOR ' '
           ) AS plataformas
    FROM jugadores j
    LEFT JOIN push_subscriptions ps ON ps.jugador_id = j.id
    WHERE j.estado='activo'
    GROUP BY j.id
    ORDER BY subs DESC, j.nombre ASC
")->fetchAll(PDO::FETCH_ASSOC);

$_flash = epl_flash_get();
$ok = ($_flash && $_flash['tipo'] === 'ok') ? $_flash['msg'] : '';

$pct_cobertura = $stats['jugadores_activos'] > 0
    ? round(($stats['jugadores_con_push'] / $stats['jugadores_activos']) * 100)
    : 0;

require_once '../includes/header.php';
?>

<div class="dash-layout">
  <?php include __DIR__ . '/partials/sidebar.php'; ?>

  <main class="dash-main">
    <div class="dash-header" style="background:linear-gradient(135deg,#1c2f48 0%, #0f1e30 100%);border-radius:18px;padding:1.5rem 1.75rem;color:#fff;margin-bottom:1.5rem;position:relative;overflow:hidden">
      <div style="position:absolute;top:-40px;right:-40px;width:180px;height:180px;background:radial-gradient(circle,rgba(201,167,98,.2),transparent 70%)"></div>
      <div style="position:relative;z-index:1">
        <span style="font-size:.65rem;font-weight:900;letter-spacing:.25em;color:#C9A762;text-transform:uppercase">Panel admin</span>
        <h1 style="color:#fff;margin:.2rem 0 .15rem;font-size:clamp(1.5rem,3.5vw,2rem);font-family:'Anton',sans-serif;text-transform:uppercase;line-height:1">Diagnóstico <span style="color:#C9A762">Push</span></h1>
        <p style="color:rgba(255,255,255,.7);margin-top:.2rem;font-size:.82rem">Probá las notificaciones device por device y limpiá los muertos.</p>
      </div>
    </div>

    <?php if ($ok): ?><div class="alert alert-success" style="margin-bottom:1rem"><?= epl_h($ok) ?></div><?php endif; ?>

    <!-- KPIs -->
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:.75rem;margin-bottom:1.5rem">
      <div class="card" style="padding:1rem;text-align:center">
        <div style="font-family:'Anton',sans-serif;font-size:2.2rem;color:var(--navy);line-height:1"><?= $pct_cobertura ?>%</div>
        <div style="font-size:.7rem;color:#64748b;font-weight:700;text-transform:uppercase;letter-spacing:.05em;margin-top:.3rem">Cobertura</div>
        <div style="font-size:.65rem;color:#94a3b8;margin-top:.15rem"><?= $stats['jugadores_con_push'] ?>/<?= $stats['jugadores_activos'] ?> jugadores</div>
      </div>
      <div class="card" style="padding:1rem;text-align:center">
        <div style="font-family:'Anton',sans-serif;font-size:2.2rem;color:var(--navy);line-height:1"><?= $stats['total_subs'] ?></div>
        <div style="font-size:.7rem;color:#64748b;font-weight:700;text-transform:uppercase;margin-top:.3rem">Total devices</div>
      </div>
      <div class="card" style="padding:1rem;text-align:center">
        <div style="font-family:'Anton',sans-serif;font-size:2.2rem;color:#059669;line-height:1"><?= $stats['android'] ?></div>
        <div style="font-size:.7rem;color:#64748b;font-weight:700;text-transform:uppercase;margin-top:.3rem">🤖 Android/Chrome</div>
      </div>
      <div class="card" style="padding:1rem;text-align:center">
        <div style="font-family:'Anton',sans-serif;font-size:2.2rem;color:#1c2f48;line-height:1"><?= $stats['ios'] ?></div>
        <div style="font-size:.7rem;color:#64748b;font-weight:700;text-transform:uppercase;margin-top:.3rem">🍎 iOS Safari</div>
      </div>
      <?php if ($stats['firefox'] > 0): ?>
      <div class="card" style="padding:1rem;text-align:center">
        <div style="font-family:'Anton',sans-serif;font-size:2.2rem;color:#ea580c;line-height:1"><?= $stats['firefox'] ?></div>
        <div style="font-size:.7rem;color:#64748b;font-weight:700;text-transform:uppercase;margin-top:.3rem">🦊 Firefox</div>
      </div>
      <?php endif; ?>
    </div>

    <!-- Acción: limpiar muertos (AJAX uno por uno) -->
    <div class="card mb-3" style="padding:1rem 1.25rem;background:#fffbeb;border-left:4px solid #f59e0b">
      <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:.75rem">
        <div style="flex:1;min-width:200px">
          <div style="font-weight:800;color:var(--navy);font-size:.9rem">🧹 Mantenimiento</div>
          <div style="font-size:.78rem;color:#64748b;margin-top:.15rem">Prueba cada endpoint individualmente y elimina los muertos (404/410)</div>
          <div id="limpiarProgress" style="display:none;margin-top:.5rem;font-size:.78rem;color:#92400e;font-weight:700"></div>
        </div>
        <button type="button" id="btnLimpiar" onclick="limpiarMuertos()" style="background:#f59e0b;color:#fff;font-weight:800;border:none;padding:.55rem 1rem;border-radius:8px;cursor:pointer;font-family:inherit">
          Limpiar muertos
        </button>
      </div>
    </div>
    <script>
    const _idsLimpiar = <?= json_encode($_ids_para_limpiar) ?>;
    async function limpiarMuertos() {
      const btn = document.getElementById('btnLimpiar');
      const prog = document.getElementById('limpiarProgress');
      if (_idsLimpiar.length === 0) { alert('No hay suscripciones para limpiar.'); return; }
      if (!confirm('Esto probará ' + _idsLimpiar.length + ' endpoints. Puede tardar un rato.')) return;

      btn.disabled = true;
      btn.textContent = 'Procesando…';
      prog.style.display = 'block';

      let borrados = 0, ok = 0, error = 0;
      for (let i = 0; i < _idsLimpiar.length; i++) {
        const id = _idsLimpiar[i];
        prog.textContent = '⏳ Probando ' + (i+1) + '/' + _idsLimpiar.length + '...';
        try {
          const fd = new FormData();
          fd.append('action', 'limpiar_uno');
          fd.append('id', id);
          const r = await fetch('diagnostico_push.php', { method: 'POST', body: fd });
          const data = await r.json();
          if (data.borrado) borrados++;
          else if (data.ok) ok++;
          else error++;
        } catch(e) { error++; }
      }
      prog.innerHTML = '✅ Completado: <strong>' + borrados + '</strong> borrados · <strong>' + ok + '</strong> OK · <strong>' + error + '</strong> con error. Recargando…';
      setTimeout(() => location.reload(), 1500);
    }
    </script>

    <?php if ($test_results !== null): ?>
    <!-- Resultados del último test -->
    <div class="card mb-3" style="border:2px solid <?= count(array_filter($test_results, fn($r) => $r['res']['ok'])) === count($test_results) ? '#10b981' : '#f59e0b' ?>">
      <div class="card-head">
        <h3 style="font-family:var(--font-head);font-size:1rem;color:var(--navy)">📡 Resultado del test</h3>
      </div>
      <div class="card-body">
        <p style="font-size:.85rem;color:#64748b;margin-bottom:1rem">
          Jugador: <strong><?= epl_h($test_jugador['nombre'].' '.$test_jugador['apellido']) ?></strong> ·
          <?= count($test_results) ?> device(s) ·
          <span style="color:#10b981;font-weight:700"><?= count(array_filter($test_results, fn($r) => $r['res']['ok'])) ?> OK</span> ·
          <span style="color:#dc2626;font-weight:700"><?= count(array_filter($test_results, fn($r) => !$r['res']['ok'])) ?> FALLÓ</span>
        </p>
        <?php if (empty($test_results)): ?>
          <p style="color:#94a3b8;text-align:center;padding:1rem">Este jugador no tiene devices suscritos.</p>
        <?php else: ?>
          <?php foreach ($test_results as $r): ?>
          <div style="display:flex;align-items:flex-start;gap:.85rem;padding:.85rem;background:<?= $r['res']['ok'] ? '#f0fdf4' : '#fef2f2' ?>;border:1px solid <?= $r['res']['ok'] ? '#86efac' : '#fca5a5' ?>;border-radius:10px;margin-bottom:.5rem">
            <div style="font-size:1.5rem;flex-shrink:0"><?= $r['res']['ok'] ? '✅' : ($r['borrado'] ? '🗑️' : '❌') ?></div>
            <div style="flex:1;min-width:0">
              <div style="font-weight:800;color:var(--navy);font-size:.88rem"><?= epl_h($r['plataforma']) ?></div>
              <div style="font-size:.75rem;color:#64748b;margin-top:.15rem">
                Status: <strong><?= $r['res']['status'] ?: 'sin respuesta' ?></strong>
                <?php if ($r['res']['ok']): ?>
                  · <span style="color:#15803d">Enviado correctamente</span>
                <?php elseif ($r['borrado']): ?>
                  · <span style="color:#b45309">Endpoint expirado — eliminado de la BD</span>
                <?php else: ?>
                  · <span style="color:#991b1b">Error: <?= epl_h(mb_strimwidth($r['res']['error'] ?: '', 0, 200, '…')) ?></span>
                <?php endif; ?>
              </div>
            </div>
          </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>
    <?php endif; ?>

    <!-- Tabla jugadores con sus subs -->
    <div class="card">
      <div class="card-head">
        <h3 style="font-family:var(--font-head);font-size:1rem;color:var(--navy)">Jugadores y sus dispositivos</h3>
      </div>
      <div style="overflow-x:auto">
        <table style="width:100%;border-collapse:collapse;font-size:.85rem">
          <thead>
            <tr style="background:var(--navy);color:#fff">
              <th style="padding:.7rem 1rem;text-align:left;font-size:.7rem;text-transform:uppercase">Jugador</th>
              <th style="padding:.7rem 1rem;text-align:center;font-size:.7rem;text-transform:uppercase">Devices</th>
              <th style="padding:.7rem 1rem;text-align:center;font-size:.7rem;text-transform:uppercase">Plataformas</th>
              <th style="padding:.7rem 1rem;text-align:center;font-size:.7rem;text-transform:uppercase">Probar</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($jugadores_lista as $j): ?>
            <tr style="border-bottom:1px solid #f1f5f9">
              <td style="padding:.7rem 1rem;font-weight:600;color:var(--navy)"><?= epl_h($j['nombre'].' '.$j['apellido']) ?></td>
              <td style="padding:.7rem 1rem;text-align:center">
                <?php if ($j['subs'] > 0): ?>
                  <span style="background:#dcfce7;color:#15803d;font-weight:800;border-radius:999px;padding:.15rem .6rem;font-size:.75rem"><?= $j['subs'] ?></span>
                <?php else: ?>
                  <span style="color:#94a3b8;font-size:.78rem">Sin push</span>
                <?php endif; ?>
              </td>
              <td style="padding:.7rem 1rem;text-align:center;font-size:1rem;letter-spacing:.2em"><?= epl_h($j['plataformas'] ?? '—') ?></td>
              <td style="padding:.7rem 1rem;text-align:center">
                <?php if ($j['subs'] > 0): ?>
                <form method="post" style="margin:0;display:inline">
                  <input type="hidden" name="test_jugador_id" value="<?= $j['id'] ?>">
                  <button type="submit" class="btn btn-sm" style="background:var(--navy);color:var(--gold);font-weight:700;border:none;padding:.4rem .85rem;border-radius:6px;font-size:.7rem;cursor:pointer">Test</button>
                </form>
                <?php else: ?>
                  <span style="color:#cbd5e1">—</span>
                <?php endif; ?>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Guía -->
    <details class="card mt-3" style="padding:0">
      <summary style="padding:1rem 1.25rem;cursor:pointer;font-weight:800;color:var(--navy);font-size:.85rem">📘 Guía rápida — por qué falla una notificación</summary>
      <div style="padding:0 1.25rem 1.25rem;font-size:.85rem;color:#475569;line-height:1.6">
        <h4 style="color:var(--navy);font-size:.9rem;margin:.5rem 0">🍎 iOS (iPhone/iPad)</h4>
        <ul style="padding-left:1.2rem">
          <li><strong>Solo funciona si la PWA está INSTALADA</strong> en la pantalla de inicio (Safari → compartir → "Añadir a inicio")</li>
          <li>El usuario tiene que <strong>abrir la app desde el icono</strong> y aceptar permisos la primera vez</li>
          <li>iOS 16.4+ requerido</li>
        </ul>
        <h4 style="color:var(--navy);font-size:.9rem;margin:1rem 0 .5rem">🤖 Android (Chrome)</h4>
        <ul style="padding-left:1.2rem">
          <li>Funciona en Chrome móvil normal o PWA instalada</li>
          <li>Si bloquean notificaciones en el navegador, no llegan</li>
        </ul>
        <h4 style="color:var(--navy);font-size:.9rem;margin:1rem 0 .5rem">Códigos de error comunes</h4>
        <ul style="padding-left:1.2rem">
          <li><strong>200/201</strong> — Enviado OK</li>
          <li><strong>404/410</strong> — Endpoint murió (el user desinstaló o limpió datos). Se borra solo.</li>
          <li><strong>413</strong> — Payload muy grande</li>
          <li><strong>429</strong> — Estás mandando demasiado, frenar</li>
          <li><strong>5xx</strong> — Problema temporal del servidor de Google/Apple, reintentar</li>
        </ul>
      </div>
    </details>

  </main>
</div>

<?php require_once '../includes/footer.php'; ?>
