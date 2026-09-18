<?php
require __DIR__ . '/../config.php';

require_admin();
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    // Whether the current admin already has this exact browser subscription saved,
    // so the panel can show the toggle as on/off after a page reload.
    $endpoint = trim($_GET['endpoint'] ?? '');
    if (!$endpoint) json_out(['subscribed' => false]);
    $stmt = $pdo->prepare('SELECT id FROM push_subscriptions WHERE endpoint_hash = ?');
    $stmt->execute([hash('sha256', $endpoint)]);
    json_out(['subscribed' => (bool) $stmt->fetch()]);
}

if ($method === 'POST') {
    $input = body_json();
    $endpoint = trim($input['endpoint'] ?? '');
    $p256dh = trim($input['keys']['p256dh'] ?? '');
    $auth = trim($input['keys']['auth'] ?? '');
    if (!$endpoint || !$p256dh || !$auth) json_out(['error' => 'מנוי לא תקין'], 400);

    $stmt = $pdo->prepare(
        'INSERT INTO push_subscriptions (admin_id, endpoint, endpoint_hash, p256dh, auth) VALUES (?,?,?,?,?)
         ON DUPLICATE KEY UPDATE p256dh = VALUES(p256dh), auth = VALUES(auth)'
    );
    $stmt->execute([$_SESSION['admin_id'], $endpoint, hash('sha256', $endpoint), $p256dh, $auth]);
    json_out(['ok' => true]);
}

if ($method === 'DELETE') {
    $input = body_json();
    $endpoint = trim($input['endpoint'] ?? '');
    if (!$endpoint) json_out(['error' => 'missing endpoint'], 400);
    $pdo->prepare('DELETE FROM push_subscriptions WHERE endpoint_hash = ?')->execute([hash('sha256', $endpoint)]);
    json_out(['ok' => true]);
}

json_out(['error' => 'method not allowed'], 405);
