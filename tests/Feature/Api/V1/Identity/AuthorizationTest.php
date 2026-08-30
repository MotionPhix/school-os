<?php

declare(strict_types=1);

use App\Enums\RoleScope;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->tenantA = makeTenant(['name' => 'School A']);
    $this->tenantB = makeTenant(['name' => 'School B']);
});

describe('role authorization', function (): void {
    it('rejects creating a role without identity.roles.write', function (): void {
        $user = User::factory()->create();
        makeMember($user, $this->tenantA, []);
        Sanctum::actingAs($user);

        $this->withHeader('X-Tenant-Id', $this->tenantA->id)
            ->postJson('/api/v1/identity/roles', [
                'key' => 'custom.role',
                'name' => 'Custom Role',
                'description' => 'A custom role',
                'scope' => 'tenant',
                'permission_keys' => [],
            ])
            ->assertStatus(403);
    });

    it('allows creating a role with identity.roles.write', function (): void {
        $user = User::factory()->create();
        makeMember($user, $this->tenantA, ['identity.roles.write']);
        Sanctum::actingAs($user);

        $this->withHeader('X-Tenant-Id', $this->tenantA->id)
            ->postJson('/api/v1/identity/roles', [
                'key' => 'custom.role',
                'name' => 'Custom Role',
                'description' => 'A custom role',
                'scope' => 'tenant',
                'permission_keys' => ['identity.roles.read'],
            ])
            ->assertStatus(201);
    });

    it('blocks viewing a role owned by another tenant', function (): void {
        $roleB = makeRole($this->tenantB, ['identity.roles.read']);
        $user = User::factory()->create();
        makeMember($user, $this->tenantA, ['identity.roles.read']);
        Sanctum::actingAs($user);

        $this->withHeader('X-Tenant-Id', $this->tenantA->id)
            ->getJson("/api/v1/identity/roles/{$roleB->id}")
            ->assertStatus(403);
    });

    it('blocks updating a role owned by another tenant', function (): void {
        $roleB = makeRole($this->tenantB, ['identity.roles.read']);
        $user = User::factory()->create();
        makeMember($user, $this->tenantA, ['identity.roles.write']);
        Sanctum::actingAs($user);

        $this->withHeader('X-Tenant-Id', $this->tenantA->id)
            ->patchJson("/api/v1/identity/roles/{$roleB->id}", ['name' => 'Hijacked'])
            ->assertStatus(403);
    });

    it('blocks updating a system role', function (): void {
        $systemRole = makeRole($this->tenantA, [], [
            'key' => 'sys.role',
            'name' => 'System Role',
            'is_system' => true,
        ]);
        $user = User::factory()->create();
        makeMember($user, $this->tenantA, ['identity.roles.write']);
        Sanctum::actingAs($user);

        $this->withHeader('X-Tenant-Id', $this->tenantA->id)
            ->patchJson("/api/v1/identity/roles/{$systemRole->id}", ['name' => 'Tampered'])
            ->assertStatus(403);
    });

    it('allows updating a role owned by the active tenant', function (): void {
        $role = makeRole($this->tenantA, []);
        $user = User::factory()->create();
        makeMember($user, $this->tenantA, ['identity.roles.write']);
        Sanctum::actingAs($user);

        $this->withHeader('X-Tenant-Id', $this->tenantA->id)
            ->patchJson("/api/v1/identity/roles/{$role->id}", ['name' => 'Renamed Role'])
            ->assertStatus(200)
            ->assertJsonPath('data.name', 'Renamed Role');
    });
});

describe('user authorization', function (): void {
    it('blocks viewing a user from another tenant', function (): void {
        $userB = User::factory()->create();
        makeMember($userB, $this->tenantB, []);

        $userA = User::factory()->create();
        makeMember($userA, $this->tenantA, ['identity.users.read']);
        Sanctum::actingAs($userA);

        $this->withHeader('X-Tenant-Id', $this->tenantA->id)
            ->getJson("/api/v1/identity/users/{$userB->id}")
            ->assertStatus(403);
    });

    it('blocks assigning roles to a user from another tenant', function (): void {
        $roleA = makeRole($this->tenantA, []);
        $userB = User::factory()->create();
        makeMember($userB, $this->tenantB, []);

        $userA = User::factory()->create();
        makeMember($userA, $this->tenantA, ['identity.users.write']);
        Sanctum::actingAs($userA);

        $this->withHeader('X-Tenant-Id', $this->tenantA->id)
            ->putJson("/api/v1/identity/users/{$userB->id}/roles", ['role_ids' => [$roleA->id]])
            ->assertStatus(403);
    });

    it('allows assigning roles within the active tenant', function (): void {
        $roleA = makeRole($this->tenantA, ['identity.users.read']);
        $target = User::factory()->create();
        makeMember($target, $this->tenantA, []);

        $userA = User::factory()->create();
        makeMember($userA, $this->tenantA, ['identity.users.write']);
        Sanctum::actingAs($userA);

        $this->withHeader('X-Tenant-Id', $this->tenantA->id)
            ->putJson("/api/v1/identity/users/{$target->id}/roles", ['role_ids' => [$roleA->id]])
            ->assertStatus(200);
    });
});

describe('tenant authorization', function (): void {
    it('rejects tenant updates from non-platform-admins', function (): void {
        $user = User::factory()->create();
        makeMember($user, $this->tenantA, ['identity.tenants.read']);
        Sanctum::actingAs($user);

        $this->withHeader('X-Tenant-Id', $this->tenantA->id)
            ->patchJson("/api/v1/identity/tenants/{$this->tenantA->id}", ['name' => 'Renamed School'])
            ->assertStatus(403);
    });

    it('allows tenant updates from platform admins', function (): void {
        $platformRole = Role::create([
            'tenant_id' => null,
            'key' => 'platform.admin',
            'name' => 'Platform Admin',
            'description' => 'Platform administrator',
            'scope' => RoleScope::Platform,
            'is_system' => true,
            'permission_keys' => [],
        ]);

        $admin = User::factory()->create();
        makeMember($admin, $this->tenantA, [], $platformRole);
        Sanctum::actingAs($admin);

        $this->withHeader('X-Tenant-Id', $this->tenantA->id)
            ->patchJson("/api/v1/identity/tenants/{$this->tenantA->id}", ['name' => 'Renamed School'])
            ->assertStatus(200)
            ->assertJsonPath('data.name', 'Renamed School');
    });
});

describe('invitation authorization', function (): void {
    it('rejects inviting without identity.invitations.write', function (): void {
        $roleA = makeRole($this->tenantA, []);
        $user = User::factory()->create();
        makeMember($user, $this->tenantA, []);
        Sanctum::actingAs($user);

        $this->withHeader('X-Tenant-Id', $this->tenantA->id)
            ->postJson('/api/v1/identity/invitations', [
                'email' => 'invitee@example.com',
                'role_ids' => [$roleA->id],
            ])
            ->assertStatus(403);
    });

    it('allows inviting with identity.invitations.write', function (): void {
        $roleA = makeRole($this->tenantA, []);
        $user = User::factory()->create();
        makeMember($user, $this->tenantA, ['identity.invitations.write']);
        Sanctum::actingAs($user);

        $this->withHeader('X-Tenant-Id', $this->tenantA->id)
            ->postJson('/api/v1/identity/invitations', [
                'email' => 'invitee@example.com',
                'role_ids' => [$roleA->id],
            ])
            ->assertStatus(201);
    });
});
