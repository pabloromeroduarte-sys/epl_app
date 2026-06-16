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

// Ventanas: [horas_antes, tolerancia_minutos, etiqueta] — configurables desde admin/alertas.php
$ventanas = epl_notif_recordatorio_ventanas();

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
            lg.nombre  AS liga_nombre,
            r.nombre  AS recinto_nombre,
            s.nombre  AS recinto_superior_nombre,
            ss.nombre AS recinto_abuelo_nombre
        FROM partidos p
        JOIN equipos el  ON el.id = p.equipo_local_id
        JOIN equipos ev  ON ev.id = p.equipo_visitante_id
        LEFT JOIN ligas lg ON lg.id = p.liga_id
        LEFT JOIN recintos r  ON r.id  = p.recinto_id
        LEFT JOIN recintos s  ON s.id  = r.superior_id
        LEFT JOIN recintos ss ON ss.id = s.superior_id
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
        $r_nombre = !empty($p['recinto_nombre']) ? trim($p['recinto_nombre']) : '';
        $r_sup    = !empty($p['recinto_superior_nombre']) ? trim($p['recinto_superior_nombre']) : '';
        $r_abu    = !empty($p['recinto_abuelo_nombre']) ? trim($p['recinto_abuelo_nombre']) : '';
        $c_campo  = !empty($p['cancha']) ? trim($p['cancha']) : '';

        if ($r_nombre) {
            if ($r_abu && $r_sup) {
                $cancha = "{$r_abu} {$r_sup} - {$r_nombre}";
            } elseif ($r_sup) {
                $cancha = "{$r_sup} - {$r_nombre}";
            } else {
                $cancha = $r_nombre;
            }
            if ($c_campo && !str_contains(strtolower($cancha), strtolower($c_campo))) {
                $cancha .= " ({$c_campo})";
            }
        } else {
            $cancha = $c_campo ?: 'Por confirmar';
        }
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
        } // fin foreach jugador_ids
    } // fin foreach partidos
} // fin foreach ventanas

// ── ALERTA DE PARTIDOS ATRASADOS (sin resultado para Administradores) ──
try {
    $atrasado_horas = epl_notif_tiempos()['atrasado_horas'];
    echo "[{$now->format('Y-m-d H:i:s')}] Ejecutando comprobación de partidos atrasados ({$atrasado_horas}h)...\n";
    $limite_tiempo = $now->modify("-{$atrasado_horas} hours")->format('Y-m-d H:i:s');
    
    // Obtener partidos que pasaron su fecha hace más de 12 horas y no tienen marcador
    $stAtrasados = $db->prepare("
        SELECT
            p.id,
            p.fecha_programada,
            p.jornada,
            el.nombre  AS local_nombre,
            ev.nombre  AS visitante_nombre,
            lg.nombre  AS liga_nombre
        FROM partidos p
        JOIN equipos el  ON el.id = p.equipo_local_id
        JOIN equipos ev  ON ev.id = p.equipo_visitante_id
        LEFT JOIN ligas lg ON lg.id = p.liga_id
        WHERE p.estado NOT IN ('jugado', 'walkover', 'no_presentado')
          AND p.fecha_programada IS NOT NULL
          AND DATE(p.fecha_programada) != '2026-12-31'
          AND p.fecha_programada <= ?
        ORDER BY p.fecha_programada ASC
    ");
    $stAtrasados->execute([$limite_tiempo]);
    $partidos_atrasados = $stAtrasados->fetchAll(PDO::FETCH_ASSOC);

    if (!empty($partidos_atrasados)) {
        echo "  [Atrasados] Se encontraron " . count($partidos_atrasados) . " partidos atrasados.\n";
        
        // Obtener administradores activos
        $stAdms = $db->query("SELECT id FROM jugadores WHERE rol = 'admin' AND estado = 'activo'");
        $admins = $stAdms->fetchAll(PDO::FETCH_COLUMN);

        if (!empty($admins)) {
            foreach ($partidos_atrasados as $pa) {
                $pa_id      = (int)$pa['id'];
                $pa_local   = trim($pa['local_nombre']);
                $pa_vis     = trim($pa['visitante_nombre']);
                $pa_liga    = trim($pa['liga_nombre'] ?? 'Elite Padel League');
                $pa_jornada = $pa['jornada'] ? "Jornada {$pa['jornada']}" : '';
                
                $pa_fecha_dt  = new DateTimeImmutable($pa['fecha_programada'], new DateTimeZone('America/Santiago'));
                $pa_fecha_str = $pa_fecha_dt->format('d/m/Y H:i');

                $titulo_adm  = "⚠️ Partido atrasado sin resultado ({$atrasado_horas}h)";
                $dedup_mark_adm = "[atrasado_12h_partido_{$pa_id}]"; // marca estable (no re-disparar al cambiar config)
                $mensaje_adm = "El partido {$pa_local} vs {$pa_vis} ({$pa_liga} " . ($pa_jornada ? "· {$pa_jornada}" : "") . ") programado para el {$pa_fecha_str} lleva más de {$atrasado_horas} horas sin marcador. {$dedup_mark_adm}";
                $url_adm     = epl_url("admin/partidos.php?search=" . urlencode($pa_local));

                foreach ($admins as $aid) {
                    $aid = (int)$aid;
                    
                    // Verificar si ya se le notificó a este admin
                    $check_adm = $db->prepare("
                        SELECT 1 FROM notificaciones 
                        WHERE jugador_id = ? 
                          AND tipo = 'admin_alerta' 
                          AND mensaje LIKE ? 
                        LIMIT 1
                    ");
                    $check_adm->execute([$aid, "%[atrasado_12h_partido_{$pa_id}]%"]);
                    
                    if ($check_adm->fetchColumn()) {
                        continue;
                    }

                    // Enviar notificaciones (BD, OneSignal Web Push, Email)
                    epl_notif_crear($aid, 'admin_alerta', $titulo_adm, $mensaje_adm, $url_adm, false);
                    echo "    -> Alerta enviada al Admin #{$aid} para el partido {$pa_local} vs {$pa_vis}\n";
                }
            }
        }
    } else {
        echo "  [Atrasados] Sin partidos atrasados.\n";
    }
} catch (Throwable $e) {
    echo "  [Atrasados] ERROR: " . $e->getMessage() . "\n";
    error_log("cron_recordatorio_partidos - alerta atrasados error: " . $e->getMessage());
}

echo "[" . date('Y-m-d H:i:s') . "] Fin. Push enviados: {$enviados_total}\n";
exit(0);


