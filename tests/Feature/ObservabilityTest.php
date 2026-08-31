<?php

declare(strict_types=1);

use App\Enums\BroadcastStatus;
use App\Models\Broadcast;
use App\Models\Notification;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->tenant = makeTenant();
    bindTenant($this->tenant);

    $this->principal = User::factory()->create(['name' => 'Principal P']);
    makeMember($this->principal, $this->tenant, ['platform.observability.alert']);

    Sanctum::actingAs($this->principal);

    $this->makeBroadcast = function (int $failed, int $total, ?Carbon $completedAt = null, ?Carbon $alertedAt = null, ?int $retryCount = null): Broadcast {
        return Broadcast::create([
            'tenant_id' => app(TenantContext::class)->id(),
            'name' => 'Term fees reminder',
            'channel' => 'sms',
            'audience' => 'whole_school',
            'audience_label' => 'Whole school',
            'template_snippet' => 'Reminder',
            'status' => BroadcastStatus::Completed->value,
            'recipient_count' => $total,
            'delivered_count' => $total - $failed,
            'failed_count' => $failed,
            'delivery_retry_count' => $retryCount ?? 0,
            'cost_minor' => 1000,
            'currency' => 'MWK',
            'created_by' => null,
            'completed_at' => $completedAt ?? Carbon::now(),
            'delivery_alerted_at' => $alertedAt,
        ]);
    };
});

it('reports healthy components on the system health endpoint', function (): void {
    config(['observability.health.checks.ai_gateway' => false]);

    $this->withHeader('X-Tenant-Id', $this->tenant->id)
        ->getJson('/api/v1/system/health')
        ->assertStatus(200)
        ->assertJsonPath('data.status', 'ok')
        ->assertJsonPath('data.components.database', 'ok')
        ->assertJsonPath('data.components.cache', 'ok')
        ->assertJsonPath('data.components.ai_gateway', 'disabled')
        ->assertJsonStructure(['data' => ['status', 'components', 'checked_at']]);
});

it('alerts platform operators about failing broadcasts', function (): void {
    // Retry budget exhausted (max_retries=3) — in-flight retries are not yet "failures".
    $broadcast = ($this->makeBroadcast)(failed: 8, total: 50, retryCount: 3);

    $this->artisan('schoolos:check-broadcast-deliveries')->assertSuccessful();

    $notification = Notification::query()
        ->where('notifiable_id', $this->principal->id)
        ->first();

    expect($notification)->not->toBeNull();
    expect($notification->data['kind'])->toBe('system');
    expect($notification->data['title'])->toContain('Broadcast delivery failure');
    expect($notification->data['body'])->toContain('8 of 50');
    expect($notification->data['href'])->toBe("/communications/broadcasts/{$broadcast->id}");
});

it('does not re-alert the same broadcast', function (): void {
    ($this->makeBroadcast)(failed: 8, total: 50, alertedAt: Carbon::now());

    $this->artisan('schoolos:check-broadcast-deliveries')->assertSuccessful();

    expect(Notification::query()->where('notifiable_id', $this->principal->id)->count())->toBe(0);
});

it('does not alert when failures are under the threshold', function (): void {
    ($this->makeBroadcast)(failed: 2, total: 100);

    $this->artisan('schoolos:check-broadcast-deliveries')->assertSuccessful();

    expect(Notification::query()->where('notifiable_id', $this->principal->id)->count())->toBe(0);
});

it('does not alert stale broadcasts outside the lookback window', function (): void {
    ($this->makeBroadcast)(failed: 8, total: 50, completedAt: Carbon::now()->subDays(2));

    $this->artisan('schoolos:check-broadcast-deliveries')->assertSuccessful();

    expect(Notification::query()->where('notifiable_id', $this->principal->id)->count())->toBe(0);
});

it('isolates alerts to the event tenant', function (): void {
    $otherTenant = makeTenant();
    bindTenant($otherTenant);

    $otherPrincipal = User::factory()->create(['name' => 'Other Principal']);
    makeMember($otherPrincipal, $otherTenant, ['platform.observability.alert']);

    ($this->makeBroadcast)(failed: 8, total: 50, retryCount: 3);

    $this->artisan('schoolos:check-broadcast-deliveries')->assertSuccessful();

    expect(Notification::query()->where('notifiable_id', $this->principal->id)->count())->toBe(0);
    expect(Notification::query()->where('notifiable_id', $otherPrincipal->id)->count())->toBe(1);
});
