<?php
// Mercaitech — Authentication API
// POST /api/auth.php  body: { action, ...fields }
//
// Actions: register · login · logout · me · forgot · reset · verify

declare(strict_types=1);
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/helpers/Mail.php';

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
// RATE LIMITING (simple, IP-based via $_SESSION)
// ============================================================================
function checkRateLimit(string $key, int $max = 5, int $windowSecs = 300): void {
    $now = time();
    $sessionKey = "rl_{$key}";

    if (!isset($_SESSION[$sessionKey])) {
        $_SESSION[$sessionKey] = ['count' => 0, 'reset_at' => $now + $windowSecs];
    }

    if ($now > $_SESSION[$sessionKey]['reset_at']) {
        $_SESSION[$sessionKey] = ['count' => 0, 'reset_at' => $now + $windowSecs];
    }

    $_SESSION[$sessionKey]['count']++;

    if ($_SESSION[$sessionKey]['count'] > $max) {
        $wait = (int) ceil(($_SESSION[$sessionKey]['reset_at'] - $now) / 60);
        jsonResponse([
            'success' => false,
            'message' => "Demasiados intentos. Espera {$wait} min e inténtalo de nuevo."
        ], 429);
    }
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

    // Validate
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

    $db = getDB();

    // Check duplicate email
    $check = $db->prepare("SELECT id FROM usuarios WHERE email = ? LIMIT 1");
    $check->execute([$email]);
    if ($check->fetch()) {
        jsonResponse(['success' => false, 'message' => 'Este correo ya está registrado. ¿Quieres iniciar sesión?'], 409);
    }

    // Hash password with BCrypt cost 12
    $hash      = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    $fullName  = trim($nombre . ($apellido ? ' ' . $apellido : ''));
    $verifyTok = bin2hex(random_bytes(32));

    $stmt = $db->prepare("
        INSERT INTO usuarios
          (nombre, email, password_hash, rol, activo, email_verificado, token_verificacion, creado_en)
        VALUES (?, ?, ?, 'cliente', 1, 0, ?, NOW())
    ");
    $stmt->execute([$fullName, $email, $hash, $verifyTok]);
    $userId = (int) $db->lastInsertId();

    // Open session
    $_SESSION['user_id']    = $userId;
    $_SESSION['user_email'] = $email;
    $_SESSION['user_role']  = 'cliente';

    // Reset rate limit on success
    unset($_SESSION['rl_register']);

    // TODO: Send verification email
    // sendVerificationEmail($email, $fullName, $verifyTok);

    $user = ['id' => $userId, 'nombre' => $fullName, 'email' => $email, 'rol' => 'cliente'];
    jsonResponse(['success' => true, 'user' => $user, 'message' => '¡Cuenta creada exitosamente!'], 201);
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
        // Increment failed attempts
        if ($user) {
            $attempts = (int)$user['intentos_fallidos'] + 1;
            $lockUntil = $attempts >= 5 ? date('Y-m-d H:i:s', time() + 900) : null; // 15 min after 5 fails
            $db->prepare("UPDATE usuarios SET intentos_fallidos = ?, bloqueado_hasta = ? WHERE id = ?")
               ->execute([$attempts, $lockUntil, $user['id']]);
        }
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

    // Remember-me cookie (30 days)
    if ($remember) {
        $token = bin2hex(random_bytes(32));
        $exp   = time() + 86400 * 30;
        setcookie('mt_remember', $token, [
            'expires'  => $exp,
            'path'     => '/',
            'httponly' => true,
            'samesite' => 'Strict',
            'secure'   => isset($_SERVER['HTTPS']),
        ]);
        // Persist token in DB so it can be validated on next visit
        $db->prepare("UPDATE usuarios SET remember_token = ?, remember_expires = ? WHERE id = ?")
           ->execute([$token, date('Y-m-d H:i:s', $exp), $user['id']]);
    }

    unset($_SESSION['rl_login']);

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
        $db   = getDB();
        $stmt = $db->prepare("
            SELECT id, nombre, email, rol
            FROM usuarios
            WHERE remember_token = ? AND remember_expires > NOW() AND activo = 1
            LIMIT 1
        ");
        $stmt->execute([$token]);
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

    // Código de 6 dígitos, expira en 15 minutos
    $code    = str_pad((string) random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
    $expires = date('Y-m-d H:i:s', time() + 900);

    $db->prepare("UPDATE usuarios SET token_reset = ?, token_reset_exp = ? WHERE id = ?")
       ->execute([$code, $expires, $user['id']]);

    try {
        $mail = new Mail();
        $mail->to($email, $user['nombre'])
             ->subject('Tu código de verificación — Mercaitech')
             ->body(
                 Mail::templateCode($user['nombre'], $code),
                 "Hola {$user['nombre']}, tu código para restablecer la contraseña es: {$code}\nExpira en 15 minutos.\nSi no lo solicitaste, ignora este mensaje."
             )
             ->send();

        jsonResponse(['success' => true, 'message' => 'Código enviado. Revisa tu bandeja de entrada y la carpeta spam.']);

    } catch (Throwable $e) {
        $errorDetail = $e->getMessage();
        file_put_contents(
            __DIR__ . '/mail_error.log',
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
function handleReset(array $body): void {
    $email    = sanitize($body['email']    ?? '');
    $code     = sanitize($body['code']     ?? '');
    $password = $body['password'] ?? '';

    if (!$email || !$code) {
        jsonResponse(['success' => false, 'message' => 'Correo y código requeridos.'], 422);
    }
    if (strlen($password) < 8) {
        jsonResponse(['success' => false, 'message' => 'La contraseña debe tener al menos 8 caracteres.'], 422);
    }

    $db   = getDB();
    $stmt = $db->prepare("
        SELECT id FROM usuarios
        WHERE email = ? AND token_reset = ? AND token_reset_exp > NOW() AND activo = 1
        LIMIT 1
    ");
    $stmt->execute([$email, $code]);
    $user = $stmt->fetch();

    if (!$user) {
        jsonResponse(['success' => false, 'message' => 'Código incorrecto o expirado. Solicita uno nuevo.'], 400);
    }

    $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    $db->prepare("
        UPDATE usuarios
        SET password_hash = ?, token_reset = NULL, token_reset_exp = NULL,
            intentos_fallidos = 0, bloqueado_hasta = NULL
        WHERE id = ?
    ")->execute([$hash, $user['id']]);

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
    $stmt = $db->prepare("SELECT id, nombre, email, rol, activo FROM usuarios WHERE google_id = ? LIMIT 1");
    $stmt->execute([$googleId]);
    $user = $stmt->fetch();

    // 2. Si no encontró por google_id, buscar por email (cuenta existente por email/password)
    if (!$user) {
        $stmt = $db->prepare("SELECT id, nombre, email, rol, activo FROM usuarios WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user) {
            // Vincular el google_id a la cuenta existente
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
              (nombre, email, password_hash, rol, activo, email_verificado, google_id, avatar_url, creado_en)
            VALUES (?, ?, '', 'cliente', 1, 1, ?, ?, NOW())
        ")->execute([$nombre, $email, $googleId, $avatar ?: null]);

        $userId = (int) $db->lastInsertId();
        $user   = ['id' => $userId, 'nombre' => $nombre, 'email' => $email, 'rol' => 'cliente', 'avatar_url' => $avatar];
    } else {
        if (!$user['activo']) {
            jsonResponse(['success' => false, 'message' => 'Esta cuenta está desactivada. Contacta soporte.'], 403);
        }
        // Actualizar avatar si viene uno nuevo
        if ($avatar && empty($user['avatar_url'])) {
            $db->prepare("UPDATE usuarios SET avatar_url = ?, ultimo_login = NOW() WHERE id = ?")
               ->execute([$avatar, $user['id']]);
            $user['avatar_url'] = $avatar;
        } else {
            $db->prepare("UPDATE usuarios SET ultimo_login = NOW() WHERE id = ?")->execute([$user['id']]);
        }
    }

    session_regenerate_id(true);
    $_SESSION['user_id']    = $user['id'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['user_role']  = $user['rol'];

    jsonResponse([
        'success'  => true,
        'is_new'   => $isNew,
        'user'     => ['id' => $user['id'], 'nombre' => $user['nombre'], 'email' => $user['email'], 'rol' => $user['rol']],
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
    $email = sanitize($body['email'] ?? '');
    $code  = sanitize($body['code']  ?? '');

    if (!$email || !$code) {
        jsonResponse(['success' => false, 'message' => 'Correo y código requeridos.'], 422);
    }

    $db   = getDB();
    $stmt = $db->prepare("
        SELECT id FROM usuarios
        WHERE email = ? AND token_reset = ? AND token_reset_exp > NOW() AND activo = 1
        LIMIT 1
    ");
    $stmt->execute([$email, $code]);

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

    // Generar nuevo hash y actualizar
    $hash = password_hash($newPwd, PASSWORD_BCRYPT, ['cost' => 12]);
    $update = $db->prepare("UPDATE usuarios SET password_hash = ?, actualizado_en = NOW() WHERE id = ?");
    $update->execute([$hash, $user['id']]);

    if ($update->rowCount() === 0) {
        jsonResponse(['success' => false, 'message' => 'No se pudo actualizar. Intenta de nuevo.'], 500);
    }

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

    $hash = password_hash($newPwd, PASSWORD_BCRYPT, ['cost' => 12]);
    $db->prepare("UPDATE usuarios SET password_hash = ?, actualizado_en = NOW() WHERE id = ?")
       ->execute([$hash, $user['id']]);

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
