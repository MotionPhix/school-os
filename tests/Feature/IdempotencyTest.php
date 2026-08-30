<?php

declare(strict_types=1);

use App\Models\IdempotencyKey;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->tenant = makeTenant();
    bindTenant($this->tenant);

    $this->user = User::factory()->create();
    makeMember($this->user, $this->tenant, ['finance.fees.write', 'finance.fees.read']);
    Sanctum::actingAs($this->user);

    $this->payload = [
        'academic_year_label' => '2026',
        'grade_label' => 'Grade 5',
        'name' => 'Tuition',
        'category' => 'tuition',
        'cycle' => 'term',
        'amount_minor' => 50000,
    ];

    $this->postFee = function (array $headers = []): TestResponse {
        return $this->withHeader('X-Tenant-Id', $this->tenant->id)
            ->withHeaders($headers)
            ->postJson('/api/v1/finance/fees', $this->payload);
    };
});

it('replays the stored response for the same key instead of re-executing', function (): void {
    $first = ($this->postFee)(['Idempotency-Key' => 'key-1'])->assertStatus(201);
    $second = ($this->postFee)(['Idempotency-Key' => 'key-1'])->assertStatus(201);

    $second->assertHeader('Idempotency-Replayed', 'true');
    $this->assertSame($first->json(), $second->json());
    $this->assertDatabaseCount('finance_fee_structures', 1);
    $this->assertDatabaseCount('idempotency_keys', 1);
});

it('treats different keys as different operations', function (): void {
    ($this->postFee)(['Idempotency-Key' => 'key-a'])->assertStatus(201);
    ($this->postFee)(['Idempotency-Key' => 'key-b'])->assertStatus(201);

    $this->assertDatabaseCount('finance_fee_structures', 2);
});

it('ignores requests without an Idempotency-Key', function (): void {
    ($this->postFee)()->assertStatus(201);
    ($this->postFee)()->assertStatus(201);

    $this->assertDatabaseCount('finance_fee_structures', 2);
    $this->assertDatabaseCount('idempotency_keys', 0);
});

it('ignores the header on read requests', function (): void {
    $this->withHeader('X-Tenant-Id', $this->tenant->id)
        ->withHeader('Idempotency-Key', 'read-key')
        ->getJson('/api/v1/finance/fees')
        ->assertStatus(200);

    $this->assertDatabaseCount('idempotency_keys', 0);
});

it('scopes keys per tenant', function (): void {
    $tenantB = makeTenant();
    $userB = User::factory()->create();
    makeMember($userB, $tenantB, ['finance.fees.write']);

    ($this->postFee)(['Idempotency-Key' => 'shared-key'])->assertStatus(201);

    Sanctum::actingAs($userB);
    $this->withHeader('X-Tenant-Id', $tenantB->id)
        ->withHeader('Idempotency-Key', 'shared-key')
        ->postJson('/api/v1/finance/fees', $this->payload)
        ->assertStatus(201)
        ->assertHeaderMissing('Idempotency-Replayed');

    $this->assertDatabaseCount('finance_fee_structures', 2);
});

it('returns 409 while a request with the same key is in flight', function (): void {
    IdempotencyKey::create([
        'scope' => $this->tenant->id,
        'key' => 'inflight',
        'method' => 'POST',
        'path' => 'api/v1/finance/fees',
        'expires_at' => now()->addDay(),
    ]);

    ($this->postFee)(['Idempotency-Key' => 'inflight'])->assertStatus(409);
});

it('caches validation errors so a retry returns the same 422', function (): void {
    $badPayload = ['name' => 'Missing required fields'];

    $first = $this->withHeader('X-Tenant-Id', $this->tenant->id)
        ->withHeader('Idempotency-Key', 'bad-1')
        ->postJson('/api/v1/finance/fees', $badPayload)
        ->assertStatus(422);

    $second = $this->withHeader('X-Tenant-Id', $this->tenant->id)
        ->withHeader('Idempotency-Key', 'bad-1')
        ->postJson('/api/v1/finance/fees', $badPayload)
        ->assertStatus(422);

    $second->assertHeader('Idempotency-Replayed', 'true');
    $this->assertSame($first->json(), $second->json());
});
