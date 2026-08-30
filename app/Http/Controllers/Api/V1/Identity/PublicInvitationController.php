<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Identity;

use App\Domains\Identity\Services\AcceptInvitation;
use App\Http\Controllers\Api\V1\CapabilityController;
use App\Http\Requests\Api\V1\Identity\AcceptInvitationRequest;
use App\Http\Resources\Api\V1\Identity\UserResource;
use Illuminate\Http\JsonResponse;

/**
 * Public — no `auth:sanctum`, no `tenant`. Mounted outside the capability
 * group in routes/api/v1/identity.php.
 */
final class PublicInvitationController extends CapabilityController
{
    public function accept(AcceptInvitationRequest $request, AcceptInvitation $service): JsonResponse
    {
        $data = $request->validated();
        $user = $service->handle($data['token'], [
            'name' => $data['name'],
            'password' => $data['password'],
        ]);

        $token = $user->createToken('web')->plainTextToken;

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
}
