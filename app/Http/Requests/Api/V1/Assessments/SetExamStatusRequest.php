<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Assessments;

use App\Enums\ExamStatus;
use App\Http\Requests\Api\V1\CapabilityFormRequest;
use Illuminate\Validation\Rules\Enum;

final class SetExamStatusRequest extends CapabilityFormRequest
{
    public function authorize(): bool
    {
        $exam = $this->route('exam');
        $to = ExamStatus::tryFrom((string) $this->input('status', ''));
        $ability = $to === ExamStatus::Published ? 'publish' : 'update';

        return $this->user()?->can($ability, $exam) ?? false;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', new Enum(ExamStatus::class)],
        ];
    }
}
