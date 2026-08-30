<?php

declare(strict_types=1);

namespace App\Domains\Finance\Events;

use App\Models\JournalEntry;
use App\Support\Events\BusinessEvent;

/**
 * Emitted by PostJournalEntry after a balanced entry is persisted.
 * Downstream capabilities (e.g. Insights) can listen to keep their
 * projections in sync without coupling to Invoice/Payment services.
 */
final class JournalEntryPosted extends BusinessEvent
{
    public function __construct(public readonly JournalEntry $entry)
    {
        parent::__construct($entry->tenant_id);
    }

    public function name(): string
    {
        return 'finance.ledger.entry_posted';
    }

    public function payload(): array
    {
        return [
            'journal_entry_id' => $this->entry->id,
            'occurred_on' => $this->entry->occurred_on?->toDateString(),
            'reference' => $this->entry->reference,
            'source_type' => $this->entry->source_type,
            'source_id' => $this->entry->source_id,
            'is_reversal' => (bool) $this->entry->is_reversal,
        ];
    }
}
