# People F01 — Customers Management: Technical Plan

> Status: TP01–TP08 approved on 2026-11-28 (user: "تمام كمل"). [Implementation tasks](tasks.md) prepared for separate review; no runtime implementation, Customer migration, grant or feature test execution. Prior documentation-head baseline CI 46088206957 succeeded; it did not exercise Customer behavior.
> Baseline: develop `746f36d86a41817b9577badcefddb317fa98bfd8` (merged PR #17); documentation PR #18 branch docs/people-customers-management.
> Sources: [approved spec](spec.md), [People](../../../docs/03-modules/people.md), [architecture](../../../docs/01-architecture/backend-architecture.md), [standards](../../../docs/02-standards/), [Core Select](../../foundation/select-registry-simplification/spec.md), actual Activities/Identity/Core files at the baseline. Preserve Laravel 12, PHP ^8.2, MySQL 8, stateful Sanctum and installed package versions. No new dependency.

## 1. Approved technical decisions (subject to verification)

| ID | Approved decision |
|---|---|
| TP01 | Register People using existing module metadata, PSR-4 merge plugin, module status and root API route include. One focused forward migration for customers only; existing Identity marker table reused. |
| TP02 | Exact DDL below: InnoDB, name utf8mb4_unicode_ci, mobile ASCII/ascii_bin, named unique and CHECK constraints. PK and unique mobile only initially; measure before adding a selector index. |
| TP03 | Native JSON allowlist/type validation, explicit normalization, exact routes and Resource. Strict positive PHP-range integer route IDs, capability before lookup. Malformed JSON 400, invalid payload 422. Authorized valid missing target 404. |
| TP04 | Preserve current IdentityMutex/CurrentStaffMutation write-authority pattern with a focused People-owned mutation boundary. One outer transaction, five-second lock wait, no blind retries. Ordinary concurrent PUT is serialized full replacement (last committed authorized write wins); no version token or new conflict API. |
| TP05 | Known customer mobile unique violation maps to approved 409 only after proven rollback. Unknown SQL/commit failures keep safe existing 500/503 contract. No query-log PII leakage, no raw request or private bindings. |
| TP06 | Three module-owned permissions extend exact live catalogue to 18. Use existing identity_feature_provisions table with one new marker row people.customers.v1; additive defaults for both roles, never routine regrant, no custom-role overwrite. Fresh seed and reviewed upgrade separately tested. |
| TP07 | Existing Select Registry provider returns complete active options; six registered keys overall, at most five requested per call. One projected primary DB read, no count or LIMIT. Exact approved label and order. |
| TP08 | Guarded real MySQL 8 schema/forward/provisioning, native HTTP/auth, races/faults, privacy, selector and full regression; measure 200/500/2,000/10,000 synthetic rows. Do not claim production SLA or green tests until actual evidence. |

## 2. Module layout and integration

Actual baseline uses `Modules/Activities/composer.json` PSR-4 mapping to app/, `module.json` provider registration, root `modules_statuses.json`, and `routes/api.php` requires each module route file with the global /api/v1 prefix already configured in bootstrap/app.php. Mirror that existing layout, not an alternative nwidart skeleton. Add only justified files.

```text
Modules/People/
  composer.json
  module.json
  app/
    Actions/Customers/CreateCustomerAction.php
    Actions/Customers/UpdateCustomerAction.php
    Enums/CustomerStatus.php
    Models/Customer.php
    Http/Controllers/CustomerController.php
    Http/Middleware/RequireCustomerCapability.php
    Http/Requests/Customers/CustomerMutationRequest.php
    Http/Requests/Customers/StoreCustomerRequest.php
    Http/Requests/Customers/UpdateCustomerRequest.php
    Http/Resources/CustomerResource.php
    Providers/PeopleServiceProvider.php
    Services/Customers/CustomerMutation.php
    Services/Customers/CustomerConstraintErrors.php
    Services/Select/CustomerOptionsProvider.php
    Support/CustomerInput.php
    Support/CustomerPermissions.php
  database/migrations/2026_11_28_000011_create_customers_table.php
  routes/api.php
lang/ar/people.php
tests/Feature/Customer*Test.php
```

Use the current root test convention for HTTP/MySQL fixtures, not a duplicate module test suite. Add People to module status and root routes exactly once. Existing Composer merge-plugin discovers the module mapping; verify autoload/module discovery and lock consistency with no package update. Keep Controllers thin and simple reads local; no repository, DTO, generic CustomerService, query builder for nonexistent index, jobs, caches, event bus, new audit store, or extra abstraction. A focused CustomerMutation service is justified only as shared security/transaction/failure handling for the two Actions, not a CRUD mirror. Actual ActivityMutation hard-codes activities permissions, so do not call it with People keys or refactor all prior modules; reuse existing Identity services and their verified pattern. New cross-module write entry points for later Sessions/POS are not implied.

## 3. Schema and model

Proposed single atomic CREATE TABLE, analogous to existing Activities migration. Exact suffix follows the ten current migrations; recheck before implementation. Run only in guarded disposable CI/test environment for this feature. Reuse MySQL 8.0.16+ enforced CHECK / strict SQL / UTC guard and verify actual server engine and DDL.

```sql
CREATE TABLE customers (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    mobile VARCHAR(11) CHARACTER SET ascii COLLATE ascii_bin NULL DEFAULT NULL,
    is_active TINYINT UNSIGNED NOT NULL DEFAULT 1,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    UNIQUE KEY uq_customers_mobile (mobile),
    CONSTRAINT chk_customers_status CHECK (is_active IN (0,1)),
    CONSTRAINT chk_customers_name CHECK (CHAR_LENGTH(TRIM(name)) > 0),
    CONSTRAINT chk_customers_mobile CHECK (
        mobile IS NULL OR
        (CHAR_LENGTH(mobile) = 11 AND mobile NOT REGEXP '[^0-9]')
    )
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

DDL is a plan, not an executed migration. Verify regex CHECK support and exact digit enforcement against installed MySQL 8, including direct insert/update, under actual SQL mode. If the server rejects the planned CHECK or semantics, report and review a compatible equivalent rather than quietly dropping integrity. Name uses existing table-default collation; no uniqueness/comparison rules borrowed from Activity names. Name tie ordering by id is deterministic. Application Str::trim handles Unicode edge whitespace more broadly than SQL TRIM; DB CHECK is a minimal secondary guard, not a claim of identical Unicode validation. Mobile is canonical ASCII text, never numeric or an international phone conversion. Strict input and DB constraints protect leading zeros and unique non-null values; multiple NULL values supported. No soft-delete/created-by/guardian_id/cached balance/notes or extra business tables; no foreign key until a real owning feature needs a reference. No index for name/is_active until measured evidence justifies it. No destructive down() by default: protected reversal requires separately reviewed preservation/rollback, like Activities.

Model: Eloquent Customer, explicit fillable name/mobile only, no hidden writes; internal default enum backing value or explicit trusted initialization consistent with DB default. Cast is_active to module-owned CustomerStatus (INACTIVE=0, ACTIVE=1); never expose it in CustomerResource. Use enum ->value for Query Builder predicate and fixed defaults, not magic status numbers in Actions. Migration freezes historical numeric assignments. Insert and Resource snapshot inside transaction, return only after proven commit. No status change method, no SoftDeletes.

## 4. API and request processing

| Method | Route | Middleware capability | HTTP success |
|---|---|---|---|
| GET | /api/v1/customers/{customer} | people.customers.show | 200, message empty, data id/name/mobile |
| POST | /api/v1/customers | people.customers.store | 201, translated message and data id/name/mobile |
| PUT | /api/v1/customers/{customer} | people.customers.update | 200, translated message and data [] |

Common chain: RequireStatefulBrowser, auth:sanctum, RequireStaffSession, then People capability middleware using the current staff_context from Identity. No role-name shortcut. Use literal route constraint [1-9][0-9]* and checked PHP_INT_MAX string comparison before int cast (same existing route ID pattern). Reject method overrides using getRealMethod() vs getMethod(). Capability checked before Customer table access; middleware checks target syntax, not a data query. GET checks empty query/body/files then reads id/name/mobile once from primary by PK; missing returns 404. For PUT, validate body after permission and ID shape, then check actual existence with lockForUpdate inside transaction; valid request for missing target 404. Invalid body + nonexistent target may return 422 before target read; this is the proposed validation order, not a permission bypass. Laravel native HEAD for GET/405 handling stays intact. No GET collection endpoint (GET /customers may return 405 since POST exists), no PATCH/DELETE/status.

Use native JSON object only, follow existing ActivityMutationRequest raw JSON decode with JSON_THROW_ON_ERROR, reject arrays/scalar/root unknown keys, files/query/body transport violations, and JSON numeric mobile. JSON syntax failure 400. Missing name/invalid name/mobile/unsupported fields 422; use Arabic field errors keyed name/mobile/request, never echo PII or unknown keys. Reuse existing HTTP normalization exemptions for /api/v1/customers and wildcard routes; apply explicit Str::trim to valid UTF-8 strings only. Name length 1–255 via mb_strlen. Mobile omitted/null/trimmed empty -> null; otherwise regex `\\A[0-9]{11}\\z` with no internal spaces or Unicode digits. Full PUT missing mobile clears to null. Name/mobile native type checks before normalization; revalidate shared normalized input at trusted Action boundary. Like existing parsed-JSON contracts, duplicate raw JSON member names collapsed by the parser are not separately detected; don't claim raw-wire duplicate detection.

CustomerResource returns exact id (int), name (string), mobile (string|null), never timestamps or status. Use ApiResponse::success with resolved array and existing safe localization; POST serializes created record, PUT returns default []. Arabic keys people.customers.created, updated, mobile_duplicate and field messages as needed in lang/ar/people.php; technical permission aliases stored per Identity convention. No new response or error handler.

## 5. Transactions, concurrency and privacy

Existing `Modules/Activities/app/Services/ActivityMutation.php` demonstrates the live security boundary. Reuse IdentityMutex and CurrentStaffMutation::lockPrepared with the original request. Proposed People-local orchestration:

```text
valid input + initial route permission
  -> ensure no existing outer transaction
  -> capture connection/session timeout, set lock timeout 5 seconds
  -> one transaction attempt
     -> lock permanent manager role (IdentityMutex)
     -> fresh actor, effective permission, original session lock (CurrentStaffMutation)
     -> POST: insert canonical name/mobile, internal ACTIVE
        PUT: lock customer PK, re-read; update only name/mobile, preserve status
     -> for POST capture exact resource fields
  -> commit; only then send success
  -> restore timeout or disconnect unsafe connection
```

No SQL read-before-write uniqueness authority; rely on actual named UNIQUE key, which naturally excludes the updated row's own unchanged mobile. Any optional duplicate precheck must not change the one 409 contract; avoid it initially. No generic DB::transaction retries after possible effects, including uncertain commit. Known rolled-back duplicate maps narrowly by connection, SQLSTATE 23000, MySQL 1062, INSERT/UPDATE customers and exact uq_customers_mobile / customers.uq_customers_mobile identity, via existing BusinessException and Arabic translation. An unrelated unique constraint or SQL error stays internal 500. 1205/1213, unavailable connection or unknown commit follow the existing Activity pattern's safe 503; restore previous session lock timeout, disconnect uncertain connection; do not claim operation was rolled back if commit outcome is unknown. Retire stale session only after confirmed rollback through existing CurrentStaffMutation. No replay on 503 (especially optional-mobile creates, where a duplicate record could otherwise be made); caller must verify outcome before retry. No idempotency table for this ordinary registration; no automatic dedup for null mobile.

Concurrent ordinary updates of the same customer are serialized under the current lock order; the later committed authorized full PUT wins, not optimistic locking. Distinct customer writes and Identity/Activities writes may contend on the same permanent manager row; maintain the existing correctness policy and measure latency/wait before proposing a finer lock design, do not add separate lock ordering. Keep critical section short, no external calls or filesystem operations.

Privacy: Customer names and mobiles are personal information. Existing ProtectSensitiveQueries only covers Identity paths; extend it narrowly for customers routes. Use SensitiveQueryScope within CustomerOptionsProvider for its read on the shared selects endpoint (do not suppress unrelated providers). Preserve existing safe ApiErrors sanitized report, add a People mutation outcome attribute to the existing allowlist if needed; add name/mobile to protected flash list for these requests using existing configuration, without changing other API semantics. No SQL bindings, customer values, raw exceptions, session tokens, real records or payload dumps in logs. Test event/query-log suppression/restoration on success and failures; tests needing counts must use guarded synthetic SQL-shape/connection-level instrumentation that sees only metadata, not weaken production privacy. No PII-bearing cache.

## 6. Permission catalogue and one-time grants

Actual Identity PermissionCatalogue combines 10 Identity + 5 Activity definitions with duplicate-key rejection. Add CustomerPermissions::CATALOGUE with exactly the three approved names and Arabic alias_name:
- people.customers.show — عرض بيانات العميل
- people.customers.store — إضافة عميل
- people.customers.update — تعديل بيانات العميل

After implementation, exact live count 18, six Identity essential keys unchanged. Both initial roles get these three, custom roles receive none automatically. Continue exact web guard/binary membership rules. Permission Select and role edit naturally use the central allowlist; do not expose unregistered keys or edit unrelated Identity capabilities.

Existing identity_feature_provisions (feature_key ascii_bin PK, completed_at UTC) is already migrated. New marker `people.customers.v1`, one row on successful fresh seed or explicitly reviewed upgrade; no new table/migration for grants. Proposed narrow Identity-owned CustomerCatalogueGrants, ProvisionCustomerCatalogueAction and command `identity:provision-customer-catalogue --confirm-reviewed-upgrade`, registered in existing IdentityServiceProvider. Names are technical proposals. Match Activity provisioning behavior (one outer transaction under manager mutex, validate exact role/catalogue/marker rows, native Spatie additive grants, verify expected pivot union, marker last, post-transaction permission cache invalidation).

Fresh seed: existing SeedIdentityCatalogueAction seeds all 18 definitions. Preserve ActivityCatalogueGrants fresh behavior (manager gets all catalogue keys, reception Activity defaults, existing Activity completion marker). In the SAME outer transaction, Customer grant completion adds only missing new keys to Manager/Reception and writes its own marker, tolerating manager already holding them from the fresh Activity path. Verify both markers and exact defaults; bootstrap now requires both markers and all current keys, not a new founder path. Existing Activity grant semantics and marker must not be reset. Fresh seeding failure rolls back both markers/grants/definitions.

Existing installations: additive routine seed creates missing definitions without rewriting aliases or role pivots. Reviewed Customer upgrade command requires existing exact Activity marker and complete valid current catalogue, active canonical roles and quiesced staff traffic; only add the three keys to both default roles once, preserve every old/custom grant and alias, atomically commit marker. A completed Customer marker makes repeat command no-op for grants; subsequent legitimate removals remain removed. No production provisioning in this feature review. Reuse existing uncertainty and cache handling; do not replay an uncertain first grant, inspect marker through separately authorized recovery. Distinguish live expanded counts from historical fixture snapshots. If prior Activity provision is incomplete, report prerequisite and do not auto-run it.

## 7. Select integration and performance

PeopleServiceProvider registers exactly customers on the existing singleton SelectRegistry through current SelectProvider contract. allows(permissions) checks people.customers.show only. options() reads from primary:
```sql
SELECT id, name, mobile
FROM customers
WHERE is_active = 1
ORDER BY name ASC, id ASC;
```
Actual query uses CustomerStatus::ACTIVE->value and explicit columns within SensitiveQueryScope; map id to numeric value, label name or name + " - " + mobile. No joins, count, LIMIT, pagination, extra metadata or separate endpoint. Empty [] eligible. Registry unchanged: auth all requested keys before any reads; exactly up to five distinct keys per request. With Customers there are six registered keys total (roles, userFilterRoles, permissions, activities, activeActivities, customers); test all six real keys rejected as a single request and representative valid batches of five. Keep earlier isolated provider cap tests meaningful, not a false claim only five registered keys can exist.

Measure 200/500/2,000/10,000 deterministic synthetic customer rows, include inactive/null mobiles, duplicate names, Arabic and English. Record actual active counts, full option cardinality, domain query counts (one per provider, GET detail one), total HTTP queries where safe, elapsed time samples, JSON response bytes, PHP peak memory and EXPLAIN on exact selector and PK queries. No synthetic data in production seeder. Compare manager-lock contention for two ordinary writes and one permission revocation; measure timeouts without arbitrary production SLA. O(n) selector payload cost is accepted; no automatic adaptive mode or hidden cap. Decide any composite index only after actual plan/measurement review.

## 8. Verification and CI gates (all Not Run for F01)

| Area | Evidence required |
|---|---|
| Schema / forward migration | Verify MySQL version, InnoDB, strict UTC, actual SHOW CREATE, six columns, PK/unique/checks/defaults, enum cast, multiple null/duplicate names, direct invalid status/mobile rejection. Guarded additive migration from populated ten-migration baseline; snapshot old schema/rows, roles/permissions/marker/activities; only customers and one migration record added, no grant in migration. |
| Validation / HTTP | Native browser/session/CSRF tests using existing AuthenticationHttpTestCase and harness, exact envelopes, localized errors, request ID; raw JSON types, blank/whitespace/UTF-8, 255/256 boundary, nulls, leading zeros, unknown fields, malformed JSON/transport, override, invalid/overflow IDs, inactive GET/edit, absent mobile PUT. |
| Security | Independent show/store/update, custom role and revoked permission, disabled staff/role/session, unauth/forbidden no customer lookup, live actor/session revocation while write waits. Test no extra routes/permissions, no Guardian/Child entities. |
| DB races and failure | Real independent connections/HTTP barrier tests: POST/POST, POST/PUT and PUT/PUT competing mobile; exactly one winner, one 409, losing row unchanged. Same row sequential full updates; direct DB unique collision as needed. Fault before write, after write before commit, unknown/commit outcome and timeout/deadlock; no false success or replay; sanitized 500/503; restore/disconnect. |
| RBAC provisioning | Fresh 18 definitions and both markers; default role grants exact; six essentials unchanged; existing upgraded custom role/pivots/aliases/Activity marker preserved; command confirmation, incomplete catalogue, failed marker atomicity, concurrent provision once, repeated seed/command does not restore revoked rights; guarded fixtures only. |
| Selector / privacy | Exact active-only projection/order and complete results, empty [] and mixed unauthorized zero reads, any six real keys rejected, valid five; query count and PII suppression/restoration, permission Select expanded to 18. |
| Regression / performance | Full existing auth/Identity/Activity/Core tests, customer-specific tests discovery, guarded forward migration and synthetic performance metrics; Pint, Composer validation/audit, module status/autoload, route list before/after cache and scope inventory. Expected 24 existing production route entries + 3 customer routes = 27 on same counting convention; verify actual list, including native HEAD, not just count. |

Extend existing `.github/workflows/ci.yml` guarded workflow minimally. Preserve prior exact migration/preflight history: do not retroactively change old 7/8/10-migration or ten/fifteen-permission historical fixture baselines; update live test assertions and seed expectations to 18/People where applicable. Audit actual hard-coded counts in IdentityScopeTest, IdentityCatalogueTest, ActivityProvisioningTest, SelectRegistryHttpTest/ProviderTest and other consumers by grep; keep old permissions/routes/behavior covered, not simply weaken tests. Historical fixture tests that deliberately run old code assumptions must explicitly isolate historical expected state versus new live catalogue. Add guarded customer-schema-forward check after existing Activity forward baseline or an equivalent populated ten-migration fixture with tested role customization. No new destructive command outside existing test-only guards (GITHUB_ACTIONS=true, testing, local kids_area_test, kids_area_ci, MySQL 8/InnoDB). Use existing guarded native HTTP race infrastructure and metadata-only instrumentation; no production test headers/routes or test-only DB tables.

Run focused Customer tests, existing suites, full PHPUnit, Pint and real GitHub Actions on exact feature head, then review actual logs and task status. Tests are plans, not results. Existing baseline CI must not be mislabeled as F01 evidence.

## 9. Delivery and non-goals

Next: review [tasks.md](tasks.md), which maps all 12 acceptance criteria to ordered tasks and verification. Documentation PR #18 remains unmerged pending explicit approval. Implement only from the approved develop baseline on feature/people-customers-management after the documentation/implementation gate. No production migration, data access, grants, dependencies, UI, F02, session/POS changes, new cache, or merge without separate authorization.
