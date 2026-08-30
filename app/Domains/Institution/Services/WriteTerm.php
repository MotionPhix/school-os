<?php

declare(strict_types=1);

namespace App\Domains\Institution\Services;

use App\Models\AcademicYear;
use App\Models\Term;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class WriteTerm
{
    /**
     * @param  array<string,mixed>  $data
     */
    public function handle(AcademicYear $year, array $data, ?Term $existing = null): Term
    {
        return DB::transaction(function () use ($year, $data, $existing): Term {
            $startsOn = $data['starts_on'];
            $endsOn = $data['ends_on'];

            if ($startsOn < $year->starts_on->toDateString() || $endsOn > $year->ends_on->toDateString()) {
                throw ValidationException::withMessages([
                    'starts_on' => 'Term must fall within the academic year window.',
                ]);
            }

            $term = $existing ?? new Term;
            $term->tenant_id = $year->tenant_id;
            $term->academic_year_id = $year->id;
            $term->fill($data);
            $term->save();

            return $term->fresh();
        });
    }
}
