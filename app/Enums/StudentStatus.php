<?php

declare(strict_types=1);

namespace App\Enums;

enum StudentStatus: string
{
    case Prospective = 'prospective';
    case Enrolled = 'enrolled';
    case OnLeave = 'on_leave';
    case Graduated = 'graduated';
    case Withdrawn = 'withdrawn';

    /** @return array<int, array{value:string,label:string}> */
    public static function options(): array
    {
        return array_map(fn (self $c) => ['value' => $c->value, 'label' => $c->label()], self::cases());
    }

    public function label(): string
    {
        return match ($this) {
            self::Prospective => 'Prospective',
            self::Enrolled => 'Enrolled',
            self::OnLeave => 'On leave',
            self::Graduated => 'Graduated',
            self::Withdrawn => 'Withdrawn',
        };
    }
}
