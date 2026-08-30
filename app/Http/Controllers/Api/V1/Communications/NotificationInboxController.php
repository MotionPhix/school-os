<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Communications;

use App\Http\Controllers\Api\V1\CapabilityController;
use App\Models\Notification;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Personal notification inbox (event-driven stored notifications; the
 * derived workspace feed lives in NotificationFeedReader).
 */
final class NotificationInboxController extends CapabilityController
{
    public function index(Request $request, TenantContext $tenant): JsonResponse
    {
        $user = $request->user();
        if (! $user instanceof User || $tenant->id() === null) {
            throw new NotFoundHttpException;
        }

        $items = Notification::query()
            ->where('tenant_id', $tenant->id())
            ->where('notifiable_id', $user->id)
            ->orderByDesc('created_at')
            ->limit(50)
            ->get()
            ->map(fn (Notification $notification): array => [
                'id' => $notification->id,
                'type' => class_basename($notification->type),
                'data' => $notification->data,
                'read_at' => $notification->read_at?->toIso8601String(),
                'created_at' => $notification->created_at?->toIso8601String(),
            ]);

        return response()->json([
            'data' => $items,
            'meta' => [
                'unread_count' => Notification::query()
                    ->where('tenant_id', $tenant->id())
                    ->where('notifiable_id', $user->id)
                    ->whereNull('read_at')
                    ->count(),
            ],
        ]);
    }

    public function markRead(Notification $notification, Request $request, TenantContext $tenant): JsonResponse
    {
        $user = $request->user();
        if (! $user instanceof User
            || $tenant->id() === null
            || $notification->notifiable_id !== $user->id
            || $notification->tenant_id !== $tenant->id()) {
            throw new NotFoundHttpException;
        }

        $notification->update(['read_at' => now()]);

        return response()->json(null, 204);
    }
}
