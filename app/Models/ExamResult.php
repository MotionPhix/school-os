<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\GradeBand;
use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * ExamResult — per-student score for an Exam.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $exam_id
 * @property string $student_id
 * @property int|null $score
 * @property GradeBand|null $band
 * @property string|null $remarks
 * @property string|null $recorded_by
 */
#[Fillable([
    'tenant_id',
    'exam_id',
    'student_id',
    'score',
    'band',
    'remarks',
    'recorded_by',
])]
final class ExamResult extends Model
{
    use BelongsToTenant;
    use HasUuid;

    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    protected function casts(): array
    {
        return [
            'score' => 'integer',
            'band' => GradeBand::class,
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    #[Scope]
    protected function graded(Builder $query): void
    {
        $query->whereNotNull('score');
    }

    #[Scope]
    protected function forExam(Builder $query, string $examId): void
    {
        $query->where('exam_id', $examId);
    }
}
