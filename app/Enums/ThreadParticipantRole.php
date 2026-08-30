<?php

declare(strict_types=1);

namespace App\Enums;

enum ThreadParticipantRole: string
{
    case Staff = 'staff';
    case Guardian = 'guardian';
    case Student = 'student';

    public function label(): string
    {
        return match ($this) {
            self::Staff => 'Staff',
            self::Guardian => 'Guardian',
            self::Student => 'Student',
        };
    }
}
