<?php
// Mercaitech — Categories API
// GET /api/categories.php → list all active categories with product count

require_once __DIR__ . '/../../config/app.php';
setCorsHeaders();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonResponse(['success' => false, 'error' => 'Method not allowed.'], 405);
}

$db = getDB();
$stmt = $db->query("
    SELECT c.id, c.nombre, c.slug, c.icono, c.descripcion,
           COUNT(p.id) AS num_productos
    FROM categorias c
    LEFT JOIN productos p ON p.categoria_id = c.id AND p.activo = 1
    WHERE c.activo = 1
    GROUP BY c.id
    ORDER BY c.orden ASC
");
$categories = $stmt->fetchAll();

jsonResponse(['success' => true, 'data' => $categories]);
