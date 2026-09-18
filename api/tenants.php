<?php
require __DIR__ . '/../config.php';

require_super_admin();
$method = $_SERVER['REQUEST_METHOD'];

function rrmdir($dir) {
    if (!is_dir($dir)) return;
    foreach (scandir($dir) as $f) {
        if ($f === '.' || $f === '..') continue;
        $path = $dir . '/' . $f;
        is_dir($path) ? rrmdir($path) : @unlink($path);
    }
    @rmdir($dir);
}

$DEFAULT_WORKING_HOURS = json_encode([
    '0' => ['closed' => false, 'open' => '10:00', 'close' => '19:00'],
    '1' => ['closed' => false, 'open' => '10:00', 'close' => '19:00'],
    '2' => ['closed' => false, 'open' => '10:00', 'close' => '19:00'],
    '3' => ['closed' => false, 'open' => '10:00', 'close' => '19:00'],
    '4' => ['closed' => false, 'open' => '10:00', 'close' => '19:00'],
    '5' => ['closed' => true, 'open' => '', 'close' => ''],
    '6' => ['closed' => true, 'open' => '', 'close' => ''],
], JSON_UNESCAPED_UNICODE);

if ($method === 'GET') {
    if (($_GET['view'] ?? '') === 'bookings') {
        // Cross-tenant upcoming bookings overview — read-only, for the super admin.
        $rows = $pdo->query(
            "SELECT b.id, b.booking_date, b.booking_time, b.status, b.customer_name,
                    s.name AS service_name, t.name AS tenant_name, t.slug AS tenant_slug
             FROM bookings b
             JOIN services s ON s.id = b.service_id
             JOIN tenants t ON t.id = b.tenant_id
             WHERE b.booking_date >= CURDATE()
             ORDER BY b.booking_date ASC, b.booking_time ASC
             LIMIT 200"
        )->fetchAll();
        json_out($rows);
    }

    $tenants = $pdo->query(
        "SELECT t.id, t.slug, t.name, t.status, t.created_at,
                (SELECT COUNT(*) FROM services x WHERE x.tenant_id = t.id) AS services_count,
                (SELECT COUNT(*) FROM bookings x WHERE x.tenant_id = t.id AND x.booking_date >= CURDATE() AND x.status = 'confirmed') AS upcoming_count,
                (SELECT username FROM admins a WHERE a.tenant_id = t.id AND a.role = 'admin' ORDER BY a.id ASC LIMIT 1) AS admin_username,
                (SELECT setting_value FROM settings s WHERE s.tenant_id = t.id AND s.setting_key = 'accent_color') AS accent_color
         FROM tenants t ORDER BY t.created_at DESC"
    )->fetchAll();
    json_out($tenants);
}

if ($method === 'POST') {
    $input = body_json();
    $name = trim($input['name'] ?? '');
    $slug = strtolower(trim($input['slug'] ?? ''));
    $adminUsername = trim($input['admin_username'] ?? '');
    $adminPassword = $input['admin_password'] ?? '';
    $accentColor = trim($input['accent_color'] ?? '');
    if (!preg_match('/^#[0-9a-fA-F]{6}$/', $accentColor)) $accentColor = '#cda15c';

    if (!$name) json_out(['error' => 'נא להזין שם עסק'], 400);
    if (!preg_match('/^[a-z0-9-]{2,60}$/', $slug)) {
        json_out(['error' => 'הכתובת (slug) חייבת להכיל רק אותיות אנגליות קטנות, ספרות ומקפים (2-60 תווים)'], 400);
    }
    if (!$adminUsername || strlen($adminPassword) < 6) {
        json_out(['error' => 'נא להזין שם משתמש וסיסמה (לפחות 6 תווים) עבור מנהל האתר'], 400);
    }

    $dup = $pdo->prepare('SELECT id FROM tenants WHERE slug = ?');
    $dup->execute([$slug]);
    if ($dup->fetch()) json_out(['error' => 'הכתובת הזו כבר תפוסה, בחרו slug אחר'], 409);

    $dupUser = $pdo->prepare('SELECT id FROM admins WHERE username = ?');
    $dupUser->execute([$adminUsername]);
    if ($dupUser->fetch()) json_out(['error' => 'שם המשתמש כבר תפוס'], 409);

    $pdo->beginTransaction();
    try {
        $pdo->prepare('INSERT INTO tenants (slug, name, status) VALUES (?, ?, "active")')->execute([$slug, $name]);
        $tenantId = (int) $pdo->lastInsertId();

        $pdo->prepare('INSERT INTO admins (tenant_id, role, username, password_hash) VALUES (?, "admin", ?, ?)')
            ->execute([$tenantId, $adminUsername, password_hash($adminPassword, PASSWORD_DEFAULT)]);

        $defaults = [
            'owner_name' => $name, 'tagline' => '', 'about_text' => '', 'accent_color' => $accentColor,
            'phone' => '', 'whatsapp_phone' => '', 'email' => '', 'address' => '',
            'instagram_url' => '', 'facebook_url' => '', 'tiktok_url' => '',
            'slot_interval_minutes' => '30', 'working_hours' => $DEFAULT_WORKING_HOURS,
            'legal_privacy_text' => 'טיוטת מדיניות פרטיות — יש לערוך בפאנל הניהול.',
            'legal_terms_text' => 'טיוטת תקנון האתר — יש לערוך בפאנל הניהול.',
            'admin_notification_email' => '', 'mail_enabled' => '0',
            'smtp_host' => '', 'smtp_port' => '587', 'smtp_username' => '', 'smtp_password' => '',
            'smtp_secure' => 'tls', 'smtp_from_email' => '', 'smtp_from_name' => $name,
        ];
        $stmt = $pdo->prepare('INSERT INTO settings (tenant_id, setting_key, setting_value) VALUES (?, ?, ?)');
        foreach ($defaults as $key => $value) {
            $stmt->execute([$tenantId, $key, $value]);
        }

        $pdo->commit();
    } catch (\Throwable $e) {
        $pdo->rollBack();
        json_out(['error' => 'יצירת האתר נכשלה: ' . $e->getMessage()], 500);
    }

    @mkdir(images_dir($tenantId), 0755, true);
    @mkdir(videos_dir($tenantId), 0755, true);

    json_out(['ok' => true, 'tenant_id' => $tenantId, 'slug' => $slug]);
}

if ($method === 'PUT') {
    $input = body_json();
    $id = (int) ($input['id'] ?? 0);
    $action = $input['action'] ?? '';
    if (!$id) json_out(['error' => 'missing id'], 400);

    if ($action === 'suspend') {
        $pdo->prepare('UPDATE tenants SET status="suspended" WHERE id=?')->execute([$id]);
        json_out(['ok' => true]);
    }
    if ($action === 'activate') {
        $pdo->prepare('UPDATE tenants SET status="active" WHERE id=?')->execute([$id]);
        json_out(['ok' => true]);
    }
    if ($action === 'set_color') {
        $accentColor = trim($input['accent_color'] ?? '');
        if (!preg_match('/^#[0-9a-fA-F]{6}$/', $accentColor)) json_out(['error' => 'צבע לא תקין'], 400);
        $stmt = $pdo->prepare(
            'INSERT INTO settings (tenant_id, setting_key, setting_value) VALUES (?, "accent_color", ?)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
        );
        $stmt->execute([$id, $accentColor]);
        json_out(['ok' => true]);
    }
    if ($action === 'rename') {
        $name = trim($input['name'] ?? '');
        if (!$name) json_out(['error' => 'נא להזין שם'], 400);
        $pdo->prepare('UPDATE tenants SET name=? WHERE id=?')->execute([$name, $id]);
        json_out(['ok' => true]);
    }
    json_out(['error' => 'unknown action'], 400);
}

if ($method === 'DELETE') {
    $id = (int) ($_GET['id'] ?? 0);
    if (!$id) json_out(['error' => 'missing id'], 400);
    // ON DELETE CASCADE on settings/services/gallery_media/bookings/admins handles the DB side.
    $pdo->prepare('DELETE FROM tenants WHERE id=?')->execute([$id]);
    rrmdir(rtrim(images_dir($id), '/'));
    rrmdir(rtrim(videos_dir($id), '/'));
    json_out(['ok' => true]);
}

json_out(['error' => 'method not allowed'], 405);
