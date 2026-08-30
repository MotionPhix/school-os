# SchoolOS Capability Spec — Attendance

Status: **Implemented (API + tests) · gaps flagged below** · Source: `app/Domains/Attendance`, `config/attendance.php`, `routes/api/v1/attendance.php`

---

## 1. Capability

What institutional responsibility does this solve?
- **Daily attendance operations**: open a register for a course section (per date + period), mark each student present/absent/late/excused, submit the locked register, and roll marks up into per-student attendance summaries with at-risk detection.

## 2. Goals

What outcomes should users achieve?
- Open a register for any section/date/period in two clicks (roster pre-filled, default Present — teachers only flip exceptions).
- Mark students during the lesson and submit the register to lock it.
- See per-student attendance rates across a term, filtered by section/campus/grade/date range, with risk bands (at-risk <90%, critical <80%, perfect =100%).
- Reopen a submitted register only for corrections (audited).

## 3. Scope

**Included**
- Register lifecycle: open (draft) → mark (single/bulk) → submit (locked); reopen for corrections.
- Session-level counts (present/absent/late/excused/total), per-student rollups, risk bands.
- Idempotent open (same section+date+period returns the existing register).

**Explicitly excluded**
- Biometric/device check-in (marks are manual teacher entries; gateway integration is future).
- Late-arrival time tracking beyond `minutes_late` on a mark.
- Parent-facing absence notifications (see §12).

## 4. Actors

| Actor | Primary actions |
|---|---|
| Teacher | Opens registers, marks students, submits |
| Head of department / Principal | Views registers + summaries; authorizes reopens |
| Registrar | Corrections via reopen |

## 5. Business Rules

Grounded in services (`OpenAttendanceSession`, `SetAttendanceMark`, `SubmitAttendanceSession`, `ReopenAttendanceSession`, `BulkAttendanceAction`):

- **One register per (course_section, date, period)** — enforced by unique index + idempotent open (returns existing).
- **Empty roster → 422**: a register cannot be opened for a section with no enrolled students.
- **Roster snapshot**: opening pre-fills `AttendanceMark` rows for the section's current roster with status `present`.
- **Mark rules**: status ∈ `present | absent | late | excused`; `late` defaults `minutes_late` to `config('attendance.defaults.minutes_late')` (5) when omitted; note optional.
- **Submit locks the register**: status → `submitted`, `taken_at` set, counts recomputed server-side (`SessionCounts`); submit is idempotent.
- **Reopen** flips a submitted register back to draft for corrections (audited via event).
- **Delete rule**: only draft (unlocked) sessions can be deleted.
- **Summary** aggregates submitted sessions only — drafts never pollute rates.

## 6. Aggregates

| Aggregate | Invariants |
|---|---|
| `AttendanceSession` | Unique `(course_section_id, date, period)`; status `draft → submitted`; counts must equal the recomputed counts of its marks (recomputed on every submit/reopen) |
| `AttendanceMark` | Unique `(session_id, student_id)`; status enum; `minutes_late` only meaningful when status = `late` |

## 7. Business Events

`AttendanceSessionOpened`, `AttendanceSessionSubmitted`, `AttendanceSessionReopened`, `AttendanceMarkChanged` — all extend `BusinessEvent` → projected into `audit_events` (who opened/marked/submitted/reopened).

## 8. Workspaces

- **School workspace**: `attendance` section of the SPA (`/attendance` — register, sessions, summary routes).
- **Teacher quick-mark surface**: per-section day view (period grid) in the academics workspace.

## 9. Interaction Patterns

| Surface | Pattern |
|---|---|
| Register (open) | Wizard-lite: pick section + date + period → opens pre-filled register |
| Register marking | Explorer/editor: roster table, per-row status toggle + minutes_late + note; bulk row ops |
| Sessions list | Explorer (date/section filters, status badges) |
| Summary | Dashboard: per-student rates, risk-band lists, filters |

## 10. Presentation Contracts

Backed by resources (`AttendanceSessionResource`, `AttendanceMarkResource`, summary payload):

- **AttendanceSessionResource**: `id, course_section_id, subject_code, subject_name, section_label, teacher_name, date, period, status, present_count, absent_count, late_count, excused_count, total_count, taken_at, updated_at, marks[]`
- **AttendanceMarkResource**: `id, student_id, student_name, student_initials, status, minutes_late, note, marked_by, updated_at`
- **Summary rows**: `student_id, student_name, student_initials, grade_label, sessions, present, absent, late, excused, attendance_rate, risk_band`

## 11. Permissions

Keys (`config/attendance.php`): `attendance.sessions.read|write`, `attendance.marks.write`, `attendance.summary.read`.

| Ability | Gate |
|---|---|
| viewAny/view | `attendance.sessions.read` |
| open | `attendance.sessions.write` |
| mark / markBulk | `attendance.marks.write` **and** `update` on the session (i.e. also `sessions.write`) — combined gate |
| submit / reopen | `attendance.sessions.write` |
| summary | **dedicated `attendance.summary.read`** (via `AttendanceSessionPolicy::viewSummary`) |
| delete | `attendance.sessions.write` + draft only |

*P0-1 note: summary previously gated on `sessions.read` (catalog key `summary.read` was dead) and its raw joins lacked explicit tenant filters on joined tables — both fixed.*

## 12. Notifications

- **Implemented**: none beyond audit projection.
- **Required (handbook Ch. 35)**: guardian absence alerts (per-tenant opt-in), daily register reminders for teachers with unsubmitted registers (cron), summary digests. Route through Business Events → Notification Policies. **Gap — Phase 5.**

## 13. Realtime

- **Implemented**: none.
- **Required (handbook Ch. 21/33)**: live register presence when multiple staff mark the same session; count bars update in real time. **Gap — Phase 7.**

## 14. Discovery

- **Implemented**: none (DB indexes: `tenant_id+date`, `tenant_id+course_section_id+date`, `tenant_id+status`).
- **Required**: per-student attendance history views are covered by indexes; term rollups may need a materialized view at scale. **Gap.**

## 15. AI

- **Implemented**: none.
- **Required (handbook Ch. 36)**: attendance-rate trend context for the insights module; "chronic absence" flagging (≥3 unexcused in a window); predictive at-risk before thresholds hit. **Gap.**

## 16. Observability

- **Implemented**: audit events (open/submit/reopen/mark changes), request logging.
- **Required**: unsubmitted-register lag (per teacher), reopen rate (correction churn), mark edit frequency after submission, summary query latency. **Gap.**

## 17. Testing

**Implemented (P0-1):**
- `tests/Feature/Api/V1/Attendance/AuthorizationTest.php` (8 tests): open gate ±, mark gate ± (combined gate proven), submit gate, summary dedicated-key gate ±, cross-tenant 404.

**Required (per handbook Ch. 38):**
- Idempotent open returns the same session; empty-roster 422.
- Roster snapshot: marks created for roster only, default present.
- Submit: counts recomputed; idempotent; `taken_at` set; marks locked (marks.write on submitted → 422).
- Reopen → draft + counts preserved; delete draft only.
- Summary: submitted-only aggregation; risk-band boundaries (90/80/100); filter correctness (section/campus/grade/from/to).
- End-to-end: SPA register → mark → submit → summary reflects data.

## Acceptance Criteria (Definition of Done)

- [ ] Every write/verb endpoint gated (controller + FormRequest + policy) — **DONE (P0-1)**
- [ ] Dedicated `summary.read` key enforced + tested — **DONE (P0-1)**
- [ ] Joined-table tenant filters present (defense in depth) — **DONE (P0-1)**
- [ ] Register lifecycle (open → mark → submit → reopen) covered by tests
- [ ] Summary aggregation + risk bands covered by tests
- [ ] Suite green, Pint clean, no new PHPStan errors
