<?php
// Mercaitech — Products API (public, no auth required)
// Returns DB products in the same JS format used by products.js

require_once __DIR__ . '/../../config/app.php';
setCorsHeaders();

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { exit; }

$db = getDB();

// ── List all active products ──────────────────────────────────────────────────
$stmt = $db->query("
    SELECT p.id, p.slug, p.titulo, p.descripcion, p.descripcion_corta,
           p.precio, p.precio_original, p.stock, p.icono, p.imagen_bg,
           p.video_url, p.badge_tipo, p.badge_etiqueta,
           p.rating, p.num_resenas, p.destacado, p.activo,
           c.slug AS categoria_slug, c.nombre AS categoria_nombre
    FROM productos p
    LEFT JOIN categorias c ON c.id = p.categoria_id
    WHERE p.activo = 1
    ORDER BY p.id ASC
");
$rows = $stmt->fetchAll();

$products = [];
foreach ($rows as $p) {
    // Main image
    $imgStmt = $db->prepare("SELECT url FROM imagenes_producto WHERE producto_id = ? AND es_principal = 1 LIMIT 1");
    $imgStmt->execute([$p['id']]);
    $mainImg = $imgStmt->fetchColumn() ?: null;

    // All images
    $allImgStmt = $db->prepare("SELECT url, alt FROM imagenes_producto WHERE producto_id = ? ORDER BY orden ASC LIMIT 6");
    $allImgStmt->execute([$p['id']]);
    $allImages = $allImgStmt->fetchAll();

    // Specs
    $specStmt = $db->prepare("SELECT clave, valor FROM especificaciones WHERE producto_id = ? ORDER BY orden ASC");
    $specStmt->execute([$p['id']]);
    $specRows = $specStmt->fetchAll();
    $specs = [];
    foreach ($specRows as $s) { $specs[$s['clave']] = $s['valor']; }

    // Badge
    $badge = null;
    if ($p['badge_tipo']) {
        $badge = ['kind' => $p['badge_tipo'], 'label' => $p['badge_etiqueta'] ?: strtoupper($p['badge_tipo'])];
    }

    // Parse video_url: may be a JSON array ["url1","url2"] or a plain string
    $rawVideo  = $p['video_url'] ?? null;
    $videoUrls = [];
    if ($rawVideo) {
        $decoded = json_decode($rawVideo, true);
        $videoUrls = is_array($decoded) ? $decoded : [$rawVideo];
    }

    $products[] = [
        'id'            => (int) $p['id'],
        'slug'          => $p['slug'] ?? '',
        'title'         => $p['titulo'],
        'category'      => $p['categoria_slug'] ?? 'tecnologia',
        'categoryLabel' => $p['categoria_nombre'] ?? 'Tecnología',
        'price'         => (float) $p['precio'],
        'oldPrice'      => $p['precio_original'] ? (float) $p['precio_original'] : null,
        'description'   => $p['descripcion'] ?: ($p['descripcion_corta'] ?: ''),
        'icon'          => $p['icono'] ?: 'sparkles',
        'bg'            => $p['imagen_bg'] ?: 'radial-gradient(ellipse at 50% 40%, rgba(0,102,255,.2), transparent 60%), linear-gradient(135deg, #0B1124, #001A47)',
        'badge'         => $badge,
        'rating'        => round((float)$p['rating'], 1),
        'reviews'       => (int) $p['num_resenas'],
        'specs'         => empty($specs) ? (object)[] : $specs,
        'image_url'     => $mainImg,
        'images'        => $allImages,
        'video_url'     => $videoUrls[0] ?? null,
        'video_urls'    => $videoUrls,
        'stock'         => (int) $p['stock'],
        'destacado'     => (bool) $p['destacado'],
        '_source'       => 'db',
    ];
}

jsonResponse(['success' => true, 'products' => $products]);
