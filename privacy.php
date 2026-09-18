<?php
require __DIR__ . '/config.php';
$slug = $_GET['slug'] ?? '';
$stmt = $pdo->prepare('SELECT * FROM tenants WHERE slug = ?');
$stmt->execute([$slug]);
$tenant = $stmt->fetch();
if (!$tenant) { http_response_code(404); die('האתר המבוקש לא נמצא.'); }

$stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE tenant_id = ? AND setting_key='legal_privacy_text'");
$stmt->execute([$tenant['id']]);
$text = $stmt->fetchColumn() ?: '';
?>
<!doctype html>
<html lang="he" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>מדיניות פרטיות — <?= htmlspecialchars($tenant['name']) ?></title>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Rubik:wght@400;500;600;700;800&display=swap">
<link rel="stylesheet" href="assets/style.css?v=<?= @filemtime(__DIR__ . '/assets/style.css') ?>">
</head>
<body>
<div class="site-header"><div class="brand">מדיניות פרטיות</div><a href="site.php?slug=<?= urlencode($slug) ?>" class="icon-btn">✕</a></div>
<div class="wrap" style="padding:24px 20px; white-space:pre-wrap; line-height:1.8; font-size:15px;">
<?= htmlspecialchars($text) ?>
</div>
</body>
</html>
