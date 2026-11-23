<?php
/**
 * Example 03 — Webhook endpoint handler.
 *
 * Deploy this as HTTP endpoint (e.g. /webhooks) in your app.
 * Configure Shoper AppStore webhook to POST here.
 */
require __DIR__ . '/../vendor/autoload.php';

use Shoper\Sdk\WebhookVerifier;

$appstoreSecret = getenv('SHOPER_APPSTORE_SECRET');
$webhookSecret = getenv('SHOPER_WEBHOOK_SECRET');

$installedShops = ['shop-hash-1' => true];
$deliveryDir = sys_get_temp_dir() . '/shoper-webhook-deliveries';
if (!is_dir($deliveryDir)) {
    mkdir($deliveryDir, 0700, true);
}

$verifier = (new WebhookVerifier((string) $appstoreSecret, (string) $webhookSecret))
    ->withShopGuard(static fn (string $shopHash): bool => isset($installedShops[$shopHash]))
    ->withReplayGuard(static function (string $deliveryId, int $timestamp) use ($deliveryDir): bool {
        $marker = @fopen($deliveryDir . '/' . hash('sha256', $deliveryId), 'x');
        if ($marker === false) {
            return false;
        }
        fwrite($marker, (string) $timestamp);
        fclose($marker);
        return true;
    });

$rawBody = file_get_contents('php://input');
if (!$verifier->verifyFromGlobals($rawBody)) {
    http_response_code(401);
    echo json_encode(['error' => 'Invalid webhook signature']);
    exit;
}

$payload = json_decode($rawBody, true);
$event = $payload['event'] ?? '';

switch ($event) {
    case 'order.created':
        // handle new order
        break;
    case 'product.updated':
        // handle product update
        break;
}

http_response_code(200);
echo json_encode(['ok' => true]);
