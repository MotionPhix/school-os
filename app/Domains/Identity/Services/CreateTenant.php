<?php

declare(strict_types=1);

namespace App\Domains\Identity\Services;

use App\Domains\Identity\Events\TenantCreated;
use App\Enums\TenantStatus;
use App\Enums\TenantTier;
use App\Models\InstitutionProfile;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

final class CreateTenant
{
    /**
     * @param array{
     *   slug:string, name:string, legal_name:string,
     *   country_code:string, timezone:string, currency_code:string,
     *   tier?:TenantTier|string, status?:TenantStatus|string
     * } $data
     */
    public function handle(array $data, ?User $owner = null): Tenant
    {
        if ($owner !== null) {
            $max = config('identity.max_tenants_per_user', 5);
            if (! is_int($max)) {
                $max = 5;
            }

            if ($owner->memberships()->count() >= $max) {
                throw ValidationException::withMessages([
                    'slug' => "You have reached the maximum of {$max} tenants per account.",
                ]);
            }
        }

        return DB::transaction(function () use ($data, $owner): Tenant {
            $tenant = Tenant::create([
                'slug' => $data['slug'],
                'name' => $data['name'],
                'legal_name' => $data['legal_name'],
                'country_code' => mb_strtoupper($data['country_code']),
                'timezone' => $data['timezone'],
                'currency_code' => mb_strtoupper($data['currency_code']),
                'tier' => $data['tier'] ?? TenantTier::Institution,
                'status' => $data['status'] ?? TenantStatus::Active,
            ]);

            // Clone the seeded tenant-scoped system role templates into this
            // tenant so the first admin has a real, editable role bundle.
            app(CloneSystemRoles::class)->handle($tenant);

            // Day-0 bootstrap: the creator becomes the first member of the
            // tenant with the tenant's own `principal` role, and the new
            // tenant becomes their active one. Without this, a freshly
            // registered user would create a tenant they cannot then enter.
            if ($owner !== null) {
                // The bootstrap role must come from THIS tenant. Falling
                // back to a null-tenant (global) role would grant the
                // creator whatever permissions that role carries across
                // every tenant — a privilege-escalation hazard. Missing
                // tenant-scoped role → abort the whole bootstrap.
                $principalId = Role::query()
                    ->where('tenant_id', $tenant->id)
                    ->where('key', 'principal')
                    ->value('id');

                if ($principalId === null) {
                    throw new RuntimeException('Tenant-scoped principal role missing after role bootstrap.');
                }

                TenantMembership::query()->create([
                    'user_id' => $owner->id,
                    'tenant_id' => $tenant->id,
                    'role_ids' => [$principalId],
                    'joined_at' => now(),
                ]);

                $owner->forceFill(['active_tenant_id' => $tenant->id])->save();
            }

            // Day-0 bootstrap: seed a placeholder institution profile so the
            // Institution capability has a row to read/patch immediately —
            // GET /institution/profile 404s on a brand-new tenant otherwise.
            InstitutionProfile::query()->create([
                'tenant_id' => $tenant->id,
                'name' => $tenant->name,
                'short_name' => mb_substr($tenant->name, 0, 32),
                'established_year' => (int) now()->format('Y'),
                'student_capacity' => 0,
                'languages_of_instruction' => ['en'],
                'contact_email' => $owner?->email ?? 'admin@'.$tenant->slug.'.example',
                'contact_phone' => '',
                'address_line' => '',
                'city' => '',
                'region' => '',
            ]);

            TenantCreated::dispatch($tenant);

            return $tenant;
        });
    }
}
