# SchoolOS Capability Spec — Academics (Subjects, Courses, Gradebook, Timetable)

Status: **Implemented (API + tests) · gaps flagged below** · Source: `app/Domains/Academics`, `config/academics.php`, `routes/api/v1/academics.php`

---

## 1. Capability

What institutional responsibility does this solve?
- **The teaching skeleton**: subject catalog, course sectioning, student enrollment, continuous-assessment gradebook, and the conflict-free weekly timetable.
- Owns the section roster — the root of attendance, exams, and gradebook integrity.

## 2. Goals

What outcomes should users achieve?
- Maintain a subject catalog and section students into year/campus/subject cohorts with teachers and capacity.
- Record continuous-assessment grades that band consistently and feed term report cards.
- Schedule lessons without teacher/room/grade-level clashes.

## 3. Scope

**Included**
- Subjects (CRUD, bulk), course sections (CRUD, status, duplicate, bulk, roster view), enrollment (enroll/drop), gradebook (upsert, bulk, curve), timetable (schedule, move, remove).

**Explicitly excluded**
- Lesson plans / curriculum content; exam papers (Assessments); room/venue master data.

## 4. Actors

| Actor | Primary actions |
|---|---|
| Registrar | Subject catalog, sectioning |
| Teacher | Grades (gradebook), views timetable |
| Principal | Curriculum oversight (read + approvals) |

## 5. Business Rules

Grounded in services (`WriteSubject`, `WriteCourseSection`, `EnrollStudentInCourse`, `UpsertGradebookEntry`, `BulkGradebookAction`, `ScheduleTimetableSlot`, `BulkAcademicsAction`):

- **Subjects**: `code` unique per tenant, **case-insensitive** (P0-1 friendly 422); category/stages enums.
- **Sections**: unique `(tenant, year, subject, section_label)` (P0-1 friendly 422); capacity enforced at enroll; status machine (draft → scheduled → active → closed).
- **Enrollment**: no duplicates (P0-1 fix — 422 instead of silent no-op); capacity check; drop removes roster entry.
- **Gradebook**: entry unique `(section, term, student)`; **roster check** — only enrolled students can be graded (P0-1 fix); `total = clamp(ca + exam, 0, max)`; band derived server-side.
- **Timetable**: slot unique `(section, weekday, period)`; **collision guards** on teacher (same weekday+period), room, and grade-level overlap; move re-validates collisions.
- All entities tenant-scoped (cross-tenant 404).

## 6. Aggregates

| Aggregate | Invariants |
|---|---|
| `Subject` | Unique code; category/stages; is_core flag |
| `CourseSection` | Identity unique; teacher FK; capacity; status machine |
| `CourseEnrollment` | Pivot (UUID pk); unique `(section, student)`; enrolled_at |
| `GradebookEntry` | Unique `(section, term, student)`; ca/exam/total/band consistency |
| `TimetableSlot` | Unique `(section, weekday, period)`; room/teacher/grade conflict-free |

## 7. Business Events

`SubjectCreated`, `SubjectUpdated`, `CourseSectionCreated`, `CourseSectionUpdated`, `CourseSectionStatusChanged`, `CourseEnrollmentAdded`, `CourseEnrollmentRemoved`, `GradebookEntryRecorded`, `TimetableSlotScheduled`, `TimetableSlotRemoved` → `audit_events`.

## 8. Workspaces

- **School workspace**: `academics` section (subjects, courses, timetable, gradebook).
- **Section profile**: roster, gradebook, timetable, attendance, exam results (cross-capability).

## 9. Interaction Patterns

| Surface | Pattern |
|---|---|
| Subjects | Explorer + editor |
| Courses/sections | Explorer + profile (roster table) |
| Timetable | Grid/dashboard (weekday × period, per grade) |
| Gradebook | Spreadsheet-like editor (rows=students, cols=terms) |

## 10. Presentation Contracts

- **SubjectResource**: `id, code, name, category, stages[], is_core, credit_hours`
- **CourseSectionResource**: `id, academic_year_id, campus_id, campus_name, subject_id, subject_code, subject_name, grade_label, section_label, teacher_id, teacher_name, capacity, enrollment_count, status, room`
- **GradebookEntryResource**: `id, course_section_id, term_id, student_id, student_name, continuous_assessment, exam_score, total, band, remarks`
- **TimetableSlotResource**: `id, course_section_id, weekday, period, starts_at, ends_at, room, teacher_name`

## 11. Permissions

Keys (`config/academics.php`): `academics.subjects.read|write`, `academics.courses.read|write`, `academics.timetable.read|write`, `academics.gradebook.read|write`.

| Ability | Gate |
|---|---|
| subject CRUD/bulk | `academics.subjects.write` |
| section CRUD/duplicate/enroll | `academics.courses.write` (enroll also gates update on section) |
| gradebook upsert/bulk/curve | `academics.gradebook.write` |
| timetable schedule/move | `academics.timetable.write` |

## 12. Notifications

- **Required (Ch. 35)**: enrollment-added notices to teachers, timetable-change alerts. **Gap — Phase 5.**

## 13. Realtime

- **Required (Ch. 21/33)**: gradebook co-edit presence, timetable conflict push. **Gap — Phase 7.**

## 14. Discovery

- **Implemented**: DB indexes (subject code, section identity, timetable `tenant+weekday+period`).
- **Required**: subject/section search. **Gap.**

## 15. AI

- **Required (Ch. 36)**: sectioning suggestions from enrollment demand, grade-trend flags. **Gap.**

## 16. Observability

- **Implemented**: audit events.
- **Required**: timetable collision attempt rate, grade-edit churn. **Gap.**

## 17. Testing

**Implemented (P0-1):**
- `tests/Feature/Api/V1/Academics/AuthorizationTest.php` (16 tests) — gates + **roster-grade 422**, **duplicate-enroll 422**, duplicate code/label 422, cross-tenant 404.

**Required (Ch. 38):**
- Timetable collision matrix (teacher/room/grade × schedule/move); capacity boundary (exact capacity, +1); grade banding property tests; bulk gradebook semantics.

## Acceptance Criteria (DoD)

- [ ] Every write gated — **DONE (P0-1)**
- [ ] Roster/duplicate/collision invariants under test — **DONE (P0-1, partial; collision matrix pending)**
- [ ] Grade banding property tests
- [ ] Suite green, Pint clean, no new PHPStan errors
