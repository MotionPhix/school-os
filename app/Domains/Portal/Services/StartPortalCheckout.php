<?php

declare(strict_types=1);

namespace App\Domains\Portal\Services;

use App\Models\Invoice;
use App\Models\PortalPaymentIntent;
use App\Models\TenantPaymentProvider;
use App\Models\User;
use App\Support\Payments\PayChanguClientFactory;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Start a PayChangu checkout for a school invoice using the TENANT'S OWN
 * credentials — the money settles to the tenant's bank account, never the
 * platform. The authoritative status always comes from a later server-side
 * verification (ConfirmPortalPayment).
 */
final class StartPortalCheckout
{
    public function __construct(private readonly PayChanguClientFactory $clients) {}

    /**
     * @return array{checkout_url: string, tx_ref: string, intent: PortalPaymentIntent}
     */
    public function handle(Invoice $invoice, User $guardian): array
    {
        $provider = TenantPaymentProvider::query()->where('is_active', true)->first();
        if ($provider === null) {
            throw ValidationException::withMessages(['provider' => 'This school has not connected a payment provider yet.']);
        }

        $balance = max(0, (int) $invoice->total_minor - (int) $invoice->paid_minor);
        if ($balance <= 0) {
            throw ValidationException::withMessages(['status' => 'This invoice has no outstanding balance.']);
        }

        $supported = (array) config('billing.paychangu.supported_currencies', ['MWK', 'USD']);
        if (! in_array(strtoupper($invoice->currency->value), $supported, true)) {
            throw ValidationException::withMessages(['currency' => 'PayChangu supports MWK/USD only for this invoice.']);
        }

        $intent = PortalPaymentIntent::create([
            'tenant_id' => $invoice->tenant_id,
            'invoice_id' => $invoice->id,
            'guardian_user_id' => $guardian->id,
            'tx_ref' => 'prt-'.(string) Str::uuid7(),
            'amount_minor' => $balance,
            'currency' => $invoice->currency,
            'status' => 'pending',
        ]);

        // A client bound to the TENANT's keys — not the platform's.
        $client = $this->clients->make($provider->decryptedSecretKey());
        $result = $client->createCheckout([
            'amount' => max(1, (int) round($balance / 100)),
            'currency' => $invoice->currency->value,
            'email' => $guardian->email,
            'first_name' => $guardian->name,
            'last_name' => '',
            'callback_url' => $this->appUrl().'/api/v1/portal/webhooks/paychangu',
            'return_url' => $this->appUrl().'/api/v1/portal/return',
            'tx_ref' => $intent->tx_ref,
            'customization' => [
                'title' => 'School fee payment',
                'description' => sprintf('Invoice %s', $invoice->number),
            ],
            'meta' => ['invoice_id' => $invoice->id],
        ]);

        $intent->forceFill([
            'tx_ref' => $result['tx_ref'],
            'checkout_url' => $result['checkout_url'],
        ])->save();

        return ['checkout_url' => $result['checkout_url'], 'tx_ref' => $result['tx_ref'], 'intent' => $intent];
    }

    private function appUrl(): string
    {
        $url = config('app.url');

        return rtrim(is_string($url) ? $url : '', '/');
    }
}
