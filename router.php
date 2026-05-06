<?php
/**
 * Mercaitech — Router para PHP built-in server
 * Permite servir tanto archivos estáticos como PHP correctamente.
 */

$uri = $_SERVER['REQUEST_URI'];

// Quitar query string para comprobar el archivo
$path = parse_url($uri, PHP_URL_PATH);
$file = __DIR__ . $path;

// Si es un archivo que existe (CSS, JS, imágenes, fuentes, HTML), servir directo
if ($path !== '/' && file_exists($file) && !is_dir($file)) {
    // Definir tipos MIME correctos
    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    $mimeTypes = [
        'css'   => 'text/css',
        'js'    => 'application/javascript',
        'html'  => 'text/html',
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
        'xml'   => 'application/xml',
        'pdf'   => 'application/pdf',
        'txt'   => 'text/plain',
    ];
    if (isset($mimeTypes[$ext])) {
        header('Content-Type: ' . $mimeTypes[$ext]);
    }
    if ($ext === 'php') {
        // Ejecutar el archivo PHP en el contexto actual
        include $file;
        return true;
    }
    readfile($file);
    return true;
}

// Si es un directorio o la raíz, servir index.html
if (is_dir($file)) {
    if (file_exists($file . '/index.html')) {
        header('Content-Type: text/html');
        readfile($file . '/index.html');
        return true;
    }
}

// Para archivos PHP, dejar que el built-in server los maneje
return false;
