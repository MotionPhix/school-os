<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Support\Carbon;

/**
 * Explicit pivot model so the membership row has its own UUID PK, own
 * timestamps, and can hold role_ids as a JSON array cast.
 *
 * @property string $id
 * @property string $user_id
 * @property string $tenant_id
 * @property array<int,string> $role_ids
 * @property Carbon $joined_at
 */
#[Fillable([
    'user_id',
    'tenant_id',
    'role_ids',
    'joined_at',
])]
final class TenantMembership extends Pivot
{
    use HasUuid;

    public $incrementing = false;

    public $timestamps = true;

    protected $keyType = 'string';

    protected $table = 'tenant_memberships';

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    protected function casts(): array
    {
        return [
            'role_ids' => 'array',
            'joined_at' => 'datetime',
        ];
    }
}
