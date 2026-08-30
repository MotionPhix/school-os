<?php

declare(strict_types=1);

namespace App\Domains\Finance\Services;

use App\Domains\Finance\Events\FeeStructureUpserted;
use App\Enums\BillingCycle;
use App\Enums\CurrencyCode;
use App\Enums\FeeCategory;
use App\Models\AcademicYear;
use App\Models\FeeStructure;
use App\Support\TenantContext;
use Illuminate\Support\Facades\DB;

/**
 * Create or update a fee-structure row. `academic_year_label` is
 * snapshotted from AcademicYear if an ID is supplied so reports stay
 * stable even if the year's display label is edited later.
 */
final class WriteFeeStructure
{
    /**
     * @param array{
     *   academic_year_id?:?string,
     *   academic_year_label?:string,
     *   grade_label:string,
     *   name:string,
     *   category:string,
     *   cycle:string,
     *   amount_minor:int,
     *   currency?:string,
     *   is_active?:bool,
     *   applies_to_student_count?:int
     * } $data
     */
    public function create(array $data): FeeStructure
    {
        return DB::transaction(function () use ($data): FeeStructure {
            $fee = new FeeStructure;
            $fee->fill($this->fill($data));
            $fee->tenant_id = app(TenantContext::class)->id() ?? $fee->tenant_id;
            $fee->save();

            FeeStructureUpserted::dispatch($fee, true);

            return $fee->refresh();
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(FeeStructure $fee, array $data): FeeStructure
    {
        return DB::transaction(function () use ($fee, $data): FeeStructure {
            $fee->fill($this->fill($data));
            $fee->save();
            FeeStructureUpserted::dispatch($fee, false);

            return $fee->refresh();
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function fill(array $data): array
    {
        $out = [];

        if (array_key_exists('academic_year_id', $data)) {
            $out['academic_year_id'] = $data['academic_year_id'];
            if ($data['academic_year_id']) {
                $year = AcademicYear::query()->find($data['academic_year_id']);
                if ($year) {
                    $out['academic_year_label'] = $year->label ?? $year->name ?? (string) $year->id;
                }
            }
        }
        if (array_key_exists('academic_year_label', $data)) {
            $out['academic_year_label'] = (string) $data['academic_year_label'];
        }

        foreach (['grade_label', 'name'] as $k) {
            if (array_key_exists($k, $data)) {
                $out[$k] = (string) $data[$k];
            }
        }
        if (isset($data['category'])) {
            $out['category'] = FeeCategory::from((string) $data['category'])->value;
        }
        if (isset($data['cycle'])) {
            $out['cycle'] = BillingCycle::from((string) $data['cycle'])->value;
        }
        if (array_key_exists('currency', $data)) {
            $out['currency'] = CurrencyCode::from((string) $data['currency'])->value;
        } else {
            // Column is NOT NULL; fall back to the tenant/platform default.
            $out['currency'] = (string) config('finance.defaults.currency', 'MWK');
        }
        if (array_key_exists('amount_minor', $data)) {
            $out['amount_minor'] = (int) $data['amount_minor'];
        }
        if (array_key_exists('is_active', $data)) {
            $out['is_active'] = (bool) $data['is_active'];
        }
        if (array_key_exists('applies_to_student_count', $data)) {
            $out['applies_to_student_count'] = (int) $data['applies_to_student_count'];
        }

        return $out;
    }
}
