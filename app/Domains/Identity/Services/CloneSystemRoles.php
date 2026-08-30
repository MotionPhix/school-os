<?php

declare(strict_types=1);

namespace App\Domains\Identity\Services;

use App\Database\Seeders\SystemRolesSeeder;
use App\Enums\RoleScope;
use App\Models\Role;
use App\Models\Tenant;

/**
 * Copies every non-platform system role template (the seeded rows with
 * `tenant_id = null`) into a tenant so the first admin has a real role
 * bundle to assign, and so the tenant can customise its own copies
 * without touching the shared templates.
 *
 * Idempotent: re-running only refreshes permission bundles of rows that
 * are still flagged `is_system` (a tenant that edited a role keeps its
 * customisation because WriteRole clears `is_system`).
 *
 * @see SystemRolesSeeder for the template source.
 */
final class CloneSystemRoles
{
    /** @return list<Role> the tenant-scoped roles after cloning */
    public function handle(Tenant $tenant): array
    {
        $templates = Role::query()
            ->whereNull('tenant_id')
            ->where('is_system', true)
            ->where('scope', '!=', RoleScope::Platform->value)
            ->get();

        $cloned = [];

        foreach ($templates as $template) {
            $role = Role::firstOrNew([
                'tenant_id' => $tenant->id,
                'key' => $template->key,
            ]);

            // Never overwrite a role the tenant has customised.
            if ($role->exists && ! $role->is_system) {
                $cloned[] = $role;

                continue;
            }

            $role->fill([
                'name' => $template->name,
                'description' => $template->description,
                'scope' => $template->scope,
                'is_system' => true,
                'permission_keys' => $template->permission_keys,
            ])->save();

            $cloned[] = $role;
        }

        return $cloned;
    }
}
