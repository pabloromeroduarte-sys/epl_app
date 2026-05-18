<?php
declare(strict_types=1);
/**
 * Automatizaciones de correo: bienvenida, cumpleaños.
 * Requiere mail.php y functions.php cargados.
 */

/**
 * Sustituye variables {{nombre}}, {{apellido}}, {{email}}, {{liga}} en una plantilla.
 * @param array<string,string> $vars
 */
function epl_auto_render(string $tpl, array $vars): string {
    foreach ($vars as $k => $v) {
        $tpl = str_replace('{{' . $k . '}}', htmlspecialchars($v, ENT_QUOTES, 'UTF-8'), $tpl);
    }
    return $tpl;
}

/**
 * Envía el correo de bienvenida al nuevo jugador.
 * @param array<string,mixed> $jugador Debe tener: nombre, apellido, email
 */
function epl_mail_bienvenida(array $jugador): void {
    if (!epl_smtp_habilitado()) return;
    if (epl_config_get('auto_bienvenida_activo', '1') !== '1') return;

    $email    = trim((string)($jugador['email']    ?? ''));
    $nombre   = trim((string)($jugador['nombre']   ?? ''));
    $apellido = trim((string)($jugador['apellido'] ?? ''));
    if (!$email) return;

    $asunto = epl_config_get('auto_bienvenida_asunto', '¡Bienvenido/a a Elite Padel League!');
    $cuerpo = epl_config_get('auto_bienvenida_cuerpo',
        '<p style="margin:0 0 1rem;color:#334155;line-height:1.6">Hola <strong>{{nombre}}</strong>,</p>'
        . '<p style="margin:0 0 1rem;color:#334155;line-height:1.6">¡Tu cuenta en <strong>Elite Padel League</strong> ha sido creada con éxito! Ya puedes iniciar sesión y explorar todos los torneos disponibles.</p>'
        . '<p style="margin:0 0 1.5rem;color:#334155;line-height:1.6">Estamos muy contentos de tenerte con nosotros. ¡A jugar!</p>'
        . '<p style="margin:0"><a href="https://epleague.cl/dashboard.php" style="display:inline-block;background:#1C2F48;color:#C9A762;font-weight:700;text-decoration:none;padding:.7rem 1.4rem;border-radius:8px">Ir a mi dashboard</a></p>'
    );

    $vars = [
        'nombre'   => $nombre,
        'apellido' => $apellido,
        'email'    => $email,
    ];

    $asuntoFinal = epl_auto_render($asunto, $vars);
    $cuerpoFinal = epl_auto_render($cuerpo, $vars);

    $html = epl_mail_plantilla($asuntoFinal, $cuerpoFinal);
    epl_mail_enviar($email, $asuntoFinal, $html, $nombre . ($apellido ? ' ' . $apellido : ''));
}

/**
 * Envía el correo de cumpleaños al jugador.
 * @param array<string,mixed> $jugador
 */
function epl_mail_cumpleanos_jugador(array $jugador): void {
    if (!epl_smtp_habilitado()) return;
    if (epl_config_get('auto_cumple_activo', '1') !== '1') return;

    $email    = trim((string)($jugador['email']    ?? ''));
    $nombre   = trim((string)($jugador['nombre']   ?? ''));
    $apellido = trim((string)($jugador['apellido'] ?? ''));
    if (!$email) return;

    $asunto = epl_config_get('auto_cumple_asunto', '¡Feliz cumpleaños, {{nombre}}! 🎉');
    $cuerpo = epl_config_get('auto_cumple_cuerpo',
        '<p style="margin:0 0 1rem;color:#334155;line-height:1.6">¡Hola <strong>{{nombre}}</strong>!</p>'
        . '<p style="margin:0 0 1rem;color:#334155;line-height:1.6">Todo el equipo de <strong>Elite Padel League</strong> te desea un feliz cumpleaños. 🎂</p>'
        . '<p style="margin:0 0 1.5rem;color:#334155;line-height:1.6">¡Que este nuevo año esté lleno de grandes partidos y victorias en la cancha!</p>'
        . '<p style="margin:0"><a href="https://epleague.cl/dashboard.php" style="display:inline-block;background:#1C2F48;color:#C9A762;font-weight:700;text-decoration:none;padding:.7rem 1.4rem;border-radius:8px">Ver mis partidos</a></p>'
    );

    $vars = [
        'nombre'   => $nombre,
        'apellido' => $apellido,
        'email'    => $email,
    ];

    $asuntoFinal = epl_auto_render($asunto, $vars);
    $cuerpoFinal = epl_auto_render($cuerpo, $vars);

    $html = epl_mail_plantilla($asuntoFinal, $cuerpoFinal);
    epl_mail_enviar($email, $asuntoFinal, $html, $nombre . ($apellido ? ' ' . $apellido : ''));
}

/**
 * Envía aviso de cumpleaños a todos los administradores.
 * @param array<string,mixed> $jugador  El jugador que cumple años
 */
function epl_mail_cumpleanos_admins(array $jugador): void {
    if (!epl_smtp_habilitado()) return;
    if (epl_config_get('auto_cumple_admin_activo', '1') !== '1') return;

    $nombre   = trim((string)($jugador['nombre']   ?? ''));
    $apellido = trim((string)($jugador['apellido'] ?? ''));
    $email    = trim((string)($jugador['email']    ?? ''));
    $nombreCompleto = trim($nombre . ' ' . $apellido);

    $db = epl_db();
    $admins = $db->query("SELECT email, nombre FROM jugadores WHERE rol='admin' AND estado='activo'")->fetchAll(PDO::FETCH_ASSOC);
    if (empty($admins)) return;

    $asunto = epl_config_get('auto_cumple_admin_asunto', 'Cumpleaños hoy: {{nombre}} {{apellido}}');
    $cuerpo = epl_config_get('auto_cumple_admin_cuerpo',
        '<p style="margin:0 0 1rem;color:#334155;line-height:1.6">Hola, solo un recordatorio:</p>'
        . '<p style="margin:0 0 1rem;color:#334155;line-height:1.6"><strong>{{nombre}} {{apellido}}</strong> '
        . '(<a href="mailto:{{email}}" style="color:#1C2F48">{{email}}</a>) cumple años hoy.</p>'
        . '<p style="margin:0 0 1.5rem;color:#334155;line-height:1.6">¡No olvides saludarlo/a! 🎂</p>'
        . '<p style="margin:0"><a href="https://epleague.cl/admin/jugadores.php" style="display:inline-block;background:#1C2F48;color:#C9A762;font-weight:700;text-decoration:none;padding:.7rem 1.4rem;border-radius:8px">Ver jugadores</a></p>'
    );

    $vars = [
        'nombre'   => $nombre,
        'apellido' => $apellido,
        'email'    => $email,
    ];

    $asuntoFinal = epl_auto_render($asunto, $vars);
    $cuerpoFinal = epl_auto_render($cuerpo, $vars);
    $html = epl_mail_plantilla($asuntoFinal, $cuerpoFinal);

    foreach ($admins as $admin) {
        $adminEmail = trim((string)($admin['email'] ?? ''));
        $adminNombre = trim((string)($admin['nombre'] ?? ''));
        if ($adminEmail) {
            epl_mail_enviar($adminEmail, $asuntoFinal, $html, $adminNombre);
        }
    }
}
