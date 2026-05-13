<?php
// Mercaitech — Reintentar pago de una orden pendiente
// GET /api/mp_retry.php?ref=MT-XXXX
// Crea una nueva preferencia MP para una orden existente en estado 'pendiente'

declare(strict_types=1);
require_once __DIR__ . '/../../config/app.php';

setCorsHeaders();
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_samesite', 'Strict');
session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonResponse(['success' => false, 'error' => 'Method not allowed.'], 405);
}

if (!MP_ACCESS_TOKEN) {
    jsonResponse(['success' => false, 'error' => 'MercadoPago no configurado.'], 500);
}

$ref = sanitize($_GET['ref'] ?? '');
if (!$ref) {
    jsonResponse(['success' => false, 'error' => 'ref requerido.'], 422);
}

// Verificar autenticación
if (empty($_SESSION['user_id'])) {
    jsonResponse(['success' => false, 'error' => 'No autenticado.'], 401);
}

$db = getDB();

// Buscar la orden pendiente y verificar que pertenece al usuario
$stmt = $db->prepare("
    SELECT o.id, o.numero_orden, o.nombre_cliente, o.email_cliente, o.telefono_cliente,
           o.direccion_envio, o.ciudad, o.pais, o.total, o.estado, o.usuario_id
    FROM ordenes o
    WHERE o.numero_orden = ? AND o.estado = 'pendiente'
    LIMIT 1
");
$stmt->execute([$ref]);
$order = $stmt->fetch();

if (!$order) {
    jsonResponse(['success' => false, 'error' => 'Orden no encontrada o ya fue procesada.'], 404);
}

// Verificar que la orden pertenece al usuario en sesión
// Permite acceso si: admin, mismo usuario_id, mismo email, o usuario_id es NULL (invitado)
$selfStmt = $db->prepare("SELECT rol, email FROM usuarios WHERE id = ? AND activo = 1 LIMIT 1");
$selfStmt->execute([$_SESSION['user_id']]);
$self = $selfStmt->fetch();

if (!$self) {
    jsonResponse(['success' => false, 'error' => 'Sesión inválida.'], 401);
}

$ownsOrder = $order['usuario_id'] === null
          || (int)$order['usuario_id'] === (int)$_SESSION['user_id']
          || strtolower($self['email']) === strtolower($order['email_cliente']);

if ($self['rol'] !== 'admin' && !$ownsOrder) {
    jsonResponse(['success' => false, 'error' => 'No autorizado.'], 403);
}

// Obtener items de la orden
$itemsStmt = $db->prepare("
    SELECT oi.cantidad, oi.precio_unitario, p.titulo, p.id AS producto_id, p.stock
    FROM orden_items oi
    JOIN productos p ON p.id = oi.producto_id
    WHERE oi.orden_id = ?
");
$itemsStmt->execute([$order['id']]);
$items = $itemsStmt->fetchAll();

if (empty($items)) {
    jsonResponse(['success' => false, 'error' => 'La orden no tiene productos.'], 422);
}

// Verificar stock disponible para los items
foreach ($items as $item) {
    if ((int)$item['stock'] < (int)$item['cantidad']) {
        jsonResponse(['success' => false,
            'error' => "Sin stock para «{$item['titulo']}». Quedan {$item['stock']} unidades."], 422);
    }
}

// ── Buscar si ya hay un pago aprobado para esta orden en MP ──────────────────
// Evita doble cobro: si el cliente ya pagó pero no regresó al checkout, lo procesamos aquí.
$ch = curl_init('https://api.mercadopago.com/v1/payments/search?external_reference=' . urlencode($ref) . '&status=approved&limit=1');
curl_setopt_array($ch, curlSecureOpts() + [
    CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . MP_ACCESS_TOKEN],
]);
$searchRaw  = curl_exec($ch);
$searchCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($searchCode === 200 && $searchRaw) {
    $searchData = json_decode($searchRaw, true);
    $results    = $searchData['results'] ?? [];
    if (!empty($results)) {
        // Ya hay un pago aprobado — procesarlo como si fuera el webhook
        $payment   = $results[0];
        $paymentId = (string)$payment['id'];
        $payMethod = $payment['payment_type_id'] ?? 'mp';

        $db->beginTransaction();
        try {
            // Re-verificar que la orden sigue pendiente (idempotencia)
            $recheck = $db->prepare("SELECT id, estado FROM ordenes WHERE numero_orden = ? LIMIT 1 FOR UPDATE");
            $recheck->execute([$ref]);
            $reorder = $recheck->fetch();

            if ($reorder && $reorder['estado'] === 'pendiente') {
                $orderId2 = (int)$reorder['id'];

                // Bloquear y validar stock
                $pIds2 = array_column($items, 'producto_id');
                $ph2   = implode(',', array_fill(0, count($pIds2), '?'));
                $lk    = $db->prepare("SELECT id, stock FROM productos WHERE id IN ($ph2) FOR UPDATE");
                $lk->execute($pIds2);
                $lk2 = [];
                foreach ($lk->fetchAll() as $p) { $lk2[(int)$p['id']] = $p; }

                $stockOk = true;
                foreach ($items as $it) {
                    if (!isset($lk2[(int)$it['producto_id']]) || (int)$lk2[(int)$it['producto_id']]['stock'] < (int)$it['cantidad']) {
                        $stockOk = false; break;
                    }
                }

                if ($stockOk) {
                    $su = $db->prepare("UPDATE productos SET stock = stock - ? WHERE id = ? AND stock >= ?");
                    foreach ($items as $it) { $su->execute([(int)$it['cantidad'], (int)$it['producto_id'], (int)$it['cantidad']]); }
                    $db->prepare("UPDATE ordenes SET estado='aprobado', metodo_pago=?, mp_payment_id=?, actualizado_en=NOW() WHERE id=?")
                       ->execute([$payMethod, $paymentId, $orderId2]);
                    $db->commit();
                    @unlink(__DIR__ . '/../../storage/cache/products.json');

                    // Enviar email
                    try {
                        require_once __DIR__ . '/../../vendor/autoload.php';
                        require_once __DIR__ . '/../../app/Helpers/Mail.php';
                        $od = $db->prepare("SELECT nombre_cliente, email_cliente, telefono_cliente, direccion_envio, ciudad, pais, total FROM ordenes WHERE id=?");
                        $od->execute([$orderId2]);
                        $ord = $od->fetch();
                        $ei  = $db->prepare("SELECT p.titulo, oi.cantidad AS qty, oi.precio_unitario AS precio FROM orden_items oi JOIN productos p ON p.id=oi.producto_id WHERE oi.orden_id=?");
                        $ei->execute([$orderId2]);
                        $fn  = explode(' ', trim($ord['nombre_cliente']))[0];
                        $html = Mail::templateOrder($fn, $ord['nombre_cliente'], $ord['telefono_cliente'] ?? '', $ref, (float)$ord['total'], $ei->fetchAll(), ['direccion'=>$ord['direccion_envio'],'ciudad'=>$ord['ciudad'],'pais'=>$ord['pais']]);
                        $tot = '$ ' . number_format((float)$ord['total'], 0, ',', '.');
                        (new Mail())->to($ord['email_cliente'], $ord['nombre_cliente'])->subject("¡Pedido confirmado! #{$ref} — Mercaitech")->body($html, "Pedido {$ref} confirmado. Total: {$tot}.")->send();
                    } catch (\Throwable) {}

                    jsonResponse(['success' => true, 'already_paid' => true, 'numero_orden' => $ref, 'message' => '¡Pago encontrado y orden aprobada!']);
                } else {
                    $db->prepare("UPDATE ordenes SET estado='cancelado', actualizado_en=NOW() WHERE id=?")->execute([$orderId2]);
                    $db->commit();
                    jsonResponse(['success' => false, 'error' => 'Sin stock disponible para completar el pedido.'], 422);
                }
            } else {
                $db->rollBack();
                jsonResponse(['success' => true, 'already_paid' => true, 'numero_orden' => $ref, 'message' => 'La orden ya fue procesada.']);
            }
        } catch (\Throwable $e) {
            $db->rollBack();
            jsonResponse(['success' => false, 'error' => 'Error al procesar: ' . $e->getMessage()], 500);
        }
    }
}

// ── No hay pago previo → crear nueva preferencia MP ───────────────────────────
$isProduction = APP_ENV === 'production';
$hasPublicUrl = str_starts_with(MP_SUCCESS_URL, 'https://');

$sep        = str_contains(MP_SUCCESS_URL, '?') ? '&' : '?';
$successUrl = MP_SUCCESS_URL . $sep . 'mp_status=approved&ref=' . urlencode($ref);
$failureUrl = MP_FAILURE_URL . (str_contains(MP_FAILURE_URL, '?') ? '&' : '?') . 'mp_status=failure&ref=' . urlencode($ref);
$pendingUrl = MP_PENDING_URL . (str_contains(MP_PENDING_URL, '?') ? '&' : '?') . 'mp_status=pending&ref=' . urlencode($ref);

$mpItems = array_map(fn($i) => [
    'id'          => (string)$i['producto_id'],
    'title'       => $i['titulo'],
    'quantity'    => (int)$i['cantidad'],
    'unit_price'  => (int)round((float)$i['precio_unitario']),
    'currency_id' => 'COP',
], $items);

$preference = [
    'items'                => $mpItems,
    'payer'                => ['name' => $order['nombre_cliente'], 'email' => $order['email_cliente']],
    'back_urls'            => ['success' => $successUrl, 'failure' => $failureUrl, 'pending' => $pendingUrl],
    'external_reference'   => $ref,
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
    $d   = $mpResponse ? (json_decode($mpResponse, true) ?? []) : [];
    $msg = $d['message'] ?? ($d['cause'][0]['description'] ?? ($curlErr ?: 'Error al crear preferencia.'));
    jsonResponse(['success' => false, 'error' => $msg], 502);
}

$mpData = json_decode($mpResponse, true);

jsonResponse([
    'success'            => true,
    'init_point'         => $mpData['init_point'],
    'sandbox_init_point' => $mpData['sandbox_init_point'] ?? $mpData['init_point'],
    'numero_orden'       => $ref,
]);
