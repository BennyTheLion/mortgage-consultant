<?php
require __DIR__ . '/../config.php';

$tenant = resolve_public_tenant($pdo);
$tenantId = $tenant['id'];

$date = $_GET['date'] ?? '';
$serviceId = (int) ($_GET['service_id'] ?? 0);
$excludeBookingId = (int) ($_GET['exclude_booking_id'] ?? 0);

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || !$serviceId) {
    json_out(['error' => 'date (YYYY-MM-DD) and service_id are required'], 400);
}

$dt = DateTime::createFromFormat('Y-m-d', $date);
if (!$dt) json_out(['error' => 'invalid date'], 400);
$today = new DateTime('today');
if ($dt < $today) json_out(['slots' => []]);

$stmt = $pdo->prepare('SELECT * FROM services WHERE id=? AND tenant_id=? AND active=1');
$stmt->execute([$serviceId, $tenantId]);
$service = $stmt->fetch();
if (!$service) json_out(['error' => 'שירות לא נמצא'], 404);
$duration = (int) $service['duration_minutes'];

// Load settings
$stmt = $pdo->prepare("SELECT setting_key, setting_value FROM settings WHERE tenant_id = ? AND setting_key IN ('working_hours','slot_interval_minutes')");
$stmt->execute([$tenantId]);
$rows = $stmt->fetchAll();
$settings = [];
foreach ($rows as $r) $settings[$r['setting_key']] = $r['setting_value'];
$workingHours = json_decode($settings['working_hours'] ?? '{}', true) ?: [];
$interval = (int) ($settings['slot_interval_minutes'] ?? 30);
if ($interval < 5) $interval = 30;

$weekday = (int) $dt->format('w'); // 0=Sunday ... 6=Saturday
$day = $workingHours[(string) $weekday] ?? null;

if (!$day || !empty($day['closed']) || empty($day['open']) || empty($day['close'])) {
    json_out(['slots' => []]);
}

[$openH, $openM] = array_map('intval', explode(':', $day['open']));
[$closeH, $closeM] = array_map('intval', explode(':', $day['close']));

$slotStart = clone $dt;
$slotStart->setTime($openH, $openM);
$closeTime = clone $dt;
$closeTime->setTime($closeH, $closeM);

// Existing bookings for that date, with their service durations, to detect overlap.
// Cancelled bookings free up their slot again.
$sql = 'SELECT b.booking_time, s.duration_minutes FROM bookings b
        JOIN services s ON s.id = b.service_id
        WHERE b.tenant_id = ? AND b.booking_date = ? AND b.status = "confirmed"';
$params = [$tenantId, $date];
if ($excludeBookingId) { $sql .= ' AND b.id != ?'; $params[] = $excludeBookingId; }
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$existing = $stmt->fetchAll();

$busyRanges = [];
foreach ($existing as $b) {
    $start = clone $dt;
    [$h, $m] = array_map('intval', explode(':', substr($b['booking_time'], 0, 5)));
    $start->setTime($h, $m);
    $end = clone $start;
    $end->modify('+' . (int) $b['duration_minutes'] . ' minutes');
    $busyRanges[] = [$start, $end];
}

$now = new DateTime();
$slots = [];
while (true) {
    $slotEnd = clone $slotStart;
    $slotEnd->modify("+{$duration} minutes");
    if ($slotEnd > $closeTime) break;

    $taken = false;
    if ($dt->format('Y-m-d') === $today->format('Y-m-d') && $slotStart < $now) {
        $taken = true; // past time today
    }
    foreach ($busyRanges as [$bStart, $bEnd]) {
        if ($slotStart < $bEnd && $slotEnd > $bStart) { $taken = true; break; }
    }

    // Only genuinely available slots are returned — taken/past ones are simply omitted
    // so the customer never sees a time they can't actually book.
    if (!$taken) {
        $slots[] = ['time' => $slotStart->format('H:i')];
    }
    $slotStart->modify("+{$interval} minutes");
}

json_out(['slots' => $slots]);
