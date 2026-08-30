<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domains\Identity\Support\PermissionCatalog;
use App\Enums\RoleScope;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Idempotently seeds system roles from config/identity.php.
 *
 * - `platform.*` roles land as global (tenant_id = null).
 * - Non-platform system roles are seeded as *templates* — they live at
 *   tenant_id = null and are cloned per-tenant on tenant creation
 *   (TODO: clone step in Domains/Identity/Services/CreateTenant once the
 *   Identity UI needs per-tenant customization).
 */
final class SystemRolesSeeder extends Seeder
{
    public function run(): void
    {
        $catalog = app(PermissionCatalog::class);
        $allKeys = $catalog->keys();

        foreach ((array) config('identity.system_roles', []) as $def) {
            $keys = $def['permission_keys'] === '*'
                ? $allKeys
                : $catalog->filterKnown((array) $def['permission_keys']);

            $role = Role::firstOrNew(['tenant_id' => null, 'key' => $def['key']]);

            // Belt-and-braces: HasUuid normally fills this on `creating`,
            // but seeding must not depend on the boot hook being present.
            if (! $role->exists && empty($role->id)) {
                $role->id = method_exists(Str::class, 'uuid7') ? (string) Str::uuid7() : (string) Str::uuid();
            }

            $role->fill([
                'name' => $def['name'],
                'description' => $def['description'],
                'scope' => RoleScope::from($def['scope']),
                'is_system' => true,
                'permission_keys' => $keys,
            ])->save();

        }
    }
}
