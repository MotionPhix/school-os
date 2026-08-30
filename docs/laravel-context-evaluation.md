# Laravel 13 `Context` — Evaluation for SchoolOS

Source: https://laravel.com/framework/docs/13.x/context (fetched 2026-08-30). Status: **research + recommendation — decision pending.**

---

## 1. What Laravel's Context is

A request-scoped data store (`Illuminate\Support\Facades\Context`): `add/get/has/forget/push/pop/scope` plus hidden variants. Three properties matter for us:

1. **Log correlation** — every value added to Context is automatically appended as metadata to **all log entries written during the request/job** (e.g. `{"url":..., "trace_id":...}` next to every `Log::info`).
2. **Queue propagation** — when a job is dispatched, Context data is **dehydrated** into the job payload and **hydrated** back while the job runs. `dehydrating`/`hydrated` events allow capture/restore (docs example: locale).
3. **Scoped context** — `Context::scope(closure, data, hidden)` temporarily overrides and restores.

## 2. Where it fits SchoolOS (and where it doesn't)

| Concern | Current state | Laravel Context |
|---|---|---|
| Tenant id during a request | Custom static `TenantContext` + global `ResolveTenantContext` pre-resolver (P0-2) | Same pattern — pre-resolver would `Context::add('tenant_id', ...)` instead |
| **Queued jobs losing tenant** (the P0-2 residual gap: `PostJournalEntry` in a queued context fails closed) | Manual `TenantContext::runAs()` wrapping — error-prone, must be remembered | **Automatic**: tenant_id rides along with every job, hydrated on execution |
| **Log correlation** | `log.api` middleware logs requests, but tenant/trace are not in every log line | `tenant_id` + `trace_id` appended to **every** log entry automatically |
| Octane safety | Static state must be manually cleared (P0-2 fix) | Framework-sanctioned request-scoped store; flushed per request |
| Early model binding (before route middleware) | Needs the global pre-resolver regardless (P0-2) | Context is populated by the same pre-resolver — no change |
| Auth/permission checks (policies, ~100 call sites) | Read `TenantContext::id()` | If we migrate, call sites become `Context::get('tenant_id')` — **or** keep the wrapper (below) |

**It does NOT replace** the `ResolveTenantContext` pre-resolver (binding-time correctness) or `resolve.tenant` (authoritative 401/403) — it replaces *where the state lives*, not the resolution logic.

## 3. Recommendation: adopt, via a thin wrapper

**Adopt Laravel Context as the storage mechanism, but keep `TenantContext` as the typed API** so ~100 call sites (`TenantContext::id()`, policies, scope) don't change:

```php
// TenantContext becomes a delegating wrapper:
final class TenantContext
{
    public function id(): ?string            { return Context::get('tenant_id'); }
    public function set(string $id): void    { Context::add('tenant_id', $id); }
    public function clear(): void            { Context::flush(); }            // also drops trace_id etc.
    public function has(): bool              { return Context::has('tenant_id'); }
    public function runAs(string $id, callable $fn): mixed { return Context::scope($fn, data: ['tenant_id' => $id]); }
}
```

Plus, in `ResolveTenantContext`:
- add `Context::add('trace_id', Str::uuid())` at request start (free distributed tracing via logs),
- add `Context::add('actor_id', $user->id)` (hidden or visible) for job-side attribution.

**Benefits:** queued jobs keep tenant + actor automatically (closes the P0-2 residual gap permanently); every log line is tenant/trace-correlated (P0-6 becomes trivial); Octane-safe by construction; no call-site churn; fully covered by the existing 124-test suite.

**Cost/risk:** one file rewritten + one middleware tweak; `Context::flush()` semantics must be verified against the existing lifecycle tests (they assert context null/derived per request — the tests will prove it).

**Caveats:**
- Do NOT put non-serializable values in visible context (docs: objects ride into logs/jobs); only scalars (tenant_id, actor_id, trace_id).
- `dehydrating`/`hydrated` callbacks in `AppServiceProvider::boot` if we ever need to restore non-context state (e.g. locale) on the queue — not needed today.

## 4. Decision

**Tracked as P0-9** in [schoolos-task-tracker.md](schoolos-task-tracker.md). Recommended: **adopt** (small change, immediate queue-propagation + observability win). Pending your green-light; if declined, the current custom static (with P0-2 fixes) remains correct for requests, and P0-6 would need a manual trace_id/log-enrichment approach instead.
