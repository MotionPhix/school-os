<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CampusStatus;
use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Physical campus belonging to a tenant. A tenant has at least one
 * campus (created alongside the InstitutionProfile) and exactly one
 * primary campus.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $name
 * @property string $code
 * @property bool $is_primary
 * @property CampusStatus $status
 * @property string $address_line
 * @property string $city
 * @property string $region
 * @property string $timezone
 * @property int $building_count
 * @property Carbon|null $opened_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, CalendarEvent> $calendarEvents
 * @property-read Collection<int, Student> $students
 * @property-read Collection<int, StaffMember> $staffMembers
 */
#[Fillable([
    'tenant_id',
    'name',
    'code',
    'is_primary',
    'status',
    'address_line',
    'city',
    'region',
    'timezone',
    'building_count',
    'opened_at',
])]
final class Campus extends Model
{
    use BelongsToTenant;
    use HasUuid;
    use SoftDeletes;

    /** @return HasMany<CalendarEvent, $this> */
    public function calendarEvents(): HasMany
    {
        return $this->hasMany(CalendarEvent::class);
    }

    /** @return HasMany<Student, $this> */
    public function students(): HasMany
    {
        return $this->hasMany(Student::class);
    }

    /** @return HasMany<StaffMember, $this> */
    public function staffMembers(): HasMany
    {
        return $this->hasMany(StaffMember::class);
    }

    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
            'status' => CampusStatus::class,
            'building_count' => 'integer',
            'opened_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    #[Scope]
    protected function operational(Builder $query): void
    {
        $query->where('status', CampusStatus::Operational->value);
    }

    #[Scope]
    protected function primary(Builder $query): void
    {
        $query->where('is_primary', true);
    }
}
