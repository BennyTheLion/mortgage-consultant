<?php
// Advisor portrait upload for the tenant admin.
// The public site (site.php) shows images/{tenantId}/advisor-portrait.jpg next to
// the "about" text — this endpoint lets the admin change/remove that photo.
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
    $path = images_dir($tenantId) . 'advisor-portrait.jpg';
    json_out(['exists' => is_file($path), 'url' => images_url($tenantId) . 'advisor-portrait.jpg']);
}

if ($method === 'POST') {
    require_tenant_admin();
    $tenantId = session_tenant_id();
    $dir = images_dir($tenantId);
    if (!is_dir($dir)) mkdir($dir, 0755, true);

    // If the upload exceeded PHP's post_max_size, PHP empties $_POST/$_FILES entirely.
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

    if (!extension_loaded('gd')) {
        json_out(['error' => 'הרחבת GD אינה מופעלת ב-PHP. פתחו את php.ini, מצאו את השורה ;extension=gd, הסירו את ה-; שלפניה, ושמרו והפעילו מחדש את Apache.'], 500);
    }
    if ($file['size'] > MAX_IMAGE_UPLOAD_BYTES) {
        json_out(['error' => 'קובץ התמונה גדול מדי (מקסימום ' . (MAX_IMAGE_UPLOAD_BYTES / 1024 / 1024) . 'MB)'], 400);
    }
    $info = @getimagesize($file['tmp_name']);
    if (!$info) json_out(['error' => 'הקובץ אינו תמונה תקינה'], 400);
    $mime = $info['mime'];
    $srcImg = null;
    switch ($mime) {
        case 'image/jpeg': $srcImg = imagecreatefromjpeg($file['tmp_name']); break;
        case 'image/png':  $srcImg = imagecreatefrompng($file['tmp_name']); break;
        case 'image/webp':
            if (!function_exists('imagecreatefromwebp')) {
                json_out(['error' => 'תמיכת WEBP לא זמינה ב-GD של PHP הזה. נסו להעלות את התמונה כ-JPG או PNG.'], 400);
            }
            $srcImg = imagecreatefromwebp($file['tmp_name']);
            break;
        default: json_out(['error' => 'פורמט נתמך: JPG, PNG, WEBP בלבד'], 400);
    }
    if (!$srcImg) json_out(['error' => 'לא ניתן לקרוא את התמונה'], 400);

    $width = imagesx($srcImg);
    $height = imagesy($srcImg);
    if ($width > MAX_IMAGE_WIDTH) {
        $newWidth = MAX_IMAGE_WIDTH;
        $newHeight = (int) round($height * ($newWidth / $width));
        $resized = imagecreatetruecolor($newWidth, $newHeight);
        imagecopyresampled($resized, $srcImg, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
        imagedestroy($srcImg);
        $srcImg = $resized;
    }

    // Fixed filename so site.php always shows the latest portrait.
    imagejpeg($srcImg, $dir . 'advisor-portrait.jpg', IMAGE_JPEG_QUALITY);
    imagedestroy($srcImg);

    json_out(['ok' => true, 'url' => images_url($tenantId) . 'advisor-portrait.jpg?v=' . time()]);
}

if ($method === 'DELETE') {
    require_tenant_admin();
    $tenantId = session_tenant_id();
    $path = images_dir($tenantId) . 'advisor-portrait.jpg';
    if (is_file($path)) @unlink($path);
    json_out(['ok' => true]);
}

json_out(['error' => 'method not allowed'], 405);