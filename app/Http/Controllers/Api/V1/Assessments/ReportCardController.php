<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Assessments;

use App\Domains\Assessments\Services\BuildTermReportCards;
use App\Http\Controllers\Api\V1\CapabilityController;
use App\Http\Resources\Api\V1\Assessments\StudentReportCardResource;
use App\Models\Exam;
use App\Models\ExamPeriod;
use App\Models\Term;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Report-card rollups. Grouped by term because that's the reporting
 * cadence schools actually publish on. When no term is supplied we
 * default to the term of the most recent exam period so the SPA's
 * "current" view lands on real data.
 */
final class ReportCardController extends CapabilityController
{
    public function term(Request $request, BuildTermReportCards $service): JsonResponse
    {
        if (! $request->user()?->can('viewAny', Exam::class)
            || ! $request->user()?->can('viewReports', Exam::class)) {
            abort(403);
        }

        $termId = $request->string('term_id')->toString();
        $term = $termId
            ? Term::query()->findOrFail($termId)
            : $this->resolveDefaultTerm();

        if ($term === null) {
            return response()->json(['data' => []]);
        }

        return response()->json([
            'data' => array_map(
                fn ($card) => (new StudentReportCardResource($card))->resolve(),
                $service->handle($term),
            ),
        ]);
    }

    private function resolveDefaultTerm(): ?Term
    {
        $period = ExamPeriod::query()->orderByDesc('starts_on')->first();

        return $period ? $period->term()->first() : null;
    }
}
