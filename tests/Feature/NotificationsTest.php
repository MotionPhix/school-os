<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\Notification;
use App\Models\NotificationPreference;
use App\Models\Student;
use App\Models\User;
use App\Notifications\AnnouncementSent as AnnouncementSentNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->tenant = makeTenant();
    bindTenant($this->tenant);

    $this->campus = Campus::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Main Campus',
        'code' => 'MAIN',
        'status' => 'operational',
        'address_line' => '1 Test Road',
        'city' => 'Lilongwe',
        'region' => 'Central',
        'timezone' => 'Africa/Blantyre',
    ]);

    $this->student = Student::create([
        'tenant_id' => $this->tenant->id,
        'campus_id' => $this->campus->id,
        'admission_number' => 'ADM-'.Str::uuid()->toString(),
        'full_name' => 'Ada Lovelace',
        'avatar_initials' => 'AL',
        'date_of_birth' => '2012-04-01',
        'stage' => 'primary',
        'grade_label' => 'Grade 5',
        'status' => 'enrolled',
    ]);

    $this->userA = User::factory()->create(['name' => 'Member A']);
    makeMember($this->userA, $this->tenant, [
        'communications.announcements.write',
        'communications.announcements.send',
        'finance.invoices.read',
        'finance.invoices.write',
        'finance.invoices.issue',
    ]);

    $this->userB = User::factory()->create(['name' => 'Member B']);
    makeMember($this->userB, $this->tenant, []);

    Sanctum::actingAs($this->userA);
});

it('sending an announcement notifies every tenant member', function (): void {
    $announcement = $this->withHeader('X-Tenant-Id', $this->tenant->id)
        ->postJson('/api/v1/communications/announcements', [
            'title' => 'Sports Day',
            'body' => 'Sports Day is on Friday.',
            'audience' => 'whole_school',
            'audience_label' => 'Whole School',
            'channels' => ['in_app'],
        ])
        ->assertStatus(201)
        ->json('data');

    $this->withHeader('X-Tenant-Id', $this->tenant->id)
        ->postJson("/api/v1/communications/announcements/{$announcement['id']}/send")
        ->assertStatus(200);

    $this->assertDatabaseCount('notifications', 2);

    $notification = Notification::query()
        ->where('notifiable_id', $this->userA->id)
        ->first();

    expect($notification)->not->toBeNull();
    expect($notification->type)->toBe(AnnouncementSentNotification::class);
    expect($notification->data['title'])->toBe('New announcement: Sports Day');
    expect($notification->tenant_id)->toBe($this->tenant->id);

    $this->withHeader('X-Tenant-Id', $this->tenant->id)
        ->getJson('/api/v1/communications/notifications')
        ->assertStatus(200)
        ->assertJsonPath('meta.unread_count', 1)
        ->assertJsonCount(1, 'data');
});

it('honours a per-user preference opt-out for the in-app channel', function (): void {
    NotificationPreference::create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->userB->id,
        'notification' => AnnouncementSentNotification::class,
        'channel' => 'tenant_database',
        'enabled' => false,
    ]);

    $announcement = $this->withHeader('X-Tenant-Id', $this->tenant->id)
        ->postJson('/api/v1/communications/announcements', [
            'title' => 'Assembly',
            'body' => 'Assembly at 8am.',
            'audience' => 'whole_school',
            'audience_label' => 'Whole School',
            'channels' => ['in_app'],
        ])
        ->assertStatus(201)
        ->json('data');

    $this->withHeader('X-Tenant-Id', $this->tenant->id)
        ->postJson("/api/v1/communications/announcements/{$announcement['id']}/send")
        ->assertStatus(200);

    // Only user A was notified; user B opted out of the in-app channel.
    $this->assertDatabaseCount('notifications', 1);
    $this->assertDatabaseMissing('notifications', ['notifiable_id' => $this->userB->id]);
});

it('issuing an invoice notifies only members with finance.invoices.read', function (): void {
    $invoice = Invoice::create([
        'tenant_id' => $this->tenant->id,
        'number' => 'INV-'.strtoupper(Str::random(8)),
        'student_id' => $this->student->id,
        'student_name' => $this->student->full_name,
        'student_initials' => 'AL',
        'grade_label' => 'Grade 5',
        'guardian_name' => 'Grace Hopper',
        'academic_year_label' => '2026',
        'term_label' => 'Term 1',
        'issued_on' => now()->toDateString(),
        'due_on' => now()->addDays(20)->toDateString(),
        'currency' => 'MWK',
        'subtotal_minor' => 10000,
        'discount_minor' => 0,
        'total_minor' => 10000,
        'paid_minor' => 0,
        'balance_minor' => 10000,
        'status' => 'draft',
    ]);

    InvoiceLine::create([
        'tenant_id' => $this->tenant->id,
        'invoice_id' => $invoice->id,
        'description' => 'Tuition',
        'category' => 'tuition',
        'quantity' => 1,
        'unit_amount_minor' => 10000,
        'amount_minor' => 10000,
        'position' => 0,
    ]);

    $this->withHeader('X-Tenant-Id', $this->tenant->id)
        ->postJson("/api/v1/finance/invoices/{$invoice->id}/issue")
        ->assertStatus(200);

    // userA has finance.invoices.read; userB does not.
    $this->assertDatabaseCount('notifications', 1);
    $this->assertDatabaseHas('notifications', ['notifiable_id' => $this->userA->id]);
    $this->assertDatabaseMissing('notifications', ['notifiable_id' => $this->userB->id]);
});

it('marks a notification as read', function (): void {
    $notification = Notification::create([
        'id' => Str::uuid()->toString(),
        'tenant_id' => $this->tenant->id,
        'type' => AnnouncementSentNotification::class,
        'notifiable_type' => User::class,
        'notifiable_id' => $this->userA->id,
        'data' => ['title' => 'Manual', 'body' => 'x', 'href' => '/'],
    ]);

    $this->withHeader('X-Tenant-Id', $this->tenant->id)
        ->postJson("/api/v1/communications/notifications/{$notification->id}/read")
        ->assertStatus(204);

    $this->assertNotNull($notification->fresh()->read_at);

    $this->withHeader('X-Tenant-Id', $this->tenant->id)
        ->getJson('/api/v1/communications/notifications')
        ->assertJsonPath('meta.unread_count', 0);
});

it('cannot mark another member\'s notification as read', function (): void {
    $notification = Notification::create([
        'id' => Str::uuid()->toString(),
        'tenant_id' => $this->tenant->id,
        'type' => AnnouncementSentNotification::class,
        'notifiable_type' => User::class,
        'notifiable_id' => $this->userA->id,
        'data' => ['title' => 'Manual', 'body' => 'x', 'href' => '/'],
    ]);

    Sanctum::actingAs($this->userB);

    $this->withHeader('X-Tenant-Id', $this->tenant->id)
        ->postJson("/api/v1/communications/notifications/{$notification->id}/read")
        ->assertStatus(404);
});

it('scopes the inbox to the active tenant', function (): void {
    $tenantC = makeTenant();
    $userC = User::factory()->create();
    makeMember($userC, $tenantC, []);
    Sanctum::actingAs($userC);

    Notification::create([
        'id' => Str::uuid()->toString(),
        'tenant_id' => $this->tenant->id,
        'type' => AnnouncementSentNotification::class,
        'notifiable_type' => User::class,
        'notifiable_id' => $userC->id,
        'data' => ['title' => 'Other tenant', 'body' => 'x', 'href' => '/'],
    ]);

    $this->withHeader('X-Tenant-Id', $tenantC->id)
        ->getJson('/api/v1/communications/notifications')
        ->assertStatus(200)
        ->assertJsonPath('meta.unread_count', 0)
        ->assertJsonCount(0, 'data');
});
