<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Classical five-account accounting classification. Determines whether
 * a debit or credit increases the account's balance (needed by reports).
 *
 *   normal balance    = increase on this side
 *   Asset/Expense     = debit
 *   Liability/Revenue/Equity = credit
 */
enum AccountType: string
{
    case Asset = 'asset';
    case Liability = 'liability';
    case Equity = 'equity';
    case Revenue = 'revenue';
    case Expense = 'expense';

    /** @return array<int, array{value:string,label:string}> */
    public static function options(): array
    {
        return array_map(fn (self $c) => ['value' => $c->value, 'label' => $c->label()], self::cases());
    }

    public function label(): string
    {
        return ucfirst($this->value);
    }

    /** Sign multiplier when computing balance = sum(debit) - sum(credit). */
    public function normalBalance(): string
    {
        return match ($this) {
            self::Asset, self::Expense => 'debit',
            self::Liability, self::Equity, self::Revenue => 'credit',
        };
    }
}
