<?php

declare(strict_types=1);

namespace App\Domains\Insights\Services;

use App\Domains\Insights\Support\PeriodResolver;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentStatus;
use App\Support\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Financial insights report — leans on the ledger for period totals
 * plus the invoice/payment operational tables for aging and channel
 * breakdowns.
 */
final class FinancialInsightsReader
{
    public function __construct(
        private readonly TenantContext $tenant,
        private readonly PeriodResolver $periods,
    ) {}

    /** @param array{period?:?string,from?:?string,to?:?string,currency?:?string} $input */
    public function read(array $input): array
    {
        $tenantId = $this->tenant->id();
        $win = $this->periods->resolve($input['period'] ?? null, $input['from'] ?? null, $input['to'] ?? null);
        $currency = $input['currency'] ?? (string) config('insights.defaults.currency');

        $collected = $this->collectionsBetween($tenantId, $currency, $win['from'], $win['to']);
        $prevCollected = $this->collectionsBetween($tenantId, $currency, $win['prev_from'], $win['prev_to']);

        $invoiced = (int) DB::table('finance_invoices')
            ->where('tenant_id', $tenantId)
            ->where('currency', $currency)
            ->whereBetween('issued_on', [$win['from']->toDateString(), $win['to']->toDateString()])
            ->sum('total_minor');

        $prevInvoiced = (int) DB::table('finance_invoices')
            ->where('tenant_id', $tenantId)
            ->where('currency', $currency)
            ->whereBetween('issued_on', [$win['prev_from']->toDateString(), $win['prev_to']->toDateString()])
            ->sum('total_minor');

        $arrears = (int) DB::table('finance_invoices')
            ->where('tenant_id', $tenantId)
            ->where('currency', $currency)
            ->whereIn('status', [InvoiceStatus::Issued->value, InvoiceStatus::PartiallyPaid->value, InvoiceStatus::Overdue->value])
            ->sum('balance_minor');

        $rate = $invoiced > 0 ? round(($collected / $invoiced) * 100, 1) : 0.0;
        $prevRate = $prevInvoiced > 0 ? round(($prevCollected / $prevInvoiced) * 100, 1) : 0.0;

        return [
            'as_of' => CarbonImmutable::now()->toIso8601String(),
            'currency' => $currency,
            'period' => [
                'key' => $win['period']->value,
                'label' => $win['period']->label(),
                'from' => $win['from']->toDateString(),
                'to' => $win['to']->toDateString(),
            ],
            'headline' => [
                $this->money('Collected', $collected, $prevCollected, $currency, 'positive'),
                $this->money('Invoiced', $invoiced, $prevInvoiced, $currency, 'neutral'),
                $this->money('Arrears', $arrears, $arrears, $currency, 'warning'),
                $this->pct('Collection rate', $rate, $prevRate, 'positive'),
            ],
            'collections_by_month' => $this->monthlyCollections($tenantId, $currency, $win),
            'arrears_aging' => $this->arrearsAging($tenantId, $currency),
            'by_channel' => $this->collectionsByChannel($tenantId, $currency, $win),
        ];
    }

    private function collectionsBetween(string $tenantId, string $currency, CarbonImmutable $from, CarbonImmutable $to): int
    {
        return (int) DB::table('finance_payments')
            ->where('tenant_id', $tenantId)
            ->where('currency', $currency)
            ->where('status', PaymentStatus::Succeeded->value)
            ->whereBetween('received_at', [$from->toDateTimeString(), $to->toDateTimeString()])
            ->sum('amount_minor');
    }

    /** @return list<array{bucket:string,value:int}> */
    private function monthlyCollections(string $tenantId, string $currency, array $win): array
    {
        $out = [];
        $cur = $win['from']->startOfMonth();
        while ($cur->lessThanOrEqualTo($win['to'])) {
            $out[] = [
                'bucket' => $cur->format('Y-m'),
                'value' => $this->collectionsBetween($tenantId, $currency, $cur, $cur->endOfMonth()),
            ];
            $cur = $cur->addMonth();
        }

        return $out;
    }

    /** @return list<array{label:string,value:int}> */
    private function arrearsAging(string $tenantId, string $currency): array
    {
        // Age buckets are expressed as plain date comparisons rather than
        // DATEDIFF(): that function is MySQL-only and blows up on Postgres.
        // age >= minDays  <=>  due_on <= today - minDays
        // age <= maxDays  <=>  due_on >= today - maxDays
        $today = CarbonImmutable::now()->startOfDay();
        $bucket = function (int $minDays, ?int $maxDays) use ($tenantId, $currency, $today) {
            $q = DB::table('finance_invoices')
                ->where('tenant_id', $tenantId)
                ->where('currency', $currency)
                ->whereIn('status', [InvoiceStatus::Issued->value, InvoiceStatus::PartiallyPaid->value, InvoiceStatus::Overdue->value])
                ->where('due_on', '<=', $today->subDays($minDays)->toDateString());

            if ($maxDays !== null) {
                $q->where('due_on', '>=', $today->subDays($maxDays)->toDateString());
            }

            return (int) $q->sum('balance_minor');
        };

        return [
            ['label' => '0–30 days',   'value' => $bucket(0, 30)],
            ['label' => '31–60 days',  'value' => $bucket(31, 60)],
            ['label' => '61–90 days',  'value' => $bucket(61, 90)],
            ['label' => '90+ days',    'value' => $bucket(91, null)],
        ];
    }

    /** @return list<array{label:string,value:int,secondary:string}> */
    private function collectionsByChannel(string $tenantId, string $currency, array $win): array
    {
        $rows = DB::table('finance_payments')
            ->where('tenant_id', $tenantId)
            ->where('currency', $currency)
            ->where('status', PaymentStatus::Succeeded->value)
            ->whereBetween('received_at', [$win['from']->toDateTimeString(), $win['to']->toDateTimeString()])
            ->groupBy('method')
            ->selectRaw('method, SUM(amount_minor) as total')
            ->get();

        $sum = max(1, (int) $rows->sum('total'));

        return $rows->map(fn ($r) => [
            'label' => ucfirst(str_replace('_', ' ', (string) $r->method)),
            'value' => (int) $r->total,
            'secondary' => round(((int) $r->total / $sum) * 100).'%',
        ])->sortByDesc('value')->values()->all();
    }

    private function money(string $label, int $value, int $prev, string $currency, string $tone): array
    {
        return [
            'label' => $label, 'value' => $value,
            'delta_pct' => $this->periods->deltaPct($value, $prev),
            'unit' => 'money', 'currency' => $currency, 'tone' => $tone,
        ];
    }

    private function pct(string $label, float $value, float $prev, string $tone): array
    {
        return [
            'label' => $label, 'value' => $value,
            'delta_pct' => $this->periods->deltaPct($value, $prev),
            'unit' => 'pct', 'tone' => $tone,
        ];
    }
}
