<?php
declare(strict_types=1);
/**
 * Automatizaciones de correo: bienvenida, cumpleaños.
 * Lee las plantillas desde la tabla `email_automatizaciones`.
 * Requiere mail.php y functions.php cargados.
 */

/**
 * Sustituye variables {{nombre}}, {{apellido}}, {{email}} en una plantilla.
 * @param array<string,string> $vars
 */
function epl_auto_render(string $tpl, array $vars): string {
    foreach ($vars as $k => $v) {
        $tpl = str_replace('{{' . $k . '}}', htmlspecialchars($v, ENT_QUOTES, 'UTF-8'), $tpl);
    }
    return $tpl;
}

/**
 * Obtiene la automatización activa para un tipo de disparo.
 * @return array<string,mixed>|null
 */
function epl_auto_obtener(string $trigger_tipo): ?array {
    static $cache = [];
    if (array_key_exists($trigger_tipo, $cache)) return $cache[$trigger_tipo];

    $db = epl_db();
    // Aseguramos que la tabla exista sin error fatal si se llama antes que el admin
    try {
        $st = $db->prepare("SELECT * FROM email_automatizaciones WHERE trigger_tipo=? AND activo=1 LIMIT 1");
        $st->execute([$trigger_tipo]);
        $row = $st->fetch(PDO::FETCH_ASSOC) ?: null;
    } catch (Throwable $e) {
        $row = null;
    }

    $cache[$trigger_tipo] = $row;
    return $row;
}

/**
 * Envía el correo de bienvenida al nuevo jugador.
 * @param array<string,mixed> $jugador  Debe tener: nombre, apellido, email
 */
function epl_mail_bienvenida(array $jugador): void {
    if (!epl_smtp_habilitado()) return;

    $auto = epl_auto_obtener('bienvenida');
    if (!$auto) return; // Sin automatización activa para este tipo

    $email    = trim((string)($jugador['email']    ?? ''));
    $nombre   = trim((string)($jugador['nombre']   ?? ''));
    $apellido = trim((string)($jugador['apellido'] ?? ''));
    if (!$email) return;

    $vars = ['nombre' => $nombre, 'apellido' => $apellido, 'email' => $email];
    $asuntoFinal = epl_auto_render($auto['asunto'], $vars);
    $cuerpoFinal = epl_auto_render($auto['cuerpo'], $vars);
    $html = epl_mail_plantilla($asuntoFinal, $cuerpoFinal);
    epl_mail_enviar($email, $asuntoFinal, $html, trim($nombre . ' ' . $apellido));
}

/**
 * Envía el correo de cumpleaños al jugador.
 * @param array<string,mixed> $jugador
 */
function epl_mail_cumpleanos_jugador(array $jugador): void {
    if (!epl_smtp_habilitado()) return;

    $auto = epl_auto_obtener('cumpleanos_jugador');
    if (!$auto) return;

    $email    = trim((string)($jugador['email']    ?? ''));
    $nombre   = trim((string)($jugador['nombre']   ?? ''));
    $apellido = trim((string)($jugador['apellido'] ?? ''));
    if (!$email) return;

    $vars = ['nombre' => $nombre, 'apellido' => $apellido, 'email' => $email];
    $asuntoFinal = epl_auto_render($auto['asunto'], $vars);
    $cuerpoFinal = epl_auto_render($auto['cuerpo'], $vars);
    $html = epl_mail_plantilla($asuntoFinal, $cuerpoFinal);
    epl_mail_enviar($email, $asuntoFinal, $html, trim($nombre . ' ' . $apellido));
}

/**
 * Envía aviso de cumpleaños a todos los administradores.
 * @param array<string,mixed> $jugador  El jugador que cumple años
 */
function epl_mail_cumpleanos_admins(array $jugador): void {
    if (!epl_smtp_habilitado()) return;

    $auto = epl_auto_obtener('cumpleanos_admin');
    if (!$auto) return;

    $nombre   = trim((string)($jugador['nombre']   ?? ''));
    $apellido = trim((string)($jugador['apellido'] ?? ''));
    $email    = trim((string)($jugador['email']    ?? ''));

    $db = epl_db();
    $admins = $db->query("SELECT email, nombre FROM jugadores WHERE rol='admin' AND estado='activo'")->fetchAll(PDO::FETCH_ASSOC);
    if (empty($admins)) return;

    $vars = ['nombre' => $nombre, 'apellido' => $apellido, 'email' => $email];
    $asuntoFinal = epl_auto_render($auto['asunto'], $vars);
    $cuerpoFinal = epl_auto_render($auto['cuerpo'], $vars);
    $html = epl_mail_plantilla($asuntoFinal, $cuerpoFinal);

    foreach ($admins as $admin) {
        $adminEmail  = trim((string)($admin['email']  ?? ''));
        $adminNombre = trim((string)($admin['nombre'] ?? ''));
        if ($adminEmail) {
            epl_mail_enviar($adminEmail, $asuntoFinal, $html, $adminNombre);
        }
    }
}
