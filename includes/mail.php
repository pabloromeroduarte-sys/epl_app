<?php
declare(strict_types=1);

require_once __DIR__ . '/functions.php';

/**
 * Construye un asunto de mail estandarizado.
 * Formato: "Acción | J{n} · Local vs Visitante"
 * Ejemplo: "⚽ Resultado ingresado | J5 · Romero-Merino vs Sfeir-Amigo"
 *
 * @param string      $accion     Texto de la acción con emoji (ej: "⚽ Resultado ingresado")
 * @param string|null $local      Nombre equipo local
 * @param string|null $visitante  Nombre equipo visitante
 * @param int|string|null $jornada Número de jornada
 */
function epl_mail_asunto(string $accion, ?string $local = null, ?string $visitante = null, $jornada = null): string {
    $s = $accion;
    $j = (string)($jornada ?? '');
    if ($j !== '' && $j !== '0') {
        $s .= ' | J' . $j;
    }
    if ($local !== null && $local !== '' && $visitante !== null && $visitante !== '') {
        $s .= ' · ' . $local . ' vs ' . $visitante;
    }
    return $s;
}

function epl_smtp_habilitado(): bool {
    if (epl_config_get('mail_pausado', '0') === '1') {
        return false; // Modo mantenimiento: silencioso, no envía nada
    }
    return epl_config_get('smtp_enabled', '0') === '1';
}

/** @return array<string, string> */
function epl_smtp_config(): array {
    return [
        'host'       => epl_config_get('smtp_host', ''),
        'port'       => epl_config_get('smtp_port', '587'),
        'encryption' => epl_config_get('smtp_encryption', 'tls'),
        'user'       => epl_config_get('smtp_user', ''),
        'pass'       => epl_config_get('smtp_pass', ''),
        'from_email' => epl_config_get('smtp_from_email', ''),
        'from_name'  => epl_config_get('smtp_from_name', 'Elite Padel League'),
        'reply_to'   => epl_config_get('smtp_reply_to', ''),
    ];
}

/**
 * Encola un email para ser enviado por el cron (no bloquea la petición del usuario).
 * Reemplaza el envío síncrono previo. El cron cron_mail_sender.php lo procesa en segundos.
 *
 * @return array{ok: bool, error?: string}
 */
function epl_mail_enviar(string $to, string $subject, string $bodyHtml, ?string $toName = null): array {
    if (!epl_smtp_habilitado()) {
        return ['ok' => false, 'error' => 'SMTP desactivado.'];
    }

    $to = trim($to);
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'error' => 'Email destinatario inválido.'];
    }

    try {
        $db = epl_db();
        // Crear tabla si no existe (primera vez)
        $db->exec("CREATE TABLE IF NOT EXISTS `mail_queue` (
            `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `to_email`   VARCHAR(255) NOT NULL,
            `to_name`    VARCHAR(150) DEFAULT NULL,
            `subject`    VARCHAR(250) NOT NULL,
            `body_html`  MEDIUMTEXT  NOT NULL,
            `estado`     ENUM('pendiente','enviando','enviado','error') NOT NULL DEFAULT 'pendiente',
            `intentos`   TINYINT UNSIGNED NOT NULL DEFAULT 0,
            `error_msg`  TEXT DEFAULT NULL,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `sent_at`    DATETIME DEFAULT NULL,
            INDEX idx_estado_created (`estado`, `created_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $db->prepare(
            "INSERT INTO mail_queue (to_email, to_name, subject, body_html) VALUES (?,?,?,?)"
        )->execute([$to, $toName, $subject, $bodyHtml]);

        return ['ok' => true];
    } catch (Throwable $e) {
        return ['ok' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Envío SMTP directo (sin cola). Solo para uso interno del cron_mail_sender.php.
 * No llamar desde peticiones web — bloquea.
 *
 * @return array{ok: bool, error?: string}
 */
function epl_mail_enviar_directo(string $to, string $subject, string $bodyHtml, ?string $toName = null): array {
    $cfg = epl_smtp_config();
    if (!$cfg['host'] || !$cfg['from_email']) {
        return ['ok' => false, 'error' => 'Completa host y correo remitente en Configuración.'];
    }

    $to = trim($to);
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'error' => 'Email destinatario inválido.'];
    }

    try {
        $client = new EplSmtpClient($cfg);
        $client->send($to, $toName, $cfg['from_email'], $cfg['from_name'], $subject, $bodyHtml);
        return ['ok' => true];
    } catch (Throwable $e) {
        return ['ok' => false, 'error' => $e->getMessage()];
    }
}

function epl_mail_notificacion_jugador(int $jugador_id, string $titulo, string $mensaje, string $url = ''): void {
    if (!epl_smtp_habilitado()) {
        return;
    }
    $st = epl_db()->prepare('SELECT email, nombre, apellido FROM jugadores WHERE id = ?');
    $st->execute([$jugador_id]);
    $j = $st->fetch(PDO::FETCH_ASSOC);
    if (!$j || empty($j['email'])) {
        return;
    }

    $nombre = trim(($j['nombre'] ?? '') . ' ' . ($j['apellido'] ?? ''));
    $link   = $url !== '' ? $url : epl_url('notificaciones.php');
    if ($link !== '' && !str_starts_with($link, 'http')) {
        $link = epl_url(ltrim($link, '/'));
    }

    $body = '<p style="margin:0 0 1rem;color:#334155;line-height:1.5">Hola ' . htmlspecialchars($nombre ?: 'jugador', ENT_QUOTES, 'UTF-8') . ',</p>'
          . '<p style="margin:0 0 1rem;color:#334155;line-height:1.5"><strong>' . htmlspecialchars($titulo, ENT_QUOTES, 'UTF-8') . '</strong></p>'
          . '<p style="margin:0 0 1.25rem;color:#334155;line-height:1.5">' . nl2br(htmlspecialchars($mensaje, ENT_QUOTES, 'UTF-8')) . '</p>'
          . '<p style="margin:0"><a href="' . htmlspecialchars($link, ENT_QUOTES, 'UTF-8') . '" style="display:inline-block;background:#1C2F48;color:#C9A762;font-weight:700;text-decoration:none;padding:.65rem 1.25rem;border-radius:8px">Ver en Elite Padel League</a></p>';

    $html = epl_mail_plantilla($titulo, $body);
    epl_mail_enviar($j['email'], $titulo . ' — Elite Padel League', $html, $nombre);
}

function epl_mail_plantilla(string $titulo, string $contenidoHtml): string {
    $app = epl_config_get('smtp_from_name', 'Elite Padel League');
    return '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><title>'
        . htmlspecialchars($titulo, ENT_QUOTES, 'UTF-8')
        . '</title></head><body style="margin:0;padding:0;background:#f1f5f9;font-family:Montserrat,Arial,sans-serif">'
        . '<table width="100%" cellpadding="0" cellspacing="0" style="background:#f1f5f9;padding:24px 12px"><tr><td align="center">'
        . '<table width="100%" style="max-width:560px;background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 2px 12px rgba(0,0,0,.06)">'
        . '<tr><td style="background:#1C2F48;padding:20px 24px;text-align:center">'
        . '<span style="font-family:Arial,sans-serif;font-size:18px;font-weight:800;color:#C9A762;text-transform:uppercase;letter-spacing:.08em">'
        . htmlspecialchars($app, ENT_QUOTES, 'UTF-8') . '</span></td></tr>'
        . '<tr><td style="padding:28px 24px">' . $contenidoHtml . '</td></tr>'
        . '<tr><td style="background:#f8fafc;padding:16px 24px;text-align:center;font-size:12px;color:#94a3b8">'
        . 'Mensaje automático — no respondas a este correo.</td></tr></table></td></tr></table></body></html>';
}

/**
 * Email visual para cualquier alerta de partido.
 * Muestra VS header (si local y visitante no son null) + tabla de datos + botón CTA.
 *
 * @param array<int,array{icon:string,label:string,valor:string}> $filas
 */
function epl_mail_partido_visual(
    int     $jugador_id,
    string  $asunto,
    ?string $local,
    ?string $visitante,
    array   $filas,
    string  $subtitulo = '',
    string  $tip       = '',
    string  $url       = '',
    string  $btn_texto = 'Ver mis partidos'
): void {
    if (!epl_smtp_habilitado()) return;

    $db = epl_db();
    $j  = $db->prepare('SELECT email, nombre, apellido, rol FROM jugadores WHERE id = ?');
    $j->execute([$jugador_id]);
    $jug = $j->fetch(PDO::FETCH_ASSOC);
    if (!$jug || empty($jug['email'])) return;

    $nombre = htmlspecialchars(trim($jug['nombre'] . ' ' . $jug['apellido']), ENT_QUOTES, 'UTF-8');

    // Auto-override URL for rescheduling in emails
    if (str_contains(strtolower($asunto), 'reprogramar') || str_contains(strtolower($asunto), 'reprogramación') || str_contains(strtolower($asunto), 'reprogramado') || str_contains($url, 'reprogramar.php') || str_contains($url, 'mis_partidos.php')) {
        if (($jug['rol'] ?? '') === 'admin') {
            $url = 'admin/dashboard_repro.php?tab=solicitudes';
            $btn_texto = 'Ver Solicitudes';
        } else {
            $url = 'reprogramar.php#mis-reprogramaciones';
            $btn_texto = 'Ver Reprogramaciones';
        }
    }

    $link   = $url !== '' ? $url : epl_url('notificaciones.php');
    if ($link !== '' && !str_starts_with($link, 'http')) {
        $link = epl_url(ltrim($link, '/'));
    }

    $fila_fn = fn(string $icon, string $label, string $valor) =>
        '<tr>
          <td style="padding:11px 14px;border-bottom:1px solid #f1f5f9;vertical-align:top;width:44%">
            <span style="font-size:15px;display:inline-block;vertical-align:middle;margin-right:6px">' . $icon . '</span>
            <span style="font-size:11px;font-weight:700;color:#64748b;text-transform:uppercase;vertical-align:middle">' . $label . '</span>
          </td>
          <td style="padding:11px 14px;border-bottom:1px solid #f1f5f9;font-size:14px;font-weight:700;color:#1c2f48;text-align:right;vertical-align:top;word-break:break-word">' . htmlspecialchars($valor, ENT_QUOTES, 'UTF-8') . '</td>
        </tr>';

    // VS header (opcional)
    $vs_html = '';
    if ($local !== null && $visitante !== null) {
        $vs_html = '
        <table width="100%" cellpadding="0" cellspacing="0" style="background:linear-gradient(135deg,#1c2f48,#1a3a64);border-radius:12px;margin-bottom:1.25rem">
          <tr>
            <td style="padding:20px;text-align:center;width:45%;color:#fff;font-size:15px;font-weight:800;line-height:1.3">' . htmlspecialchars($local,     ENT_QUOTES) . '</td>
            <td style="padding:20px;text-align:center;width:10%">
              <span style="background:#C9A762;color:#1c2f48;font-weight:900;font-size:13px;padding:4px 10px;border-radius:6px">VS</span>
            </td>
            <td style="padding:20px;text-align:center;width:45%;color:#fff;font-size:15px;font-weight:800;line-height:1.3">' . htmlspecialchars($visitante, ENT_QUOTES) . '</td>
          </tr>
        </table>';
    }

    // Tabla de datos
    $filas_html = '';
    foreach ($filas as $f) {
        $filas_html .= $fila_fn($f['icon'], $f['label'], $f['valor']);
    }
    $tabla_html = $filas_html
        ? '<table width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #e2e8f0;border-radius:12px;overflow:hidden;margin-bottom:1.5rem">' . $filas_html . '</table>'
        : '';

    $subtitulo_html = $subtitulo
        ? '<p style="margin:0 0 1.25rem;font-size:14px;color:#64748b">' . nl2br(htmlspecialchars($subtitulo, ENT_QUOTES)) . '</p>'
        : '';

    $tip_html = $tip
        ? '<p style="margin:0 0 1.5rem;font-size:13px;color:#64748b;background:#f8fafc;border-radius:8px;padding:.75rem 1rem;border-left:3px solid #C9A762">' . htmlspecialchars($tip, ENT_QUOTES) . '</p>'
        : '';

    $body = '
    <p style="margin:0 0 1.25rem;font-size:15px;color:#334155">Hola <strong>' . $nombre . '</strong>,</p>
    ' . $subtitulo_html . $vs_html . $tabla_html . $tip_html . '
    <p style="margin:0;text-align:center">
      <a href="' . htmlspecialchars($link, ENT_QUOTES) . '" style="display:inline-block;background:#C9A762;color:#1c2f48;font-weight:900;font-size:13px;text-decoration:none;padding:.75rem 2rem;border-radius:8px;text-transform:uppercase;letter-spacing:.05em">' . htmlspecialchars($btn_texto, ENT_QUOTES) . '</a>
    </p>';

    $html = epl_mail_plantilla($asunto, $body);
    epl_mail_enviar(
        $jug['email'],
        $asunto . ' — Elite Padel League',
        $html,
        trim($jug['nombre'] . ' ' . $jug['apellido'])
    );
}

final class EplSmtpClient
{
    /** @var array<string, string> */
    private array $cfg;
    private $socket;

    /** @param array<string, string> $cfg */
    public function __construct(array $cfg)
    {
        $this->cfg = $cfg;
    }

    public function send(string $to, ?string $toName, string $fromEmail, string $fromName, string $subject, string $bodyHtml): void
    {
        $host = $this->cfg['host'];
        $port = (int)($this->cfg['port'] ?: 587);
        $enc  = $this->cfg['encryption'] ?? 'tls';

        $remote = ($enc === 'ssl' ? 'ssl://' : 'tcp://') . $host . ':' . $port;
        $this->socket = @stream_socket_client($remote, $errno, $errstr, 20, STREAM_CLIENT_CONNECT);
        if (!$this->socket) {
            throw new RuntimeException("No se pudo conectar al servidor SMTP: {$errstr} ({$errno})");
        }
        stream_set_timeout($this->socket, 20);

        $this->expect($this->read(), [220]);
        $this->cmd('EHLO ' . gethostname(), [250]);

        if ($enc === 'tls') {
            $this->cmd('STARTTLS', [220]);
            if (!stream_socket_enable_crypto($this->socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new RuntimeException('No se pudo iniciar TLS con el servidor SMTP.');
            }
            $this->cmd('EHLO ' . gethostname(), [250]);
        }

        if ($this->cfg['user'] !== '') {
            $this->authLogin($this->cfg['user'], $this->cfg['pass']);
        }

        $fromHeader = $this->formatAddress($fromEmail, $fromName);
        $toHeader   = $this->formatAddress($to, $toName ?? '');
        // Por defecto para SMTP profesional (como Brevo) el envelope debe ser el remitente real.
        // Solo para Gmail forzamos que sea el usuario autenticado (requerido por su SMTP).
        $envelopeFrom = $fromEmail;
        if (str_contains(strtolower($host), 'gmail.com')) {
            $userEmail = trim($this->cfg['user'] ?? '');
            if ($userEmail !== '' && filter_var($userEmail, FILTER_VALIDATE_EMAIL)) {
                $envelopeFrom = $userEmail;
            }
        }
        $this->cmd('MAIL FROM:<' . $envelopeFrom . '>', [250, 251]);
        $this->cmd('RCPT TO:<' . $to . '>', [250, 251]);
        $this->cmd('DATA', [354]);

        // Codificar body en quoted-printable para garantizar líneas ≤ 76 chars
        // y evitar el error "lines too long for transport" de ciertos MTA.
        $encodedBody = quoted_printable_encode($bodyHtml);

        $headers = [
            'From: ' . $fromHeader,
            'To: ' . $toHeader,
            'Subject: ' . $this->encodeHeader($subject),
            'MIME-Version: 1.0',
            'Content-Type: text/html; charset=UTF-8',
            'Content-Transfer-Encoding: quoted-printable',
            'Date: ' . date('r'),
        ];
        $replyTo = trim($this->cfg['reply_to'] ?? '');
        if ($replyTo !== '' && filter_var($replyTo, FILTER_VALIDATE_EMAIL)) {
            $headers[] = 'Reply-To: ' . $replyTo;
        }
        $message = implode("\r\n", $headers) . "\r\n\r\n" . $encodedBody . "\r\n.";
        fwrite($this->socket, $message . "\r\n");
        $this->expect($this->read(), [250]);
        $this->cmd('QUIT', [221]);
        fclose($this->socket);
    }

    private function authLogin(string $user, string $pass): void
    {
        $this->cmd('AUTH LOGIN', [334]);
        $this->cmd(base64_encode($user), [334]);
        $this->cmd(base64_encode($pass), [235]);
    }

    private function cmd(string $cmd, array $okCodes): void
    {
        fwrite($this->socket, $cmd . "\r\n");
        $this->expect($this->read(), $okCodes);
    }

    private function read(): string
    {
        $data = '';
        while ($line = fgets($this->socket, 515)) {
            $data .= $line;
            if (isset($line[3]) && $line[3] === ' ') {
                break;
            }
        }
        return $data;
    }

    private function expect(string $response, array $codes): void
    {
        $code = (int)substr(trim($response), 0, 3);
        if (!in_array($code, $codes, true)) {
            throw new RuntimeException('SMTP: respuesta inesperada — ' . trim($response));
        }
    }

    private function formatAddress(string $email, string $name): string
    {
        $name = trim($name);
        if ($name === '') {
            return $email;
        }
        return $this->encodeHeader($name) . ' <' . $email . '>';
    }

    private function encodeHeader(string $text): string
    {
        if (preg_match('/[^\x20-\x7E]/', $text)) {
            return '=?UTF-8?B?' . base64_encode($text) . '?=';
        }
        return $text;
    }
}
