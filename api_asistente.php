<?php
/**
 * Asistente EPL — motor de intents sin IA externa.
 * POST { pregunta: string } → JSON { respuesta, link|null, sugerencias[] }
 */
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['respuesta' => 'Método no permitido.', 'link' => null, 'sugerencias' => []]);
    exit;
}

$context = trim($_POST['context'] ?? 'player');

// ── Validación de seguridad y sesión por contexto ──────────────────────
if ($context === 'admin') {
    $j = epl_jugador_actual();
    if (!$j) {
        echo json_encode([
            'respuesta' => 'Tu sesión de administrador ha expirado. Por favor, inicia sesión de nuevo.',
            'link' => ['url' => '/login.php', 'texto' => '🔑 Iniciar Sesión'],
            'sugerencias' => []
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    if ($j['rol'] !== 'admin') {
        echo json_encode([
            'respuesta' => 'No tienes permisos de administrador.',
            'link' => null,
            'sugerencias' => []
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
} elseif ($context === 'player') {
    if (!epl_jugador_actual()) {
        echo json_encode([
            'respuesta' => 'Tu sesión ha expirado. Por favor, inicia sesión de nuevo para continuar.',
            'link' => ['url' => '/login.php', 'texto' => '🔑 Iniciar Sesión'],
            'sugerencias' => []
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
} // 'public' no requiere sesión

$pregunta = trim($_POST['pregunta'] ?? '');
if ($pregunta === '') {
    echo json_encode(['respuesta' => 'Escribe tu pregunta 😊', 'link' => null, 'sugerencias' => []]);
    exit;
}

// ── Normalizar texto ──────────────────────────────────────────
function asist_norm(string $s): string {
    $s = mb_strtolower($s, 'UTF-8');
    $s = str_replace(
        ['á','é','í','ó','ú','ñ','ü','à','è','ì','ò','ù','â','ê','î','ô','û'],
        ['a','e','i','o','u','n','u','a','e','i','o','u','a','e','i','o','u'],
        $s
    );
    return $s;
}

function asist_tokens(string $s): array {
    return array_values(array_filter(preg_split('/[\s\W]+/u', asist_norm($s))));
}

// Interceptar si el usuario intenta registrar un resultado conversacionalmente
if ($context === 'player') {
    $jugador = epl_jugador_actual();
    if ($jugador) {
        $preg_norm = asist_norm($pregunta);
        // Buscar sets en el texto (ej: 6-3 6-4, 7-6 2-6 6-3)
        preg_match_all('/(\d+)\s*[-–/]\s*(\d+)/', $pregunta, $set_matches, PREG_SET_ORDER);
        
        $has_score = !empty($set_matches) && count($set_matches) >= 1 && count($set_matches) <= 3;
        $is_intent = false;
        
        // Palabras clave que indican registro de marcador
        $keywords_reg = ['jugamos', 'jugue', 'ganamos', 'gane', 'perdimos', 'perdi', 'resultado', 'marcador', 'sets', 'score', 'anota', 'registra', 'cargue', 'cargar', 'anotar'];
        foreach ($keywords_reg as $kw) {
            if (str_contains($preg_norm, $kw)) {
                $is_intent = true;
                break;
            }
        }
        
        if ($has_score && $is_intent) {
            $db = epl_db();
            $liga = epl_liga_activa();
            $equipo = $liga ? epl_equipo_del_jugador($jugador['id'], $liga['id']) : null;
            
            if (!$equipo) {
                echo json_encode([
                    'respuesta' => 'Lo siento, no he podido registrar el resultado porque no tienes un equipo activo en la liga actual.',
                    'link' => ['url' => '/inscribirse.php', 'texto' => '🏅 Ver Competiciones'],
                    'sugerencias' => ['¿Cómo me inscribo a un torneo?', '¿Cómo me registro?']
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }
            
            // Buscar partidos pendientes del equipo del jugador en la liga activa ordenados por prioridad
            $stP = $db->prepare("
                SELECT p.*,
                       el.nombre AS local_nombre,
                       ev.nombre AS visitante_nombre,
                       CASE WHEN p.fecha_programada IS NOT NULL AND p.fecha_programada < NOW() AND DATE(p.fecha_programada) != '2026-12-31' THEN 1 ELSE 0 END AS vencido
                FROM partidos p
                JOIN equipos el ON el.id = p.equipo_local_id
                JOIN equipos ev ON ev.id = p.equipo_visitante_id
                WHERE p.liga_id = ?
                  AND (p.equipo_local_id = ? OR p.equipo_visitante_id = ?)
                  AND p.estado IN ('pendiente', 'reprogramado')
                ORDER BY
                    vencido DESC,
                    p.fecha_programada ASC,
                    p.id ASC
            ");
            $stP->execute([$liga['id'], $equipo['id'], $equipo['id']]);
            $partidos_pendientes = $stP->fetchAll(PDO::FETCH_ASSOC);
            
            if (empty($partidos_pendientes)) {
                echo json_encode([
                    'respuesta' => 'No encontré ningún partido pendiente o reprogramado para tu equipo en la liga actual.',
                    'link' => ['url' => '/dashboard.php', 'texto' => '🏠 Ir a Inicio'],
                    'sugerencias' => ['¿Cómo veo mis próximos partidos?', '¿Cómo veo la tabla de clasificación?']
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }
            
            $partido_seleccionado = null;
            
            if (count($partidos_pendientes) === 1) {
                $partido_seleccionado = $partidos_pendientes[0];
            } else {
                // Si hay múltiples, buscar coincidencia por nombre/apellido del rival
                $mejor_coincidencia = null;
                $max_score = 0;
                
                foreach ($partidos_pendientes as $p) {
                    $op_nombre = ($p['equipo_local_id'] == $equipo['id']) ? $p['visitante_nombre'] : $p['local_nombre'];
                    $op_tokens = asist_tokens($op_nombre);
                    
                    $score_op = 0;
                    foreach ($op_tokens as $t) {
                        if (str_contains($preg_norm, $t)) {
                            $score_op++;
                        }
                    }
                    
                    if ($score_op > $max_score) {
                        $max_score = $score_op;
                        $mejor_coincidencia = $p;
                    }
                }
                
                if ($max_score > 0) {
                    $partido_seleccionado = $mejor_coincidencia;
                } else {
                    // Si no coincide con ninguna mención, seleccionamos el más prioritario por defecto (atrasado o próximo)
                    $partido_seleccionado = $partidos_pendientes[0];
                }
            }
            
            if (!$partido_seleccionado) {
                // Listar rivales posibles
                $oponentes = [];
                foreach ($partidos_pendientes as $p) {
                    $op_n = ($p['equipo_local_id'] == $equipo['id']) ? $p['visitante_nombre'] : $p['local_nombre'];
                    $oponentes[] = "• **" . $op_n . "**";
                }
                echo json_encode([
                    'respuesta' => "Encontré múltiples partidos pendientes para tu equipo. 😅 ¿Contra cuál de estas parejas jugaste?\n\n" . implode("\n", $oponentes) . "\n\nPor favor, repítemelo indicando el rival (ej: *\"jugamos contra " . strip_tags($partidos_pendientes[0]['local_nombre']) . " y ganamos 6-3 6-4\"*).",
                    'link' => ['url' => '/ingresar_resultado.php', 'texto' => '🏆 Ingresar Marcador Manual'],
                    'sugerencias' => []
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }
            
            // Determinar si el usuario ganó o perdió
            $gano = null;
            if (str_contains($preg_norm, 'ganamos') || str_contains($preg_norm, 'gane') || str_contains($preg_norm, 'victoria') || str_contains($preg_norm, 'vencimos')) {
                $gano = true;
            } elseif (str_contains($preg_norm, 'perdimos') || str_contains($preg_norm, 'perdi') || str_contains($preg_norm, 'derrota')) {
                $gano = false;
            }
            
            // Contar sets de izquierda/derecha para validar intención
            $left_won = 0; $right_won = 0;
            foreach ($set_matches as $m) {
                if ((int)$m[1] > (int)$m[2]) $left_won++; else $right_won++;
            }
            
            if ($gano === null) {
                $gano = ($left_won > $right_won);
            }
            
            // Mapear scores (izq es del usuario si coinciden intenciones de victoria)
            $sets_usuario = [];
            $sets_rival = [];
            $izq_es_usuario = ($gano && $left_won >= $right_won) || (!$gano && $left_won <= $right_won);
            
            foreach ($set_matches as $m) {
                $g1 = (int)$m[1];
                $g2 = (int)$m[2];
                if ($izq_es_usuario) {
                    $sets_usuario[] = $g1;
                    $sets_rival[] = $g2;
                } else {
                    $sets_usuario[] = $g2;
                    $sets_rival[] = $g1;
                }
            }
            
            $es_local = ($partido_seleccionado['equipo_local_id'] == $equipo['id']);
            
            $games_s1_local = null; $games_s1_visitante = null;
            $games_s2_local = null; $games_s2_visitante = null;
            $games_s3_local = null; $games_s3_visitante = null;
            
            if ($es_local) {
                $games_s1_local = $sets_usuario[0] ?? null; $games_s1_visitante = $sets_rival[0] ?? null;
                $games_s2_local = $sets_usuario[1] ?? null; $games_s2_visitante = $sets_rival[1] ?? null;
                $games_s3_local = $sets_usuario[2] ?? null; $games_s3_visitante = $sets_rival[2] ?? null;
            } else {
                $games_s1_local = $sets_rival[0] ?? null; $games_s1_visitante = $sets_usuario[0] ?? null;
                $games_s2_local = $sets_rival[1] ?? null; $games_s2_visitante = $sets_usuario[1] ?? null;
                $games_s3_local = $sets_rival[2] ?? null; $games_s3_visitante = $sets_usuario[2] ?? null;
            }
            
            // Calcular sets totales
            $sets_local = 0; $sets_vis = 0;
            if ($games_s1_local !== null && $games_s1_visitante !== null) {
                if ($games_s1_local > $games_s1_visitante) $sets_local++; else $sets_vis++;
            }
            if ($games_s2_local !== null && $games_s2_visitante !== null) {
                if ($games_s2_local > $games_s2_visitante) $sets_local++; else $sets_vis++;
            }
            if ($games_s3_local !== null && $games_s3_visitante !== null) {
                if ($games_s3_local > $games_s3_visitante) $sets_local++; else $sets_vis++;
            }
            
            $ganador_id = ($sets_local > $sets_vis) ? $partido_seleccionado['equipo_local_id'] : $partido_seleccionado['equipo_visitante_id'];
            $ahora = date('Y-m-d H:i:s');
            
            // Guardar resultado
            $db->prepare("
                UPDATE partidos SET
                  estado='jugado', fecha_jugado=?,
                  sets_local=?, sets_visitante=?,
                  games_s1_local=?, games_s1_visitante=?,
                  games_s2_local=?, games_s2_visitante=?,
                  games_s3_local=?, games_s3_visitante=?,
                  ganador_id=?, ingresado_por=?,
                  resultado_ingresado_at=?
                WHERE id=?
            ")->execute([
                $ahora,
                $sets_local, $sets_vis,
                $games_s1_local, $games_s1_visitante,
                $games_s2_local, $games_s2_visitante,
                $games_s3_local, $games_s3_visitante,
                $ganador_id, $jugador['id'], $ahora, $partido_seleccionado['id']
            ]);
            
            // Recalcular clasificación
            epl_recalcular_clasificacion($liga['id']);
            
            // Notificaciones
            $rival_id = $es_local ? $partido_seleccionado['equipo_visitante_id'] : $partido_seleccionado['equipo_local_id'];
            $rival_nombre = $es_local ? $partido_seleccionado['visitante_nombre'] : $partido_seleccionado['local_nombre'];
            $mi_nombre = $equipo['nombre'];
            $resultado_sets = implode(' / ', array_filter(array_map(function($u, $r) {
                return ($u !== null && $r !== null) ? "{$u}-{$r}" : null;
            }, $sets_usuario, $sets_rival)));
            
            $asunto_res = epl_mail_asunto('⚽ Resultado ingresado', $partido_seleccionado['local_nombre'], $partido_seleccionado['visitante_nombre'], $partido_seleccionado['jornada'] ?? null);
            $nombre_quien_ingresa = trim(($jugador['nombre'] ?? '') . ' ' . ($jugador['apellido'] ?? ''));
            $texto_rival_subtitulo = "El jugador {$nombre_quien_ingresa} ingresó el resultado de tu partido {$partido_seleccionado['local_nombre']} vs {$partido_seleccionado['visitante_nombre']} (Jornada " . ($partido_seleccionado['jornada'] ?? '—') . ").";
            $texto_rival_tip = "⚠️ En caso de tener algún problema con el resultado contáctate con los organizadores (tienes 24 horas para reclamar).";
            $url_reclamar = epl_url("reclamar_resultado.php?partido_id={$partido_seleccionado['id']}");
            
            // Notificar rivales
            $re_st = $db->prepare("SELECT jugador1_id, jugador2_id FROM equipos WHERE id = ?");
            $re_st->execute([$rival_id]);
            $re = $re_st->fetch(PDO::FETCH_ASSOC);
            $rivales_ids = array_values(array_filter([ (int)($re['jugador1_id'] ?? 0), (int)($re['jugador2_id'] ?? 0) ]));
            $ganador_nombre = ($ganador_id == $equipo['id']) ? $mi_nombre : $rival_nombre;
            
            foreach ($rivales_ids as $rival_jugador_id) {
                epl_notif_crear($rival_jugador_id, 'resultado', $asunto_res, $texto_rival_subtitulo . ' ' . $texto_rival_tip, $url_reclamar, true);
                epl_mail_partido_visual($rival_jugador_id, $asunto_res, $partido_seleccionado['local_nombre'], $partido_seleccionado['visitante_nombre'], [
                    ['icon' => '🏆', 'label' => 'Ganador',   'valor' => $ganador_nombre],
                    ['icon' => '🎾', 'label' => 'Resultado', 'valor' => $resultado_sets],
                ], $texto_rival_subtitulo, $texto_rival_tip, $url_reclamar, '⚠️ Reclamar Resultado');
            }
            
            // Notificar admins
            $admins_st = $db->query("SELECT id FROM jugadores WHERE rol = 'admin'");
            $admins_ids = $admins_st->fetchAll(PDO::FETCH_COLUMN);
            $fecha_hora_fmt = date('d/m/Y H:i', strtotime($ahora));
            $texto_admin_subtitulo = "El jugador {$nombre_quien_ingresa} registró el resultado {$resultado_sets} del partido {$partido_seleccionado['local_nombre']} vs {$partido_seleccionado['visitante_nombre']} de la jornada " . ($partido_seleccionado['jornada'] ?? '—') . ".";
            $texto_admin_tip = "Fecha y hora de registro: {$fecha_hora_fmt}";
            $url_admin = epl_url("admin/partido_detalle.php?id={$partido_seleccionado['id']}");
            
            foreach ($admins_ids as $admin_id) {
                epl_notif_crear((int)$admin_id, 'resultado', $asunto_res, $texto_admin_subtitulo . ' ' . $texto_admin_tip, $url_admin, true);
                epl_mail_partido_visual((int)$admin_id, $asunto_res, $partido_seleccionado['local_nombre'], $partido_seleccionado['visitante_nombre'], [
                    ['icon' => '🏆', 'label' => 'Ganador',   'valor' => $ganador_nombre],
                    ['icon' => '🎾', 'label' => 'Resultado', 'valor' => $resultado_sets],
                ], $texto_admin_subtitulo, $texto_admin_tip, $url_admin, '🔍 Ver Detalle');
            }
            
            $resultado_final_str = ($ganador_id == $equipo['id']) ? "ganando" : "perdiendo";
            
            echo json_encode([
                'respuesta' => "✅ **¡Marcador registrado con éxito por el chat!**\n\nHe anotado el partido contra **" . ($es_local ? $partido_seleccionado['visitante_nombre'] : $partido_seleccionado['local_nombre']) . "** {$resultado_final_str} por **" . implode(" / ", array_map(function($u, $r) { return "{$u}-{$r}"; }, $sets_usuario, $sets_rival)) . "**.\n\nTu rival ha recibido la notificación y tiene 24 horas para disputar el marcador si hay algún error.",
                'link' => ['url' => '/dashboard.php', 'texto' => '🏠 Ir a Inicio'],
                'sugerencias' => ['¿Cómo veo la tabla de clasificación?', '¿Cuándo es mi próximo partido?']
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }
}

// ── Intents para ADMIN ────────────────────────────────────────
$intents_admin = [
    [
        'id'       => 'resultados_admin',
        'kw'       => ['resultado','resultados','registrar','ingresar','cambiar resultado','editar resultado',
                       'corregir resultado','marcador','puntaje','score','modificar resultado','sets','games','anotar'],
        'peso'     => 2,
        'resp'     => 'Para **gestionar resultados** como administrador:\n1. Ve a la sección **Partidos** en el menú.\n2. Busca el partido por liga/jornada o usando los filtros.\n3. Haz clic en **Editar/Ver detalle** de ese partido.\n4. Desde el detalle del partido, puedes ingresar o modificar el resultado set a set.\n\n💡 Recuerda que cuando un jugador ingresa un resultado, su rival tiene 24 horas para disputarlo antes de que quede confirmado automáticamente.',
        'link'     => ['url' => '/admin/partidos.php', 'texto' => '🎾 Gestionar Partidos'],
        'sug'      => ['¿Cómo gestiono reprogramaciones?','¿Cómo resuelvo una disputa?'],
    ],
    [
        'id'       => 'reprogramar_admin',
        'kw'       => ['reprogramar','reprogramacion','reprogramaciones','cambiar fecha','aprobar reprogramacion',
                       'rechazar reprogramacion','solicitudes','cambio fecha','reagendar','mover partido'],
        'peso'     => 2,
        'resp'     => 'Para **gestionar reprogramaciones**:\n1. En tu **Inicio de Admin** verás la sección de "Solicitudes de reprogramación" pendientes.\n2. Haz clic en **Aprobar** para asignar la fecha final y cancha definitiva, o **Rechazar** si corresponde.\n3. También puedes ver todo el historial y solicitudes pendientes en **Competencia → Reprogramaciones**.',
        'link'     => ['url' => '/admin/dashboard_repro.php', 'texto' => '📅 Ver Reprogramaciones'],
        'sug'      => ['¿Cómo asigno una cancha?','¿Cómo edito un partido?'],
    ],
    [
        'id'       => 'disputas_admin',
        'kw'       => ['disputa','disputas','reclamar','reclamo','reclamos','resultado disputado','impugnar',
                       'resultado incorrecto','error resultado','marcador incorrecto'],
        'peso'     => 2,
        'resp'     => 'Cuando un jugador disputa un resultado:\n1. Recibirás una notificación y aparecerá una alerta en el panel.\n2. Ve a **Competencia → Disputas**.\n3. Revisa el comentario del jugador que reclama y el marcador propuesto.\n4. Puedes contactar a las parejas y luego modificar el marcador definitivo en el detalle del partido.',
        'link'     => ['url' => '/admin/disputas.php', 'texto' => '⚠️ Ver Disputas'],
        'sug'      => ['¿Cómo modifico un resultado?','¿Cómo contacto a un jugador?'],
    ],
    [
        'id'       => 'inscripciones_admin',
        'kw'       => ['inscripcion','inscripciones','inscribir','aprobar inscripcion','rechazar inscripcion',
                       'validar pago','pendiente','aprobar pareja','parejas inscritas'],
        'peso'     => 2,
        'resp'     => 'Para **gestionar inscripciones**:\n1. Ve a **Competencia → Inscripciones** en el menú lateral.\n2. Verás las solicitudes de parejas pendientes de aprobación.\n3. Verifica que hayan realizado el pago de la inscripción.\n4. Presiona **Aprobar** para asignarlos formalmente a la categoría seleccionada, o **Rechazar** si hay algún inconveniente.',
        'link'     => ['url' => '/admin/inscripciones.php', 'texto' => '📝 Ver Inscripciones'],
        'sug'      => ['¿Cómo creo una liga?','¿Cómo agrego jugadores?'],
    ],
    [
        'id'       => 'jugadores_admin',
        'kw'       => ['jugador','jugadores','crear jugador','editar jugador','buscar jugador','cambiar clave',
                       'resetear contrasena','cambiar correo','bloquear jugador','rol','hacer admin','datos jugador'],
        'peso'     => 2,
        'resp'     => 'Para **gestionar jugadores**:\n1. Ve a **Jugadores** en el menú.\n2. Usa el buscador para encontrar al jugador por nombre, apellido o email.\n3. Haz clic en **Editar** para cambiar sus datos, cambiar su rol (hacerlo admin/jugador), o resetear su contraseña generando una clave provisoria que le llegará a su correo.',
        'link'     => ['url' => '/admin/jugadores.php', 'texto' => '👤 Gestionar Jugadores'],
        'sug'      => ['¿Cómo creo un equipo?','¿Cómo veo las categorías?'],
    ],
    [
        'id'       => 'notificaciones_admin',
        'kw'       => ['notificacion','notificaciones','enviar aviso','correo masivo','enviar mail','push masivo',
                       'alertas','alerta masiva','avisar','comunicacion','mensajes'],
        'peso'     => 2,
        'resp'     => 'Para **enviar avisos masivos** (correos y notificaciones push):\n1. Ve a **Comunicación → Notificaciones** en el menú.\n2. Selecciona si quieres enviar un Correo, una Alerta Push o Ambos.\n3. Filtra los destinatarios (todos, por liga específica, o por categorías).\n4. Escribe el asunto y el mensaje, y presiona **Enviar**.',
        'link'     => ['url' => '/admin/notificaciones.php', 'texto' => '🔔 Enviar Notificaciones'],
        'sug'      => ['¿Cómo pruebo si funcionan las notificaciones?'],
    ],
    [
        'id'       => 'finanzas_admin',
        'kw'       => ['finanzas','pagos','cobros','ingresos','egresos','caja','erps','financiero',
                       'dinero','balance','resumen financiero','gasto','gastos'],
        'peso'     => 2,
        'resp'     => 'El **ERP Financiero** te permite llevar el control de la liga:\n1. Ve a **Finanzas → ERP Financiero**.\n2. Aquí puedes registrar un nuevo ingreso (como auspicios o inscripciones manuales) o un egreso (pago de canchas, premios, pelotas, etc.).\n3. Verás un balance en tiempo real con gráficos y resumen de cobros pendientes.',
        'link'     => ['url' => '/admin/erp_financiero.php', 'texto' => '💵 ERP Financiero'],
        'sug'      => ['¿Cómo veo las inscripciones pendientes?'],
    ],
    [
        'id'       => 'ligas_admin',
        'kw'       => ['liga','ligas','crear liga','nueva liga','categoria','categorias','grupos',
                       'fixture','generar fixture','generar partidos','fases'],
        'peso'     => 2,
        'resp'     => 'Para **gestionar ligas y categorías**:\n1. Ve a **Competencia → Ligas**.\n2. Puedes crear una nueva liga, definir sus categorías y grupos.\n3. Una vez cerradas las inscripciones, puedes generar el fixture y calendario de partidos automáticamente desde la configuración de la categoría.',
        'link'     => ['url' => '/admin/ligas.php', 'texto' => '🏆 Gestionar Ligas'],
        'sug'      => ['¿Cómo creo un recinto o cancha?'],
    ],
    [
        'id'       => 'recintos_admin',
        'kw'       => ['sede','recinto','cancha','canchas','agregar cancha','crear recinto','club','clubes'],
        'peso'     => 2,
        'resp'     => 'Para **gestionar recintos y canchas**:\n1. Ve a **Configuración → Sedes/Recintos**.\n2. Puedes crear clubes (recinto superior) y agregar canchas específicas dentro de cada club.\n3. Esto facilitará que al aprobar reprogramaciones o crear partidos puedas seleccionarlas en un menú desplegable ordenado.',
        'link'     => ['url' => '/admin/recintos.php', 'texto' => '🏛️ Gestionar Sedes'],
        'sug'      => ['¿Cómo creo una nueva liga?'],
    ],
    [
        'id'       => 'saludo_admin',
        'kw'       => ['hola','buenas','buen dia','buenos dias','buenas tardes','buenas noches','hey','hola admin'],
        'peso'     => 1,
        'resp'     => '¡Hola, Administrador! 👋 Soy tu asistente de *Elite Padel League*. Puedo guiarte en el uso del panel para gestionar ligas, reprogramaciones, resultados, notificaciones y finanzas. ¿Qué deseas consultar?',
        'link'     => null,
        'sug'      => ['¿Cómo edito un resultado?','¿Cómo apruebo una inscripción?','¿Cómo gestiono reprogramaciones?'],
    ],
];

// ── Intents para PUBLIC (Landing/Registro/Login) ─────────────
$intents_public = [
    [
        'id'       => 'registro_pub',
        'kw'       => ['registrar','registro','crear cuenta','unirse','inscribirse liga','inscribirme',
                       'cuenta nueva','como entro','formulario','registrarse'],
        'peso'     => 2,
        'resp'     => 'Para **crear tu cuenta** en Elite Padel League:\n1. Ve a la página de **Registro**.\n2. Completa tus datos personales, comuna, teléfono, y tu perfil deportivo (categoría, lado de juego, marca de pala, frecuencia).\n3. Al finalizar, el sistema te enviará automáticamente un email con una **contraseña provisoria**.\n4. Usa esa contraseña para iniciar sesión por primera vez, y luego cámbiala por una de tu preferencia.',
        'link'     => ['url' => '/registro.php', 'texto' => '📝 Crear Cuenta'],
        'sug'      => ['No me llega el correo con la contraseña','¿Qué categoría me corresponde?'],
    ],
    [
        'id'       => 'inscripcion_pub',
        'kw'       => ['inscribirse torneo','inscribirme liga','inscribir pareja','jugar torneo',
                       'jugar liga','precio','costo','inscribir','unirse torneo'],
        'peso'     => 2,
        'resp'     => 'Para **inscribirte en una competición activa**:\n1. Primero debes registrarte e iniciar sesión.\n2. Ve a **Inscripciones** en el menú principal.\n3. Selecciona la liga o torneo en el que deseas participar.\n4. Ingresa los datos de tu pareja de juego (ambos deben estar registrados, o puedes ingresar su correo).\n5. Sigue las instrucciones de pago que indique el sistema.\n6. El administrador validará el pago y confirmará tu inscripción.',
        'link'     => ['url' => '/inscribirse.php', 'texto' => '🏅 Ver Competiciones'],
        'sug'      => ['¿Qué pasa si no tengo pareja?','¿Cómo me registro?'],
    ],
    [
        'id'       => 'pareja_pub',
        'kw'       => ['sin pareja','no tengo pareja','buscar pareja','jugar solo','pareja suplente','singles'],
        'peso'     => 2,
        'resp'     => 'Elite Padel League es una competencia en modalidad de **dobles**.\n\n- **Si tienes pareja**: Puedes inscribirla ingresando su correo al momento de inscribirte en la liga.\n- **Si no tienes pareja**: Te sugerimos unirte al grupo oficial de WhatsApp de la comunidad para buscar partner, o registrarte y marcar en tu perfil que estás disponible para que otros jugadores te contacten.',
        'link'     => null,
        'sug'      => ['¿Cómo me registro?','¿Qué categoría me corresponde?'],
    ],
    [
        'id'       => 'contrasena_pub',
        'kw'       => ['contrasena provisoria','no me llega','no me llego','no recibi','contrasena temporal',
                       'password temporal','error correo','correo temporal','no me llega el mail','no puedo entrar','olvide clave'],
        'peso'     => 3,
        'resp'     => 'Si te registraste pero **no has recibido el email** con tu contraseña provisoria:\n1. Revisa tu carpeta de **Spam o Correo No Deseado**.\n2. Asegúrate de haber escrito correctamente tu correo electrónico.\n3. Si sigues sin recibirlo, ve a **Recuperar Contraseña** e ingresa tu email para solicitar una nueva clave.\n4. Si el problema persiste, contacta al soporte de la liga por WhatsApp.',
        'link'     => ['url' => '/recuperar.php', 'texto' => '🔑 Recuperar Contraseña'],
        'sug'      => ['¿Cómo contacto al soporte?','¿Cómo me registro?'],
    ],
    [
        'id'       => 'categorias_pub',
        'kw'       => ['categoria','categorias','nivel','niveles','que categoria','mi nivel',
                       'masculino','femenino','drive','reves'],
        'peso'     => 2,
        'resp'     => 'Las categorías en Elite Padel League se dividen en:\n\n- 👨 **Masculinas (1ª a 6ª)**: Donde 1ª es el nivel más avanzado (profesional/federado) y 6ª es el nivel inicial/principiantes.\n- 👩 **Femeninas (Categorías A a D)**: Donde A es el nivel más alto y D es el nivel inicial.\n\nAl registrarte debes seleccionar tu categoría. Si no estás seguro, te aconsejamos inscribirte en la categoría en la que sueles jugar habitualmente o consultar al soporte de la liga.',
        'link'     => null,
        'sug'      => ['¿Cómo me registro?','¿Cómo me inscribo a un torneo?'],
    ],
    [
        'id'       => 'contacto_pub',
        'kw'       => ['contacto','soporte','whatsapp','telefono','ayuda','hablar con alguien','organizadores','organizador','administrador'],
        'peso'     => 2,
        'resp'     => 'Puedes contactar a los organizadores de **Elite Padel League** enviando un mensaje directo de WhatsApp al número de soporte de la liga (el enlace está disponible en el pie de página de la web principal) o por el correo oficial de la liga. Estamos para ayudarte con cualquier duda sobre inscripciones o registro.',
        'link'     => null,
        'sug'      => ['¿Cómo me registro?','¿Cómo me inscribo a un torneo?'],
    ],
    [
        'id'       => 'saludo_pub',
        'kw'       => ['hola','buenas','buen dia','buenos dias','buenas tardes','buenas noches','hey'],
        'peso'     => 1,
        'resp'     => '¡Hola! 👋 Soy el asistente de *Elite Padel League*. Estoy aquí para ayudarte con el registro de cuenta, inscripciones en torneos, dudas sobre categorías o contacto de soporte. ¿En qué puedo ayudarte?',
        'link'     => null,
        'sug'      => ['¿Cómo me registro?','¿Cómo me inscribo a un torneo?','¿Qué pasa si no tengo pareja?'],
    ],
];

// ── Intents para JUGADOR (Existentes) ────────────────────────
$intents_player = [
    [
        'id'       => 'resultado',
        'kw'       => ['resultado','resultados','registrar','ingresar','puntaje','marcador','anotar',
                       'sets','games','gane','perdi','ganamos','perdimos','cargue','cargar','score',
                       'puntos','partido jugado','finalizo','termino'],
        'peso'     => 2,
        'resp'     => 'Para *registrar el resultado* de un partido:\n1. Ve a **Ingresar Resultado**\n2. Selecciona tu partido pendiente\n3. Completa el marcador set a set\n4. Confirma — tu rival recibirá una notificación con 24 horas para reclamar si hay un error',
        'link'     => ['url' => '/ingresar_resultado.php', 'texto' => '🏆 Ingresar Resultado'],
        'sug'      => ['¿Cuánto tiempo tiene el rival para reclamar?','¿Qué pasa si el rival disputa?'],
    ],
    [
        'id'       => 'reclamar',
        'kw'       => ['reclamar','reclamo','disputar','disputa','resultado mal','marcador mal',
                       'marcador incorrecto','resultado incorrecto','resultado equivocado',
                       'error en el resultado','score malo','sets malos','impugnar','objetar',
                       'no es correcto','esta mal el marcador'],
        'peso'     => 3,
        'resp'     => 'Si el marcador está incorrecto, puedes *reclamarlo* en las **24 horas** siguientes a que fue ingresado:\n1. Abre el correo o notificación que recibiste\n2. Toca **"⚠️ Reclamar Resultado"**\n3. Describe el error con detalle\n4. El administrador será notificado y lo resolverá\n\n⏱️ Pasadas las 24 horas, el resultado queda confirmado.',
        'link'     => ['url' => '/tutoriales.php', 'texto' => '⚠️ Ver Tutorial Reclamos'],
        'sug'      => ['¿Cuánto tiempo tengo para reclamar?','¿Quién puede reclamar un resultado?','¿Cómo contacto al admin?'],
    ],
    [
        'id'       => 'reprogramar',
        'kw'       => ['reprogramar','reprogramacion','reprogramación','cambiar fecha','nueva fecha',
                       'posponer','aplazar','mover partido','cambiar horario','reagendar','postergar',
                       'cambio de fecha','no puedo el dia','no puedo jugar ese dia'],
        'peso'     => 2,
        'resp'     => 'Para *reprogramar un partido*:\n1. Coordina primero con tu rival la nueva fecha\n2. Ve a **Reprogramar Partido**\n3. Selecciona el partido\n4. Indica la fecha propuesta y el motivo\n5. El administrador lo confirmará',
        'link'     => ['url' => '/reprogramar.php', 'texto' => '📅 Reprogramar Partido'],
        'sug'      => ['¿Qué pasa si el rival no responde?','¿Puedo reprogramar más de una vez?'],
    ],
    [
        'id'       => 'clasificacion',
        'kw'       => ['clasificacion','clasificación','tabla','posicion','posición','ranking',
                       'standings','puestos','posiciones','primero','segundo','lider','lideres',
                       'cuanto tengo','cuantos puntos','mis puntos'],
        'peso'     => 2,
        'resp'     => 'En la *tabla de clasificación* ves las posiciones de todos los equipos: puntos, partidos jugados, ganados, perdidos y diferencia de games.',
        'link'     => ['url' => '/clasificacion.php', 'texto' => '📊 Ver Clasificación'],
        'sug'      => ['¿Cómo se calculan los puntos?','¿Cuándo termina la liga?'],
    ],
    [
        'id'       => 'partidos',
        'kw'       => ['partido','partidos','calendario','proximo','proximos','cuando juego',
                       'cuando es','horario','agenda','mis partidos','ver partidos','inicio',
                       'dashboard','proxima fecha','fecha partido'],
        'peso'     => 1,
        'resp'     => 'En tu *Inicio (Dashboard)* ves todos tus **próximos partidos** con fecha, hora y cancha. También hay un historial con los resultados de partidos ya jugados.',
        'link'     => ['url' => '/dashboard.php', 'texto' => '🏠 Ir al Inicio'],
        'sug'      => ['¿Cómo registro un resultado?','¿Cómo reprogramo?'],
    ],
    [
        'id'       => 'inscripcion',
        'kw'       => ['inscribirse','inscripcion','inscripción','torneo','liga','unirse','participar',
                       'registrarse','entrar','categoria','categoría','anotarse','nueva liga',
                       'nuevo torneo','quiero jugar','como entro'],
        'peso'     => 2,
        'resp'     => 'Para *inscribirte en una liga o torneo*:\n1. Ve a **Inscripciones**\n2. Revisa las competiciones abiertas y sus bases\n3. Presiona Inscribirme e ingresa los datos de tu pareja\n4. El administrador confirma y recibes notificación\n\n💡 Necesitas tener pareja para inscribirte.',
        'link'     => ['url' => '/inscribirse.php', 'texto' => '🏅 Ver Inscripciones'],
        'sug'      => ['¿Puedo inscribirme solo?','¿Cuándo cierran las inscripciones?'],
    ],
    [
        'id'       => 'notificaciones',
        'kw'       => ['notificacion','notificaciones','notificación','alerta','alertas','aviso',
                       'avisos','activar','push','no me llegan','no recibo','no llegan','sonido',
                       'silencio'],
        'peso'     => 2,
        'resp'     => 'Para *activar las notificaciones*:\n1. Ve al **Inicio** de la app\n2. Toca el banner dorado **"Activar notificaciones"**\n3. Acepta el permiso\n\n¿No aparece el banner? Entra al Inicio directamente 👇',
        'link'     => ['url' => '/dashboard.php', 'texto' => '🔔 Ir al Inicio'],
        'sug'      => ['No me llegó la pregunta de permiso','Tengo iPhone, ¿cómo activo?'],
    ],
    [
        'id'       => 'notif_iphone',
        'kw'       => ['iphone','ios','safari','apple','no me pregunto','no me llega iphone',
                       'ajustes iphone','configuracion iphone'],
        'peso'     => 3,
        'resp'     => 'En *iPhone* las notificaciones requieren:\n1. Tener la app instalada desde **Safari** (no Chrome)\n2. Abrirla tocando el **ícono** en tu pantalla (no desde Safari)\n3. Tocar el banner **"Activar notificaciones"** en el Inicio\n\n¿Ya hiciste todo eso y nada? Ve a **Ajustes del iPhone → busca "Elite Padel" → Notificaciones → Activar**.\n\nRequiere iOS 16.4 o superior.',
        'link'     => ['url' => '/tutoriales.php', 'texto' => '📖 Ver Tutorial iPhone'],
        'sug'      => ['¿Cómo instalo la app en iPhone?'],
    ],
    [
        'id'       => 'instalar',
        'kw'       => ['instalar','descargar','app','aplicacion','aplicación','icono','ícono',
                       'pantalla inicio','home screen','agregar pantalla','como instalo',
                       'bajar app','bajar la app','quiero la app'],
        'peso'     => 2,
        'resp'     => 'Puedes instalar *Elite Padel League* como app en tu teléfono:\n\n🤖 **Android**: Chrome → menú 3 puntos → «Instalar app» o «Añadir a pantalla de inicio»\n\n🍎 **iPhone**: Safari (obligatorio) → botón compartir → «Añadir a pantalla de inicio»\n\nEl tutorial tiene los pasos con imágenes 👇',
        'link'     => ['url' => '/tutoriales.php', 'texto' => '📲 Tutorial Instalación'],
        'sug'      => ['¿Funciona en iPhone?','¿Necesito la Play Store?'],
    ],
    [
        'id'       => 'perfil',
        'kw'       => ['perfil','datos','informacion','información','foto','contraseña','password',
                       'nombre','email','correo','cambiar datos','actualizar','mis datos',
                       'cambiar correo','cambiar clave'],
        'peso'     => 2,
        'resp'     => 'En *Mi Perfil* puedes actualizar tus datos personales, cambiar la foto y modificar tu contraseña.',
        'link'     => ['url' => '/mi_perfil.php', 'texto' => '👤 Ir a Mi Perfil'],
        'sug'      => ['¿Cómo cambio mi foto?','¿Puedo cambiar mi correo?'],
    ],
    [
        'id'       => 'suplentes',
        'kw'       => ['suplente','suplentes','reemplazo','reemplazar','lesion','lesión',
                       'no puedo jugar','falta al partido','no voy a poder','reemplazante'],
        'peso'     => 2,
        'resp'     => 'Si no puedes jugar un partido, puedes registrar un *suplente* que juegue en tu lugar. Ve a **Suplentes** y regístralo antes del partido.',
        'link'     => ['url' => '/mis_suplentes.php', 'texto' => '🔄 Ir a Suplentes'],
        'sug'      => ['¿Hasta cuándo puedo registrar suplente?'],
    ],
    [
        'id'       => 'cancha',
        'kw'       => ['cancha','sede','recinto','donde','dónde','direccion','dirección',
                       'lugar','instalacion','instalación','ubicacion','ubicación','mapa'],
        'peso'     => 2,
        'resp'     => 'La *cancha y sede* de cada partido aparece en los detalles del partido en tu Dashboard. Si la cancha dice "Por confirmar", el administrador la actualizará pronto.',
        'link'     => ['url' => '/dashboard.php', 'texto' => '🏟️ Ver mis Partidos'],
        'sug'      => ['¿Cómo reprogramo si no hay cancha?'],
    ],
    [
        'id'       => 'reglas',
        'kw'       => ['reglas','bases','reglamento','normas','formato','sistema','grupos',
                       'llaves','playoff','como funciona','como se juega','cuantos sets',
                       'tie break','tiebreak','ventaja'],
        'peso'     => 2,
        'resp'     => 'Las *bases y formato* de competición las define el administrador de cada liga. Puedes consultarlas en la sección de Inscripciones o contactando al organizador directamente.',
        'link'     => ['url' => '/inscribirse.php', 'texto' => '📋 Ver Inscripciones'],
        'sug'      => ['¿Cómo contacto al administrador?'],
    ],
    [
        'id'       => 'contacto',
        'kw'       => ['contacto','administrador','admin','ayuda','soporte','problema','error',
                       'consulta','whatsapp','comunicar','hablar','reclamo','queja','disputa',
                       'resultado incorrecto','error en'],
        'peso'     => 1,
        'resp'     => 'Para contactar al *administrador* o reportar un problema, escribe directamente al organizador por WhatsApp o al correo de la liga. Si hay un resultado incorrecto, el administrador lo puede corregir desde el panel.',
        'link'     => null,
        'sug'      => ['¿Cómo disputo un resultado?','¿Cómo reprogramo un partido?'],
    ],
    [
        'id'       => 'tutoriales',
        'kw'       => ['tutorial','tutoriales','guia','guía','como','cómo','ayuda','aprender',
                       'instrucciones','paso a paso','no se','no sé','no entiendo'],
        'peso'     => 1,
        'resp'     => 'En la sección de *Tutoriales* tienes guías paso a paso para todo: registrar resultados, reprogramar, instalar la app, activar notificaciones y más.',
        'link'     => ['url' => '/tutoriales.php', 'texto' => '📖 Ver Tutoriales'],
        'sug'      => ['¿Cómo registro un resultado?','¿Cómo instalo la app?'],
    ],
    [
        'id'       => 'historial',
        'kw'       => ['historial','anteriores','jugados','pasados','resultados anteriores',
                       'partidos jugados','ver resultados','resultados pasados'],
        'peso'     => 2,
        'resp'     => 'En tu *Inicio (Dashboard)* puedes ver el **historial** de todos tus partidos jugados con sus resultados set a set.',
        'link'     => ['url' => '/dashboard.php', 'texto' => '📈 Ver Historial'],
        'sug'      => ['¿Cómo registro un resultado?'],
    ],
    [
        'id'       => 'saludo',
        'kw'       => ['hola','buenas','buen dia','buenos dias','buenas tardes','buenas noches',
                       'hey','ola','hello','hi'],
        'peso'     => 1,
        'resp'     => '¡Hola! 👋 Soy el asistente de *Elite Padel League*. Puedo ayudarte con resultados, reprogramaciones, clasificación, inscripciones y más. ¿Qué necesitas?',
        'link'     => null,
        'sug'      => ['¿Cómo registro un resultado?','¿Cómo veo mi próximo partido?','¿Cómo activo notificaciones?'],
    ],
];

// Seleccionar base de intents según contexto
$intents = $intents_player;
if ($context === 'admin') {
    $intents = $intents_admin;
} elseif ($context === 'public') {
    $intents = $intents_public;
}


// ── Motor de matching ─────────────────────────────────────────
$tokens_pregunta = asist_tokens($pregunta);

$mejor_score  = 0;
$mejor_intent = null;

foreach ($intents as $intent) {
    $score = 0;
    foreach ($intent['kw'] as $kw) {
        $kw_norm = asist_norm($kw);
        // Match exacto de frase
        if (str_contains(asist_norm($pregunta), $kw_norm)) {
            $score += $intent['peso'] * 2;
            continue;
        }
        // Match por tokens
        $kw_tokens = asist_tokens($kw);
        $match = 0;
        foreach ($kw_tokens as $kt) {
            if (in_array($kt, $tokens_pregunta, true)) $match++;
        }
        if ($match > 0) {
            $score += $intent['peso'] * ($match / count($kw_tokens));
        }
    }
    if ($score > $mejor_score) {
        $mejor_score  = $score;
        $mejor_intent = $intent;
    }
}

// ── Respuesta ─────────────────────────────────────────────────
if ($mejor_intent && $mejor_score >= 1) {
    echo json_encode([
        'respuesta'   => $mejor_intent['resp'],
        'link'        => $mejor_intent['link'] ?? null,
        'sugerencias' => $mejor_intent['sug']  ?? [],
    ], JSON_UNESCAPED_UNICODE);
} else {
    if ($context === 'admin') {
        echo json_encode([
            'respuesta'   => 'No encontré una respuesta exacta para administradores. 😅 Puedo ayudarte con gestión de resultados, reprogramaciones, jugadores, finanzas, notificaciones masivas y creación de ligas.',
            'link'        => ['url' => '/admin/configuracion.php', 'texto' => '⚙️ Configuración del Sistema'],
            'sugerencias' => ['¿Cómo edito un resultado?','¿Cómo apruebo una inscripción?','¿Cómo gestiono reprogramaciones?','¿Cómo envío notificaciones masivas?'],
        ], JSON_UNESCAPED_UNICODE);
    } elseif ($context === 'public') {
        echo json_encode([
            'respuesta'   => 'No encontré una respuesta exacta para eso. 😅 Puedo ayudarte con el registro de cuenta, inscripción a torneos, categorías y contacto de soporte.',
            'link'        => ['url' => '/registro.php', 'texto' => '📝 Registrarme ahora'],
            'sugerencias' => ['¿Cómo me registro?','¿Cómo me inscribo a un torneo?','¿Qué pasa si no tengo pareja?'],
        ], JSON_UNESCAPED_UNICODE);
    } else {
        echo json_encode([
            'respuesta'   => 'No encontré una respuesta exacta para eso. 😅 Puedo ayudarte con resultados, reprogramaciones, clasificación, notificaciones e inscripciones. ¿O prefieres ver los tutoriales?',
            'link'        => ['url' => '/tutoriales.php', 'texto' => '📖 Ver Tutoriales'],
            'sugerencias' => ['¿Cómo registro un resultado?','¿Cómo reprogramo un partido?','¿Cómo activo notificaciones?'],
        ], JSON_UNESCAPED_UNICODE);
    }
}
