<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Stable slugs for the standard chart-of-accounts seeded per tenant.
 * Services look accounts up by kind (not name), so labels can change
 * without breaking journal-entry code.
 *
 * One row per (tenant_id, kind) — enforced at the DB level.
 */
enum AccountKind: string
{
    // Assets
    case Cash = 'cash';
    case BankPaychangu = 'bank_paychangu';
    case BankManual = 'bank_manual';
    case AccountsReceivable = 'accounts_receivable';

    // Revenue (one per FeeCategory)
    case TuitionRevenue = 'tuition_revenue';
    case BoardingRevenue = 'boarding_revenue';
    case TransportRevenue = 'transport_revenue';
    case UniformRevenue = 'uniform_revenue';
    case ActivityRevenue = 'activity_revenue';
    case ExamRevenue = 'exam_revenue';
    case OtherRevenue = 'other_revenue';

    // Contra-revenue / expenses
    case DiscountsGiven = 'discounts_given';
    case GatewayFees = 'gateway_fees';
    case Refunds = 'refunds';

    public function type(): AccountType
    {
        return match ($this) {
            self::Cash,
            self::BankPaychangu,
            self::BankManual,
            self::AccountsReceivable => AccountType::Asset,

            self::TuitionRevenue,
            self::BoardingRevenue,
            self::TransportRevenue,
            self::UniformRevenue,
            self::ActivityRevenue,
            self::ExamRevenue,
            self::OtherRevenue => AccountType::Revenue,

            self::DiscountsGiven,
            self::GatewayFees,
            self::Refunds => AccountType::Expense,
        };
    }

    public function displayName(): string
    {
        return match ($this) {
            self::Cash => 'Cash on hand',
            self::BankPaychangu => 'Bank — Paychangu clearing',
            self::BankManual => 'Bank — manual deposits',
            self::AccountsReceivable => 'Accounts receivable — students',
            self::TuitionRevenue => 'Revenue — Tuition',
            self::BoardingRevenue => 'Revenue — Boarding',
            self::TransportRevenue => 'Revenue — Transport',
            self::UniformRevenue => 'Revenue — Uniform',
            self::ActivityRevenue => 'Revenue — Activities',
            self::ExamRevenue => 'Revenue — Exam fees',
            self::OtherRevenue => 'Revenue — Other',
            self::DiscountsGiven => 'Discounts given',
            self::GatewayFees => 'Gateway processing fees',
            self::Refunds => 'Refunds issued',
        };
    }
}
