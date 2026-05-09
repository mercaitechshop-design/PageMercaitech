<?php
declare(strict_types=1);

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as MailerException;

/**
 * Thin wrapper around PHPMailer with STARTTLS for Gmail.
 * Templates de correo incluidos como métodos estáticos.
 */
class Mail {
    private PHPMailer $mailer;

    public function __construct() {
        require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

        $this->mailer = new PHPMailer(true);
        $this->mailer->isSMTP();
        $this->mailer->Host       = defined('SMTP_HOST') ? SMTP_HOST : 'smtp.gmail.com';
        $this->mailer->Port       = defined('SMTP_PORT') ? (int) SMTP_PORT : 587;
        $this->mailer->SMTPAuth   = true;
        $this->mailer->Username   = defined('SMTP_USER') ? SMTP_USER : '';
        $this->mailer->Password   = defined('SMTP_PASS') ? SMTP_PASS : '';
        $this->mailer->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $this->mailer->CharSet    = 'UTF-8';
        $this->mailer->Timeout    = 20;
        // Desactiva verificación SSL — necesario en localhost / dev
        $this->mailer->SMTPOptions = [
            'ssl' => [
                'verify_peer'       => false,
                'verify_peer_name'  => false,
                'allow_self_signed' => true,
            ],
        ];

        $fromEmail = defined('MAIL_FROM_EMAIL') ? MAIL_FROM_EMAIL : (defined('SMTP_USER') ? SMTP_USER : '');
        $fromName  = defined('MAIL_FROM_NAME')  ? MAIL_FROM_NAME  : 'Mercaitech';
        $this->mailer->setFrom($fromEmail, $fromName);
    }

    public function to(string $email, string $name = ''): static {
        $this->mailer->addAddress($email, $name);
        return $this;
    }

    public function subject(string $s): static {
        $this->mailer->Subject = $s;
        return $this;
    }

    public function replyTo(string $email, string $name = ''): static {
        $this->mailer->addReplyTo($email, $name);
        return $this;
    }

    public function body(string $html, string $plain = ''): static {
        $this->mailer->isHTML(true);
        $this->mailer->Body    = $html;
        $this->mailer->AltBody = $plain ?: strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $html));
        return $this;
    }

    /** @throws MailerException|\Throwable */
    public function send(): bool {
        return $this->mailer->send();
    }

    public function getError(): string {
        return $this->mailer->ErrorInfo;
    }

    // ── Email templates ──────────────────────────────────────────────────────

    public static function templateCode(string $nombre, string $code): string {
        return <<<HTML
<!DOCTYPE html>
<html lang="es">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>
<body style="margin:0;padding:0;background:#03060D;font-family:-apple-system,BlinkMacSystemFont,'Inter',sans-serif">
  <table width="100%" cellpadding="0" cellspacing="0" style="background:#03060D;padding:40px 20px">
    <tr><td align="center">
      <table width="520" cellpadding="0" cellspacing="0" style="background:#0B1124;border-radius:16px;border:1px solid rgba(255,255,255,.08);overflow:hidden;max-width:100%">
        <tr>
          <td style="background:linear-gradient(135deg,#001A47,#002C7A,#0B1124);padding:26px 40px;border-bottom:1px solid rgba(31,214,255,.15)">
            <span style="font-weight:800;font-size:20px;color:#fff;letter-spacing:-.02em">MERCAI<span style="color:#1A8CFF">TECH</span></span>
            <span style="font-size:12px;color:#8593B2;margin-left:12px">Restablecer contraseña</span>
          </td>
        </tr>
        <tr>
          <td style="padding:40px;text-align:center">
            <h2 style="color:#fff;font-size:22px;font-weight:800;margin:0 0 8px">Código de verificación</h2>
            <p style="color:#8593B2;font-size:14px;margin:0 0 32px;line-height:1.6">Hola <strong style="color:#fff">{$nombre}</strong>, usa el código de abajo para restablecer tu contraseña.</p>
            <div style="background:rgba(0,102,255,.08);border:1px solid rgba(0,102,255,.3);border-radius:14px;padding:28px 20px;margin-bottom:28px">
              <div style="font-size:46px;font-weight:900;letter-spacing:14px;color:#fff;font-family:monospace;line-height:1">{$code}</div>
              <p style="color:#FFB020;font-size:12px;margin:14px 0 0;font-weight:600">Expira en 15 minutos</p>
            </div>
            <p style="color:#5C6B8C;font-size:12px;line-height:1.6;margin:0">Si no solicitaste este código, ignora este correo.<br>Tu cuenta permanece segura.</p>
          </td>
        </tr>
        <tr>
          <td style="padding:16px 40px;border-top:1px solid rgba(255,255,255,.06);text-align:center">
            <p style="color:#5C6B8C;font-size:12px;margin:0">© 2026 Mercaitech · Correo automático, no responder</p>
          </td>
        </tr>
      </table>
    </td></tr>
  </table>
</body>
</html>
HTML;
    }

    public static function templateReset(string $nombre, string $resetUrl): string {
        return <<<HTML
<!DOCTYPE html>
<html lang="es">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>
<body style="margin:0;padding:0;background:#03060D;font-family:-apple-system,BlinkMacSystemFont,'Inter',sans-serif">
  <table width="100%" cellpadding="0" cellspacing="0" style="background:#03060D;padding:40px 20px">
    <tr><td align="center">
      <table width="560" cellpadding="0" cellspacing="0" style="background:#0B1124;border-radius:16px;border:1px solid rgba(255,255,255,.08);overflow:hidden;max-width:100%">
        <tr>
          <td style="background:linear-gradient(135deg,#001A47 0%,#002C7A 50%,#0B1124 100%);padding:32px 40px;text-align:center;border-bottom:1px solid rgba(31,214,255,.15)">
            <span style="font-weight:800;font-size:22px;color:#fff;letter-spacing:-.02em">MERCAI<span style="color:#1A8CFF">TECH</span></span>
          </td>
        </tr>
        <tr>
          <td style="padding:40px">
            <p style="color:#8593B2;font-size:13px;margin:0 0 8px;letter-spacing:.18em;text-transform:uppercase">Seguridad de cuenta</p>
            <h1 style="color:#fff;font-size:26px;font-weight:800;letter-spacing:-.025em;margin:0 0 16px">Restablecer contraseña</h1>
            <p style="color:#B4BED4;font-size:15px;line-height:1.6;margin:0 0 28px">Hola <strong style="color:#fff">{$nombre}</strong>, recibimos una solicitud para restablecer tu contraseña en Mercaitech.</p>
            <table cellpadding="0" cellspacing="0" width="100%"><tr><td align="center" style="padding:8px 0 32px">
              <a href="{$resetUrl}" style="display:inline-block;background:#0066FF;color:#fff;text-decoration:none;font-weight:700;font-size:14px;letter-spacing:.07em;text-transform:uppercase;padding:16px 36px;border-radius:10px;box-shadow:0 6px 18px rgba(0,102,255,.4)">RESTABLECER CONTRASEÑA</a>
            </td></tr></table>
            <p style="color:#8593B2;font-size:13px;line-height:1.6;margin:0 0 12px">Este enlace expirará en <strong style="color:#fff">1 hora</strong>. Si no solicitaste este cambio, ignora este correo.</p>
            <p style="color:#5C6B8C;font-size:12px;word-break:break-all">Enlace: {$resetUrl}</p>
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

    public static function templateWelcome(string $nombre, string $verifyUrl = ''): string {
        $verifySection = $verifyUrl ? "
          <table cellpadding='0' cellspacing='0' width='100%'><tr><td align='center' style='padding:8px 0 28px'>
            <a href='$verifyUrl' style='display:inline-block;background:#0066FF;color:#fff;text-decoration:none;font-weight:700;font-size:14px;letter-spacing:.07em;text-transform:uppercase;padding:16px 36px;border-radius:10px'>VERIFICAR CORREO</a>
          </td></tr></table>" : '';

        return <<<HTML
<!DOCTYPE html>
<html lang="es">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>
<body style="margin:0;padding:0;background:#03060D;font-family:-apple-system,BlinkMacSystemFont,'Inter',sans-serif">
  <table width="100%" cellpadding="0" cellspacing="0" style="background:#03060D;padding:40px 20px">
    <tr><td align="center">
      <table width="560" cellpadding="0" cellspacing="0" style="background:#0B1124;border-radius:16px;border:1px solid rgba(255,255,255,.08);overflow:hidden;max-width:100%">
        <tr>
          <td style="background:linear-gradient(135deg,#001A47 0%,#002C7A 50%,#0B1124 100%);padding:32px 40px;text-align:center;border-bottom:1px solid rgba(31,214,255,.15)">
            <span style="font-weight:800;font-size:22px;color:#fff;letter-spacing:-.02em">MERCAI<span style="color:#1A8CFF">TECH</span></span>
          </td>
        </tr>
        <tr>
          <td style="padding:40px">
            <h1 style="color:#fff;font-size:26px;font-weight:800;letter-spacing:-.025em;margin:0 0 16px">¡Bienvenido/a, {$nombre}!</h1>
            <p style="color:#B4BED4;font-size:15px;line-height:1.6;margin:0 0 24px">Tu cuenta en Mercaitech ha sido creada exitosamente. Ya puedes explorar miles de productos con envíos rápidos, garantía total y <strong style="color:#1FD6FF">Inteligencia</strong> que te entiende.</p>
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
