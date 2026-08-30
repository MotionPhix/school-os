<?php

declare(strict_types=1);

namespace App\Domains\Assessments\Services;

use App\Domains\Assessments\Events\ExamPeriodCreated;
use App\Enums\ExamPeriodStatus;
use App\Models\ExamPeriod;
use App\Models\Term;
use App\Support\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Create or update an ExamPeriod. Dates must fall inside the parent
 * Term; when creating, `academic_year_id` is derived from the term so
 * callers don't have to keep them in sync.
 */
final class WriteExamPeriod
{
    /**
     * @param  array{term_id:string,name:string,starts_on:string,ends_on:string,status?:string}  $data
     */
    public function create(array $data): ExamPeriod
    {
        return DB::transaction(function () use ($data): ExamPeriod {
            $term = Term::query()->findOrFail($data['term_id']);
            $this->assertDatesInsideTerm($term, $data['starts_on'], $data['ends_on']);

            $period = new ExamPeriod;
            $period->fill([
                'tenant_id' => app(TenantContext::class)->id() ?? $term->tenant_id,
                'academic_year_id' => $term->academic_year_id,
                'term_id' => $term->id,
                'name' => $data['name'],
                'starts_on' => $data['starts_on'],
                'ends_on' => $data['ends_on'],
                'status' => $data['status'] ?? ExamPeriodStatus::Draft->value,
            ]);
            $period->save();

            ExamPeriodCreated::dispatch($period);

            return $period->refresh();
        });
    }

    /**
     * @param  array{name?:string,starts_on?:string,ends_on?:string}  $data
     */
    public function update(ExamPeriod $period, array $data): ExamPeriod
    {
        return DB::transaction(function () use ($period, $data): ExamPeriod {
            $starts = $data['starts_on'] ?? $period->starts_on?->toDateString();
            $ends = $data['ends_on'] ?? $period->ends_on?->toDateString();

            $term = $period->term()->firstOrFail();
            $this->assertDatesInsideTerm($term, $starts, $ends);

            $period->fill(array_filter([
                'name' => $data['name'] ?? null,
                'starts_on' => $data['starts_on'] ?? null,
                'ends_on' => $data['ends_on'] ?? null,
            ], fn ($v) => $v !== null));
            $period->save();

            return $period->refresh();
        });
    }

    private function assertDatesInsideTerm(Term $term, string $starts, string $ends): void
    {
        if (strtotime($starts) > strtotime($ends)) {
            throw ValidationException::withMessages([
                'ends_on' => 'End date must be on or after the start date.',
            ]);
        }
        $termStart = $term->starts_on?->toDateString();
        $termEnd = $term->ends_on?->toDateString();
        if ($termStart && $starts < $termStart) {
            throw ValidationException::withMessages([
                'starts_on' => "Exam period must start on or after the term start ({$termStart}).",
            ]);
        }
        if ($termEnd && $ends > $termEnd) {
            throw ValidationException::withMessages([
                'ends_on' => "Exam period must end on or before the term end ({$termEnd}).",
            ]);
        }
    }
}
