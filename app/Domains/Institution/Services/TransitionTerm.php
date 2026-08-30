<?php

declare(strict_types=1);

namespace App\Domains\Institution\Services;

use App\Domains\Institution\Events\TermStatusChanged;
use App\Enums\AcademicYearStatus;
use App\Enums\TermStatus;
use App\Models\Term;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Drives the Term lifecycle: upcoming -> in_progress -> completed
 * (with a supervised reopen path for mark corrections).
 */
final class TransitionTerm
{
    public function handle(Term $term, TermStatus $target): Term
    {
        $from = $term->status;

        if ($from === $target) {
            return $term;
        }

        if (! $from->canTransitionTo($target)) {
            throw ValidationException::withMessages([
                'status' => sprintf(
                    'A term cannot move from %s to %s.',
                    $from->label(),
                    $target->label(),
                ),
            ]);
        }

        $year = $term->academicYear;

        if ($target === TermStatus::InProgress) {
            if ($year !== null && $year->status !== AcademicYearStatus::Active) {
                throw ValidationException::withMessages([
                    'status' => "Activate {$year->label} before starting one of its terms.",
                ]);
            }

            $conflict = Term::query()
                ->where('academic_year_id', $term->academic_year_id)
                ->where('id', '!=', $term->id)
                ->where('status', TermStatus::InProgress->value)
                ->first();

            if ($conflict !== null) {
                throw ValidationException::withMessages([
                    'status' => "{$conflict->name} is already in progress. Complete it first.",
                ]);
            }
        }

        return DB::transaction(function () use ($term, $from, $target): Term {
            $term->status = $target;
            $term->save();

            TermStatusChanged::dispatch($term, $from->value, $target->value);

            return $term->fresh();
        });
    }
}
