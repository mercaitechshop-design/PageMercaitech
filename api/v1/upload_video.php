<?php
// Mercaitech — Video upload endpoint (multipart/form-data)
// POST /api/upload_video.php

declare(strict_types=1);
require_once __DIR__ . '/../../config/app.php';
setCorsHeaders();

session_start();
if (empty($_SESSION['user_id'])) {
    jsonResponse(['success' => false, 'message' => 'No autenticado.'], 401);
}
$db = getDB();
$roleStmt = $db->prepare("SELECT rol FROM usuarios WHERE id = ? AND activo = 1 LIMIT 1");
$roleStmt->execute([$_SESSION['user_id']]);
$roleRow = $roleStmt->fetch();
if (!$roleRow || $roleRow['rol'] !== 'admin') {
    jsonResponse(['success' => false, 'message' => 'Acceso denegado.'], 403);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Method not allowed.'], 405);
}

if (empty($_FILES['video'])) {
    jsonResponse(['success' => false, 'message' => 'No se recibió ningún video.'], 422);
}

$file    = $_FILES['video'];
$maxSize = 100 * 1024 * 1024; // 100MB

if ($file['error'] !== UPLOAD_ERR_OK) {
    $errMsg = match ($file['error']) {
        UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'El video supera el tamaño máximo permitido. Reinicia el servidor con: php -c php.ini -S localhost:8080 routes/router.php',
        UPLOAD_ERR_PARTIAL  => 'La subida se interrumpió. Inténtalo de nuevo.',
        UPLOAD_ERR_NO_FILE  => 'No se recibió ningún archivo.',
        UPLOAD_ERR_NO_TMP_DIR => 'Error interno: carpeta temporal no disponible.',
        UPLOAD_ERR_CANT_WRITE => 'Error interno: no se pudo escribir el archivo.',
        default => 'Error desconocido al subir el archivo (código ' . $file['error'] . ').',
    };
    jsonResponse(['success' => false, 'message' => $errMsg], 500);
}
if ($file['size'] > $maxSize) {
    jsonResponse(['success' => false, 'message' => 'El video supera el límite de 100MB.'], 413);
}

$allowed = ['video/mp4', 'video/webm', 'video/ogg', 'video/quicktime'];
$mime    = mime_content_type($file['tmp_name']);
if (!in_array($mime, $allowed)) {
    jsonResponse(['success' => false, 'message' => 'Formato no permitido. Usa MP4, WebM u OGG.'], 415);
}

$ext      = pathinfo($file['name'], PATHINFO_EXTENSION) ?: 'mp4';
$filename = 'vid_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . strtolower($ext);
$dir      = dirname(__DIR__, 2) . '/public/uploads/products/';

if (!is_dir($dir)) mkdir($dir, 0755, true);

if (!move_uploaded_file($file['tmp_name'], $dir . $filename)) {
    jsonResponse(['success' => false, 'message' => 'No se pudo guardar el archivo.'], 500);
}

jsonResponse(['success' => true, 'url' => '/uploads/products/' . $filename]);
