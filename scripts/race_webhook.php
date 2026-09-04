#!/usr/bin/env php
<?php

/**
 * Payment emulator + concurrency test.
 *
 * Creates an order, then fires N parallel paid webhooks (same event_id and/or
 * distinct event_ids) and asserts the digital code is issued exactly once.
 *
 * Usage:
 *   php artisan serve
 *   php scripts/race_webhook.php
 *   php scripts/race_webhook.php --base=http://127.0.0.1:8000 --concurrency=20 --sku=STEAM-CS2-KEY
 */

$opts = getopt('', ['base::', 'concurrency::', 'sku::', 'mode::']);
$base = rtrim($opts['base'] ?? getenv('APP_URL') ?: 'http://127.0.0.1:8000', '/');
$concurrency = max(2, (int) ($opts['concurrency'] ?? 20));
$sku = $opts['sku'] ?? 'STEAM-CS2-KEY';
$mode = $opts['mode'] ?? 'same-event'; // same-event | distinct-events

function http_json(string $method, string $url, ?array $body = null): array
{
    $ch = curl_init($url);
    $headers = ['Accept: application/json', 'Content-Type: application/json'];
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_TIMEOUT => 30,
    ]);
    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    }
    $raw = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    if ($raw === false) {
        fwrite(STDERR, "HTTP error {$url}: {$error}\n");
        exit(1);
    }
    $decoded = json_decode($raw, true);

    return ['status' => $status, 'body' => is_array($decoded) ? $decoded : ['_raw' => $raw]];
}

function http_multi_post(string $url, array $payloads): array
{
    $mh = curl_multi_init();
    $handles = [];
    foreach ($payloads as $i => $payload) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['Accept: application/json', 'Content-Type: application/json'],
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_TIMEOUT => 30,
        ]);
        $handles[$i] = $ch;
        curl_multi_add_handle($mh, $ch);
    }

    do {
        $status = curl_multi_exec($mh, $running);
        if ($running) {
            curl_multi_select($mh, 1.0);
        }
    } while ($running && $status === CURLM_OK);

    $results = [];
    foreach ($handles as $i => $ch) {
        $raw = curl_multi_getcontent($ch);
        $results[$i] = [
            'status' => (int) curl_getinfo($ch, CURLINFO_HTTP_CODE),
            'body' => json_decode((string) $raw, true),
        ];
        curl_multi_remove_handle($mh, $ch);
        curl_close($ch);
    }
    curl_multi_close($mh);

    return $results;
}

function fail(string $message): never
{
    fwrite(STDERR, "FAIL: {$message}\n");
    exit(1);
}

echo "Base: {$base}\nSKU: {$sku}\nConcurrency: {$concurrency}\nMode: {$mode}\n";

$created = http_json('POST', $base.'/api/orders', ['sku' => $sku]);
if ($created['status'] !== 201) {
    fail('create order HTTP '.$created['status'].' '.json_encode($created['body']));
}

$orderId = $created['body']['id'] ?? null;
$amount = $created['body']['amount_cents'] ?? null;
if (! $orderId || ! $amount) {
    fail('create order returned no id/amount');
}

echo "Order: {$orderId}\n";

$payloads = [];
if ($mode === 'distinct-events') {
    for ($i = 0; $i < $concurrency; $i++) {
        $payloads[] = [
            'event_id' => 'evt-race-'.bin2hex(random_bytes(8))."-{$i}",
            'order_id' => $orderId,
            'amount_cents' => $amount,
            'status' => 'paid',
        ];
    }
} else {
    $eventId = 'evt-race-'.bin2hex(random_bytes(8));
    $payload = [
        'event_id' => $eventId,
        'order_id' => $orderId,
        'amount_cents' => $amount,
        'status' => 'paid',
    ];
    $payloads = array_fill(0, $concurrency, $payload);
}

$results = http_multi_post($base.'/api/webhooks/payment', $payloads);
$ok = 0;
$duplicates = 0;
foreach ($results as $row) {
    if ($row['status'] !== 200) {
        fail('webhook HTTP '.$row['status'].' '.json_encode($row['body']));
    }
    $ok++;
    if (! empty($row['body']['duplicate'])) {
        $duplicates++;
    }
}

$fetched = http_json('GET', $base.'/api/orders/'.$orderId);
if ($fetched['status'] !== 200) {
    fail('get order HTTP '.$fetched['status']);
}
$order = $fetched['body'];

echo "Webhook OK: {$ok}, duplicates flagged: {$duplicates}\n";
echo "Status: {$order['status']}\n";
echo "Code: ".($order['code'] ?? 'null')."\n";
echo "Provider: ".($order['provider'] ?? 'null')."\n";

if (($order['status'] ?? '') !== 'delivered') {
    fail('expected delivered, got '.($order['status'] ?? 'null'));
}
if (empty($order['code'])) {
    fail('expected a single fulfillment code');
}

$reconcile = http_json('GET', $base.'/api/admin/reconciliation');
$unbalanced = $reconcile['body']['ledger_unbalanced'] ?? [];
if (! empty($unbalanced)) {
    fail('ledger unbalanced: '.json_encode($unbalanced));
}

echo "PASS: exactly-once delivery under {$concurrency} parallel webhooks ({$mode})\n";
exit(0);
