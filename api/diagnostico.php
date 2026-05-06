<?php
/**
 * Mercaitech — Página de diagnóstico (solo para desarrollo)
 * Acceder en: http://localhost/mercaitech/api/diagnostico.php
 */
declare(strict_types=1);
require_once __DIR__ . '/config.php';

if (APP_ENV !== 'development') {
    http_response_code(403);
    exit('Acceso denegado.');
}

header('Content-Type: text/html; charset=utf-8');

function check(string $label, callable $fn): array {
    try {
        $result = $fn();
        return ['ok' => true,  'label' => $label, 'msg' => $result];
    } catch (Throwable $e) {
        return ['ok' => false, 'label' => $label, 'msg' => $e->getMessage()];
    }
}

$checks = [];

// 1. PHP version
$checks[] = check('PHP Version', fn() => PHP_VERSION . ' (mín. 8.0 requerido) ' . (version_compare(PHP_VERSION, '8.0', '>=') ? '✓' : '✗'));

// 2. Extensiones PHP
$exts = ['pdo', 'pdo_mysql', 'openssl', 'mbstring', 'json'];
foreach ($exts as $ext) {
    $checks[] = ['ok' => extension_loaded($ext), 'label' => "PHP ext: $ext", 'msg' => extension_loaded($ext) ? 'Cargada' : 'NO instalada — ejecuta: sudo apt install php-' . $ext];
}

// 3. Vendor / Composer
$checks[] = check('Composer autoload', function() {
    if (!file_exists(__DIR__ . '/../vendor/autoload.php')) throw new \Exception('vendor/autoload.php no existe. Ejecuta: composer install');
    require_once __DIR__ . '/../vendor/autoload.php';
    return 'vendor/ encontrado ✓';
});

// 4. Conexión a base de datos
$checks[] = check('Conexión MariaDB', function() {
    $db = getDB();
    $v  = $db->query('SELECT VERSION()')->fetchColumn();
    return "Conectado. MariaDB $v ✓";
});

// 5. Tabla usuarios existe
$checks[] = check('Tabla usuarios', function() {
    $db   = getDB();
    $stmt = $db->query("SHOW TABLES LIKE 'usuarios'");
    if (!$stmt->fetch()) throw new \Exception("Tabla 'usuarios' no existe. Importa el esquema: mysql -u mercaitech -pMercaitech2026! mercaitech < api/database/mercaitech.sql");
    $count = $db->query("SELECT COUNT(*) FROM usuarios")->fetchColumn();
    return "Existe. $count usuario(s) registrados ✓";
});

// 6. Columnas token_reset en usuarios
$checks[] = check('Columnas token_reset', function() {
    $db   = getDB();
    $cols = $db->query("SHOW COLUMNS FROM usuarios LIKE 'token_reset%'")->fetchAll();
    if (count($cols) < 2) throw new \Exception("Faltan columnas token_reset / token_reset_exp. Reimporta el esquema.");
    return "token_reset + token_reset_exp presentes ✓";
});

// 7. PHPMailer SMTP
$checks[] = check('PHPMailer SMTP (Gmail)', function() {
    require_once __DIR__ . '/../vendor/autoload.php';
    $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
    $mail->isSMTP();
    $mail->Host       = SMTP_HOST;
    $mail->Port       = SMTP_PORT;
    $mail->SMTPAuth   = true;
    $mail->Username   = SMTP_USER;
    $mail->Password   = SMTP_PASS;
    $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
    $mail->SMTPOptions = ['ssl' => ['verify_peer' => false, 'verify_peer_name' => false, 'allow_self_signed' => true]];
    // Solo verificar conexión, no enviar
    $mail->smtpConnect();
    $mail->smtpClose();
    return 'Conectado a smtp.gmail.com:587 ✓';
});

// 8. Verificar que email funciona enviando uno real
$sendTest = isset($_GET['enviar']) && $_GET['enviar'] === '1';
if ($sendTest) {
    $testEmail = $_GET['email'] ?? SMTP_USER;
    $checks[] = check("Envío real a $testEmail", function() use ($testEmail) {
        require_once __DIR__ . '/../vendor/autoload.php';
        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->Port       = SMTP_PORT;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USER;
        $mail->Password   = SMTP_PASS;
        $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->SMTPOptions = ['ssl' => ['verify_peer' => false, 'verify_peer_name' => false, 'allow_self_signed' => true]];
        $mail->CharSet = 'UTF-8';
        $mail->setFrom(MAIL_FROM_EMAIL, MAIL_FROM_NAME);
        $mail->addAddress($testEmail);
        $mail->isHTML(true);
        $mail->Subject = '✅ Test Mercaitech — Código de prueba: 123456';
        $mail->Body = '<div style="font-family:sans-serif;padding:24px;background:#03060D;color:#fff"><h2 style="color:#1A8CFF">Mercaitech Test</h2><p>Si ves este correo, el sistema de envío funciona correctamente.</p><div style="font-size:42px;font-weight:900;letter-spacing:12px;color:#fff;font-family:monospace;background:rgba(0,102,255,.15);padding:20px;border-radius:12px;margin:16px 0;text-align:center">123456</div><p style="color:#8593B2;font-size:12px">Correo enviado desde diagnostico.php</p></div>';
        $mail->AltBody = 'Test Mercaitech. Código: 123456';
        $mail->send();
        return "¡Correo enviado a $testEmail! Revisa Gmail (incluye spam) ✓";
    });
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Diagnóstico — Mercaitech</title>
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body { font-family: -apple-system, BlinkMacSystemFont, 'Inter', sans-serif; background: #03060D; color: #fff; padding: 32px 24px; }
  h1 { font-size: 22px; font-weight: 800; margin-bottom: 6px; color: #fff; }
  .sub { font-size: 13px; color: #5C6B8C; margin-bottom: 32px; }
  .card { background: #0B1124; border: 1px solid rgba(255,255,255,.08); border-radius: 14px; padding: 24px; max-width: 720px; }
  .item { display: flex; align-items: flex-start; gap: 12px; padding: 12px 0; border-bottom: 1px solid rgba(255,255,255,.05); }
  .item:last-child { border-bottom: 0; }
  .dot { width: 22px; height: 22px; border-radius: 50%; flex-shrink: 0; display: flex; align-items: center; justify-content: center; font-size: 12px; margin-top: 1px; }
  .dot.ok  { background: rgba(16,201,138,.15); border: 1px solid rgba(16,201,138,.4); color: #10C98A; }
  .dot.err { background: rgba(255,84,112,.12); border: 1px solid rgba(255,84,112,.35); color: #FF5470; }
  .label { font-size: 13px; font-weight: 600; color: #fff; }
  .msg   { font-size: 12px; color: #8593B2; margin-top: 3px; font-family: monospace; word-break: break-all; }
  .msg.err { color: #FF5470; }
  .actions { margin-top: 24px; display: flex; flex-wrap: wrap; gap: 10px; }
  .btn { display: inline-flex; align-items: center; gap: 8px; padding: 10px 20px; border-radius: 10px; font-size: 13px; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; cursor: pointer; text-decoration: none; border: 0; }
  .btn-primary { background: #0066FF; color: #fff; }
  .btn-secondary { background: rgba(255,255,255,.08); color: #fff; }
  input[type=email] { background: #0B1124; border: 1.5px solid rgba(255,255,255,.12); border-radius: 8px; padding: 10px 14px; color: #fff; font-size: 13px; outline: none; width: 260px; }
  .summary { font-size: 14px; color: #8593B2; margin-top: 20px; }
  .summary strong { color: #fff; }
  .warning { background: rgba(255,180,0,.08); border: 1px solid rgba(255,180,0,.25); border-radius: 10px; padding: 12px 16px; font-size: 12px; color: #FFB020; margin-top: 20px; }
</style>
</head>
<body>
<h1>🔧 Diagnóstico del Sistema</h1>
<p class="sub">Mercaitech — Verifica que todos los componentes estén funcionando</p>

<div class="card">
<?php
$total = count($checks);
$ok    = count(array_filter($checks, fn($c) => $c['ok']));
foreach ($checks as $c): ?>
  <div class="item">
    <div class="dot <?= $c['ok'] ? 'ok' : 'err' ?>"><?= $c['ok'] ? '✓' : '✗' ?></div>
    <div>
      <div class="label"><?= htmlspecialchars($c['label']) ?></div>
      <div class="msg <?= $c['ok'] ? '' : 'err' ?>"><?= htmlspecialchars($c['msg']) ?></div>
    </div>
  </div>
<?php endforeach; ?>

<p class="summary">
  Estado: <strong><?= $ok ?>/<?= $total ?></strong> checks correctos
  <?= $ok === $total ? ' — <span style="color:#10C98A">✅ Todo listo</span>' : ' — <span style="color:#FF5470">⚠ Hay errores que corregir</span>' ?>
</p>

<?php if (!$sendTest): ?>
<div class="actions">
  <form method="GET" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
    <input type="hidden" name="enviar" value="1">
    <input type="email" name="email" placeholder="correo@destino.com" value="juanflondonob@gmail.com" required>
    <button type="submit" class="btn btn-primary">📧 Enviar correo de prueba</button>
  </form>
  <a href="../login.html" class="btn btn-secondary">← Volver al login</a>
</div>
<?php endif; ?>

<div class="warning">⚠ Elimina este archivo antes de poner el proyecto en producción.</div>
</div>
</body>
</html>
