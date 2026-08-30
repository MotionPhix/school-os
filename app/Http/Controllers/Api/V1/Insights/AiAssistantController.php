<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Insights;

use App\Domains\Insights\Services\AskSchoolAssistant;
use App\Domains\Insights\Support\InsightsPermission;
use App\Http\Controllers\Api\V1\CapabilityController;
use App\Http\Requests\Api\V1\Insights\AskSchoolAssistantRequest;
use Illuminate\Http\JsonResponse;
use Throwable;

/**
 * School AI assistant — answers questions from the tenant's authoritative
 * AI context snapshot (AiContextBuilder). Gated by `insights.ai.read` and
 * disabled until INSIGHTS_AI_ENABLED=true + OPENCODE_ZEN_KEY are set.
 */
final class AiAssistantController extends CapabilityController
{
    public function __invoke(
        AskSchoolAssistantRequest $request,
        InsightsPermission $perm,
        AskSchoolAssistant $ask,
    ): JsonResponse {
        $user = $request->user();
        abort_unless($user !== null && $perm->has($user, 'insights.ai.read'), 403);

        abort_unless((bool) config('insights.ai.enabled'), 503, 'AI assistant is not enabled.');

        /** @var array{question: string} $validated */
        $validated = $request->validated();

        try {
            $answer = $ask->ask($validated['question']);
        } catch (Throwable $e) {
            report($e);

            abort(503, 'AI assistant is temporarily unavailable.');
        }

        return response()->json(['data' => ['answer' => $answer]]);
    }
}
