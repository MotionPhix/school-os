<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\GuardianStatus;
use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Guardian aggregate — parent, caregiver, or sponsor associated with
 * one or more students. Portal access (once Slice 1 rolls out guardian
 * logins) will be issued through `user_id`.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string|null $user_id
 * @property string $full_name
 * @property string $avatar_initials
 * @property string|null $occupation
 * @property string|null $employer
 * @property string|null $contact_email
 * @property string|null $contact_phone
 * @property string|null $contact_address_line
 * @property string|null $contact_city
 * @property string|null $contact_region
 * @property string $preferred_language
 * @property GuardianStatus $portal_status
 * @property Carbon|null $portal_last_seen_at
 * @property string|null $avatar_path
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Student> $students
 * @property-read Collection<int, PersonDocument> $documents
 * @property-read StudentGuardian $pivot
 */
#[Fillable([
    'tenant_id',
    'user_id',
    'full_name',
    'avatar_initials',
    'occupation',
    'employer',
    'contact_email',
    'contact_phone',
    'contact_address_line',
    'contact_city',
    'contact_region',
    'preferred_language',
    'portal_status',
    'portal_last_seen_at',
    'avatar_path',
])]
final class Guardian extends Model
{
    use BelongsToTenant;
    use HasUuid;
    use SoftDeletes;

    /** @return BelongsToMany<Student, $this> */
    public function students(): BelongsToMany
    {
        return $this->belongsToMany(Student::class, 'student_guardians')
            ->using(StudentGuardian::class)
            ->withPivot(['id', 'tenant_id', 'relationship', 'is_primary'])
            ->withTimestamps();
    }

    /** @return MorphMany<PersonDocument, $this> */
    public function documents(): MorphMany
    {
        return $this->morphMany(PersonDocument::class, 'subject', 'subject_type', 'subject_id', 'id');
    }

    protected function casts(): array
    {
        return [
            'portal_status' => GuardianStatus::class,
            'portal_last_seen_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('portal_status', GuardianStatus::Active->value);
    }
}
