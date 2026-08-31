<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\PlatformInvoice;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PlatformInvoice>
 */
class PlatformInvoiceFactory extends Factory
{
    protected $model = PlatformInvoice::class;

    public function definition(): array
    {
        $monthlyFee = config('billing.monthly_fee_minor');

        return [
            'tenant_id' => Tenant::factory(),
            'period' => now()->format('Y-m'),
            'amount_minor' => is_int($monthlyFee) ? max(0, $monthlyFee) : 50000,
            'currency' => 'MWK',
            'status' => 'pending',
            'issued_at' => now(),
        ];
    }
}
