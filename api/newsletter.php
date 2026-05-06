<?php
// Mercaitech — Newsletter subscription API
// POST /api/newsletter.php  body: { email, nombre?, source? }

require_once __DIR__ . '/config.php';
setCorsHeaders();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'error' => 'Method not allowed.'], 405);
}

$body  = getJsonBody();
$email = sanitize($body['email'] ?? '');
$nombre = sanitize($body['nombre'] ?? '');
$source = sanitize($body['source'] ?? 'web');

if (!$email || !validEmail($email)) {
    jsonResponse(['success' => false, 'message' => 'Correo electrónico no válido.'], 422);
}

$db = getDB();

// Check duplicate
$check = $db->prepare("SELECT id, activo FROM newsletter WHERE email = ? LIMIT 1");
$check->execute([$email]);
$existing = $check->fetch();

if ($existing) {
    if ($existing['activo']) {
        jsonResponse(['success' => false, 'message' => 'Este correo ya está suscrito.'], 409);
    } else {
        // Re-activate
        $db->prepare("UPDATE newsletter SET activo = 1, actualizado_en = NOW() WHERE email = ?")
           ->execute([$email]);
        jsonResponse(['success' => true, 'message' => '¡Suscripción reactivada! Revisa tu correo.']);
    }
}

// Insert new subscriber
$stmt = $db->prepare("
    INSERT INTO newsletter (email, nombre, fuente, activo, creado_en)
    VALUES (?, ?, ?, 1, NOW())
");
$stmt->execute([$email, $nombre ?: null, $source]);

// Here you'd integrate your email provider (Mailchimp, SendGrid, etc.)
// Example with SendGrid (commented out):
/*
$sg = new \SendGrid(SENDGRID_API_KEY);
$email_msg = new \SendGrid\Mail\Mail();
$email_msg->setFrom("hola@mercaitech.com", "Mercaitech");
$email_msg->setSubject("Bienvenido a Mercaitech — Tu código de descuento");
$email_msg->addTo($email, $nombre ?: "Cliente");
$email_msg->addContent("text/plain", "Gracias por suscribirte. Tu código: BIENVENIDO10");
$sg->send($email_msg);
*/

jsonResponse(['success' => true, 'message' => '¡Suscripción exitosa! Revisa tu correo.']);
