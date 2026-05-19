<?php
/**
 * Asistente EPL — motor de intents sin IA externa.
 * POST { pregunta: string } → JSON { respuesta, link|null, sugerencias[] }
 */
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
epl_require_login();

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['respuesta' => 'Método no permitido.', 'link' => null, 'sugerencias' => []]);
    exit;
}

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

// ── Base de intents ───────────────────────────────────────────
$intents = [

    // ── RESULTADO ──────────────────────────────────────────────
    [
        'id'       => 'resultado',
        'kw'       => ['resultado','resultados','registrar','ingresar','puntaje','marcador','anotar',
                       'sets','games','gane','perdi','ganamos','perdimos','cargue','cargar','score',
                       'puntos','partido jugado','finalizo','termino'],
        'peso'     => 2,
        'resp'     => 'Para *registrar el resultado* de un partido:\n1. Ve a **Ingresar Resultado**\n2. Selecciona tu partido pendiente\n3. Completa el marcador set a set\n4. Guarda — tu rival recibirá una notificación automática',
        'link'     => ['url' => '/ingresar_resultado.php', 'texto' => '🏆 Ingresar Resultado'],
        'sug'      => ['¿Cuánto tiempo tengo para cargar?','¿Qué pasa si el rival disputa?'],
    ],

    // ── REPROGRAMAR ────────────────────────────────────────────
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

    // ── CLASIFICACIÓN ──────────────────────────────────────────
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

    // ── MIS PARTIDOS / DASHBOARD ───────────────────────────────
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

    // ── INSCRIPCIÓN ────────────────────────────────────────────
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

    // ── NOTIFICACIONES ─────────────────────────────────────────
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

    // ── NOTIFICACIONES IPHONE ──────────────────────────────────
    [
        'id'       => 'notif_iphone',
        'kw'       => ['iphone','ios','safari','apple','no me pregunto','no me llega iphone',
                       'ajustes iphone','configuracion iphone'],
        'peso'     => 3,
        'resp'     => 'En *iPhone* las notificaciones requieren:\n1. Tener la app instalada desde **Safari** (no Chrome)\n2. Abrirla tocando el **ícono** en tu pantalla (no desde Safari)\n3. Tocar el banner **"Activar notificaciones"** en el Inicio\n\n¿Ya hiciste todo eso y nada? Ve a **Ajustes del iPhone → busca "Elite Padel" → Notificaciones → Activar**.\n\nRequiere iOS 16.4 o superior.',
        'link'     => ['url' => '/tutoriales.php', 'texto' => '📖 Ver Tutorial iPhone'],
        'sug'      => ['¿Cómo instalo la app en iPhone?'],
    ],

    // ── INSTALAR APP ───────────────────────────────────────────
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

    // ── PERFIL ─────────────────────────────────────────────────
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

    // ── SUPLENTES ──────────────────────────────────────────────
    [
        'id'       => 'suplentes',
        'kw'       => ['suplente','suplentes','reemplazo','reemplazar','lesion','lesión',
                       'no puedo jugar','falta al partido','no voy a poder','reemplazante'],
        'peso'     => 2,
        'resp'     => 'Si no puedes jugar un partido, puedes registrar un *suplente* que juegue en tu lugar. Ve a **Suplentes** y regístralo antes del partido.',
        'link'     => ['url' => '/mis_suplentes.php', 'texto' => '🔄 Ir a Suplentes'],
        'sug'      => ['¿Hasta cuándo puedo registrar suplente?'],
    ],

    // ── CANCHA / SEDE ──────────────────────────────────────────
    [
        'id'       => 'cancha',
        'kw'       => ['cancha','sede','recinto','donde','dónde','direccion','dirección',
                       'lugar','instalacion','instalación','ubicacion','ubicación','mapa'],
        'peso'     => 2,
        'resp'     => 'La *cancha y sede* de cada partido aparece en los detalles del partido en tu Dashboard. Si la cancha dice "Por confirmar", el administrador la actualizará pronto.',
        'link'     => ['url' => '/dashboard.php', 'texto' => '🏟️ Ver mis Partidos'],
        'sug'      => ['¿Cómo reprogramo si no hay cancha?'],
    ],

    // ── REGLAS / BASES ─────────────────────────────────────────
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

    // ── CONTACTO / ADMIN ───────────────────────────────────────
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

    // ── TUTORIALES ─────────────────────────────────────────────
    [
        'id'       => 'tutoriales',
        'kw'       => ['tutorial','tutoriales','guia','guía','como','cómo','ayuda','aprender',
                       'instrucciones','paso a paso','no se','no sé','no entiendo'],
        'peso'     => 1,
        'resp'     => 'En la sección de *Tutoriales* tienes guías paso a paso para todo: registrar resultados, reprogramar, instalar la app, activar notificaciones y más.',
        'link'     => ['url' => '/tutoriales.php', 'texto' => '📖 Ver Tutoriales'],
        'sug'      => ['¿Cómo registro un resultado?','¿Cómo instalo la app?'],
    ],

    // ── HISTORIAL ──────────────────────────────────────────────
    [
        'id'       => 'historial',
        'kw'       => ['historial','anteriores','jugados','pasados','resultados anteriores',
                       'partidos jugados','ver resultados','resultados pasados'],
        'peso'     => 2,
        'resp'     => 'En tu *Inicio (Dashboard)* puedes ver el **historial** de todos tus partidos jugados con sus resultados set a set.',
        'link'     => ['url' => '/dashboard.php', 'texto' => '📈 Ver Historial'],
        'sug'      => ['¿Cómo registro un resultado?'],
    ],

    // ── SALUDO ─────────────────────────────────────────────────
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
    echo json_encode([
        'respuesta'   => 'No encontré una respuesta exacta para eso. 😅 Puedo ayudarte con resultados, reprogramaciones, clasificación, notificaciones e inscripciones. ¿O prefieres ver los tutoriales?',
        'link'        => ['url' => '/tutoriales.php', 'texto' => '📖 Ver Tutoriales'],
        'sugerencias' => ['¿Cómo registro un resultado?','¿Cómo reprogramo un partido?','¿Cómo activo notificaciones?'],
    ], JSON_UNESCAPED_UNICODE);
}
