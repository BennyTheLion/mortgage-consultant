<?php
/**
 * Sends a real browser push notification to every device the advisor enabled push on.
 * Never throws — a broken push setup should never break the booking flow itself.
 */
function send_push_notifications($pdo, $tenantId, $title, $body, $url = null) {
    try {
        if (!VAPID_PUBLIC_KEY || !VAPID_PRIVATE_KEY) return; // not generated on this install

        // Only this tenant's own admins — never another business's devices.
        $stmt = $pdo->prepare(
            'SELECT ps.id, ps.endpoint, ps.p256dh, ps.auth FROM push_subscriptions ps
             JOIN admins a ON a.id = ps.admin_id WHERE a.tenant_id = ?'
        );
        $stmt->execute([$tenantId]);
        $subs = $stmt->fetchAll();
        if (!$subs) return;

        $row = $pdo->prepare("SELECT setting_value FROM settings WHERE tenant_id = ? AND setting_key='admin_notification_email'");
        $row->execute([$tenantId]);
        $row = $row->fetch();
        $subject = !empty($row['setting_value']) ? 'mailto:' . $row['setting_value'] : 'mailto:admin@example.com';

        $input = json_encode([
            'vapid' => ['subject' => $subject, 'publicKey' => VAPID_PUBLIC_KEY, 'privateKey' => VAPID_PRIVATE_KEY],
            'subscriptions' => array_map(function ($s) {
                return ['id' => $s['id'], 'endpoint' => $s['endpoint'], 'p256dh' => $s['p256dh'], 'auth' => $s['auth']];
            }, $subs),
            'payload' => ['title' => $title, 'body' => $body, 'url' => $url ?: 'admin.php'],
        ]);

        $env = getenv();
        if (OPENSSL_CNF_PATH) $env['OPENSSL_CONF'] = OPENSSL_CNF_PATH;

        $descriptors = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $process = proc_open([PHP_BINARY, __DIR__ . '/../bin/push_worker.php'], $descriptors, $pipes, __DIR__ . '/../bin', $env);
        if (!is_resource($process)) return;

        fwrite($pipes[0], $input);
        fclose($pipes[0]);
        $out = stream_get_contents($pipes[1]);
        $err = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($process);

        if ($err) error_log('Push worker stderr: ' . $err);

        $result = json_decode($out, true);
        if (!empty($result['error'])) error_log('Push worker error: ' . $result['error']);
        if (!empty($result['expired_ids'])) {
            $ids = array_map('intval', $result['expired_ids']);
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $pdo->prepare("DELETE FROM push_subscriptions WHERE id IN ($placeholders)")->execute($ids);
        }
    } catch (\Throwable $e) {
        error_log('Push notification failed: ' . $e->getMessage());
    }
}
