<?php

declare(strict_types=1);

namespace App\Domains\Insights\Services;

use App\Ai\Agents\SchoolAssistant;
use LogicException;

/**
 * Runs a question through the School Assistant: builds the tenant context
 * snapshot, prompts the configured provider (opencode Zen by default) and
 * returns the answer text.
 */
final class AskSchoolAssistant
{
    public function __construct(private readonly AiContextBuilder $context) {}

    public function ask(string $question): string
    {
        $provider = config('insights.ai.provider', 'zen');
        $model = config('insights.ai.model');
        $timeout = config('insights.ai.timeout', 60);

        if (! is_string($provider) || ! is_string($model) || ! is_int($timeout)) {
            throw new LogicException('The insights.ai configuration is invalid.');
        }

        $facts = $this->context->facts();

        $agent = new SchoolAssistant(
            $facts['school_name'],
            $this->context->render($facts),
        );

        $response = $agent->prompt($question, provider: $provider, model: $model, timeout: $timeout);

        return (string) $response;
    }
}
