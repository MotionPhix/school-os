<?php

declare(strict_types=1);

namespace App\Domains\Assessments\Services;

use App\Enums\ExamPeriodStatus;
use App\Enums\ExamStatus;
use App\Models\Exam;
use App\Models\ExamPeriod;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * Batch operations over exam papers and exam periods.
 *
 * Mirrors src/lib/verbs/assessments.ts. Every row goes through the
 * single-record services so business events keep firing; rows that
 * violate a guard are skipped with a reason instead of failing the
 * whole batch.
 *
 * @phpstan-type BulkResult array{affected:int, skipped:array<int,string>}
 */
final class BulkAssessmentsAction
{
    public function __construct(
        private readonly SetExamStatus $setExamStatus,
        private readonly SetExamPeriodStatus $setPeriodStatus,
    ) {}

    /**
     * Exam papers: any ExamStatus value, or `delete`.
     *
     * @param  array<int,string>  $ids
     * @return BulkResult
     */
    public function exams(array $ids, string $action, ?User $actor = null): array
    {
        $exams = Exam::query()
            ->with('courseSection.subject')
            ->whereIn('id', $ids)
            ->get();

        $skipped = [];
        $affected = 0;

        foreach ($exams as $exam) {
            $label = $this->examLabel($exam);

            try {
                if ($action === 'delete') {
                    if ($exam->status === ExamStatus::Published) {
                        $skipped[] = "{$label}: published papers cannot be deleted.";

                        continue;
                    }
                    $exam->delete();
                    $affected++;

                    continue;
                }

                $this->setExamStatus->handle($exam, ExamStatus::from($action), $actor);
                $affected++;
            } catch (ValidationException $e) {
                $skipped[] = "{$label}: ".$e->getMessage();
            }
        }

        return ['affected' => $affected, 'skipped' => $skipped];
    }

    /**
     * Exam periods: any ExamPeriodStatus value, or `delete` (empty only).
     *
     * @param  array<int,string>  $ids
     * @return BulkResult
     */
    public function periods(array $ids, string $action): array
    {
        $periods = ExamPeriod::query()
            ->whereIn('id', $ids)
            ->withCount('exams')
            ->get();

        $skipped = [];
        $affected = 0;

        foreach ($periods as $period) {
            try {
                if ($action === 'delete') {
                    if ((int) $period->exams_count > 0) {
                        $skipped[] = "{$period->name}: period still has papers.";

                        continue;
                    }
                    $period->delete();
                    $affected++;

                    continue;
                }

                $this->setPeriodStatus->handle($period, ExamPeriodStatus::from($action));
                $affected++;
            } catch (ValidationException $e) {
                $skipped[] = "{$period->name}: ".$e->getMessage();
            }
        }

        return ['affected' => $affected, 'skipped' => $skipped];
    }

    private function examLabel(Exam $exam): string
    {
        return mb_trim(sprintf(
            '%s %s',
            $exam->courseSection?->subject?->code ?? 'Paper',
            $exam->paper_title ?? '',
        ));
    }
}
