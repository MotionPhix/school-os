# SchoolOS Capability Spec — Insights (Analytics & Reporting)

Status: **Implemented (read-only readers) · gaps flagged below** · Source: `app/Domains/Insights`, `config/insights.php`, `routes/api/v1/insights.php`

---

## 1. Capability

What institutional responsibility does this solve?
- **Leadership decision support**: aggregated, cross-domain snapshots — academic performance, enrollment health, financial position, and institutional counts.
- Owns the "single source of truth" for headline numbers; deliberately read-only so it never corrupts operational data.

## 2. Goals

What outcomes should users achieve?
- Answer "how are we doing" in one screen: pass rates by subject, enrollment trends, cash position + receivables, and school-wide counts.
- Drill into the same filters (campus/grade/term) used everywhere else.

## 3. Scope

**Included**
- Four readers: `AcademicReportReader` (results/grade distributions), `EnrollmentReportReader` (trends), `FinancialInsightsReader` (cash position, AR aging), `InstitutionSnapshotReader` (counts). All tenant-scoped, read-only.

**Explicitly excluded**
- BI tooling / ad-hoc SQL surface, exports (CSV/PDF), cross-tenant anonymized benchmarking (future), real-time streaming dashboards.

## 4. Actors

| Actor | Primary actions |
|---|---|
| Principal | All four dashboards |
| Bursar | Financial insights only |
| Platform (future) | Cross-tenant anonymized benchmarking |

## 5. Business Rules

Grounded in readers (`AcademicReportReader`, `EnrollmentReportReader`, `FinancialInsightsReader`, `InstitutionSnapshotReader`):

- **Read-only**: readers never mutate; no write routes exist.
- **Data quality gate**: only authoritative states feed metrics — published exams (not drafts), submitted attendance sessions, issued invoices (not drafts).
- **Tenant-scoped**: every aggregation filters by the active tenant.
- **Time-bounded**: term/year filters default to the current academic year.
- **Deterministic**: identical inputs → identical outputs (no sampling).

## 6. Aggregates

None — read models computed from source aggregates (no tables of its own).

## 7. Business Events

None emitted (read-only capability).

## 8. Workspaces

- **School workspace**: `insights` section (academic, enrollment, financial, institution dashboards).

## 9. Interaction Patterns

| Surface | Pattern |
|---|---|
| Academic | Dashboard (subject pass rates, band distribution) |
| Enrollment | Dashboard (line/bar trends, headcount) |
| Financial | Dashboard (cash position, AR aging) |
| Institution | Dashboard (counts: students, staff, campuses, sections) |

## 10. Presentation Contracts

Reader payloads (no dedicated resource classes):

- **Academic**: `per_subject[{subject_code, subject_name, examined, passed, pass_rate}], band_distribution[{band, count}], overall_pass_rate`
- **Enrollment**: `total_students, by_grade[{grade_label, count}], by_campus[{campus_name, count}], trend[{term_label, count}]`
- **Financial**: `cash_balance_minor, receivables_minor, ar_aging[{bucket, amount_minor}], collected_minor, outstanding_minor`
- **Institution**: `students_count, staff_count, campuses_count, sections_count, guardians_count`

## 11. Permissions

Keys (`config/insights.php`): `insights.institution.read`, `insights.enrollment.read`, `insights.academic.read`, `insights.financial.read`.

| Ability | Gate |
|---|---|
| academic dashboard | `insights.academic.read` |
| enrollment dashboard | `insights.enrollment.read` |
| financial dashboard | `insights.financial.read` |
| institution snapshot | `insights.institution.read` |

## 12. Notifications

None (dashboards only).

## 13. Realtime

- **Required (Ch. 21/33)**: dashboard refresh on data events (reports published, payments posted). **Gap — Phase 7.**

## 14. Discovery

- **Required**: no search surface; consider materialized views at scale. **Gap.**

## 15. AI

- **Required (Ch. 36)**: trend forecasting (enrollment, cash flow), at-risk cohort identification from academic data. **Gap.**

## 16. Observability

- **Required**: reader latency + cache hit rates; dashboard load alerts. **Gap.**

## 17. Testing

**Implemented:** none yet (module is read-only; gate verification confirmed authorized readers).

**Required (Ch. 38):**
- Reader contract tests: given seeded authoritative data, exact expected aggregates; draft/void data excluded; tenant isolation of aggregates; determinism.

## Acceptance Criteria (DoD)

- [ ] Every reader gated by its dedicated key (verified) — **DONE (P0-1)**
- [ ] Reader contract tests with authoritative-only data
- [ ] Tenant isolation of every aggregate
- [ ] Suite green, Pint clean, no new PHPStan errors
