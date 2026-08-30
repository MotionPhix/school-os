<?php

declare(strict_types=1);

namespace App\Enums;

enum SubjectCategory: string
{
    case Core = 'core';
    case Language = 'language';
    case Science = 'science';
    case Humanities = 'humanities';
    case Arts = 'arts';
    case Physical = 'physical';
    case Vocational = 'vocational';
    case Elective = 'elective';

    /** @return array<int, array{value:string,label:string}> */
    public static function options(): array
    {
        return array_map(fn (self $c) => ['value' => $c->value, 'label' => $c->label()], self::cases());
    }

    public function label(): string
    {
        return match ($this) {
            self::Core => 'Core',
            self::Language => 'Language',
            self::Science => 'Science',
            self::Humanities => 'Humanities',
            self::Arts => 'Arts',
            self::Physical => 'Physical',
            self::Vocational => 'Vocational',
            self::Elective => 'Elective',
        };
    }
}
