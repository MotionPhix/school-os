<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CurrencyCode;
use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property Carbon $occurred_on
 * @property string $reference
 * @property string|null $memo
 * @property string|null $source_type
 * @property string|null $source_id
 * @property CurrencyCode $currency
 * @property bool $is_reversal
 */
#[Fillable([
    'tenant_id', 'occurred_on', 'reference', 'memo', 'source_type',
    'source_id', 'currency', 'is_reversal', 'reverses_entry_id',
    'posted_by', 'posted_at',
])]
final class JournalEntry extends Model
{
    use BelongsToTenant;
    use HasUuid;

    protected $table = 'finance_journal_entries';

    public function postings(): HasMany
    {
        return $this->hasMany(LedgerPosting::class);
    }

    public function reverses(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reverses_entry_id');
    }

    public function reversals(): HasMany
    {
        return $this->hasMany(self::class, 'reverses_entry_id');
    }

    protected function casts(): array
    {
        return [
            'occurred_on' => 'date',
            'currency' => CurrencyCode::class,
            'is_reversal' => 'boolean',
            'posted_at' => 'datetime',
        ];
    }
}
