# Activities Module — Module Reference

> **Status:** Business and module design decisions D01–D102 approved on 2026-11-26; module reference submitted for documentation review. No Activities application implementation or verification has started.
> **Scope:** V1; one location; Laravel 12 modular API-first backend, MySQL 8 / InnoDB, shared hosting, Arabic-first.
> **Canonical path:** `docs/03-modules/activities.md`.
> **Owning module:** `Modules/Activities`.
> **Related:** [Project Overview](../00-overview/project-overview.md), [Backend Architecture](../01-architecture/backend-architecture.md), [Identity](identity.md), [API](../02-standards/api-standards.md), [Database](../02-standards/database-standards.md), [Code](../02-standards/code-standards.md), [Permissions](../02-standards/permissions-standards.md), [Errors](../02-standards/error-handling-standards.md), [Localization](../02-standards/localization-standards.md), [AI Workflow](../02-standards/ai-workflow-rules.md).

## 1. Purpose, ownership and superseding decisions

One activity catalogue supports entertainment, games, education and nursery. An activity may offer hourly use, subscription use, or both independently. The manager chooses the available modes and their prices. Nursery is configured as an ordinary subscription-only activity; no separate nursery subsystem, hardcoded ID, activity type/category, image or description is required. The monthly price is a **unit price**, not a restriction to a one-month contract.

Walk-in hourly operation belongs to Sessions: no child record is required; the rate is not multiplied by the number of children; an existing subscription neither includes nor prevents separately charged hourly play. Sessions owns independent timers, time segments, rate snapshots and session settlement. Activities does not implement timers or a second money ledger.

Latest approved changes supersede earlier discussion: **multiple trainers** per group (D79), group operating start and optional end (D82/D85), subscriptions for **1–12 whole calendar months** (D83–D86), automatic bounded rolling generation (D80/D87), exceptional classes (D81/D89), and price-change confirmation (D90). Do not reintroduce single-trainer-only, one-month-only, perpetual-group-only, or purely manual/lazy-only class creation.

| Owner | Authoritative facts |
|---|---|
| Activities | Catalogue, billing modes/rates, group periods, trainer assignments, weekly schedules, class identity/occurrences, subscription eligibility/period/price agreement, child attendance, make-up entitlement/booking, operational history |
| People | Children, guardians (mandatory name/mobile), customers and their relationships |
| Employees | Employee identities, jobs, employment/contract history; employees need not be system users |
| Workforce | Actual trainer attendance/delivery evidence, staff shifts, operational attendance and exceptions |
| Payroll | Pay calculation using approved delivered-class evidence and contract terms; approvals and payouts |
| Sessions | Walk-in customer sessions, independent timers and hourly rate snapshots |
| POS | Invoices, payment receipts/methods, refunds and cashier shifts |
| Finance | Canonical financial movements and financial reporting facts |
| Identity/Core | Current authenticated staff authority, module-owned permission aggregation, common Select Registry, API envelope, localization, shared BusinessException |

Excluded: freezing, automatic renewals, part-month subscriptions/prorating, standalone group-level pricing, overpayment/customer wallet or spendable credit, client login, multibranch, new audit subsystem, queues/Redis/workers/cron dependence, frontend implementation. Shared Audit remains explicitly deferred; source operational and financial history is mandatory regardless.

## 2. Catalogue (initial approvals, D01–D05, D67)

### 2.1 Data and invariants

Logical `activities` data (first feature):

| Field | Storage / rule |
|---|---|
| id | BIGINT UNSIGNED primary identifier |
| name | VARCHAR(255), required, trimmed at edges, Arabic or English, unique across retained active/inactive records |
| supports_hourly | Boolean; independent of subscription mode |
| supports_subscription | Boolean; independent of hourly mode |
| hourly_rate | DECIMAL(12,2), required strictly positive if hourly is enabled; NULL otherwise |
| monthly_price | DECIMAL(12,2), required strictly positive if subscription is enabled; NULL otherwise |
| is_active | Boolean, defaults true on creation |
| created_at / updated_at | Standard timestamps |

At least one mode must be enabled. Disabled-mode price must be omitted or explicitly null; reject an incompatible non-null price, not silently accept it. Monetary inputs are exact two-decimal-compatible values; validate syntax, range and precision before persistence. No separate persisted billing_mode column. Name equivalence/actual collation and exact SQL constraints must be verified in the catalogue plan; do not rely on an application duplicate precheck alone.

`ActivityBillingMode` is an int-backed query enum: hourly=0, subscription=1. An activity supporting both matches either filter. Missing filter means all. Zero is a valid value, not empty. API booleans are JSON booleans; filter codes 0/1 are explicit query values.

### 2.2 Lifecycle

Disable the activity or remove a supported mode to prevent new usage/enrollments/renewals for that mode; allow previously opened timers, confirmed subscriptions, outstanding financial collections and entitled historical operations to continue under their agreed conditions. Changing a price affects only newly agreed contracts/timers. Preserve original values in source snapshots. PUT is full replacement, including both flags and active state, with valid enabled prices. POST defaults active if omitted. Do not add a separate status-toggle route.

Hard delete only when no protected dependency exists, including any group (even unused), historical subscription, class/timer, operational or financial reference. A group must be explicitly removed first if independently deletable. Otherwise return a business conflict and offer deactivation. Restrictive foreign keys and a consistent concurrency protocol prevent a new dependent racing a delete/disable. Catalogue may be implemented independently; add actual cross-module deletion protection as each real dependency is introduced, before dependent writes become available.

### 2.3 Catalogue API

All paths below are relative to /api/v1. Standard success/error envelopes; GET message is empty. CRUD mutations return data: [] by default.

| Method | Path | Permission |
|---|---|---|
| GET | /activities | activities.index |
| GET | /activities/{id} | activities.show |
| POST | /activities | activities.store |
| PUT | /activities/{id} | activities.update |
| DELETE | /activities/{id} | activities.delete |

Example create/replace:
```json
{
  "name": "السباحة",
  "supportsHourly": true,
  "supportsSubscription": true,
  "hourlyRate": "60.00",
  "monthlyPrice": "800.00",
  "isActive": true
}
```
GET Resource returns id plus these fields; disabled prices null. No group/subscriber counts or unrelated relationship expansion. Index allowlist: filter[search] (bounded name search), filter[isActive]=0/1, filter[billingMode]=0/1. Default id DESC. Standard page/perPage, 15 default, max 100 (clamp valid larger requests); explicit allowed sorts/includes in spec. No implicit full-model serialization. Select keys activities and activeActivities are described in §10.

## 3. Groups, operating periods, trainers and weekly schedules (D06–D13, D68, D79, D82–D85, D88, D100)

### 3.1 Group data

A group belongs to exactly one subscription-supporting activity; activity_id is immutable. Name unique within that activity, including disabled groups. No group-specific price, description or image. A group requires at least one unique eligible trainer (real Employees IDs), a nonempty weekly schedule, an operating startDate (today or future), and optional endDate. Capacity is NULL for unlimited or a strictly positive integer. isActive controls new admission; it is separate from date eligibility.

- startDate is the first allowed operating date; group endDate is the **inclusive last operating day**, or NULL for a continuous group. No class before start or after end. A subscription cannot start before group start or extend beyond its operating window. For an inclusive group end G, an exclusive subscription end E must be on/before G + one local calendar day. Group date expiry is computed, not a scheduler transition.
- Extend/remove an end date, or define an end for a continuous group, subject to confirmed commitments. Shortening must not invalidate existing subscriptions, their eligible operation or confirmed bookings; moving the start is allowed only before operation and without conflicting references. Synchronize future generated occurrences. Do not delete previous class/attendance history.
- Disabling a group/activity or subscription mode blocks new subscriptions and renewals. Existing confirmed subscriptions retain their entitled classes and financial collections; disabling is not a mass class cancellation. Continue generation necessary for existing obligations and honor valid confirmed make-up bookings. New enrollment selects exclude inactive/ineligible records.
- Hard delete only if no protected subscriptions, classes/history or other dependents. Mere future generated occurrences create an important distinction from prior unused-group deletion; the feature plan must explicitly define whether/how pristine unbooked generated schedule artifacts may be removed transactionally before deleting a truly unused group. No deletion of executed, cancelled, attended, booked or otherwise protected history; default RESTRICT. Do not silently make all groups deletable or cascade historical data.

### 3.2 Multiple trainers and recurring slots

Use a many-to-many group/trainer relationship with effective periods (`activity_group_trainers`); not a single trainer_id column. Keep historical assignments. All configured trainers may co-deliver a class. A class-specific trainer assignment history (`activity_class_trainers`) allows substitutions, additions and actual delivery evidence linkage. Do not pay a trainer simply for membership or assignment. Eligible per-class compensation is for each trainer who actually participated in delivery, based on Workforce evidence and the trainer's contract; a replaced non-delivering trainer or a merely present trainer is not credited. A cancelled class has no delivery pay.

Weekly slots store day, local start/end time and effective period/history. Each weekday may have distinct times and several non-overlapping slots; adjacent intervals may meet at the same boundary. Approved API dayOfWeek codes Sunday=0 through Saturday=6. Prevent overlap within a group and between any assigned trainers' applicable schedules across groups. Consider continuing obligations of disabled groups, date/effective periods, exceptional/rescheduled occurrences and substitutions, not just active flags. Actual trainer eligibility and conflict checks are repeated in locked domain writes. Keep past schedule/trainer history even for an occurrence that was not originally materialized. Changes apply prospectively; protect confirmed bookings/classes and reject unresolved conflicts rather than rewriting history.

### 3.3 Capacity

A group's limit counts all overlapping regular membership/reservation intervals (confirmed future subscriptions included), plus confirmed make-up bookings for the affected class. Check **peak occupancy** over affected date/instant ranges rather than one current count or all historical subscribers. A regular place counts even if the child later misses that class. Make-up seats are class-specific, not permanent membership. New regular enrollment/renewal/transfer cannot displace confirmed make-up seats. Capacity reduction must respect already committed peak occupancy. Cancelled/expired/ended membership frees future seats as applicable. Serialize the last-seat race; a failed transfer leaves the original place intact.

### 3.4 Groups API

| Method | Path | Permission suffix under activities.groups |
|---|---|---|
| GET | /activity-groups | index |
| GET | /activity-groups/{id} | show |
| POST | /activity-groups | store |
| PUT | /activity-groups/{id} | update |
| DELETE | /activity-groups/{id} | delete |

```json
{
  "activityId": 1,
  "name": "مجموعة A",
  "trainerIds": [5, 20],
  "capacity": 15,
  "startDate": "2026-12-01",
  "endDate": null,
  "isActive": true,
  "schedules": [
    {"dayOfWeek": 6, "startTime": "16:00", "endTime": "17:00"},
    {"dayOfWeek": 2, "startTime": "18:00", "endTime": "19:00"}
  ]
}
```
PUT replaces authorized mutable configuration, not history; activity reassignment forbidden. Filters: search (name), activityId, trainerId (membership), isActive. Precise effective-date payload, list/detail shape, slot constraints and time ranges belong to feature spec/plan. Existing authorization checks apply to embedded child/trainer projections. No HR salary or contract details exposed.

## 4. Class occurrences, rolling generation and exceptions (D25–D27, D56, D71, D80–D81, D87–D89, D94, D100)

Weekly schedules are recurring templates; actual class occurrences are separate persisted records. Approved automatic Rolling Horizon Generation is an application strategy, **not Laravel Horizon, not a scheduler/worker or a dependency**.

- Create the applicable regular occurrences for the next 60 local calendar days automatically when a group is created. Respect the group operating window and applicable effective schedule/trainer versions. Generation proceeds at ordinary authorized write events, not manual creation of every class.
- Extend/repair missing future records on relevant group changes and on subscription creation/renewal. Guarantee necessary coverage for the **entire confirmed subscription period**, including 12-month contracts, even beyond the ordinary 60-day horizon. Transfers must preserve destination commitments. Generate only within authorized bounded windows; exact boundary/anchor, future-start bounds and synchronous batch limits require feature plan approval. Protect existing obligations of disabled groups.
- A future class screen may request an authorized, bounded, explicit write synchronization automatically when needed; it must not mutate through GET. The read API may project missing expected slots without writes. After inactivity, no background progress is promised; on next use reconcile missing applicable dates/history without retroactively fabricating completed classes or attendance.
- Stable regular occurrence identity must survive reschedule/cancel and schedule-version changes; use a reviewed database uniqueness rule and locked reconciliation. Generate missing occurrences only; no duplicate class, resurrection of cancelled class, overwritten individual reschedule, lost exceptional class, or destructive rebuild. Avoid naive insert-or-update that replaces manually maintained facts. Tests must cover concurrent generation/edits and catch-up.
- Manager may add an exceptional class outside the usual weekly recurrence but within the group's operating period, with date, time and selected trainers. It is a genuine class, same capacity, conflict, attendance, make-up and actual-delivery pay rules; no additional subscription charge. Reschedule an existing class instead of creating a second one when changing its time.
- Future reschedule, cancellation and class-specific trainer substitution are explicit commands; preserve stable identity, booked-child eligibility, original facts, and relevant reason/history; verify destination eligibility and conflict before changes. Existing bookings that become invalid must be resolved before changing the schedule. No implicit extensions of subscription dates.
- Class status: scheduled=0, completed=1, cancelled=2. Past time does not imply completion. No auto attendance/payroll. Explicit completion with the eligible roster and actual delivery evidence; cancellation requires manager-default permission and reason, excludes delivery pay, not an absence for all children. Manager chooses whether a cancelled regular class is compensable. Cancelled booked make-up returns the existing entitlement if still valid (§7).

| Method | Path | Purpose / approved capability |
|---|---|---|
| GET | /activity-classes | Bounded expected/actual class listing; classes.index |
| GET | /activity-classes/{id} | Persisted class detail; classes.show |
| POST | /activity-classes | Authorized materialization; classes.store for ordinary classes |
| POST | /activity-classes/{id}/complete | Explicit delivery/roster completion; classes.complete |
| POST | /activity-classes/{id}/cancel | Cancel with reason/compensation choice; classes.cancel |
| POST | /activity-classes/{id}/substitute | Class-specific trainer substitution; classes.substitute |
| POST | /activity-classes/{id}/reschedule | Move an occurrence; classes.reschedule |

All capability suffixes here use activities prefix. Filters: activityId, groupId, trainerId, status, dateFrom/dateTo. Limit date range and projection cardinality before DB work. Precise request identity for projected slots and materialization, sync command route/permission and manager-only exceptional-create authorization must be specified before implementation; the 31 approved capabilities do not yet allocate a distinct exceptional-create or sync key. Do not expose privileged exceptional writes through ordinary reception class creation. Obtain approval for any added capability/route, not a hardcoded role check. No GET mutation.

## 5. Subscriptions (D14–D24, D43–D52, D55, D69–D70, D83–D87, D90, D95, D101–D102)

### 5.1 Contract and date model

A subscription is a registered child's agreement for one activity and selected group, with start, computed end, durationMonths=1..12, saved monthly price, original and agreed price snapshots, creator, cancellation state/history and previous subscription link for renewal. Child/guardian records come from People. Activity derives from the group; group activity never changes. Keep historical group assignment intervals (`activity_subscription_groups`) for transfers; one group at a time with no overlap. Exact FK layout and effective-period representation belong to the plan, not duplicate competing current and historical facts. Do not add redundant paid/remaining/status copies.

Start today or future only. Subscription start inclusive, end **exclusive**. End = add the selected number of calendar months directly to original start without overflowing the target month; clamp to its last date if the day does not exist. Do not add months iteratively with intermediate clamp. Examples:

| Start | Months | Exclusive end |
|---|---:|---|
| 2026-12-01 | 3 | 2027-03-01 |
| 2026-12-15 | 2 | 2027-02-15 |
| 2027-01-31 | 1 | 2027-02-28 |
| 2027-01-31 | 2 | 2027-03-31 |
| 2028-02-29 | 1 | 2028-03-29 |

Group start <= subscription start; subscription exclusive end <= inclusive group end plus one day if finite. No part-month subscription/prorating. Reject >12, zero, fractions and impossible dates. Compute using local calendar dates and configured timezone (Africa/Cairo default), not UTC date or a fixed number of days. Renewal follows its new start; do not silently promise a preserved original billing-day anchor when a clamped prior end changes it.

Statuses computed: upcoming=0, active=1, expired=2, cancelled=3. Persist cancellation facts; cancellation overrides temporal states. No expiry cron. One noncancelled overlapping subscription per child per activity, including future reservations. Different activities may overlap. Warn on regular schedule clashes between different activities without blocking enrollment; separately reject overlapping make-up bookings/attendance and actual simultaneous attendance by the same child. Warning contract and bounded schedule-check strategy belong to feature spec. Same-activity conflict and capacity enforced atomically.

### 5.2 Pricing and collection

Saved monthly unit price × months = originalPrice; authorized fixed discount (not percentage) produces agreedPrice > 0. Freeze agreement and initial pricing for that subscription. Do not trust client-calculated end/total/balance. Confirm displayed unit price (or equivalent approved price version) against authoritative current price while locked. If changed since preview, fail with ACTIVITY_PRICE_CHANGED and require a refreshed confirmation, rather than silently billing the changed price. The exact confirmation field/preview contract is a spec decision. Use exact decimal arithmetic, validate DECIMAL(12,2) storage bounds/overflow before posting. Monetary API strings.

A new subscription may be unpaid, partially paid, or fully paid; no effect on temporal status. Issue one sale/financial obligation, not one sale per month. Later collections reduce debt; no new sale. Methods proposed and approved at module level cash=0, card=1, wallet=2 (coordinate final POS contract), one method per installment, different installments allowed. Positive collection requires method; no zero payment movement. Reception financial operations require an open cashier shift. Use POS authorization in addition to Activities command permission where money/discount/refund/settlement is involved; do not infer collection privilege from enrollment. No new overpayment/customer wallet credit in V1.

### 5.3 Renewal, transfer, cancellation

- Manual renewal creates a distinct linked agreement, using current agreed price for its selected number of months (1–12) and its own financial obligation. Early renewal starts at previous exclusive end; late renewal starts today or a valid chosen future date. Prevent duplicate renewal and overlap under concurrency; preserve previous balances and dates. Previous debt does not block renewal. Do not automatically merge or pay old debt. A later cancellation of the earlier contract does not automatically cancel an already renewed contract.
- Transfer within the same activity only; preserve agreed price, duration and original contract, move group membership intervals atomically, recheck destination dates, schedule, capacity and confirmed bookings. Previous attendance stays attributed to original class/group. Effective date defaults next day or today if safe, with exact cutoff in feature spec. No direct transfer to a different activity; use authorized cancellation and new agreement. Preserve history; do not permit raw child/group/price/date edits.
- Cancellation is separately authorized, reason mandatory; retain record, free future capacity, stop future attendance/new make-up use, resolve affected bookings, preserve completed history. No delete. Manager chooses final payable amount from 0 to original agreedPrice; persist adjustment distinct from original sale, no automatic money refund. Outstanding adjusted debt may still be collected after expiry/deactivation/cancellation. Refund liability is not a spendable credit. Separate permission and reason for partial/full eligible refund; financial correction on active contract may increase debt. See §8. Cancel before start must not create an invalid effective membership interval. Exact history fields and state transition cutoffs require technical plan.

### 5.4 Subscription API

| Method | Path | Permission suffix under activities.subscriptions |
|---|---|---|
| GET | /subscriptions | index |
| GET | /subscriptions/{id} | show |
| POST | /subscriptions | store |
| POST | /subscriptions/{id}/renew | renew |
| POST | /subscriptions/{id}/transfer | transfer |
| POST | /subscriptions/{id}/cancel | cancel |

No generic PUT/DELETE. Discount adds activities.subscriptions.discount permission. Money-bearing commands also enforce owning POS/Finance permissions, session/shift checks and idempotency.

Conceptual create request (final expected price confirmation field still to approve):
```json
{
  "childId": 12,
  "groupId": 3,
  "startDate": "2026-12-01",
  "durationMonths": 3,
  "discount": "100.00",
  "receivedAmount": "1000.00",
  "paymentMethod": 0
}
```
For a confirmed monthly price of 800.00, originalPrice 2400.00, agreedPrice/dueAmount 2300.00, paidAmount 1000.00, remainingAmount 1300.00, refundableAmount 0.00, exclusive end 2027-03-01. Backend derives these, and dates/availability under current state. Minimal list/detail projections include id, child/activity/group id and name, dates, durationMonths, saved monthly unit price, originalPrice, discount, agreedPrice, dueAmount, paidAmount (define as net collected consistently with financial source), remainingAmount, refundableAmount, status, paymentStatus, subject to permissions. Status depends on as-of date. No full model tree or guardian/HR private fields.

Filters: search (child name), childId, activityId, groupId (current assignment), status, paymentStatus, startDateFrom/startDateTo. Define precise current/upcoming assignment semantics, bounded filters, safe sorts/includes and as-of consistency in spec. One future frontend subscription screen with these filters and authorized detail actions; no separate screens needed per status; no frontend implementation now. Create/renew returns minimal ID if workflow requires it, not full model. Other ordinary writes data: [] unless documented need.

## 6. Child attendance and trainer delivery (D28–D33, D72, D79, D88–D89, D96)

Determine eligible roster from subscription dates and effective group membership at the class's actual relevant date/instant plus confirmed make-up bookings. No attendance outside eligibility; avoid double-counting a child attending the same target as both regular and makeup. One attendance record per class/child; absent=0, present=1, no row means unmarked (not automatically absent). Reception may mark one child or submit a batch. Batch atomic: all valid or none; repeated submission must not create duplicate evidence. State/authorization/eligibility rechecked under lock; prevent same child marked present for overlapping actual classes. Mark all eligible children before completion. Past/future roster snapshot, delivery confirmation timing and correction behavior must be precisely specified. Do not let future enrollment, transfer, edit or cancellation retroactively rewrite completed class evidence.

Workforce records actual trainer attendance/delivery; Activities does not build duplicate employee attendance. Every assigned or substituted trainer who actually co-delivers can earn eligible per-class compensation once for that class according to contracts, not every assigned or physically present person. Zero children requires explicit confirmation of actual delivery; never infer from schedule/time. Cancelled class yields no delivery pay. Explicit corrections require separate manager-default permission and reason, retain original and corrected facts, and protect dependent entitlement, bookings, attendance and payroll already calculated/paid. Resolve conflicts via reviewed corrective workflows instead of destroying history. Shared Audit deferred, but `activity_attendance_corrections` operational history required.

| Method | Path | Capability |
|---|---|---|
| GET | /activity-classes/{id}/attendance | activities.attendance.index |
| POST | /activity-classes/{id}/attendance | activities.attendance.store |
| POST | /activity-classes/{id}/attendance-corrections | activities.attendance.correct |

Example recording: {"attendances":[{"childId":12,"status":1},{"childId":15,"status":0}]}. Class completion and child/teacher evidence must be integrated against real Workforce contracts/evidence before that operational flow is released.

## 7. Make-up entitlements and bookings (D34–D42, D73, D96, D99–D100, D102)

- Create at most one entitlement per eligible original **regular** class/subscription for a recorded absence on an executed class or a management-cancelled class explicitly marked compensable. No entitlement for presence, unmarked roster, ineligible/out-of-period attendance or cancelled subscription. Manager chooses compensation on class cancellation; cancelled class is not recorded as all children absent.
- Target any eligible group of the same activity, within entitlement window and target operating dates, and with actual eligibility and capacity. Child cannot already be a regular participant in the target, nor book an overlapping class/makeup. Existing rights remain valid despite renewal and disabling of new enrollment, subject to eligible continuing classes; do not silently block existing commitments solely by an activity/group active flag.
- Actual compensating class must occur during original subscription or in the **seven local calendar days after exclusive expiry**. Example exclusive end Dec 1, grace Dec 1–7 inclusive, Dec 8 exclusive. Merely booking before expiry is insufficient. A finite group is not extended for make-ups: after group ends, another eligible same-activity group may be used; if none is available the entitlement expires. Renewal never resets or merges old entitlement validity. No additional invoice/fee.
- Reserve one class seat on confirmed booking; capacity counts regular commitments plus existing confirmed makeups, regardless of anticipated absence. Prevent duplicate live booking/use for the same entitlement and overlapping child booking under concurrency. Keep booking history.
- Entitlement status codes: available=0, booked=1, used=2, expired=3, cancelled=4, forfeited=5. Expiry/eligibility derived by date rather than cron; do not overwrite historical terminal states. Mark consumption through actual attendance, not a separate unconstrained consume button. A no-show at a makeup forfeits entitlement, no new recursive entitlement.
- Cancel booking before target start, or manager cancels target: release seat and return original entitlement to available only if still valid (no extension); otherwise expired. Cancelled target containing a makeup booking is handled as release of that original right, not creation of a second right for the makeup itself. Cancellation of original subscription stops future make-up use and releases affected commitments. Correction of completed original/target attendance must respect booked/consumed rights and downstream facts.
- Entitlement creation, consumption, correction and seat release are atomic with respective class/attendance/booking actions.

| Method | Path | Capability |
|---|---|---|
| GET | /makeup-entitlements | activities.makeups.index |
| GET | /makeup-entitlements/{id} | activities.makeups.show |
| GET | /makeup-entitlements/{id}/available-classes | authorized show/availability read; exact policy in spec |
| POST | /makeup-entitlements/{id}/bookings | activities.makeups.book |
| POST | /makeup-bookings/{id}/cancel | activities.makeups.cancel |

No unconstrained manual entitlement-create, independent consumption, or history deletion endpoint. Exact filters, date bounds, book/cancel payload, eligible class projection and state transition timing in feature spec.

## 8. Financial integration (D43–D52, D62, D64, D74, D97)

Activities owns eligibility and agreement; POS owns the invoice, receipts, refunds, shift operations; Finance owns canonical movements. Same InnoDB transaction for the subscription (including seats), corresponding obligation/invoice, optional initial collection and required ledger movement. No fake financial writes, stubs released as working financial functionality, second payment table or duplicate Activities collection/refund endpoints. Later installments/refunds use owning POS API against correct subscription-linked invoice, not a new sale. Define exact source relation/unique keys, invoice numbering and POS method enum and permissions in POS/Finance specs before releasing subscription financial writes. One authoritative record per fact.

Financial terms: originalPrice and agreedPrice preserved; final payable after authorized adjustment = dueAmount; net collected = actual payments less eligible posted refunds. remainingAmount = max(dueAmount - net collected, 0); refundableAmount = max(net collected - dueAmount, 0), subject to validated eligible refundable sources. Refunds separate from cancellation and never exceed eligible amounts; refund adjustments that correct active receipt errors may increase debt under approved rules. No excess payment, negative amounts, zero-value movements or spendable wallet balance. Cancel adjustment 0..agreedPrice only; retain actor, reason, amounts and time, do not silently issue cash. Payment status unpaid=0, partial=1, paid=2; a zero due/zero received settled contract is paid (net received >= payable); refund liability remains independent. Derive paid/balance/status from authoritative consistent financial reads, not copied state. Historical dues remain collectible under appropriate payment/cashier authority even when subscription expires, is disabled or cancelled.

Idempotency-Key required for duplicate-sensitive create, renew, transfer, cancel/settlement, makeup booking, installment/collection, refund and other approved critical commands. Same key + same equivalent request replays prior result without new effect; same key + materially different input -> 409 IDEMPOTENCY_CONFLICT. Authenticate/authorize current caller before replay; plan scope, canonical fingerprint, retention, locking and ambiguous commit/retry behavior per standards; no indefinite speculative storage requirement. Not blanket ordinary Activity/Group CRUD. Do not send side effects that cannot roll back inside retryable transactions.

## 9. Persistence, concurrency, error and time design (D53–D65, D88, D91–D98)

### 9.1 Logical table inventory (not an approved migration list)

The ten earlier proposed Activities tables plus two trainer relationship tables from D88:

| Table | Responsibility |
|---|---|
| activities | Catalogue and current unit rates |
| activity_groups | Group identity, operating window and capacity |
| activity_group_trainers | Effective-dated group/trainer assignments |
| activity_group_schedules | Effective-dated weekly slots |
| activity_subscriptions | Child agreement, price snapshots, duration, dates, cancellation and renewal linkage |
| activity_subscription_groups | Non-overlapping group assignment intervals and transfers |
| activity_classes | Persisted regular/exceptional occurrences and identity, state/history |
| activity_class_trainers | Class assignments/substitutions and links to Workforce evidence |
| activity_class_attendances | Eligible child attendance, unique class/child |
| activity_attendance_corrections | Prior/new facts, reason, actor/time |
| activity_makeup_entitlements | Unique original regular-class/subscription right and lifecycle |
| activity_makeup_bookings | Booking history, target class, seat commitment |

Tables are created only as their approved feature needs them. Additional necessary history representation or constraint helpers must be reviewed in the feature plan. No parallel employee, guardian, invoice, payments, ledger, payroll or audit tables in Activities. Explicit FKs to real owner tables; RESTRICT by default; cascade only specifically approved disposable nonhistorical rows. Preserve history and protect deletion of referenced staff, children, activities/groups; expand existing Identity/People/Employees deletion rules as dependencies become real. Do not invent placeholders or defer referential integrity silently.

### 9.2 Database integrity

Use MySQL 8/InnoDB; unsigned compatible keys; DECIMAL(12,2) monetary snapshots; DATE local business dates, TIME local recurring slots, UTC authoritative actual instants. Configured IANA timezone Africa/Cairo default; handle ambiguous/nonexistent DST times explicitly (reject ambiguous/nonexistent input with safe explanation); no fixed UTC offset or browser authority.

Require DB unique constraints for name, group activity/name, stable regular occurrence identity, class/child attendance, original class/subscription entitlement, single renewal link; verify actual collation and NULL behavior. CHECK service flags, positive enabled price, NULL disabled price, positive-or-null capacity, valid month range and date intervals where applicable. MySQL CHECK accepts UNKNOWN; explicitly test required non-NULL branches, not only comparisons. Nullable uniqueness alone cannot enforce one live booking or non-overlapping memberships. Interval overlap/capacity/temporal validity requires locked domain checks as well as safe database constraints. For exceptional classes ensure distinct identity and prevent prohibited overlap. Exact DDL, FK names, safe migrations, representation of schedule/trainer versions and class adjustments, indexes and query shapes require plan approval.

### 9.3 Concurrency / atomicity

Use consistent, documented lock order per affected command and actual database evidence. Narrow scope (activity, affected group(s), child, trainer(s), subscription/class/booking and financial records as appropriate); stable order among multiple IDs; no global Activities mutex or speculative cross-module lock protocol. Design lock ownership/order together with current Identity authorization/session protections and POS/Finance. Re-read current eligibility, price, active flags, membership, date capacity, actual overlap, commitments, permissions and financial outstanding amount after acquiring necessary locks. Correctly serialize new dependent vs deactivate/delete, trainer assignment/schedule conflicts, last seat (regular/makeup), overlapping enrollments/bookings, duplicate class occurrence, renewals and simultaneous payments/refunds. Transfer reserves destination/releases old atomically. Use one outer transaction for each coherent command including affected financial postings/attendance/entitlement updates. Failure rolls back all relevant changes; limited safe retries only after known rollback and idempotency outcome checks, no blind retry on ambiguous commit. Map only known integrity violations in context. Verify actual InnoDB concurrent connections, not SQLite or serial mocks.

Index candidates must support actual equality/range filters, joins, child/activity membership dates, group/schedule/capacity, trainer overlap, occurrence lookups, attendance and entitlement/booking relationships. Measure EXPLAIN and EXPLAIN ANALYZE on guarded MySQL8 fixtures, query count/no N+1, memory and lock duration; do not add every speculative index. Use paginated bounded reads, selected columns and appropriate eager loading. Optimize bulk generation only after measurements; use missing-only bounded inserts/reconciliation. No unbounded synchronization request or hidden background work.

### 9.4 API errors and localization

Use existing shared `App\\Exceptions\\BusinessException` in Actions for expected business state conflicts, not a new exception framework. E.g.:
```php
throw new BusinessException(
    message: ApiResponse::text('activities.errors.group_capacity_reached'),
    httpStatus: 409,
    errorCode: 'GROUP_CAPACITY_REACHED',
);
```
Form Requests / ValidationException for invalid input 422 (shape, invalid enum/price/months, etc.); authentication 401; permission 403; missing record 404; business conflict 409; CSRF 419; unexpected faults 500 or established temporary unavailability 503, per Error Standards. Authorization before protected target disclosure. No SQL/stack/private data in errors. Translation keys for Arabic messages, English technical codes. No duplicate response wrapper; use Core ApiResponse. Normal successful reads 200 message ""; new record 201; commands/updates/delete 200 with minimal documented data or [].

Reviewed candidate business error codes to finalize in owning feature specs: ACTIVITY_IN_USE, ACTIVITY_INACTIVE, GROUP_IN_USE, GROUP_CAPACITY_REACHED, GROUP_PERIOD_EXCEEDED, TRAINER_SCHEDULE_CONFLICT, SUBSCRIPTION_OVERLAP, SUBSCRIPTION_ALREADY_RENEWED, CLASS_ALREADY_COMPLETED, ATTENDANCE_ALREADY_RECORDED, MAKEUP_ALREADY_USED, MAKEUP_EXPIRED, ACTIVITY_PRICE_CHANGED, PAYMENT_AMOUNT_EXCEEDED, IDEMPOTENCY_CONFLICT. Reuse existing shared SELECT_OPTIONS_LIMIT and owning POS/Finance codes. Error code naming is not permission to add a new exception class. Invalid field date ordering/amount/month range is validation, while valid inputs conflicting with persisted state are business conflicts. Recognized concurrent uniqueness/FK violations map safely; unexpected database failures remain faults.

## 10. API conventions, permissions and Select Registry (D05, D66–D78)

All APIs /api/v1, plural kebab-case, camelCase public fields, snake_case storage, explicit Form Requests/Actions/Resources, native Sanctum stateful browser cookie/CSRF, existing first-party transport and current staff authority. Use Spatie Query Builder explicit allowlists; no arbitrary includes/sorts/filters, no unbounded queries. Pagination default 15, valid larger clamped to 100 before query. Follow standard envelopes and Arabic translations. No role-name authorization or super-admin bypass; every custom role uses actually assigned permissions. No access escalation through nested relation reads or selectors.

### 10.1 Approved catalogue of 31 Activities capabilities

Each row is independent; every key has a nonempty Arabic alias from module-owned definitions. Manager default all 31; reception 19 read/routine operational capabilities. Initial mapping is not a role-name bypass and not a requirement that a Manager keep all grants. Scope aliases finalized in approved feature specs.

| Permission | Manager | Reception |
|---|:---:|:---:|
| activities.index | Yes | Yes |
| activities.show | Yes | Yes |
| activities.store | Yes | No |
| activities.update | Yes | No |
| activities.delete | Yes | No |
| activities.groups.index | Yes | Yes |
| activities.groups.show | Yes | Yes |
| activities.groups.store | Yes | No |
| activities.groups.update | Yes | No |
| activities.groups.delete | Yes | No |
| activities.subscriptions.index | Yes | Yes |
| activities.subscriptions.show | Yes | Yes |
| activities.subscriptions.store | Yes | Yes |
| activities.subscriptions.renew | Yes | Yes |
| activities.subscriptions.transfer | Yes | Yes |
| activities.subscriptions.cancel | Yes | No |
| activities.subscriptions.discount | Yes | No |
| activities.classes.index | Yes | Yes |
| activities.classes.show | Yes | Yes |
| activities.classes.store | Yes | Yes |
| activities.classes.complete | Yes | Yes |
| activities.classes.cancel | Yes | No |
| activities.classes.substitute | Yes | No |
| activities.classes.reschedule | Yes | No |
| activities.attendance.index | Yes | Yes |
| activities.attendance.store | Yes | Yes |
| activities.attendance.correct | Yes | No |
| activities.makeups.index | Yes | Yes |
| activities.makeups.show | Yes | Yes |
| activities.makeups.book | Yes | Yes |
| activities.makeups.cancel | Yes | Yes |

Class store's routine scope does not authorize manager-only exceptional creation; see §4 unresolved granular capability. Money-related access always additionally requires the owning module's permissions as applicable, including sensitive refunds/adjustments and cashier shift. Do not seed future permissions all at once.

### 10.2 Extend Identity at first catalogue feature

Currently exact registered permissions are Identity-only (ten); `PermissionCatalogue::constrain` uses `IdentityPermissions::keys()` and exact binary membership. Extend central aggregate to module-owned definitions so all reviewed new keys are consistently recognized by Role validation, role/detail/select queries, authenticated authority, cache invalidation and seeding. Review all existing exact-count assumptions and tests. Preserve existing Identity definitions and labels, one active role/no direct User grants, founder protections and **unchanged six essential last-manager capabilities**. No superadmin bypass.

D05 specifically approves initial assignment of the five catalogue capabilities (manager all, reception index/show), including existing installations via a **reviewed, one-time explicit provisioning step** for the new feature; future features likewise follow their approved default mapping only when delivered. This is not implicit auto-grant behavior in the routine seeder. Align with Permissions Standards: fresh install explicit defaults; existing roles require authorized reviewed grant change; never sync/replace customized roles, reset existing aliases or reinstate a removed grant on later reruns. Define transactional, race-safe, permanently idempotent first-provision marker/migration strategy and authorization/cache outcome, including fresh install and missing prerequisites, in the catalogue plan. No automatic grant of future unreviewed keys. Audit persistence deferred; preserve controlled deployment evidence and reassess real rollout. Do not alter last-manager semantics.

### 10.3 Shared selects

Only existing GET /api/v1/selects?selects=...; no module-specific dropdown endpoint or arbitrary extra query params.

| Key | Owner | Scope |
|---|---|---|
| activities | Activities | All retained activities including inactive |
| activeActivities | Activities | Active activities; not filtered by billing mode at registry level |
| groups | Activities | All retained groups |
| activeGroups | Activities | Active admission-eligible groups of active subscription-enabled activities, respecting operating eligibility as specified; not a seat guarantee |
| trainers | Employees | Eligible trainer options, as defined by owning feature |

Example response {"success":true,"message":"","data":[{"label":"activities","options":[{"value":1,"label":"السباحة"}]}]}. Register catalogue keys in first feature, other keys only when real owner exists. Exact provider permission mapping per purpose in spec; catalogue read providers should align with catalogue list authority (D04/D05 scope); any assignment-oriented lookup must have documented authorized purpose, does not confer general detail permission. Current registry max 3 distinct keys/request, 200 options per provider; query bounded limit+1, no silent truncation; overflow 409 SELECT_OPTIONS_LIMIT, unknown/invalid 422, unauthorized requested key entire request 403 before provider reads, no partial results, unauthenticated 401. Do not loosen current strict query handling. For context-specific groups use paginated group index filter activityId/isActive; no new select filters now. Child/guardian lookup via paginated People search, not unbounded select. Larger options/other filters only through reviewed later changes.

## 11. Acceptance and verification strategy (D91–D98)

All below are **requirements, not executed tests**. Each feature spec must derive numbered acceptance cases and plan actual implementation evidence. Exercise unit, feature/native authenticated HTTP, integration, real DB constraints, concurrent transactions, failure/rollback, security, performance and regressions as appropriate; existing project suites, migrations, Laravel Pint, Composer/audit, module/route scope and CI required. Use disposable guarded MySQL8; no real data/destructive production tests. Include authorization independence, invalid input/state errors, Arabic messages, payload bounds, unauthorized/mixed selectors, inactive role/revocation and no private fields for each relevant endpoint.

| Area | Minimum acceptance coverage |
|---|---|
| Catalogue (D92) | hourly-only, subscription-only and both; neither rejected; zero/negative/missing enabled and non-null disabled prices rejected; valid two-decimal bounds; duplicate name incl inactive and concurrent duplicate (one wins); safe conditional delete and disabled obligations; snapshot price unaffected; 0/1 billing filter incl both; all/active selects, authorization/overflow; additive catalogue, approved one-time role grants, reseed never restores revoked grants, Identity regression |
| Groups (D93) | no trainer/schedule, duplicate trainers, multi-trainer, trainer/group overlap vs adjacency and distinct slots; inactive obligations; optional capacity and invalid zero; range/date checks, start/end modifications preserving confirmed commitments; lower capacity rejected below peak; historical version and assignment retained; protected deletion |
| Rolling generation (D94) | automatic first 60-day horizon, idempotent repeat/concurrent calls, missing-only and catch-up after inactivity, 12-month confirmed coverage, inclusive group end, no regeneration of cancelled/original rescheduled classes, exceptional preserved, GET read-only, no background dependency |
| Subscriptions (D95) | 1/12 valid, 13 invalid, invalid partial months/group dates/backdate, future, 29–31 and leap boundaries; same-activity overlap and concurrent renewal, different-activity permitted with warning, distinct old/new debts, renewal dates, transfer preserves agreement/history and rollback if full, cancellation retains independent renewal, stale price conflict and derived temporal status |
| Attendance/make-up/trainer (D96) | only eligible roster, one record/child, unmarked not absent, complete all roster plus actual delivery evidence, batch rollback, overlap prevention, cancellation and compensation choice, unique entitlement, last seat concurrency, consumption once/no-show forfeiture, grace expiry, renewal independence, booking cancellation/return, finite group end and alternative group, each delivered trainer only, no pay on cancellation, authorized correction protects consumed rights and payroll |
| Financial (D97) | one sale for multi-month contract, partial/full/zero initial, exact amounts, distinct old debt, discount permission, no overpayment, adjusted due/refundable amounts and full/partial refund, concurrent installments/refunds, old debt collection, shift authorization, retry/idempotency conflict, actual cross-module failure rollback, no duplicate ledger/source |

Scale fixtures (D98, illustrative synthetic targets, not production estimates): 200 activities, 500 groups, 10,000 children, 50,000 subscriptions, 100,000 classes, 1,000,000 attendance records. Test progressively where the relevant owners exist and environment allows. Include skewed occupancy/active vs history, high-frequency weekly schedules, future and historical windows. Record actual EXPLAIN/EXPLAIN ANALYZE, query count and growth (N+1), response average/P95 with sample sizes, PHP peak/worker RSS, bulk rows/time, lock duration/waits, concurrency correctness and rollback evidence. No unmeasured host SLA or arbitrary index/Redis/queue change. All Activities tests currently Not Run.

## 12. Delivery sequence and feature gates

Dependency order, not entire-module-at-once completion. First feature approved for design is **Activity Catalogue**. Each separately approved feature spec, technical plan and tasks must be reviewed before its code; one feature branch from develop/PR to develop, explicit merge approval. No production migration/deployment authorization.

| Sequence | Feature / prerequisite | Scope |
|---|---|---|
| 1 | Activities — Activity Catalogue | Only activities table, five CRUD routes/permissions, two selectors, necessary central catalogue integration and tests |
| 2 | People Core | Actual child/guardian/customer records |
| 3 | Employees Core | Actual employee/trainer records and eligibility contract |
| 4 | Activities — Groups, Schedules & Class Generation | Group operating dates, trainer versions/slots, automatic/exceptional occurrence management; no fake child/payment/attendance integration |
| 5 | POS & Finance foundations | Actual invoice/receipt/refund/shift and movement contracts |
| 6 | Activities — Subscriptions Management | Enrollment, multi-month price confirmation, dates, transfer/renew/cancel, real financial integration |
| 7 | Workforce — Trainer Attendance | Actual evidence of trainer delivery/substitution; coordinate with class lifecycle |
| 8 | Activities — Classes & Child Attendance | Roster, actual execution/completion/correction with Workforce and subscriptions |
| 9 | Activities — Make-up Classes | Actual entitlement, target availability, booking and consumption |

Catalogue can precede People/Employees because it has no dependent writes. Sessions/Timers and Café may be delivered according to overall roadmap after their own prerequisites. Other modules (Workforce, Payroll, Reports) remain in scope. No shared Audit implementation in this sequence until separately approved. Split substantial groups/generation/occurrence work further into reviewed features if needed; no speculative placeholder dependency or circular build requirement. Actual first feature artifacts:
- `specs/activities/activity-catalogue/spec.md`
- `specs/activities/activity-catalogue/plan.md`
- `specs/activities/activity-catalogue/tasks.md`

These files do **not** exist as a result of this reference. Next action: review/approve the documentation PR, then draft and discuss first feature spec before plan/tasks/implementation.

## 13. Explicit feature-level technical details still to review

Do not treat these as new approved decisions: exact MySQL collation/unique/check/index DDL, cross-module FK/deletion coverage, versioned schedule/trainer and class identity representation, bounded forward start/generation/sync/catch-up/range and batch limits, sync route/permission, privileged exceptional-create permission separation from ordinary classes.store, precise pristine generated future occurrence deletion policy, exact roster/correction/transfer/cancellation effective cutoffs, date/slot conflict and DST handling, current price confirmation field and conflict refresh, warning payload, minimal resource and filter/include/sort lists, per-provider authorization and trainer eligibility, one-time provisioning/cache/locking strategy, POS/Finance invoice/settlement/payment/refund/source model and permissions, Workforce actual delivery evidence and Payroll correction contract, idempotency scope/retention, and measured performance budgets. These require owning feature spec/plan review before implementation; obtain explicit new business/permission approval if necessary. No silent assumption that a candidate name or table is final.

## 14. Decision traceability and documentation precedence

| Decisions | Reference |
|---|---|
| Initial catalogue agreements; D01–D05 | §§1–2, 10 |
| D06–D13 | §3 (updated by D79, D82, D85, D88, D100) |
| D14–D24 | §5 (duration superseded by D83–D86, D90, D101–D102) |
| D25–D33 | §§4, 6 (generation superseded by D80/D87, multiple trainers by D79/D88) |
| D34–D42 | §7 (D99–D102 clarification) |
| D43–D52 | §§5, 8 |
| D53–D65 | §9, §8, with D88 relationship additions |
| D66–D78 | §§2–10; updated request fields and later class generation/permission decisions |
| D79–D81 | §§3–4, 6 |
| D82–D85 | §§3, 5 |
| D86–D90 | §§3–5, 8–9 |
| D91–D98 | §11 |
| D99–D102 | §§3, 5–7 |

This reference records the latest approved module rules; project overview and architecture summaries align with it, while shared standards remain in force. Approved feature specs/plans refine, not contradict, these decisions. If an unresolved conflict emerges, stop at the review gate; do not write production code assuming an unapproved resolution. All examples are illustrative synthetic data. No verification or implementation is claimed by documentation.
