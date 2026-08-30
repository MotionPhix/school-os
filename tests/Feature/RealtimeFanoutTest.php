<?php

declare(strict_types=1);

use App\Domains\Academics\Services\ScheduleTimetableSlot;
use App\Domains\Communications\Services\SendAnnouncement;
use App\Models\AcademicYear;
use App\Models\Announcement;
use App\Models\Campus;
use App\Models\CourseSection;
use App\Models\StaffMember;
use App\Models\Subject;
use App\Models\User;
use App\Support\RealtimeChannels;
use Illuminate\Contracts\Broadcasting\Broadcaster as BroadcasterContract;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Str;
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

    $this->teacherUser = User::factory()->create(['name' => 'Teacher T']);
    makeMember($this->teacherUser, $this->tenant, []);

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
        'user_id' => $this->teacherUser->id,
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

it('pushes a timetable change to the section teacher when a slot is scheduled', function (): void {
    $recorder = ($this->useRecorder)();

    app(ScheduleTimetableSlot::class)->schedule($this->section, [
        'weekday' => 'mon',
        'period' => 1,
        'room' => 'R1',
    ]);

    $event = collect($recorder->events)
        ->first(fn (array $e): bool => $e['event'] === 'timetable.changed');

    expect($event)->not->toBeNull();
    expect($event['channels'])->toContain("private-users.{$this->teacherUser->id}");
    expect($event['payload']['action'])->toBe('scheduled');
    expect($event['payload']['section_label'])->toBe('5A');
    expect($event['payload']['weekday'])->toBe('mon');
    expect($event['payload']['period'])->toBe(1);
    expect($event['payload']['room'])->toBe('R1');
});

it('pushes a timetable change when a slot is moved and removed', function (): void {
    $recorder = ($this->useRecorder)();

    $service = app(ScheduleTimetableSlot::class);
    $slot = $service->schedule($this->section, ['weekday' => 'mon', 'period' => 1]);
    $service->move($slot, 'tue', 2);
    $service->remove($slot);

    $actions = collect($recorder->events)
        ->filter(fn (array $e): bool => $e['event'] === 'timetable.changed')
        ->pluck('payload.action')
        ->all();

    expect($actions)->toBe(['scheduled', 'moved', 'removed']);
});

it('does not push timetable changes for teachers without a portal account', function (): void {
    $recorder = ($this->useRecorder)();

    $this->staff->update(['user_id' => null]);

    app(ScheduleTimetableSlot::class)->schedule($this->section, ['weekday' => 'mon', 'period' => 1]);

    expect($recorder->events)->toBe([]);
});

it('fans an announcement out to the tenant channel when sent', function (): void {
    $recorder = ($this->useRecorder)();

    $announcement = Announcement::create([
        'tenant_id' => $this->tenant->id,
        'title' => 'Sports day moved',
        'body' => 'Sports day moves to Friday.',
        'audience' => 'whole_school',
        'audience_label' => 'Whole school',
        'channels' => ['in_app'],
        'status' => 'draft',
        'author_id' => $this->teacherUser->id,
        'author_name' => 'Teacher T',
        'recipient_count' => 10,
        'delivered_count' => 0,
        'read_count' => 0,
    ]);

    app(SendAnnouncement::class)->handle($announcement);

    $event = collect($recorder->events)
        ->first(fn (array $e): bool => $e['event'] === 'announcement.published');

    expect($event)->not->toBeNull();
    expect($event['channels'])->toContain("private-tenant.{$this->tenant->id}");
    expect($event['payload']['title'])->toBe('Sports day moved');
    expect($event['payload']['author_name'])->toBe('Teacher T');
});
