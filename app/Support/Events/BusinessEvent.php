<?php

declare(strict_types=1);

namespace App\Support\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

/**
 * Base class for capability domain events (Business Events in the
 * SchoolOS handbook). Every mutation of consequence emits one of these
 * so downstream capabilities can react without direct coupling.
 *
 * Concrete events extend this and expose named-constructor factories,
 * e.g. `StudentEnrolled::from($student)`.
 */
abstract class BusinessEvent
{
    use Dispatchable;
    use SerializesModels;

    public readonly string $eventId;

    public readonly string $occurredAt;

    public function __construct(
        public readonly string $tenantId,
    ) {
        $this->eventId = (string) Str::uuid7();
        $this->occurredAt = now()->toIso8601String();
    }

    /**
     * Stable dot-notation name for the event, e.g. "identity.user.invited".
     * Used by listeners, audit logs, and future outbox publishing.
     */
    abstract public function name(): string;

    /**
     * Payload persisted to the audit log / outbox.
     *
     * @return array<string, mixed>
     */
    abstract public function payload(): array;
}
