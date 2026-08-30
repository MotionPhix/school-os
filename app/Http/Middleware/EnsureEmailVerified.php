<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Str;

final class EnsureEmailVerified
{
    /**
     * Ensure the user's email is verified before allowing access.
     */
    public function handle(Request $request, Closure $next): mixed
    {
        $user = $request->user();

        // Public routes (no authenticated user) pass through untouched —
        // `auth:sanctum` already rejects unauthenticated access to the
        // protected ones.
        if (! $user) {
            return $next($request);
        }

        if (! $user->hasVerifiedEmail()) {
            return response()->json([
                'success' => false,
                'message' => 'Your email address is not verified. Please verify your email to continue.',
                'trace_id' => Context::get('trace_id') ?? Str::uuid()->toString(),
            ], 403);
        }

        return $next($request);
    }
}
