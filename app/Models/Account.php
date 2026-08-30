<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AccountKind;
use App\Enums\AccountType;
use App\Enums\CurrencyCode;
use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Chart-of-accounts row for a tenant.
 *
 * @property string $id
 * @property string $tenant_id
 * @property AccountKind $kind
 * @property AccountType $type
 * @property string $name
 * @property CurrencyCode $currency
 * @property bool $is_system
 * @property bool $is_active
 */
#[Fillable([
    'tenant_id', 'kind', 'type', 'name', 'currency', 'is_system', 'is_active',
])]
final class Account extends Model
{
    use BelongsToTenant;
    use HasUuid;

    protected $table = 'finance_accounts';

    public function postings(): HasMany
    {
        return $this->hasMany(LedgerPosting::class);
    }

    protected function casts(): array
    {
        return [
            'kind' => AccountKind::class,
            'type' => AccountType::class,
            'currency' => CurrencyCode::class,
            'is_system' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    #[Scope]
    protected function ofKind(Builder $query, AccountKind $kind): void
    {
        $query->where('kind', $kind->value);
    }

    #[Scope]
    protected function ofType(Builder $query, AccountType $type): void
    {
        $query->where('type', $type->value);
    }
}
