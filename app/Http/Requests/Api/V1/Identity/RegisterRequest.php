<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Identity;

use App\Http\Requests\Api\V1\CapabilityFormRequest;
use Illuminate\Validation\Rules\Password;

/**
 * @property string $name
 * @property string $email
 * @property string $password
 */
final class RegisterRequest extends CapabilityFormRequest
{
    public function rules(): array
    {
        return [
            'full_name' => ['required', 'string', 'max:160'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'confirmed', Password::min(8)],
            'device_name' => ['nullable', 'string', 'max:120'],
        ];
    }
}
