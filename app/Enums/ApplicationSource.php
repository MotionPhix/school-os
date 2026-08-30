<?php

declare(strict_types=1);

namespace App\Enums;

enum ApplicationSource: string
{
    case WalkIn = 'walk_in';
    case Website = 'website';
    case Referral = 'referral';
    case Agent = 'agent';
    case Transfer = 'transfer';

    /** @return array<int, array{value:string,label:string}> */
    public static function options(): array
    {
        return array_map(fn (self $s) => ['value' => $s->value, 'label' => $s->label()], self::cases());
    }

    public function label(): string
    {
        return match ($this) {
            self::WalkIn => 'Walk-in',
            self::Website => 'Website',
            self::Referral => 'Referral',
            self::Agent => 'Agent',
            self::Transfer => 'Transfer',
        };
    }
}
