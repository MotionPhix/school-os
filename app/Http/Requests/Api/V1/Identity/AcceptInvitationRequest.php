<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Identity;

use App\Http\Requests\Api\V1\CapabilityFormRequest;

/**
 * Public endpoint — no auth. The invitation token is the credential.
 */
final class AcceptInvitationRequest extends CapabilityFormRequest
{
    public function rules(): array
    {
        return [
            'token' => ['required', 'string', 'size:48'],
            'name' => ['required', 'string', 'max:120'],
            'password' => ['required', 'string', 'min:12', 'confirmed'],
        ];
    }
}
