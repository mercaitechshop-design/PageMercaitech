<?php
// Mercaitech — Newsletter subscription API
// POST /api/newsletter.php  body: { email, nombre?, source? }

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../app/Helpers/Mail.php';
setCorsHeaders();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'error' => 'Method not allowed.'], 405);
}

$body   = getJsonBody();
$email  = sanitize($body['email']  ?? '');
$nombre = sanitize($body['nombre'] ?? '');
$source = sanitize($body['source'] ?? 'web');

if (!$email || !validEmail($email)) {
    jsonResponse(['success' => false, 'message' => 'Correo electrónico no válido.'], 422);
}

$db = getDB();

// Verificar duplicado
$check = $db->prepare("SELECT id, activo FROM newsletter WHERE email = ? LIMIT 1");
$check->execute([$email]);
$existing = $check->fetch();

if ($existing) {
    if ($existing['activo']) {
        jsonResponse(['success' => false, 'message' => 'Este correo ya está suscrito. ¡Gracias!'], 409);
    }
    // Reactivar suscripción
    $db->prepare("UPDATE newsletter SET activo = 1, actualizado_en = NOW() WHERE email = ?")
       ->execute([$email]);
} else {
    $db->prepare("
        INSERT INTO newsletter (email, nombre, fuente, activo, creado_en)
        VALUES (?, ?, ?, 1, NOW())
    ")->execute([$email, $nombre ?: null, $source]);
}

// Enviar correo de bienvenida con código de descuento
try {
    $displayName = $nombre ?: explode('@', $email)[0];
    $html = <<<HTML
<!DOCTYPE html>
<html lang="es">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>
<body style="margin:0;padding:0;background:#03060D;font-family:-apple-system,BlinkMacSystemFont,'Inter',sans-serif">
  <table width="100%" cellpadding="0" cellspacing="0" style="background:#03060D;padding:40px 20px">
    <tr><td align="center">
      <table width="560" cellpadding="0" cellspacing="0" style="background:#0B1124;border-radius:16px;border:1px solid rgba(255,255,255,.08);overflow:hidden;max-width:100%">
        <tr>
          <td style="background:linear-gradient(135deg,#001A47,#002C7A,#0B1124);padding:28px 40px;border-bottom:1px solid rgba(31,214,255,.15)">
            <span style="font-weight:800;font-size:20px;color:#fff;letter-spacing:-.02em">MERCAI<span style="color:#1A8CFF">TECH</span></span>
            <span style="font-size:12px;color:#8593B2;margin-left:12px">Newsletter</span>
          </td>
        </tr>
        <tr>
          <td style="padding:36px 40px">
            <h1 style="color:#fff;font-size:24px;font-weight:800;margin:0 0 12px;letter-spacing:-.02em">¡Bienvenido/a, {$displayName}! 🎉</h1>
            <p style="color:#B4BED4;font-size:14px;line-height:1.7;margin:0 0 24px">
              Ya eres parte de la comunidad Mercaitech. Recibirás las mejores ofertas exclusivas,
              novedades de productos y acceso anticipado a promociones.
            </p>
            <div style="background:rgba(0,102,255,.08);border:1px solid rgba(0,102,255,.3);border-radius:14px;padding:24px;text-align:center;margin-bottom:28px">
              <p style="color:#8593B2;font-size:12px;margin:0 0 8px;text-transform:uppercase;letter-spacing:.1em">Tu código de bienvenida</p>
              <div style="font-size:32px;font-weight:900;letter-spacing:8px;color:#fff;font-family:monospace">BIENVENIDO10</div>
              <p style="color:#FFB020;font-size:12px;margin:10px 0 0;font-weight:600">10% de descuento en tu primera compra</p>
            </div>
            <p style="color:#5C6B8C;font-size:12px;line-height:1.6;margin:0">
              Si no solicitaste esta suscripción, puedes ignorar este correo. No compartiremos tu correo con terceros.
            </p>
          </td>
        </tr>
        <tr>
          <td style="padding:16px 40px;border-top:1px solid rgba(255,255,255,.06);text-align:center">
            <p style="color:#5C6B8C;font-size:12px;margin:0">© 2026 Mercaitech · Mercancía · IA · Tecnología</p>
          </td>
        </tr>
      </table>
    </td></tr>
  </table>
</body>
</html>
HTML;

    $mail = new Mail();
    $mail->to($email, $displayName)
         ->subject('¡Bienvenido/a a Mercaitech! Tu código de descuento 🎁')
         ->body($html, "Hola {$displayName}, gracias por suscribirte a Mercaitech. Tu código de descuento de bienvenida es: BIENVENIDO10 (10% en tu primera compra).")
         ->send();

} catch (Throwable $e) {
    // Log el error pero no fallar la suscripción
    file_put_contents(
        __DIR__ . '/../../storage/logs/mail.log',
        date('Y-m-d H:i:s') . ' [newsletter] ' . $e->getMessage() . PHP_EOL,
        FILE_APPEND
    );
}

jsonResponse(['success' => true, 'message' => '¡Suscripción exitosa! Revisa tu correo para tu código de descuento.']);
