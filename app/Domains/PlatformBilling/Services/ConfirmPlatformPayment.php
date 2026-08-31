<?php

declare(strict_types=1);

namespace App\Domains\PlatformBilling\Services;

use App\Models\PlatformPayment;
use App\Support\Payments\PayChanguClient;

/**
 * Re-verify a platform payment with PayChangu (the authoritative source)
 * and, on success, settle the invoice. Idempotent — a succeeded payment is
 * never re-processed, and a settled invoice is never re-marked.
 */
final class ConfirmPlatformPayment
{
    public function __construct(private readonly PayChanguClient $client) {}

    public function handleByTxRef(string $txRef): ?PlatformPayment
    {
        // Called from the PayChangu webhook — there is no tenant context,
        // so the BelongsToTenant global scope must be bypassed.
        $payment = PlatformPayment::query()
            ->withoutGlobalScopes()
            ->where('tx_ref', $txRef)
            ->first();

        if ($payment === null) {
            return null;
        }

        return $this->handle($payment);
    }

    public function handle(PlatformPayment $payment): PlatformPayment
    {
        if ($payment->status === 'succeeded') {
            return $payment; // idempotent
        }

        $verified = $this->client->verify($payment->tx_ref);
        if ($verified === null) {
            return $payment;
        }

        $payment->forceFill([
            'payload' => $verified,
            'verified_at' => now(),
        ]);

        if ($verified['status'] === 'success' && $verified['amount_minor'] >= (int) $payment->amount_minor) {
            $payment->status = 'succeeded';
            $payment->save();

            $invoice = $payment->invoice;
            $invoice->forceFill([
                'status' => 'paid',
                'paid_at' => now(),
            ])->save();
        } elseif (in_array($verified['status'], ['failed', 'cancelled', 'rejected', 'error'], true)) {
            $payment->status = 'failed';
            $payment->save();
        } else {
            $payment->save(); // still pending — leave the invoice open
        }

        return $payment;
    }
}
