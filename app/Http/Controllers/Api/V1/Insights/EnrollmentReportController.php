<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Insights;

use App\Domains\Insights\Services\EnrollmentReportReader;
use App\Domains\Insights\Support\InsightsPermission;
use App\Domains\Insights\Support\ReportCsvWriter;
use App\Http\Controllers\Api\V1\CapabilityController;
use App\Http\Requests\Api\V1\Insights\InsightsPeriodRequest;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class EnrollmentReportController extends CapabilityController
{
    public function __invoke(
        InsightsPeriodRequest $request,
        EnrollmentReportReader $reader,
        InsightsPermission $perm,
        ReportCsvWriter $csv,
    ): JsonResponse|StreamedResponse {
        $user = $request->user();
        abort_unless($user !== null && $perm->has($user, 'insights.enrollment.read'), 403);

        $report = $reader->read($request->validated());

        if ($request->string('format')->toString() === 'csv') {
            return $csv->stream($report, 'enrollment-report');
        }

        return response()->json(['data' => $report]);
    }
}
