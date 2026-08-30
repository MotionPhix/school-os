<?php

declare(strict_types=1);

namespace App\Enums;

enum CommunicationAudience: string
{
    case WholeSchool = 'whole_school';
    case Staff = 'staff';
    case Teachers = 'teachers';
    case Students = 'students';
    case Guardians = 'guardians';
    case Class_ = 'class';
    case Custom = 'custom';

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(fn (self $c) => $c->value, self::cases());
    }

    public function label(): string
    {
        return match ($this) {
            self::WholeSchool => 'Whole school',
            self::Staff => 'All staff',
            self::Teachers => 'Teachers',
            self::Students => 'Students',
            self::Guardians => 'Guardians',
            self::Class_ => 'Class',
            self::Custom => 'Custom list',
        };
    }
}
