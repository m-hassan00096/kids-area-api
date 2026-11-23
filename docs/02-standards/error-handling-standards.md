# Kids Area — Backend Error Handling Standards

> **Status:** Approved V1 standards; implementation not yet started.  
> **Location:** `docs/02-standards/error-handling-standards.md`  
> **Scope:** Laravel 12 REST API; one Kids Area location; single MySQL 8 / InnoDB database; API-first modular monolith.  
> **Language:** Arabic (`ar`) for user-facing error messages in V1.  
> **Related documents:** [Project Overview](../00-overview/project-overview.md), [Backend Architecture](../01-architecture/backend-architecture.md), [Code Standards](code-standards.md), [API Standards](api-standards.md), [Database Standards](database-standards.md), and the appropriate feature specification.

## 1. Purpose and Boundaries

Define **how errors are identified, raised, rendered, logged, correlated, recovered from, and tested** across Kids Area.

The shared policy applies to **Identity, People, Employees, Workforce, Payroll, Activities, Sessions, Cafe, POS, Finance and Reports**. It does not replace feature-specific state machines, financial calculations or the API response contract.

**Principles**

1. **Laravel 12 conventions first.** Use Laravel Form Requests, Policies/Gates, native exceptions, the `withExceptions(...)` configuration and normal logging. No homegrown error framework.
2. **One consistent external contract.** All Laravel-handled `/api/*` errors use the approved JSON envelope in [API Standards](api-standards.md).
3. **Separate responsibilities.** Form Requests validate input; Policies/Gates authorize operations and resource visibility; Actions enforce current business state and throw focused failures; Controllers do not format every failure with `try/catch`.
4. **Minimal BusinessException.** One shared business exception in Core, specialized module exceptions only when genuinely useful.
5. **Safe Arabic messages.** No SQL, stack traces, server paths, credentials, private children/guardians' details or payroll figures in production error responses.
6. **Selective reporting.** Expected `4xx` failures are not routine technical errors. Log unexpected `500` failures, identified `503` outages and deliberately selected suspicious attempts.
7. **Financial safety.** Error handling does not create partial invoices/collections/refunds, duplicate payouts, or untracked state changes.
8. **Correlation.** Attach an independent server-generated `X-Request-ID` to every Laravel-handled response, and correlate technical logs by that ID.
9. **V1 simplicity.** No queues, Redis, cron/scheduler jobs, dead-letter processors, external monitoring dependency or mandatory distributed tracing.

## 2. Approved API Error Response Contract

**The API response envelope is already decided. Do not create a second convention.**

```json
{
  "success": false,
  "message": "لا يمكن إغلاق السيشن قبل إنهاء جميع المؤقتات.",
  "errors": {
    "code": "SESSION_TIMERS_NOT_FINISHED"
  }
}
```

**Mandatory rules**

- Error responses include **`success: false`, an Arabic `message`, and `errors`**; never include `data` for an error.
- `errors` can be an empty JSON array `[]` or an object containing meaningful structured information.
- `errors.code` is **optional** for stable machine-readable business failure identities. **Never add a top-level `code` field.**
- Public validation keys use **`camelCase`**, matching requests, including nested fields where appropriate.
- Messages are Arabic via localization keys; technical machine codes are stable English `UPPER_SNAKE_CASE`.
- Successful GET/login/logout `"message": ""` rules are **success-only**; an error on those routes has a meaningful error message.
- Successful Excel/PDF downloads return binary responses, **not** the JSON success envelope. A failure before download streaming begins returns the normal JSON error response.

**Example: validation failure (422)**

```json
{
  "success": false,
  "message": "البيانات المدخلة غير صحيحة.",
  "errors": {
    "childId": ["حقل الطفل مطلوب."],
    "groupId": ["حقل المجموعة مطلوب."]
  }
}
```

**Example: no specialized business code required**

```json
{
  "success": false,
  "message": "لا يمكن تنفيذ العملية في الحالة الحالية.",
  "errors": []
}
```

Do not require a code for each framework validation/authentication error. Avoid parsing an Arabic message to distinguish business cases in the frontend.

## 3. HTTP Status Codes and Error Classification

| Failure | HTTP status | Kids Area example |
|---|---|---|
| Invalid request structure outside ordinary field validation | `400 Bad Request` | Malformed request |
| Unauthenticated | `401 Unauthorized` | Missing/invalid authenticated context |
| Missing permission for the **feature/capability** | `403 Forbidden` | Receptionist cannot manage payroll |
| Record missing **or outside permitted record visibility** | `404 Not Found` | Private child/employee record not visible to the caller |
| Valid operation conflicts with current persisted business state | `409 Conflict` | Active timer prevents session close; group full |
| Idempotency key reused with materially different input | `409 Conflict` | Reused collection key with new amount |
| Invalid submitted fields, unregistered Select, missing required idempotency key | `422 Unprocessable Entity` | Invalid money; unknown Select |
| Too many requests | `429 Too Many Requests` | Excessive login attempts |
| Unexpected internal error or unidentified SQL fault | `500 Internal Server Error` | Unexpected exception |
| **Confirmed temporary service unavailability** | **`503 Service Unavailable`** | Confirmed DB outage/maintenance |

**Decision rules**

- `422` means bad submitted data; `409` means valid submitted data meets an invalid **current business state**.
- MySQL error codes do not determine public HTTP codes without context. A driver error may represent a known `409` business conflict or an unexpected `500` fault.
- Use `503` for confirmed **temporary unavailability**, not a generic replacement for all `500` errors. Do not assume a payment failed merely because the caller received `503`.
- Exhausted safe deadlock retries can be `503` when temporary unavailability is established. If not, use the correct unexpected-fault handling instead.
- Preserve the `429` throttling behavior and any applicable `Retry-After` header. For `503` set `Retry-After` only when the next safe attempt time is genuinely known.
- **Laravel's `419` CSRF/session response is deferred** until the approved future frontend/auth transport plan. Laravel Sanctum is approved, but a Cookie/Session or Bearer token strategy has **not** yet been chosen.
- This standard does **not** require creating an `HttpStatusCode` enum; existing API Standards permit a pragmatic shared helper and integer codes.

## 4. Permission-Aware 403 / 404 and the Strict Select Registry

The approved access failure policy is:

- Use **`403`** when an authenticated user lacks permission to perform a **feature-level action**, such as viewing payroll management.
- Use **`404`** when a particular requested record **does not exist** or is **outside the caller's permitted visibility scope**. Use the same public body for both so probing IDs does not expose protected records.
- Apply authorization/visibility filtering to the query or Policy correctly; do not fetch arbitrary private models and then expose their attributes before rejecting.
- For private attachments, enforce both the download capability and the requested parent record's visibility. Apply a scoped `404` where concealing existence is appropriate.
- Do not disguise unexpected DB errors as `404`.

**Strict Select Registry exception (already approved in API Standards):**

```http
GET /api/v1/selects?selects=activities,groups,trainers
```

1. Authenticate.
2. Validate requested key names. **Unknown/unregistered key => 422**.
3. Authorize *all* requested keys. **Any forbidden key => 403 for the entire request, with no partial options**.
4. Only then execute the owning modules' providers.

Do not change the Select Registry's successful `data` array-of-`label/options` shape or silently omit unauthorized providers.

**403 example**

```json
{
  "success": false,
  "message": "ليس لديك صلاحية لتنفيذ هذه العملية.",
  "errors": []
}
```

**404 example**

```json
{
  "success": false,
  "message": "السجل المطلوب غير موجود.",
  "errors": []
}
```

## 5. Ownership of Exceptions and Minimal BusinessException

| Layer | Responsibility |
|---|---|
| `FormRequest` | Validate field types/structure/ranges; `ValidationException` becomes 422 |
| `Policy` / Gate / permission middleware | Enforce authentication, capability permission, particular-record visibility |
| `Action` | Enforce the current state, availability, limits and financial invariants; throw expected `BusinessException` |
| `Service` | May throw an owning domain failure in reusable logic; not HTTP formatting |
| `Controller` | Receive validated input, delegate **all writes** to Actions, authorized simple reads, successful API Responses |
| `Global Handler` | Central Laravel 12 exception → standard JSON/status mapping |
| `Owning module` | Feature-specific Arabic message keys, stable codes and regression tests |

Use a minimal shared class, conventionally at `app/Exceptions/BusinessException.php`:

```php
<?php

namespace App\Exceptions;

use RuntimeException;

class BusinessException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly int $httpStatus = 409,
        public readonly ?string $errorCode = null,
    ) {
        parent::__construct($message);
    }
}
```

This is **illustrative**, not a fixed Core helper signature or evidence that the application already exists. Validate or constrain accepted statuses in the implementation.

**Illustrative Sessions Action:**

```php
throw new BusinessException(
    message: __('sessions.errors.timers_not_finished'),
    httpStatus: 409,
    errorCode: 'SESSION_TIMERS_NOT_FINISHED',
);
```

Specialized exceptions can live inside their owning module, for example `Modules/Sessions/app/Exceptions/SessionAlreadyClosedException.php`, when meaningfully reused or needing distinct processing. Follow the installed module package's real namespace layout.

**Do not** create a custom exception for every ordinary Form Request rule or a hierarchy of Domain/Application/Infrastructure exception abstractions just to fit the architecture.

## 6. Module-Owned Stable Error Codes

- Codes are stable, nontranslated English **`UPPER_SNAKE_CASE`** strings.
- A module owns its codes and documents important public codes in its **feature spec**; local constants/enums are optional implementation details.
- Do not change the meaning of a published code when Arabic messages are revised.
- Use optional `errors.code` only when the frontend/client needs a different behavior or a stable error identity.
- Do not add a global error-code registry or a mandatory code for validation, 401, 404 or every infrastructure failure.

**Illustrative codes, pending feature-specific approval**

| Module | Possible code | Typical status |
|---|---|---|
| Sessions | `SESSION_ALREADY_CLOSED` | 409 |
| Sessions | `SESSION_TIMERS_NOT_FINISHED` | 409 |
| Activities | `GROUP_CAPACITY_REACHED` | 409 |
| Activities | `ATTENDANCE_ALREADY_RECORDED` | 409 |
| POS/Finance | `REFUND_AMOUNT_EXCEEDED` | 409 |
| Shared sensitive command | `IDEMPOTENCY_CONFLICT` | 409 |

The specific code ownership, exact names and error scenarios are approved within the relevant feature spec; these examples do not introduce a hidden completed endpoint inventory.

## 7. Global Exception Handler in Laravel 12

Configure error-to-response mapping **once** in Laravel 12's `bootstrap/app.php` using `withExceptions(...)` and the existing **`App\Support\ApiResponse`** API helper. Do not create a second response renderer.

**Required mappings**

- `BusinessException` → its safe Arabic message, approved HTTP status, optional `errors.code`.
- `ValidationException` → `422` and `camelCase` field-error arrays.
- Authentication failure → `401`, non-HTML.
- Unauthorized feature action → `403`; scoped invisible record/model not found → `404`.
- Unknown Select → `422`; forbidden requested Select → strict `403`, no partial result.
- Framework missing model/route → `404` with generic Arabic message where applicable.
- Rate limiting → `429` with valid relevant headers.
- Explicit known temporary service failure → `503`; unknown exceptions → generic `500`.

**Illustrative Laravel configuration outline (not full runnable mapping)**

```php
// In the existing bootstrap/app.php Application::configure(...) chain:
->withExceptions(function (Exceptions $exceptions): void {
    // Render /api/* exceptions through App\Support\ApiResponse.
    // Map BusinessException, ValidationException, auth, 403/404, 429,
    // confirmed 503, then unexpected 500.
    // Preserve useful safe response headers and correlation IDs.
    // Configure selective reporting rather than logging every 4xx.
});
```

**Important safeguards**

- Consistently render JSON for **all Laravel-handled `/api/*` exceptions**; never leak Laravel's default HTML login redirect/debug output to API consumers.
- Use a global handler for response formatting and Laravel's framework reporting configuration for selective logs. Do not implement repetitive `failedValidation()` overrides and Controller-level catches.
- **Never render the raw `getMessage()` of an unknown SQL/PHP exception** to production clients.
- Production uses `APP_DEBUG=false`. Keep safe Arabic generic messages for `500` and `503`.
- Implement handlers against the **actual installed Laravel 12 APIs**, not copied Laravel 13 snippets assumed identical.
- **Binary export exception:** successful Excel/PDF is binary, but prestreaming failures return regular JSON. Do not attempt JSON inside already-partially-sent binary output.
- A genuine web server/PHP startup failure outside Laravel may produce non-JSON `5xx`; middleware cannot guarantee formatting for responses it never handled.
- The future Sanctum Cookie/Session vs Bearer decision will determine CSRF/session-expiry nuances, including any needed `419` mapping.

## 8. Contextual Database Errors and Safe Deadlock Retries

**Approved: Safe Contextual Database Error Mapping.** An expected/recognized constraint failure can become an appropriate BusinessException in the owning Action. Unknown SQL/connection/schema failures remain unexpected operational errors.

| MySQL example | Cause | Policy |
|---|---|---|
| `1062` | Unique constraint violation | **Known** uniqueness conflict → safe business `409` (or idempotent result); unknown → `500` |
| `1451` / `1452` | Foreign key restriction/reference error | Known domain invalid state → justified `409`/`422`; unknown → `500` |
| `1213` | Deadlock | Limited **safe full-transaction retry**; no duplicate effect |
| `1205` | Lock wait timeout | Evaluate rollback/transaction state, retry only if provably safe |
| `1040` | Too many DB connections | Operational fault; `503` only if temporary unavailability established |
| `2002` | Connection refused | Operational fault; `503` only if temporary unavailability established |
| Other driver/SQL exception | Unexpected fault | Safe `500` and sanitized operational report |

Codes are examples; use actual driver/SQLSTATE plus **known constraint name and business context**, not raw message string matching alone.

**Rules**

1. Unique constraints and row locking are still mandatory where required by [Database Standards](database-standards.md). Prior `exists()` checks alone cannot prevent concurrent duplicates.
2. The coordinating Action or its transaction boundary owns retries, not the response renderer.
3. Retry a **whole rollback-safe transaction**, including fresh state checks, a limited number of times only where idempotency and lack of external side effects are assured.
4. If the commit result is uncertain after timeout/connection loss, **do not assume the entire operation rolled back**.
5. Unknown SQL faults must not be reported as "already paid" or "group full" merely to provide a nicer message.
6. Sanitize database-error logging: Laravel `QueryException` messages may contain SQL and bindings, including personal data. Do not log raw SQL/bindings or connection secrets.
7. After a confirmed transaction rollback, keep the database in the prior valid state; never leave posted movement without linked receipt or vice versa.

## 9. Selective Error Logging, Privacy and Audit Separation

Use **Laravel Logging** with controlled rotating/protected files suitable for shared hosting. No compulsory third-party monitoring service in V1.

| Class | Routine Error Log? |
|---|---|
| Normal `401` / `403` / `404` | No; record suspicious/security-sensitive patterns intentionally |
| Validation `422` | No |
| Expected business `409` | No |
| Throttling `429` | Only security-relevant monitoring as needed |
| Recovered deadlock | Optional concise warning |
| Unknown `500` | Yes; safe diagnostic context |
| Confirmed temporary `503` outage | Yes; safe operational context |

**Useful safe context:** server-generated Request ID, module/Action, route template, authenticated user ID, safe resource IDs, exception class and allowed machine identifier.

**Never log:** passwords, tokens, authorization/session/CSRF headers, raw request bodies, private child/guardian identity or contacts, confidential staff contracts/payroll details, full payment credentials, private files, raw SQL bindings, SQL dumps or unredacted sensitive exception messages.

- Avoid catch-log-rethrow patterns that duplicate a single error report.
- Ensure selected framework exception reporters do not accidentally record SQL-bound personal data by default.
- Protect logs outside the public document root; configure rotation/retention according to cPanel disk limits.
- Do not use Error Logs as transaction history or as an excuse to commit partial operations.

**Different records with different responsibilities**

| Record | Purpose |
|---|---|
| Application/Error Log | Technical diagnostics, unexpected failures |
| Core selective Audit Log | Sensitive business actions, actor/time/approved reasons |
| Finance movements | Canonical money in/out, append-only after posting |
| Domain history | Attendance, timer segments, subscriptions, contracts and approved payroll snapshots |

A successful authorized refund creates appropriate Finance OUT movement and audit evidence, **not** a technical Error Log. A rejected refund does not create a fake OUT movement; a narrowly scoped audit entry may be appropriate only if specified.

## 10. Request ID and Traceability

**Approved:** every response **handled by Laravel** receives an opaque, unique, server-trusted correlation ID in:

```http
X-Request-ID: 7f82a1c0-4d92-4f75-8ab1-31c27b50c612
```

**Rules**

- Generate one Request ID per inbound HTTP attempt via small middleware/correlation service; make it available to logs and the exception reporting pipeline.
- Return it on normal JSON successes, expected errors, unexpected errors and **successful binary exports** that pass through Laravel.
- Attach the same identifier to Error Logs produced for that request, avoiding raw request data.
- Do **not** trust an arbitrary client-supplied identifier as the authoritative ID; generate a server-owned value or apply an explicitly approved trusted-proxy policy. Prevent header/log injection.
- **No JSON changes**: `X-Request-ID` is a response header only, not `errors.requestId`, and no top-level `requestId` is added.
- A Request ID identifies **this particular HTTP attempt**. It is **not** an `Idempotency-Key`; financial retries reuse their original idempotency key, while each retry receives a fresh Request ID.
- If the future frontend is cross-origin, configure CORS to **expose `X-Request-ID`** to permitted browser clients without prematurely selecting Sanctum transport.
- An outage before Laravel runs cannot guarantee a Request ID from Laravel.

**Example**

```http
HTTP/1.1 500 Internal Server Error
X-Request-ID: 7f82a1c0-4d92-4f75-8ab1-31c27b50c612
Content-Type: application/json
```

```json
{
  "success": false,
  "message": "حدث خطأ غير متوقع. يرجى التواصل مع المسؤول.",
  "errors": []
}
```

## 11. Production 500/503 and Uncertain Results

**500: unexpected internal failure**

```json
{
  "success": false,
  "message": "حدث خطأ غير متوقع. يرجى التواصل مع المسؤول.",
  "errors": []
}
```

**503: established temporary unavailability**

```json
{
  "success": false,
  "message": "الخدمة غير متاحة مؤقتًا. يرجى المحاولة لاحقًا.",
  "errors": []
}
```

- Optional `errors.code` may identify an approved client recovery flow, but is **not mandatory** for every `500` or `503`.
- Record unexpected/outage incidents with safe, correlated Error Logs.
- The global handler must be able to produce a safe error **without querying MySQL again** when the database is down.
- Add `Retry-After` to `503` only with reliable timing; preserve authentic throttle headers for `429`.
- A temporary outage or timeout **does not prove whether a financial transaction committed**. Do not authorize a new collection simply because a `5xx` was shown.
- Web server or proxy-generated errors before Laravel may not use this JSON envelope or header; the client must handle transport failures separately.

## 12. Financial Fail-Closed Recovery and State Reconciliation

**Approved: Fail-Closed + State Reconciliation.**

Applies to session settlement, receipts, subscription installments, refunds, payroll payouts, cashier expenses, and any other sensitive replay-risk command.

### Before a successful commit

- One outer MySQL `DB::transaction()` controlled by the coordinating Action covers **all required writes**, including cross-module Sessions/POS/Finance effects and critical synchronous audit evidence when specified.
- If a required step fails, propagate the exception so the **entire transaction rolls back**. Never catch, suppress and continue with a half-paid invoice.
- Lock current state, use approved unique source references and scoped idempotency keys; "disable submit button" is not an atomicity mechanism.
- A declined refund/payout cannot create a new posted movement.
- **Do not** perform long PDF/Excel generation, external network calls, printing or irreversible side effects while holding financial locks.

### After commit or while the result is uncertain

- A PDF/receipt/printing failure **after a successful commit** must not undo the posted payment. Regenerate/reprint the document separately.
- A timeout or connection loss **during/after commit** can make the outcome unknown to the caller. Do not infer rollback or failure.
- The client should query authoritative state or **retry the equivalent logical command with the original `Idempotency-Key`**.
- Reuse of the same key and materially different input returns **409**, without a new effect. Same key and equivalent input returns prior result/equivalent success, without duplicate movement.
- **Never generate a new key solely because the previous request returned 500, 503 or timed out.**
- If reliable state reconciliation is temporarily impossible, avoid issuing an additional manual collection until resolved.
- Never create a compensating Refund or Financial Movement automatically merely to "fix" an HTTP error.
- Error Logs and Audit Logs are separate from the immutable posted Finance movements.

**Illustrative workflow**

```php
DB::transaction(function () use ($sessionId): void {
    // Lock session; ensure timers have finished.
    // Calculate and snapshot charges.
    // Create invoice, full payment and canonical Finance movement.
    // Update cashier shift; mark session closed.
    // Persist required business audit evidence.
});

// After successful commit, generate receipt/PDF separately if needed.
```

Feature specs own the precise transaction boundaries, idempotency scope/retention and final business state machine.

## 13. PHPUnit Error Contract and Business Failure Tests

**Approved: Contract + Business Failure Tests**. Use **PHPUnit** for shared API error rendering and separate module/feature tests for failure effects. MySQL 8 is mandatory where real transactions, locks or uniqueness are material.

| Test | Required outcome |
|---|---|
| Validation | 422; Arabic message; `camelCase` errors; no `data` |
| Authentication | 401 JSON, no HTML redirect |
| Feature permission | 403 without private details |
| Scoped record | 404 for missing/invisible record with indistinguishable public response |
| Select Registry | Unknown 422; any forbidden 403 for whole request |
| Business state | 409 for unfinished session timer, full group, duplicate attendance etc. |
| Error codes | Optional correctly located `errors.code`, stable naming, no top-level code |
| Throttling | 429 JSON and relevant safe rate-limit headers |
| Unexpected failure | 500 generic safe Arabic response, without SQL/paths/stack trace |
| Confirmed outage | 503 safe message; no invented retry window |
| Request correlation | `X-Request-ID` for Laravel-handled successes, errors and binary exports; log ID matches |
| Logging and privacy | Expected 4xx not ERROR-logged by default; no secrets/raw SQL/personal data |
| Contextual database error | Recognized duplicate/FK conflict mapped appropriately; unknown error not masked |
| Rollback | Injected failure in financial posting leaves no partial invoice/receipt/movement/closed session |
| Idempotency | Same key+payload one effect; same key+different payload 409; no duplicate on retry |
| Concurrency | Real overlapping MySQL connections block duplicate refund, final invoice, open timer segment and last group seat |
| Payroll | Approved snapshot preserved; duplicate payout prevented |
| Exports | Prestreaming export failure returns JSON, never a mixed binary/JSON payload |

- Test **true overlap** with independent MySQL connections/processes/transactions; sequential tests alone do not prove race safety. Be careful of Laravel test transaction wrappers hiding contention.
- Verify state changes and database row counts, not merely HTTP status.
- Test Request ID presence and consistency, while distinguishing it from `Idempotency-Key` across retries.
- For PRs to `develop`, require relevant PHPUnit + MySQL 8 tests and Laravel Pint **when CI is configured**; do not claim tests passed without actually executing them.
- Add a regression test when fixing a sensitive error-handling defect.

### Definition of Done

1. Owning feature spec lists relevant business failures, status semantics and any required machine code.
2. The appropriate FormRequest/Policy/Action throws or rejects correctly; Controllers do not repeat JSON error formatting.
3. Laravel 12 global rendering returns Arabic unified errors, appropriate status and `X-Request-ID`.
4. Unexpected faults are safely reported; routine 4xx are not noisy; private details remain protected.
5. Failed sensitive commands roll back completely; uncertain outcomes use original idempotency key/state verification.
6. Applicable PHPUnit/MySQL 8 and Pint checks were **actually run** before being reported as passed.

## 14. Explicit Non-Goals, Alignment and Change Rule

**Do not add simply because this standard exists:**

- A mandatory global error-code registry, custom error framework, new response package or exception class for every validation rule.
- OAuth/Passport/JWT requirements, Cookie-only Sanctum or mandatory 419 handling before selecting frontend authentication transport.
- Tenant/branch-specific errors, a public customer API or unrelated printing-production exceptions.
- Queue-driven retries, Redis locks, cron jobs, dead-letter handling, mandatory external monitoring or distributed tracing.
- Automatic compensating refunds or state changes triggered solely by 500/503.
- Logging full request bodies, SQL bindings, password/session/token data, personal child data or confidential employee payroll.

**Cross-document source of truth**

| Document | Owns |
|---|---|
| [Project Overview](../00-overview/project-overview.md) | Business features and V1 boundaries |
| [Backend Architecture](../01-architecture/backend-architecture.md) | Modules, hosting, synchronous critical operations |
| [Code Standards](code-standards.md) | Actions/controllers/services, PHP conventions and test quality |
| [API Standards](api-standards.md) | JSON envelope, public names, API routes, status contract, idempotency requests |
| [Database Standards](database-standards.md) | MySQL constraints, exact financial values, transactions and source history |
| **This Error Handling Standards file** | Exception mapping, error codes, privacy/reporting, tracing, failure/recovery policy |
| Module feature specs | Approved exact business failures, message keys, transitions, retry rules and tests |

**Documentation alignment (no open design decision):** The already-approved API Standards status table predates the newly adopted `503` rule and its content predates the `X-Request-ID` header rule. Those two entries should be synchronized with API Standards in a **separately reviewed documentation change**; this file records the approved requirements now. Older printing reference snippets are Laravel 13 and SPA-cookie-specific; do not copy their `419` assumption into Kids Area.

**Implementation status:** These are the approved **design standards only**. Publishing this file does **not** establish that the Laravel exception handler, correlation middleware, logging policy, tests, Actions or GitHub Actions workflow have been built or executed.

**Change rule:** Cross-module changes to the error contract, status classification, redaction or correlation policy require reviewed changes to this standard and affected shared docs. An individual feature error message, code or failure case belongs to the owning feature specification.
