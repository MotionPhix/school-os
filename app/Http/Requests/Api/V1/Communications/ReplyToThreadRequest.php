<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Communications;

use App\Http\Requests\Api\V1\CapabilityFormRequest;

final class ReplyToThreadRequest extends CapabilityFormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('reply', $this->route('message_thread')) ?? false;
    }

    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'min:1', 'max:8000'],
        ];
    }
}
