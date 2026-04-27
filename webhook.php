<?php

declare(strict_types=1);

/**
 * מאזין Zoom Webhook — אימות, url_validation, ואופציונלית הקצאת רישיון דרך API.
 *
 * Secret Token מאמת בקשות בלבד. להקצאת רישיון צריך גם Server-to-Server OAuth בקונפיג.
 * דרישות PHP: curl, json, hash
 */

header('Content-Type: application/json; charset=utf-8');

$configPath = __DIR__ . '/config.php';
if (! is_file($configPath)) {
    http_response_code(500);
    echo json_encode(['error' => 'Missing config.php — copy from config.example.php']);
    exit;
}

/** @var array $config */
$config = require $configPath;

require_once __DIR__ . '/src/ZoomWebhookVerifier.php';

$rawBody = file_get_contents('php://input') ?: '';

function log_webhook(array $config, string $message, array $context = []): void
{
    $path = $config['log_file'] ?? null;
    if (! $path || $path === '') {
        return;
    }
    $dir = dirname($path);
    if (! is_dir($dir)) {
        @mkdir($dir, 0750, true);
    }
    $line = date('c') . ' ' . $message . ' ' . json_encode($context, JSON_UNESCAPED_UNICODE) . "\n";
    @file_put_contents($path, $line, FILE_APPEND | LOCK_EX);
}

$secret = (string) ($config['webhook_secret_token'] ?? '');
$verifier = new ZoomWebhookVerifier($secret);

$signature = $_SERVER['HTTP_X_ZM_SIGNATURE'] ?? null;
$timestamp = $_SERVER['HTTP_X_ZM_REQUEST_TIMESTAMP'] ?? null;

if ($secret !== '' && ! $verifier->verify($rawBody, $timestamp, $signature)) {
    log_webhook($config, 'signature_failed', ['ip' => $_SERVER['REMOTE_ADDR'] ?? '']);
    http_response_code(401);
    echo json_encode(['error' => 'Invalid signature']);
    exit;
}

$payload = json_decode($rawBody, true);
if (! is_array($payload)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON']);
    exit;
}

// אימות URL מה-Marketplace
$event = $payload['event'] ?? '';
if ($event === 'endpoint.url_validation') {
    $plain = $payload['payload']['plainToken'] ?? '';
    if ($plain === '' || $secret === '') {
        http_response_code(400);
        echo json_encode(['error' => 'plainToken or secret missing']);
        exit;
    }
    $out = $verifier->urlValidationResponse($plain);
    echo json_encode($out);
    exit;
}

/**
 * חילוץ מזהה משתמש ממבנים נפוצים של payload ב-Zoom.
 */
function extract_user_id(array $payload): ?string
{
    $p = $payload['payload'] ?? null;
    if (! is_array($p)) {
        return null;
    }

    if (isset($p['object']['id']) && is_string($p['object']['id'])) {
        return $p['object']['id'];
    }

    if (isset($p['object']['user_id']) && is_string($p['object']['user_id'])) {
        return $p['object']['user_id'];
    }

    return null;
}

$licenseEvents = $config['license_on_events'] ?? ['user.created', 'user.invitation_accepted'];

if (! in_array($event, $licenseEvents, true)) {
    log_webhook($config, 'event_ignored', ['event' => $event]);
    http_response_code(200);
    echo json_encode(['status' => 'ignored', 'event' => $event]);
    exit;
}

$userId = extract_user_id($payload);
if ($userId === null) {
    log_webhook($config, 'no_user_id', ['event' => $event, 'payload_keys' => array_keys($payload)]);
    http_response_code(200);
    echo json_encode(['status' => 'no_user_in_payload']);
    exit;
}

$accountId = (string) ($config['account_id'] ?? '');
$clientId = (string) ($config['client_id'] ?? '');
$clientSecret = (string) ($config['client_secret'] ?? '');

if ($accountId === '' || $clientId === '' || $clientSecret === '') {
    log_webhook($config, 'event_received_no_api_credentials', [
        'user_id' => $userId,
        'event' => $event,
        'hint' => 'Add S2S OAuth (account_id, client_id, client_secret) to assign license via API.',
    ]);
    http_response_code(200);
    echo json_encode([
        'status' => 'ok',
        'license' => 'skipped',
        'reason' => 'Zoom REST API credentials not configured (Secret Token cannot call the API)',
        'user_id' => $userId,
    ]);
    exit;
}

require_once __DIR__ . '/src/ZoomApiClient.php';

try {
    $api = new ZoomApiClient($accountId, $clientId, $clientSecret);
    $result = $api->assignLicensedUser($userId);
    log_webhook($config, 'license_assigned', [
        'user_id' => $userId,
        'http_code' => $result['http_code'],
    ]);

    if ($result['http_code'] >= 400) {
        http_response_code(200);
        echo json_encode([
            'status' => 'api_error',
            'http_code' => $result['http_code'],
            'detail' => $result['body'],
        ]);
        exit;
    }

    http_response_code(200);
    echo json_encode(['status' => 'licensed', 'user_id' => $userId]);
} catch (Throwable $e) {
    log_webhook($config, 'exception', ['message' => $e->getMessage(), 'user_id' => $userId]);
    http_response_code(500);
    echo json_encode(['error' => 'Internal error']);
}
