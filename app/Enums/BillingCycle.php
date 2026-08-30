<?php

declare(strict_types=1);

namespace App\Enums;

enum BillingCycle: string
{
    case Term = 'term';
    case Monthly = 'monthly';
    case OneTime = 'one_time';
    case Annual = 'annual';

    /** @return array<int, array{value:string,label:string}> */
    public static function options(): array
    {
        return array_map(fn (self $c) => ['value' => $c->value, 'label' => $c->label()], self::cases());
    }

    public function label(): string
    {
        return match ($this) {
            self::Term => 'Per term',
            self::Monthly => 'Monthly',
            self::OneTime => 'One-time',
            self::Annual => 'Annual',
        };
    }
}
