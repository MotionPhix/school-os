<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Communications;

use App\Enums\MessageThreadStatus;
use App\Http\Requests\Api\V1\CapabilityFormRequest;
use Illuminate\Validation\Rule;

final class SetThreadStatusRequest extends CapabilityFormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('message_thread')) ?? false;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(MessageThreadStatus::class)],
        ];
    }
}
