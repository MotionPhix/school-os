<?php

declare(strict_types=1);

namespace App\Domains\Academics\Services;

use App\Domains\Academics\Events\TimetableSlotRemoved;
use App\Domains\Academics\Events\TimetableSlotScheduled;
use App\Domains\Academics\Support\PeriodGrid;
use App\Events\TimetableChanged;
use App\Models\CourseSection;
use App\Models\TimetableSlot;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Scheduling is the only writer of TimetableSlot.
 *
 * Three clash dimensions are enforced for a (weekday, period) cell, mirroring
 * the presentation layer exactly:
 *   - teacher: a teacher cannot be in two rooms at once
 *   - room:    a room cannot hold two sections at once
 *   - grade:   a grade cohort cannot sit two lessons at once
 */
final class ScheduleTimetableSlot
{
    /**
     * @param  array<string,mixed>  $data
     */
    public function schedule(CourseSection $section, array $data): TimetableSlot
    {
        return DB::transaction(function () use ($section, $data): TimetableSlot {
            $weekday = (string) $data['weekday'];
            $period = (int) $data['period'];
            $room = $data['room'] ?? $section->room;

            if (! isset($data['starts_at']) || ! isset($data['ends_at'])) {
                $derived = PeriodGrid::forPeriod($period);
                $data['starts_at'] = $data['starts_at'] ?? $derived['starts_at'];
                $data['ends_at'] = $data['ends_at'] ?? $derived['ends_at'];
            }

            $this->assertNoClash($section, $weekday, $period, $room);

            $slot = TimetableSlot::updateOrCreate(
                [
                    'course_section_id' => $section->id,
                    'weekday' => $weekday,
                    'period' => $period,
                ],
                [
                    'tenant_id' => $section->tenant_id,
                    'starts_at' => $data['starts_at'],
                    'ends_at' => $data['ends_at'],
                    'room' => $room,
                ],
            );

            TimetableSlotScheduled::dispatch($slot);

            $this->pushTimetableChange($slot, 'scheduled');

            return $slot->refresh();
        });
    }

    /**
     * Move an existing slot to another (weekday, period) cell.
     */
    public function move(TimetableSlot $slot, string $weekday, int $period): TimetableSlot
    {
        return DB::transaction(function () use ($slot, $weekday, $period): TimetableSlot {
            if ($slot->weekday === $weekday && (int) $slot->period === $period) {
                return $slot;
            }

            $section = $slot->courseSection;
            $this->assertNoClash($section, $weekday, $period, $slot->room, $slot->id);

            $grid = PeriodGrid::forPeriod($period);

            $slot->forceFill([
                'weekday' => $weekday,
                'period' => $period,
                'starts_at' => $grid['starts_at'],
                'ends_at' => $grid['ends_at'],
            ])->save();

            TimetableSlotScheduled::dispatch($slot);

            $this->pushTimetableChange($slot, 'moved');

            return $slot->refresh();
        });
    }

    public function remove(TimetableSlot $slot): void
    {
        DB::transaction(function () use ($slot): void {
            $snapshot = clone $slot;
            $slot->delete();
            TimetableSlotRemoved::dispatch($snapshot);
            $this->pushTimetableChange($snapshot, 'removed');
        });
    }

    private function pushTimetableChange(TimetableSlot $slot, string $action): void
    {
        $teacherUserId = $slot->courseSection->teacher->user_id;

        if ($teacherUserId === null) {
            return;
        }

        TimetableChanged::dispatch(
            (string) $slot->id,
            (string) $slot->course_section_id,
            $slot->courseSection->section_label,
            $slot->weekday->value,
            (int) $slot->period,
            $slot->room,
            $action,
            (string) $teacherUserId,
        );
    }

    /**
     * @throws ValidationException
     */
    private function assertNoClash(
        CourseSection $section,
        string $weekday,
        int $period,
        ?string $room,
        ?string $ignoreSlotId = null,
    ): void {
        $base = fn () => TimetableSlot::query()
            ->where('tenant_id', $section->tenant_id)
            ->where('weekday', $weekday)
            ->where('period', $period)
            ->where('course_section_id', '!=', $section->id)
            ->when($ignoreSlotId !== null, fn ($q) => $q->where('id', '!=', $ignoreSlotId));

        $teacherClash = $base()
            ->whereHas('courseSection', fn ($q) => $q->where('teacher_id', $section->teacher_id))
            ->exists();

        if ($teacherClash) {
            throw ValidationException::withMessages([
                'period' => "Teacher is already scheduled for {$weekday} period {$period}.",
            ]);
        }

        if ($room !== null && $room !== '') {
            $roomClash = $base()->where('room', $room)->exists();

            if ($roomClash) {
                throw ValidationException::withMessages([
                    'period' => "{$room} is already booked for {$weekday} period {$period}.",
                ]);
            }
        }

        if ($section->grade_label !== null && $section->grade_label !== '') {
            $gradeClash = $base()
                ->whereHas('courseSection', fn ($q) => $q
                    ->where('grade_label', $section->grade_label)
                    ->where('campus_id', $section->campus_id))
                ->exists();

            if ($gradeClash) {
                throw ValidationException::withMessages([
                    'period' => "{$section->grade_label} already has a lesson on {$weekday} period {$period}.",
                ]);
            }
        }
    }
}
