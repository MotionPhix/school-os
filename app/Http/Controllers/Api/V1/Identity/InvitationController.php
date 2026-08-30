<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Identity;

use App\Domains\Identity\Services\InviteUser;
use App\Domains\Identity\Services\RevokeInvitation;
use App\Http\Controllers\Api\V1\CapabilityController;
use App\Http\Requests\Api\V1\Identity\InviteUserRequest;
use App\Http\Resources\Api\V1\Identity\InvitationResource;
use App\Models\Invitation;
use App\Support\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

final class InvitationController extends CapabilityController
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Invitation::class);

        $paginator = Invitation::query()
            ->where('tenant_id', app(TenantContext::class)->id())
            ->orderByDesc('created_at')
            ->paginate((int) $request->integer('per_page', 25));

        return $this->respondPaginated(
            InvitationResource::collection($paginator),
            $paginator,
        );
    }

    public function store(InviteUserRequest $request, InviteUser $service): JsonResponse
    {
        $result = $service->handle(
            app(TenantContext::class)->id(),
            $request->validated('email'),
            $request->validated('role_ids'),
            $request->user(),
        );

        // Raw token is returned once so the caller can hand it to a mailer.
        // TODO(Slice 1 follow-up): move delivery into a Notification once the
        // Communications capability lands with a proper mail transport.
        return response()->json([
            'data' => (new InvitationResource($result['invitation']))->resolve($request),
            'raw_token' => $result['raw_token'],
            'issued_at' => now()->toIso8601String(),
        ], 201);
    }

    public function resend(Request $request, Invitation $invitation, InviteUser $service): JsonResponse
    {
        $this->authorize('resend', $invitation);

        // Simplest strategy: revoke the old one and issue a fresh invite
        // with the same role bundle. Ensures token rotation.
        $result = $service->handle(
            $invitation->tenant_id,
            $invitation->email,
            $invitation->role_ids,
            $request->user(),
        );

        return response()->json([
            'data' => (new InvitationResource($result['invitation']))->resolve($request),
            'raw_token' => $result['raw_token'],
            'issued_at' => now()->toIso8601String(),
        ], 201);
    }

    public function revoke(Request $request, Invitation $invitation, RevokeInvitation $service): JsonResponse
    {
        $this->authorize('revoke', $invitation);
        $service->handle($invitation, $request->user()->id);

        return $this->respond(new InvitationResource($invitation));
    }

    /**
     * Bulk resend/revoke. Individual failures are skipped, not fatal, so a
     * partially-valid selection still succeeds for the rows that qualify.
     */
    public function bulk(Request $request, InviteUser $inviter, RevokeInvitation $revoker): JsonResponse
    {
        $validated = $request->validate([
            'invitation_ids' => ['required', 'array', 'min:1'],
            'invitation_ids.*' => ['uuid'],
            'action' => ['required', 'in:resend,revoke'],
        ]);

        $invitations = Invitation::query()
            ->whereIn('id', $validated['invitation_ids'])
            ->where('tenant_id', app(TenantContext::class)->id())
            ->get();

        $updated = 0;
        $skipped = 0;

        foreach ($invitations as $invitation) {
            try {
                if ($validated['action'] === 'resend') {
                    $this->authorize('resend', $invitation);
                    $inviter->handle(
                        $invitation->tenant_id,
                        $invitation->email,
                        $invitation->role_ids,
                        $request->user(),
                    );
                } else {
                    $this->authorize('revoke', $invitation);
                    $revoker->handle($invitation, $request->user()->id);
                }
                $updated++;
            } catch (Throwable) {
                $skipped++;
            }
        }

        return response()->json([
            'data' => ['updated' => $updated, 'skipped' => $skipped],
        ]);
    }
}
