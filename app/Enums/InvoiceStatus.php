<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Invoice lifecycle. `draft` invoices have not hit the ledger yet;
 * `issued` and beyond have Dr AR / Cr Revenue postings; `void`
 * carries a reversing entry.
 */
enum InvoiceStatus: string
{
    case Draft = 'draft';
    case Issued = 'issued';
    case PartiallyPaid = 'partially_paid';
    case Paid = 'paid';
    case Overdue = 'overdue';
    case Void = 'void';

    /** @return array<int, array{value:string,label:string}> */
    public static function options(): array
    {
        return array_map(fn (self $c) => ['value' => $c->value, 'label' => $c->label()], self::cases());
    }

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Issued => 'Issued',
            self::PartiallyPaid => 'Partially paid',
            self::Paid => 'Paid',
            self::Overdue => 'Overdue',
            self::Void => 'Void',
        };
    }

    /** No further mutations allowed. */
    public function isTerminal(): bool
    {
        return $this === self::Void;
    }

    /** Invoice affects the ledger. Draft/void do not. */
    public function isPosted(): bool
    {
        return match ($this) {
            self::Draft, self::Void => false,
            default => true,
        };
    }
}
