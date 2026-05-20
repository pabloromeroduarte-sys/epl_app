<?php
/**
 * Cron de envío de emails desde la cola mail_queue.
 * Ejecutar cada minuto:
 *   Crontab: * * * * * php /home/elitepadel/htdocs/padel.207.246.68.77.nip.io/cron/cron_mail_sender.php
 *
 * - Procesa hasta 30 emails por ejecución.
 * - Máximo 3 intentos por email; luego marca como 'error'.
 * - Una sola conexión SMTP para todos los envíos del lote.
 */

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit('Solo CLI.');
}

define('EPL_CRON', true);
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/mail.php';

if (!epl_smtp_habilitado()) {
    echo "SMTP desactivado — nada que hacer.\n";
    exit(0);
}

$db  = epl_db();
$now = date('Y-m-d H:i:s');

// Asegurar que la tabla existe
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

// Tomar hasta 30 pendientes (excluye los que ya fallaron 3+ veces)
$st = $db->query("
    SELECT id, to_email, to_name, subject, body_html, intentos
    FROM mail_queue
    WHERE estado IN ('pendiente','enviando')
      AND intentos < 3
    ORDER BY created_at ASC
    LIMIT 30
");
$pendientes = $st->fetchAll(PDO::FETCH_ASSOC);

if (empty($pendientes)) {
    echo "[{$now}] Sin emails pendientes.\n";
    exit(0);
}

echo "[{$now}] " . count($pendientes) . " email(s) a enviar.\n";

$enviados = 0;
$errores  = 0;

foreach ($pendientes as $mail) {
    $id = (int)$mail['id'];

    // Marcar como 'enviando' para evitar procesamiento doble si el cron se superpone
    $db->prepare("UPDATE mail_queue SET estado='enviando', intentos=intentos+1 WHERE id=?")
       ->execute([$id]);

    $result = epl_mail_enviar_directo(
        $mail['to_email'],
        $mail['subject'],
        $mail['body_html'],
        $mail['to_name']
    );

    if ($result['ok']) {
        $db->prepare("UPDATE mail_queue SET estado='enviado', sent_at=NOW() WHERE id=?")
           ->execute([$id]);
        echo "  ✓ #{$id} → {$mail['to_email']}\n";
        $enviados++;
    } else {
        $err = $result['error'] ?? 'Error desconocido';
        // Si llegó a 3 intentos, marcar definitivamente como error
        $nuevo_estado = ((int)$mail['intentos'] + 1) >= 3 ? 'error' : 'pendiente';
        $db->prepare("UPDATE mail_queue SET estado=?, error_msg=? WHERE id=?")
           ->execute([$nuevo_estado, $err, $id]);
        echo "  ✗ #{$id} → {$mail['to_email']}: {$err}\n";
        $errores++;
    }

    // Pausa mínima entre emails para no saturar el relay
    usleep(100000); // 100ms
}

echo "[" . date('Y-m-d H:i:s') . "] Fin: {$enviados} enviados, {$errores} errores.\n";
exit(0);
