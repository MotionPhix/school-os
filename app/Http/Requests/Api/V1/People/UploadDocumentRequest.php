<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\People;

use App\Http\Requests\Api\V1\CapabilityFormRequest;

final class UploadDocumentRequest extends CapabilityFormRequest
{
    public function rules(): array
    {
        $media = (array) config('people.media');
        $maxKb = (int) ($media['document_max_kb'] ?? 10240);
        $mimes = (array) ($media['document_mimes'] ?? ['application/pdf']);

        return [
            'name' => ['required', 'string', 'max:160'],
            'file' => ['required', 'file', 'max:'.$maxKb, 'mimetypes:'.implode(',', $mimes)],
        ];
    }
}
