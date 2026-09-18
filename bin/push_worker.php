<?php
// Runs as a separate CLI process (spawned by includes/push.php) so it can receive
// OPENSSL_CONF in its own process environment — see the comment in config.php for why
// that's needed on Windows/XAMPP. Talks to the parent over stdin/stdout as JSON only;
// never emit warnings/notices to stdout, they'd corrupt that JSON.
error_reporting(0);
ini_set('display_errors', '0');

require __DIR__ . '/../vendor/autoload.php';

use Minishlink\WebPush\WebPush;
use Minishlink\WebPush\Subscription;

$input = json_decode(file_get_contents('php://stdin'), true);
$result = ['expired_ids' => []];

try {
    if (!is_array($input) || empty($input['subscriptions'])) {
        echo json_encode($result);
        exit;
    }

    $webPush = new WebPush(['VAPID' => $input['vapid']]);
    $webPush->setReuseVAPIDHeaders(true);

    $idByEndpoint = [];
    foreach ($input['subscriptions'] as $s) {
        $idByEndpoint[$s['endpoint']] = $s['id'];
        $webPush->queueNotification(
            Subscription::create([
                'endpoint' => $s['endpoint'],
                'publicKey' => $s['p256dh'],
                'authToken' => $s['auth'],
                'contentEncoding' => 'aes128gcm',
            ]),
            json_encode($input['payload'])
        );
    }

    foreach ($webPush->flush() as $report) {
        if (!$report->isSuccess() && $report->isSubscriptionExpired()) {
            $endpoint = $report->getEndpoint();
            if (isset($idByEndpoint[$endpoint])) {
                $result['expired_ids'][] = $idByEndpoint[$endpoint];
            }
        }
    }
} catch (\Throwable $e) {
    $result['error'] = $e->getMessage();
}

echo json_encode($result);
