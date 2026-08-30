<?php

declare(strict_types=1);

use App\Domains\Communications\Services\CompleteBroadcast;
use App\Domains\Communications\Services\StartBroadcast;
use App\Domains\Finance\Events\PaymentRecorded;
use App\Models\AcademicYear;
use App\Models\Broadcast as BroadcastModel;
use App\Models\Campus;
use App\Models\Guardian;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Student;
use App\Models\User;
use App\Support\RealtimeChannels;
use Illuminate\Contracts\Broadcasting\Broadcaster as BroadcasterContract;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\Support\RecordingBroadcaster;

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

    $this->year = AcademicYear::create([
        'tenant_id' => $this->tenant->id,
        'label' => '2026/2027',
        'starts_on' => '2026-09-01',
        'ends_on' => '2027-08-31',
        'status' => 'planning',
        'is_current' => false,
    ]);

    $this->guardianUser = User::factory()->create(['name' => 'Guardian G']);
    makeMember($this->guardianUser, $this->tenant, []);

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

    $guardian = Guardian::create([
        'tenant_id' => $this->tenant->id,
        'full_name' => 'Grace Parent',
        'avatar_initials' => 'GP',
        'user_id' => $this->guardianUser->id,
    ]);

    DB::table('student_guardians')->insert([
        'id' => Str::uuid()->toString(),
        'tenant_id' => $this->tenant->id,
        'student_id' => $this->student->id,
        'guardian_id' => $guardian->id,
        'relationship' => 'Parent',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->invoice = Invoice::create([
        'tenant_id' => $this->tenant->id,
        'number' => 'INV-'.mb_strtoupper(Str::random(8)),
        'student_id' => $this->student->id,
        'student_name' => $this->student->full_name,
        'student_initials' => 'AL',
        'grade_label' => 'Grade 5',
        'guardian_name' => 'Grace Parent',
        'academic_year_id' => $this->year->id,
        'academic_year_label' => '2026/2027',
        'term_label' => 'Term 1',
        'issued_on' => now()->toDateString(),
        'due_on' => now()->addDays(20)->toDateString(),
        'currency' => 'MWK',
        'subtotal_minor' => 50000,
        'discount_minor' => 0,
        'total_minor' => 50000,
        'paid_minor' => 0,
        'balance_minor' => 50000,
        'status' => 'issued',
    ]);

    $this->payment = Payment::create([
        'tenant_id' => $this->tenant->id,
        'invoice_id' => $this->invoice->id,
        'invoice_number' => $this->invoice->number,
        'student_name' => $this->student->full_name,
        'reference' => 'PAY-'.mb_strtoupper(Str::random(8)),
        'method' => 'cash',
        'gateway' => 'manual',
        'amount_minor' => 20000,
        'gateway_fee_minor' => 0,
        'currency' => 'MWK',
        'status' => 'succeeded',
        'received_at' => now(),
    ]);

    /**
     * Swap the default broadcaster for a recording one that carries the
     * real channel definitions and restores the configured driver after.
     */
    $this->useRecorder = function (): RecordingBroadcaster {
        $recorder = new RecordingBroadcaster;

        foreach (RealtimeChannels::definitions() as $name => $callback) {
            $recorder->channel($name, $callback);
        }

        Broadcast::extend('recording', fn (): RecordingBroadcaster => $recorder);
        Broadcast::setDefaultDriver('recording');
        Broadcast::forgetDrivers();
        app()->forgetInstance(BroadcasterContract::class);

        return $recorder;
    };
});

afterEach(function (): void {
    Broadcast::setDefaultDriver((string) config('broadcasting.default'));
    Broadcast::forgetDrivers();
    app()->forgetInstance(BroadcasterContract::class);
});

it('pushes a feed badge when a notification is stored', function (): void {
    $recorder = ($this->useRecorder)();

    event(new PaymentRecorded($this->payment, 'entry-'.Str::uuid()->toString()));

    expect($recorder->events)->toHaveCount(1);

    $event = $recorder->events[0];
    expect($event['channels'])->toContain("private-users.{$this->guardianUser->id}");
    expect($event['event'])->toBe('feed.badge.updated');
    expect($event['payload']['unread_count'])->toBe(1);
});

it('pushes sending progress when a broadcast starts', function (): void {
    $recorder = ($this->useRecorder)();

    $broadcast = BroadcastModel::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Fees reminder',
        'channel' => 'sms',
        'audience' => 'guardians',
        'audience_label' => 'Guardians',
        'template_snippet' => 'Fees due.',
        'status' => 'queued',
        'scheduled_for' => now(),
        'recipient_count' => 120,
        'delivered_count' => 0,
        'failed_count' => 0,
        'cost_minor' => 12000,
        'currency' => 'MWK',
        'created_by' => $this->guardianUser->id,
    ]);

    app(StartBroadcast::class)->handle($broadcast);

    expect($recorder->events)->toHaveCount(1);

    $event = $recorder->events[0];
    expect($event['channels'])->toContain("private-users.{$this->guardianUser->id}");
    expect($event['event'])->toBe('broadcast.progress.updated');
    expect($event['payload']['status'])->toBe('sending');
    expect($event['payload']['recipient_count'])->toBe(120);
    expect($event['payload']['delivered_count'])->toBe(48);
});

it('pushes completed progress when a broadcast completes', function (): void {
    $recorder = ($this->useRecorder)();

    $broadcast = BroadcastModel::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Fees reminder',
        'channel' => 'sms',
        'audience' => 'guardians',
        'audience_label' => 'Guardians',
        'template_snippet' => 'Fees due.',
        'status' => 'sending',
        'started_at' => now(),
        'recipient_count' => 120,
        'delivered_count' => 48,
        'failed_count' => 0,
        'cost_minor' => 12000,
        'currency' => 'MWK',
        'created_by' => $this->guardianUser->id,
    ]);

    app(CompleteBroadcast::class)->handle($broadcast);

    // Completion also emits the BroadcastReport notification, whose badge
    // push is a second broadcast — locate the progress tick explicitly.
    $progress = collect($recorder->events)
        ->first(fn (array $e): bool => $e['event'] === 'broadcast.progress.updated');

    expect($progress)->not->toBeNull();
    expect($progress['channels'])->toContain("private-users.{$this->guardianUser->id}");
    expect($progress['payload']['status'])->toBe('completed');
    expect($progress['payload']['delivered_count'])->toBe(116);
    expect($progress['payload']['failed_count'])->toBe(4);
});

it('does not push progress for system broadcasts without a creator', function (): void {
    $recorder = ($this->useRecorder)();

    $broadcast = BroadcastModel::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'System sweep',
        'channel' => 'sms',
        'audience' => 'whole_school',
        'audience_label' => 'Whole school',
        'template_snippet' => 'System notice.',
        'status' => 'queued',
        'scheduled_for' => now(),
        'recipient_count' => 10,
        'delivered_count' => 0,
        'failed_count' => 0,
        'cost_minor' => 1000,
        'currency' => 'MWK',
        'created_by' => null,
    ]);

    app(StartBroadcast::class)->handle($broadcast);

    expect($recorder->events)->toBe([]);
});

it('authorizes the private users channel for the owner only', function (): void {
    $otherUser = User::factory()->create(['name' => 'Other O']);
    makeMember($otherUser, $this->tenant, []);

    ($this->useRecorder)();
    Sanctum::actingAs($this->guardianUser);

    $this->withHeader('X-Tenant-Id', $this->tenant->id)
        ->postJson('/broadcasting/auth', [
            'channel_name' => "private-users.{$this->guardianUser->id}",
            'socket_id' => '123456.789012',
        ])
        ->assertStatus(200);

    $this->withHeader('X-Tenant-Id', $this->tenant->id)
        ->postJson('/broadcasting/auth', [
            'channel_name' => "private-users.{$otherUser->id}",
            'socket_id' => '123456.789012',
        ])
        ->assertStatus(403);
});

it('authorizes the tenant channel for members only', function (): void {
    $outsider = User::factory()->create(['name' => 'Outsider']);

    ($this->useRecorder)();
    Sanctum::actingAs($this->guardianUser);

    $this->withHeader('X-Tenant-Id', $this->tenant->id)
        ->postJson('/broadcasting/auth', [
            'channel_name' => "tenant.{$this->tenant->id}",
            'socket_id' => '123456.789012',
        ])
        ->assertStatus(200);

    Sanctum::actingAs($outsider);

    $this->withHeader('X-Tenant-Id', $this->tenant->id)
        ->postJson('/broadcasting/auth', [
            'channel_name' => "tenant.{$this->tenant->id}",
            'socket_id' => '123456.789012',
        ])
        ->assertStatus(403);
});
