<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\People;

use App\Http\Resources\Api\V1\CapabilityResource;
use App\Models\PersonDocument;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * @mixin PersonDocument
 */
final class PersonDocumentResource extends CapabilityResource
{
    public function toArray(Request $request): array
    {
        $disk = (string) config('people.media.disk', 'local');

        return [
            'id' => $this->id,
            'name' => $this->name,
            'mime' => $this->mime,
            'size' => (int) $this->size,
            // Temporary URL where supported; fall back to the disk URL otherwise.
            // Frontend treats this as opaque — signed / short-lived when the
            // Media capability lands.
            'url' => $this->resolveUrl($disk),
            'uploaded_at' => $this->iso($this->uploaded_at),
        ];
    }

    private function resolveUrl(string $disk): string
    {
        $storage = Storage::disk($disk);

        try {
            return $storage->temporaryUrl($this->storage_path, now()->addMinutes(30));
        } catch (Throwable) {
            return $storage->url($this->storage_path);
        }
    }
}
