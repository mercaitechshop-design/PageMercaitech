<?php
// Mercaitech — Webhook MercadoPago
// MP llama aquí cuando un pago cambia de estado (aprobado, rechazado, cancelado).
// Este endpoint es el responsable de:
//   - Decrementar stock (solo al aprobar, con SELECT FOR UPDATE)
//   - Marcar la orden como aprobada/cancelada
//   - Enviar el correo de confirmación

declare(strict_types=1);
require_once __DIR__ . '/../../config/app.php';

// Siempre responder 200 a MP para evitar reintentos infinitos
http_response_code(200);
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { echo '{"received":true}'; exit; }
if (!MP_ACCESS_TOKEN) { echo '{"received":true}'; exit; }

$rawBody = file_get_contents('php://input');
$body    = json_decode($rawBody ?: '{}', true) ?: [];
$type    = $body['type'] ?? ($_GET['topic'] ?? '');
$dataId  = (string)($body['data']['id'] ?? ($_GET['id'] ?? ''));

// Verificar firma HMAC-SHA256 (protege contra webhooks falsos)
if (MP_WEBHOOK_SECRET && !verifyMpWebhookSignature($dataId)) {
    http_response_code(401);
    echo json_encode(['error' => 'Invalid signature']);
    exit;
}

// Solo procesar pagos
if (!in_array($type, ['payment', 'merchant_order'], true) || !$dataId) {
    echo '{"received":true}'; exit;
}

// merchant_order → resolver el payment_id real
if ($type === 'merchant_order') {
    $ch = curl_init("https://api.mercadopago.com/merchant_orders/$dataId");
    curl_setopt_array($ch, curlSecureOpts() + [CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . MP_ACCESS_TOKEN]]);
    $res    = json_decode((string)curl_exec($ch), true);
    curl_close($ch);
    $dataId = (string)($res['payments'][0]['id'] ?? '');
    if (!$dataId) { echo '{"received":true}'; exit; }
}

// Consultar el pago en MP
$ch = curl_init("https://api.mercadopago.com/v1/payments/$dataId");
curl_setopt_array($ch, curlSecureOpts() + [CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . MP_ACCESS_TOKEN]]);
$mpRaw    = (string)curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode !== 200) { echo '{"received":true}'; exit; }

$payment   = json_decode($mpRaw, true);
$mpStatus  = $payment['status']             ?? '';
$extRef    = $payment['external_reference'] ?? '';  // = numero_orden
$paymentId = (string)($payment['id']        ?? $dataId);
$payMethod = $payment['payment_type_id']    ?? 'mp';

@file_put_contents(
    __DIR__ . '/../../storage/logs/webhook.log',
    date('Y-m-d H:i:s') . " | type=$type id=$dataId ref=$extRef status=$mpStatus\n",
    FILE_APPEND
);

if (!$extRef) { echo '{"received":true}'; exit; }

try {
    $db = getDB();

    // ── APROBADO ──────────────────────────────────────────────────────────────
    if ($mpStatus === 'approved') {
        $db->beginTransaction();

        // Bloquear la fila de la orden (evita procesar el mismo webhook dos veces)
        $orderStmt = $db->prepare("SELECT id, estado FROM ordenes WHERE numero_orden = ? LIMIT 1 FOR UPDATE");
        $orderStmt->execute([$extRef]);
        $order = $orderStmt->fetch();

        if (!$order || $order['estado'] !== 'pendiente') {
            // Ya fue procesado o no existe — idempotencia
            $db->rollBack();
            echo '{"received":true}'; exit;
        }

        $orderId = (int)$order['id'];

        // Obtener items de la orden
        $itemsStmt = $db->prepare("SELECT producto_id, cantidad FROM orden_items WHERE orden_id = ?");
        $itemsStmt->execute([$orderId]);
        $orderItems = $itemsStmt->fetchAll();

        if (empty($orderItems)) {
            $db->rollBack();
            echo '{"received":true}'; exit;
        }

        // Bloquear filas de productos + validar stock
        $pIds     = array_column($orderItems, 'producto_id');
        $ph       = implode(',', array_fill(0, count($pIds), '?'));
        $lockStmt = $db->prepare("SELECT id, titulo, stock FROM productos WHERE id IN ($ph) FOR UPDATE");
        $lockStmt->execute($pIds);
        $locked   = [];
        foreach ($lockStmt->fetchAll() as $p) { $locked[(int)$p['id']] = $p; }

        foreach ($orderItems as $oi) {
            $pid = (int)$oi['producto_id'];
            $qty = (int)$oi['cantidad'];
            if (!isset($locked[$pid]) || (int)$locked[$pid]['stock'] < $qty) {
                // Sin stock — cancelar la orden (MP ya cobró; requiere reembolso manual)
                $db->prepare("UPDATE ordenes SET estado='cancelado', actualizado_en=NOW() WHERE id=?")->execute([$orderId]);
                $db->commit();
                @file_put_contents(
                    __DIR__ . '/../../storage/logs/webhook.log',
                    date('Y-m-d H:i:s') . " | STOCK_FAIL ref=$extRef pid=$pid disponible=" . ($locked[$pid]['stock'] ?? 0) . " requerido=$qty\n",
                    FILE_APPEND
                );
                echo '{"received":true}'; exit;
            }
        }

        // Descontar stock atómicamente
        $stockStmt = $db->prepare("UPDATE productos SET stock = stock - ? WHERE id = ? AND stock >= ?");
        foreach ($orderItems as $oi) {
            $stockStmt->execute([(int)$oi['cantidad'], (int)$oi['producto_id'], (int)$oi['cantidad']]);
            if ($stockStmt->rowCount() === 0) {
                // Race condition — otro proceso ganó el stock
                $db->rollBack();
                @file_put_contents(
                    __DIR__ . '/../../storage/logs/webhook.log',
                    date('Y-m-d H:i:s') . " | STOCK_RACE ref=$extRef pid={$oi['producto_id']}\n",
                    FILE_APPEND
                );
                echo '{"received":true}'; exit;
            }
        }

        // Aprobar la orden
        $db->prepare("
            UPDATE ordenes
            SET estado = 'aprobado', metodo_pago = ?, mp_payment_id = ?, actualizado_en = NOW()
            WHERE id = ?
        ")->execute([$payMethod, $paymentId, $orderId]);

        $db->commit();

        // Invalidar cache de productos para que el stock actualizado sea visible
        @unlink(__DIR__ . '/../../storage/cache/products.json');

        // Enviar correo de confirmación
        try {
            require_once __DIR__ . '/../../vendor/autoload.php';
            require_once __DIR__ . '/../../app/Helpers/Mail.php';

            $ordData = $db->prepare("SELECT nombre_cliente, email_cliente, telefono_cliente, direccion_envio, ciudad, pais, total FROM ordenes WHERE id = ?");
            $ordData->execute([$orderId]);
            $ord = $ordData->fetch();

            $emailItems = $db->prepare("SELECT p.titulo, oi.cantidad AS qty, oi.precio_unitario AS precio FROM orden_items oi JOIN productos p ON p.id = oi.producto_id WHERE oi.orden_id = ?");
            $emailItems->execute([$orderId]);
            $emailList = $emailItems->fetchAll();

            $firstName = explode(' ', trim($ord['nombre_cliente']))[0];
            $html      = Mail::templateOrder(
                $firstName,
                $ord['nombre_cliente'],
                $ord['telefono_cliente'] ?? '',
                $extRef,
                (float)$ord['total'],
                $emailList,
                ['direccion' => $ord['direccion_envio'], 'ciudad' => $ord['ciudad'], 'pais' => $ord['pais']]
            );
            $totalFmt = '$ ' . number_format((float)$ord['total'], 0, ',', '.');

            (new Mail())
                ->to($ord['email_cliente'], $ord['nombre_cliente'])
                ->subject("¡Pedido confirmado! #{$extRef} — Mercaitech")
                ->body($html, "Pedido {$extRef} confirmado. Total: {$totalFmt}. Gracias {$firstName} por tu compra en Mercaitech.")
                ->send();
        } catch (\Throwable $e) {
            @file_put_contents(
                __DIR__ . '/../../storage/logs/webhook.log',
                date('Y-m-d H:i:s') . " | EMAIL_FAIL ref=$extRef: " . $e->getMessage() . "\n",
                FILE_APPEND
            );
        }

    // ── RECHAZADO / CANCELADO ─────────────────────────────────────────────────
    } elseif (in_array($mpStatus, ['rejected', 'cancelled'], true)) {
        // Stock no fue descontado → solo cambiar estado
        $db->prepare("
            UPDATE ordenes SET estado = 'cancelado', actualizado_en = NOW()
            WHERE numero_orden = ? AND estado = 'pendiente'
        ")->execute([$extRef]);
    }

} catch (\Throwable $e) {
    try { $db->rollBack(); } catch (\Throwable) {}
    @file_put_contents(
        __DIR__ . '/../../storage/logs/webhook.log',
        date('Y-m-d H:i:s') . " | DB_ERROR ref=$extRef: " . $e->getMessage() . "\n",
        FILE_APPEND
    );
}

echo '{"received":true}';
