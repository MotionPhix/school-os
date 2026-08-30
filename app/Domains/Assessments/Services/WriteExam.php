<?php

declare(strict_types=1);

namespace App\Domains\Assessments\Services;

use App\Domains\Assessments\Events\ExamCreated;
use App\Domains\Assessments\Events\ExamUpdated;
use App\Enums\ExamStatus;
use App\Models\CourseSection;
use App\Models\Exam;
use App\Models\ExamPeriod;
use App\Support\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Create or update an Exam paper. Paper's scheduled date must land
 * inside its parent ExamPeriod window; the section must belong to the
 * same tenant.
 */
final class WriteExam
{
    /**
     * @param array{
     *   period_id:string,
     *   course_section_id:string,
     *   paper_title:string,
     *   scheduled_on:string,
     *   starts_at:string,
     *   duration_minutes?:int,
     *   room?:?string,
     *   max_score?:int,
     *   pass_mark?:int,
     *   status?:string
     * } $data
     */
    public function create(array $data): Exam
    {
        return DB::transaction(function () use ($data): Exam {
            $period = ExamPeriod::query()->findOrFail($data['period_id']);
            $section = CourseSection::query()->findOrFail($data['course_section_id']);

            if ($period->tenant_id !== $section->tenant_id) {
                throw ValidationException::withMessages([
                    'course_section_id' => 'Course section belongs to a different tenant.',
                ]);
            }
            $this->assertDateInsidePeriod($period, $data['scheduled_on']);

            $exam = new Exam;
            $exam->fill([
                'tenant_id' => app(TenantContext::class)->id() ?? $period->tenant_id,
                'period_id' => $period->id,
                'course_section_id' => $section->id,
                'paper_title' => $data['paper_title'],
                'scheduled_on' => $data['scheduled_on'],
                'starts_at' => $data['starts_at'],
                'duration_minutes' => (int) ($data['duration_minutes'] ?? config('assessments.defaults.duration_minutes', 90)),
                'room' => $data['room'] ?? null,
                'max_score' => (int) ($data['max_score'] ?? config('assessments.defaults.max_score', 100)),
                'pass_mark' => (int) ($data['pass_mark'] ?? config('assessments.defaults.pass_mark', 40)),
                'status' => $data['status'] ?? ExamStatus::Draft->value,
            ]);
            $exam->save();

            ExamCreated::dispatch($exam);

            return $exam->refresh();
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Exam $exam, array $data): Exam
    {
        return DB::transaction(function () use ($exam, $data): Exam {
            if ($exam->status->isLocked()) {
                throw ValidationException::withMessages([
                    'status' => 'Published exams cannot be edited.',
                ]);
            }

            if (isset($data['scheduled_on'])) {
                $period = $exam->period()->firstOrFail();
                $this->assertDateInsidePeriod($period, (string) $data['scheduled_on']);
            }

            $allowed = [
                'paper_title', 'scheduled_on', 'starts_at', 'duration_minutes',
                'room', 'max_score', 'pass_mark',
            ];
            $changes = [];
            foreach ($allowed as $field) {
                if (array_key_exists($field, $data)) {
                    $changes[$field] = $data[$field];
                }
            }
            if ($changes !== []) {
                $exam->fill($changes);
                $exam->save();
                ExamUpdated::dispatch($exam, $changes);
            }

            return $exam->refresh();
        });
    }

    private function assertDateInsidePeriod(ExamPeriod $period, string $scheduledOn): void
    {
        $starts = $period->starts_on?->toDateString();
        $ends = $period->ends_on?->toDateString();
        if ($starts && $scheduledOn < $starts) {
            throw ValidationException::withMessages([
                'scheduled_on' => "Exam must be scheduled on or after the period start ({$starts}).",
            ]);
        }
        if ($ends && $scheduledOn > $ends) {
            throw ValidationException::withMessages([
                'scheduled_on' => "Exam must be scheduled on or before the period end ({$ends}).",
            ]);
        }
    }
}
