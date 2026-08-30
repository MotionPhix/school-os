<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\FeeCategory;
use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $invoice_id
 * @property string|null $fee_structure_id
 * @property string $description
 * @property FeeCategory $category
 * @property int $quantity
 * @property int $unit_amount_minor
 * @property int $amount_minor
 * @property int $position
 */
#[Fillable([
    'tenant_id', 'invoice_id', 'fee_structure_id', 'description',
    'category', 'quantity', 'unit_amount_minor', 'amount_minor', 'position',
])]
final class InvoiceLine extends Model
{
    use BelongsToTenant;
    use HasUuid;

    protected $table = 'finance_invoice_lines';

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function feeStructure(): BelongsTo
    {
        return $this->belongsTo(FeeStructure::class);
    }

    protected function casts(): array
    {
        return [
            'category' => FeeCategory::class,
            'quantity' => 'integer',
            'unit_amount_minor' => 'integer',
            'amount_minor' => 'integer',
            'position' => 'integer',
        ];
    }
}
