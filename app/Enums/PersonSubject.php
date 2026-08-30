<?php

declare(strict_types=1);

namespace App\Enums;

use App\Models\Guardian;
use App\Models\StaffMember;
use App\Models\Student;
use Illuminate\Database\Eloquent\Model;

/**
 * Polymorphic subject type for shared People-domain artefacts
 * (documents, avatars, notes). Values match the URL segment used in
 * the People routes so it can be parsed straight from the request.
 */
enum PersonSubject: string
{
    case Students = 'students';
    case Guardians = 'guardians';
    case Staff = 'staff';

    /** @return array<int, array{value:string,label:string}> */
    public static function options(): array
    {
        return array_map(fn (self $c) => ['value' => $c->value, 'label' => $c->label()], self::cases());
    }

    /** @return class-string<Model> */
    public function modelClass(): string
    {
        return match ($this) {
            self::Students => Student::class,
            self::Guardians => Guardian::class,
            self::Staff => StaffMember::class,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Students => 'Student',
            self::Guardians => 'Guardian',
            self::Staff => 'Staff member',
        };
    }
}
