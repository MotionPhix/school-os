<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Communications;

use App\Enums\CommunicationAudience;
use App\Enums\CommunicationChannel;
use App\Http\Requests\Api\V1\CapabilityFormRequest;
use Illuminate\Validation\Rule;

final class UpdateAnnouncementRequest extends CapabilityFormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('announcement')) ?? false;
    }

    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'string', 'max:200'],
            'body' => ['sometimes', 'string'],
            'audience' => ['sometimes', Rule::enum(CommunicationAudience::class)],
            'audience_label' => ['sometimes', 'string', 'max:160'],
            'channels' => ['sometimes', 'array', 'min:1'],
            'channels.*' => [Rule::enum(CommunicationChannel::class)],
            'scheduled_for' => ['sometimes', 'nullable', 'date'],
        ];
    }
}
