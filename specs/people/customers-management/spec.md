# People Feature 01 — Customers Management

> Status: Draft for feature specification review. The user approved the minimal Customer scope, routes, permissions, table fields, and integer-backed status on 2026-11-28; finer behavior below is identified for review. No implementation, tests, migration or grant run.
> Baseline: develop `746f36d86a41817b9577badcefddb317fa98bfd8`, merged Core Select Registry Simplification [PR #17](https://github.com/m-hassan00096/kids-area-api/pull/17).
> Sources: [People reference](../../../docs/03-modules/people.md), [Overview](../../../docs/00-overview/project-overview.md), [Architecture](../../../docs/01-architecture/backend-architecture.md), [API](../../../docs/02-standards/api-standards.md), [Database](../../../docs/02-standards/database-standards.md), [Code](../../../docs/02-standards/code-standards.md), [Permissions](../../../docs/02-standards/permissions-standards.md), [Errors](../../../docs/02-standards/error-handling-standards.md), [Localization](../../../docs/02-standards/localization-standards.md), [Workflow](../../../docs/02-standards/ai-workflow-rules.md), [Core Select Registry](../../foundation/select-registry-simplification/spec.md).

## 1. Goal and scope

Customers are a small supporting entity for later Sessions and POS workflows, not a standalone management page. A staff member can select an existing Customer or explicitly create/edit one within an authorized host workflow. No mandatory permanent Customer or Child for a written-name hourly session; the downstream feature owns that rule.

F01 delivers one Customer business table and People-owned model/enum, three protected endpoints, three capabilities and one existing-registry provider, with necessary Identity/catalogue integration. No Customer index, standalone screen, separate search endpoint, delete, soft delete, activation/deactivation, Guardian/Child relationship, implicit registration, merge, finance, subscriptions, POS/session implementation, frontend implementation, shared Audit implementation, new packages or background jobs. No future-feature placeholders.

## 2. Approved data and invariants

| DB field | Type | Rule |
|---|---|---|
| id | BIGINT UNSIGNED AUTO_INCREMENT | PK |
| name | VARCHAR(255) | Required; names may repeat |
| mobile | VARCHAR(11) | Nullable; unique when non-null, including inactive records |
| is_active | TINYINT UNSIGNED | NOT NULL, default 1; server managed |
| created_at / updated_at | TIMESTAMP | Normal Laravel timestamps, UTC |

`CustomerStatus: int`: INACTIVE = 0, ACTIVE = 1. Store/cast the integer-backed enum, use explicit enum cases in filters and Actions, and never reuse persisted numeric codes. All ordinary creates start ACTIVE; neither create nor update may set status. Use module-local integer-backed enums for other fixed-value concepts only when needed. Existing modules are unaffected. No additional business column/table. Exact migration, enum check constraint, collation and named indexes are plan-level decisions. MySQL unique constraint is mandatory; multiple NULL mobiles are valid. No cross-table matching or uniqueness.

## 3. Protected HTTP contract

| Method | Path | Required permission | Default Manager / Reception |
|---|---|---|---|
| GET | /api/v1/customers/{id} | people.customers.show | yes / yes |
| POST | /api/v1/customers | people.customers.store | yes / yes |
| PUT | /api/v1/customers/{id} | people.customers.update | yes / yes |

No role-name bypass. Require current authenticated staff and effective active role/session per existing Identity contract. Permissions independent: no automatic implication between show/store/update. Provision only the three new keys through existing reviewed Identity catalogue/feature-provision mechanism; do not reset role customizations through routine seeding. Arabic aliases proposed: عرض بيانات العميل، إضافة عميل، تعديل بيانات العميل. Exact provisioning plan reviewed next.

Only these writable JSON fields:

```json
{"name":"أحمد محمد","mobile":"01000001234"}
```

Mobile is a string or null, not a numeric JSON value. Preserve leading zero. The discovery convention is exactly 11 ASCII digits if present, no prefix inference or E.164 conversion. Name required; no unique name rule. Empty mobile becomes null. Full PUT replaces the editable name and mobile (not lifecycle or server fields). Form Requests validate input; Actions own writes. Reject unsupported fields (including phone, isActive, is_active, status, id), not mass-assignment. Never change the existing enum value during ordinary update.

POST 201 returns the created Customer to select immediately; GET 200 returns the authorized minimal record. Approved field spelling is mobile, not phone. Proposed exact examples:

```json
{"success":true,"message":"تم إضافة العميل بنجاح","data":{"id":15,"name":"أحمد محمد","mobile":"01000001234"}}
```
```json
{"success":true,"message":"","data":{"id":15,"name":"أحمد محمد","mobile":"01000001234"}}
```

Proposed PUT 200 uses the standard minimal mutation response `{"success":true,"message":"تم تعديل بيانات العميل بنجاح","data":[]}`; the host refreshes affected options and displayed details. Exact localization keys at plan/implementation. Null mobile remains explicit JSON null. No status/label/timestamps in this minimal projection unless separately approved. All responses use existing centralized API/error handling and request-ID behavior.

## 4. Customer Select

```http
GET /api/v1/selects?selects=customers
```

Use existing Core registry and the People-owned read-only provider. Proposed required permission: `people.customers.show`. No new lookup permission. Default Manager and Reception have it; other roles only if granted. No authorization bypass by combining keys; all requested providers authorized before any option read. Up to five distinct keys; no server search, cap, pagination, count or meta. Active records only via CustomerStatus::ACTIVE; include only numeric id and label in options. Proposed stable order name ASC, id ASC. Name alone when mobile null; otherwise concatenate name and mobile with " - ". Local UI search by name/mobile and refresh after create/update. Empty options array when no eligible records.

```json
{"success":true,"message":"","data":[{"label":"customers","options":[{"value":15,"label":"أحمد محمد - 01000001234"},{"value":16,"label":"أحمد محمد"}]}]}
```

No separate index, no extra data fields, no partial unauthorized results. Future host integration must recheck eligibility at its own write boundary; F01 does not implement that host.

## 5. Proposed review details (not implementation approval)

| ID | Recommendation for specification review |
|---|---|
| F01-R01 | Trim outer whitespace from name; reject blank and >255 characters, preserve remaining entered name. Nullable mobile omitted, null or blank after trim => null; trim outer whitespace only, then exactly 11 ASCII digits. No conversion from country code, Arabic-Indic digits, numeric input or formatting punctuation. PUT missing mobile clears it to null (full replacement). |
| F01-R02 | Duplicate non-null mobile is a persisted-state conflict: same safe Arabic 409 `errors.code = CUSTOMER_MOBILE_ALREADY_EXISTS` for normal and concurrent duplicates, including inactive records. Invalid input returns 422 with field errors. Updating a record without changing its mobile is allowed. Map only the known unique-constraint conflict, never every SQL exception. |
| F01-R03 | Detail and ordinary edit by explicit ID may access a retained inactive Customer if appropriately authorized; edit never changes status. Active-only is the selection eligibility rule, not deletion or automatic reactivation. No inactive selector option. |
| F01-R04 | Minimal detail/create response as above and PUT data: []; no isActive or label returned in F01. Selector permission show, name ASC/id ASC, label name plus optional mobile. |

If any proposal is rejected, revise the draft and affected canonical reference before plan review. Missing ID -> 404, no authentication -> 401, missing effective permission -> 403, malformed/invalid input -> 422, known duplicate -> proposed 409, unexpected fault -> safe 500. No raw SQL, stack trace, mobile/name in diagnostic logs or real customer fixtures. No additional error class unless genuinely justified.

## 6. Acceptance criteria (all Not Run; subject to final spec review)

| ID | Required behavior |
|---|---|
| AC01 | Create named Customer with valid mobile, 201, exact returned object and database ACTIVE default. |
| AC02 | Create without mobile and with null/blank mobile; multiple NULL rows and duplicate names valid; preserve leading zero. |
| AC03 | Reject invalid/missing/blank/oversized name and invalid mobile shape/length/digits with Arabic 422 errors; no record created. |
| AC04 | Reject non-null duplicate mobile across active/inactive Customers, including simultaneous creates/updates; safe proposed 409, no duplicate row. |
| AC05 | GET existing id returns exact minimal data with empty message; missing id returns 404. |
| AC06 | PUT updates only editable fields, preserves status, accepts own unchanged mobile, permits null, rejects conflicts without unintended writes. |
| AC07 | Reject status/server/unknown fields on POST/PUT; no index/delete/change-status/PATCH route or permission. |
| AC08 | Three permissions independently enforced; Manager/Reception initial grants; custom roles/permission removal and active account/session checked; 401/403 no data leaks. |
| AC09 | Selector requires approved show permission; active only; correct value/label/order/null case; full options; empty [] and local name/mobile search contract. |
| AC10 | Core 1–5 keys and mixed unauthorized/invalid semantics preserved; no partial lookup reads, new count, cap or extra route. |
| AC11 | MySQL 8 constraints and concurrency; enum persistence/casting; existing catalogue/seed/grants safe and repeatable; no speculative tables or dependency changes. |
| AC12 | Real HTTP, schema, focused tests, full PHPUnit/Pint and exact-head CI evidence; measure selector growth at 200/500/2,000/10,000 suitable synthetic rows (queries, latency, bytes, memory) without claiming production SLA. |

## 7. Risks and delivery gates

Uncapped selectors have O(n) payload and memory cost; this is the already-approved V1 trade-off. No hidden truncation; measure and report concerns. Optional mobile permits legitimate same-name/no-mobile duplicates; do not pretend repeated requests without mobile can be deduplicated. No destructive actions, production data, deployment or live grants.

Next: review this specification (especially R01–R04), then author/review plan.md (actual module layout, migration/constraints, permission provisioning, native HTTP and concurrency strategy), then tasks.md, then explicitly approved implementation. Do not claim any tests or code complete.
