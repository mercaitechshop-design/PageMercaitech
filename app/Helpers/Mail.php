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
        $isProd = defined('APP_ENV') && APP_ENV === 'production';
        $this->mailer->SMTPOptions = [
            'ssl' => [
                'verify_peer'       => $isProd,
                'verify_peer_name'  => $isProd,
                'allow_self_signed' => !$isProd,
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
        $nombre = htmlspecialchars($nombre, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $code   = htmlspecialchars($code,   ENT_QUOTES | ENT_HTML5, 'UTF-8');
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
              <p style="color:#FFB020;font-size:12px;margin:14px 0 0;font-weight:600">Expira en 5 minutos</p>
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
        $nombre   = htmlspecialchars($nombre,   ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $resetUrl = htmlspecialchars($resetUrl, ENT_QUOTES | ENT_HTML5, 'UTF-8');
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
        $safeUrl    = htmlspecialchars($verifyUrl, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $ctaSection = $verifyUrl
            ? "<a href=\"{$safeUrl}\" style=\"display:inline-block;background:#0066FF;color:#fff;text-decoration:none;font-weight:700;font-size:13px;letter-spacing:.07em;text-transform:uppercase;padding:14px 36px;border-radius:10px;box-shadow:0 6px 18px rgba(0,102,255,.4)\">VERIFICAR CORREO</a>"
            : "<a href=\"#\" style=\"display:inline-block;background:#0066FF;color:#fff;text-decoration:none;font-weight:700;font-size:13px;letter-spacing:.07em;text-transform:uppercase;padding:14px 36px;border-radius:10px;box-shadow:0 6px 18px rgba(0,102,255,.4)\">EXPLORAR PRODUCTOS</a>";

        $firstName = htmlspecialchars(explode(' ', trim($nombre))[0], ENT_QUOTES | ENT_HTML5, 'UTF-8');

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
          <td style="background:linear-gradient(135deg,#001A47 0%,#002C7A 50%,#0B1124 100%);padding:28px 40px;text-align:center;border-bottom:1px solid rgba(31,214,255,.15)">
            <table width="100%" cellpadding="0" cellspacing="0"><tr>
              <td align="left"><span style="font-weight:800;font-size:20px;color:#fff;letter-spacing:-.02em">MERCAI<span style="color:#1A8CFF">TECH</span></span></td>
              <td align="right"><span style="font-size:11px;color:#8593B2;letter-spacing:.1em;text-transform:uppercase">Bienvenida</span></td>
            </tr></table>
          </td>
        </tr>

        <!-- Hero -->
        <tr>
          <td style="padding:44px 40px 28px;text-align:center">
            <div style="width:80px;height:80px;border-radius:50%;background:linear-gradient(135deg,#001A66,#003AB8);display:inline-flex;align-items:center;justify-content:center;margin-bottom:20px;box-shadow:0 10px 32px rgba(0,102,255,.4);font-size:36px;line-height:80px">🎉</div>
            <h1 style="color:#fff;font-size:28px;font-weight:800;letter-spacing:-.03em;margin:0 0 10px">¡Bienvenido/a, {$firstName}!</h1>
            <p style="color:#8593B2;font-size:14px;line-height:1.7;margin:0 auto;max-width:380px">Tu cuenta en <strong style="color:#fff">Mercaitech</strong> ha sido creada exitosamente. Ya eres parte de la comunidad.</p>
          </td>
        </tr>

        <!-- Feature cards -->
        <tr>
          <td style="padding:0 40px 28px">
            <table width="100%" cellpadding="0" cellspacing="0">
              <tr>
                <td width="33%" style="padding-right:6px">
                  <table width="100%" cellpadding="0" cellspacing="0" style="background:rgba(0,102,255,.06);border:1px solid rgba(0,102,255,.18);border-radius:12px">
                    <tr><td style="padding:16px 14px;text-align:center">
                      <div style="font-size:24px;margin-bottom:8px">🚚</div>
                      <p style="font-size:12px;font-weight:700;color:#fff;margin:0 0 4px">Envío rápido</p>
                      <p style="font-size:11px;color:#5C6B8C;margin:0">24–48 h a domicilio</p>
                    </td></tr>
                  </table>
                </td>
                <td width="33%" style="padding:0 3px">
                  <table width="100%" cellpadding="0" cellspacing="0" style="background:rgba(46,230,166,.06);border:1px solid rgba(46,230,166,.18);border-radius:12px">
                    <tr><td style="padding:16px 14px;text-align:center">
                      <div style="font-size:24px;margin-bottom:8px">🛡️</div>
                      <p style="font-size:12px;font-weight:700;color:#fff;margin:0 0 4px">Garantía total</p>
                      <p style="font-size:11px;color:#5C6B8C;margin:0">Compra con confianza</p>
                    </td></tr>
                  </table>
                </td>
                <td width="33%" style="padding-left:6px">
                  <table width="100%" cellpadding="0" cellspacing="0" style="background:rgba(31,214,255,.06);border:1px solid rgba(31,214,255,.18);border-radius:12px">
                    <tr><td style="padding:16px 14px;text-align:center">
                      <div style="font-size:24px;margin-bottom:8px">🤖</div>
                      <p style="font-size:12px;font-weight:700;color:#fff;margin:0 0 4px">IA inteligente</p>
                      <p style="font-size:11px;color:#5C6B8C;margin:0">Hecha para ti</p>
                    </td></tr>
                  </table>
                </td>
              </tr>
            </table>
          </td>
        </tr>

        <!-- Coupon -->
        <tr>
          <td style="padding:0 40px 28px">
            <table width="100%" cellpadding="0" cellspacing="0" style="background:linear-gradient(135deg,rgba(0,102,255,.12),rgba(31,214,255,.08));border:1px dashed rgba(31,214,255,.4);border-radius:14px">
              <tr><td style="padding:20px 24px;text-align:center">
                <p style="font-size:11px;text-transform:uppercase;letter-spacing:.12em;color:#8593B2;margin:0 0 10px;font-weight:600">🎁 Tu cupón de bienvenida</p>
                <div style="font-size:28px;font-weight:900;letter-spacing:6px;color:#1FD6FF;font-family:monospace;line-height:1;margin-bottom:8px">BIENVENIDO10</div>
                <p style="font-size:12px;color:#5C6B8C;margin:0">10% de descuento en tu primera compra · Sin mínimo de compra</p>
              </td></tr>
            </table>
          </td>
        </tr>

        <!-- CTA -->
        <tr>
          <td style="padding:0 40px 36px;text-align:center">
            {$ctaSection}
          </td>
        </tr>

        <!-- Footer -->
        <tr>
          <td style="padding:20px 40px;border-top:1px solid rgba(255,255,255,.06)">
            <table width="100%" cellpadding="0" cellspacing="0"><tr>
              <td><p style="color:#5C6B8C;font-size:12px;margin:0">¿Preguntas? <a href="mailto:mercaitechshop@gmail.com" style="color:#1A8CFF;text-decoration:none">mercaitechshop@gmail.com</a></p></td>
              <td align="right"><p style="color:#5C6B8C;font-size:12px;margin:0">© 2026 Mercaitech · Colombia</p></td>
            </tr></table>
          </td>
        </tr>

      </table>
    </td></tr>
  </table>
</body>
</html>
HTML;
    }

    public static function templateOrder(
        string $firstName,
        string $name,
        string $phone,
        string $numero,
        float  $total,
        array  $items,
        array  $envio = []
    ): string {
        $fmt        = fn(float $n) => '$ ' . number_format($n, 0, ',', '.');
        $baseUrl    = defined('APP_URL') ? rtrim(APP_URL, '/') : 'http://localhost:8080';
        $pedidosUrl = htmlspecialchars($baseUrl . '/pedidos.html', ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $firstName  = htmlspecialchars($firstName, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        $itemsHtml = implode('', array_map(function($li) use ($fmt) {
            $titulo = htmlspecialchars($li['titulo'] ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $qty    = (int)$li['qty'];
            $total  = $fmt((float)$li['precio'] * $qty);
            return "<tr>
              <td style=\"padding:10px 0;border-bottom:1px solid rgba(255,255,255,.05)\">
                <span style=\"font-size:14px;color:#fff;font-weight:600\">{$titulo}</span>
              </td>
              <td style=\"padding:10px 0;border-bottom:1px solid rgba(255,255,255,.05);text-align:center\">
                <span style=\"font-size:13px;color:#8593B2\">&#215;{$qty}</span>
              </td>
              <td style=\"padding:10px 0;border-bottom:1px solid rgba(255,255,255,.05);text-align:right\">
                <span style=\"font-size:14px;color:#1FD6FF;font-weight:700\">{$total}</span>
              </td>
            </tr>";
        }, $items));

        $totalFmt    = $fmt($total);
        $direccion   = htmlspecialchars($envio['direccion']     ?? '', ENT_QUOTES);
        $ciudad      = htmlspecialchars($envio['ciudad']        ?? '', ENT_QUOTES);
        $pais        = htmlspecialchars($envio['pais']          ?? 'Colombia', ENT_QUOTES);
        $cp          = htmlspecialchars($envio['codigo_postal'] ?? '', ENT_QUOTES);
        $phoneFmt    = $phone ? htmlspecialchars($phone, ENT_QUOTES) : '—';
        $addressLine = trim("{$direccion}, {$ciudad}" . ($cp ? " {$cp}" : '') . ", {$pais}", ', ');
        $nameSafe    = htmlspecialchars($name, ENT_QUOTES);

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
          <td style="background:linear-gradient(135deg,#001A47 0%,#002C7A 50%,#0B1124 100%);padding:28px 40px;border-bottom:1px solid rgba(31,214,255,.15)">
            <table width="100%" cellpadding="0" cellspacing="0"><tr>
              <td><span style="font-weight:800;font-size:20px;color:#fff;letter-spacing:-.02em">MERCAI<span style="color:#1A8CFF">TECH</span></span></td>
              <td align="right"><span style="font-size:11px;color:#8593B2;letter-spacing:.1em;text-transform:uppercase">Confirmación de pedido</span></td>
            </tr></table>
          </td>
        </tr>

        <!-- Success hero -->
        <tr>
          <td style="padding:40px 40px 28px;text-align:center">
            <!--[if mso]><table align="center" cellpadding="0" cellspacing="0"><tr><td width="72" height="72" style="background:#0066FF;border-radius:36px"><![endif]-->
            <table cellpadding="0" cellspacing="0" align="center" style="margin:0 auto 20px">
              <tr>
                <td width="72" height="72" align="center" valign="middle"
                    style="background:#0066FF;border-radius:36px;mso-border-radius:36px;box-shadow:0 8px 28px rgba(0,102,255,.45)">
                  <span style="color:#ffffff;font-size:42px;font-weight:900;font-family:Arial,Helvetica,sans-serif;line-height:1;display:block">&#10003;</span>
                </td>
              </tr>
            </table>
            <!--[if mso]></td></tr></table><![endif]-->
            <h1 style="color:#fff;font-size:26px;font-weight:800;letter-spacing:-.025em;margin:0 0 8px">¡Pedido confirmado!</h1>
            <p style="color:#8593B2;font-size:14px;margin:0;line-height:1.6">Hola <strong style="color:#fff">{$firstName}</strong>, gracias por tu compra en Mercaitech.</p>
          </td>
        </tr>

        <!-- Order number + status -->
        <tr>
          <td style="padding:0 40px 24px">
            <table width="100%" cellpadding="0" cellspacing="0" style="background:rgba(0,102,255,.08);border:1px solid rgba(0,102,255,.25);border-radius:12px">
              <tr>
                <td style="padding:16px 20px">
                  <span style="font-size:11px;text-transform:uppercase;letter-spacing:.1em;color:#5C6B8C">Número de orden</span><br>
                  <span style="font-size:20px;font-weight:800;color:#1FD6FF;font-family:monospace;letter-spacing:.05em">{$numero}</span>
                </td>
                <td align="right" style="padding:16px 20px">
                  <span style="font-size:11px;text-transform:uppercase;letter-spacing:.1em;color:#5C6B8C;display:block;margin-bottom:6px">Estado</span>
                  <span style="font-size:12px;font-weight:700;color:#2EE6A6;background:rgba(46,230,166,.12);border:1px solid rgba(46,230,166,.3);padding:4px 12px;border-radius:20px">Aprobado</span>
                </td>
              </tr>
            </table>
          </td>
        </tr>

        <!-- Products -->
        <tr>
          <td style="padding:0 40px 4px">
            <p style="font-size:11px;text-transform:uppercase;letter-spacing:.1em;color:#5C6B8C;margin:0 0 12px;font-weight:600">&#128230; Productos</p>
            <table width="100%" cellpadding="0" cellspacing="0">
              <tr>
                <th style="text-align:left;font-size:11px;color:#5C6B8C;font-weight:600;padding-bottom:8px;border-bottom:1px solid rgba(255,255,255,.08)">Descripción</th>
                <th style="text-align:center;font-size:11px;color:#5C6B8C;font-weight:600;padding-bottom:8px;border-bottom:1px solid rgba(255,255,255,.08)">Cant.</th>
                <th style="text-align:right;font-size:11px;color:#5C6B8C;font-weight:600;padding-bottom:8px;border-bottom:1px solid rgba(255,255,255,.08)">Precio</th>
              </tr>
              {$itemsHtml}
            </table>
          </td>
        </tr>

        <!-- Totals -->
        <tr>
          <td style="padding:12px 40px 24px">
            <table width="100%" cellpadding="0" cellspacing="0" style="border-top:1px solid rgba(255,255,255,.06)">
              <tr>
                <td style="padding:8px 0 4px"><span style="font-size:13px;color:#8593B2">Envío</span></td>
                <td align="right" style="padding:8px 0 4px"><span style="font-size:13px;color:#2EE6A6;font-weight:600">Gratis</span></td>
              </tr>
              <tr>
                <td style="padding:6px 0 0;border-top:1px solid rgba(255,255,255,.06)"><span style="font-size:16px;font-weight:800;color:#fff">Total</span></td>
                <td align="right" style="padding:6px 0 0;border-top:1px solid rgba(255,255,255,.06)"><span style="font-size:18px;font-weight:800;color:#fff">{$totalFmt}</span></td>
              </tr>
            </table>
          </td>
        </tr>

        <!-- Shipping address -->
        <tr>
          <td style="padding:0 40px 24px">
            <table width="100%" cellpadding="0" cellspacing="0" style="background:rgba(255,255,255,.03);border:1px solid rgba(255,255,255,.07);border-radius:12px">
              <tr><td style="padding:16px 20px">
                <p style="font-size:11px;text-transform:uppercase;letter-spacing:.1em;color:#5C6B8C;margin:0 0 10px;font-weight:600">&#128205; Dirección de entrega</p>
                <p style="font-size:14px;color:#fff;font-weight:700;margin:0 0 4px">{$nameSafe}</p>
                <p style="font-size:13px;color:#B4BED4;margin:0 0 4px">{$addressLine}</p>
                <p style="font-size:13px;color:#8593B2;margin:0">&#128222; {$phoneFmt}</p>
              </td></tr>
            </table>
          </td>
        </tr>

        <!-- Delivery info cards -->
        <tr>
          <td style="padding:0 40px 28px">
            <table width="100%" cellpadding="0" cellspacing="0">
              <tr>
                <td width="48%" style="padding-right:8px">
                  <table width="100%" cellpadding="0" cellspacing="0" style="background:rgba(46,230,166,.05);border:1px solid rgba(46,230,166,.2);border-radius:10px">
                    <tr><td style="padding:14px 16px">
                      <span style="font-size:11px;text-transform:uppercase;color:#5C6B8C;letter-spacing:.08em;display:block;margin-bottom:4px">Tiempo de entrega</span>
                      <span style="font-size:14px;font-weight:700;color:#2EE6A6">24 – 48 horas</span>
                    </td></tr>
                  </table>
                </td>
                <td width="52%" style="padding-left:8px">
                  <table width="100%" cellpadding="0" cellspacing="0" style="background:rgba(0,102,255,.05);border:1px solid rgba(0,102,255,.2);border-radius:10px">
                    <tr><td style="padding:14px 16px">
                      <span style="font-size:11px;text-transform:uppercase;color:#5C6B8C;letter-spacing:.08em;display:block;margin-bottom:4px">Método de pago</span>
                      <span style="font-size:14px;font-weight:700;color:#1A8CFF">MercadoPago</span>
                    </td></tr>
                  </table>
                </td>
              </tr>
            </table>
          </td>
        </tr>

        <!-- CTA -->
        <tr>
          <td style="padding:0 40px 32px;text-align:center">
            <a href="{$pedidosUrl}" style="display:inline-block;background:#0066FF;color:#fff;text-decoration:none;font-weight:700;font-size:13px;letter-spacing:.06em;text-transform:uppercase;padding:14px 32px;border-radius:10px;box-shadow:0 6px 18px rgba(0,102,255,.35)">VER MIS PEDIDOS</a>
          </td>
        </tr>

        <!-- Footer -->
        <tr>
          <td style="padding:20px 40px;border-top:1px solid rgba(255,255,255,.06)">
            <table width="100%" cellpadding="0" cellspacing="0"><tr>
              <td><p style="color:#5C6B8C;font-size:12px;margin:0">¿Preguntas? <a href="mailto:mercaitechshop@gmail.com" style="color:#1A8CFF;text-decoration:none">mercaitechshop@gmail.com</a></p></td>
              <td align="right"><p style="color:#5C6B8C;font-size:12px;margin:0">© 2026 Mercaitech · Colombia</p></td>
            </tr></table>
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
