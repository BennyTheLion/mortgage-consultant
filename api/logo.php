<?php
// Logo upload for the tenant admin.
// The public site (site.php) shows images/{tenantId}/logo.{png|jpg|webp} in the
// header and footer brand when it exists. The uploaded file is kept in its
// original format (unlike gallery photos) so PNG transparency survives.
require __DIR__ . '/../config.php';

$method = $_SERVER['REQUEST_METHOD'];
$EXTS = ['png', 'jpg', 'webp'];

function logo_path($tenantId, $ext) { return images_dir($tenantId) . 'logo.' . $ext; }

if ($method === 'GET') {
    $isTenantAdmin = !empty($_SESSION['admin_id']) && !empty($_SESSION['tenant_id']);
    if ($isTenantAdmin) {
        $tenantId = session_tenant_id();
    } else {
        $tenant = resolve_public_tenant($pdo);
        $tenantId = $tenant['id'];
    }
    global $EXTS;
    foreach ($EXTS as $ext) {
        if (is_file(logo_path($tenantId, $ext))) {
            json_out(['exists' => true, 'url' => images_url($tenantId) . 'logo.' . $ext]);
        }
    }
    json_out(['exists' => false, 'url' => null]);
}

if ($method === 'POST') {
    require_tenant_admin();
    $tenantId = session_tenant_id();
    $dir = images_dir($tenantId);
    if (!is_dir($dir)) mkdir($dir, 0755, true);

    if (empty($_FILES) && empty($_POST) && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
        json_out(['error' => 'הקובץ גדול מדי עבור הגדרות השרת (post_max_size). הגדילו upload_max_filesize ו-post_max_size ב-php.ini והפעילו מחדש את Apache.'], 400);
    }

    if (empty($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        $errCode = $_FILES['file']['error'] ?? null;
        if ($errCode === UPLOAD_ERR_INI_SIZE || $errCode === UPLOAD_ERR_FORM_SIZE) {
            json_out(['error' => 'הקובץ גדול מדי עבור הגדרות השרת (upload_max_filesize). הגדילו אותו ב-php.ini והפעילו מחדש את Apache.'], 400);
        }
        json_out(['error' => 'לא התקבל קובץ תקין'], 400);
    }
    $file = $_FILES['file'];

    if ($file['size'] > MAX_IMAGE_UPLOAD_BYTES) {
        json_out(['error' => 'קובץ התמונה גדול מדי (מקסימום ' . (MAX_IMAGE_UPLOAD_BYTES / 1024 / 1024) . 'MB)'], 400);
    }
    $info = @getimagesize($file['tmp_name']);
    if (!$info) json_out(['error' => 'הקובץ אינו תמונה תקינה'], 400);
    $mime = $info['mime'];
    $ext = null;
    switch ($mime) {
        case 'image/png':  $ext = 'png'; break;
        case 'image/jpeg': $ext = 'jpg'; break;
        case 'image/webp': $ext = 'webp'; break;
        default: json_out(['error' => 'פורמט נתמך: PNG, JPG, WEBP בלבד'], 400);
    }

    // Replace any existing logo (regardless of its previous format).
    global $EXTS;
    foreach ($EXTS as $old) {
        $p = logo_path($tenantId, $old);
        if (is_file($p)) @unlink($p);
    }

    if (!move_uploaded_file($file['tmp_name'], logo_path($tenantId, $ext))) {
        json_out(['error' => 'שמירת הקובץ נכשלה'], 500);
    }

    json_out(['ok' => true, 'url' => images_url($tenantId) . 'logo.' . $ext . '?v=' . time()]);
}

if ($method === 'DELETE') {
    require_tenant_admin();
    $tenantId = session_tenant_id();
    global $EXTS;
    foreach ($EXTS as $ext) {
        $p = logo_path($tenantId, $ext);
        if (is_file($p)) @unlink($p);
    }
    json_out(['ok' => true]);
}

json_out(['error' => 'method not allowed'], 405);