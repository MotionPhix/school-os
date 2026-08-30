<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * ISO-4217 currencies SchoolOS bills in. Enum stays the source of truth
 * so DB columns can remain plain `string(3)`.
 */
enum CurrencyCode: string
{
    case MWK = 'MWK';
    case NGN = 'NGN';
    case ZAR = 'ZAR';
    case USD = 'USD';

    /** @return array<int, array{value:string,label:string}> */
    public static function options(): array
    {
        return array_map(fn (self $c) => ['value' => $c->value, 'label' => $c->label()], self::cases());
    }

    public function symbol(): string
    {
        return match ($this) {
            self::MWK => 'MK',
            self::NGN => '₦',
            self::ZAR => 'R',
            self::USD => '$',
        };
    }

    public function label(): string
    {
        return $this->value;
    }
}
