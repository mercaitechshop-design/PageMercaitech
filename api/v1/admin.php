<?php
// Mercaitech — Admin API (products CRUD)
// Requires: rol = 'admin' in session

declare(strict_types=1);
require_once __DIR__ . '/../../config/app.php';
setCorsHeaders();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'error' => 'Method not allowed.'], 405);
}

// ── Auth guard ────────────────────────────────────────────────────────────────
session_start();
if (empty($_SESSION['user_id'])) {
    jsonResponse(['success' => false, 'message' => 'No autenticado.'], 401);
}
$db = getDB();
$adminStmt = $db->prepare("SELECT rol FROM usuarios WHERE id = ? AND activo = 1 LIMIT 1");
$adminStmt->execute([$_SESSION['user_id']]);
$adminUser = $adminStmt->fetch();
if (!$adminUser || $adminUser['rol'] !== 'admin') {
    jsonResponse(['success' => false, 'message' => 'Acceso denegado.'], 403);
}

$body   = getJsonBody();
$action = sanitize($body['action'] ?? $_GET['action'] ?? '');

match ($action) {
    'list_products'    => listProducts(),
    'get_product'      => getProduct($body),
    'save_product'     => saveProduct($body),
    'delete_product'   => deleteProduct($body),
    'toggle_product'   => toggleProduct($body),
    'list_categories'  => listCategories(),
    default            => jsonResponse(['success' => false, 'message' => 'Acción desconocida.'], 400),
};

// ── LIST PRODUCTS ─────────────────────────────────────────────────────────────
function listProducts(): void {
    $db = getDB();
    $stmt = $db->query("
        SELECT p.id, p.titulo, p.descripcion_corta, p.precio, p.precio_original,
               p.stock, p.activo, p.icono, p.imagen_bg, p.video_url,
               p.badge_tipo, p.badge_etiqueta, p.rating, p.num_resenas,
               p.destacado, p.creado_en,
               c.nombre AS categoria_nombre, c.slug AS categoria_slug,
               (SELECT url FROM imagenes_producto WHERE producto_id = p.id AND es_principal = 1 LIMIT 1) AS imagen_principal
        FROM productos p
        LEFT JOIN categorias c ON c.id = p.categoria_id
        ORDER BY p.id DESC
    ");
    $products = $stmt->fetchAll();
    jsonResponse(['success' => true, 'products' => $products]);
}

// ── GET SINGLE PRODUCT ────────────────────────────────────────────────────────
function getProduct(array $body): void {
    $id = (int)($body['id'] ?? 0);
    if (!$id) jsonResponse(['success' => false, 'message' => 'ID inválido.'], 422);

    $db = getDB();

    $stmt = $db->prepare("
        SELECT p.*, c.slug AS categoria_slug, c.nombre AS categoria_nombre
        FROM productos p
        LEFT JOIN categorias c ON c.id = p.categoria_id
        WHERE p.id = ? LIMIT 1
    ");
    $stmt->execute([$id]);
    $p = $stmt->fetch();
    if (!$p) jsonResponse(['success' => false, 'message' => 'Producto no encontrado.'], 404);

    // Imagenes
    $imgStmt = $db->prepare("SELECT id, url, alt, es_principal, orden FROM imagenes_producto WHERE producto_id = ? ORDER BY orden ASC");
    $imgStmt->execute([$id]);
    $p['imagenes'] = $imgStmt->fetchAll();

    // Specs
    $specStmt = $db->prepare("SELECT clave, valor FROM especificaciones WHERE producto_id = ? ORDER BY orden ASC");
    $specStmt->execute([$id]);
    $p['specs'] = $specStmt->fetchAll();

    // Parse video_url into video_urls array for the admin panel
    $rawVideo = $p['video_url'] ?? null;
    if ($rawVideo) {
        $decoded = json_decode($rawVideo, true);
        $p['video_urls'] = is_array($decoded) ? $decoded : [$rawVideo];
    } else {
        $p['video_urls'] = [];
    }

    jsonResponse(['success' => true, 'product' => $p]);
}

// ── SAVE PRODUCT (create / update) ───────────────────────────────────────────
function saveProduct(array $body): void {
    $db = getDB();
    $id = (int)($body['id'] ?? 0);

    $titulo    = sanitize($body['titulo'] ?? '');
    $desc      = $body['descripcion']        ?? '';
    $descCorta = sanitize($body['descripcion_corta'] ?? '');
    $catSlug   = sanitize($body['categoria_slug']     ?? 'tecnologia');
    $precio    = (float)($body['precio']    ?? 0);
    $precioOri = $body['precio_original'] !== null && $body['precio_original'] !== '' ? (float)$body['precio_original'] : null;
    $stock     = (int)($body['stock']       ?? 0);
    $activo    = (int)($body['activo']      ?? 1);
    $destacado = (int)($body['destacado']   ?? 0);
    $icono     = sanitize($body['icono']    ?? 'sparkles');
    $bg        = $body['imagen_bg']          ?? 'radial-gradient(ellipse at 50% 40%, rgba(0,102,255,.2), transparent 60%), linear-gradient(135deg, #0B1124, #001A47)';
    // video_urls is an array of uploaded paths; store as JSON (or plain string for 1 item)
    $rawVideos = $body['video_urls'] ?? null;
    if (is_array($rawVideos)) {
        $rawVideos = array_values(array_filter($rawVideos));
        $videoUrl  = count($rawVideos) === 0 ? null
            : (count($rawVideos) === 1 ? $rawVideos[0] : json_encode($rawVideos));
    } else {
        $videoUrl = sanitize($body['video_url'] ?? '') ?: null;
    }
    $badgeTipo = $body['badge_tipo'] ?: null;
    $badgeText = sanitize($body['badge_etiqueta'] ?? '');
    $rating    = (float)($body['rating']     ?? 5.0);
    $resenas   = (int)($body['num_resenas']  ?? 0);
    $imagenes  = $body['imagenes']            ?? [];
    $specs     = $body['specs']               ?? [];

    if (!$titulo) jsonResponse(['success' => false, 'message' => 'El título es obligatorio.'], 422);
    if ($precio <= 0) jsonResponse(['success' => false, 'message' => 'El precio debe ser mayor a 0.'], 422);

    // Get category id
    $catStmt = $db->prepare("SELECT id FROM categorias WHERE slug = ? LIMIT 1");
    $catStmt->execute([$catSlug]);
    $cat = $catStmt->fetch();
    if (!$cat) jsonResponse(['success' => false, 'message' => 'Categoría inválida.'], 422);
    $catId = $cat['id'];

    $descuento = ($precioOri && $precioOri > $precio)
        ? (int)round((($precioOri - $precio) / $precioOri) * 100) : 0;

    if ($id) {
        // UPDATE
        $db->prepare("
            UPDATE productos SET
                titulo = ?, descripcion = ?, descripcion_corta = ?, categoria_id = ?,
                precio = ?, precio_original = ?, descuento = ?, stock = ?, activo = ?,
                destacado = ?, icono = ?, imagen_bg = ?, video_url = ?,
                badge_tipo = ?, badge_etiqueta = ?, rating = ?, num_resenas = ?,
                actualizado_en = NOW()
            WHERE id = ?
        ")->execute([$titulo, $desc, $descCorta, $catId, $precio, $precioOri, $descuento,
                     $stock, $activo, $destacado, $icono, $bg, $videoUrl,
                     $badgeTipo, $badgeText ?: null, $rating, $resenas, $id]);

        // Clear images and specs and re-insert
        $db->prepare("DELETE FROM imagenes_producto WHERE producto_id = ?")->execute([$id]);
        $db->prepare("DELETE FROM especificaciones WHERE producto_id = ?")->execute([$id]);
    } else {
        // INSERT — generate slug
        $slug = preg_replace('/[^a-z0-9]+/', '-', strtolower($titulo));
        $slug = trim($slug, '-');
        // Ensure unique slug
        $existing = $db->prepare("SELECT id FROM productos WHERE slug = ? LIMIT 1");
        $existing->execute([$slug]);
        if ($existing->fetch()) $slug .= '-' . time();

        $db->prepare("
            INSERT INTO productos
                (titulo, slug, descripcion, descripcion_corta, categoria_id, precio, precio_original,
                 descuento, stock, activo, destacado, icono, imagen_bg, video_url,
                 badge_tipo, badge_etiqueta, rating, num_resenas)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ")->execute([$titulo, $slug, $desc, $descCorta, $catId, $precio, $precioOri, $descuento,
                     $stock, $activo, $destacado, $icono, $bg, $videoUrl,
                     $badgeTipo, $badgeText ?: null, $rating, $resenas]);
        $id = (int)$db->lastInsertId();
    }

    // Insert images
    foreach ($imagenes as $i => $img) {
        if (empty($img['url'])) continue;
        $isPrincipal = ($i === 0) ? 1 : 0;
        $alt = sanitize($img['alt'] ?? $titulo);
        $db->prepare("INSERT INTO imagenes_producto (producto_id, url, alt, es_principal, orden) VALUES (?, ?, ?, ?, ?)")
           ->execute([$id, $img['url'], $alt, $isPrincipal, $i]);
    }

    // Insert specs
    foreach ($specs as $i => $spec) {
        if (empty($spec['clave'])) continue;
        $db->prepare("INSERT INTO especificaciones (producto_id, clave, valor, orden) VALUES (?, ?, ?, ?)")
           ->execute([$id, sanitize($spec['clave']), sanitize($spec['valor'] ?? ''), $i]);
    }

    jsonResponse(['success' => true, 'id' => $id,
                  'message' => $body['id'] ? 'Producto actualizado.' : 'Producto creado.']);
}

// ── DELETE PRODUCT ────────────────────────────────────────────────────────────
function deleteProduct(array $body): void {
    $id = (int)($body['id'] ?? 0);
    if (!$id) jsonResponse(['success' => false, 'message' => 'ID inválido.'], 422);
    $db = getDB();
    $db->prepare("DELETE FROM especificaciones WHERE producto_id = ?")->execute([$id]);
    $db->prepare("DELETE FROM imagenes_producto WHERE producto_id = ?")->execute([$id]);
    $db->prepare("DELETE FROM productos WHERE id = ?")->execute([$id]);
    jsonResponse(['success' => true, 'message' => 'Producto eliminado.']);
}

// ── TOGGLE ACTIVE ─────────────────────────────────────────────────────────────
function toggleProduct(array $body): void {
    $id     = (int)($body['id'] ?? 0);
    $activo = (int)($body['activo'] ?? 0);
    if (!$id) jsonResponse(['success' => false, 'message' => 'ID inválido.'], 422);
    $db = getDB();
    $db->prepare("UPDATE productos SET activo = ? WHERE id = ?")->execute([$activo, $id]);
    jsonResponse(['success' => true, 'message' => $activo ? 'Producto activado.' : 'Producto desactivado.']);
}

// ── LIST CATEGORIES ───────────────────────────────────────────────────────────
function listCategories(): void {
    $db   = getDB();
    $stmt = $db->query("SELECT id, nombre, slug, icono FROM categorias WHERE activo = 1 ORDER BY orden ASC");
    jsonResponse(['success' => true, 'categories' => $stmt->fetchAll()]);
}
