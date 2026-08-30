<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Observability
    |--------------------------------------------------------------------------
    |
    | Health checks for GET /api/v1/system/health and the scheduled
    | broadcast-delivery failure alert (schoolos:check-broadcast-deliveries).
    */

    'health' => [
        // Component probes; `ai_gateway` only runs when the AI assistant
        // is enabled (it otherwise reports "disabled", never "down").
        'checks' => [
            'database' => true,
            'cache' => true,
            'queue' => true,
            'ai_gateway' => (bool) env('INSIGHTS_AI_ENABLED', false),
        ],
        'ai_gateway_timeout' => 5, // seconds
    ],

    'broadcast_delivery_alert' => [
        // Alert when a completed broadcast has at least min_failed
        // failures, or a failure ratio above max_failure_rate.
        'min_failed' => (int) env('OBSERVABILITY_MIN_FAILED', 5),
        'max_failure_rate' => 0.10,
        'lookback_hours' => 24,
    ],
];
