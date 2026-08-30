# SchoolOS Capability Spec — Finance & Billing

Status: **Implemented (API + tests) · gaps flagged below** · Source: `app/Domains/Finance`, `config/finance.php`, `routes/api/v1/finance.php`

---

## 1. Capability

What institutional responsibility does this solve?
- **School billing & accounting**: fee structures, invoice lifecycle, payment recording/refunds, double-entry ledger, chart of accounts, and financial reporting.
- Owns the guarantee that **the ledger always balances** and money moves are auditable end-to-end.

## 2. Goals

What outcomes should users achieve?
- Define fee structures per grade/category/cycle and issue invoices to students.
- Record payments (cash/card/PayChangu) that post to the ledger automatically; refund when needed.
- Reconcile: every invoice's paid/balance state matches its ledger postings; reports answer "cash position" and "who owes what".

## 3. Scope

**Included**
- Fee structures (CRUD, toggle, bulk), invoices (draft → issue → void → remind, lines, bulk), payments (record for invoice, refund), chart of accounts + journal entries (auto-posted), financial reports + overview.

**Explicitly excluded**
- Gateway *execution*: payments are recorded against PayChangu/Paystack metadata but not yet sent to a live gateway (**P0-4/Phase 4**).
- Payroll, procurement, tax filings.
- Idempotency keys (payment double-record race) — **P0-3**.

## 4. Actors

| Actor | Primary actions |
|---|---|
| Bursar / Finance officer | Fee structures, invoices, payments, refunds, reports |
| Principal | Approve-worthy visibility (read keys + reports) |
| Platform | Ledger/report read (cross-tenant oversight) |

## 5. Business Rules

Grounded in services (`IssueInvoice`, `VoidInvoice`, `RecordPayment`, `RefundPayment`, `PostJournalEntry`, `EnsureChartOfAccounts`, `ToggleFeeStructure`, `SendInvoiceReminder`, `BulkFinanceAction`, `WriteInvoice`, `WriteFeeStructure`):

- **Money is integer minor units** (cents) end-to-end; no floats in stored amounts.
- **Invoice lifecycle**: `draft → issued → partially_paid → paid`; `void` from draft or unpaid states; paid invoices cannot be voided; issued invoices lock line edits.
- **Issue posts the ledger**: `EnsureChartOfAccounts` idempotently creates standard accounts; each issue posts AR↑ + Revenue↑ balanced entries.
- **Payments**: allocate to the invoice's oldest unpaid portion; overpayment blocked; `record` requires an issued invoice; refund allowed **only on succeeded payments** and posts an offsetting entry.
- **Journal invariant** (`PostJournalEntry`): ≥2 postings, positive amounts, `sum(debit) === sum(credit)` — else 422.
- **Reminders**: only non-void invoices (fixed in P0-1); gateway fee defaults from config.
- **Bulk actions** are authorized per-row against the action's dedicated ability (issue/void/remind/delete) — fixed in P0-1.

## 6. Aggregates

| Aggregate | Invariants |
|---|---|
| `FeeStructure` | Unique `(tenant, grade_label, category, cycle)`; amount in minor units; toggleable |
| `Invoice` | Unique `number` per tenant; line-item snapshot (name/amount/type); totals = Σ lines − discount; status machine |
| `InvoiceLineItem` | Belongs to invoice; immutable after issue |
| `Payment` | Unique `reference` per tenant; method + status machine; `amount ≤ invoice.balance` |
| `Account` | Unique `(tenant, code)`; fixed side (debit/credit) |
| `JournalEntry` | Balanced postings; unique reference per tenant |
| `LedgerPosting` | Side + amount + account FK; entry-level integrity |

## 7. Business Events

`FeeStructureUpserted`, `FeeStructureToggled`, `InvoiceDrafted`, `InvoiceIssued`, `InvoiceUpdated`, `InvoiceVoided`, `InvoiceReminderSent`, `PaymentRecorded`, `PaymentRefunded`, `JournalEntryPosted` → `audit_events`.

## 8. Workspaces

- **School workspace**: `finance` section (fees, invoices, payments, ledger, reports).
- **Student profile**: fee statement (invoices + payments + balance).

## 9. Interaction Patterns

| Surface | Pattern |
|---|---|
| Fee structures | Explorer + editor |
| Invoices | Explorer (status badges) + issue wizard |
| Payments | Explorer (record payment drawer) |
| Ledger | Dashboard (postings, account balances) |
| Reports | Dashboard (cash position, AR aging) |

## 10. Presentation Contracts

- **FeeStructureResource**: `id, academic_year_label, grade_label, name, category, cycle, amount_minor, currency, is_active`
- **InvoiceResource**: `id, number, student_id, student_name, grade_label, term_label, status, subtotal_minor, discount_minor, total_minor, paid_minor, balance_minor, issued_on, due_on, lines[], payments[]`
- **InvoiceLineResource**: `id, description, type, amount_minor`
- **PaymentResource**: `id, invoice_id, reference, method, status, amount_minor, gateway_fee_minor, received_at, refunded_at`
- **AccountResource / LedgerPostingResource**: `code, name, side` / `entry_reference, account_code, side, amount_minor`

## 11. Permissions

Keys (`config/finance.php`): `finance.fees.read|write`, `finance.invoices.read|write|issue|void`, `finance.payments.read|write|refund`, `finance.ledger.read`, `finance.reports.read`.

| Ability | Gate |
|---|---|
| fee/invoice CRUD | `.write` key (draft-only edits via policy) |
| issue | **dedicated `invoices.issue`** |
| void | **dedicated `invoices.void`** |
| remind | `invoices.write` + non-void (P0-1 fix) |
| record payment | `payments.write` |
| refund | **dedicated `payments.refund`** + succeeded-only |
| ledger/reports | `ledger.read` / `reports.read` |

## 12. Notifications

- **Implemented**: `InvoiceReminderSent` event (in-app only).
- **Required (Ch. 35)**: invoice-issued email, overdue reminders, payment receipts (email/SMS). **Gap — Phase 5.**

## 13. Realtime

- **Required (Ch. 21/33)**: payment-posted push to the finance board. **Gap — Phase 7.**

## 14. Discovery

- **Implemented**: indexes (`number`, `reference`, `tenant+status`).
- **Required**: invoice/payment search by student/ref. **Gap.**

## 15. AI

- **Required (Ch. 36)**: cash-flow forecast, fee-default risk flags. **Gap.**

## 16. Observability

- **Implemented**: audit events, request logs.
- **Required**: reconciliation drift alerts (invoice paid_minor vs ledger), double-payment race detection — **P0-4** adds the row-lock fix.

## 17. Testing

**Implemented (P0-1):**
- `tests/Feature/Api/V1/Finance/AuthorizationTest.php` (7 tests) — dedicated keys (issue/void/refund), bulk per-row authz, remind fix.

**Required (Ch. 38):**
- Double-entry property tests: every issue/payment/refund posts balanced entries; invoice totals == ledger postings.
- Void/refund state-machine matrix; overpayment block; partial allocation order.
- Idempotency tests once P0-3 lands; concurrency (double-record) test once P0-4 row-lock lands.

## Acceptance Criteria (DoD)

- [ ] Every mutation gated; dedicated keys enforced — **DONE (P0-1)**
- [ ] Journal balancing holds under test
- [ ] No float money (minor units everywhere)
- [ ] Idempotency keys on payment/void/refund — **P0-3**
- [ ] Row-lock on payment recording — **P0-4**
- [ ] Suite green, Pint clean, no new PHPStan errors
