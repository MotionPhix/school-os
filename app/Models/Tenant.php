<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TenantStatus;
use App\Enums\TenantTier;
use App\Models\Concerns\HasUuid;
use Database\Factories\TenantFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $slug
 * @property string $name
 * @property string $legal_name
 * @property string $country_code
 * @property string $timezone
 * @property string $currency_code
 * @property TenantTier $tier
 * @property TenantStatus $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'slug',
    'name',
    'legal_name',
    'country_code',
    'timezone',
    'currency_code',
    'tier',
    'status',
])]
final class Tenant extends Model
{
    /** @use HasFactory<TenantFactory> */
    use HasFactory;

    use HasUuid;

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'tenant_memberships')
            ->using(TenantMembership::class)
            ->withPivot(['id', 'role_ids', 'joined_at'])
            ->withTimestamps();
    }

    public function campuses(): HasMany
    {
        return $this->hasMany(Campus::class);
    }

    public function roles(): HasMany
    {
        return $this->hasMany(Role::class);
    }

    public function invitations(): HasMany
    {
        return $this->hasMany(Invitation::class);
    }

    protected function casts(): array
    {
        return [
            'tier' => TenantTier::class,
            'status' => TenantStatus::class,
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('status', TenantStatus::Active->value);
    }

    #[Scope]
    protected function tier(Builder $query, TenantTier|string $tier): void
    {
        $query->where('tier', $tier instanceof TenantTier ? $tier->value : $tier);
    }
}
