# Activities Feature 01 — Activity Catalogue Management

> **Status:** Approved F01-D01–D06 and TP01–TP13, documentation PR #14 merged. Activity Catalogue implementation verified for its standalone scope: exact `cde1c7f8673d564dfac66fbc3816854306ed397c`, CI 45909789792, 249/20062, all 15 implemented-scope ACs; [tasks.md](tasks.md) records actual evidence (T01–T40). PR #15 final documentation-head CI/review and explicit merge authorization remain pending. No production migration/grants/deployment or future dependency verification.
> **Baseline:** develop `16eb572c8c64d341bfb614e7ec16d79c45050ba6`, merged Activities module-reference [PR #13](https://github.com/m-hassan00096/kids-area-api/pull/13). Exact reviewed documentation head 854c82da853a043a2ebce20df1d12a830bcbb31d, [CI 45863711197](https://github.com/m-hassan00096/kids-area-api/actions/runs/45863711197), 206 existing tests / 16626 assertions, Pint 184 files, 19 existing routes. Merged tree verified identical. This is baseline evidence, not Activities feature test evidence.
> **Sources:** [Activities module reference](../../../docs/03-modules/activities.md) (especially §§2, 9–13 and decisions D01–D05, D67, D75–D78, D92), [Project Overview](../../../docs/00-overview/project-overview.md), [Backend Architecture](../../../docs/01-architecture/backend-architecture.md), [Identity](../../../docs/03-modules/identity.md), [API](../../../docs/02-standards/api-standards.md), [Database](../../../docs/02-standards/database-standards.md), [Permissions](../../../docs/02-standards/permissions-standards.md), [Errors](../../../docs/02-standards/error-handling-standards.md), [Localization](../../../docs/02-standards/localization-standards.md), [Code](../../../docs/02-standards/code-standards.md), [Workflow](../../../docs/02-standards/ai-workflow-rules.md).
> **Owner:** Activities; necessary limited integration with Identity/Core. This is a feature specification, not a specification or approval to build all of Activities.

## 1. Goal and scope

Provide a simple activity catalogue for the manager to configure available games, lessons, entertainment and nursery services and their current hourly and/or monthly unit prices; authorized reception staff can read them for later operational workflows.

In scope: five independent protected CRUD capabilities/routes, catalogue records and prices, activation/deactivation via full PUT, name search, active-state and billing-mode filters, approved sorting and pagination, two bounded providers on the existing Select Registry, necessary central permission catalogue integration, initial permission provisioning and regression verification.

Out of scope: groups/schedules, trainers, children/guardians, subscriptions and their duration/price-confirmation commands, classes, rolling generation, attendance, make-ups, hourly timers, POS/Finance, invoices, collections/refunds, payroll, shared Audit (deferred), frontend, multibranch, new infrastructure, extra generic abstractions, speculative relations/tables or future permission keys. No package installation assumed; use installed compatible packages. No activity-specific type/category/image/description, group-level pricing, persisted billing_mode, created_by/updated_by or soft-delete fields. Do not create stub modules or endpoints for deferred business flows.

## 2. Approved decisions

The module-reference decisions still apply; the user explicitly approved the following feature-level refinements.

| Decision | Approved behavior |
|---|---|
| F01-D01 | Name uniqueness is case-insensitive for English letter case, even among inactive records. Trim edges; preserve entered name otherwise. Swimming and swimming conflict. Retain utf8mb4_0900_as_ci based on measured MySQL 8.0.46: user explicitly approved tatweel (الرسم/الـرسم) and internal ASCII-space/NBSP variants as duplicates. Preserve trimmed display name; canonical accent forms equivalent, distinct accents and Arabic vowel marks remain distinct. Native escaped literal LIKE may differ from unique equality. See plan and measured tasks; revised matrix passed on CI 45909789792 (see tasks.md). |
| F01-D02 | Allow sorting by id or name, ascending/descending; default id DESC. No arbitrary column sort. |
| F01-D03 | Both activities and activeActivities Select keys require activities.index; option order name ASC. |
| F01-D04 | Existing shared BusinessException; duplicate name ACTIVITY_NAME_ALREADY_EXISTS, in-use delete ACTIVITY_IN_USE, selector overflow SELECT_OPTIONS_LIMIT, all 409; invalid input 422. |
| F01-D05 | Hard delete unused catalogue records now. Add real restrictive dependency protection in the feature introducing each group, subscription, timer or other reference; no fake dependent tables in this feature. |
| F01-D06 | Accept exact decimal-string prices such as "60", "60.5", "60.50"; normalize output to exactly two fractional digits, reject excess precision or storage range. |

## 3. Actors and scenarios

- Authorized manager creates an hourly-only service, subscription-only service or service supporting both. Nursery is an ordinary activity configured subscription-only, not a special code/ID/type. The monthly price is a unit price for future agreements of 1–12 whole calendar months, not a forced duration.
- Authorized staff list, filter and view catalogue entries, and use the shared selector. Inactive entries remain visible to authorized historical/list readers and the all-activities selector.
- Authorized manager changes the name, price, enabled services or active status using full replacement. Each enabled mode must have a positive price; disabled modes have null prices. Existing timer/contract snapshots and confirmed obligations are not rewritten (future owning features enforce those contracts).
- Authorized manager deletes an unused record. With any protected dependency, delete must fail safely; deactivation is the alternative. Catalogue has no such dependent module yet, so prove current standalone deletion and preserve the integration requirement, not a fabricated dependent-record test.
- Default Manager receives all five new permissions; Reception receives list and detail only. Custom roles may hold independent subsets; backend checks effective permission, not role name.

## 4. Logical data and business invariants

One new business table `activities`; exact migration, collation and constraints in the separately approved plan.

| Field | Logical type | Required / default |
|---|---|---|
| id | BIGINT UNSIGNED | primary generated identifier |
| name | VARCHAR(255) | required, nonblank, unique retained name |
| supports_hourly | BOOLEAN | required |
| supports_subscription | BOOLEAN | required |
| hourly_rate | DECIMAL(12,2) | nullable, NULL when mode disabled |
| monthly_price | DECIMAL(12,2) | nullable, NULL when mode disabled |
| is_active | BOOLEAN | required, default true on creation |
| created_at, updated_at | standard timestamps | Laravel conventions; not public Resource fields |

At least one mode is true, including inactive activities. Price is strictly positive when its mode is enabled; when disabled the field must be omitted or explicitly null, and persisted value must be NULL. An enabled mode requires a supplied valid price, not null or omission. Reject incompatible nonnull disabled price; do not silently discard submitted amount. On PUT disabling a mode with its price omitted/null clears its old price, not a partial update. No implicit reuse of price on re-enabling a mode.

Price unit EGP. Exact DECIMAL(12,2) positive range 0.01 through 9999999999.99. Input is a JSON string representing a plain decimal with zero, one or two fractional digits (e.g. "60", "60.5", "60.50", "0.01"). No JSON numeric/float, scientific notation, negative/zero, separators/currency suffix, more than two fractional digits or magnitude exceeding storage range. Do not round invalid precision to make it valid; return 422. Output always string with exactly two decimal places or null, no float conversion. TP05 approved: reject zero padding on integer part and surrounding price whitespace; exact grammar and verification in plan.

Name: valid UTF-8, nonblank after trimming leading/trailing whitespace, up to 255 characters after trim. Arabic or English names supported; preserve case and internal text. Reject duplicates on create or rename, including case-only variants and inactive rows, except same record may retain its name or change its displayed letter case when no other row conflicts. DB uniqueness is authoritative and application checks may improve errors but cannot replace it. Plan must verify actual MySQL equality behavior (including accent variants/Unicode/whitespace); do not silently commit unapproved accent-equivalence behavior.

ActivityBillingMode is integer-backed query enum HOURLY=0, SUBSCRIPTION=1, **not a DB column**. Both-mode activities match either filter. Independent Boolean flags are canonical.

Disable activity / service mode: block future admissions using that activity/mode once relevant dependent features exist. Confirmed subscriptions, existing timers, eligible continuing classes and old balances remain under original agreement; no automatic cancellation or refund. Rate changes apply prospectively only. No historical pricing ledger in this standalone feature; owning timer/subscription features persist exact snapshots when created. Delete only with no protected historical or current references. Future FKs and locking must protect activity versus dependent writes before those flows are enabled. No soft delete, cascading historical deletion, alternate status endpoint or automatic delete-on-disable.

## 5. HTTP contracts

All paths under `/api/v1`; Sanctum stateful browser Cookie/Session and CSRF, existing current staff context, backend permission checks, Arabic safe messages, camelCase public names and shared ApiResponse. Form Requests validate; Actions enforce business rules and writes; explicit Resources only. Existing transport and error conventions apply. No separate response wrapper.

| Method | Path | Capability | Success |
|---|---|---|---|
| GET | /activities | activities.index | 200, paginated items |
| GET | /activities/{id} | activities.show | 200, detail |
| POST | /activities | activities.store | 201, data: [] |
| PUT | /activities/{id} | activities.update | 200, data: [] |
| DELETE | /activities/{id} | activities.delete | 200, data: [] |

Each permission independent. Deny capability before protected target disclosure, no authorization by role-name shortcut, no super-admin bypass. Numeric record IDs; unknown target 404 once authorized. GET never mutates catalogue; no GET body or upload; POST/PUT use JSON object and approved keys only, DELETE requires no extra input. Use existing first-party transport's native parsing semantics; malformed JSON handled under shared 400 contract; invalid submitted fields 422. No arbitrary includes, sparse fields or unapproved query/body fields. Auth, CSRF and session behavior stays consistent with Identity.

### 5.1 Create and update

```json
{
  "name": "السباحة",
  "supportsHourly": true,
  "supportsSubscription": true,
  "hourlyRate": "100.00",
  "monthlyPrice": "800.00",
  "isActive": true
}
```

| Public field | POST | PUT |
|---|---|---|
| name | required | required |
| supportsHourly | required native JSON Boolean | required native JSON Boolean |
| supportsSubscription | required native JSON Boolean | required native JSON Boolean |
| hourlyRate | required positive decimal string if hourly=true; absent/null otherwise | same |
| monthlyPrice | required positive decimal string if subscription=true; absent/null otherwise | same |
| isActive | optional native JSON Boolean, defaults true | required native JSON Boolean |

Full PUT is deliberate, not partial PATCH. No client-provided id, timestamps, enum column, price history or derived data. Native true/false required, not "true", "false", 0, 1, null. Example hourly-only create: {"name":"الألعاب","supportsHourly":true,"supportsSubscription":false,"hourlyRate":"60"} -> active, hourlyRate "60.00", monthlyPrice null. Subscription-only create reverses the flags and supplies monthlyPrice. Both-mode create requires both prices. Example create response:
```json
{"success":true,"message":"تم إضافة النشاط بنجاح","data":[]}
```
Update/delete likewise return localized nonempty success message and data: [] (HTTP 200), no model serialization or nested data envelope.

### 5.2 List and detail

```http
GET /api/v1/activities?filter[search]=سباحة&filter[isActive]=1&filter[billingMode]=0&sort=-id&page=1&perPage=15
```

| Input | Contract |
|---|---|
| filter[search] | bounded name substring search; literal text, not client SQL/wildcard program |
| filter[isActive] | exact query code 0 inactive / 1 active, omitted all |
| filter[billingMode] | exact 0 hourly / 1 subscription, omitted all |
| sort | allowlisted id, -id, name, -name; default -id |
| page | positive integer, default 1 |
| perPage | positive integer, default 15; valid oversized clamped to 100 before DB query |

Filters combine by AND. Mode matches flag=true, independent of active state; do not accidentally filter out inactive unless requested. Filter value 0 must not disappear via truthiness or null/empty normalization. Name search uses bound/escaped literal partial matching and actual reviewed collation; Arabic search supported. For ordinary list semantics follow existing strict query shape, bounded valid text (up to catalogue name length) and empty search as no filter, stable ID tie-break when name order is equivalent; exact lexical/query bounds refined in plan. Unknown filters, unsupported sort/include, malformed scalars/arrays, invalid page/perPage and query fields fail safely as 422. No expensive unbounded joins or arbitrary include fields. Use existing Spatie Query Builder with explicit filter/sort/include allowlists. Keep out-of-range pages empty with correct metadata; avoid unsafe offset arithmetic.

```json
{
  "success": true,
  "message": "",
  "data": {
    "items": [
      {
        "id": 1,
        "name": "السباحة",
        "supportsHourly": true,
        "supportsSubscription": true,
        "hourlyRate": "100.00",
        "monthlyPrice": "800.00",
        "isActive": true
      }
    ],
    "pagination": {
      "total": 1,
      "currentPage": 1,
      "perPage": 15,
      "lastPage": 1
    }
  }
}
```
Detail uses the identical seven-field Activity Resource directly as data object. GET message always empty. No timestamps, count columns, billingMode field, relationship tree, labels, user data, raw Eloquent attributes or duplicate paginator meta/links. Empty page uses items: [] and standard pagination.

### 5.3 Business errors

Use existing `App\Exceptions\BusinessException` and shared ApiResponse Arabic translation keys in Actions for expected business conflicts.

| Scenario | HTTP | Technical code |
|---|---:|---|
| Duplicate name (including recognized concurrent unique violation) | 409 | ACTIVITY_NAME_ALREADY_EXISTS |
| Protected dependency prevents delete (future dependent feature) | 409 | ACTIVITY_IN_USE |
| Existing Select option cap exceeded | 409 | SELECT_OPTIONS_LIMIT |
| Invalid input/unsupported filter/sort/select | 422 | standard field errors |
| Unauthenticated / forbidden | 401 / 403 | shared error contract |
| Missing authorized target | 404 | shared error contract |
| CSRF failure | 419 | shared error contract |
| Malformed transport / unexpected fault / established temporary unavailability | 400 / 500 / 503 | shared safe rendering |

Conflict example:
```json
{
  "success": false,
  "message": "اسم النشاط موجود بالفعل.",
  "errors": {"code": "ACTIVITY_NAME_ALREADY_EXISTS"}
}
```
Actual Arabic message keys centralized in module translations, no SQL/constraint names, stack traces or protected records in responses. Map only recognized expected constraint faults; unknown database errors remain faults. No new per-error exception classes; no ordinary CRUD Idempotency-Key requirement.

## 6. Select Registry

Reuse current GET /api/v1/selects?selects=activities,activeActivities (no extra query parameters).

| Registered key | Visibility | Capability |
|---|---|---|
| activities | all retained activities, active and inactive, any billing mode | activities.index |
| activeActivities | only isActive=true, any billing mode | activities.index |

Order name ASC with stable ID tie-break; option fields only numeric value (id) and stored label (name). No price/status extra fields; no duplicate selectors or mode/group-specific filtering now.

```json
{
  "success": true,
  "message": "",
  "data": [
    {"label":"activities","options":[{"value":2,"label":"الرسم"},{"value":1,"label":"السباحة"}]},
    {"label":"activeActivities","options":[{"value":1,"label":"السباحة"}]}
  ]
}
```
Illustrative data. Provider results follow existing registry requested key order and configured 200-option maximum, max 3 unique keys per request. No silent truncation; cap overflow 409 SELECT_OPTIONS_LIMIT, unknown/duplicate/invalid keys 422, any forbidden requested provider entire request 403 before provider queries (no partial), unauthenticated 401. Existing role/permission selector behavior remains intact. Authorize list separately from detail, create, update or delete; Select does not grant any of those capabilities.

## 7. Permission catalogue and provisioning

| Permission | Initial Arabic alias | Manager | Reception |
|---|---|:---:|:---:|
| activities.index | عرض قائمة الأنشطة | Yes | Yes |
| activities.show | عرض تفاصيل النشاط | Yes | Yes |
| activities.store | إضافة نشاط | Yes | No |
| activities.update | تعديل نشاط | Yes | No |
| activities.delete | حذف نشاط | Yes | No |

Only these five new technical keys in this feature; 15 registered total (10 existing Identity + 5 catalogue) when successfully provisioned. No future groups/subscription/attendance/finance permissions. Keep permission name stable, alias_name stored, web guard, module-owned definitions and central Identity aggregation. Today PermissionCatalogue, Role mutation validation, grant resolution, select authorization and seed flows are built around Identity-only catalogue; review all consumers before changing them. Role/permission detail, permissions selector and effective authenticated authorization must recognize the new keys without breaking existing aliases/roles/grants/sessions. No direct User permission grants; one active role; six essential last-manager permissions unchanged. Preserve protected permanent roles and founder protections; custom role assignment remains existing authorized behavior.

Fresh installation uses explicit approved default grants (manager all then-approved, reception these two activities reads). Existing installations get this approved feature's new default grants once via reviewed explicit provisioning; **routine additive seeding must not auto-grant or restore removed grants**. Never replace customized grant sets, overwrite aliases, change active status, reset custom roles or regrant on repeated provisioning after an intentional revoke. No unrelated grant adjustments. Plan must define durable one-time completion evidence (including fresh installs/partial failures), transactional integration with existing Identity mutex, safe concurrent seed/admin changes, cache coherence, upgrade/rollback/recovery and exact test evidence. Grant change is approved in principle under D05 but its implementation and any actual production execution remain separate approval gates; shared Audit still deferred, preserve controlled deployment evidence and acknowledge persistent audit gap. Do not change routine seeder's non-destructive contract or silently introduce extra schema.

## 8. Acceptance criteria

All 15 criteria are verified on the exact implementation source cde1c7f8 for the approved standalone scope; see [actual evidence and acceptance traceability](tasks.md). The final documentation-only head CI and merge gate remain open. Each later dependent scenario must be separately verified in its owning feature. Use guarded disposable MySQL 8/InnoDB and native browser auth, not real host/data or mock-only concurrency tests.

| ID | Required behavior / verification |
|---|---|
| AC01 — Scope/schema | Exactly approved catalogue business table/fields, real MySQL type/NULL/default/constraints and name uniqueness; no groups, children, trainers, finance, audit, timers, speculative dependencies, future keys or frontend. Verify migration forward/fresh paths under approved guards. |
| AC02 — Create modes | Hourly-only, subscription-only (ordinary nursery example), both-mode create succeed, POST omitted isActive true, explicit false allowed; persist flags/rates/null correctly and return minimal 201 envelope. Neither mode, missing flags, wrong native types, incompatible price rejected. |
| AC03 — Exact prices and input | Valid decimal strings including min and max normalize to two places; reject zero/negative, too large, extra fraction, invalid lexical/JSON types and missing enabled rate; no silent rounding/coercion, null disabled price; validate required/trimmed Unicode name, 255 bound, unexpected fields and safe validation keys. |
| AC04 — Unique names and race | Stored trimmed case retained, duplicate incl inactive or English case variant -> 409 approved code; same record can retain name/valid display case; simultaneous same/equivalent-name create or competing rename cannot commit duplicates. Verify actual collation outcomes in plan and DB tests. |
| AC05 — Update/state | Full PUT requires fields, can change price/name/modes/isActive, disabling mode clears its rate, reenable requires new price, no status toggle, invalid change leaves prior state unchanged. Active/inactive transitions reflected in all relevant GETs. No phantom downstream changes; future snapshot/continuing-obligation integration gates recorded. |
| AC06 — Delete | Authorized unused row deleted, no stale select/list option; second authorized delete/detail absent -> 404. No implicit deactivation or soft delete; concurrent writes have safe serial outcomes. Future linked deletion fails with ACTIVITY_IN_USE before dependent functionality is released; no fake table here. |
| AC07 — Read contract | Authorized index and show return exact Resource fields and envelope, correct decimal strings/nulls/native flags, no private/internal/timestamp fields, no unrelated loads. Empty result correct, read-only. |
| AC08 — Search, filters, ordering | Literal Arabic/English search, mode 0/1 including both-mode rows, active 0/1, combinations AND, absent filters all; id/name asc/desc stable ordering, default -id; malformed/unknown/unsupported filters/sorts/includes safe 422. |
| AC09 — Pagination | Default 15, valid oversized clamped to 100 in query and response, positive page/perPage only, empty/out-of-range consistent and safe, stable ordering; no unbounded scan/materialization of result pages or offset overflow. |
| AC10 — Select Registry | Correct all/active options and order, only value/label, empty lists, bounded 200/overflow 409, three-key and duplicate/unknown validation, mixed permission denial before providers query; no partial responses; existing roles/permissions selectors still work. |
| AC11 — Independent authorization | Each of five operations and two selectors requires exact capability; unauthenticated/CSRF/stale revoked sessions fail via shared contract, no role-name bypass, missing capability cannot probe record, custom subsets and Manager/Reception mapping work, no HR/financial leakage. |
| AC12 — Catalogue expansion | Module-owned five definitions integrated into shared exact registered catalogue; 15 total, old ten names/aliases stable; Role create/edit/detail and permission select accept/display correct new keys, reject arbitrary/wrong guard/unregistered grants, effective authorization follows actual current grants, last-manager essentials unchanged. |
| AC13 — Initial/repeat provisioning | Fresh install default grants; upgrade existing grants/aliases/custom roles preserved except explicit approved new defaults once; normal reseed and repeated provision never restore revoked grants, no duplicate definitions/pivots; partial failure atomic, simultaneous provisioning/admin changes safe; cache and native session authority coherent. |
| AC14 — Concurrency/fault/security | Actual independent MySQL connections for duplicate/rename/update/delete and access revocation relevant to feature, known integrity conflict mapping, expected rollback, no lost invariant on lock timeout/deadlock/ambiguous outcome; no unauthorized mutation/GET write. Technical implementation and safe outcomes in plan. |
| AC15 — Regression and performance | Existing Identity/Auth/Users/Roles/Select/security/schema/forward migration and package scope checks pass with deliberately updated exact catalogue assumptions; five routes added only, expected module and permission inventory; Pint, locked Composer/audit, route cache; measure actual catalogue queries, pagination/search/select performance and relevant lock duration with bounded synthetic fixtures (D98: 200 activities plus justified boundary stress), no unsupported host SLA or speculative indexes. |

Actual guarded CI 45909789792 passed all 249 tests / 20062 assertions, 223 Pint files and 24 routes before/after cache. The earlier Identity-only CI remains baseline evidence, not the feature acceptance evidence. Tests that require later real dependencies are explicitly deferred to their owning feature, not substituted with mocked protected tables or claimed complete. Verify real constraint behavior and plan exact compatibility before writes.

## 9. Technical plan gate and open details

User approved TP01–TP13 on 2026-11-26. Documentation PR #14 merged at b604d51893b89312096703d31fe3065ebb049227. The guarded collation, schema, strict price grammar, RBAC/provisioning, current authority, native HTTP, race/fault and performance gates passed on exact implementation cde1c7f8; [plan.md](plan.md) preserves approved design and [tasks.md](tasks.md) records actual evidence and limits. No production deployment. Current implementation PR #15 awaits exact documentation closure-head CI, final review and explicit merge approval. No new schema helper, future module, shared Audit, destructive DB operation or dependency change is authorized. Host compatibility/backup/controlled provisioning and rollout remain separate gates.

The documented scope, decisions F01-D01–D06, approved module rules and shared standards are authoritative. Routine compatible transport details above follow current conventions; material unresolved decisions must be brought back for approval. This spec is not an approval to implement the complete Activities module.
