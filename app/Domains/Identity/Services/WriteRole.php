<?php

declare(strict_types=1);

namespace App\Domains\Identity\Services;

use App\Domains\Identity\Events\RoleCreated;
use App\Domains\Identity\Events\RoleUpdated;
use App\Domains\Identity\Support\PermissionCatalog;
use App\Enums\RoleScope;
use App\Models\Role;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class WriteRole
{
    public function __construct(private readonly PermissionCatalog $catalog) {}

    /**
     * @param array{
     *   tenant_id?:string|null,
     *   key:string, name:string, description:string,
     *   scope:RoleScope|string, permission_keys:list<string>
     * } $data
     */
    public function create(array $data): Role
    {
        $role = Role::create([
            'tenant_id' => $data['tenant_id'] ?? null,
            'key' => $data['key'],
            'name' => $data['name'],
            'description' => $data['description'],
            'scope' => $data['scope'],
            'is_system' => false,
            'permission_keys' => $this->catalog->filterKnown($data['permission_keys']),
        ]);

        RoleCreated::dispatch($role);

        return $role;
    }

    /**
     * @param  array{name?:string, description?:string, permission_keys?:list<string>}  $data
     */
    public function update(Role $role, array $data): Role
    {
        if ($role->is_system && array_key_exists('permission_keys', $data)) {
            throw new HttpException(423, 'System roles have locked permissions.');
        }

        if (array_key_exists('permission_keys', $data)) {
            $data['permission_keys'] = $this->catalog->filterKnown($data['permission_keys']);
        }

        $role->update($data);
        RoleUpdated::dispatch($role);

        return $role;
    }
}
