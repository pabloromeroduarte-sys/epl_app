<?php
/**
 * Cron de cumpleaños — ejecutar una vez al día.
 * Ejemplo crontab: 0 9 * * * php /ruta/al/proyecto/cron/cron_cumpleanos.php
 *
 * Seguridad: solo se puede ejecutar desde CLI.
 */
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit('Solo se puede ejecutar desde CLI.');
}

// Bootstrapping mínimo
define('EPL_CRON', true);
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/mail.php';
require_once __DIR__ . '/../includes/mail_automations.php';

$hoy = date('m-d'); // ej. "05-18"
echo "[" . date('Y-m-d H:i:s') . "] Cron cumpleaños — revisando fecha: " . date('d/m') . "\n";

$db = epl_db();

// Jugadores que cumplen años hoy y tienen email + estado activo
$st = $db->prepare(
    "SELECT id, nombre, apellido, email, fecha_nacimiento
     FROM jugadores
     WHERE estado = 'activo'
       AND email IS NOT NULL
       AND email <> ''
       AND fecha_nacimiento IS NOT NULL
       AND DATE_FORMAT(fecha_nacimiento, '%m-%d') = ?"
);
$st->execute([$hoy]);
$cumpleaneros = $st->fetchAll(PDO::FETCH_ASSOC);

if (empty($cumpleaneros)) {
    echo "Sin cumpleaños hoy.\n";
    exit(0);
}

echo count($cumpleaneros) . " jugador(es) con cumpleaños hoy.\n";

$admins_notificados = false; // notificamos una vez por jugador

foreach ($cumpleaneros as $jugador) {
    $nombre = trim($jugador['nombre'] . ' ' . $jugador['apellido']);
    echo " -> " . $nombre . " <" . $jugador['email'] . ">\n";

    // Correo al jugador
    epl_mail_cumpleanos_jugador($jugador);

    // Aviso a los admins
    epl_mail_cumpleanos_admins($jugador);
}

echo "[" . date('Y-m-d H:i:s') . "] Cron cumpleaños finalizado.\n";
exit(0);
