# Kids Area — Backend API Standards

> **Status:** Approved V1 API conventions; implementation not started.  
> **Canonical location:** `docs/02-standards/api-standards.md`  
> **Scope:** Laravel 12 JSON API for one Kids Area location, consumed by a frontend to be designed later.  
> **Related sources:** [Project Overview](../00-overview/project-overview.md), [Backend Architecture](../01-architecture/backend-architecture.md), [Code Standards](code-standards.md), and the relevant feature specification.

## 1. Purpose, Scope & Approved Decisions

This document defines the **external HTTP contract** shared by the Identity, People, Employees, Workforce, Payroll, Activities, Sessions, Cafe, POS, Finance, and Reports modules: routes, public names, request validation, response envelopes, pagination, filters, select lookups, authentication boundaries, idempotency, file transport, and API contract tests.

The backend is a **Laravel 12 API-first hybrid modular monolith** using `nwidart/laravel-modules` and **one MySQL 8 / InnoDB database**. This standard does not specify detailed table schemas, payroll calculations, timer charging algorithms, subscription edge cases, or individual endpoint inventories; their owning feature specifications do.

| Topic | Approved V1 rule |
|---|---|
| URL base | `/api/v1` |
| Resource paths | Plural, descriptive, `kebab-case` |
| Public API names | **`camelCase`** for JSON request/response keys, public filters, sorts and query parameters |
| Database names | `snake_case`, mapped explicitly at the HTTP boundary |
| Success envelope | `success`, `message`, `data` |
| Error envelope | `success`, `message`, `errors` |
| Create/update output | **`data: []` by default** when the client does not need a returned object |
| Successful GET/login/logout message | Always `""` (except direct-file GET responses, which have no JSON envelope) |
| Pagination | `data.items` + `data.pagination`; default 15, **effective maximum 100** |
| Oversized page request | Valid integer `perPage` above 100 is **clamped to 100**, not rejected |
| Filter/search/sort/include | `spatie/laravel-query-builder` with endpoint-specific allowlists |
| Shared dropdowns | `GET /api/v1/selects?selects=...`, a `data` **array of {label, options}** |
| Select authorization | **All-or-nothing**: unauthorized key => 403; unknown key => 422 |
| Authentication | Laravel Sanctum; **session/cookie vs. Bearer token deferred** until frontend deployment is specified |
| Authorization | Spatie Laravel Permission + Policies/Gates on the backend |
| Retry protection | `Idempotency-Key` **required for financial/sensitive replay-risk commands**, not every POST |
| Money | Exact decimal **strings**, EGP only |
| Fixed statuses | Integer-backed PHP enums; localized Arabic labels |
| Durations | Nonnegative **integer seconds** |
| Date and timestamp | `YYYY-MM-DD` for dates; ISO 8601 with offset/UTC for instants |
| Exports | Direct synchronous binary Excel/PDF response; JSON error on failure |
| Uploads | `multipart/form-data`, Laravel Filesystem, **private by default** |
| Tests | PHPUnit; MySQL 8 for real constraint/concurrency behavior |

**Source priority:** These are the specifically approved Kids Area API decisions. Do not copy Tabaa/printing-specific orders, production stages, Laravel 13 choices, or its alternative Select Registry shape into this API. Where older illustrative architecture samples use `snake_case` JSON or textual fixed statuses, **the subsequently approved `camelCase` and integer-enum API conventions govern implementation**. Update affected documentation examples through a separately reviewed documentation change; do not silently change their business meaning.

## 2. Routes, HTTP Methods & Module Registration

- Every application endpoint belongs under **`/api/v1`**; use `/api/v2` only for a separately approved breaking contract.
- Use plural resource-oriented path segments in **`kebab-case`**: `/children`, `/activity-groups`, `/cashier-shifts`.
- Module routes live in their own `routes/api.php`, registered by the installed modules package/Laravel setup. Apply the global version prefix **once**.
- Use `GET` for reads, `POST` for creates or explicit domain commands, `PATCH` for partial changes, `PUT` only for intentional full replacement, and `DELETE` only when the business allows deletion.
- **Never mutate state via GET**, including a GET endpoint used to display a running timer.
- Use an explicit **POST command endpoint** for actions whose meaning is not ordinary CRUD (pause/resume/finish timer, close session, refund, renew subscription, reconcile shift).
- Route parameters identify records; authorization and business checks are required even when an ID is valid.
- Thin controllers use Form Requests and Resources, delegate **all writes to Actions**, and may perform simple authorized read-only queries under Code Standards.

**Illustrative routes — not a committed feature endpoint inventory:**

```http
GET    /api/v1/children
POST   /api/v1/children
GET    /api/v1/children/{child}
PATCH  /api/v1/children/{child}

GET    /api/v1/activities
GET    /api/v1/subscriptions
POST   /api/v1/subscriptions
POST   /api/v1/subscriptions/{subscription}/renew

POST   /api/v1/sessions
POST   /api/v1/sessions/{session}/timers/{timer}/pause
POST   /api/v1/sessions/{session}/timers/{timer}/resume
POST   /api/v1/sessions/{session}/close

POST   /api/v1/invoices/{invoice}/refund
GET    /api/v1/selects?selects=activities,groups,trainers
GET    /api/v1/reports/finance/export?format=xlsx
```

Endpoint names, supported transitions, permissions, and payloads must be approved by each owning feature specification. Do not invent a separate `/admin`, `/tenant`, `/platform`, or customer self-service API as part of the standard.

## 3. Public Naming, Field Types & Serialization

**Public requests/responses/query parameters:** `camelCase`, consistently.  
**MySQL tables/columns:** `snake_case`.  
**PHP:** use ordinary PHP/Laravel conventions as defined in [Code Standards](code-standards.md).

Example external request:

```json
{
  "childId": 8,
  "groupId": 3,
  "startDate": "2026-11-11",
  "receivedAmount": "500.00"
}
```

Internal storage fields may be `child_id`, `group_id`, `start_date`, `received_amount`. Form Requests validate the **public names**; Actions receive validated, explicitly mapped inputs; Resources expose the **public names**. Never return an Eloquent model's raw attributes as the public API contract.

- Use JSON booleans (`true` / `false`) for flags, not integer boolean substitutes.
- Use numeric JSON identifiers for ordinary record IDs unless an owning feature specifies a different stable identifier.
- Use integer-backed enum **codes** for fixed business statuses; do not accept a translated status label as an authoritative write input.
- Use decimal **strings** for monetary amounts, not JSON floating-point numbers.
- Dates, timestamps and durations follow section 9.
- Return only documented, authorized Resource fields; eager-load appropriate relations and avoid leaking salary, child/guardian or other private attributes.
- Validation error keys use the same `camelCase` field names submitted by clients.
- Do not mix `camelCase` and `snake_case` fields in a single public payload, including nested objects.
- Do not serialize internal pivot fields, secret keys, application model internals or relationship trees by accident.

## 4. Unified API Response & Status Codes

Use a single small **`App\Support\ApiResponse`** helper for the outer JSON envelope, defined centrally in the application Core. Do not recreate response wrappers in each module. Central exception handling must use the same convention.

### 4.1 Successful read

```http
GET /api/v1/activities/3
```

```json
{
  "success": true,
  "message": "",
  "data": {
    "id": 3,
    "name": "سباحة",
    "isActive": true
  }
}
```

**Every successful JSON GET response** has `"message": ""`, including detail, list and shared select endpoints. This does **not** apply to successful direct-file download responses, which return file bytes rather than JSON.

### 4.2 Successful creation — minimal by default

```http
HTTP/1.1 201 Created
Content-Type: application/json
```

```json
{
  "success": true,
  "message": "تم إنشاء الاشتراك بنجاح.",
  "data": []
}
```

- For `POST` creates and ordinary `PATCH`/`PUT` mutations **return `data: []` by default** if the client does not need the created/updated representation.
- If the next workflow genuinely needs a new identifier or other fields immediately (e.g., session ID needed to start timers), return **only the minimal documented data**. Define the exception in that feature's API contract.
- Do not return the entire saved model just because Laravel can serialize it.
- Standard non-empty mutation success messages are in **Arabic** and use translation keys. A mutation that does not need text may use `"message": ""` where the feature contract allows.
- The outer response always has **exactly one** `data` key; never create unintended `data.data` or nested success envelopes.
- `200 OK` is appropriate for successful updates/commands with JSON; `201 Created` for a newly created resource.
- `204 No Content` is permitted only with **no JSON body**. Prefer `200` and the standard envelope when a result/message is useful.

### 4.3 Successful login / logout

**Both successful login and successful logout** must return `"message": ""`. The data can be `[]` when nothing is required, or an explicitly documented minimal authenticated user Resource when needed by the client.

```json
{
  "success": true,
  "message": "",
  "data": []
}
```

This rule does not require prematurely choosing Cookie/Session or Bearer token transport.

### 4.4 Standard validation error

```http
HTTP/1.1 422 Unprocessable Entity
Content-Type: application/json
```

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

### 4.5 Standard business error

```http
HTTP/1.1 409 Conflict
Content-Type: application/json
```

```json
{
  "success": false,
  "message": "لا يمكن تسجيل الطفل لأن المجموعة مكتملة.",
  "errors": {
    "code": "GROUP_CAPACITY_REACHED"
  }
}
```

**Error rules:**

- All JSON failures contain `success: false`, a useful safe Arabic `message`, and `errors`; do **not** include `data` in an error response.
- `errors` may be `[]` or an object; validation errors are an object of `camelCase` field names mapped to arrays of Arabic messages.
- `errors.code` is an **optional** stable, technical `UPPER_SNAKE_CASE` business error identifier (e.g., `GROUP_CAPACITY_REACHED`, `IDEMPOTENCY_CONFLICT`). **No top-level `code` field** is introduced.
- Missing permissions, invalid states, and unavailable records must produce predictable, non-HTML API errors; never leak SQL, stack traces, environment settings, or secrets.
- Domain Actions **throw** domain/business exceptions; the global Laravel exception handler formats them. Controllers should not repeat per-exception `try/catch` JSON formatting.

### 4.6 HTTP status mapping

| Situation | Status |
|---|---|
| Successful read/update/domain command with body | `200` |
| Resource created | `201` |
| Empty HTTP response, no JSON body | `204` |
| Malformed request outside normal field validation | `400` |
| Not authenticated | `401` |
| Authenticated but lacks permission | `403` |
| Resource missing or not exposed under documented visibility rules | `404` |
| Valid command conflicts with current state; conflicting idempotency payload | `409` |
| Invalid input/validation; unknown select key | `422` |
| Request rate-limited | `429` |
| Unexpected server failure | `500` |

If browser session/CSRF transport is later selected, its session-expiry/CSRF error mapping (including Laravel's usual `419` when applicable) must be decided and centrally implemented in that auth-specific plan. Avoid adding session-specific assumptions now.

## 5. Form Requests, Actions, Resources & Exception Boundaries

- A **Form Request** owns payload validation and input normalization for create/update, transitions, payments, refunds, cash reconciliation, and other mutations. It checks structure, types, ranges and simple input-level constraints.
- **Policies/Gates/permission middleware** determine whether the signed-in operator may perform the action or read that particular resource.
- An **Action** enforces state-dependent business rules and handles every write. For instance, group capacity at enrollment, timer state before pause, and refundable amount before refund are checked **at execution time** with the needed transaction/locking.
- **Resources** construct explicit external representations and `camelCase` output. Use a special list Resource only when list/detail differences warrant it; no compulsory one-collection-class-per-model rule.
- Simple GET read-only Eloquent/Query Builder queries may remain in a Controller; extract complex/repeated reads when needed. All reads still check permissions and visibility.
- The global exception handler in Laravel `bootstrap/app.php` maps validation, authentication, authorization, missing resources, expected conflicts, and unexpected failures to the unified JSON error contract.
- **Never** trust frontend amounts, session durations, role/permission assertions, derived subscription balances, or other authoritative server facts.

**Illustrative controller write response:**

```php
public function store(
    StoreChildRequest $request,
    CreateChildAction $action,
): JsonResponse {
    $action->execute($request->validated());

    return ApiResponse::success(
        data: [],
        message: __('children.created'),
        status: HttpStatusCode::CREATED,
    );
}
```

This example illustrates the approved response **shape**, not a mandate to introduce a status-code enum or specific class signature before the application Core is implemented. A minimal shared helper can use integer HTTP statuses or a small shared enum **as decided in implementation**; do not create multiple response abstractions.

## 6. Pagination

All ordinary table/list endpoints should paginate, unless a feature explicitly defines a bounded unpaginated read-only lookup.

Request:

```http
GET /api/v1/children?page=2&perPage=15
```

**Approved behavior:**

- `page` is a positive integer, default `1`. Zero, negative, nonnumeric or malformed values are invalid (`422`).
- `perPage` is a positive integer, default **15**.
- A valid integer `perPage > 100` is **silently clamped to 100**. This is an explicit project decision, **not** a validation error.
- For invalid `perPage` values (zero, negative, nonnumeric, malformed), return the normal `422` validation response. Do not confuse these with valid oversized values.
- Return the **effective** page size in `data.pagination.perPage`. For example, `?perPage=200` uses and reports `100`.
- Results always appear in `data.items`; metadata always appears in `data.pagination`, both inside the shared `data` response object.
- The pagination keys are `total`, `currentPage`, `perPage`, and `lastPage`. Other metadata requires an explicit contract change.
- Keep a stable ordering; use newest-first for chronological lists where appropriate and include an ID tie-breaker when order stability matters.
- Do not leak raw default Laravel paginator `links`/`meta` as additional public wrappers. Pagination shape is applied once.
- Select Registry option sets are intentionally **small, bounded, and unpaginated**; large search/list operations use regular paginated endpoints.

```json
{
  "success": true,
  "message": "",
  "data": {
    "items": [
      {
        "id": 15,
        "name": "أحمد محمد",
        "isActive": true
      }
    ],
    "pagination": {
      "total": 68,
      "currentPage": 2,
      "perPage": 15,
      "lastPage": 5
    }
  }
}
```

Empty list results return `items: []`, a valid pagination object, and `"message": ""`.

**Example request with clamping:**

```http
GET /api/v1/children?page=1&perPage=200
# Server executes perPage=100 and reports "perPage": 100.
```

Pagination normalization must happen **before** passing `perPage` to a database query. Never fetch 200 records and then trim them in PHP.

## 7. Filtering, Sorting, Search & Includes

Use **`spatie/laravel-query-builder`** for list filtering, sorting and approved relationship includes. Before installing, check the package version officially compatible with **Laravel 12** and the installed PHP version; do not blindly copy Laravel 13/Tabaa package signatures.

```http
GET /api/v1/subscriptions?filter[activityId]=3&filter[status]=1
GET /api/v1/children?filter[name]=أحمد
GET /api/v1/sessions?sort=-createdAt&page=1&perPage=15
GET /api/v1/subscriptions?filter[status]=1&sort=-createdAt
```

Rules:

1. Every index/list endpoint has **explicit allowlists** for `allowedFilters`, `allowedSorts`, and `allowedIncludes` where includes are supported. Do not accept arbitrary column names or SQL fragments.
2. Public aliases are **`camelCase`** (e.g., `activityId`, `createdAt`) and map explicitly to database `snake_case` (e.g., `activity_id`, `created_at`).
3. Use exact filters for IDs, enum statuses and other enumerated fields; use deliberate bounded partial text search only on approved names/phone-like fields when appropriate.
4. `sort=-createdAt` means descending; `sort=createdAt` means ascending. The default may be descending creation date for time-ordered lists, but an owner's feature contract may specify another order.
5. Relationship includes are **opt-in and allowlisted**; requesting private or expensive relationships is never implicitly authorized just because a relationship exists.
6. Unsupported filters, sorts, and includes must fail through the project error contract. Map package exceptions safely (typically a client-validation error); do not expose internal table names or package traces.
7. Authorization and record visibility restrictions **cannot be bypassed** through filters or includes.
8. Sparse-field selection, unbounded free-text search and external search infrastructure are **not required** in V1.
9. The feature spec identifies approved fields, sorting behavior, and any relationship expansion for each endpoint.

Package configuration must be shared where helpful without creating an unnecessary generic repository/query abstraction for every CRUD resource.

## 8. Central Select Registry — Shared Dropdown Contract

The approved endpoint is:

```http
GET /api/v1/selects?selects=activities,groups,trainers
```

This is **one read-only Core registry/aggregator**, not a separate business module. Each business module owns its own bounded Select Provider, data visibility, eligibility logic, authorization, and option labels.

### Request validation and dispatch

- The `selects` query parameter is required and contains a comma-separated list of **registered, allowlisted keys**. Do not accept client-provided class names, methods, arbitrary SQL, or unrestricted lookup parameters.
- Apply a reasonable configured limit to the number of keys and result sizes. The exact bound may be finalized in the Select Registry foundation feature; do not invent business-wide unbounded selects.
- Reject unsupported/unknown keys with **`422`** and an Arabic validation message.
- **Authorize each requested key** for the signed-in operator. If any requested select is forbidden, reject the **entire request with `403`**, returning **no partial results**.
- Authentication failure returns `401`; invalid syntax/keys return `422`; missing permissions for otherwise valid requested keys return `403`.
- A clear evaluation order is **authenticate → validate keys/parameters → authorize all requested keys → execute providers**. No provider should release protected options while evaluating a mixed-access request.
- Only fetch **requested** keys and only eligible option rows. Unknown keys must not be silently ignored; unauthorized keys must not be silently omitted.
- No pagination wrapper for these deliberately bounded lists. If a dataset could be large (children, guardians, customers, transactions), expose a permission-controlled paginated/search endpoint instead.

### Approved success payload

```json
{
  "success": true,
  "message": "",
  "data": [
    {
      "label": "activities",
      "options": [
        { "value": 1, "label": "سباحة" },
        { "value": 2, "label": "رسم" }
      ]
    },
    {
      "label": "groups",
      "options": [
        { "value": 10, "label": "السبت والاثنين - 4 مساءً" }
      ]
    },
    {
      "label": "trainers",
      "options": [
        { "value": 5, "label": "أحمد محمد" }
      ]
    }
  ]
}
```

**Contract details:**

- `data` is an **array**, in requested-key order, of objects with `label` (the stable English **select key**) and `options` (an array of `{value, label}` objects).
- `options[].label` is the user-visible **Arabic** option name. `value` is a numeric record ID or an approved stable technical string (for example, a permission name); the owning provider/feature spec defines which type is used.
- Empty eligible sets return `"options": []` for the requested key; they do not silently disappear.
- Successful lookup responses always return `"message": ""`.
- Provider read permissions apply even when the caller can open a general create/edit form. The registry must not become an authorization bypass.
- The reference printing project's *other* `data`-as-object lookup example is **not** the Kids Area contract. Keep the array-of-`label/options` structure approved in Kids Area Backend Architecture.

## 9. Money, Currency, Statuses, Dates & Durations

### 9.1 Money — exact values

- **EGP is the only currency** in V1; do not add currencies, conversions, or payment gateway semantics to this API standard.
- Serialize monetary amounts as exact **decimal strings**, e.g. `"350.00"` or `"1200.50"`, never JSON floating-point numbers for authoritative values.
- Validate external decimal input and perform authoritative money arithmetic on the backend using exact decimal-safe methods; never trust a frontend total, refundable balance or payroll payout as final.
- Return the actual approved final price, charge, collection, expense or refund from its owning module. A calculated displayed total must not supersede the **Finance canonical movements**.
- The exact MySQL decimal precision, rounding implementation and amount bounds belong to [Database Standards](database-standards.md) and the relevant finance/timer feature specification.

```json
{
  "totalAmount": "350.00",
  "receivedAmount": "350.00",
  "currency": "EGP"
}
```

`currency` is **illustrative only**, not a mandatory field on every response. The system's fixed currency can be documented once in settings/reference.

### 9.2 Fixed statuses and flags

- Fixed workflow statuses are **integer-backed PHP enums** with stable published numeric meanings.
- When a human-readable label is needed, a Resource may expose the numeric value plus Arabic `statusLabel`.
- Boolean active/disabled flags are real JSON booleans (`isActive: true`), not enums.
- User-managed activities, jobs and catalogues are relational records and **not** fixed enum sets.
- Actual enum values and transitions are determined by the owning module's approved feature spec, **not by illustrative examples**.

### 9.3 Dates and timestamps

| Value | API format | Meaning |
|---|---|---|
| Date-only value | `YYYY-MM-DD` | A calendar date, such as a subscription start date |
| Event timestamp | ISO 8601 with UTC `Z` or an explicit offset | An unambiguous instant, such as timer start, payment time or audit time |
| Stored server event timestamp | UTC | Consistent backend persistence |
| Local business schedule/calendar | Explicit Egypt local-time semantics when relevant | Days/classes/monthly subscription boundaries must not shift because UTC differs |

Do not return formatted Arabic dates as authoritative date fields; UI/export presentation can format them separately.

### 9.4 Duration — integer seconds

Every API **duration** is represented as **nonnegative integer seconds** unless a feature explicitly documents a distinct physical unit for a different concept. Timer elapsed time, paused time and calculated billable time are server-authoritative.

```json
{
  "timerId": 15,
  "elapsedSeconds": 4200,
  "hourlyRate": "60.00",
  "totalAmount": "70.00"
}
```

The example illustrates serialization only; final pricing and rounding/segment rules are defined in Sessions specs. The frontend may display `4200` as `01:10:00` but must not supply its own elapsed clock reading as the authoritative duration. Live UI timers are derived from server state and timestamps rather than continuous database updates.

## 10. Laravel Sanctum Authentication — Transport Deferred

**Approved:** Laravel Sanctum is the authentication foundation. **Not yet approved:** whether the future frontend uses Sanctum stateful Cookie/Session authentication or an applicable Bearer-token strategy.

Until frontend location and deployment requirements are known:

- Protected staff endpoints use `auth:sanctum` and the normal backend role/permission/Policy checks.
- Every operator has an **individual login**; shared reception/cashier accounts are forbidden.
- Account deactivation and relevant permission changes must prevent unauthorized **subsequent requests**; the Identity/Permissions specification determines the precise implementation and session/token invalidation behavior.
- Successful login and logout use the approved JSON envelope with `"message": ""`.
- Unauthorized API access returns **`401` JSON**, not a browser HTML login redirect; authenticated but forbidden access returns `403`.
- Rate-limit login and other sensitive endpoints, hash passwords, and avoid revealing account existence unnecessarily through error messages.
- Use HTTPS in production; never return or log passwords, authentication cookies, bearer tokens or secrets.
- Cookie/Session-specific CSRF and credentialed CORS rules, secure cookie attributes, allowed frontend origins, and/or Bearer token lifecycle/rotation/storage rules are decided in the future **auth transport implementation spec**.
- Do not claim either transport is already selected. In particular, **do not force browser Bearer tokens or stateful SPA cookies** solely by copying a reference project.

Sanctum configuration, guard mapping, login user Resource, role assignment, account blocking and logout semantics must be verified against the actual Laravel 12 implementation when the Identity/Auth feature is developed.

## 11. Idempotency for Financial & Sensitive Commands

**`Idempotency-Key` is mandatory only for operations where retries could duplicate money or another critical irreversible effect.** It is **not** a blanket requirement for every POST or ordinary CRUD update.

At minimum, include the header for approved command endpoints that:

- Close a customer session **with full collection**.
- Record a subscription installment/payment or other new receipt.
- Create a standalone café sale that collects money.
- Execute full/partial refunds or other financial corrections.
- Post a payroll payout.
- Record a cashier/operational financial expense.
- Create a subscription **with its associated financial obligation/posting**, where retries could duplicate that obligation.

Add another sensitive replay-risk command to its feature spec where needed, but do not impose needless stateful idempotency storage on simple `CreateChild` or `UpdateActivity` operations by default.

```http
POST /api/v1/sessions/125/close
Idempotency-Key: 8b0f2f2a-9cb1-4ba2-8f0b-1e22f4f27611
Content-Type: application/json
```

### Required behavior

1. The client generates a **unique key for one logical new operation** (prefer a UUID). Retry the same operation with the **same key** if the response was lost.
2. Scope key uniqueness to an appropriate **actor + operation + domain context**; exact scope is fixed in the owning financial/feature spec.
3. Persist a suitable **request fingerprint, operation identity and replayable outcome**. Enforce the scope with a **MySQL unique constraint** and transactions/locking, not a check-then-insert race.
4. **Same key + equivalent request** must not post new invoice/payment/refund/payroll/expense/movement records; return the previous result or an equivalent documented safe success.
5. **Same key + materially different payload** must return **`409 Conflict`** without any additional financial effect. Optionally report `errors.code = "IDEMPOTENCY_CONFLICT"`.
6. A missing or malformed required key must produce a **422** validation error, with an Arabic message and the unified error envelope.
7. Prevent concurrent submissions from both creating records. An in-flight duplicate may have a documented transient response, but **must never** result in a second posting.
8. Coordinate original posting, its canonical Finance movement, and the idempotency outcome within one coherent transaction. Rollback must not leave partial money movements.
9. Keep original posted financial facts immutable; corrections are traceable **new** movements.
10. Retention duration, recovery after uncertain outcomes, exact fingerprint normalization and duplicate-in-flight responses are specified in the feature design; **no global queue/event platform** is introduced.

The backend must always perform authentication, authorization and current-state checks independently of the presence of a valid idempotency key.

## 12. File Uploads & Direct Excel/PDF Exports

### 12.1 Uploads — private by default

**No attachment feature is created merely by this standard.** If an approved feature later needs a child photo, employee document, or other attachment:

- Accept uploads via **`multipart/form-data`**; do not require base64-encoded file bytes inside JSON.
- Use **Laravel Filesystem**, private storage by default, and metadata/ownership references in MySQL where appropriate.
- Validate file size, real MIME/content type, allowed extensions/types and upload constraints. The feature spec owns the explicit allowlist and maximum size.
- Generate safe storage filenames/paths; do not trust user-provided paths or names or expose internal file paths as public URLs.
- Download/access files only through an endpoint or delivery mechanism that enforces **record-level authorization**.
- Publicly accessible storage is an **explicit feature-level exception** requiring review; never make child/guardian/staff files public as a default.
- Avoid long file processing during financial transactions. Define cleanup/consistency handling when a file write and DB write cannot be atomic.

### 12.2 Exports — direct file response

Approved Excel (`.xlsx`) and PDF exports are generated **synchronously** and return the file directly, **not** JSON containing a download URL:

```http
GET /api/v1/reports/finance/export?format=xlsx
```

Successful export **file response** (example):

```http
HTTP/1.1 200 OK
Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet
Content-Disposition: attachment; filename="finance-report.xlsx"
```

For PDF use `application/pdf`. Binary/streaming success is a deliberate exception to the JSON success envelope (including its normally empty GET `message`). **Error responses remain the normal JSON envelope**, such as `401`, `403` or `422`.

- Authenticate and authorize the exact report/export scope; a user who cannot read payroll records cannot export them either.
- Validate `format`, date range, requested columns/filters and other explicit report parameters.
- Bound report sizes/date ranges, memory usage and execution time to fit shared hosting/cPanel.
- Prefer streaming/chunked reads where supported by the chosen library; the library and numerical limits are specified in the Reports feature.
- Do not store generated sensitive files under a public path or return an unprotected URL.
- Do not require queue workers, scheduled cleanup, Redis, external conversion services or background Jobs to produce a V1 export.
- If an export cannot be produced within the approved limits, fail **before** streaming irreversible output where possible and return a meaningful Arabic JSON error. Avoid corrupt binary/JSON hybrid responses.

## 13. API Security & Privacy

- Authorization applies to **GET**, POST, updates, select lookups, exports and private file downloads; frontend element visibility is not an authorization mechanism.
- Use `auth:sanctum`, permission middleware and Policies/Gates as appropriate. The exact role catalog and active-account rules belong to Permissions Standards.
- Restrict access to child/guardian profiles, phone numbers, staff contracts, payroll and payment/cashier data based on actual permissions.
- Use Form Requests; guard mass assignment; do not construct unchecked SQL from `filter`/`sort`/`include` query parameters.
- Avoid insecure direct object reference (IDOR): verify access to the **particular** requested child, guardian, session, invoice, employee or report.
- Protect authentication via HTTPS and rate limits; add other endpoint-specific throttling only where justified.
- Do not return raw application error traces or sensitive DB details in `500` responses. Production failures use a safe Arabic message; unexpected errors go to restricted server logs.
- Important role changes, refunds, cash corrections and other approved sensitive actions are written to a **selective Audit Log** by their owning Actions/Core, not as an exception response side effect.
- No API response should expose password hashes, token secrets, unneeded personal details or payroll amounts to unauthorized roles.
- CORS/CSRF/session-cookie rules follow the **future Sanctum transport decision**, not a premature assumption.
- There is no tenant isolation layer or public self-service/customer API in V1.

## 14. PHPUnit API Contract Tests & Definition of Done

Use **PHPUnit Feature tests** for the HTTP contract and domain-integrity tests in the owning module for business effects. Critical financial/concurrency tests must execute against **MySQL 8**, not SQLite substitutes.

| Contract or feature | Minimum verification |
|---|---|
| JSON success | Exact `success/message/data` keys; no accidental nested envelope |
| JSON errors | Exact `success/message/errors` keys; no top-level `code` or error `data` |
| GET/login/logout | Successful `"message": ""` |
| Create/update | `201` on new resource; `data: []` unless minimal result explicitly required |
| Naming | `camelCase` public output and Form Request validation keys, including nested keys |
| Access | `401` unauthenticated; `403` forbidden; authorized/unauthorized individual records and reports |
| Business conflicts | `409` for invalid current states, correct safe Arabic message and optional `errors.code` |
| Pagination | Default 15; max 100; **`perPage=200` clamps to 100** in both query and metadata; invalid/zero/negative values => 422 |
| Query Builder | Allowed filters/sorts/includes work, unsupported ones fail safely, no forbidden relation leakage |
| Select Registry | Array-of-`label/options` response; Arabic labels; requested keys only; unknown key 422; any unauthorized key 403 **with no partial output** |
| Money | Exact string amounts and server-side authoritative totals |
| Enums/durations/time | Integer status, Arabic labels where required, seconds as integer, date/time formats |
| Idempotency | Missing required key 422; same key+payload one effect; same key+different payload 409; concurrent duplicate no double posting |
| Exports | Authorized Excel/PDF returns direct file bytes and proper headers; denied/invalid export returns JSON |
| Uploads | Approved allowed MIME/size, private storage, unauthorized download blocked |
| Session/auth | Login rate limits, invalid sessions/credentials safe, no HTML API redirects |
| Error hygiene | Generic 500, no SQL/stack traces/tokens/private values |

**Feature definition of done:**

1. The approved feature spec names exact endpoints, payloads, permissions, business effects, status mappings, and necessary API response exceptions.
2. Controllers follow [Code Standards](code-standards.md): HTTP-only writes through Actions; simple authorized reads may remain in controllers.
3. Public fields, Resources, pagination, lookups and error formatting obey this file.
4. Financial/time-state effects, retries, database constraints and necessary concurrency safeguards behave correctly.
5. Applicable PHPUnit/Feature/Integration checks **actually run**; Laravel Pint check is green.
6. GitHub Actions for PRs to `develop` enforce required tests and formatting when configured; **do not claim an unconfigured CI or branch ruleset already exists**.
7. Changes to cross-module conventions are reflected in the relevant standards; feature-specific details stay in the feature spec.

## 15. Explicit Non-Goals, Document Ownership & Change Rule

**Do not introduce solely because of this standard:**

- Laravel Passport/OAuth, mandatory JWT, premature SPA Bearer-token storage or a fixed Cookie/Session scheme.
- Multi-tenancy, tenant route prefixes, extra databases, multi-branch routing or public checkout.
- A generic API gateway, GraphQL, hypermedia layer, generic repository/DTO interface per endpoint, or mandatory ResourceCollection subclasses.
- External full-text search infrastructure for ordinary activity/customer/child filters.
- A new upload management platform, a background export queue, temporary public download URLs by default, cron cleanup or Redis.
- Arbitrary user-selected locales, translation tables or localized JSON property names.
- Complex payment allocation, gateway webhook processing, general event sourcing or bookkeeping engines that the Kids Area features have not approved.

### Source-of-truth boundaries

| Document | Owns |
|---|---|
| `docs/00-overview/project-overview.md` | Business requirements, modules, journeys, V1 scope |
| `docs/01-architecture/backend-architecture.md` | System-wide architecture, module ownership, runtime and hosting constraints |
| `docs/02-standards/code-standards.md` | Action/Service/controller responsibilities and PHP implementation style |
| **This API Standards file** | Routes, public names, HTTP methods, JSON/binary transport, pagination, select/filters, API security and contract tests |
| `docs/02-standards/database-standards.md` | MySQL schema/migration rules, exact money precision, indexing/foreign keys and persistence integrity |
| `docs/02-standards/error-handling-standards.md` | Central exception mapping, error codes/logging and internal error hygiene |
| `docs/02-standards/permissions-standards.md` | Staff roles, guard configuration, permission definitions, audit of authorization changes |
| `docs/02-standards/localization-standards.md` | Arabic translation keys, fixed-label localization and future locales |
| `docs/02-standards/ai-workflow-rules.md` | Codex, Spec Kit, implementation workflow and verification reporting |
| `specs/<feature-number>-<slug>/` | Approved feature-level API payloads, decisions, plan, acceptance criteria and tasks |

**Change rule:** Update this file **only when the common cross-module HTTP/API contract changes**. An individual endpoint, price, permission or database table belongs in the owning feature spec unless it changes an approved shared convention.

> **Implementation status:** This document records the **approved API rules**. It does **not** assert that routes, API helpers, Sanctum transport, package installation, tests, CI enforcement, Excel/PDF exports or upload features are already implemented.