<?php
declare(strict_types=1);
/**
 * Motor de automatizaciones de correo.
 * Lee las plantillas activas desde `email_automatizaciones`.
 * Loguea cada intento en `email_log`.
 * Requiere mail.php y functions.php ya cargados.
 */

/** Sustituye {{variable}} en una plantilla. */
function epl_auto_render(string $tpl, array $vars): string {
    foreach ($vars as $k => $v) {
        $tpl = str_replace('{{' . $k . '}}', htmlspecialchars($v, ENT_QUOTES, 'UTF-8'), $tpl);
    }
    return $tpl;
}

/** Crea la tabla de log si no existe (idempotente). */
function epl_email_log_init(): void {
    static $done = false;
    if ($done) return;
    try {
        epl_db()->exec("CREATE TABLE IF NOT EXISTS email_log (
            id             INT AUTO_INCREMENT PRIMARY KEY,
            auto_id        INT          NULL,
            auto_nombre    VARCHAR(150) NOT NULL DEFAULT '',
            trigger_tipo   VARCHAR(60)  NOT NULL DEFAULT '',
            email_destino  VARCHAR(255) NOT NULL,
            nombre_destino VARCHAR(200) NOT NULL DEFAULT '',
            rol_destino    VARCHAR(20)  NOT NULL DEFAULT 'jugador',
            asunto         VARCHAR(255) NOT NULL DEFAULT '',
            estado         VARCHAR(10)  NOT NULL DEFAULT 'enviado',
            error_msg      TEXT         NULL,
            enviado_at     TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_auto   (auto_id),
            INDEX idx_fecha  (enviado_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } catch (Throwable $e) { /* ya existe */ }
    $done = true;
}

/** Registra un intento de envío en el log. */
function epl_email_log(
    ?int   $auto_id,
    string $auto_nombre,
    string $trigger_tipo,
    string $email_destino,
    string $nombre_destino,
    string $rol_destino,
    string $asunto,
    bool   $ok,
    string $error_msg = ''
): void {
    epl_email_log_init();
    try {
        epl_db()->prepare(
            "INSERT INTO email_log
             (auto_id, auto_nombre, trigger_tipo, email_destino, nombre_destino, rol_destino, asunto, estado, error_msg)
             VALUES (?,?,?,?,?,?,?,?,?)"
        )->execute([
            $auto_id,
            $auto_nombre,
            $trigger_tipo,
            $email_destino,
            $nombre_destino,
            $rol_destino,
            $asunto,
            $ok ? 'enviado' : 'error',
            $ok ? null : $error_msg,
        ]);
    } catch (Throwable $e) {
        error_log('epl_email_log: ' . $e->getMessage());
    }
}

/**
 * Despacha todas las automatizaciones activas para un trigger dado.
 * @param string              $trigger  'registro' | 'cumpleanos'
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
        $autoId   = (int)$auto['id'];
        $autoNom  = (string)($auto['nombre'] ?? '');
        $asuntoF  = epl_auto_render($auto['asunto'], $vars);
        $cuerpoF  = epl_auto_render($auto['cuerpo'], $vars);
        $html     = epl_mail_plantilla($asuntoF, $cuerpoF);
        $dest     = $auto['destinatario'] ?? 'jugador';

        // ── Al jugador ──────────────────────────────────────────────────────
        if (in_array($dest, ['jugador', 'ambos'], true) && $email) {
            $res = epl_mail_enviar($email, $asuntoF, $html, trim($nombre . ' ' . $apellido));
            epl_email_log($autoId, $autoNom, $trigger, $email,
                trim($nombre . ' ' . $apellido), 'jugador', $asuntoF,
                $res['ok'], $res['error'] ?? '');
        }

        // ── A admins ─────────────────────────────────────────────────────────
        if (in_array($dest, ['admins', 'ambos'], true)) {
            try {
                $admins = $db->query(
                    "SELECT email, nombre FROM jugadores WHERE rol='admin' AND estado='activo' AND email IS NOT NULL AND email<>''"
                )->fetchAll(PDO::FETCH_ASSOC);
                foreach ($admins as $adm) {
                    $admEmail  = trim((string)($adm['email']  ?? ''));
                    $admNombre = trim((string)($adm['nombre'] ?? ''));
                    if (!$admEmail) continue;
                    $res = epl_mail_enviar($admEmail, $asuntoF, $html, $admNombre);
                    epl_email_log($autoId, $autoNom, $trigger, $admEmail,
                        $admNombre, 'admin', $asuntoF,
                        $res['ok'], $res['error'] ?? '');
                }
            } catch (Throwable $e) {
                error_log('epl_auto_dispatch admins: ' . $e->getMessage());
            }
        }
    }
}

// ─── Helpers de conveniencia ──────────────────────────────────────────────────

function epl_mail_bienvenida(array $jugador): void {
    epl_auto_dispatch('registro', $jugador);
}

/**
 * Envía email con contraseña temporal (registro inicial o reset).
 * $contexto: 'registro' (cuenta recién creada) o 'reset' (recuperación)
 */
function epl_mail_password_temporal(array $jugador, string $password_plano, string $contexto = 'reset'): void {
    if (empty($jugador['email'])) return;

    $nombre = trim(($jugador['nombre'] ?? '') . ' ' . ($jugador['apellido'] ?? ''));
    $login_url = epl_url('login.php');

    if ($contexto === 'registro') {
        $asunto = '🎾 Tu cuenta en Elite Padel League — Contraseña provisoria';
        $titulo_email = '¡Bienvenido a Elite Padel League!';
        $intro = "Hola <strong>" . htmlspecialchars($nombre, ENT_QUOTES) . "</strong>, tu cuenta fue creada con éxito. Te enviamos una <strong>contraseña provisoria</strong> para que ingreses por primera vez.";
        $cta = 'Ingresar a mi cuenta';
    } else {
        $asunto = '🔑 Recuperación de contraseña — Elite Padel League';
        $titulo_email = 'Recuperación de contraseña';
        $intro = "Hola <strong>" . htmlspecialchars($nombre, ENT_QUOTES) . "</strong>, recibimos tu solicitud para recuperar tu contraseña. Te asignamos una <strong>contraseña provisoria</strong> que podrás usar para ingresar.";
        $cta = 'Ingresar y cambiar contraseña';
    }

    $contenido = <<<HTML
<h2 style="font-family:Anton,Arial,sans-serif;color:#1C2F48;font-size:22px;margin:0 0 12px;text-transform:uppercase">{$titulo_email}</h2>
<p style="font-size:14px;color:#475569;line-height:1.6;margin:0 0 16px">{$intro}</p>
<div style="background:#fef9c3;border:1.5px solid #fbbf24;border-radius:10px;padding:16px 20px;margin:16px 0;text-align:center">
  <div style="font-size:11px;font-weight:800;color:#854d0e;text-transform:uppercase;letter-spacing:.08em;margin-bottom:6px">Tu contraseña provisoria</div>
  <div style="font-family:'Courier New',monospace;font-size:26px;font-weight:800;color:#1C2F48;letter-spacing:.05em">{$password_plano}</div>
</div>
<p style="font-size:13px;color:#64748b;line-height:1.6;margin:0 0 20px">
  Por seguridad, al ingresar te pediremos que la cambies por una contraseña personal.
</p>
<div style="text-align:center;margin:20px 0">
  <a href="{$login_url}" style="display:inline-block;background:#1C2F48;color:#C9A762;padding:12px 28px;border-radius:10px;text-decoration:none;font-weight:800;font-size:14px;text-transform:uppercase;letter-spacing:.05em">{$cta}</a>
</div>
<p style="font-size:12px;color:#94a3b8;margin:20px 0 0;line-height:1.5">
  Si no solicitaste este correo, podés ignorarlo. La contraseña anterior ya no es válida.
</p>
HTML;

    $html = epl_mail_plantilla($asunto, $contenido);
    try {
        epl_mail_enviar($jugador['email'], $asunto, $html, $nombre);
    } catch (Throwable $e) {
        error_log('[epl_mail_password_temporal] ' . $e->getMessage());
    }
}

function epl_mail_cumpleanos_jugador(array $jugador): void {
    epl_auto_dispatch('cumpleanos', $jugador);
}

function epl_mail_cumpleanos_admins(array $jugador): void {
    // No-op: cubierto por epl_auto_dispatch con destinatario=ambos/admins.
}
