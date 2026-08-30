<?php

declare(strict_types=1);

namespace App\Domains\Institution\Services;

use App\Domains\Institution\Events\InstitutionProfileUpdated;
use App\Models\InstitutionProfile;
use App\Support\TenantContext;
use Illuminate\Support\Facades\DB;

final class UpsertInstitutionProfile
{
    public function __construct(private readonly TenantContext $tenants) {}

    /**
     * @param  array<string,mixed>  $data
     */
    public function handle(array $data): InstitutionProfile
    {
        return DB::transaction(function () use ($data): InstitutionProfile {
            $tenantId = $this->tenants->id();

            $profile = InstitutionProfile::withoutGlobalScopes()
                ->firstOrNew(['tenant_id' => $tenantId]);

            $profile->fill($data);
            $profile->tenant_id = $tenantId;
            $profile->save();

            InstitutionProfileUpdated::dispatch($profile);

            return $profile;
        });
    }
}
