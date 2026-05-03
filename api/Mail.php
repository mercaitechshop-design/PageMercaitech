<?php
// Mercaitech — Minimal SMTP mailer (no dependencies)
// Supports Gmail SMTP with App Password (STARTTLS on port 587)
//
// Usage:
//   $mail = new Mail();
//   $mail->to('destino@gmail.com', 'Nombre')
//        ->subject('Asunto')
//        ->body('<h1>HTML body</h1>')
//        ->send();

declare(strict_types=1);

class Mail {
    private string $host;
    private int    $port;
    private string $user;
    private string $pass;
    private string $fromEmail;
    private string $fromName;

    private string $toEmail   = '';
    private string $toName    = '';
    private string $subject   = '';
    private string $htmlBody  = '';

    public function __construct() {
        $this->host      = defined('SMTP_HOST')       ? SMTP_HOST       : 'smtp.gmail.com';
        $this->port      = defined('SMTP_PORT')       ? (int)SMTP_PORT  : 587;
        $this->user      = defined('SMTP_USER')       ? SMTP_USER       : '';
        $this->pass      = defined('SMTP_PASS')       ? SMTP_PASS       : '';
        $this->fromEmail = defined('MAIL_FROM_EMAIL') ? MAIL_FROM_EMAIL : SMTP_USER ?? '';
        $this->fromName  = defined('MAIL_FROM_NAME')  ? MAIL_FROM_NAME  : 'Mercaitech';
    }

    public function to(string $email, string $name = ''): static {
        $this->toEmail = $email;
        $this->toName  = $name;
        return $this;
    }

    public function subject(string $s): static { $this->subject = $s; return $this; }
    public function body(string $html): static  { $this->htmlBody = $html; return $this; }

    public function send(): bool {
        if (!$this->user || !$this->pass) {
            error_log('Mail: SMTP credentials not configured (SMTP_USER / SMTP_PASS)');
            return false;
        }
        try {
            return $this->_sendSmtp();
        } catch (\Throwable $e) {
            error_log('Mail error: ' . $e->getMessage());
            return false;
        }
    }

    private function _sendSmtp(): bool {
        $socket = @fsockopen('tcp://' . $this->host, $this->port, $errno, $errstr, 10);
        if (!$socket) throw new \RuntimeException("SMTP connect failed: $errstr ($errno)");

        $this->_expect($socket, 220);
        $this->_cmd($socket, "EHLO " . gethostname(), 250);

        // STARTTLS
        $this->_cmd($socket, "STARTTLS", 220);
        if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            throw new \RuntimeException('TLS handshake failed');
        }
        $this->_cmd($socket, "EHLO " . gethostname(), 250);

        // Auth
        $this->_cmd($socket, "AUTH LOGIN", 334);
        $this->_cmd($socket, base64_encode($this->user), 334);
        $this->_cmd($socket, base64_encode($this->pass), 235);

        // Envelope
        $this->_cmd($socket, "MAIL FROM:<{$this->fromEmail}>", 250);
        $this->_cmd($socket, "RCPT TO:<{$this->toEmail}>", 250);
        $this->_cmd($socket, "DATA", 354);

        // Headers + body
        $boundary = 'mp_' . md5(uniqid());
        $headers  = implode("\r\n", [
            "From: =?UTF-8?B?" . base64_encode($this->fromName) . "?= <{$this->fromEmail}>",
            "To: =?UTF-8?B?"   . base64_encode($this->toName  ?: $this->toEmail) . "?= <{$this->toEmail}>",
            "Subject: =?UTF-8?B?" . base64_encode($this->subject) . "?=",
            "MIME-Version: 1.0",
            "Content-Type: multipart/alternative; boundary=\"$boundary\"",
            "X-Mailer: Mercaitech/1.0",
            "Date: " . date('r'),
        ]);

        $plain = strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $this->htmlBody));
        $msg   = $headers . "\r\n\r\n"
            . "--$boundary\r\n"
            . "Content-Type: text/plain; charset=UTF-8\r\n\r\n"
            . $plain . "\r\n"
            . "--$boundary\r\n"
            . "Content-Type: text/html; charset=UTF-8\r\n\r\n"
            . $this->htmlBody . "\r\n"
            . "--$boundary--\r\n"
            . ".";

        $this->_cmd($socket, $msg, 250);
        $this->_cmd($socket, "QUIT", 221);
        fclose($socket);
        return true;
    }

    private function _cmd($socket, string $cmd, int $expect): string {
        fwrite($socket, $cmd . "\r\n");
        return $this->_expect($socket, $expect);
    }

    private function _expect($socket, int $code): string {
        $response = '';
        while ($line = fgets($socket, 512)) {
            $response .= $line;
            if (substr($line, 3, 1) === ' ') break;
        }
        $actual = (int) substr($response, 0, 3);
        if ($actual !== $code) {
            throw new \RuntimeException("SMTP expected $code, got $actual: " . trim($response));
        }
        return $response;
    }

    // ── Email templates ────────────────────────────────────
    public static function templateReset(string $nombre, string $resetUrl): string {
        return <<<HTML
<!DOCTYPE html>
<html lang="es">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>
<body style="margin:0;padding:0;background:#03060D;font-family:-apple-system,BlinkMacSystemFont,'Inter',sans-serif">
  <table width="100%" cellpadding="0" cellspacing="0" style="background:#03060D;padding:40px 20px">
    <tr><td align="center">
      <table width="560" cellpadding="0" cellspacing="0" style="background:#0B1124;border-radius:16px;border:1px solid rgba(255,255,255,.08);overflow:hidden;max-width:100%">
        <!-- Header -->
        <tr>
          <td style="background:linear-gradient(135deg,#001A47 0%,#002C7A 50%,#0B1124 100%);padding:32px 40px;text-align:center;border-bottom:1px solid rgba(31,214,255,.15)">
            <span style="font-weight:800;font-size:22px;color:#fff;letter-spacing:-0.02em">MERCAI<span style="color:#1A8CFF">TECH</span></span>
          </td>
        </tr>
        <!-- Body -->
        <tr>
          <td style="padding:40px">
            <p style="color:#8593B2;font-size:13px;margin:0 0 8px;letter-spacing:.18em;text-transform:uppercase">Seguridad de cuenta</p>
            <h1 style="color:#fff;font-size:26px;font-weight:800;letter-spacing:-0.025em;margin:0 0 16px">Restablecer contraseña</h1>
            <p style="color:#B4BED4;font-size:15px;line-height:1.6;margin:0 0 28px">
              Hola <strong style="color:#fff">{$nombre}</strong>, recibimos una solicitud para restablecer la contraseña de tu cuenta en Mercaitech.
            </p>
            <table cellpadding="0" cellspacing="0" width="100%"><tr><td align="center" style="padding:8px 0 32px">
              <a href="{$resetUrl}" style="display:inline-block;background:#0066FF;color:#fff;text-decoration:none;font-weight:700;font-size:14px;letter-spacing:.07em;text-transform:uppercase;padding:16px 36px;border-radius:10px;box-shadow:0 6px 18px rgba(0,102,255,.4)">
                RESTABLECER CONTRASEÑA
              </a>
            </td></tr></table>
            <p style="color:#8593B2;font-size:13px;line-height:1.6;margin:0 0 12px">
              Este enlace expirará en <strong style="color:#fff">1 hora</strong>. Si no solicitaste este cambio, puedes ignorar este correo de forma segura.
            </p>
            <p style="color:#5C6B8C;font-size:12px;word-break:break-all">
              Si el botón no funciona, copia este enlace: {$resetUrl}
            </p>
          </td>
        </tr>
        <!-- Footer -->
        <tr>
          <td style="padding:20px 40px;border-top:1px solid rgba(255,255,255,.06);text-align:center">
            <p style="color:#5C6B8C;font-size:12px;margin:0">© 2026 Mercaitech · Mercancía · IA · Tecnología</p>
          </td>
        </tr>
      </table>
    </td></tr>
  </table>
</body>
</html>
HTML;
    }

    public static function templateWelcome(string $nombre, string $verifyUrl = ''): string {
        $verifySection = $verifyUrl ? "
            <table cellpadding='0' cellspacing='0' width='100%'><tr><td align='center' style='padding:8px 0 28px'>
              <a href='$verifyUrl' style='display:inline-block;background:#0066FF;color:#fff;text-decoration:none;font-weight:700;font-size:14px;letter-spacing:.07em;text-transform:uppercase;padding:16px 36px;border-radius:10px'>
                VERIFICAR CORREO
              </a>
            </td></tr></table>" : '';

        return <<<HTML
<!DOCTYPE html>
<html lang="es">
<head><meta charset="UTF-8"></head>
<body style="margin:0;padding:0;background:#03060D;font-family:-apple-system,BlinkMacSystemFont,'Inter',sans-serif">
  <table width="100%" cellpadding="0" cellspacing="0" style="background:#03060D;padding:40px 20px">
    <tr><td align="center">
      <table width="560" cellpadding="0" cellspacing="0" style="background:#0B1124;border-radius:16px;border:1px solid rgba(255,255,255,.08);overflow:hidden;max-width:100%">
        <tr>
          <td style="background:linear-gradient(135deg,#001A47 0%,#002C7A 50%,#0B1124 100%);padding:32px 40px;text-align:center;border-bottom:1px solid rgba(31,214,255,.15)">
            <span style="font-weight:800;font-size:22px;color:#fff;letter-spacing:-0.02em">MERCAI<span style="color:#1A8CFF">TECH</span></span>
          </td>
        </tr>
        <tr>
          <td style="padding:40px">
            <h1 style="color:#fff;font-size:26px;font-weight:800;letter-spacing:-0.025em;margin:0 0 16px">¡Bienvenido/a, {$nombre}!</h1>
            <p style="color:#B4BED4;font-size:15px;line-height:1.6;margin:0 0 24px">
              Tu cuenta en Mercaitech ha sido creada exitosamente. Ya puedes explorar miles de productos con envíos rápidos, garantía total y <strong style="color:#1FD6FF">Inteligencia</strong> que te entiende.
            </p>
            {$verifySection}
            <p style="color:#8593B2;font-size:13px">Código de descuento de bienvenida: <strong style="color:#1FD6FF;font-family:monospace">BIENVENIDO10</strong></p>
          </td>
        </tr>
        <tr>
          <td style="padding:20px 40px;border-top:1px solid rgba(255,255,255,.06);text-align:center">
            <p style="color:#5C6B8C;font-size:12px;margin:0">© 2026 Mercaitech · Mercancía · IA · Tecnología</p>
          </td>
        </tr>
      </table>
    </td></tr>
  </table>
</body>
</html>
HTML;
    }
}
