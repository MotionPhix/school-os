<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Finance;

use App\Domains\Finance\Services\BulkFinanceAction;
use App\Domains\Finance\Services\IssueInvoice;
use App\Domains\Finance\Services\SendInvoiceReminder;
use App\Domains\Finance\Services\VoidInvoice;
use App\Domains\Finance\Services\WriteInvoice;
use App\Http\Controllers\Api\V1\CapabilityController;
use App\Http\Requests\Api\V1\Finance\BulkInvoicesRequest;
use App\Http\Requests\Api\V1\Finance\StoreInvoiceRequest;
use App\Http\Requests\Api\V1\Finance\UpdateInvoiceRequest;
use App\Http\Requests\Api\V1\Finance\VoidInvoiceRequest;
use App\Http\Resources\Api\V1\Finance\InvoiceResource;
use App\Models\Invoice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class InvoiceController extends CapabilityController
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Invoice::class);

        $q = Invoice::query();
        if ($status = $request->string('status')->toString()) {
            $q->where('status', $status);
        }
        if ($studentId = $request->string('student_id')->toString()) {
            $q->where('student_id', $studentId);
        }
        if ($grade = $request->string('grade_label')->toString()) {
            $q->where('grade_label', $grade);
        }
        if ($termId = $request->string('term_id')->toString()) {
            $q->where('term_id', $termId);
        }
        if ($from = $request->string('from')->toString()) {
            $q->whereDate('issued_on', '>=', $from);
        }
        if ($to = $request->string('to')->toString()) {
            $q->whereDate('issued_on', '<=', $to);
        }
        if ($request->boolean('outstanding_only')) {
            $q->where('balance_minor', '>', 0);
        }
        if ($needle = $request->string('search')->toString()) {
            $q->where(function ($sub) use ($needle) {
                $sub->where('number', 'like', "%{$needle}%")
                    ->orWhere('student_name', 'like', "%{$needle}%")
                    ->orWhere('guardian_name', 'like', "%{$needle}%")
                    ->orWhere('grade_label', 'like', "%{$needle}%");
            });
        }

        $paginator = $q->orderByDesc('issued_on')
            ->paginate((int) $request->integer('per_page', 25));

        return $this->respondPaginated(InvoiceResource::collection($paginator), $paginator);
    }

    public function show(Invoice $invoice): JsonResponse
    {
        $this->authorize('view', $invoice);
        $invoice->load(['lines', 'payments']);

        return $this->respond(new InvoiceResource($invoice));
    }

    public function store(StoreInvoiceRequest $request, WriteInvoice $service): JsonResponse
    {
        $invoice = $service->create($request->validated());
        $invoice->load(['lines']);

        return $this->respondCreated(new InvoiceResource($invoice));
    }

    public function update(UpdateInvoiceRequest $request, Invoice $invoice, WriteInvoice $service): JsonResponse
    {
        $invoice = $service->update($invoice, $request->validated());
        $invoice->load(['lines']);

        return $this->respond(new InvoiceResource($invoice));
    }

    public function issue(Invoice $invoice, IssueInvoice $service, Request $request): JsonResponse
    {
        $this->authorize('issue', $invoice);
        $invoice = $service->handle($invoice, $request->user());
        $invoice->load(['lines', 'payments']);

        return $this->respond(new InvoiceResource($invoice));
    }

    public function void(VoidInvoiceRequest $request, Invoice $invoice, VoidInvoice $service): JsonResponse
    {
        $invoice = $service->handle($invoice, $request->user(), $request->input('reason'));
        $invoice->load(['lines', 'payments']);

        return $this->respond(new InvoiceResource($invoice));
    }

    public function destroy(Invoice $invoice): JsonResponse
    {
        $this->authorize('delete', $invoice);
        $invoice->delete();

        return $this->respondNoContent();
    }

    /** POST /finance/invoices/{invoice}/remind — chase the outstanding balance. */
    public function remind(Invoice $invoice, SendInvoiceReminder $service): JsonResponse
    {
        $this->authorize('remind', $invoice);
        $invoice = $service->handle($invoice);
        $invoice->load(['lines', 'payments']);

        return $this->respond(new InvoiceResource($invoice));
    }

    /** POST /finance/invoices/bulk — issue | void | remind | delete. */
    public function bulk(BulkInvoicesRequest $request, BulkFinanceAction $service): JsonResponse
    {
        $data = $request->validated();

        // Authorize every row against the action's dedicated ability so a
        // user holding only finance.invoices.write cannot bulk-issue/void.
        $invoices = Invoice::query()->whereIn('id', $data['ids'])->get();

        foreach ($invoices as $invoice) {
            $this->authorize($data['action'], $invoice);
        }

        return response()->json([
            'data' => $service->invoices($invoices->pluck('id')->all(), $data['action'], $request->user()),
        ]);
    }
}
