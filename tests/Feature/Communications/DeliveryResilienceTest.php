<?php

declare(strict_types=1);

use App\Domains\Communications\Events\BroadcastDeliveryFailureDetected;
use App\Domains\Communications\Services\CompleteBroadcast;
use App\Domains\Communications\Support\BroadcastFailureReasonWeights;
use App\Enums\BroadcastDeliveryFailureReason;
use App\Enums\BroadcastStatus;
use App\Enums\CommunicationAudience;
use App\Enums\CommunicationChannel;
use App\Enums\CurrencyCode;
use App\Models\Broadcast;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

/**
 * Track 5 — communications resilience: failure taxonomy, retry/backoff and
 * dead-letter handling for broadcast deliveries.
 */
beforeEach(function (): void {
    $this->tenant = makeTenant();
    bindTenant($this->tenant);

    $this->makeBroadcast = function (int $recipients, int $delivered, int $failed, array $overrides = []): Broadcast {
        return Broadcast::create(array_merge([
            'tenant_id' => $this->tenant->id,
            'name' => 'Term fee reminder',
            'channel' => CommunicationChannel::InApp,
            'audience' => CommunicationAudience::Guardians,
            'audience_label' => 'All guardians',
            'template_snippet' => 'Reminder…',
            'status' => BroadcastStatus::Completed,
            'completed_at' => Carbon::now(),
            'recipient_count' => $recipients,
            'delivered_count' => $delivered,
            'failed_count' => $failed,
            'cost_minor' => 0,
            'currency' => CurrencyCode::MWK,
        ], $overrides));
    };
});

it('distributes a failure count exactly across the taxonomy', function (): void {
    $out = BroadcastFailureReasonWeights::distribute(10, [
        'offline' => 40,
        'connection_failed' => 25,
        'timeout' => 20,
        'unauthorized' => 10,
        'rejected' => 5,
    ]);

    expect(array_sum($out))->toBe(10)
        // Proportional shares: 4 + 2 + 2 + 1 + 0 = 9, remainder 1 → largest bucket.
        ->and($out['offline'])->toBe(5)
        ->and($out['connection_failed'])->toBe(2)
        ->and($out['timeout'])->toBe(2)
        ->and($out['unauthorized'])->toBe(1)
        ->and($out['rejected'])->toBe(0);
});

it('keeps every taxonomy label distinct', function (): void {
    $labels = collect(BroadcastDeliveryFailureReason::options())->pluck('label')->all();
    expect(count($labels))->toBe(count(array_unique($labels)))
        ->and(BroadcastDeliveryFailureReason::cases())->toHaveCount(5);
});

it('seeds the failure taxonomy and arms the first retry on completion', function (): void {
    $b = ($this->makeBroadcast)(100, 0, 0, ['status' => BroadcastStatus::Sending]);

    app(CompleteBroadcast::class)->handle($b);

    $row = $b->fresh();
    expect($row->status)->toBe(BroadcastStatus::Completed)
        ->and((int) $row->failed_count)->toBe(3) // 100 − 97
        ->and($row->failure_reasons)->toBeArray()
        ->and(array_sum($row->failure_reasons ?? []))->toBe(3)
        ->and($row->delivery_next_retry_at)->not->toBeNull()
        ->and((int) round(now()->diffInMinutes($row->delivery_next_retry_at)))->toBe(15);
});

it('retries with exponential backoff and honours the backoff gate', function (): void {
    Carbon::setTestNow(Carbon::parse('2026-08-31 09:00:00'));

    $b = ($this->makeBroadcast)(100, 97, 3);

    // First scan: recover ceil(3 × 1/2) = 2 → 1 failure left, retry #1,
    // next attempt at 09:00 + 15·2¹ = 09:30.
    $this->artisan('schoolos:retry-broadcast-deliveries')->assertExitCode(0);
    $b->refresh();
    expect((int) $b->delivered_count)->toBe(99)
        ->and((int) $b->failed_count)->toBe(1)
        ->and((int) $b->delivery_retry_count)->toBe(1)
        ->and($b->delivery_next_retry_at->format('H:i'))->toBe('09:30');

    // Running again before the gate opens does nothing.
    $this->artisan('schoolos:retry-broadcast-deliveries')->assertExitCode(0);
    $b->refresh();
    expect((int) $b->delivery_retry_count)->toBe(1)
        ->and((int) $b->failed_count)->toBe(1);

    // After the gate, retry #2 doubles the interval from now: 09:31 + 60 = 10:31.
    Carbon::setTestNow(Carbon::parse('2026-08-31 09:31:00'));
    $this->artisan('schoolos:retry-broadcast-deliveries')->assertExitCode(0);
    $b->refresh();
    expect((int) $b->delivery_retry_count)->toBe(2)
        ->and((int) $b->failed_count)->toBe(0) // recovered the last one
        ->and($b->delivery_next_retry_at->format('H:i'))->toBe('10:31');

    Carbon::setTestNow();
});

it('dead-letters a broadcast once the retry budget is exhausted', function (): void {
    Carbon::setTestNow(Carbon::parse('2026-08-31 09:00:00'));
    config(['observability.broadcast_delivery_retry.max_retries' => 1]);

    // 100 recipients, 30 failed: retry #1 recovers 15 → 15 left, then the
    // next scan finds retry_count (1) >= max_retries (1) → dead-lettered.
    $b = ($this->makeBroadcast)(100, 70, 30, ['delivery_next_retry_at' => Carbon::now()]);

    $this->artisan('schoolos:retry-broadcast-deliveries')->assertExitCode(0);
    $b->refresh();
    expect((int) $b->delivery_retry_count)->toBe(1)
        ->and((int) $b->failed_count)->toBe(15)
        ->and($b->delivery_dead_lettered_at)->toBeNull();

    // Next scan after the backoff gate: retry budget (1) is exhausted → dead-letter.
    Carbon::setTestNow(Carbon::parse('2026-08-31 09:31:00'));
    $this->artisan('schoolos:retry-broadcast-deliveries')->assertExitCode(0);
    $b->refresh();
    expect((int) $b->delivery_retry_count)->toBe(1)
        ->and((int) $b->failed_count)->toBe(15)
        ->and($b->delivery_dead_lettered_at)->not->toBeNull();

    Carbon::setTestNow();
});

it('alerts only dead-lettered or retry-exhausted broadcasts, deduped', function (): void {
    Event::fake([BroadcastDeliveryFailureDetected::class]);

    // Still within its retry budget → no alert yet.
    $inFlight = ($this->makeBroadcast)(100, 80, 20);
    // Retry budget exhausted (max_retries=3, retried 3×) → alert.
    $exhausted = ($this->makeBroadcast)(100, 80, 20, ['delivery_retry_count' => 3]);
    // Dead-lettered → alert.
    $deadLettered = ($this->makeBroadcast)(100, 80, 20, [
        'delivery_retry_count' => 2,
        'delivery_dead_lettered_at' => Carbon::now(),
        'failure_reasons' => ['offline' => 8, 'timeout' => 12],
    ]);
    // Below the failure-rate threshold → no alert.
    $healthy = ($this->makeBroadcast)(1000, 990, 10, ['delivery_retry_count' => 3]);

    $this->artisan('schoolos:check-broadcast-deliveries')->assertExitCode(0);

    Event::assertDispatchedTimes(BroadcastDeliveryFailureDetected::class, 2);

    // Dedupe: a second scan emits nothing more.
    $this->artisan('schoolos:check-broadcast-deliveries')->assertExitCode(0);
    Event::assertDispatchedTimes(BroadcastDeliveryFailureDetected::class, 2);

    // The alert payload carries the taxonomy + retry/dead-letter state.
    Event::assertDispatched(BroadcastDeliveryFailureDetected::class, function ($event): bool {
        $payload = $event->payload();

        return $payload['dead_lettered'] === true
            && $payload['failure_reasons'] === ['offline' => 8, 'timeout' => 12]
            && $payload['retry_count'] === 2;
    });
});
