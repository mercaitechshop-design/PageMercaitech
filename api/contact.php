<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';

setCorsHeaders();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'error' => 'Method not allowed.'], 405);
}

$body    = getJsonBody();
$nombre  = sanitize($body['nombre']   ?? '');
$empresa = sanitize($body['empresa']  ?? '');
$email   = sanitize($body['email']    ?? '');
$telefono= sanitize($body['telefono'] ?? '');
$servicio= sanitize($body['servicio'] ?? '');
$mensaje = sanitize($body['mensaje']  ?? '');

if (!$nombre || !$email || !$mensaje || !$servicio) {
    jsonResponse(['success' => false, 'message' => 'Completa todos los campos requeridos.'], 422);
}
if (!validEmail($email)) {
    jsonResponse(['success' => false, 'message' => 'El correo electrónico no es válido.'], 422);
}
if (strlen($mensaje) < 10) {
    jsonResponse(['success' => false, 'message' => 'El mensaje es demasiado corto.'], 422);
}

try {
    require_once __DIR__ . '/helpers/Mail.php';

    $html = <<<HTML
<!DOCTYPE html>
<html lang="es">
<head><meta charset="UTF-8"></head>
<body style="margin:0;padding:0;background:#03060D;font-family:-apple-system,BlinkMacSystemFont,'Inter',sans-serif">
  <table width="100%" cellpadding="0" cellspacing="0" style="background:#03060D;padding:40px 20px">
    <tr><td align="center">
      <table width="560" cellpadding="0" cellspacing="0" style="background:#0B1124;border-radius:16px;border:1px solid rgba(255,255,255,.08);overflow:hidden;max-width:100%">
        <tr>
          <td style="background:linear-gradient(135deg,#001A47,#002C7A,#0B1124);padding:28px 40px;border-bottom:1px solid rgba(31,214,255,.15)">
            <span style="font-weight:800;font-size:20px;color:#fff;letter-spacing:-.02em">MERCAI<span style="color:#1A8CFF">TECH</span></span>
            <span style="font-size:12px;color:#8593B2;margin-left:12px">Nueva consulta de servicios</span>
          </td>
        </tr>
        <tr>
          <td style="padding:32px 40px">
            <table width="100%" cellpadding="0" cellspacing="0">
              <tr><td style="padding:10px 0;border-bottom:1px solid rgba(255,255,255,.05)">
                <span style="font-size:11px;text-transform:uppercase;letter-spacing:.1em;color:#5C6B8C">Nombre</span><br>
                <span style="font-size:15px;color:#fff;font-weight:600">{$nombre}</span>
              </td></tr>
              <tr><td style="padding:10px 0;border-bottom:1px solid rgba(255,255,255,.05)">
                <span style="font-size:11px;text-transform:uppercase;letter-spacing:.1em;color:#5C6B8C">Empresa / Proyecto</span><br>
                <span style="font-size:15px;color:#fff">{$empresa}</span>
              </td></tr>
              <tr><td style="padding:10px 0;border-bottom:1px solid rgba(255,255,255,.05)">
                <span style="font-size:11px;text-transform:uppercase;letter-spacing:.1em;color:#5C6B8C">Correo electrónico</span><br>
                <a href="mailto:{$email}" style="font-size:15px;color:#1A8CFF;text-decoration:none">{$email}</a>
              </td></tr>
              <tr><td style="padding:10px 0;border-bottom:1px solid rgba(255,255,255,.05)">
                <span style="font-size:11px;text-transform:uppercase;letter-spacing:.1em;color:#5C6B8C">Teléfono</span><br>
                <span style="font-size:15px;color:#fff">+57 {$telefono}</span>
              </td></tr>
              <tr><td style="padding:10px 0;border-bottom:1px solid rgba(255,255,255,.05)">
                <span style="font-size:11px;text-transform:uppercase;letter-spacing:.1em;color:#5C6B8C">Servicio solicitado</span><br>
                <span style="font-size:15px;color:#1FD6FF;font-weight:700">{$servicio}</span>
              </td></tr>
              <tr><td style="padding:14px 0">
                <span style="font-size:11px;text-transform:uppercase;letter-spacing:.1em;color:#5C6B8C">Mensaje</span><br>
                <p style="font-size:14px;color:#B4BED4;line-height:1.7;margin:8px 0 0">{$mensaje}</p>
              </td></tr>
            </table>
          </td>
        </tr>
        <tr>
          <td style="padding:16px 40px;border-top:1px solid rgba(255,255,255,.06);text-align:center">
            <p style="color:#5C6B8C;font-size:12px;margin:0">© 2026 Mercaitech · Formulario de contacto</p>
          </td>
        </tr>
      </table>
    </td></tr>
  </table>
</body>
</html>
HTML;

    $sent = (new Mail())
        ->to(MAIL_FROM_EMAIL, MAIL_FROM_NAME)
        ->subject("Consulta de servicios: {$servicio} — {$nombre}")
        ->body($html)
        ->send();

    if (!$sent) {
        jsonResponse(['success' => false, 'message' => 'No se pudo enviar el correo. Inténtalo de nuevo.'], 500);
    }

    jsonResponse(['success' => true, 'message' => '¡Consulta enviada! Te contactaremos pronto.']);

} catch (Throwable $e) {
    error_log('Contact form error: ' . $e->getMessage());
    jsonResponse(['success' => false, 'message' => 'Error interno. Inténtalo de nuevo.'], 500);
}
