<?php
// Mercaitech — Products API (public, no auth required)
// Optimizaciones de rendimiento:
//   1. Cache de archivo (60s) — evita consultas repetidas a la BD
//   2. Consultas en bulk — resuelve el problema N+1 (1 query por tabla, no por producto)
//   3. HTTP Cache-Control — el browser cachea la respuesta

require_once __DIR__ . '/../../config/app.php';
setCorsHeaders();

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { exit; }

$cacheFile = __DIR__ . '/../../storage/cache/products.json';
$cacheTTL  = 60; // segundos — ajustar según frecuencia de cambios en productos

// Helper: enviar JSON con gzip si el cliente lo soporta
function sendJson(string $json, string $cacheHit): void {
    $etag = '"' . substr(md5($json), 0, 16) . '"';
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: public, max-age=55');
    header('X-Cache: ' . $cacheHit);
    header('ETag: ' . $etag);
    // 304 Not Modified si el browser ya tiene esta versión
    if (isset($_SERVER['HTTP_IF_NONE_MATCH']) && trim($_SERVER['HTTP_IF_NONE_MATCH']) === $etag) {
        http_response_code(304); exit;
    }
    // Gzip: reduce 13MB → ~500KB en transferencia
    if (str_contains($_SERVER['HTTP_ACCEPT_ENCODING'] ?? '', 'gzip')) {
        $gz = gzencode($json, 6);
        if ($gz !== false) {
            header('Content-Encoding: gzip');
            header('Content-Length: ' . strlen($gz));
            echo $gz; exit;
        }
    }
    echo $json;
}

// ── Servir desde cache si está fresco ────────────────────────────────────────
if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < $cacheTTL) {
    $cached = file_get_contents($cacheFile);
    if ($cached) {
        sendJson($cached, 'HIT');
        exit;
    }
}

// ── Query principal ───────────────────────────────────────────────────────────
$db   = getDB();
$stmt = $db->query("
    SELECT p.id, p.slug, p.titulo, p.descripcion, p.descripcion_corta,
           p.precio, p.precio_original, p.stock, p.icono, p.imagen_bg,
           p.video_url, p.badge_tipo, p.badge_etiqueta,
           p.rating, p.num_resenas, p.destacado,
           c.slug AS categoria_slug, c.nombre AS categoria_nombre
    FROM productos p
    LEFT JOIN categorias c ON c.id = p.categoria_id
    WHERE p.activo = 1
    ORDER BY p.id ASC
");
$rows = $stmt->fetchAll();

if (!$rows) {
    $out = json_encode(['success' => true, 'products' => []]);
    file_put_contents($cacheFile, $out);
    header('Content-Type: application/json; charset=utf-8');
    echo $out; exit;
}

$ids = array_column($rows, 'id');
$ph  = implode(',', array_fill(0, count($ids), '?'));

// ── Imágenes en una sola query (bulk) — elimina N+1 ──────────────────────────
$imgStmt = $db->prepare("
    SELECT producto_id, url, alt, es_principal
    FROM imagenes_producto
    WHERE producto_id IN ($ph)
    ORDER BY orden ASC
");
$imgStmt->execute($ids);
$allImgRows = $imgStmt->fetchAll();

$imagesByProduct = [];
$mainImgByProduct = [];
foreach ($allImgRows as $img) {
    $pid = $img['producto_id'];
    $imagesByProduct[$pid][] = ['url' => $img['url'], 'alt' => $img['alt']];
    if ($img['es_principal'] && !isset($mainImgByProduct[$pid])) {
        $mainImgByProduct[$pid] = $img['url'];
    }
}
// Fallback: primer imagen si ninguna marcada como principal
foreach ($imagesByProduct as $pid => $imgs) {
    if (!isset($mainImgByProduct[$pid])) {
        $mainImgByProduct[$pid] = $imgs[0]['url'];
    }
}

// ── Especificaciones en una sola query (bulk) — elimina N+1 ──────────────────
$specStmt = $db->prepare("
    SELECT producto_id, clave, valor
    FROM especificaciones
    WHERE producto_id IN ($ph)
    ORDER BY orden ASC
");
$specStmt->execute($ids);
$specsByProduct = [];
foreach ($specStmt->fetchAll() as $s) {
    $specsByProduct[$s['producto_id']][$s['clave']] = $s['valor'];
}

// ── Construir respuesta ───────────────────────────────────────────────────────
$products = [];
foreach ($rows as $p) {
    $pid = (int)$p['id'];

    $badge = null;
    if ($p['badge_tipo']) {
        $badge = ['kind' => $p['badge_tipo'], 'label' => $p['badge_etiqueta'] ?: strtoupper($p['badge_tipo'])];
    }

    $rawVideo  = $p['video_url'] ?? null;
    $videoUrls = [];
    if ($rawVideo) {
        $decoded   = json_decode($rawVideo, true);
        $videoUrls = is_array($decoded) ? $decoded : [$rawVideo];
    }

    $specs  = $specsByProduct[$pid] ?? [];
    $images = $imagesByProduct[$pid]  ?? [];

    $products[] = [
        'id'            => $pid,
        'slug'          => $p['slug'] ?? '',
        'title'         => $p['titulo'],
        'category'      => $p['categoria_slug'] ?? 'tecnologia',
        'categoryLabel' => $p['categoria_nombre'] ?? 'Tecnología',
        'price'         => (float) $p['precio'],
        'oldPrice'      => $p['precio_original'] ? (float) $p['precio_original'] : null,
        'description'   => $p['descripcion'] ?: ($p['descripcion_corta'] ?: ''),
        'icon'          => $p['icono'] ?: 'sparkles',
        'bg'            => $p['imagen_bg'] ?: 'linear-gradient(135deg,#0B1124,#001A47)',
        'badge'         => $badge,
        'rating'        => round((float)$p['rating'], 1),
        'reviews'       => (int) $p['num_resenas'],
        'specs'         => empty($specs) ? (object)[] : $specs,
        'image_url'     => $mainImgByProduct[$pid] ?? null,
        'images'        => $images,
        'video_url'     => $videoUrls[0] ?? null,
        'video_urls'    => $videoUrls,
        'stock'         => (int) $p['stock'],
        'destacado'     => (bool) $p['destacado'],
        '_source'       => 'db',
    ];
}

$out = json_encode(['success' => true, 'products' => $products], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

// Guardar en cache
@file_put_contents($cacheFile, $out);

sendJson($out, 'MISS');
