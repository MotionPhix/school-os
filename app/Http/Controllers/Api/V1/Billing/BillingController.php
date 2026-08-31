<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Billing;

use App\Domains\PlatformBilling\Readers\BillingOverviewReader;
use App\Domains\PlatformBilling\Services\ConfirmPlatformPayment;
use App\Domains\PlatformBilling\Services\StartPlatformCheckout;
use App\Http\Controllers\Api\V1\CapabilityController;
use App\Http\Resources\Api\V1\Billing\PlatformCheckoutResource;
use App\Http\Resources\Api\V1\Billing\PlatformInvoiceResource;
use App\Http\Resources\Api\V1\Billing\PlatformPaymentResource;
use App\Models\PlatformInvoice;
use App\Models\PlatformPayment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Platform billing — the TENANT pays the platform for the subscription.
 * Payments are collected via PayChangu standard checkout; the status is
 * always re-verified server-side before an invoice is settled.
 */
class BillingController extends CapabilityController
{
    public function overview(BillingOverviewReader $reader): JsonResponse
    {
        $this->authorize('viewAny', PlatformInvoice::class);

        $overview = $reader->read();

        return $this->respond(
            new PlatformInvoiceResource($overview['invoice'], $overview['payments'], $overview['total_paid_minor']),
        );
    }

    public function checkout(PlatformInvoice $invoice, StartPlatformCheckout $service, Request $request): JsonResponse
    {
        $this->authorize('checkout', $invoice);

        $result = $service->handle($invoice, $request->string('email')->toString(), $request->string('name')->toString());

        return $this->respond(new PlatformCheckoutResource($result['checkout_url'], $result['tx_ref']));
    }

    public function refresh(PlatformPayment $payment, ConfirmPlatformPayment $service): JsonResponse
    {
        $this->authorize('refresh', $payment);

        $payment = $service->handle($payment);

        return $this->respond(new PlatformPaymentResource($payment));
    }

    /**
     * PayChangu IPN (callback_url from the checkout). Public — the payload
     * is never trusted; the payment is re-verified with PayChangu before
     * any invoice is settled.
     */
    public function webhook(Request $request, ConfirmPlatformPayment $service): JsonResponse
    {
        $txRef = $request->string('tx_ref', $request->string('reference')->toString())->toString();

        if ($txRef === '') {
            return response()->json(['success' => false, 'message' => 'Missing tx_ref.'], 422);
        }

        $payment = $service->handleByTxRef($txRef);

        if ($payment === null) {
            return response()->json(['success' => false, 'message' => 'Unknown transaction reference.'], 404);
        }

        return response()->json(['success' => true, 'status' => $payment->status]);
    }
}
