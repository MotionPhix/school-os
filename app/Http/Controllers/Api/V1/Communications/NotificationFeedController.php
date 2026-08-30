<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Communications;

use App\Domains\Communications\Services\NotificationFeedReader;
use App\Http\Controllers\Api\V1\CapabilityController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Workspace notification feed — derived, permission-filtered, polled by the
 * topbar bell. No dedicated permission key: each source inside the reader is
 * gated by the key of the capability it points at.
 */
final class NotificationFeedController extends CapabilityController
{
    public function __invoke(Request $request, NotificationFeedReader $reader): JsonResponse
    {
        $user = $request->user();
        abort_unless($user !== null, 401);

        return response()->json(['data' => $reader->read($user)]);
    }
}
