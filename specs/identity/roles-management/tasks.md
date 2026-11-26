# Feature 04 — Roles & Permissions Management: Tasks

> **Status:** APPROVED task breakdown (2026-11-26). **43/44 verified (T01–T43); 12/12 acceptance criteria verified on exact application/test source 1ad5649 / CI 45839858158 (206/16610, Pint 184).** T44 handoff documented; final documentation-only head CI/Ready for Review gate pending at publication, closed by verified live PR #12 evidence. No merge or deployment.
> **Sources:** [spec.md](spec.md), [plan.md](plan.md). Dependencies are numbered Txx (ranges inclusive); acceptance IDs abbreviated F04-xx. Do not treat review drafts or authored tests as completed tasks.
> **Gate:** D01–D08 and spec/plan/tasks approved; guarded disposable CI forward-drop testing authorized only. New destructive reset/down procedures are not authorized. Documentation gate passed; implementation authorized. Do not execute against a real or unknown DB.

## Phase A — Review and preparation (T01–T06)
- [x] **T01 — Review approved baseline and decisions.** Verify develop/PR #10 exact merge, lock, migrations, canonical specs and affected alias call sites; approve D01-D08. No implementation.
- [x] **T02 — Approve specification, plan and task scope.** Review docs PR; record explicit user approvals, no speculative scope or new dependencies.
- [x] **T03 — Authorize disposable destructive migration verification.** User authorized only the guarded CI forward DROP; record exact environment, required synthetic backup/export and preservation checks. No new reset/down procedures or real DB. Actual guard/fixture verification belongs to T05–T07.
- [x] **T04 — Documentation gate and implementation branch.** Merge approved docs only on explicit authorization; verify tree; create feature branch from develop after implementation approval.
- [x] **T05 — Inspect actual schema, collation and index metadata.** Read-only MySQL metadata in guarded disposable CI; exact role unique/FK/index names, collation equivalence, permission/morph guard and sessions owner index.
- [x] **T06 — Migration safety design and synthetic legacy fixture.** Fixture baseline roles (including differing aliases), grants, active/inactive members, sessions and permission aliases; snapshot values and backup/recovery proof.

## Phase B — Single Role name and backward regression (T07–T12)
- [x] **T07 — Forward migration remove roles.alias_name only.** Preserve historical migration; exact one-column change and no synthetic down restoration; verify schema/row/pivot/session preservation on isolated historical upgrade.
- [x] **T08 — Seeders and bootstrap compatibility.** Role seeds no alias; permission alias kept; repeat seeding preserves role names/state/grants, bootstrap prerequisites and permanent identity.
- [x] **T09 — Auth context and MeResource projection.** Remove Role alias field only; retain exact authentication/permissions/eligibility and no User ID.
- [x] **T10 — Users query and Resource compatibility.** Remove role alias projection/output; exact previous list/detail/nullable role and performance shape otherwise unchanged.
- [x] **T11 — Role Select labels and baseline fixture migration.** ID and eligibility stable, label name; adjust actual role-only fixtures/assertions across prior tests and guarded probes, preserve permission aliases.
- [x] **T12 — Baseline regression checkpoint after naming change.** F01/F02/F03 focused and full suite, schema/seed/Me/Users/Select checks; actual upgrade and fresh CI evidence.

## Phase C — Role transport, reads and Select Registry (T13–T22)
- [x] **T13 — Role route inventory and protected transport.** Five independent routes, native Cookie/CSRF/Origin/CORS, numeric ID contract, no target resolution before capability.
- [x] **T14 — Role capability and trusted target binding.** Independent authority from fresh context, exact guard/ID, stale and wrong guard handling; no hidden manager bypass.
- [x] **T15 — Role index input validation.** Allowlisted filters, literal search, state, bounded page/perPage, reject malformed/unknown sort/include.
- [x] **T16 — Mutation input contract and Arabic validation.** Strict JSON shape, name length/content/reserved/collation, typed bool, required list including [], no alias/guard/ID injection.
- [x] **T17 — Permission catalogue resolver.** Exact registered web names, strict uniqueness/guard and safe missing/anomalous state handling, no auto-seed.
- [x] **T18 — Role index query and batched counts.** Snapshot count/page/count maps, no N+1, exact count/ordering/status and out-of-range semantics.
- [x] **T19 — Detail query and Role Resource.** Snapshot detail counts and ordered permission names/aliases, exact safe projection and computed protected flag.
- [x] **T20 — Actual read HTTP integration.** Authorized index/show, forbidden lookup count, valid/invalid filters, exact Resources.
- [x] **T21 — PermissionOptionsProvider and registration.** Fixed approved keys, stored aliases, independent store/update purpose authorization, exact guard and deterministic options.
- [x] **T22 — Central Select Registry key limit and tests.** Max three, all-or-nothing validation/auth before dispatch; bounds/overflow/empty, preserve existing keys and ordering.

## Phase D — Transactions and write Actions (T23–T32)
- [x] **T23 — Current actor/session boundary refactor review.** Factor minimal shared authority primitive or dedicated Role boundary, do not call Users-target-specific lock unchanged; preserve F01-F03 contracts.
- [x] **T24 — Role transaction/lock/outcome boundary.** Manager mutex, compatible ordered rows/session locks, one attempt, timeout restore/disconnect, last-admin postcondition, known/unknown outcomes.
- [x] **T25 — Known constraint and safe error mapping.** Reviewed role name 1062 -> 422, invariant conflicts, 1205/1213/connectivity/unknown commit -> 503; safe unknown errors.
- [x] **T26 — CreateRoleAction with atomic grants.** Use native package, exact D01 approved grant scope, optional inactive per D03, no implicit assignment or direct grants.
- [x] **T27 — Protected role and custom rename lifecycle.** Protect manager/reception exact names and state; custom unique name stable ID, no session changes on rename.
- [x] **T28 — Role-disable revocation primitive.** All affected users incl inactive, safe version bounds, owner-index session deletion, single transaction, no account active-state changes.
- [x] **T29 — UpdateRoleAction grant replacement and status.** Atomic name/state/grants, disable only transition, initiating self-revoked handling after commit, re-enable no old sessions.
- [x] **T30 — DeleteRoleAction membership protection.** Zero assignments including inactive users, fail closed orphan/foreign morph, serialized native deletion, no permission definition deletion.
- [x] **T31 — Permission cache and commit-failure behavior.** Native package + postcommit/rollback eviction, cross-process visibility, safe known/unknown outcome, no blind retries.
- [x] **T32 — Wire write controllers and actual HTTP tests.** End-to-end JSON, all five independent capabilities, protected/error/validation cases, grant scope and last-admin.

## Phase E — Sessions, concurrency, faults, security, scale (T33–T40)
- [x] **T33 — Role lifecycle session matrix.** Affected/all devices/inactive users, unaffected others, no-change edit, disable->enable, self-role disable, stale native saves and old session rejection.
- [x] **T34 — Real MySQL race: unique names and role assignment/delete.** Independent connections/barriers both orderings; actual constraint or current-state conflict; no cascaded assignment loss.
- [x] **T35 — Real MySQL race: role disable/Login/Profile.** Both winner orderings, concurrent enable/assignment, old credentials/session generation non-restoration, stale actor rejection.
- [x] **T36 — Real MySQL race: grants/last admin and Users mutations.** Competing administrative changes across Role/User endpoints, role grants revoked while actor waits, last viable custom path, exact rollback.
- [x] **T37 — Fault injection and transaction outcomes.** Actual DB/pivot/session failure, native deadlock/timeout, cache/commit acknowledgement probes; state checks and redaction, no 500-only proof.
- [x] **T38 — Security and boundary regression.** Native CSRF/origin and capability ordering, wrong guard/anomalies, request IDs, safe Arabic responses, no sensitive bindings, no production probes.
- [x] **T39 — Scale fixture and actual list query plans.** 10/100/200 roles and 100/1k/10k users, 15/100 pages, search/filters/skew, EXPLAIN+ANALYZE, count/query ceiling.
- [x] **T40 — Role-disable performance and cache measurement.** Synthetic 1/100/1k target users, measured lock/query/memory/commit; no speculative new index or live SLA.

## Phase F — Full verification and review (T41–T44)
- [x] **T41 — Final schema/routes/scope and dependency regression.** Only one forward column removal and five Role routes, exact ten permissions, no new packages/tables/modules, previous F03 behavior apart from approved shape.
- [x] **T42 — Scoped CI discovery and full regression.** Guarded real MySQL upgrade/fresh as authorized, Role focused, existing Auth/Identity/Users/Avatar/Select, full PHPUnit, Pint, Composer audit/platform, route cache/scope.
- [x] **T43 — Acceptance reconciliation and draft PR evidence.** Inspect exact head and logs; map 12 criteria to actual assertions and measured evidence, close only verified tasks; record risks and blockers.
- [ ] **T44 — Final handoff and review gate.** Handoff and review evidence published in this docs-only checkpoint; last exact-head CI and Ready for Review transition must be verified in [PR #12 checks](https://github.com/m-hassan00096/kids-area-api/pull/12/checks). Live PR evidence closes T44 without a recursive status-only commit. Explicit merge approval still required; no deployment.

## Dependencies and evidence matrix

| Task | Depends on | Acceptance | Completion evidence |
| --- | --- | --- | --- |
| T01 | — | F04-01–12 | Verify develop/PR #10 exact merge, lock, migrations, canonical specs and affected alias call sites; approve D01-D08. No implementation. |
| T02 | 01 | All | Review docs PR; record explicit user approvals, no speculative scope or new dependencies. |
| T03 | 02 | 11,12 | Exact CI host/database/principal/APP_ENV guard, backup/export synthetic legacy aliases; separately authorize DROP and any isolated reset/rollback. No real DB. |
| T04 | 02,03 | 12 | Merge approved docs only on explicit authorization; verify tree; create feature branch from develop after implementation approval. |
| T05 | 04 | 02,04,11 | Read-only MySQL metadata in guarded disposable CI; exact role unique/FK/index names, collation equivalence, permission/morph guard and sessions owner index. |
| T06 | 03,05 | 11 | Fixture baseline roles (including differing aliases), grants, active/inactive members, sessions and permission aliases; snapshot values and backup/recovery proof. |
| T07 | 06 | 11 | Preserve historical migration; exact one-column change and no synthetic down restoration; verify schema/row/pivot/session preservation on isolated historical upgrade. |
| T08 | 07 | 11,12 | Role seeds no alias; permission alias kept; repeat seeding preserves role names/state/grants, bootstrap prerequisites and permanent identity. |
| T09 | 07 | 11,12 | Remove Role alias field only; retain exact authentication/permissions/eligibility and no User ID. |
| T10 | 07 | 11,12 | Remove role alias projection/output; exact previous list/detail/nullable role and performance shape otherwise unchanged. |
| T11 | 07 | 10,11,12 | ID and eligibility stable, label name; adjust actual role-only fixtures/assertions across prior tests and guarded probes, preserve permission aliases. |
| T12 | 08–11 | 11,12 | F01/F02/F03 focused and full suite, schema/seed/Me/Users/Select checks; actual upgrade and fresh CI evidence. |
| T13 | 04,12 | 06,12 | Five independent routes, native Cookie/CSRF/Origin/CORS, numeric ID contract, no target resolution before capability. |
| T14 | 13 | 06 | Independent authority from fresh context, exact guard/ID, stale and wrong guard handling; no hidden manager bypass. |
| T15 | 13 | 01 | Allowlisted filters, literal search, state, bounded page/perPage, reject malformed/unknown sort/include. |
| T16 | 13 | 02,03,05,06 | Strict JSON shape, name length/content/reserved/collation, typed bool, required list including [], no alias/guard/ID injection. |
| T17 | 16 | 05 | Exact registered web names, strict uniqueness/guard and safe missing/anomalous state handling, no auto-seed. |
| T18 | 05,15 | 01 | Snapshot count/page/count maps, no N+1, exact count/ordering/status and out-of-range semantics. |
| T19 | 14,18 | 01,05 | Snapshot detail counts and ordered permission names/aliases, exact safe projection and computed protected flag. |
| T20 | 13–19 | 01,06 | Authorized index/show, forbidden lookup count, valid/invalid filters, exact Resources. |
| T21 | 11,17 | 10 | Fixed approved keys, stored aliases, independent store/update purpose authorization, exact guard and deterministic options. |
| T22 | 21 | 10 | Max three, all-or-nothing validation/auth before dispatch; bounds/overflow/empty, preserve existing keys and ordering. |
| T23 | 12,14 | 06,08,09 | Factor minimal shared authority primitive or dedicated Role boundary, do not call Users-target-specific lock unchanged; preserve F01-F03 contracts. |
| T24 | 23 | 06,08,09 | Manager mutex, compatible ordered rows/session locks, one attempt, timeout restore/disconnect, last-admin postcondition, known/unknown outcomes. |
| T25 | 05,16,24 | 02,04,05,09 | Reviewed role name 1062 -> 422, invariant conflicts, 1205/1213/connectivity/unknown commit -> 503; safe unknown errors. |
| T26 | 17,24,25 | 02,05,08 | Use native package, exact D01 approved grant scope, optional inactive per D03, no implicit assignment or direct grants. |
| T27 | 16,24,25 | 03,04,08 | Protect manager/reception exact names and state; custom unique name stable ID, no session changes on rename. |
| T28 | 23,24 | 07,08,09 | All affected users incl inactive, safe version bounds, owner-index session deletion, single transaction, no account active-state changes. |
| T29 | 17,24,27,28 | 03,05,07,08 | Atomic name/state/grants, disable only transition, initiating self-revoked handling after commit, re-enable no old sessions. |
| T30 | 24,25,27 | 04,08,09 | Zero assignments including inactive users, fail closed orphan/foreign morph, serialized native deletion, no permission definition deletion. |
| T31 | 24,26,29,30 | 05,06,09 | Native package + postcommit/rollback eviction, cross-process visibility, safe known/unknown outcome, no blind retries. |
| T32 | 26–31 | 02–08 | End-to-end JSON, all five independent capabilities, protected/error/validation cases, grant scope and last-admin. |
| T33 | 28,29,32 | 03,07,08 | Affected/all devices/inactive users, unaffected others, no-change edit, disable->enable, self-role disable, stale native saves and old session rejection. |
| T34 | 24,26,27,30,32 | 02,04,09 | Independent connections/barriers both orderings; actual constraint or current-state conflict; no cascaded assignment loss. |
| T35 | 28,29,33 | 07,09 | Both winner orderings, concurrent enable/assignment, old credentials/session generation non-restoration, stale actor rejection. |
| T36 | 24,29,30,32 | 06,08,09 | Competing administrative changes across Role/User endpoints, role grants revoked while actor waits, last viable custom path, exact rollback. |
| T37 | 31,32,33 | 05,07,09 | Actual DB/pivot/session failure, native deadlock/timeout, cache/commit acknowledgement probes; state checks and redaction, no 500-only proof. |
| T38 | 20,22,32–37 | 06,09,10,12 | Native CSRF/origin and capability ordering, wrong guard/anomalies, request IDs, safe Arabic responses, no sensitive bindings, no production probes. |
| T39 | 18,19,20 | 01,12 | 10/100/200 roles and 100/1k/10k users, 15/100 pages, search/filters/skew, EXPLAIN+ANALYZE, count/query ceiling. |
| T40 | 28,29,31,33,39 | 07,09,12 | Synthetic 1/100/1k target users, measured lock/query/memory/commit; no speculative new index or live SLA. |
| T41 | 12,22,32,38–40 | 11,12 | Only one forward column removal and five Role routes, exact ten permissions, no new packages/tables/modules, previous F03 behavior apart from approved shape. |
| T42 | 03,41 | 12 | Guarded real MySQL upgrade/fresh as authorized, Role focused, existing Auth/Identity/Users/Avatar/Select, full PHPUnit, Pint, Composer audit/platform, route cache/scope. |
| T43 | 34–42 | All | Inspect exact head and logs; map 12 criteria to actual assertions and measured evidence, close only verified tasks; record risks and blockers. |
| T44 | 43 | All | Update same handoff and PR with exact counts/links, final doc-only CI if needed; request explicit merge approval, no deployment. |

## Acceptance status

| Scenario | Status |
| --- | --- |
| F04-01 | Verified |
| F04-02 | Verified |
| F04-03 | Verified |
| F04-04 | Verified |
| F04-05 | Verified |
| F04-06 | Verified |
| F04-07 | Verified |
| F04-08 | Verified |
| F04-09 | Verified |
| F04-10 | Verified |
| F04-11 | Verified (synthetic CI only) |
| F04-12 | Verified (CI 45839858158) |

## Completion rules

Record actual commit, command, CI run/job, pass/fail counts, relevant assertions and limitations. No task marked done merely because its code or a test was written. No unsafe data deletion or scope change; no weaken existing assertions. Final required CI exact head must succeed before Ready for Review; no merge without separate explicit user approval, no production deployment. Keep prior F02 intermittent observation risk, deferred Audit and real-host/backup/restore caveats in final report. Next action: verify the documentation-only head's required CI, record T44 and Ready for Review in PR #12 (no recursive documentation push), then request explicit merge approval. No real DB.


## Approval evidence (2026-11-26)

T01: verified PR #10 merged at develop 41b5eeca7e9b04eb7b064f93464b290754fea16a, reviewed actual pinned dependencies, historical migrations, current Identity services and role-only alias call sites, canonical documents and the eight decision defaults. T02: the user replied affirmatively to the explicit request to approve spec.md, plan.md, tasks.md and proposed decisions. T03: the same reply authorized the specifically requested test of removing roles.alias_name only inside isolated GitHub Actions MySQL 8 test DB. Existing CI guard and guarded IdentityDatabaseTestCase fixture initialization inspected; planned pre-drop export and preservation checks remain T05–T07, not claimed executed. No new destructive reset/down procedures authorized; use fresh CI lifecycle and existing approved fixture setup. No code, real DB, merge or deployment. All 12 acceptance criteria Not Run. T04 requires exact head green CI and explicit documentation merge/implementation approval. Historical F03 test results are not F04 evidence.


## Implementation checkpoint — T04 verified; T05/T06 authored (2026-11-26)

User explicitly authorized docs merge and implementation. Exact docs head 772d084d8becccb52dc4166f42b7e69b29d4f6ae [CI 45818002441](https://github.com/m-hassan00096/kids-area-api/actions/runs/45818002441) completed green, job 139902613229: full 174/9042, Authentication 41/1446, Identity 56/828, focused 73/6757, Pint 159; all steps passed. PR #11 merged at 92f8845a5cb15526965448eab93735902936d612, verified tree identical to reviewed head and develop ref exact; implementation branch feature/identity-roles-management created from that merge. T04 complete. Added only CI/test support preflight: fresh legacy schema after reviewed existing migrations, recheck GitHub Actions/APP_ENV/host/DB/principal/DB_URL/MySQL8/InnoDB, verify actual column/index/FK metadata and collation equivalence, synthetic legacy roles and changed permission alias, four Users with distinct active state/generation and two actual native authoritative browser sessions, export aliases and per-table non-alias state fingerprints to exclusive private runner-temp file and verify round-trip. No new reset/down/drop, no production code, no real DB. T05/T06 remain open until exact CI logs inspected; backup round-trip is not actual production restore or forward preservation proof. T07 and all acceptance criteria Not Run.


## T05 verified and T06–T12 migration checkpoint (2026-11-26)

Exact preflight head d1760d4977189c382265caf09031be65bcbc1271, CI [45819970456](https://github.com/m-hassan00096/kids-area-api/actions/runs/45819970456), job 139909399781. Authentication 41/1469, Identity 56/828, focused 73/6809, full 174/9116, Pint 160 passed. **Do not equate green workflow with complete preflight.** Actual log contains ROLES_SCHEMA_METADATA and ROLES_NAME_EQUIVALENCE but no ROLES_LEGACY_FIXTURE; standalone script reported Synthetic role fixture handshake failed, and Laravel's console exception rendering left the step apparently successful. Source confirmed the preflight used a nonconforming runner-temp barrier directory, whereas the guarded HTTP router strictly requires sys_get_temp_dir()/kids-auth-*; corrected it, without weakening the router. Explicit nonzero exception handling plus required completion-marker checks now prevent an incomplete script passing. T06 not verified yet.

T05 actual MySQL 8.0.46, InnoDB. roles.name/guard_name/alias_name VARCHAR(255) NOT NULL utf8mb4_unicode_ci; is_active tinyint(1) default 1; permissions.alias_name unchanged. Existing unique roles_name_guard_name_unique(name,guard_name), PK model_has_roles(role_id,model_id,model_type), identity_single_role_unique(model_type,model_id), role_has_permissions PK(permission_id,role_id), implicit leading role_id index role_has_permissions_role_id_foreign, sessions_auth_owner_index(auth_user_id,id), exact FK cascade references to roles and permissions verified. Actual collation equates manager, MANAGER, mánaGer and manager with trailing space; reserve equivalent variants using actual DB collation, never PHP-only comparison. No new index indicated by metadata. T05 complete.

Authored T06–T11 coherent migration/compatibility checkpoint: new forward-only migration 2026_11_26_000008_remove_alias_name_from_roles; down intentionally refuses fabricated data restoration. Historical migrations unchanged. CI applies only seven historical migrations to fresh guarded service, prepares four Roles, ten Permissions, four Users and real native browser sessions, exports private synthetic alias map and state fingerprints, then executes only new forward migration after successful backup; compares all non-alias data across eight tables plus column/index/FK metadata, tests existing sessions and both Role Selects after drop. Existing reviewed fixture resets remain; no new reset/down path. Production seed, IdentityAccess, MeResource, UsersQuery, UserManagementResource and RoleOptionsProvider updated to name-only. Role-only test fixtures/expected projections and query counter updated; historical avatar migration regression specifically uses pre-F03 legacy role fixture and runs only the historical avatar migration to preserve exact earlier assertion. Permission aliases/seed and other checks retained. All 12 F04 acceptance criteria remain Not Run pending composite evidence; T06–T12 unchecked until actual exact CI logs. No real database operation, dependency or application merge.


## T06–T07 verified; historical Authentication migration regression repair (2026-11-26)

Exact implementation head 41c6b1f78c4d40f42403276ef153fbf57699b8f5, [CI 45821490179](https://github.com/m-hassan00096/kids-area-api/actions/runs/45821490179), job 139914593558. Actual ROLES_LEGACY_FIXTURE and ROLES_FORWARD_MIGRATION markers both present, required guarded preflight/forward step succeeded. MySQL 8.0.46 / InnoDB, synthetic backup round-trip of four legacy aliases succeeded. Before and after raw non-alias fingerprints and counts exactly equal: roles 4, permissions 10, users 4 (including inactive and inactive-role fixtures), authoritative sessions 2, model_has_roles 4, model_has_permissions 0, role_has_permissions 11, identity_login_throttles 3. Verified exact migration inventory, roles.alias_name removed, permissions.alias_name and customized stored alias retained, all other column definitions/indexes/FKs unchanged, same two existing browser sessions still authorize identical Me payloads, both Role Select name/eligibility outputs correct. T06–T07 complete for the authorized synthetic CI environment. No real DB migration or production recovery proof; backup is synthetic, not a host backup. Existing F04 drop remains intentionally non-reversible without real approved backup/restoration.

Overall workflow FAILED: focused Authentication 40 passed / 1 failed, 1432 assertions; Identity, Users/Selects, full and Pint skipped. Actual failure AuthenticationSchemaTest:18 invoked historical migrate:rollback --step=4 after F04 fresh setup, hitting intentionally non-reversible Role alias down(). Not a failure of the forward migration. Preserve the historical F01→F02/F03 regression: allow only this existing test to choose exact three historical migration paths during the existing guarded fixture initialization instead of rolling back newer migrations; populate native legacy Role/Permission fixtures as necessary; apply exact four historical forward migrations and retain original User ID/hash/phone/role/auth_version assertions plus explicit legacy schema checks. No new reset/down operation. Tighten inherited fixture reset guard to GITHUB_ACTIONS=true, exact 127.0.0.1, DB_URL empty and verified kids_area_ci server principal before any reset; new drop remains CI-only. No production code change in this repair. T08–T12 and all composite acceptance criteria remain pending until exact repaired CI; T13 onward blocked by baseline regression dependency. No merge/deployment.


## Historical fixture insert repair (2026-11-26)

Exact head 08882d24953379760bbca820d7901a91734980b7, [CI 45823930646](https://github.com/m-hassan00096/kids-area-api/actions/runs/45823930646), job 139923059377. Required pre-drop backup and forward-preservation markers present again (four aliases, eight preserved table fingerprints/counts, two native sessions, both Role Selects, schema/index/FK and permission alias preservation). T06–T07 remain verified. Focused Authentication: 40 passed, 1 failed, 1565 assertions. Prior rollback failure resolved: exact historical F01 fixture schema reached. New actual error 1364 in AuthenticationSchemaTest:40: native Role::create generated INSERT without alias_name against legacy NOT NULL column despite fixture attributes. Later required checks skipped; no overall green CI. Identical historical Role::create path also existed in UsersManagementSchemaTest. Repair both test-only historical fixtures with explicit DB table inserts for legacy Role/Permission fields (the already-proven preflight fixture pattern), assert stored aliases/catalogue, then native Role/Permission models and syncPermissions. Keep production Role, Permission, seeder and migration unchanged, retain all prior historical migration assertions; no new destructive operation or reset. T08–T12 remain unchecked, all 12 F04 acceptance criteria Not Run pending composite verification. T13+ baseline gate remains. No real database or deployment.


## Baseline regression verified; Roles reads/Select checkpoint (2026-11-26)

Exact tested source 2855a39a281f4f4101479f4defff042b6ee0237e, [CI 45830926166](https://github.com/m-hassan00096/kids-area-api/actions/runs/45830926166), job 139947167836: all steps successful, actual required legacy fixture and forward-preservation markers present. Authentication 41/1563, Identity 56/1002, focused Users/Avatar/Select 73/6970, full PHPUnit 174/9536, Pint 161 files, locked Composer audit/platform and route/module/scope passed. Historical F01/F02/F03 migration tests passed without dropping legacy alias via rollback. T08–T12 complete on verified source. Migration preservation plus actual exact Auth/Users/Role Select resources verified on synthetic CI; F04-11 remains a composite release criterion until final reconciliation, no real data/host recovery proof.

Next implementation checkpoint: two protected GET Role routes only, capability-before-target lookup, exact web guard/numeric ID, strict read query allowlist, literal name search, 0/1 status, read-only primary repeatable-read snapshot and batched independent User/grant counts (target four domain SELECTs for nonempty pages, one for empty/out-of-range), explicit Role Resource, detail ordered catalogue grants with permission alias preserved. Shared exact catalogue query constraint; new permissions Select provider (store OR update), max three keys, existing all-or-nothing and 200 cap retained. Extended first-party transport and sensitive Role query boundary, exact read-route scope, native browser tests for capabilities/lookup order, invalid inputs, counts including inactive Users, orphan/foreign-morph and wrong-guard/unregistered grant exclusion, page bounds, query counts and permissions provider key/order/overflow. No write routes/actions yet; T13 and T14 remain partially implemented, T15/T18–T22 authored, not closed before actual new CI; T16/T17 write-specific validation/resolution pending. No new schema/dependency, no real DB, merge or deployment.


## Roles read/Select verified; write transaction checkpoint (2026-11-26)

Exact head 683602838e8526092cc777a9343bd81cdcc1a3ec, [CI 45832455232](https://github.com/m-hassan00096/kids-area-api/actions/runs/45832455232), job 139952464156: all steps passed; migration preservation marker repeated. Authentication 41/1574, Identity 56/1004, focused Users/Avatar/Select 75/7106, RolesRead 3/242, full PHPUnit 179/9924, Pint 169. T15 and T18–T22 verified (literal/strict filters, four domain SELECTs nonempty list/one empty, detail/counts/resources, independent read authority and no target lookup on denial, exact catalogue Select, authorized combination/order/bounds). T13/T14 remain open for full write coverage. Final composite acceptance reconciliation still pending; historical regression verified; corrected stale progress summaries.

Write checkpoint authored: strict JSON FormRequests, exact registered permission names/native booleans, empty array allowed, current collation duplicate/reserved checks, protected roles, missing catalogue and anomalous target grants fail closed; three independent write routes. CurrentStaffMutation::lockPrepared extracts shared preparation point while preserving Users behavior, locks actor/access rows before initiating authoritative session and defers expected business/validation preparation errors until current session check. IdentityAccessMutation::executeRoles uses manager mutex/postcondition, prelocks essential viability rows and permanent roles before session, bounded 5s lock wait, one transaction attempt, safe rollback/commit ambiguity and exact role constraint mapping, cache eviction after outcome. Create uses native Role model and exact permission models without implicit assignment; Update replaces grants/name/status atomically, prelocks members including inactive users and uses existing CredentialRevocation once per true-to-false transition; self-revocation clears browser only after known commit. Delete rejects any membership row, protected roles and anomalous grants. Added Arabic messages and safe correlated role mutation outcome reporting; no audit persistence. Added native HTTP tests for actual CRUD, strict shape/collation, catalogue, independent delegation, CSRF/CORS, protected/assigned roles, last-admin rollback, ordinary/disabled/re-enabled and self-revoked sessions. All new write tasks remain unchecked until actual exact-head CI; full race, fault/cache/commit ambiguity, scale and final verification still required. No new package/table/index/permission definition, production data or merge.


## Role write functionality verified; concurrency and fault checkpoint (2026-11-26)

Exact head 1f71d19d11ce9ad1f0c02ac3f836400d048d8f6d, [CI 45834607444](https://github.com/m-hassan00096/kids-area-api/actions/runs/45834607444), job 139960048501: required guarded migration preservation passed, Composer locked audit passed, Authentication 41/1572, Identity 56/1007, Users/Avatar/Select 75/7109, focused Roles 14/812, full PHPUnit 185/10320. All functional tests passed. **Workflow overall failed only at Pint** (178 files, one ordered_imports issue in RolesController.php; subsequent modular/route-cache and scope steps skipped). Fixed the exact import order shown by Pint. No test waiver. Close T13, T16, T17, T23 and T27 on their actual HTTP/validation/authority/legacy regression/protected rename evidence; 23/44 verified. Other write tasks remain open for constraint/failure, serialized races, full session matrix and outcome evidence; the previous completed tasks remain supported by the earlier green checkpoint. No claim of final CI success or complete acceptance criteria.

Authored nine real HTTP concurrency tests using the existing distinct MySQL connection/barrier harness: duplicate creates and create/rename both orders under actual collation; delete/assign and disable/assign both orders; disable/Login and disable/Profile password change both orders (Profile's existing current-eligibility rejection is 403 when disable wins, then old session 401); actual late native anonymous INSERT after Role disable and subsequent re-enable; stale granted actor waiting for role mutation loses authority without logout; two opposing Role/User removals of the last viable path in both orders. Added five actual HTTP fault tests: role row/grant insertion, generation, authoritative session deletion and role deletion faults compare exact pre/post role/user/session/pivot snapshots; actual named 1062 via a synthetic same-transaction insert after uniqueness precheck; real 1205 and timeout restoration fault; actual 1213 deadlock using a guarded external fixture transaction with 100 synthetic throttle rows and inverse manager/User lock order (existing tables only, no DDL or new reset); acknowledged COMMIT followed by cache failure, real COMMIT with simulated lost acknowledgement, and native final session-save fault after known self-disable. Record native MySQL code/attempt count and safe correlated outcome headers only in test router. Strengthened test router actual server principal and Role test-mode CI guard. No production logic change besides import ordering; no new table/index/dependency or permission. All new tests Not Run on this new head, not counted complete. Real shared file-cache repopulation, generation-bound/anomaly expansion, scale/EXPLAIN, final exact CI and release reconciliation remain outstanding. No real database operation, merge or deployment.


## Shared-cache, scale and remaining security checkpoint (2026-11-26)

Exact preceding head 96561a6c96ac67cc99d6419f908fbd56b467dc80, [CI 45837355195](https://github.com/m-hassan00096/kids-area-api/actions/runs/45837355195), job 139969629063, was still in progress at inspection. Actual step statuses: guarded legacy backup/forward preservation, focused Authentication, Identity, Users/Avatar/Select and Roles discovery/execution all successful; full PHPUnit in progress, Pint/module/scope checks not finished. Job log download still unavailable (GitHub returned 404 BlobNotFound while running). This supports focused step success, not exact test/assertion counts or a complete green workflow. Keep 23/44 tasks verified until detailed evidence is reconciled. No failure waiver, no premature acceptance checkmarks.

Authored seven additional test methods, confined to the existing guarded synthetic MySQL and HTTP harness, with no production-code change. RolesCacheTest uses native Spatie PermissionRegistrar and an isolated shared file cache across distinct HTTP workers; PDO delegate pauses at actual commit/rollback boundary after native package eviction. Observer repopulates the previous committed grants, then tests required postcommit/rollback eviction, fresh package grants and actual protected API authorization/session behavior. Existing application default and production cache configuration unchanged. Test cache directory lives under the verified temporary fixture path and is removed with existing fixture teardown. RolesSecurityTest tests no-op edits, auth_version zero/PHP_INT_MAX fail-closed whole mutation, orphan/foreign-morph membership and wrong-guard write targets, stale actor after role disable, concurrent disable/re-enable in both transition directions and assignment while the inactive role's enable is uncommitted. RolesPerformanceTest pairs 10 roles/100 Users, 100/1000 and 200/10000, including strongly skewed membership, 15/100 pages, deep and empty pages, literal wildcard search, Arabic/status filters and combinations; 30 list cases, three detail and three combined Select cases. Capture actual service SELECT SQL only inside guarded synthetic test, execute EXPLAIN and EXPLAIN ANALYZE, verify exact resources, four nonempty list SELECTs/one empty, three detail domain + one admission, one snapshot setup, three bounded providers. Measure role-disable for 1, 100 and 1000 affected Users (each includes one actual native session plus 2 synthetic binding rows per additional account; synthetic payloads are intentionally not native cookies), checking one generation increment and indexed session DELETE per member, unaffected sessions/accounts, reactivation without old authority, membership and password preservation. Extend existing test-only PDO measurement for actual manager acquisition-to-commit acknowledgement, commit time, query counts, PHP peak/RSS. Keep linear 2N+100 query ceiling as a test regression guard, not a real host SLA. Measure before considering optimization; no new index or query architecture change. All seven new tests and metrics Not Run until exact-head CI. No new database reset/down, table, dependency, permission, or real data operation.


## Verified functional, concurrency, cache and performance evidence (2026-11-26)

Green preceding source 96561a6c96ac67cc99d6419f908fbd56b467dc80, [CI 45837355195](https://github.com/m-hassan00096/kids-area-api/actions/runs/45837355195), job 139969629063. All steps passed: Authentication 41/1585, Identity 56/1007, Users/Avatar/Select 75/7122, Roles 28/1688, full 199/11191, Pint 180 files, Composer lock/platform/audit clean, 19 routes before/after cache, module/scope checks passed. Exact backup and forward-preservation markers present. All five native fault and nine concurrency tests passed in both focused and full runs.

Exact measured source be4b16d8c0d7a6b5966f9d43dc1d798c901ad2ac, [CI 45838272845](https://github.com/m-hassan00096/kids-area-api/actions/runs/45838272845), job 139972820223. Actual log inspected: guarded upgrade/backup/preservation markers present again, Authentication 41/1577, Identity 56/1007, Users/Avatar/Select 75/7118, Roles 35/7019, full PHPUnit 206/16517; all functional tests passed, Composer validate/platform/audit passed. Both native shared-file-cache commit/rollback tests, both scale/disable tests and all three security/generation/re-enable tests passed in both runs. **Overall CI failed only Pint (184 files, one class_attributes_separation issue: extra blank line before closing class in RolesSecurityTest.php).** Subsequent module/route-cache/scope steps skipped. Exact one-line whitespace removal in the test, no production or assertion change. Final exact-head green gate remains. Close T01–T40 on actual evidence; final release tasks T41–T44 remain open. F04-01–11 verified in the synthetic CI environment, F04-12 pending exact final green workflow. Do not claim deployment, real-data restore proof, or final merge readiness yet.

### Observed read measurements (two executions, focused and full)

| Roles / Users | List HTTP range (ms), all 10 cases | Nonempty direct service range (ms) | Detail HTTP (ms) | Detail assigned Users | Combined Select HTTP (ms) |
| --- | ---: | ---: | ---: | ---: | ---: |
| 10 / 100 | 12.972–31.823 | 2.311–4.660 | 14.813–14.912 | 79 | 16.619–22.243 |
| 100 / 1,000 | 10.866–26.789 | 2.529–6.033 | 12.763–29.232 | 720 | 14.426–16.464 |
| 200 / 10,000 | 11.063–63.760 | 2.820–20.635 | 18.106–21.038 | 7,200 | 16.656–42.478 |

60 list samples (30 distinct cases twice), six detail and six combined Select samples. Each nonempty list exactly four domain SELECTs plus one snapshot setup, 12 total instrumented HTTP SQL statements; empty/out-of-range one domain SELECT, nine total. Detail three domain SELECTs plus one admission and snapshot setup; three bounded provider reads, LIMIT 201, exact options counts at largest scale [134,200,10]. 222 actual service SELECTs each EXPLAINed and EXPLAIN ANALYZEd (111 per run), not 222 different query shapes. Representative largest full-run first100 count uses covering roles_name_guard_name_unique (200 rows, actual count 0.0496ms), page PRIMARY backward scan (100 rows, actual limit 0.0424ms), membership aggregate optimizer chooses identity_single_role_unique on exact morph candidate (10,000 indexed candidates, 8,100 matching page roles joined by users.PRIMARY, temporary aggregate 9.65ms), grants aggregate uses role_has_permissions_role_id_foreign (101 matched grants, actual group aggregate 0.212ms) and permissions.PRIMARY. Across 60 count plans 42 covering index scans and 18 small roles table scans; page PRIMARY all 48 nonempty cases; membership PRIMARY range/ref/index or identity_single_role_unique ref depending on fanout, not universally a role_id range. All 54 User joins eq_ref PRIMARY. All 48 grant aggregates use role_id foreign-key index scan/range, all six detail grant queries use ref on it. Literal contains search may scan the bounded small roles catalogue; do not claim substring search is indexed. Current measurements do not justify a speculative extra index for the approved single-site V1. Review fanout and actual plans if cardinality/traffic changes.

### Observed role disable measurements (two executions)

| Affected Users | Binding rows removed | HTTP wall ms | Instrumented SQL statements | Manager acquisition-to-commit ack ms | Commit ms | PHP peak MiB | Linux process peak RSS KiB |
| ---: | ---: | ---: | ---: | ---: | ---: | ---: | ---: | ---: |
| 1 | 1 | 56.780–57.036 | 47 | 31.925–32.615 | 0.686–1.020 | 4 | 112804–113252 |
| 100 | 199 | 136.659–138.639 | 245 | 112.718–114.071 | 1.068–1.107 | 4 | 113008–113016 |
| 1,000 | 1,999 | 733.008–800.062 | 2045 | 708.730–775.766 | 3.005–8.389 | 6 | 112956–112964 |

One real native browser per disable size and two explicitly synthetic binding rows per additional account (not real browser cookies). Verified one generation UPDATE and one indexed owner DELETE per member, unchanged memberships/password/active flags, unaffected native session preserved, re-enable does not revive old session. Actual manager wait 0.102–0.125ms in these uncontended cases. The measured largest disable used 1,103 total Users in DB, not 10,000; paired 10,000 dataset is a separate read test. The 2N+constant query growth (2,045 at 1,000) is intentional reuse of existing per-account CredentialRevocation within ONE transaction, not an N+1 read bug; broad currentHasAll staff locking and ~0.71–0.78s manager lock span are write-cost watchpoints. PHP peak excludes some native/runtime memory; observed Linux worker RSS ~110 MiB, not 6 MiB total process. Two CI samples, no p95/p99, concurrent throughput, production capacity/SLA or actual cPanel measurements. Keep current design at measured V1 scope, revisit bounded set-based revocation only after reviewed evidence and same atomic/overflow/affected-row guarantees. No new index/dependency/table, no architecture change.

### Acceptance reconciliation on actual CI evidence

| Criterion | Evidence | Result |
| --- | --- | --- |
| F04-01 | RolesReadHttpTest exact resources, literal filters, counts, pagination; RolesPerformanceTest 30 cases and plans twice | Verified |
| F04-02 | RolesWriteHttpTest Arabic/English name lifecycle, default/explicit inactive, empty grants, actual collation; RolesConcurrencyTest both name race orders and RolesActualFaultTest native 1062 | Verified |
| F04-03 | RolesWriteHttpTest ID-preserving rename/grant edit, active session and member preservation, permanent role guards; RolesSecurityTest wrong guard and no-op | Verified |
| F04-04 | RolesWriteHttpTest protected/assigned delete and definition preservation; RolesSecurityTest orphan/foreign morph; RolesConcurrencyTest delete/assign both orders | Verified |
| F04-05 | RolesWriteHttpTest strict catalogue/body and atomic replacement, RolesActualFaultTest grant rollback, RolesSecurityTest anomalies | Verified |
| F04-06 | RolesReadHttpTest and RolesWriteHttpTest independent five capabilities, no early target lookup, native CSRF/origin/preflight; RolesConcurrencyTest revoked grant, RolesSecurityTest stale disabled actor | Verified |
| F04-07 | RolesWriteHttpTest affected/inactive/self-revocation, RolesConcurrencyTest late save, RolesSecurityTest version bounds/re-enable and RolesPerformanceTest one/100/1000 disable and unaffected sessions | Verified |
| F04-08 | RolesWriteHttpTest protected last path and self-disable rollback, RolesConcurrencyTest opposing Role/User last-admin mutations both orders | Verified |
| F04-09 | RolesConcurrencyTest nine cases, RolesActualFaultTest five cases including actual 1062/1205/1213 and outcome/redaction; RolesSecurityTest concurrent enable/disable and RolesCacheTest real shared native file-cache commit/rollback repopulation | Verified |
| F04-10 | SelectRegistryHttpTest/ProviderTest independent capabilities, exact aliases/names, malformed/bounds, combinations; RolesPerformanceTest three bounded providers | Verified |
| F04-11 | Actual preflight/forward markers, eight fingerprints, four alias backup round-trip, two native sessions, exact schema/index/FK and resource/seed regression | Verified (synthetic only) |
| F04-12 | Full 206/16517 passed, static scope audit and prior green 19-route cache; latest failed one Pint blank line; exact corrected-head CI still required | Pending final CI |

Review limitations: approved role alias removal is a breaking API contract; coordinate clients. Before any real DROP, inspect actual legacy alias/name mappings with separate approval, decide mismatches, take/verify host backup, review restore with compatible application version; no real restoration proven and down intentionally refuses fabricated data. Shared-cache test uses isolated file storage on CI; host filesystem/permissions/TLS/proxy/native codec and memory/capacity validation remain separate deployment gates. Native JSON/multipart parser duplicate-key/collapsed field limitations remain documented; no custom parser. Existing broad locks and per-member writes limit concurrent write scalability; no load/SLA claim. Deferred F05 Audit means no persistent central trail yet; prior intermittent F02 contention-observation caveat remains, no waiver. No real DB, merge, or deployment authorized.


## Final verified application/test checkpoint and release handoff (2026-11-26)

Exact tested source `1ad564969bcf00c45d90846e6029be1f4b31bb73`, [CI 45839858158](https://github.com/m-hassan00096/kids-area-api/actions/runs/45839858158), job 139978347999, actual run head verified against GitHub metadata and job logs. **All required steps succeeded**. Disposable GitHub Actions MySQL 8/InnoDB guard and seven historical migrations; both actual ROLES_LEGACY_FIXTURE and ROLES_FORWARD_MIGRATION completion markers; four alias export/round-trip, eight preserved table fingerprints, two existing authoritative browser sessions, Role Select name/eligibility and Permission alias preservation. Composer locked dependency validation/install/platform/security audit passed (no security vulnerability advisories found), native codecs verified. Focused discovery and tests, full PHPUnit, Pint, route/module/cache and scope checks all passed, including cleanup. No skipped or waived required check.

| Command / gate | Tests | Assertions | Result |
| --- | ---: | ---: | --- |
| php artisan test --filter=Authentication | 41 | 1,588 | Passed |
| php artisan test --filter=Identity | 56 | 1,007 | Passed |
| php artisan test --filter='Users&#124;Avatar&#124;SelectRegistry' | 75 | 7,193 | Passed |
| php artisan test --filter=Roles | 35 | 7,049 | Passed |
| php artisan test | 206 | 16,610 | Passed |
| vendor/bin/pint --test -v | 184 files | — | Passed |
| module:list / route:list / route:cache / route:list / route:clear | 19 routes before and after cache; only Identity | — | Passed |

T41–T43 verified on exact tested source; T01–T43 complete; all F04-01–F04-12 criteria verified on the authorized isolated environment (F04-11 synthetic only, not real-data recovery proof). Static scope review against develop 92f8845a5cb15526965448eab93735902936d612: 69 changed files before this documentation-only checkpoint, identical composer.json/lock and IdentityPermissions catalogue blobs, no altered historical migration, sole new migration removes roles.alias_name forward-only, exact existing nine tables including migrations, ten existing permission keys, five additional Role routes, no new package/table/index/module. Previous F01–F03 behavior preserved except approved name-only Role payload/Select labels. Prior Pint-only failures now fully resolved. This documentation-only checkpoint does not change application, test, workflow, migration, configuration or dependency files. F04 measured/security/acceptance evidence above remains valid for the unchanged application/test tree.

### Repeated performance evidence on the green source

| Roles / Users | List HTTP ms | Nonempty direct service ms | Detail HTTP ms | Three Selects HTTP ms |
| --- | ---: | ---: | ---: | ---: |
| 10 / 100 | 13.509–34.537 | 2.861–5.658 | 14.945–20.944 | 16.617–19.857 |
| 100 / 1,000 | 13.557–35.522 | 3.291–8.546 | 15.539–18.509 | 17.957–21.127 |
| 200 / 10,000 | 13.344–31.728 | 3.184–19.407 | 22.369–91.246 | 20.729–92.787 |

60 list samples, six detail, six Select and six disable samples; another 222 actual EXPLAIN + ANALYZE pairs. Four/one domain SELECTs and 12/9 total HTTP SQL statements nonempty/empty maintained. The latest run is independently measured, not a claim that the previous timing samples are bounds or a p95/p99.

| Affected Users | HTTP ms | SQL statements | Manager lock-to-commit ack ms | Commit ms | PHP peak MiB |
| --- | ---: | ---: | ---: | ---: | ---: |
| 1 | 64.484–66.974 | 47 | 36.057–37.791 | 0.784–0.806 | 4 |
| 100 | 172.726–180.968 | 245 | 144.911–155.097 | 0.966–12.379 | 4 |
| 1,000 | 992.487–1005.478 | 2045 | 964.838–977.174 | 3.183–6.221 | 6 |

Latest 1,000-member disable is approximately one second, not guaranteed below one second. Removed 1,999 bindings, one actual native browser and 1,998 synthetic bindings, 1,103 total Users in this separate fixture. Linux worker RSS 113080–113460 KiB at 1,000 affected (around 110 MiB); PHP allocator peak is not total process RSS. Manager wait 0.144–0.154ms in this uncontended case. Preserve current reviewed single-location V1 design; per-member writes, broad access-row locks and the measured ~0.965–0.977s held mutex are explicit scaling watchpoints. No speculative index, queue, Redis or architectural change. CI measures not host capacity or production SLA.

### T44 and final exact-head gate

The living handoff, spec/plan/tasks and module reference are updated together in this documentation-only checkpoint, and [PR #12](https://github.com/m-hassan00096/kids-area-api/pull/12) carries the actual tested-source evidence and review limitations. **43/44 tasks verified at publication; T44's documentation deliverable is complete, but its exact final documentation-head CI / Ready for Review condition remains pending until verified.** The last gate is intentionally evidenced by the live [PR checks](https://github.com/m-hassan00096/kids-area-api/pull/12/checks) and PR review status rather than another status-only commit that would recursively trigger CI. T44 is verified only when the current documentation-only head's complete green workflow and Ready for Review transition have been inspected and recorded in PR #12. The checkbox below/above is the pre-gate checkpoint, not a substitute for that live evidence. Do not push another commit simply to record the success of a status-only CI; if any required check fails, keep Draft and repair it. Separate explicit user merge approval is mandatory after readiness, and merge to develop does not authorize deployment.

Remaining deployment limitations (not blockers to code review): prior intermittent F02 contention-observation caveat, deferred F05 Audit (no persistent central sensitive-operation trail), real host filesystem/TLS/cookie/proxy/native GD/Argon2id and memory/capacity checks, no actual production restore proof. Before real DROP, separately authorize real-data inspection and decide legacy name/alias disposition, verify backup and compatible code/database restore, coordinate approved breaking nested Role aliasName removal/role Select label changes with clients. The down migration intentionally refuses invented alias recovery. D01/D04 delegation capabilities may assign approved grants the actor does not hold, so grant store/update deliberately. No real DB, merge or deployment performed.
