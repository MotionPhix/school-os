<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasUuid;
use App\Policies\PlatformBilling\PlatformInvoicePolicy;
use Database\Factories\PlatformInvoiceFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\Access\UsePolicy;
use Illuminate\Support\Carbon;

/**
 * A platform invoice: the amount the TENANT owes the platform for a billing
 * period (subscription). This is system-level billing — distinct from the
 * tenant's own finance invoices (which bill students).
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $period
 * @property int $amount_minor
 * @property string $currency
 * @property string $status
 * @property Carbon $issued_at
 * @property Carbon|null $due_at
 * @property Carbon|null $paid_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Tenant $tenant
 * @property-read Collection<int, PlatformPayment> $payments
 */
#[UsePolicy(PlatformInvoicePolicy::class)]
class PlatformInvoice extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<PlatformInvoiceFactory> */
    use HasFactory;

    use HasUuid;

    protected $table = 'platform_invoices';

    protected $fillable = [
        'tenant_id', 'period', 'amount_minor', 'currency',
        'status', 'issued_at', 'due_at', 'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'issued_at' => 'datetime',
            'due_at' => 'datetime',
            'paid_at' => 'datetime',
            'amount_minor' => 'integer',
        ];
    }

    /** @return BelongsTo<Tenant, $this> */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /** @return HasMany<PlatformPayment, $this> */
    public function payments(): HasMany
    {
        return $this->hasMany(PlatformPayment::class);
    }
}
