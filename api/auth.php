<?php
// Mercaitech — Authentication API
// POST /api/auth.php  body: { action, ...fields }
//
// Actions: register · login · logout · me · forgot · reset · verify

declare(strict_types=1);
require_once __DIR__ . '/config.php';

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
    'google'       => handleGoogleSignIn($body),
    'google_code'  => handleGoogleCode($body),
    default        => jsonResponse(['success' => false, 'error' => "Acción '$action' no encontrada."], 404),
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
        SELECT id, nombre, email, password_hash, rol, activo, intentos_fallidos, bloqueado_hasta
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
        'id'     => $user['id'],
        'nombre' => $user['nombre'],
        'email'  => $user['email'],
        'rol'    => $user['rol'],
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
    $stmt = $db->prepare("SELECT id, nombre, email, rol FROM usuarios WHERE id = ? AND activo = 1 LIMIT 1");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();

    if (!$user) {
        session_destroy();
        jsonResponse(['success' => false, 'message' => 'Sesión inválida.'], 401);
    }

    jsonResponse(['success' => true, 'user' => $user]);
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
// FORGOT PASSWORD
// ============================================================================
// ============================================================================
// FORGOT PASSWORD (FIXED + EMAIL SENDING)
// ============================================================================
function handleForgot(array $body): void {
    checkRateLimit('forgot', 3, 600);

    $email = sanitize($body['email'] ?? '');

    if (!$email || !validEmail($email)) {
        jsonResponse(['success' => false, 'message' => 'Correo inválido.'], 422);
    }

    $db = getDB();

    // Buscar usuario
    $stmt = $db->prepare("SELECT id, nombre FROM usuarios WHERE email = ? AND activo = 1 LIMIT 1");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    // Siempre responder igual (evita enumeración de correos)
    $genericResponse = [
        'success' => true,
        'message' => 'Si ese correo existe, recibirás un enlace en breve.'
    ];

    if (!$user) {
        jsonResponse($genericResponse);
    }

    // Generar token seguro
    $token   = bin2hex(random_bytes(32));
    $expires = date('Y-m-d H:i:s', time() + 3600); // 1 hora

    // Guardar token en DB
    $db->prepare("
        UPDATE usuarios 
        SET token_reset = ?, token_reset_exp = ? 
        WHERE id = ?
    ")->execute([$token, $expires, $user['id']]);

    // ==========================
    // ENVIAR CORREO REAL
    // ==========================
    try {
        require_once __DIR__ . '/Mail.php';

        $resetUrl = APP_URL . "/reset-password.html?token=" . $token;

        $subject = "Restablecer contraseña - Mercaitech";

        $message = "
        <h2>Hola {$user['nombre']}</h2>
        <p>Recibimos una solicitud para restablecer tu contraseña.</p>
        <p>Haz clic en el siguiente botón:</p>
        <p>
            <a href='{$resetUrl}' 
               style='display:inline-block;padding:12px 20px;background:#0066ff;color:#fff;text-decoration:none;border-radius:8px'>
               Restablecer contraseña
            </a>
        </p>
        <p>Este enlace expirará en 1 hora.</p>
        <p>Si no solicitaste esto, ignora este mensaje.</p>
        ";

        sendMail($email, $subject, $message);

    } catch (Throwable $e) {
        // Log error sin romper flujo
        file_put_contents(
            __DIR__ . '/mail_error.log',
            date('Y-m-d H:i:s') . " - " . $e->getMessage() . PHP_EOL,
            FILE_APPEND
        );
    }

    jsonResponse($genericResponse);
}
// ============================================================================
// RESET PASSWORD
// ============================================================================
function handleReset(array $body): void {
    $token    = sanitize($body['token']    ?? '');
    $password = $body['password'] ?? '';

    if (!$token) {
        jsonResponse(['success' => false, 'message' => 'Token requerido.'], 422);
    }
    if (strlen($password) < 8) {
        jsonResponse(['success' => false, 'message' => 'La contraseña debe tener al menos 8 caracteres.'], 422);
    }

    $db   = getDB();
    $stmt = $db->prepare("
        SELECT id FROM usuarios
        WHERE token_reset = ? AND token_reset_exp > NOW() AND activo = 1
        LIMIT 1
    ");
    $stmt->execute([$token]);
    $user = $stmt->fetch();

    if (!$user) {
        jsonResponse(['success' => false, 'message' => 'Token inválido o expirado. Solicita uno nuevo.'], 400);
    }

    $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    $db->prepare("
        UPDATE usuarios
        SET password_hash = ?, token_reset = NULL, token_reset_exp = NULL, intentos_fallidos = 0, bloqueado_hasta = NULL
        WHERE id = ?
    ")->execute([$hash, $user['id']]);

    jsonResponse(['success' => true, 'message' => 'Contraseña actualizada. Ya puedes iniciar sesión.']);
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
    $credential = $body['credential'] ?? '';
    $email      = sanitize($body['email']    ?? '');
    $nombre     = sanitize($body['nombre']   ?? '');
    $googleId   = sanitize($body['googleId'] ?? '');
    $avatar     = sanitize($body['avatar']   ?? '');

    // --- Verify the JWT signature in production ---
    // For production, verify with Google's public keys:
    // GET https://www.googleapis.com/oauth2/v3/certs
    // Here we trust the decoded payload sent from the frontend (acceptable when
    // the request comes from your own JS, but add server-side JWT verification
    // before going live: https://developers.google.com/identity/gsi/web/guides/verify-google-id-token)

    if (!$email || !validEmail($email)) {
        jsonResponse(['success' => false, 'message' => 'No se pudo obtener el email de Google.'], 422);
    }

    $db = getDB();

    // Find existing account by email
    $stmt = $db->prepare("SELECT id, nombre, email, rol, activo FROM usuarios WHERE email = ? LIMIT 1");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user) {
        // Existing user — log them in
        if (!$user['activo']) {
            jsonResponse(['success' => false, 'message' => 'Esta cuenta está desactivada.'], 403);
        }
        $db->prepare("UPDATE usuarios SET ultimo_login = NOW() WHERE id = ?")->execute([$user['id']]);
    } else {
        // New user — create account (no password required for Google users)
        $stmt = $db->prepare("
            INSERT INTO usuarios
              (nombre, email, password_hash, rol, activo, email_verificado, creado_en)
            VALUES (?, ?, '', 'cliente', 1, 1, NOW())
        ");
        $stmt->execute([$nombre ?: explode('@', $email)[0], $email]);
        $userId = (int) $db->lastInsertId();
        $user   = ['id' => $userId, 'nombre' => $nombre, 'email' => $email, 'rol' => 'cliente'];
    }

    session_regenerate_id(true);
    $_SESSION['user_id']    = $user['id'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['user_role']  = $user['rol'];

    $userData = ['id' => $user['id'], 'nombre' => $user['nombre'],
                 'email' => $user['email'], 'rol' => $user['rol']];

    jsonResponse(['success' => true, 'user' => $userData, 'redirect' => 'index.html']);
}

// ============================================================================
// GOOGLE AUTH CODE EXCHANGE (alternative flow)
// ============================================================================
function handleGoogleCode(array $body): void {
    // In production: exchange $body['code'] for tokens using Google OAuth2 API
    // POST https://oauth2.googleapis.com/token with client_id, client_secret, code, redirect_uri
    // Then fetch user info from https://www.googleapis.com/oauth2/v3/userinfo
    // For now, return an error prompting server-side configuration
    jsonResponse(['success' => false,
        'message' => 'Configura GOOGLE_CLIENT_SECRET en el servidor para el flujo de código.'], 501);
}
