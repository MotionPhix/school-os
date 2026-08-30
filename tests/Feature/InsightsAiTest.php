<?php

declare(strict_types=1);

use App\Ai\Agents\SchoolAssistant;
use App\Models\Campus;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Ai\Prompts\AgentPrompt;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->tenant = makeTenant(['name' => 'Kawale Academy']);
    bindTenant($this->tenant);

    $this->campus = Campus::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Main Campus',
        'code' => 'MAIN',
        'status' => 'operational',
        'address_line' => '1 Test Road',
        'city' => 'Lilongwe',
        'region' => 'Central',
        'timezone' => 'Africa/Blantyre',
    ]);

    Student::create([
        'tenant_id' => $this->tenant->id,
        'campus_id' => $this->campus->id,
        'admission_number' => 'ADM-1001',
        'full_name' => 'Ada Lovelace',
        'avatar_initials' => 'AL',
        'date_of_birth' => '2012-04-01',
        'stage' => 'primary',
        'grade_label' => 'Grade 5',
        'status' => 'enrolled',
    ]);

    $this->principal = User::factory()->create(['name' => 'Principal P']);
    makeMember($this->principal, $this->tenant, ['insights.ai.read']);

    Sanctum::actingAs($this->principal);
});

it('rejects callers without the AI assistant permission', function (): void {
    $staff = User::factory()->create(['name' => 'Staff S']);
    makeMember($staff, $this->tenant, []);
    Sanctum::actingAs($staff);

    $this->withHeader('X-Tenant-Id', $this->tenant->id)
        ->postJson('/api/v1/insights/ai/ask', ['question' => 'How many students?'])
        ->assertStatus(403);
});

it('validates the question is required and bounded', function (): void {
    $this->withHeader('X-Tenant-Id', $this->tenant->id)
        ->postJson('/api/v1/insights/ai/ask', [])
        ->assertStatus(422);

    $this->withHeader('X-Tenant-Id', $this->tenant->id)
        ->postJson('/api/v1/insights/ai/ask', ['question' => Str::repeat('x', 501)])
        ->assertStatus(422);
});

it('returns 503 while the AI assistant is disabled', function (): void {
    config(['insights.ai.enabled' => false]);

    $this->withHeader('X-Tenant-Id', $this->tenant->id)
        ->postJson('/api/v1/insights/ai/ask', ['question' => 'How many students?'])
        ->assertStatus(503);
});

it('answers from the authoritative tenant context snapshot', function (): void {
    makeTenant(['name' => 'Other School']); // must never leak into the prompt

    config(['insights.ai.enabled' => true]);
    SchoolAssistant::fake(['Kawale Academy currently has 1 enrolled student.']);

    $this->withHeader('X-Tenant-Id', $this->tenant->id)
        ->postJson('/api/v1/insights/ai/ask', ['question' => 'How many students do we have?'])
        ->assertStatus(200)
        ->assertJsonPath('data.answer', 'Kawale Academy currently has 1 enrolled student.');

    SchoolAssistant::assertPrompted(function (AgentPrompt $prompt): bool {
        $instructions = (string) $prompt->agent->instructions();

        return str_contains($instructions, 'Kawale Academy')
            && str_contains($instructions, 'Snapshot as of')
            && ! str_contains($instructions, 'Other School')
            && str_contains($prompt->prompt, 'How many students do we have?');
    });
});
