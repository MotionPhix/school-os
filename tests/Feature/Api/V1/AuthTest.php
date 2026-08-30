<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('Registration', function (): void {
    it('registers a new user successfully', function (): void {
        $response = $this->postJson('/api/v1/identity/registration', [
            'full_name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'data' => [
                    'token',
                    'token_type',
                    'user' => ['id', 'full_name', 'email'],
                    'active_tenant_id',
                ],
            ])
            ->assertJsonPath('data.user.email', 'test@example.com');

        $this->assertDatabaseHas('users', [
            'email' => 'test@example.com',
        ]);
    });

    it('fails registration with invalid data', function (): void {
        $response = $this->postJson('/api/v1/identity/registration', [
            'full_name' => '',
            'email' => 'invalid-email',
            'password' => 'short',
        ]);

        $response->assertStatus(422);
    });

    it('fails registration with duplicate email', function (): void {
        User::factory()->create(['email' => 'existing@example.com']);

        $response = $this->postJson('/api/v1/identity/registration', [
            'full_name' => 'Test User',
            'email' => 'existing@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(422);
    });
});

describe('Login', function (): void {
    it('logs in with valid credentials', function (): void {
        $user = User::factory()->create([
            'password' => bcrypt('password123'),
        ]);

        $response = $this->postJson('/api/v1/identity/session', [
            'email' => $user->email,
            'password' => 'password123',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'data' => [
                    'token',
                    'token_type',
                    'user' => ['id', 'full_name', 'email'],
                ],
            ]);
    });

    it('fails login with invalid credentials', function (): void {
        $user = User::factory()->create([
            'password' => bcrypt('password123'),
        ]);

        $response = $this->postJson('/api/v1/identity/session', [
            'email' => $user->email,
            'password' => 'wrongpassword',
        ]);

        $response->assertStatus(422);
    });

    it('fails login with non-existent user', function (): void {
        $response = $this->postJson('/api/v1/identity/session', [
            'email' => 'nonexistent@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(422);
    });
});

describe('Logout', function (): void {
    it('logs out authenticated user', function (): void {
        $user = User::factory()->create();
        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->deleteJson('/api/v1/identity/session');

        $response->assertStatus(204);
    });

    it('fails logout without authentication', function (): void {
        $response = $this->deleteJson('/api/v1/identity/session');

        $response->assertStatus(401);
    });
});

describe('Me', function (): void {
    it('returns authenticated user data', function (): void {
        $user = User::factory()->create();
        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/identity/session');

        $response->assertStatus(200)
            ->assertJsonPath('data.user.id', $user->id);
    });

    it('fails without authentication', function (): void {
        $response = $this->getJson('/api/v1/identity/session');

        $response->assertStatus(401);
    });
});
