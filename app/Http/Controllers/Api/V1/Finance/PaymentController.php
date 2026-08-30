<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Finance;

use App\Domains\Finance\Services\RecordPayment;
use App\Domains\Finance\Services\RefundPayment;
use App\Http\Controllers\Api\V1\CapabilityController;
use App\Http\Requests\Api\V1\Finance\RecordPaymentRequest;
use App\Http\Requests\Api\V1\Finance\RefundPaymentRequest;
use App\Http\Resources\Api\V1\Finance\PaymentResource;
use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class PaymentController extends CapabilityController
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Payment::class);

        $q = Payment::query();
        if ($gateway = $request->string('gateway')->toString()) {
            $q->where('gateway', $gateway);
        }
        if ($status = $request->string('status')->toString()) {
            $q->where('status', $status);
        }
        if ($invoiceId = $request->string('invoice_id')->toString()) {
            $q->where('invoice_id', $invoiceId);
        }
        if ($method = $request->string('method')->toString()) {
            $q->where('method', $method);
        }
        if ($from = $request->string('from')->toString()) {
            $q->whereDate('received_at', '>=', $from);
        }
        if ($to = $request->string('to')->toString()) {
            $q->whereDate('received_at', '<=', $to);
        }
        if ($needle = $request->string('search')->toString()) {
            $q->where(function ($sub) use ($needle) {
                $sub->where('reference', 'like', "%{$needle}%")
                    ->orWhere('invoice_number', 'like', "%{$needle}%")
                    ->orWhere('student_name', 'like', "%{$needle}%");
            });
        }

        $paginator = $q->orderByDesc('received_at')
            ->paginate((int) $request->integer('per_page', 25));

        return $this->respondPaginated(PaymentResource::collection($paginator), $paginator);
    }

    public function show(Payment $payment): JsonResponse
    {
        $this->authorize('view', $payment);

        return $this->respond(new PaymentResource($payment));
    }

    /** POST /finance/invoices/{invoice}/payments */
    public function storeForInvoice(RecordPaymentRequest $request, Invoice $invoice, RecordPayment $service): JsonResponse
    {
        $payment = $service->handle($invoice, $request->validated(), $request->user());

        return $this->respondCreated(new PaymentResource($payment));
    }

    public function refund(RefundPaymentRequest $request, Payment $payment, RefundPayment $service): JsonResponse
    {
        $payment = $service->handle($payment, $request->user(), $request->input('reason'));

        return $this->respond(new PaymentResource($payment));
    }
}
