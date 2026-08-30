<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Resolves the active tenant for the request and binds it to TenantContext.
 *
 * Resolution order:
 *   1. `X-Tenant-Id` header — must match one of the user's memberships.
 *   2. User's `active_tenant_id` column (last-selected tenant).
 *   3. First membership (deterministic by joined_at ASC).
 *
 * Fails closed with 403 if the user has no memberships, or with 409 if
 * the requested tenant is not a valid membership.
 */
final class ResolveTenant
{
    public function __construct(private readonly TenantContext $context) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user === null) {
            throw new HttpException(401, 'Unauthenticated.');
        }

        $membershipIds = $user->memberships()->pluck('tenant_id')->all();
        if ($membershipIds === []) {
            throw new HttpException(403, 'User has no tenant memberships.');
        }

        $requested = $request->header('X-Tenant-Id');
        $active = $user->active_tenant_id;

        $tenantId = match (true) {
            is_string($requested) && in_array($requested, $membershipIds, true) => $requested,
            is_string($requested) => throw new HttpException(409, 'Not a member of the requested tenant.'),
            is_string($active) && in_array($active, $membershipIds, true) => $active,
            default => $membershipIds[0],
        };

        $this->context->set($tenantId);

        return $next($request);
    }
}
