<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\OfferStatus;
use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string $application_id
 * @property OfferStatus $status
 * @property int $fee_amount Minor units.
 * @property string $currency_code
 * @property Carbon|null $sent_at
 * @property Carbon|null $expires_on
 * @property Carbon|null $responded_at
 */
#[Fillable([
    'tenant_id',
    'application_id',
    'status',
    'fee_amount',
    'currency_code',
    'sent_at',
    'expires_on',
    'responded_at',
])]
final class ApplicationOffer extends Model
{
    use BelongsToTenant;
    use HasUuid;

    protected $table = 'application_offers';

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    protected function casts(): array
    {
        return [
            'status' => OfferStatus::class,
            'fee_amount' => 'integer',
            'sent_at' => 'datetime',
            'expires_on' => 'date',
            'responded_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }
}
