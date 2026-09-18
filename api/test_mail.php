<?php
require __DIR__ . '/../config.php';
require __DIR__ . '/../includes/mailer.php';

require_tenant_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_out(['error' => 'method not allowed'], 405);

$input = body_json();
$to = trim($input['to'] ?? '');
if (!$to || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
    json_out(['error' => 'נא להזין כתובת אימייל תקינה לבדיקה'], 400);
}

$result = send_app_mail_verbose(
    $pdo, session_tenant_id(), $to, '',
    'מייל בדיקה מהאתר',
    booking_email_html('מייל בדיקה', ['סטטוס' => 'אם קיבלת את המייל הזה, ההגדרות תקינות!'])
);

if ($result['ok']) {
    json_out(['ok' => true]);
} else {
    json_out(['error' => $result['error']], 400);
}
