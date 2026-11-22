# Kids Area — Backend Database Standards

> **Status:** Approved V1 database conventions; implementation not started.  
> **Location:** `docs/02-standards/database-standards.md`  
> **Scope:** Laravel 12, MySQL 8 / InnoDB, one Kids Area location, API-first modular monolith.  
> **Currency:** Egyptian Pound (`EGP`) only in V1.  
> **Related documents:** [Project Overview](../00-overview/project-overview.md), [Backend Architecture](../01-architecture/backend-architecture.md), [Code Standards](code-standards.md), [API Standards](api-standards.md), and approved module/feature specifications.

## 1. Purpose, Architecture & Decision Summary

This standard defines shared rules for **relational data modeling, constraints, exact amounts, temporal data, financial history, concurrency, indexes, migrations, backups, and database tests** in Kids Area. It is **not** a final table-by-table ERD or a mandate to implement all modules at once.

**Architecture baseline**

- Use **one MySQL 8 database, InnoDB tables, and one Laravel 12 application**. All modules share this database but retain clear **business ownership** of their data.
- Organize business code and module migrations using the installed Laravel 12-compatible `nwidart/laravel-modules` version. Follow the package's actual registered paths and structure.
- **Identity, People, Employees, Workforce, Payroll, Activities, Sessions, Cafe, POS, Finance and Reports** own their domain behaviors and persistence boundaries as documented in Backend Architecture. Reports primarily reads approved source records and does not independently post money.
- Writes crossing module boundaries call the **owning module's Action/approved Service**, within the **same outer business transaction** when the result must be atomic. Avoid writing directly into another module's tables.
- Prefer Eloquent, appropriate Laravel Query Builder, normal relational keys, real foreign keys, and focused Actions. Do not create repositories/CQRS/event-sourcing infrastructure without approved need.
- V1 serves **one business location**. Do not introduce tenants, branches, per-tenant databases, cross-database joins or branch-aware foreign keys in anticipation of future growth.
- V1 has **no application queues, Redis, cron/scheduler business dependency, background Jobs or long-running workers**. Hosting-provider-managed backup processes are outside this prohibition.
- Store technical names in English; the application is Arabic-first, while the external API uses the **`camelCase`** contract defined in API Standards.

### Approved database decisions

| Concern | Approved V1 standard |
|---|---|
| Primary keys | `BIGINT UNSIGNED AUTO_INCREMENT` via Laravel `$table->id()` |
| Foreign keys | `foreignId()` matching unsigned BIGINT parents; **RESTRICT by default** |
| Names | Plural `snake_case` tables, `snake_case` columns/index intent; `camelCase` API boundary |
| Money | **`DECIMAL(12,2)`**; exact arithmetic, never SQL/PHP float for financial calculations |
| Currency | EGP only, centrally configured |
| Fixed statuses | Stable **integer-backed PHP enums** persisted as suitable integer columns |
| Simple flags | Database booleans, e.g. `is_active` |
| Deletion | **Hybrid:** deactivate, feature-approved soft delete, or tightly controlled hard delete |
| Historical facts | No deleting or overwriting posted movements; preserve operational evidence |
| Time | **UTC-first** for actual instants; local recurrence + IANA timezone for weekly schedules |
| Durations | Integer seconds (API); derive elapsed time from authoritative timer segments |
| Uniqueness | Database unique constraints for true invariants, plus locks for state-dependent ones |
| Transactions | Single atomic transaction for sensitive multi-record/multi-module workflows |
| Financial movement | Positive `DECIMAL(12,2)` amount + integer `direction` (`IN` / `OUT`) |
| Sessions | Independently running timers with persisted **timer segments** |
| Classes | Real **class occurrences** with separate child/trainer attendance evidence |
| Renewal | A **new subscription record for each manual renewal**, linked to the previous record |
| Contracts | **Effective-dated contract history** and immutable approved payroll snapshots |
| Migrations | **Safe forward-only production evolution**; module-local and data-preserving |
| Seeders | Repeatable/idempotent roles and permissions; no production demo financial data |
| Backups | Hosting-managed **daily backups**; **quarterly isolated restore tests** plus major-change checks |
| Database tests | PHPUnit + MySQL 8; fresh schema per PR and **risk-based upgrade tests** |

**Specific precedence over older illustrative architecture samples:** The subsequently approved standard is **`DECIMAL(12,2)`**, even where an older Backend Architecture example says `DECIMAL(15,2)`. The V1 default schedule timezone is `Africa/Cairo`, but the model must use a **configurable IANA timezone**, not hard-code Egypt for future operation elsewhere. Those older examples should be synchronized by a separate reviewed documentation change; do not change other architecture decisions.

## 2. Naming, Primary Keys & Ownership

### 2.1 Table and column names

- Use **plural snake_case** table names: `children`, `guardians`, `activity_groups`, `activity_subscriptions`, `session_timers`, `timer_segments`, `financial_movements`.
- Use meaningful **snake_case** columns: `child_id`, `group_id`, `received_amount`, `started_at`, `created_by_user_id`.
- Conventional lifecycle columns, when useful, are `created_at`, `updated_at` and selectively `deleted_at`; `created_at` and `updated_at` are **not** mandatory on immutable high-volume detail tables unless needed.
- Every normal relational table has `id` as an unsigned BIGINT auto-increment primary key unless the approved feature spec proves a more appropriate key.
- Relational references use `foreignId('child_id')` etc. matching the referenced primary key's type. Give optional relationships explicit nullability.
- Technical names and enum case names remain English; Arabic appears in presentation/localization, never in table/column/constraint identifiers.
- Use a clear, reasonably short explicit index/constraint name when automatically generated MySQL names would be ambiguous or exceed limits.
- Public API serializers explicitly translate `snake_case` DB attributes into `camelCase`; do not expose raw Eloquent attributes as the API contract.

Illustrative migration fragment, **not an approved full schema**:

```php
Schema::create('activity_subscriptions', function (Blueprint $table): void {
    $table->id();

    $table->foreignId('child_id')
        ->constrained('children')
        ->restrictOnDelete();

    $table->foreignId('group_id')
        ->constrained('activity_groups')
        ->restrictOnDelete();

    $table->date('start_date');
    $table->date('end_date');
    $table->decimal('agreed_price', 12, 2);
    $table->timestamps();
});
```

### 2.2 IDs and business references

- Default to **numeric IDs** for normal internal records; do not impose UUID/ULID primary keys on every table.
- A **`Idempotency-Key`** (normally a UUID-like request identifier) is an **operation identifier**, not a reason to change every table's primary key.
- Human-readable invoice/receipt/reference numbers, if required, are **separate** from primary IDs. Their format, scope, immutability and uniqueness are defined by the owner feature.
- Generate business references server-side with a concurrency-safe method. Do **not** issue numbers using unprotected `MAX(number) + 1`.
- A numeric ID never bypasses **record-level authorization**; enforce data visibility in reads and mutations.
- Do not create generic polymorphic source references merely for convenience where explicit relational links offer safer integrity.

### 2.3 Module and relationship ownership

- Each module owns its models, migrations and data writes; **cross-module foreign keys are allowed** inside the same MySQL database for real, stable relationships.
- Order migrations so required parent tables already exist before adding a foreign key, and document dependencies between module migrations.
- Read-only joins across owned tables are allowed when authorized and appropriate; they do not grant another module permission to mutate the joined rows.
- Important business facts have **one authoritative persistence owner**. An auxiliary aggregate or report projection is never a second editable truth.

## 3. Data Types, Defaults, Enums & Validation

| Concept | MySQL/Laravel type and guidance |
|---|---|
| Identity key | `$table->id()` — unsigned BIGINT auto-increment |
| Relation | `$table->foreignId('...')`, with explicit FK/delete action |
| Short text | `string` / `VARCHAR` with meaningful length limits |
| Description/notes | `text` when unbounded short-string fields are unsuitable |
| Money | `decimal('amount', 12, 2)` |
| Fixed business state | `unsignedTinyInteger` or `unsignedSmallInteger` for integer-backed enum values |
| Active flag | `boolean('is_active')` with an intentional default |
| Calendar date | `date(...)` (no timezone) |
| Weekly wall-clock time | `time(...)` plus an IANA timezone determined by the location |
| Event instant | `dateTime(...)` or deliberate `timestamp(...)`, consistently read/written as UTC |
| Duration | An appropriate signed/unsigned **integer** of seconds; choose range per feature |
| Optional flexible metadata | `json(...)` only when relational columns are not justified |
| Sensitive files | File bytes in **private Laravel storage**, metadata/references in relational tables |

**Rules**

- Use `NOT NULL` unless absence has an explicit business meaning. Never use `NULL` as an undocumented pseudo-status.
- A database default must reflect a true default, not conceal missing financial or workflow input. Avoid defaulting required payments/prices to zero merely to bypass validation.
- Define suitable **check constraints** in MySQL 8 where they materially protect a domain invariant, after confirming the deployed MySQL patch version and legacy data compatibility. Application checks remain required for cross-row/time-sensitive rules.
- Fixed statuses use **PHP integer-backed enums**, persisted as numbers, with explicit Eloquent casting and stable integer assignments. Numeric values and transitions are owned by feature specs.
- Avoid native MySQL `ENUM` for changeable PHP workflow statuses and avoid localized text as persisted status.
- `is_active` is a normal boolean for simple enable/disable behavior; user-managed activities, jobs, coaches, price lists and categories are **rows**, not hard-coded enum cases.
- Never silently reuse a persisted enum's numeric value for a new meaning.
- Do not store formatted Arabic money/date/time text as the authoritative field.
- JSON is not an acceptable substitute for transactional child rows, attendance, timer segments or financial movements.

## 4. Money Precision, Price Snapshots & Derived Balances

**All financial amount columns use MySQL `DECIMAL(12,2)`.** This has two fractional digits; the feature defines any additional intermediate calculation precision and safe rounding procedure before final persistence.

```php
$table->decimal('hourly_rate', 12, 2);
$table->decimal('agreed_price', 12, 2);
$table->decimal('total_amount', 12, 2);
$table->decimal('received_amount', 12, 2);
```

These are **examples of types**, not a directive to store all these fields on the same table or persist redundant sums.

- **Never use MySQL `FLOAT`/`DOUBLE` or PHP floating-point arithmetic** for authoritative financial math, comparisons, payroll, pro-rata pricing, refunds or balances.
- Use exact decimal strings and a decimal-safe arithmetic approach (or integer minor units where mathematically appropriate). Validate scale/range at the boundary and before persistence.
- The API exposes exact monetary values as **decimal strings** such as `"70.00"`; the DB stores `DECIMAL(12,2)`, not the formatted currency string.
- V1 currency is **EGP**. Do not add a currency-conversion subsystem or `currency_code` to every row without a separately approved requirement.
- **Snapshot historical economic terms:** the hourly rate when an activity was used, the agreed subscription price for its period, the price/quantity of cafe invoice lines, and applicable employee contract rates for an approved payroll calculation.
- **Never recalculate old invoices, session charges, subscriptions, or approved pay from today's catalogue/contract price**.
- Derived balances, cached totals and summary tables may be introduced only after measured need and with a reproducible reconciliation method; they are **not** authoritative second financial ledgers.
- The feature spec owns timer partial-second/minute policy, discount authority, monetary rounding and exact billing formulas. A stored integer elapsed duration does not itself select the rounding policy.
- Do not introduce tax, general-accounting or multi-currency columns as mandatory infrastructure without a related feature requirement.

**Important distinction:** A **service sale/invoice** records agreed value; a **receipt** records money actually received; a **receivable** records unpaid obligation; a **payroll accrual** records approved entitlement; a **payout** records money actually paid. These facts must not be conflated.

## 5. Foreign Keys, Delete Rules & Historical Protection

### 5.1 RESTRICT by default

Every normal relationship must have a real FK when feasible, and its delete behavior must be intentional.

| Relationship class | Default |
|---|---|
| Child/guardian/group/employee referenced by active or historical records | **RESTRICT** |
| Subscription, invoice, receipt, refund, attendance, class-delivery, payroll or timer evidence | **RESTRICT**; do not cascade away history |
| Disposable child rows of a genuinely deletable nonhistorical draft | `CASCADE` **only when the feature explicitly approves it** |
| Truly optional reference whose removal does not erase meaning or auditability | `SET NULL` **only with justification and nullable FK** |

```php
$table->foreignId('child_id')
    ->constrained('children')
    ->restrictOnDelete();
```

- A user-facing delete command must not be able to erase posted financial or required attendance, subscription, contract or timer history by cascading through a parent.
- Business checks at the **Action** boundary supplement FK rules; a foreign key cannot decide whether it is safe to archive a group that has active subscriptions.
- Parent record visibility in historical views must be preserved as authorized even when it is no longer selectable for new activity.
- Do not avoid integrity by removing essential FKs to make deletes easier.

### 5.2 Hybrid delete strategy — approved

- Use `is_active = false` for **operational deactivation**: an unavailable activity, discontinued cafe product, disabled option, or inactive staff account. Existing historical references remain valid.
- Add Laravel `softDeletes()` / `deleted_at` **only** when the specific feature requires real archive/restore semantics. Soft deletes are **not automatically added to every table**.
- Permit **hard deletion** only for explicitly approved disposable or draft records without protected dependencies, after authorization and relationship checks.
- **Posted financial movements and their linked correction history are neither soft-deletable nor hard-deletable** by normal application flows; the same principle protects approved payroll and other legally/business-critical evidence.
- Archiving is an **UPDATE**, not an SQL DELETE. Therefore MySQL FK `RESTRICT` does **not** protect against invalid soft deletes: the owning Action must apply state/capacity/relationship rules.
- Do not implement "unique among active soft-deletable rows" as `UNIQUE(email, deleted_at)` and assume it works. **MySQL unique indexes allow multiple `NULL` values.** Use a deliberate generated/functional active-key approach or a suitably locked application rule plus enforceable uniqueness as approved by the relevant feature.
- Confidential child/guardian/staff data retention and any future lawful purge/anonymization requirements need an approved privacy procedure separate from ordinary deactivation/archival. Do not erase linked financial facts as a workaround.

## 6. UTC-First Time, Calendar Dates & Weekly Schedules

**Approved: UTC-first across all actual system events.**

- Event instants include `created_at`, `updated_at`, financial posting, attendance check-in, class delivery, timer starts/ends, refunds and relevant audit times; persist and interpret them consistently in **UTC**.
- MySQL `DATETIME` itself carries **no timezone**. Laravel, MySQL connection/session settings and serializers must consistently treat authoritative instants as UTC. `TIMESTAMP` has MySQL session-timezone conversion semantics and a narrower supported range; pick deliberately, not inconsistently.
- API timestamp responses use unambiguous **ISO 8601 with UTC `Z` or an explicit offset**, according to API Standards.
- Store pure calendar dates (`subscription start/end`, eligible class/business dates) as `DATE` **without timezone**. A one-calendar-month subscription is **not** modeled as a fixed 30-day/2,592,000-second duration.
- A repeating class such as "Saturday at 16:00" is a **local wall-clock recurrence**. Store the weekday/time and resolve them using the **location's IANA timezone** (e.g., `Africa/Cairo` in the initial setup), rather than a hard-coded UTC offset.
- For an actual class occurrence, resolve its date/time to authoritative **UTC start/end instants**; historical occurrences must not shift unpredictably when recurring schedules or locale settings change.
- Changing the location timezone is a controlled business configuration change with documented implications for future occurrences, existing reservations and reports.
- **One location only in V1**; keeping a configurable `timezone` setting does not imply multi-location or tenant schemas.
- Durations (especially in the API) are **integer seconds**, calculated on the server from persisted authoritative instants; never persist a ticking counter every second.
- Day boundaries for local schedules and subscription/month eligibility are evaluated against the correct location calendar, not by assuming a UTC date is always the same local business date.
- DST/ambiguous or nonexistent local schedule times are a feature-level policy, handled with timezone-aware conversion when generating occurrences. Do not store a fixed "UTC+02:00" offset as if it identifies Egypt for all dates.

### Illustrative data separation

| Fact | Stored form |
|---|---|
| Timer started at an instant | UTC `DATETIME` / intentionally chosen UTC `TIMESTAMP` |
| A class actually occurred | Immutable dated occurrence with UTC instant(s) |
| Group meets weekly | Weekday + `TIME` and the location's IANA timezone |
| Subscription begins/ends | `DATE` |
| Timer chargeable duration | Integer seconds, derived from segments |
| Display in current locale | Convert at authorized API/frontend boundary; never overwrite UTC persisted instants |

## 7. Canonical Financial Movements & Immutable Posted History

**Finance owns the canonical ledger of actual money movement**, not a second copy of sale amounts or an arbitrary customer-account/debit-credit engine inherited from the printing reference project. V1 is **operational finance**, not a double-entry general ledger.

### 7.1 Approved movement representation

A posted movement has an exact **strictly positive** amount and a separate **integer-backed direction enum**:

| Field concept | Approved meaning |
|---|---|
| `amount` | `DECIMAL(12,2)`, **positive** for a real posted money movement |
| `direction` | Stable integer-backed enum representing **IN** or **OUT** |
| `movement_type` | Business category from owning Finance feature, not a replacement for direction |
| `source reference` | Traceable identity of the source receipt/refund/expense/payout or approved command |
| `posted_at` | Authoritative UTC posting instant |
| `actor and reason` | Recorded for sensitive/corrective actions as relevant |
| `reversal / correction link` | Optional explicit link to original posted movement, when needed |

The actual numeric `IN`/`OUT` codes, complete column inventory, relations and indexes belong in **Finance Feature Spec**. Do not hard-code illustration numbers before approval. For zero-value events, the feature decides whether **no financial movement** is appropriate; do not create misleading `0.00` cash movements.

Example history (not a mandated DB record shape):

| Movement | Amount | Direction | Effect |
|---|---:|---|---|
| Session collection | `350.00` | `IN` | Money received |
| Authorized partial refund | `50.00` | `OUT` | Money returned |
| Net cash effect | | | `+300.00` (derived) |

- All **posted financial movements are append-only**. Do not update/delete/soft-delete a posted row to repair an error.
- A reversal, refund or correction is a **new linked movement** with a reason and authorized actor, preserving the original.
- A source receipt/payout/refund and its **single corresponding canonical movement** must be posted in the **same MySQL transaction**.
- Enforce source-operation uniqueness in MySQL to stop accidentally posting the same receipt, invoice settlement or payout twice.
- The **same subscription installment reduces an existing receivable**; it must not be recorded as a second sale.
- A **payroll entitlement is not an OUT movement**. Record `OUT` only when the payout actually occurs.
- Refunds require the approved permission, a reason and verification of the actually collected and still-refundable amount; repeated or concurrent refunds cannot exceed it.
- POS records invoice/payment method/cashier shift evidence; Finance owns the canonical posted financial impact. Any cached shift/balance projection must reconcile from source movements.
- Cashier financial shifts are separate from employee/work shifts. Cash reconciliation must distinguish **cash vs card vs wallets** before determining expected physical cash or shortages.

### 7.2 Source and adjustment references

- Prefer explicit normal FKs/association records for known source types when practical. Avoid uncontrolled `source_type/source_id` pairs as a substitute for referential integrity where a small explicit relationship works.
- The Finance feature must define which source events are **one-to-one**, which permits **multiple distinct installments**, and which adjustments may legitimately reference one original movement.
- Do not add a general chart of accounts, mandatory opening customer balances or customer-ledger order-allocation logic from Tabaa.
- Reported sales, collected amounts, outstanding dues, refunds, operating expenses and paid salaries are **separate measures** and must remain reconstructable.

## 8. Transactions, Isolation, Concurrency & Idempotency

Use **one outer `DB::transaction()`** for a sensitive business command requiring multiple related writes, even if those writes are owned by different modules.

| Workflow | Required atomic result |
|---|---|
| Close full customer session | Lock session; verify **all** activity timers are finished; finalize charges; one invoice; full collection; one linked movement; cashier shift impact; closed state |
| Collect subscription installment | Valid receipt/payment + same obligation's collection record + canonical IN movement |
| Refund payment | Recheck refundable remainder, record authorized refund + OUT movement + linked history |
| Reserve last group seat | Lock appropriate capacity/registration state, check availability, create eligible subscription/attendance reservation as specified |
| Change timer state | Lock timer; validate transition; create/close segment; update timer state together |
| Approve payroll and then pay | Preserve approved snapshot; payout is a separate authorized atomic payment + OUT movement |
| Reconcile cashier shift | Lock open shift and relevant posted movements; record closing count, expected amount and variance consistently |

**Rules**

1. The **coordinating Action** owns the outer transaction boundary and calls approved Actions/Services from other owning modules. Do not independently commit Finance/POS/Session steps that must succeed or fail together.
2. Protect mutable state with **`lockForUpdate()`** (or a justified equivalent) inside a real InnoDB transaction. Lock records in a **consistent order** across competing workflows to reduce deadlocks.
3. Use **unique constraints** for immutable one-of-a-kind business identities; do not rely on a prior `exists()` check alone.
4. Validate permissions, current state, available capacity, refundable amount, all finished timers and payroll payout eligibility **at execution time**, with locks where racing writers could invalidate a read.
5. **No partial posting:** if any required invoice/payment/Finance/shift write fails, the entire command rolls back.
6. Keep transactions short. **Never perform long exports, file uploads, external service calls or irreversible side effects while holding financial locks**.
7. Handle **deadlocks/lock wait conflicts** with safe, bounded retries only when re-running the entire Action is demonstrably idempotent. Never retry a partially externalized charge or duplicate an irreversible effect.
8. InnoDB isolation behavior matters: test the actual reads/locks and concurrent requests under the configured MySQL isolation level.
9. Critical audit/history records are written synchronously alongside their protected operation where appropriate; audit logs are **not** substitutes for receipt, ledger, timer or attendance source records.
10. Report generation and read APIs **never** post new movements, and may not expose another operator's private data.

### 8.1 Idempotency — only sensitive replay-risk commands

API Standards require `Idempotency-Key` for financial/sensitive operations such as full session settlement, installments, standalone paid cafe sale, refunds, expense posting and payroll payout. It is **not required for every ordinary POST**.

- Assign a **unique database-enforced key within an approved scope** (actor/operation/domain context as specified by the owning feature).
- Preserve a normalized request fingerprint and sufficient previous result to answer retry attempts safely.
- Same key + equivalent payload => **no duplicate business effect**. Same key + materially different payload => **409 Conflict** via API Standards.
- Missing/malformed required key => **422**.
- Record the idempotency outcome coherently with the domain change/financial movement; a crash must not leave a successful receipt without a safe way to detect/replay its completion.
- Multiple concurrent requests with the same logical key must serialize or be resolved safely. Unique indexes, transactions and state checks are required; disabling a frontend button is insufficient.
- Scope/retention, fingerprint normalization and in-progress retry behavior are feature-level decisions. Do not introduce a queue/cron solely to maintain idempotency data.
- Separate **different legitimate installments/refunds** from a replay of the *same* command; deduplication must not block lawful later operations.

## 9. Sessions, Independent Timers & Timer Segments

**Approved:** each customer session can contain **multiple independently running timers** for hourly activities; one session is settled as a whole when all its timers are finished.

### 9.1 Conceptual tables (feature spec owns final names)

```text
sessions
    └── session_timers      (one row per independent activity timer)
            └── timer_segments  (one row per active/run interval)
```

- **Start:** validate ready state, persist a new **open segment** with authoritative UTC `started_at` and `ended_at = NULL`, set timer to running.
- **Pause:** close the current segment by writing its UTC `ended_at`, set timer to paused.
- **Resume:** validate paused state, create a new open segment, set timer to running.
- **Finish:** close any active interval and persist the timer's final state/duration/billable snapshot. Feature spec determines allowed Finish transitions from paused/ready states.
- **Chargeable elapsed time = sum of started→ended intervals**, excluding paused gaps. Concurrent timers do not merge into one activity duration.
- A background tick is **not** needed. UI countdown/up-counter reconstructs live time from persisted server state; no MySQL write is performed every second.
- Browser closure/network loss does not implicitly pause or finish a server timer.
- Protect every transition using **row locking on the owning timer** and state-machine checks inside a transaction; prevent Start/Resume races or two open intervals.
- Preserve finalized time-segment evidence and its original rate/charge snapshot; later price edits cannot reprice old sessions. Any exceptional correction needs a protected, auditable action.
- Store duration totals as integer seconds/projections if useful; derive or reconcile them with source intervals. Do not treat unverified client duration as authoritative.

### 9.2 Database constraint: at most one open segment

**Do not rely on `UNIQUE(session_timer_id, ended_at)`** to prevent two open intervals: MySQL unique indexes allow repeated `NULL` values.

Possible MySQL 8 implementation is a generated column holding `session_timer_id` **only while `ended_at IS NULL`**, plus a unique index on that generated column, with closed segments producing NULL and therefore allowing history. Confirm syntax/support with the deployed MySQL version and test the specific migration.

Alternatively, choose another **database-enforceable** active-segment representation designed in Sessions Feature Spec. An application-level lock/state guard is required either way; it is not a substitute for testing concurrent persistence. This file fixes the invariant, **not one universal migration implementation**.

- Where possible enforce `ended_at >= started_at` for closed intervals (subject to timestamp precision and approved feature policy).
- Detect invalid, overlapping or out-of-order segments in Action logic and regression tests.
- Never erase finished segment history via a parent `CASCADE`.

## 10. Activities, Occurrences, Attendance, Renewals & Make-Ups

### 10.1 Distinct business facts

| Concept | Persistence responsibility |
|---|---|
| `activity_groups` + schedules | Group membership/coach, capacity if any, and recurring local dates/times |
| `activity_subscriptions` | Child, selected group, start/end calendar dates, agreed price and payment obligation |
| `class_occurrences` | **Specific class** on a specific date/time, including actual or scheduled occurrence identity |
| Child attendance | Child's one attendance outcome for a particular occurrence |
| Trainer/class-delivery attendance | Evidence of the actual trainer who delivered a particular class |
| Make-up record | Original subscription and missed occurrence, eligible replacement occurrence, actual attendance/use |

The names above are conceptual; module feature specs own actual table names, status values and detailed constraints.

### 10.2 Attendance uniqueness and capacity

- Class occurrences have their own **stable identity**; do not key attendance solely by free-text date or group/day.
- Enforce a unique key equivalent to **`(child_id, class_occurrence_id)`** for the attendance fact (provided one row represents the active/historical status of that fact). A change from absent to present is an authorized update/history event, **not a second attendance row**.
- Enforce appropriate uniqueness of trainer delivery/credit per occurrence and prevent paying both the planned and substitute trainer for a **single delivered class**. Specific multiple-coach support, if later approved, needs its own explicit model.
- Group capacity is **optional**; if configured, concurrent enrollment into the last place must be serialized with the relevant locks and consistent eligibility/counting rules.
- For attendance/make-up booking against capacity, ensure checking availability and persisting the booking happen in the **same transaction**.
- Do not add a naive `UNIQUE(child_id, activity_id)` on subscriptions; one child may subscribe to **different activities simultaneously** and renew the same activity in later periods.
- Prevent unapproved **overlapping subscription periods** using an Action transaction and suitable locked parent/scope records. A simple ordinary MySQL unique constraint cannot generally express arbitrary interval overlaps.

### 10.3 Subscription renewal and history

**Every manual renewal creates a NEW `activity_subscriptions` row** for the next term; it does not extend and overwrite the historical term.

- Link the new record to the old one via a nullable reference such as `renewed_from_subscription_id` with a restrictive self-FK.
- Preserve each period's **agreed price, start/end dates, group, attendance, obligation and payments** independently.
- Determine subscription expiry **on demand**, from calendar dates and approved business rules; expiry does **not** require cron, create a new subscription, or create an unapproved debt.
- The term is **one calendar month from its start date**, with exact end-date and boundary calculations defined in Activities Feature Spec.
- A child may hold **multiple active subscriptions to different activities**. Group changes, overlapping periods and future-dated renewals follow explicit Activities eligibility rules.
- Make-up attendance remains linked to the **original subscription and missed class**, even after renewal. Eligibility extends **up to seven days after the original subscription expires**; the make-up does not extend that subscription.
- Prevent duplicate consumption of one make-up entitlement with constraints plus locked validation, while preserving exception history and authorized corrections.
- Generate class occurrences **when required by an authorized business operation/read workflow**, using idempotent creation/uniqueness so concurrent generation does not duplicate them. Do **not** depend on cron.

## 11. Employees, Effective-Dated Contracts & Approved Payroll

- **Employee** and **User** are separate entities: an employee may or may not have a login. Login/permissions belong to Identity; employee identity and contracts belong to Employees.
- Employment compensation contracts support **monthly fixed pay**, **per-delivered-class pay**, or **both**; start date is required, end date optional.
- **Approved: effective-dated contract history.** Any material change to salary/per-class rate creates a **new contract/contract-version record with an effective date**; preserve the previous terms.
- Prevent conflicting **overlap of active compensation terms** for the same employee/scope where it would pay twice. Use validated date ranges and appropriate transactions/locks; feature spec owns exact transfer/end-date rules and potential exceptions.
- A payroll calculation for a historical date uses the **contract terms effective on that date**, not today's employee salary.
- Trainer pay uses only **actually delivered classes** evidenced by attendance/occurrence records. Substitute trainer credit goes to **the person who delivered the class**, not automatically the scheduled trainer.
- Operational staff shift attendance is separate from class-based trainer attendance. The employment pay model must not determine the employee's attendance tracking type.
- Manager-reviewed deductions: preserve **suggested** attendance deductions separately from **approved** deductions; exceptions are auditable and must avoid double-penalizing the same missed delivery.
- When payroll is **approved/finalized**, persist a stable snapshot of applicable contract terms, quantities, earnings, additions/deductions, approved net amount, period and approver. Repricing a contract later cannot mutate past approved results.
- Approved **payable salary is not the same as paid salary**. A real payout writes an authorized payment source and **Finance OUT movement** atomically, with idempotency and safeguards against duplicate payout.
- Correct finalized payroll using approved linked adjustment/correction records and audit evidence, not silent overwrites or deleting history.
- Final payroll table design, prorations, partial payouts, periods and exact calculation formulas remain in Payroll/Employees/Workforce feature specs.

## 12. Indexes, Unique Constraints, Query Plans & Performance

Create indexes for **actual joins, permission scopes, filters, sorting and financial/report queries**, not every imaginable query. V1 targets cPanel/shared-hosting constraints and should favor efficient bounded queries over infrastructure.

### Candidate indexes — verify against real feature queries

| Table/concept | Candidate index or constraint | Purpose |
|---|---|---|
| Child attendance | `UNIQUE(child_id, class_occurrence_id)` | No duplicated attendance fact |
| Financial source posting | Scoped unique source-operation/reference | No duplicate canonical movement for one posting |
| Idempotency records | `UNIQUE(scope, idempotency_key)` (conceptual) | Replay-safe writes |
| Session final invoice | Appropriate unique session-settlement reference | Prevent second final invoice |
| Timer segment open marker | Database-enforceable unique active-segment key | At most one open segment per timer |
| Subscriptions | e.g. `(child_id, start_date)` and/or status/group filters | Find history/eligible periods; **non-unique unless justified** |
| Movements | e.g. `(posted_at, direction)`, source lookup, cashier-shift reference | Finance summaries and traceability |
| Payroll | Employee/period and contract-effective-date lookup | Historic accrual and payout reports |
| Class occurrence | Group/date/time uniqueness with feature-defined rescheduling policy | Avoid duplicated generated classes |
| Cashier shifts | Cashier/state/date and linked movement indexes | Reconciliation and cashier reads |

Illustrative Laravel migration:

```php
$table->unique(
    ['child_id', 'class_occurrence_id'],
    'uq_child_occurrence_attendance'
);

$table->index(
    ['child_id', 'status'],
    'idx_subscription_child_status'
);
```

The example indexes are **not automatically appropriate on the same table**; choose them only for the table/queries for which those columns exist.

**Index design rules**

- A `UNIQUE` constraint is for **true uniqueness**, not simply improving read performance. It must not incorrectly prohibit legitimate repeat installments, renewals, parallel timers or different activity subscriptions.
- Check existing primary, FK and composite indexes before adding a redundant single-column index. Extra indexes consume storage and make writes slower.
- For composite indexes, account for leading column order and actual filter+sort patterns. Review query plans with `EXPLAIN` and real/reasonable data volumes when choosing performance indexes.
- Test filtered pages through the **Spatie Query Builder** API contract and enforce visibility conditions; an index never replaces authorization.
- Do not assume `UNIQUE(a, nullable_b)` treats multiple `NULL` as equal. Active-only uniqueness and open-segment rules need special design.
- Be careful adding new unique indexes to historical data: discover, report and safely resolve existing duplicates **before** enforcing constraints.
- Keep aggregates, balances and counts derived unless a feature explicitly justifies a reconciled projection.

## 13. JSON, Files, Snapshots & Audit Separation

- Normalize **payments, movements, subscription periods, class occurrences, attendance, timer segments, payroll payouts and critical cross-record links** into proper relational rows.
- Use `JSON` for genuinely flexible, low-query metadata or **immutable before/after snapshots** where individual keys do not require FK integrity, filtering or frequent indexing.
- Relational finance/timer/attendance records must **not** be bundled into opaque JSON arrays.
- Store private files with **Laravel Filesystem**; database records hold safe metadata, owner/link and access rules. Avoid storing binary file contents in ordinary MySQL rows.
- Historical snapshots (e.g., contract rate, activity rate, invoice line pricing) are **business evidence**, distinct from optional technical audit JSON.
- The selective Core Audit Log tracks sensitive actor/time/action/reason/before-after details as needed. It **does not replace** domain payment/contract/attendance/segment history or Finance movements.
- Exclude passwords, tokens, payment credentials and unneeded child/guardian/staff sensitive data from audit snapshots, error logs and debug output.
- Do not make private attachment storage publicly reachable. If a future feature requires retention/purge, define an explicit approved policy separately from ordinary record deletion.

## 14. Module Migrations, Production Evolution & Seeders

### 14.1 Module-owned schema

Migrations, factories and feature seeders live with their owning modules according to the **installed** `nwidart/laravel-modules` version. Shared/framework tables may remain under the main Laravel `database/` directory where justified.

```text
Modules/
  Identity/database/migrations/
  People/database/migrations/
  Employees/database/migrations/
  Workforce/database/migrations/
  Payroll/database/migrations/
  Activities/database/migrations/
  Sessions/database/migrations/
  Cafe/database/migrations/
  POS/database/migrations/
  Finance/database/migrations/
database/migrations/          # Shared/framework, only when justified
```

Example structure is illustrative. Check the real installed module package's registration, migration order and generated structure before adding files.

### 14.2 Safe forward-only production migrations — approved

1. **Do not edit, rename or delete a migration already executed on Production.** Create a new incremental migration for every subsequent schema change.
2. Use **add/expand → backfill/validate → enforce/contract** when existing data and consumers need a safe transition. Make code/schema compatibility across deployment steps explicit.
3. Adding a new `NOT NULL`, `UNIQUE`, or FK to a populated table requires evaluating current data, correcting exceptions safely, then enforcing the constraint.
4. Avoid casual drops/renames/type reductions or historical-data rewrites. Every destructive change needs an approved preservation/conversion plan, backup and tested deployment sequence.
5. MySQL DDL may issue **implicit commits**. Do **not** assume wrapping schema changes in `DB::transaction()` makes the whole migration rollback-able.
6. A `down()` method may exist for development where safe, but **production recovery is not based on `migrate:rollback` alone**. Favor corrective forward migrations and a tested restore plan if a destructive failure occurs.
7. Keep migrations focused, predictable and reviewable; avoid long unpredictable data changes, cross-network calls, interactive prompts or requiring a worker.
8. Review lock duration, row count and hosting memory/time limits before schema alterations on large tables. Decide if a maintenance window or phased rollout is needed.
9. Require a **recent verified backup before risky production migrations**, and a verified recovery path before continuing.
10. Only approved releases are deployed; a push to `develop` does not automatically imply a production migration.

**Production prohibited commands:** `db:wipe`, `migrate:fresh`, `migrate:refresh`, destructive demo seeders and unreviewed automatic migration commands. `migrate:fresh` is acceptable **only on an isolated test/development database** where wiping is intentional.

A reviewed production migration can be run with Laravel Artisan's `--force` flag under a controlled release procedure, after confirming the **correct environment and database connection**. A safe deployment is more than one Artisan command.

### 14.3 Idempotent seeders

- Seed **fixed application roles, permissions and approved baseline configuration** with stable names/keys and **idempotent** operations (e.g., thoughtful `firstOrCreate` / `updateOrCreate` / `upsert`).
- Do **not** overwrite live administrators' customized role assignments or operational settings merely because a seeder is rerun.
- Initial manager account creation, if required, must use a secure one-time provisioning process without hard-coded production passwords/secrets.
- Demo children, guardians, subscriptions, payroll records, cash movements, fake invoices and test file references belong in **local/testing fixtures only**.
- Never use production real personal data in public CI fixtures. Keep secrets in protected runtime configuration.
- Re-running approved seeders should not duplicate rows or change real financial history.

## 15. Backups, Quarterly Restore Tests & Go-Live Safety

**Hosting-provider-managed daily backups are mandatory before V1 go-live.** The provider must actually support database backups and restoration; an unverified checkbox or untested downloadable archive is insufficient.

| Concern | Approved requirement |
|---|---|
| Database | Daily automated **MySQL backup** by hosting provider |
| Private attachments | Included in the overall recovery plan, with regular backups suitable for data consistency |
| Before risky migrations | Obtain a fresh recovery point and verify restoration path |
| Restore frequency | **Every 3 months**, plus after significant database/backup architecture changes |
| Test target | **Isolated nonproduction environment**, never overwrite live production as a test |
| Security | Limited access, protected backups, transport/storage encryption where supported |
| Retention | Choose and verify against actual host capabilities; **no invented guaranteed day count** |
| Recovery verification | Relational links, historical money, timer segments, subscriptions, attendance, contracts, payroll and private files |

**Restore-test procedure (operational target):**

1. Confirm daily backup jobs completed and that a current DB + relevant private-files recovery point exists.
2. Restore into a protected **isolated** environment with no real outbound messages/payments and access limited to authorized testers.
3. Check DB migration/schema version and counts, representative FKs, child/guardian and subscription links, classes/attendance, timer segment history, immutable financial movements, cashier and payroll links.
4. Reconcile representative money totals and approved payroll/payout snapshots with their sources; verify private attachment authorization and expected file availability.
5. Record test date, backup point, restore result, detected gaps, recovery steps and the responsible operator. Securely remove the test copy after verification.
6. Repeat before launch, **quarterly**, and after major changes affecting data or backups.

If the hosting plan lacks required daily backup, private-file recovery or practical restoration capabilities, **deployment is not ready**. Do not add Laravel cron/queues merely to compensate without an explicit architectural change.

## 16. MySQL 8 Database Testing & CI

**Approved: Risk-Based Database Testing.** Use PHPUnit/Laravel Feature and Integration tests against real **MySQL 8** for DB-specific behavior; SQLite-only success is not evidence that InnoDB locking/constraint behavior is safe.

### 16.1 Every PR to `develop`

- Run a **fresh isolated test database migration** and relevant PHPUnit tests against MySQL 8.
- Run the already-approved **Laravel Pint check** as a required CI gate once GitHub Actions is configured.
- Keep test data independent, reproducible and never sourced from real Production.
- Failure of constraints, transactions, API contract tests, or relevant business tests blocks merging.
- `migrate:fresh` is permitted on isolated CI/testing data only, never on a live database.

### 16.2 Upgrade migration tests — required by risk

For **changes that touch populated tables, data migration/backfill, uniqueness, nullability, foreign keys, historical values, or destructive/rename/type constraints**, test upgrading **an existing previous schema with representative existing data**, not just creating a fresh schema.

Example: adding an effective contract reference to historical payroll rows requires proving old payroll still points to correct applicable terms after backfill and FK enforcement. New isolated tables with no compatibility risk need not incur artificial upgrade fixtures for every migration.

### 16.3 Minimum database-backed coverage

| Behavior | Required assertion |
|---|---|
| FK/delete | A referenced child/group/employee/posted record cannot be deleted destructively |
| Hybrid delete | Inactive/archived records remain historically accessible where authorized and ineligible for new invalid operations |
| Uniqueness | Duplicate child attendance for one occurrence is rejected; legitimate renewals/parallel timers remain permitted |
| Financial atomicity | Failed settlement, installment, refund, payroll payout or expense leaves **no partial posting** |
| Idempotency | Same key+payload one result; key with different payload rejected; simultaneous duplicates never double-post |
| Money | Exact `DECIMAL(12,2)` persistence and arithmetic without floating-point drift |
| Timer segments | Correct pause exclusion, transitions, parallel timers, no two open segments for one timer |
| Subscription history | Renewal preserves previous price/date/attendance/payments; grace make-ups retain original reference |
| Capacity | Concurrent registration for the last group seat cannot overfill the group |
| Occurrences & payroll | Trainer delivery/substitution credited once; no duplicate payable class |
| Contracts & snapshots | Later pay raise cannot change old finalized payroll; no overlapping incompatible contract ranges |
| Timezones | UTC events and local recurrence/date-boundary/DST handling are correct for configured IANA zone |
| Migrations | Fresh schema passes; upgrade/backfill tests run when risk warrants them |
| Backups | Periodic **operational restore drill**, not merely a passing PHPUnit assertion |

### 16.4 Real concurrency tests

- Where behavior relies on `lockForUpdate()`, isolation, deadlock recovery or concurrent uniqueness, use **separate real MySQL connections/processes/transactions** to create actual overlap.
- Sequentially sending two HTTP requests is not enough to prove race safety.
- Some Laravel test wrappers place a test in a transaction that can **mask real lock contention**. Design these tests to exercise actual independent connections.
- Verify at most one settlement, one effective idempotency posting, no oversubscribed group, no duplicate paid class and no simultaneous open timer segments.
- Exercise safe retry behavior following simulated deadlocks/rollbacks without duplicating money.

**Implementation status:** This document defines **required future checks**; it does not claim migrations, the app, CI jobs, branch protection or tests are already installed or passing. When CI/branch rules are configured, enforce the PHPUnit/MySQL 8 and Pint gates on PRs targeting `develop`.

## 17. Database Change Review Checklist

Before merging a schema or sensitive database-write change, verify:

- [ ] Correct business module owns the table/migration/Action and required parent tables exist first.
- [ ] One MySQL 8 / InnoDB database; no tenant/branch/extra schema infrastructure.
- [ ] Tables/columns are `snake_case`, public API names remain `camelCase`.
- [ ] Normal primary/foreign IDs use matching unsigned BIGINT types.
- [ ] Defaults/nullability reflect actual business semantics; integer enums are stable and booleans used appropriately.
- [ ] Every money column uses `DECIMAL(12,2)` and calculations avoid SQL/PHP floating point.
- [ ] Actual instants are UTC; local recurring times are tied to an IANA timezone; calendar dates use `DATE`.
- [ ] Historical parents and financial records cannot be erased via unintended cascade or soft deletion.
- [ ] Delete/deactivate/archive eligibility is verified by the owning Action; children/guardians' privacy is protected.
- [ ] Real uniqueness is backed by MySQL constraints, not only validation or a pre-insert query.
- [ ] `NULL` semantics in unique indexes have been considered, especially soft-deletes/open timer segments.
- [ ] Session close, collections, refunds, capacity and payouts lock rows and commit/rollback atomically.
- [ ] Sensitive replayable commands use scoped idempotency and source posting uniqueness.
- [ ] Posted Finance movements are positive-amount + integer-direction, immutable and traceably corrected.
- [ ] Original subscription periods, class occurrences and make-ups retain independent references and history.
- [ ] Timer segments/price snapshots remain authoritative after Finish, without per-second DB updates.
- [ ] Historical effective-dated employee contracts and finalized payroll snapshots are preserved.
- [ ] Indexes match actual query/filter/sort joins; duplicates and write overhead are reviewed.
- [ ] Existing data can survive the forward migration; risky DDL/backfill has representative upgrade tests.
- [ ] Production seeders are idempotent and never inject demo children, debts, invoices or movements.
- [ ] Recent hosting backup and quarterly/major-change restore plan are verified; uploads are recoverable.
- [ ] Relevant PHPUnit/MySQL 8/real-concurrency checks and Pint actually ran; do not claim success without execution.
- [ ] No new queue, worker, Redis, cron or unrelated platform added to implement this feature.

## 18. Explicit Non-Goals & Change Rule

**Do not add without a separately approved business/architecture requirement:**

- Multi-tenancy, multi-branch isolation, per-tenant databases, branch selectors, or extra service databases.
- UUID/ULID on every record, CQRS/event-sourcing infrastructure, repositories/DTOs per table, generic accounting engines or speculative polymorphic ledgers.
- General ledger/double-entry accounting, chart of accounts, bank reconciliation, or the Tabaa-specific customer-ledger and opening-balance model.
- Multi-currency pricing/conversion, unnecessary currency columns, or payment gateway clearing/webhook infrastructure.
- A universal soft-delete policy, destructive cleanup of posted finance, or automatic purging of attendance/payroll history.
- Scheduled recurring invoice/renewal generation, expiry cron tasks, queued payouts, Redis locks, asynchronous financial processing or timer-per-second DB writes.
- General attachment platform, speculative stock/warehouse/accounting tables, or unrelated printing/order-production schema.
- Exact app-specific status numbers, monetary rounding formulas, class-schedule eligibility, make-up capacity rules or payroll prorations **before the owning feature specification approves them**.

### Source-of-truth boundaries

| Document | Authority |
|---|---|
| [Project Overview](../00-overview/project-overview.md) | Approved business flows, V1 scope and module inventory |
| [Backend Architecture](../01-architecture/backend-architecture.md) | Monolith, module ownership, runtime, hosting and deployment constraints |
| [Code Standards](code-standards.md) | Actions/controllers/Services, PHP style, code tests and Pint |
| [API Standards](api-standards.md) | HTTP routes, `camelCase` request/response, pagination, Sanctum, idempotency headers and API errors |
| **This Database Standards file** | Shared MySQL persistence, precision, integrity, lifecycle, migration, testing and restore requirements |
| Feature specifications | Exact table schema, field names, enum values, business transitions, queries, rounding, and approved edge cases |

**Change rule:** A change to a **single feature's columns, exact indexes or business workflow** belongs in its feature spec and migration. A change to a **shared cross-module persistence principle** requires review and an update to this file and any conflicting architecture/API examples. Future deployment to other places may change a configured IANA timezone, but **does not silently approve multi-branch architecture**.

> **Status note:** These are approved design standards. No claim is made that tables, migrations, seeders, MySQL tests, backups/restore drills, GitHub Actions or branch-protection checks have already been implemented or run.