<?php

declare(strict_types=1);

namespace App\Domains\Finance\Services;

use App\Enums\AccountType;
use App\Enums\CurrencyCode;
use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\LedgerPosting;
use App\Models\Payment;
use App\Support\TenantContext;
use Illuminate\Support\Facades\DB;

/**
 * Cross-capability reporting queries against the ledger.
 *
 * The ledger is the single source of truth. Invoice/payment tables
 * store operational state (status, references, notes), but every
 * financial figure a report shows is derived by summing postings.
 *
 * "How much did we make in 2022?" ->
 *     periodProfitAndLoss('2022-01-01','2022-12-31')
 * "How much are we owed right now?" ->
 *     receivablesAging()
 * "Is our ledger consistent?" ->
 *     trialBalance()
 */
final class FinancialReports
{
    /**
     * P&L for an arbitrary date range. Revenue is credit-normal, so a
     * revenue account's contribution to income = credits - debits.
     * Expenses (incl. discounts, gateway fees, refunds) are debit-normal
     * so expense = debits - credits. Net income = revenue - expense.
     *
     * @return array{
     *   from:string, to:string, currency:string,
     *   revenue:list<array{account_id:string,kind:string,name:string,amount_minor:int}>,
     *   expenses:list<array{account_id:string,kind:string,name:string,amount_minor:int}>,
     *   totals:array{revenue_minor:int,expense_minor:int,net_income_minor:int}
     * }
     */
    public function periodProfitAndLoss(string $from, string $to, ?CurrencyCode $currency = null): array
    {
        $tenantId = app(TenantContext::class)->id();
        $currency ??= CurrencyCode::from((string) config('finance.defaults.currency'));

        $rows = DB::table('finance_ledger_postings as p')
            ->join('finance_accounts as a', 'a.id', '=', 'p.account_id')
            ->where('p.tenant_id', $tenantId)
            ->where('p.currency', $currency->value)
            ->whereBetween('p.occurred_on', [$from, $to])
            ->whereIn('a.type', [AccountType::Revenue->value, AccountType::Expense->value])
            ->groupBy('a.id', 'a.type', 'a.kind', 'a.name')
            ->selectRaw('a.id as account_id, a.type as account_type, a.kind, a.name,
                SUM(CASE WHEN p.side = ? THEN p.amount_minor ELSE 0 END) as debit_sum,
                SUM(CASE WHEN p.side = ? THEN p.amount_minor ELSE 0 END) as credit_sum',
                [LedgerPosting::SIDE_DEBIT, LedgerPosting::SIDE_CREDIT])
            ->get();

        $revenue = [];
        $expense = [];
        $revenueTotal = 0;
        $expenseTotal = 0;

        foreach ($rows as $r) {
            $bal = (int) $r->credit_sum - (int) $r->debit_sum; // credit-normal
            if ($r->account_type === AccountType::Revenue->value) {
                $revenue[] = [
                    'account_id' => (string) $r->account_id,
                    'kind' => (string) $r->kind,
                    'name' => (string) $r->name,
                    'amount_minor' => $bal,
                ];
                $revenueTotal += $bal;
            } else {
                $exp = -$bal; // for expense accounts, debit-normal
                $expense[] = [
                    'account_id' => (string) $r->account_id,
                    'kind' => (string) $r->kind,
                    'name' => (string) $r->name,
                    'amount_minor' => $exp,
                ];
                $expenseTotal += $exp;
            }
        }

        return [
            'from' => $from,
            'to' => $to,
            'currency' => $currency->value,
            'revenue' => $revenue,
            'expenses' => $expense,
            'totals' => [
                'revenue_minor' => $revenueTotal,
                'expense_minor' => $expenseTotal,
                'net_income_minor' => $revenueTotal - $expenseTotal,
            ],
        ];
    }

    /**
     * Monthly revenue/collection trend used by the SPA overview.
     * Collections = payments received (credit to AR), i.e. debits to
     * cash/bank accounts.
     *
     * @return array{
     *   currency:string,
     *   months: list<array{bucket:string,revenue_minor:int,collections_minor:int}>
     * }
     */
    public function monthlyTrend(string $from, string $to, ?CurrencyCode $currency = null): array
    {
        $tenantId = app(TenantContext::class)->id();
        $currency ??= CurrencyCode::from((string) config('finance.defaults.currency'));

        $rev = DB::table('finance_ledger_postings as p')
            ->join('finance_accounts as a', 'a.id', '=', 'p.account_id')
            ->where('p.tenant_id', $tenantId)
            ->where('p.currency', $currency->value)
            ->where('a.type', AccountType::Revenue->value)
            ->whereBetween('p.occurred_on', [$from, $to])
            ->selectRaw("DATE_FORMAT(p.occurred_on, '%Y-%m') as bucket,
                SUM(CASE WHEN p.side='credit' THEN p.amount_minor ELSE 0 END) -
                SUM(CASE WHEN p.side='debit'  THEN p.amount_minor ELSE 0 END) as amount")
            ->groupBy('bucket')
            ->pluck('amount', 'bucket');

        $col = DB::table('finance_ledger_postings as p')
            ->join('finance_accounts as a', 'a.id', '=', 'p.account_id')
            ->where('p.tenant_id', $tenantId)
            ->where('p.currency', $currency->value)
            ->where('a.type', AccountType::Asset->value)
            ->whereIn('a.kind', ['cash', 'bank_paychangu', 'bank_manual'])
            ->whereBetween('p.occurred_on', [$from, $to])
            ->selectRaw("DATE_FORMAT(p.occurred_on, '%Y-%m') as bucket,
                SUM(CASE WHEN p.side='debit'  THEN p.amount_minor ELSE 0 END) -
                SUM(CASE WHEN p.side='credit' THEN p.amount_minor ELSE 0 END) as amount")
            ->groupBy('bucket')
            ->pluck('amount', 'bucket');

        $buckets = array_unique(array_merge(array_keys($rev->all()), array_keys($col->all())));
        sort($buckets);
        $months = array_map(fn ($b) => [
            'bucket' => (string) $b,
            'revenue_minor' => (int) ($rev[$b] ?? 0),
            'collections_minor' => (int) ($col[$b] ?? 0),
        ], $buckets);

        return ['currency' => $currency->value, 'months' => $months];
    }

    /**
     * Trial balance as of a date. Sums to zero when the ledger is
     * internally consistent — a great smoke test for developers.
     *
     * @return array{
     *   as_of:string, currency:string,
     *   rows: list<array{account_id:string,kind:string,name:string,type:string,debit_minor:int,credit_minor:int,balance_minor:int}>,
     *   totals:array{debit_minor:int,credit_minor:int}
     * }
     */
    public function trialBalance(string $asOf, ?CurrencyCode $currency = null): array
    {
        $tenantId = app(TenantContext::class)->id();
        $currency ??= CurrencyCode::from((string) config('finance.defaults.currency'));

        $rows = DB::table('finance_ledger_postings as p')
            ->join('finance_accounts as a', 'a.id', '=', 'p.account_id')
            ->where('p.tenant_id', $tenantId)
            ->where('p.currency', $currency->value)
            ->where('p.occurred_on', '<=', $asOf)
            ->groupBy('a.id', 'a.kind', 'a.name', 'a.type')
            ->selectRaw("a.id as account_id, a.kind, a.name, a.type,
                SUM(CASE WHEN p.side='debit'  THEN p.amount_minor ELSE 0 END) as debit_sum,
                SUM(CASE WHEN p.side='credit' THEN p.amount_minor ELSE 0 END) as credit_sum")
            ->get();

        $out = [];
        $td = 0;
        $tc = 0;
        foreach ($rows as $r) {
            $d = (int) $r->debit_sum;
            $c = (int) $r->credit_sum;
            $td += $d;
            $tc += $c;
            $out[] = [
                'account_id' => (string) $r->account_id,
                'kind' => (string) $r->kind,
                'name' => (string) $r->name,
                'type' => (string) $r->type,
                'debit_minor' => $d,
                'credit_minor' => $c,
                'balance_minor' => $d - $c,
            ];
        }

        return [
            'as_of' => $asOf,
            'currency' => $currency->value,
            'rows' => $out,
            'totals' => ['debit_minor' => $td, 'credit_minor' => $tc],
        ];
    }

    /**
     * Receivables aging derived from invoice balances (operational
     * view). The AR account balance in the ledger equals SUM of
     * these buckets by construction.
     *
     * @return array{
     *   as_of:string, currency:string,
     *   buckets: list<array{label:string,invoice_count:int,amount_minor:int}>,
     *   total_minor:int
     * }
     */
    public function receivablesAging(?string $asOf = null, ?CurrencyCode $currency = null): array
    {
        $asOf ??= now()->toDateString();
        $currency ??= CurrencyCode::from((string) config('finance.defaults.currency'));
        $tenantId = app(TenantContext::class)->id();

        $q = Invoice::query()
            ->where('tenant_id', $tenantId)
            ->where('currency', $currency->value)
            ->outstanding();

        $buckets = [
            'current' => ['label' => 'Not yet due',    'min' => -PHP_INT_MAX, 'max' => 0],
            'd_1_30' => ['label' => '1 – 30 days',    'min' => 1,            'max' => 30],
            'd_31_60' => ['label' => '31 – 60 days',   'min' => 31,           'max' => 60],
            'd_61_90' => ['label' => '61 – 90 days',   'min' => 61,           'max' => 90],
            'd_90_up' => ['label' => '90+ days',       'min' => 91,           'max' => PHP_INT_MAX],
        ];
        $counts = array_fill_keys(array_keys($buckets), 0);
        $sums = array_fill_keys(array_keys($buckets), 0);

        foreach ($q->cursor() as $inv) {
            $daysOverdue = (int) $inv->due_on->diffInDays($asOf, false); // negative if not yet due
            foreach ($buckets as $key => $b) {
                if ($daysOverdue >= $b['min'] && $daysOverdue <= $b['max']) {
                    $counts[$key] += 1;
                    $sums[$key] += (int) $inv->balance_minor;
                    break;
                }
            }
        }

        $out = [];
        $total = 0;
        foreach ($buckets as $key => $b) {
            $out[] = ['label' => $b['label'], 'invoice_count' => $counts[$key], 'amount_minor' => $sums[$key]];
            $total += $sums[$key];
        }

        return ['as_of' => $asOf, 'currency' => $currency->value, 'buckets' => $out, 'total_minor' => $total];
    }

    /**
     * Overview KPIs mirrored from src/lib/verbs/finance.ts::overview.get.
     *
     * @return array<string, mixed>
     */
    public function overview(?CurrencyCode $currency = null): array
    {
        $currency ??= CurrencyCode::from((string) config('finance.defaults.currency'));
        $tenantId = app(TenantContext::class)->id();

        $invoices = Invoice::query()->where('tenant_id', $tenantId)->where('currency', $currency->value);

        $invoiced = (int) (clone $invoices)->where('status', '<>', InvoiceStatus::Void->value)->sum('total_minor');
        $collected = (int) (clone $invoices)->sum('paid_minor');
        $outstanding = (int) (clone $invoices)->where('status', '<>', InvoiceStatus::Void->value)->sum('balance_minor');
        $overdueRows = (clone $invoices)->where('status', InvoiceStatus::Overdue->value);
        $overdue = (int) (clone $overdueRows)->sum('balance_minor');
        $overdueCnt = (int) (clone $overdueRows)->count();

        $paychanguFees = (int) Payment::query()
            ->where('tenant_id', $tenantId)
            ->where('currency', $currency->value)
            ->where('gateway', 'paychangu')
            ->where('status', 'succeeded')
            ->sum('gateway_fee_minor');

        $byStatus = [];
        foreach (InvoiceStatus::cases() as $s) {
            $rows = (clone $invoices)->where('status', $s->value);
            $byStatus[] = [
                'status' => $s->value,
                'count' => (int) (clone $rows)->count(),
                'amount_minor' => (int) (clone $rows)->sum('total_minor'),
            ];
        }

        return [
            'currency' => $currency->value,
            'invoiced_minor' => $invoiced,
            'collected_minor' => $collected,
            'outstanding_minor' => $outstanding,
            'overdue_minor' => $overdue,
            'overdue_count' => $overdueCnt,
            'paychangu_fees_minor' => $paychanguFees,
            'by_status' => $byStatus,
        ];
    }
}
