<?php

declare(strict_types=1);

namespace App\Domains\Communications\Events;

use App\Models\Broadcast;
use App\Support\Events\BusinessEvent;

/**
 * Emitted by the observability scheduler when a completed broadcast's
 * delivery failure rate crosses the configured threshold. Drives the
 * in-app BroadcastDeliveryAlert to platform operators.
 */
final class BroadcastDeliveryFailureDetected extends BusinessEvent
{
    public function __construct(public readonly Broadcast $broadcast)
    {
        parent::__construct($broadcast->tenant_id);
    }

    public function name(): string
    {
        return 'observability.broadcast.delivery_failure';
    }

    public function payload(): array
    {
        return [
            'broadcast_id' => $this->broadcast->id,
            'recipient_count' => (int) $this->broadcast->recipient_count,
            'delivered_count' => (int) $this->broadcast->delivered_count,
            'failed_count' => (int) $this->broadcast->failed_count,
            'retry_count' => (int) $this->broadcast->delivery_retry_count,
            'dead_lettered' => $this->broadcast->delivery_dead_lettered_at !== null,
            'failure_reasons' => $this->broadcast->failure_reasons ?? [],
        ];
    }
}
