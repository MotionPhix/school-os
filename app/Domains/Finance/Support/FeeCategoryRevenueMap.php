<?php

declare(strict_types=1);

namespace App\Domains\Finance\Support;

use App\Enums\AccountKind;
use App\Enums\FeeCategory;

/**
 * Bridges a fee category (presentation concept) to the revenue
 * AccountKind (accounting concept). Keeping the mapping in one file
 * means adding a new category is a two-line change: extend
 * FeeCategory + add the mapping here.
 */
final class FeeCategoryRevenueMap
{
    public static function for(FeeCategory $category): AccountKind
    {
        return match ($category) {
            FeeCategory::Tuition => AccountKind::TuitionRevenue,
            FeeCategory::Boarding => AccountKind::BoardingRevenue,
            FeeCategory::Transport => AccountKind::TransportRevenue,
            FeeCategory::Uniform => AccountKind::UniformRevenue,
            FeeCategory::Activity => AccountKind::ActivityRevenue,
            FeeCategory::Exam => AccountKind::ExamRevenue,
            FeeCategory::Other => AccountKind::OtherRevenue,
        };
    }
}
