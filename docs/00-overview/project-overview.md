# Kids Area — Operations Management System
## Business Requirements & Technical Overview

> **Status:** V1 business scope agreed; Identity F01–F04 merged, Activities module decisions D01–D102 approved; feature specifications and implementation delivered incrementally.  
> **Scope:** One physical Kids Area location, one backend application, API-first development.  
> **Canonical location:** `docs/00-overview/project-overview.md`.

## Overview

**Kids Area** is an internal operations management system for a children's entertainment and education venue. The business has three operational areas: **café sales**, **games and activities** (subscriptions priced per calendar month and/or pay-per-hour usage), and **nursery** (configured as a subscription-only activity, not as a separate business system).

The system supports reception staff handling customer visits, activity sessions, attendance, café sales, invoices, collections, and cashier shifts. Managers oversee the entire operation, including staff, contracts, payroll, expenses, permissions, and reports. The primary objective is **fast daily operation without unnecessary administrative complexity**, while keeping money and attendance records reliable.

This is a **single-location, Laravel 12 modular monolith**, with a RESTful JSON backend built first. A separate frontend will be designed and developed later. **V1 includes all agreed core modules, implemented incrementally rather than all at once.** Arabic is the operating language for V1, with a localization-ready foundation for future languages.

An **activity** can offer subscriptions priced per calendar month, hourly use, or both independently. Subscriptions for 1–12 whole calendar months belong to registered children, with groups, scheduled classes, attendance, and payments. Hourly activity use belongs to a **customer session**, which can contain several independently timed activities plus café orders. Session settlement is for the **whole session**, not per child.

---

## Goals

1. **Simple Reception Operations:** Let reception staff start sessions, manage activity timers, register children and attendance, sell café products, and receive payments quickly.
2. **Unified Activities:** Manage games, lessons, swimming, and nursery within one activity model, supporting the relevant usage/pricing modes without duplicated systems.
3. **Clear Child & Subscription History:** Keep children, guardians, monthly plans, group attendance, make-up classes, renewals, payments, and unpaid balances traceable.
4. **Fair Time-Based Billing:** Measure hourly usage independently for each activity, exclude paused periods, and settle a session accurately with optional discounts.
5. **Practical Staff Management:** Manage jobs, employees, contracts, staff attendance, shifts, leave, permissions, class delivery, substitutions, and payroll.
6. **Controlled Cash Operations:** Distinguish sales from money collected, reconcile each cashier shift, and maintain traceable expenses, cancellations, and refunds.
7. **Useful Management Visibility:** Provide role-appropriate dashboards, operational/financial reports, and Excel/PDF exports.
8. **Maintainable API-First Backend:** Use coherent Laravel modules, centralized authorization and lookups, Arabic messages, and automated tests for important business rules.

---

## Core User Flows

### Walk-in, Pay-Per-Hour Session

```text
[Reception opens a session under a customer's name]
             |
             v
[Add one or more hourly activities; start independent timers]
             |
             +--> [Start / Pause / Resume / Finish each timer independently]
             |
             +--> [Add café orders to the same session]
             |
             v
[Review measured activity charges + café orders]
             |
             v
[Apply an optional discount]
             |
             v
[Collect the entire amount by cash, card, or e-wallet]
             |
             v
[Close the session and record its invoice/payment]
```

- A session does **not** require registered child records and is **not priced by child count**.
- Multiple activity timers may run simultaneously and finish at different times.
- Café purchases can also be invoiced and paid **independently**, without an open session.

### Child Enrollment and Subscription (Monthly Unit Pricing)

```text
[Reception registers or selects a child and guardian]
             |
             v
[Select an activity and an available scheduled group]
             |
             v
[Select start date and number of months (1–12); confirm total and agreement]
             |
             v
[Collect full, partial or no initial payment; keep any outstanding balance]
             |
             v
[Record attendance; arrange eligible make-up classes when needed]
             |
             v
[Subscription expires at the end of its period]
             |
             v
[Reception or manager manually renews it when requested]
```

- A guardian's **name and mobile number** are mandatory when registering a child.
- One guardian may be associated with several children; a child can hold separate active subscriptions to **different** activities.
- A child can be registered without buying a subscription immediately.
- **Nursery follows this subscription flow** as an ordinary activity configured subscription-only, without an hourly timer; no special type or hardcoded ID.

### Employee Attendance, Classes, and Payroll

```text
[Manager creates jobs, employees, and pay contracts]
             |
             +--> [Operational staff follow fixed/variable shifts]
             |
             +--> [Trainers/teachers follow scheduled group classes]
                           |
                           v
               [Reception records trainer attendance]
                           |
                           v
               [System derives eligible delivered classes]
                           |
                           v
[Payroll calculates fixed pay and/or delivered-class compensation]
             |
             v
[System suggests attendance deductions; manager decides actual deductions]
             |
             v
[Manager reviews and records payroll payments]
```

### Cashier Shift

```text
[Cashier opens a financial shift with opening cash]
             |
             v
[Cash collections, card/e-wallet collections, and cash expenses recorded]
             |
             v
[Cashier enters actual cash at closing]
             |
             v
[System compares actual cash to expected cash]
             |
             v
[Cash difference is recorded; manager reviews shift]
```

---

## Core Business Rules & Boundaries

### Children, Guardians, and Subscriptions

- **Child enrollment is mandatory for monthly subscriptions**, including nursery; a simple hourly session can be opened using the customer's name without registering a child.
- **Every registered child must have a registered guardian** with a name and mobile number. One guardian may have multiple children.
- Children may be registered in advance, before buying any subscription.
- A child may have **multiple concurrent subscriptions to different activities**. Each subscription owns its group, period, attendance, payments, and remaining balance.
- A subscription runs for **1–12 whole calendar months** from its selected start date, computed without month overflow, start inclusive/end exclusive. Monthly unit price is saved at agreement; total is that price times the selected months, less any authorized fixed discount. No part-month subscription or prorating. The period must fit the group's operating dates; see [Activities](../03-modules/activities.md).
- Subscription renewal is **manual**. Expiry does not create another subscription, automatic renewal, or new debt.
- **Full, partial or zero initial payment is allowed for subscriptions**, including nursery. One sale per agreed contract; later installments reduce debt, not new sales. Any adjusted unpaid balance remains collectible even after expiry, deactivation or cancellation.
- **Subscription freezing is not available in V1.**
- A missed scheduled class may be made up once, subject to available group capacity. **Make-up classes remain available for seven days after subscription expiry**, without extending the subscription itself. Exact eligibility and booking controls belong in the Activities specification.

### Activities, Groups, and Sessions

- An activity may be configured for **subscription use, hourly use, or both**; independent modes with prices on the activity. Nursery is configured subscription-only, without timers. Hourly play is a separately paid service, even for an enrolled child.
- A subscription-based activity can have **multiple groups**, each with one or more trainers/teachers, weekly slots, a required operating start date and optional inclusive end date. A child subscribes to a selected group. Classes are generated automatically with a bounded 60-day rolling horizon, extended to cover confirmed subscriptions; managers may add exceptional classes or make individual schedule changes. No scheduler reliance.
- Group capacity is **optional**. If set, overlapping regular commitments (including future subscriptions) plus confirmed make-up reservations must fit the limit. Disablement prevents new enrollments/renewals but does not cancel prior obligations. Transfer, cancellation and capacity checks preserve history and commitments atomically.
- A customer session can contain **multiple independently running hourly activity timers** and café orders, with a single final settlement.
- Each hourly timer supports **Start, Pause, Resume, and Finish**. Paused time is excluded from chargeable duration.
- Hourly billing is **proportional to actual chargeable time** at the activity's hourly rate: e.g. 70 minutes at EGP 60/hour costs EGP 70. Precision/rounding details will be set in the Sessions specification.
- The cashier may apply an **optional discount** to the overall session before settlement.
- A session must be **paid in full on closure** using cash, bank card, or e-wallet; no partial settlement or unpaid session balance in V1.
- Source-of-truth times and financial calculations belong to the backend, not a browser timer.

### Café and Sales

- Café products have categories, prices, and availability/active status.
- Café sales can be **standalone** or **added to an open session** for settlement together with activity use.
- **Inventory, stock counting, purchasing, ingredient consumption, and recipes are not in V1.**

### Employees, Attendance, and Payroll

- **Employee and system user are different concepts**. An employee may or may not have a login account.
- Employment contracts support **monthly fixed pay, per-class pay, or monthly fixed pay plus per-class pay**.
- Each contract requires a **start date**; an end date is optional. Historical contracts must be preserved, without overlapping active contract periods.
- **Contract pay type does not determine attendance mode.** Reception, cleaning, and other operational staff follow shifts. Trainers and teachers are tracked against the scheduled classes of their activity groups, not against shift attendance solely because they receive fixed pay.
- Operational shifts may be fixed or variable; the manager can modify a particular day's schedule.
- **Per-class compensation counts only classes actually delivered**, derived from the trainer's recorded attendance matched to the scheduled class; cancelled/not-delivered classes are excluded. Exceptions must remain reviewable.
- The manager can assign or substitute one or more trainers for a particular class. Several trainers may genuinely co-deliver a class; each actual participant is eligible for the applicable per-class pay once, according to contract and recorded delivery evidence. Assignment or presence alone is insufficient; a replaced non-delivering trainer receives no class credit.
- The system may **suggest salary deductions** based on shifts, absence, lateness, leave, and permissions, but the manager decides whether to apply, change, or waive them. Avoid double deductions for undelivered classes already excluded from class pay.
- **The manager directly records leave and hourly/partial-day permissions** for employees; no employee self-service leave approval flow in V1.

### Finance, Cashier, and Permissions

- V1 has **operational finance**, not double-entry general accounting. Track sales, actual collections, subscription receivables, expenses, payroll payments, and payment-method balances.
- **Sales and collections are different measures**. A later installment payment against an existing subscription must not be counted as a new sale.
- **Cashier financial shifts are separate from employee attendance shifts**. A cashier shift has opening cash, recorded movements, actual closing cash, and any shortage/surplus.
- Reception may record **cash expenses from an open cashier shift**; the manager reviews them and records other business expenses. Recorded cash expenses affect expected cash immediately.
- Invoice cancellation and **full or partial refunds** are allowed only through the relevant **permissions**. A reason is mandatory, the original financial history must remain traceable, and refunds must not exceed amounts eligible for refund.
- The backend enforces permissions. The manager has full access; reception handles attendance, day-to-day sales, invoices, collections, and cashier duties, subject to configured permissions for sensitive actions.
- Financial movements must be **idempotent where retries could duplicate money**, and sensitive changes retain actor, time, and reason as appropriate.

### Operational Boundaries

- **One physical Kids Area and one operating location** in V1; no multi-branch or multi-tenant functionality.
- V1 is **backend/API first**; the frontend is a separate later phase.
- Dashboards are **role-appropriate**: management sees financial and operational performance; reception sees daily operational tasks.
- Reports are available in the system, with **Excel and PDF export** where relevant.
- Arabic (`ar`) is the **V1 operating language**. Keep technical keys language-neutral and prepare localization infrastructure for later expansion; no language-switching UI is needed now.

---

## Features

### Identity — Authentication, Users & Permissions

- Login and user account management; employees need not have accounts.
- Manager and reception are the initial business roles. Roles, permissions, and sensitive operations must be enforced on the backend.
- A permission catalogue uses stable technical keys and Arabic display labels.

### People — Customers, Guardians & Children

- Independent Customers (name required, mobile optional, integer-backed active status default ACTIVE = 1) are a supporting entity with no standalone customer screen or index API. Staff can view, explicitly create and update from authorized host workflows; select active customers using the shared Select Registry. Customer deletion and status changes are deferred. Hourly sessions may use a written customer name without a permanent customer record or choose a registered customer; no child required. Customer and Guardian records have no relationship or cross-table uniqueness requirement.
- Children are the UI entry point for Guardians: select an existing eligible Guardian by name and mobile, or create one with the child atomically. No standalone guardian-management screen; Guardian name/mobile required, address optional, active flag. One guardian to many children, each child has exactly one guardian.
- Child profiles can exist without subscriptions: name and gender required, optional birth date, relationship, photo, one notes field, active flag. Names unique within the same guardian; mobile unique independently within each entity when present. Preserve historical operational/financial rights across status and relationship changes. See [People](../03-modules/people.md) for reviewed details and pending feature-level contracts.

### Employees — Jobs, Employees & Contracts

- Jobs and employee profiles, optionally linked to user accounts.
- Historical pay contracts with the three supported payment models and optional end dates.

### Workforce — Attendance, Shifts, Leave & Permissions

- Reception-recorded attendance for operational staff and trainers/teachers.
- Fixed and variable operational shifts, with day-specific changes.
- Manager-entered leaves and time permissions; attendance exceptions visible for payroll review.

### Payroll — Salaries & Trainer Compensation

- Monthly calculation of contractual pay and eligible delivered-class compensation.
- Attendance-based **suggested** deductions, manager-approved actual deductions, and payroll payment tracking.
- Class substitutions and excluded classes reflected without duplicate compensation.

### Activities — Groups, Schedules, Subscriptions & Attendance

- Activity catalogue with monthly and/or hourly availability.
- Scheduled groups with multiple trainer assignments, operating start/optional end, effective weekly schedules and optional capacity; automatic bounded class generation plus exceptional classes, rescheduling and cancellation.
- Child enrollment, subscriptions for 1–12 calendar months, price snapshots, full/partial/zero initial payment, installment collections, manual renewal, same-activity transfers, controlled cancellation, class attendance and make-up classes.
- Nursery represented by an ordinary activity configured for subscription use only; see [Activities Module Reference](../03-modules/activities.md).

### Sessions — Hourly Usage & Timers

- Open customer sessions, add independently timed activity uses, pause/resume, and finish each timer.
- Proportional hourly pricing, session summaries, optional discount, and full-payment settlement on close.

### Café — Products & Orders

- Product categories, products, prices, and active/inactive management.
- Standalone café orders and café orders attached to sessions; no stock module.

### POS & Cashier — Invoices, Payments & Cashier Shifts

- Invoicing and collections through cash/card/e-wallet.
- Cashier shift opening balances, cash movements, closing counts, differences, and review.
- Permission-controlled invoice cancellations and refunds, with audit history.

### Finance — Expenses, Collections & Balances

- Operational income/collection reporting, subscription outstanding balances, and business expenses.
- Correct treatment of cashier cash expenses and refunds, without double-counting revenue.

### Reports — Dashboards & Exports

- Separate management and reception dashboard experiences.
- Reports for sales, sessions, activities, subscriptions, outstanding payments, expenses, staff attendance, payroll, and cashier shifts.
- On-screen filters plus Excel/PDF export, according to authorization.

---

## Scope

### In Scope — V1 (Incremental Backend Delivery)

- All eleven coherent business modules listed above, implemented iteratively using approved feature specifications.
- RESTful APIs, authentication, permissions, input validation, shared response/error conventions, and central select/lookups infrastructure.
- Arabic system messages and labels, with a foundation for later localization.
- Accurate session time and pricing, subscription billing/collections, payroll calculations, cashier reconciliation, and financial corrections.
- Automated tests for business rules, security, and financial correctness.

### Out of Scope — V1

- Frontend implementation during the current backend phase.
- Multiple branches, tenants, or independent businesses.
- Café stock management, purchasing, ingredients, and recipes.
- Full double-entry accounting, general ledger, journal entries, and financial statements.
- Automatic subscription renewal and subscription freezing.
- Employee self-service leave requests and approval workflows.
- Payment gateway or card-terminal integrations beyond recording the payment method (unless separately approved).

---

## Tech Stack & Architectural Approach

- **Backend:** Laravel **12**, RESTful JSON API under `/api/v1`.
- **Architecture:** A single Laravel **modular monolith**, organized by business responsibility with `nwidart/laravel-modules`. Modules share one database and deployment.
- **Database:** MySQL **8** / InnoDB, as approved in the Backend Architecture and Database Standards.
- **Frontend:** Separate application **later**; technology and UI details are outside the current backend phase.
- **Authentication:** Laravel Sanctum **stateful Cookie/Session** is the approved staff-browser approach; actual frontend origin, cookie/CSRF/CORS values and the implementation plan are finalized when the frontend is deployed.
- **Authorization:** Spatie Laravel Permission integrated with Laravel's backend policies/gates.
- **Shared selects:** One central Select Registry and endpoint (`GET /api/v1/selects?selects=...`) following the proven pattern in the `printingApi` reference repository. Business modules register their own allowed select providers; providers enforce permissions and return `value`/`label` options.
- **Localization:** Arabic (`ar`) as both default and fallback in V1; translation keys for system-owned text, stable English technical identifiers, and a future-ready translation structure.
- **Specifications & delivery:** GitHub-first, manual module-first workflow. Approved module references live in `docs/03-modules/<module>.md`; directly authored feature `spec.md`, `plan.md` and `tasks.md` live in `specs/<module>/<feature>/`. No Spec Kit CLI or separate project constitution.
- **Implementation:** Prefer Laravel Form Requests, Resources, Policies, Eloquent, focused Actions/Services, database transactions, appropriate locking, exact money types, and automated feature tests. Avoid abstraction without a clear business purpose.

**Deliberately avoided:** Microservices, per-module databases, a separate nursery subsystem, workflow engines, unnecessary repositories/interfaces, and a complete accounting suite. Exact module boundaries, API contracts, schemas, pricing precision, attendance matching, and exception handling belong in architecture and module specs.

---

## Success Criteria

1. **Walk-in Settlement:** Reception can open a named customer session, run overlapping independent timers with pauses, add café orders, apply an optional discount, and close only after full payment.
2. **Correct Hourly Charges:** Server-derived chargeable time excludes pauses; each activity uses its own saved hourly rate and produces a correct final amount.
3. **Flexible Café Sales:** Standalone orders and session-linked café orders both work, and the same sale is not posted twice.
4. **Child & Guardian Records:** A registered child has a guardian with mandatory name and mobile number; one guardian can have several children and each child can keep multiple independent activity subscriptions.
5. **Subscription Integrity:** Enrollment respects group dates and interval-based capacity, tracks an agreed 1–12 calendar-month period and installments, expires without auto-renewal, and preserves historical price, attendance and payments. Automatic class generation preserves exceptions and confirmed commitments.
6. **Controlled Make-up Classes:** An absence can be made up once within the subscription period or its seven-day post-expiry grace window, without extending the subscription or exceeding capacity.
7. **Accurate Trainer Pay:** Every trainer who actually delivers or co-delivers a class may receive one eligible credit according to contract; non-delivering, substituted-out or cancelled classes do not produce incorrect compensation.
8. **Manager-Controlled Payroll:** Suggested attendance deductions are visible with supporting facts; the manager may accept, change, or waive them before finalizing payroll.
9. **Cashier Accountability:** Each cashier shift reconciles opening cash, actual cash collections, recorded cash expenses, and counted closing cash, while card and wallet amounts remain separate.
10. **Correct Financial Reporting:** Sales, receipts, outstanding subscription balances, expenses, refunds, and payroll payouts reconcile without double posting or disappearing historical records.
11. **Enforced Authorization & Arabic Responses:** Protected APIs reject unauthorized requests; labels and user-facing system messages are available in Arabic.
12. **API Readiness:** Key flows pass automated tests, use predictable JSON responses, and produce accurate on-screen reports and Excel/PDF exports as authorized.

---

> **Document purpose:** This is the agreed project-level business overview and V1 scope boundary, not a database design or implementation plan. Keep it focused; detailed tables, endpoints, edge cases, concurrency handling, and task lists belong in the backend architecture, relevant standards, module references, and approved feature documents. Update it only when a project-wide business decision changes.
