<?php

declare(strict_types=1);

namespace App\Domains\Institution\Services;

use App\Domains\Institution\Events\AcademicYearClosed;
use App\Domains\Institution\Events\AcademicYearOpened;
use App\Enums\AcademicYearStatus;
use App\Models\AcademicYear;
use Illuminate\Support\Facades\DB;

final class WriteAcademicYear
{
    /**
     * @param  array<string,mixed>  $data
     */
    public function handle(array $data, ?AcademicYear $existing = null): AcademicYear
    {
        return DB::transaction(function () use ($data, $existing): AcademicYear {
            $before = $existing?->status;
            $year = $existing ?? new AcademicYear;
            $year->fill($data);
            $year->save();

            $after = $year->status;
            if ($before !== $after) {
                if ($after === AcademicYearStatus::Active) {
                    AcademicYearOpened::dispatch($year);
                } elseif ($after === AcademicYearStatus::Closed) {
                    AcademicYearClosed::dispatch($year);
                }
            }

            return $year->fresh();
        });
    }
}
