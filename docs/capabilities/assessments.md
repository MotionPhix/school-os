# SchoolOS Capability Spec — Assessments & Exams

Status: **Implemented (API + tests) · gaps flagged below** · Source: `app/Domains/Assessments`, `config/assessments.php`, `routes/api/v1/assessments.php`

---

## 1. Capability

What institutional responsibility does this solve?
- **Assessment operations**: plan exam windows within terms, schedule exam papers against course sections, record and revise per-student scores, publish results, and roll published results into term report cards.
- Owns the guarantee that **no partial marksheet ever reaches parents**: publishing is blocked until every enrolled student is graded.

## 2. Goals

What outcomes should users achieve?
- Schedule an exam window (e.g. "Term 1 Mid-Term") and place every paper (subject × section) into it.
- Mark each student's paper with a score that is server-clamped and banded consistently.
- Publish results only when the marksheet is complete; see the completeness guard before publishing.
- View per-term report cards aggregating published results, per student, per subject.

## 3. Scope

**Included**
- Exam periods (CRUD + status), exams (CRUD + status machine), results (single/bulk/fill/curve), term report cards (read-only rollup).
- Band computation (`BandForExam`), pass-mark colouring, publish-completeness guard.

**Explicitly excluded**
- Question-paper content management (papers are metadata only — no question bank).
- Timetabling of exam *rooms/venues* beyond `room` + `scheduled_on`/`starts_at` strings (no venue conflict engine).
- Student-report-card printing/PDF generation (currently API JSON only; SPA renders).
- Gradebook (continuous assessment) — lives in Academics; report cards consume both.

## 4. Actors

| Actor | Primary actions |
|---|---|
| Principal | Views periods/exams/report cards (`read` keys); publishes via delegate |
| Registrar / Exams Officer | Creates periods, schedules papers, records results, publishes |
| Teacher | Records results for their section's papers (if granted `assessments.results.write`) |
| Portal parent | (future) Views published report cards via portal |

## 5. Business Rules

Grounded in services (`SetExamStatus`, `SetExamResult`, `BulkSetExamResults`, `BuildTermReportCards`):

- **Exam state machine**: `draft → scheduled | marking`; `scheduled → draft | marking`; `marking → scheduled | published`; `published` is terminal and **locked** (`ExamStatus::isLocked()`). Invalid transitions → 422.
- **Publish completeness**: publishing requires every student on the section roster to have a graded result; otherwise 422 `"Cannot publish — N of M students graded."`
- **Score integrity**: scores are clamped to `[0, max_score]` and re-banded server-side; `null` score = unmark; band derived from `BandForExam`.
- **Locked exams** reject all result writes (422).
- **Roster check**: a result can only be recorded for a student on the exam's course-section roster.
- **Tenant check**: exam and student must share a tenant.
- **Delete rule**: only `draft` exams with zero recorded results can be deleted.
- **Defaults** (`config/assessments.php`): `max_score` 100, `pass_mark` 40, `duration_minutes` 90.
- Exam period identity: unique per `(tenant, term, name)`; exam identity: unique per `(tenant, period, course_section)`.

## 6. Aggregates

| Aggregate | Invariants |
|---|---|
| `ExamPeriod` | Term-bound window: `starts_on ≤ ends_on`; status `draft → active → closed` (via `SetExamPeriodStatus`); unique name per term |
| `Exam` | Belongs to exactly one period + section; status machine (see §5); `max_score ≥ pass_mark` enforced at write; holds `published_at/published_by` |
| `ExamResult` | Unique `(exam_id, student_id)`; `score ∈ [0, max_score] ∪ {null}`; `band` must agree with `score` |

## 7. Business Events

`ExamCreated`, `ExamUpdated`, `ExamStatusChanged` (carries from/to), `ExamPublished`, `ExamResultRecorded`, `ExamPeriodCreated`, `ExamPeriodStatusChanged` — all extend `BusinessEvent` and are projected into `audit_events` by the wildcard listener.

## 8. Workspaces

- **School workspace** (tenant shell): `assessments` section of the SPA (`/assessments` — exams, periods, reports routes).
- Report cards are also surfaced in the **Student profile** (read-only rollup).

## 9. Interaction Patterns

| Surface | Pattern |
|---|---|
| Periods list | Explorer (filterable list, status badges) |
| Period create/edit | Wizard (dates + name → save) |
| Exams list per period | Explorer grouped by period |
| Exam detail | Profile with marksheet table (per-student score inputs) |
| Results entry | Explorer/editor (row editing, bulk paste, fill, curve) |
| Report cards | Dashboard (per-student cards, term selector) |

## 10. Presentation Contracts

Backed by resources (`ExamResource`, `ExamPeriodResource`, `ExamResultResource`, `StudentReportCardResource`):

- **ExamResource**: `id, period_id, period_name, course_section_id, subject_code, subject_name, grade_label, section_label, teacher_name, paper_title, scheduled_on, starts_at, duration_minutes, room, max_score, pass_mark, status, result_count, updated_at, results[]`
- **AttendanceSessionResource** (sibling pattern): `id, course_section_id, subject_code, subject_name, section_label, teacher_name, date, period, status, present_count, absent_count, late_count, excused_count, total_count, taken_at, updated_at, marks[]`
- **StudentReportCardResource**: per student + term: subjects × (score, band, grade), totals, teacher comments (free-form remarks), attendance snapshot.

## 11. Permissions

Keys (`config/assessments.php`): `assessments.periods.read|write`, `assessments.exams.read|write`, `assessments.results.write`, `assessments.publish`, `assessments.reports.read`.

| Ability | Gate |
|---|---|
| viewAny/view (exams, periods) | `.read` key |
| create/update (exams, periods) | `.write` key + `!status.isLocked()` for update |
| publish | **dedicated `assessments.publish`** key |
| results set/bulk/fill/curve | `assessments.results.write` (+ update on exam via controller) |
| report cards | `viewAny(Exam)` **and** dedicated `assessments.reports.read` (via `ExamPolicy::viewReports`) |
| delete | `.write` + status draft + zero results |

*P0-1 note: `ReportCardController` previously hand-rolled membership→permission resolution; replaced with the policy abstraction (dead-code removal).*

## 12. Notifications

- **Implemented**: none beyond audit projection (results/publish flow is in-app only).
- **Required (handbook Ch. 35)**: "results published" notification to guardians (email/SMS via portal), "report card available" push. Route through Business Events → Notification Policies (per-tenant channel config). **Gap — Phase 5.**

## 13. Realtime

- **Implemented**: none.
- **Required (handbook Ch. 21/33)**: marksheet co-editing presence; live "N of M graded" progress on publish; broadcast `ExamPublished` to the tenant channel. **Gap — Phase 7.**

## 14. Discovery

- **Implemented**: none (DB indexes only: `tenant_id+period_id`, `tenant_id+course_section_id+scheduled_on`, `tenant_id+status`).
- **Required**: report cards are natural documents for tenant-scoped search (Scout/Meilisearch) once the SPA search is built. **Gap.**

## 15. AI

- **Implemented**: none.
- **Required (handbook Ch. 36)**: per-exam performance context for the insights module; "at-risk cohort" suggestions from band distributions; teacher remark drafting from score patterns. **Gap.**

## 16. Observability

- **Implemented**: audit events (who published when, score changes), API request logging.
- **Required**: publish-failure rate (completeness guard), results-edit churn per exam, report-card generation latency, alerts on repeated publish blocks. **Gap.**

## 17. Testing

**Implemented (P0-1):**
- `tests/Feature/Api/V1/Assessments/AuthorizationTest.php` (7 tests): store gate ±, results gate, publish dedicated-key gate, report-card dedicated-key gate, cross-tenant 404.

**Required (per handbook Ch. 38):**
- State-machine tests: every transition ± invalid transitions; publish completeness (0 of M, M-1 of M, M of M).
- Score clamping/banding property tests; unmark (`null`) behaviour.
- Bulk results: fill (complete gaps), curve (monotonic), bulk overwrite semantics.
- Report-card rollup contract test (published results only; drafts excluded).
- End-to-end: SPA marksheet → publish → report card reflects data.

## Acceptance Criteria (Definition of Done)

- [ ] Every write/verb endpoint gated (controller + FormRequest + policy) — **DONE (P0-1)**
- [ ] Dedicated keys (`publish`, `reports.read`) enforced and tested — **DONE (P0-1)**
- [ ] State machine + publish-completeness guard covered by tests
- [ ] Locked-exam writes and roster checks covered by tests
- [ ] Report-card resource contract verified against real published data
- [ ] No partial marksheet can reach report cards (property holds under test)
- [ ] Suite green, Pint clean, no new PHPStan errors
