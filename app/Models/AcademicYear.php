<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AcademicYearStatus;
use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * A tenant's academic year. Owns a collection of Terms and is the
 * anchor for scheduling and calendar events.
 *
 * Exactly one AcademicYear per tenant may have `is_current = true`.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $label
 * @property Carbon $starts_on
 * @property Carbon $ends_on
 * @property AcademicYearStatus $status
 * @property bool $is_current
 */
#[Fillable([
    'tenant_id',
    'label',
    'starts_on',
    'ends_on',
    'status',
    'is_current',
])]
final class AcademicYear extends Model
{
    use BelongsToTenant;
    use HasUuid;
    use SoftDeletes;

    public function terms(): HasMany
    {
        return $this->hasMany(Term::class)->orderBy('sequence');
    }

    public function calendarEvents(): HasMany
    {
        return $this->hasMany(CalendarEvent::class);
    }

    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
            'status' => AcademicYearStatus::class,
            'is_current' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    #[Scope]
    protected function current(Builder $query): void
    {
        $query->where('is_current', true);
    }

    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('status', AcademicYearStatus::Active->value);
    }
}
