<?php

declare(strict_types=1);

namespace App\Domains\Institution\Services;

use App\Domains\Institution\Events\AcademicYearClosed;
use App\Domains\Institution\Events\AcademicYearOpened;
use App\Domains\Institution\Events\AcademicYearStatusChanged;
use App\Enums\AcademicYearStatus;
use App\Enums\TermStatus;
use App\Models\AcademicYear;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Drives the AcademicYear lifecycle: planning -> active -> closed
 * (with a supervised reopen path). Guards are enforced here rather than
 * in the controller so every caller — API, console, importer — obeys the
 * same rules.
 */
final class TransitionAcademicYear
{
    public function handle(AcademicYear $year, AcademicYearStatus $target): AcademicYear
    {
        $from = $year->status;

        if ($from === $target) {
            return $year;
        }

        if (! $from->canTransitionTo($target)) {
            throw ValidationException::withMessages([
                'status' => sprintf(
                    'An academic year cannot move from %s to %s.',
                    $from->label(),
                    $target->label(),
                ),
            ]);
        }

        if ($target === AcademicYearStatus::Active) {
            $conflict = AcademicYear::query()
                ->where('id', '!=', $year->id)
                ->where('status', AcademicYearStatus::Active->value)
                ->where('starts_on', '<=', $year->ends_on)
                ->where('ends_on', '>=', $year->starts_on)
                ->first();

            if ($conflict !== null) {
                throw ValidationException::withMessages([
                    'status' => "{$conflict->label} is already active over the same dates.",
                ]);
            }
        }

        if ($target === AcademicYearStatus::Closed) {
            $open = $year->terms()
                ->where('status', '!=', TermStatus::Completed->value)
                ->count();

            if ($open > 0) {
                throw ValidationException::withMessages([
                    'status' => "Complete all {$open} open term(s) before closing this year.",
                ]);
            }
        }

        return DB::transaction(function () use ($year, $from, $target): AcademicYear {
            $year->status = $target;

            if ($target === AcademicYearStatus::Closed) {
                $year->is_current = false;
            }

            $year->save();

            AcademicYearStatusChanged::dispatch($year, $from->value, $target->value);

            if ($target === AcademicYearStatus::Active) {
                AcademicYearOpened::dispatch($year);
            }

            if ($target === AcademicYearStatus::Closed) {
                AcademicYearClosed::dispatch($year);
            }

            return $year->fresh(['terms']);
        });
    }
}
