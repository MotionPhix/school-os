<?php

declare(strict_types=1);

namespace App\Domains\Institution\Services;

use App\Domains\Institution\Events\CurrentAcademicYearChanged;
use App\Models\AcademicYear;
use Illuminate\Support\Facades\DB;

final class SetCurrentAcademicYear
{
    public function handle(AcademicYear $year): AcademicYear
    {
        return DB::transaction(function () use ($year): AcademicYear {
            $previousId = AcademicYear::query()
                ->where('tenant_id', $year->tenant_id)
                ->where('is_current', true)
                ->where('id', '!=', $year->id)
                ->value('id');

            AcademicYear::query()
                ->where('tenant_id', $year->tenant_id)
                ->where('id', '!=', $year->id)
                ->update(['is_current' => false]);

            $year->is_current = true;
            $year->save();

            CurrentAcademicYearChanged::dispatch($year, $previousId);

            return $year->fresh();
        });
    }
}
