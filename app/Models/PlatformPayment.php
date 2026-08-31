<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasUuid;
use App\Policies\PlatformBilling\PlatformPaymentPolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\Access\UsePolicy;
use Illuminate\Support\Carbon;

/**
 * A payment attempt against a platform invoice, collected via PayChangu
 * standard checkout. The authoritative status is always re-verified
 * server-side with PayChangu before an invoice is marked paid.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $platform_invoice_id
 * @property string $tx_ref
 * @property int $amount_minor
 * @property string $currency
 * @property string $status
 * @property string|null $checkout_url
 * @property array<string, mixed>|null $payload
 * @property Carbon|null $verified_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read PlatformInvoice $invoice
 */
#[UsePolicy(PlatformPaymentPolicy::class)]
class PlatformPayment extends Model
{
    use BelongsToTenant;
    use HasUuid;

    protected $table = 'platform_payments';

    protected $fillable = [
        'tenant_id', 'platform_invoice_id', 'tx_ref', 'amount_minor',
        'currency', 'status', 'checkout_url', 'payload', 'verified_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'verified_at' => 'datetime',
            'amount_minor' => 'integer',
        ];
    }

    /** @return BelongsTo<PlatformInvoice, $this> */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(PlatformInvoice::class, 'platform_invoice_id');
    }
}
