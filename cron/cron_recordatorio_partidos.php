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
        $titulo_email  = "Recordatorio: tu partido es en {$etiqueta}";
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
            epl_mail_recordatorio_partido(
                (int)$jid, $titulo_email, $local, $visitante,
                $dia, $hora, $cancha, $liga, $jornada, $etiqueta
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


// ─────────────────────────────────────────────────────────────
// Email bonito con tabla de datos del partido
// ─────────────────────────────────────────────────────────────
function epl_mail_recordatorio_partido(
    int $jugador_id,
    string $asunto,
    string $local,
    string $visitante,
    string $dia,
    string $hora,
    string $cancha,
    string $liga,
    string $jornada,
    string $etiqueta
): void {
    if (!epl_smtp_habilitado()) return;

    $db = epl_db();
    $j  = $db->prepare('SELECT email, nombre, apellido FROM jugadores WHERE id = ?');
    $j->execute([$jugador_id]);
    $jug = $j->fetch(PDO::FETCH_ASSOC);
    if (!$jug || empty($jug['email'])) return;

    $nombre = htmlspecialchars(trim($jug['nombre'] . ' ' . $jug['apellido']), ENT_QUOTES, 'UTF-8');
    $link   = epl_url('dashboard.php');

    $fila = fn(string $icon, string $label, string $valor) =>
        '<tr>
          <td style="padding:10px 14px;border-bottom:1px solid #f1f5f9;width:36px;font-size:18px;text-align:center">' . $icon . '</td>
          <td style="padding:10px 14px;border-bottom:1px solid #f1f5f9;font-size:12px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.05em;white-space:nowrap">' . $label . '</td>
          <td style="padding:10px 14px;border-bottom:1px solid #f1f5f9;font-size:14px;font-weight:700;color:#1c2f48">' . htmlspecialchars($valor, ENT_QUOTES, 'UTF-8') . '</td>
        </tr>';

    $jornadaFila = $jornada ? $fila('🏆', 'Liga / Jornada', $liga . ($jornada ? " · {$jornada}" : '')) : $fila('🏆', 'Liga', $liga);

    $body = '
    <p style="margin:0 0 1.25rem;font-size:15px;color:#334155">Hola <strong>' . $nombre . '</strong>,</p>
    <p style="margin:0 0 1rem;font-size:14px;color:#64748b">Tu próximo partido es en <strong style="color:#1c2f48">' . htmlspecialchars($etiqueta, ENT_QUOTES) . '</strong>. Aquí están todos los detalles:</p>

    <!-- VS Header -->
    <table width="100%" cellpadding="0" cellspacing="0" style="background:linear-gradient(135deg,#1c2f48,#1a3a64);border-radius:12px;margin-bottom:1.25rem">
      <tr>
        <td style="padding:20px;text-align:center;width:45%;color:#fff;font-size:15px;font-weight:800;line-height:1.3">' . htmlspecialchars($local, ENT_QUOTES) . '</td>
        <td style="padding:20px;text-align:center;width:10%">
          <span style="background:#C9A762;color:#1c2f48;font-weight:900;font-size:13px;padding:4px 10px;border-radius:6px">VS</span>
        </td>
        <td style="padding:20px;text-align:center;width:45%;color:#fff;font-size:15px;font-weight:800;line-height:1.3">' . htmlspecialchars($visitante, ENT_QUOTES) . '</td>
      </tr>
    </table>

    <!-- Datos del partido -->
    <table width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #e2e8f0;border-radius:12px;overflow:hidden;margin-bottom:1.5rem">
      ' . $fila('📅', 'Fecha', $dia) . '
      ' . $fila('🕐', 'Hora', $hora . ' hrs') . '
      ' . $fila('🏟️', 'Cancha', $cancha) . '
      ' . $jornadaFila . '
    </table>

    <p style="margin:0 0 1.5rem;font-size:13px;color:#64748b;background:#f8fafc;border-radius:8px;padding:.75rem 1rem;border-left:3px solid #C9A762">
      💡 Recuerda llegar 10 minutos antes. Si no puedes jugar, reprograma con tiempo en la plataforma.
    </p>

    <p style="margin:0;text-align:center">
      <a href="' . $link . '" style="display:inline-block;background:#C9A762;color:#1c2f48;font-weight:900;font-size:13px;text-decoration:none;padding:.75rem 2rem;border-radius:8px;text-transform:uppercase;letter-spacing:.05em">Ver mis partidos</a>
    </p>';

    $html = epl_mail_plantilla($asunto, $body);
    epl_mail_enviar($jug['email'], $asunto . ' — Elite Padel League', $html, trim($jug['nombre'] . ' ' . $jug['apellido']));
}
