<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Models\AuditEvent;
use App\Models\User;
use App\Support\Events\BusinessEvent;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Throwable;

/**
 * Projects every dispatched BusinessEvent into the audit_events table.
 *
 * Registered as a wildcard listener in AuditServiceProvider so new
 * capabilities are covered automatically — no per-event wiring needed.
 * Audit writes must never break the originating request, so failures are
 * swallowed and logged.
 */
final class RecordBusinessEvent
{
    public function handle(BusinessEvent $event): void
    {
        try {
            $payload = $event->payload();

            $actorId = Arr::get($payload, 'actor_id');
            $actor = $actorId ? User::query()->find($actorId) : null;

            AuditEvent::create([
                'tenant_id' => $event->tenantId,
                'name' => $event->name(),
                'actor_id' => $actor?->id,
                'actor_name' => $actor?->name ?? 'System',
                'subject_type' => $this->subjectType($event->name()),
                'subject_id' => Arr::get($payload, 'subject_id')
                    ?? Arr::get($payload, 'user_id')
                    ?? Arr::get($payload, 'invitation_id')
                    ?? Arr::get($payload, 'role_id'),
                'subject_label' => Arr::get($payload, 'subject_label')
                    ?? Arr::get($payload, 'email')
                    ?? Arr::get($payload, 'name'),
                'summary' => $this->summarize($event->name(), $payload),
                'metadata' => Arr::except($payload, ['actor_id']),
                'occurred_at' => $event->occurredAt,
            ]);
        } catch (Throwable $e) {
            report($e);
        }
    }

    /** "identity.user.suspended" → "user" */
    private function subjectType(string $name): string
    {
        $parts = explode('.', $name);

        return $parts[1] ?? 'record';
    }

    /** @param array<string,mixed> $payload */
    private function summarize(string $name, array $payload): string
    {
        $label = Arr::get($payload, 'subject_label')
            ?? Arr::get($payload, 'email')
            ?? Arr::get($payload, 'name')
            ?? Arr::get($payload, 'subject_id')
            ?? 'record';

        $verb = Str::of($name)->afterLast('.')->replace('_', ' ')->toString();

        return ucfirst($verb).': '.$label;
    }
}
