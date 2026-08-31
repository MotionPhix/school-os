<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TermStatus;
use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * A term (trimester / semester) inside an AcademicYear.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $academic_year_id
 * @property string $name
 * @property int $sequence
 * @property Carbon $starts_on
 * @property Carbon $ends_on
 * @property int $instructional_days
 * @property TermStatus $status
 * @property-read AcademicYear $academicYear
 */
#[Fillable([
    'tenant_id',
    'academic_year_id',
    'name',
    'sequence',
    'starts_on',
    'ends_on',
    'instructional_days',
    'status',
])]
final class Term extends Model
{
    use BelongsToTenant;
    use HasUuid;
    use SoftDeletes;

    /** @return BelongsTo<AcademicYear, $this> */
    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
            'status' => TermStatus::class,
            'sequence' => 'integer',
            'instructional_days' => 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }
}
