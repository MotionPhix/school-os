<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\BillingCycle;
use App\Enums\CurrencyCode;
use App\Enums\FeeCategory;
use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string $academic_year_label
 * @property string $grade_label
 * @property string $name
 * @property FeeCategory $category
 * @property BillingCycle $cycle
 * @property int $amount_minor
 * @property CurrencyCode $currency
 * @property bool $is_active
 * @property int $applies_to_student_count
 */
#[Fillable([
    'tenant_id', 'academic_year_id', 'academic_year_label', 'grade_label',
    'name', 'category', 'cycle', 'amount_minor', 'currency', 'is_active',
    'applies_to_student_count',
])]
final class FeeStructure extends Model
{
    use BelongsToTenant;
    use HasUuid;
    use SoftDeletes;

    protected $table = 'finance_fee_structures';

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    protected function casts(): array
    {
        return [
            'category' => FeeCategory::class,
            'cycle' => BillingCycle::class,
            'currency' => CurrencyCode::class,
            'is_active' => 'boolean',
            'amount_minor' => 'integer',
        ];
    }

    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('is_active', true);
    }
}
