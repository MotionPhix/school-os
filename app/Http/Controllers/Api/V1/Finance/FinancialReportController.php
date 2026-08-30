<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Finance;

use App\Domains\Finance\Services\FinancialReports;
use App\Enums\CurrencyCode;
use App\Http\Controllers\Api\V1\CapabilityController;
use App\Http\Requests\Api\V1\Finance\PeriodReportRequest;
use App\Models\Account;
use Illuminate\Http\JsonResponse;

/**
 * Ledger-derived reports. These are the primary answer to questions
 * like "how much did we make in 2022?" — every figure is a SUM over
 * finance_ledger_postings, never a stale rollup table.
 */
final class FinancialReportController extends CapabilityController
{
    public function __construct(private readonly FinancialReports $reports) {}

    public function period(PeriodReportRequest $request): JsonResponse
    {
        $this->authorize('viewAny', Account::class);
        $from = $request->string('from')->toString() ?: date('Y-01-01');
        $to = $request->string('to')->toString() ?: date('Y-12-31');

        return response()->json(['data' => $this->reports->periodProfitAndLoss($from, $to, $this->currency($request))]);
    }

    public function monthlyTrend(PeriodReportRequest $request): JsonResponse
    {
        $this->authorize('viewAny', Account::class);
        $from = $request->string('from')->toString() ?: now()->subMonths(11)->startOfMonth()->toDateString();
        $to = $request->string('to')->toString() ?: now()->endOfMonth()->toDateString();

        return response()->json(['data' => $this->reports->monthlyTrend($from, $to, $this->currency($request))]);
    }

    public function trialBalance(PeriodReportRequest $request): JsonResponse
    {
        $this->authorize('viewAny', Account::class);
        $asOf = $request->string('as_of')->toString() ?: now()->toDateString();

        return response()->json(['data' => $this->reports->trialBalance($asOf, $this->currency($request))]);
    }

    public function receivablesAging(PeriodReportRequest $request): JsonResponse
    {
        $this->authorize('viewAny', Account::class);
        $asOf = $request->string('as_of')->toString() ?: now()->toDateString();

        return response()->json(['data' => $this->reports->receivablesAging($asOf, $this->currency($request))]);
    }

    private function currency(PeriodReportRequest $request): ?CurrencyCode
    {
        return $request->filled('currency')
            ? CurrencyCode::from($request->string('currency')->toString())
            : null;
    }
}
