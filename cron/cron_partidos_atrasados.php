<?php
/**
 * Cron para alertar sobre partidos atrasados (más de 12 horas sin resultado).
 * Crontab recomendado (cada hora):
 * 0 * * * * php /home/elitepadel/htdocs/padel.207.246.68.77.nip.io/cron/cron_partidos_atrasados.php
 */

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit('Solo CLI.');
}

define('EPL_CRON', true);
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/web_push.php';
require_once __DIR__ . '/../includes/mail.php';

try {
    $db = epl_db();
    // Obtener la hora actual en la zona horaria del club (Santiago)
    $now = new DateTimeImmutable('now', new DateTimeZone('America/Santiago'));
    echo "[{$now->format('Y-m-d H:i:s')}] Inicio cron de partidos atrasados.\n";

    // Calcular el límite de 12 horas en el pasado
    $limite_tiempo = $now->modify('-12 hours')->format('Y-m-d H:i:s');
    
    // 1. Obtener partidos que pasaron su fecha hace más de 12 horas y no tienen marcador
    $st = $db->prepare("
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
    $st->execute([$limite_tiempo]);
    $partidos_atrasados = $st->fetchAll(PDO::FETCH_ASSOC);

    if (empty($partidos_atrasados)) {
        echo "No hay partidos atrasados de más de 12 horas sin resultado.\n";
        exit(0);
    }

    echo "Se encontraron " . count($partidos_atrasados) . " partidos atrasados.\n";

    // 2. Obtener todos los administradores activos del sistema
    $stAdms = $db->query("SELECT id FROM jugadores WHERE rol = 'admin' AND estado = 'activo'");
    $admins = $stAdms->fetchAll(PDO::FETCH_COLUMN);

    if (empty($admins)) {
        echo "No hay administradores activos registrados para notificar.\n";
        exit(0);
    }

    $notificaciones_enviadas = 0;

    // 3. Notificar a cada administrador
    foreach ($partidos_atrasados as $p) {
        $pid       = (int)$p['id'];
        $local     = trim($p['local_nombre']);
        $visitante = trim($p['visitante_nombre']);
        $liga      = trim($p['liga_nombre'] ?? 'Elite Padel League');
        $jornada   = $p['jornada'] ? "Jornada {$p['jornada']}" : '';
        
        $fecha_dt  = new DateTimeImmutable($p['fecha_programada'], new DateTimeZone('America/Santiago'));
        $fecha_str = $fecha_dt->format('d/m/Y H:i');

        $titulo  = "⚠️ Partido atrasado sin resultado (12h)";
        // Incluimos un marcador único al final del mensaje para la deduplicación
        $dedup_mark = "[atrasado_12h_partido_{$pid}]";
        $mensaje = "El partido {$local} vs {$visitante} ({$liga} " . ($jornada ? "· {$jornada}" : "") . ") programado para el {$fecha_str} lleva más de 12 horas sin marcador. {$dedup_mark}";
        $url     = epl_url("admin/partidos.php?search=" . urlencode($local));

        foreach ($admins as $aid) {
            $aid = (int)$aid;

            // Verificar si este admin ya recibió esta alerta específica
            $check = $db->prepare("
                SELECT 1 FROM notificaciones 
                WHERE jugador_id = ? 
                  AND tipo = 'admin_alerta' 
                  AND mensaje LIKE ? 
                LIMIT 1
            ");
            $check->execute([$aid, "%[atrasado_12h_partido_{$pid}]%"]);
            
            if ($check->fetchColumn()) {
                // Ya se le notificó a este admin sobre este partido
                continue;
            }

            // Crear notificación (esto registra en BD, envía OneSignal Web Push y correo)
            epl_notif_crear($aid, 'admin_alerta', $titulo, $mensaje, $url, false);
            $notificaciones_enviadas++;
            echo "  -> Alerta enviada al Admin #{$aid} para el partido {$local} vs {$visitante}\n";
        }
    }

    echo "Cron completado. Alertas enviadas: {$notificaciones_enviadas}\n";
    exit(0);

} catch (Throwable $e) {
    echo "ERROR en cron: " . $e->getMessage() . "\n";
    error_log("cron_partidos_atrasados error: " . $e->getMessage());
    exit(1);
}
