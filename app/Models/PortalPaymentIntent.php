<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A guardian's checkout attempt in the parents portal. The tx_ref is the
 * link between the tenant's PayChangu account and the finance booking —
 * the webhook/refresh re-verifies server-side with the TENANT's keys, then
 * RecordPayment settles the invoice inside the tenant's finance domain.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $invoice_id
 * @property string $guardian_user_id
 * @property string $tx_ref
 * @property int $amount_minor
 * @property string $currency
 * @property string $status
 * @property string|null $checkout_url
 * @property array<string, mixed>|null $payload
 * @property Carbon|null $verified_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Invoice $invoice
 */
class PortalPaymentIntent extends Model
{
    use BelongsToTenant;
    use HasUuid;

    protected $table = 'portal_payment_intents';

    protected $fillable = [
        'tenant_id', 'invoice_id', 'guardian_user_id', 'tx_ref', 'amount_minor',
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

    /** @return BelongsTo<Invoice, $this> */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
