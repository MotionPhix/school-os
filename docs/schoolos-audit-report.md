# SchoolOS — Backend Audit Report & Phase 0 Plan

**Audit date:** 2026-08-30
**Scope:** `E:\Herd\schoolos` (Laravel 13 headless API) — all 10 domains, platform foundation, handbook compliance
**Baseline:** [SchoolOS Enterprise Architecture Handbook.docx](../E:/Herd/schoolos-ui/SchoolOS%20Enterprise%20Architecture%20Handbook.docx) (v1.0, 40 chapters)
**Method:** Full handbook read → per-domain controller/service/policy/route audit (3 parallel read-only audits) → foundation verification (middleware, providers, migrations, audit pipeline, RBAC)

---

## 1. Executive summary

The API is **substantially implemented — no stubs, no placeholder logic** anywhere in the 10 domains. The domain layer is genuinely mature: ~60 controllers, ~120 Form Requests, ~90 domain services, ~90 domain events, ~35 policies, a wildcard audit listener that projects every business event into an append-only `audit_events` table, UUID primary keys everywhere, and a fail-closed tenant-resolution middleware backed by a global tenant scope.

**The systemic defect is enforcement, not implementation:**

1. **Authorization is almost entirely missing on write/verb endpoints.** Reads are gated; ~50+ writes (create/update/delete plus business verbs like `enroll`, `sendOffer`, `recordPayment`, `void`, `transfer`, `link`) run with **no `authorize()` call**. Policy classes exist for nearly all of these but are dead code. Any authenticated tenant member can currently create, modify, or delete any tenant data — including recording and refunding payments and voiding invoices.
2. **Three cross-tenant IDOR holes** on global (non-scoped) models: `User`, `Role`, `Tenant`.
3. **Finance has race and precision defects** (no row lock on payment recording, no idempotency keys, float rounding on integer-cent money).
4. **Zero domain tests.** Only 3 auth tests exist. Nothing verifies tenant isolation, RBAC, double-entry balancing, or the state machines that are actually well-built.
5. Handbook mandates not yet implemented: idempotency, soft lifecycle (no `SoftDeletes` anywhere), email-verification enforcement, correlation/error contract, notification architecture, realtime, event-store projections.

**Verdict:** the foundation and domain logic are production-*shaped* but not production-*proven*. Phase 0 (platform foundation: authorization enforcement, IDOR fixes, tenant-isolation tests, error contract, idempotency) is exactly the right next step, followed by per-module hardening passes.

---

## 2. Handbook compliance scorecard

| Handbook mandate | Status | Evidence / gap |
|---|---|---|
| UUID identity everywhere (ADR-005, Ch 19.7) | ✅ | Zero `bigIncrements` in 46 migrations; all PKs uuid; composite `(tenant_id, …)` uniques |
| Tenant schema & isolation (Law VI, Ch 23) | 🟡 | Schema ✅ (tenant_id FK + index on every tenant table; `TenantScope` global scope; fail-closed `resolve.tenant`). Enforcement tests ❌; IDOR on User/Role/Tenant ❌ |
| Thunk Verbs / business endpoints (Ch 17, 20) | 🟡 | Verbs genuinely used (`advance`, `sendOffer`, `recordPayment`, `submit`, `publish`…) but mixed with generic CRUD; some generic naming remains |
| Business Events (Ch 12, 18) | 🟡 | ~90 events dispatched; wildcard listener → `audit_events` (append-only audit trail). No event store / replay / projections / read models / broadcasting yet |
| Capability-based authorization (Ch 23) | ❌ | Policies exist (35) but ~50+ write endpoints never call them; platform-admin gates are dead code |
| Idempotent business actions (Ch 20.5) | ❌ | No `Idempotency-Key` support anywhere; payments generate fresh references per call |
| Lifecycle over deletion (Ch 28.7) | ❌ | No `SoftDeletes`; hard deletes incl. `AcademicYearController::destroy` cascading `terms()->delete()` |
| Email verification (Ch 24 stack) | 🟡 | `MustVerifyEmail` + signed verify link implemented; `verified` middleware registered but **never applied** — unverified accounts can log in and use the API |
| Business-oriented errors + correlation (Ch 20.7–20.8) | 🟡 | Standard `{success, message, data|errors}` envelope exists; no request/correlation/tenant IDs in responses; `withExceptions` empty |
| Notifications (Ch 35) | ❌ | Only `InvitationIssued` notification exists; no notification policies, audience resolution, channels, centre |
| Realtime (Ch 21, 33) | ❌ | No Echo/Pusher, no broadcasting events, no workspace subscriptions |
| Testing architecture (Ch 38) | ❌ | 3 auth tests only (Auth / EmailVerification / PasswordReset) |
| Observability (Ch 37) | 🟡 | `log.api` request logging + audit events; no Horizon/Pulse/Telescope, no metrics |
| Reference stack (Ch 24) | 🟡 | Laravel 13 ✅, Sanctum ✅, Spatie Data ✅. Divergences: **Inertia → headless REST API + TanStack SPA** (user decision, see §7), **Spatie Permission → custom RBAC**, **Spatie Media Library → custom `PersonMediaService`**, **Spatie Activitylog → custom wildcard audit listener** |

---

## 3. Foundation audit (Phase 0 scope)

### What is solid

- **Tenant resolution** — `app/Http/Middleware/ResolveTenant.php`: `X-Tenant-Id` → `active_tenant_id` → first membership; fails closed (403 no memberships / 409 invalid tenant). Correct design.
- **Tenant context** — `app/Support/TenantContext.php`: static request-lifetime holder with `runAs()` for jobs/console; consumed by `TenantScope` global scope.
- **Tenant schema** — every tenant-owned table has `tenant_id` FK + composite indexes/uniques; `BelongsToTenant` concern auto-fills and scopes on create/read.
- **Audit/event pipeline** — `app/Listeners/RecordBusinessEvent.php` is a wildcard listener registered in `AuditServiceProvider`; every `BusinessEvent` is projected to `audit_events` with tenant, actor, subject, summary, metadata, occurred_at. Failures swallowed so audit never breaks the request. This is an elegant, low-cost institutional-memory layer.
- **RBAC building blocks** — `PermissionCatalog`, `AbstractCapabilityPolicy`, `AbstractIdentityPolicy`, roles table with `is_system` protection intent, per-tenant role cloning (`CloneSystemRoles`).
- **Quality tooling** — Pest, PHPStan (max), Rector, Pint wired into `composer test`; Scramble docs; versioned routes.

### Critical findings

| # | Finding | Location |
|---|---|---|
| F1 | **Write endpoints are unguarded.** Reads gate; writes don't. Any authenticated member can CRUD all tenant data. | ~50+ controller methods across all 10 domains (full list in §4) |
| F2 | **`Tenant::update` runs no authorization** — `TenantPolicy::update` (platform-admin) exists but is dead code → any member can rename any tenant. | `app/Http/Controllers/Api/V1/Identity/TenantController.php:52` |
| F3 | **`User::show` + `assignRoles` have no tenant/membership check** — users are global/unscoped → cross-tenant profile IDOR and role assignment. | `UserController.php:36,43`; `UserPolicy.php:16` |
| F4 | **`Role` is not tenant-scoped and `RolePolicy` never checks tenant** → UUID enumeration edits other tenants' roles; `store`/`update` skip authorization entirely, bypassing the `is_system` guard. | `RoleController.php:46-73`; `RolePolicy.php` |
| F5 | **`GuardianController::link` performs zero authorization** (unlink is gated; link is not). | `GuardianController.php:109` |
| F6 | **Payment recording/refund and invoice void run permissionless** — policy abilities `create`/`refund`/`void` exist but are never invoked. | `PaymentController.php:52,58`; `InvoiceController.php:80` |
| F7 | **Double-payment race**: `RecordPayment` has no `lockForUpdate` on the invoice inside the transaction; two concurrent requests can both pass the `amount > balance_minor` check. The `unique(tenant_id, reference)` constraint does not dedupe (fresh random reference per call). | `app/Domains/Finance/Services/RecordPayment.php:49-51` |
| F8 | **Money precision**: float arithmetic on integer-cent money at two sites (discount allocation in `IssueInvoice` — also contains a dead proportional-allocation loop — and gateway-fee bps in `RecordPayment`). Works only because the last share absorbs remainder. | `IssueInvoice.php:69-107`; `RecordPayment.php:68` |
| F9 | **Journal tenant dependency**: `PostJournalEntry` takes `tenant_id` from `TenantContext` only — a queued job without context writes orphaned ledger rows invisible to scopes. | `PostJournalEntry.php:63,69` |
| F10 | **No domain tests at all** (only 3 auth tests). Tenant isolation, RBAC, finance balancing, and all state machines are unverified. | `tests/Feature/Api/V1/` |

### High-priority findings

| # | Finding | Location |
|---|---|---|
| F11 | `EnsureEmailVerified` alias registered but never applied → unverified accounts can log in and use tenant APIs. | `bootstrap/app.php:24`; `SessionController` |
| F12 | `TenantContext` is static; routes that skip `resolve.tenant` (session/onboarding/tenant-store groups) can read a stale tenant from a previous request under long-lived workers; no `clear()` between requests. | `TenantContext.php`; `routes/api/v1/identity.php:47-65` |
| F13 | No soft deletes anywhere; hard deletes include `AcademicYear::destroy` (cascades terms), student/guardian/campus/fee-structure deletes. Handbook mandates lifecycle states (archive/retire/void/cancel). | `app/Models/*` (no `SoftDeletes`) |
| F14 | `respondOffer` trusts client-supplied `actor_name` for the audit timeline → spoofable actor identity. | `ApplicationController.php:121` |
| F15 | Gradebook upsert never verifies the student is enrolled in the section (you can grade a non-enrolled student). | `app/Domains/Academics/Services/UpsertGradebookEntry.php:59` |
| F16 | `EnrollStudentInCourse` uses `syncWithoutDetaching` → duplicate enrollment silently no-ops instead of rejecting. | `EnrollStudentInCourse.php:26` |
| F17 | Attendance `summary` uses raw-table joins that bypass `TenantScope` on joined models (low risk today, fragile). | `AttendanceSessionController.php:204` |
| F18 | `ReportCardController` hand-rolls permission resolution duplicating `AbstractCapabilityPolicy::keys` → drift risk. | `ReportCardController.php:57` |
| F19 | Duplicate invariants (subject code, section label) rely on DB unique constraints only; no friendly pre-check (DB error leaks on duplicates). | `WriteSubject.php:18`; `WriteCourseSection.php:20` |
| F20 | `CapabilityRouteServiceProvider` exists on disk but is **not registered** (`bootstrap/providers.php`) — dead/stale file that should be removed or reconciled with the `routes/api/v1.php` glob loader. | `app/Providers/CapabilityRouteServiceProvider.php` |

---

## 4. Per-module audit summary

All modules: **implemented** (no stubs); tenant-scoped via global scope; events dispatched in services; FormRequests + Resources used consistently. The defect pattern is uniform: **reads authorized, writes/verbs not**.

| Module | Controllers / endpoints | Writes missing `authorize()` | Module-specific issues |
|---|---|---|---|
| **identity** | Account, AuditEvent, Invitation, Permission, PublicInvitation, Role, Session, Tenant, User (24 endpoints) | `Invitation::store`, `Role::store/update`, `Tenant::store/update`, `User::assignRoles` | **F2, F3, F4**; `resend` comment says "revoke old" but doesn't; `AuditEvent` model has no global scope (explicit filter, OK) |
| **institution** | InstitutionProfile, Campus, AcademicYear, Term, CalendarEvent (22 endpoints) | `InstitutionProfile::update/uploadLogo`, `Campus::store/update/bulk`, `AcademicYear::store/update/transition`, `Term::store/update/transition`, `CalendarEvent::store/update` | `AcademicYear::destroy` hard-cascades terms (F13); status guards duplicated across controller/policy/service |
| **people** | Student, Guardian, StaffMember (30 endpoints) | `Student::store/update/setStatus/transfer/bulk`, `Guardian::store/update/setPortalStatus/bulk` + `link` (F5), `StaffMember::store/update/setStatus/bulk` | `destroyDocument` does explicit subject-ownership check ✅ (pattern to replicate) |
| **academics** | Subject, CourseSection, Gradebook, Timetable (27 endpoints) | `Subject::store/update/bulk`, `CourseSection::store/update/enroll/bulk/duplicate`, `Gradebook::upsert/bulkSave`, `Timetable::schedule/move` | F15, F16, F19; timetable 3-way clash guard (teacher/room/grade) is strong ✅ |
| **admissions** | Application (11 endpoints) | `store/update/advance/sendOffer/respondOffer/enroll/recordScores/bulk` | Stage machine (`StageTransitionGuard`) is real ✅; F14 (spoofable actor) |
| **assessments** | Exam, ExamPeriod, ReportCard (19 endpoints) | `Exam::store/update/setStatus/setResult/bulkResults/fillResults/curveResults/bulk`, `ExamPeriod::store/update/setStatus/bulk` | Publish-requires-all-graded + locked-exam invariants ✅; F18 (ReportCard) |
| **attendance** | AttendanceSession (10 endpoints) | `open/bulk` | open→draft→submit→reopen machine + roster snapshot ✅; F17 (raw joins) |
| **finance** | Account, FeeStructure, FinanceOverview, FinancialReport, Invoice, Payment (20 endpoints) | `FeeStructure::store/update/toggle/bulk`, `Invoice::store/update/void/bulk`, `Payment::storeForInvoice/refund` | **F6–F9**; ledger balancing (debits==credits, integer cents) enforced ✅; void is proper soft-lifecycle with reversal entry ✅ |
| **communications** | Announcement, Broadcast, CommunicationsOverview, MessageThread, NotificationFeed (20 endpoints) | `Announcement::store/update/bulk`, `Broadcast::store/bulk`, `MessageThread::store/reply/setStatus/bulk` | `NotificationFeed` permissionless by design (documented); thread `reply` is a data-exposing mutation |
| **insights** | InstitutionSnapshot, EnrollmentReport, AcademicReport, FinancialInsights (4 read endpoints) | — (read-only, all authorized ✅) | CSV export supported; permission-key checks clean |

**Route surface:** 10 capability files under `routes/api/v1/` auto-loaded by `routes/api/v1.php` with `[force.json, log.api, auth:sanctum, throttle:authenticated, resolve.tenant]`; identity additionally exposes public session/onboarding routes. Total ≈ **180 endpoints**.

---

## 5. Risk register (ranked)

| Severity | Item | Impact |
|---|---|---|
| 🔴 Critical | Write/verb endpoints unguarded (~50+) | Any authenticated member = full tenant data control; payment/refund/void permissionless |
| 🔴 Critical | IDOR: User / Role / Tenant | Cross-tenant data read/write; tenant rename; role tampering |
| 🔴 Critical | Zero domain tests | All of the above ships unverified; regressions invisible |
| 🔴 Critical | Payment double-record race | Financial loss / ledger corruption |
| 🟠 High | No idempotency on mutations | Duplicate payments, duplicate invoices, duplicate enrollments under retries |
| 🟠 High | Verified-email not enforced | Account-takeover surface; handbook violation |
| 🟠 High | Hard deletes / no lifecycle states | Irrecoverable data loss; audit holes |
| 🟠 High | Static TenantContext staleness | Cross-tenant context bleed under Octane/queue workers |
| 🟠 High | Money float rounding (2 sites) | Off-by-one-cent ledger drift |
| 🟡 Medium | Spoofable audit actor (respondOffer) | Audit integrity |
| 🟡 Medium | Gradebook non-roster grading; silent duplicate enrollment | Data integrity |
| 🟡 Medium | Dead code: `CapabilityRouteServiceProvider`, discount-allocation loop | Maintenance confusion |

---

## 6. Phase 0 work plan (platform foundation)

Ordered; each item has acceptance criteria. **Goal: close every 🔴 Critical before any module pass.**

### P0-1 — Enforce authorization on every endpoint *(the big one)*
- Add an `authorize` call (or a `permission:` route middleware alias) to every write/verb controller method across all 10 domains, reusing existing policies; add missing policy abilities where none exist (`setStatus`, `transfer`, `link`, `schedule`, `open`, `setPortalStatus`, etc.).
- Fix the three IDORs: `Tenant::update` → platform-admin gate; `User::show/assignRoles` → membership check; `Role` → tenant scoping + tenant check in `RolePolicy` + invoke `is_system` guard on `update`.
- Guard `GuardianController::link`; gate `respondOffer` actor from authenticated user.
- Delete/reconcile dead `CapabilityRouteServiceProvider`.
- **Acceptance:** Pest suite per module asserting role X can/cannot call each write endpoint; IDOR tests for User/Role/Tenant; `composer test` green.

### P0-2 — Tenant-isolation verification + context lifecycle
- Add `TenantContext::clear()` on request termination (or bind per-request); add fail-closed fallback in `PostJournalEntry` when context is null.
- **Acceptance:** Cross-tenant test matrix (tenant A user → tenant B resource: 404/403 on read, 403/404 on write, for a representative set in every module); queue-job test proving tenant binding via `runAs`.

### P0-3 — Idempotency middleware (cross-cutting)
- Install `grazulex/laravel-api-idempotency` (already suggested in composer) or implement a lightweight `Idempotency-Key` middleware; apply to finance mutations (`storeForInvoice`, `refund`, `void`) and key verbs (`enroll`, `sendOffer`, `advance`, bulk ops).
- **Acceptance:** Duplicate request with same key returns cached result, no second side effect; finance double-post test.

### P0-4 — Finance correctness hotfixes
- `lockForUpdate` on invoice row in `RecordPayment`; integer-arithmetic (or BC math) for discount allocation and gateway-fee bps; delete dead discount loop.
- **Acceptance:** Concurrent-payment test (two parallel requests → exactly one succeeds, balance correct); ledger always balances after discount/refund/fee scenarios.

### P0-5 — Verified-email enforcement
- Apply `verified` middleware to tenant routes (with graceful 403 + resend flow), or an explicit documented policy for unverified access.
- **Acceptance:** Unverified user gets 403 on tenant APIs; verify link flow works end-to-end.

### P0-6 — Error & correlation contract
- Populate `withExceptions`; standardize error envelope incl. `request_id` (per-request UUID), tenant id, and business-oriented messages (handbook Ch 20.7–20.8).
- **Acceptance:** Every 4xx/5xx response includes `request_id`; Scramble docs reflect envelope.

### P0-7 — Test infrastructure & quality gates
- Pest helpers: `actingAsTenantMember($role, $tenant)`, tenant/role factories, system-role seeder; keep `composer test` (lint + types + unit) as the CI gate; add GitHub Actions if not present.
- **Acceptance:** New helper suite documented; a smoke test proves isolation matrix runs in CI.

### P0-8 — Soft-lifecycle standard (decision + pattern)
- Adopt a standard (soft delete vs status lifecycle per entity class, e.g. students = status lifecycle; fee structures = status; audit-sensitive = soft delete), migrate the high-risk hard deletes, and encode in policies.
- **Acceptance:** No hard `destroy` remains on any tenant-owned aggregate except documented exceptions; deletion always records an event.

---

## 7. Notes & decisions to record as ADRs

1. **ADR-011 (suggested) — Presentation transport divergence.** Handbook Ch 29/31 prescribes Inertia as the canonical web transport; the implementation is a headless REST API + TanStack Start SPA with a verb-dispatch seam and mock/live toggle. User decision (2026-08-30): **keep the split**. Record as ADR; the REST API plays both "presentation adapter" and "integration adapter" roles today — revisit if SPA SSR/SEO or a parent/student portal requires server-driven pages.
2. **ADR-012 (suggested) — Custom RBAC over Spatie Permission.** Custom `roles`/`tenant_memberships` + `PermissionCatalog` + policy gates. Fine — but the P0-1 authorization wiring must treat it as the single source of truth; do not introduce Spatie Permission later without an ADR.
3. **Custom audit over Spatie Activitylog** — keep; the wildcard `RecordBusinessEvent` listener is simpler and event-native.
4. **Live testing path:** the ngrok tunnel (`https://disabled-subpar-overtime.ngrok-free.dev`) + `VITE_API_MODE=live` + `VITE_API_URL` lets each module pass be verified against the real API from the existing UI.


---

## 8. Addendum — P0-1 Slice 1 (finance + identity) executed, 2026-08-30

### 8.1 Audit correction (verified at code level)

The controller-level audit over-stated the authorization gap. **Most finance and identity write endpoints were already gated by `FormRequest::authorize()`** (e.g. `StoreFeeStructureRequest`, `RecordPaymentRequest`, `VoidInvoiceRequest`, `UpdateTenantRequest`, `UpdateUserRolesRequest`, `StoreRoleRequest`), which the audit's controller scan missed. The genuine defects were narrower:

| # | Defect (verified) | Severity |
|---|---|---|
| R1 | `InvoiceController::remind` authorized `'update'` → `InvoicePolicy::update` requires **Draft** status → reminding an **issued** invoice always returned 403 | 🔴 functional |
| R2 | `BulkInvoicesRequest` gated only on `can('create')` (write key) → bulk **issue/void** bypassed the dedicated `finance.invoices.issue` / `finance.invoices.void` keys | 🟠 privilege bypass |
| R3 | `UserPolicy` instance gates had no tenant-membership check → cross-tenant IDOR on `show` / `assignRoles` / `suspend` / `reactivate` | 🔴 IDOR |
| R4 | `RolePolicy` had no tenant check and `Role` is unscoped → cross-tenant role read/edit/delete; `is_system` guard existed but tenant check didn't | 🔴 IDOR |
| R5 | `RolesAssigned`, `UserSuspended`, `UserReactivated` redeclared `public readonly $tenantId` in promoted params while `BusinessEvent` also promotes it → **PHP fatal on dispatch** → `assignRoles` / `suspend` / `reactivate` all 500'd (uncovered by tests) | 🔴 crash |
| R6 | `finance_fee_structures.academic_year_label` NOT NULL but optional in request → fee store 500 without it; `currency` NOT NULL but optional → 500 | 🟠 crash |
| R7 | `InviteUserRequest` gated on `UserPolicy::invite` (`identity.users.write`) instead of `InvitationPolicy::create` (`identity.invitations.write`) — catalog-defined invite key was dead | 🟡 mismatch |
| R8 | `routes/api/v1.php` section A referenced deleted `AuthController` → `/api/v1/register|login|logout|me|forgot-password|reset-password|email/*` all 500'd; 3 legacy test files targeted the dead surface | 🔴 broken surface |

### 8.2 Fixes applied

- **R1** — added `InvoicePolicy::remind` (write key, status ≠ void); controller now authorizes `remind`.
- **R2** — `InvoiceController::bulk` + `FeeStructureController::bulk` authorize every row against the action's dedicated ability before delegating to `BulkFinanceAction`.
- **R3** — `UserPolicy` added `inActiveTenant()` membership check to `view` / `assignRoles` / `suspend`.
- **R4** — `RolePolicy` scoped `view` to platform-or-own-tenant and `update`/`delete` to own-tenant + non-system.
- **R5** — removed the child redeclaration of `$tenantId` in the three events (single parent-owned readonly property).
- **R6** — `StoreFeeStructureRequest.academic_year_label` now required; `WriteFeeStructure` defaults `currency` from `config('finance.defaults.currency')`.
- **R7** — `InviteUserRequest` now authorizes `can('create', Invitation::class)`. Seeded roles unaffected: `it.admin` holds `identity.invitations.write`; `principal` was already invite-read-only by design.
- **R8** — removed section A from `routes/api/v1.php`; added framework-compatible aliases `verification.verify` and `password.reset` (hardcoded names used by the framework's `VerifyEmail`/`ResetPassword` notifications) pointing at `Identity\AccountController`.

### 8.3 Tests added / ported (suite now 48 tests, all green)

- `tests/Pest.php` — helpers: `makeTenant`, `makeRole`, `makeMember`, `bindTenant`.
- `tests/Feature/Api/V1/Finance/AuthorizationTest.php` (10 tests) — fee CRUD/bulk gates, **bulk-issue key-bypass regression**, remind policy matrix, payment/refund gates.
- `tests/Feature/Api/V1/Identity/AuthorizationTest.php` (11 tests) — role/user/tenant/invitation gates + **cross-tenant IDOR regression** + platform-admin tenant update + system-role protection.
- Ported `AuthTest`, `EmailVerificationTest`, `PasswordResetTest` from the dead surface to `/api/v1/identity/*` (registration, session login/logout/me, signed verify link, enumeration-safe forgot/reset).

### 8.4 Verification

- `php artisan test` → **48 passed, 0 failed** (was 26 failed before this slice).
- `pint` → clean on all touched files (repo has pre-existing violations elsewhere, untouched).
- `phpstan` → **no errors in any touched file**; repo baseline has 1000+ pre-existing errors (e.g. `BelongsToTenant` generics) — see P0-7: introduce a PHPStan baseline before treating `composer test` as a gate.

### 8.5 Notes

- `app/Http/Controllers/Api/V1/AuthController.php.BAK` is dead clutter (the retired controller) — safe to delete once the identity surface is confirmed.
- The whole SchoolOS layer is **uncommitted** in git (untracked) — recommend committing after each slice.
- Next: roll the same verification pattern (controller + FormRequest + policy-level tenant scoping + tests) through **institution → people → academics → admissions → assessments → attendance → communications**.


---

## 9. Addendum — P0-1 Slice 2 (institution + people) executed, 2026-08-30

### 9.1 Findings (verified at code level)

Both modules were re-verified end-to-end (controller `authorize()` + FormRequest `authorize()` + policy abilities + tenant scoping). **Unlike finance/identity, institution and people were already gate-complete:**

- All write endpoints (store/update/verbs/bulk) are gated by FormRequests or controller `authorize()` — including `GuardianController::link` (gated by `LinkGuardianRequest`, which requires write on **both** the student and the guardian), `unlink`, `transfer`, `setStatus`, `issueLogin`, `revokeLogin`, document/avatar uploads.
- Policies carry the right abilities (`setCurrent` on AcademicYearPolicy, planning-only delete on AcademicYearPolicy, primary-campus protection on CampusPolicy).
- Cross-tenant isolation is structural: every institution/people model is `BelongsToTenant` → global scope → cross-tenant route binding returns **404 by construction**.
- Bulk endpoints are gated on the entity's single write key (create/update/delete share one key per entity), so no key-bypass exists (unlike the finance bulk issue/void case).

### 9.2 Fix applied

- **`TermPolicy::delete` now rejects in-progress terms** (mirrors the controller's inline 422 guard and the AcademicYearPolicy pattern). State-based denials are now consistently enforced at the policy layer → 403, defense-in-depth.

### 9.3 Tests added (suite: 76 tests, all green)

- `tests/Feature/Api/V1/Institution/AuthorizationTest.php` (16 tests) — campus/academic-year/term/calendar/profile gates: negative 403s, positive writes, primary-campus delete protection, run-year delete protection, in-progress term delete, **cross-tenant 404** per entity.
- `tests/Feature/Api/V1/People/AuthorizationTest.php` (12 tests) — student/guardian/staff gates: negative 403s, positive writes, student status verb, **guardian link requires write on both records**, cross-tenant 404 per entity.

### 9.4 Verification

- `php artisan test` → **76 passed, 0 failed**.
- Pint clean on touched files; PHPStan clean (`TermPolicy`).

### 9.5 Remaining P0-1 roll-out

Modules verified/fixed: **finance, identity, institution, people** (28 new tests so far).
Remaining: **academics, admissions, assessments, attendance, communications** (insights is read-only and already authorized). Same verification pattern applies — expect the FormRequest layer to already gate most writes, with targeted fixes needed at the policy level.


---

## 10. Addendum — P0-1 Slice 3 (academics + admissions) executed, 2026-08-30

### 10.1 Findings (verified at code level)

Both modules were gate-complete at the FormRequest layer, same as institution/people:
- Academics — every write/verb gated (`StoreSubjectRequest`, `StoreCourseSectionRequest`, `EnrollStudentRequest` (requires update on the section), `ScheduleTimetableSlotRequest` (`schedule` ability), `MoveTimetableSlotRequest`, `UpsertGradebookEntryRequest`, `BulkGradebookRequest`, `CurveGradebookRequest`, controller `authorize()` on destroy/drop).
- Admissions — every write/verb gated with dedicated abilities (`sendOffer`, `respondOffer`, `enroll` all exist on `ApplicationPolicy`; store/update/advance/recordScores via FormRequests).
- Tenant isolation structural (all models `BelongsToTenant` → cross-tenant 404).

### 10.2 Fixes applied (invariant & audit-integrity defects)

| # | Defect | Fix |
|---|---|---|
| F15 | Gradebook upsert could grade a student **not on the section roster** | `UpsertGradebookEntry` now rejects with 422 ("not enrolled in the section") |
| F16 | `EnrollStudentInCourse` used `syncWithoutDetaching` → **silent duplicate enrollment** (no-op but still dispatched the event) | Now rejects duplicate enrollment with 422 |
| F14 | `respondOffer` accepted a **client-supplied `actor_name`** for the audit timeline (spoofable) | `RespondToOffer` now records the **authenticated user** as `actor_name`/`actor_id`; removed the `actor_name` field from `RespondOfferRequest` (extra fields ignored for BC) |
| F19 | Subject/section duplicate codes relied on DB unique constraints → raw 500 on duplicates | `WriteSubject` / `WriteCourseSection` now run friendly tenant-scoped duplicate pre-checks → 422 with a clear message (case-insensitive for subject codes) |

### 10.3 Tests added (suite: 92 tests, all green)

- `tests/Feature/Api/V1/Academics/AuthorizationTest.php` (16 tests) — subject/section gates, **duplicate-code 422**, **duplicate-enrollment 422**, **non-roster grading 422**, roster grading 201, cross-tenant 404.
- `tests/Feature/Api/V1/Admissions/AuthorizationTest.php` (5 tests) — application gates, advance verb, **offer-response audit integrity** (actor = authenticated user, spoofed `actor_name` ignored), cross-tenant 404.

### 10.4 Verification

- `php artisan test` → **92 passed, 0 failed**.
- Pint clean; **no new PHPStan errors** (remaining flags in these files are pre-existing baseline items, e.g. `(string) $data['code']` cast).

### 10.5 P0-1 status

Done: **finance, identity, institution, people, academics, admissions** (6/9 modules).
Remaining: **assessments, attendance, communications** (insights is read-only, already authorized).


---

## 11. Addendum — P0-1 Slices 4–5 (assessments + attendance, communications) executed + capability specs, 2026-08-30

### 11.1 Assessments + Attendance

Gate verification: gate-complete at controller + FormRequest level (dedicated abilities `publish`, `submit`, `reopen`, `schedule`, etc. all exist). Two real defects fixed:

| Defect | Fix |
|---|---|
| `AttendanceSessionController::summary` gated on `sessions.read` while the catalog's dedicated `attendance.summary.read` was dead; raw joins lacked tenant filters on joined tables | New `AttendanceSessionPolicy::viewSummary` (`attendance.summary.read`); explicit `tenant_id` filters on `attendance_sessions` + `students` joins (defense in depth) |
| `ReportCardController` hand-rolled membership→role→permission-key resolution (duplicated the policy helper; drift risk) | New `ExamPolicy::viewReports` (`assessments.reports.read`); controller now uses `can('viewAny') && can('viewReports')`; deleted the hand-rolled `permissionKeys()` |

Tests: `Assessments/AuthorizationTest.php` (7) + `Attendance/AuthorizationTest.php` (8) — dedicated-key gates (publish/reports.read/summary.read), combined mark gate, cross-tenant 404.

### 11.2 Communications

Gate verification: **zero gaps** — every endpoint covered by controller `authorize()` (send/archive/start/cancel/complete/duplicate) or FormRequest `authorize()`; overview uses the shared `CommunicationsPermission` helper with the dedicated key; feed is personal + per-source filtered by design.

Tests: `Communications/AuthorizationTest.php` (11) — store ±, send/archive/start dedicated keys, thread reply gate, overview key ±, cross-tenant 404.

### 11.3 Capability specs (handbook structure)

Three 17-section specs written (code-grounded): `capabilities/assessments.md`, `capabilities/attendance.md`, `capabilities/communications.md` — each covers Capability/Goals/Scope/Actors/Business Rules/Aggregates/Events/Workspaces/Interaction Patterns/Presentation Contracts/Permissions/Notifications/Realtime/Discovery/AI/Observability/Testing + acceptance criteria, with implemented-vs-gap flags so nothing drifts.

### 11.4 P0-1 closed

All 9 writable modules verified: **finance, identity, institution, people, academics, admissions, assessments, attendance, communications** (insights read-only, pre-authorized). Suite: **119 tests, 0 failures** (was 3 auth tests, 26 failing at start). 28 new regression tests total across slices 2–5.

**Next: P0-2 tenant-isolation matrix + TenantContext lifecycle; P0-3 idempotency; P0-4 finance hotfixes (row locks, integer math); P0-5 verified-email enforcement.**


---

## 12. Addendum — Capability specs backfill + P0-2 executed, 2026-08-30

### 12.1 Capability specs (all 10 modules now have one)

Backfilled 7 specs in the handbook's 17-section format (code-grounded): `capabilities/identity.md`, `finance.md`, `institution.md`, `people.md`, `academics.md`, `admissions.md`, `insights.md` — joining the earlier `assessments.md`, `attendance.md`, `communications.md`. Every spec: Capability / Goals / Scope / Actors / Business Rules / Aggregates / Business Events / Workspaces / Interaction Patterns / Presentation Contracts / Permissions / Notifications / Realtime / Discovery / AI / Observability / Testing + Acceptance Criteria, with implemented-vs-gap flags per section.

### 12.2 P0-2 — TenantContext lifecycle + isolation matrix

**Root cause found (test-driven):** implicit route model bindings resolve **before** route-level middleware in this Laravel version. The static `TenantContext` at binding time was therefore either stale (previous request's tenant — cross-tenant scope applied to the WRONG tenant in production) or null (after a naive request-start clear — scope no-op, cross-tenant rows visible). The `ResolveTenantContext` global pre-resolver fixes it properly:

| Change | Why |
|---|---|
| `ResolveTenantContext` (global, prepend) | Clears stale state AND pre-warms the context from the current request's Sanctum auth + membership resolution (X-Tenant-Id > active_tenant_id > first), so early binding is always correctly scoped. `resolve.tenant` stays the authoritative 401/403 gate |
| `PostJournalEntry` | Fails closed (422) when no tenant context — no orphaned/wrong-tenant ledger rows in queued contexts; `tenant_id` accepted explicitly |
| `UserResource` | `status?->value` defensive — null-status users (in-memory/factory) no longer 500 on `session.me` |
| `UserFactory` | Sets `status`/`mfa_enabled` for realistic fixtures |

**Tests** (`tests/Feature/TenantIsolationTest.php`): context bound during tenant requests; context derived from the current request (actor switch A→B, never stale A); null for unauthenticated public routes; finance cross-tenant 404 (the missing module case); own-tenant positive control.

**Verification:** 124/124 tests, Pint clean, **zero new PHPStan errors** (remaining flags are pre-existing).

### 12.3 Status

P0-1 ✅ (9 modules) · P0-2 ✅ · **Next: P0-3 idempotency, P0-4 finance hotfixes (row-lock + integer math), P0-5 verified-email enforcement.**


---

## 13. Addendum — P0-9 (Context) + P0-3 (Idempotency) executed, 2026-08-30

### 13.1 P0-9 — Laravel Context adoption

`TenantContext` rewritten as a thin wrapper over `Illuminate\Support\Facades\Context` (`add/get/flush/scope`) — zero call-site churn (~40 files keep using `app(TenantContext::class)`). `ResolveTenantContext` now also adds `trace_id` (per-request UUID) and `actor_id` to Context at request start.

**What it buys:** queued jobs dispatched during a request automatically carry tenant/actor/trace (no manual `runAs()` wrapping); every log line is tenant/trace-correlated (P0-6 becomes trivial); Octane-safe by construction. The P0-2 early-binding fix is unchanged (the pre-resolver populates Context before binding). Suite stayed green with zero behavioral change (131 tests).

### 13.2 P0-3 — Idempotency (`Idempotency-Key`)

New `EnsureIdempotency` route middleware (registered last in the capability group, after `resolve.tenant`) + `idempotency_keys` table + `IdempotencyKey` model:

- **Opt-in**: only POST/PUT/PATCH/DELETE with a non-empty `Idempotency-Key` are tracked.
- **Reserve-first**: key inserted before execution (unique scope+key) → concurrent same-key requests get **409** instead of double-executing.
- **Replay**: subsequent calls return the stored status+body with `Idempotency-Replayed: true` (as a real JsonResponse so `force.json` passes it through — a bug the tests caught).
- **Scope**: per-tenant bucket (`'platform'` for public routes) — identical keys in different tenants never collide.
- **Failure handling**: 5xx and streamed responses are not cached (retries re-execute); exceptions delete the reservation so a retry isn't stuck; 24h TTL with lazy cleanup.

**Tests (7):** same-key replay (single row created), different keys, no-header passthrough, GET ignored, per-tenant scoping, in-flight 409, cached 422 replay.

### 13.3 Verification

**131 tests, 0 failures** (124 + 7). Pint clean. PHPStan: **no errors** on all touched files.

### 13.4 Status

P0-1 ✅ · P0-2 ✅ · P0-3 ✅ · P0-9 ✅ — **Next: P0-4 finance hotfixes (row-lock + integer math + allocation), P0-5 verified-email, P0-6 error/correlation contract (now mostly free via Context), P0-7 CI, P0-8 soft lifecycle.**


---

## 14. Addendum — P0-4 (finance hotfixes) + P0-5 (verified-email) executed, 2026-08-30

### 14.1 P0-4 — Finance correctness

| Defect | Fix |
|---|---|
| Double-payment race (F7) | `RecordPayment` now `lockForUpdate()`s the invoice row at transaction start — concurrent payments block on the lock, then see the updated balance → 422 instead of double-credit |
| Gateway-fee float (F9) | Pure-integer fee: `intdiv(amount * bps + 5000, 10000)` (round-half-up, no float path) |
| Dead discount branch (F8) | `IssueInvoice` had a ~40-line proportional discount-allocation loop whose output was immediately discarded (rebuilt from lines); removed. Effective behaviour preserved: Dr AR (net) + Dr Discounts == Cr Revenue (gross), entry always balanced |
| received_at nullsafe noise | Simplified to the direct call (always set in this service) |

**Tests (5)** in `tests/Feature/Api/V1/Finance/CorrectnessTest.php`: full payment settles + balanced entry; partial → partially_paid; overpayment/double-payment 422 (one row); gateway fee = 2 500 minor on 1 000 000 with balanced postings; discounted issue posts balanced entry.

### 14.2 P0-5 — Verified-email enforcement

- `EnsureEmailVerified` fixed: **unauthenticated requests pass through** (it previously 401'd every public route); authenticated-but-unverified → 403 with a clear message.
- `verified` added to the capability route group (after `resolve.tenant`); the verification route is exempt (`withoutMiddleware('verified')` — both the identity.php route and the framework-name alias).
- Posture: **unverified members are blocked from all tenant routes and the session surface** until they verify via the signed link; public routes (login/register/password/verification) untouched.
- **Tests (5)**: block unverified 403, block on session surface, verified passes, verify-then-pass flow, public routes untouched.

### 14.3 Verification

**141 tests, 0 failures** (131 + 5 + 5). Pint clean. PHPStan: **no errors** in all touched files.

### 14.4 Status

P0-1 ✅ · P0-2 ✅ · P0-3 ✅ · P0-4 ✅ · P0-5 ✅ · P0-9 ✅ — **Next: P0-6 error/correlation contract (largely free via Context trace_id), P0-7 CI/PHPStan baseline, P0-8 soft lifecycle.**


---

## 15. Addendum — P0-6 (error/correlation contract) executed, 2026-08-30

### 15.1 Delivered

- **Standardized error envelope** for every `api/*` error: `{ success: false, message, errors? (validation), trace_id }` — rendered via the exception handler (`bootstrap/app.php` `withExceptions->render`), with correct status mapping: Validation 422, Auth 401, Model/Route 404, HttpException passthrough, else 500. In production, 500s return a generic message + trace_id (no stack leakage); in debug, the real message.
- **`X-Trace-Id` header on every response** (success + error) — set by the global `ResolveTenantContext` middleware; the body `trace_id` matches the header for correlation.
- **Middleware error payloads aligned** (they bypass the exception handler): verified-email 403, idempotency 409 ×2, force-json fallback — all now carry `success` + `trace_id`.
- Log correlation was already free from P0-9 (tenant/actor/trace ride in Illuminate Context → every log line).

### 15.2 Tests (6)

`tests/Feature/ErrorContractTest.php`: 422 validation envelope with header↔body trace match; 404 envelope (`Not Found.`); 403 envelope; 401 envelope (`Unauthenticated.`); `X-Trace-Id` on success responses; idempotency 409 envelope.

### 15.3 Verification

**147 tests, 0 failures** (141 + 6). Pint clean. PHPStan: **no errors** in all touched files.

### 15.4 Status

P0-1 ✅ · P0-2 ✅ · P0-3 ✅ · P0-4 ✅ · P0-5 ✅ · P0-6 ✅ · P0-9 ✅ — **Next: P0-7 CI/PHPStan baseline, P0-8 soft lifecycle, then Phase 1+ (notifications → realtime → AI → observability) and live-mode E2E.**


---

## 16. Addendum — P0-7 (CI/types gate) + P0-8 (soft lifecycle) executed — Phase 0 complete, 2026-08-30

### 16.1 P0-7 — CI & type gate

- **PHPStan baseline** (`phpstan-baseline.neon`, 1 342 pinned errors): the types gate is now enforceable — known errors excluded, **any new error fails**.
- **Pint repo-wide clean** + `pint.json` (`preset: laravel`).
- **Two real bugs found and fixed while making the gate green:**
  1. **Pint's `final_public_method_for_abstract_class` rule had added `final` to `CapabilityFormRequest::authorize()`** — every one of the ~89 FormRequests that override it (the entire authorization architecture) would **fatal on load** (500 on those endpoints). Rule disabled in `pint.json`; the 89 non-ignorable PHPStan errors vanished with it.
  2. **`phpstan.neon` `tmpDir: /tmp/phpstan` resolved relative on Windows** → a multi-MB `tmp/phpstan` cache dir inside the repo broke Pint's full scan (hung for minutes). tmpDir removed (system default); stray cache deleted; `rector.php` cache path made Windows-safe too.
- **Rector**: crashes (parser bug `assert($startLine > 0)`) on two valid pivot models (`StudentGuardian`, `TenantMembership`) — skip doesn't prevent the parse crash, so Rector moved out of the mandatory gate to an optional `test:refactor` script. Documented; re-enable when upstream fixes it.
- **CI**: `.github/workflows/tests.yml` (PHP 8.3/8.4 matrix, `composer test`) now runs a **green gate** end-to-end.

### 16.2 P0-8 — Soft lifecycle

- `SoftDeletes` on **15 core models** (students, guardians, staff, campuses, academic years, terms, calendar events, fee structures, applications, invoices, announcements, broadcasts, message threads, subjects, course sections).
- **Natural-key unique indexes rebuilt to include `deleted_at`** (9 tables) so archived records don't block key reuse; **FormRequest unique rules (12) now ignore archived rows**.
- Attendance summary excludes archived students (join filter).
- **Tests (4):** archive a student via API → hidden from list/show, restore → visible; campus code reusable after archive; **finance history survives archiving a student** (invoice row intact); archived students excluded from the attendance summary.

### 16.3 Verification

`composer test` (the exact CI gate): **lint ✓ types ✓ 151 tests ✓**. Pint clean. PHPStan clean with baseline.

### 16.4 Phase 0 complete

P0-1 ✅ · P0-2 ✅ · P0-3 ✅ · P0-4 ✅ · P0-5 ✅ · P0-6 ✅ · P0-7 ✅ · P0-8 ✅ · P0-9 ✅

**Phase 0 (production foundation) is DONE.** Next: **Phase 1+ per module specs** (notifications → realtime → AI → observability), live-mode E2E against the ngrok tunnel, and the housekeeping items (commit the layer, move `docs/` into the repo, restore endpoints for archives).


---

## 17. Addendum — Phase 1 (Notifications) executed, 2026-08-30

### 17.1 Delivered

Event-driven notification infrastructure per handbook Ch. 35:

- **Schema**: `notifications` (tenant-stamped, Laravel-compatible morph) + `notification_preferences` (per user × notification × channel opt-out, default on).
- **Base + channel**: `SchoolNotification` (queued, preference-gated `via()`), `TenantDatabaseChannel` (stamps `tenant_id` from request/job context — the stock channel instantiates the base model, so the stamp must happen in the channel).
- **Dispatcher**: `DispatchBusinessNotifications` wildcard listener (universal `'*'` pattern — parent-class listeners don't match children in this framework, same lesson as the audit projection) driven by `config/notifications.php` policies with `tenant_members` / `permission:<key>` recipient strategies (via the new `User::hasPermission()`).
- **Inbox API**: `GET /communications/notifications` (own, unread count) + `POST /communications/notifications/{id}/read` (own + tenant guarded).
- **Live policies**: `AnnouncementSent → all tenant members`, `InvoiceIssued → members with finance.invoices.read`; invitations already email via `SendInvitationEmail`.

### 17.2 Bugs found while building

- Parent-class `Event::listen(BusinessEvent::class)` does NOT match child events → fixed with the universal `'*'` + instanceof pattern (consistent with RecordBusinessEvent).
- PHP 8.4 rejects the redundant `User|object` union type at compile time.
- The stock database channel can't stamp `tenant_id` (it uses the base DatabaseNotification) → custom channel.

### 17.3 Verification

`composer test` (the CI gate): **lint ✓ types ✓ 157 tests ✓** (6 new notification tests: members notified, preference opt-out, permission-filtered recipients, mark-read, own-only, tenant-scoped inbox). PHPStan baseline regenerated (1 352 pinned).

### 17.4 Status

Phase 0 ✅ (all P0-1..P0-9) · **Phase 1 (Notifications) ✅** — next: more notification policies, live-mode E2E, realtime, discovery, AI, observability.


---

## 18. Addendum — Live-mode E2E against MySQL + ngrok, 2026-08-30

### 18.1 The proof

Full user journey executed against the **real API through the ngrok tunnel on MySQL**:

`register 201 → signed-URL email verify 204 → login 201 → Day-0 tenant onboarding 201 → tenant-scoped profile 200 → campus 201 → student 201 → announcement create+send 200 → queued notification job DONE → inbox API unread=1 ("New announcement: E2E Sports Day") → 404 error envelope with trace_id`

The queued notification carried the correct `tenant_id` into the job — **proving P0-9's Context queue-propagation + the TenantDatabaseChannel work in production**, not just tests.

### 18.2 Real deployment bugs found & fixed by the live run

| Bug | Fix |
|---|---|
| MySQL: auto-named index `application_stage_events_tenant_id_application_id_occurred_at_index` (67 chars) exceeds MySQL's 64-char limit → migrate fails | Explicit short index names (also `notification_preferences` unique, 70 chars) |
| MySQL: unique indexes backing FKs (every tenant_id FK) cannot be dropped → soft-deletes migration failed | `fk_support_idx` added before each drop (up + down) |
| Closure shadowing: `function (Blueprint $table)` shadowed the string table name → "Blueprint could not be converted to string" | Renamed closure param |
| **Signed URLs (email verification) failed behind ngrok (403 Invalid signature)** — untrusted proxies → app saw `http://` while URLs were generated as `https://` | `trustProxies(at: '*')` in bootstrap — required for any reverse-proxy deployment (ngrok/nginx/Cloudflare) |

### 18.3 Housekeeping

- **Git checkpoint committed** (`56202ad`) — the entire SchoolOS layer + Phase 0/1 work, working tree clean.
- MySQL database `schoolos` created; `migrate:fresh --seed` green on MySQL.

### 18.4 Verification

`composer test` (CI gate): lint ✓ types ✓ **157 tests ✓**.

### 18.5 Status

Phase 0 ✅ · Phase 1 (Notifications) ✅ · **Live E2E ✅** — next: more notification policies (+ recipient-resolver refactor), realtime, discovery, AI, observability, docs-into-repo.


---

## 19. Addendum — Notification recipient resolvers + results-published policy, 2026-08-30

### 19.1 Delivered

- **`ResolvesNotificationRecipients` interface** — notification policies can now reference any recipient-resolver class (container-resolved per event), not just the built-in string strategies.
- **Resolvers**: `TenantMembersRecipients`, `PermissionRecipients`, `ExamTeacherRecipients` (section teacher's portal user), `ExamGuardianRecipients` (portal users of guardians linked to enrolled students — via `course_enrollments` → `student_guardians` → `guardians.user_id`).
- **Dispatcher** now accepts a string strategy, a resolver class name, or an array of either — recipients merged + de-duplicated; container results asserted.
- **New policy**: `ExamPublished → [ExamTeacherRecipients, ExamGuardianRecipients]` — "results published" reaches the section teacher and parents' portal accounts in-app, preference-gated like everything else.
- **Tests (2)**: teacher + guardian notified with correct payload; staff/guardians without portal accounts skipped.

### 19.2 Verification

`composer test` (CI gate): lint ✓ types ✓ **159 tests ✓**. Committed.

### 19.3 Status

Phase 0 ✅ · Phase 1 (Notifications) ✅ · Live E2E ✅ · **Recipient-resolver extensibility ✅** — next: more policies (absence alerts, payment receipts), realtime, discovery, AI, observability.
