<?php
// Mercaitech — Verificación de pago MercadoPago
//
// GET  /api/mp_verify.php?ref=MT-XXXX  → estado actual de la orden en BD
// POST /api/mp_verify.php { payment_id } → consulta MP + procesa orden si aprobada (fallback del webhook)

declare(strict_types=1);
require_once __DIR__ . '/../../config/app.php';

setCorsHeaders();

ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_samesite', 'Strict');
session_start();

if (!MP_ACCESS_TOKEN) {
    jsonResponse(['success' => false, 'error' => 'MP no configurado.'], 500);
}

// ── GET: estado de la orden por numero_orden ──────────────────────────────────
// Usado por el frontend para polling después de volver de MercadoPago
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $ref = sanitize($_GET['ref'] ?? '');
    if (!$ref) {
        jsonResponse(['success' => false, 'error' => 'ref requerido.'], 422);
    }

    $db   = getDB();
    $stmt = $db->prepare("SELECT numero_orden, estado, total, nombre_cliente, email_cliente FROM ordenes WHERE numero_orden = ? LIMIT 1");
    $stmt->execute([$ref]);
    $order = $stmt->fetch();

    if (!$order) {
        jsonResponse(['success' => false, 'error' => 'Orden no encontrada.'], 404);
    }

    jsonResponse([
        'success'      => true,
        'numero_orden' => $order['numero_orden'],
        'estado'       => $order['estado'],
        'approved'     => $order['estado'] === 'aprobado',
        'total'        => (float)$order['total'],
        'email'        => $order['email_cliente'],
    ]);
}

// ── POST: verificar payment_id con MP + procesar orden (fallback del webhook) ─
// Se usa cuando el usuario vuelve de MP antes de que el webhook dispare,
// o en entornos locales donde el webhook no llega.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'error' => 'Method not allowed.'], 405);
}

$body      = getJsonBody();
$paymentId = (int)($body['payment_id'] ?? 0);
$ref       = sanitize($body['ref'] ?? '');  // numero_orden opcional

if (!$paymentId) {
    jsonResponse(['success' => false, 'error' => 'payment_id requerido.'], 422);
}

// Consultar el pago en MP
$ch = curl_init("https://api.mercadopago.com/v1/payments/$paymentId");
curl_setopt_array($ch, curlSecureOpts() + [
    CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . MP_ACCESS_TOKEN],
]);
$mpRaw    = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlErr  = curl_error($ch);
curl_close($ch);

if ($curlErr || $httpCode !== 200) {
    jsonResponse(['success' => false, 'error' => 'No se pudo verificar el pago con MercadoPago.'], 502);
}

$payment   = json_decode($mpRaw, true);
$mpStatus  = $payment['status']             ?? '';
$extRef    = $payment['external_reference'] ?? $ref;
$payMethod = $payment['payment_type_id']    ?? 'mp';

// Si el pago está aprobado, intentar procesar la orden como fallback del webhook
if ($mpStatus === 'approved' && $extRef) {
    try {
        $db = getDB();
        $db->beginTransaction();

        $orderStmt = $db->prepare("SELECT id, estado FROM ordenes WHERE numero_orden = ? LIMIT 1 FOR UPDATE");
        $orderStmt->execute([$extRef]);
        $order = $orderStmt->fetch();

        if ($order && $order['estado'] === 'pendiente') {
            $orderId = (int)$order['id'];

            // Obtener items
            $itemsStmt = $db->prepare("SELECT producto_id, cantidad FROM orden_items WHERE orden_id = ?");
            $itemsStmt->execute([$orderId]);
            $orderItems = $itemsStmt->fetchAll();

            // Bloquear y validar stock
            $pIds     = array_column($orderItems, 'producto_id');
            $ph       = implode(',', array_fill(0, count($pIds), '?'));
            $lockStmt = $db->prepare("SELECT id, stock FROM productos WHERE id IN ($ph) FOR UPDATE");
            $lockStmt->execute($pIds);
            $locked   = [];
            foreach ($lockStmt->fetchAll() as $p) { $locked[(int)$p['id']] = $p; }

            $stockOk = true;
            foreach ($orderItems as $oi) {
                if (!isset($locked[(int)$oi['producto_id']]) || (int)$locked[(int)$oi['producto_id']]['stock'] < (int)$oi['cantidad']) {
                    $stockOk = false; break;
                }
            }

            if ($stockOk) {
                // Descontar stock
                $stockStmt = $db->prepare("UPDATE productos SET stock = stock - ? WHERE id = ? AND stock >= ?");
                foreach ($orderItems as $oi) {
                    $stockStmt->execute([(int)$oi['cantidad'], (int)$oi['producto_id'], (int)$oi['cantidad']]);
                }
                // Aprobar orden
                $db->prepare("UPDATE ordenes SET estado='aprobado', metodo_pago=?, mp_payment_id=?, actualizado_en=NOW() WHERE id=?")
                   ->execute([$payMethod, (string)$paymentId, $orderId]);
                $db->commit();

                @unlink(__DIR__ . '/../../storage/cache/products.json');

                // Email de confirmación
                try {
                    require_once __DIR__ . '/../../vendor/autoload.php';
                    require_once __DIR__ . '/../../app/Helpers/Mail.php';
                    $ordData = $db->prepare("SELECT nombre_cliente, email_cliente, telefono_cliente, direccion_envio, ciudad, pais, total FROM ordenes WHERE id=?");
                    $ordData->execute([$orderId]);
                    $ord = $ordData->fetch();
                    $emailItems = $db->prepare("SELECT p.titulo, oi.cantidad AS qty, oi.precio_unitario AS precio FROM orden_items oi JOIN productos p ON p.id=oi.producto_id WHERE oi.orden_id=?");
                    $emailItems->execute([$orderId]);
                    $firstName = explode(' ', trim($ord['nombre_cliente']))[0];
                    $html = Mail::templateOrder($firstName, $ord['nombre_cliente'], $ord['telefono_cliente'] ?? '', $extRef, (float)$ord['total'], $emailItems->fetchAll(), ['direccion' => $ord['direccion_envio'], 'ciudad' => $ord['ciudad'], 'pais' => $ord['pais']]);
                    $totalFmt = '$ ' . number_format((float)$ord['total'], 0, ',', '.');
                    (new Mail())->to($ord['email_cliente'], $ord['nombre_cliente'])->subject("¡Pedido confirmado! #{$extRef} — Mercaitech")->body($html, "Pedido {$extRef} confirmado. Total: {$totalFmt}.")->send();
                } catch (\Throwable) {}
            } else {
                $db->prepare("UPDATE ordenes SET estado='cancelado', actualizado_en=NOW() WHERE id=?")->execute([$orderId]);
                $db->commit();
            }
        } else {
            $db->rollBack();
        }
    } catch (\Throwable) {
        try { $db->rollBack(); } catch (\Throwable) {}
    }
}

jsonResponse([
    'success'        => true,
    'approved'       => $mpStatus === 'approved',
    'payment_status' => $mpStatus,
    'payment_id'     => $paymentId,
    'numero_orden'   => $extRef,
]);
