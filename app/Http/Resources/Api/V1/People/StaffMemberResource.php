<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\People;

use App\Http\Resources\Api\V1\CapabilityResource;
use App\Models\StaffMember;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * @mixin StaffMember
 */
final class StaffMemberResource extends CapabilityResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'campus_id' => $this->campus_id,
            'campus_name' => $this->whenLoaded('campus', fn () => $this->campus?->name),
            'staff_number' => $this->staff_number,
            'full_name' => $this->full_name,
            'avatar_initials' => $this->avatar_initials,
            'avatar_url' => $this->avatarUrl(),
            'title' => $this->title,
            'category' => $this->category->value,
            'department' => $this->department,
            'employment_type' => $this->employment_type->value,
            'status' => $this->status->value,
            'contact' => [
                'email' => $this->contact_email,
                'phone' => $this->contact_phone,
                'address_line' => $this->contact_address_line,
                'city' => $this->contact_city,
                'region' => $this->contact_region,
            ],
            'user_id' => $this->user_id,
            'subjects_taught' => (array) $this->subjects_taught,
            'hired_on' => optional($this->hired_on)?->toDateString(),
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
