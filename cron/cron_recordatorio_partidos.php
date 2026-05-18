<?php
/**
 * Cron de recordatorios de partidos — ejecutar cada hora.
 * Crontab: 0 * * * * php /home/elitepadel/htdocs/padel.207.246.68.77.nip.io/cron/cron_recordatorio_partidos.php
 *
 * Envía push notification a los jugadores con partidos en:
 *   - 24 horas
 *   - 12 horas
 *   - 3 horas
 */
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit('Solo CLI.');
}

define('EPL_CRON', true);
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/web_push.php';

$db  = epl_db();
$now = new DateTimeImmutable('now', new DateTimeZone('America/Santiago'));
echo "[{$now->format('Y-m-d H:i:s')}] Cron recordatorio partidos\n";

// Ventanas de aviso: [horas_antes, tolerancia_minutos, etiqueta]
$ventanas = [
    [24, 30, '24 horas'],
    [12, 30, '12 horas'],
    [ 3, 30,  '3 horas'],
];

$enviados_total = 0;

foreach ($ventanas as [$horas, $tolerancia, $etiqueta]) {
    // Rango: NOW + Xh ± tolerancia minutos
    $desde = $now->modify("+{$horas} hours -{$tolerancia} minutes")->format('Y-m-d H:i:s');
    $hasta  = $now->modify("+{$horas} hours +{$tolerancia} minutes")->format('Y-m-d H:i:s');

    // Partidos pendientes o reprogramados dentro de la ventana
    $st = $db->prepare("
        SELECT
            p.id,
            p.fecha_programada,
            el.nombre    AS local_nombre,
            ev.nombre    AS visitante_nombre,
            el.jugador1_id AS jl1_id,
            el.jugador2_id AS jl2_id,
            ev.jugador1_id AS jv1_id,
            ev.jugador2_id AS jv2_id
        FROM partidos p
        JOIN equipos el ON el.id = p.equipo_local_id
        JOIN equipos ev ON ev.id = p.equipo_visitante_id
        WHERE p.estado IN ('pendiente','reprogramado')
          AND p.fecha_programada BETWEEN ? AND ?
    ");
    $st->execute([$desde, $hasta]);
    $partidos = $st->fetchAll(PDO::FETCH_ASSOC);

    if (empty($partidos)) {
        echo "  [{$etiqueta}] Sin partidos en ventana.\n";
        continue;
    }

    echo "  [{$etiqueta}] " . count($partidos) . " partido(s).\n";

    foreach ($partidos as $p) {
        $fecha_fmt  = (new DateTimeImmutable($p['fecha_programada']))->format('d/m H:i');
        $vs         = trim($p['local_nombre']) . ' vs ' . trim($p['visitante_nombre']);
        $title      = "⏰ Partido en {$etiqueta}";
        $body       = "{$vs} — {$fecha_fmt}h";
        $url        = '/dashboard.php';

        // Recolectar IDs únicos de jugadores del partido
        $jugador_ids = array_unique(array_filter([
            $p['jl1_id'], $p['jl2_id'],
            $p['jv1_id'], $p['jv2_id'],
        ]));

        foreach ($jugador_ids as $jid) {
            // Evitar duplicados: guardar en BD que ya enviamos este aviso
            $clave = "recordatorio_{$p['id']}_{$horas}h";
            $check = $db->prepare(
                "SELECT 1 FROM notificaciones
                 WHERE jugador_id=? AND tipo='sistema' AND mensaje LIKE ?
                 AND created_at > DATE_SUB(NOW(), INTERVAL 2 HOUR)
                 LIMIT 1"
            );
            $check->execute([$jid, "%{$clave}%"]);
            if ($check->fetchColumn()) {
                continue; // ya enviado
            }

            // Notificación in-app
            epl_notif_crear($jid, $title, $body . " [{$clave}]", $url);

            // Push web
            $enviados = epl_push_jugador($jid, $title, $body, $url);
            $enviados_total += $enviados;

            echo "    -> Jugador #{$jid}: {$vs} ({$enviados} push)\n";
        }
    }
}

echo "[" . date('Y-m-d H:i:s') . "] Fin. Push enviados: {$enviados_total}\n";
exit(0);
