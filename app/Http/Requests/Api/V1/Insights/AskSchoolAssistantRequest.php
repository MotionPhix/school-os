<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Insights;

use Illuminate\Foundation\Http\FormRequest;

final class AskSchoolAssistantRequest extends FormRequest
{
    /** @return array{question: string[]} */
    public function rules(): array
    {
        $max = config('insights.ai.max_question_length', 500);
        if (! is_int($max)) {
            $max = 500;
        }

        return [
            'question' => ['required', 'string', "max:{$max}"],
        ];
    }
}
