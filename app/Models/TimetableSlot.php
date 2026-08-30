<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Weekday;
use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * TimetableSlot — a scheduled period for a CourseSection on a weekday.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $course_section_id
 * @property Weekday $weekday
 * @property int $period
 * @property string $starts_at
 * @property string $ends_at
 * @property string|null $room
 */
#[Fillable([
    'tenant_id',
    'course_section_id',
    'weekday',
    'period',
    'starts_at',
    'ends_at',
    'room',
])]
final class TimetableSlot extends Model
{
    use BelongsToTenant;
    use HasUuid;

    public function courseSection(): BelongsTo
    {
        return $this->belongsTo(CourseSection::class);
    }

    protected function casts(): array
    {
        return [
            'weekday' => Weekday::class,
            'period' => 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    #[Scope]
    protected function onDay(Builder $query, string $weekday): void
    {
        $query->where('weekday', $weekday);
    }
}
