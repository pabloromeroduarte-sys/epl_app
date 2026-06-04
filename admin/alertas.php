<?php
$page_title = 'Alertas y notificaciones';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../includes/mail.php';
require_once '../includes/mail_automations.php';
epl_require_admin();

$db = epl_db();
epl_email_log_init();

$_flash = epl_flash_get();
$ok = ($_flash && $_flash['tipo'] === 'ok') ? $_flash['msg'] : '';
$err = '';

// ── Guardar tiempos ───────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'guardar_tiempos') {
    // Recordatorio: lista de horas separadas por coma
    $raw_horas = trim($_POST['recordatorio_horas'] ?? '');
    $horas = array_filter(array_map('intval', explode(',', $raw_horas)), fn($h) => $h > 0 && $h <= 720);
    $horas = array_values(array_unique($horas));
    rsort($horas);
    $horas_str = $horas ? implode(',', $horas) : '24,12,3';

    $tol      = max(5,  min(180, (int)($_POST['recordatorio_tol_min'] ?? 30)));
    $atrasado = max(1,  min(168, (int)($_POST['atrasado_horas'] ?? 12)));
    $lock     = max(0,  min(168, (int)($_POST['reprog_lock_horas'] ?? 24)));

    epl_config_set('notif_recordatorio_horas',   $horas_str);
    epl_config_set('notif_recordatorio_tol_min', (string)$tol);
    epl_config_set('notif_atrasado_horas',       (string)$atrasado);
    epl_config_set('notif_reprog_lock_horas',    (string)$lock);

    epl_redirect_ok('Tiempos actualizados correctamente.', 'alertas.php');
}

$tab = ($_GET['tab'] ?? 'catalogo') === 'resumen' ? 'resumen' : 'catalogo';

$t = epl_notif_tiempos();
$ventanas = epl_notif_recordatorio_ventanas();
$ventanas_lbl = implode(' · ', array_map(fn($v) => $v[2], $ventanas));
$smtp_ok = epl_smtp_habilitado();

// ── Catálogo de alertas del sistema ───────────────────────────────────────────
// canales: in_app, push, email · cada alerta describe qué dice y cuándo se manda
$CH = ['app' => ['📲', 'App', '#6366f1', '#eef2ff'], 'push' => ['🔔', 'Push', '#0ea5e9', '#e0f2fe'], 'email' => ['✉️', 'Email', '#16a34a', '#f0fdf4']];

$alertas = [
    [
        'icon' => '📝', 'nombre' => 'Bienvenida / Registro', 'color' => '#6366f1', 'bg' => '#eef2ff',
        'cuando' => 'Cuando un jugador crea su cuenta',
        'canales' => ['email'],
        'audiencia' => 'Jugador (y admins si se configura)',
        'ejemplo' => '¡Bienvenido a Elite Padel League! Tu cuenta fue creada con éxito.',
        'config' => 'Plantilla editable en Automatizaciones',
        'link' => 'automatizaciones.php',
    ],
    [
        'icon' => '🔑', 'nombre' => 'Contraseña provisoria', 'color' => '#d97706', 'bg' => '#fffbeb',
        'cuando' => 'Al registrarse o al pedir recuperar contraseña',
        'canales' => ['email'],
        'audiencia' => 'Jugador',
        'ejemplo' => 'Te enviamos una contraseña provisoria para que ingreses. Al entrar deberás cambiarla.',
        'config' => 'Automático', 'link' => '',
    ],
    [
        'icon' => '🎂', 'nombre' => 'Cumpleaños', 'color' => '#db2777', 'bg' => '#fdf2f8',
        'cuando' => 'Cron diario — cuando la fecha de nacimiento coincide con hoy',
        'canales' => ['email'],
        'audiencia' => 'Jugador (y admins si se configura)',
        'ejemplo' => '¡Feliz cumpleaños! Todo el equipo de Elite Padel League te desea un gran día.',
        'config' => 'Plantilla editable en Automatizaciones',
        'link' => 'automatizaciones.php',
    ],
    [
        'icon' => '⏰', 'nombre' => 'Recordatorio de partido', 'color' => '#2563eb', 'bg' => '#eff6ff',
        'cuando' => 'Cron por hora — ' . $ventanas_lbl . ' antes del partido',
        'canales' => ['app', 'push', 'email'],
        'audiencia' => 'Los 4 jugadores del partido',
        'ejemplo' => '⏰ Tu partido es en 24 horas. Equipo A vs Equipo B — 12/06 20:00h · Cancha Central.',
        'config' => 'Tiempos editables abajo', 'link' => '', 'editable' => true,
    ],
    [
        'icon' => '📅', 'nombre' => 'Solicitud de reprogramación', 'color' => '#ea580c', 'bg' => '#fff7ed',
        'cuando' => 'Cuando un jugador solicita reprogramar un partido',
        'canales' => ['app', 'push', 'email'],
        'audiencia' => 'Rival + administradores',
        'ejemplo' => 'El equipo rival solicitó reprogramar el partido. Revisa la solicitud.',
        'config' => 'Automático', 'link' => 'dashboard_repro.php',
    ],
    [
        'icon' => '✅', 'nombre' => 'Reprogramación aprobada', 'color' => '#16a34a', 'bg' => '#f0fdf4',
        'cuando' => 'Cuando el admin aprueba una reprogramación',
        'canales' => ['app', 'push', 'email'],
        'audiencia' => 'Los jugadores del partido',
        'ejemplo' => '📅 Tu partido fue reprogramado para el 14/06 21:00 en Cancha Norte.',
        'config' => 'Automático', 'link' => '',
    ],
    [
        'icon' => '⚽', 'nombre' => 'Resultado ingresado', 'color' => '#0891b2', 'bg' => '#ecfeff',
        'cuando' => 'Cuando un jugador ingresa el resultado de un partido',
        'canales' => ['app', 'push', 'email'],
        'audiencia' => 'Rival + administradores',
        'ejemplo' => '⚽ Se ingresó el resultado de tu partido. Tienes 24 horas para reclamar si hay un problema.',
        'config' => 'Automático', 'link' => '',
    ],
    [
        'icon' => '⚠️', 'nombre' => 'Disputa / reclamo', 'color' => '#dc2626', 'bg' => '#fef2f2',
        'cuando' => 'Cuando un jugador reclama un resultado',
        'canales' => ['app', 'push', 'email'],
        'audiencia' => 'Administradores',
        'ejemplo' => '⚠️ Un jugador reclamó el resultado de un partido. Requiere revisión.',
        'config' => 'Automático', 'link' => 'disputas.php',
    ],
    [
        'icon' => '🕐', 'nombre' => 'Partido atrasado sin resultado', 'color' => '#b45309', 'bg' => '#fffbeb',
        'cuando' => 'Cron — ' . (int)$t['atrasado_horas'] . ' horas después sin marcador cargado',
        'canales' => ['app', 'push', 'email'],
        'audiencia' => 'Administradores',
        'ejemplo' => '⚠️ El partido A vs B lleva más de ' . (int)$t['atrasado_horas'] . ' horas sin marcador.',
        'config' => 'Tiempo editable abajo', 'link' => '', 'editable' => true,
    ],
    [
        'icon' => '🎾', 'nombre' => 'Cancha confirmada por el club', 'color' => '#15803d', 'bg' => '#f0fdf4',
        'cuando' => 'Cuando el club confirma la cancha de un partido',
        'canales' => ['app', 'push', 'email'],
        'audiencia' => 'Jugadores + administradores',
        'ejemplo' => '🎾 El club asignó la cancha «Central» para tu partido.',
        'config' => 'Automático', 'link' => '',
    ],
    [
        'icon' => '🔻', 'nombre' => 'Baja de cancha confirmada', 'color' => '#9333ea', 'bg' => '#faf5ff',
        'cuando' => 'Cuando el club confirma la baja de una reserva',
        'canales' => ['app', 'push', 'email'],
        'audiencia' => 'Administradores',
        'ejemplo' => '✅ El club confirmó la baja de la reserva del partido A vs B.',
        'config' => 'Automático', 'link' => '',
    ],
    [
        'icon' => '🤝', 'nombre' => 'Invitación de compañero', 'color' => '#7c3aed', 'bg' => '#f5f3ff',
        'cuando' => 'Cuando un capitán invita a un compañero a su equipo',
        'canales' => ['app', 'push', 'email'],
        'audiencia' => 'Jugador invitado',
        'ejemplo' => '🤝 Te invitaron a formar equipo en una liga. Acepta la invitación.',
        'config' => 'Automático', 'link' => '',
    ],
];

// ── Datos del resumen ─────────────────────────────────────────────────────────
$resumen = ['app_24h' => 0, 'app_7d' => 0, 'app_30d' => 0, 'mail_7d' => 0, 'mail_err_7d' => 0, 'por_tipo' => [], 'recientes' => []];
if ($tab === 'resumen') {
    try {
        $resumen['app_24h'] = (int)$db->query("SELECT COUNT(*) FROM notificaciones WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)")->fetchColumn();
        $resumen['app_7d']  = (int)$db->query("SELECT COUNT(*) FROM notificaciones WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetchColumn();
        $resumen['app_30d'] = (int)$db->query("SELECT COUNT(*) FROM notificaciones WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)")->fetchColumn();
        $resumen['por_tipo'] = $db->query("
            SELECT tipo, COUNT(*) AS n, MAX(created_at) AS ultima
            FROM notificaciones
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            GROUP BY tipo ORDER BY n DESC
        ")->fetchAll(PDO::FETCH_ASSOC);
        $resumen['recientes'] = $db->query("
            SELECT n.tipo, n.titulo, n.created_at, j.nombre, j.apellido
            FROM notificaciones n
            LEFT JOIN jugadores j ON j.id = n.jugador_id
            ORDER BY n.created_at DESC LIMIT 40
        ")->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) { $err = 'No se pudo leer el resumen: ' . $e->getMessage(); }
    try {
        $resumen['mail_7d']     = (int)$db->query("SELECT COUNT(*) FROM email_log WHERE enviado_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetchColumn();
        $resumen['mail_err_7d'] = (int)$db->query("SELECT COUNT(*) FROM email_log WHERE estado='error' AND enviado_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetchColumn();
    } catch (Throwable $e) {}
}
?>
<?php require_once '../includes/header.php'; ?>

<style>
.al-page { max-width:1100px }
.al-card { background:#fff;border:1.5px solid #e2e8f0;border-radius:14px;padding:1.1rem 1.2rem;display:flex;flex-direction:column;gap:.7rem }
.al-grid { display:grid;grid-template-columns:repeat(auto-fill,minmax(330px,1fr));gap:1rem }
.ch-badge { display:inline-flex;align-items:center;gap:.3rem;font-size:.7rem;font-weight:800;padding:.2rem .55rem;border-radius:999px }
.al-tab { padding:.55rem 1.1rem;border-radius:8px 8px 0 0;font-size:.85rem;font-weight:700;text-decoration:none;border:2px solid transparent;border-bottom:none;margin-bottom:-2px }
.al-stat { background:#fff;border:1.5px solid #e2e8f0;border-radius:12px;padding:1rem 1.2rem;flex:1;min-width:140px }
.al-stat-num { font-family:var(--font-head);font-size:1.9rem;color:#1C2F48;line-height:1 }
.al-stat-lbl { font-size:.74rem;color:#64748b;font-weight:700;text-transform:uppercase;letter-spacing:.05em;margin-top:.3rem }
.tg-field { display:flex;flex-direction:column;gap:.35rem }
.tg-field label { font-size:.78rem;font-weight:800;color:#1C2F48 }
.tg-field small { font-size:.72rem;color:#94a3b8;font-weight:600 }
.tg-field input { padding:.6rem .8rem;border:1.5px solid #e2e8f0;border-radius:10px;font-size:.9rem;font-weight:700;color:#1C2F48;box-sizing:border-box }
</style>

<div class="dash-layout">
  <?php include __DIR__ . '/partials/sidebar.php'; ?>
  <main class="dash-main">
  <div class="al-page">

  <!-- Header -->
  <div class="dash-header" style="margin-bottom:1rem">
    <h1 class="dash-title">🔔 Alertas y notificaciones</h1>
    <p style="color:#64748b;font-size:.88rem;margin:.3rem 0 0">Todas las alertas del sistema en un solo lugar: qué dicen, cuándo se mandan y por qué canales. <strong style="color:#16a34a">Push y email salen siempre juntos.</strong></p>
  </div>

  <!-- Tabs -->
  <div style="display:flex;gap:.4rem;margin-bottom:1.5rem;border-bottom:2px solid #e2e8f0">
    <a href="alertas.php" class="al-tab" style="background:<?= $tab==='catalogo'?'#fff':'transparent' ?>;border-color:<?= $tab==='catalogo'?'#e2e8f0 #e2e8f0 #fff':'transparent' ?>;color:<?= $tab==='catalogo'?'#1C2F48':'#94a3b8' ?>">📋 Catálogo de alertas</a>
    <a href="?tab=resumen" class="al-tab" style="background:<?= $tab==='resumen'?'#fff':'transparent' ?>;border-color:<?= $tab==='resumen'?'#e2e8f0 #e2e8f0 #fff':'transparent' ?>;color:<?= $tab==='resumen'?'#1C2F48':'#94a3b8' ?>">📊 Resumen de envíos</a>
  </div>

  <?php if ($ok): ?><div class="alert alert-success"><?= epl_h($ok) ?></div><?php endif; ?>
  <?php if ($err): ?><div class="alert alert-error"><?= epl_h($err) ?></div><?php endif; ?>
  <?php if (!$smtp_ok): ?>
  <div style="background:#fffbeb;border:1px solid #fde68a;border-radius:10px;padding:.8rem 1.1rem;margin-bottom:1.25rem;font-size:.83rem;color:#92400e;font-weight:600">
    ⚠ SMTP desactivado — <a href="configuracion.php?tab=general" style="color:#1C2F48;text-decoration:underline">configúralo</a> para que los correos se envíen.
  </div>
  <?php endif; ?>

  <?php if ($tab === 'catalogo'): ?>

  <!-- ── Editor de tiempos ── -->
  <div style="background:linear-gradient(135deg,#1C2F48,#2a4365);border-radius:16px;padding:1.4rem 1.5rem;margin-bottom:1.75rem;color:#fff">
    <div style="display:flex;align-items:center;gap:.6rem;margin-bottom:.3rem">
      <span style="font-size:1.3rem">⏱️</span>
      <h2 style="font-family:var(--font-head);font-size:1.1rem;text-transform:uppercase;color:#C9A762;margin:0">Tiempos de las alertas</h2>
    </div>
    <p style="font-size:.82rem;color:rgba(255,255,255,.7);margin:0 0 1.1rem">Controla cuándo se disparan los recordatorios y los avisos automáticos. Aplica al instante (el cron debe correr cada hora).</p>

    <form method="POST">
      <input type="hidden" name="action" value="guardar_tiempos">
      <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:1rem;margin-bottom:1.1rem">
        <div class="tg-field">
          <label style="color:#fff">⏰ Recordatorios (horas antes)</label>
          <input type="text" name="recordatorio_horas" value="<?= epl_h($t['recordatorio_horas']) ?>" placeholder="24,12,3">
          <small style="color:rgba(255,255,255,.55)">Separadas por coma. Ej: 48,24,3</small>
        </div>
        <div class="tg-field">
          <label style="color:#fff">🎯 Tolerancia (minutos)</label>
          <input type="number" name="recordatorio_tol_min" value="<?= (int)$t['recordatorio_tol_min'] ?>" min="5" max="180">
          <small style="color:rgba(255,255,255,.55)">Margen de la ventana del cron.</small>
        </div>
        <div class="tg-field">
          <label style="color:#fff">🕐 Atrasado sin resultado (horas)</label>
          <input type="number" name="atrasado_horas" value="<?= (int)$t['atrasado_horas'] ?>" min="1" max="168">
          <small style="color:rgba(255,255,255,.55)">Avisa a admins pasado este tiempo.</small>
        </div>
        <div class="tg-field">
          <label style="color:#fff">🔒 Bloqueo de reprogramación (horas)</label>
          <input type="number" name="reprog_lock_horas" value="<?= (int)$t['reprog_lock_horas'] ?>" min="0" max="168">
          <small style="color:rgba(255,255,255,.55)">No se puede reprogramar con menos de N horas.</small>
        </div>
      </div>
      <button type="submit" class="btn" style="background:#C9A762;color:#1C2F48;font-weight:800">💾 Guardar tiempos</button>
    </form>
  </div>

  <!-- ── Catálogo ── -->
  <div class="al-grid">
    <?php foreach ($alertas as $a): ?>
    <div class="al-card" style="<?= !empty($a['editable']) ? 'border-color:#fcd34d;background:#fffdf5' : '' ?>">
      <div style="display:flex;align-items:flex-start;gap:.75rem">
        <div style="width:42px;height:42px;border-radius:11px;background:<?= $a['bg'] ?>;display:flex;align-items:center;justify-content:center;font-size:1.3rem;flex-shrink:0"><?= $a['icon'] ?></div>
        <div style="flex:1;min-width:0">
          <div style="font-weight:800;font-size:.95rem;color:#1C2F48"><?= epl_h($a['nombre']) ?></div>
          <div style="font-size:.76rem;color:#64748b;margin-top:.15rem"><strong>👥</strong> <?= epl_h($a['audiencia']) ?></div>
        </div>
        <?php if (!empty($a['editable'])): ?>
          <span style="font-size:.62rem;font-weight:900;background:#fef3c7;color:#92400e;padding:.2rem .5rem;border-radius:6px;text-transform:uppercase;letter-spacing:.04em;white-space:nowrap">⏱ Editable</span>
        <?php endif; ?>
      </div>

      <!-- Canales -->
      <div style="display:flex;flex-wrap:wrap;gap:.35rem">
        <?php foreach ($a['canales'] as $c): [$ic,$lb,$col,$cbg] = $CH[$c]; ?>
          <span class="ch-badge" style="background:<?= $cbg ?>;color:<?= $col ?>"><?= $ic ?> <?= $lb ?></span>
        <?php endforeach; ?>
      </div>

      <!-- Cuándo -->
      <div style="background:#f8fafc;border-radius:8px;padding:.5rem .75rem;font-size:.76rem;color:#475569">
        <strong style="color:#1C2F48">📅 Cuándo:</strong> <?= epl_h($a['cuando']) ?>
      </div>

      <!-- Qué dice -->
      <div style="font-size:.78rem;color:#334155;line-height:1.45;font-style:italic;border-left:3px solid <?= $a['color'] ?>;padding-left:.65rem">
        “<?= epl_h($a['ejemplo']) ?>”
      </div>

      <!-- Config / link -->
      <div style="display:flex;align-items:center;justify-content:space-between;gap:.5rem;margin-top:auto;padding-top:.3rem">
        <span style="font-size:.72rem;color:#94a3b8;font-weight:600"><?= epl_h($a['config']) ?></span>
        <?php if (!empty($a['link'])): ?>
          <a href="<?= epl_h($a['link']) ?>" style="font-size:.74rem;font-weight:700;color:#1e40af;text-decoration:none;white-space:nowrap">Configurar →</a>
        <?php endif; ?>
      </div>
    </div>
    <?php endforeach; ?>
  </div>

  <!-- Nota canales -->
  <div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:12px;padding:1rem 1.25rem;margin-top:1.75rem;font-size:.84rem;color:#15803d">
    <strong>✅ Push + Email + App siempre juntos.</strong> Cada vez que se dispara una alerta de partido, el jugador la recibe en la app, como notificación push en su teléfono y por correo. Así no se pierde ningún aviso.
  </div>

  <?php else: ?>
  <!-- ══════════════ RESUMEN ══════════════ -->

  <!-- Stats -->
  <div style="display:flex;gap:.85rem;flex-wrap:wrap;margin-bottom:1.5rem">
    <div class="al-stat"><div class="al-stat-num"><?= $resumen['app_24h'] ?></div><div class="al-stat-lbl">Alertas últimas 24h</div></div>
    <div class="al-stat"><div class="al-stat-num"><?= $resumen['app_7d'] ?></div><div class="al-stat-lbl">Últimos 7 días</div></div>
    <div class="al-stat"><div class="al-stat-num"><?= $resumen['app_30d'] ?></div><div class="al-stat-lbl">Últimos 30 días</div></div>
    <div class="al-stat"><div class="al-stat-num" style="color:#16a34a"><?= $resumen['mail_7d'] ?></div><div class="al-stat-lbl">Correos 7 días<?= $resumen['mail_err_7d'] ? ' · <span style="color:#dc2626">'.$resumen['mail_err_7d'].' err</span>' : '' ?></div></div>
  </div>

  <!-- Por tipo -->
  <h2 style="font-size:1rem;font-weight:800;color:#1C2F48;margin:0 0 .85rem">Por tipo de alerta (30 días)</h2>
  <?php if (empty($resumen['por_tipo'])): ?>
    <div style="background:#f8fafc;border:2px dashed #e2e8f0;border-radius:12px;text-align:center;padding:2.5rem 1rem;margin-bottom:2rem">
      <div style="font-size:2rem;margin-bottom:.4rem">📭</div>
      <p style="color:#64748b;font-size:.88rem;margin:0">Sin alertas registradas en los últimos 30 días.</p>
    </div>
  <?php else: ?>
    <?php
      $max_n = max(array_map(fn($r) => (int)$r['n'], $resumen['por_tipo'])) ?: 1;
    ?>
    <div style="background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:1.1rem 1.25rem;margin-bottom:2rem;display:flex;flex-direction:column;gap:.7rem">
      <?php foreach ($resumen['por_tipo'] as $r):
        $pct = round((int)$r['n'] / $max_n * 100);
        $ult = $r['ultima'] ? date('d/m H:i', strtotime($r['ultima'])) : '—';
      ?>
      <div style="display:flex;align-items:center;gap:.75rem">
        <div style="width:160px;flex-shrink:0;font-size:.82rem;font-weight:700;color:#1C2F48;display:flex;align-items:center;gap:.4rem">
          <span><?= epl_notif_icono($r['tipo']) ?></span>
          <span style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= epl_h(epl_notif_tipo_label($r['tipo'])) ?></span>
        </div>
        <div style="flex:1;background:#f1f5f9;border-radius:999px;height:22px;position:relative;overflow:hidden">
          <div style="position:absolute;inset:0 auto 0 0;width:<?= $pct ?>%;background:linear-gradient(90deg,#1C2F48,#3b82f6);border-radius:999px;min-width:24px"></div>
        </div>
        <div style="width:90px;text-align:right;flex-shrink:0">
          <span style="font-weight:800;color:#1C2F48;font-size:.9rem"><?= (int)$r['n'] ?></span>
          <span style="font-size:.68rem;color:#94a3b8;display:block">últ. <?= $ult ?></span>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <!-- Recientes -->
  <h2 style="font-size:1rem;font-weight:800;color:#1C2F48;margin:0 0 .85rem">Últimas alertas enviadas</h2>
  <?php if (empty($resumen['recientes'])): ?>
    <div style="background:#f8fafc;border:2px dashed #e2e8f0;border-radius:12px;text-align:center;padding:2rem 1rem">
      <p style="color:#64748b;font-size:.88rem;margin:0">Sin actividad reciente.</p>
    </div>
  <?php else: ?>
    <div style="background:#fff;border:1px solid #e2e8f0;border-radius:12px;overflow:hidden;overflow-x:auto">
      <table style="width:100%;border-collapse:collapse;font-size:.82rem;min-width:520px">
        <thead>
          <tr style="background:#f8fafc;border-bottom:2px solid #e2e8f0">
            <th style="padding:.7rem 1rem;text-align:left;font-weight:700;color:#475569;font-size:.72rem;text-transform:uppercase;white-space:nowrap">Fecha</th>
            <th style="padding:.7rem 1rem;text-align:left;font-weight:700;color:#475569;font-size:.72rem;text-transform:uppercase">Tipo</th>
            <th style="padding:.7rem 1rem;text-align:left;font-weight:700;color:#475569;font-size:.72rem;text-transform:uppercase">Destinatario</th>
            <th style="padding:.7rem 1rem;text-align:left;font-weight:700;color:#475569;font-size:.72rem;text-transform:uppercase">Título</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($resumen['recientes'] as $i => $r):
            $ts = strtotime($r['created_at']);
            $bg = $i % 2 === 0 ? '#fff' : '#fafafa';
          ?>
          <tr style="background:<?= $bg ?>;border-bottom:1px solid #f1f5f9">
            <td style="padding:.6rem 1rem;white-space:nowrap;color:#64748b">
              <div style="font-weight:700;color:#1C2F48"><?= date('d/m/Y', $ts) ?></div>
              <div style="font-size:.72rem"><?= date('H:i', $ts) ?></div>
            </td>
            <td style="padding:.6rem 1rem;white-space:nowrap">
              <span style="font-weight:700;color:#334155"><?= epl_notif_icono($r['tipo']) ?> <?= epl_h(epl_notif_tipo_label($r['tipo'])) ?></span>
            </td>
            <td style="padding:.6rem 1rem;color:#475569">
              <?= epl_h(trim(($r['nombre'] ?? '') . ' ' . ($r['apellido'] ?? '')) ?: '—') ?>
            </td>
            <td style="padding:.6rem 1rem;color:#475569;max-width:260px">
              <div style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="<?= epl_h($r['titulo']) ?>"><?= epl_h($r['titulo']) ?></div>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <p style="font-size:.76rem;color:#94a3b8;margin:.75rem 0 0">Mostrando las 40 alertas más recientes. ¿Buscás el detalle de correos? Mira el <a href="automatizaciones.php?tab=historial" style="color:#1e40af">historial de envíos</a>.</p>
  <?php endif; ?>

  <?php endif; ?>

  </div><!-- .al-page -->
  </main>
</div>

<?php require_once '../includes/footer.php'; ?>
