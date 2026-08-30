# SchoolOS Capability Spec — Identity

Status: **Implemented (API + tests) · gaps flagged below** · Source: `app/Domains/Identity`, `config/identity.php`, `routes/api/v1/identity.php`

---

## 1. Capability

What institutional responsibility does this solve?
- **Platform + tenant identity**: user accounts, tenant memberships, roles & permissions, invitations, sessions (login/logout/me), tenant switching, and the append-only audit trail.
- Owns the "tenant trust" boundary: every request knows exactly which tenant it operates in, and platform staff never implicitly act inside a school.

## 2. Goals

What outcomes should users achieve?
- Register once, join one or more schools (via invitation or Day-0 onboarding), and switch tenants seamlessly.
- Tenants assign capability bundles (roles) to members; platform staff manage system roles and tenant lifecycle.
- Every meaningful action is traceable to a user + tenant in the audit trail.

## 3. Scope

**Included**
- Registration, login/logout, "me", tenant switching, email verification + password recovery, invitations (issue/resend/revoke/accept), roles CRUD + cloning of system roles, users (status, role assignment), tenants (Day-0 create, platform update), permissions catalog, audit event stream.

**Explicitly excluded**
- MFA/SSO (currently `mfa_enabled` flag only) — **Gap**.
- Self-service billing/plan management.
- Verified-email *enforcement* (flow exists, not enforced on login) — **P0-5**.

## 4. Actors

| Actor | Primary actions |
|---|---|
| Platform admin (`platform.admin`) | Tenant lifecycle (update), system roles |
| `it.admin` | Manages users, roles, integrations, invitations |
| Principal | Operational visibility; manages own staff/users via delegate grants |
| Regular member | Uses the school workspace with granted capability keys |
| Public (unauthenticated) | Register, login, verify email, reset password |

## 5. Business Rules

Grounded in services + middleware (`CreateTenant`, `InviteUser`, `AcceptInvitation`, `AssignRoles`, `SetUserStatus`, `WriteRole`, `CloneSystemRoles`, `ResolveTenant`):

- **Tenant trust**: every tenant-scoped route resolves the tenant from `X-Tenant-Id` → the caller's `active_tenant_id` → first membership; fail-closed (403/409) when absent. Public routes opt out explicitly.
- **Roles**: scope `tenant` or `platform` (`tenant_id` null = platform role); `is_system` roles are immutable; a tenant may only modify roles it owns (P0-1 fix).
- **Invitations**: pending invite is unique per (tenant, email); issuing revokes a prior pending invite for the same pair; accept creates the membership and assigns role_ids.
- **Sessions**: login issues a Sanctum token; logout revokes; login rate-limited (5/min); unverified users can log in today (**P0-5** to enforce).
- **Audit**: every `BusinessEvent` (all domains) is projected into `audit_events` with tenant + actor.

## 6. Aggregates

| Aggregate | Invariants |
|---|---|
| `User` | Global record; unique email; status active/suspended; `active_tenant_id` pointer |
| `Tenant` | Unique slug; status/tier enums; lifecycle owned by platform |
| `Role` | Unique `(tenant_id nullable, key)`; `permission_keys` bundle; `is_system` immutable |
| `Invitation` | Unique `(tenant_id, email)` while pending; token + expiry; role_ids snapshot |
| `TenantMembership` | Pivot user↔tenant with `role_ids` array |

## 7. Business Events

`TenantCreated`, `UserInvited`, `InvitationAccepted`, `InvitationRevoked`, `RolesAssigned`, `UserSuspended`, `UserReactivated`, `RoleCreated`, `RoleUpdated` — all extend `BusinessEvent` → `audit_events`.

## 8. Workspaces

- **Platform workspace** (SchoolOS staff): tenants, system roles, platform audit.
- **School workspace shell**: account menu (me, tenant switcher), users, roles, invitations, audit trail sections.

## 9. Interaction Patterns

| Surface | Pattern |
|---|---|
| Login/register | Wizard |
| Tenant switcher | Dropdown (shell) |
| Users list | Explorer (status filters, search) |
| User profile | Profile (memberships, roles editor) |
| Roles editor | Editor (permission checklist) |
| Invitations | Explorer (pending, resend/revoke) |
| Audit trail | Dashboard (filterable event stream) |

## 10. Presentation Contracts

- **SessionResource**: `token, token_type, user{id, full_name, email, status, mfa_enabled, last_active_at, memberships[]}, active_tenant_id`
- **UserResource**: `id, full_name, email, status, mfa_enabled, last_active_at, memberships[]`
- **RoleResource**: `id, key, name, description, scope, is_system, permission_keys[], created_at`
- **TenantResource**: `id, slug, name, legal_name, status, tier, country_code, timezone, currency_code`
- **InvitationResource**: `id, email, role_ids, status, token, expires_at, created_at`
- **AuditEventResource**: `id, tenant_id, type, actor_id, actor_name, payload, occurred_at`
- **PermissionResource** (catalog): `key, label, description`

## 11. Permissions

Keys (`config/identity.php`): `identity.users.read|write`, `identity.roles.read|write`, `identity.tenants.read|write`, `identity.invitations.read|write`; special role key `platform.admin`.

| Ability | Gate |
|---|---|
| view/assignRoles/suspend/reactivate user | key + **target must be in active tenant** (P0-1 IDOR fix) |
| role view | `roles.read` + platform-or-own-tenant scope |
| role update/delete | `roles.write` + own-tenant + non-system |
| tenant update | **platform.admin only** |
| invitation issue/resend/revoke | `invitations.write` (via `InvitationPolicy::create`) |
| audit stream | `identity.users.read` (platform/principal visibility) |

## 12. Notifications

- **Implemented**: `InvitationIssued` notification (email), password-reset + verification emails (framework).
- **Required (Ch. 35)**: suspension notices, "new member joined" to admins, session-revoked push. **Gap.**

## 13. Realtime

- **Implemented**: none.
- **Required (Ch. 21/33)**: session revocation push (kick on role change), membership-change badges. **Gap — Phase 7.**

## 14. Discovery

- **Implemented**: DB indexes (`users.email` unique, memberships `tenant_id+user_id`, roles `tenant_id+key`).
- **Required**: full-text user/audit search. **Gap.**

## 15. AI

- **Implemented**: none.
- **Required (Ch. 36)**: privilege-oversight suggestions (permission drift detection), invitation-list recommendations. **Gap.**

## 16. Observability

- **Implemented**: login throttle, audit projection, request logs.
- **Required**: failed-login spikes per tenant, role-edit churn, invitation acceptance rate. **Gap.**

## 17. Testing

**Implemented (P0-1):**
- `tests/Feature/Api/V1/AuthTest.php` (ported to identity surface, 10 tests), `tests/Feature/Api/V1/Identity/AuthorizationTest.php` (10 tests incl. IDOR + platform-gate proofs).

**Required (Ch. 38):**
- Invitation expiry/unique/revoke-on-reissue; role immutability of system roles; tenant-switch flow; verified-email enforcement (P0-5); audit actor integrity for every identity event.

## Acceptance Criteria (DoD)

- [ ] Every write gated; user/role IDOR closed — **DONE (P0-1)**
- [ ] Audit trail actor integrity — **DONE (P0-1)**
- [ ] Verified-email enforcement — **P0-5**
- [ ] TenantContext lifecycle (no stale context across requests) — **P0-2**
- [ ] Invitation lifecycle covered by tests
