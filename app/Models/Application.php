<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ApplicationSource;
use App\Enums\Gender;
use App\Enums\PipelineStage;
use App\Enums\StudentStage;
use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Admissions Application — root aggregate for a prospective learner
 * moving through the enrollment pipeline. On terminal `enrolled` stage
 * the ApplicationEnrolled event mints a Student and stamps `student_id`.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $reference
 * @property string $applicant_full_name
 * @property string|null $applicant_preferred_name
 * @property string $avatar_initials
 * @property Carbon $date_of_birth
 * @property Gender $gender
 * @property string $guardian_name
 * @property string|null $guardian_email
 * @property string|null $guardian_phone
 * @property string|null $guardian_id
 * @property string $campus_id
 * @property string $academic_year_id
 * @property StudentStage $intended_stage
 * @property string $intended_grade_label
 * @property ApplicationSource $source
 * @property PipelineStage $stage
 * @property int|null $assessment_score
 * @property int|null $interview_score
 * @property string|null $student_id
 * @property Carbon|null $submitted_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'tenant_id',
    'reference',
    'applicant_full_name',
    'applicant_preferred_name',
    'avatar_initials',
    'date_of_birth',
    'gender',
    'guardian_name',
    'guardian_email',
    'guardian_phone',
    'guardian_id',
    'campus_id',
    'academic_year_id',
    'intended_stage',
    'intended_grade_label',
    'source',
    'stage',
    'assessment_score',
    'interview_score',
    'student_id',
    'submitted_at',
])]
final class Application extends Model
{
    use BelongsToTenant;
    use HasUuid;
    use SoftDeletes;

    public function campus(): BelongsTo
    {
        return $this->belongsTo(Campus::class);
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function guardian(): BelongsTo
    {
        return $this->belongsTo(Guardian::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function offers(): HasMany
    {
        return $this->hasMany(ApplicationOffer::class);
    }

    /** Convenience: the latest offer (any status) for offer-panel UIs. */
    public function currentOffer(): HasOne
    {
        return $this->hasOne(ApplicationOffer::class)->latestOfMany('created_at');
    }

    public function timeline(): HasMany
    {
        return $this->hasMany(ApplicationStageEvent::class)->orderBy('occurred_at');
    }

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'gender' => Gender::class,
            'intended_stage' => StudentStage::class,
            'source' => ApplicationSource::class,
            'stage' => PipelineStage::class,
            'assessment_score' => 'integer',
            'interview_score' => 'integer',
            'submitted_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    #[Scope]
    protected function open(Builder $query): void
    {
        $query->whereIn('stage', [
            PipelineStage::Enquiry->value,
            PipelineStage::Application->value,
            PipelineStage::Assessment->value,
            PipelineStage::Interview->value,
            PipelineStage::Offer->value,
            PipelineStage::Accepted->value,
        ]);
    }

    #[Scope]
    protected function atStage(Builder $query, string $stage): void
    {
        $query->where('stage', $stage);
    }
}
