<?php

declare(strict_types=1);

namespace App\Domains\Portal\Services;

use App\Domains\Finance\Services\RecordPayment;
use App\Enums\PaymentMethod;
use App\Models\Invoice;
use App\Models\PortalPaymentIntent;
use App\Models\TenantPaymentProvider;
use App\Support\Payments\PayChanguClientFactory;
use App\Support\TenantContext;

/**
 * Re-verify a portal payment with PayChangu using the TENANT's own keys and,
 * on success, book it through the tenant's finance domain (RecordPayment:
 * locked invoice, journal entries, status transition). Idempotent.
 */
final class ConfirmPortalPayment
{
    public function __construct(private readonly PayChanguClientFactory $clients) {}

    public function handleByTxRef(string $txRef): ?PortalPaymentIntent
    {
        // Called from the PayChangu webhook — no tenant context, so the
        // BelongsToTenant global scope must be bypassed.
        $intent = PortalPaymentIntent::query()
            ->withoutGlobalScopes()
            ->where('tx_ref', $txRef)
            ->first();

        return $intent === null ? null : $this->handle($intent);
    }

    public function handle(PortalPaymentIntent $intent): PortalPaymentIntent
    {
        if ($intent->status === 'succeeded') {
            return $intent;
        }

        $provider = TenantPaymentProvider::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $intent->tenant_id)
            ->where('is_active', true)
            ->first();

        if ($provider === null) {
            $intent->forceFill(['status' => 'failed', 'verified_at' => now()])->save();

            return $intent;
        }

        $client = $this->clients->make($provider->decryptedSecretKey());
        $verified = $client->verify($intent->tx_ref);
        if ($verified === null) {
            return $intent;
        }

        $intent->forceFill(['payload' => $verified, 'verified_at' => now()]);

        if ($verified['status'] === 'success' && $verified['amount_minor'] >= (int) $intent->amount_minor) {
            $intent->status = 'succeeded';
            $intent->save();

            // Book inside the tenant's own finance domain (journaled, locked).
            app(TenantContext::class)->runAs($intent->tenant_id, function () use ($intent): void {
                $invoice = Invoice::query()->findOrFail($intent->invoice_id);
                app(RecordPayment::class)->handle($invoice, [
                    'amount_minor' => (int) $intent->amount_minor,
                    'method' => PaymentMethod::PaychanguCard->value,
                    'note' => 'Portal payment via PayChangu ('.$intent->tx_ref.')',
                ]);
            });
        } elseif (in_array($verified['status'], ['failed', 'cancelled', 'rejected', 'error'], true)) {
            $intent->status = 'failed';
            $intent->save();
        } else {
            $intent->save(); // still pending
        }

        return $intent;
    }
}
