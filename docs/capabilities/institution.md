# SchoolOS Capability Spec — Institution

Status: **Implemented (API + tests) · gaps flagged below** · Source: `app/Domains/Institution`, `config/institution.php`, `routes/api/v1/institution.php`

---

## 1. Capability

What institutional responsibility does this solve?
- **School identity & academic calendar**: the institution profile (branding), campus organization, academic years, terms, and the school-wide calendar.
- Owns the temporal skeleton every other capability hangs off (terms feed assessments/finance; years gate sectioning).

## 2. Goals

What outcomes should users achieve?
- Maintain a branded institution profile (name, type, accreditation, logo).
- Model multi-campus structure; designate a primary campus.
- Run the academic calendar: open a year, run its terms, close the year cleanly.

## 3. Scope

**Included**
- Institution profile (show/update/logo), campuses (CRUD, bulk status, primary designation), academic years (CRUD, transition, set-current), terms (CRUD per year, transitions), calendar events (CRUD, publish, bulk).

**Explicitly excluded**
- Room/venue management (belongs to facilities, future).
- Timetable (Academics capability).
- Grade/curriculum structure (People/Academics).

## 4. Actors

| Actor | Primary actions |
|---|---|
| Principal | Profile branding, year/term transitions |
| Registrar | Campus + calendar administration |
| `it.admin` | Setup-time configuration |

## 5. Business Rules

Grounded in services (`UpsertInstitutionProfile`, `WriteCampus`, `BulkCampusAction`, `WriteAcademicYear`, `TransitionAcademicYear`, `SetCurrentAcademicYear`, `WriteTerm`, `TransitionTerm`, `WriteCalendarEvent`):

- **Profile is a tenant singleton** — show/update act on the one row; logo via media service.
- **Campus**: unique `code` per tenant; one `is_primary`; status `operational | maintenance | closed`; primary campus cannot be deleted (policy).
- **Academic year**: unique `label` per tenant; status `planning → active → closed`; only one current year; delete only while planning.
- **Term**: unique `(year, sequence)`; status `upcoming → in_progress → completed`; delete blocked while in progress (policy, P0-1 hardening).
- **Calendar events**: belong to a year (+ optional campus); required kind; publish flips draft → published.
- All entities are tenant-scoped (global scope → cross-tenant 404).

## 6. Aggregates

| Aggregate | Invariants |
|---|---|
| `InstitutionProfile` | Singleton per tenant; name/type/accreditation snapshot |
| `Campus` | Unique code; `is_primary` single; status machine |
| `AcademicYear` | Unique label; status machine; `is_current` single |
| `Term` | Unique `(year, sequence)`; status machine |
| `CalendarEvent` | Year-bound; kind enum; draft → published |

## 7. Business Events

`InstitutionProfileUpdated`, `CampusCreated`, `CampusUpdated`, `AcademicYearOpened`, `AcademicYearClosed`, `AcademicYearStatusChanged`, `CurrentAcademicYearChanged`, `TermStatusChanged`, `CalendarEventPublished` → `audit_events`.

## 8. Workspaces

- **School workspace**: `institution` section (profile, campuses, academic years, calendar).
- Calendar events surface in the school shell (upcoming widget).

## 9. Interaction Patterns

| Surface | Pattern |
|---|---|
| Profile | Editor (branding + logo upload) |
| Campuses | Explorer + editor (primary badge) |
| Academic years | Explorer + transition wizard |
| Calendar | Dashboard (month view, event drawer) |

## 10. Presentation Contracts

- **InstitutionProfileResource**: `id, name, short_name, type, accreditation_status, established_year, currency_code, timezone, languages_of_instruction[], logo_path, theme_color`
- **CampusResource**: `id, code, name, status, is_primary, address_line, city, region, timezone`
- **AcademicYearResource**: `id, label, status, is_current, starts_on, ends_on, terms[]`
- **TermResource**: `id, name, sequence, status, starts_on, ends_on`
- **CalendarEventResource**: `id, title, kind, starts_on, ends_on, all_day, audience, campus_id, status`

## 11. Permissions

Keys (`config/institution.php`): `institution.profile.read|write`, `institution.campuses.read|write`, `institution.years.read|write`, `institution.calendar.read|write`.

| Ability | Gate |
|---|---|
| profile update/logo | `institution.profile.write` |
| campus CRUD/bulk | `institution.campuses.write` (primary-delete guard in policy) |
| year CRUD/transition/set-current | `institution.years.write` |
| term CRUD/transition | `institution.years.write` (in-progress delete guard in policy) |
| calendar CRUD/bulk | `institution.calendar.write` |

## 12. Notifications

- **Required (Ch. 35)**: term/year start reminders, calendar-event notifications. **Gap — Phase 5.**

## 13. Realtime

- **Required (Ch. 21/33)**: calendar sync across staff. **Gap — Phase 7.**

## 14. Discovery

- **Implemented**: DB indexes (`tenant_id+code`, `tenant_id+status`, `tenant_id+label`).
- **Required**: none urgent (small data volume).

## 15. AI

- **Required (Ch. 36)**: term-planning suggestions from past years. **Gap.**

## 16. Observability

- **Implemented**: audit events, request logs.
- **Required**: year/term transition failure rates, profile-change audit. **Gap.**

## 17. Testing

**Implemented (P0-1):**
- `tests/Feature/Api/V1/Institution/AuthorizationTest.php` (16 tests) — profile/campus/year/term/calendar gates, primary-campus delete guard, in-progress term delete guard, cross-tenant 404.

**Required (Ch. 38):**
- Year/term transition state machines; single-current-year invariant; campus primary reassignment; calendar publish flow.

## Acceptance Criteria (DoD)

- [ ] Every write gated; guards enforced via policy — **DONE (P0-1)**
- [ ] Single-current-year invariant under test
- [ ] Year/term transition matrix under test
- [ ] Suite green, Pint clean, no new PHPStan errors
