<?php
// Mercaitech — Configuración base
// Las credenciales reales van en config/local.php (ignorado por git)

declare(strict_types=1);

// Cargar credenciales locales PRIMERO para que sus define() tengan prioridad
if (file_exists(__DIR__ . '/local.php')) {
    require_once __DIR__ . '/local.php';
}

// ── Defaults ──────────────────────────────────────────────────────────────────
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

if (!defined('MP_ACCESS_TOKEN')) define('MP_ACCESS_TOKEN', '');
if (!defined('MP_PUBLIC_KEY'))   define('MP_PUBLIC_KEY',   '');
if (!defined('MP_WEBHOOK_SECRET')) define('MP_WEBHOOK_SECRET', '');
if (!defined('MP_SUCCESS_URL'))  define('MP_SUCCESS_URL',  'http://localhost:8080/public/checkout.html?mp_status=approved');
if (!defined('MP_FAILURE_URL'))  define('MP_FAILURE_URL',  'http://localhost:8080/public/checkout.html?mp_status=failure');
if (!defined('MP_PENDING_URL'))  define('MP_PENDING_URL',  'http://localhost:8080/public/checkout.html?mp_status=pending');

// ── PDO singleton ─────────────────────────────────────────────────────────────
function getDB(): PDO {
    static $pdo = null;
    if ($pdo !== null) return $pdo;

    $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=%s', DB_HOST, DB_PORT, DB_NAME, DB_CHARSET);
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
    ];
    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
    } catch (PDOException $e) {
        // 503 Service Unavailable — el servidor existe pero la BD no responde
        // Los load balancers y monitores de uptime interpretan 503 correctamente
        http_response_code(503);
        header('Content-Type: application/json');
        header('Retry-After: 30'); // indica al cliente que reintente en 30 seg
        @file_put_contents(__DIR__ . '/../storage/logs/security.log',
            date('Y-m-d H:i:s') . " [DB_DOWN] " . $e->getMessage() . PHP_EOL, FILE_APPEND);
        echo json_encode(['success' => false, 'error' => APP_ENV === 'development'
            ? $e->getMessage()
            : 'Servicio temporalmente no disponible. Inténtalo en unos minutos.']);
        exit;
    }
    return $pdo;
}

// ── CORS ──────────────────────────────────────────────────────────────────────
function setCorsHeaders(): void {
    $isProd  = APP_ENV === 'production';
    $allowed = $isProd ? APP_URL : '*';
    header('Access-Control-Allow-Origin: ' . $allowed);
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-CSRF-Token');
    header('Access-Control-Allow-Credentials: true');

    // Aplicar headers de seguridad en cada respuesta
    setSecurityHeaders();

    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(204);
        exit;
    }
}

// ── Security headers ──────────────────────────────────────────────────────────
function setSecurityHeaders(): void {
    // Previene MIME sniffing (el browser no puede adivinar el tipo de contenido)
    header('X-Content-Type-Options: nosniff');

    // Previene clickjacking (la página no puede cargarse en un <iframe> externo)
    header('X-Frame-Options: SAMEORIGIN');

    // Protección XSS del browser (legacy, complementa CSP)
    header('X-XSS-Protection: 1; mode=block');

    // No envía el referrer a sitios externos
    header('Referrer-Policy: strict-origin-when-cross-origin');

    // Deshabilita features del browser que no usamos
    header('Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=()');

    // Elimina firma del servidor
    header('X-Powered-By: ');

    // HSTS — solo en producción HTTPS
    if (APP_ENV === 'production') {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload');
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

// ── IP hashing (GDPR-safe) ────────────────────────────────────────────────────
function ipHash(): string {
    $ip = $_SERVER['HTTP_CF_CONNECTING_IP']   // Cloudflare
       ?? $_SERVER['HTTP_X_REAL_IP']
       ?? (explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '')[0])
       ?? $_SERVER['REMOTE_ADDR']
       ?? 'unknown';
    return hash('sha256', trim($ip) . '_mt_rl_salt_2026');
}

// ── Rate limiting persistente (DB-based) ──────────────────────────────────────
// A diferencia del rate limiting por sesión, este persiste aunque el usuario
// borre cookies o cambie de pestaña. Bloquea por IP real.
function checkRateLimitDB(string $action, int $max = 5, int $windowSecs = 300): void {
    try {
        $db     = getDB();
        $ipHash = ipHash();
        $now    = time();

        // Tabla auto-creada si no existe
        $db->exec("
            CREATE TABLE IF NOT EXISTS rate_limits (
                id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                ip_hash      CHAR(64) NOT NULL,
                action       VARCHAR(50) NOT NULL,
                attempts     INT UNSIGNED NOT NULL DEFAULT 1,
                window_start INT UNSIGNED NOT NULL,
                INDEX idx_ip_action (ip_hash, action)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        // Limpiar ventanas viejas
        $db->prepare("DELETE FROM rate_limits WHERE window_start < ?")->execute([$now - $windowSecs]);

        // Buscar o crear registro
        $stmt = $db->prepare("
            SELECT id, attempts FROM rate_limits
            WHERE ip_hash = ? AND action = ? AND window_start >= ?
            LIMIT 1
        ");
        $stmt->execute([$ipHash, $action, $now - $windowSecs]);
        $row = $stmt->fetch();

        if ($row) {
            $attempts = (int)$row['attempts'] + 1;
            $db->prepare("UPDATE rate_limits SET attempts = ? WHERE id = ?")->execute([$attempts, $row['id']]);
        } else {
            $attempts = 1;
            $db->prepare("INSERT INTO rate_limits (ip_hash, action, attempts, window_start) VALUES (?,?,1,?)")
               ->execute([$ipHash, $action, $now]);
        }

        if ($attempts > $max) {
            $wait = max(1, (int) ceil($windowSecs / 60));
            http_response_code(429);
            header('Retry-After: ' . $windowSecs);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'success' => false,
                'message' => "Demasiados intentos. Espera {$wait} minuto(s) e inténtalo de nuevo."
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

    } catch (\Throwable $e) {
        // Non-fatal: si la BD de rate limiting falla, la petición pasa
        // Se loguea para detectar ataques durante degradación del servicio
        @file_put_contents(__DIR__ . '/../storage/logs/security.log',
            date('Y-m-d H:i:s') . " [RL_FAIL action=$action] " . $e->getMessage() . PHP_EOL, FILE_APPEND);
    }
}

function clearRateLimitDB(string $action): void {
    try {
        getDB()->prepare("DELETE FROM rate_limits WHERE ip_hash = ? AND action = ?")
               ->execute([ipHash(), $action]);
    } catch (\Throwable) {}
}

// ── Audit / Security logging ──────────────────────────────────────────────────
function securityLog(string $event, array $context = []): void {
    $line = date('Y-m-d H:i:s') . " [$event] " . json_encode($context, JSON_UNESCAPED_UNICODE) . PHP_EOL;
    @file_put_contents(__DIR__ . '/../storage/logs/security.log', $line, FILE_APPEND);
}

// ── cURL seguro ───────────────────────────────────────────────────────────────
// Devuelve opciones de cURL adaptadas al entorno.
// En producción verifica SSL; en desarrollo lo omite (localhost no tiene certs válidos).
function curlSecureOpts(): array {
    $isProd = APP_ENV === 'production';
    return [
        CURLOPT_SSL_VERIFYPEER => $isProd,
        CURLOPT_SSL_VERIFYHOST => $isProd ? 2 : false,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 20,
        CURLOPT_FOLLOWLOCATION => true,
    ];
}

// ── Verificación de webhook MercadoPago ───────────────────────────────────────
// MP envía: x-signature: ts=TIMESTAMP,v1=HMAC_SHA256
// Se verifica usando MP_WEBHOOK_SECRET del dashboard
function verifyMpWebhookSignature(string $dataId): bool {
    if (!MP_WEBHOOK_SECRET) return true; // si no hay secret configurado, se omite la verificación

    $signature = $_SERVER['HTTP_X_SIGNATURE'] ?? '';
    if (!$signature) return false;

    $ts = $v1 = '';
    foreach (explode(',', $signature) as $part) {
        [$k, $val] = array_pad(explode('=', $part, 2), 2, '');
        if ($k === 'ts') $ts = $val;
        if ($k === 'v1') $v1 = $val;
    }

    if (!$ts || !$v1) return false;

    $manifest = "id:{$dataId};request-date:{$ts}";
    $expected  = hash_hmac('sha256', $manifest, MP_WEBHOOK_SECRET);

    return hash_equals($expected, $v1);
}
