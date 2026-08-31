<?php

declare(strict_types=1);

namespace App\Domains\PlatformBilling\Services;

use App\Models\PlatformInvoice;
use App\Models\PlatformPayment;
use App\Support\Payments\PayChanguClient;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Start a PayChangu hosted checkout for a platform invoice. Reuses an
 * open (pending) payment for the invoice so repeated clicks don't spawn
 * orphan sessions; the authoritative status always comes from a
 * server-side verification afterwards.
 */
final class StartPlatformCheckout
{
    public function __construct(private readonly PayChanguClient $client) {}

    /**
     * @return array{checkout_url: string, tx_ref: string, payment: PlatformPayment}
     */
    public function handle(PlatformInvoice $invoice, string $email = '', string $name = ''): array
    {
        if ($invoice->status === 'paid') {
            throw ValidationException::withMessages(['status' => 'This invoice is already paid.']);
        }

        $payment = PlatformPayment::query()
            ->where('platform_invoice_id', $invoice->id)
            ->where('status', 'pending')
            ->latest('id')
            ->first();

        if ($payment === null) {
            $payment = PlatformPayment::create([
                'tenant_id' => $invoice->tenant_id,
                'platform_invoice_id' => $invoice->id,
                'tx_ref' => 'scp-'.(string) Str::uuid7(),
                'amount_minor' => (int) $invoice->amount_minor,
                'currency' => $invoice->currency,
                'status' => 'pending',
            ]);
        }

        // PayChangu charges in major units.
        $amountMajor = max(1, (int) round($invoice->amount_minor / 100));
        $names = collect(explode(' ', $name, 2))->map(fn (string $p) => $p)->values();

        $result = $this->client->createCheckout([
            'amount' => $amountMajor,
            'currency' => $invoice->currency,
            'email' => $email !== '' ? $email : 'billing@schoolos.app',
            'first_name' => $names->get(0) ?? 'Tenant',
            'last_name' => $names->get(1) ?? 'Admin',
            'callback_url' => $this->callbackUrl(),
            'return_url' => $this->returnUrl(),
            'tx_ref' => $payment->tx_ref,
            'customization' => [
                'title' => 'SchoolOS subscription',
                'description' => sprintf('Subscription for period %s', $invoice->period),
            ],
            'meta' => ['platform_invoice_id' => $invoice->id],
        ]);

        $payment->forceFill([
            'tx_ref' => $result['tx_ref'],
            'checkout_url' => $result['checkout_url'],
        ])->save();

        return ['checkout_url' => $result['checkout_url'], 'tx_ref' => $result['tx_ref'], 'payment' => $payment];
    }

    private function appUrl(): string
    {
        $url = config('app.url');

        return rtrim(is_string($url) ? $url : '', '/');
    }

    private function callbackUrl(): string
    {
        return $this->appUrl().'/api/v1/billing/webhooks/paychangu';
    }

    private function returnUrl(): string
    {
        return $this->appUrl().'/api/v1/billing/return';
    }
}
