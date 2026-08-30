<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\URL;

uses(RefreshDatabase::class);

describe('Email Verification', function (): void {
    it('verifies email successfully with valid link', function (): void {
        // Fake only Verified: a full Event::fake() swaps the dispatcher and
        // the model's UUID `creating` hook stops firing.
        Event::fake([Verified::class]);

        $user = User::factory()->create(['email_verified_at' => null]);
        $token = $user->createToken('test-token')->plainTextToken;

        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1($user->email)]
        );

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson($verificationUrl);

        $response->assertStatus(204);

        $this->assertNotNull($user->fresh()->email_verified_at);
        Event::assertDispatched(Verified::class);
    });

    it('returns 204 if email is already verified', function (): void {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);
        $token = $user->createToken('test-token')->plainTextToken;

        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1($user->email)]
        );

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson($verificationUrl);

        $response->assertStatus(204);
    });

    it('fails verification without authentication', function (): void {
        $user = User::factory()->create(['email_verified_at' => null]);

        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1($user->email)]
        );

        $response = $this->postJson($verificationUrl);

        $response->assertStatus(401);
    });

    it('fails verification with invalid signature', function (): void {
        $user = User::factory()->create(['email_verified_at' => null]);
        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson("/api/v1/identity/email/verify/{$user->id}/invalid-hash");

        $response->assertStatus(403);
    });
});

describe('Resend Verification Email', function (): void {
    it('resends verification email for an unverified user', function (): void {
        $user = User::factory()->create(['email_verified_at' => null]);

        $response = $this->postJson('/api/v1/identity/email/resend', [
            'email' => $user->email,
        ]);

        $response->assertStatus(204);
    });

    it('does not leak account existence', function (): void {
        $response = $this->postJson('/api/v1/identity/email/resend', [
            'email' => 'nonexistent@example.com',
        ]);

        $response->assertStatus(204);
    });
});
