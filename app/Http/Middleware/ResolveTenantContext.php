<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Global pre-resolver that runs before route matching / model binding.
 *
 * The tenant binding is intentionally request-scoped, and this framework
 * resolves
 * implicit route model bindings *before* route-level middleware
 * (including `resolve.tenant`), so a binding could otherwise observe a
 * stale context from a previous request — scoping (or failing to scope)
 * a cross-tenant row incorrectly. This middleware:
 *
 *   1. clears the previous request's binding, and
 *   2. pre-warms the context from the current request's Sanctum
 *      authentication + membership resolution
 *      (X-Tenant-Id > active_tenant_id > first membership).
 *
 * `resolve.tenant` remains the authoritative gate (401/403) and re-sets
 * the same value. Public routes without a token keep a null context, so
 * events dispatched outside a tenant can never be attributed to a stale
 * one.
 */
final class ResolveTenantContext
{
    public function __construct(private readonly TenantContext $context) {}

    public function handle(Request $request, Closure $next): Response
    {
        $this->context->clear();

        // Correlation id for this request/job — appears on every log line,
        // rides into queued jobs alongside the tenant, and is echoed back
        // as the X-Trace-Id header (P0-6 error/correlation contract).
        $traceId = Str::uuid()->toString();
        Context::add('trace_id', $traceId);

        $user = Auth::guard('sanctum')->user();
        if ($user instanceof User) {
            Context::add('actor_id', $user->id);

            $tenantId = $this->resolveTenantId($user, $request);
            if ($tenantId !== null) {
                $this->context->set($tenantId);
            }
        }

        /** @var Response $response */
        $response = $next($request);

        $response->headers->set('X-Trace-Id', $traceId);

        return $response;
    }

    private function resolveTenantId(User $user, Request $request): ?string
    {
        /** @var list<string> $membershipIds */
        $membershipIds = $user->memberships()->pluck('tenant_id')->all();
        if ($membershipIds === []) {
            return null;
        }

        $requested = $request->header('X-Tenant-Id');
        $active = $user->active_tenant_id;

        return match (true) {
            is_string($requested) && in_array($requested, $membershipIds, true) => $requested,
            is_string($active) && in_array($active, $membershipIds, true) => $active,
            default => $membershipIds[0],
        };
    }
}
