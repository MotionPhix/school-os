<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CurrencyCode;
use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One debit or credit line inside a JournalEntry.
 *
 * @property string $id
 * @property string $journal_entry_id
 * @property string $account_id
 * @property string $side debit|credit
 * @property int $amount_minor
 * @property Carbon $occurred_on
 * @property CurrencyCode $currency
 * @property string|null $memo
 */
#[Fillable([
    'tenant_id', 'journal_entry_id', 'account_id', 'side',
    'amount_minor', 'occurred_on', 'currency', 'memo',
])]
final class LedgerPosting extends Model
{
    use BelongsToTenant;
    use HasUuid;

    public const SIDE_DEBIT = 'debit';

    public const SIDE_CREDIT = 'credit';

    protected $table = 'finance_ledger_postings';

    public function entry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'journal_entry_id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    protected function casts(): array
    {
        return [
            'amount_minor' => 'integer',
            'occurred_on' => 'date',
            'currency' => CurrencyCode::class,
        ];
    }

    #[Scope]
    protected function debits(Builder $query): void
    {
        $query->where('side', self::SIDE_DEBIT);
    }

    #[Scope]
    protected function credits(Builder $query): void
    {
        $query->where('side', self::SIDE_CREDIT);
    }

    #[Scope]
    protected function between(Builder $query, string $from, string $to): void
    {
        $query->whereBetween('occurred_on', [$from, $to]);
    }
}
