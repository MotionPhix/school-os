<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Communications;

use App\Enums\CommunicationAudience;
use App\Enums\CommunicationChannel;
use App\Http\Requests\Api\V1\CapabilityFormRequest;
use App\Models\Announcement;
use Illuminate\Validation\Rule;

final class StoreAnnouncementRequest extends CapabilityFormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Announcement::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:200'],
            'body' => ['required', 'string'],
            'audience' => ['required', Rule::enum(CommunicationAudience::class)],
            'audience_label' => ['required', 'string', 'max:160'],
            'channels' => ['required', 'array', 'min:1'],
            'channels.*' => [Rule::enum(CommunicationChannel::class)],
            'scheduled_for' => ['nullable', 'date'],
        ];
    }
}
