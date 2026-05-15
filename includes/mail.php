<?php
declare(strict_types=1);

require_once __DIR__ . '/functions.php';

function epl_smtp_habilitado(): bool {
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
 * @return array{ok: bool, error?: string}
 */
function epl_mail_enviar(string $to, string $subject, string $bodyHtml, ?string $toName = null): array {
    if (!epl_smtp_habilitado()) {
        return ['ok' => false, 'error' => 'SMTP está desactivado. Marcá «Activar envío de correos por SMTP» y pulsá Guardar SMTP o «Guardar y enviar correo de prueba».'];
    }

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
        // Gmail: autenticas con @gmail.com; el From visible puede ser un alias verificado en "Enviar como".
        $envelopeFrom = trim($this->cfg['user'] ?? '');
        if ($envelopeFrom === '' || !filter_var($envelopeFrom, FILTER_VALIDATE_EMAIL)) {
            $envelopeFrom = $fromEmail;
        }
        $this->cmd('MAIL FROM:<' . $envelopeFrom . '>', [250, 251]);
        $this->cmd('RCPT TO:<' . $to . '>', [250, 251]);
        $this->cmd('DATA', [354]);

        $headers = [
            'From: ' . $fromHeader,
            'To: ' . $toHeader,
            'Subject: ' . $this->encodeHeader($subject),
            'MIME-Version: 1.0',
            'Content-Type: text/html; charset=UTF-8',
            'Content-Transfer-Encoding: 8bit',
            'Date: ' . date('r'),
        ];
        $replyTo = trim($this->cfg['reply_to'] ?? '');
        if ($replyTo !== '' && filter_var($replyTo, FILTER_VALIDATE_EMAIL)) {
            $headers[] = 'Reply-To: ' . $replyTo;
        }
        $message = implode("\r\n", $headers) . "\r\n\r\n" . $bodyHtml . "\r\n.";
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
