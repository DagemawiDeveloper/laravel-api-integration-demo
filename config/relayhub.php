<?php

return [
    'route_prefix' => env('RELAYHUB_ROUTE_PREFIX', 'api/relayhub'),

    'inbound_secret' => env('RELAYHUB_INBOUND_SECRET'),

    'outbound' => [
        'url' => env('RELAYHUB_OUTBOUND_URL'),
        'secret' => env('RELAYHUB_OUTBOUND_SECRET'),
        'timeout' => (int) env('RELAYHUB_TIMEOUT', 10),
        'connect_timeout' => (int) env('RELAYHUB_CONNECT_TIMEOUT', 3),
        'max_attempts' => (int) env('RELAYHUB_MAX_ATTEMPTS', 5),
        'backoff_seconds' => [10, 30, 120, 300],
    ],

    'queue' => env('RELAYHUB_QUEUE', 'integrations'),

    'idempotency_ttl_minutes' => (int) env('RELAYHUB_IDEMPOTENCY_TTL', 1440),
];
