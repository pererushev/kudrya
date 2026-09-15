<?php

return [
    'webhook_secret' => env('PAYMENT_WEBHOOK_SECRET'),

    'fulfillment' => [
        'client_timeout_ms' => (int) env('PROVIDER_TIMEOUT_MS', 2000),
        'status_attempts' => (int) env('PROVIDER_STATUS_ATTEMPTS', 3),
        'status_backoff_ms' => (int) env('PROVIDER_STATUS_BACKOFF_MS', 50),
        'stuck_after_seconds' => (int) env('ORDER_STUCK_AFTER_SECONDS', 30),
        'issue_attempts' => (int) env('PROVIDER_ISSUE_ATTEMPTS', 3),
        'issue_backoff_ms' => (int) env('PROVIDER_ISSUE_BACKOFF_MS', 50),
    ],

    'providers' => [
        'chaos' => (bool) env('PROVIDER_CHAOS', false),
        'a' => [
            'fail_rate' => (float) env('PROVIDER_A_FAIL_RATE', 0.2),
            'timeout_rate' => (float) env('PROVIDER_A_TIMEOUT_RATE', 0.2),
        ],
        'b' => [
            'fail_rate' => (float) env('PROVIDER_B_FAIL_RATE', 0.15),
            'timeout_rate' => (float) env('PROVIDER_B_TIMEOUT_RATE', 0.15),
        ],
    ],
];
