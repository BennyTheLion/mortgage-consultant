<?php
require __DIR__ . '/../config.php';

$tenantSlug = null;
if (!empty($_SESSION['tenant_id'])) {
    $t = $pdo->prepare('SELECT slug FROM tenants WHERE id = ?');
    $t->execute([$_SESSION['tenant_id']]);
    $row = $t->fetch();
    $tenantSlug = $row ? $row['slug'] : null;
}

json_out([
    'loggedIn' => !empty($_SESSION['admin_id']),
    'username' => $_SESSION['admin_username'] ?? null,
    'role' => $_SESSION['role'] ?? null,
    'tenant_slug' => $tenantSlug,
]);
