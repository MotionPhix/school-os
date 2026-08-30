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

        $question = $this->sanitize($question);
        if ($question === '') {
            throw new LogicException('The question is empty after sanitization.');
        }

        $facts = $this->context->facts();

        $agent = new SchoolAssistant(
            $facts['school_name'],
            $this->context->render($facts),
        );

        $response = $agent->prompt($question, provider: $provider, model: $model, timeout: $timeout);

        return (string) $response;
    }

    /**
     * Collapse whitespace (newlines/tabs to single spaces) then strip the
     * remaining control characters so stray bytes cannot reach the model
     * prompt or inflate the token budget. The control-character class
     * deliberately excludes \t (0x09), \n (0x0A) and \r (0x0D) — those
     * are already normalised to spaces by the first pass.
     */
    private function sanitize(string $question): string
    {
        $collapsed = preg_replace('/\s+/u', ' ', $question);

        $clean = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $collapsed ?? '');

        return trim((string) $clean);
    }
}
