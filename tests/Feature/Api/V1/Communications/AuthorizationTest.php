<?php

declare(strict_types=1);

use App\Models\Announcement;
use App\Models\Broadcast;
use App\Models\MessageThread;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->tenant = makeTenant();
    bindTenant($this->tenant);

    $this->makeAnnouncement = function (array $overrides = []): Announcement {
        return Announcement::create(array_merge([
            'tenant_id' => $this->tenant->id,
            'title' => 'Term 1 Fees Due',
            'body' => 'Please settle fees by the end of the month.',
            'audience' => 'whole_school',
            'audience_label' => 'Whole School',
            'channels' => ['in_app'],
            'status' => 'draft',
            'author_name' => 'Registrar One',
        ], $overrides));
    };

    $this->makeBroadcast = function (array $overrides = []): Broadcast {
        return Broadcast::create(array_merge([
            'tenant_id' => $this->tenant->id,
            'name' => 'Fee Reminder SMS',
            'channel' => 'sms',
            'audience' => 'guardians',
            'audience_label' => 'Guardians',
            'template_snippet' => 'Dear parent, fees are due...',
            'status' => 'draft',
            'currency' => 'MWK',
        ], $overrides));
    };

    $this->makeThread = function (array $overrides = []): MessageThread {
        return MessageThread::create(array_merge([
            'tenant_id' => $this->tenant->id,
            'subject' => 'Uniform question',
            'status' => 'open',
            'last_message_preview' => '',
        ], $overrides));
    };
});

describe('announcement authorization', function (): void {
    it('rejects creating an announcement without communications.announcements.write', function (): void {
        $user = User::factory()->create();
        makeMember($user, $this->tenant, []);
        Sanctum::actingAs($user);

        $this->withHeader('X-Tenant-Id', $this->tenant->id)
            ->postJson('/api/v1/communications/announcements', [
                'title' => 'Term 1 Fees Due',
                'body' => 'Please settle fees by the end of the month.',
                'audience' => 'whole_school',
                'audience_label' => 'Whole School',
                'channels' => ['in_app'],
            ])
            ->assertStatus(403);
    });

    it('allows creating an announcement with communications.announcements.write', function (): void {
        $user = User::factory()->create();
        makeMember($user, $this->tenant, ['communications.announcements.write']);
        Sanctum::actingAs($user);

        $this->withHeader('X-Tenant-Id', $this->tenant->id)
            ->postJson('/api/v1/communications/announcements', [
                'title' => 'Term 1 Fees Due',
                'body' => 'Please settle fees by the end of the month.',
                'audience' => 'whole_school',
                'audience_label' => 'Whole School',
                'channels' => ['in_app'],
            ])
            ->assertStatus(201);
    });

    it('rejects sending without the dedicated announcements.send key', function (): void {
        $announcement = ($this->makeAnnouncement)();
        $user = User::factory()->create();
        makeMember($user, $this->tenant, ['communications.announcements.write']);
        Sanctum::actingAs($user);

        $this->withHeader('X-Tenant-Id', $this->tenant->id)
            ->postJson("/api/v1/communications/announcements/{$announcement->id}/send")
            ->assertStatus(403);
    });

    it('allows sending with the dedicated announcements.send key', function (): void {
        $announcement = ($this->makeAnnouncement)();
        $user = User::factory()->create();
        makeMember($user, $this->tenant, ['communications.announcements.write', 'communications.announcements.send']);
        Sanctum::actingAs($user);

        $this->withHeader('X-Tenant-Id', $this->tenant->id)
            ->postJson("/api/v1/communications/announcements/{$announcement->id}/send")
            ->assertStatus(200);
    });

    it('rejects archiving without the dedicated announcements.archive key', function (): void {
        $announcement = ($this->makeAnnouncement)();
        $user = User::factory()->create();
        makeMember($user, $this->tenant, ['communications.announcements.write']);
        Sanctum::actingAs($user);

        $this->withHeader('X-Tenant-Id', $this->tenant->id)
            ->postJson("/api/v1/communications/announcements/{$announcement->id}/archive")
            ->assertStatus(403);
    });

    it('returns 404 for an announcement of another tenant', function (): void {
        $otherTenant = makeTenant();
        $announcementB = Announcement::create([
            'tenant_id' => $otherTenant->id,
            'title' => 'Other School Notice',
            'body' => 'N/A',
            'audience' => 'whole_school',
            'audience_label' => 'Whole School',
            'channels' => ['in_app'],
            'status' => 'draft',
            'author_name' => 'Other Registrar',
        ]);

        $user = User::factory()->create();
        makeMember($user, $this->tenant, ['communications.announcements.read']);
        Sanctum::actingAs($user);

        $this->withHeader('X-Tenant-Id', $this->tenant->id)
            ->getJson("/api/v1/communications/announcements/{$announcementB->id}")
            ->assertStatus(404);
    });
});

describe('broadcast authorization', function (): void {
    it('rejects creating a broadcast without communications.broadcasts.write', function (): void {
        $user = User::factory()->create();
        makeMember($user, $this->tenant, []);
        Sanctum::actingAs($user);

        $this->withHeader('X-Tenant-Id', $this->tenant->id)
            ->postJson('/api/v1/communications/broadcasts', [
                'name' => 'Fee Reminder SMS',
                'channel' => 'sms',
                'audience' => 'guardians',
                'audience_label' => 'Guardians',
                'template_snippet' => 'Dear parent, fees are due...',
            ])
            ->assertStatus(403);
    });

    it('allows creating a broadcast with communications.broadcasts.write', function (): void {
        $user = User::factory()->create();
        makeMember($user, $this->tenant, ['communications.broadcasts.write']);
        Sanctum::actingAs($user);

        $this->withHeader('X-Tenant-Id', $this->tenant->id)
            ->postJson('/api/v1/communications/broadcasts', [
                'name' => 'Fee Reminder SMS',
                'channel' => 'sms',
                'audience' => 'guardians',
                'audience_label' => 'Guardians',
                'template_snippet' => 'Dear parent, fees are due...',
            ])
            ->assertStatus(201);
    });

    it('rejects starting without the dedicated broadcasts.start key', function (): void {
        $broadcast = ($this->makeBroadcast)();
        $user = User::factory()->create();
        makeMember($user, $this->tenant, ['communications.broadcasts.write']);
        Sanctum::actingAs($user);

        $this->withHeader('X-Tenant-Id', $this->tenant->id)
            ->postJson("/api/v1/communications/broadcasts/{$broadcast->id}/start")
            ->assertStatus(403);
    });
});

describe('message thread authorization', function (): void {
    it('rejects replying without communications.threads.write', function (): void {
        $thread = ($this->makeThread)();
        $user = User::factory()->create();
        makeMember($user, $this->tenant, ['communications.threads.read']);
        Sanctum::actingAs($user);

        $this->withHeader('X-Tenant-Id', $this->tenant->id)
            ->postJson("/api/v1/communications/threads/{$thread->id}/reply", [
                'body' => 'Please bring the invoice.',
            ])
            ->assertStatus(403);
    });
});

describe('overview authorization', function (): void {
    it('rejects the overview without communications.overview.read', function (): void {
        $user = User::factory()->create();
        makeMember($user, $this->tenant, ['communications.announcements.read']);
        Sanctum::actingAs($user);

        $this->withHeader('X-Tenant-Id', $this->tenant->id)
            ->getJson('/api/v1/communications/overview')
            ->assertStatus(403);
    });

    it('allows the overview with communications.overview.read', function (): void {
        $user = User::factory()->create();
        makeMember($user, $this->tenant, ['communications.overview.read']);
        Sanctum::actingAs($user);

        $this->withHeader('X-Tenant-Id', $this->tenant->id)
            ->getJson('/api/v1/communications/overview')
            ->assertStatus(200);
    });
});
