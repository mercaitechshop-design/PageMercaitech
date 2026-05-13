<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../app/Helpers/Mail.php';

if (APP_ENV !== 'development') { http_response_code(403); echo 'Solo en desarrollo'; exit; }

$to = $_GET['to'] ?? SMTP_USER;

try {
    $items = [['titulo' => 'Localizador Airtag Rastreador', 'qty' => 2, 'precio' => 89900.0]];
    $envio = ['direccion' => 'Carrera 2A # 32-05', 'ciudad' => 'Puerto Boyacá', 'pais' => 'Colombia'];
    $html  = Mail::templateOrder('Juan', 'Juan Francisco', '3103003738', 'MT-TEST01', 179800.0, $items, $envio);

    (new Mail())
        ->to($to, 'Test')
        ->subject('✅ Test correo Mercaitech — ' . date('H:i:s'))
        ->body($html, 'Correo de prueba enviado correctamente.')
        ->send();

    echo "<h2 style='font-family:sans-serif;color:green'>✅ Correo enviado a {$to}</h2><p>Revisa tu bandeja de entrada (y spam).</p>";
} catch (\Throwable $e) {
    echo "<h2 style='font-family:sans-serif;color:red'>❌ Error SMTP</h2><pre>" . htmlspecialchars($e->getMessage()) . "</pre>";
}
