<?php
require_once __DIR__ . '/../vendor/phpmailer/src/Exception.php';
require_once __DIR__ . '/../vendor/phpmailer/src/PHPMailer.php';
require_once __DIR__ . '/../vendor/phpmailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;

/**
 * Sends an HTML email using the SMTP settings configured in the admin panel.
 * Returns true on success, false on failure (or if mail is disabled/unconfigured).
 * Never throws — a broken mail setup should never break the booking flow itself.
 */
function send_app_mail($pdo, $tenantId, $toEmail, $toName, $subject, $htmlBody) {
    $result = send_app_mail_verbose($pdo, $tenantId, $toEmail, $toName, $subject, $htmlBody);
    return $result['ok'];
}

/**
 * Same as send_app_mail() but always returns ['ok' => bool, 'error' => string|null],
 * including the reason it was skipped or failed — used by the admin test-mail tool.
 */
function send_app_mail_verbose($pdo, $tenantId, $toEmail, $toName, $subject, $htmlBody) {
    if (!$toEmail) return ['ok' => false, 'error' => 'לא צוינה כתובת יעד'];

    $stmt = $pdo->prepare(
        "SELECT setting_key, setting_value FROM settings WHERE tenant_id = ? AND setting_key IN
         ('mail_enabled','smtp_host','smtp_port','smtp_username','smtp_password','smtp_secure','smtp_from_email','smtp_from_name')"
    );
    $stmt->execute([$tenantId]);
    $s = [];
    foreach ($stmt->fetchAll() as $r) $s[$r['setting_key']] = $r['setting_value'];

    if (empty($s['mail_enabled']) || $s['mail_enabled'] !== '1') {
        return ['ok' => false, 'error' => 'שליחת מיילים כבויה (סמנו "שליחת מיילים פעילה" בהגדרות ושמרו)'];
    }
    if (empty($s['smtp_host']) || empty($s['smtp_username']) || empty($s['smtp_password'])) {
        return ['ok' => false, 'error' => 'חסרים פרטי SMTP (host / username / password)'];
    }
    if (!extension_loaded('openssl')) {
        return ['ok' => false, 'error' => 'הרחבת openssl אינה מופעלת ב-PHP, נדרשת לחיבור TLS/SSL'];
    }

    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = $s['smtp_host'];
        $mail->SMTPAuth = true;
        $mail->Username = $s['smtp_username'];
        $mail->Password = $s['smtp_password'];
        $mail->SMTPSecure = ($s['smtp_secure'] === 'ssl') ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = (int) ($s['smtp_port'] ?: 587);
        $mail->CharSet = 'UTF-8';

        $fromEmail = $s['smtp_from_email'] ?: $s['smtp_username'];
        $fromName = $s['smtp_from_name'] ?: 'ייעוץ משכנתאות';
        $mail->setFrom($fromEmail, $fromName);
        $mail->addAddress($toEmail, $toName ?: '');

        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $htmlBody;

        $mail->send();
        return ['ok' => true, 'error' => null];
    } catch (\Throwable $e) {
        error_log('Mail send failed: ' . $e->getMessage());
        return ['ok' => false, 'error' => $e->getMessage()];
    }
}

function booking_email_html($title, $lines) {
    $rows = '';
    foreach ($lines as $label => $value) {
        $rows .= '<tr><td style="padding:6px 10px;color:#666;font-size:13px;">' . htmlspecialchars($label) . '</td>'
               . '<td style="padding:6px 10px;font-size:14px;font-weight:600;">' . htmlspecialchars($value) . '</td></tr>';
    }
    return '<div dir="rtl" style="font-family:Arial,sans-serif;max-width:420px;margin:0 auto;">'
         . '<h2 style="color:#1a1a1a;">' . htmlspecialchars($title) . '</h2>'
         . '<table style="border-collapse:collapse;width:100%;">' . $rows . '</table>'
         . '</div>';
}
