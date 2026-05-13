<?php
// Mercaitech — Calculator Scenarios API
// GET    /api/calculator.php           → list user's scenarios
// POST   /api/calculator.php           → save/update scenario
// DELETE /api/calculator.php           → delete scenario

declare(strict_types=1);
require_once __DIR__ . '/../../config/app.php';

setCorsHeaders();

ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_samesite', 'Strict');
session_start();

// Auto-create table on first request
autoMigrate();

// Restore session from remember-me cookie if needed
if (empty($_SESSION['user_id']) && !empty($_COOKIE['mt_remember'])) {
    tryRemember($_COOKIE['mt_remember']);
}

$userId = $_SESSION['user_id'] ?? null;
if (!$userId) {
    jsonResponse(['success' => false, 'error' => 'No autenticado.'], 401);
}

$method = $_SERVER['REQUEST_METHOD'];

match ($method) {
    'GET'    => listScenarios((int)$userId),
    'POST'   => saveScenario((int)$userId),
    'DELETE' => deleteScenario((int)$userId),
    default  => jsonResponse(['success' => false, 'error' => 'Method not allowed.'], 405),
};

// ── Table auto-migration ──────────────────────────────────────────────────────
function autoMigrate(): void {
    try {
        $db = getDB();
        $db->exec("
            CREATE TABLE IF NOT EXISTS calculator_scenarios (
                id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id     INT NOT NULL,
                name        VARCHAR(255) NOT NULL,
                data        JSON NOT NULL,
                created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uq_user_name (user_id, name),
                INDEX idx_user (user_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    } catch (\Throwable $e) {
        // Non-fatal: proceed even if migration fails
    }
}

// ── Remember-me helper ────────────────────────────────────────────────────────
function tryRemember(string $token): void {
    if (!$token) return;
    try {
        $db   = getDB();
        $stmt = $db->prepare("
            SELECT id, nombre, email, rol
            FROM usuarios
            WHERE remember_token = ? AND remember_expires > NOW() AND activo = 1
            LIMIT 1
        ");
        $stmt->execute([$token]);
        $user = $stmt->fetch();
        if ($user) {
            $_SESSION['user_id']    = $user['id'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['user_role']  = $user['rol'];
        }
    } catch (\Throwable) {}
}

// ── GET: list all scenarios for user ─────────────────────────────────────────
function listScenarios(int $userId): void {
    $db   = getDB();
    $stmt = $db->prepare("
        SELECT id, name, data, DATE_FORMAT(updated_at, '%d/%m/%Y') AS date
        FROM calculator_scenarios
        WHERE user_id = ?
        ORDER BY updated_at DESC
    ");
    $stmt->execute([$userId]);
    $rows = $stmt->fetchAll();

    foreach ($rows as &$r) {
        $r['data'] = json_decode($r['data'], true);
    }
    jsonResponse(['success' => true, 'scenarios' => $rows]);
}

// ── POST: save or update scenario ────────────────────────────────────────────
function saveScenario(int $userId): void {
    $body = getJsonBody();
    $name = trim($body['name'] ?? '');
    $data = $body['data'] ?? null;

    if (!$name || !$data) {
        jsonResponse(['success' => false, 'error' => 'name y data son requeridos.'], 422);
    }

    $db   = getDB();
    $stmt = $db->prepare("
        INSERT INTO calculator_scenarios (user_id, name, data)
        VALUES (?, ?, ?)
        ON DUPLICATE KEY UPDATE data = VALUES(data), updated_at = NOW()
    ");
    $stmt->execute([$userId, $name, json_encode($data)]);
    $id = $db->lastInsertId() ?: null;

    if (!$id) {
        $row = $db->prepare("SELECT id FROM calculator_scenarios WHERE user_id = ? AND name = ? LIMIT 1");
        $row->execute([$userId, $name]);
        $id = (int)($row->fetchColumn() ?: 0);
    }

    jsonResponse(['success' => true, 'id' => (int)$id, 'name' => $name]);
}

// ── DELETE: delete scenario ───────────────────────────────────────────────────
function deleteScenario(int $userId): void {
    $body = getJsonBody();
    $id   = (int)($body['id'] ?? 0);

    if (!$id) {
        jsonResponse(['success' => false, 'error' => 'id es requerido.'], 422);
    }

    $db   = getDB();
    $stmt = $db->prepare("DELETE FROM calculator_scenarios WHERE id = ? AND user_id = ?");
    $stmt->execute([$id, $userId]);

    jsonResponse(['success' => true]);
}
