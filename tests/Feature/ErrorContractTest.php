<?php

declare(strict_types=1);

use App\Models\IdempotencyKey;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->tenant = makeTenant();
    bindTenant($this->tenant);

    $this->user = User::factory()->create();
    makeMember($this->user, $this->tenant, ['finance.fees.read', 'finance.fees.write']);
    Sanctum::actingAs($this->user);
});

it('renders a standardized validation error envelope with a matching trace_id', function (): void {
    $response = $this->withHeader('X-Tenant-Id', $this->tenant->id)
        ->postJson('/api/v1/finance/fees', [])
        ->assertStatus(422);

    $response->assertJsonStructure(['success', 'message', 'errors', 'trace_id'])
        ->assertJsonPath('success', false);

    $traceId = $response->json('trace_id');
    expect($traceId)->toBeString()->not->toBeEmpty();
    $response->assertHeader('X-Trace-Id', $traceId);
});

it('renders a 404 with the envelope', function (): void {
    $response = $this->withHeader('X-Tenant-Id', $this->tenant->id)
        ->getJson('/api/v1/finance/fees/'.Str::uuid()->toString())
        ->assertStatus(404);

    $response->assertJsonStructure(['success', 'message', 'trace_id'])
        ->assertJsonPath('message', 'Not Found.')
        ->assertJsonPath('success', false);
});

it('renders a 403 with the envelope', function (): void {
    $user = User::factory()->create();
    makeMember($user, $this->tenant, ['finance.fees.read']);
    Sanctum::actingAs($user);

    $this->withHeader('X-Tenant-Id', $this->tenant->id)
        ->postJson('/api/v1/finance/fees', [
            'academic_year_label' => '2026',
            'grade_label' => 'Grade 5',
            'name' => 'Tuition',
            'category' => 'tuition',
            'cycle' => 'term',
            'amount_minor' => 50000,
        ])
        ->assertStatus(403)
        ->assertJsonStructure(['success', 'message', 'trace_id'])
        ->assertJsonPath('success', false);
});

it('renders a 401 with the envelope for unauthenticated requests', function (): void {
    // beforeEach authenticates a member; drop it to exercise the true
    // unauthenticated path.
    app('auth')->forgetGuards();

    $this->getJson('/api/v1/institution/campuses')
        ->assertStatus(401)
        ->assertJsonStructure(['success', 'message', 'trace_id'])
        ->assertJsonPath('message', 'Unauthenticated.')
        ->assertJsonPath('success', false);
});

it('renders 401 JSON even when the client omits the Accept header', function (): void {
    // No `Accept: application/json` — Laravel's web fallback would try to
    // redirect to route('login'), which does not exist, yielding a 500.
    // API routes must always render the JSON envelope instead.
    app('auth')->forgetGuards();

    $this->get('/api/v1/institution/campuses')
        ->assertStatus(401)
        ->assertJsonPath('success', false)
        ->assertJsonPath('message', 'Unauthenticated.');
});

it('adds X-Trace-Id to success responses', function (): void {
    $this->withHeader('X-Tenant-Id', $this->tenant->id)
        ->getJson('/api/v1/finance/fees')
        ->assertStatus(200)
        ->assertHeader('X-Trace-Id');

    $this->withHeader('X-Tenant-Id', $this->tenant->id)
        ->getJson('/api/v1/finance/fees')
        ->assertStatus(200);
});

it('carries the envelope on idempotency conflicts', function (): void {
    IdempotencyKey::create([
        'scope' => $this->tenant->id,
        'key' => 'inflight-err',
        'method' => 'POST',
        'path' => 'api/v1/finance/fees',
        'expires_at' => now()->addDay(),
    ]);

    $this->withHeader('X-Tenant-Id', $this->tenant->id)
        ->withHeader('Idempotency-Key', 'inflight-err')
        ->postJson('/api/v1/finance/fees', ['name' => 'x'])
        ->assertStatus(409)
        ->assertJsonStructure(['success', 'message', 'trace_id'])
        ->assertJsonPath('success', false);
});
