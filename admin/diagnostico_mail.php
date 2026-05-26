<?php
$page_title = 'Admin — Diagnóstico Mail';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../includes/mail.php';
epl_require_admin();

$db = epl_db();

// ── ACCIÓN: Forzar procesamiento manual de la cola ──────────────────
$cron_output = null;
if (isset($_GET['action']) && $_GET['action'] === 'procesar_cola') {
    $now = date('Y-m-d H:i:s');
    $cron_output = [];
    
    // Obtener los pendientes
    $st = $db->query("
        SELECT id, to_email, to_name, subject, body_html, intentos
        FROM mail_queue
        WHERE estado IN ('pendiente','enviando')
          AND intentos < 3
        ORDER BY created_at ASC
        LIMIT 10
    ");
    $pendientes = $st->fetchAll(PDO::FETCH_ASSOC);

    if (empty($pendientes)) {
        $cron_output[] = "[{$now}] Sin emails pendientes en la cola.";
    } else {
        $cron_output[] = "[{$now}] Procesando " . count($pendientes) . " correo(s) pendiente(s) en vivo...";
        foreach ($pendientes as $mail) {
            $id = (int)$mail['id'];
            $cron_output[] = "· Enviando #{$id} a {$mail['to_email']}...";
            
            // Marcar intento
            $db->prepare("UPDATE mail_queue SET estado='enviando', intentos=intentos+1 WHERE id=?")->execute([$id]);
            
            $result = epl_mail_enviar_directo(
                $mail['to_email'],
                $mail['subject'],
                $mail['body_html'],
                $mail['to_name']
            );
            
            if ($result['ok']) {
                $db->prepare("UPDATE mail_queue SET estado='enviado', sent_at=NOW() WHERE id=?")->execute([$id]);
                $cron_output[] = "  ✓ ÉXITO";
            } else {
                $err = $result['error'] ?? 'Error desconocido';
                $nuevo_estado = ((int)$mail['intentos'] + 1) >= 3 ? 'error' : 'pendiente';
                $db->prepare("UPDATE mail_queue SET estado=?, error_msg=? WHERE id=?")->execute([$nuevo_estado, $err, $id]);
                $cron_output[] = "  ✗ FALLÓ: {$err}";
            }
        }
    }
}

// ── ACCIÓN: Encolar correo de prueba ────────────────────────────────
$test_success_msg = null;
$test_error_msg = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'encolar_prueba') {
    $destino = trim($_POST['test_email'] ?? '');
    if (!filter_var($destino, FILTER_VALIDATE_EMAIL)) {
        $test_error_msg = 'Indica un email válido.';
    } else {
        $body = epl_mail_plantilla(
            'Prueba de Diagnóstico',
            '<p style="margin:0 0 1rem;color:#334155;line-height:1.5">Hola,</p>'
            . '<p style="margin:0 0 1rem;color:#334155;line-height:1.5">Este es un correo de prueba enviado manualmente desde el Panel de Diagnóstico Mail de Elite Padel League.</p>'
            . '<p style="margin:0;color:#94a3b8;font-size:12px">Enviado vía Brevo SMTP.</p>'
        );
        
        $res = epl_mail_enviar($destino, 'Prueba de Diagnóstico Mail', $body);
        if ($res['ok']) {
            $test_success_msg = 'Correo de prueba agregado a la cola (ID pendiente).';
        } else {
            $test_error_msg = $res['error'] ?? 'No se pudo encolar el correo.';
        }
    }
}

// ── STATS ──────────────────────────────────────────────────────
$stats = [
    'total'      => 0,
    'enviado'    => 0,
    'pendiente'  => 0,
    'error'      => 0,
];
try {
    $st = $db->query("SELECT estado, COUNT(*) as cant FROM mail_queue GROUP BY estado");
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $stats[$row['estado']] = (int)$row['cant'];
    }
    $stats['total'] = array_sum($stats);
} catch (Throwable $_e) {}

// ── LISTA DE COLA ────────────────────────────────────────────────
$mail_queue_list = [];
try {
    $st = $db->query("SELECT id, to_email, subject, estado, intentos, error_msg, created_at, sent_at FROM mail_queue ORDER BY id DESC LIMIT 20");
    $mail_queue_list = $st->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $_e) {}

$cfg = epl_smtp_config();
$_flash = epl_flash_get();
$ok = ($_flash && $_flash['tipo'] === 'ok') ? $_flash['msg'] : '';

require_once '../includes/header.php';
?>

<div class="dash-layout">
  <?php include __DIR__ . '/partials/sidebar.php'; ?>

  <main class="dash-main">
    <div class="dash-header" style="background:linear-gradient(135deg,#1c2f48 0%, #0f1e30 100%);border-radius:18px;padding:1.5rem 1.75rem;color:#fff;margin-bottom:1.5rem;position:relative;overflow:hidden">
      <div style="position:absolute;top:-40px;right:-40px;width:180px;height:180px;background:radial-gradient(circle,rgba(201,167,98,.2),transparent 70%)"></div>
      <div style="position:relative;z-index:1">
        <span style="font-size:.65rem;font-weight:900;letter-spacing:.25em;color:#C9A762;text-transform:uppercase">Panel admin</span>
        <h1 style="color:#fff;margin:.2rem 0 .15rem;font-size:clamp(1.5rem,3.5vw,2rem);font-family:'Anton',sans-serif;text-transform:uppercase;line-height:1">Diagnóstico <span style="color:#C9A762">Mail</span></h1>
        <p style="color:rgba(255,255,255,.7);margin-top:.2rem;font-size:.82rem">Monitorea la cola de correos, revisa errores de SMTP y fuerza envíos en tiempo real.</p>
      </div>
    </div>

    <?php if ($ok): ?><div class="alert alert-success" style="margin-bottom:1rem"><?= epl_h($ok) ?></div><?php endif; ?>
    <?php if ($test_success_msg): ?><div class="alert alert-success" style="margin-bottom:1rem"><?= epl_h($test_success_msg) ?></div><?php endif; ?>
    <?php if ($test_error_msg): ?><div class="alert alert-error" style="margin-bottom:1rem"><?= epl_h($test_error_msg) ?></div><?php endif; ?>

    <!-- KPIs -->
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:.75rem;margin-bottom:1.5rem">
      <div class="card" style="padding:1rem;text-align:center">
        <div style="font-family:'Anton',sans-serif;font-size:2.2rem;color:var(--navy);line-height:1"><?= $stats['total'] ?></div>
        <div style="font-size:.7rem;color:#64748b;font-weight:700;text-transform:uppercase;letter-spacing:.05em;margin-top:.3rem">Total Histórico</div>
      </div>
      <div class="card" style="padding:1rem;text-align:center">
        <div style="font-family:'Anton',sans-serif;font-size:2.2rem;color:#10b981;line-height:1"><?= $stats['enviado'] ?></div>
        <div style="font-size:.7rem;color:#64748b;font-weight:700;text-transform:uppercase;margin-top:.3rem">✓ Enviados</div>
      </div>
      <div class="card" style="padding:1rem;text-align:center">
        <div style="font-family:'Anton',sans-serif;font-size:2.2rem;color:#f59e0b;line-height:1"><?= $stats['pendiente'] + ($stats['enviando'] ?? 0) ?></div>
        <div style="font-size:.7rem;color:#64748b;font-weight:700;text-transform:uppercase;margin-top:.3rem">⏳ Pendientes</div>
      </div>
      <div class="card" style="padding:1rem;text-align:center">
        <div style="font-family:'Anton',sans-serif;font-size:2.2rem;color:#dc2626;line-height:1"><?= $stats['error'] ?></div>
        <div style="font-size:.7rem;color:#64748b;font-weight:700;text-transform:uppercase;margin-top:.3rem">✗ Con Error</div>
      </div>
    </div>

    <!-- Mantenimiento y SMTP Config -->
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:1.25rem;margin-bottom:1.5rem;align-items:start">
      
      <!-- Bloque: Configuración Activa -->
      <div class="card" style="padding:1.25rem">
        <h3 style="font-family:var(--font-head);font-size:.95rem;color:var(--navy);margin:0 0 .75rem;display:flex;align-items:center;gap:.4rem">
          ⚙️ Credenciales SMTP Configuradas
        </h3>
        <table style="width:100%;font-size:.8rem;border-collapse:collapse">
          <tr style="border-bottom:1px solid #f1f5f9"><td style="padding:.4rem 0;color:#64748b">Estado:</td><td style="font-weight:700;color:<?= epl_smtp_habilitado() ? '#10b981' : '#dc2626' ?>"><?= epl_smtp_habilitado() ? 'ACTIVO' : 'PAUSADO/DESACTIVADO' ?></td></tr>
          <tr style="border-bottom:1px solid #f1f5f9"><td style="padding:.4rem 0;color:#64748b">Servidor:</td><td style="font-weight:700"><?= epl_h($cfg['host'] ?: 'No configurado') ?></td></tr>
          <tr style="border-bottom:1px solid #f1f5f9"><td style="padding:.4rem 0;color:#64748b">Puerto / Cifrado:</td><td style="font-weight:700"><?= epl_h($cfg['port']) ?> (<?= epl_h($cfg['encryption']) ?>)</td></tr>
          <tr style="border-bottom:1px solid #f1f5f9"><td style="padding:.4rem 0;color:#64748b">Usuario:</td><td style="font-weight:700"><?= epl_h($cfg['user'] ?: '—') ?></td></tr>
          <tr><td style="padding:.4rem 0;color:#64748b">Remitente:</td><td style="font-weight:700"><?= epl_h($cfg['from_email']) ?></td></tr>
        </table>
        <div style="margin-top:.85rem;font-size:.72rem;color:#94a3b8">
          Para modificar estos datos, ve a <a href="configuracion.php?tab=general" style="color:var(--navy);font-weight:700">Configuración general</a>.
        </div>
      </div>

      <!-- Bloque: Acciones de Diagnóstico -->
      <div class="card" style="padding:1.25rem">
        <h3 style="font-family:var(--font-head);font-size:.95rem;color:var(--navy);margin:0 0 .75rem">
          🛠️ Acciones Rápidas
        </h3>
        
        <!-- Enviar prueba -->
        <form method="post" style="margin:0 0 1rem;padding-bottom:1rem;border-bottom:1px solid #f1f5f9">
          <input type="hidden" name="action" value="encolar_prueba">
          <label style="display:block;font-size:.78rem;font-weight:700;color:var(--navy);margin-bottom:.35rem">Encolar nuevo correo de prueba</label>
          <div style="display:flex;gap:.5rem">
            <input type="email" name="test_email" value="<?= epl_h(epl_jugador_actual()['email'] ?? '') ?>" required
                   style="flex:1;padding:.45rem .6rem;border:1px solid #cbd5e1;border-radius:6px;font-size:.8rem">
            <button type="submit" class="btn btn-sm" style="background:var(--navy);color:var(--gold);font-weight:700">Encolar</button>
          </div>
        </form>

        <!-- Ejecutar Cron -->
        <div>
          <label style="display:block;font-size:.78rem;font-weight:700;color:var(--navy);margin-bottom:.35rem">Procesar cola manualmente</label>
          <p style="font-size:.72rem;color:#64748b;margin:0 0 .5rem">Ejecuta el ciclo de envío SMTP en este instante para procesar pendientes.</p>
          <a href="?action=procesar_cola" class="btn btn-sm" style="background:#ea580c;color:#fff;text-decoration:none;display:inline-block;font-weight:700;text-align:center">
            🚀 Ejecutar envío en vivo
          </a>
        </div>
      </div>
    </div>

    <!-- Resultados del Cron en vivo -->
    <?php if ($cron_output !== null): ?>
    <div class="card mb-3" style="border:2px solid #ea580c">
      <div class="card-head" style="background:#fff7ed;padding:.85rem 1.25rem;border-bottom:1px solid #ffedd5">
        <h3 style="font-family:var(--font-head);font-size:.9rem;color:#ea580c;margin:0">📡 Consola de Envío de Correo</h3>
      </div>
      <div class="card-body" style="padding:1rem">
        <pre style="background:#f8fafc;padding:.75rem;border:1px solid #e2e8f0;border-radius:8px;font-family:monospace;font-size:.8rem;margin:0;max-height:220px;overflow:auto"><?= implode("\n", array_map('epl_h', $cron_output)) ?></pre>
      </div>
    </div>
    <?php endif; ?>

    <!-- Tabla Cola de Correos -->
    <div class="card">
      <div class="card-head" style="display:flex;justify-content:space-between;align-items:center;padding:1rem 1.25rem;border-bottom:1px solid #f1f5f9">
        <h3 style="font-family:var(--font-head);font-size:.95rem;color:var(--navy);margin:0">📬 Cola de Correos (Últimos 20)</h3>
        <button type="button" onclick="location.reload()" class="btn btn-sm" style="background:#f1f5f9;color:var(--navy);font-weight:700">🔄 Actualizar</button>
      </div>
      <div style="overflow-x:auto">
        <table style="width:100%;border-collapse:collapse;font-size:.82rem">
          <thead>
            <tr style="background:var(--navy);color:#fff">
              <th style="padding:.7rem 1rem;text-align:left;font-size:.7rem;text-transform:uppercase">ID</th>
              <th style="padding:.7rem 1rem;text-align:left;font-size:.7rem;text-transform:uppercase">Destinatario</th>
              <th style="padding:.7rem 1rem;text-align:left;font-size:.7rem;text-transform:uppercase">Asunto</th>
              <th style="padding:.7rem 1rem;text-align:center;font-size:.7rem;text-transform:uppercase">Estado</th>
              <th style="padding:.7rem 1rem;text-align:center;font-size:.7rem;text-transform:uppercase">Intentos</th>
              <th style="padding:.7rem 1rem;text-align:left;font-size:.7rem;text-transform:uppercase">Detalle Error</th>
              <th style="padding:.7rem 1rem;text-align:center;font-size:.7rem;text-transform:uppercase">Creado</th>
              <th style="padding:.7rem 1rem;text-align:center;font-size:.7rem;text-transform:uppercase">Enviado</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($mail_queue_list)): ?>
            <tr><td colspan="8" style="padding:2rem;text-align:center;color:#94a3b8">La cola de correos está vacía.</td></tr>
            <?php else: ?>
              <?php foreach ($mail_queue_list as $r): ?>
              <?php 
                $bg = '#fff';
                $txt_color = '#475569';
                if ($r['estado'] === 'enviado') { $bg = '#f0fdf4'; $txt_color = '#15803d'; }
                elseif ($r['estado'] === 'error') { $bg = '#fef2f2'; $txt_color = '#991b1b'; }
                elseif ($r['estado'] === 'enviando') { $bg = '#fffbeb'; $txt_color = '#b45309'; }
              ?>
              <tr style="border-bottom:1px solid #f1f5f9;background:<?= $bg ?>;color:<?= $txt_color ?>">
                <td style="padding:.7rem 1rem;font-weight:700">#<?= $r['id'] ?></td>
                <td style="padding:.7rem 1rem;font-weight:600"><?= epl_h($r['to_email']) ?></td>
                <td style="padding:.7rem 1rem"><?= epl_h($r['subject']) ?></td>
                <td style="padding:.7rem 1rem;text-align:center">
                  <span style="font-weight:800;text-transform:uppercase;font-size:.7rem"><?= epl_h($r['estado']) ?></span>
                </td>
                <td style="padding:.7rem 1rem;text-align:center;font-weight:700"><?= $r['intentos'] ?></td>
                <td style="padding:.7rem 1rem;max-width:280px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-family:monospace;font-size:.75rem">
                  <?= $r['error_msg'] ? epl_h($r['error_msg']) : '<span style="color:#94a3b8">—</span>' ?>
                </td>
                <td style="padding:.7rem 1rem;text-align:center;color:#94a3b8;font-size:.75rem"><?= date('H:i:s d/m', strtotime($r['created_at'])) ?></td>
                <td style="padding:.7rem 1rem;text-align:center;font-weight:600;font-size:.75rem"><?= $r['sent_at'] ? date('H:i:s d/m', strtotime($r['sent_at'])) : '—' ?></td>
              </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

  </main>
</div>

<?php require_once '../includes/footer.php'; ?>
