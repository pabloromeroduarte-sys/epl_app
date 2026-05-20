<?php
/**
 * Cron de recordatorios de partidos — ejecutar cada hora.
 * Crontab: 0 * * * * php /home/elitepadel/htdocs/padel.207.246.68.77.nip.io/cron/cron_recordatorio_partidos.php
 */
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit('Solo CLI.');
}

define('EPL_CRON', true);
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/web_push.php';
require_once __DIR__ . '/../includes/mail.php';

$db  = epl_db();
$now = new DateTimeImmutable('now', new DateTimeZone('America/Santiago'));
echo "[{$now->format('Y-m-d H:i:s')}] Cron recordatorio partidos\n";

// Ventanas: [horas_antes, tolerancia_minutos, etiqueta]
$ventanas = [
    [24, 30, '24 horas'],
    [12, 30, '12 horas'],
    [ 3, 30,  '3 horas'],
];

$enviados_total = 0;

foreach ($ventanas as [$horas, $tolerancia, $etiqueta]) {
    $desde = $now->modify("+{$horas} hours -{$tolerancia} minutes")->format('Y-m-d H:i:s');
    $hasta  = $now->modify("+{$horas} hours +{$tolerancia} minutes")->format('Y-m-d H:i:s');

    $st = $db->prepare("
        SELECT
            p.id,
            p.fecha_programada,
            p.cancha,
            p.jornada,
            el.nombre  AS local_nombre,
            ev.nombre  AS visitante_nombre,
            el.jugador1_id AS jl1_id,
            el.jugador2_id AS jl2_id,
            ev.jugador1_id AS jv1_id,
            ev.jugador2_id AS jv2_id,
            lg.nombre  AS liga_nombre
        FROM partidos p
        JOIN equipos el  ON el.id = p.equipo_local_id
        JOIN equipos ev  ON ev.id = p.equipo_visitante_id
        LEFT JOIN ligas lg ON lg.id = p.liga_id
        WHERE p.estado IN ('pendiente','reprogramado')
          AND p.fecha_programada BETWEEN ? AND ?
    ");
    $st->execute([$desde, $hasta]);
    $partidos = $st->fetchAll(PDO::FETCH_ASSOC);

    if (empty($partidos)) {
        echo "  [{$etiqueta}] Sin partidos.\n";
        continue;
    }

    echo "  [{$etiqueta}] " . count($partidos) . " partido(s).\n";

    foreach ($partidos as $p) {
        $fecha_dt  = new DateTimeImmutable($p['fecha_programada'], new DateTimeZone('America/Santiago'));
        $dia       = $fecha_dt->format('d/m/Y');
        $hora      = $fecha_dt->format('H:i');
        $cancha    = !empty($p['cancha']) ? trim($p['cancha']) : 'Por confirmar';
        $liga      = !empty($p['liga_nombre']) ? trim($p['liga_nombre']) : 'Elite Padel League';
        $jornada   = !empty($p['jornada']) ? 'Jornada ' . $p['jornada'] : '';
        $local     = trim($p['local_nombre']);
        $visitante = trim($p['visitante_nombre']);

        $titulo_notif  = "⏰ Tu partido es en {$etiqueta}";
        $titulo_email  = epl_mail_asunto("⏰ Recordatorio: partido en {$etiqueta}", $local, $visitante, $p['jornada'] ?? null);
        $push_body     = "{$local} vs {$visitante} — {$dia} {$hora}h · {$cancha}";

        $jugador_ids = array_unique(array_filter([
            $p['jl1_id'], $p['jl2_id'],
            $p['jv1_id'], $p['jv2_id'],
        ]));

        foreach ($jugador_ids as $jid) {
            // Deduplicación sin ensuciar el contenido visible
            $check = $db->prepare(
                "SELECT 1 FROM notificaciones
                 WHERE jugador_id = ?
                   AND tipo = 'recordatorio'
                   AND mensaje LIKE ?
                   AND created_at > DATE_SUB(NOW(), INTERVAL 2 HOUR)
                 LIMIT 1"
            );
            $dedup_mark = "rec_{$p['id']}_{$horas}h";
            $check->execute([$jid, "%{$dedup_mark}%"]);
            if ($check->fetchColumn()) {
                echo "    -> Jugador #{$jid}: ya enviado.\n";
                continue;
            }

            // ── Notificación in-app (sin disparar email automático) ──
            $db->prepare(
                "INSERT INTO notificaciones (jugador_id, tipo, titulo, mensaje, url)
                 VALUES (?, 'recordatorio', ?, ?, ?)"
            )->execute([
                $jid,
                $titulo_notif,
                // Mensaje con dedup_mark al final — no visible en la app, solo para dedup
                "{$local} vs {$visitante} · {$dia} {$hora}h · {$cancha} [{$dedup_mark}]",
                '/dashboard.php',
            ]);

            // ── Email con tabla visual ──
            $filas_rec = array_values(array_filter([
                ['icon' => '📅', 'label' => 'Fecha',    'valor' => $dia],
                ['icon' => '🕐', 'label' => 'Hora',     'valor' => $hora . ' hrs'],
                ['icon' => '🏟️', 'label' => 'Cancha',   'valor' => $cancha],
                ['icon' => '🏆', 'label' => $jornada ? 'Liga / Jornada' : 'Liga',
                                 'valor' => $liga . ($jornada ? " · {$jornada}" : '')],
            ]));
            epl_mail_partido_visual(
                (int)$jid,
                $titulo_email,
                $local,
                $visitante,
                $filas_rec,
                "Tu próximo partido es en <strong>{$etiqueta}</strong>.",
                '💡 Recuerda llegar 10 minutos antes. Si no puedes jugar, reprograma con tiempo en la plataforma.',
                epl_url('dashboard.php')
            );

            // ── Push web ──
            $enviados = epl_push_jugador((int)$jid, $titulo_notif, $push_body, '/dashboard.php');
            $enviados_total += $enviados;

            echo "    -> Jugador #{$jid}: notif + email + {$enviados} push\n";
        }
    }
}

echo "[" . date('Y-m-d H:i:s') . "] Fin. Push enviados: {$enviados_total}\n";
exit(0);


