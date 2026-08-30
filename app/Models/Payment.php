<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CurrencyCode;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $invoice_id
 * @property string $invoice_number
 * @property string $student_name
 * @property string $reference
 * @property PaymentMethod $method
 * @property string $gateway
 * @property int $amount_minor
 * @property int $gateway_fee_minor
 * @property CurrencyCode $currency
 * @property PaymentStatus $status
 * @property Carbon $received_at
 * @property string|null $note
 */
#[Fillable([
    'tenant_id', 'invoice_id', 'invoice_number', 'student_name', 'reference',
    'method', 'gateway', 'amount_minor', 'gateway_fee_minor', 'currency',
    'status', 'received_at', 'note', 'recorded_by', 'refunded_by_payment_id',
])]
final class Payment extends Model
{
    use BelongsToTenant;
    use HasUuid;

    protected $table = 'finance_payments';

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    protected function casts(): array
    {
        return [
            'method' => PaymentMethod::class,
            'status' => PaymentStatus::class,
            'currency' => CurrencyCode::class,
            'amount_minor' => 'integer',
            'gateway_fee_minor' => 'integer',
            'received_at' => 'datetime',
        ];
    }

    #[Scope]
    protected function succeeded(Builder $query): void
    {
        $query->where('status', PaymentStatus::Succeeded->value);
    }

    #[Scope]
    protected function forGateway(Builder $query, string $gateway): void
    {
        $query->where('gateway', $gateway);
    }
}
