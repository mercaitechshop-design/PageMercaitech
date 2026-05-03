<?php
// Mercaitech — Database configuration
// Copy this file to config.local.php and set your real credentials
// Never commit config.local.php to version control

define('DB_HOST', 'localhost');
define('DB_PORT', '3306');
define('DB_NAME', 'mercaitech');
define('DB_USER', 'root');       // Change in production
define('DB_PASS', '');           // Change in production
define('DB_CHARSET', 'utf8mb4');

// App settings
define('APP_ENV', 'development'); // 'production' | 'development'
define('APP_URL', 'http://localhost');
define('JWT_SECRET', 'change-this-to-a-random-secret-key-in-production');

// ── Email (Gmail SMTP) ─────────────────────────────────────────────────────
// 1. Activa verificación en 2 pasos en tu cuenta Google
// 2. Ve a myaccount.google.com → Seguridad → Contraseñas de aplicación
// 3. Crea una contraseña de aplicación para "Correo / Otro"
// 4. Pega la contraseña de 16 caracteres en SMTP_PASS
define('SMTP_HOST',       'smtp.gmail.com');
define('SMTP_PORT',       587);
define('SMTP_USER',       'mercaitechshop@gmail.com');  // Tu Gmail
define('SMTP_PASS',       'kafhvdrlarpknkyh');          // Contraseña de aplicación (16 chars)
define('MAIL_FROM_EMAIL', 'mercaitechshop@gmail.com');
define('MAIL_FROM_NAME',  'Mercaitech');

// Load local overrides if they exist
if (file_exists(__DIR__ . '/config.local.php')) {
    require_once __DIR__ . '/config.local.php';
}

// PDO connection factory
function getDB(): PDO {
    static $pdo = null;
    if ($pdo !== null) return $pdo;

    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=%s',
        DB_HOST, DB_PORT, DB_NAME, DB_CHARSET
    );
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
    ];

    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
    } catch (PDOException $e) {
        if (APP_ENV === 'development') {
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        } else {
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Database connection error.']);
        }
        exit;
    }
    return $pdo;
}

// CORS headers — adjust origins for production
function setCorsHeaders(): void {
    $allowed = APP_ENV === 'production' ? APP_URL : '*';
    header('Access-Control-Allow-Origin: ' . $allowed);
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(204);
        exit;
    }
}

// JSON response helper
function jsonResponse(array $data, int $status = 200): void {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

// Sanitize input
function sanitize(string $input): string {
    return htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8');
}

// Validate email
function validEmail(string $email): bool {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

// Get JSON body
function getJsonBody(): array {
    $raw = file_get_contents('php://input');
    if (!$raw) return [];
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}
