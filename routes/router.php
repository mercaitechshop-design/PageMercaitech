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

    // ── Cache strategy por tipo de recurso ─────────────────────────────────────
    // HTML: no-cache (revalida con ETag, no re-descarga si no cambió)
    // CSS/JS versionados (?v=N): 1 año inmutable en cache del browser
    // Imágenes/fuentes: 7 días (raramente cambian)
    $hasVersion = str_contains($uri, '?v=') || str_contains($uri, '?t=');
    if ($ext === 'html') {
        header('Cache-Control: no-cache, must-revalidate');
        header('Vary: Accept-Encoding');
        // ETag basado en mtime del archivo para revalidación condicional
        $mtime = filemtime($publicFile);
        $etag  = '"' . dechex($mtime) . '-' . dechex(filesize($publicFile)) . '"';
        header('ETag: ' . $etag);
        header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $mtime) . ' GMT');
        if (isset($_SERVER['HTTP_IF_NONE_MATCH']) && trim($_SERVER['HTTP_IF_NONE_MATCH']) === $etag) {
            http_response_code(304); exit;
        }
    } elseif (in_array($ext, ['css', 'js']) && $hasVersion) {
        // Versionados → cache permanente (1 año). El ?v= garantiza cache busting.
        header('Cache-Control: public, max-age=31536000, immutable');
    } elseif (in_array($ext, ['css', 'js'])) {
        header('Cache-Control: public, max-age=3600'); // 1 hora para no versionados
    } elseif (in_array($ext, ['woff', 'woff2', 'ttf'])) {
        header('Cache-Control: public, max-age=31536000, immutable');
    } elseif (in_array($ext, ['png', 'jpg', 'jpeg', 'gif', 'webp', 'svg', 'ico'])) {
        header('Cache-Control: public, max-age=604800'); // 7 días
    }

    // Security headers
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('X-XSS-Protection: 1; mode=block');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
    if ($ext === 'html') {
        // connect-src incluye el origen actual para que fetch() funcione en cualquier dominio
        $selfOrigin = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
        header("Content-Security-Policy: " .
            "default-src 'self'; " .
            "script-src 'self' 'unsafe-inline' https://accounts.google.com https://apis.google.com; " .
            "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; " .
            "font-src 'self' https://fonts.gstatic.com; " .
            "img-src 'self' data: https: blob:; " .
            "connect-src 'self' {$selfOrigin} https://api.mercadopago.com https://www.googleapis.com; " .
            "frame-src https://accounts.google.com https://sandbox.mercadopago.com.co https://mercadopago.com.co; " .
            "object-src 'none'; base-uri 'self';"
        );
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
