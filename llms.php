<?php
require __DIR__ . '/config.php';
require_once __DIR__ . '/includes/site_content.php';

$slug = $_GET['slug'] ?? '';
$stmt = $pdo->prepare("SELECT * FROM tenants WHERE slug = ? AND status = 'active'");
$stmt->execute([$slug]);
$tenant = $stmt->fetch();
header('Content-Type: text/plain; charset=utf-8');
if (!$tenant) { http_response_code(404); echo "Not found\n"; exit; }

$p = public_profile($pdo, (int) $tenant['id']);
$base = site_base_url();
$siteUrl = $base !== '' ? $base . 'site/' . rawurlencode($slug) . '/' : '';
$days = ['ראשון', 'שני', 'שלישי', 'רביעי', 'חמישי', 'שישי', 'שבת'];
$one = function ($s) { return trim(preg_replace('/\s+/', ' ', (string) $s)); };

echo '# ' . $one($tenant['name']) . "\n\n";
if (($p['tagline'] ?? '') !== '') echo '> ' . $one($p['tagline']) . "\n\n";
if (($p['about_text'] ?? '') !== '') echo $one($p['about_text']) . "\n\n";

echo "## Contact\n";
if ($siteUrl) echo '- Website: ' . $siteUrl . "\n";
foreach (['phone' => 'Phone', 'email' => 'Email', 'address' => 'Address'] as $k => $label) {
    if (($p[$k] ?? '') !== '') echo "- $label: " . $one($p[$k]) . "\n";
}
if (($p['whatsapp_phone'] ?? '') !== '') echo '- WhatsApp: https://wa.me/' . preg_replace('/\D/', '', $p['whatsapp_phone']) . "\n";

echo "\n## Working hours\n";
foreach ($days as $i => $name) {
    $d = $p['working_hours'][(string) $i] ?? [];
    $open = (empty($d['closed']) && !empty($d['open']) && !empty($d['close'])) ? $d['open'] . '-' . $d['close'] : 'closed';
    echo "- $name: $open\n";
}

if ($p['services']) {
    echo "\n## Bookable services\n";
    foreach ($p['services'] as $s) {
        echo '- ' . $one($s['name']) . ' (' . (int) $s['duration_minutes'] . ' min' . (trim((string) $s['price']) !== '' ? ', ' . $one($s['price']) : '') . ")\n";
    }
    echo "\n## How to book\n";
    echo "Online booking is on the website: choose a service, a date and an available time, then enter name and phone (email optional). No account is needed. Existing bookings can be cancelled or rescheduled from the \"manage booking\" card using the phone number the booking was made with.\n";
}
