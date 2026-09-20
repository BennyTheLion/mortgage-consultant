<?php
require __DIR__ . '/../config.php';

// accent_color is deliberately NOT here: only the super admin may set it (via api/tenants.php),
// never the tenant's own admin — enforced server-side, not just hidden in the admin UI.
$ALLOWED_KEYS = [
    'owner_name', 'tagline', 'about_text', 'phone', 'whatsapp_phone', 'email', 'address',
    'instagram_url', 'facebook_url', 'tiktok_url', 'slot_interval_minutes',
    'working_hours', 'legal_privacy_text', 'legal_terms_text',
    'admin_notification_email', 'mail_enabled', 'smtp_host', 'smtp_port',
    'smtp_username', 'smtp_password', 'smtp_secure', 'smtp_from_email', 'smtp_from_name',
    'site_content',
];
require_once __DIR__ . '/../includes/site_content.php';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $isTenantAdmin = !empty($_SESSION['admin_id']) && !empty($_SESSION['tenant_id']);
    // A logged-in tenant admin reads their own tenant (ignores any slug param);
    // everyone else (the public site) must supply ?slug= for an active tenant.
    if ($isTenantAdmin) {
        $tenantId = session_tenant_id();
    } else {
        $tenant = resolve_public_tenant($pdo);
        $tenantId = $tenant['id'];
    }

    // Mail/SMTP credentials are never returned to public (unauthenticated) requests.
    $privateKeys = ['admin_notification_email', 'mail_enabled', 'smtp_host', 'smtp_port', 'smtp_username', 'smtp_password', 'smtp_secure', 'smtp_from_email', 'smtp_from_name'];

    $stmt = $pdo->prepare('SELECT setting_key, setting_value FROM settings WHERE tenant_id = ?');
    $stmt->execute([$tenantId]);
    $rows = $stmt->fetchAll();
    $out = [];
    foreach ($rows as $r) {
        if (!$isTenantAdmin && in_array($r['setting_key'], $privateKeys, true)) continue;
        $out[$r['setting_key']] = $r['setting_value'];
    }
    if (isset($out['working_hours'])) {
        $decoded = json_decode($out['working_hours'], true);
        $out['working_hours'] = $decoded ?: new stdClass();
    }
    $out['site_content'] = get_site_content($pdo, $tenantId);
    json_out($out);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_tenant_admin();
    $tenantId = session_tenant_id();
    $input = body_json();
    $stmt = $pdo->prepare(
        'INSERT INTO settings (tenant_id, setting_key, setting_value) VALUES (?, ?, ?)
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
    );
    foreach ($ALLOWED_KEYS as $key) {
        if (!array_key_exists($key, $input)) continue;
        $value = $input[$key];
        if ($key === 'working_hours' && is_array($value)) {
            $value = json_encode($value, JSON_UNESCAPED_UNICODE);
        }
        if ($key === 'site_content') {
            $value = json_encode(sanitize_site_content($value), JSON_UNESCAPED_UNICODE);
        }
        $stmt->execute([$tenantId, $key, $value]);
    }
    json_out(['ok' => true]);
}

json_out(['error' => 'method not allowed'], 405);
