<?php

declare(strict_types=1);

namespace App\Domains\Finance\Services;

use App\Enums\InvoiceStatus;
use App\Models\FeeStructure;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * Batch operations over invoices and fee structures.
 *
 * Mirrors src/lib/verbs/finance.ts. Rows are applied through the
 * single-record services so ledger postings and business events keep
 * firing; guard violations are skipped with a reason rather than
 * failing the whole batch.
 *
 * @phpstan-type BulkResult array{affected:int, skipped:array<int,string>}
 */
final class BulkFinanceAction
{
    public function __construct(
        private readonly IssueInvoice $issue,
        private readonly VoidInvoice $void,
        private readonly SendInvoiceReminder $remind,
    ) {}

    /**
     * @param  array<int,string>  $ids
     * @param  'issue'|'void'|'remind'|'delete'  $action
     * @return BulkResult
     */
    public function invoices(array $ids, string $action, ?User $actor = null): array
    {
        $invoices = Invoice::query()->whereIn('id', $ids)->get();

        $skipped = [];
        $affected = 0;

        foreach ($invoices as $invoice) {
            try {
                switch ($action) {
                    case 'issue':
                        if ($invoice->status !== InvoiceStatus::Draft) {
                            $skipped[] = "{$invoice->number}: already {$invoice->status->value}.";

                            continue 2;
                        }
                        $this->issue->handle($invoice, $actor);
                        break;

                    case 'void':
                        if (in_array($invoice->status, [InvoiceStatus::Void, InvoiceStatus::Paid], true)) {
                            $skipped[] = "{$invoice->number}: {$invoice->status->value} invoices cannot be voided.";

                            continue 2;
                        }
                        $this->void->handle($invoice, $actor);
                        break;

                    case 'remind':
                        $this->remind->handle($invoice);
                        break;

                    default:
                        if ($invoice->status !== InvoiceStatus::Draft) {
                            $skipped[] = "{$invoice->number}: only drafts can be deleted.";

                            continue 2;
                        }
                        $invoice->delete();
                }

                $affected++;
            } catch (ValidationException $e) {
                $skipped[] = "{$invoice->number}: ".$e->getMessage();
            }
        }

        return ['affected' => $affected, 'skipped' => $skipped];
    }

    /**
     * @param  array<int,string>  $ids
     * @param  'activate'|'deactivate'|'delete'  $action
     * @return BulkResult
     */
    public function fees(array $ids, string $action): array
    {
        $fees = FeeStructure::query()->whereIn('id', $ids)->get();

        $skipped = [];
        $affected = 0;

        foreach ($fees as $fee) {
            if ($action === 'delete') {
                $billed = InvoiceLine::query()->where('fee_structure_id', $fee->id)->exists();
                if ($billed) {
                    $skipped[] = "{$fee->name}: already billed on an invoice.";

                    continue;
                }
                $fee->delete();
                $affected++;

                continue;
            }

            $fee->is_active = $action === 'activate';
            $fee->save();
            $affected++;
        }

        return ['affected' => $affected, 'skipped' => $skipped];
    }
}
