<?php

declare(strict_types=1);

namespace App\Enums;

enum CalendarEventKind: string
{
    case Holiday = 'holiday';
    case TermStart = 'term_start';
    case TermEnd = 'term_end';
    case Exam = 'exam';
    case Event = 'event';
    case ProfessionalDevelopment = 'professional_development';
    case Break = 'break';

    /** @return array<int, array{value:string,label:string}> */
    public static function options(): array
    {
        return array_map(fn (self $c) => ['value' => $c->value, 'label' => $c->label()], self::cases());
    }

    public function label(): string
    {
        return match ($this) {
            self::Holiday => 'Holiday',
            self::TermStart => 'Term start',
            self::TermEnd => 'Term end',
            self::Exam => 'Exam',
            self::Event => 'Event',
            self::ProfessionalDevelopment => 'Professional development',
            self::Break => 'Break',
        };
    }
}
