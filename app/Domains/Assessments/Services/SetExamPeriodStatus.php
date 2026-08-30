<?php

declare(strict_types=1);

namespace App\Domains\Assessments\Services;

use App\Domains\Assessments\Events\ExamPeriodStatusChanged;
use App\Enums\ExamPeriodStatus;
use App\Enums\ExamStatus;
use App\Models\ExamPeriod;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Move an exam period through its state machine.
 *
 * - Rejects invalid transitions via ExamPeriodStatus::canTransitionTo().
 * - Closing requires every paper in the period to be published, so a
 *   closed window can never hide unreleased marks.
 */
final class SetExamPeriodStatus
{
    public function handle(ExamPeriod $period, ExamPeriodStatus $status): ExamPeriod
    {
        return DB::transaction(function () use ($period, $status): ExamPeriod {
            $from = $period->status;
            if ($from === $status) {
                return $period;
            }

            if (! $from->canTransitionTo($status)) {
                throw ValidationException::withMessages([
                    'status' => "Cannot move period from '{$from->value}' to '{$status->value}'.",
                ]);
            }

            if ($status === ExamPeriodStatus::Closed) {
                $open = $period->exams()->where('status', '!=', ExamStatus::Published->value)->count();
                if ($open > 0) {
                    throw ValidationException::withMessages([
                        'status' => "Cannot close — {$open} paper(s) are not published yet.",
                    ]);
                }
            }

            $period->status = $status;
            $period->save();

            ExamPeriodStatusChanged::dispatch($period, $from, $status);

            return $period->refresh();
        });
    }
}
