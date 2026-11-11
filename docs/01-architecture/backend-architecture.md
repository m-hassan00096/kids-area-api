# Kids Area — Backend Architecture

> **Status:** Approved architectural decisions; implementation not started  
> **Scope:** V1, single-location, backend/API-first  
> **Canonical repository path:** `docs/01-architecture/backend-architecture.md`  
> **Business source of truth:** [`../00-overview/project-overview.md`](../00-overview/project-overview.md)

## 1. Architecture Overview

Kids Area is a **Laravel 12, API-first, hybrid modular monolith** supporting one physical business location. The V1 backend is one deployable Laravel application with one **MySQL 8 / InnoDB** database, organized into business modules using the Laravel 12-compatible major of `nwidart/laravel-modules` (`^12.0`). There are no microservices, separate module databases, tenants, or branch-level isolation.

The architecture optimizes for **straightforward reception workflows**, correct time and financial calculations, safe changes to historical records, and maintainability without a dedicated operations team. The eleven business modules are delivered incrementally by feature; a later frontend will consume `/api/v1`.

### Architectural principles

1. **Own business data and behavior:** Each module defines and updates its owned business records and enforces its own invariants.
2. **Use pragmatically explicit boundaries:** Services and contracts at important cross-module boundaries; do not require interfaces or events for trivial interactions.
3. **Keep critical operations synchronous:** Closing a session, billing, receiving money, refunding, and timer state transitions must commit or fail as a coherent request.
4. **Use one authoritative source per fact:** No independent copies of collections or balances. Financial movements, invoices, attendance facts, and timer segments have explicit ownership.
5. **Avoid runtime dependencies unavailable in V1:** No queue driver, queued jobs, Redis, Horizon, cron/scheduler dependency, WebSocket server, or always-running application worker.
6. **Enforce authorization and protect history:** Security lives in the API, not only in future client UI. Historical financial records remain reconstructable.
7. **Keep business specifics in feature specifications:** The architecture defines boundaries and invariants; individual Spec Kit plans own precise tables, endpoints, edge cases, and algorithms.

## 2. Technology Stack & Runtime Constraints

| Concern | Decision |
|---|---|
| Language / framework | PHP with Laravel 12; supported PHP version on hosting must be checked before deployment (Laravel 12 minimum PHP 8.2) |
| Modular package | `nwidart/laravel-modules:^12.0` (match the Laravel 12 major, not latest major blindly) |
| Database | MySQL 8, InnoDB, UTF-8/utf8mb4 |
| Authentication | Laravel Sanctum |
| Authorization | Spatie Laravel Permission, Laravel Policies / Gates |
| API style | Versioned REST-style JSON, `/api/v1` |
| Currency | EGP, exact decimal arithmetic; never floating-point for money |
| Operating locale | Arabic (`ar`); default and fallback `ar`; future `en` readiness |
| Server time | UTC persisted for event timestamps; Egypt local-time presentation where relevant |
| Deployment | Shared Hosting / cPanel; web root restricted to Laravel `public` |
| CI | GitHub Actions on pull requests targeting `develop`, exercising MySQL 8 |
| Background processing | **Disallowed in V1:** queues, queued jobs, Redis, scheduled/cron tasks |
| Frontend | Separate later phase; authentication transport to be finalized when its deployment origin is known |

Composer dependencies are version-locked through `composer.lock`. New libraries must justify their purpose, PHP-extension requirements, shared-hosting compatibility, and maintenance cost.

## 3. Project Structure & Module Ownership

Representative structure (directories are created when needed; not every module requires every folder):

```text
kids-area-api/
├── app/
│   ├── Http/                 # cross-cutting HTTP middleware and exception responses
│   ├── Services/Select/      # central select registry and dispatcher
│   └── Support/              # narrowly scoped shared utilities
├── Modules/
│   ├── Identity/
│   ├── People/
│   ├── Employees/
│   ├── Workforce/
│   ├── Payroll/
│   ├── Activities/
│   ├── Sessions/
│   ├── Cafe/
│   ├── POS/
│   ├── Finance/
│   └── Reports/
├── config/
├── database/                 # framework/shared migrations if justified
├── docs/
│   ├── 00-overview/
│   ├── 01-architecture/
│   └── 02-standards/
├── lang/ar/
├── lang/en/                  # optional future-language scaffolding
├── routes/
├── tests/                     # cross-module integration tests
├── specs/                     # Spec Kit feature specifications
└── .specify/                  # Spec Kit templates/configuration/constitution
```

Typical module:

```text
Modules/Activities/
├── app/
│   ├── Actions/
│   ├── Http/Controllers/
│   ├── Http/Requests/
│   ├── Http/Resources/
│   ├── Models/
│   ├── Policies/
│   ├── Providers/
│   └── Services/
├── database/migrations/
├── database/seeders/
├── routes/api.php
└── tests/{Feature,Unit}/
```

All routes are registered under the application-wide `/api/v1` convention. The module-specific route file owns its endpoint definitions; controllers handle HTTP concerns, Form Requests validation, Resources serialization, and Actions/Services business operations. Avoid fat controllers and unneeded layers of abstraction.

### Eleven business modules

| Module | Owns | Does not own |
|---|---|---|
| **Identity** | Users, authentication, roles, permissions, optional user/employee association rules | Employee contracts or payroll |
| **People** | Customers, guardians, child profiles and guardian relationships | Subscriptions and group attendance |
| **Employees** | Jobs, employee records, employment contract history | Daily attendance or payroll execution |
| **Workforce** | Operational shifts, attendance, trainer attendance evidence, leaves, permissions | Contract compensation terms |
| **Payroll** | Payroll calculations, period runs, adjustments, approvals, payment intent/status | Raw staff/contract records or underlying collection entries |
| **Activities** | Activity modes, groups, schedules, subscription periods, group/class attendance, make-ups, substitutions | Cash receipt bookkeeping or hourly session timers |
| **Sessions** | Customer sessions, independent activity timers, time segments, session charges and state | Recording customer payments or café product catalogue |
| **Cafe** | Products, categories, prices, café orders and their source lines | Stock, ingredients, purchases, cash movements |
| **POS** | Invoices, payment recording, payment-method classification, refunds, cashier shifts and reconciliation | Subscription eligibility or activity pricing policy |
| **Finance** | Canonical financial movements, operating expenses, collection/balance views | Duplicate second copy of an existing POS payment |
| **Reports** | Permission-scoped dashboard queries and Excel/PDF exports | Posting new business or financial facts |

Business ownership takes priority over directory names. Where a particular entity could reasonably sit in either POS or Finance, its detailed specification must identify **one** write owner before implementation.

## 4. Module Communication & Dependency Rules

The approved model is **Hybrid Modular Architecture**:

- Modules may reference another module's stable IDs using real MySQL foreign keys for essential relationships. A foreign key proves referential integrity; it does **not** authorize bypassing that module's rules.
- Prefer a directly called application Service/Action for an operation with cross-module business behavior. Introduce a Contract/interface when it improves an actual dependency boundary, testability, or implementation independence.
- Read-only joins or projections may be used intentionally for reporting and simple reference data, with ownership boundaries documented. **Mutating another module's records directly is prohibited** unless explicitly designed as one jointly owned operation.
- Cross-module monetary and session-settlement workflows are synchronous, with a single orchestrator and one enclosing transaction. Do not use asynchronous listeners or eventual consistency for core money posting.
- Events, if used in future, are only for optional, non-critical in-process concerns; they must not make correctness depend on a worker or delayed processing.
- Modules call each other **in-process**, not through local HTTP endpoints. Avoid circular dependencies and require architecture review when one appears.

Example synchronous close path:

```text
Sessions.close request
    -> validate session and timer states
    -> lock session; finalize authoritative timer charge snapshots
    -> include finalized Cafe order line snapshots
    -> POS issue exactly one invoice and record full payment
    -> Finance post exactly one associated canonical financial movement
    -> update linked cashier financial shift / close session
    -> commit once (or roll back the complete operation)
```

A failed receipt, invoice, movement, or state transition means **the entire close fails**. Existing requests can be safely retried without duplicate posting. The exact orchestration classes and transaction boundaries belong in the Sessions/POS/Finance feature plans.

## 5. Database Architecture

- **One MySQL 8 InnoDB database.** Each module owns its migrations and data models; migrations are ordered so referenced tables exist before their foreign keys.
- Use Laravel conventional **unsigned BIGINT IDs** (`id`, `foreignId`) unless a documented specific requirement calls for a different key.
- Enforce essential foreign keys within and across modules (`subscriptions.child_id`, `subscriptions.group_id`, `staff_attendance.employee_id`, `payments.invoice_id`, etc.). Do not add speculative foreign keys or loose generic polymorphic links where a safer explicit relation is possible.
- Monetary amounts use **DECIMAL** with appropriate scale (e.g., `DECIMAL(15,2)` for settled EGP amounts); calculations use exact decimal arithmetic / integer minor units where appropriate. Rounding and minute-precision policy belong in the respective feature specifications.
- Use `NOT NULL`, unique constraints, meaningful check/application invariants, and indexes reflecting actual filters and join paths. Database constraints supplement—not replace—application validation.
- Historic financial, attendance, contract, and time evidence should not disappear due to cascading deletes. Prefer restrictive foreign keys, status transitions, and explicit reversible corrections over destructive deletion. Soft delete is not a substitute for a financial audit trail.
- Store server-authoritative instants as UTC; use explicit time zones when interpreting scheduled class times or calendar months in the Egyptian operating context.
- For financial idempotency, enforce uniqueness at the **database layer** as well as at the action level (source operation references, request idempotency keys, unique invoice-session constraints as specified).
- Use transaction isolation and row-level locking where two users could race to close a session, collect the same due amount, reserve the last group seat, or refund the same payment. Retried transactions must not emit duplicate effects.

**Shared database does not mean shared write ownership.** Cross-module foreign keys are intentional integrity constraints, not a reason to build a large shared model library.

## 6. API Architecture

### Conventions

- Base prefix: **`/api/v1`**. Each module registers its own `routes/api.php` and owns Controllers, Form Requests, Resources, and policies.
- Common authentication, error handling, localization, and response semantics apply consistently. Sensitive endpoints enforce effective permission checks on the backend.
- Use RESTful CRUD for ordinary records and explicit **action endpoints** for stateful operations (e.g., timer pause/resume, session close, refund, cashier shift reconciliation, subscription renewal).
- Examples, not final contracts: `POST /api/v1/sessions`, `POST /api/v1/sessions/{id}/close`, `POST /api/v1/sessions/{id}/timers/{timerId}/pause`, `POST /api/v1/invoices/{id}/refund`.
- Business operations should be idempotent where a retry would otherwise duplicate a payment, refund, payroll payout, or finalized invoice.
- API error status codes are meaningful: `401` unauthenticated, `403` forbidden, `404` not found, `409` business-state conflict, `422` validation, and correct `2xx` success codes.
- Pagination and response/error envelopes are centrally standardized. Large collections should be filtered and paginated.

### Representative response envelopes

```json
{
  "success": true,
  "message": "تم تسجيل الحضور بنجاح",
  "data": { "id": 15, "status": "present" }
}
```

```json
{
  "success": false,
  "message": "البيانات المدخلة غير صحيحة",
  "errors": { "child_id": ["حقل الطفل مطلوب"] }
}
```

The exact pagination/meta contract will be specified in the API standards, and existing `printingApi` conventions may be reused where they are consistent with this document.

### Central Select Registry

A single `GET /api/v1/selects?selects=activities,groups,trainers` endpoint returns batched dropdown options. Providers belong to the module that owns the data; registration and dispatch are in Core. Follow the established `printingApi` approach:

```json
{
  "success": true,
  "message": "",
  "data": [
    { "label": "activities", "options": [{ "value": 3, "label": "السباحة" }] }
  ]
}
```

Providers enforce authorization and accept only approved parameters/filters. The select service is not a way to bypass normal authorization, expose private lists, or introduce duplicated CRUD endpoints.

## 7. Authentication, Authorization & Identity Separation

- **Laravel Sanctum** handles API authentication. The SPA cookie vs. token strategy is deferred until the later frontend origin/deployment design is known; do not assume tokens are always the right browser strategy.
- **Every operator has an individual login**; shared reception/cashier accounts are not allowed. Account disablement immediately blocks protected operations under the selected authentication mechanism.
- A **User** is an Identity entity with permissions; an **Employee** is an HR entity with jobs, contracts, shifts, and payroll. Linking user to employee is optional in either direction where it makes business sense.
- Children and guardians do **not** require sign-in accounts in V1.
- **Spatie Permission + Policies/Gates** check actual permission keys, not fixed role-name conditions throughout business code. Manager receives broad management capability; reception capabilities follow explicitly assigned permissions, including invoice cancellations/refunds when granted.
- Security checks apply to reading sensitive records, dropdown providers, reports, exports, and business actions—not just write endpoints. Audit sensitive permission changes.
- Login/rate-limit protections, session invalidation, password hashing, and appropriate `401`/`403` handling are required. Never log plaintext passwords, tokens, payment credentials, or secrets.

## 8. Shared Infrastructure & Localization

**Core**, not a separate business module, provides centralized Select Registry, response serialization/error handling, localization, and a narrow audit logger. Avoid generic helper packages growing into an ungoverned 'shared business logic' module.

**Arabic-first V1:** `APP_LOCALE=ar`, `APP_FALLBACK_LOCALE=ar`. System-owned API messages, validation errors, permission/role display labels, and status labels are Arabic in the active user experience. Underlying permission keys, enum/status codes, route names, and database identifiers remain stable English. Build for future language packs but do not require translated user-entered names or a language switch in V1.

**Audit Logs:** Record **business-sensitive** events only (payments/refunds, prices, payroll and contract changes, subscriptions/corrections, permissions, cashier review, critical attendance overrides, timer transitions). Capture actor, time, target, action, reason when relevant, and safe before/after fields. Do not log ordinary reads or secret/sensitive payloads. The audit log is **not** a substitute for source transaction, financial, attendance, or time-segment records. Critical audit entries are persisted synchronously alongside the protected operation when appropriate.

## 9. Timer & Session Integrity

- A session is opened under a customer's name; it may contain multiple independent hourly activity uses and café orders. No child enrollment or per-child count is necessary for hourly use.
- Each timer has a **current state** (`ready`, `running`, `paused`, `finished` or equivalent defined in spec), an authoritative time accumulator/projection, and **time segments / transition history**. The state machine validates Start/Pause/Resume/Finish transitions.
- Server timestamps drive calculations; the frontend renders a live countdown/up-counter from server-supplied state and reference time. **There is no background tick process** and no database write per second.
- Paused intervals are non-chargeable. Simultaneous timers charge independently; their elapsed times are never merged into a single activity duration.
- Example: `60 EGP/hour × 70 active minutes ÷ 60 = 70 EGP`. Exact rounding for partial minutes and resulting monetary scale is deferred to the Sessions spec; use consistent exact arithmetic.
- Closing the browser or losing connectivity does not stop an already-running server timer; it remains running until a valid server-side command changes its state.
- **Capture price-at-use** so a later activity catalogue price edit cannot rewrite prior costs. Finalized billable durations and prices are immutable bill snapshots.
- Lock timer/session records when changing states to protect against duplicate requests and concurrent operators. A session cannot be financially closed while any timer is still running or paused; finish timers first.
- Timer segment history is operational evidence separate from the selective centralized audit log.

## 10. Financial Integrity, POS & Cashier

### One canonical financial source

- **POS** records source invoices, collections, payment methods, refunds, and cashier shift references; **Finance** maintains the canonical immutable **financial movements** and operating expense postings. Do not create a second independent collection or balance truth.
- Every cash receipt, subscription installment, refund, authorized cash expense, and payroll payout results in exactly the appropriate source record(s) and linked canonical movement **within the same database transaction**.
- The financial movement is append-only after posting; corrections and refunds are separately linked reversal/adjustment movements. The user who made the correction and the mandatory refund/cancellation reason remain traceable.
- Original service value (**sales**), actual payments (**collections**), remaining subscription dues (**receivables**), expenses, and salary payouts are distinct reporting measures. A later installment reduces the debt; it is **not** a new subscription sale.
- Maintain optional derived/cache balances only if verifiably reproducible from canonical movements. Do not make mutable cache fields the financial source of truth.

### Atomicity & idempotency

- A session closing transaction locks the session, verifies all timers finished, fixes charges and optional allowed discount, issues a single invoice, records **full** payment, posts the financial movement, updates the relevant cashier shift, and marks the session closed atomically.
- Subscription payments may be partial and must not exceed business-approved rules in their feature spec; all installments map back to the same subscription obligations.
- Refunds/cancellations require permission plus **reason**, may be partial or full, and cannot exceed the amount actually collected and still refundable. The original invoice/payment is never simply erased.
- Use request idempotency keys and database uniqueness to defeat double-clicks, network retries, and near-simultaneous transactions. Scope and expiry of keys are defined per feature; don't rely only on a disabled frontend button.
- Avoid external irreversible side effects inside a database transaction. V1 records cash, card, and e-wallet payment methods; third-party gateway integrations are out of scope unless separately approved.

### Cashier shifts

- A cashier **financial shift** is distinct from an employee **work shift**. Record opening cash, shift-linked cash receipts, cash expenses, actual closing count, expected closing cash, and shortage/surplus.
- **Expected closing cash = opening cash + cash collections − cash refunds − cash expenses**, adjusted only by other explicitly defined cash drawer movements. Card/e-wallet receipts are reported separately and never treated as drawer cash.
- Reception may record permitted cash expenses against an open cashier shift; these affect expected cash immediately and are subject to manager review. Shifts can be reconciled without losing the original payment/movement evidence.

## 11. Attendance, Subscriptions & Payroll Boundaries

- An activity may be monthly, hourly, or both; nursery is **monthly-only**. Monthly subscriptions have child, guardian, group, period, price/collection history, and active/expired determination from dates.
- No cron is required to expire a subscription: **time-based eligibility is computed on query/action**. Renewal is manual; freezing and automatic renewal are not supported.
- A child may hold concurrent subscriptions to different activities. Group capacity, if configured, is enforced under concurrency protection.
- Make-up classes are linked to a missed scheduled class and cannot be consumed twice. They may be fulfilled within the active period or within **seven days following expiry**, without extending the subscription; capacity and booking details belong in the Activities spec.
- Workforce distinguishes staff **shift attendance** from trainer **scheduled-class attendance**. Pay contract type (fixed / per-class / combined) does not determine attendance model.
- Trainer substitution is per delivered class; only the person who actually delivered a class receives eligible per-class compensation. A trainer's presence without class delivery does not imply a payable class.
- Payroll calculations are **on demand**. Suggested lateness/absence deductions require review; the manager can accept/change/waive them. Preserve suggested versus final approved figures and avoid double-penalizing an undelivered class.
- Leave/permissions are recorded directly by authorized management; no staff self-service approval workflow in V1.

## 12. Synchronous-Only Operations, Reports & Performance

**V1 forbids application queues, queued jobs, Redis, background workers, scheduler/cron reliance, and push/websocket infrastructure.** Laravel APIs must not depend on them implicitly (including queued notifications or listeners).

- Timers derive elapsed duration from stored instants, not scheduled increments.
- Subscription status/expiry and reminders shown to staff derive from real-time queries when a screen is opened.
- Payroll runs calculate on manager request, within bounded transactional work units.
- Dashboards query business-owned records and authorized projections, distinguishing sales from collections and refunds. Do not post new financial movements in reports.
- Excel and PDF exports are **synchronous**. Prefer streaming/chunked queries and small-memory output where the chosen library supports them; apply date filters, explicit size/range limits, and a clear validation error for oversized requests. Do not silently queue.
- Avoid leaving sensitive generated documents in publicly accessible directories. Where temporary files are necessary, use private storage, return securely, and choose an explicit cleanup approach **that does not require cron**.
- Add indexes based on actual queries; paginate operational tables. Measure slow exports before introducing complexity, while remaining within cPanel memory/execution limits.

## 13. Hosting, Deployment, Security & Backups

### Deployment contract: Shared Hosting / cPanel

- Verify actual hosting **PHP version/extensions** (Laravel 12 compatible), MySQL 8, Composer dependency installation path, memory and request timeouts, secure SSH/terminal or equivalent **Artisan** execution ability, and SSL availability.
- Laravel files and `.env` live **outside the publicly served document root**; only the `public` front controller and intended public assets are web-accessible. Never expose `vendor`, `storage` secrets, project root, or migration files through the site.
- Production configuration requires `APP_DEBUG=false`, HTTPS, controlled database credentials, writable `storage` and `bootstrap/cache`, safe cookie/CORS settings as appropriate, and secrets excluded from git.
- Deploy an approved stable release (not every `develop` merge automatically): install locked production dependencies, configure environment, run reviewed migrations, build/refresh appropriate caches, verify write permissions, smoke-test critical endpoints, and retain a rollback/restoration plan.
- Migrations must consider existing historical records and shared hosting maintenance constraints. A MySQL backup is required before high-risk changes.
- Account/session secrets and access to children/guardians, pay data, export files, and payroll are permission controlled. Apply rate limits to login and sensitive endpoints.
- **No long-running workers, scheduled tasks, or background application services are part of deployment.**

### Application logs vs. audit logs

- Application error logs use controlled rotating files with retention/space limits and exclude personal secrets or full private request payloads.
- Central selective audit logs are business events in MySQL, not the primary financial ledger.

### Backups & release gate

- **Automated daily backups managed by the hosting provider are a V1 go-live requirement.** Verify that the purchased plan actually backs up the MySQL database and essential private files, that backup retention and access are acceptable, and that restoration is available.
- Test a full restoration in a safe environment before launch and periodically thereafter; an unverified backup is not sufficient.
- Provider-side backup automation is outside Laravel and therefore does not violate the no-Jobs/no-Cron architecture. If the host does not provide the required backup and restoration capabilities, the deployment is **not ready**.
- Proposed operational target (subject to actual hosting capability): daily database backups, regular full file backups, defined retention, and tested restoration procedure. Do not claim a 30-day retention policy is implemented before verifying the hosting plan.

## 14. Testing & CI Reliability

Every business feature requires tests appropriate to its risk. A change is not complete because an endpoint merely returns `200`.

| Level | Minimum intended coverage |
|---|---|
| Unit | Hourly price calculation, pause exclusion, calendar-month eligibility, payroll formulas and deductions |
| Feature | Auth, permissions, Form Request validation, localized errors, CRUD/action responses |
| Integration | Session/Cafe/POS/Finance atomic closing, installment receivables, cashier reconciliation, substitutions and payroll |
| Concurrency / regression | Two simultaneous settlements, repeat refund, last group seat, simultaneous timer state transitions, idempotent retries |
| Manual acceptance | Reception daily session + café workflow, subscription + make-up, trainer/payroll, cash shift closing, exports |

- Use **MySQL 8** in CI integration/concurrency tests; SQLite alone is not an adequate substitute for testing InnoDB row locks and foreign keys.
- **GitHub Actions runs on pull requests targeting `develop`.** Its required checks include dependency installation, migrations/test fixtures, and automated tests. Apply branch-protection/ruleset required status checks when GitHub permissions and plan allow them; until then treat passing CI as a manual merge gate.
- A feature PR includes the Spec Kit specification/plan/tasks as relevant, production code, automated tests, migration notes, and manual validation of business flows. No merge with failing relevant tests.
- Keep secrets in protected GitHub/hosting settings; never in repository configuration or example responses.

## 15. Spec Kit Workflow & Development Branches

**Canonical product overview:** `docs/00-overview/project-overview.md`  
**Architecture:** this document  
**Project constitution (next artifact):** `.specify/memory/constitution.md`  
**Cross-cutting standards (subsequently):** `docs/02-standards/`  
**Feature documents:** Spec Kit-managed `specs/<feature-number>-<slug>/` paths.

Development sequence for each feature:

```text
Approved project-wide principles
  -> Feature spec (`spec.md`: requirements, scenarios, acceptance criteria)
  -> Implementation plan (`plan.md`: module impact, schemas, APIs, trade-offs)
  -> Tasks (`tasks.md`: small ordered delivery steps)
  -> Implement and test
  -> Pull request to develop + passing MySQL 8 CI + review
  -> Approved merge
```

- Split large modules into **independently reviewable features**, each with its own spec, plan, tasks, tests, and separate PR where useful. A module can be visited in more than one delivery phase.
- `feature/*` starts from `develop`; `develop` integrates completed reviewed work; `main` represents a stable, separately approved release. Production deployments are not automatically triggered by merges to `develop`.
- The `.specify/memory/constitution.md` document will state mandatory governance gates, including module ownership, accurate money/timer state, secure Arabic-first API conventions, tests, and the **no queue/job/redis/cron** constraint.

## 16. Approved Implementation Roadmap

| Phase | Focus | Example deliverables |
|---|---|---|
| **00 — Foundation** | Framework and shared foundation | Laravel, modules, MySQL, common API conventions, locale, selects, audit infrastructure, GitHub CI |
| **01 — Identity + Core Data** | Access and basic entities | Login/permissions, children/guardians, customers, initial employees/trainers |
| **02 — Activities + Café Catalogue** | Priced service setup | Activity types, groups, schedules, trainers, café products/prices |
| **03 — Finance + POS Foundation** | Safe financial base | Canonical movements, invoices/payments, cash shifts, expenses and refunds |
| **04 — Sessions & Timers** | Walk-in daily operations | Timer state machine/segments, café attachment, full settlement and closing |
| **05 — Subscriptions & Child Attendance** | Enrollment | Monthly subscriptions, installments, renewals, group attendance and make-up classes |
| **06 — Workforce & Payroll** | Staff operations | Shifts, trainer class attendance/substitution, leave, contracts, payroll and review |
| **07 — Reports & Final Integration** | Management visibility and release quality | Role dashboards, operational/finance reports, Excel/PDF, cross-module acceptance tests |

This is an **order of dependencies, not a sequence of whole-module completions**. A module may be worked on across multiple phases to support end-to-end features. All eleven modules remain in V1 scope.

## 17. Decisions Deferred to Feature Specifications

Do not invent these rules at architecture level; settle them when the owning feature is specified:

- Exact minute/second treatment and rounding of time-based charges, discounts, and decimal precision.
- Precise definition of an eligible trainer-delivered class and attendance evidence; exception approval and substitution accounting.
- Group-capacity concurrency and make-up booking/cancellation rules; boundary dates for subscriptions starting on the 29th–31st.
- Subscription receipt/obligation modeling and policy for overpayments, cancellations, and adjustments.
- Exact idempotency key scope/retention, source-reference uniqueness, refund/partial refund reversals, and invoice numbering.
- Large-report export size thresholds and the chosen synchronous Excel/PDF libraries.
- Frontend-origin-aware Sanctum authentication mode, session expiry, and secure browser transport.
- Hosting-specific backup retention, restoration drills, and safe deployment/rollback procedure.

Any later change to a **project-wide approved architectural decision** must be reviewed here and, where applicable, in the project constitution before features rely on it.