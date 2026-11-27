# Select Registry Simplification — Technical Plan

> Draft for review; proposed implementation only. No implementation, test or migration run is claimed.
> Base: develop 3220d56e3b1b7c7c9842907b2a0266544834c64f. Exact source checked: current registry max 3, config 200, three provider classes with limit+1 (five registered keys).

## 1. Change inventory and design

| Current path | Proposed focused change |
|---|---|
| app/Services/Select/SelectRegistry.php | change maximum distinct keys from 3 to 5; retain validation and two-pass authorize-then-read, key order |
| app/Services/Select/SelectProvider.php | unchanged interface allows(array), options(): array |
| app/Http/Controllers/SelectController.php | unchanged strict selects-only validation, staff_context and envelope |
| config/selects.php | remove obsolete max_options setting; delete file only if verified no other configuration remains/consumer |
| Modules/Identity/app/Services/Select/RoleOptionsProvider.php | remove cap checks, limit+1 and overflow branch; preserve primary, exact guard, active-assignment and id ordering/projection |
| Modules/Identity/app/Services/Select/PermissionOptionsProvider.php | remove cap checks, limit+1 and overflow branch; retain exact PermissionCatalogue constrain, primary read, name ordering and stored-alias integrity failure |
| Modules/Activities/app/Services/Select/ActivityOptionsProvider.php | remove cap checks, limit+1 and overflow branch; retain primary, all/active filter, name/id ordering and id/name projection |
| lang/ar/identity.php | remove retired select_options_limit translation only after exact live-consumer inventory; do not remove shared BusinessException |
| tests/Feature/SelectRegistryProviderTest.php; SelectRegistryHttpTest.php; ActivitySelectHttpTest.php | replace historical cap tests with full-option assertions, keep authorization, filter/order, strict validation and empty/fault cases |
| tests/Feature/ActivityPerformanceTest.php | replace cap/overflow expectations with full eligible materialization and actual plan/size/memory metrics; retain unrelated list/write/lock tests |
| tests/Support/authentication-router.php | inspect actual limit-dependent SQL observation and X-Test-Select-Limit, maintain query detection for uncapped selects, preserve guarded instrumentation and no provider reads on denied request |

T02 must inventory the exact live tree with git grep for selects.max_options, max_options, SELECT_OPTIONS_LIMIT, select_options_limit, X-Test-Select-Limit, cap_plus_one, relevant old three-key tests and limit-dependent SQL matchers, and update all affected consumers, including workflow test discovery if necessary. Inspect actual active code and CI; the above is observed inventory, not guarantee of every occurrence. Historical specs/CI remain intact. No new service abstraction, generic parameters, fallback, auto page, row count, shared error redesign or changes to authorization/session mechanism.

## 2. Queries and security

One domain query per data-backed requested provider where practical, selected columns only; remove only option cap/overflow handling, not where/order/useWritePdo. Preserve exact approved filter for permission catalogue and missing-alias failure. Do not alter normal list limits. Check native authenticated HTTP for invalid, unauthorized, expired or revoked context and multiple keys; instrument SQL reads independently from presence of LIMIT, avoid false zero on forbidden tests. Use a synthetic sixth provider in isolated registry tests to distinguish count rejection from unknown-key rejection, no new runtime key. Tests verify returned full actual options, not just status or count; stable order, numeric ids/string permission values, active/inactive, exact web guard/collation, empty results and no extraneous fields. Any unexpected SQL/alias failure safe 500; do not add error masking.

## 3. Measurements

Use guarded disposable MySQL 8 CI, synthetic records only. Retain historical 200/2,000 Activity read/write/lock measures and replace obsolete Select cap assertions on active tests. Add appropriate 200/201, 500/501, 1,000, 2,000, 10,000 row datasets for real scalable Role/Activity providers (PermissionCatalogue is a fixed actual allowlist, do not invent thousands of real permissions). Include small/empty and skewed active/inactive cases; demonstrate complete result values/order at boundaries and mixed up-to-five requests. Measure actual query count, SQL shape/EXPLAIN ANALYZE, elapsed SQL/HTTP samples with median/max and sample counts, response JSON bytes, PHP peak allocation (not worker RSS), materialized options and combined payload. Retain no N+1 and one domain SELECT per provider; do not impose invented fixed latency/SLA or hidden row cap. Document observed growth, memory limits and possible host risk. If mandatory checks fail or uncapped results cannot fit environment safely, report blocker and obtain decision; do not claim approval for adaptive behavior.

## 4. Regression, release and rollback

Run focused Registry/Activity Select tests, actual Performance and all current Identity, Auth, Roles, Activity tests, full PHPUnit/MySQL 8/Pint, CI configured preflight/migration/route cache/Composer scope checks. Compare exact code/tree and verify route/schema/permission grants/dependencies unchanged. No new migration/seed operation, no change to one-time grant markers, six essential keys, auth/CSRF or other business logic. No new dependencies. Only development/test fixtures; no production access. Code rollback is a separately reviewed revert of the exact Core change (no schema rollback); old client may not accept larger sets, verify release compatibility and host memory/response size before production. Do not deploy. Current docs record approved direction and runtime distinction until merged.

## 5. Review gates

Separate implementation branch from approved develop after documents merged and tasks reviewed. No implementation until final spec/plan/tasks approval. Verify actual CI logs on exact final head; record all AC evidence and residual risk; ready for review only when mandatory checks pass; merge to develop only on explicit approval and verify resulting tree. People F01 follows separately.
