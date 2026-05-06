<?php
require_once __DIR__ . '/config.php';

if (APP_ENV !== 'development') {
    http_response_code(403);
    exit('Acceso denegado.');
}
require_once __DIR__ . '/../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

header('Content-Type: application/json; charset=utf-8');

try {
    $mail = new PHPMailer(true);

    // depuración SMTP (útil para ver errores en terminal)
    $mail->SMTPDebug = SMTP::DEBUG_SERVER;

    $mail->isSMTP();
    $mail->Host       = SMTP_HOST;
    $mail->Port       = SMTP_PORT;
    $mail->SMTPAuth   = true;
    $mail->Username   = SMTP_USER;
    $mail->Password   = SMTP_PASS;
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;

    $mail->setFrom(MAIL_FROM_EMAIL, MAIL_FROM_NAME);

    // correo donde quieres recibir la prueba
    $mail->addAddress('juanflondonob@gmail.com');

    $mail->isHTML(true);
    $mail->Subject = 'Prueba SMTP Mercaitech';
    $mail->Body    = '<p>Correo de prueba enviado correctamente.</p>';
    $mail->AltBody = 'Correo de prueba enviado correctamente.';

    $mail->send();

    echo json_encode([
        'success' => true,
        'message' => 'Correo enviado correctamente'
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $mail->ErrorInfo
    ]);
}