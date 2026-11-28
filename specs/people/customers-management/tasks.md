# People F01 — Customers Management: Implementation Tasks

> Status: Approved execution tasks, 2026-11-28. Scope/spec R01–R04, technical plan TP01–TP08 and all 32 tasks approved; documentation merge and implementation authorized. T01/T02 verified; 2/32 tasks, all 12 Customer acceptance criteria Not Run.
> Sources: [approved specification](spec.md), [approved technical plan](plan.md), [People reference](../../../docs/03-modules/people.md), [workflow](../../../docs/02-standards/ai-workflow-rules.md), [handoff](../../../docs/00-overview/kids-area-conversation-handoff.md).
> Baseline: develop `746f36d86a41817b9577badcefddb317fa98bfd8` (merged Core PR #17). Documentation PR #18 merged at 3347d046465eb0d946fee68b1786dd7ad495a4bb. Implementation branch feature/people-customers-management created from that verified merged commit.
> Prior baseline CI: [46088206957](https://github.com/m-hassan00096/kids-area-api/actions/runs/46088206957) completed success on exact documentation head b29f1b60fc1b05bca51c5ed53ff836b1728ffb4b; Foundation job 140802698182 and Activity preflight job 140802698385 both completed success. Verified run/job metadata (not individual log assertion counts). This proves only existing baseline checks, not Customers.

## 1. Execution rules

- Complete each dependency before its dependent task. Source authored, CI queued and CI verified are distinct states. Check a task only after actual verification with an exact commit/run/job or equivalent evidence. Failed/blocked checks remain unchecked; record reason, not invented results. Keep AC outcomes separate from task completion.
- Documentation PR #18 merged with the user's explicit approval; implementation authorized within approved spec/plan/tasks. Feature merge remains a separate approval gate. No merge to develop or main, no deployment, production database migration, live grant, protected reset, package addition or scope expansion without separate approval.
- Preserve Laravel 12/PHP 8.2/MySQL 8, installed dependencies, one location, stateful Sanctum, six essential Identity capabilities and all existing business behavior. Runtime changes must not implement F02, Sessions, POS, frontend, status/delete/index, new Audit, or speculative tables.
- Real schema, race, grant, HTTP and performance checks use only the existing verified disposable CI environment: GITHUB_ACTIONS=true, testing, DB_URL empty, localhost 127.0.0.1, kids_area_test/kids_area_ci, MySQL 8/InnoDB; validate actual server principal and database before reset. Use only synthetic customer data. Do not extend existing reset privileges or copy production data.
- Reuse existing authentication/race/fault fixtures; test-only instrumentation must remain guarded and not leak into runtime. No raw SQL binding/PII logs. When privacy suppresses query events, count metadata by safe test-only connection instrumentation, not by weakening suppression.

## 2. Ordered tasks

### Phase A — Review and baseline (T01–T03)

| Task | Dependency | Work and required evidence |
|---|---|---|
| [x] T01 | — | Review the 32 tasks and trace all ACs to spec/plan; verify current develop SHA, PR #18 diff limited to documentation, exact-head required CI jobs, current branch/merge state and any new conflicts. Record actual evidence and outstanding decisions. |
| [x] T02 | T01 | Obtain explicit documentation merge approval; verify exact reviewed docs head and CI before merging; verify merged tree and develop HEAD. Obtain explicit implementation approval, create feature/people-customers-management from verified develop. Do not infer either approval from task review. |
| [ ] T03 | T02 | Inventory installed Laravel/nwidart/Spatie versions, module/autoload/routes, all ten migrations, provision markers, permission catalogue and actual test consumers (hard-coded counts/routes/schema fixtures). Record inventory and test-only environment guard; no dependencies changed. |

### Phase B — People module and database (T04–T08)

| Task | Dependency | Work and required evidence |
|---|---|---|
| [ ] T04 | T03 | In guarded MySQL CI, probe approved exact DDL (MySQL 8.0.16+, strict mode, UTC, InnoDB, ascii/ascii_bin, regex CHECK, name collation); test direct invalid status/mobile, duplicate mobile, multiple NULLs, duplicate names, leading zeros. Record actual SHOW CREATE and results. If approved CHECK unsupported, stop affected migration and review equivalent; never silently omit a constraint. |
| [ ] T05 | T04 | Create minimal People module metadata/composer/autoload/service-provider registration and module status following Activities. Verify discovery, PSR-4 and locked dependencies; no premature unresolved provider/route registration. |
| [ ] T06 | T05 | Author only the forward customers migration (proposed 2026_11_28_000011_create_customers_table.php, recheck suffix). One atomic CREATE with six columns, named checks, PK and unique mobile. No additional index/FK/table, no grant DML, no destructive automatic down. Validate actual migration in guarded MySQL. |
| [ ] T07 | T06 | Implement CustomerStatus:int (0/1), Eloquent Customer, safe fillable name/mobile only, explicit default and cast, UTC timestamps. CustomerSchemaTest verifies exact schema, enums, direct constraints, multiple NULL, duplicates and no extra fields. Do not add SoftDeletes or status mutation. |
| [ ] T08 | T07 | Guarded additive forward verification from a populated exact ten-migration baseline with existing activities, staff, role customization, permission aliases and Activity marker. Snapshot existing rows/schema and synthetic backup; assert only customers and one migration record added. Preserve historical 7/8/10-migration fixture paths and no grant in migration. |

### Phase C — Permissions and one-time provisioning (T09–T13)

| Task | Dependency | Work and required evidence |
|---|---|---|
| [ ] T09 | T07 | Define exactly three CustomerPermissions keys and Arabic aliases. Extend PermissionCatalogue exact web membership from 15 to 18; detect collisions across all definitions; preserve 10 Identity, five Activity and six essentials. Review role/permission option consumers; no new direct grants or F02 keys. |
| [ ] T10 | T09 | Implement focused Identity-owned CustomerCatalogueGrants using existing identity_feature_provisions row people.customers.v1, validated catalogue/roles, manager mutex, additive native Spatie methods, exact pivot union and marker last. Integrate fresh SeedIdentityCatalogueAction: Activity marker/defaults first, Customer defaults second in same transaction; manager may already hold Customer keys. Routine existing seed definitions only, no pivot/alias reset. |
| [ ] T11 | T10 | Implement reviewed upgrade Action/console command identity:provision-customer-catalogue --confirm-reviewed-upgrade; register in Identity provider. Require Activity marker, exact complete catalogue, canonical active roles and confirmation/quiesced traffic; preserve custom grants and aliases. No-op completed marker, no automatic replay after unknown commit, restore timeout/cache handling. Guarded test only. |
| [ ] T12 | T10,T11 | Update founder bootstrap prerequisite for both markers/all current keys, keep founder ID/authority unchanged. Audit expanded live catalogue/count/fixture consumers and update only active expectations; keep historical baseline snapshots truthful. |
| [ ] T13 | T12 | CustomerProvisioningTest + existing Identity/Activity regression: fresh roles exact grants/18 definitions/two markers, legacy upgrade preserves old pivots/aliases/users/Activity marker; repeat seed/command after revocation no regrant; no custom auto grants; missing prerequisites/confirmation fail, concurrent provision one completion, marker/commit fault atomicity. Six essentials unchanged; test safe diagnostics. |

### Phase D — Customer HTTP and mutations (T14–T21)

| Task | Dependency | Work and required evidence |
|---|---|---|
| [ ] T14 | T07 | Implement CustomerInput and Store/Update Form Requests with native JSON object parsing, explicit field allowlist and normalization. UTF-8 name 1–255 after trim, mobile exactly 11 ASCII digits or null, omitted/blank -> null (PUT clears); reject numeric/unknown/status fields, query/file/override transport. Revalidate Action input. Add narrow existing middleware normalization exemptions; malformed JSON 400, invalid input 422. |
| [ ] T15 | T09,T14 | Implement RequireCustomerCapability using existing stateful staff context, exact route permission and method/ID shape checks before data lookup; guard PHP_INT_MAX. No role-name bypass, extra target admission query or new authorization system. |
| [ ] T16 | T07,T15 | Implement shared People-local CustomerMutation boundary for two writes, reusing IdentityMutex and CurrentStaffMutation. Outer transaction one attempt, lock order manager -> fresh actor/session -> Customer row, timeout 5s, restoration/disconnect, proven outcome, stale session retirement after rollback only. Safe 503 for timeout/deadlock/unknown commit, no automatic replay. |
| [ ] T17 | T16 | Implement CustomerConstraintErrors: only proven rollback + exact connection/SQLSTATE 23000/1062 + customers INSERT/UPDATE + named uq_customers_mobile match -> Arabic 409 CUSTOMER_MOBILE_ALREADY_EXISTS; all other errors retain safe common contract. Add focused direct mapper and actual MySQL fault checks; no generic duplicate mapping. |
| [ ] T18 | T14,T16,T17 | CreateCustomerAction inserts only canonical name/mobile with server ACTIVE; check save succeeded, snapshot exact resource data within transaction and return only after proven commit. No implicit creation, uniqueness pre-read requirement or idempotency table. |
| [ ] T19 | T14,T16,T17 | UpdateCustomerAction locks/re-reads explicit Customer PK in outer transaction; full replacement name/mobile only, status preserved (including inactive), same mobile valid, omitted mobile clears; missing valid target 404. Last committed authorized PUT wins; no optimistic token or status API. |
| [ ] T20 | T15,T18,T19 | CustomerResource + controller + module route include: exact GET/POST/PUT and 200/201/200 data contract; GET empty message, one projected primary PK read, authorized inactive detail; POST returns created record; PUT data []; no index/delete/PATCH/status. Existing ApiResponse/ApiErrors, Arabic people translations, native HEAD/405 and request ID. Verify route cache and exact path/method scope. |
| [ ] T21 | T20 | Extend existing ProtectSensitiveQueries for customers, safe flash exclusion name/mobile and Customer outcome logging allowlist as appropriate. No raw request/SQL bindings or values; preserve other routes' behavior. Confirm query scope restoration and sanitized errors; provider-specific privacy in T22. |

### Phase E — Select Registry (T22)

| Task | Dependency | Work and required evidence |
|---|---|---|
| [ ] T22 | T05,T07,T09,T21 | CustomerOptionsProvider registered once through PeopleServiceProvider in existing SelectRegistry. Only show permission, active enum predicate, primary projected id/name/mobile, name ASC/id ASC, label name or name - mobile, numeric id, full options; wrap its read in SensitiveQueryScope. No count/LIMIT/search/pagination/cache. Six live keys total, still at most five per request. Verify empty/inactive/null and deterministic ordering; no Core contract rewrite. |

### Phase F — Functional, safety and integration tests (T23–T28)

| Task | Dependency | Work and required evidence |
|---|---|---|
| [ ] T23 | T13,T20,T22 | Extend guarded existing AuthenticationHttpTestCase/browser and fixtures for Customer API. Test synthetic rows/users/roles, native session/CSRF transport, exact response envelopes/Arabic localization/X-Request-ID, safe test-only metadata instrumentation; no new runtime test endpoints. |
| [ ] T24 | T23 | CustomerRead/Write/ValidationHttp tests: POST with/without/null/blank mobile, duplicate names/leading zero, required/type/length/UTF-8 name (255/256), invalid mobile (length/Unicode digits/format/numeric), unsupported fields/status/transport, malformed JSON, GET missing/invalid/overflow ID, inactive GET/PUT, full PUT clear/unchanged/conflict, exact returned fields and database effects. Valid nonexistent PUT 404; invalid payload may be 422 before target lookup per plan. |
| [ ] T25 | T23 | CustomerAuthorizationHttp tests: independent show/store/update custom-role matrix, defaults for Manager/Reception, revoked permission and disabled account/role/session, 401/403 before Customer reads and writes, native CSRF/override behavior; no implicit permissions, no role bypass. |
| [ ] T26 | T24,T25 | Real MySQL independent connections/native HTTP barriers: duplicate POST/POST, POST/PUT both winner orders, PUT/PUT to same mobile, active/inactive retained duplicate, same-row serialized full PUT; exactly one duplicate winner and known 409, losing data unchanged. Live permission revocation/session invalidation while waiting prevents unauthorized write. Verify lock order and distinguish serialization from optimistic locking. |
| [ ] T27 | T26 | Guarded CustomerFaultHttp tests: failure before and after write/precommit, known rollback and unknown/commit outcome, timeout/deadlock, unrelated SQL failure, safe 500/503 and known duplicate 409 only, no blind replay/no false success, connection timeout restored or unsafe PDO disconnected, session retirement safe; assert actual DB state from independent connection. |
| [ ] T28 | T22,T23,T25,T27 | CustomerSelectHttp + privacy/scope regression: complete active options, no extra fields/count/cap, order/labels/nulls, independent show and mixed unauthorized zero provider reads, valid five real keys and six real keys rejected, 18 permission Select options. Metadata-only proof of one domain SELECT/detail PK query and query-log/event suppression/restoration on success/failure; no PII logs. Exact tables/routes/permissions, no F02/index/delete/status. Preserve unrelated providers' behavior. |

### Phase G — Performance, CI and review (T29–T32)

| Task | Dependency | Work and required evidence |
|---|---|---|
| [ ] T29 | T28 | CustomerPerformanceTest on synthetic 200/500/2,000/10,000 rows with inactive/null/duplicate names and Arabic/English. Record true eligible counts, complete option cardinality, domain and safe HTTP queries, elapsed samples, response bytes, PHP peak memory and EXPLAIN for selector/PK. Measure two writes plus permission-revocation manager-lock contention. Report evidence without invented SLA; no index/adaptive changes without measured review. |
| [ ] T30 | T08,T13,T24,T25,T26,T27,T28,T29 | Extend guarded ci.yml minimally: discovery of all Customer tests, forward fixture, focused tests and performance; preserve previous suites and historical preflight. Run locked Composer validation/audit, full PHPUnit, Pint, module list/autoload, approved scope/route cache (expected same-counting 27 routes vs 24 baseline), verify no forbidden files or dependencies. Inspect real exact-head job logs, record counts, AC outcomes and any blockers. Fix only in scope, rerun affected and full checks; no green claim on partial/old run. |
| [ ] T31 | T30 | Open or update feature PR to develop, attach exact feature-head verification, migration/provision safety, performance observations, 12 AC matrix and remaining risks. Verify final diff, required CI and reviewed tree; record task outcomes. No production operations or automatic merge. |
| [ ] T32 | T31 | Obtain explicit user approval for feature merge, verify exact reviewed head and green required CI, merge only approved branch into develop; verify resulting tree/HEAD, update handoff with actual checkpoint and next F02 discussion. If task-file evidence commit changes the head, require exact new CI; avoid endless status-only commits by recording final merge evidence in PR/handoff. No deployment. |

## 3. Acceptance-criteria traceability

All outcomes are Not Run until execution. Prior baseline CI cannot mark these Passed.

| AC | Verification tasks | Outcome |
|---|---|---|
| AC01 — Create/response/ACTIVE | T07,T18,T20,T24 | Not Run |
| AC02 — Optional mobile, duplicates names/NULL, leading zero | T04,T07,T14,T24 | Not Run |
| AC03 — Invalid inputs, Arabic validation | T14,T23,T24 | Not Run |
| AC04 — Duplicate mobile incl. concurrency/inactive | T04,T07,T17,T24,T26,T27 | Not Run |
| AC05 — Exact GET and 404 | T15,T20,T24 | Not Run |
| AC06 — Full PUT, preserve status and own mobile | T19,T24,T26 | Not Run |
| AC07 — Reject unexpected/status fields; no extra operations | T09,T14,T15,T20,T24,T28 | Not Run |
| AC08 — Independent capabilities/initial grants/live authority | T09,T10,T11,T12,T13,T15,T16,T25,T26 | Not Run |
| AC09 — Complete authorized active customer options | T22,T28,T29 | Not Run |
| AC10 — Core 1–5 keys, all-or-nothing | T22,T28,T30 | Not Run |
| AC11 — MySQL constraints/enum/provisioning/scope | T04,T06,T07,T08,T13,T26,T27,T28,T30 | Not Run |
| AC12 — Native HTTP/CI/performance evidence | T23,T26,T27,T29,T30,T31 | Not Run |

## 4. Verification record

- Tasks verified: **2/32** (T01/T02). Acceptance criteria verified: **0/12**.
- T01: reviewed exact documentation head `64f9697507d597c0e63d7b4aa1d0359f1ed32c85` vs prior develop; eight Markdown files, three commits, all 32 ordered dependency IDs and 12 AC trace mappings checked. [CI 46141833603](https://github.com/m-hassan00096/kids-area-api/actions/runs/46141833603) completed success on that exact head; both jobs (140969244378 and 140969244541) passed. Actual logs reviewed: 252 tests / 20,623 assertions, Pint 223 files, 24 routes before/after route cache, locked Composer audit no advisories, MySQL 8.0.46 InnoDB/strict/UTC, existing forward migration preservation and Activity preflight passed. Baseline only, not Customer tests.
- T02: user authorized tasks, documentation merge after successful CI and implementation. [PR #18](https://github.com/m-hassan00096/kids-area-api/pull/18) merged at `3347d046465eb0d946fee68b1786dd7ad495a4bb`; develop HEAD and identical reviewed/merged tree `6563996839481e886f4a57a6fd5a5181c9e2c0dc` verified. Branch `feature/people-customers-management` created from verified merged commit and ref read back. No runtime code, Customer DDL probe, migration, test, grant or benchmark executed.
- When implementing, append exact commit SHA, CI run/job links, measured results and blockers per task; never update historical baseline evidence to today's expanded catalogue. Preserve the canonical handoff, not a parallel progress tracker.
- T03 source inventory: verified baseline `3347d046465eb0d946fee68b1786dd7ad495a4bb` tree (not truncated), two active module manifests, ten exact historical migrations, composer lock Laravel 12.73.0 / Sanctum 4.5.0 / nwidart 12.0.6 / Spatie Permission 6.25.0 / Query Builder 6.4.4 / merge-plugin 2.1.0 / PHPUnit 11.5.58 / Pint 1.30.4. Identity catalogue combines 10+5, Activity marker and seed/bootstrap/current access consumers verified. Live exact inventories/counts to review: IdentityScopeTest (tables/routes/15), IdentityCatalogueTest (15, defaults/marker), IdentityConcurrencyTest (15/one marker/fault), IdentityBootstrapTest (marker prerequisite), ActivityProvisioningTest (live catalogue/defaults vs legacy fixture), RolesReadHttpTest (15), SelectRegistryHttpTest (15), SelectRegistryGrowthTest (five provider reads). Keep historical AuthenticationSchemaTest migration paths, roles-migration-preflight.php and activity-schema-forward.php baseline data/counts intact; review actual auth router/identity worker fault matching and scope checks. Existing CI locked dependencies/24 routes verified in T01. Full tracked-source git grep in new isolated CI job is additional inventory evidence; T03 not yet checked pending that run/log review.
- T04 authored: tests/Support/customer-catalogue-preflight.php and separate customer-preflight CI job. Script uses the exact approved DDL from plan (same customers table name only inside a newly empty disposable MySQL service after ten baseline migrations). No new runtime migration, module, seeding, destructive reset or package. Guard checks local service, explicit CI flag, actual DB principal, MySQL version, strict UTC, charset/collations; probes actual metadata, named CHECK/unique, direct valid/invalid inserts/updates, rollback and historical baseline preservation. Preflight has not yet run; no green claim. Stop at T04 if actual DDL or semantics differ; review before T05/T06.
