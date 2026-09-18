<?php
require __DIR__ . '/../config.php';

$method = $_SERVER['REQUEST_METHOD'];
$type = $_GET['type'] ?? '';
if (!in_array($type, ['image', 'video'], true)) json_out(['error' => 'type must be image or video'], 400);

if ($method === 'GET') {
    $isTenantAdmin = !empty($_SESSION['admin_id']) && !empty($_SESSION['tenant_id']);
    if ($isTenantAdmin) {
        $tenantId = session_tenant_id();
    } else {
        $tenant = resolve_public_tenant($pdo);
        $tenantId = $tenant['id'];
    }
    $stmt = $pdo->prepare('SELECT id, filename, sort_order FROM gallery_media WHERE tenant_id=? AND type=? ORDER BY sort_order ASC, id ASC');
    $stmt->execute([$tenantId, $type]);
    $rows = $stmt->fetchAll();
    $baseUrl = $type === 'image' ? images_url($tenantId) : videos_url($tenantId);
    foreach ($rows as &$r) $r['url'] = $baseUrl . rawurlencode($r['filename']);
    json_out($rows);
}

if ($method === 'POST') {
    require_tenant_admin();
    $tenantId = session_tenant_id();
    $dir = $type === 'image' ? images_dir($tenantId) : videos_dir($tenantId);
    if (!is_dir($dir)) mkdir($dir, 0755, true);

    // If the upload exceeded PHP's post_max_size, PHP empties $_POST/$_FILES entirely
    // (and normally prints a raw warning that would break the JSON response).
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

    // Enforce max item count
    $countStmt = $pdo->prepare('SELECT COUNT(*) c FROM gallery_media WHERE tenant_id=? AND type=?');
    $countStmt->execute([$tenantId, $type]);
    $count = (int) $countStmt->fetch()['c'];
    $limit = $type === 'image' ? MAX_IMAGES : MAX_VIDEOS;
    if ($count >= $limit) {
        json_out(['error' => "הגעתם למכסה המקסימלית של $limit " . ($type === 'image' ? 'תמונות' : 'סרטונים') . '. מחקו קובץ ישן לפני העלאת חדש.'], 400);
    }

    if ($type === 'image') {
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

        $filename = 'img_' . bin2hex(random_bytes(8)) . '.jpg';
        imagejpeg($srcImg, $dir . $filename, IMAGE_JPEG_QUALITY);
        imagedestroy($srcImg);

        $stmt = $pdo->prepare('INSERT INTO gallery_media (tenant_id, type, filename, sort_order) VALUES (?, ?, ?, ?)');
        $stmt->execute([$tenantId, 'image', $filename, $count]);
        json_out(['ok' => true, 'filename' => $filename, 'url' => images_url($tenantId) . $filename]);
    }

    if ($type === 'video') {
        if ($file['size'] > MAX_VIDEO_UPLOAD_BYTES) {
            json_out(['error' => 'קובץ הוידאו גדול מדי (מקסימום ' . (MAX_VIDEO_UPLOAD_BYTES / 1024 / 1024) . 'MB)'], 400);
        }
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ALLOWED_VIDEO_EXT, true)) {
            json_out(['error' => 'פורמט נתמך: MP4, WEBM, MOV בלבד'], 400);
        }
        $filename = 'vid_' . bin2hex(random_bytes(8)) . '.' . $ext;
        if (!move_uploaded_file($file['tmp_name'], $dir . $filename)) {
            json_out(['error' => 'שמירת הקובץ נכשלה'], 500);
        }
        $stmt = $pdo->prepare('INSERT INTO gallery_media (tenant_id, type, filename, sort_order) VALUES (?, ?, ?, ?)');
        $stmt->execute([$tenantId, 'video', $filename, $count]);
        json_out(['ok' => true, 'filename' => $filename, 'url' => videos_url($tenantId) . $filename]);
    }
}

if ($method === 'DELETE') {
    require_tenant_admin();
    $tenantId = session_tenant_id();
    $id = (int) ($_GET['id'] ?? 0);
    if (!$id) json_out(['error' => 'missing id'], 400);
    $stmt = $pdo->prepare('SELECT * FROM gallery_media WHERE id=? AND tenant_id=? AND type=?');
    $stmt->execute([$id, $tenantId, $type]);
    $row = $stmt->fetch();
    if ($row) {
        $dir = $type === 'image' ? images_dir($tenantId) : videos_dir($tenantId);
        $path = $dir . $row['filename'];
        if (is_file($path)) @unlink($path);
        $pdo->prepare('DELETE FROM gallery_media WHERE id=? AND tenant_id=?')->execute([$id, $tenantId]);
    }
    json_out(['ok' => true]);
}

json_out(['error' => 'method not allowed'], 405);
