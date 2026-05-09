<?php
// Mercaitech — Products API
// GET  /api/products.php              → list products (with optional filters)
// GET  /api/products.php?id=1         → single product
// GET  /api/products.php?cat=gaming   → by category slug

require_once __DIR__ . '/../../config/app.php';
setCorsHeaders();
header('Content-Type: application/json; charset=utf-8');

$method = $_SERVER['REQUEST_METHOD'];

if ($method !== 'GET') {
    jsonResponse(['success' => false, 'error' => 'Method not allowed.'], 405);
}

$db = getDB();

// Single product
if (isset($_GET['id'])) {
    $id = (int) $_GET['id'];
    $stmt = $db->prepare("
        SELECT p.*, c.nombre AS categoria_nombre, c.slug AS categoria_slug
        FROM productos p
        JOIN categorias c ON p.categoria_id = c.id
        WHERE p.id = ? AND p.activo = 1
        LIMIT 1
    ");
    $stmt->execute([$id]);
    $product = $stmt->fetch();

    if (!$product) {
        jsonResponse(['success' => false, 'error' => 'Producto no encontrado.'], 404);
    }

    // Get specs
    $specs = $db->prepare("SELECT clave, valor FROM especificaciones WHERE producto_id = ? ORDER BY orden");
    $specs->execute([$id]);
    $product['specs'] = $specs->fetchAll();

    // Get images
    $images = $db->prepare("SELECT url, alt, es_principal FROM imagenes_producto WHERE producto_id = ? ORDER BY orden");
    $images->execute([$id]);
    $product['imagenes'] = $images->fetchAll();

    jsonResponse(['success' => true, 'data' => $product]);
}

// Product list with filters
$where = ['p.activo = 1'];
$params = [];

if (!empty($_GET['cat'])) {
    $where[] = 'c.slug = ?';
    $params[] = sanitize($_GET['cat']);
}

if (!empty($_GET['q'])) {
    $where[] = '(p.titulo LIKE ? OR p.descripcion LIKE ?)';
    $q = '%' . sanitize($_GET['q']) . '%';
    $params[] = $q;
    $params[] = $q;
}

if (isset($_GET['precio_min'])) {
    $where[] = 'p.precio >= ?';
    $params[] = (float) $_GET['precio_min'];
}

if (isset($_GET['precio_max'])) {
    $where[] = 'p.precio <= ?';
    $params[] = (float) $_GET['precio_max'];
}

$order = 'p.destacado DESC, p.creado_en DESC';
if (!empty($_GET['orden'])) {
    $ordenMap = [
        'precio_asc'  => 'p.precio ASC',
        'precio_desc' => 'p.precio DESC',
        'rating'      => 'p.rating DESC',
        'nuevo'       => 'p.creado_en DESC',
    ];
    if (isset($ordenMap[$_GET['orden']])) {
        $order = $ordenMap[$_GET['orden']];
    }
}

$limit  = min((int) ($_GET['limit']  ?? 20), 100);
$offset = max((int) ($_GET['offset'] ?? 0), 0);

$sql = "
    SELECT p.id, p.titulo, p.slug, p.precio, p.precio_original, p.descuento,
           p.stock, p.rating, p.num_resenas, p.badge_tipo, p.badge_etiqueta,
           p.imagen_bg, p.icono, p.descripcion_corta,
           c.nombre AS categoria_nombre, c.slug AS categoria_slug
    FROM productos p
    JOIN categorias c ON p.categoria_id = c.id
    WHERE " . implode(' AND ', $where) . "
    ORDER BY $order
    LIMIT $limit OFFSET $offset
";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();

// Total count
$countSql = "
    SELECT COUNT(*) FROM productos p
    JOIN categorias c ON p.categoria_id = c.id
    WHERE " . implode(' AND ', $where);
$countStmt = $db->prepare($countSql);
$countStmt->execute($params);
$total = (int) $countStmt->fetchColumn();

jsonResponse([
    'success' => true,
    'data'    => $products,
    'meta'    => ['total' => $total, 'limit' => $limit, 'offset' => $offset]
]);
