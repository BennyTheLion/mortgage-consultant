<?php
require __DIR__ . '/../config.php';

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $isTenantAdmin = !empty($_SESSION['admin_id']) && !empty($_SESSION['tenant_id']);
    if ($isTenantAdmin) {
        $tenantId = session_tenant_id();
    } else {
        $tenant = resolve_public_tenant($pdo);
        $tenantId = $tenant['id'];
    }
    $sql = 'SELECT * FROM services WHERE tenant_id = ?' . ($isTenantAdmin ? '' : ' AND active = 1') . ' ORDER BY sort_order ASC, id ASC';
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$tenantId]);
    json_out($stmt->fetchAll());
}

if ($method === 'POST') {
    require_tenant_admin();
    $tenantId = session_tenant_id();
    $input = body_json();
    $name = trim($input['name'] ?? '');
    $duration = (int) ($input['duration_minutes'] ?? 30);
    $price = trim($input['price'] ?? '');
    $sort = (int) ($input['sort_order'] ?? 0);
    $active = !empty($input['active']) ? 1 : 0;
    if (!$name || $duration < 5) json_out(['error' => 'שם השירות ומשך תקין נדרשים'], 400);

    if (!empty($input['id'])) {
        // tenant_id in the WHERE clause: an admin can never edit another tenant's service by guessing its id.
        $stmt = $pdo->prepare('UPDATE services SET name=?, duration_minutes=?, price=?, sort_order=?, active=? WHERE id=? AND tenant_id=?');
        $stmt->execute([$name, $duration, $price, $sort, $active, (int) $input['id'], $tenantId]);
    } else {
        $stmt = $pdo->prepare('INSERT INTO services (tenant_id, name, duration_minutes, price, sort_order, active) VALUES (?,?,?,?,?,?)');
        $stmt->execute([$tenantId, $name, $duration, $price, $sort, $active]);
    }
    json_out(['ok' => true]);
}

if ($method === 'DELETE') {
    require_tenant_admin();
    $tenantId = session_tenant_id();
    $id = (int) ($_GET['id'] ?? 0);
    if (!$id) json_out(['error' => 'missing id'], 400);
    $pdo->prepare('DELETE FROM services WHERE id=? AND tenant_id=?')->execute([$id, $tenantId]);
    json_out(['ok' => true]);
}

json_out(['error' => 'method not allowed'], 405);
