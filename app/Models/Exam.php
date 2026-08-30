<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ExamStatus;
use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Exam — a paper set for one CourseSection inside an ExamPeriod.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $period_id
 * @property string $course_section_id
 * @property string $paper_title
 * @property Carbon $scheduled_on
 * @property string $starts_at
 * @property int $duration_minutes
 * @property string|null $room
 * @property int $max_score
 * @property int $pass_mark
 * @property ExamStatus $status
 * @property Carbon|null $published_at
 * @property string|null $published_by
 */
#[Fillable([
    'tenant_id',
    'period_id',
    'course_section_id',
    'paper_title',
    'scheduled_on',
    'starts_at',
    'duration_minutes',
    'room',
    'max_score',
    'pass_mark',
    'status',
    'published_at',
    'published_by',
])]
final class Exam extends Model
{
    use BelongsToTenant;
    use HasUuid;

    public function period(): BelongsTo
    {
        return $this->belongsTo(ExamPeriod::class, 'period_id');
    }

    public function courseSection(): BelongsTo
    {
        return $this->belongsTo(CourseSection::class);
    }

    public function results(): HasMany
    {
        return $this->hasMany(ExamResult::class);
    }

    protected function casts(): array
    {
        return [
            'status' => ExamStatus::class,
            'scheduled_on' => 'date',
            'duration_minutes' => 'integer',
            'max_score' => 'integer',
            'pass_mark' => 'integer',
            'published_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    #[Scope]
    protected function published(Builder $query): void
    {
        $query->where('status', ExamStatus::Published->value);
    }

    #[Scope]
    protected function forPeriod(Builder $query, string $periodId): void
    {
        $query->where('period_id', $periodId);
    }

    #[Scope]
    protected function forSection(Builder $query, string $sectionId): void
    {
        $query->where('course_section_id', $sectionId);
    }
}
