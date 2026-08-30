<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Communications;

use App\Domains\Communications\Services\CommunicationsOverviewReader;
use App\Domains\Communications\Support\CommunicationsPermission;
use App\Http\Controllers\Api\V1\CapabilityController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class CommunicationsOverviewController extends CapabilityController
{
    public function __invoke(
        Request $request,
        CommunicationsOverviewReader $reader,
        CommunicationsPermission $perm,
    ): JsonResponse {
        $user = $request->user();
        abort_unless($user !== null && $perm->has($user, 'communications.overview.read'), 403);

        return response()->json(['data' => $reader->read()]);
    }
}
