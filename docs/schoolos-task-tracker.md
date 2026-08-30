# SchoolOS — Task Tracker & Status

Living status document derived from [schoolos-audit-report.md](schoolos-audit-report.md). Updated: 2026-08-30.
Legend: ✅ done (verified) · 🟡 partial · ⏳ pending · ❗ decision needed

---

## 1. Phase 0 — Production Foundation

| ID | Item | Status | Evidence | Next action |
|---|---|---|---|---|
| P0-1 | **Authorization enforcement, all 9 writable modules** (finance, identity, institution, people, academics, admissions, assessments, attendance, communications; insights read-only verified) | ✅ | Suite 124/124; dedicated keys enforced (`publish`, `issue`, `void`, `refund`, `send`, `archive`, `start`, `cancel`, `summary.read`, `reports.read`…) | — |
| P0-2 | **TenantContext lifecycle + isolation matrix** (early-binding fix, stale-context elimination, journal fail-closed) | ✅ | `ResolveTenantContext` global middleware; 5 isolation/lifecycle tests | — |
| P0-3 | **Idempotency** — `Idempotency-Key` support on state-changing endpoints (payments, void, refund, bulk) | ✅ | `EnsureIdempotency` middleware + `idempotency_keys` table; 7 tests (replay, per-tenant scope, in-flight 409, cached 422, read/header no-op) | Next consumer: finance double-submit E2E (with P0-4 row-lock) |
| P0-4 | **Finance correctness hotfixes** — `lockForUpdate` on `RecordPayment` (double-payment race), integer math cleanup (2 float sites), payment-allocation order | ✅ | Invoice row-lock; integer gateway-fee `intdiv`; dead discount-allocation block removed; 5 correctness tests (settle, partial, overpay/double-pay, gateway fee balanced, discounted issue balanced) | Next: gateway E2E + reconciliation job |
| P0-5 | **Verified-email enforcement** — apply `EnsureEmailVerified` to tenant routes (or policy: unverified read-only) | ✅ | Middleware fixed (unauthenticated passes through); `verified` in the route group; verification route exempt; 5 tests (block unverified 403, allow verified, verify-then-pass, public routes untouched) | Frontend: render the "verify your email" state on 403 |
| P0-6 | **Error/correlation contract** — request-id + tenant in every error/log payload; business-oriented errors | ✅ | Standardized `{success, message, errors?, trace_id}` envelope via exception handler; `X-Trace-Id` header on every response; middleware errors aligned; 6 tests (422/404/403/401 envelopes, header on success, idempotency 409) | Frontend: surface trace_id in support flows |
| P0-7 | **Test infrastructure & CI** — PHPStan baseline (repo has 1 000+ pre-existing errors), Pest helpers cleanup, CI pipeline (lint+types+tests on push) | ✅ | `composer test` fully green: PHPStan baseline (1 342 errors pinned), Pint clean repo-wide + `pint.json`, CI workflow (`.github/workflows/tests.yml`) runs the gate on push/PR; two bugs found & fixed (see §16) | Keep the baseline shrinking; restore Rector to the gate once its parser bug is fixed upstream |
| P0-8 | **Soft lifecycle** — soft deletes / archive-retire-void over hard deletes (students, guardians, campuses, years, fee drafts) | ✅ | `SoftDeletes` on 15 core models; `deleted_at` in natural-key unique indexes; FormRequest unique rules ignore archived rows; attendance summary excludes archived students; 4 tests (archive+restore, code reuse, finance history intact, summary exclusion) | Restore/purge endpoints (admin), per-module archive UX |
| P0-9 | **Laravel Context adoption** (queue propagation + log correlation) | ✅ | `TenantContext` now delegates to Illuminate Context; `trace_id` + `actor_id` in every request/job; lifecycle tests green | Verify queue propagation with a real queued flow (Phase 5 notifications) |

## 2. Resolved defect register (found via audits + tests)

| # | Defect | Fixed in | Proof |
|---|---|---|---|
| F1 | `RolesAssigned`/`UserSuspended`/`UserReactivated` fatal: readonly `$tenantId` redeclared vs parent | P0-1 slice 1 | tests |
| F2 | `remind` always 403 for issued invoices (draft-only ability) | P0-1 slice 1 | new `remind` ability |
| F3 | Bulk invoice issue/void bypassed dedicated permission keys | P0-1 slice 1 | per-row authz |
| F4 | Cross-tenant IDOR on `User` (show/assignRoles/suspend) | P0-1 slice 1 | membership checks |
| F5 | Cross-tenant IDOR on `Role` (view/update/delete, system-role bypass) | P0-1 slice 1 | tenant-scoped policy |
| F6 | Fee-store 500s: NOT NULL `academic_year_label`/`currency` optional | P0-1 slice 1 | required + config default |
| F7 | Invite gate mismatch: `users.write` instead of `invitations.write` | P0-1 slice 1 | `InvitationPolicy::create` |
| F8 | Dead auth surface: routes referenced deleted `AuthController` (all 500) | P0-1 slice 1 | removed; identity surface + notification route aliases |
| F9 | Gradebook could grade non-enrolled students | P0-1 slice 3 | roster check 422 |
| F10 | Duplicate enrollment silently no-opped | P0-1 slice 3 | 422 + event |
| F11 | `respondOffer` accepted spoofable `actor_name` | P0-1 slice 3 | actor = authenticated user |
| F12 | Subject/section duplicates → raw 500 | P0-1 slice 3 | friendly 422 pre-checks |
| F13 | Attendance summary: wrong key + unscoped joins | P0-1 slice 4 | `summary.read` + join tenant filters |
| F14 | Report cards: hand-rolled permission resolution (drift risk) | P0-1 slice 4 | `ExamPolicy::viewReports` |
| F15 | Term delete lacked in-progress guard at policy layer | P0-1 slice 2 | `TermPolicy::delete` |
| F16 | `UserResource` 500 on null-status users | P0-2 | defensive `status?->value` + factory realism |
| F17 | `PostJournalEntry` could write wrong-tenant ledger rows in queued contexts | P0-2 | fail-closed 422 + explicit `tenant_id` |
| F18 | **Cross-tenant binding at request start** — model binding runs before route middleware; stale/null context scoped (or failed to scope) wrong tenant | P0-2 | global `ResolveTenantContext` pre-resolver; lifecycle tests |

## 3. Module status (10 capabilities)

| Module | Authz (P0-1) | Tests | Spec | Known gaps (spec §flags) |
|---|---|---|---|---|
| Identity | ✅ | 20 | ✅ | MFA/SSO; verified-email (P0-5); invite expiry tests |
| Finance | ✅ | 7 | ✅ | gateway; idempotency (P0-3); row-lock (P0-4) |
| Institution | ✅ | 16 | ✅ | transition matrix tests; year/term state tests |
| People | ✅ | 12 | ✅ | soft lifecycle (P0-8); portal flow tests |
| Academics | ✅ | 16 | ✅ | timetable collision matrix tests |
| Admissions | ✅ | 5 | ✅ | stage matrix tests; conversion flow tests |
| Assessments | ✅ | 7 | ✅ | state machine + publish-completeness tests |
| Attendance | ✅ | 8 | ✅ | lifecycle + risk-band tests |
| Communications | ✅ | 11 | ✅ | lifecycle tests; feed filtering tests |
| Insights | ✅ (read-only) | 0 | ✅ | reader contract tests |

## 4. Outstanding risks / housekeeping

- **PHPStan baseline**: 1 000+ pre-existing errors (mostly `BelongsToTenant` generics) — needs a baseline + incremental fix (P0-7). Zero new errors introduced so far.
- **Soft deletes absent everywhere** (P0-8).
- **Git**: the entire SchoolOS layer is uncommitted — commit after each slice; `.BAK` auth controller file to delete. ✅ committed per slice (Phase 1 policies + housekeeping 26eda58).
- **Docs**: move `capabilities/` + this tracker + evaluation into the repo (`E:\\Herd\\schoolos\\docs\\`) so they version with code — ✅ done (26eda58); keep this tracker updated as the living status doc.
- **MySQL**: switched from pgsql — run `migrate:fresh --seed` before live-mode verification.
- **Live-mode E2E**: UI runs on mocks; flip `VITE_API_MODE=live` against the ngrok tunnel once Phase 0 core lands.
- **ADRs to record**: (1) headless API + TanStack SPA over handbook's Inertia reference (approved); (2) custom RBAC over spatie/laravel-permission; (3) Laravel Context adoption (P0-9).
- **Cross-cutting gaps (all specs)**: notifications (Phase 5), realtime (Phase 7), AI, discovery/search, observability — flagged per spec with phase tags; none started.

## 5. Phase 1+ — next plausible actions

**Phase 1 (Notifications) — ✅ DONE (2026-08-30):** event-driven notification infrastructure (tables, `SchoolNotification` base, `TenantDatabaseChannel`, policy-driven dispatcher via `config/notifications.php`, personal inbox endpoints) + live policies (announcement→members, invoice→finance readers, exam→teacher+guardians) + preference opt-outs. Recipient resolvers (`ResolvesNotificationRecipients`) + tenant/permission/teacher/guardian resolvers.

**Phase 1 policies — ✅ ALL DONE (2026-08-30):** absence alerts (guardians of absent students, a85b9fb) · payment receipts (guardians of the invoice student, 2d7522a) · broadcast delivery reports (creator, 11fd3c2). Suite 168 green.

**Housekeeping — ✅ DONE (2026-08-30, 26eda58):** audit report + tracker + evaluation + 10 capability specs moved into `E:\Herd\schoolos\docs\` (now versioned with code) · dead `AuthController.php.BAK` deleted · **admin trash restore** endpoint `POST /api/v1/admin/trash/{resource}/{id}/restore` (15-resource whitelist, tenant-scoped, archived-only; `platform.trash.restore` permission, principal role) — suite 173 green.

Next:
1. **Live-mode E2E — ✅ DONE (2026-08-30)**: full loop proven against MySQL through the ngrok tunnel — register → signed-URL email verify → login → Day-0 onboarding → campus → student → announcement send → **queued notification delivered with correct tenant context** → inbox API (`unread=1`) → error contract (`404 + trace_id`). MySQL-compat fixes shipped: index identifier lengths, FK-backed unique drops, proxy trust (signed URLs behind ngrok/nginx/Cloudflare).
2. **Recipient resolvers + results-published policy — ✅ DONE (2026-08-30)**: `ResolvesNotificationRecipients` interface; tenant/permission/teacher/guardian resolvers; `ExamPublished` now notifies the section teacher + guardians of enrolled students (portal users); dispatcher accepts strategies, resolver classes, or arrays. 2 new tests (159 total). Committed.
3. **More policies — ✅ ALL DONE (2026-08-30)**: absence alerts → guardians (a85b9fb); payment receipts → invoice-student guardians (2d7522a); broadcast delivery stats → creator (11fd3c2). 9 new tests.
4. **Realtime — ✅ ALL DONE (2026-08-30)**: slices 1-3 — Reverb transport; `/broadcasting/auth` (sanctum+force.json+resolve.tenant); channels from `App\Support\RealtimeChannels` (private `users.{id}`/`tenant.{id}` + presence `sessions.{id}`/`exams.{id}`/`threads.{id}`); **feed badge push**, **broadcast progress ticks**, **live register counts**, **thread reply push**, **timetable change push** (`TimetableChanged` → section teacher), **tenant-wide fan-out** (`AnnouncementPublished` on `private-tenant.{id}`). Recording-broadcaster tests (15) — suite 188 green.
5. **Discovery — ✅ DONE (2026-08-30, 41ac252)**: `laravel/scout` (database driver, swap later via `SCOUT_DRIVER`); `Searchable` on Student/User/Invoice/Announcement; `GET /api/v1/search?q=` typed results, per-resource permission gating, tenant-scoped (users filtered by membership), 8/type cap. 6 tests — suite 194 green.
6. **AI context builders — ✅ DONE (2026-08-30)**: School Assistant via **opencode Zen** (`laravel/ai` 0.7.2→0.11 + framework 13.2→13.29 for the `openai-compatible` driver; 0.7.2 only spoke the Responses API). `config/ai.php` 'zen' provider (key from env, model `big-pickle`); `SchoolAssistant` agent (answers ONLY from the tenant snapshot); `AiContextBuilder` (headline KPIs, cohorts, trend, announcements); `POST /api/v1/insights/ai/ask` (permission `insights.ai.read` — principal+bursar; 503 while disabled). Live-validated against the Zen gateway. 4 tests — suite 198 green.
7. **Observability — ✅ DONE (2026-08-30)**: `GET /api/v1/system/health` (DB/cache/queue/AI-gateway probes; 503 only for criticals; LB-friendly); `schoolos:check-broadcast-deliveries` hourly → in-app `BroadcastDeliveryAlert` (kind `system`) to platform operators (`platform.observability.alert`, principal) when a completed broadcast's failure count/rate crosses the threshold; deduped per broadcast (`delivery_alerted_at`), tenant-scoped. Pulse/Horizon deferred — both need a web dashboard/Redis this API-first app doesn't host. 6 tests — suite 204 green.

## 6. Hardening phase (one track at a time)

- **Track 1 — Security & abuse ✅ (e40eed7)**: AI ask strict limiter (15/min/user, env `INSIGHTS_AI_RATE_LIMIT`) + question sanitization; broadcasting auth limiter (60/min/user); audit confirmed login/register 5/min/IP, password reset 6/min, idempotency on all writes. 4 tests — suite 208 green.
- **Track 2 — PHPStan baseline reduction** ⏳ next: shrink the ~1350 pinned errors module-by-module (BelongsToTenant generics, magic props).
- **Track 3 — Finance**: money math edge cases, partial payments, discount rounding, row-lock races.
- **Track 4 — Attendance + assessments**: risk-band boundaries, marksheet completeness, rollover.
- **Track 5 — Communications resilience**: delivery retry/backoff, failure taxonomy, dead-letter handling.
8. Housekeeping — ✅ DONE (2026-08-30, 26eda58): docs into repo, `.BAK` deleted, admin trash restore endpoints shipped

---

*Companion docs: [schoolos-audit-report.md](schoolos-audit-report.md) · [laravel-context-evaluation.md](laravel-context-evaluation.md) · `capabilities/` (10 module specs)*
