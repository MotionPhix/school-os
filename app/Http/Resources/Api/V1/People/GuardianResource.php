<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\People;

use App\Http\Resources\Api\V1\CapabilityResource;
use App\Models\Guardian;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * @mixin Guardian
 */
final class GuardianResource extends CapabilityResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'user_id' => $this->user_id,
            'full_name' => $this->full_name,
            'avatar_initials' => $this->avatar_initials,
            'avatar_url' => $this->avatarUrl(),
            'occupation' => $this->occupation,
            'employer' => $this->employer,
            'contact' => [
                'email' => $this->contact_email,
                'phone' => $this->contact_phone,
                'address_line' => $this->contact_address_line,
                'city' => $this->contact_city,
                'region' => $this->contact_region,
            ],
            'preferred_language' => $this->preferred_language,
            'portal_status' => $this->portal_status->value,
            'portal_last_seen_at' => $this->iso($this->portal_last_seen_at),
            'students' => $this->whenLoaded('students', function () {
                return $this->students->map(fn ($s) => [
                    'student_id' => $s->id,
                    'student_name' => $s->full_name,
                    'grade_label' => $s->grade_label,
                    'relationship' => $s->pivot->relationship,
                    'is_primary' => (bool) $s->pivot->is_primary,
                ])->values();
            }, []),
            'documents' => PersonDocumentResource::collection(
                $this->whenLoaded('documents', fn () => $this->documents),
            ),
            'updated_at' => $this->iso($this->updated_at),
        ];
    }

    private function avatarUrl(): ?string
    {
        if (! $this->avatar_path) {
            return null;
        }
        $disk = (string) config('people.media.disk', 'local');
        try {
            return Storage::disk($disk)->temporaryUrl($this->avatar_path, now()->addMinutes(60));
        } catch (Throwable) {
            return Storage::disk($disk)->url($this->avatar_path);
        }
    }
}
