<?php
require __DIR__ . '/../config.php';
require __DIR__ . '/../includes/mailer.php';
require __DIR__ . '/../includes/push.php';

$method = $_SERVER['REQUEST_METHOD'];

// Phones are compared digits-only so "050-1234567" / "0501234567" / "+972501234567" all match.
function normalize_phone($phone) {
    return preg_replace('/\D/', '', (string) $phone);
}

function notify_booking($pdo, $tenantId, $booking, $service, $title, $customerNote) {
    $stmt = $pdo->prepare("SELECT setting_key, setting_value FROM settings WHERE tenant_id = ? AND setting_key IN ('owner_name','admin_notification_email','email')");
    $stmt->execute([$tenantId]);
    $s = [];
    foreach ($stmt->fetchAll() as $r) $s[$r['setting_key']] = $r['setting_value'];
    $adminEmail = $s['admin_notification_email'] ?: ($s['email'] ?? '');

    $lines = [
        'שירות' => $service['name'],
        'תאריך' => $booking['booking_date'],
        'שעה' => substr($booking['booking_time'], 0, 5),
        'לקוח' => $booking['customer_name'],
        'טלפון' => $booking['customer_phone'],
    ];

    if (!empty($booking['customer_email'])) {
        send_app_mail($pdo, $tenantId, $booking['customer_email'], $booking['customer_name'], $title, booking_email_html($title . ' — ' . $customerNote, $lines));
    }
    if ($adminEmail) {
        send_app_mail($pdo, $tenantId, $adminEmail, $s['owner_name'] ?? '', $title . ' (עדכון פנימי)', booking_email_html($title, $lines));
    }

    $pushBody = $service['name'] . ' — ' . $booking['booking_date'] . ' ' . substr($booking['booking_time'], 0, 5) . ' — ' . $booking['customer_name'];
    send_push_notifications($pdo, $tenantId, $title, $pushBody, 'admin.php');
}

if ($method === 'GET') {
    // A phone param always means the public self-service lookup — checked first and
    // regardless of session, so an advisor who happens to be logged into the admin panel in
    // the same browser never has this call silently upgraded into the full admin listing
    // (which would leak every customer's bookings, cancelled ones included).
    if (isset($_GET['phone'])) {
        $tenant = resolve_public_tenant($pdo);
        $phone = normalize_phone($_GET['phone']);
        if (!$phone) json_out(['error' => 'נא להזין מספר טלפון'], 400);

        $stmt = $pdo->prepare(
            "SELECT b.id, b.customer_name, b.customer_phone, b.booking_date, b.booking_time, b.status, s.name AS service_name, s.duration_minutes, s.id AS service_id
             FROM bookings b JOIN services s ON s.id = b.service_id
             WHERE b.tenant_id = ? AND b.booking_date >= CURDATE() AND b.status = 'confirmed'
             ORDER BY b.booking_date ASC, b.booking_time ASC"
        );
        $stmt->execute([$tenant['id']]);
        $mine = array_values(array_filter($stmt->fetchAll(), function ($b) use ($phone) {
            return normalize_phone($b['customer_phone']) === $phone;
        }));
        foreach ($mine as &$b) { unset($b['customer_phone']); }
        json_out($mine);
    }

    // Admin: list upcoming bookings (including cancelled, shown with status) for their own tenant.
    require_tenant_admin();
    $stmt = $pdo->prepare(
        "SELECT b.id, b.customer_name, b.customer_phone, b.customer_email, b.booking_date, b.booking_time, b.status, s.name AS service_name, s.id AS service_id
         FROM bookings b JOIN services s ON s.id = b.service_id
         WHERE b.tenant_id = ? AND b.booking_date >= CURDATE()
         ORDER BY b.booking_date ASC, b.booking_time ASC"
    );
    $stmt->execute([session_tenant_id()]);
    json_out($stmt->fetchAll());
}

if ($method === 'POST') {
    $tenant = resolve_public_tenant($pdo);
    $tenantId = $tenant['id'];

    $input = body_json();
    $serviceId = (int) ($input['service_id'] ?? 0);
    $date = $input['date'] ?? '';
    $time = $input['time'] ?? '';
    $name = trim($input['name'] ?? '');
    $phone = trim($input['phone'] ?? '');
    $email = trim($input['email'] ?? '');

    if (!$serviceId || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || !preg_match('/^\d{2}:\d{2}$/', $time) || !$name || !$phone) {
        json_out(['error' => 'נא למלא את כל השדות'], 400);
    }
    if ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        json_out(['error' => 'כתובת האימייל אינה תקינה'], 400);
    }

    $stmt = $pdo->prepare('SELECT * FROM services WHERE id=? AND tenant_id=? AND active=1');
    $stmt->execute([$serviceId, $tenantId]);
    $service = $stmt->fetch();
    if (!$service) json_out(['error' => 'שירות לא נמצא'], 404);
    $duration = (int) $service['duration_minutes'];

    $newStart = DateTime::createFromFormat('Y-m-d H:i', "$date $time");
    $newEnd = (clone $newStart)->modify("+{$duration} minutes");
    if ($newStart < new DateTime()) json_out(['error' => 'לא ניתן לקבוע תור בעבר'], 400);

    $stmt = $pdo->prepare(
        'SELECT b.booking_time, s.duration_minutes FROM bookings b
         JOIN services s ON s.id = b.service_id WHERE b.tenant_id = ? AND b.booking_date = ? AND b.status = "confirmed"'
    );
    $stmt->execute([$tenantId, $date]);
    foreach ($stmt->fetchAll() as $b) {
        $bStart = DateTime::createFromFormat('Y-m-d H:i', $date . ' ' . substr($b['booking_time'], 0, 5));
        $bEnd = (clone $bStart)->modify('+' . (int) $b['duration_minutes'] . ' minutes');
        if ($newStart < $bEnd && $newEnd > $bStart) {
            json_out(['error' => 'השעה הזו נתפסה זה עתה, נא לבחור שעה אחרת'], 409);
        }
    }

    $stmt = $pdo->prepare('INSERT INTO bookings (tenant_id, service_id, customer_name, customer_phone, customer_email, booking_date, booking_time, status) VALUES (?,?,?,?,?,?,?,"confirmed")');
    $stmt->execute([$tenantId, $serviceId, $name, $phone, $email ?: null, $date, $time]);

    $booking = [
        'booking_date' => $date, 'booking_time' => $time,
        'customer_name' => $name, 'customer_phone' => $phone, 'customer_email' => $email,
    ];
    notify_booking($pdo, $tenantId, $booking, $service, 'תור חדש נקבע', 'התור שלך אושר בהצלחה');

    json_out(['ok' => true, 'summary' => $service['name'] . ', ' . $date . ' בשעה ' . $time]);
}

if ($method === 'PUT') {
    $isTenantAdmin = !empty($_SESSION['admin_id']) && !empty($_SESSION['tenant_id']);
    $input = body_json();
    $id = (int) ($input['id'] ?? 0);
    $action = $input['action'] ?? '';
    if (!$id) json_out(['error' => 'missing id'], 400);

    // A logged-in tenant admin acts on their own tenant's booking; the public flow
    // (customer self-service) must supply ?slug= and prove ownership via phone match.
    if ($isTenantAdmin) {
        $tenantId = session_tenant_id();
    } else {
        $tenant = resolve_public_tenant($pdo);
        $tenantId = $tenant['id'];
    }

    $stmt = $pdo->prepare(
        'SELECT b.*, s.name AS service_name, s.duration_minutes FROM bookings b
         JOIN services s ON s.id = b.service_id WHERE b.id = ? AND b.tenant_id = ?'
    );
    $stmt->execute([$id, $tenantId]);
    $booking = $stmt->fetch();
    if (!$booking) json_out(['error' => 'תור לא נמצא'], 404);

    // Customers may only manage their own booking, proven by matching the phone they booked with.
    if (!$isTenantAdmin) {
        $phone = normalize_phone($input['phone'] ?? '');
        if (!$phone || $phone !== normalize_phone($booking['customer_phone'])) {
            json_out(['error' => 'מספר הטלפון אינו תואם לתור זה'], 403);
        }
        if ($booking['status'] !== 'confirmed') json_out(['error' => 'התור הזה כבר בוטל'], 400);
    }

    if ($action === 'cancel') {
        $pdo->prepare('UPDATE bookings SET status="cancelled" WHERE id=? AND tenant_id=?')->execute([$id, $tenantId]);
        notify_booking($pdo, $tenantId, $booking, ['name' => $booking['service_name']], 'התור בוטל', 'התור שלך בוטל');
        json_out(['ok' => true]);
    }

    if ($action === 'reschedule') {
        $date = $input['date'] ?? '';
        $time = $input['time'] ?? '';
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || !preg_match('/^\d{2}:\d{2}$/', $time)) {
            json_out(['error' => 'תאריך/שעה לא תקינים'], 400);
        }
        $duration = (int) $booking['duration_minutes'];
        $newStart = DateTime::createFromFormat('Y-m-d H:i', "$date $time");
        $newEnd = (clone $newStart)->modify("+{$duration} minutes");
        if ($newStart < new DateTime()) json_out(['error' => 'לא ניתן לקבוע תור בעבר'], 400);

        $stmt = $pdo->prepare(
            'SELECT b.booking_time, s.duration_minutes FROM bookings b
             JOIN services s ON s.id = b.service_id
             WHERE b.tenant_id = ? AND b.booking_date = ? AND b.status = "confirmed" AND b.id != ?'
        );
        $stmt->execute([$tenantId, $date, $id]);
        foreach ($stmt->fetchAll() as $b) {
            $bStart = DateTime::createFromFormat('Y-m-d H:i', $date . ' ' . substr($b['booking_time'], 0, 5));
            $bEnd = (clone $bStart)->modify('+' . (int) $b['duration_minutes'] . ' minutes');
            if ($newStart < $bEnd && $newEnd > $bStart) {
                json_out(['error' => 'השעה הזו תפוסה כבר'], 409);
            }
        }

        $pdo->prepare('UPDATE bookings SET booking_date=?, booking_time=?, status="confirmed" WHERE id=? AND tenant_id=?')->execute([$date, $time, $id, $tenantId]);
        $booking['booking_date'] = $date; $booking['booking_time'] = $time;
        notify_booking($pdo, $tenantId, $booking, ['name' => $booking['service_name']], 'התור עודכן', 'התור שלך עודכן לזמן חדש');
        json_out(['ok' => true]);
    }

    json_out(['error' => 'unknown action'], 400);
}

json_out(['error' => 'method not allowed'], 405);
