<?php
$page_title = 'Admin — Reprogramaciones';
$page_css   = 'repro'; // assets/css/repro.css — se carga desde el <head>
require_once '../includes/auth.php';
require_once '../includes/functions.php';
epl_require_admin();

$db = epl_db();
epl_ensure_partidos_columnas_originales();

// Auto-limpieza: Si un partido es pre-aprobado (fecha_original es NULL) y ya tiene cancha asignada,
// no requiere dar de baja nada ni solicitar cancha al club. Limpiar tokens/fechas de solicitud residuales.
$db->query("
    UPDATE partidos 
    SET baja_solicitada_at = NULL, 
        baja_confirmada_at = NULL,
        baja_token = NULL, 
        cancha_solicitada_at = NULL, 
        cancha_confirmada_at = NULL,
        cancha_token = NULL
    WHERE estado = 'reprogramado' 
      AND fecha_original IS NULL 
      AND recinto_id IS NOT NULL
");

// ── POST: acciones administrativas de reprogramación ─────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'eliminar_solicitud_sin_cambios') {
        $sid = (int)($_POST['solicitud_id'] ?? 0);
        $return_tab = ($_POST['return_tab'] ?? '') === 'informe' ? 'informe' : 'solicitudes';

        if ($sid) {
            $sol_st = $db->prepare("SELECT id FROM solicitudes_reprogramacion WHERE id=?");
            $sol_st->execute([$sid]);

            if ($sol_st->fetchColumn()) {
                // Esta acción solo elimina la solicitud administrativa. No modifica
                // estado, fecha, recinto ni ningún otro dato del partido.
                $db->prepare("DELETE FROM solicitudes_reprogramacion WHERE id=?")->execute([$sid]);
                $_SESSION['_epl_flash'] = [
                    'tipo' => 'ok',
                    'msg' => 'Gestión eliminada. El partido mantuvo su estado, fecha y recinto sin cambios.',
                ];
            } else {
                $_SESSION['_epl_flash'] = ['tipo' => 'error', 'msg' => 'La gestión ya no existe.'];
            }
        } else {
            $_SESSION['_epl_flash'] = ['tipo' => 'error', 'msg' => 'No se pudo identificar la gestión.'];
        }

        header('Location: dashboard_repro.php?tab=' . $return_tab); exit;
    }

    if ($action === 'rechazar_solicitud') {
        $sid = (int)($_POST['solicitud_id'] ?? 0);
        if ($sid) {
            // Obtener el partido_id y solicitante_id de la solicitud
            $sol_st = $db->prepare("SELECT partido_id, solicitante_id FROM solicitudes_reprogramacion WHERE id=?");
            $sol_st->execute([$sid]);
            $sol = $sol_st->fetch(PDO::FETCH_ASSOC);

            $db->prepare("UPDATE solicitudes_reprogramacion SET estado='rechazada' WHERE id=?")
               ->execute([$sid]);

            if ($sol) {
                $pid = (int)$sol['partido_id'];
                // Restaurar partido a pendiente con su fecha/cancha original
                $row = $db->prepare("SELECT fecha_original, recinto_original_id FROM partidos WHERE id=?");
                $row->execute([$pid]);
                $orig = $row->fetch(PDO::FETCH_ASSOC);

                $sets = "estado='pendiente', fecha_original=NULL, recinto_original_id=NULL";
                if ($orig && !empty($orig['fecha_original'])) {
                    $sets .= ", fecha_programada=" . $db->quote($orig['fecha_original']);
                }
                if ($orig && !empty($orig['recinto_original_id'])) {
                    $sets .= ", recinto_id=" . (int)$orig['recinto_original_id'];
                }
                $db->exec("UPDATE partidos SET $sets WHERE id=$pid");

                // Notificar al solicitante que fue rechazada (para que esté al tanto)
                if ($sol['solicitante_id']) {
                    try {
                        epl_notif_crear((int)$sol['solicitante_id'], 'reprogramacion',
                            '❌ Reprogramación no aprobada',
                            'Tu solicitud de reprogramación no fue aprobada. Coordina directamente con tu rival.',
                            epl_url('reprogramar.php#mis-reprogramaciones'), true
                        );
                    } catch (Throwable $e) {}
                }
            }

            if (session_status() === PHP_SESSION_NONE) session_start();
            $_SESSION['_epl_flash'] = ['tipo' => 'ok', 'msg' => 'Solicitud rechazada. Partido vuelto a Pendiente con su horario original.'];
        }
        header('Location: dashboard_repro.php?tab=solicitudes'); exit;
    }
}

// ────────────────────────────────────────────────────────────────────────
// QUERY: SOLO partidos REPROGRAMADOS (los pendientes con fecha futura
// son del calendario normal del torneo, no son problema del admin acá)
// ────────────────────────────────────────────────────────────────────────
epl_ensure_recintos_contactos();
$partidos_open = $db->query("
    SELECT p.id, p.jornada, p.nombre_fecha, p.fecha_programada, p.fecha_original,
           p.estado, p.alerta_admin, p.recinto_original_id, p.baja_token,
           p.baja_solicitada_at, p.baja_confirmada_at, p.baja_confirmada_por,
           p.recinto_id, p.cancha_confirmada_at,
           l.id AS liga_id, l.nombre AS liga_nombre,
           el.id AS local_id, el.nombre AS local_nombre,
           ev.id AS visitante_id, ev.nombre AS visitante_nombre,
           r.nombre AS recinto_nombre, rs.nombre AS recinto_sup,
           ro.nombre AS recinto_original_nombre, rop.nombre AS recinto_original_sup,
           ro.contacto1_nombre, ro.contacto1_telefono,
           ro.contacto2_nombre, ro.contacto2_telefono,
           ro.contacto3_nombre, ro.contacto3_telefono,
           sr.id AS solicitud_id, sr.motivo, sr.rival_no_responde, sr.created_at AS fecha_solicitud,
           sr.estado AS sol_estado, sr.mutuo_acuerdo AS sol_mutuo, sr.fecha_propuesta
    FROM partidos p
    JOIN ligas l ON l.id = p.liga_id
    JOIN equipos el ON el.id = p.equipo_local_id
    JOIN equipos ev ON ev.id = p.equipo_visitante_id
    LEFT JOIN recintos r  ON r.id  = p.recinto_id
    LEFT JOIN recintos rs ON rs.id = r.superior_id
    LEFT JOIN recintos ro ON ro.id = p.recinto_original_id
    LEFT JOIN recintos rop ON rop.id = ro.superior_id
    LEFT JOIN solicitudes_reprogramacion sr ON sr.id = (
        SELECT MAX(sr2.id) FROM solicitudes_reprogramacion sr2
        WHERE sr2.partido_id = p.id
    )
    WHERE p.estado = 'reprogramado'
      AND (sr.estado IS NULL OR sr.estado != 'rechazada')
    ORDER BY
        (p.fecha_programada IS NULL OR DATE(p.fecha_programada)='2026-12-31') ASC,
        p.fecha_programada ASC
")->fetchAll();

// Reservas a dar de baja (partidos reprogramados con fecha/cancha original conocida)
$reservas_baja = array_values(array_filter($partidos_open, function($p) {
    return !empty($p['fecha_original']) || !empty($p['recinto_original_id']);
}));
// Ordenar por fecha_original ascendente para que las más cercanas aparezcan primero
usort($reservas_baja, function($a, $b) {
    $ta = $a['fecha_original'] ? strtotime($a['fecha_original']) : PHP_INT_MAX;
    $tb = $b['fecha_original'] ? strtotime($b['fecha_original']) : PHP_INT_MAX;
    return $ta <=> $tb;
});

// Helpers
$hoy = new DateTime('today');
$es_sin_fecha = fn($p) => !$p['fecha_programada'] || date('Y-m-d', strtotime($p['fecha_programada'])) === '2026-12-31';
$es_vencido   = fn($p) => !$es_sin_fecha($p) && new DateTime($p['fecha_programada']) < $hoy;

// Recientes: solicitudes creadas en las últimas 48h Y que aún necesitan gestión
$limite_reciente = new DateTime('-48 hours');

/**
 * ¿Este partido todavía necesita acción del admin o del club?
 * Si está todo resuelto (baja confirmada + cancha asignada, o partido sin gestión pendiente),
 * no aparece en "Nuevas".
 */
$necesita_gestion = function(array $p): bool {
    $tiene_fecha_nueva = !empty($p['fecha_programada']) && date('Y-m-d', strtotime($p['fecha_programada'])) !== '2026-12-31';

    // 1) Solicitud aún pendiente de aprobación del admin
    if (($p['sol_estado'] ?? '') === 'pendiente') return true;

    // 2) Baja iniciada pero no confirmada por el club
    if (!empty($p['baja_solicitada_at']) && empty($p['baja_confirmada_at'])) return true;

    // 3) Post-aprobado (con snapshot) y la baja todavía no fue resuelta
    if (!empty($p['fecha_original']) && empty($p['baja_confirmada_at']) && (($p['sol_estado'] ?? 'aprobada') !== 'pendiente')) return true;

    // 4) Hay fecha nueva pero falta asignar cancha
    if ($tiene_fecha_nueva && empty($p['cancha_confirmada_at']) && empty($p['recinto_id'])) return true;

    return false;
};

$es_reciente = fn($p) => !empty($p['fecha_solicitud'])
    && new DateTime($p['fecha_solicitud']) >= $limite_reciente
    && $necesita_gestion($p);

$recientes = array_values(array_filter($partidos_open, $es_reciente));

// Partidos que necesitan gestión operativa. Las solicitudes aún pendientes se
// muestran en su tarjeta propia más abajo, así cada partido aparece una sola vez.
$pendientes_gestion = array_values(array_filter($partidos_open, function($p) use ($necesita_gestion) {
    return $necesita_gestion($p) && (($p['sol_estado'] ?? '') !== 'pendiente');
}));
// Ordenar: más urgentes (sin fecha) primero, luego por fecha de solicitud descendente
usort($pendientes_gestion, function($a, $b) {
    $sa = !empty($a['fecha_solicitud']) ? strtotime($a['fecha_solicitud']) : 0;
    $sb = !empty($b['fecha_solicitud']) ? strtotime($b['fecha_solicitud']) : 0;
    return $sb <=> $sa;
});
$n_pendientes_gestion = count($pendientes_gestion);

// Reprogramados YA GESTIONADOS (no requieren más acción) — para el toggle de la pestaña SOLICITUDES
$gestionados = array_values(array_filter($partidos_open, fn($p) => !$necesita_gestion($p)));
usort($gestionados, function($a, $b) {
    $ta = !empty($a['fecha_programada']) ? strtotime($a['fecha_programada']) : PHP_INT_MAX;
    $tb = !empty($b['fecha_programada']) ? strtotime($b['fecha_programada']) : PHP_INT_MAX;
    return $ta <=> $tb;
});
$n_gestionados = count($gestionados);

// Ordenar recientes: más nuevas primero
usort($recientes, fn($a,$b) => strtotime($b['fecha_solicitud']) - strtotime($a['fecha_solicitud']));
$n_recientes = count($recientes);

// Segmentar (excluyendo los recientes para no duplicar)
$sin_fecha = array_values(array_filter($partidos_open, fn($p) => $es_sin_fecha($p) && !$es_reciente($p)));
$vencidos  = array_values(array_filter($partidos_open, fn($p) => $es_vencido($p) && !$es_reciente($p)));
$con_fecha = array_values(array_filter($partidos_open, fn($p) => !$es_sin_fecha($p) && !$es_vencido($p) && !$es_reciente($p)));

// Avance del torneo
$total_jugados  = (int)$db->query("SELECT COUNT(*) FROM partidos WHERE estado IN ('jugado','walkover','no_presentado')")->fetchColumn();
$total_partidos = (int)$db->query("SELECT COUNT(*) FROM partidos")->fetchColumn();
$pct_avance     = $total_partidos > 0 ? round(($total_jugados / $total_partidos) * 100) : 0;

// ────────────────────────────────────────────────────────────────────────
// SOLICITUDES (para tab Solicitudes): pendientes + procesadas últimos 14 días
// ────────────────────────────────────────────────────────────────────────
$solicitudes_pendientes = $db->query("
    SELECT sr.id AS solicitud_id, sr.partido_id, sr.solicitante_id, sr.motivo,
           sr.fecha_propuesta, sr.rival_no_responde, sr.mutuo_acuerdo, sr.estado AS sol_estado, sr.created_at,
           p.jornada, p.fecha_programada, p.recinto_id,
           l.id AS liga_id, l.nombre AS liga_nombre,
           el.nombre AS local_nombre, ev.nombre AS visitante_nombre,
           j.nombre AS sol_nombre, j.apellido AS sol_apellido,
           r.nombre AS recinto_nombre, rs.nombre AS recinto_sup,
           r.contacto1_nombre, r.contacto1_telefono,
           r.contacto2_nombre, r.contacto2_telefono,
           r.contacto3_nombre, r.contacto3_telefono
    FROM solicitudes_reprogramacion sr
    JOIN partidos p   ON p.id = sr.partido_id
    JOIN ligas l      ON l.id = p.liga_id
    JOIN equipos el   ON el.id = p.equipo_local_id
    JOIN equipos ev   ON ev.id = p.equipo_visitante_id
    JOIN jugadores j  ON j.id = sr.solicitante_id
    LEFT JOIN recintos r ON r.id = p.recinto_id
    LEFT JOIN recintos rs ON rs.id = r.superior_id
    WHERE sr.estado = 'pendiente'
      AND sr.id = (
          SELECT MAX(sr2.id)
          FROM solicitudes_reprogramacion sr2
          WHERE sr2.partido_id = sr.partido_id
      )
      AND p.estado NOT IN ('jugado','walkover','no_presentado')
    ORDER BY sr.created_at DESC
")->fetchAll();
$n_solicitudes = count($solicitudes_pendientes);

// Solicitudes procesadas recientes (aprobadas o rechazadas en últimos 14 días)
$solicitudes_procesadas = $db->query("
    SELECT sr.id AS solicitud_id, sr.partido_id, sr.solicitante_id, sr.motivo,
           sr.fecha_propuesta, sr.fecha_aprobada, sr.cancha_aprobada, sr.estado AS sol_estado, sr.created_at,
           p.jornada, p.estado AS partido_estado,
           l.id AS liga_id, l.nombre AS liga_nombre,
           el.nombre AS local_nombre, ev.nombre AS visitante_nombre,
           j.nombre AS sol_nombre, j.apellido AS sol_apellido
    FROM solicitudes_reprogramacion sr
    JOIN partidos p   ON p.id = sr.partido_id
    JOIN ligas l      ON l.id = p.liga_id
    JOIN equipos el   ON el.id = p.equipo_local_id
    JOIN equipos ev   ON ev.id = p.equipo_visitante_id
    JOIN jugadores j  ON j.id = sr.solicitante_id
    WHERE sr.estado IN ('aprobada','rechazada')
      AND sr.id = (
          SELECT MAX(sr2.id)
          FROM solicitudes_reprogramacion sr2
          WHERE sr2.partido_id = sr.partido_id
      )
      AND sr.created_at >= DATE_SUB(NOW(), INTERVAL 14 DAY)
    ORDER BY sr.created_at DESC
    LIMIT 20
")->fetchAll();

// Si el partido sigue en el flujo activo, se representa por su estado actual
// (pendiente de gestión o gestionado), no además como una solicitud histórica.
$partidos_open_ids = array_fill_keys(
    array_map('intval', array_column($partidos_open, 'id')),
    true
);
$solicitudes_procesadas = array_values(array_filter(
    $solicitudes_procesadas,
    fn($s) => !isset($partidos_open_ids[(int)$s['partido_id']])
));
$n_procesadas = count($solicitudes_procesadas);

// Los badges cuentan partidos únicos y no la suma de dos listas que pueden
// representar distintas etapas del mismo flujo.
$ids_pendientes = array_unique(array_merge(
    array_map('intval', array_column($pendientes_gestion, 'id')),
    array_map('intval', array_column($solicitudes_pendientes, 'partido_id'))
));
$ids_gestionados = array_unique(array_merge(
    array_map('intval', array_column($gestionados, 'id')),
    array_map('intval', array_column($solicitudes_procesadas, 'partido_id'))
));
$n_pendientes_total = count($ids_pendientes);
$n_gestionados_total = count($ids_gestionados);

// Top equipos con más reprogramaciones (para llamar la atención a los que cuelgan más)
$por_equipo = [];
foreach ($partidos_open as $p) {
    foreach ([
        ['id' => (int)$p['local_id'],    'nombre' => $p['local_nombre']],
        ['id' => (int)$p['visitante_id'],'nombre' => $p['visitante_nombre']],
    ] as $eq) {
        if (!$eq['id']) continue;
        $por_equipo[$eq['id']]['nombre']    = $eq['nombre'];
        $por_equipo[$eq['id']]['liga_id']   = $p['liga_id'];
        $por_equipo[$eq['id']]['partidos'][] = $p;
    }
}
uasort($por_equipo, fn($a,$b) => count($b['partidos']) - count($a['partidos']));

// Función de fila partido (reutilizable)
function repro_fila_partido(array $p, bool $sin_fecha, bool $vencido, bool $resuelto = false, string $tag_html = '', string $return_tab = 'solicitudes'): string {
    $cls = 'partido-row';
    if ($resuelto) $cls .= ' pr-resuelto';
    elseif ($vencido) $cls .= ' pr-vencido';
    elseif ($sin_fecha) $cls .= ' pr-urgente';
    if ($tag_html === '' && $resuelto) {
        $tag_html = '<span class="partido-tag" style="background:#dcfce7;color:#15803d">✅ Resuelto</span>';
    }
    ob_start(); ?>
    <div class="<?= $cls ?>" data-sf="<?= $sin_fecha?'1':'0' ?>" data-venc="<?= $vencido?'1':'0' ?>" data-resuelto="<?= $resuelto?'1':'0' ?>" data-est="<?= epl_h($p['estado']) ?>" data-eq="<?= $p['local_id'] ?>,<?= $p['visitante_id'] ?>" data-search="<?= epl_h(strtolower($p['local_nombre'].' '.$p['visitante_nombre'].' '.$p['liga_nombre'])) ?>">
      <div class="partido-row-main">
        <div class="partido-meta">
          <span class="partido-liga"><?= epl_h($p['liga_nombre']) ?></span>
          <?php if ($p['jornada']): ?>
            <span class="partido-jornada">J<?= $p['jornada'] ?></span>
          <?php endif; ?>
          <?= $tag_html ?>
          <?php if ($p['rival_no_responde']): ?>
            <span class="partido-tag tag-norespon">⚠ Rival no responde</span>
          <?php endif; ?>
        </div>
        <div class="partido-equipos">
          <strong><?= epl_h($p['local_nombre']) ?></strong>
          <span class="vs">vs</span>
          <strong><?= epl_h($p['visitante_nombre']) ?></strong>
        </div>
        <div class="partido-extra">
          <?php
            // Determinar qué fecha mostrar en el encabezado
            $_fecha_mostrar = $p['fecha_programada'];
            // Si es pre-aprobado (no hay fecha_original en DB) y hay una fecha propuesta en la solicitud, mostramos la propuesta
            if (empty($p['fecha_original']) && !empty($p['fecha_propuesta'])) {
                $_fecha_mostrar = $p['fecha_propuesta'];
            }
            $_sf_mostrar = !$_fecha_mostrar || date('Y-m-d', strtotime($_fecha_mostrar)) === '2026-12-31';
          ?>
          <?php if (!$_sf_mostrar): ?>
            <span class="extra-item"><svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg> <?= date('d/m H:i', strtotime($_fecha_mostrar)) ?></span>
          <?php endif; ?>
          <?php if ($p['recinto_nombre']): ?>
            <span class="extra-item" style="color:#15803d;font-weight:600">✅ <?= epl_h($p['recinto_nombre']) ?></span>
          <?php endif; ?>
          <?php if (!empty($p['motivo'])): ?>
            <span class="extra-item motivo">"<?= epl_h(mb_strimwidth($p['motivo'], 0, 70, '…')) ?>"</span>
          <?php endif; ?>
        </div>
        <?php
          // ── Datos del partido ───────────────────────────────────────
          $_es_post        = !empty($p['fecha_original']) && ($p['sol_estado'] ?? 'aprobada') !== 'pendiente';
          $_fo             = $p['fecha_original'] ?: $p['fecha_programada'];
          $_fo_lbl         = ($_fo && date('Y-m-d', strtotime($_fo)) !== '2026-12-31') ? date('d/m/Y H:i', strtotime($_fo)) : null;
          $_rec_orig       = $p['recinto_original_nombre'] ?: ($p['recinto_nombre'] ?? null);
          $_rec_orig_sup   = $p['recinto_original_sup'] ?: ($p['recinto_sup'] ?? null);
          // "Cancha 12 (Santa Blanca)"
          $_rec_orig_full  = $_rec_orig ? $_rec_orig . ($_rec_orig_sup ? " ($_rec_orig_sup)" : '') : null;
          $_rec_actual_full = $_es_post ? ($p['recinto_nombre']
              ? $p['recinto_nombre'] . (!empty($p['recinto_sup']) ? ' (' . $p['recinto_sup'] . ')' : '')
              : null) : null;
          $_tiene_original = $_es_post && ($_fo_lbl || $_rec_orig);

          $_fecha_nueva_raw = $_es_post ? $p['fecha_programada'] : ($p['fecha_propuesta'] ?? $p['fecha_programada']);
          $_sf_nueva    = !$_fecha_nueva_raw || date('Y-m-d', strtotime($_fecha_nueva_raw)) === '2026-12-31';
          $_fecha_nueva = !$_sf_nueva ? date('d/m/Y H:i', strtotime($_fecha_nueva_raw)) : null;
          $_necesita_cancha = $_fecha_nueva && ($_es_post ? empty($p['recinto_nombre']) : empty($p['recinto_id']));

          $_confirmada  = !empty($p['baja_confirmada_at']);
          $_solicitada  = !empty($p['baja_solicitada_at']);

          // Mostrar bloque cuando: hay algo de baja O necesita asignar cancha nueva
          $_mostrar_bloque = $_tiene_original || $_necesita_cancha;

          // Contactos: prueba en el orden: recinto_original_id → recinto_id (subiendo la jerarquía)
          // Si la cancha exacta no tiene contactos, busca en la sede / club padre.
          // Si nada da resultado, recomienda el club más usado en la liga.
          $_contactos = [];
          $_contactos_recomendados = false;
          $_contactos_origen_nombre = '';
          for ($i = 1; $i <= 3; $i++) {
            if (!empty($p["contacto{$i}_telefono"])) {
              $_contactos[] = ['nombre' => $p["contacto{$i}_nombre"] ?? '', 'telefono' => $p["contacto{$i}_telefono"]];
            }
          }
          // Fallback 1: subir por la jerarquía desde recinto_original_id
          if (empty($_contactos) && !empty($p['recinto_original_id'])) {
            $_h = epl_recinto_contactos_jerarquico((int)$p['recinto_original_id']);
            if (!empty($_h['contactos'])) { $_contactos = $_h['contactos']; $_contactos_origen_nombre = $_h['recinto_nombre'] ?? ''; }
          }
          // Fallback 2: usar recinto_id (cancha asignada actual) y subir la jerarquía
          if (empty($_contactos) && !empty($p['recinto_id'])) {
            $_h = epl_recinto_contactos_jerarquico((int)$p['recinto_id']);
            if (!empty($_h['contactos'])) { $_contactos = $_h['contactos']; $_contactos_origen_nombre = $_h['recinto_nombre'] ?? ''; }
          }
          // Fallback 3: recomendar el club más usado en la liga
          if (empty($_contactos) && !empty($p['liga_id'])) {
            $_h = epl_recintos_recomendados_liga((int)$p['liga_id']);
            if (!empty($_h['contactos'])) {
              $_contactos = $_h['contactos'];
              $_contactos_origen_nombre = $_h['recinto_nombre'] ?? '';
              $_contactos_recomendados = true;
            }
          }

          // Token + link (baja + cancha en una sola página)
          $_proto = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
          $_host  = $_SERVER['HTTP_HOST'] ?? 'epleague.cl';
          $_token = (!$_confirmada && $_mostrar_bloque) ? epl_partido_baja_token((int)$p['id']) : '';
          $_link  = $_token ? "$_proto://$_host/gestion_reserva.php?t=$_token" : '';

          // ── Construir mensaje WhatsApp ──────────────────────────────
          $_msg = "Hola, te hablo de Elite Padel League.\n\n";
          if ($_tiene_original) {
              $_msg .= "Necesitamos DAR DE BAJA esta reserva:\n";
              if ($_fo_lbl)  $_msg .= "📅 $_fo_lbl\n";
              if ($_rec_orig_full) $_msg .= "🎾 $_rec_orig_full\n";
              $_msg .= "👥 {$p['local_nombre']} vs {$p['visitante_nombre']}\n";
              if ($_fecha_nueva) {
                  $_msg .= "\nNueva fecha del partido: $_fecha_nueva\n";
                  if ($_rec_actual_full) $_msg .= "Nueva cancha: $_rec_actual_full\n";
              }
          } else {
              $_msg .= "Tenemos un partido reprogramado y necesitamos asignar cancha:\n";
              if ($_fecha_nueva) $_msg .= "📅 $_fecha_nueva\n";
              $_msg .= "👥 {$p['local_nombre']} vs {$p['visitante_nombre']}\n";
          }
          if ($_link) {
              if ($_necesita_cancha && $_tiene_original) {
                  $_msg .= "\nDesde este enlace confirmas la baja y eliges la cancha para la nueva fecha:\n$_link\n(¡Solo toca la cancha y queda todo listo!)";
              } elseif ($_necesita_cancha) {
                  $_msg .= "\nElige la cancha para la nueva fecha desde acá:\n$_link\n(¡Solo toca la cancha y queda confirmada!)";
              } else {
                  $_msg .= "\nConfirma la baja desde este enlace:\n$_link";
              }
          }
          $_msg .= "\n\n¡Gracias!";

          if ($_mostrar_bloque):
            // Colores del bloque según estado
            if ($_confirmada) {
                [$_bg,$_bd,$_tc,$_lbl] = ['#dcfce7','#10b981','#15803d','✅ BAJA CONFIRMADA'];
            } elseif ($_necesita_cancha && !$_tiene_original) {
                [$_bg,$_bd,$_tc,$_lbl] = ['#dbeafe','#3b82f6','#1e40af','🎾 ASIGNAR CANCHA'];
            } elseif ($_solicitada) {
                [$_bg,$_bd,$_tc,$_lbl] = ['#fef3c7','#f59e0b','#92400e','⏳ ESPERANDO CONFIRMACIÓN'];
            } else {
                [$_bg,$_bd,$_tc,$_lbl] = ['#fee2e2','#dc2626','#991b1b', $_necesita_cancha ? '🚫 BAJA + 🎾 ELEGIR CANCHA' : '🚫 DAR DE BAJA'];
            }
        ?>
          <div style="margin-top:.5rem;padding:.55rem .75rem;background:<?= $_bg ?>;border-left:3px solid <?= $_bd ?>;border-radius:6px;font-size:.75rem;color:<?= $_tc ?>;line-height:1.5">
            <div style="display:flex;align-items:center;gap:.4rem;flex-wrap:wrap;font-weight:800;margin-bottom:.2rem">
              <?= $_lbl ?>
              <?php if ($_fo_lbl): ?><span style="font-weight:600"><?= $_fo_lbl ?></span><?php endif; ?>
              <?php if ($_rec_orig): ?><span style="font-weight:600">· <?= epl_h($_rec_orig) ?></span><?php endif; ?>
              <?php if ($_necesita_cancha && $_fecha_nueva): ?>
                <span style="font-weight:600;color:#1d4ed8">→ Nueva: <?= $_fecha_nueva ?></span>
              <?php endif; ?>
            </div>

            <?php if ($_confirmada): ?>
              <div style="font-size:.7rem;font-weight:600;opacity:.85">
                Confirmado <?= date('d/m H:i', strtotime($p['baja_confirmada_at'])) ?>
                <?php if ($p['baja_confirmada_por']): ?>· por <?= epl_h($p['baja_confirmada_por']) ?><?php endif; ?>
              </div>
            <?php elseif (!empty($_contactos)): ?>
              <?php if ($_contactos_recomendados): ?>
                <div style="font-size:.68rem;margin:.25rem 0 .15rem;color:#92400e;background:#fef3c7;border:1px solid #fcd34d;padding:.2rem .5rem;border-radius:5px;display:inline-block;font-weight:700">
                  ★ Recomendado · <?= epl_h($_contactos_origen_nombre ?: 'club habitual') ?>
                </div>
              <?php endif; ?>
              <div style="margin-top:.4rem;display:flex;flex-wrap:wrap;gap:.35rem">
                <?php foreach ($_contactos as $_c):
                  $_tel = preg_replace('/[^0-9]/', '', $_c['telefono']);
                  if (!$_tel) continue;
                  if (substr($_tel, 0, 2) !== '56') $_tel = '56' . $_tel;
                  $_wsp_url = "https://wa.me/{$_tel}?text=" . rawurlencode($_msg);
                ?>
                <a href="<?= $_wsp_url ?>" target="_blank" rel="noopener"
                   style="display:inline-flex;align-items:center;gap:.3rem;background:#25D366;color:#fff;padding:.3rem .65rem;border-radius:6px;font-size:.68rem;font-weight:800;text-decoration:none">
                  <svg width="13" height="13" fill="currentColor" viewBox="0 0 24 24"><path d="M17.6 6.32A7.85 7.85 0 0012.05 4a7.94 7.94 0 00-6.88 11.93L4 20l4.21-1.1a7.95 7.95 0 003.84.98h.01a7.94 7.94 0 005.54-13.56M12.05 18.5a6.62 6.62 0 01-3.36-.92l-.24-.14-2.5.66.67-2.44-.16-.25a6.59 6.59 0 0110.21-8.16 6.55 6.55 0 011.93 4.66 6.62 6.62 0 01-6.55 6.59"/></svg>
                  <?= epl_h($_c['nombre'] ?: 'WhatsApp') ?>
                </a>
                <?php endforeach; ?>
              </div>
            <?php else: ?>
              <div style="font-size:.68rem;margin-top:.25rem;opacity:.75">
                ⚠ Sin contactos — agrégalos en <a href="recintos.php" style="color:inherit;font-weight:700">Recintos</a>
              </div>
            <?php endif; ?>
          </div>
        <?php endif; ?>
      </div>
      <div class="partido-actions">
        <a href="partido_detalle.php?id=<?= $p['id'] ?>" class="btn-gestionar">👁 Ver partido</a>
        <?php if ($_confirmada && $p['recinto_id']): ?>
          <button type="button" class="btn-sec" onclick="reenviarNotifCancha(<?= $p['id'] ?>, this)">
            📢 Reenviar notif.
          </button>
        <?php endif; ?>
        <?php if (!empty($p['solicitud_id'])): ?>
        <form method="post" style="margin:0"
              data-confirm="¿Eliminar esta gestión de <?= epl_h($p['local_nombre']) ?> vs <?= epl_h($p['visitante_nombre']) ?>? Solo se borrará la solicitud; el partido mantendrá su estado, fecha y recinto actuales."
              data-confirm-ok="Sí, eliminar gestión">
          <input type="hidden" name="action" value="eliminar_solicitud_sin_cambios">
          <input type="hidden" name="solicitud_id" value="<?= $p['solicitud_id'] ?>">
          <input type="hidden" name="return_tab" value="<?= epl_h($return_tab) ?>">
          <button type="submit" class="btn-text-danger"
                  data-confirm="¿Eliminar esta gestión de <?= epl_h($p['local_nombre']) ?> vs <?= epl_h($p['visitante_nombre']) ?>? Solo se borrará la solicitud; el partido mantendrá su estado, fecha y recinto actuales."
                  data-confirm-ok="Sí, eliminar gestión">
            🗑 Eliminar gestión
          </button>
        </form>
        <?php endif; ?>
      </div>
    </div>
    <?php
    return ob_get_clean();
}

require_once '../includes/header.php';
?>

<div class="dash-layout">
  <?php include __DIR__ . '/partials/sidebar.php'; ?>

  <main class="dash-main">

    <?php $_flash = epl_flash_get(); if ($_flash): ?>
      <div class="alert alert-<?= $_flash['tipo'] === 'ok' ? 'success' : 'error' ?>" style="margin-bottom:1rem"><?= epl_h($_flash['msg']) ?></div>
    <?php endif; ?>

    <!-- HEADER hero compacto -->
    <div class="dash-header" style="background:linear-gradient(135deg,#1c2f48 0%, #0f1e30 100%);border-radius:18px;padding:1.5rem 1.75rem;color:#fff;margin-bottom:1.5rem;position:relative;overflow:hidden;box-shadow:0 8px 28px rgba(28,47,72,.18)">
      <div style="position:absolute;top:-40px;right:-40px;width:180px;height:180px;background:radial-gradient(circle,rgba(201,167,98,.18) 0%,transparent 70%);pointer-events:none"></div>
      <div style="position:relative;z-index:1;display:flex;justify-content:space-between;align-items:flex-end;gap:1rem;flex-wrap:wrap">
        <div>
          <span style="font-size:.65rem;font-weight:900;letter-spacing:.25em;color:#C9A762;text-transform:uppercase">Panel admin</span>
          <h1 style="color:#fff;margin:.2rem 0 .15rem;font-size:clamp(1.5rem,3.5vw,2rem);font-family:'Anton',sans-serif;text-transform:uppercase;line-height:1">Re<span style="color:#C9A762">programaciones</span></h1>
          <p style="color:rgba(255,255,255,.7);margin-top:.2rem;font-size:.82rem">Solo partidos reprogramados — primero los urgentes, después los agendados.</p>
        </div>
        <div style="text-align:right">
          <div style="font-size:.65rem;color:rgba(255,255,255,.55);text-transform:uppercase;letter-spacing:.15em;font-weight:700">Avance del torneo</div>
          <div style="font-family:'Anton',sans-serif;font-size:2.4rem;color:#C9A762;line-height:1"><?= $pct_avance ?>%</div>
          <div style="font-size:.7rem;color:rgba(255,255,255,.6)"><?= $total_jugados ?>/<?= $total_partidos ?> partidos</div>
        </div>
      </div>
    </div>

    <!-- TABS: Solicitudes | Informe -->
    <?php
      // El badge representa partidos únicos que todavía requieren una acción.
      $badge_solicitudes = $n_pendientes_total;
      // Abrir en Solicitudes si hay algo pendiente; si no, Informe
      $tab_inicial = isset($_GET['tab']) ? $_GET['tab']
          : ($badge_solicitudes > 0 ? 'solicitudes' : ($n_recientes > 0 ? 'informe' : 'informe'));
    ?>
    <div class="tabs-bar">
      <button class="tab-btn <?= $tab_inicial==='solicitudes'?'active':'' ?>" data-tab="solicitudes" onclick="cambiarTab('solicitudes')">
        📨 Solicitudes
        <?php if ($badge_solicitudes > 0): ?>
          <span class="tab-badge"><?= $badge_solicitudes ?></span>
        <?php endif; ?>
      </button>
      <button class="tab-btn <?= $tab_inicial==='informe'?'active':'' ?>" data-tab="informe" onclick="cambiarTab('informe')">
        📊 Informe
        <?php if ($n_recientes > 0): ?>
          <span class="tab-badge" style="background:#8b5cf6"><?= $n_recientes ?> nuevo<?= $n_recientes>1?'s':'' ?></span>
        <?php endif; ?>
      </button>
    </div>

    <!-- ═══════════════════ TAB SOLICITUDES ═══════════════════ -->
    <div id="tab-solicitudes" class="tab-content" style="display:<?= $tab_inicial==='solicitudes'?'block':'none' ?>">

      <!-- Buscador + Toggle Todos / Pendientes / Gestionados -->
      <div class="filtros-bar">
        <div class="busqueda">
          <svg width="16" height="16" fill="none" stroke="#94a3b8" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
          <input type="text" id="buscarSol" placeholder="Buscar pareja, partido o liga…" oninput="buscarSolicitudes(this.value)">
        </div>
        <div class="estado-filtro">
          <button class="estado-btn" data-solfiltro="todos" onclick="filtrarSolicitudes('todos', this)">Todos</button>
          <button class="estado-btn active" data-solfiltro="pendientes" onclick="filtrarSolicitudes('pendientes', this)">
            ⏳ Pendientes
            <?php if ($n_pendientes_total > 0): ?><span class="tab-badge" style="background:#f59e0b"><?= $n_pendientes_total ?></span><?php endif; ?>
          </button>
          <button class="estado-btn" data-solfiltro="gestionados" onclick="filtrarSolicitudes('gestionados', this)">
            ✅ Gestionados
            <?php if ($n_gestionados_total > 0): ?><span class="tab-badge" style="background:#10b981"><?= $n_gestionados_total ?></span><?php endif; ?>
          </button>
        </div>
      </div>

      <!-- ═════════════ GRUPO: PENDIENTES ═════════════ -->
      <div data-sol-grupo="pendientes">
      <?php if (empty($solicitudes_pendientes) && empty($pendientes_gestion)): ?>
        <section class="sec-card">
          <div style="padding:3rem;text-align:center;color:var(--gray-400)">
            <div style="font-size:3rem">✅</div>
            <p style="font-weight:700;margin-top:.5rem">No hay reprogramaciones pendientes</p>
            <p style="font-size:.85rem">Todo lo que requería una acción ya fue gestionado.</p>
          </div>
        </section>
      <?php else: ?>


      <!-- ═════════════════════ PENDIENTES DE GESTIÓN ═════════════════════ -->
      <?php if ($n_pendientes_gestion > 0): ?>
      <section class="sec-card sec-urgente" style="border-left:5px solid #f59e0b">
        <div class="sec-head">
          <div>
            <h2 class="sec-title" style="color:#92400e">⏳ Partidos en gestión</h2>
            <p class="sec-sub">Reprogramados que todavía esperan dar de baja la reserva original o asignar una cancha nueva</p>
          </div>
          <div class="sec-count" style="background:#fef3c7;color:#92400e"><?= $n_pendientes_gestion ?></div>
        </div>
        <div class="sec-body">
          <?php foreach ($pendientes_gestion as $p):
            // Decidir qué tag mostrar arriba del partido
            $_tag = '';
            $_tag_bg = '#fef3c7';
            $_tag_color = '#92400e';
            if (!empty($p['baja_solicitada_at']) && empty($p['baja_confirmada_at'])) {
                $_tag = '⏳ Esperando confirmación del club';
                $_tag_bg = '#dbeafe'; $_tag_color = '#1e40af';
            } elseif (!empty($p['fecha_original']) && empty($p['baja_confirmada_at'])) {
                $_tag = '🚫 Falta dar de baja la cancha original';
                $_tag_bg = '#fee2e2'; $_tag_color = '#991b1b';
            } else {
                $_tag = '🎾 Falta asignar cancha nueva';
                $_tag_bg = '#dbeafe'; $_tag_color = '#1e40af';
            }
            $sf = $es_sin_fecha($p);
            $vc = $es_vencido($p);
            $_tag_html = '<span class="partido-tag" style="background:'.$_tag_bg.';color:'.$_tag_color.'">'.$_tag.'</span>';
          ?>
          <?= repro_fila_partido($p, $sf, $vc, false, $_tag_html) ?>
          <?php endforeach; ?>
        </div>
      </section>
      <?php endif; ?>

      <?php if (!empty($solicitudes_pendientes)): ?>
        <section class="sec-card sec-urgente">
          <div class="sec-head">
            <div>
              <h2 class="sec-title">📨 Solicitudes de reprogramación pendientes</h2>
              <p class="sec-sub">Revisa cada una, aprueba o rechaza desde la página del torneo</p>
            </div>
            <div class="sec-count danger"><?= $n_solicitudes ?></div>
          </div>
          <div class="sec-body">
            <?php foreach ($solicitudes_pendientes as $s):
              $sol_sin_fecha = empty($s['fecha_propuesta']) || $s['rival_no_responde'];
              $fecha_pp = $s['fecha_propuesta']
                  ? date('d/m/Y H:i', strtotime($s['fecha_propuesta']))
                  : 'Sin fecha propuesta';
              $fecha_solicitud = date('d/m H:i', strtotime($s['created_at']));
            ?>
            <div class="partido-row">
              <div class="partido-row-main">
                <div class="partido-meta">
                  <span class="partido-liga"><?= epl_h($s['liga_nombre']) ?></span>
                  <?php if ($s['jornada']): ?>
                    <span class="partido-jornada">J<?= $s['jornada'] ?></span>
                  <?php endif; ?>
                  <?php if ($s['mutuo_acuerdo']): ?>
                    <span class="partido-tag tag-acuerdo">🤝 Mutuo acuerdo</span>
                  <?php endif; ?>
                  <?php if ($s['rival_no_responde']): ?>
                    <span class="partido-tag tag-norespon">⚠ Rival no responde</span>
                  <?php endif; ?>
                </div>
                <div class="partido-equipos">
                  <strong><?= epl_h($s['local_nombre']) ?></strong>
                  <span class="vs">vs</span>
                  <strong><?= epl_h($s['visitante_nombre']) ?></strong>
                </div>
                <div class="partido-extra">
                  <span class="extra-item">
                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                    Solicita <strong><?= epl_h($s['sol_nombre'].' '.$s['sol_apellido']) ?></strong>
                  </span>
                  <span class="extra-item">
                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/></svg>
                    Propone: <strong><?= $fecha_pp ?></strong>
                  </span>
                  <span class="extra-item" style="color:#94a3b8">hace <?= $fecha_solicitud ?></span>
                  <?php if (!empty($s['motivo'])): ?>
                    <span class="extra-item motivo">"<?= epl_h(mb_strimwidth($s['motivo'], 0, 80, '…')) ?>"</span>
                  <?php endif; ?>
                  <?php if ($sol_sin_fecha): ?>
                    <div style="margin-top:.45rem;padding:.45rem .75rem;background:#fef3c7;border-left:3px solid #f59e0b;border-radius:4px;font-size:.75rem;color:#92400e;font-weight:600;line-height:1.4">
                      ⚠️ Solicita reprogramar SIN FECHA. Se debe liberar la cancha original:
                      <div style="margin-top:.2rem;font-weight:700;font-size:.72rem">
                        <?php if ($s['fecha_programada']): ?>📅 <?= date('d/m/Y H:i', strtotime($s['fecha_programada'])) ?><?php endif; ?>
                        <?php if ($s['recinto_nombre']): ?> · 🏟️ <?= epl_h($s['recinto_nombre']) ?><?php endif; ?>
                      </div>
                    </div>
                    <?php
                      $contactos = [];
                      for ($i = 1; $i <= 3; $i++) {
                          if (!empty($s["contacto{$i}_telefono"])) {
                              $contactos[] = ['nombre' => $s["contacto{$i}_nombre"] ?? '', 'telefono' => $s["contacto{$i}_telefono"]];
                          }
                      }
                      if (empty($contactos) && !empty($s['recinto_id'])) {
                          $h = epl_recinto_contactos_jerarquico((int)$s['recinto_id']);
                          if (!empty($h['contactos'])) {
                              $contactos = $h['contactos'];
                          }
                      }
                      if (empty($contactos) && !empty($s['liga_id'])) {
                          $h = epl_recintos_recomendados_liga((int)$s['liga_id']);
                          if (!empty($h['contactos'])) {
                              $contactos = $h['contactos'];
                          }
                      }
                      if (!empty($contactos)) {
                          $fo_lbl = ($s['fecha_programada'] && date('Y-m-d', strtotime($s['fecha_programada'])) !== '2026-12-31') 
                              ? date('d/m/Y H:i', strtotime($s['fecha_programada'])) 
                              : null;
                          $rec_orig = $s['recinto_nombre'];
                          $rec_orig_sup = $s['recinto_sup'];
                          $rec_orig_full = $rec_orig ? $rec_orig . ($rec_orig_sup ? " ($rec_orig_sup)" : '') : null;
                          
                          $wsp_msg = "Hola, te hablo de Elite Padel League.\n\n"
                                   . "Necesitamos DAR DE BAJA esta reserva porque el partido se reprogramó sin fecha:\n";
                          if ($fo_lbl)        $wsp_msg .= "📅 $fo_lbl\n";
                          if ($rec_orig_full) $wsp_msg .= "🏟️ $rec_orig_full\n";
                          $wsp_msg .= "👥 {$s['local_nombre']} vs {$s['visitante_nombre']}\n\n"
                                    . "Por favor, confírmanos cuando esté liberada.\n\n¡Gracias!";
                          
                          echo '<div style="margin-top:.45rem;display:flex;flex-wrap:wrap;gap:.35rem">';
                          foreach ($contactos as $c) {
                              $tel = preg_replace('/[^0-9]/', '', $c['telefono']);
                              if (!$tel) continue;
                              if (substr($tel, 0, 2) !== '56') $tel = '56' . $tel;
                              $wsp_url = "https://wa.me/{$tel}?text=" . rawurlencode($wsp_msg);
                              ?>
                              <a href="<?= $wsp_url ?>" target="_blank" rel="noopener"
                                 style="display:inline-flex;align-items:center;gap:.3rem;background:#25D366;color:#fff;padding:.3rem .6rem;border-radius:6px;font-size:.68rem;font-weight:800;text-decoration:none">
                                <svg width="12" height="12" fill="currentColor" viewBox="0 0 24 24"><path d="M17.6 6.32A7.85 7.85 0 0012.05 4a7.94 7.94 0 00-6.88 11.93L4 20l4.21-1.1a7.95 7.95 0 003.84.98h.01a7.94 7.94 0 005.54-13.56M12.05 18.5a6.62 6.62 0 01-3.36-.92l-.24-.14-2.5.66.67-2.44-.16-.25a6.59 6.59 0 0110.21-8.16 6.55 6.55 0 011.93 4.66 6.62 6.62 0 01-6.55 6.59"/></svg>
                                <?= epl_h($c['nombre'] ?: 'WhatsApp') ?>
                              </a>
                              <?php
                          }
                          echo '</div>';
                      } else {
                          ?>
                          <div style="font-size:.68rem;color:#dc2626;margin-top:.45rem;font-weight:700">
                            ⚠️ Sin contactos del club configurados.
                          </div>
                          <?php
                      }
                    ?>
                  <?php endif; ?>
                </div>
              </div>
              <div style="display:flex;flex-direction:column;gap:.35rem;align-items:stretch">
                <a href="partido_detalle.php?id=<?= $s['partido_id'] ?>" class="btn-gestionar"
                   style="width:100%;text-align:center">👁 Ver partido</a>
                <?php if ($sol_sin_fecha): ?>
                  <form method="post" action="api_reprogramacion.php" style="margin:0"
                        data-confirm="¿Aprobar esta reprogramación sin fecha? El partido quedará 'A coordinar' y se liberará la cancha original."
                        data-confirm-ok="Sí, liberar cancha">
                    <input type="hidden" name="id" value="<?= $s['solicitud_id'] ?>">
                    <input type="hidden" name="accion" value="aprobar">
                    <input type="hidden" name="fecha_aprobada" value="">
                    <button type="submit" class="btn-gestionar"
                            style="width:100%;background:#d97706;color:#fff;border:1px solid #d97706;font-size:.65rem;padding:.35rem .6rem;font-weight:700">
                      Aprobar (Lib.)
                    </button>
                  </form>
                <?php endif; ?>
                <form method="post" style="margin:0"
                      data-confirm="¿Rechazar la solicitud de <?= epl_h($s['local_nombre']) ?> vs <?= epl_h($s['visitante_nombre']) ?>?"
                      data-confirm-ok="Sí, rechazar">
                  <input type="hidden" name="action" value="rechazar_solicitud">
                  <input type="hidden" name="solicitud_id" value="<?= $s['solicitud_id'] ?>">
                  <button type="submit" class="btn-gestionar"
                          style="width:100%;background:#fee2e2;color:#991b1b;border:1px solid #fca5a5;font-size:.65rem;padding:.35rem .6rem"
                          data-confirm="¿Rechazar la solicitud de <?= epl_h($s['local_nombre']) ?> vs <?= epl_h($s['visitante_nombre']) ?>?"
                          data-confirm-ok="Sí, rechazar">
                    🗑 Rechazar
                  </button>
                </form>
                <form method="post" style="margin:0"
                      data-confirm="¿Eliminar esta gestión de <?= epl_h($s['local_nombre']) ?> vs <?= epl_h($s['visitante_nombre']) ?>? Solo se borrará la solicitud; el partido mantendrá su estado, fecha y recinto actuales."
                      data-confirm-ok="Sí, eliminar gestión">
                  <input type="hidden" name="action" value="eliminar_solicitud_sin_cambios">
                  <input type="hidden" name="solicitud_id" value="<?= $s['solicitud_id'] ?>">
                  <input type="hidden" name="return_tab" value="solicitudes">
                  <button type="submit" class="btn-gestionar"
                          style="width:100%;background:#fff;color:#991b1b;border:1px solid #fca5a5;font-size:.65rem;padding:.35rem .6rem"
                          data-confirm="¿Eliminar esta gestión de <?= epl_h($s['local_nombre']) ?> vs <?= epl_h($s['visitante_nombre']) ?>? Solo se borrará la solicitud; el partido mantendrá su estado, fecha y recinto actuales."
                          data-confirm-ok="Sí, eliminar gestión">
                    🗑 Eliminar gestión
                  </button>
                </form>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
        </section>
      <?php endif; ?>

      <?php endif; ?>
      </div><!-- /grupo pendientes -->

      <!-- ═════════════ GRUPO: GESTIONADOS ═════════════ -->
      <div data-sol-grupo="gestionados" style="display:none">

      <!-- Solicitudes ya gestionadas (aprobadas / rechazadas) -->
      <?php if (!empty($solicitudes_procesadas)): ?>
        <section class="sec-card" style="border-left:5px solid #94a3b8">
          <div class="sec-head">
            <div>
              <h2 class="sec-title">🗂️ Solicitudes gestionadas recientemente</h2>
              <p class="sec-sub">Últimos 14 días — aprobadas y rechazadas</p>
            </div>
            <div class="sec-count" style="background:#f1f5f9;color:#64748b"><?= $n_procesadas ?></div>
          </div>
          <div class="sec-body">
            <?php foreach ($solicitudes_procesadas as $s):
              $es_aprobada = $s['sol_estado'] === 'aprobada';
              $estado_color = $es_aprobada ? '#15803d' : '#dc2626';
              $estado_bg    = $es_aprobada ? '#dcfce7' : '#fee2e2';
              $estado_label = $es_aprobada ? '✓ APROBADA' : '✗ RECHAZADA';
              $fecha_pp = $s['fecha_aprobada']
                  ? date('d/m/Y H:i', strtotime($s['fecha_aprobada']))
                  : ($s['fecha_propuesta'] ? date('d/m/Y H:i', strtotime($s['fecha_propuesta'])) : 'Sin fecha');
              $fecha_solicitud = date('d/m H:i', strtotime($s['created_at']));
            ?>
            <div class="partido-row" style="opacity:.92">
              <div class="partido-row-main">
                <div class="partido-meta">
                  <span class="partido-tag" style="background:<?= $estado_bg ?>;color:<?= $estado_color ?>"><?= $estado_label ?></span>
                  <span class="partido-liga"><?= epl_h($s['liga_nombre']) ?></span>
                  <?php if ($s['jornada']): ?>
                    <span class="partido-jornada">J<?= $s['jornada'] ?></span>
                  <?php endif; ?>
                </div>
                <div class="partido-equipos">
                  <strong><?= epl_h($s['local_nombre']) ?></strong>
                  <span class="vs">vs</span>
                  <strong><?= epl_h($s['visitante_nombre']) ?></strong>
                </div>
                <div class="partido-extra">
                  <span class="extra-item">
                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                    Solicitó <strong><?= epl_h($s['sol_nombre'].' '.$s['sol_apellido']) ?></strong>
                  </span>
                  <?php if ($es_aprobada): ?>
                    <span class="extra-item" style="color:#15803d;font-weight:700">
                      <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                      Nueva fecha: <?= $fecha_pp ?>
                      <?php if (!empty($s['cancha_aprobada'])): ?> · <?= epl_h(trim($s['cancha_aprobada'])) ?><?php endif; ?>
                    </span>
                  <?php endif; ?>
                  <span class="extra-item" style="color:#94a3b8">solicitado <?= $fecha_solicitud ?></span>
                </div>
              </div>
              <div class="partido-actions">
                <a href="partido_detalle.php?id=<?= $s['partido_id'] ?>" class="btn-gestionar" style="background:#94a3b8;color:#fff">👁 Ver partido</a>
                <form method="post" style="margin:0"
                      data-confirm="¿Eliminar esta gestión de <?= epl_h($s['local_nombre']) ?> vs <?= epl_h($s['visitante_nombre']) ?>? Solo se borrará la solicitud; el partido mantendrá su estado, fecha y recinto actuales."
                      data-confirm-ok="Sí, eliminar gestión">
                  <input type="hidden" name="action" value="eliminar_solicitud_sin_cambios">
                  <input type="hidden" name="solicitud_id" value="<?= $s['solicitud_id'] ?>">
                  <input type="hidden" name="return_tab" value="solicitudes">
                  <button type="submit" class="btn-text-danger"
                          data-confirm="¿Eliminar esta gestión de <?= epl_h($s['local_nombre']) ?> vs <?= epl_h($s['visitante_nombre']) ?>? Solo se borrará la solicitud; el partido mantendrá su estado, fecha y recinto actuales."
                          data-confirm-ok="Sí, eliminar gestión">🗑 Eliminar gestión</button>
                </form>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
        </section>
      <?php endif; ?>

        <?php if (empty($gestionados) && empty($solicitudes_procesadas)): ?>
          <section class="sec-card">
            <div style="padding:3rem;text-align:center;color:var(--gray-400)">
              <div style="font-size:3rem">📭</div>
              <p style="font-weight:700;margin-top:.5rem">Todavía no hay reprogramados gestionados</p>
              <p style="font-size:.85rem">Cuando resuelvas un partido (fecha + cancha asignada), aparecerá aquí.</p>
            </div>
          </section>
        <?php elseif (!empty($gestionados)): ?>
          <section class="sec-card" style="border-left:5px solid #10b981">
            <div class="sec-head">
              <div>
                <h2 class="sec-title" style="color:#15803d">✅ Reprogramados gestionados</h2>
                <p class="sec-sub">Ya tienen todo resuelto (fecha y cancha). Quedan aquí como referencia hasta que se jueguen.</p>
              </div>
              <div class="sec-count" style="background:#dcfce7;color:#15803d"><?= $n_gestionados ?></div>
            </div>
            <div class="sec-body">
              <?php foreach ($gestionados as $p): ?>
                <?= repro_fila_partido($p, $es_sin_fecha($p), $es_vencido($p), true) ?>
              <?php endforeach; ?>
            </div>
          </section>
        <?php endif; ?>
      </div><!-- /grupo gestionados -->
    </div>

    <!-- ═══════════════════ TAB INFORME ═══════════════════ -->
    <div id="tab-informe" class="tab-content" style="display:<?= $tab_inicial==='informe'?'block':'none' ?>">

    <!-- 🆕 SECCIÓN RECIENTES (últimas 48h) -->
    <?php if (!empty($recientes)): ?>
    <section class="sec-card" style="border-left:5px solid #8b5cf6;margin-bottom:1.25rem;background:linear-gradient(135deg,#faf5ff,#f5f3ff)">
      <div class="sec-head" style="background:transparent">
        <div>
          <h2 class="sec-title" style="color:#6d28d9">🆕 Nuevas — últimas 48 horas</h2>
          <p class="sec-sub">Recién llegadas, todavía no gestionadas</p>
        </div>
        <div class="sec-count" style="background:#ede9fe;color:#6d28d9"><?= $n_recientes ?></div>
      </div>
      <div class="sec-body">
        <?php foreach ($recientes as $p):
          $sf = $es_sin_fecha($p);
          $vc = $es_vencido($p);
          $hace = '';
          if (!empty($p['fecha_solicitud'])) {
              $diff = (new DateTime())->diff(new DateTime($p['fecha_solicitud']));
              $hace = $diff->h > 0 ? "hace {$diff->h}h" : "hace {$diff->i}m";
          }
          $_resuelto_nueva = !$necesita_gestion($p);
          $_tag_html_nueva = $hace ? '<span class="partido-tag" style="background:#8b5cf6;color:#fff">🆕 '.$hace.'</span>' : '';
        ?>
        <?= repro_fila_partido($p, $sf, $vc, $_resuelto_nueva, $_tag_html_nueva, 'informe') ?>
        <?php endforeach; ?>
      </div>
    </section>
    <?php endif; ?>

    <!-- 4 KPI cards grandes y simples -->
    <div class="kpi-row">
      <button class="kpi" data-filter="all" onclick="filtrar('all', this)">
        <div class="kpi-num kpi-blue"><?= count($partidos_open) ?></div>
        <div class="kpi-label">Reprogramados totales</div>
        <div class="kpi-sub">Todos los que faltan jugar</div>
      </button>
      <button class="kpi kpi-danger" data-filter="sf" onclick="filtrar('sf', this)">
        <div class="kpi-num kpi-red"><?= count($sin_fecha) ?></div>
        <div class="kpi-label">⚠ Sin fecha</div>
        <div class="kpi-sub">No están agendados</div>
      </button>
      <button class="kpi kpi-warn" data-filter="venc" onclick="filtrar('venc', this)">
        <div class="kpi-num kpi-orange"><?= count($vencidos) ?></div>
        <div class="kpi-label">🔴 Vencidos</div>
        <div class="kpi-sub">Fecha pasó sin jugar</div>
      </button>
      <button class="kpi" data-filter="cf" onclick="filtrar('cf', this)">
        <div class="kpi-num kpi-green"><?= count($con_fecha) ?></div>
        <div class="kpi-label">📅 Con fecha futura</div>
        <div class="kpi-sub">En agenda</div>
      </button>
    </div>

    <!-- Buscador y filtros activos -->
    <div class="filtros-bar">
      <div class="busqueda">
        <svg width="16" height="16" fill="none" stroke="#94a3b8" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
        <input type="text" id="buscar" placeholder="Buscar partido, equipo o liga…" oninput="aplicarFiltros()">
      </div>
      <div class="estado-filtro">
        <button class="estado-btn active" data-estado="all" onclick="filtrarEstado('all', this)">Todos</button>
        <button class="estado-btn" data-estado="pendiente" onclick="filtrarEstado('pendiente', this)">⏳ Pendientes</button>
        <button class="estado-btn" data-estado="resuelto" onclick="filtrarEstado('resuelto', this)">✅ Gestionados</button>
      </div>
      <div id="filtroActivoMsg" class="filtro-activo" style="display:none"></div>
      <button id="btnLimpiar" class="btn-limpiar" onclick="limpiarFiltro()" style="display:none">✕ Limpiar</button>
    </div>

    <!-- ═════════════════════ SECCIÓN 1: SIN FECHA (URGENTE) ═════════════════════ -->
    <?php if (!empty($sin_fecha)): ?>
    <section class="sec-card sec-urgente" data-section="sf">
      <div class="sec-head">
        <div>
          <h2 class="sec-title">⚠ Sin fecha asignada</h2>
          <p class="sec-sub">Resuelve estos primero — los equipos están esperando que se agende</p>
        </div>
        <div class="sec-count danger"><?= count($sin_fecha) ?></div>
      </div>
      <div class="sec-body">
        <?php foreach ($sin_fecha as $p): ?>
          <?= repro_fila_partido($p, true, false, !$necesita_gestion($p), '', 'informe') ?>
        <?php endforeach; ?>
      </div>
    </section>
    <?php endif; ?>

    <!-- ═════════════════════ SECCIÓN 2: VENCIDOS ═════════════════════ -->
    <?php if (!empty($vencidos)): ?>
    <section class="sec-card sec-vencido" data-section="venc">
      <div class="sec-head">
        <div>
          <h2 class="sec-title">🔴 Vencidos</h2>
          <p class="sec-sub">La fecha ya pasó y todavía no se jugaron. Hay que reagendar o cargar el resultado.</p>
        </div>
        <div class="sec-count warn"><?= count($vencidos) ?></div>
      </div>
      <div class="sec-body">
        <?php foreach ($vencidos as $p): ?>
          <?= repro_fila_partido($p, false, true, !$necesita_gestion($p), '', 'informe') ?>
        <?php endforeach; ?>
      </div>
    </section>
    <?php endif; ?>

    <!-- ═════════════════════ SECCIÓN 3: CON FECHA FUTURA (agrupado por día) ═════════════════════ -->
    <?php if (!empty($con_fecha)): ?>
    <?php
      // Agrupar por fecha
      $por_dia = [];
      foreach ($con_fecha as $p) {
          $dia = date('Y-m-d', strtotime($p['fecha_programada']));
          $por_dia[$dia][] = $p;
      }
      ksort($por_dia);
      $dias_es = ['Mon'=>'Lunes','Tue'=>'Martes','Wed'=>'Miércoles','Thu'=>'Jueves','Fri'=>'Viernes','Sat'=>'Sábado','Sun'=>'Domingo'];
      $meses_es = ['01'=>'enero','02'=>'febrero','03'=>'marzo','04'=>'abril','05'=>'mayo','06'=>'junio','07'=>'julio','08'=>'agosto','09'=>'septiembre','10'=>'octubre','11'=>'noviembre','12'=>'diciembre'];
    ?>
    <section class="sec-card sec-futuro" data-section="cf">
      <div class="sec-head">
        <div>
          <h2 class="sec-title">📅 Con fecha futura</h2>
          <p class="sec-sub">Agendados — en orden cronológico, los más próximos arriba</p>
        </div>
        <div class="sec-count info"><?= count($con_fecha) ?></div>
      </div>
      <div class="sec-body">
        <?php foreach ($por_dia as $dia => $ps):
          $ts = strtotime($dia);
          $dia_label = $dias_es[date('D', $ts)] . ' ' . date('d', $ts) . ' de ' . $meses_es[date('m', $ts)] . ' ' . date('Y', $ts);
          $delta_dias = (int)floor(($ts - $hoy->getTimestamp()) / 86400);
          $delta_label = $delta_dias === 0 ? 'HOY' : ($delta_dias === 1 ? 'mañana' : "en $delta_dias días");
        ?>
        <div class="dia-group">
          <div class="dia-header">
            <span class="dia-fecha"><?= $dia_label ?></span>
            <span class="dia-delta"><?= $delta_label ?></span>
            <span class="dia-count"><?= count($ps) ?> <?= count($ps)===1?'partido':'partidos' ?></span>
          </div>
          <?php foreach ($ps as $p): ?>
            <?= repro_fila_partido($p, false, false, !$necesita_gestion($p), '', 'informe') ?>
          <?php endforeach; ?>
        </div>
        <?php endforeach; ?>
      </div>
    </section>
    <?php endif; ?>

    <!-- ═════════════════════ SECCIÓN 4: TOP equipos con más reprogramaciones ═════════════════════ -->
    <?php if (!empty($por_equipo)):
      $top_equipos = array_slice($por_equipo, 0, 10, true);
    ?>
    <section class="sec-card sec-equipos">
      <div class="sec-head">
        <div>
          <h2 class="sec-title">🏷️ Equipos con más partidos reprogramados</h2>
          <p class="sec-sub">Los que más cuelgan — top 10. Click en un equipo para filtrar sus partidos.</p>
        </div>
      </div>
      <div class="sec-body equipos-grid">
        <?php foreach ($top_equipos as $eq_id => $eq):
          $cnt = count($eq['partidos']);
          $sev = $cnt >= 3 ? 'critico' : ($cnt === 2 ? 'medio' : 'normal');
        ?>
          <button class="equipo-card equipo-<?= $sev ?>" data-filter="eq:<?= $eq_id ?>" onclick="filtrar('eq:<?= $eq_id ?>', this)">
            <div class="equipo-cnt"><?= $cnt ?></div>
            <div class="equipo-nombre"><?= epl_h($eq['nombre']) ?></div>
            <div class="equipo-tag"><?= $cnt===1?'partido reprogramado':'partidos reprogramados' ?></div>
          </button>
        <?php endforeach; ?>
      </div>
    </section>
    <?php endif; ?>

    <?php if (empty($partidos_open)): ?>
      <section class="sec-card">
        <div style="padding:3rem;text-align:center;color:var(--gray-400)">
          <div style="font-size:3rem">🎉</div>
          <p style="font-weight:700;margin-top:.5rem">No hay partidos reprogramados</p>
          <p style="font-size:.85rem">Todo el calendario está al día.</p>
        </div>
      </section>
    <?php endif; ?>

    <div id="noResults" style="display:none;padding:2.5rem;text-align:center;background:#fff;border-radius:14px;border:1px solid #e2e8f0;color:#94a3b8;margin-top:1rem">
      <div style="font-size:2.5rem">🔍</div>
      <p style="font-weight:600;margin-top:.5rem">Sin resultados</p>
      <p style="font-size:.82rem">No hay partidos que coincidan con el filtro.</p>
    </div>

    </div><!-- /tab-informe -->

  </main>
</div>

<script>
function reenviarNotifCancha(partidoId, btn) {
  if (!confirm('¿Reenviar notificaciones de cancha a los jugadores y administradores?')) return;
  const prevText = btn.textContent;
  btn.disabled = true;
  btn.textContent = '⏳ Enviando...';
  
  const fd = new FormData();
  fd.append('accion', 'reenviar_notif_cancha');
  fd.append('partido_id', partidoId);
  
  fetch('api_reprogramacion.php', {
    method: 'POST',
    body: fd
  })
  .then(res => res.json())
  .then(data => {
    if (data.ok) {
      alert('✅ Notificaciones enviadas correctamente');
    } else {
      alert('Error al reenviar notificaciones');
    }
  })
  .catch(err => {
    console.error(err);
    alert('Error de conexión');
  })
  .finally(() => {
    btn.disabled = false;
    btn.textContent = prevText;
  });
}

function cambiarTab(tab) {
  document.querySelectorAll('.tab-btn').forEach(b => b.classList.toggle('active', b.dataset.tab === tab));
  document.querySelectorAll('.tab-content').forEach(c => c.style.display = 'none');
  const el = document.getElementById('tab-' + tab);
  if (el) el.style.display = 'block';
  // Persistir tab activa en URL sin recargar
  const url = new URL(window.location.href);
  url.searchParams.set('tab', tab);
  window.history.replaceState({}, '', url);
}

// Toggle Todos / Pendientes / Gestionados dentro de la pestaña Solicitudes
function filtrarSolicitudes(grupo, btn) {
  document.querySelectorAll('[data-solfiltro]').forEach(b => b.classList.toggle('active', b.dataset.solfiltro === grupo));
  document.querySelectorAll('[data-sol-grupo]').forEach(g => {
    g.style.display = (grupo === 'todos' || g.dataset.solGrupo === grupo) ? '' : 'none';
  });
}

// Buscar dentro de la pestaña Solicitudes (pareja, partido o liga) sobre el texto visible de cada fila
function buscarSolicitudes(q) {
  q = (q || '').toLowerCase().trim();
  const cont = document.getElementById('tab-solicitudes');
  if (!cont) return;
  cont.querySelectorAll('.partido-row').forEach(row => {
    row.style.display = (!q || row.textContent.toLowerCase().includes(q)) ? '' : 'none';
  });
  // ocultar secciones que quedaron sin filas visibles (sin tocar las tarjetas de estado vacío)
  cont.querySelectorAll('section.sec-card').forEach(sec => {
    const rows = sec.querySelectorAll('.partido-row');
    if (!rows.length) return;
    const visible = [...rows].some(r => r.style.display !== 'none');
    sec.style.display = (!q || visible) ? '' : 'none';
  });
}

let filtroActual = null;
let filtroEstado = 'all';

function filtrarEstado(tipo, btn) {
  filtroEstado = tipo;
  document.querySelectorAll('.estado-btn').forEach(b => b.classList.toggle('active', b.dataset.estado === tipo));
  aplicarFiltros();
}

function aplicarFiltros() {
  const q = (document.getElementById('buscar').value || '').toLowerCase().trim();
  const sections = document.querySelectorAll('section.sec-card[data-section]');
  let totalVisibles = 0;

  // Si hay filtro de sección o equipo, ocultar las demás secciones
  sections.forEach(sec => {
    const secType = sec.dataset.section;
    let mostrarSec = true;
    if (filtroActual === 'sf'   && secType !== 'sf')   mostrarSec = false;
    if (filtroActual === 'venc' && secType !== 'venc') mostrarSec = false;
    if (filtroActual === 'cf'   && secType !== 'cf')   mostrarSec = false;
    sec.style.display = mostrarSec ? '' : 'none';
  });

  // Filtrar filas por búsqueda, equipo o estado (pendiente/resuelto)
  document.querySelectorAll('.partido-row').forEach(row => {
    let show = true;
    if (q) {
      show = (row.dataset.search || '').includes(q);
    }
    if (filtroActual && filtroActual.startsWith('eq:')) {
      const eqId = filtroActual.split(':')[1];
      const eqs = (row.dataset.eq || '').split(',');
      if (eqs.indexOf(eqId) === -1) show = false;
    }
    if (filtroEstado === 'pendiente' && row.dataset.resuelto === '1') show = false;
    if (filtroEstado === 'resuelto'  && row.dataset.resuelto === '0') show = false;
    row.style.display = show ? '' : 'none';
    if (show) totalVisibles++;
  });

  // Ocultar grupos de día vacíos
  document.querySelectorAll('.dia-group').forEach(g => {
    const visibles = g.querySelectorAll('.partido-row:not([style*="display: none"])').length;
    g.style.display = visibles > 0 ? '' : 'none';
  });

  // Mensaje filtro activo
  const fa = document.getElementById('filtroActivoMsg');
  const btn = document.getElementById('btnLimpiar');
  let msg = '';
  if (filtroActual === 'sf')   msg = '⚠ Solo sin fecha';
  else if (filtroActual === 'venc') msg = '🔴 Solo vencidos';
  else if (filtroActual === 'cf')   msg = '📅 Solo con fecha';
  else if (filtroActual && filtroActual.startsWith('eq:')) {
    const card = document.querySelector('.equipo-card.activo .equipo-nombre');
    msg = '🎾 ' + (card ? card.textContent : 'Equipo');
  }
  if (msg || q) {
    fa.style.display = 'block';
    fa.textContent = (msg || 'Búsqueda activa') + ' · ' + totalVisibles + ' resultado' + (totalVisibles===1?'':'s');
    btn.style.display = 'inline-flex';
  } else {
    fa.style.display = 'none';
    btn.style.display = 'none';
  }

  // No results global
  const noRes = document.getElementById('noResults');
  if (noRes) noRes.style.display = totalVisibles === 0 ? 'block' : 'none';
}

function filtrar(tipo, btn) {
  // Limpiar visual
  document.querySelectorAll('.kpi').forEach(k => k.classList.remove('activo'));
  document.querySelectorAll('.equipo-card').forEach(e => e.classList.remove('activo'));

  if (filtroActual === tipo || tipo === 'all') {
    filtroActual = null;
  } else {
    filtroActual = tipo;
    if (btn) btn.classList.add('activo');
  }
  aplicarFiltros();
  // Scroll suave si filtro aplicado
  if (filtroActual && filtroActual !== 'all') {
    const target = filtroActual.startsWith('eq:')
      ? document.querySelector('.sec-urgente, .sec-vencido, .sec-futuro')
      : document.querySelector('section[data-section]:not([style*="display: none"])');
    if (target) target.scrollIntoView({behavior:'smooth', block:'start'});
  }
}

function limpiarFiltro() {
  document.getElementById('buscar').value = '';
  filtroActual = null;
  filtroEstado = 'all';
  document.querySelectorAll('.kpi').forEach(k => k.classList.remove('activo'));
  document.querySelectorAll('.equipo-card').forEach(e => e.classList.remove('activo'));
  document.querySelectorAll('.estado-btn').forEach(b => b.classList.toggle('active', b.dataset.estado === 'all'));
  aplicarFiltros();
}
</script>

<?php require_once '../includes/footer.php'; ?>
