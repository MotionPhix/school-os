<?php

declare(strict_types=1);

namespace App\Domains\Finance\Services;

use App\Domains\Finance\Events\JournalEntryPosted;
use App\Enums\CurrencyCode;
use App\Models\JournalEntry;
use App\Models\LedgerPosting;
use App\Support\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The one place that writes to the ledger. Guarantees:
 *   - every entry balances (sum(debit) == sum(credit))
 *   - every posting shares the entry's tenant and currency
 *   - postings carry occurred_on for fast date-range queries
 *
 * Callers pass a *list of postings*, not signed amounts, so debits and
 * credits stay explicit at the call site.
 */
final class PostJournalEntry
{
    /**
     * @param array{
     *   occurred_on:string,
     *   reference:string,
     *   tenant_id?:?string,
     *   memo?:?string,
     *   source_type?:?string,
     *   source_id?:?string,
     *   currency:CurrencyCode,
     *   is_reversal?:bool,
     *   reverses_entry_id?:?string,
     *   posted_by?:?string,
     *   postings: list<array{account_id:string,side:string,amount_minor:int,memo?:?string}>
     * } $data
     */
    public function handle(array $data): JournalEntry
    {
        return DB::transaction(function () use ($data): JournalEntry {
            $currency = $data['currency'];
            $postings = $data['postings'];

            if (count($postings) < 2) {
                throw ValidationException::withMessages(['postings' => 'A journal entry needs at least two postings.']);
            }

            $totalDebit = 0;
            $totalCredit = 0;
            foreach ($postings as $p) {
                $amount = (int) $p['amount_minor'];
                if ($amount <= 0) {
                    throw ValidationException::withMessages(['postings' => 'Posting amounts must be positive.']);
                }
                match ($p['side']) {
                    LedgerPosting::SIDE_DEBIT => $totalDebit += $amount,
                    LedgerPosting::SIDE_CREDIT => $totalCredit += $amount,
                    default => throw ValidationException::withMessages(['postings' => "Unknown side: {$p['side']}"]),
                };
            }
            if ($totalDebit !== $totalCredit) {
                throw ValidationException::withMessages([
                    'postings' => "Entry does not balance: debit {$totalDebit} vs credit {$totalCredit}.",
                ]);
            }

            $tenantId = $data['tenant_id'] ?? app(TenantContext::class)->id();
            if ($tenantId === null) {
                // Fail loud instead of writing ledger rows without a tenant
                // (e.g. when this runs outside a resolve.tenant request).
                throw ValidationException::withMessages(['postings' => 'A tenant context is required to post journal entries.']);
            }

            $entry = new JournalEntry;
            $entry->fill([
                'tenant_id' => $tenantId,
                'occurred_on' => $data['occurred_on'],
                'reference' => $data['reference'],
                'memo' => $data['memo'] ?? null,
                'source_type' => $data['source_type'] ?? null,
                'source_id' => $data['source_id'] ?? null,
                'currency' => $currency->value,
                'is_reversal' => (bool) ($data['is_reversal'] ?? false),
                'reverses_entry_id' => $data['reverses_entry_id'] ?? null,
                'posted_by' => $data['posted_by'] ?? null,
                'posted_at' => now(),
            ]);
            $entry->save();

            foreach ($postings as $p) {
                LedgerPosting::query()->create([
                    'tenant_id' => $tenantId,
                    'journal_entry_id' => $entry->id,
                    'account_id' => $p['account_id'],
                    'side' => $p['side'],
                    'amount_minor' => (int) $p['amount_minor'],
                    'occurred_on' => $data['occurred_on'],
                    'currency' => $currency->value,
                    'memo' => $p['memo'] ?? null,
                ]);
            }

            JournalEntryPosted::dispatch($entry);

            return $entry->refresh()->load('postings');
        });
    }

    /**
     * Post a reversing entry that mirrors the original with sides
     * flipped. Used by invoice void and payment refund.
     */
    public function reverse(JournalEntry $original, string $reference, ?string $memo = null, ?string $postedBy = null): JournalEntry
    {
        $original->loadMissing('postings');
        $postings = $original->postings->map(fn ($p) => [
            'account_id' => $p->account_id,
            'side' => $p->side === LedgerPosting::SIDE_DEBIT ? LedgerPosting::SIDE_CREDIT : LedgerPosting::SIDE_DEBIT,
            'amount_minor' => (int) $p->amount_minor,
            'memo' => $p->memo,
        ])->all();

        return $this->handle([
            'occurred_on' => now()->toDateString(),
            'reference' => $reference,
            'memo' => $memo,
            'source_type' => $original->source_type,
            'source_id' => $original->source_id,
            'currency' => $original->currency,
            'is_reversal' => true,
            'reverses_entry_id' => $original->id,
            'posted_by' => $postedBy,
            'postings' => $postings,
        ]);
    }
}
