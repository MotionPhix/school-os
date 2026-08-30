<?php

declare(strict_types=1);

use App\Domains\Attendance\Services\SetAttendanceMark;
use App\Domains\Communications\Services\ReplyToThread;
use App\Enums\AttendanceSessionStatus;
use App\Models\AcademicYear;
use App\Models\AttendanceMark;
use App\Models\AttendanceSession;
use App\Models\Campus;
use App\Models\CourseSection;
use App\Models\MessageThread;
use App\Models\StaffMember;
use App\Models\Student;
use App\Models\Subject;
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

    $this->subject = Subject::create([
        'tenant_id' => $this->tenant->id,
        'code' => 'MATH',
        'name' => 'Mathematics',
        'category' => 'core',
        'stages' => ['primary'],
        'is_core' => false,
        'credit_hours' => 4,
    ]);

    $this->staffUser = User::factory()->create(['name' => 'Staff S']);
    makeMember($this->staffUser, $this->tenant, [
        'attendance.sessions.write',
        'assessments.results.write',
    ]);

    $this->plainUser = User::factory()->create(['name' => 'Plain P']);
    makeMember($this->plainUser, $this->tenant, []);

    $this->staff = StaffMember::create([
        'tenant_id' => $this->tenant->id,
        'campus_id' => $this->campus->id,
        'staff_number' => 'STF-'.Str::uuid()->toString(),
        'full_name' => 'Alan Turing',
        'avatar_initials' => 'AT',
        'title' => 'Teacher',
        'department' => 'Science',
        'subjects_taught' => ['Mathematics'],
        'hired_on' => '2024-01-15',
    ]);

    $this->section = CourseSection::create([
        'tenant_id' => $this->tenant->id,
        'academic_year_id' => $this->year->id,
        'campus_id' => $this->campus->id,
        'subject_id' => $this->subject->id,
        'grade_label' => 'Grade 5',
        'section_label' => '5A',
        'teacher_id' => $this->staff->id,
        'capacity' => 32,
        'status' => 'draft',
    ]);

    $this->makeStudent = function (string $name): Student {
        return Student::create([
            'tenant_id' => $this->tenant->id,
            'campus_id' => $this->campus->id,
            'admission_number' => 'ADM-'.Str::uuid()->toString(),
            'full_name' => $name,
            'avatar_initials' => 'XX',
            'date_of_birth' => '2012-04-01',
            'stage' => 'primary',
            'grade_label' => 'Grade 5',
            'status' => 'enrolled',
        ]);
    };

    $this->studentA = ($this->makeStudent)('Ada Lovelace');
    $this->studentB = ($this->makeStudent)('Grace Hopper');

    $this->session = AttendanceSession::create([
        'tenant_id' => $this->tenant->id,
        'course_section_id' => $this->section->id,
        'date' => '2026-09-10',
        'period' => 1,
        'status' => AttendanceSessionStatus::Draft->value,
        'present_count' => 0,
        'absent_count' => 0,
        'late_count' => 0,
        'excused_count' => 0,
        'total_count' => 2,
    ]);

    foreach ([$this->studentA->id => 'absent', $this->studentB->id => 'present'] as $studentId => $status) {
        AttendanceMark::create([
            'tenant_id' => $this->tenant->id,
            'session_id' => $this->session->id,
            'student_id' => $studentId,
            'status' => $status,
        ]);
    }

    $this->thread = MessageThread::create([
        'tenant_id' => $this->tenant->id,
        'subject' => 'About Ada',
        'status' => 'open',
        'student_id' => $this->studentA->id,
        'student_name' => $this->studentA->full_name,
        'last_message_preview' => '',
        'last_message_at' => null,
        'unread_count' => 0,
    ]);

    DB::table('comm_thread_participants')->insert([
        [
            'id' => Str::uuid()->toString(),
            'tenant_id' => $this->tenant->id,
            'thread_id' => $this->thread->id,
            'user_id' => $this->staffUser->id,
            'name' => $this->staffUser->name,
            'role' => 'staff',
            'avatar_initials' => 'SS',
            'created_at' => now(),
            'updated_at' => now(),
        ],
        [
            'id' => Str::uuid()->toString(),
            'tenant_id' => $this->tenant->id,
            'thread_id' => $this->thread->id,
            'user_id' => $this->plainUser->id,
            'name' => $this->plainUser->name,
            'role' => 'staff',
            'avatar_initials' => 'PP',
            'created_at' => now(),
            'updated_at' => now(),
        ],
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

it('allows staff with marks permission into the session register presence', function (): void {
    ($this->useRecorder)();
    Sanctum::actingAs($this->staffUser);

    $this->withHeader('X-Tenant-Id', $this->tenant->id)
        ->postJson('/broadcasting/auth', [
            'channel_name' => "presence-sessions.{$this->session->id}",
            'socket_id' => '123456.789012',
        ])
        ->assertStatus(200);

    Sanctum::actingAs($this->plainUser);

    $this->withHeader('X-Tenant-Id', $this->tenant->id)
        ->postJson('/broadcasting/auth', [
            'channel_name' => "presence-sessions.{$this->session->id}",
            'socket_id' => '123456.789012',
        ])
        ->assertStatus(403);
});

it('allows staff with results permission into the exam marksheet presence', function (): void {
    ($this->useRecorder)();
    Sanctum::actingAs($this->staffUser);

    $this->withHeader('X-Tenant-Id', $this->tenant->id)
        ->postJson('/broadcasting/auth', [
            'channel_name' => 'presence-exams.exam-1',
            'socket_id' => '123456.789012',
        ])
        ->assertStatus(200);

    Sanctum::actingAs($this->plainUser);

    $this->withHeader('X-Tenant-Id', $this->tenant->id)
        ->postJson('/broadcasting/auth', [
            'channel_name' => 'presence-exams.exam-1',
            'socket_id' => '123456.789012',
        ])
        ->assertStatus(403);
});

it('allows thread participants into the conversation presence', function (): void {
    ($this->useRecorder)();
    Sanctum::actingAs($this->staffUser);

    $this->withHeader('X-Tenant-Id', $this->tenant->id)
        ->postJson('/broadcasting/auth', [
            'channel_name' => "presence-threads.{$this->thread->id}",
            'socket_id' => '123456.789012',
        ])
        ->assertStatus(200);

    $outsider = User::factory()->create(['name' => 'Outsider']);
    makeMember($outsider, $this->tenant, []);
    Sanctum::actingAs($outsider);

    $this->withHeader('X-Tenant-Id', $this->tenant->id)
        ->postJson('/broadcasting/auth', [
            'channel_name' => "presence-threads.{$this->thread->id}",
            'socket_id' => '123456.789012',
        ])
        ->assertStatus(403);
});

it('pushes live status counts to the register presence when a mark changes', function (): void {
    $recorder = ($this->useRecorder)();

    app(SetAttendanceMark::class)->handle($this->session, $this->studentA, ['status' => 'present']);

    $event = collect($recorder->events)
        ->first(fn (array $e): bool => $e['event'] === 'session.marks.updated');

    expect($event)->not->toBeNull();
    expect($event['channels'])->toContain("presence-sessions.{$this->session->id}");
    expect($event['payload']['present_count'])->toBe(2);
    expect($event['payload']['absent_count'])->toBe(0);
    expect($event['payload']['total_count'])->toBe(2);
});

it('pushes a reply to the conversation presence when a thread gets a message', function (): void {
    $recorder = ($this->useRecorder)();

    app(ReplyToThread::class)->handle($this->thread, 'Thanks for the update.', $this->staffUser);

    $event = collect($recorder->events)
        ->first(fn (array $e): bool => $e['event'] === 'thread.reply.created');

    expect($event)->not->toBeNull();
    expect($event['channels'])->toContain("presence-threads.{$this->thread->id}");
    expect($event['payload']['author_name'])->toBe('Staff S');
    expect($event['payload']['preview'])->toBe('Thanks for the update.');
});
