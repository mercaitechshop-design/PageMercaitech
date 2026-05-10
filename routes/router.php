<?php
/**
 * Mercaitech — Router para PHP built-in server
 * Sirve archivos estáticos desde public/ y ejecuta API desde api/v1/
 *
 * Uso: php -S localhost:8080 routes/router.php
 */

$uri  = $_SERVER['REQUEST_URI'];
$path = parse_url($uri, PHP_URL_PATH);
$root = dirname(__DIR__);   // raíz del proyecto (un nivel arriba de routes/)

// ── Upload video (multipart) → api/v1/upload_video.php ──────────────────────
if ($path === '/api/upload_video.php') {
    $f = $root . '/api/v1/upload_video.php';
    if (file_exists($f)) { include $f; return true; }
}

// ── API: /api/*.php  →  api/v1/*.php ─────────────────────────────────────────
if (preg_match('#^/api/([a-z_\-]+)\.php$#i', $path, $m)) {
    $apiFile = $root . '/api/v1/' . $m[1] . '.php';
    if (file_exists($apiFile)) {
        include $apiFile;
        return true;
    }
    http_response_code(404);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'API endpoint not found']);
    return true;
}

// ── Archivos estáticos desde public/ ─────────────────────────────────────────
$publicFile = $root . '/public' . $path;

if ($path !== '/' && file_exists($publicFile) && !is_dir($publicFile)) {
    $ext = strtolower(pathinfo($publicFile, PATHINFO_EXTENSION));
    $mimeTypes = [
        'css'   => 'text/css; charset=UTF-8',
        'js'    => 'application/javascript; charset=UTF-8',
        'html'  => 'text/html; charset=UTF-8',
        'png'   => 'image/png',
        'jpg'   => 'image/jpeg',
        'jpeg'  => 'image/jpeg',
        'gif'   => 'image/gif',
        'svg'   => 'image/svg+xml',
        'woff'  => 'font/woff',
        'woff2' => 'font/woff2',
        'ttf'   => 'font/truetype',
        'ico'   => 'image/x-icon',
        'json'  => 'application/json',
        'txt'   => 'text/plain',
    ];
    if (isset($mimeTypes[$ext])) {
        header('Content-Type: ' . $mimeTypes[$ext]);
    }
    if ($ext === 'css' || $ext === 'js' || $ext === 'html') {
        header('Cache-Control: no-store, no-cache, must-revalidate');
        header('Pragma: no-cache');
    }
    readfile($publicFile);
    return true;
}

// ── Directorio raíz → public/index.html ──────────────────────────────────────
if ($path === '/' || is_dir($publicFile)) {
    $index = $root . '/public/index.html';
    if (file_exists($index)) {
        header('Content-Type: text/html; charset=UTF-8');
        header('Cache-Control: no-store, no-cache, must-revalidate');
        header('Pragma: no-cache');
        readfile($index);
        return true;
    }
}

// ── 404 ──────────────────────────────────────────────────────────────────────
http_response_code(404);
header('Content-Type: text/html; charset=UTF-8');
echo '<h1>404 — Not Found</h1><p><a href="/">Volver al inicio</a></p>';
return true;
