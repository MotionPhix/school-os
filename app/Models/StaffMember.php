<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\StaffCategory;
use App\Enums\StaffEmploymentType;
use App\Enums\StaffStatus;
use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Staff member aggregate — employed person (teaching or non-teaching)
 * assigned to a campus. `user_id` links to an Identity user once a
 * login is issued.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $campus_id
 * @property string|null $user_id
 * @property string $staff_number
 * @property string $full_name
 * @property string $avatar_initials
 * @property string $title
 * @property StaffCategory $category
 * @property string $department
 * @property StaffEmploymentType $employment_type
 * @property StaffStatus $status
 * @property string|null $contact_email
 * @property string|null $contact_phone
 * @property string|null $contact_address_line
 * @property string|null $contact_city
 * @property string|null $contact_region
 * @property array<int,string> $subjects_taught
 * @property Carbon $hired_on
 * @property string|null $avatar_path
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Campus $campus
 * @property-read User $user
 * @property-read Collection<int, PersonDocument> $documents
 */
#[Fillable([
    'tenant_id',
    'campus_id',
    'user_id',
    'staff_number',
    'full_name',
    'avatar_initials',
    'title',
    'category',
    'department',
    'employment_type',
    'status',
    'contact_email',
    'contact_phone',
    'contact_address_line',
    'contact_city',
    'contact_region',
    'subjects_taught',
    'hired_on',
    'avatar_path',
])]
final class StaffMember extends Model
{
    use BelongsToTenant;
    use HasUuid;
    use SoftDeletes;

    protected $table = 'staff_members';

    /** @return BelongsTo<Campus, $this> */
    public function campus(): BelongsTo
    {
        return $this->belongsTo(Campus::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return MorphMany<PersonDocument, $this> */
    public function documents(): MorphMany
    {
        return $this->morphMany(PersonDocument::class, 'subject', 'subject_type', 'subject_id', 'id');
    }

    protected function casts(): array
    {
        return [
            'category' => StaffCategory::class,
            'employment_type' => StaffEmploymentType::class,
            'status' => StaffStatus::class,
            'subjects_taught' => 'array',
            'hired_on' => 'date',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('status', StaffStatus::Active->value);
    }

    #[Scope]
    protected function teaching(Builder $query): void
    {
        $query->where('category', StaffCategory::Teaching->value);
    }
}
