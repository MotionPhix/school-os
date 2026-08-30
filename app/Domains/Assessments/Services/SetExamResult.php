<?php

declare(strict_types=1);

namespace App\Domains\Assessments\Services;

use App\Domains\Assessments\Events\ExamResultRecorded;
use App\Domains\Assessments\Support\BandForExam;
use App\Models\Exam;
use App\Models\ExamResult;
use App\Models\Student;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Record or clear one student's score for an Exam. Score is clamped
 * to [0, max_score] and re-banded server-side; passing `null` unmarks.
 * Rejects writes on published exams — the state machine owns locking.
 */
final class SetExamResult
{
    /**
     * @param  array{score:?int,remarks?:?string}  $data
     */
    public function handle(Exam $exam, Student $student, array $data, ?User $actor = null): ExamResult
    {
        return DB::transaction(function () use ($exam, $student, $data, $actor): ExamResult {
            if ($exam->status->isLocked()) {
                throw ValidationException::withMessages([
                    'exam_id' => 'Exam is published and cannot be edited.',
                ]);
            }
            if ($exam->tenant_id !== $student->tenant_id) {
                throw ValidationException::withMessages([
                    'student_id' => 'Student belongs to a different tenant.',
                ]);
            }

            $enrolled = $exam->courseSection()->firstOrFail()->students()->whereKey($student->id)->exists();
            if (! $enrolled) {
                throw ValidationException::withMessages([
                    'student_id' => 'Student is not on this exam roster.',
                ]);
            }

            $raw = $data['score'] ?? null;
            $clean = $raw === null ? null : max(0, min((int) $exam->max_score, (int) round((float) $raw)));
            $band = $clean === null ? null : BandForExam::for($clean, (int) $exam->max_score);

            /** @var ExamResult $result */
            $result = ExamResult::query()->firstOrNew([
                'exam_id' => $exam->id,
                'student_id' => $student->id,
            ]);
            $result->fill([
                'tenant_id' => app(TenantContext::class)->id() ?? $exam->tenant_id,
                'exam_id' => $exam->id,
                'student_id' => $student->id,
                'score' => $clean,
                'band' => $band?->value,
                'remarks' => array_key_exists('remarks', $data) ? $data['remarks'] : $result->remarks,
                'recorded_by' => $actor?->id ?? $result->recorded_by,
            ]);
            $result->save();

            ExamResultRecorded::dispatch($result);

            return $result->refresh();
        });
    }
}
