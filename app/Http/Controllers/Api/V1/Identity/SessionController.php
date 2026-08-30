<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Identity;

use App\Enums\UserStatus;
use App\Http\Controllers\Api\V1\CapabilityController;
use App\Http\Requests\Api\V1\Identity\LoginRequest;
use App\Http\Requests\Api\V1\Identity\RegisterRequest;
use App\Http\Requests\Api\V1\Identity\SwitchTenantRequest;
use App\Http\Resources\Api\V1\Identity\SessionResource;
use App\Http\Resources\Api\V1\Identity\UserResource;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Session lifecycle: register, login (issues Sanctum token), logout, me,
 * switch tenant.
 *
 * This is the ONLY auth surface in SchoolOS. The legacy
 * App\Http\Controllers\Api\V1\AuthController is superseded: its
 * login/logout/me/register live here, and its password-reset and email
 * verification actions live in Identity\AccountController.
 *
 * Note: `register`, `login` and `acceptInvitation` are the only
 * unauthenticated endpoints in the Identity capability. They opt out of the
 * capability group middleware in routes/api/v1/identity.php.
 */
final class SessionController extends CapabilityController
{
    public function register(RegisterRequest $request): JsonResponse
    {
        $data = $request->validated();

        $user = User::query()->create([
            'name' => $data['full_name'],
            'email' => mb_strtolower($data['email']),
            'password' => Hash::make($data['password']),
            'status' => UserStatus::Active,
            'last_active_at' => now(),
        ]);

        $user->sendEmailVerificationNotification();

        $token = $user->createToken($data['device_name'] ?? 'web')->plainTextToken;

        return response()->json([
            'data' => [
                'token' => $token,
                'token_type' => 'Bearer',
                'user' => (new UserResource($user->load('memberships')))->resolve($request),
                'active_tenant_id' => null,
            ],
            'issued_at' => now()->toIso8601String(),
        ], 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $data = $request->validated();
        $user = User::query()->where('email', mb_strtolower($data['email']))->first();

        if ($user === null || ! Hash::check($data['password'], $user->password)) {
            throw new HttpException(422, 'Invalid credentials.');
        }
        if (! $user->status->canAuthenticate()) {
            throw new HttpException(403, 'Account is not active.');
        }

        $user->forceFill(['last_active_at' => now()])->save();

        $device = $data['device_name'] ?? 'web';
        $token = $user->createToken($device)->plainTextToken;

        return response()->json([
            'data' => [
                'token' => $token,
                'token_type' => 'Bearer',
                'user' => (new UserResource($user->load('memberships')))->resolve($request),
                'active_tenant_id' => $user->active_tenant_id,
            ],
            'issued_at' => now()->toIso8601String(),
        ], 201);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()?->currentAccessToken()?->delete();

        return $this->respondNoContent();
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->load('memberships');

        return $this->respond(new SessionResource([
            'user' => $user,
            'active_tenant_id' => app(TenantContext::class)->id() ?? $user->active_tenant_id,
            'issued_at' => now(),
        ]));
    }

    public function switchTenant(SwitchTenantRequest $request): JsonResponse
    {
        $user = $request->user();
        $tenantId = $request->validated('tenant_id');

        $isMember = $user->memberships()->where('tenants.id', $tenantId)->exists();
        if (! $isMember) {
            throw new HttpException(409, 'Not a member of the requested tenant.');
        }

        $user->update(['active_tenant_id' => $tenantId]);
        app(TenantContext::class)->set($tenantId);
        $user->load('memberships');

        return $this->respond(new SessionResource([
            'user' => $user,
            'active_tenant_id' => $tenantId,
            'issued_at' => now(),
        ]));
    }
}
