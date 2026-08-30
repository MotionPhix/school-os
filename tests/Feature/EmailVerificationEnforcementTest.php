<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->tenant = makeTenant();
    bindTenant($this->tenant);
});

it('blocks an unverified member from tenant routes with 403', function (): void {
    $user = User::factory()->create(['email_verified_at' => null]);
    makeMember($user, $this->tenant, ['institution.campuses.read']);
    Sanctum::actingAs($user);

    $this->withHeader('X-Tenant-Id', $this->tenant->id)
        ->getJson('/api/v1/institution/campuses')
        ->assertStatus(403);
});

it('blocks an unverified member from the session surface', function (): void {
    $user = User::factory()->create(['email_verified_at' => null]);
    makeMember($user, $this->tenant, []);
    Sanctum::actingAs($user);

    $this->getJson('/api/v1/identity/session')
        ->assertStatus(403);
});

it('allows verified members through', function (): void {
    $user = User::factory()->create(); // factory verifies email by default
    makeMember($user, $this->tenant, ['institution.campuses.read']);
    Sanctum::actingAs($user);

    $this->withHeader('X-Tenant-Id', $this->tenant->id)
        ->getJson('/api/v1/institution/campuses')
        ->assertStatus(200);
});

it('lets an unverified member verify via the signed link, then pass', function (): void {
    $user = User::factory()->create(['email_verified_at' => null]);
    makeMember($user, $this->tenant, ['institution.campuses.read']);
    Sanctum::actingAs($user);

    $this->withHeader('X-Tenant-Id', $this->tenant->id)
        ->getJson('/api/v1/institution/campuses')
        ->assertStatus(403);

    $verificationUrl = URL::temporarySignedRoute(
        'verification.verify',
        now()->addMinutes(60),
        ['id' => $user->id, 'hash' => sha1($user->email)],
    );

    $this->postJson($verificationUrl)->assertStatus(204);

    $this->withHeader('X-Tenant-Id', $this->tenant->id)
        ->getJson('/api/v1/institution/campuses')
        ->assertStatus(200);
});

it('leaves unauthenticated public routes untouched', function (): void {
    $this->postJson('/api/v1/identity/password/forgot', [
        'email' => 'nobody@example.com',
    ])->assertStatus(204);
});
