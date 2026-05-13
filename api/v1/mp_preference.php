<?php
// Mercaitech — MercadoPago Checkout Pro
// POST /api/mp_preference.php
// Crea una orden PENDIENTE en la BD y luego genera la preferencia de pago en MP.
// El stock NO se descuenta aquí — lo hace el webhook cuando MP confirma el pago.

declare(strict_types=1);
require_once __DIR__ . '/../../config/app.php';

setCorsHeaders();

ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_samesite', 'Strict');
session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'error' => 'Method not allowed.'], 405);
}

if (!MP_ACCESS_TOKEN) {
    jsonResponse(['success' => false, 'error' => 'MercadoPago no configurado.'], 500);
}

$body    = getJsonBody();
$items   = $body['items']   ?? [];
$cliente = $body['cliente'] ?? [];
$envio   = $body['envio']   ?? [];

if (empty($items)) {
    jsonResponse(['success' => false, 'error' => 'Carrito vacío.'], 422);
}

$nombreCliente = sanitize($cliente['nombre']   ?? '');
$emailCliente  = sanitize($cliente['email']    ?? '');
$telefono      = sanitize($cliente['telefono'] ?? '');

// Si hay sesión activa, forzar el email de la cuenta — el cliente no puede cambiarlo
if (!empty($_SESSION['user_id'])) {
    $db        = getDB();
    $selfStmt  = $db->prepare("SELECT email, nombre, apellido, telefono FROM usuarios WHERE id = ? AND activo = 1 LIMIT 1");
    $selfStmt->execute([$_SESSION['user_id']]);
    $selfUser  = $selfStmt->fetch();
    if ($selfUser) {
        $emailCliente  = $selfUser['email'];
        // Usar nombre de la cuenta si el cliente no envió uno
        if (!$nombreCliente) {
            $nombreCliente = trim($selfUser['nombre'] . ($selfUser['apellido'] ? ' ' . $selfUser['apellido'] : ''));
        }
        if (!$telefono && $selfUser['telefono']) {
            $telefono = $selfUser['telefono'];
        }
    }
}

if (!$nombreCliente || !$emailCliente || !validEmail($emailCliente)) {
    jsonResponse(['success' => false, 'error' => 'Nombre y correo son obligatorios.'], 422);
}

// ── 1. Validar items + precios desde BD (sin descontar stock) ─────────────────
$db           = getDB();
$productIds   = array_map(fn($i) => (int)$i['id'], $items);
$placeholders = implode(',', array_fill(0, count($productIds), '?'));
$stmt = $db->prepare("SELECT id, titulo, precio, stock FROM productos WHERE id IN ($placeholders) AND activo = 1");
$stmt->execute($productIds);
$dbProducts = [];
foreach ($stmt->fetchAll() as $p) { $dbProducts[$p['id']] = $p; }

$subtotal  = 0.0;
$lineItems = [];
foreach ($items as $item) {
    $id  = (int)$item['id'];
    $qty = max(1, (int)($item['qty'] ?? 1));

    if (!isset($dbProducts[$id])) {
        jsonResponse(['success' => false, 'error' => "Producto no disponible: $id."], 422);
    }
    if ((int)$dbProducts[$id]['stock'] < $qty) {
        jsonResponse(['success' => false, 'error' => "Stock insuficiente para «{$dbProducts[$id]['titulo']}». Quedan {$dbProducts[$id]['stock']} unidades."], 422);
    }

    $unit      = (float)$dbProducts[$id]['precio'];
    $subtotal += $unit * $qty;
    $lineItems[] = ['id' => $id, 'titulo' => $dbProducts[$id]['titulo'], 'qty' => $qty, 'precio' => $unit];
}

if ($subtotal <= 0) {
    jsonResponse(['success' => false, 'error' => 'Total inválido.'], 422);
}

// Aplicar descuento (validado server-side: máximo 50%)
$discountRate = min(0.5, max(0.0, (float)($body['discount'] ?? 0)));
$descuento    = round($subtotal * $discountRate, 2);

// ── 2. Crear orden PENDIENTE en BD ────────────────────────────────────────────
$numeroOrden = 'MT-' . strtoupper(bin2hex(random_bytes(4)));
$tokenSeguim = bin2hex(random_bytes(16));
$userId      = $_SESSION['user_id'] ?? ($body['usuario_id'] ?? null);
$total       = $subtotal - $descuento;

$db->beginTransaction();
try {
    $db->prepare("
        INSERT INTO ordenes
          (numero_orden, token_seguimiento, usuario_id, nombre_cliente, email_cliente,
           telefono_cliente, direccion_envio, ciudad, pais, subtotal, descuento, costo_envio, total,
           estado, metodo_pago, creado_en)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,0,?,'pendiente','mp_pending',NOW())
    ")->execute([
        $numeroOrden, $tokenSeguim, $userId,
        $nombreCliente, $emailCliente, $telefono,
        sanitize($envio['direccion']     ?? ''),
        sanitize($envio['ciudad']        ?? ''),
        sanitize($envio['pais']          ?? 'Colombia'),
        $subtotal, $descuento, $total,
    ]);
    $orderId = (int)$db->lastInsertId();

    $itemStmt = $db->prepare("INSERT INTO orden_items (orden_id, producto_id, cantidad, precio_unitario, subtotal) VALUES (?,?,?,?,?)");
    foreach ($lineItems as $li) {
        $itemStmt->execute([$orderId, $li['id'], $li['qty'], $li['precio'], $li['precio'] * $li['qty']]);
    }
    $db->commit();
} catch (\Throwable $e) {
    $db->rollBack();
    jsonResponse(['success' => false, 'error' => 'Error al crear la orden. Inténtalo de nuevo.'], 500);
}

// ── 3. Crear preferencia en MercadoPago ───────────────────────────────────────
$isProduction = APP_ENV === 'production';
// notification_url: se envía cuando hay URL pública https (ngrok o producción)
// Así el webhook llega aunque el usuario no vuelva al checkout
$hasPublicUrl = str_starts_with(MP_SUCCESS_URL, 'https://');

// Incluir numero_orden en las back_urls para recuperarlo al volver
$sep        = str_contains(MP_SUCCESS_URL, '?') ? '&' : '?';
$successUrl = MP_SUCCESS_URL . $sep . 'mp_status=approved&ref=' . urlencode($numeroOrden);
$failureUrl = MP_FAILURE_URL . (str_contains(MP_FAILURE_URL, '?') ? '&' : '?') . 'mp_status=failure&ref=' . urlencode($numeroOrden);
$pendingUrl = MP_PENDING_URL . (str_contains(MP_PENDING_URL, '?') ? '&' : '?') . 'mp_status=pending&ref=' . urlencode($numeroOrden);

// Enviar precios descontados a MP para que el cobro coincida con el total mostrado
$factor  = 1.0 - $discountRate;
$mpItems = array_map(fn($li) => [
    'id'          => (string)$li['id'],
    'title'       => $li['titulo'],
    'quantity'    => $li['qty'],
    'unit_price'  => (int)round($li['precio'] * $factor),
    'currency_id' => 'COP',
], $lineItems);

// Si hay descuento, agregar línea descriptiva (no afecta el cobro, solo lo muestra en MP)
if ($descuento > 0 && $discountRate > 0) {
    $mpItems[] = [
        'id'         => 'DESCUENTO',
        'title'      => 'Descuento (' . round($discountRate * 100) . '%)',
        'quantity'   => 1,
        'unit_price' => 0,
        'currency_id'=> 'COP',
    ];
}

$preference = [
    'items'                => $mpItems,
    'payer'                => ['name' => $nombreCliente, 'email' => $emailCliente],
    'back_urls'            => ['success' => $successUrl, 'failure' => $failureUrl, 'pending' => $pendingUrl],
    'external_reference'   => $numeroOrden,   // clave: identifica la orden en el webhook
    'statement_descriptor' => 'MERCAITECH',
    ...($isProduction ? ['auto_return' => 'approved'] : []),
    ...($hasPublicUrl  ? ['notification_url' => rtrim(APP_URL, '/') . '/api/mp_webhook.php'] : []),
];

$ch = curl_init('https://api.mercadopago.com/checkout/preferences');
curl_setopt_array($ch, curlSecureOpts() + [
    CURLOPT_POST       => true,
    CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Authorization: Bearer ' . MP_ACCESS_TOKEN],
    CURLOPT_POSTFIELDS => json_encode($preference, JSON_UNESCAPED_UNICODE),
]);
$mpResponse = curl_exec($ch);
$httpCode   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlErr    = curl_error($ch);
curl_close($ch);

if ($curlErr || $httpCode !== 201) {
    // MP falló — cancelar la orden para no dejar basura pendiente
    $db->prepare("UPDATE ordenes SET estado='cancelado', actualizado_en=NOW() WHERE numero_orden=?")->execute([$numeroOrden]);
    $d   = $mpResponse ? (json_decode($mpResponse, true) ?? []) : [];
    $msg = $d['message'] ?? ($d['cause'][0]['description'] ?? ($curlErr ?: 'Error al crear preferencia en MercadoPago.'));
    jsonResponse(['success' => false, 'error' => $msg], 502);
}

$mpData = json_decode($mpResponse, true);

jsonResponse([
    'success'            => true,
    'preference_id'      => $mpData['id'],
    'init_point'         => $mpData['init_point'],
    'sandbox_init_point' => $mpData['sandbox_init_point'] ?? $mpData['init_point'],
    'numero_orden'       => $numeroOrden,
]);
