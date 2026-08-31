<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AttendanceSessionStatus;
use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * AttendanceSession — one taken register for a CourseSection on a date/period.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $course_section_id
 * @property Carbon $date
 * @property int $period
 * @property AttendanceSessionStatus $status
 * @property int $present_count
 * @property int $absent_count
 * @property int $late_count
 * @property int $excused_count
 * @property int $total_count
 * @property string|null $opened_by
 * @property Carbon|null $taken_at
 * @property Carbon|null $updated_at
 * @property-read CourseSection $courseSection
 * @property-read Collection<int, AttendanceMark> $marks
 */
#[Fillable([
    'tenant_id',
    'course_section_id',
    'date',
    'period',
    'status',
    'present_count',
    'absent_count',
    'late_count',
    'excused_count',
    'total_count',
    'opened_by',
    'taken_at',
])]
final class AttendanceSession extends Model
{
    use BelongsToTenant;
    use HasUuid;

    /** @return BelongsTo<CourseSection, $this> */
    public function courseSection(): BelongsTo
    {
        return $this->belongsTo(CourseSection::class);
    }

    /** @return HasMany<AttendanceMark, $this> */
    public function marks(): HasMany
    {
        return $this->hasMany(AttendanceMark::class, 'session_id');
    }

    protected function casts(): array
    {
        return [
            'status' => AttendanceSessionStatus::class,
            'date' => 'date',
            'period' => 'integer',
            'present_count' => 'integer',
            'absent_count' => 'integer',
            'late_count' => 'integer',
            'excused_count' => 'integer',
            'total_count' => 'integer',
            'taken_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    #[Scope]
    protected function draft(Builder $query): void
    {
        $query->where('status', AttendanceSessionStatus::Draft->value);
    }

    #[Scope]
    protected function submitted(Builder $query): void
    {
        $query->where('status', AttendanceSessionStatus::Submitted->value);
    }

    #[Scope]
    protected function forSection(Builder $query, string $sectionId): void
    {
        $query->where('course_section_id', $sectionId);
    }

    #[Scope]
    protected function onDate(Builder $query, string $date): void
    {
        $query->whereDate('date', $date);
    }
}
