<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Append-only projection of Business Events, used for the tenant activity
 * log and for troubleshooting. Rows are never updated or deleted by the API.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $name
 * @property string|null $actor_id
 * @property string $actor_name
 * @property string|null $subject_type
 * @property string|null $subject_id
 * @property string|null $subject_label
 * @property string $summary
 * @property array<string,mixed> $metadata
 * @property Carbon $occurred_at
 */
#[Fillable([
    'tenant_id',
    'name',
    'actor_id',
    'actor_name',
    'subject_type',
    'subject_id',
    'subject_label',
    'summary',
    'metadata',
    'occurred_at',
])]
final class AuditEvent extends Model
{
    use HasUuid;

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'occurred_at' => 'datetime',
        ];
    }

    #[Scope]
    protected function forDomain(Builder $query, string $domain): void
    {
        $query->where('name', 'like', $domain.'.%');
    }

    #[Scope]
    protected function since(Builder $query, Carbon $moment): void
    {
        $query->where('occurred_at', '>=', $moment);
    }
}
