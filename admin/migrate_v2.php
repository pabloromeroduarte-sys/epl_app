<?php
require_once '../includes/auth.php';
require_once '../includes/functions.php';
epl_require_admin();

$db = epl_db();
$results = [];

// ── 1. Crear tabla mail_queue ────────────────────────────────────────────────
try {
    $db->exec("CREATE TABLE IF NOT EXISTS `mail_queue` (
        `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `to_email`   VARCHAR(255) NOT NULL,
        `to_name`    VARCHAR(150) DEFAULT NULL,
        `subject`    VARCHAR(250) NOT NULL,
        `body_html`  MEDIUMTEXT  NOT NULL,
        `estado`     ENUM('pendiente','enviando','enviado','error') NOT NULL DEFAULT 'pendiente',
        `intentos`   TINYINT UNSIGNED NOT NULL DEFAULT 0,
        `error_msg`  TEXT DEFAULT NULL,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `sent_at`    DATETIME DEFAULT NULL,
        INDEX idx_estado_created (`estado`, `created_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $results[] = ['tabla' => 'mail_queue', 'accion' => 'ok', 'msg' => 'Tabla creada (o ya existía)'];
} catch (Throwable $e) {
    $results[] = ['tabla' => 'mail_queue', 'accion' => 'error', 'msg' => $e->getMessage()];
}

// ── 2. Crear tabla epl_remember_tokens ───────────────────────────────────────
try {
    $db->exec("CREATE TABLE IF NOT EXISTS `epl_remember_tokens` (
        `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `jugador_id`  INT UNSIGNED NOT NULL,
        `token_hash`  CHAR(64) NOT NULL,
        `expires_at`  DATETIME NOT NULL,
        `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY `uk_token` (`token_hash`),
        INDEX `idx_jugador` (`jugador_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $results[] = ['tabla' => 'epl_remember_tokens', 'accion' => 'ok', 'msg' => 'Tabla creada (o ya existía)'];
} catch (Throwable $e) {
    $results[] = ['tabla' => 'epl_remember_tokens', 'accion' => 'error', 'msg' => $e->getMessage()];
}

// ── 3. Índices en partidos ───────────────────────────────────────────────────
function idx_existe(PDO $db, string $tabla, string $idx): bool {
    $st = $db->prepare("SELECT 1 FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ? LIMIT 1");
    $st->execute([$tabla, $idx]);
    return (bool)$st->fetchColumn();
}

$indices = [
    ['partidos',                   'idx_estado_fecha',   "ALTER TABLE partidos ADD INDEX idx_estado_fecha (estado, fecha_programada)"],
    ['partidos',                   'idx_liga_jornada',   "ALTER TABLE partidos ADD INDEX idx_liga_jornada (liga_id, jornada)"],
    ['partidos',                   'idx_fecha_prog',     "ALTER TABLE partidos ADD INDEX idx_fecha_prog (fecha_programada)"],
    ['notificaciones',             'idx_tipo_created',   "ALTER TABLE notificaciones ADD INDEX idx_tipo_created (tipo, created_at)"],
    ['solicitudes_reprogramacion', 'idx_partido_estado', "ALTER TABLE solicitudes_reprogramacion ADD INDEX idx_partido_estado (partido_id, estado)"],
];

foreach ($indices as [$tabla, $idx, $sql]) {
    if (idx_existe($db, $tabla, $idx)) {
        $results[] = ['tabla' => "{$tabla}.{$idx}", 'accion' => 'skip', 'msg' => 'Ya existía'];
        continue;
    }
    try {
        $db->exec($sql);
        $results[] = ['tabla' => "{$tabla}.{$idx}", 'accion' => 'ok', 'msg' => 'Índice creado'];
    } catch (Throwable $e) {
        $results[] = ['tabla' => "{$tabla}.{$idx}", 'accion' => 'error', 'msg' => $e->getMessage()];
    }
}

// ── 4. Columna resultado_ingresado_at en partidos ────────────────────────────
$st = $db->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'partidos' AND COLUMN_NAME = 'resultado_ingresado_at'");
if (!$st->fetchColumn()) {
    try {
        $db->exec("ALTER TABLE partidos ADD COLUMN resultado_ingresado_at DATETIME DEFAULT NULL AFTER ganador_id");
        $results[] = ['tabla' => 'partidos.resultado_ingresado_at', 'accion' => 'ok', 'msg' => 'Columna creada'];
    } catch (Throwable $e) {
        $results[] = ['tabla' => 'partidos.resultado_ingresado_at', 'accion' => 'error', 'msg' => $e->getMessage()];
    }
} else {
    $results[] = ['tabla' => 'partidos.resultado_ingresado_at', 'accion' => 'skip', 'msg' => 'Ya existía'];
}

// ── 5. Columna recinto_id en partidos ────────────────────────────────────────
$st2 = $db->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'partidos' AND COLUMN_NAME = 'recinto_id'");
if (!$st2->fetchColumn()) {
    try {
        $db->exec("ALTER TABLE partidos ADD COLUMN recinto_id INT UNSIGNED DEFAULT NULL");
        $results[] = ['tabla' => 'partidos.recinto_id', 'accion' => 'ok', 'msg' => 'Columna creada'];
    } catch (Throwable $e) {
        $results[] = ['tabla' => 'partidos.recinto_id', 'accion' => 'error', 'msg' => $e->getMessage()];
    }
} else {
    $results[] = ['tabla' => 'partidos.recinto_id', 'accion' => 'skip', 'msg' => 'Ya existía'];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Migración v2 — EPL</title>
  <style>
    body{font-family:monospace;background:#0f172a;color:#e2e8f0;padding:2rem;max-width:800px}
    h1{color:#f0b429;margin-bottom:1.5rem}
    table{border-collapse:collapse;width:100%;margin-top:1rem}
    th,td{padding:.4rem .8rem;text-align:left;border-bottom:1px solid #1e293b;font-size:.85rem}
    th{color:#94a3b8;font-size:.75rem;text-transform:uppercase}
    .ok{color:#22c55e;font-weight:700}
    .skip{color:#64748b}
    .error{color:#ef4444}
    .back{display:inline-block;margin-top:1.5rem;color:#f0b429;text-decoration:none;font-size:.85rem}
    .cron-info{background:#1e293b;border:1px solid #334155;border-radius:8px;padding:1rem;margin-top:1.5rem;font-size:.82rem;line-height:1.8}
    .cron-info code{color:#f0b429}
  </style>
</head>
<body>
<h1>⚙️ Migración v2 — EPL Performance & Features</h1>

<table>
  <tr><th>Objeto</th><th>Estado</th><th>Detalle</th></tr>
  <?php foreach ($results as $r):
    $cls = $r['accion'] === 'ok' ? 'ok' : ($r['accion'] === 'error' ? 'error' : 'skip');
    $icon = $r['accion'] === 'ok' ? '✅' : ($r['accion'] === 'error' ? '❌' : '—');
  ?>
  <tr>
    <td><?= htmlspecialchars($r['tabla']) ?></td>
    <td class="<?= $cls ?>"><?= $icon ?> <?= htmlspecialchars($r['accion']) ?></td>
    <td><?= htmlspecialchars($r['msg']) ?></td>
  </tr>
  <?php endforeach; ?>
</table>

<div class="cron-info">
  <strong style="color:#f0b429">📧 Cola de emails — agregar al crontab del servidor:</strong><br><br>
  <code># Enviar emails encolados cada minuto</code><br>
  <code>* * * * * php /home/elitepadel/htdocs/padel.207.246.68.77.nip.io/cron/cron_mail_sender.php >> /tmp/epl_mail.log 2>&1</code><br><br>
  <strong style="color:#f0b429">📅 Recordatorios — ya configurado (cada hora):</strong><br>
  <code>0 * * * * php /home/elitepadel/htdocs/padel.207.246.68.77.nip.io/cron/cron_recordatorio_partidos.php</code>
</div>

<a href="index.php" class="back">← Volver al admin</a>
</body>
</html>
