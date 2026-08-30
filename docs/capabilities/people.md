# SchoolOS Capability Spec — People (Students, Guardians, Staff)

Status: **Implemented (API + tests) · gaps flagged below** · Source: `app/Domains/People`, `config/people.php`, `routes/api/v1/people.php`

---

## 1. Capability

What institutional responsibility does this solve?
- **The school's human registry**: student records, guardian relationships + portal access, and staff employment records.
- Owns the master data every other capability references (invoices → students, attendance → rosters, threads → guardians).

## 2. Goals

What outcomes should users achieve?
- Enroll a student once and reuse the record across admissions, academics, finance, attendance.
- Link guardians to students, issue them portal access, and keep documents/avatars current.
- Maintain staff records with employment metadata and login access.

## 3. Scope

**Included**
- Students (CRUD, status transitions, campus transfer, bulk, avatar/documents), guardians (CRUD, portal status, link/unlink to students, bulk, avatar/documents), staff (CRUD, status, login issue/revoke, bulk, avatar/documents).

**Explicitly excluded**
- HR/payroll; staff scheduling; academic history beyond grade snapshot.
- Guardian self-service portal (records exist; portal UI is future).

## 4. Actors

| Actor | Primary actions |
|---|---|
| Registrar | Student/guardian records, transfers, documents |
| Principal | Status approvals, staff hiring |
| Teacher | Views rosters (read) |
| `it.admin` | Portal access issuance |

## 5. Business Rules

Grounded in services (`WriteStudent`, `WriteGuardian`, `WriteStaffMember`, `SetStudentStatus`, `TransferStudent`, `LinkStudentGuardian`, `UnlinkStudentGuardian`, `SetGuardianPortalStatus`, `IssuePortalAccess`, `SetStaffStatus`, `BulkPeopleAction`, `PersonMediaService`):

- **Students**: `admission_number` unique per tenant; `avatar_initials` auto-generated; status machine (prospective → enrolled → on_leave → graduated/withdrawn) with status-specific guards.
- **Transfer**: admission number uniqueness re-checked at the target campus; cascades to campus-scoped children.
- **Guardians**: link requires write on **both** student and guardian (combined gate); `portal_status` controls portal access; `IssuePortalAccess` provisions a portal user.
- **Staff**: `staff_number` unique per tenant; category/employment-type enums; login issue provisions credentials + role.
- **Documents/avatars**: `PersonMediaService` stores per person; ownership checks on every media action.
- Hard deletes exist today — **soft-delete/lifecycle is P0-8** (handbook mandates archive/retire over delete).
- All entities tenant-scoped (cross-tenant 404).

## 6. Aggregates

| Aggregate | Invariants |
|---|---|
| `Student` | Unique admission number; status machine; campus FK; grade snapshot |
| `Guardian` | Full name + initials; portal status; many-to-many students |
| `StaffMember` | Unique staff number; category/employment enums; subjects taught |
| `PersonDocument` | Person polymorph; media reference |
| `student_guardian` | Pivot (UUID pk, unique pair) |

## 7. Business Events

`StudentCreated`, `StudentUpdated`, `StudentStatusChanged`, `GuardianCreated`, `GuardianUpdated`, `StudentGuardianLinked`, `StudentGuardianUnlinked`, `StaffMemberHired`, `StaffMemberUpdated`, `StaffMemberStatusChanged`, `PersonAvatarUpdated`, `PersonDocumentAttached`, `PersonDocumentRemoved` → `audit_events`.

## 8. Workspaces

- **School workspace**: `people` section (students, guardians, staff).
- **Student profile**: linked guardians, documents, fee statement, report cards, attendance summary (cross-capability profile).

## 9. Interaction Patterns

| Surface | Pattern |
|---|---|
| Students list | Explorer (filters: campus/grade/status) |
| Student profile | Profile (tabs: overview, guardians, documents, finance) |
| Enroll student | Wizard (record → campus → grade → status) |
| Guardians | Explorer + link drawer |
| Staff | Explorer + profile |
| Documents | Drawer/manager per person |

## 10. Presentation Contracts

- **StudentResource**: `id, admission_number, full_name, avatar_initials, gender, date_of_birth, stage, grade_label, campus_id, campus_name, status, enrolled_on, guardians[], documents[]`
- **GuardianResource**: `id, full_name, avatar_initials, portal_status, primary_contact{email, phone}, linked_students[]`
- **StaffMemberResource**: `id, staff_number, full_name, avatar_initials, title, department, category, employment_type, subjects_taught[], campus_id, status, hired_on, login{user_id, active}`
- **PersonDocumentResource**: `id, person_type, person_id, media_id, kind, label, created_at`

## 11. Permissions

Keys (`config/people.php`): `people.students.read|write`, `people.guardians.read|write`, `people.staff.read|write`, `people.documents.read|write`.

| Ability | Gate |
|---|---|
| student CRUD/status/transfer/bulk | `people.students.write` |
| guardian CRUD/portal/bulk | `people.guardians.write` |
| link/unlink | **both** `students.write` + `guardians.write` (combined) |
| staff CRUD/status/login | `people.staff.write` |
| documents/avatar | `people.documents.write` + ownership check |

## 12. Notifications

- **Implemented**: portal-access provisioning (invitation email path exists).
- **Required (Ch. 35)**: guardian welcome/portal emails, status-change notices. **Gap — Phase 5.**

## 13. Realtime

- **Required (Ch. 21/33)**: roster updates pushed to attendance/gradebook surfaces. **Gap — Phase 7.**

## 14. Discovery

- **Implemented**: DB indexes (`admission_number`, `staff_number`, `tenant_id+status`).
- **Required**: fuzzy name search for the SPA. **Gap.**

## 15. AI

- **Required (Ch. 36)**: duplicate-record detection (same name+DOB), enrollment-pattern insights. **Gap.**

## 16. Observability

- **Implemented**: audit events.
- **Required**: transfer/status churn rates, portal-access failure tracking. **Gap.**

## 17. Testing

**Implemented (P0-1):**
- `tests/Feature/Api/V1/People/AuthorizationTest.php` (12 tests) — student/guardian/staff gates, combined link gate, cross-tenant 404.

**Required (Ch. 38):**
- Status machine matrix; transfer duplicate-number guard; portal provisioning flow; document ownership; soft-delete behaviour once P0-8 lands.

## Acceptance Criteria (DoD)

- [ ] Every write gated; combined link gate proven — **DONE (P0-1)**
- [ ] Transfer/status invariants under test
- [ ] Portal provisioning flow under test
- [ ] Soft-lifecycle standard adopted (P0-8)
- [ ] Suite green, Pint clean, no new PHPStan errors
