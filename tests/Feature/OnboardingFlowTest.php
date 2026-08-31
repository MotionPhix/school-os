<?php

declare(strict_types=1);

use App\Domains\Identity\Services\CreateTenant;
use App\Models\InstitutionProfile;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use Database\Seeders\SystemRolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

/** A payload that passes StoreTenantRequest rules; slug must be unique per test. */
function onboardingTenantPayload(string $slug): array
{
    return [
        'slug' => $slug,
        'name' => 'New School',
        'legal_name' => 'New School Ltd',
        'country_code' => 'MW',
        'timezone' => 'Africa/Blantyre',
        'currency_code' => 'MWK',
    ];
}

describe('CreateTenant hardening', function (): void {
    it('rolls back the whole bootstrap when the tenant-scoped principal role is missing', function (): void {
        // No system role templates exist in this test, so CloneSystemRoles
        // clones nothing and the new tenant has no `principal` role.
        // CreateTenant must fail closed — no fallback to the global
        // (null-tenant) role — and the transaction must leave no trace behind.
        $user = User::factory()->create();
        $service = app(CreateTenant::class);

        expect(fn () => $service->handle(onboardingTenantPayload('escalation-school'), $user))
            ->toThrow(RuntimeException::class, 'Tenant-scoped principal role missing');

        expect(Tenant::count())->toBe(0)
            ->and(InstitutionProfile::count())->toBe(0)
            ->and(TenantMembership::count())->toBe(0)
            ->and($user->fresh()->active_tenant_id)->toBeNull();
    });

    it('rejects tenant creation once the per-account cap is reached', function (): void {
        config(['identity.max_tenants_per_user' => 1]);

        $user = User::factory()->create();
        makeMember($user, makeTenant());

        $service = app(CreateTenant::class);

        try {
            $service->handle(onboardingTenantPayload('capped-school'), $user);
            $this->fail('Expected a ValidationException for the tenant cap.');
        } catch (ValidationException $e) {
            expect($e->status)->toBe(422)
                ->and($e->errors())->toHaveKey('slug');
        }

        expect(Tenant::where('slug', 'capped-school')->exists())->toBeFalse();
    });
});

describe('Session landing contract', function (): void {
    it('reports verified without memberships while the user is on the onboarding step', function (): void {
        $user = User::factory()->create(); // verified by default, no memberships
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/identity/session')
            ->assertOk()
            ->assertJsonPath('data.email_verified', true)
            ->assertJsonPath('data.has_memberships', false);
    });

    it('reports verified with memberships once the user has landed in a tenant', function (): void {
        $user = User::factory()->create();
        makeMember($user, makeTenant());
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/identity/session')
            ->assertOk()
            ->assertJsonPath('data.email_verified', true)
            ->assertJsonPath('data.has_memberships', true);
    });

    it('keeps the 403 as the unverified signal on the session surface', function (): void {
        $user = User::factory()->unverified()->create();
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/identity/session')->assertStatus(403);
    });
});

describe('register → verify → onboard → land', function (): void {
    it('walks a tenant admin from registration to the console', function (): void {
        // Day-0 bootstrap needs the seeded role templates so CloneSystemRoles
        // can create the tenant-scoped `principal` role for the creator.
        $this->seed(SystemRolesSeeder::class);

        $register = $this->postJson('/api/v1/identity/registration', [
            'full_name' => 'Onboarding Admin',
            'email' => 'onboard@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertStatus(201);

        $token = $register->json('data.token');

        // Step 1 — unverified: the session surface signals the verify screen.
        $this->withToken($token)
            ->getJson('/api/v1/identity/session')
            ->assertStatus(403);

        // Step 2 — verify via the signed link from the verification email.
        $user = User::query()->where('email', 'onboard@example.com')->firstOrFail();
        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1($user->email)],
        );

        $this->withToken($token)->postJson($verificationUrl)->assertStatus(204);

        // Step 3 — verified, no memberships yet: the SPA routes to onboarding.
        $this->withToken($token)
            ->getJson('/api/v1/identity/session')
            ->assertOk()
            ->assertJsonPath('data.email_verified', true)
            ->assertJsonPath('data.has_memberships', false);

        // Step 4 — create the first tenant: the creator becomes its principal.
        $this->withToken($token)
            ->postJson('/api/v1/identity/tenants', onboardingTenantPayload('onboard-school'))
            ->assertStatus(201)
            ->assertJsonPath('data.slug', 'onboard-school');

        // Step 5 — member now: the SPA lands in the console.
        $this->withToken($token)
            ->getJson('/api/v1/identity/session')
            ->assertOk()
            ->assertJsonPath('data.email_verified', true)
            ->assertJsonPath('data.has_memberships', true);
    });
});
