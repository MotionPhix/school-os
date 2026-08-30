<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AttendanceStatus;
use App\Enums\Gender;
use App\Enums\StudentStage;
use App\Enums\StudentStatus;
use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Student aggregate — master record for a learner enrolled (or being
 * enrolled) at a campus. Personal data lives here; other capabilities
 * (Admissions, Academics, Attendance, Finance) reference by `id`.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $campus_id
 * @property string $admission_number
 * @property string $full_name
 * @property string|null $preferred_name
 * @property string $avatar_initials
 * @property Gender $gender
 * @property Carbon $date_of_birth
 * @property StudentStage $stage
 * @property string $grade_label
 * @property string|null $house
 * @property StudentStatus $status
 * @property Carbon|null $enrolled_on
 * @property string|null $avatar_path
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'tenant_id',
    'campus_id',
    'admission_number',
    'full_name',
    'preferred_name',
    'avatar_initials',
    'gender',
    'date_of_birth',
    'stage',
    'grade_label',
    'house',
    'status',
    'enrolled_on',
    'avatar_path',
])]
final class Student extends Model
{
    use BelongsToTenant;
    use HasUuid;
    use SoftDeletes;

    public function campus(): BelongsTo
    {
        return $this->belongsTo(Campus::class);
    }

    public function guardians(): BelongsToMany
    {
        return $this->belongsToMany(Guardian::class, 'student_guardians')
            ->using(StudentGuardian::class)
            ->withPivot(['id', 'tenant_id', 'relationship', 'is_primary'])
            ->withTimestamps();
    }

    public function documents(): MorphMany
    {
        return $this->morphMany(PersonDocument::class, 'subject', 'subject_type', 'subject_id', 'id');
    }

    /** Finance capability read-model reference (no write coupling). */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    /** Attendance capability read-model reference. */
    public function attendanceMarks(): HasMany
    {
        return $this->hasMany(AttendanceMark::class);
    }

    /** Hydrates the same computed columns on an already-loaded model. */
    public function loadMetrics(): self
    {
        $fresh = self::query()->withMetrics()->whereKey($this->getKey())->first();

        if ($fresh) {
            $this->setAttribute('invoices_sum_balance_minor', $fresh->invoices_sum_balance_minor);
            $this->setAttribute('attendance_total_30d', $fresh->attendance_total_30d);
            $this->setAttribute('attendance_present_30d', $fresh->attendance_present_30d);
        }

        return $this;
    }

    protected function casts(): array
    {
        return [
            'gender' => Gender::class,
            'stage' => StudentStage::class,
            'status' => StudentStatus::class,
            'date_of_birth' => 'date',
            'enrolled_on' => 'date',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * Cross-capability computed columns used by StudentResource:
     * `invoices_sum_balance_minor`, `attendance_present_30d`,
     * `attendance_total_30d`. Keeps list endpoints free of N+1 reads.
     */
    #[Scope]
    protected function withMetrics(Builder $query): void
    {
        $since = now()->subDays(30)->toDateString();

        $inWindow = fn (Builder $marks) => $marks->whereHas(
            'session',
            fn (Builder $session) => $session->whereDate('date', '>=', $since),
        );

        $query
            ->withSum('invoices', 'balance_minor')
            ->withCount([
                'attendanceMarks as attendance_total_30d' => $inWindow,
                'attendanceMarks as attendance_present_30d' => fn (Builder $marks) => $inWindow($marks)
                    ->whereIn('status', [
                        AttendanceStatus::Present->value,
                        AttendanceStatus::Late->value,
                    ]),
            ]);
    }

    #[Scope]
    protected function enrolled(Builder $query): void
    {
        $query->where('status', StudentStatus::Enrolled->value);
    }

    #[Scope]
    protected function atCampus(Builder $query, string $campusId): void
    {
        $query->where('campus_id', $campusId);
    }
}
