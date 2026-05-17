<?php
// Mercaitech — Orders API
// GET  /api/orders.php?usuario=email  → list orders for a user (pedidos.html)
// GET  /api/orders.php?id=XX&token=XX → single order status
// POST /api/orders.php                → create order + send confirmation email

declare(strict_types=1);
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../vendor/autoload.php';

setCorsHeaders();

ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_samesite', 'Strict');
session_start();

$method = $_SERVER['REQUEST_METHOD'];

// ── GET ────────────────────────────────────────────────────────────────────────
if ($method === 'GET') {

    // Admin: listar TODOS los pedidos
    if (isset($_GET['all'])) {
        if (empty($_SESSION['user_id'])) {
            jsonResponse(['success' => false, 'error' => 'No autenticado.'], 401);
        }
        $db = getDB();
        $roleStmt = $db->prepare("SELECT rol FROM usuarios WHERE id = ? AND activo = 1 LIMIT 1");
        $roleStmt->execute([$_SESSION['user_id']]);
        $me = $roleStmt->fetch();
        if (!$me || $me['rol'] !== 'admin') {
            jsonResponse(['success' => false, 'error' => 'No autorizado.'], 403);
        }

        $page    = max(1, (int)($_GET['page'] ?? 1));
        $perPage = min(500, max(10, (int)($_GET['per_page'] ?? 200)));
        $offset  = ($page - 1) * $perPage;

        $total   = (int)$db->query("SELECT COUNT(*) FROM ordenes")->fetchColumn();

        $stmt = $db->prepare("
            SELECT id, numero_orden, nombre_cliente, email_cliente, telefono_cliente,
                   subtotal, total, estado, metodo_pago,
                   direccion_envio, ciudad, pais, creado_en
            FROM ordenes
            ORDER BY creado_en DESC
            LIMIT ? OFFSET ?
        ");
        $stmt->execute([$perPage, $offset]);
        $orders = $stmt->fetchAll();

        // Batch load items — single query instead of N queries (N+1 prevention)
        $orderIds = array_column($orders, 'id');
        $itemsByOrder = [];
        if (!empty($orderIds)) {
            $ph = implode(',', array_fill(0, count($orderIds), '?'));
            $itemStmt = $db->prepare("
                SELECT oi.orden_id, p.titulo, oi.cantidad, oi.precio_unitario, oi.subtotal
                FROM orden_items oi
                LEFT JOIN productos p ON oi.producto_id = p.id
                WHERE oi.orden_id IN ($ph)
                ORDER BY oi.id
            ");
            $itemStmt->execute($orderIds);
            foreach ($itemStmt->fetchAll() as $row) {
                $itemsByOrder[$row['orden_id']][] = $row;
            }
        }
        foreach ($orders as &$order) {
            $order['items'] = $itemsByOrder[$order['id']] ?? [];
        }
        unset($order);

        jsonResponse([
            'success'  => true,
            'data'     => $orders,
            'total'    => $total,
            'page'     => $page,
            'per_page' => $perPage,
        ]);
    }

    // List all orders for a user email (used by pedidos.html)
    // SECURITY: requiere sesión activa + solo puede ver sus propias órdenes (admin ve todas)
    if (isset($_GET['usuario'])) {
        if (empty($_SESSION['user_id'])) {
            jsonResponse(['success' => false, 'error' => 'No autenticado.'], 401);
        }

        $email = sanitize($_GET['usuario'] ?? '');
        if (!$email || !validEmail($email)) {
            jsonResponse(['success' => false, 'error' => 'Email inválido.'], 422);
        }

        $db = getDB();

        // Verificar que el email solicitado pertenece al usuario en sesión
        // (admins pueden consultar cualquier email)
        $selfStmt = $db->prepare("SELECT email, rol FROM usuarios WHERE id = ? AND activo = 1 LIMIT 1");
        $selfStmt->execute([$_SESSION['user_id']]);
        $self = $selfStmt->fetch();

        if (!$self) {
            jsonResponse(['success' => false, 'error' => 'Sesión inválida.'], 401);
        }
        if ($self['rol'] !== 'admin' && strtolower($self['email']) !== strtolower($email)) {
            securityLog('orders_unauthorized', ['user_id' => $_SESSION['user_id'], 'requested_email' => $email]);
            jsonResponse(['success' => false, 'error' => 'No autorizado.'], 403);
        }

        $stmt = $db->prepare("
            SELECT id, numero_orden, subtotal, total, estado,
                   nombre_cliente, telefono_cliente,
                   direccion_envio, ciudad, pais,
                   metodo_pago, creado_en
            FROM ordenes
            WHERE email_cliente = ?
            ORDER BY creado_en DESC
        ");
        $stmt->execute([$email]);
        $orders = $stmt->fetchAll();

        // Batch load items — single query instead of N queries (N+1 prevention)
        $orderIds = array_column($orders, 'id');
        $itemsByOrder = [];
        if (!empty($orderIds)) {
            $ph = implode(',', array_fill(0, count($orderIds), '?'));
            $itemStmt = $db->prepare("
                SELECT oi.orden_id, p.titulo, oi.cantidad, oi.precio_unitario, oi.subtotal
                FROM orden_items oi
                LEFT JOIN productos p ON oi.producto_id = p.id
                WHERE oi.orden_id IN ($ph)
                ORDER BY oi.id
            ");
            $itemStmt->execute($orderIds);
            foreach ($itemStmt->fetchAll() as $row) {
                $itemsByOrder[$row['orden_id']][] = $row;
            }
        }
        foreach ($orders as &$order) {
            $order['items'] = $itemsByOrder[$order['id']] ?? [];
        }
        unset($order);

        jsonResponse(['success' => true, 'data' => $orders]);
    }

    // Single order by number + tracking token
    $orderId = sanitize($_GET['id'] ?? '');
    $token   = sanitize($_GET['token'] ?? '');

    if (!$orderId || !$token) {
        jsonResponse(['success' => false, 'error' => 'Parámetros requeridos: id, token.'], 422);
    }

    $db   = getDB();
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

// ── POST ───────────────────────────────────────────────────────────────────────
if ($method === 'POST') {
    $body = getJsonBody();

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

    // Si hay sesión activa, forzar el email de la cuenta registrada
    if (!empty($_SESSION['user_id'])) {
        $su = $db->prepare("SELECT email, nombre, apellido FROM usuarios WHERE id = ? AND activo = 1 LIMIT 1");
        $su->execute([$_SESSION['user_id']]);
        $su = $su->fetch();
        if ($su) {
            $cliente['email']  = $su['email'];
            if (empty($cliente['nombre'])) {
                $cliente['nombre'] = trim($su['nombre'] . ($su['apellido'] ? ' ' . $su['apellido'] : ''));
            }
        }
    }

    // Validate & price products from DB (also fetch titulo for email)
    $productIds   = array_map(fn($i) => (int)$i['id'], $items);
    $placeholders = implode(',', array_fill(0, count($productIds), '?'));
    $stmt = $db->prepare("SELECT id, titulo, precio, stock FROM productos WHERE id IN ($placeholders) AND activo = 1");
    $stmt->execute($productIds);
    $dbProducts = [];
    foreach ($stmt->fetchAll() as $p) {
        $dbProducts[$p['id']] = $p;
    }

    $subtotal  = 0;
    $lineItems = [];
    foreach ($items as $item) {
        $id  = (int) $item['id'];
        $qty = max(1, (int) ($item['qty'] ?? 1));

        // Límite máximo por producto (previene manipulación de totales)
        if ($qty > 100) {
            jsonResponse(['success' => false, 'error' => "Cantidad máxima por producto es 100."], 422);
        }
        if (!isset($dbProducts[$id])) {
            jsonResponse(['success' => false, 'error' => "Producto no disponible: $id."], 422);
        }
        if ($dbProducts[$id]['stock'] < $qty) {
            jsonResponse(['success' => false, 'error' => "Stock insuficiente para el producto: $id."], 422);
        }
        // Usar precio de la DB, nunca del cliente
        $unitPrice  = (float) $dbProducts[$id]['precio'];
        $subtotal  += $unitPrice * $qty;
        $lineItems[] = [
            'id'     => $id,
            'titulo' => $dbProducts[$id]['titulo'],
            'qty'    => $qty,
            'precio' => $unitPrice,
        ];
    }

    // Validar que el total sea positivo (previene pedidos con precio 0)
    if ($subtotal <= 0) {
        jsonResponse(['success' => false, 'error' => 'Total inválido.'], 422);
    }

    $total       = $subtotal; // envío gratis
    $numeroOrden = 'MT-' . strtoupper(bin2hex(random_bytes(4)));
    $tokenSeguim = bin2hex(random_bytes(16));

    // Prefer session user_id, fall back to body value
    $userId = $_SESSION['user_id'] ?? ($body['usuario_id'] ?? null);

    $db->beginTransaction();
    try {
        // Re-verificar stock con bloqueo de fila (SELECT FOR UPDATE) dentro de la transacción
        // Previene race condition: dos pedidos simultáneos no pueden leer el mismo stock
        $lockPlaceholders = implode(',', array_fill(0, count($productIds), '?'));
        $lockStmt = $db->prepare("SELECT id, stock, precio FROM productos WHERE id IN ($lockPlaceholders) AND activo = 1 FOR UPDATE");
        $lockStmt->execute($productIds);
        $lockedProducts = [];
        foreach ($lockStmt->fetchAll() as $p) {
            $lockedProducts[$p['id']] = $p;
        }

        // Segunda validación de stock con datos bloqueados
        foreach ($lineItems as $li) {
            $locked = $lockedProducts[$li['id']] ?? null;
            if (!$locked) {
                $db->rollBack();
                jsonResponse(['success' => false, 'error' => "Producto #$li[id] ya no está disponible."], 422);
            }
            if ($locked['stock'] < $li['qty']) {
                $db->rollBack();
                jsonResponse(['success' => false, 'error' => "Stock insuficiente para «{$li['titulo']}». Disponible: {$locked['stock']}."], 422);
            }
        }

        $stmt = $db->prepare("
            INSERT INTO ordenes
              (numero_orden, token_seguimiento, usuario_id, nombre_cliente, email_cliente,
               telefono_cliente, direccion_envio, ciudad, pais, subtotal, costo_envio, total,
               estado, metodo_pago, creado_en)
            VALUES (?,?,?,?,?,?,?,?,?,?,0,?,'aprobado','card',NOW())
        ");
        $stmt->execute([
            $numeroOrden, $tokenSeguim,
            $userId,
            sanitize($cliente['nombre']   ?? ''),
            sanitize($cliente['email']    ?? ''),
            sanitize($cliente['telefono'] ?? ''),
            sanitize($envio['direccion']  ?? ''),
            sanitize($envio['ciudad']     ?? ''),
            sanitize($envio['pais']       ?? 'Colombia'),
            $subtotal, $total,
        ]);
        $orderId = (int) $db->lastInsertId();

        $itemStmt  = $db->prepare("INSERT INTO orden_items (orden_id, producto_id, cantidad, precio_unitario, subtotal) VALUES (?,?,?,?,?)");
        $stockStmt = $db->prepare("UPDATE productos SET stock = stock - ? WHERE id = ? AND stock >= ?");

        foreach ($lineItems as $li) {
            $itemStmt->execute([$orderId, $li['id'], $li['qty'], $li['precio'], $li['precio'] * $li['qty']]);
            $updated = $stockStmt->execute([$li['qty'], $li['id'], $li['qty']]);
            if ($stockStmt->rowCount() === 0) {
                $db->rollBack();
                jsonResponse(['success' => false, 'error' => "Stock insuficiente para «{$li['titulo']}»."], 409);
            }
        }

        $db->commit();

        // Invalidar cache de productos para que el siguiente request refleje el nuevo stock.
        // Sin esto, el cache de 60s sirviría stock incorrecto y permitiría sobre-vender.
        @unlink(__DIR__ . '/../../storage/cache/products.json');

        // Send confirmation email (non-fatal)
        sendOrderConfirmation(
            sanitize($cliente['email']    ?? ''),
            sanitize($cliente['nombre']   ?? ''),
            sanitize($cliente['telefono'] ?? ''),
            $numeroOrden,
            $total,
            $lineItems,
            $envio
        );

        jsonResponse([
            'success'            => true,
            'numero_orden'       => $numeroOrden,
            'token_seguimiento'  => $tokenSeguim,
            'total'              => $total,
            'message'            => '¡Orden creada exitosamente!',
        ], 201);

    } catch (\Exception $e) {
        $db->rollBack();
        jsonResponse(['success' => false, 'error' => 'Error al procesar la orden.'], 500);
    }
}

// ── PATCH — actualizar estado de un pedido (solo admin) ───────────────────────
if ($method === 'PATCH') {
    if (empty($_SESSION['user_id'])) {
        jsonResponse(['success' => false, 'error' => 'No autenticado.'], 401);
    }
    $db = getDB();
    $roleStmt = $db->prepare("SELECT rol FROM usuarios WHERE id = ? AND activo = 1 LIMIT 1");
    $roleStmt->execute([$_SESSION['user_id']]);
    $me = $roleStmt->fetch();
    if (!$me || $me['rol'] !== 'admin') {
        jsonResponse(['success' => false, 'error' => 'No autorizado.'], 403);
    }

    $body        = getJsonBody();
    $numeroOrden = sanitize($body['numero_orden'] ?? '');
    $estado      = sanitize($body['estado']       ?? '');

    $validos = ['pendiente', 'aprobado', 'procesando', 'enviado', 'entregado', 'cancelado'];
    if (!$numeroOrden || !in_array($estado, $validos)) {
        jsonResponse(['success' => false, 'error' => 'Datos inválidos.'], 422);
    }

    $db->prepare("UPDATE ordenes SET estado = ? WHERE numero_orden = ?")
       ->execute([$estado, $numeroOrden]);

    securityLog('order_status_updated', [
        'admin_id'     => $_SESSION['user_id'],
        'numero_orden' => $numeroOrden,
        'nuevo_estado' => $estado,
    ]);

    jsonResponse(['success' => true, 'message' => 'Estado actualizado.']);
}

// ── DELETE — anular pedido pendiente ──────────────────────────────────────────
if ($method === 'DELETE') {
    if (empty($_SESSION['user_id'])) {
        jsonResponse(['success' => false, 'error' => 'No autenticado.'], 401);
    }

    $body        = getJsonBody();
    $numeroOrden = sanitize($body['numero_orden'] ?? '');

    if (!$numeroOrden) {
        jsonResponse(['success' => false, 'error' => 'Número de orden requerido.'], 422);
    }

    $db = getDB();

    // Obtener email del usuario en sesión
    $selfStmt = $db->prepare("SELECT email, rol FROM usuarios WHERE id = ? AND activo = 1 LIMIT 1");
    $selfStmt->execute([$_SESSION['user_id']]);
    $self = $selfStmt->fetch();

    if (!$self) {
        jsonResponse(['success' => false, 'error' => 'Sesión inválida.'], 401);
    }

    // Buscar la orden: debe ser 'pendiente' y pertenecer al usuario
    $stmt = $db->prepare("
        SELECT id, estado FROM ordenes
        WHERE numero_orden = ?
          AND email_cliente = ?
          AND estado = 'pendiente'
        LIMIT 1
    ");
    $stmt->execute([$numeroOrden, $self['email']]);
    $order = $stmt->fetch();

    if (!$order) {
        jsonResponse(['success' => false, 'error' => 'Pedido no encontrado o no puede anularse.'], 404);
    }

    $db->prepare("UPDATE ordenes SET estado = 'cancelado' WHERE id = ?")
       ->execute([$order['id']]);

    securityLog('order_cancelled', [
        'user_id'      => $_SESSION['user_id'],
        'order_id'     => $order['id'],
        'numero_orden' => $numeroOrden,
    ]);

    jsonResponse(['success' => true, 'message' => 'Pedido anulado correctamente.']);
}

jsonResponse(['success' => false, 'error' => 'Method not allowed.'], 405);

// ── Email confirmation ─────────────────────────────────────────────────────────
function sendOrderConfirmation(string $to, string $name, string $phone, string $numero, float $total, array $items, array $envio = []): void {
    if (!$to || !filter_var($to, FILTER_VALIDATE_EMAIL)) return;
    if (!SMTP_USER || !SMTP_PASS) return;

    try {
        require_once __DIR__ . '/../../app/Helpers/Mail.php';

        $firstName = explode(' ', trim($name))[0];
        $totalFmt  = '$ ' . number_format($total, 0, ',', '.');

        $html = Mail::templateOrder($firstName, $name, $phone, $numero, $total, $items, $envio);

        $mail = new Mail();
        $mail->to($to, $name)
             ->subject("¡Pedido confirmado! #{$numero} — Mercaitech")
             ->body($html, "Pedido {$numero} confirmado. Total: {$totalFmt}. Gracias {$firstName} por tu compra en Mercaitech.");
        $mail->send();

    } catch (\Throwable $e) {
        // Non-fatal — la orden ya fue creada; registrar fallo de email para soporte
        @file_put_contents(
            __DIR__ . '/../../storage/logs/mail.log',
            date('Y-m-d H:i:s') . " [order_email_fail] order=$numero to=$to error=" . $e->getMessage() . PHP_EOL,
            FILE_APPEND
        );
    }
}
