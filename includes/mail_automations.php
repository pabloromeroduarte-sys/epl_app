<?php
declare(strict_types=1);
/**
 * Motor de automatizaciones de correo.
 * Lee las plantillas activas desde `email_automatizaciones`.
 * Requiere mail.php y functions.php ya cargados.
 */

/**
 * Sustituye {{variable}} en una plantilla.
 * @param array<string,string> $vars
 */
function epl_auto_render(string $tpl, array $vars): string {
    foreach ($vars as $k => $v) {
        $tpl = str_replace('{{' . $k . '}}', htmlspecialchars($v, ENT_QUOTES, 'UTF-8'), $tpl);
    }
    return $tpl;
}

/**
 * Despacha todas las automatizaciones activas para un trigger dado.
 * Maneja destinatario: 'jugador', 'admins', 'ambos'.
 *
 * @param string             $trigger  'registro' | 'cumpleanos'
 * @param array<string,mixed> $jugador  Debe tener nombre, apellido, email
 */
function epl_auto_dispatch(string $trigger, array $jugador): void {
    if (!epl_smtp_habilitado()) return;

    $db = epl_db();
    try {
        $st = $db->prepare("SELECT * FROM email_automatizaciones WHERE trigger_tipo=? AND activo=1");
        $st->execute([$trigger]);
        $autos = $st->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        error_log('epl_auto_dispatch: ' . $e->getMessage());
        return;
    }
    if (empty($autos)) return;

    $nombre   = trim((string)($jugador['nombre']   ?? ''));
    $apellido = trim((string)($jugador['apellido'] ?? ''));
    $email    = trim((string)($jugador['email']    ?? ''));
    $vars     = ['nombre' => $nombre, 'apellido' => $apellido, 'email' => $email];

    foreach ($autos as $auto) {
        $asuntoF = epl_auto_render($auto['asunto'], $vars);
        $cuerpoF = epl_auto_render($auto['cuerpo'], $vars);
        $html    = epl_mail_plantilla($asuntoF, $cuerpoF);
        $dest    = $auto['destinatario'] ?? 'jugador';

        // Enviar al jugador
        if (in_array($dest, ['jugador', 'ambos'], true) && $email) {
            epl_mail_enviar($email, $asuntoF, $html, trim($nombre . ' ' . $apellido));
        }

        // Enviar a admins
        if (in_array($dest, ['admins', 'ambos'], true)) {
            try {
                $admins = $db->query(
                    "SELECT email, nombre FROM jugadores WHERE rol='admin' AND estado='activo' AND email IS NOT NULL AND email<>''"
                )->fetchAll(PDO::FETCH_ASSOC);
                foreach ($admins as $adm) {
                    $admEmail  = trim((string)($adm['email']  ?? ''));
                    $admNombre = trim((string)($adm['nombre'] ?? ''));
                    if ($admEmail) {
                        epl_mail_enviar($admEmail, $asuntoF, $html, $admNombre);
                    }
                }
            } catch (Throwable $e) {
                error_log('epl_auto_dispatch admins: ' . $e->getMessage());
            }
        }
    }
}

// ─── Helpers de conveniencia (usados en registro.php y cron) ─────────────────

/** @param array<string,mixed> $jugador */
function epl_mail_bienvenida(array $jugador): void {
    epl_auto_dispatch('registro', $jugador);
}

/** @param array<string,mixed> $jugador */
function epl_mail_cumpleanos_jugador(array $jugador): void {
    epl_auto_dispatch('cumpleanos', $jugador);
}

/**
 * Mantiene compatibilidad: el cron llama esto pero ahora
 * epl_auto_dispatch ya maneja destinatario=admins / ambos.
 * Aquí solo despachamos si hay una auto específica para admins.
 * @param array<string,mixed> $jugador
 */
function epl_mail_cumpleanos_admins(array $jugador): void {
    // No-op: epl_mail_cumpleanos_jugador ya cubre destinatario=ambos y destinatario=admins.
    // Se mantiene para no romper llamadas existentes en cron_cumpleanos.php.
}
