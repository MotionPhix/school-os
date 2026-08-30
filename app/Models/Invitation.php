<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\InvitationStatus;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Invitation to join a tenant with a preset role bundle.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $email
 * @property array<int,string> $role_ids
 * @property string $token_hash
 * @property InvitationStatus $status
 * @property string|null $invited_by_id
 * @property Carbon $expires_at
 * @property Carbon|null $accepted_at
 * @property Carbon|null $revoked_at
 */
#[Fillable([
    'tenant_id',
    'email',
    'role_ids',
    'token_hash',
    'status',
    'invited_by_id',
    'expires_at',
    'accepted_at',
    'revoked_at',
])]
#[Hidden([
    'token_hash',
])]
final class Invitation extends Model
{
    use HasUuid;

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function invitedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by_id');
    }

    public function isRedeemable(): bool
    {
        return $this->status === InvitationStatus::Pending
            && $this->expires_at->isFuture();
    }

    protected function casts(): array
    {
        return [
            'role_ids' => 'array',
            'status' => InvitationStatus::class,
            'expires_at' => 'datetime',
            'accepted_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    #[Scope]
    protected function open(Builder $query): void
    {
        $query->where('status', InvitationStatus::Pending->value)
            ->where('expires_at', '>', now());
    }
}
