<?php

declare(strict_types=1);

namespace App\Enums;

enum PaymentMethod: string
{
    case PaychanguCard = 'paychangu_card';
    case PaychanguMobileMoney = 'paychangu_mobile_money';
    case PaychanguBankTransfer = 'paychangu_bank_transfer';
    case Cash = 'cash';
    case ManualBank = 'manual_bank';

    /** @return array<int, array{value:string,label:string}> */
    public static function options(): array
    {
        return array_map(fn (self $c) => ['value' => $c->value, 'label' => $c->label()], self::cases());
    }

    public function label(): string
    {
        return match ($this) {
            self::PaychanguCard => 'Paychangu — card',
            self::PaychanguMobileMoney => 'Paychangu — mobile money',
            self::PaychanguBankTransfer => 'Paychangu — bank transfer',
            self::Cash => 'Cash',
            self::ManualBank => 'Manual bank transfer',
        };
    }

    public function gateway(): string
    {
        return str_starts_with($this->value, 'paychangu') ? 'paychangu' : 'manual';
    }
}
