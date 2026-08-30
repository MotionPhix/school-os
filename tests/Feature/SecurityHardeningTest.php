<?php

declare(strict_types=1);

use App\Ai\Agents\SchoolAssistant;
use App\Models\User;
use App\Support\RealtimeChannels;
use Illuminate\Contracts\Broadcasting\Broadcaster as BroadcasterContract;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Broadcast;
use Laravel\Ai\Prompts\AgentPrompt;
use Laravel\Sanctum\Sanctum;
use Tests\Support\RecordingBroadcaster;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->tenant = makeTenant();
    bindTenant($this->tenant);

    $this->principal = User::factory()->create(['name' => 'Principal P']);
    makeMember($this->principal, $this->tenant, ['insights.ai.read']);

    Sanctum::actingAs($this->principal);

    config(['insights.ai.enabled' => true]);
});

it('rate-limits the AI assistant to protect provider spend', function (): void {
    SchoolAssistant::fake(['An answer.']);

    for ($i = 0; $i < 15; $i++) {
        $this->withHeader('X-Tenant-Id', $this->tenant->id)
            ->postJson('/api/v1/insights/ai/ask', ['question' => "Question {$i}"])
            ->assertStatus(200);
    }

    $this->withHeader('X-Tenant-Id', $this->tenant->id)
        ->postJson('/api/v1/insights/ai/ask', ['question' => 'Question 16'])
        ->assertStatus(429);
});

it('replays cached AI answers for the same idempotency key', function (): void {
    SchoolAssistant::fake(['The cached answer.']);

    $payload = ['question' => 'How many students?'];
    $headers = ['X-Tenant-Id' => $this->tenant->id, 'Idempotency-Key' => 'ai-ask-1'];

    $first = $this->withHeaders($headers)->postJson('/api/v1/insights/ai/ask', $payload)->assertStatus(200);
    $second = $this->withHeaders($headers)->postJson('/api/v1/insights/ai/ask', $payload)->assertStatus(200);

    expect($second->json('data.answer'))->toBe($first->json('data.answer'));

    SchoolAssistant::assertPromptedTimes(1);
});

it('sanitizes control characters out of the question before prompting', function (): void {
    SchoolAssistant::fake(['Sanitized answer.']);

    $this->withHeader('X-Tenant-Id', $this->tenant->id)
        ->postJson('/api/v1/insights/ai/ask', ['question' => "How\n\nmany\tstudents?\x00\x1F"])
        ->assertStatus(200);

    SchoolAssistant::assertPrompted(function (AgentPrompt $prompt): bool {
        return str_contains($prompt->prompt, 'How many students?')
            && ! str_contains($prompt->prompt, "\x00")
            && ! str_contains($prompt->prompt, "\t");
    });
});

it('rate-limits the broadcasting auth endpoint', function (): void {
    $recorder = new RecordingBroadcaster;

    foreach (RealtimeChannels::definitions() as $name => $callback) {
        $recorder->channel($name, $callback);
    }

    Broadcast::extend('recording', fn (): RecordingBroadcaster => $recorder);
    Broadcast::setDefaultDriver('recording');
    Broadcast::forgetDrivers();
    app()->forgetInstance(BroadcasterContract::class);

    for ($i = 0; $i < 60; $i++) {
        $this->withHeader('X-Tenant-Id', $this->tenant->id)
            ->postJson('/broadcasting/auth', [
                'channel_name' => "private-users.{$this->principal->id}",
                'socket_id' => '123456.789012',
            ])
            ->assertStatus(200);
    }

    $this->withHeader('X-Tenant-Id', $this->tenant->id)
        ->postJson('/broadcasting/auth', [
            'channel_name' => "private-users.{$this->principal->id}",
            'socket_id' => '123456.789012',
        ])
        ->assertStatus(429);

    Broadcast::setDefaultDriver((string) config('broadcasting.default'));
    Broadcast::forgetDrivers();
    app()->forgetInstance(BroadcasterContract::class);
});
