<?php

declare(strict_types=1);

namespace App\Support;

use Closure;
use Illuminate\Support\Facades\Context;

/**
 * Request-lifetime holder for the active tenant, backed by Laravel's
 * request-scoped Context store (see docs/13.x/context and P0-9).
 *
 * Delegating to Illuminate Context gives us two things the old static
 * property could not:
 *   1. queued jobs dispatched during a request automatically carry the
 *      tenant (plus actor_id/trace_id) — no manual runAs() wrapping needed
 *      in every job;
 *   2. every log line written during the request is correlated with the
 *      tenant via Context's log metadata.
 *
 * Keep using this class everywhere (tenant scope, policies, middleware) —
 * call sites never touch the facade directly, so swapping storage later
 * stays a one-file change.
 */
final class TenantContext
{
    private const KEY = 'tenant_id';

    public function set(string $tenantId): void
    {
        Context::add(self::KEY, $tenantId);
    }

    public function id(): ?string
    {
        $value = Context::get(self::KEY);

        return is_string($value) ? $value : null;
    }

    public function has(): bool
    {
        return Context::has(self::KEY);
    }

    public function clear(): void
    {
        Context::flush();
    }

    /**
     * Run a callback with a specific tenant bound, then restore.
     * Useful for queued jobs and cross-tenant admin operations.
     *
     * @template T
     *
     * @param  callable():T  $callback
     * @return T
     */
    public function runAs(string $tenantId, callable $callback): mixed
    {
        return Context::scope(Closure::fromCallable($callback), [self::KEY => $tenantId]);
    }
}
