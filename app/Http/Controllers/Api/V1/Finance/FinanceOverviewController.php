<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Finance;

use App\Domains\Finance\Services\FinancialReports;
use App\Enums\CurrencyCode;
use App\Http\Controllers\Api\V1\CapabilityController;
use App\Http\Requests\Api\V1\Finance\PeriodReportRequest;
use App\Models\Invoice;
use Illuminate\Http\JsonResponse;

/**
 * Read-only overview KPIs and derived rollups. Mirrors
 * src/lib/verbs/finance.ts::overview.get so the SPA response drops
 * straight into the FinanceOverview contract.
 */
final class FinanceOverviewController extends CapabilityController
{
    public function overview(PeriodReportRequest $request, FinancialReports $reports): JsonResponse
    {
        $this->authorize('viewAny', Invoice::class);

        $currency = $request->filled('currency')
            ? CurrencyCode::from($request->string('currency')->toString())
            : null;

        return response()->json(['data' => $reports->overview($currency)]);
    }
}
