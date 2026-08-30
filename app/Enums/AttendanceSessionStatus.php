<?php

declare(strict_types=1);

namespace App\Enums;

enum AttendanceSessionStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';

    /** @return array<int, array{value:string,label:string}> */
    public static function options(): array
    {
        return array_map(fn (self $c) => ['value' => $c->value, 'label' => $c->label()], self::cases());
    }

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Submitted => 'Submitted',
        };
    }

    public function isLocked(): bool
    {
        return $this === self::Submitted;
    }
}
