<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\People;

use App\Http\Requests\Api\V1\CapabilityFormRequest;

/**
 * Avatar upload. `file` may be null to clear the current avatar.
 * Mime + size limits come from `config('people.media')`.
 */
final class UploadAvatarRequest extends CapabilityFormRequest
{
    public function rules(): array
    {
        $media = (array) config('people.media');
        $maxKb = (int) ($media['avatar_max_kb'] ?? 2048);
        $mimes = (array) ($media['avatar_mimes'] ?? ['image/png', 'image/jpeg', 'image/webp']);

        return [
            'file' => ['nullable', 'file', 'max:'.$maxKb, 'mimetypes:'.implode(',', $mimes)],
        ];
    }
}
