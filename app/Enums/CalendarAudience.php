<?php

declare(strict_types=1);

namespace App\Enums;

enum CalendarAudience: string
{
    case WholeSchool = 'whole_school';
    case Staff = 'staff';
    case Students = 'students';
    case Guardians = 'guardians';

    /** @return array<int, array{value:string,label:string}> */
    public static function options(): array
    {
        return array_map(fn (self $c) => ['value' => $c->value, 'label' => $c->label()], self::cases());
    }

    public function label(): string
    {
        return match ($this) {
            self::WholeSchool => 'Whole school',
            self::Staff => 'Staff',
            self::Students => 'Students',
            self::Guardians => 'Guardians',
        };
    }
}
