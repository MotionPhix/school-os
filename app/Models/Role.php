<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\RoleScope;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A named bundle of permission keys, optionally scoped to a tenant.
 *
 * Platform roles have `tenant_id = null` and are visible to every tenant.
 * System roles (`is_system = true`) are seeded from config/identity.php
 * and cannot be deleted through the API.
 *
 * @property string $id
 * @property string|null $tenant_id
 * @property string $key
 * @property string $name
 * @property string $description
 * @property RoleScope $scope
 * @property bool $is_system
 * @property array<int,string> $permission_keys
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'tenant_id',
    'key',
    'name',
    'description',
    'scope',
    'is_system',
    'permission_keys',
])]
final class Role extends Model
{
    use HasUuid;

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    protected function casts(): array
    {
        return [
            'scope' => RoleScope::class,
            'is_system' => 'boolean',
            'permission_keys' => 'array',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    #[Scope]
    protected function platform(Builder $query): void
    {
        $query->whereNull('tenant_id');
    }

    #[Scope]
    protected function forTenant(Builder $query, string $tenantId): void
    {
        $query->where(function (Builder $q) use ($tenantId): void {
            $q->whereNull('tenant_id')->orWhere('tenant_id', $tenantId);
        });
    }

    #[Scope]
    protected function system(Builder $query): void
    {
        $query->where('is_system', true);
    }
}
