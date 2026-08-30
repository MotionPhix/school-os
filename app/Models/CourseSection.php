<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CourseStatus;
use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * CourseSection — a taught class: subject × grade × section × teacher
 * × academic year × campus.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $academic_year_id
 * @property string $campus_id
 * @property string $subject_id
 * @property string $grade_label
 * @property string $section_label
 * @property string $teacher_id
 * @property string|null $room
 * @property int $capacity
 * @property CourseStatus $status
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'tenant_id',
    'academic_year_id',
    'campus_id',
    'subject_id',
    'grade_label',
    'section_label',
    'teacher_id',
    'room',
    'capacity',
    'status',
])]
final class CourseSection extends Model
{
    use BelongsToTenant;
    use HasUuid;
    use SoftDeletes;

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function campus(): BelongsTo
    {
        return $this->belongsTo(Campus::class);
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(StaffMember::class, 'teacher_id');
    }

    public function students(): BelongsToMany
    {
        return $this->belongsToMany(Student::class, 'course_enrollments')
            ->withPivot(['id', 'tenant_id', 'enrolled_at'])
            ->withTimestamps();
    }

    public function timetableSlots(): HasMany
    {
        return $this->hasMany(TimetableSlot::class)->orderBy('weekday')->orderBy('period');
    }

    public function gradebookEntries(): HasMany
    {
        return $this->hasMany(GradebookEntry::class);
    }

    protected function casts(): array
    {
        return [
            'status' => CourseStatus::class,
            'capacity' => 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    #[Scope]
    protected function published(Builder $query): void
    {
        $query->where('status', CourseStatus::Published->value);
    }

    #[Scope]
    protected function forYear(Builder $query, string $yearId): void
    {
        $query->where('academic_year_id', $yearId);
    }
}
