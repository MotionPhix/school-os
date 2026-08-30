<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ExamPeriodStatus;
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
 * ExamPeriod — a scheduled exam window within a Term.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $academic_year_id
 * @property string $term_id
 * @property string $name
 * @property Carbon $starts_on
 * @property Carbon $ends_on
 * @property ExamPeriodStatus $status
 */
#[Fillable([
    'tenant_id',
    'academic_year_id',
    'term_id',
    'name',
    'starts_on',
    'ends_on',
    'status',
])]
final class ExamPeriod extends Model
{
    use BelongsToTenant;
    use HasUuid;

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function term(): BelongsTo
    {
        return $this->belongsTo(Term::class);
    }

    public function exams(): HasMany
    {
        return $this->hasMany(Exam::class, 'period_id');
    }

    protected function casts(): array
    {
        return [
            'status' => ExamPeriodStatus::class,
            'starts_on' => 'date',
            'ends_on' => 'date',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    #[Scope]
    protected function open(Builder $query): void
    {
        $query->whereIn('status', [
            ExamPeriodStatus::Scheduled->value,
            ExamPeriodStatus::InProgress->value,
        ]);
    }

    #[Scope]
    protected function forTerm(Builder $query, string $termId): void
    {
        $query->where('term_id', $termId);
    }
}
