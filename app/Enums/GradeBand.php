<?php

declare(strict_types=1);

namespace App\Enums;

enum GradeBand: string
{
    case A = 'A';
    case B = 'B';
    case C = 'C';
    case D = 'D';
    case E = 'E';
    case F = 'F';

    /** Derive band from a 0..100 total (mirrors src/contracts/academics.ts::bandFor). */
    public static function forTotal(int $total): self
    {
        return match (true) {
            $total >= 75 => self::A,
            $total >= 65 => self::B,
            $total >= 55 => self::C,
            $total >= 45 => self::D,
            $total >= 40 => self::E,
            default => self::F,
        };
    }

    /** @return array<int, array{value:string,label:string}> */
    public static function options(): array
    {
        return array_map(fn (self $c) => ['value' => $c->value, 'label' => $c->label()], self::cases());
    }

    public function label(): string
    {
        return match ($this) {
            self::A => 'A — Distinction',
            self::B => 'B — Very good',
            self::C => 'C — Credit',
            self::D => 'D — Pass',
            self::E => 'E — Marginal pass',
            self::F => 'F — Fail',
        };
    }
}
