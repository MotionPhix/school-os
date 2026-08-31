<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Portal;

use App\Domains\Portal\Services\ConfirmPortalPayment;
use App\Domains\Portal\Services\StartPortalCheckout;
use App\Enums\InvoiceStatus;
use App\Models\Guardian;
use App\Models\Invoice;
use App\Models\PortalPaymentIntent;
use App\Models\Student;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Parents portal — guardians pay their children's school fees directly to
 * the TENANT's own PayChangu account. Authorization is the guardian↔student
 * link itself (no role-based permission keys). The tenant is derived from
 * the guardian/student/invoice rows because guardians are not members.
 */
class PortalController
{
    public function students(Request $request): JsonResponse
    {
        $guardian = $this->guardianFor($request->user());

        if ($guardian === null) {
            return $this->ok([]);
        }

        return $this->ok(app(TenantContext::class)->runAs(
            $guardian->tenant_id,
            fn () => $guardian->students->map(fn (Student $student) => [
                'id' => $student->id,
                'full_name' => $student->full_name,
                'grade_label' => $student->grade_label,
                'open_balance_minor' => (int) $student->invoices()
                    ->whereIn('status', [InvoiceStatus::Issued->value, InvoiceStatus::PartiallyPaid->value, InvoiceStatus::Overdue->value])
                    ->get()
                    ->sum(fn (Invoice $i) => max(0, (int) $i->total_minor - (int) $i->paid_minor)),
            ])->values(),
        ));
    }

    public function studentInvoices(Request $request, string $studentId): JsonResponse
    {
        $guardian = $this->guardianFor($request->user());
        $student = Student::query()->withoutGlobalScopes()->findOrFail($studentId);

        if ($guardian === null || ! $this->isGuardianOf($guardian, $student->id)) {
            abort(403);
        }

        return $this->ok(app(TenantContext::class)->runAs(
            $student->tenant_id,
            fn () => $student->invoices()
                ->whereIn('status', [InvoiceStatus::Issued->value, InvoiceStatus::PartiallyPaid->value, InvoiceStatus::Overdue->value])
                ->orderByDesc('due_on')
                ->get()
                ->map(fn (Invoice $i) => [
                    'id' => $i->id,
                    'number' => $i->number,
                    'total_minor' => (int) $i->total_minor,
                    'paid_minor' => (int) $i->paid_minor,
                    'balance_minor' => max(0, (int) $i->total_minor - (int) $i->paid_minor),
                    'currency' => $i->currency,
                    'status' => $i->status->value,
                    'due_on' => $i->due_on->toDateString(),
                ])->values(),
        ));
    }

    public function checkout(Request $request, string $invoiceId, StartPortalCheckout $service): JsonResponse
    {
        $user = $request->user();
        if (! $user instanceof User) {
            abort(401);
        }

        $guardian = $this->guardianFor($user);
        $invoice = Invoice::query()->withoutGlobalScopes()->findOrFail($invoiceId);

        if ($guardian === null || ! $this->isGuardianOf($guardian, (string) $invoice->student_id)) {
            abort(403);
        }

        $result = app(TenantContext::class)->runAs(
            $invoice->tenant_id,
            fn () => $service->handle($invoice, $user),
        );

        return $this->ok(['checkout_url' => $result['checkout_url'], 'tx_ref' => $result['tx_ref']]);
    }

    public function refresh(Request $request, string $intentId, ConfirmPortalPayment $service): JsonResponse
    {
        $user = $request->user();
        if (! $user instanceof User) {
            abort(401);
        }

        $intent = PortalPaymentIntent::query()->withoutGlobalScopes()->findOrFail($intentId);

        if ($intent->guardian_user_id !== $user->id) {
            abort(403);
        }

        $intent = $service->handle($intent);

        return $this->ok([
            'status' => $intent->status,
            'verified_at' => $intent->verified_at?->toISOString(),
        ]);
    }

    /** Public PayChangu IPN for tenant payments — always re-verified server-side. */
    public function webhook(Request $request, ConfirmPortalPayment $service): JsonResponse
    {
        $txRef = $request->string('tx_ref', $request->string('reference')->toString())->toString();
        if ($txRef === '') {
            return response()->json(['success' => false, 'message' => 'Missing tx_ref.'], 422);
        }

        $intent = $service->handleByTxRef($txRef);
        if ($intent === null) {
            return response()->json(['success' => false, 'message' => 'Unknown transaction reference.'], 404);
        }

        return response()->json(['success' => true, 'status' => $intent->status]);
    }

    private function guardianFor(User $user): ?Guardian
    {
        return Guardian::query()->withoutGlobalScopes()->where('user_id', $user->id)->first();
    }

    private function isGuardianOf(Guardian $guardian, string $studentId): bool
    {
        return DB::table('student_guardians')
            ->where('guardian_id', $guardian->id)
            ->where('student_id', $studentId)
            ->exists();
    }

    /** @param  array<mixed>|Collection  $data */
    private function ok(array|Collection $data): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $data instanceof Collection ? $data->all() : $data]);
    }
}
