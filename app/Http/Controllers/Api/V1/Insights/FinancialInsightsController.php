<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Insights;

use App\Domains\Insights\Services\FinancialInsightsReader;
use App\Domains\Insights\Support\InsightsPermission;
use App\Domains\Insights\Support\ReportCsvWriter;
use App\Http\Controllers\Api\V1\CapabilityController;
use App\Http\Requests\Api\V1\Insights\InsightsPeriodRequest;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class FinancialInsightsController extends CapabilityController
{
    public function __invoke(
        InsightsPeriodRequest $request,
        FinancialInsightsReader $reader,
        InsightsPermission $perm,
        ReportCsvWriter $csv,
    ): JsonResponse|StreamedResponse {
        $user = $request->user();
        abort_unless($user !== null && $perm->has($user, 'insights.financial.read'), 403);

        $report = $reader->read($request->validated());

        if ($request->string('format')->toString() === 'csv') {
            return $csv->stream($report, 'financial-report');
        }

        return response()->json(['data' => $report]);
    }
}
