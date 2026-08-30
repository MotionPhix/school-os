<?php

declare(strict_types=1);

namespace App\Domains\Assessments\Services;

use App\Models\Exam;
use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Marksheet batch operations: save many edited cells, fill the roster
 * (or only the ungraded remainder) with a fixed score, and moderate
 * ("curve") every graded score by points or a percentage.
 *
 * All writes funnel through SetExamResult so clamping, re-banding,
 * roster checks and ExamResultRecorded events stay in one place.
 */
final class BulkSetExamResults
{
    public function __construct(private readonly SetExamResult $setResult) {}

    /**
     * @param  array<int,array{student_id:string,score:?int,remarks?:?string}>  $entries
     */
    public function save(Exam $exam, array $entries, ?User $actor = null): int
    {
        return DB::transaction(function () use ($exam, $entries, $actor): int {
            $students = Student::query()
                ->whereIn('id', array_column($entries, 'student_id'))
                ->get()
                ->keyBy('id');

            $saved = 0;
            foreach ($entries as $entry) {
                $student = $students->get($entry['student_id']);
                if ($student === null) {
                    continue;
                }
                $this->setResult->handle($exam, $student, [
                    'score' => $entry['score'] ?? null,
                    'remarks' => $entry['remarks'] ?? null,
                ], $actor);
                $saved++;
            }

            return $saved;
        });
    }

    /**
     * @param  'all'|'remaining'  $scope
     */
    public function fill(Exam $exam, string $scope, int $score, ?User $actor = null): int
    {
        $roster = $exam->courseSection()->firstOrFail()->students()->get();
        $graded = $exam->results()->whereNotNull('score')->pluck('score', 'student_id');

        $entries = [];
        foreach ($roster as $student) {
            if ($scope === 'remaining' && $graded->has($student->id)) {
                continue;
            }
            $entries[] = ['student_id' => (string) $student->id, 'score' => $score];
        }

        return $this->save($exam, $entries, $actor);
    }

    /**
     * @param  'points'|'percent'  $mode
     */
    public function curve(Exam $exam, string $mode, float $amount, ?User $actor = null): int
    {
        if ($exam->status->isLocked()) {
            throw ValidationException::withMessages([
                'exam_id' => 'Exam is published and cannot be edited.',
            ]);
        }

        $entries = [];
        foreach ($exam->results()->whereNotNull('score')->get() as $result) {
            $current = (int) $result->score;
            $next = $mode === 'points' ? $current + $amount : $current * (1 + $amount / 100);
            $entries[] = [
                'student_id' => (string) $result->student_id,
                'score' => (int) round($next),
            ];
        }

        return $this->save($exam, $entries, $actor);
    }
}
