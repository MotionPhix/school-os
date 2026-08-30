<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\UserStatus;
use App\Models\Concerns\HasUuid;
use App\Support\TenantContext;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\HasApiTokens;

/**
 * @property string $id
 * @property string $name
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property UserStatus $status
 * @property Carbon|null $last_active_at
 * @property bool $mfa_enabled
 * @property string|null $active_tenant_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'name',
    'email',
    'password',
    'status',
    'last_active_at',
    'mfa_enabled',
    'active_tenant_id',
])]
#[Hidden([
    'password',
    'remember_token',
])]
final class User extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens;

    /** @use HasFactory<UserFactory> */
    use HasFactory;

    use HasUuid;
    use Notifiable;

    public function memberships(): BelongsToMany
    {
        return $this->belongsToMany(Tenant::class, 'tenant_memberships')
            ->using(TenantMembership::class)
            ->withPivot(['id', 'role_ids', 'joined_at'])
            ->withTimestamps();
    }

    /** Convenience: the initials rendered by the frontend avatar. */
    public function avatarInitials(): string
    {
        $parts = preg_split('/\s+/', mb_trim($this->name)) ?: [];
        $first = mb_substr($parts[0] ?? '', 0, 1);
        $last = mb_substr($parts[count($parts) - 1] ?? '', 0, 1);

        return mb_strtoupper($first.($first !== $last ? $last : ''));
    }

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_active_at' => 'datetime',
            'password' => 'hashed',
            'status' => UserStatus::class,
            'mfa_enabled' => 'boolean',
        ];
    }

    /**
     * Whether the user's roles in the given tenant carry a capability key.
     * Used by notification recipient policies and ad-hoc capability checks.
     */
    public function hasPermission(string $key, ?string $tenantId = null): bool
    {
        $tenantId ??= app(TenantContext::class)->id();
        if ($tenantId === null) {
            return false;
        }

        $roleIds = $this->memberships()
            ->where('tenants.id', $tenantId)
            ->first()
            ?->pivot
            ?->role_ids ?? [];

        if ($roleIds === []) {
            return false;
        }

        return Role::query()
            ->whereIn('id', $roleIds)
            ->get(['permission_keys'])
            ->flatMap(fn (Role $role): array => $role->permission_keys)
            ->unique()
            ->contains($key);
    }

    #[Scope]
    protected function verified(Builder $query): void
    {
        $query->whereNotNull('email_verified_at');
    }

    #[Scope]
    protected function inTenant(Builder $query, string $tenantId): void
    {
        $query->whereHas('memberships', fn (Builder $q) => $q->where('tenants.id', $tenantId));
    }

    #[Scope]
    protected function status(Builder $query, UserStatus|string $status): void
    {
        $query->where('status', $status instanceof UserStatus ? $status->value : $status);
    }
}
