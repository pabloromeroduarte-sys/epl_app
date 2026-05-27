<?php
$page_title = 'Automatizaciones';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../includes/mail.php';
epl_require_admin();

$db = epl_db();

$db->exec("CREATE TABLE IF NOT EXISTS email_automatizaciones (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    nombre       VARCHAR(150) NOT NULL,
    trigger_tipo VARCHAR(60)  NOT NULL,
    destinatario VARCHAR(20)  NOT NULL DEFAULT 'jugador',
    activo       TINYINT(1)   NOT NULL DEFAULT 0,
    asunto       VARCHAR(255) NOT NULL DEFAULT '',
    cuerpo       LONGTEXT     NOT NULL,
    created_at   TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    updated_at   TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Migración: agregar columnas que pueden no existir en instalaciones anteriores
foreach ([
    "ALTER TABLE email_automatizaciones ADD COLUMN destinatario VARCHAR(20) NOT NULL DEFAULT 'jugador' AFTER trigger_tipo",
    "ALTER TABLE email_automatizaciones ADD COLUMN updated_at   TIMESTAMP   DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP",
] as $migration) {
    try { $db->exec($migration); } catch (Throwable $e) { /* columna ya existe */ }
}

$triggers = [
    'registro'   => ['label'=>'Registro de cuenta',  'icon'=>'📝','color'=>'#6366f1','bg'=>'#eef2ff','campo'=>'Evento del sistema',        'cuando'=>'Cuando un jugador crea su cuenta'],
    'cumpleanos' => ['label'=>'Cumpleaños',           'icon'=>'🎂','color'=>'#f59e0b','bg'=>'#fffbeb','campo'=>'Campo: fecha_nacimiento',   'cuando'=>'Cuando la fecha de nacimiento coincide con hoy'],
];

$dest_labels = [
    'jugador' => ['icon'=>'👤','label'=>'Al jugador'],
    'admins'  => ['icon'=>'👥','label'=>'A los admins'],
    'ambos'   => ['icon'=>'🤝','label'=>'Jugador + Admins'],
];

$_flash = epl_flash_get();
$ok     = ($_flash && $_flash['tipo']==='ok') ? $_flash['msg'] : '';
$err    = '';
$editing = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if (in_array($action, ['crear','actualizar'], true)) {
        $nombre = trim($_POST['nombre']      ?? '');
        $tipo   = $_POST['trigger_tipo']     ?? '';
        $dest   = $_POST['destinatario']     ?? 'jugador';
        $asunto = trim($_POST['asunto']      ?? '');
        $cuerpo = trim($_POST['cuerpo']      ?? '');
        $activo = !empty($_POST['activo']) ? 1 : 0;

        if (!$nombre)                         $err = 'El nombre es obligatorio.';
        elseif (!isset($triggers[$tipo]))     $err = 'Selecciona un tipo de disparo.';
        elseif (!isset($dest_labels[$dest]))  $err = 'Selecciona el destinatario.';
        elseif (!$asunto)                     $err = 'El asunto es obligatorio.';
        else {
            if ($activo) {
                $db->prepare("UPDATE email_automatizaciones SET activo=0 WHERE trigger_tipo=? AND destinatario=?")
                   ->execute([$tipo, $dest]);
            }
            if ($action === 'crear') {
                $db->prepare("INSERT INTO email_automatizaciones (nombre,trigger_tipo,destinatario,activo,asunto,cuerpo) VALUES (?,?,?,?,?,?)")
                   ->execute([$nombre,$tipo,$dest,$activo,$asunto,$cuerpo]);
                epl_redirect_ok('Automatización creada.', 'automatizaciones.php');
            } else {
                $id = (int)($_POST['id'] ?? 0);
                $db->prepare("UPDATE email_automatizaciones SET nombre=?,trigger_tipo=?,destinatario=?,activo=?,asunto=?,cuerpo=?,updated_at=NOW() WHERE id=?")
                   ->execute([$nombre,$tipo,$dest,$activo,$asunto,$cuerpo,$id]);
                epl_redirect_ok('Guardado.', 'automatizaciones.php');
            }
        }
        if ($err && $action === 'actualizar') {
            $id = (int)($_POST['id'] ?? 0);
            if ($id) {
                $st = $db->prepare("SELECT * FROM email_automatizaciones WHERE id=?");
                $st->execute([$id]);
                $editing = $st->fetch(PDO::FETCH_ASSOC) ?: null;
            }
        }
    }

    if ($action === 'toggle') {
        $id   = (int)($_POST['id'] ?? 0);
        $st   = $db->prepare("SELECT * FROM email_automatizaciones WHERE id=?");
        $st->execute([$id]);
        $auto = $st->fetch(PDO::FETCH_ASSOC);
        if ($auto) {
            $nuevo = $auto['activo'] ? 0 : 1;
            if ($nuevo) {
                $db->prepare("UPDATE email_automatizaciones SET activo=0 WHERE trigger_tipo=? AND destinatario=?")
                   ->execute([$auto['trigger_tipo'], $auto['destinatario']]);
            }
            $db->prepare("UPDATE email_automatizaciones SET activo=? WHERE id=?")->execute([$nuevo,$id]);
        }
        header('Location: automatizaciones.php'); exit;
    }

    if ($action === 'eliminar') {
        $db->prepare("DELETE FROM email_automatizaciones WHERE id=?")->execute([(int)($_POST['id'] ?? 0)]);
        header('Location: automatizaciones.php'); exit;
    }

    if ($action === 'probar') {
        $id = (int)($_POST['id'] ?? 0);
        $st = $db->prepare("SELECT * FROM email_automatizaciones WHERE id=?");
        $st->execute([$id]);
        $auto = $st->fetch(PDO::FETCH_ASSOC);
        if ($auto) {
            require_once '../includes/mail_automations.php';
            $j    = epl_jugador_actual();
            $vars = ['nombre'=>$j['nombre']??'Admin','apellido'=>$j['apellido']??'','email'=>$j['email']??''];
            $asF  = epl_auto_render($auto['asunto'], $vars);
            $coF  = epl_auto_render($auto['cuerpo'], $vars);
            $html = epl_mail_plantilla($asF, $coF);
            $res  = epl_mail_enviar($j['email']??'', $asF.' [PRUEBA]', $html);
            // Loguear la prueba
            epl_email_log_init();
            epl_email_log((int)$auto['id'], $auto['nombre'], $auto['trigger_tipo'],
                $j['email']??'', ($j['nombre']??'').' '.($j['apellido']??''), 'prueba',
                $asF.' [PRUEBA]', $res['ok'], $res['error']??'');
            $ok  = $res['ok'] ? 'Prueba enviada a '.($j['email']??'').'.': '';
            $err = $res['ok'] ? '' : ($res['error']??'Error al enviar');
        }
    }

    if ($action === 'enviar_bienvenida_manual') {
        require_once '../includes/mail_automations.php';
        $ids = array_filter(array_map('intval', $_POST['jugador_ids'] ?? []));
        if (empty($ids)) {
            $err = 'No seleccionaste ningún jugador.';
        } else {
            $ph = implode(',', array_fill(0, count($ids), '?'));
            $stJ = $db->prepare("SELECT id, nombre, apellido, email FROM jugadores WHERE id IN ($ph) AND estado='activo'");
            $stJ->execute($ids);
            $jugadores_send = $stJ->fetchAll(PDO::FETCH_ASSOC);
            $enviados = 0; $fallidos = 0;
            foreach ($jugadores_send as $jg) {
                if (empty($jg['email'])) { $fallidos++; continue; }
                try {
                    epl_auto_dispatch('registro', $jg);
                    $enviados++;
                } catch (Throwable $e) {
                    $fallidos++;
                    error_log('[bienvenida_manual] ' . $e->getMessage());
                }
            }
            $msg = "Bienvenida enviada a {$enviados} jugador(es)";
            if ($fallidos) $msg .= " ({$fallidos} sin email o con error)";
            epl_redirect_ok($msg . '.', 'automatizaciones.php');
        }
    }

    if ($action === 'limpiar_log') {
        $auto_id = (int)($_POST['auto_id'] ?? 0);
        if ($auto_id) {
            $db->prepare("DELETE FROM email_log WHERE auto_id=?")->execute([$auto_id]);
        } else {
            $db->exec("DELETE FROM email_log");
        }
        header('Location: automatizaciones.php?tab=historial'); exit;
    }
}

if (!$editing && isset($_GET['editar'])) {
    $st = $db->prepare("SELECT * FROM email_automatizaciones WHERE id=?");
    $st->execute([(int)$_GET['editar']]);
    $editing = $st->fetch(PDO::FETCH_ASSOC) ?: null;
}

$tab      = isset($_GET['tab']) && $_GET['tab'] === 'historial' ? 'historial' : 'automatizaciones';
$show_form = ($tab === 'automatizaciones') && (isset($_GET['nuevo']) || $editing !== null);
$lista    = $db->query("SELECT * FROM email_automatizaciones ORDER BY trigger_tipo, activo DESC, nombre")->fetchAll(PDO::FETCH_ASSOC);
$smtp_ok  = epl_smtp_habilitado();
// Jugadores recientes (últimas 12 horas) para el envío manual de bienvenida
$jugadores_recientes = $db->query("
    SELECT id, nombre, apellido, email, created_at
    FROM jugadores
    WHERE estado='activo'
      AND email IS NOT NULL AND email <> ''
      AND created_at >= DATE_SUB(NOW(), INTERVAL 12 HOUR)
    ORDER BY created_at DESC
    LIMIT 100
")->fetchAll(PDO::FETCH_ASSOC);
$app_name = epl_config_get('smtp_from_name', 'Elite Padel League');

// Datos JS para el editor
$editing_tipo = $editing['trigger_tipo'] ?? '';
$editing_dest = $editing['destinatario'] ?? 'jugador';

// Historial: inicializar tabla e inicializar log
require_once '../includes/mail_automations.php';
epl_email_log_init();

// Datos del historial (siempre cargamos, para el badge de cantidad)
$log_filtro_id = isset($_GET['auto_id']) ? (int)$_GET['auto_id'] : 0;
if ($log_filtro_id) {
    $stLog = $db->prepare("SELECT l.*, a.nombre as auto_nombre_rel FROM email_log l LEFT JOIN email_automatizaciones a ON a.id=l.auto_id WHERE l.auto_id=? ORDER BY l.enviado_at DESC LIMIT 200");
    $stLog->execute([$log_filtro_id]);
} else {
    $stLog = $db->query("SELECT l.*, a.nombre as auto_nombre_rel FROM email_log l LEFT JOIN email_automatizaciones a ON a.id=l.auto_id ORDER BY l.enviado_at DESC LIMIT 200");
}
$log_rows   = $stLog ? $stLog->fetchAll(PDO::FETCH_ASSOC) : [];
$log_total  = count($log_rows);
$log_errors = count(array_filter($log_rows, fn($r) => $r['estado'] === 'error'));
?>
<?php require_once '../includes/header.php'; ?>

<style>
.auto-page { max-width:1100px }

/* Lista */
.auto-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(310px,1fr)); gap:1rem; margin-bottom:2rem }
.auto-card-item {
  background:#fff; border:1.5px solid #e2e8f0; border-radius:14px;
  padding:1.1rem 1.2rem; display:flex; flex-direction:column; gap:.75rem;
  transition:box-shadow .15s, border-color .15s;
}
.auto-card-item:hover { box-shadow:0 4px 18px rgba(0,0,0,.08); border-color:#cbd5e1 }
.auto-card-item.is-active { border-color:#22c55e; background:#f0fdf4 }

.trigger-pill { display:inline-flex;align-items:center;gap:.4rem;font-size:.73rem;font-weight:700;padding:.28rem .7rem;border-radius:999px;white-space:nowrap }
.dest-pill    { display:inline-flex;align-items:center;gap:.35rem;font-size:.73rem;font-weight:600;padding:.28rem .65rem;border-radius:999px;background:#f1f5f9;color:#475569 }

/* Toggle switch */
.sw { position:relative;display:inline-block;width:44px;height:24px;flex-shrink:0 }
.sw input { opacity:0;width:0;height:0 }
.sw-slider { position:absolute;cursor:pointer;inset:0;background:#cbd5e1;border-radius:999px;transition:.25s }
.sw-slider:before { content:'';position:absolute;width:18px;height:18px;left:3px;top:3px;background:#fff;border-radius:50%;transition:.25s;box-shadow:0 1px 4px rgba(0,0,0,.2) }
.sw input:checked + .sw-slider { background:#22c55e }
.sw input:checked + .sw-slider:before { transform:translateX(20px) }

/* Trigger cards */
.trigger-grid { display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:.75rem;margin-bottom:1.5rem }
.trigger-opt {
  border:2px solid #e2e8f0;border-radius:12px;padding:1rem;
  cursor:pointer;transition:border-color .15s,background .15s;background:#fff;
  display:flex;flex-direction:column;gap:.4rem;user-select:none;
}
.trigger-opt:hover { border-color:#94a3b8;background:#f8fafc }
.trigger-opt.sel  { border-width:2px }
.t-icon  { font-size:1.6rem }
.t-label { font-weight:700;font-size:.88rem;color:#1C2F48 }
.t-campo { font-size:.73rem;color:#64748b;font-weight:600 }
.t-when  { font-size:.72rem;color:#94a3b8;line-height:1.4 }

/* Dest cards */
.dest-grid { display:flex;flex-wrap:wrap;gap:.65rem;margin-bottom:1.5rem }
.dest-opt {
  border:2px solid #e2e8f0;border-radius:10px;padding:.65rem 1.1rem;
  cursor:pointer;transition:border-color .15s,background .15s;background:#fff;
  display:flex;align-items:center;gap:.5rem;
  font-weight:700;font-size:.85rem;color:#1C2F48;user-select:none;
}
.dest-opt:hover { border-color:#94a3b8 }
.dest-opt.sel   { border-color:#1C2F48;background:#eef2ff }

/* Editor + Preview */
.ep-wrap { display:grid;grid-template-columns:1fr 1fr;gap:1.25rem }
@media(max-width:860px){ .ep-wrap{grid-template-columns:1fr} }
.preview-iframe { width:100%;height:500px;border:1px solid #e2e8f0;border-radius:10px;background:#f1f5f9 }

/* Section steps */
.section-step { display:flex;align-items:center;gap:.6rem;font-size:.75rem;font-weight:800;text-transform:uppercase;letter-spacing:.1em;color:#64748b;margin-bottom:.75rem }
.step-num { width:22px;height:22px;border-radius:50%;background:#1C2F48;color:#fff;display:flex;align-items:center;justify-content:center;font-size:.7rem;font-weight:900;flex-shrink:0 }
.vars-chip { display:inline-block;background:#dbeafe;color:#1e40af;font-size:.72rem;font-family:monospace;padding:.1rem .45rem;border-radius:4px;margin:.1rem .2rem;font-weight:600 }
</style>

<div class="dash-layout">
  <?php include __DIR__ . '/partials/sidebar.php'; ?>
  <main class="dash-main">
  <div class="auto-page">

  <!-- Header -->
  <div class="dash-header" style="display:flex;align-items:flex-start;justify-content:space-between;flex-wrap:wrap;gap:.75rem;margin-bottom:1rem">
    <div>
      <h1 class="dash-title">Automatizaciones</h1>
      <p style="color:#64748b;font-size:.88rem;margin:.3rem 0 0">Correos automáticos según eventos o campos del jugador.</p>
    </div>
    <?php if (!$show_form && $tab === 'automatizaciones'): ?>
    <a href="?nuevo=1" class="btn btn-primary" style="white-space:nowrap">+ Nueva automatización</a>
    <?php endif; ?>
  </div>

  <!-- Tabs -->
  <div style="display:flex;gap:.4rem;margin-bottom:1.5rem;border-bottom:2px solid #e2e8f0;padding-bottom:0">
    <a href="automatizaciones.php" style="padding:.55rem 1.1rem;border-radius:8px 8px 0 0;font-size:.83rem;font-weight:700;text-decoration:none;border:2px solid transparent;border-bottom:none;margin-bottom:-2px;
       background:<?= $tab==='automatizaciones' ? '#fff' : 'transparent' ?>;
       border-color:<?= $tab==='automatizaciones' ? '#e2e8f0 #e2e8f0 #fff' : 'transparent' ?>;
       color:<?= $tab==='automatizaciones' ? '#1C2F48' : '#94a3b8' ?>">
      ✉ Automatizaciones
    </a>
    <a href="?tab=historial" style="padding:.55rem 1.1rem;border-radius:8px 8px 0 0;font-size:.83rem;font-weight:700;text-decoration:none;border:2px solid transparent;border-bottom:none;margin-bottom:-2px;
       background:<?= $tab==='historial' ? '#fff' : 'transparent' ?>;
       border-color:<?= $tab==='historial' ? '#e2e8f0 #e2e8f0 #fff' : 'transparent' ?>;
       color:<?= $tab==='historial' ? '#1C2F48' : '#94a3b8' ?>">
      📋 Historial
      <?php if ($log_total > 0): ?>
        <span style="background:<?= $log_errors > 0 ? '#fef2f2' : '#f0fdf4' ?>;color:<?= $log_errors > 0 ? '#dc2626' : '#15803d' ?>;font-size:.7rem;padding:.1rem .45rem;border-radius:999px;margin-left:.3rem">
          <?= $log_total ?>
        </span>
      <?php endif; ?>
    </a>
  </div>

  <?php if ($ok): ?><div class="alert alert-success"><?= epl_h($ok) ?></div><?php endif; ?>
  <?php if ($err): ?><div class="alert alert-error"><?= epl_h($err) ?></div><?php endif; ?>
  <?php if (!$smtp_ok): ?>
  <div style="background:#fffbeb;border:1px solid #fde68a;border-radius:10px;padding:.8rem 1.1rem;margin-bottom:1.25rem;font-size:.83rem;color:#92400e;font-weight:600">
    ⚠ SMTP desactivado —
    <a href="configuracion.php?tab=general" style="color:#1C2F48;text-decoration:underline">Configúralo aquí</a>
    para que los correos se envíen.
  </div>
  <?php endif; ?>

  <?php if ($tab === 'historial'): ?>
  <!-- ══════════════ HISTORIAL ══════════════ -->
  <div style="max-width:960px">

    <!-- Filtros + acciones -->
    <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:.75rem;margin-bottom:1.1rem">
      <!-- Filtro por automatización -->
      <div style="display:flex;align-items:center;gap:.5rem;flex-wrap:wrap">
        <span style="font-size:.82rem;font-weight:700;color:#64748b">Filtrar:</span>
        <a href="?tab=historial" style="font-size:.8rem;font-weight:700;padding:.35rem .75rem;border-radius:8px;text-decoration:none;
           background:<?= !$log_filtro_id ? '#1C2F48' : '#f1f5f9' ?>;color:<?= !$log_filtro_id ? '#fff' : '#475569' ?>">
          Todos
        </a>
        <?php foreach ($lista as $li): ?>
        <a href="?tab=historial&auto_id=<?= (int)$li['id'] ?>" style="font-size:.8rem;font-weight:700;padding:.35rem .75rem;border-radius:8px;text-decoration:none;
           background:<?= $log_filtro_id===$li['id'] ? '#1C2F48' : '#f1f5f9' ?>;color:<?= $log_filtro_id===$li['id'] ? '#fff' : '#475569' ?>">
          <?= epl_h($li['nombre']) ?>
        </a>
        <?php endforeach; ?>
      </div>
      <!-- Limpiar log -->
      <form method="POST" onsubmit="return confirm('¿Eliminar estos registros del historial?')">
        <input type="hidden" name="action"  value="limpiar_log">
        <input type="hidden" name="auto_id" value="<?= $log_filtro_id ?>">
        <button type="submit" style="font-size:.78rem;font-weight:700;padding:.38rem .85rem;background:#fef2f2;color:#dc2626;border:none;border-radius:8px;cursor:pointer">
          🗑 Limpiar<?= $log_filtro_id ? ' este historial' : ' todo' ?>
        </button>
      </form>
    </div>

    <?php if (empty($log_rows)): ?>
    <div style="background:#f8fafc;border:2px dashed #e2e8f0;border-radius:12px;text-align:center;padding:2.5rem 1rem">
      <div style="font-size:2rem;margin-bottom:.4rem">📭</div>
      <p style="color:#64748b;font-size:.88rem;margin:0">Sin envíos registrados todavía.</p>
    </div>
    <?php else: ?>

    <!-- Resumen rápido -->
    <div style="display:flex;gap:.75rem;flex-wrap:wrap;margin-bottom:1rem">
      <?php
        $tot     = count($log_rows);
        $enviados = count(array_filter($log_rows, fn($r) => $r['estado'] === 'enviado'));
        $errores  = $tot - $enviados;
        $pruebas  = count(array_filter($log_rows, fn($r) => $r['rol_destino'] === 'prueba'));
      ?>
      <div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:10px;padding:.6rem 1rem;font-size:.82rem;font-weight:700;color:#15803d">
        ✓ <?= $enviados ?> enviados
      </div>
      <?php if ($errores): ?>
      <div style="background:#fef2f2;border:1px solid #fecaca;border-radius:10px;padding:.6rem 1rem;font-size:.82rem;font-weight:700;color:#dc2626">
        ✗ <?= $errores ?> con error
      </div>
      <?php endif; ?>
      <?php if ($pruebas): ?>
      <div style="background:#eff6ff;border:1px solid #bfdbfe;border-radius:10px;padding:.6rem 1rem;font-size:.82rem;font-weight:700;color:#1e40af">
        ▶ <?= $pruebas ?> pruebas
      </div>
      <?php endif; ?>
    </div>

    <!-- Tabla -->
    <div style="background:#fff;border:1px solid #e2e8f0;border-radius:12px;overflow:hidden">
      <table style="width:100%;border-collapse:collapse;font-size:.82rem">
        <thead>
          <tr style="background:#f8fafc;border-bottom:2px solid #e2e8f0">
            <th style="padding:.7rem 1rem;text-align:left;font-weight:700;color:#475569;font-size:.75rem;text-transform:uppercase;letter-spacing:.06em;white-space:nowrap">Fecha y hora</th>
            <th style="padding:.7rem 1rem;text-align:left;font-weight:700;color:#475569;font-size:.75rem;text-transform:uppercase;letter-spacing:.06em">Automatización</th>
            <th style="padding:.7rem 1rem;text-align:left;font-weight:700;color:#475569;font-size:.75rem;text-transform:uppercase;letter-spacing:.06em">Destinatario</th>
            <th style="padding:.7rem 1rem;text-align:left;font-weight:700;color:#475569;font-size:.75rem;text-transform:uppercase;letter-spacing:.06em">Asunto</th>
            <th style="padding:.7rem 1rem;text-align:center;font-weight:700;color:#475569;font-size:.75rem;text-transform:uppercase;letter-spacing:.06em">Estado</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($log_rows as $i => $row):
            $es_error  = $row['estado'] === 'error';
            $es_prueba = $row['rol_destino'] === 'prueba';
            $trig_info = $triggers[$row['trigger_tipo']] ?? ['icon'=>'✉','label'=>$row['trigger_tipo']];
            $bg = $i % 2 === 0 ? '#fff' : '#fafafa';
          ?>
          <tr style="background:<?= $bg ?>;border-bottom:1px solid #f1f5f9" <?= $es_error ? 'title="'.epl_h($row['error_msg'] ?? '').'"' : '' ?>>
            <!-- Fecha -->
            <td style="padding:.65rem 1rem;white-space:nowrap;color:#64748b">
              <?php
                $ts  = strtotime($row['enviado_at']);
                echo '<div style="font-weight:700;color:#1C2F48">'.date('d/m/Y', $ts).'</div>';
                echo '<div style="font-size:.73rem">'.date('H:i:s', $ts).'</div>';
              ?>
            </td>
            <!-- Automatización -->
            <td style="padding:.65rem 1rem">
              <div style="font-weight:700;color:#1C2F48;white-space:nowrap">
                <?= $trig_info['icon'] ?> <?= epl_h($row['auto_nombre'] ?: ($row['auto_nombre_rel'] ?? '—')) ?>
              </div>
              <div style="font-size:.73rem;color:#94a3b8"><?= epl_h($trig_info['label']) ?></div>
            </td>
            <!-- Destinatario -->
            <td style="padding:.65rem 1rem">
              <div style="font-weight:600;color:#334155">
                <?= epl_h($row['nombre_destino'] ?: $row['email_destino']) ?>
              </div>
              <div style="font-size:.73rem;color:#94a3b8"><?= epl_h($row['email_destino']) ?></div>
              <?php if ($es_prueba): ?>
                <span style="font-size:.7rem;font-weight:700;background:#eff6ff;color:#1e40af;padding:.1rem .4rem;border-radius:4px">PRUEBA</span>
              <?php elseif ($row['rol_destino'] === 'admin'): ?>
                <span style="font-size:.7rem;font-weight:700;background:#faf5ff;color:#7c3aed;padding:.1rem .4rem;border-radius:4px">ADMIN</span>
              <?php endif; ?>
            </td>
            <!-- Asunto -->
            <td style="padding:.65rem 1rem;max-width:220px">
              <div style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap;color:#475569" title="<?= epl_h($row['asunto']) ?>">
                <?= epl_h($row['asunto']) ?>
              </div>
            </td>
            <!-- Estado -->
            <td style="padding:.65rem 1rem;text-align:center">
              <?php if ($es_error): ?>
                <span style="display:inline-flex;align-items:center;gap:.3rem;background:#fef2f2;color:#dc2626;font-size:.75rem;font-weight:700;padding:.28rem .7rem;border-radius:999px" title="<?= epl_h($row['error_msg'] ?? '') ?>">
                  ✗ Error
                </span>
                <?php if ($row['error_msg']): ?>
                <div style="font-size:.7rem;color:#dc2626;margin-top:.2rem;max-width:160px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="<?= epl_h($row['error_msg']) ?>">
                  <?= epl_h(mb_substr($row['error_msg'], 0, 60)) ?>…
                </div>
                <?php endif; ?>
              <?php else: ?>
                <span style="display:inline-flex;align-items:center;gap:.3rem;background:#f0fdf4;color:#15803d;font-size:.75rem;font-weight:700;padding:.28rem .7rem;border-radius:999px">
                  ✓ Enviado
                </span>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php if ($log_total >= 200): ?>
    <p style="font-size:.78rem;color:#94a3b8;text-align:center;margin:.75rem 0 0">Mostrando los últimos 200 registros.</p>
    <?php endif; ?>
    <?php endif; ?>

  </div>
  <?php elseif (!$show_form): ?>
  <!-- ══════════════ LISTA ══════════════ -->
  <?php if (empty($lista)): ?>
  <div style="background:#f8fafc;border:2px dashed #e2e8f0;border-radius:14px;text-align:center;padding:3rem 1rem;max-width:640px">
    <div style="font-size:2.5rem;margin-bottom:.5rem">✉</div>
    <p style="font-weight:700;color:#1C2F48;margin:0 0 .35rem">Sin automatizaciones todavía</p>
    <p style="color:#64748b;font-size:.85rem;margin:0 0 1.25rem">Crea la primera para empezar a enviar correos automáticos.</p>
    <a href="?nuevo=1" class="btn btn-primary">+ Crear automatización</a>
  </div>
  <?php else: ?>
  <div class="auto-grid">
    <?php foreach ($lista as $auto):
      $trig = $triggers[$auto['trigger_tipo']] ?? ['label'=>$auto['trigger_tipo'],'icon'=>'✉','color'=>'#64748b','bg'=>'#f1f5f9','cuando'=>''];
      $dest = $dest_labels[$auto['destinatario']] ?? ['icon'=>'','label'=>$auto['destinatario']];
    ?>
    <div class="auto-card-item <?= $auto['activo'] ? 'is-active' : '' ?>">
      <!-- Cabecera -->
      <div style="display:flex;align-items:flex-start;gap:.75rem">
        <div style="width:42px;height:42px;border-radius:11px;background:<?= $trig['bg'] ?>;display:flex;align-items:center;justify-content:center;font-size:1.3rem;flex-shrink:0">
          <?= $trig['icon'] ?>
        </div>
        <div style="flex:1;min-width:0">
          <div style="font-weight:700;font-size:.92rem;color:#1C2F48;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="<?= epl_h($auto['nombre']) ?>">
            <?= epl_h($auto['nombre']) ?>
          </div>
          <div style="font-size:.76rem;color:#94a3b8;margin-top:.15rem;line-height:1.35">
            <?= epl_h($auto['asunto']) ?>
          </div>
        </div>
        <!-- Toggle -->
        <form method="POST" style="flex-shrink:0">
          <input type="hidden" name="action" value="toggle">
          <input type="hidden" name="id"     value="<?= (int)$auto['id'] ?>">
          <label class="sw" title="<?= $auto['activo'] ? 'Desactivar' : 'Activar' ?>">
            <input type="checkbox" <?= $auto['activo'] ? 'checked' : '' ?> onchange="this.closest('form').submit()">
            <span class="sw-slider"></span>
          </label>
        </form>
      </div>

      <!-- Chips -->
      <div style="display:flex;flex-wrap:wrap;gap:.4rem">
        <span class="trigger-pill" style="background:<?= $trig['bg'] ?>;color:<?= $trig['color'] ?>">
          <?= $trig['icon'] ?> <?= $trig['label'] ?>
        </span>
        <span class="dest-pill"><?= $dest['icon'] ?> <?= $dest['label'] ?></span>
      </div>

      <!-- Cuándo -->
      <div style="background:#f8fafc;border-radius:8px;padding:.5rem .8rem;font-size:.77rem;color:#475569">
        <strong style="color:#1C2F48">Disparo:</strong> <?= epl_h($trig['cuando']) ?>
      </div>

      <!-- Acciones -->
      <div style="display:flex;gap:.4rem;flex-wrap:wrap">
        <a href="?editar=<?= (int)$auto['id'] ?>" style="flex:1;text-align:center;padding:.45rem .5rem;background:#eff6ff;color:#1e40af;border-radius:8px;font-size:.78rem;font-weight:700;text-decoration:none">✏ Editar</a>
        <form method="POST" style="flex:1">
          <input type="hidden" name="action" value="probar">
          <input type="hidden" name="id"     value="<?= (int)$auto['id'] ?>">
          <button type="submit" style="width:100%;padding:.45rem .5rem;background:#f0fdf4;color:#15803d;border:none;border-radius:8px;font-size:.78rem;font-weight:700;cursor:pointer">▶ Probar</button>
        </form>
        <form method="POST" onsubmit="return confirm('¿Eliminar esta automatización?')">
          <input type="hidden" name="action" value="eliminar">
          <input type="hidden" name="id"     value="<?= (int)$auto['id'] ?>">
          <button type="submit" style="padding:.45rem .65rem;background:#fef2f2;color:#dc2626;border:none;border-radius:8px;font-size:.78rem;cursor:pointer">🗑</button>
        </form>
      </div>

      <?php if ($auto['trigger_tipo'] === 'registro' && $auto['activo']): ?>
        <!-- Envío manual a jugadores recientes -->
        <button type="button"
                onclick="document.getElementById('modalBienvenidaManual').style.display='flex'"
                style="margin-top:.25rem;width:100%;padding:.5rem .75rem;background:linear-gradient(135deg,#fef3c7,#fde68a);color:#92400e;border:1px solid #fcd34d;border-radius:8px;font-size:.78rem;font-weight:700;cursor:pointer">
          📤 Enviar a jugadores recientes (<?= count($jugadores_recientes) ?>)
        </button>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <!-- Modal: Enviar bienvenida a jugadores recientes -->
  <div id="modalBienvenidaManual" style="display:none;position:fixed;inset:0;background:rgba(15,23,42,.55);z-index:99999;align-items:center;justify-content:center;padding:1rem;backdrop-filter:blur(4px)">
    <div style="background:#fff;border-radius:18px;max-width:560px;width:100%;max-height:88vh;display:flex;flex-direction:column;box-shadow:0 25px 60px rgba(15,23,42,.3);overflow:hidden">
      <div style="padding:1.25rem 1.5rem;border-bottom:1px solid #e2e8f0;display:flex;justify-content:space-between;align-items:center;background:linear-gradient(135deg,#fef3c7,#fde68a)">
        <div>
          <h3 style="font-family:var(--font-head);font-size:1rem;text-transform:uppercase;color:#92400e;margin:0">📤 Enviar bienvenida manualmente</h3>
          <p style="font-size:.78rem;color:#78350f;margin:.2rem 0 0">Últimas 12 horas — selecciona a quién enviarle el correo de bienvenida.</p>
        </div>
        <button onclick="document.getElementById('modalBienvenidaManual').style.display='none'" style="background:transparent;border:none;font-size:1.6rem;color:#92400e;cursor:pointer;line-height:1">×</button>
      </div>

      <?php if (empty($jugadores_recientes)): ?>
        <div style="padding:2rem;text-align:center;color:var(--gray-400)">
          <div style="font-size:2.5rem;margin-bottom:.5rem">📭</div>
          <p style="margin:0;font-weight:600">No hay jugadores registrados en las últimas 12 horas.</p>
        </div>
      <?php else: ?>
        <form method="POST" id="formBienvenidaManual" style="display:flex;flex-direction:column;flex:1;min-height:0">
          <input type="hidden" name="action" value="enviar_bienvenida_manual">

          <div style="padding:.75rem 1.5rem;border-bottom:1px solid #e2e8f0;display:flex;align-items:center;gap:.75rem;flex-wrap:wrap;background:#f8fafc">
            <label style="display:flex;align-items:center;gap:.5rem;cursor:pointer;font-size:.82rem;font-weight:700;color:var(--navy)">
              <input type="checkbox" id="bvSelectAll" onchange="bvToggleAll(this)" style="width:18px;height:18px;cursor:pointer;accent-color:var(--navy)">
              Seleccionar todos
            </label>
            <span id="bvCount" style="margin-left:auto;font-size:.78rem;color:var(--gray-500)">0 seleccionados</span>
          </div>

          <div style="flex:1;overflow-y:auto;padding:.25rem 0">
            <?php foreach ($jugadores_recientes as $jr):
              $minutos = max(1, (int) ((time() - strtotime($jr['created_at'])) / 60));
              if ($minutos < 60) {
                  $tiempo_lbl = "hace {$minutos} min";
              } else {
                  $horas = (int) ($minutos / 60);
                  $tiempo_lbl = $horas === 1 ? 'hace 1 hora' : "hace {$horas} horas";
              }
            ?>
              <label class="bv-row" style="display:flex;align-items:center;gap:.75rem;padding:.65rem 1.5rem;border-bottom:1px solid #f1f5f9;cursor:pointer;transition:background .15s">
                <input type="checkbox" name="jugador_ids[]" value="<?= (int)$jr['id'] ?>"
                       class="bv-check" onchange="bvUpdateCount()"
                       style="width:18px;height:18px;cursor:pointer;accent-color:var(--navy);flex-shrink:0">
                <div style="flex:1;min-width:0">
                  <div style="font-weight:700;font-size:.88rem;color:var(--navy);overflow:hidden;text-overflow:ellipsis;white-space:nowrap">
                    <?= epl_h($jr['nombre'].' '.$jr['apellido']) ?>
                  </div>
                  <div style="font-size:.75rem;color:var(--gray-500);overflow:hidden;text-overflow:ellipsis;white-space:nowrap">
                    <?= epl_h($jr['email']) ?>
                  </div>
                </div>
                <span style="font-size:.7rem;font-weight:600;color:#92400e;background:#fef3c7;padding:.2rem .55rem;border-radius:10px;white-space:nowrap;flex-shrink:0"><?= $tiempo_lbl ?></span>
              </label>
            <?php endforeach; ?>
          </div>

          <div style="padding:1rem 1.5rem;border-top:1px solid #e2e8f0;display:flex;gap:.65rem;background:#fff">
            <button type="button" onclick="document.getElementById('modalBienvenidaManual').style.display='none'"
                    style="flex:1;padding:.75rem 1rem;background:var(--gray-100);color:var(--gray-600);border:none;border-radius:10px;font-weight:700;font-size:.85rem;cursor:pointer;text-transform:uppercase;letter-spacing:.05em">
              Cancelar
            </button>
            <button type="submit" id="bvBtnEnviar" disabled
                    style="flex:2;padding:.75rem 1rem;background:linear-gradient(135deg,#f59e0b,#d97706);color:#fff;border:none;border-radius:10px;font-weight:700;font-size:.85rem;cursor:pointer;text-transform:uppercase;letter-spacing:.05em;box-shadow:0 4px 12px rgba(245,158,11,.3);opacity:.5">
              📤 Enviar bienvenida
            </button>
          </div>
        </form>
      <?php endif; ?>
    </div>
  </div>

  <script>
    function bvToggleAll(chk) {
      document.querySelectorAll('.bv-check').forEach(c => { c.checked = chk.checked; });
      bvUpdateCount();
    }
    function bvUpdateCount() {
      const checked = document.querySelectorAll('.bv-check:checked').length;
      const cnt = document.getElementById('bvCount');
      const btn = document.getElementById('bvBtnEnviar');
      if (cnt) cnt.textContent = checked + ' seleccionado' + (checked === 1 ? '' : 's');
      if (btn) { btn.disabled = checked === 0; btn.style.opacity = checked === 0 ? .5 : 1; }
      const all = document.getElementById('bvSelectAll');
      const total = document.querySelectorAll('.bv-check').length;
      if (all) { all.checked = (checked > 0 && checked === total); all.indeterminate = (checked > 0 && checked < total); }
    }
    document.querySelectorAll('.bv-row').forEach(r => {
      r.addEventListener('mouseenter', () => r.style.background = '#fffbeb');
      r.addEventListener('mouseleave', () => r.style.background = '');
    });
    var _frm = document.getElementById('formBienvenidaManual');
    if (_frm) _frm.addEventListener('submit', function(e) {
      const n = document.querySelectorAll('.bv-check:checked').length;
      if (n === 0) { e.preventDefault(); return; }
      if (!confirm('Vas a enviar el correo de bienvenida a ' + n + ' jugador(es). ¿Continuar?')) {
        e.preventDefault();
      }
    });
  </script>

  <!-- Cron info -->
  <details style="max-width:640px">
    <summary style="cursor:pointer;font-size:.83rem;font-weight:700;color:#1C2F48;padding:.6rem .9rem;background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;list-style:none">
      ⏰ Configurar cron en el servidor
    </summary>
    <div style="background:#f8fafc;border:1px solid #e2e8f0;border-top:none;border-radius:0 0 8px 8px;padding:.9rem 1.1rem">
      <p style="font-size:.82rem;color:#334155;margin:0 0 .5rem">Agrega al crontab con <code>crontab -e</code>:</p>
      <div style="background:#0f172a;border-radius:7px;padding:.7rem 1rem;font-family:monospace;font-size:.79rem;color:#a3e635;word-break:break-all;margin-bottom:.5rem">
        0 9 * * * php <?= epl_h(dirname(__DIR__)) ?>/cron/cron_cumpleanos.php
      </div>
    </div>
  </details>

  <?php else: ?>
  <!-- ══════════════ EDITOR ══════════════ -->
  <div style="margin-bottom:2rem">

    <div style="display:flex;align-items:center;gap:1rem;margin-bottom:1.5rem;flex-wrap:wrap">
      <a href="automatizaciones.php" style="color:#94a3b8;font-size:.83rem;text-decoration:none">← Volver</a>
      <h2 style="font-size:1.05rem;font-weight:800;color:#1C2F48;margin:0">
        <?= $editing ? 'Editar: '.epl_h($editing['nombre']) : 'Nueva automatización' ?>
      </h2>
    </div>

    <form method="POST" id="autoForm">
      <input type="hidden" name="action"       value="<?= $editing ? 'actualizar' : 'crear' ?>">
      <input type="hidden" name="trigger_tipo" id="triggerVal" value="<?= epl_h($editing_tipo) ?>">
      <input type="hidden" name="destinatario" id="destVal"    value="<?= epl_h($editing_dest) ?>">
      <?php if ($editing): ?>
        <input type="hidden" name="id" value="<?= (int)$editing['id'] ?>">
      <?php endif; ?>

      <!-- 1 · Nombre -->
      <div style="margin-bottom:1.75rem">
        <div class="section-step"><span class="step-num">1</span> Nombre</div>
        <input type="text" name="nombre" required placeholder="Ej: Bienvenida temporada 2026"
               value="<?= epl_h($editing['nombre'] ?? '') ?>"
               style="width:100%;max-width:480px;padding:.65rem .9rem;border:1.5px solid #e2e8f0;border-radius:10px;font-size:.9rem;font-weight:600;color:#1C2F48;box-sizing:border-box">
      </div>

      <!-- 2 · Trigger -->
      <div style="margin-bottom:1.75rem">
        <div class="section-step"><span class="step-num">2</span> ¿Cuándo se activa?</div>
        <div class="trigger-grid" id="triggerGrid">
          <?php foreach ($triggers as $tkey => $t): ?>
          <div class="trigger-opt <?= $editing_tipo === $tkey ? 'sel' : '' ?>"
               data-key="<?= epl_h($tkey) ?>"
               data-color="<?= epl_h($t['color']) ?>"
               data-bg="<?= epl_h($t['bg']) ?>">
            <div class="t-icon"><?= $t['icon'] ?></div>
            <div class="t-label"><?= $t['label'] ?></div>
            <div class="t-campo">🔑 <?= $t['campo'] ?></div>
            <div class="t-when"><?= $t['cuando'] ?></div>
          </div>
          <?php endforeach; ?>
        </div>
        <div id="triggerError" style="display:none;color:#dc2626;font-size:.8rem;margin-top:-.75rem;margin-bottom:.75rem">Selecciona un tipo de disparo.</div>
      </div>

      <!-- 3 · Destinatario -->
      <div style="margin-bottom:1.75rem">
        <div class="section-step"><span class="step-num">3</span> ¿A quién se envía?</div>
        <div class="dest-grid" id="destGrid">
          <?php foreach ($dest_labels as $dkey => $d): ?>
          <div class="dest-opt <?= $editing_dest === $dkey ? 'sel' : '' ?>" data-key="<?= epl_h($dkey) ?>">
            <span style="font-size:1.15rem"><?= $d['icon'] ?></span>
            <?= $d['label'] ?>
          </div>
          <?php endforeach; ?>
        </div>
        <div id="destError" style="display:none;color:#dc2626;font-size:.8rem;margin-top:-.75rem;margin-bottom:.75rem">Selecciona el destinatario.</div>
      </div>

      <!-- 4 · Activar -->
      <div style="margin-bottom:1.75rem">
        <div class="section-step"><span class="step-num">4</span> Estado</div>
        <div style="display:inline-flex;align-items:center;gap:.85rem;background:#f8fafc;border:1.5px solid #e2e8f0;border-radius:10px;padding:.75rem 1.1rem">
          <label class="sw">
            <input type="checkbox" name="activo" value="1" id="activoCheck" <?= !empty($editing['activo']) ? 'checked' : '' ?>>
            <span class="sw-slider"></span>
          </label>
          <div>
            <div style="font-weight:700;font-size:.88rem;color:#1C2F48">Activar esta automatización</div>
            <div style="font-size:.74rem;color:#94a3b8">Si hay otra activa del mismo tipo y destinatario, se desactivará</div>
          </div>
        </div>
      </div>

      <!-- 5 · Asunto -->
      <div style="margin-bottom:1.25rem">
        <div class="section-step"><span class="step-num">5</span> Asunto del correo</div>
        <input type="text" name="asunto" id="asuntoInput" required
               placeholder="Ej: ¡Bienvenido/a a Elite Padel League!"
               value="<?= epl_h($editing['asunto'] ?? '') ?>"
               style="width:100%;padding:.65rem .9rem;border:1.5px solid #e2e8f0;border-radius:10px;font-size:.88rem;box-sizing:border-box">
      </div>

      <!-- 6 · Cuerpo + Preview -->
      <div style="margin-bottom:1.5rem">
        <div class="section-step"><span class="step-num">6</span> Contenido y vista previa</div>
        <div style="background:#eff6ff;border:1px solid #bfdbfe;border-radius:8px;padding:.6rem .9rem;margin-bottom:.9rem;font-size:.78rem;color:#1e3a5f">
          <strong>Variables:</strong>
          <span class="vars-chip">{{nombre}}</span>
          <span class="vars-chip">{{apellido}}</span>
          <span class="vars-chip">{{email}}</span>
          <span style="color:#64748b;margin-left:.3rem">— se reemplazan por los datos del jugador al enviarse</span>
        </div>
        <div class="ep-wrap">
          <div>
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:.4rem">
              <span style="font-size:.8rem;font-weight:700;color:#1C2F48">HTML del correo</span>
              <button type="button" id="btnPlantilla" style="font-size:.73rem;font-weight:700;color:#6366f1;background:none;border:none;cursor:pointer;text-decoration:underline;padding:0">Insertar plantilla base</button>
            </div>
            <textarea name="cuerpo" id="cuerpoInput" rows="20"
                      placeholder="<p>Hola <strong>{{nombre}}</strong>,</p>"
                      style="width:100%;padding:.7rem .85rem;border:1.5px solid #e2e8f0;border-radius:10px;font-size:.8rem;font-family:monospace;resize:vertical;box-sizing:border-box"><?= epl_h($editing['cuerpo'] ?? '') ?></textarea>
          </div>
          <div>
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:.4rem">
              <span style="font-size:.8rem;font-weight:700;color:#1C2F48">Vista previa</span>
              <span style="font-size:.72rem;color:#94a3b8">Se actualiza al escribir</span>
            </div>
            <iframe id="previewFrame" class="preview-iframe" title="Vista previa del correo"></iframe>
          </div>
        </div>
      </div>

      <!-- Botones -->
      <div style="display:flex;flex-wrap:wrap;gap:.65rem;padding-top:.75rem;border-top:1px solid #f1f5f9">
        <button type="submit" class="btn btn-primary" style="min-width:180px">
          <?= $editing ? '💾 Guardar cambios' : '✅ Crear automatización' ?>
        </button>
        <a href="automatizaciones.php" class="btn" style="background:#f1f5f9;color:#1C2F48;font-weight:700">Cancelar</a>
      </div>
    </form>
  </div>
  <?php endif; ?>

  </div><!-- .auto-page -->
  </main>
</div>

<script>
(function () {
  // ── Trigger cards ──────────────────────────────────────────────────────────
  var triggerVal = document.getElementById('triggerVal');
  var triggerErr = document.getElementById('triggerError');

  document.querySelectorAll('#triggerGrid .trigger-opt').forEach(function (el) {
    // Aplicar colores al ya-seleccionado en carga
    if (el.classList.contains('sel')) applyTriggerStyle(el);

    el.addEventListener('click', function () {
      document.querySelectorAll('#triggerGrid .trigger-opt').forEach(function (e) {
        e.classList.remove('sel');
        e.style.borderColor = '';
        e.style.background  = '';
      });
      el.classList.add('sel');
      applyTriggerStyle(el);
      triggerVal.value = el.dataset.key;
      if (triggerErr) triggerErr.style.display = 'none';
    });
  });

  function applyTriggerStyle(el) {
    el.style.borderColor = el.dataset.color || '';
    el.style.background  = el.dataset.bg    || '';
  }

  // ── Dest cards ─────────────────────────────────────────────────────────────
  var destVal = document.getElementById('destVal');
  var destErr = document.getElementById('destError');

  document.querySelectorAll('#destGrid .dest-opt').forEach(function (el) {
    el.addEventListener('click', function () {
      document.querySelectorAll('#destGrid .dest-opt').forEach(function (e) {
        e.classList.remove('sel');
      });
      el.classList.add('sel');
      destVal.value = el.dataset.key;
      if (destErr) destErr.style.display = 'none';
    });
  });

  // ── Validación ─────────────────────────────────────────────────────────────
  var form = document.getElementById('autoForm');
  if (form) {
    form.addEventListener('submit', function (e) {
      var ok = true;
      if (!triggerVal || !triggerVal.value) {
        if (triggerErr) triggerErr.style.display = 'block';
        ok = false;
      }
      if (!destVal || !destVal.value) {
        if (destErr) destErr.style.display = 'block';
        ok = false;
      }
      if (!ok) e.preventDefault();
    });
  }

  // ── Vista previa ───────────────────────────────────────────────────────────
  var APP = <?= json_encode($app_name, JSON_UNESCAPED_UNICODE) ?>;

  function buildEmail(cuerpo) {
    var c = (cuerpo || '')
      .replace(/\{\{nombre\}\}/g,   'Pablo')
      .replace(/\{\{apellido\}\}/g, 'Romero')
      .replace(/\{\{email\}\}/g,    'pablo@epleague.cl');
    var empty = c.trim() === ''
      ? '<p style="color:#94a3b8;font-size:.88rem;text-align:center;padding:2rem 0">El cuerpo aparecerá aquí…</p>'
      : c;
    return '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8">'
      + '<style>body{margin:0;padding:0;background:#f1f5f9;font-family:Arial,sans-serif}</style>'
      + '</head><body>'
      + '<table width="100%" cellpadding="0" cellspacing="0" style="background:#f1f5f9;padding:20px 10px"><tr><td align="center">'
      + '<table width="100%" style="max-width:560px;background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 2px 12px rgba(0,0,0,.08)">'
      + '<tr><td style="background:#1C2F48;padding:18px 22px;text-align:center">'
      + '<span style="font-size:17px;font-weight:800;color:#C9A762;text-transform:uppercase;letter-spacing:.08em">' + APP + '</span></td></tr>'
      + '<tr><td style="padding:26px 22px">' + empty + '</td></tr>'
      + '<tr><td style="background:#f8fafc;padding:14px 22px;text-align:center;font-size:12px;color:#94a3b8">Mensaje automático — no respondas a este correo.</td></tr>'
      + '</table></td></tr></table></body></html>';
  }

  function refreshPreview() {
    var frame = document.getElementById('previewFrame');
    if (!frame) return;
    var cuerpo = (document.getElementById('cuerpoInput') || {}).value || '';
    frame.srcdoc = buildEmail(cuerpo);
  }

  var cuerpoTA = document.getElementById('cuerpoInput');
  if (cuerpoTA) cuerpoTA.addEventListener('input', refreshPreview);

  // ── Plantilla base ─────────────────────────────────────────────────────────
  var btnP = document.getElementById('btnPlantilla');
  if (btnP) {
    btnP.addEventListener('click', function () {
      if (cuerpoTA && cuerpoTA.value.trim() && !confirm('¿Reemplazar el contenido actual?')) return;
      if (cuerpoTA) {
        cuerpoTA.value = [
          '<p style="margin:0 0 1rem;color:#334155;line-height:1.6">Hola <strong>{{nombre}}</strong>,</p>',
          '<p style="margin:0 0 1rem;color:#334155;line-height:1.6">Tu mensaje principal aquí.</p>',
          '<p style="margin:0 0 1.5rem;color:#334155;line-height:1.6">Un párrafo adicional si lo necesitas.</p>',
          '<p style="margin:0">',
          '  <a href="https://epleague.cl/dashboard.php"',
          '     style="display:inline-block;background:#1C2F48;color:#C9A762;font-weight:700;',
          '            text-decoration:none;padding:.7rem 1.4rem;border-radius:8px">',
          '    Ir a mi dashboard',
          '  </a>',
          '</p>',
        ].join('\n');
        refreshPreview();
      }
    });
  }

  // Init
  refreshPreview();
})();
</script>

<?php require_once '../includes/footer.php'; ?>
