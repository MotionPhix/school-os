<?php

declare(strict_types=1);

namespace App\Domains\Academics\Services;

use App\Domains\Academics\Events\GradebookEntryRecorded;
use App\Enums\GradeBand;
use App\Models\CourseSection;
use App\Models\GradebookEntry;
use App\Models\Student;
use App\Models\Term;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Create or update a gradebook entry for (course × term × student).
 * Derives `total` (CA + exam, clamped 0..100) and `band` (GradeBand::forTotal).
 */
final class UpsertGradebookEntry
{
    /**
     * @param array{
     *   continuous_assessment:int,
     *   exam_score:int,
     *   remarks?:?string,
     * } $data
     */
    public function handle(
        CourseSection $section,
        Term $term,
        Student $student,
        array $data,
        ?User $actor = null,
    ): GradebookEntry {
        return DB::transaction(function () use ($section, $term, $student, $data, $actor): GradebookEntry {
            $caMax = (int) config('academics.gradebook.continuous_assessment_max', 40);
            $examMax = (int) config('academics.gradebook.exam_max', 60);
            $totalMax = (int) config('academics.gradebook.total_max', 100);

            $ca = (int) $data['continuous_assessment'];
            $exam = (int) $data['exam_score'];

            if ($ca < 0 || $ca > $caMax) {
                throw ValidationException::withMessages([
                    'continuous_assessment' => "Must be between 0 and {$caMax}.",
                ]);
            }
            if ($exam < 0 || $exam > $examMax) {
                throw ValidationException::withMessages([
                    'exam_score' => "Must be between 0 and {$examMax}.",
                ]);
            }

            $total = max(0, min($totalMax, $ca + $exam));
            $band = GradeBand::forTotal($total);

            // A grade can only be recorded for a student on the section roster.
            if (! $section->students()->whereKey($student->id)->exists()) {
                throw ValidationException::withMessages([
                    'student_id' => 'This student is not enrolled in the section.',
                ]);
            }

            $entry = GradebookEntry::updateOrCreate(
                [
                    'course_section_id' => $section->id,
                    'term_id' => $term->id,
                    'student_id' => $student->id,
                ],
                [
                    'tenant_id' => $section->tenant_id,
                    'continuous_assessment' => $ca,
                    'exam_score' => $exam,
                    'total' => $total,
                    'band' => $band->value,
                    'remarks' => $data['remarks'] ?? null,
                    'recorded_by' => $actor?->id,
                ],
            );

            GradebookEntryRecorded::dispatch($entry);

            return $entry->refresh();
        });
    }
}
