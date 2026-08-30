<?php

declare(strict_types=1);

namespace App\Domains\Assessments\Services;

use App\Domains\Assessments\Events\ExamPublished;
use App\Domains\Assessments\Events\ExamStatusChanged;
use App\Enums\ExamStatus;
use App\Models\Exam;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Move an exam through its state machine.
 *
 * - Rejects invalid transitions via ExamStatus::canTransitionTo().
 * - Publishing requires every enrolled student to have a graded result,
 *   so partial marksheets can never leak into report cards.
 * - Dispatches ExamStatusChanged always; ExamPublished additionally when
 *   the destination is `published`.
 */
final class SetExamStatus
{
    public function handle(Exam $exam, ExamStatus $to, ?User $actor = null): Exam
    {
        return DB::transaction(function () use ($exam, $to, $actor): Exam {
            $from = $exam->status;
            if ($from === $to) {
                return $exam;
            }

            if (! $from->canTransitionTo($to)) {
                throw ValidationException::withMessages([
                    'status' => "Cannot move exam from '{$from->value}' to '{$to->value}'.",
                ]);
            }

            if ($to === ExamStatus::Published) {
                $enrolled = $exam->courseSection()->firstOrFail()->students()->count();
                $graded = $exam->results()->graded()->count();
                if ($enrolled === 0 || $graded < $enrolled) {
                    throw ValidationException::withMessages([
                        'status' => "Cannot publish — {$graded} of {$enrolled} students graded.",
                    ]);
                }
                $exam->published_at = now();
                $exam->published_by = $actor?->id;
            }

            $exam->status = $to;
            $exam->save();

            ExamStatusChanged::dispatch($exam, $from, $to);
            if ($to === ExamStatus::Published) {
                ExamPublished::dispatch($exam);
            }

            return $exam->refresh();
        });
    }
}
