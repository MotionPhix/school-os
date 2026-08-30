<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Communications;

use App\Enums\ThreadParticipantRole;
use App\Http\Requests\Api\V1\CapabilityFormRequest;
use App\Models\MessageThread;
use Illuminate\Validation\Rule;

final class StoreMessageThreadRequest extends CapabilityFormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', MessageThread::class) ?? false;
    }

    public function rules(): array
    {
        $tenantId = $this->tenantId();

        return [
            'subject' => ['required', 'string', 'max:200'],
            'student_id' => ['nullable', 'uuid', Rule::exists('students', 'id')->where('tenant_id', $tenantId)],
            'participants' => ['required', 'array', 'min:2'],
            'participants.*.user_id' => ['nullable', 'uuid', Rule::exists('users', 'id')],
            'participants.*.name' => ['required', 'string', 'max:160'],
            'participants.*.role' => ['required', Rule::enum(ThreadParticipantRole::class)],
            'participants.*.avatar_initials' => ['required', 'string', 'max:8'],
        ];
    }
}
