<?php
// Mercaitech — Orders API
// POST /api/orders.php   → create order
// GET  /api/orders.php?id=XXX → get order status (with token)

require_once __DIR__ . '/../../config/app.php';
setCorsHeaders();

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $orderId = sanitize($_GET['id'] ?? '');
    $token   = sanitize($_GET['token'] ?? '');

    if (!$orderId || !$token) {
        jsonResponse(['success' => false, 'error' => 'Parámetros requeridos: id, token.'], 422);
    }

    $db = getDB();
    $stmt = $db->prepare("
        SELECT o.*, u.nombre AS cliente_nombre, u.email AS cliente_email
        FROM ordenes o
        LEFT JOIN usuarios u ON o.usuario_id = u.id
        WHERE o.numero_orden = ? AND o.token_seguimiento = ?
        LIMIT 1
    ");
    $stmt->execute([$orderId, $token]);
    $order = $stmt->fetch();

    if (!$order) {
        jsonResponse(['success' => false, 'error' => 'Orden no encontrada.'], 404);
    }

    // Get order items
    $items = $db->prepare("
        SELECT oi.*, p.titulo, p.imagen_bg, p.icono
        FROM orden_items oi
        JOIN productos p ON oi.producto_id = p.id
        WHERE oi.orden_id = ?
    ");
    $items->execute([$order['id']]);
    $order['items'] = $items->fetchAll();

    jsonResponse(['success' => true, 'data' => $order]);
}

if ($method === 'POST') {
    $body = getJsonBody();

    // Validate required fields
    $required = ['items', 'cliente', 'envio'];
    foreach ($required as $field) {
        if (empty($body[$field])) {
            jsonResponse(['success' => false, 'error' => "Campo requerido: $field."], 422);
        }
    }

    $items   = $body['items'];
    $cliente = $body['cliente'];
    $envio   = $body['envio'];

    if (!is_array($items) || count($items) === 0) {
        jsonResponse(['success' => false, 'error' => 'El carrito está vacío.'], 422);
    }

    $db = getDB();

    // Validate & price products from DB
    $productIds = array_map(fn($i) => (int)$i['id'], $items);
    $placeholders = implode(',', array_fill(0, count($productIds), '?'));
    $stmt = $db->prepare("SELECT id, precio, stock FROM productos WHERE id IN ($placeholders) AND activo = 1");
    $stmt->execute($productIds);
    $dbProducts = [];
    foreach ($stmt->fetchAll() as $p) {
        $dbProducts[$p['id']] = $p;
    }

    $subtotal = 0;
    $lineItems = [];
    foreach ($items as $item) {
        $id  = (int) $item['id'];
        $qty = max(1, (int) ($item['qty'] ?? 1));
        if (!isset($dbProducts[$id])) {
            jsonResponse(['success' => false, 'error' => "Producto no disponible: $id."], 422);
        }
        if ($dbProducts[$id]['stock'] < $qty) {
            jsonResponse(['success' => false, 'error' => "Stock insuficiente para el producto: $id."], 422);
        }
        $unitPrice = (float) $dbProducts[$id]['precio'];
        $subtotal += $unitPrice * $qty;
        $lineItems[] = ['id' => $id, 'qty' => $qty, 'precio' => $unitPrice];
    }

    $envioGratis  = $subtotal >= 50;
    $costoEnvio   = $envioGratis ? 0 : 9.99;
    $total        = $subtotal + $costoEnvio;
    $numeroOrden  = 'MT-' . strtoupper(bin2hex(random_bytes(4)));
    $tokenSeguim  = bin2hex(random_bytes(16));

    $db->beginTransaction();
    try {
        // Create order
        $stmt = $db->prepare("
            INSERT INTO ordenes
              (numero_orden, token_seguimiento, usuario_id, nombre_cliente, email_cliente,
               telefono_cliente, direccion_envio, ciudad, pais, subtotal, costo_envio, total,
               estado, metodo_pago, creado_en)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,'pendiente','card',NOW())
        ");
        $stmt->execute([
            $numeroOrden, $tokenSeguim,
            $body['usuario_id'] ?? null,
            sanitize($cliente['nombre'] ?? ''),
            sanitize($cliente['email'] ?? ''),
            sanitize($cliente['telefono'] ?? ''),
            sanitize($envio['direccion'] ?? ''),
            sanitize($envio['ciudad'] ?? ''),
            sanitize($envio['pais'] ?? 'Colombia'),
            $subtotal, $costoEnvio, $total
        ]);
        $orderId = (int) $db->lastInsertId();

        // Insert items & update stock
        $itemStmt = $db->prepare("
            INSERT INTO orden_items (orden_id, producto_id, cantidad, precio_unitario, subtotal)
            VALUES (?,?,?,?,?)
        ");
        $stockStmt = $db->prepare("UPDATE productos SET stock = stock - ? WHERE id = ?");

        foreach ($lineItems as $li) {
            $itemStmt->execute([$orderId, $li['id'], $li['qty'], $li['precio'], $li['precio'] * $li['qty']]);
            $stockStmt->execute([$li['qty'], $li['id']]);
        }

        $db->commit();

        jsonResponse([
            'success'         => true,
            'numero_orden'    => $numeroOrden,
            'token_seguimiento' => $tokenSeguim,
            'total'           => $total,
            'message'         => '¡Orden creada exitosamente!'
        ], 201);

    } catch (Exception $e) {
        $db->rollBack();
        jsonResponse(['success' => false, 'error' => 'Error al procesar la orden.'], 500);
    }
}

jsonResponse(['success' => false, 'error' => 'Method not allowed.'], 405);
