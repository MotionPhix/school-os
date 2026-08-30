<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Communications;

use App\Http\Requests\Api\V1\CapabilityFormRequest;
use App\Models\Broadcast;
use Illuminate\Validation\Rule;

final class BulkBroadcastsRequest extends CapabilityFormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Broadcast::class) ?? false;
    }

    public function rules(): array
    {
        $tenantId = $this->tenantId();

        return [
            'ids' => ['required', 'array', 'min:1', 'max:200'],
            'ids.*' => ['uuid', Rule::exists('comm_broadcasts', 'id')->where('tenant_id', $tenantId)],
            'action' => ['required', 'string', Rule::in(['start', 'cancel', 'delete'])],
        ];
    }
}
