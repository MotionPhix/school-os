<?php

declare(strict_types=1);

namespace App\Domains\Finance\Services;

use App\Domains\Finance\Events\PaymentRecorded;
use App\Domains\Finance\Support\InvoiceNumberGenerator;
use App\Enums\AccountKind;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Invoice;
use App\Models\LedgerPosting;
use App\Models\Payment;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Record a payment against an outstanding invoice and post:
 *
 *   Dr Cash / Bank (net = amount - gateway_fee)
 *   Dr Gateway Fees (gateway_fee, if any)
 *   Cr Accounts Receivable (amount)
 *
 * Rejects overpayments and payments on non-posted invoices.
 */
final class RecordPayment
{
    public function __construct(
        private readonly EnsureChartOfAccounts $chart,
        private readonly PostJournalEntry $journal,
        private readonly WriteInvoice $writeInvoice,
        private readonly InvoiceNumberGenerator $numbers,
    ) {}

    /**
     * @param array{
     *   amount_minor:int,
     *   method:string,
     *   note?:?string,
     *   gateway_fee_minor?:?int,
     *   received_at?:?string
     * } $data
     */
    public function handle(Invoice $invoice, array $data, ?User $actor = null): Payment
    {
        return DB::transaction(function () use ($invoice, $data, $actor): Payment {
            // Lock the invoice row so two concurrent payments cannot both
            // pass the balance check (double-payment race).
            $invoice = Invoice::query()->lockForUpdate()->findOrFail($invoice->id);

            if (! $invoice->status->isPosted() || $invoice->status === InvoiceStatus::Void) {
                throw ValidationException::withMessages(['status' => 'Invoice is not open for payment.']);
            }
            $amount = (int) $data['amount_minor'];
            if ($amount <= 0) {
                throw ValidationException::withMessages(['amount_minor' => 'Amount must be positive.']);
            }
            if ($amount > (int) $invoice->balance_minor) {
                throw ValidationException::withMessages(['amount_minor' => 'Amount exceeds outstanding balance.']);
            }

            $method = PaymentMethod::from((string) $data['method']);
            $gateway = $method->gateway();
            /** @var int|string $rawBps */
            $rawBps = config('finance.defaults.paychangu_fee_bps', 25);
            $bps = (int) $rawBps;
            /** @var int|null $explicitFee */
            $explicitFee = $data['gateway_fee_minor'] ?? null;
            $fee = $explicitFee !== null
                ? max(0, (int) $explicitFee)
                : ($gateway === 'paychangu' ? intdiv($amount * $bps + 5000, 10000) : 0);

            $currency = $invoice->currency;
            $this->chart->forCurrency($currency, $invoice->tenant_id);

            $cashKind = match ($method) {
                PaymentMethod::Cash => AccountKind::Cash,
                PaymentMethod::ManualBank => AccountKind::BankManual,
                PaymentMethod::PaychanguCard,
                PaymentMethod::PaychanguMobileMoney,
                PaymentMethod::PaychanguBankTransfer => AccountKind::BankPaychangu,
            };
            $cash = $this->chart->get($cashKind, $currency, $invoice->tenant_id);
            $ar = $this->chart->get(AccountKind::AccountsReceivable, $currency, $invoice->tenant_id);

            $reference = $this->numbers->nextPaymentReference($gateway);

            $payment = new Payment;
            $payment->fill([
                'tenant_id' => app(TenantContext::class)->id() ?? $invoice->tenant_id,
                'invoice_id' => $invoice->id,
                'invoice_number' => $invoice->number,
                'student_name' => $invoice->student_name,
                'reference' => $reference,
                'method' => $method->value,
                'gateway' => $gateway,
                'amount_minor' => $amount,
                'gateway_fee_minor' => $fee,
                'currency' => $currency->value,
                'status' => PaymentStatus::Succeeded->value,
                'received_at' => $data['received_at'] ?? now(),
                'note' => $data['note'] ?? null,
                'recorded_by' => $actor?->id,
            ]);
            $payment->save();

            $postings = [
                ['account_id' => $cash->id, 'side' => LedgerPosting::SIDE_DEBIT, 'amount_minor' => $amount - $fee, 'memo' => "Cash — {$reference}"],
            ];
            if ($fee > 0) {
                $feeAcct = $this->chart->get(AccountKind::GatewayFees, $currency, $invoice->tenant_id);
                $postings[] = ['account_id' => $feeAcct->id, 'side' => LedgerPosting::SIDE_DEBIT, 'amount_minor' => $fee, 'memo' => "Gateway fee — {$reference}"];
            }
            $postings[] = ['account_id' => $ar->id, 'side' => LedgerPosting::SIDE_CREDIT, 'amount_minor' => $amount, 'memo' => "AR settled — {$invoice->number}"];

            $entry = $this->journal->handle([
                'occurred_on' => (string) $payment->received_at->toDateString(),
                'reference' => $reference,
                'memo' => "Payment {$reference} for {$invoice->number}",
                'source_type' => 'payment',
                'source_id' => $payment->id,
                'currency' => $currency,
                'posted_by' => $actor?->id,
                'postings' => $postings,
            ]);

            // Roll paid_minor + status forward
            $invoice->paid_minor = (int) $invoice->paid_minor + $amount;
            $invoice->save();
            $this->writeInvoice->recomputeTotals($invoice);

            PaymentRecorded::dispatch($payment, $entry->id);

            return $payment->refresh();
        });
    }
}
