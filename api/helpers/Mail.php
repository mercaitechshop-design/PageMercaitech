<?php
declare(strict_types=1);

class Mail {
    private string $host;
    private int    $port;
    private string $user;
    private string $pass;
    private string $fromEmail;
    private string $fromName;

    private string $toEmail    = '';
    private string $toName     = '';
    private string $replyTo    = '';
    private string $subject    = '';
    private string $htmlBody   = '';

    public function __construct() {
        $this->host      = defined('SMTP_HOST')       ? SMTP_HOST       : 'smtp.gmail.com';
        $this->port      = defined('SMTP_PORT')       ? (int)SMTP_PORT  : 587;
        $this->user      = defined('SMTP_USER')       ? SMTP_USER       : '';
        $this->pass      = defined('SMTP_PASS')       ? SMTP_PASS       : '';
        $this->fromEmail = defined('MAIL_FROM_EMAIL') ? MAIL_FROM_EMAIL : ($this->user ?: '');
        $this->fromName  = defined('MAIL_FROM_NAME')  ? MAIL_FROM_NAME  : 'Mercaitech';
    }

    public function to(string $email, string $name = ''): static {
        $this->toEmail = $email;
        $this->toName  = $name;
        return $this;
    }

    public function replyTo(string $email): static {
        $this->replyTo = $email;
        return $this;
    }

    public function subject(string $s): static { $this->subject = $s; return $this; }
    public function body(string $html): static  { $this->htmlBody = $html; return $this; }

    public function send(): bool {
        if (!$this->user || !$this->pass) {
            error_log('[Mail] SMTP credentials not configured (SMTP_USER / SMTP_PASS)');
            return false;
        }
        if (!$this->toEmail) {
            error_log('[Mail] No recipient set');
            return false;
        }
        try {
            return $this->_sendSmtp();
        } catch (\Throwable $e) {
            error_log('[Mail] ' . $e->getMessage());
            return false;
        }
    }

    private function _sendSmtp(): bool {
        // Connect TCP
        $socket = @fsockopen('tcp://' . $this->host, $this->port, $errno, $errstr, 15);
        if (!$socket) {
            throw new \RuntimeException("SMTP connect failed [{$errno}]: {$errstr}");
        }
        stream_set_timeout($socket, 15);

        $this->_expect($socket, 220, 'connect');
        $this->_send($socket, "EHLO mercaitech.com");
        $this->_readResponse($socket); // absorb multi-line EHLO

        // STARTTLS
        $this->_cmd($socket, "STARTTLS", 220);
        if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_1_CLIENT | STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            throw new \RuntimeException('TLS handshake failed');
        }

        $this->_send($socket, "EHLO mercaitech.com");
        $this->_readResponse($socket); // absorb post-TLS EHLO

        // Auth LOGIN
        $this->_cmd($socket, "AUTH LOGIN", 334);
        $this->_cmd($socket, base64_encode($this->user), 334);
        $this->_cmd($socket, base64_encode($this->pass), 235);

        // Envelope
        $this->_cmd($socket, "MAIL FROM:<{$this->fromEmail}>", 250);
        $this->_cmd($socket, "RCPT TO:<{$this->toEmail}>", 250);
        $this->_cmd($socket, "DATA", 354);

        // Build message
        $boundary = 'mp_' . bin2hex(random_bytes(8));
        $plain    = strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $this->htmlBody));

        $hdrs = [
            "From: " . $this->_encodeHeader($this->fromName) . " <{$this->fromEmail}>",
            "To: "   . $this->_encodeHeader($this->toName ?: $this->toEmail) . " <{$this->toEmail}>",
        ];
        if ($this->replyTo) {
            $hdrs[] = "Reply-To: <{$this->replyTo}>";
        }
        $hdrs = array_merge($hdrs, [
            "Subject: " . $this->_encodeHeader($this->subject),
            "MIME-Version: 1.0",
            "Content-Type: multipart/alternative; boundary=\"{$boundary}\"",
            "X-Mailer: Mercaitech/2.0",
            "Date: " . date('r'),
            "Message-ID: <" . uniqid('mt_') . "@mercaitech.com>",
        ]);

        $msg  = implode("\r\n", $hdrs) . "\r\n\r\n";
        $msg .= "--{$boundary}\r\n";
        $msg .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $msg .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
        $msg .= $plain . "\r\n\r\n";
        $msg .= "--{$boundary}\r\n";
        $msg .= "Content-Type: text/html; charset=UTF-8\r\n";
        $msg .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
        $msg .= $this->htmlBody . "\r\n\r\n";
        $msg .= "--{$boundary}--";

        // Dot-stuffing: lines starting with "." must be doubled
        $msg = preg_replace('/^\./m', '..', $msg);

        // Send DATA body terminated by CRLF.CRLF
        fwrite($socket, $msg . "\r\n.\r\n");
        $this->_expect($socket, 250, 'DATA end');

        $this->_send($socket, "QUIT");
        fclose($socket);
        return true;
    }

    /** Send a line and expect a specific response code. */
    private function _cmd($socket, string $cmd, int $expect): string {
        $this->_send($socket, $cmd);
        return $this->_expect($socket, $expect, $cmd);
    }

    /** Write a CRLF-terminated line to socket. */
    private function _send($socket, string $line): void {
        fwrite($socket, $line . "\r\n");
    }

    /** Read a full (possibly multi-line) SMTP response and check the status code. */
    private function _expect($socket, int $code, string $ctx = ''): string {
        $response = $this->_readResponse($socket);
        $actual   = (int) substr($response, 0, 3);
        if ($actual !== $code) {
            throw new \RuntimeException(
                sprintf('SMTP [%s] expected %d, got %d: %s', $ctx, $code, $actual, trim($response))
            );
        }
        return $response;
    }

    /** Read a complete SMTP response (handles multi-line 250-... responses). */
    private function _readResponse($socket): string {
        $response = '';
        while ($line = fgets($socket, 1024)) {
            $response .= $line;
            // Continue reading while 4th char is '-' (multi-line response)
            if (strlen($line) >= 4 && $line[3] === ' ') break;
            if (strlen($line) < 4) break;
        }
        return $response;
    }

    private function _encodeHeader(string $value): string {
        if (preg_match('/[^\x20-\x7E]/', $value)) {
            return '=?UTF-8?B?' . base64_encode($value) . '?=';
        }
        return $value;
    }

    // ── Email templates ──────────────────────────────────────────
    public static function templateReset(string $nombre, string $resetUrl): string {
        return <<<HTML
<!DOCTYPE html>
<html lang="es">
<head><meta charset="UTF-8"></head>
<body style="margin:0;padding:0;background:#03060D;font-family:-apple-system,BlinkMacSystemFont,'Inter',sans-serif">
  <table width="100%" cellpadding="0" cellspacing="0" style="background:#03060D;padding:40px 20px">
    <tr><td align="center">
      <table width="560" cellpadding="0" cellspacing="0" style="background:#0B1124;border-radius:16px;border:1px solid rgba(255,255,255,.08);overflow:hidden;max-width:100%">
        <tr><td style="background:linear-gradient(135deg,#001A47 0%,#002C7A 50%,#0B1124 100%);padding:32px 40px;text-align:center;border-bottom:1px solid rgba(31,214,255,.15)">
          <span style="font-weight:800;font-size:22px;color:#fff;letter-spacing:-.02em">MERCAI<span style="color:#1A8CFF">TECH</span></span>
        </td></tr>
        <tr><td style="padding:40px">
          <p style="color:#8593B2;font-size:13px;margin:0 0 8px;letter-spacing:.18em;text-transform:uppercase">Seguridad de cuenta</p>
          <h1 style="color:#fff;font-size:26px;font-weight:800;letter-spacing:-.025em;margin:0 0 16px">Restablecer contraseña</h1>
          <p style="color:#B4BED4;font-size:15px;line-height:1.6;margin:0 0 28px">Hola <strong style="color:#fff">{$nombre}</strong>, recibimos una solicitud para restablecer tu contraseña en Mercaitech.</p>
          <table cellpadding="0" cellspacing="0" width="100%"><tr><td align="center" style="padding:8px 0 32px">
            <a href="{$resetUrl}" style="display:inline-block;background:#0066FF;color:#fff;text-decoration:none;font-weight:700;font-size:14px;letter-spacing:.07em;text-transform:uppercase;padding:16px 36px;border-radius:10px;box-shadow:0 6px 18px rgba(0,102,255,.4)">RESTABLECER CONTRASEÑA</a>
          </td></tr></table>
          <p style="color:#8593B2;font-size:13px;line-height:1.6;margin:0 0 12px">Este enlace expirará en <strong style="color:#fff">1 hora</strong>. Si no solicitaste este cambio, ignora este correo.</p>
          <p style="color:#5C6B8C;font-size:12px;word-break:break-all">Enlace: {$resetUrl}</p>
        </td></tr>
        <tr><td style="padding:20px 40px;border-top:1px solid rgba(255,255,255,.06);text-align:center">
          <p style="color:#5C6B8C;font-size:12px;margin:0">© 2026 Mercaitech · Mercancía · IA · Tecnología</p>
        </td></tr>
      </table>
    </td></tr>
  </table>
</body></html>
HTML;
    }

    public static function templateWelcome(string $nombre, string $verifyUrl = ''): string {
        $verifySection = $verifyUrl ? "
            <table cellpadding='0' cellspacing='0' width='100%'><tr><td align='center' style='padding:8px 0 28px'>
              <a href='$verifyUrl' style='display:inline-block;background:#0066FF;color:#fff;text-decoration:none;font-weight:700;font-size:14px;letter-spacing:.07em;text-transform:uppercase;padding:16px 36px;border-radius:10px'>VERIFICAR CORREO</a>
            </td></tr></table>" : '';

        return <<<HTML
<!DOCTYPE html>
<html lang="es">
<head><meta charset="UTF-8"></head>
<body style="margin:0;padding:0;background:#03060D;font-family:-apple-system,BlinkMacSystemFont,'Inter',sans-serif">
  <table width="100%" cellpadding="0" cellspacing="0" style="background:#03060D;padding:40px 20px">
    <tr><td align="center">
      <table width="560" cellpadding="0" cellspacing="0" style="background:#0B1124;border-radius:16px;border:1px solid rgba(255,255,255,.08);overflow:hidden;max-width:100%">
        <tr><td style="background:linear-gradient(135deg,#001A47 0%,#002C7A 50%,#0B1124 100%);padding:32px 40px;text-align:center;border-bottom:1px solid rgba(31,214,255,.15)">
          <span style="font-weight:800;font-size:22px;color:#fff;letter-spacing:-.02em">MERCAI<span style="color:#1A8CFF">TECH</span></span>
        </td></tr>
        <tr><td style="padding:40px">
          <h1 style="color:#fff;font-size:26px;font-weight:800;letter-spacing:-.025em;margin:0 0 16px">¡Bienvenido/a, {$nombre}!</h1>
          <p style="color:#B4BED4;font-size:15px;line-height:1.6;margin:0 0 24px">Tu cuenta en Mercaitech ha sido creada exitosamente. Ya puedes explorar miles de productos con envíos rápidos, garantía total y <strong style="color:#1FD6FF">Inteligencia</strong> que te entiende.</p>
          {$verifySection}
          <p style="color:#8593B2;font-size:13px">Código de descuento de bienvenida: <strong style="color:#1FD6FF;font-family:monospace">BIENVENIDO10</strong></p>
        </td></tr>
        <tr><td style="padding:20px 40px;border-top:1px solid rgba(255,255,255,.06);text-align:center">
          <p style="color:#5C6B8C;font-size:12px;margin:0">© 2026 Mercaitech · Mercancía · IA · Tecnología</p>
        </td></tr>
      </table>
    </td></tr>
  </table>
</body></html>
HTML;
    }
}
