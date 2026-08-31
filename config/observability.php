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
        // failures, or a failure ratio above max_failure_rate. Only
        // broadcasts that are dead-lettered or past their retry budget
        // are alerted — in-flight retries are not yet "failures".
        'min_failed' => (int) env('OBSERVABILITY_MIN_FAILED', 5),
        'max_failure_rate' => 0.10,
        'lookback_hours' => 24,
    ],

    'broadcast_delivery_retry' => [
        // Exponential backoff for failed broadcast deliveries.
        // next_retry = base_minutes * factor^retry_count, capped.
        'base_interval_minutes' => (int) env('OBSERVABILITY_RETRY_BASE_MINUTES', 15),
        'backoff_factor' => 2,
        'max_interval_minutes' => 240,
        // Dead-letter a broadcast once retries are exhausted and failures
        // remain. Set to 0 to disable retries (dead-letter on first scan).
        'max_retries' => (int) env('OBSERVABILITY_MAX_RETRIES', 3),
        // Simulated per-retry recovery: ceil(failed * num / den) recipients
        // are moved from failed → delivered on each attempt. Replaces the
        // placeholder receipts when the channel adapter has no real acks.
        'recovery_numerator' => 1,
        'recovery_denominator' => 2,
        // Weighted taxonomy for the initial failure breakdown. Weights must
        // sum to 100; the remainder lands on the largest bucket.
        'failure_weights' => [
            'offline' => 40,
            'connection_failed' => 25,
            'timeout' => 20,
            'unauthorized' => 10,
            'rejected' => 5,
        ],
    ],
];
