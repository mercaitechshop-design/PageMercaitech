<?php
// Mercaitech — Authentication API
// POST /api/auth.php  body: { action, ...fields }
//
// Actions: register · login · logout · me · forgot · reset · verify

declare(strict_types=1);
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../app/Helpers/Mail.php';

setCorsHeaders();

// Start session with secure settings
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_samesite', 'Strict');
if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
    ini_set('session.cookie_secure', '1');
}
session_start();

$method = $_SERVER['REQUEST_METHOD'];
if ($method !== 'POST') {
    jsonResponse(['success' => false, 'error' => 'Method not allowed.'], 405);
}

$body   = getJsonBody();
$action = sanitize($body['action'] ?? '');

match ($action) {
    'register'     => handleRegister($body),
    'login'        => handleLogin($body),
    'logout'       => handleLogout(),
    'me'           => handleMe(),
    'forgot'       => handleForgot($body),
    'reset'        => handleReset($body),
    'verify'       => handleVerify($body),
    'verify_email'         => handleVerifyEmail($body),
    'resend_verification'  => handleResendVerification($body),
    'google'          => handleGoogleSignIn($body),
    'google_code'     => handleGoogleCode($body),
    'change_password'      => handleChangePassword($body),
    'verify_reset_code'    => handleVerifyResetCode($body),
    'validate_reset_token' => handleValidateResetToken($body),
    'update_profile'       => handleUpdateProfile($body),
    'set_password'         => handleSetPassword($body),
    'upload_avatar'        => handleUploadAvatar($body),
    'delete_avatar'        => handleDeleteAvatar(),
    default                => jsonResponse(['success' => false, 'error' => "Acción '$action' no encontrada."], 404),
};

// ============================================================================
// RATE LIMITING — delega al rate limiter persistente por IP en app.php
// ============================================================================
function checkRateLimit(string $key, int $max = 5, int $windowSecs = 300): void {
    checkRateLimitDB($key, $max, $windowSecs);
}

// ============================================================================
// REGISTER
// ============================================================================
function handleRegister(array $body): void {
    checkRateLimit('register', 10, 600);

    $nombre   = sanitize($body['nombre']   ?? '');
    $apellido = sanitize($body['apellido'] ?? '');
    $email    = sanitize($body['email']    ?? '');
    $password = $body['password'] ?? '';
    $telefono = sanitize($body['telefono'] ?? '');

    // Validaciones
    if (!$nombre) {
        jsonResponse(['success' => false, 'message' => 'El nombre es obligatorio.'], 422);
    }
    if (!$email || !validEmail($email)) {
        jsonResponse(['success' => false, 'message' => 'Ingresa un correo electrónico válido.'], 422);
    }
    if (strlen($password) < 8) {
        jsonResponse(['success' => false, 'message' => 'La contraseña debe tener al menos 8 caracteres.'], 422);
    }
    if (strlen($password) > 128) {
        jsonResponse(['success' => false, 'message' => 'La contraseña es demasiado larga.'], 422);
    }
    if (!preg_match('/[A-Z]/', $password)) {
        jsonResponse(['success' => false, 'message' => 'La contraseña debe tener al menos una letra mayúscula.'], 422);
    }
    if (!preg_match('/[0-9]/', $password)) {
        jsonResponse(['success' => false, 'message' => 'La contraseña debe tener al menos un número.'], 422);
    }

    $db = getDB();

    // Verificar email duplicado
    $check = $db->prepare("SELECT id FROM usuarios WHERE email = ? LIMIT 1");
    $check->execute([$email]);
    if ($check->fetch()) {
        jsonResponse(['success' => false, 'message' => 'Este correo ya está registrado. ¿Quieres iniciar sesión?'], 409);
    }

    // Generar código de 6 dígitos (expira en 15 min)
    $verifyCode     = str_pad((string) random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
    $verifyCodeHash = hash('sha256', $verifyCode);
    $verifyExp      = time() + 900;

    // Guardar datos en sesión — la cuenta NO se crea hasta que el código sea verificado
    $_SESSION['pending_register'] = [
        'nombre'    => $nombre,
        'apellido'  => $apellido,
        'email'     => $email,
        'telefono'  => $telefono,
        'hash'      => password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]),
        'code_hash' => $verifyCodeHash,
        'expires'   => $verifyExp,
    ];

    // Enviar código al correo
    $displayName = trim($nombre . ($apellido ? ' ' . $apellido : ''));
    $emailSent   = false;
    try {
        $mail = new Mail();
        $mail->to($email, $displayName)
             ->subject('Verifica tu correo — Mercaitech')
             ->body(
                 Mail::templateCode($displayName, $verifyCode),
                 "Hola {$displayName}, tu código de verificación es: {$verifyCode}\nExpira en 15 minutos."
             )
             ->send();
        $emailSent = true;
    } catch (\Throwable $e) {
        @file_put_contents(
            __DIR__ . '/../../storage/logs/mail.log',
            date('Y-m-d H:i:s') . " [register_verify] to={$email} error=" . $e->getMessage() . PHP_EOL,
            FILE_APPEND
        );
    }

    clearRateLimitDB('register');

    jsonResponse([
        'success'           => true,
        'needs_verification'=> true,
        'email_sent'        => $emailSent,
        'user'              => ['nombre' => $nombre, 'apellido' => $apellido, 'email' => $email, 'telefono' => $telefono],
        'message'           => 'Revisa tu correo e ingresa el código de verificación.',
    ], 200);
}

// ============================================================================
// LOGIN
// ============================================================================
function handleLogin(array $body): void {
    checkRateLimit('login', 8, 300);

    $email    = sanitize($body['email']    ?? '');
    $password = $body['password'] ?? '';
    $remember = (bool) ($body['remember']  ?? false);

    if (!$email || !$password) {
        jsonResponse(['success' => false, 'message' => 'Correo y contraseña son obligatorios.'], 422);
    }

    $db = getDB();
    $stmt = $db->prepare("
        SELECT id, nombre, apellido, email, password_hash, rol, activo, intentos_fallidos, bloqueado_hasta,
               avatar_url, telefono
        FROM usuarios WHERE email = ? LIMIT 1
    ");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    // Brute-force: check lockout BEFORE verifying password
    if ($user && $user['bloqueado_hasta'] && strtotime($user['bloqueado_hasta']) > time()) {
        $min = (int) ceil((strtotime($user['bloqueado_hasta']) - time()) / 60);
        jsonResponse(['success' => false,
            'message' => "Cuenta bloqueada temporalmente. Inténtalo en {$min} min."], 423);
    }

    // Verify password (always call password_verify to prevent timing attacks)
    $dummyHash = '$2y$12$invaliddummyhashpadding0000000000000000000000000000000';
    $hash      = $user ? $user['password_hash'] : $dummyHash;
    $valid     = password_verify($password, $hash);

    if (!$user || !$valid) {
        if ($user) {
            $attempts  = (int)$user['intentos_fallidos'] + 1;
            $lockUntil = $attempts >= 5 ? date('Y-m-d H:i:s', time() + 900) : null;
            $db->prepare("UPDATE usuarios SET intentos_fallidos = ?, bloqueado_hasta = ? WHERE id = ?")
               ->execute([$attempts, $lockUntil, $user['id']]);
        }
        // Log intento fallido para auditoría
        securityLog('login_fail', ['email' => $email, 'ip' => ipHash()]);
        jsonResponse(['success' => false, 'message' => 'Correo o contraseña incorrectos.'], 401);
    }

    if (!$user['activo']) {
        jsonResponse(['success' => false, 'message' => 'Esta cuenta está desactivada. Contacta soporte.'], 403);
    }

    // Reset failed attempts and update last login
    $db->prepare("UPDATE usuarios SET intentos_fallidos = 0, bloqueado_hasta = NULL, ultimo_login = NOW() WHERE id = ?")
       ->execute([$user['id']]);

    // Regenerate session ID to prevent fixation
    session_regenerate_id(true);

    $_SESSION['user_id']    = $user['id'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['user_role']  = $user['rol'];

    // Remember-me cookie (30 días)
    // Seguridad: la cookie guarda el token raw; la BD guarda hash(token).
    // Si la BD es comprometida, el hash no sirve para suplantar sesiones.
    if ($remember) {
        $token     = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $token);
        $exp       = time() + 86400 * 30;
        setcookie('mt_remember', $token, [
            'expires'  => $exp,
            'path'     => '/',
            'httponly' => true,
            'samesite' => 'Strict',
            'secure'   => isset($_SERVER['HTTPS']),
        ]);
        $db->prepare("UPDATE usuarios SET remember_token = ?, remember_expires = ? WHERE id = ?")
           ->execute([$tokenHash, date('Y-m-d H:i:s', $exp), $user['id']]);
    }

    clearRateLimitDB('login');
    securityLog('login_ok', ['user_id' => $user['id']]);

    $userData = [
        'id'           => $user['id'],
        'nombre'       => $user['nombre'],
        'apellido'     => $user['apellido'] ?? '',
        'email'        => $user['email'],
        'rol'          => $user['rol'],
        'telefono'     => $user['telefono'] ?? '',
        'avatar_url'   => $user['avatar_url'] ?? '',
        'has_password' => true,
    ];
    jsonResponse(['success' => true, 'user' => $userData, 'redirect' => 'index.html']);
}

// ============================================================================
// LOGOUT
// ============================================================================
function handleLogout(): void {
    // Clear remember-me cookie
    if (isset($_COOKIE['mt_remember'])) {
        setcookie('mt_remember', '', ['expires' => time() - 1, 'path' => '/']);
        // Invalidate token in DB
        try {
            $db = getDB();
            if (isset($_SESSION['user_id'])) {
                $db->prepare("UPDATE usuarios SET remember_token = NULL, remember_expires = NULL WHERE id = ?")
                   ->execute([$_SESSION['user_id']]);
            }
        } catch (\Throwable) {}
    }
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires'  => time() - 42000,
            'path'     => $params['path'],
            'domain'   => $params['domain'],
            'secure'   => $params['secure'],
            'httponly' => $params['httponly'],
        ]);
    }
    session_destroy();
    jsonResponse(['success' => true, 'message' => 'Sesión cerrada correctamente.']);
}

// ============================================================================
// ME — return current authenticated user
// ============================================================================
function handleMe(): void {
    // Try remember-me cookie if session is empty
    if (empty($_SESSION['user_id']) && !empty($_COOKIE['mt_remember'])) {
        tryRememberMe($_COOKIE['mt_remember']);
    }

    if (empty($_SESSION['user_id'])) {
        jsonResponse(['success' => false, 'message' => 'No autenticado.'], 401);
    }

    $db   = getDB();
    $stmt = $db->prepare("SELECT id, nombre, apellido, email, rol, telefono, avatar_url, password_hash FROM usuarios WHERE id = ? AND activo = 1 LIMIT 1");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();

    if (!$user) {
        session_destroy();
        jsonResponse(['success' => false, 'message' => 'Sesión inválida.'], 401);
    }

    $userData = [
        'id'           => $user['id'],
        'nombre'       => $user['nombre'],
        'apellido'     => $user['apellido'] ?? '',
        'email'        => $user['email'],
        'rol'          => $user['rol'],
        'telefono'     => $user['telefono'] ?? '',
        'avatar_url'   => $user['avatar_url'] ?? '',
        'has_password' => !empty($user['password_hash']),
    ];
    jsonResponse(['success' => true, 'user' => $userData]);
}

function tryRememberMe(string $token): void {
    if (!$token) return;
    try {
        $db        = getDB();
        $tokenHash = hash('sha256', $token); // comparar hash, nunca el token raw
        $stmt      = $db->prepare("
            SELECT id, nombre, email, rol
            FROM usuarios
            WHERE remember_token = ? AND remember_expires > NOW() AND activo = 1
            LIMIT 1
        ");
        $stmt->execute([$tokenHash]);
        $user = $stmt->fetch();
        if ($user) {
            session_regenerate_id(true);
            $_SESSION['user_id']    = $user['id'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['user_role']  = $user['rol'];
        }
    } catch (\Throwable) {}
}

// ============================================================================
// FORGOT PASSWORD — envía código de 6 dígitos al correo
// ============================================================================
function handleForgot(array $body): void {
    checkRateLimit('forgot', 10, 600);

    $email = sanitize($body['email'] ?? '');
    if (!$email || !validEmail($email)) {
        jsonResponse(['success' => false, 'message' => 'Correo inválido.'], 422);
    }

    $db   = getDB();
    $stmt = $db->prepare("SELECT id, nombre FROM usuarios WHERE email = ? AND activo = 1 LIMIT 1");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    $genericResponse = ['success' => true, 'message' => 'Si ese correo está registrado, recibirás el código en breve.'];

    if (!$user) {
        jsonResponse($genericResponse);
    }

    // Código de 6 dígitos, expira en 5 minutos
    // Seguridad: se guarda el hash SHA-256; el código raw solo viaja por correo.
    $code     = str_pad((string) random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
    $codeHash = hash('sha256', $code);
    $expires  = date('Y-m-d H:i:s', time() + 300);

    $db->prepare("UPDATE usuarios SET token_reset = ?, token_reset_exp = ? WHERE id = ?")
       ->execute([$codeHash, $expires, $user['id']]);

    try {
        $mail = new Mail();
        $mail->to($email, $user['nombre'])
             ->subject('Tu código de verificación — Mercaitech')
             ->body(
                 Mail::templateCode($user['nombre'], $code),
                 "Hola {$user['nombre']}, tu código para restablecer la contraseña es: {$code}\nExpira en 5 minutos.\nSi no lo solicitaste, ignora este mensaje."
             )
             ->send();

        jsonResponse(['success' => true, 'message' => 'Código enviado. Revisa tu bandeja de entrada y la carpeta spam.']);

    } catch (Throwable $e) {
        $errorDetail = $e->getMessage();
        file_put_contents(
            __DIR__ . '/../../storage/logs/mail.log',
            date('Y-m-d H:i:s') . ' [forgot] ' . $errorDetail . PHP_EOL,
            FILE_APPEND
        );

        if (APP_ENV === 'development') {
            jsonResponse(['success' => false, 'message' => 'Error al enviar el correo: ' . $errorDetail], 500);
        }
        jsonResponse($genericResponse);
    }
}

// ============================================================================
// RESET PASSWORD — verifica código + email y cambia contraseña
// ============================================================================
// ── Historial de contraseñas ─────────────────────────────────────────────────
function isPasswordInHistory(PDO $db, int $userId, string $newPwd): bool {
    $stmt = $db->prepare(
        "SELECT password_hash FROM password_historial WHERE usuario_id = ? ORDER BY creado_en DESC LIMIT 5"
    );
    $stmt->execute([$userId]);
    foreach ($stmt->fetchAll() as $row) {
        if (password_verify($newPwd, $row['password_hash'])) return true;
    }
    return false;
}

function savePasswordHistory(PDO $db, int $userId, string $hash): void {
    $db->prepare("INSERT INTO password_historial (usuario_id, password_hash) VALUES (?, ?)")
       ->execute([$userId, $hash]);
    // Conservar solo las últimas 5
    $db->prepare("
        DELETE FROM password_historial
        WHERE usuario_id = ?
          AND id NOT IN (
              SELECT id FROM (
                  SELECT id FROM password_historial
                  WHERE usuario_id = ?
                  ORDER BY creado_en DESC LIMIT 5
              ) t
          )
    ")->execute([$userId, $userId]);
}

function handleReset(array $body): void {
    checkRateLimitDB('reset', 5, 300);

    $email    = sanitize($body['email']    ?? '');
    $code     = sanitize($body['code']     ?? '');
    $password = $body['password'] ?? '';

    if (!$email || !$code) {
        jsonResponse(['success' => false, 'message' => 'Correo y código requeridos.'], 422);
    }
    // Validación robusta de contraseña
    if (strlen($password) < 8) {
        jsonResponse(['success' => false, 'message' => 'La contraseña debe tener al menos 8 caracteres.'], 422);
    }
    if (!preg_match('/[A-Z]/', $password)) {
        jsonResponse(['success' => false, 'message' => 'La contraseña debe incluir al menos una mayúscula.'], 422);
    }
    if (!preg_match('/[0-9]/', $password)) {
        jsonResponse(['success' => false, 'message' => 'La contraseña debe incluir al menos un número.'], 422);
    }

    $db       = getDB();
    $codeHash = hash('sha256', $code); // comparar contra hash almacenado
    $stmt     = $db->prepare("
        SELECT id, password_hash FROM usuarios
        WHERE email = ? AND token_reset = ? AND token_reset_exp > NOW() AND activo = 1
        LIMIT 1
    ");
    $stmt->execute([$email, $codeHash]);
    $user = $stmt->fetch();

    if (!$user) {
        jsonResponse(['success' => false, 'message' => 'Código incorrecto o expirado. Solicita uno nuevo.'], 400);
    }

    // Verificar que no sea una contraseña ya usada
    if (isPasswordInHistory($db, (int)$user['id'], $password)) {
        jsonResponse(['success' => false, 'message' => 'No puedes usar una contraseña que ya usaste anteriormente. Elige una nueva.'], 400);
    }

    $oldHash = $user['password_hash'];
    $hash    = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

    $db->prepare("
        UPDATE usuarios
        SET password_hash = ?, token_reset = NULL, token_reset_exp = NULL,
            intentos_fallidos = 0, bloqueado_hasta = NULL
        WHERE id = ?
    ")->execute([$hash, $user['id']]);

    // Guardar contraseña anterior en historial
    if (!empty($oldHash)) savePasswordHistory($db, (int)$user['id'], $oldHash);
    savePasswordHistory($db, (int)$user['id'], $hash);

    jsonResponse(['success' => true, 'message' => '¡Contraseña actualizada! Ya puedes iniciar sesión.']);
}

// ============================================================================
// VERIFY EMAIL
// ============================================================================
function handleVerify(array $body): void {
    $token = sanitize($body['token'] ?? '');
    if (!$token) {
        jsonResponse(['success' => false, 'message' => 'Token requerido.'], 422);
    }

    $db   = getDB();
    $stmt = $db->prepare("SELECT id FROM usuarios WHERE token_verificacion = ? AND activo = 1 LIMIT 1");
    $stmt->execute([$token]);
    $user = $stmt->fetch();

    if (!$user) {
        jsonResponse(['success' => false, 'message' => 'Token de verificación inválido.'], 400);
    }

    $db->prepare("UPDATE usuarios SET email_verificado = 1, token_verificacion = NULL WHERE id = ?")
       ->execute([$user['id']]);

    jsonResponse(['success' => true, 'message' => 'Correo verificado. ¡Bienvenido a Mercaitech!']);
}

// ============================================================================
// GOOGLE SIGN-IN (credential JWT from GIS)
// ============================================================================
function handleGoogleSignIn(array $body): void {
    $email    = sanitize($body['email']    ?? '');
    $nombre   = sanitize($body['nombre']   ?? '');
    $apellido = sanitize($body['apellido'] ?? '');
    $googleId = sanitize($body['googleId'] ?? '');

    if (!$email || !validEmail($email)) {
        jsonResponse(['success' => false, 'message' => 'No se pudo obtener el email de Google.'], 422);
    }
    if (!$googleId) {
        jsonResponse(['success' => false, 'message' => 'No se pudo verificar la identidad de Google.'], 422);
    }

    $db    = getDB();
    $isNew = false;

    // 1. Buscar por google_id (identificador único de Google)
    $stmt = $db->prepare("SELECT id, nombre, apellido, email, rol, activo, avatar_url, telefono, password_hash FROM usuarios WHERE google_id = ? LIMIT 1");
    $stmt->execute([$googleId]);
    $user = $stmt->fetch();

    // 2. Si no encontró por google_id, buscar por email (cuenta existente por email/password)
    if (!$user) {
        $stmt = $db->prepare("SELECT id, nombre, apellido, email, rol, activo, avatar_url, telefono, password_hash FROM usuarios WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user) {
            if (!$user['activo']) {
                jsonResponse(['success' => false, 'message' => 'Esta cuenta está desactivada. Contacta soporte.'], 403);
            }
            $db->prepare("UPDATE usuarios SET google_id = ?, ultimo_login = NOW() WHERE id = ?")
               ->execute([$googleId, $user['id']]);
        }
    }

    $avatar = sanitize($body['avatar'] ?? '');

    // 3. Si tampoco existe → crear cuenta nueva
    if (!$user) {
        $isNew  = true;
        $nombre = $nombre ?: explode('@', $email)[0];
        $db->prepare("
            INSERT INTO usuarios
              (nombre, apellido, email, password_hash, rol, activo, email_verificado, google_id, avatar_url, creado_en)
            VALUES (?, ?, ?, '', 'cliente', 1, 1, ?, ?, NOW())
        ")->execute([$nombre, $apellido ?: null, $email, $googleId, $avatar ?: null]);

        $userId = (int) $db->lastInsertId();
        $user   = ['id' => $userId, 'nombre' => $nombre, 'apellido' => $apellido, 'email' => $email,
                   'rol' => 'cliente', 'avatar_url' => $avatar, 'telefono' => '', 'password_hash' => ''];
    } else {
        if (!$user['activo']) {
            jsonResponse(['success' => false, 'message' => 'Esta cuenta está desactivada. Contacta soporte.'], 403);
        }
        // Solo actualizar avatar con el de Google si el usuario NO tiene un avatar personalizado
        $hasCustomAvatar = !empty($user['avatar_url']) && str_starts_with($user['avatar_url'], 'data:');
        if ($avatar && !$hasCustomAvatar) {
            $db->prepare("UPDATE usuarios SET avatar_url = ?, ultimo_login = NOW() WHERE id = ?")
               ->execute([$avatar, $user['id']]);
            $user['avatar_url'] = $avatar;
        } else {
            $db->prepare("UPDATE usuarios SET ultimo_login = NOW() WHERE id = ?")->execute([$user['id']]);
        }
    }

    // Solo abrir sesión para cuentas existentes (login).
    // Las cuentas nuevas deben iniciar sesión manualmente después del registro.
    if (!$isNew) {
        session_regenerate_id(true);
        $_SESSION['user_id']    = $user['id'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['user_role']  = $user['rol'];
    }

    jsonResponse([
        'success'  => true,
        'is_new'   => $isNew,
        'user'     => [
            'id'           => $isNew ? null : $user['id'],
            'nombre'       => $user['nombre'],
            'apellido'     => $user['apellido'] ?? '',
            'email'        => $user['email'],
            'rol'          => $user['rol'],
            'telefono'     => $user['telefono'] ?? '',
            'avatar_url'   => $user['avatar_url'] ?? '',
            'has_password' => !empty($user['password_hash']),
        ],
        'redirect' => 'index.html',
    ]);
}

// ============================================================================
// GOOGLE AUTH CODE EXCHANGE (alternative flow)
// ============================================================================
function handleGoogleCode(array $body): void {
    jsonResponse(['success' => false,
        'message' => 'Configura GOOGLE_CLIENT_SECRET en el servidor para el flujo de código.'], 501);
}

// ============================================================================
// VERIFY RESET CODE — comprueba código sin cambiar la contraseña (paso 2 del modal)
// ============================================================================
function handleVerifyResetCode(array $body): void {
    checkRateLimitDB('verify_code', 10, 300);

    $email = sanitize($body['email'] ?? '');
    $code  = sanitize($body['code']  ?? '');

    if (!$email || !$code) {
        jsonResponse(['success' => false, 'message' => 'Correo y código requeridos.'], 422);
    }

    $db       = getDB();
    $codeHash = hash('sha256', $code);
    $stmt     = $db->prepare("
        SELECT id FROM usuarios
        WHERE email = ? AND token_reset = ? AND token_reset_exp > NOW() AND activo = 1
        LIMIT 1
    ");
    $stmt->execute([$email, $codeHash]);

    if (!$stmt->fetch()) {
        jsonResponse(['success' => false, 'message' => 'Código incorrecto o expirado. Verifica e inténtalo de nuevo.'], 400);
    }

    jsonResponse(['success' => true]);
}

// ============================================================================
// CHANGE PASSWORD (acepta sesión PHP o email+contraseña actual)
// ============================================================================
function handleChangePassword(array $body): void {
    $currentPwd = $body['current_password'] ?? '';
    $newPwd     = $body['new_password']     ?? '';
    $email      = sanitize($body['email']   ?? '');

    // Admite autenticación por sesión PHP O por email (localStorage-based auth)
    $userId = $_SESSION['user_id'] ?? null;

    if (!$userId && !$email) {
        jsonResponse(['success' => false, 'message' => 'Sesión no válida. Inicia sesión de nuevo.'], 401);
    }
    if (!$currentPwd || !$newPwd) {
        jsonResponse(['success' => false, 'message' => 'Datos incompletos.'], 422);
    }
    if (strlen($newPwd) < 8) {
        jsonResponse(['success' => false, 'message' => 'La nueva contraseña debe tener al menos 8 caracteres.'], 422);
    }

    $db = getDB();
    if ($userId) {
        $stmt = $db->prepare("SELECT id, password_hash FROM usuarios WHERE id = ? AND activo = 1 LIMIT 1");
        $stmt->execute([$userId]);
    } else {
        $stmt = $db->prepare("SELECT id, password_hash FROM usuarios WHERE email = ? AND activo = 1 LIMIT 1");
        $stmt->execute([$email]);
    }
    $user = $stmt->fetch();

    if (!$user) {
        jsonResponse(['success' => false, 'message' => 'Usuario no encontrado. Verifica que estés iniciado sesión.'], 404);
    }

    // Verificar contraseña actual — password_hash vacío (usuarios Google sin contraseña)
    if (empty($user['password_hash'])) {
        jsonResponse(['success' => false, 'message' => 'Tu cuenta fue creada con Google. No tiene contraseña local.'], 400);
    }

    if (!password_verify($currentPwd, $user['password_hash'])) {
        jsonResponse(['success' => false, 'message' => 'La contraseña actual es incorrecta.'], 400);
    }

    // Verificar que no sea una contraseña ya usada
    if (isPasswordInHistory($db, (int)$user['id'], $newPwd)) {
        jsonResponse(['success' => false, 'message' => 'No puedes usar una contraseña que ya usaste anteriormente. Elige una nueva.'], 400);
    }

    $oldHash = $user['password_hash'];
    $hash    = password_hash($newPwd, PASSWORD_BCRYPT, ['cost' => 12]);
    $update  = $db->prepare("UPDATE usuarios SET password_hash = ?, actualizado_en = NOW() WHERE id = ?");
    $update->execute([$hash, $user['id']]);

    if ($update->rowCount() === 0) {
        jsonResponse(['success' => false, 'message' => 'No se pudo actualizar. Intenta de nuevo.'], 500);
    }

    // Guardar contraseña anterior y nueva en historial
    savePasswordHistory($db, (int)$user['id'], $oldHash);
    savePasswordHistory($db, (int)$user['id'], $hash);

    jsonResponse(['success' => true, 'message' => '¡Contraseña actualizada! Ya puedes iniciar sesión con la nueva contraseña.']);
}

// ============================================================================
// VALIDATE RESET TOKEN (used by reset-password.html on load)
// ============================================================================
function handleValidateResetToken(array $body): void {
    $token = sanitize($body['token'] ?? '');
    if (!$token) {
        jsonResponse(['success' => false, 'message' => 'Token requerido.'], 422);
    }

    $db   = getDB();
    $stmt = $db->prepare("SELECT id FROM usuarios WHERE token_reset = ? AND token_reset_exp > NOW() AND activo = 1 LIMIT 1");
    $stmt->execute([$token]);

    jsonResponse(['success' => (bool) $stmt->fetch()]);
}

// ============================================================================
// UPDATE PROFILE — guarda nombre y teléfono en la BD
// ============================================================================
function handleUpdateProfile(array $body): void {
    if (empty($_SESSION['user_id'])) {
        jsonResponse(['success' => false, 'message' => 'No autenticado.'], 401);
    }

    $nombre   = sanitize($body['nombre']   ?? '');
    $apellido = sanitize($body['apellido'] ?? '');
    $telefono = sanitize($body['telefono'] ?? '');

    if (!$nombre) {
        jsonResponse(['success' => false, 'message' => 'El nombre es obligatorio.'], 422);
    }

    $db = getDB();
    $db->prepare("UPDATE usuarios SET nombre = ?, apellido = ?, telefono = ?, actualizado_en = NOW() WHERE id = ? AND activo = 1")
       ->execute([$nombre, $apellido ?: null, $telefono ?: null, $_SESSION['user_id']]);

    jsonResponse([
        'success'  => true,
        'message'  => 'Perfil actualizado correctamente.',
        'nombre'   => $nombre,
        'apellido' => $apellido,
        'telefono' => $telefono,
    ]);
}

// ============================================================================
// SET PASSWORD — permite a usuarios Google crear una contraseña por primera vez
// ============================================================================
function handleSetPassword(array $body): void {
    if (empty($_SESSION['user_id'])) {
        jsonResponse(['success' => false, 'message' => 'No autenticado.'], 401);
    }

    $newPwd  = $body['new_password']     ?? '';
    $confirm = $body['confirm_password'] ?? '';

    if (strlen($newPwd) < 8) {
        jsonResponse(['success' => false, 'message' => 'La contraseña debe tener al menos 8 caracteres.'], 422);
    }
    if ($newPwd !== $confirm) {
        jsonResponse(['success' => false, 'message' => 'Las contraseñas no coinciden.'], 422);
    }

    $db   = getDB();
    $stmt = $db->prepare("SELECT id, password_hash FROM usuarios WHERE id = ? AND activo = 1 LIMIT 1");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();

    if (!$user) {
        jsonResponse(['success' => false, 'message' => 'Usuario no encontrado.'], 404);
    }
    if (!empty($user['password_hash'])) {
        jsonResponse(['success' => false, 'message' => 'Esta cuenta ya tiene contraseña. Usa "Cambiar contraseña".'], 400);
    }

    // Para set_password (primera vez) también verificar historial por si acaso
    if (isPasswordInHistory($db, (int)$user['id'], $newPwd)) {
        jsonResponse(['success' => false, 'message' => 'No puedes usar una contraseña que ya usaste anteriormente. Elige una nueva.'], 400);
    }

    $hash = password_hash($newPwd, PASSWORD_BCRYPT, ['cost' => 12]);
    $db->prepare("UPDATE usuarios SET password_hash = ?, actualizado_en = NOW() WHERE id = ?")
       ->execute([$hash, $user['id']]);

    savePasswordHistory($db, (int)$user['id'], $hash);

    jsonResponse(['success' => true, 'message' => '¡Contraseña creada! Ya puedes iniciar sesión con email y contraseña.']);
}

// ============================================================================
// UPLOAD AVATAR — guarda avatar como URL externa o base64 en avatar_url
// ============================================================================
function handleUploadAvatar(array $body): void {
    if (empty($_SESSION['user_id'])) {
        jsonResponse(['success' => false, 'message' => 'No autenticado.'], 401);
    }

    $avatarUrl = $body['avatar_url'] ?? '';
    if (!$avatarUrl) {
        jsonResponse(['success' => false, 'message' => 'No se recibió imagen.'], 422);
    }

    // Aceptar URL https o base64 data:image
    $isBase64 = str_starts_with($avatarUrl, 'data:image/');
    $isUrl    = str_starts_with($avatarUrl, 'https://') || str_starts_with($avatarUrl, 'http://');

    if (!$isBase64 && !$isUrl) {
        jsonResponse(['success' => false, 'message' => 'Formato de imagen no válido.'], 422);
    }

    // Limitar tamaño de base64 (~1MB)
    if ($isBase64 && strlen($avatarUrl) > 1400000) {
        jsonResponse(['success' => false, 'message' => 'La imagen es demasiado grande. Máximo 1MB.'], 422);
    }

    $db = getDB();
    $db->prepare("UPDATE usuarios SET avatar_url = ?, actualizado_en = NOW() WHERE id = ?")
       ->execute([$avatarUrl, $_SESSION['user_id']]);

    jsonResponse(['success' => true, 'avatar_url' => $avatarUrl, 'message' => 'Foto de perfil actualizada.']);
}

// ============================================================================
// DELETE AVATAR — elimina la foto de perfil
// ============================================================================
function handleDeleteAvatar(): void {
    if (empty($_SESSION['user_id'])) {
        jsonResponse(['success' => false, 'message' => 'No autenticado.'], 401);
    }
    $db = getDB();
    $db->prepare("UPDATE usuarios SET avatar_url = NULL, actualizado_en = NOW() WHERE id = ?")
       ->execute([$_SESSION['user_id']]);
    jsonResponse(['success' => true, 'message' => 'Foto de perfil eliminada.']);
}

// ============================================================================
// VERIFY EMAIL — verifica el código enviado al registrarse
// ============================================================================
function handleVerifyEmail(array $body): void {
    checkRateLimitDB('verify_email', 10, 300);

    $code = sanitize($body['code'] ?? '');
    if (!$code) {
        jsonResponse(['success' => false, 'message' => 'Código requerido.'], 422);
    }

    // Verificar que hay un registro pendiente en sesión
    $pending = $_SESSION['pending_register'] ?? null;
    if (!$pending) {
        jsonResponse(['success' => false, 'message' => 'No hay registro pendiente. Vuelve a crear tu cuenta.'], 400);
    }

    // Verificar expiración
    if (time() > $pending['expires']) {
        unset($_SESSION['pending_register']);
        jsonResponse(['success' => false, 'message' => 'El código expiró. Vuelve a registrarte.'], 400);
    }

    // Verificar código
    if (hash('sha256', $code) !== $pending['code_hash']) {
        jsonResponse(['success' => false, 'message' => 'Código incorrecto. Inténtalo de nuevo.'], 400);
    }

    // Código correcto → crear la cuenta ahora
    $db = getDB();

    // Verificar que el email no fue registrado por alguien más mientras esperaba
    $check = $db->prepare("SELECT id FROM usuarios WHERE email = ? LIMIT 1");
    $check->execute([$pending['email']]);
    if ($check->fetch()) {
        unset($_SESSION['pending_register']);
        jsonResponse(['success' => false, 'message' => 'Este correo ya fue registrado. Inicia sesión.'], 409);
    }

    $stmt = $db->prepare("
        INSERT INTO usuarios
          (nombre, apellido, email, password_hash, telefono, rol, activo, email_verificado, creado_en)
        VALUES (?, ?, ?, ?, ?, 'cliente', 1, 1, NOW())
    ");
    $stmt->execute([
        $pending['nombre'],
        $pending['apellido'] ?: null,
        $pending['email'],
        $pending['hash'],
        $pending['telefono'] ?: null,
    ]);
    $userId = (int) $db->lastInsertId();

    // Guardar contraseña en historial
    savePasswordHistory($db, $userId, $pending['hash']);

    // Limpiar datos de registro pendiente — NO se abre sesión.
    // El usuario debe iniciar sesión manualmente tras verificar el correo.
    unset($_SESSION['pending_register']);

    clearRateLimitDB('verify_email');

    jsonResponse([
        'success' => true,
        'message' => '¡Correo verificado! Ya puedes iniciar sesión.',
        'user'    => [
            'nombre'           => $pending['nombre'],
            'apellido'         => $pending['apellido'] ?? '',
            'email'            => $pending['email'],
            'email_verificado' => 1,
        ],
    ]);
}

// ============================================================================
// RESEND VERIFICATION — reenvía el código de verificación
// ============================================================================
function handleResendVerification(array $body): void {
    checkRateLimitDB('resend_verify', 3, 300);

    $pending = $_SESSION['pending_register'] ?? null;
    if (!$pending) {
        jsonResponse(['success' => false, 'message' => 'No hay registro pendiente.'], 400);
    }

    $code     = str_pad((string) random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
    $codeHash = hash('sha256', $code);

    // Actualizar código en sesión
    $_SESSION['pending_register']['code_hash'] = $codeHash;
    $_SESSION['pending_register']['expires']   = time() + 900;

    $displayName = trim($pending['nombre'] . ($pending['apellido'] ? ' ' . $pending['apellido'] : ''));

    try {
        $mail = new Mail();
        $mail->to($pending['email'], $displayName)
             ->subject('Nuevo código de verificación — Mercaitech')
             ->body(
                 Mail::templateCode($displayName, $code),
                 "Tu nuevo código de verificación es: {$code}\nExpira en 15 minutos."
             )
             ->send();
        jsonResponse(['success' => true, 'message' => 'Código reenviado. Revisa tu correo.']);
    } catch (\Throwable $e) {
        @file_put_contents(
            __DIR__ . '/../../storage/logs/mail.log',
            date('Y-m-d H:i:s') . " [resend_verify] " . $e->getMessage() . PHP_EOL,
            FILE_APPEND
        );
        jsonResponse(['success' => false, 'message' => 'No se pudo enviar el correo. Inténtalo de nuevo.'], 500);
    }
}
