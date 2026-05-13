<?php
// Migración: convierte imágenes base64 de imagenes_producto a archivos en disco
// Ejecutar UNA sola vez: php database/migrations/migrate_images_to_files.php

declare(strict_types=1);
require_once __DIR__ . '/../../config/app.php';

$uploadDir    = __DIR__ . '/../../public/uploads/products/';
$uploadUrlBase = '/uploads/products/';

if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0775, true);
}

$db = getDB();

// Obtener todas las imágenes base64
$stmt = $db->query("SELECT id, producto_id, url, alt FROM imagenes_producto WHERE url LIKE 'data:image/%'");
$images = $stmt->fetchAll();

if (empty($images)) {
    echo "✅ No hay imágenes base64 por migrar.\n";
    exit(0);
}

echo "🔄 Migrando " . count($images) . " imágenes base64 a archivos...\n\n";

$ok = 0; $fail = 0;

foreach ($images as $img) {
    // Extraer tipo y datos del base64
    if (!preg_match('/^data:image\/(\w+);base64,(.+)$/s', $img['url'], $m)) {
        echo "  ⚠️  ID {$img['id']}: formato inválido, se omite\n";
        $fail++;
        continue;
    }

    $ext      = strtolower($m[1] === 'jpeg' ? 'jpg' : $m[1]);
    $data     = base64_decode($m[2], true);
    if ($data === false) {
        echo "  ⚠️  ID {$img['id']}: base64 inválido, se omite\n";
        $fail++;
        continue;
    }

    $filename = "product-{$img['producto_id']}-img-{$img['id']}.{$ext}";
    $filepath = $uploadDir . $filename;
    $fileUrl  = $uploadUrlBase . $filename;

    if (file_put_contents($filepath, $data) === false) {
        echo "  ❌ ID {$img['id']}: no se pudo guardar el archivo\n";
        $fail++;
        continue;
    }

    $db->prepare("UPDATE imagenes_producto SET url = ? WHERE id = ?")
       ->execute([$fileUrl, $img['id']]);

    $kb = round(strlen($data) / 1024);
    echo "  ✅ ID {$img['id']} (producto {$img['producto_id']}): {$filename} ({$kb} KB)\n";
    $ok++;
}

// Borrar cache para que se regenere con las nuevas URLs
@unlink(__DIR__ . '/../../storage/cache/products.json');

echo "\n📊 Resultado: {$ok} migradas, {$fail} fallidas\n";
echo "🗑️  Cache de productos eliminado — se regenerará con las nuevas URLs\n";
