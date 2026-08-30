<?php

declare(strict_types=1);

namespace App\Domains\Institution\Services;

use App\Domains\Institution\Events\InstitutionProfileUpdated;
use App\Models\InstitutionProfile;
use App\Support\TenantContext;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Stores (or clears) the institution crest on the public disk and records
 * the resulting URL on the profile singleton.
 */
final class SetInstitutionLogo
{
    public function __construct(private readonly TenantContext $tenants) {}

    public function store(UploadedFile $file): InstitutionProfile
    {
        $tenantId = $this->tenants->id();
        $path = $file->store("tenants/{$tenantId}/branding", 'public');

        return $this->persist(Storage::disk('public')->url($path));
    }

    public function clear(): InstitutionProfile
    {
        return $this->persist(null);
    }

    private function persist(?string $url): InstitutionProfile
    {
        return DB::transaction(function () use ($url): InstitutionProfile {
            $profile = InstitutionProfile::query()
                ->where('tenant_id', $this->tenants->id())
                ->firstOrFail();

            $previous = $profile->logo_url;
            $profile->logo_url = $url;
            $profile->save();

            if ($previous !== null) {
                $this->deleteIfLocal($previous);
            }

            InstitutionProfileUpdated::dispatch($profile);

            return $profile;
        });
    }

    private function deleteIfLocal(string $url): void
    {
        $base = Storage::disk('public')->url('');
        if (! str_starts_with($url, $base)) {
            return;
        }

        Storage::disk('public')->delete(mb_ltrim(mb_substr($url, mb_strlen($base)), '/'));
    }
}
