# SchoolOS Capability Spec — Communications

Status: **Implemented (API + tests) · gaps flagged below** · Source: `app/Domains/Communications`, `config/communications.php`, `routes/api/v1/communications.php`

---

## 1. Capability

What institutional responsibility does this solve?
- **School-wide and direct communication**: compose/schedule/send announcements to audiences, run SMS/email broadcast campaigns with delivery stats, operate staff↔guardian message threads, and serve the per-user notification feed (topbar bell).
- Owns the tenant's outbound messaging surface — who can send, to whom, on which channel, and what the delivery outcome was.

## 2. Goals

What outcomes should users achieve?
- Draft an announcement once, schedule it, and send it to a defined audience (whole school / staff / class / custom).
- Run a broadcast campaign (e.g. fee reminder SMS) with recipient/delivery/cost visibility.
- Hold asynchronous staff↔guardian conversations on a thread with read tracking.
- See unread/at-a-glance activity in the notification feed without leaving the workspace.

## 3. Scope

**Included**
- Announcements (CRUD, send, archive, unschedule, bulk), broadcasts (CRUD, start/cancel/complete, duplicate, bulk), message threads (open, reply, status, mark read, bulk), overview KPI board, notification feed.

**Explicitly excluded**
- Actual channel delivery: announcements/broadcasts record intent + stats; SMS/email gateway integration (SMS `paychangu`/mailer) is not yet wired — delivery counts come from the reader layer, not a real gateway.
- Guardian-portal outbound inbox (portal is future).
- Group chat / realtime messaging.

## 4. Actors

| Actor | Primary actions |
|---|---|
| Principal | Sends announcements (dedicated `send` key); sees overview |
| Registrar / Admin | Drafts + schedules announcements, runs broadcasts, replies on threads |
| Teacher | Replies on guardian threads (`threads.write`), sees announcements |
| Guardian (future portal) | Replies on threads, receives announcements |

## 5. Business Rules

Grounded in services (`WriteAnnouncement`, `SendAnnouncement`, `ArchiveAnnouncement`, `UnscheduleAnnouncement`, `WriteBroadcast`, `StartBroadcast`, `CancelBroadcast`, `CompleteBroadcast`, `DuplicateBroadcast`, `OpenMessageThread`, `ReplyToThread`, `SetThreadStatus`, `MarkThreadRead`, `BulkCommunicationsAction`, `CommunicationsOverviewReader`, `NotificationFeedReader`):

- **Announcement lifecycle**: `draft → sent` (send), `sent/draft → archived` (archive); `unschedule` only valid while scheduled and not sent.
- **Audience**: enum (`whole_school | staff | teachers | students | guardians | class | custom`) with a resolved `audience_label` snapshot.
- **Channels**: enum list (`in_app | email | sms`); archived announcements stop appearing in the active list.
- **Broadcast lifecycle**: `draft → started → completed`; `cancel` halts an in-flight broadcast; `complete` reuses the `start` ability (operational gate).
- **Cost estimates**: draft broadcasts show a blended cost estimate (`config('communications.sms_cost_minor_per_recipient')`, MWK 25/recipient) until the gateway returns a real settlement cost.
- **Threads**: status `open | resolved`; replies snapshot `author_name` + `author_role`; read tracking per participant; `last_message_preview` capped at 240 chars.
- **Overview board** aggregates: sent/archived announcements, active broadcasts, open threads, unread counts.
- **Feed** is derived per user and permission-filtered per source (no dedicated key).

## 6. Aggregates

| Aggregate | Invariants |
|---|---|
| `Announcement` | Title ≤200; body non-empty; audience + label snapshot; channels non-empty; status machine (§5) |
| `Broadcast` | Single `channel`; audience + label; template snippet; status machine; `cost_minor`/`currency` always present |
| `MessageThread` | Subject ≤200; snapshot `student_name` when linked to a student; unread counts per participant |
| `ThreadMessage` | Belongs to thread; author snapshot (name + role); read flag per recipient |

## 7. Business Events

`AnnouncementCreated`, `AnnouncementSent`, `AnnouncementArchived`, `BroadcastCreated`, `BroadcastStarted`, `BroadcastCancelled`, `BroadcastCompleted`, `MessageThreadOpened`, `MessageReplied`, `ThreadStatusChanged` — all extend `BusinessEvent` → projected into `audit_events`.

## 8. Workspaces

- **School workspace**: `communications` section of the SPA (`/communications` — overview, announcements, broadcasts, threads routes).
- **Topbar bell**: notification feed rendered in the shell (every workspace).

## 9. Interaction Patterns

| Surface | Pattern |
|---|---|
| Overview | Dashboard (KPI cards: sent, unread, delivery, costs) |
| Announcements list | Explorer (status filters, scheduled/sent badges) |
| Announcement compose | Wizard (title/body → audience → channels → schedule/send) |
| Broadcasts | Explorer + editor (campaign rows with delivery/cost columns) |
| Threads | Profile/inbox pattern (participant list, message stream, reply composer) |
| Notification feed | Dropdown/toast list (polled) |

## 10. Presentation Contracts

Backed by resources (`AnnouncementResource`, `BroadcastResource`, `MessageThreadResource`, `ThreadMessageResource`, `ThreadParticipantResource`, `CommunicationsOverviewReader`):

- **AnnouncementResource**: `id, title, body, audience, audience_label, channels[], status, author_name, scheduled_for, sent_at, recipient_count, delivered_count, read_count, created_at`
- **BroadcastResource**: `id, name, channel, audience, audience_label, template_snippet, status, scheduled_for, started_at, completed_at, recipient_count, delivered_count, failed_count, cost_minor, currency`
- **MessageThreadResource**: `id, subject, status, participants[], last_message_preview, last_message_at, unread_count, student_id, student_name, messages[]`
- **ThreadMessageResource**: `id, thread_id, author_id, author_name, author_role, body, sent_at, read`
- **ThreadParticipantResource**: `user_id, name, role, avatar_initials`
- **Overview**: throughput (sent/archived), unread, delivery, cost aggregates.

## 11. Permissions

Keys (`config/communications.php`): `communications.overview.read`, `communications.announcements.read|write|send|archive`, `communications.threads.read|write`, `communications.broadcasts.read|write|start|cancel`.

| Ability | Gate |
|---|---|
| viewAny/view (announcements/broadcasts/threads) | respective `.read` key |
| store/update (announcements/broadcasts/threads) | respective `.write` key |
| send announcement | **dedicated `announcements.send`** |
| archive announcement | **dedicated `announcements.archive`** |
| unschedule | `announcements.write` (update) |
| start broadcast | **dedicated `broadcasts.start`** |
| cancel broadcast | **dedicated `broadcasts.cancel`** |
| complete broadcast | `broadcasts.start` (operational) |
| reply/status/read thread | `threads.write` |
| overview | **dedicated `communications.overview.read`** (via `CommunicationsPermission` helper) |
| notification feed | authenticated only (personal, per-source filtered) |

*P0-1 note: gate verification found zero gaps — every endpoint covered by controller `authorize()` or FormRequest `authorize()`.*

## 12. Notifications

- **Implemented (Phase 1)**: event-driven notification infrastructure — `notifications` + `notification_preferences` tables, `SchoolNotification` base (preference-gated channels), `TenantDatabaseChannel` (tenant-stamped in-app rows), `DispatchBusinessNotifications` wildcard listener driven by `config/notifications.php` policies, personal inbox endpoints (`GET/POST /communications/notifications`), plus the existing derived feed. First policies live: `AnnouncementSent → all tenant members`, `InvoiceIssued → members with finance.invoices.read`. Email channel: invitations (`SendInvitationEmail`); per-channel opt-out preferences work.
- **Next policies (per handbook Ch. 35)**: exam results published → guardians; absence alerts → guardians; payment receipts → finance readers; broadcast delivery stats → admins. SMS gateway (PayChangu) still a gap.

## 13. Realtime

- **Implemented**: none.
- **Required (handbook Ch. 21/33)**: broadcast progress ticks (recipient/delivered counters), thread reply presence, feed badge push via Echo/Pusher on tenant channel. **Gap — Phase 7.**

## 14. Discovery

- **Implemented**: DB indexes (`tenant_id+status`, `tenant_id+scheduled_for`, `tenant_id+last_message_at`, `tenant_id+student_id`).
- **Required**: announcement/thread search for the SPA search box (Scout). **Gap.**

## 15. AI

- **Implemented**: none.
- **Required (handbook Ch. 36)**: send-time audience estimation (from People data instead of static config estimates), draft tone/compression for SMS, "best time to send" from delivery stats. **Gap.**

## 16. Observability

- **Implemented**: audit events (sent/archived/started/cancelled), request logging.
- **Required**: delivery failure rates per channel, broadcast cost drift vs estimate, reply latency, overview query latency. **Gap.**

## 17. Testing

**Implemented (P0-1):**
- `tests/Feature/Api/V1/Communications/AuthorizationTest.php` (11 tests): announcement store ±, send dedicated-key ±, archive dedicated-key, broadcast store ±, start dedicated-key, thread reply gate, overview dedicated-key ±, cross-tenant 404.

**Required (per handbook Ch. 38):**
- Lifecycle: draft→send→archive; unschedule-only-when-scheduled; broadcast start→cancel→complete transitions.
- Audience/channel validation; scheduled sends via scheduler.
- Thread: open→reply→resolve; read tracking; unread counts.
- Feed: per-source permission filtering.
- End-to-end: compose → schedule → send → overview reflects counts.

## Acceptance Criteria (Definition of Done)

- [ ] Every write/verb endpoint gated (controller + FormRequest + policy) — **DONE (P0-1)**
- [ ] Dedicated keys (`send`, `archive`, `start`, `cancel`, `overview.read`) enforced + tested — **DONE (P0-1)**
- [ ] Announcement/broadcast state machines covered by tests
- [ ] Thread read-tracking + unread counts covered by tests
- [ ] Feed permission filtering covered by tests
- [ ] Suite green, Pint clean, no new PHPStan errors
