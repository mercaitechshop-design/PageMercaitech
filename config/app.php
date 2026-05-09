<?php
// Mercaitech — Configuración base
// Las credenciales reales van en config.local.php (ignorado por git)

declare(strict_types=1);

// Cargar credenciales locales PRIMERO para que sus define() tengan prioridad
if (file_exists(__DIR__ . '/local.php')) {
    require_once __DIR__ . '/local.php';
}

// Valores por defecto — solo se aplican si config.local.php no los definió
if (!defined('DB_HOST'))         define('DB_HOST',         'localhost');
if (!defined('DB_PORT'))         define('DB_PORT',         '3306');
if (!defined('DB_NAME'))         define('DB_NAME',         'mercaitech');
if (!defined('DB_USER'))         define('DB_USER',         'mercaitech');
if (!defined('DB_PASS'))         define('DB_PASS',         '');
if (!defined('DB_CHARSET'))      define('DB_CHARSET',      'utf8mb4');

if (!defined('APP_ENV'))         define('APP_ENV',         'production');
if (!defined('APP_URL'))         define('APP_URL',         'http://localhost:8080');
if (!defined('JWT_SECRET'))      define('JWT_SECRET',      '');

if (!defined('SMTP_HOST'))       define('SMTP_HOST',       'smtp.gmail.com');
if (!defined('SMTP_PORT'))       define('SMTP_PORT',       587);
if (!defined('SMTP_USER'))       define('SMTP_USER',       '');
if (!defined('SMTP_PASS'))       define('SMTP_PASS',       '');
if (!defined('MAIL_FROM_EMAIL')) define('MAIL_FROM_EMAIL', '');
if (!defined('MAIL_FROM_NAME'))  define('MAIL_FROM_NAME',  'Mercaitech');

// ── PDO connection factory ────────────────────────────────────────────────────
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
        http_response_code(500);
        header('Content-Type: application/json');
        $msg = APP_ENV === 'development' ? $e->getMessage() : 'Database connection error.';
        echo json_encode(['success' => false, 'error' => $msg]);
        exit;
    }
    return $pdo;
}

// ── CORS ─────────────────────────────────────────────────────────────────────
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

// ── Helpers ───────────────────────────────────────────────────────────────────
function jsonResponse(array $data, int $status = 200): void {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function sanitize(string $input): string {
    return htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8');
}

function validEmail(string $email): bool {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function getJsonBody(): array {
    $raw  = file_get_contents('php://input');
    if (!$raw) return [];
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}
