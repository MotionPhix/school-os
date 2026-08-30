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
use Illuminate\Support\Carbon;

/**
 * GradebookEntry — student × course_section × term score record.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $course_section_id
 * @property string $term_id
 * @property string $student_id
 * @property int $continuous_assessment
 * @property int $exam_score
 * @property int $total
 * @property GradeBand $band
 * @property string|null $remarks
 * @property string|null $recorded_by
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'tenant_id',
    'course_section_id',
    'term_id',
    'student_id',
    'continuous_assessment',
    'exam_score',
    'total',
    'band',
    'remarks',
    'recorded_by',
])]
final class GradebookEntry extends Model
{
    use BelongsToTenant;
    use HasUuid;

    public function courseSection(): BelongsTo
    {
        return $this->belongsTo(CourseSection::class);
    }

    public function term(): BelongsTo
    {
        return $this->belongsTo(Term::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    protected function casts(): array
    {
        return [
            'continuous_assessment' => 'integer',
            'exam_score' => 'integer',
            'total' => 'integer',
            'band' => GradeBand::class,
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    #[Scope]
    protected function forSection(Builder $query, string $sectionId): void
    {
        $query->where('course_section_id', $sectionId);
    }

    #[Scope]
    protected function forTerm(Builder $query, string $termId): void
    {
        $query->where('term_id', $termId);
    }
}
