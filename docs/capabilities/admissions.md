# SchoolOS Capability Spec — Admissions

Status: **Implemented (API + tests) · gaps flagged below** · Source: `app/Domains/Admissions`, `config/admissions.php`, `routes/api/v1/admissions.php`

---

## 1. Capability

What institutional responsibility does this solve?
- **The applicant pipeline**: capture enquiries, advance applicants through stages, assess, offer, and convert accepted applicants into enrolled students.
- Owns the auditability of every stage change and offer decision.

## 2. Goals

What outcomes should users achieve?
- Track every applicant's journey from enquiry to enrollment on one board.
- Make data-driven admission decisions (scores + interviews recorded), issue offers with fees, and convert accepted applicants into students with one action.

## 3. Scope

**Included**
- Applications (CRUD, stage advancement, assessment scores, bulk actions), offers (send/respond), enrollment conversion, timeline audit.

**Explicitly excluded**
- Entrance-exam content (scores only), waitlists, scholarship workflows (future).

## 4. Actors

| Actor | Primary actions |
|---|---|
| Admissions officer | Pipeline management, scores, offers |
| Principal | Offer approval (dedicated key) |
| Registrar | Enrollment conversion |
| Guardian (future portal) | Responds to offers |

## 5. Business Rules

Grounded in services (`WriteApplication`, `AdvanceApplicationStage`, `StageTransitionGuard`, `RecordAssessmentScores`, `SendOffer`, `RespondToOffer`, `EnrollApplication`, `BulkAdmissionsAction`):

- **Pipeline** (`StageTransitionGuard`): `enquiry → application → assessment → interview → offer → accepted → enrolled`; reject/withdraw allowed from most stages; invalid transitions → 422.
- **Offers**: one live offer per application; `draft → sent → accepted | declined`; accept → stage `accepted`; decline → `withdrawn`.
- **Audit integrity**: offer responses record the **authenticated user** as actor (P0-1 fix — client-supplied names ignored).
- **Enrollment conversion**: `EnrollApplication` creates the student record (reusing People invariants) and links guardians; idempotent per application.
- **Scores**: recorded per assessment type; used at report/decision time; tenant-scoped.
- **Reference**: unique per tenant (auto-generated).
- All entities tenant-scoped (cross-tenant 404).

## 6. Aggregates

| Aggregate | Invariants |
|---|---|
| `Application` | Unique reference; applicant snapshot (name, DOB, gender, guardian); intended campus/year/grade; stage machine |
| `ApplicationOffer` | One live offer; fee amount + currency; status machine |
| `ApplicationStageEvent` | Append-only timeline (stage from/to, actor, note) |
| `AssessmentRecord` | Per application + assessment type |

## 7. Business Events

`ApplicationCreated`, `ApplicationUpdated`, `ApplicationStageAdvanced`, `ApplicationScoresRecorded`, `OfferSent`, `OfferResponded`, `ApplicationEnrolled` → `audit_events`.

## 8. Workspaces

- **School workspace**: `admissions` section (pipeline board, applications, offers).
- **Applicant profile**: timeline, scores, offer status.

## 9. Interaction Patterns

| Surface | Pattern |
|---|---|
| Pipeline | Kanban-style explorer (stage columns) |
| Application profile | Profile (tabs: details, timeline, scores, offer) |
| New application | Wizard |
| Offer composer | Editor (fee, deadline) |

## 10. Presentation Contracts

- **ApplicationResource**: `id, reference, applicant_full_name, avatar_initials, date_of_birth, gender, guardian_name, campus_id, campus_name, academic_year_id, intended_stage, intended_grade_label, source, stage, status, timeline[], current_offer`
- **OfferSummaryResource**: `id, status, fee_amount, currency_code, sent_at, responded_at`
- **StageEventResource**: `id, stage_from, stage_to, actor_id, actor_name, note, occurred_at`

## 11. Permissions

Keys (`config/admissions.php`): `admissions.applications.read|write`, `admissions.offers.write`, `admissions.enroll`.

| Ability | Gate |
|---|---|
| application CRUD/advance/scores/bulk | `admissions.applications.write` |
| send/respond offer | **dedicated `admissions.offers.write`** |
| enroll accepted applicant | **dedicated `admissions.enroll`** |

## 12. Notifications

- **Required (Ch. 35)**: offer-issued email to guardian, interview scheduling. **Gap — Phase 5.**

## 13. Realtime

- **Required (Ch. 21/33)**: pipeline board live updates. **Gap — Phase 7.**

## 14. Discovery

- **Implemented**: DB indexes (`reference`, `tenant_id+stage`, `tenant_id+campus_id`).
- **Required**: applicant search. **Gap.**

## 15. AI

- **Required (Ch. 36)**: applicant-risk scoring, capacity-aware offer suggestions. **Gap.**

## 16. Observability

- **Implemented**: append-only stage timeline (audit).
- **Required**: stage-dwell times (applicants stuck at a stage), conversion funnel rates. **Gap.**

## 17. Testing

**Implemented (P0-1):**
- `tests/Feature/Api/V1/Admissions/AuthorizationTest.php` (5 tests) — application gates, advance verb, **offer-response audit integrity**, cross-tenant 404.

**Required (Ch. 38):**
- Full stage-transition matrix (± every edge); one-live-offer invariant; enrollment conversion (student + guardian links created); duplicate-conversion guard.

## Acceptance Criteria (DoD)

- [ ] Every write gated; audit actor integrity — **DONE (P0-1)**
- [ ] Stage-transition matrix under test
- [ ] Conversion creates valid People records
- [ ] Suite green, Pint clean, no new PHPStan errors
