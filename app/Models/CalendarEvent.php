<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CalendarAudience;
use App\Enums\CalendarEventKind;
use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Institutional calendar event. Anchored to an AcademicYear; optionally
 * targeted at a single Campus (null = all campuses).
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $academic_year_id
 * @property string|null $campus_id
 * @property string $title
 * @property CalendarEventKind $kind
 * @property Carbon $starts_on
 * @property Carbon $ends_on
 * @property bool $all_day
 * @property CalendarAudience $audience
 * @property string|null $description
 */
#[Fillable([
    'tenant_id',
    'academic_year_id',
    'campus_id',
    'title',
    'kind',
    'starts_on',
    'ends_on',
    'all_day',
    'audience',
    'description',
])]
final class CalendarEvent extends Model
{
    use BelongsToTenant;
    use HasUuid;
    use SoftDeletes;

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function campus(): BelongsTo
    {
        return $this->belongsTo(Campus::class);
    }

    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
            'all_day' => 'boolean',
            'kind' => CalendarEventKind::class,
            'audience' => CalendarAudience::class,
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    #[Scope]
    protected function inYear(Builder $query, string $academicYearId): void
    {
        $query->where('academic_year_id', $academicYearId);
    }

    #[Scope]
    protected function forCampus(Builder $query, ?string $campusId): void
    {
        $query->where(function (Builder $q) use ($campusId): void {
            $q->whereNull('campus_id');
            if ($campusId !== null) {
                $q->orWhere('campus_id', $campusId);
            }
        });
    }
}
