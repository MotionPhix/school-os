<?php

declare(strict_types=1);

use App\Models\FeeStructure;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

describe('tenant context lifecycle', function (): void {
    it('binds the active tenant during a tenant-scoped request', function (): void {
        $tenant = makeTenant();
        $user = User::factory()->create();
        makeMember($user, $tenant, []);
        Sanctum::actingAs($user);

        // resolve.tenant runs before authorization, so the 403 still proves
        // the context was bound for this request.
        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->getJson('/api/v1/identity/users')
            ->assertStatus(403);

        expect(app(TenantContext::class)->id())->toBe($tenant->id);
    });

    it('derives the tenant from the current request, never a stale one', function (): void {
        $tenantA = makeTenant();
        $tenantB = makeTenant();

        $userA = User::factory()->create();
        makeMember($userA, $tenantA, ['identity.users.read']);
        Sanctum::actingAs($userA);

        $this->withHeader('X-Tenant-Id', $tenantA->id)
            ->getJson('/api/v1/identity/users')
            ->assertStatus(200);

        expect(app(TenantContext::class)->id())->toBe($tenantA->id);

        // Switch actor to a tenant-B user hitting a route that skips
        // resolve.tenant: the context must come from *this* request
        // (active tenant B), not the previous request's tenant A.
        $this->flushHeaders();
        $userB = User::factory()->create();
        makeMember($userB, $tenantB, []);
        Sanctum::actingAs($userB);

        $this->getJson('/api/v1/identity/session')
            ->assertStatus(200);

        expect(app(TenantContext::class)->id())->toBe($tenantB->id);
    });

    it('leaves the context null for unauthenticated public routes', function (): void {
        $this->postJson('/api/v1/identity/password/forgot', [
            'email' => 'nobody@example.com',
        ])->assertStatus(204);

        expect(app(TenantContext::class)->id())->toBeNull();
    });
});

describe('finance tenant isolation', function (): void {
    it('returns 404 for a fee structure of another tenant', function (): void {
        $tenantA = makeTenant();
        $tenantB = makeTenant();

        bindTenant($tenantB);
        $feeB = FeeStructure::create([
            'tenant_id' => $tenantB->id,
            'academic_year_label' => '2026',
            'grade_label' => 'Grade 5',
            'name' => 'Tuition',
            'category' => 'tuition',
            'cycle' => 'term',
            'amount_minor' => 50000,
            'currency' => 'MWK',
            'is_active' => true,
        ]);

        bindTenant($tenantA);
        $user = User::factory()->create();
        makeMember($user, $tenantA, ['finance.fees.read']);
        Sanctum::actingAs($user);

        $this->withHeader('X-Tenant-Id', $tenantA->id)
            ->getJson("/api/v1/finance/fees/{$feeB->id}")
            ->assertStatus(404);
    });

    it('returns the fee structure for the caller\'s own tenant', function (): void {
        $tenant = makeTenant();
        bindTenant($tenant);

        $fee = FeeStructure::create([
            'tenant_id' => $tenant->id,
            'academic_year_label' => '2026',
            'grade_label' => 'Grade 5',
            'name' => 'Tuition',
            'category' => 'tuition',
            'cycle' => 'term',
            'amount_minor' => 50000,
            'currency' => 'MWK',
            'is_active' => true,
        ]);

        $user = User::factory()->create();
        makeMember($user, $tenant, ['finance.fees.read']);
        Sanctum::actingAs($user);

        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->getJson("/api/v1/finance/fees/{$fee->id}")
            ->assertStatus(200)
            ->assertJsonPath('data.id', $fee->id);
    });
});
