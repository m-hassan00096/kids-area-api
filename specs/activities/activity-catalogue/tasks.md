# Activities Feature 01 — Activity Catalogue: Tasks

> **Status:** TP01–TP13 approved; task breakdown prepared for review, **0/42 implementation/delivery tasks verified**, all 15 feature ACs Not Run. This document does not authorize code, merge or production operations. Prior spec-only CI 45891656713 passed 206/16498, not feature evidence.
> **Sources:** [spec.md](spec.md), [plan.md](plan.md). Txx dependencies are mandatory; ACxx references correspond exactly to spec §8. Every unchecked task requires recorded actual verification; authored code/tests alone are insufficient. No task checkbox is closed merely because a previous baseline passed.
> **Gate:** PR #14 docs-only review, exact-head CI, explicit merge approval, then reviewed task list and implementation authorization. Guarded synthetic MySQL only; no real DB/reset/down/deploy or future feature. Reopen any affected decision if actual MySQL behavior conflicts with approved collation expectations.

## Phase A — Review and verification prerequisites (T01–T06)

- [ ] **T01 — Documentation gate and baseline** (AC01, AC15). Verify TP01–TP13, docs-only PR #14 exact changed paths/head/CI, explicit approval to merge, tree equality after merge. Verify actual develop and create feature branch only after separate implementation authorization. Evidence: PR/merge/branch SHAs, CI job and actual gate.
- [ ] **T02 — Inventory actual implementation and consumers** (depends T01; AC01, AC12, AC15). Inspect locked packages, module provider/route discovery, migrations, IdentityPermissions keys usages, PermissionCatalogue, BootstrapManagerAction, seeder, Roles readers/writers, Auth context, Select Registry, existing tests and CI scope/count assertions. Document exact file paths and preserve six ESSENTIAL keys; classify global vs Identity-only. Evidence: reviewed consumer matrix and baseline diff.
- [ ] **T03 — Guarded disposable MySQL preflight** (T02; AC01, AC04, AC14, AC15). Verify testing environment, synthetic DB name/server/principal, version, InnoDB, strict/UTC, schema/migration history; collation availability and CHECK enforcement; preserve existing guards. No real DB or new destructive reset. Evidence: safe version/metadata/guard outputs and approved isolated fixture approach.
- [ ] **T04 — Collation and lexical feasibility** (T03; AC01, AC03, AC04, AC08). Measure actual equality, LIKE and unique behavior for every plan sample including Arabic variants, accents, canonical forms, edge/internal whitespace, mixed scripts and literal search. Record results and stop affected work for review on material mismatch; no fallback. Evidence: actual MySQL test matrix, not PHP-only.
- [ ] **T05 — Forward/fresh migration test design** (T03,T04; AC01, AC13, AC15). Prepare synthetic historical fixture and exact snapshot of existing users/roles/permission aliases/pivots/sessions/throttles/migrations; verify prior Roles migration preservation and clean new install paths. Confirm no unapproved down/reset or business data. Evidence: guard, snapshot comparison strategy, safe synthetic backup/restore plan.
- [ ] **T06 — Test harness and CI transition design** (T02,T03; AC11–AC15). Reuse guarded real browser/independent DB connection and process barrier harness; identify actual new tests discovery, scope and route inventory changes. Do not relax Identity assertions or enable modules outside scope. Evidence: harness/CI review and guard checks.

## Phase B — Activities schema, module and domain foundations (T07–T11)

- [ ] **T07 — Register Activities module** (T01,T02,T06; AC01, AC15). Add only necessary package-generated metadata/autoload/provider/module status/routes wiring; use one /api/v1 prefix, no default web or duplicate routes, no package. Evidence: package/module discovery, exact scope, route checks.
- [ ] **T08 — Activities migration** (T04,T05,T07; AC01, AC04). Implement approved nine columns, InnoDB, explicit name collation, uq_activities_name and five named CHECKs, PK only otherwise. No global collation or prior migration edits. Evidence: real SHOW CREATE/metadata, positive/negative DB constraint cases including NULL/flags, migration upgrade and fresh.
- [ ] **T09 — Identity marker migration** (T05,T07; AC01, AC13). One technical identity_feature_provisions table with exact ascii_bin PK and completed_at UTC; no grant DML in DDL migration; ensure prior records unchanged. Evidence: fresh/forward metadata and old-row snapshot equality, no third table.
- [ ] **T10 — Activity model, enum, resource** (T08; AC02, AC03, AC07). Explicit field mapping and casts, no soft-delete/relations, integer billing query enum only, exact decimal strings/null and seven public fields. Evidence: unit/domain and DB-backed resource cases including min/max values, no timestamp exposure.
- [ ] **T11 — Arabic messages and known constraint mapping** (T08; AC03, AC04, AC06, AC10, AC14). Reuse native translation, BusinessException and ApiErrors; only known named name-index 1062 => 409; unknown SQL remains 500; future dependency error documented, not fabricated. Evidence: safe localized error and actual expected/unexpected constraint tests.

## Phase C — RBAC, provisioning and shared integration (T12–T19)

- [ ] **T12 — Module-owned permission definitions and aggregation** (T02,T07; AC12). Add exact five keys/aliases; extend existing PermissionCatalogue map/constrain, collision guard, stable web exact checks, total 15. Six ESSENTIAL unchanged. Evidence: exact inventory, original alias/name preservation, reject unknown/wrong guard/case collision.
- [ ] **T13 — Role management consumers** (T12; AC12). Update RoleMutationRequest/Data, RolesQuery, PermissionOptionsProvider and all relevant call sites, exact list limits/membership and grant resolution, old anomaly/last-manager checks. Evidence: role create/edit/show/select with custom subsets, invalid/unknown rejects and no unrelated exposure.
- [ ] **T14 — Routine additive seeder** (T09,T12; AC12, AC13). Existing installation creates missing definitions only; preserves aliases, role state, assignments, sessions, existing marker; no routine grant restoration. Evidence: repeated seeding, customized aliases/grants, marker-present and marker-absent upgrade cases.
- [ ] **T15 — Fresh seed and founder bootstrap** (T09,T12,T14; AC12, AC13). Extend true fresh seed transaction, manager 15/reception two approved reads, insert marker atomically, check bootstrap full catalogue while essential viability remains six. Evidence: actual fresh race, bootstrap founder prerequisites, no invented old reception grants, failure rollback.
- [ ] **T16 — Explicit one-time provisioning command/Action** (T09,T12,T14; AC13, AC14). Fixed feature key, no HTTP/arbitrary grants, IdentityMutex, native grant APIs, role/permission/pivot checks, marker+grants one transaction, no overwrite. Evidence: upgrade diff vs baseline, marker and explicit default grant mapping, repeat no-op after intentional revocation.
- [ ] **T17 — Provisioning faults and serialization** (T15,T16; AC13, AC14). Independent connections for seed/provision and concurrent provision/role updates in both orders; inject guarded actual DB fault mid-grant; assert rollback, marker atomicity, exact unaffected grants/roles. Verify lost COMMIT acknowledgement and marker-based reconciliation, no blind replay. Evidence: actual barriers/native outcomes and snapshots.
- [ ] **T18 — Cache, session and deployment gate** (T13–T17; AC11–AC14). Native Spatie mutation and postcommit/rollback cache eviction; shared cache repopulation race, current primary session grants, revoked privileges not restored. Document controlled upgrade traffic gate, prerequisites/marker reconciliation, backup/forward recovery and residual audit gap. Evidence: browser/cache integration and reviewed runbook; no production execution.
- [ ] **T19 — RBAC checkpoint** (T12–T18; AC12, AC13, AC15). Run focused Identity/Auth/Roles/Select and seed/migration regression with deliberate exact catalogue assertions; verify old security behavior unchanged. Evidence: exact head test discovery/results/logs, no waiver.

## Phase D — Transport, strict validation and reads (T20–T26)

- [ ] **T20 — Five protected routes and independent capability** (T07,T12,T19; AC07, AC11, AC15). Integrate existing stateful/auth/staff middleware; validate ID range after capability; target disclosure only after authorization, no bearer fallback. Evidence: five route inventory, CSRF/auth/capability/probe/native session tests.
- [ ] **T21 — Mutation request validation** (T10,T11,T20; AC02, AC03, AC05). Raw native JSON object, strict fields/flags/prices, Unicode trim, canonical decimals, POST default and full PUT; scoped middleware exemptions only. Reject malformed/unknown/wrong types, overflow, padding/whitespace, price null/mode conflicts. Evidence: comprehensive boundary matrix including 400 vs 422 and exact field keys.
- [ ] **T22 — Index query validation and filter builder** (T04,T10,T20; AC08, AC09). Exact scalar allowlist, literal bound ESCAPE search, two 0/1 filters, one sort, positive bounded pagination; no unapproved include/fields or SQL. Evidence: Arabic/English/wildcards/commas/arrays, invalid values, safe page bounds and no overflow.
- [ ] **T23 — List query and snapshot** (T22; AC07–AC09, AC15). Primary repeatable-read count + bounded page, stable sort, out-of-range no offset/fetch, no N+1, correct total/lastPage. Evidence: data/envelope, two domain SELECTs or one empty, actual concurrent snapshot test.
- [ ] **T24 — Detail read and target handling** (T20,T10; AC06, AC07, AC11). Re-resolve authorized numeric target on primary, exact seven-field resource; missing 404, no private fields. Evidence: existing/missing/raced deletion, exact response.
- [ ] **T25 — Two Select providers** (T07,T12,T20; AC10, AC11). Register all/active keys in existing registry; name ASC,id ASC; 200 cap + one, exact options, no unauthorized reads, no third provider. Evidence: 0/200/201, mixed authorization, unknown/duplicates/max keys, existing three Identity providers still pass.
- [ ] **T26 — Read/transport checkpoint** (T20–T25; AC07–AC11, AC15). Native HTTP tests all list/detail/filter/Select shapes, no catalogue mutation by reads, session-touch semantics preserved; run focused suites. Evidence: actual execution logs and query count assertions.

## Phase E — Write Actions and atomicity (T27–T34)

- [ ] **T27 — Catalogue write transaction boundary** (T19–T21; AC11, AC14). Reuse IdentityMutex and CurrentStaffMutation original binding and capability, short outer transaction, consistent locks, 5s wait and restoration/disconnect, rollback/commit outcome classification. No broad refactor or ordinary CRUD idempotency. Evidence: actual transaction/session security and wait/fault tests.
- [ ] **T28 — CreateActivityAction** (T08,T21,T27; AC02–AC04). All three mode combinations, explicit persistence/normalization, unique name arbitration and recognized duplicate 409, 201 data:[]; no implicit invalid coercion. Evidence: HTTP+DB snapshots including inactive duplicate and no duplicate row.
- [ ] **T29 — UpdateActivityAction** (T08,T21,T27; AC03–AC05). Recheck locked target, full replacement, clear disabled rate, require new enabled price, same-record/case rename, preserve on rejection. Evidence: HTTP success/422/409/404, active list/select state, exact DB values.
- [ ] **T30 — DeleteActivityAction** (T08,T20,T27; AC06). Recheck authorized locked target, unused hard delete, no fake dependency, no soft delete or cascade, second delete 404. Evidence: row removed, list/detail/select gone; later real FK obligations recorded.
- [ ] **T31 — Actual concurrent duplicate and rename races** (T28,T29; AC04, AC14). Distinct committed MySQL connections and HTTP workers, same/equivalent names, create vs rename, competing renames; exactly one succeeds when conflict, recognized unique path exercised. Evidence: observed overlap, final rows, HTTP outcomes/native code.
- [ ] **T32 — Concurrent update/delete and access revocation** (T27,T29,T30; AC05, AC06, AC11, AC14). Both serial orders update/delete, update/update and delete/delete; stale capability/role/session revoked before lock vs write before revoke; no unauthorized committed write, no stale session restoration. Evidence: real barriers, final DB/session state and native responses.
- [ ] **T33 — Actual fault and uncertain outcome matrix** (T27–T32; AC04, AC14). Real 1205/1213, known/unknown constraint faults, rollback after write, lost acknowledgement and postcommit session failure, timeout restoration/disconnect; distinguish acknowledged commit/rollback/unknown, no blind replay or leaked SQL. Evidence: actual DB and test-only PDO/barrier outcomes, snapshots, safe responses/logs.
- [ ] **T34 — Write checkpoint** (T28–T33; AC02–AC06, AC11, AC14). Run focused create/update/delete, validation/authority/fault/concurrency suites; no partial writes; future dependent protection not misreported as tested. Evidence: exact head test logs and outstanding gaps.

## Phase F — End-to-end verification and release review (T35–T42)

- [ ] **T35 — Independent RBAC and security matrix** (T18,T26,T34; AC10–AC14). Five separate permissions incl Manager/Reception defaults/custom subsets, forbidden target enumeration, disabled/revoked session, CSRF and forged grants, existing no direct grants/last-admin/founder constraints. Evidence: actual browser requests, data/session/grant snapshots.
- [ ] **T36 — Performance and query plans** (T26,T34; AC08–AC10, AC14, AC15). 200 and 2,000 guarded synthetic records; actual SQL/EXPLAIN/ANALYZE, row counts, query count, median/max time, memory, lock wait/hold/commit, concurrent RBAC effects. Assert bounded domain SELECT/materialization; measure total request separately. Evidence: environment/fixtures/plans/results; no fabricated SLA.
- [ ] **T37 — Index and locking review** (T36; AC01, AC15). Assess PK/unique sufficiency and broad Identity lock scope; propose additional index/strategy only if evidence warrants, request review for plan change. Evidence: measured decision and limitations, no speculative change.
- [ ] **T38 — Fresh/upgrade/migration and scope verification** (T05,T08,T09,T18,T34; AC01, AC12, AC13, AC15). Real guarded historical upgrade and fresh schema, preservation of previous roles/users/aliases/grants/session/migrations, exact two new tables, exact 15 permissions, 5 new routes, no other modules or packages. Evidence: schema/data diffs and migration markers; no production or unapproved reset/down.
- [ ] **T39 — CI exact-head regression** (T19,T26,T34–T38; AC01–AC15). Update CI exact module/route inventory, add Activities discovery/focused suite without skipping prior suites; retain Composer/audit/Pint, full PHPUnit, route cache and previous forward migration guards. Evidence: full actual job logs, counts, failures fixed and exact final head rerun green.
- [ ] **T40 — Acceptance reconciliation and documentation** (T39; AC01–AC15). Map every criterion to actual verified case/run/commit and limits; no checkmark on unexecuted tests. Update task status and single handoff; preserve module rules, record future real FK/snapshot tests and deferred audit/host risks. Evidence: completed matrix and docs diff.
- [ ] **T41 — Implementation PR review gate** (T40; AC01–AC15). Open/review feature PR against develop; exact allowed files, CI on current head, no unrelated changes; review release/rollback, provisioning marker/cache and host gates. Request explicit merge authorization; no automatic merge/deploy. Evidence: PR link, SHA, CI, reviewer approval.
- [ ] **T42 — Authorized final merge and tree verification** (T41; AC15). Only after explicit user approval merge reviewed head to develop and verify exact merged tree, update single handoff/PR evidence without recursive status-only commits. No production migration/provisioning/deploy. Evidence: merge SHA, matching tree, next-feature boundary.

## Acceptance traceability — all Not Run

| AC | Evidence-producing tasks | Status |
|---|---|---|
| AC01 schema/scope | T03–T09, T37–T39 | Not Run |
| AC02 create modes | T10,T21,T28,T34,T39 | Not Run |
| AC03 exact input | T04,T10,T11,T21,T28,T29,T34,T39 | Not Run |
| AC04 unique/race | T04,T08,T11,T28,T29,T31,T33,T39 | Not Run |
| AC05 full update | T21,T29,T32,T34,T39 | Not Run |
| AC06 unused delete | T24,T30,T32,T34,T39; real dependent checks deferred | Not Run |
| AC07 reads | T10,T20,T23,T24,T26,T39 | Not Run |
| AC08 filters/order | T04,T22,T23,T26,T36,T39 | Not Run |
| AC09 pagination | T22,T23,T26,T36,T39 | Not Run |
| AC10 Select | T25,T26,T35,T36,T39 | Not Run |
| AC11 authorization | T18,T20,T25–T27,T32,T35,T39 | Not Run |
| AC12 catalogue | T02,T12–T15,T19,T35,T38,T39 | Not Run |
| AC13 provisioning | T09,T14–T19,T35,T38,T39 | Not Run |
| AC14 concurrency/faults | T17,T18,T27,T31–T33,T35,T36,T39 | Not Run |
| AC15 regression/performance | T19,T26,T34,T36–T42 | Not Run |

## Evidence and release restrictions

Record for every completed task: exact source/head, command/test method, observed CI run/job link, pass/fail/assertion counts where relevant, actual database result/metrics, known limitations and follow-up. Baseline CI proves existing Identity behavior only. No fake FK tests or future acceptance claims. Do not mark any task done until its dependent evidence exists. Before production (not authorized): real MySQL/version/collation compatibility, automatic daily backup and tested restore/recovery, explicit one-time provisioning and controlled traffic gate, no silent regrants, host capacity/cookies/TLS/proxy checks and acknowledgement of deferred shared Audit. Final code merge does not equal production release.
