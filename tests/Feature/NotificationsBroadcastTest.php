<?php

declare(strict_types=1);

use App\Domains\Communications\Events\BroadcastCompleted;
use App\Models\Broadcast;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->tenant = makeTenant();
    bindTenant($this->tenant);

    $this->creatorUser = User::factory()->create(['name' => 'Broadcast Creator']);
    makeMember($this->creatorUser, $this->tenant, []);

    $this->makeBroadcast = function (array $overrides = []): Broadcast {
        return Broadcast::create(array_merge([
            'tenant_id' => $this->tenant->id,
            'name' => 'Fees reminder',
            'channel' => 'sms',
            'audience' => 'guardians',
            'audience_label' => 'Guardians',
            'template_snippet' => 'Dear parent, school fees are due.',
            'status' => 'sending',
            'scheduled_for' => now(),
            'started_at' => now(),
            'recipient_count' => 120,
            'delivered_count' => 0,
            'failed_count' => 0,
            'cost_minor' => 12000,
            'currency' => 'MWK',
            'created_by' => $this->creatorUser->id,
        ], $overrides));
    };

    $this->broadcast = ($this->makeBroadcast)();
    $this->broadcast->update([
        'status' => 'completed',
        'completed_at' => now(),
        'delivered_count' => 116,
        'failed_count' => 4,
    ]);
});

it('sends a delivery report to the broadcast creator', function (): void {
    event(new BroadcastCompleted($this->broadcast));

    $this->assertDatabaseHas('notifications', ['notifiable_id' => $this->creatorUser->id]);

    $notification = Notification::query()
        ->where('notifiable_id', $this->creatorUser->id)
        ->first();

    expect($notification->data['kind'])->toBe('communications');
    expect($notification->data['title'])->toBe('Broadcast delivered — Fees reminder');
    expect($notification->data['body'])->toBe('116/120 delivered · 4 failed');
    expect($notification->data['href'])->toBe("/communications/broadcasts/{$this->broadcast->id}");
});

it('skips broadcasts without a creator', function (): void {
    $this->broadcast->update(['created_by' => null]);

    event(new BroadcastCompleted($this->broadcast));

    $this->assertDatabaseCount('notifications', 0);
});

it('skips broadcasts whose creator no longer exists', function (): void {
    $this->creatorUser->delete();

    event(new BroadcastCompleted($this->broadcast));

    $this->assertDatabaseCount('notifications', 0);
});
