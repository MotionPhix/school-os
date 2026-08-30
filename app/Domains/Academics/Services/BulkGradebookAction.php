<?php

declare(strict_types=1);

namespace App\Domains\Academics\Services;

use App\Domains\Academics\Events\GradebookEntryRecorded;
use App\Enums\GradeBand;
use App\Models\CourseSection;
use App\Models\GradebookEntry;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Batch writers for the marking sheet.
 *
 * Both operations recompute total + band exactly like UpsertGradebookEntry so
 * a curved sheet is indistinguishable from a hand-marked one.
 *
 * @phpstan-type BulkResult array{affected:int, skipped:array<int,string>}
 */
final class BulkGradebookAction
{
    /**
     * @param  array<int,array{id:string, continuous_assessment:int, exam_score:int, remarks?:?string}>  $rows
     * @return BulkResult
     */
    public function save(array $rows, ?User $actor = null): array
    {
        $caMax = (int) config('academics.gradebook.continuous_assessment_max', 40);
        $examMax = (int) config('academics.gradebook.exam_max', 60);

        $entries = GradebookEntry::query()
            ->whereIn('id', array_column($rows, 'id'))
            ->get()
            ->keyBy('id');

        $skipped = [];
        $affected = 0;

        DB::transaction(function () use ($rows, $entries, $caMax, $examMax, $actor, &$skipped, &$affected): void {
            foreach ($rows as $row) {
                $entry = $entries->get($row['id']);

                if ($entry === null) {
                    $skipped[] = "Entry {$row['id']} no longer exists.";

                    continue;
                }

                $ca = (int) $row['continuous_assessment'];
                $exam = (int) $row['exam_score'];

                if ($ca < 0 || $ca > $caMax) {
                    $skipped[] = "{$entry->student->full_name}: continuous assessment must be 0–{$caMax}.";

                    continue;
                }

                if ($exam < 0 || $exam > $examMax) {
                    $skipped[] = "{$entry->student->full_name}: exam score must be 0–{$examMax}.";

                    continue;
                }

                $this->applyScores($entry, $ca, $exam, $row['remarks'] ?? $entry->remarks, $actor);
                $affected++;
            }
        });

        return ['affected' => $affected, 'skipped' => $skipped];
    }

    /**
     * Shift every exam score in a section by $points, clamped to the ceiling.
     *
     * @return BulkResult
     */
    public function curve(CourseSection $section, int $points, ?string $termId = null, ?User $actor = null): array
    {
        $examMax = (int) config('academics.gradebook.exam_max', 60);
        $points = max(-20, min(20, $points));

        if ($points === 0) {
            return ['affected' => 0, 'skipped' => ['A curve of zero points changes nothing.']];
        }

        $entries = GradebookEntry::query()
            ->where('course_section_id', $section->id)
            ->when($termId !== null, fn ($q) => $q->where('term_id', $termId))
            ->get();

        $skipped = [];
        $affected = 0;

        DB::transaction(function () use ($entries, $points, $examMax, $actor, &$skipped, &$affected): void {
            foreach ($entries as $entry) {
                $exam = max(0, min($examMax, (int) $entry->exam_score + $points));

                if ($exam === (int) $entry->exam_score) {
                    $skipped[] = "{$entry->student->full_name}: already at the score ceiling.";

                    continue;
                }

                $this->applyScores($entry, (int) $entry->continuous_assessment, $exam, $entry->remarks, $actor);
                $affected++;
            }
        });

        $note = sprintf('Curve of %+d exam point(s) applied.', $points);

        return ['affected' => $affected, 'skipped' => array_merge([$note], $skipped)];
    }

    private function applyScores(
        GradebookEntry $entry,
        int $ca,
        int $exam,
        ?string $remarks,
        ?User $actor,
    ): void {
        $totalMax = (int) config('academics.gradebook.total_max', 100);
        $total = max(0, min($totalMax, $ca + $exam));

        $entry->forceFill([
            'continuous_assessment' => $ca,
            'exam_score' => $exam,
            'total' => $total,
            'band' => GradeBand::forTotal($total)->value,
            'remarks' => $remarks,
            'recorded_by' => $actor?->id ?? $entry->recorded_by,
        ])->save();

        GradebookEntryRecorded::dispatch($entry->refresh());
    }
}
