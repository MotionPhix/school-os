<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * FeeCategory drives BOTH presentation (badges) and accounting (which
 * revenue account an invoice line credits). Adding a category means
 * (a) add case here, (b) map it in `FeeCategoryRevenueMap`, (c) make
 * sure the corresponding revenue AccountKind exists.
 */
enum FeeCategory: string
{
    case Tuition = 'tuition';
    case Boarding = 'boarding';
    case Transport = 'transport';
    case Uniform = 'uniform';
    case Activity = 'activity';
    case Exam = 'exam';
    case Other = 'other';

    /** @return array<int, array{value:string,label:string}> */
    public static function options(): array
    {
        return array_map(fn (self $c) => ['value' => $c->value, 'label' => $c->label()], self::cases());
    }

    public function label(): string
    {
        return match ($this) {
            self::Tuition => 'Tuition',
            self::Boarding => 'Boarding',
            self::Transport => 'Transport',
            self::Uniform => 'Uniform',
            self::Activity => 'Activity',
            self::Exam => 'Exam',
            self::Other => 'Other',
        };
    }
}
