<?php

declare(strict_types=1);

namespace App\Domains\PlatformBilling\Readers;

use App\Models\PlatformInvoice;
use App\Models\PlatformPayment;
use App\Models\Tenant;
use App\Support\TenantContext;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Billing overview for the current tenant: ensures the invoice for the
 * current billing period exists (issue-on-read), and returns it together
 * with the recent payment history and totals.
 */
final class BillingOverviewReader
{
    /**
     * @return array{invoice: PlatformInvoice, payments: Collection<int, PlatformPayment>, total_paid_minor: int, currency: string}
     */
    public function read(?Tenant $tenant = null): array
    {
        $tenant ??= Tenant::query()->findOrFail((string) app(TenantContext::class)->id());
        $period = now()->format('Y-m');

        $invoice = PlatformInvoice::query()
            ->where('tenant_id', $tenant->id)
            ->where('period', $period)
            ->first();

        if ($invoice === null) {
            $monthlyFee = config('billing.monthly_fee_minor');
            $invoice = PlatformInvoice::create([
                'tenant_id' => $tenant->id,
                'period' => $period,
                'amount_minor' => is_int($monthlyFee) ? max(0, $monthlyFee) : 50000,
                'currency' => $this->tenantCurrency($tenant),
                'status' => 'pending',
                'issued_at' => now(),
                'due_at' => Carbon::now()->endOfMonth(),
            ]);
        }

        $payments = $invoice->payments()->latest('created_at')->limit(12)->get();

        return [
            'invoice' => $invoice,
            'payments' => $payments,
            'total_paid_minor' => (int) $invoice->payments()->where('status', 'succeeded')->sum('amount_minor'),
            'currency' => $invoice->currency,
        ];
    }

    private function tenantCurrency(Tenant $tenant): string
    {
        $currency = strtoupper((string) $tenant->currency_code);
        $supported = (array) config('billing.paychangu.supported_currencies', ['MWK', 'USD']);

        return in_array($currency, $supported, true) ? $currency : 'MWK';
    }
}
