<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Communications;

use App\Enums\CommunicationAudience;
use App\Enums\CommunicationChannel;
use App\Http\Requests\Api\V1\CapabilityFormRequest;
use App\Models\Broadcast;
use Illuminate\Validation\Rule;

final class StoreBroadcastRequest extends CapabilityFormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Broadcast::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:200'],
            'channel' => ['required', Rule::enum(CommunicationChannel::class)],
            'audience' => ['required', Rule::enum(CommunicationAudience::class)],
            'audience_label' => ['required', 'string', 'max:160'],
            'template_snippet' => ['required', 'string', 'max:8000'],
            'scheduled_for' => ['nullable', 'date'],
        ];
    }
}
